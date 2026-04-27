#!/usr/bin/env python3
import argparse
import json
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlparse
from urllib.request import Request, urlopen


DEFAULT_BASE_URL = "https://clickfix.carsonww.com/api/search"

DEFAULT_HEADERS = {
    "accept": "*/*",
    "accept-language": "es-ES,es;q=0.6",
    "content-type": "application/json",
    "sec-ch-ua": '"Not:A-Brand";v="99", "Brave";v="145", "Chromium";v="145"',
    "sec-ch-ua-mobile": "?0",
    "sec-ch-ua-platform": '"Windows"',
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "sec-gpc": "1",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36",
    "referer": "https://clickfix.carsonww.com/domains?limit=50",
}


def build_url(base_url: str, q: str, page: int, limit: int) -> str:
    params = {"q": q, "page": page, "limit": limit}
    return f"{base_url}?{urlencode(params)}"


def decode_response(data: bytes, encoding: str | None) -> str:
    if encoding:
        return data.decode(encoding, errors="replace")
    return data.decode("utf-8", errors="replace")


def fetch_json(url: str, headers: dict[str, str], timeout: int) -> tuple[int, Any, str]:
    request = Request(url, headers=headers, method="GET")
    try:
        with urlopen(request, timeout=timeout) as response:
            status = response.getcode()
            body = response.read()
            text = decode_response(body, response.headers.get_content_charset())
            payload = json.loads(text)
            return status, payload, text
    except HTTPError as exc:
        body = exc.read()
        text = decode_response(body, exc.headers.get_content_charset() if exc.headers else None)
        return exc.code, None, text
    except URLError as exc:
        return 0, None, str(exc)
    except json.JSONDecodeError:
        return 200, None, ""


def extract_items(payload: Any) -> list[Any]:
    if isinstance(payload, list):
        return payload
    if not isinstance(payload, dict):
        return []
    for key in ("items", "data", "results", "rows", "domains", "records", "entries"):
        value = payload.get(key)
        if isinstance(value, list):
            return value
        if isinstance(value, dict):
            nested = extract_items(value)
            if nested:
                return nested
    for value in payload.values():
        if isinstance(value, list):
            return value
    for value in payload.values():
        if isinstance(value, dict):
            nested = extract_items(value)
            if nested:
                return nested
    return []


def payload_has_error(payload: Any) -> bool:
    if not isinstance(payload, dict):
        return False
    if payload.get("error"):
        return True
    if payload.get("errors"):
        return True
    status = str(payload.get("status", "")).strip().lower()
    if status in {"error", "failed", "bad", "invalid"}:
        return True
    return False


def normalize_candidate(value: str) -> tuple[str, str]:
    value = value.strip()
    if not value:
        return "", ""
    if "://" in value:
        parsed = urlparse(value)
        host = parsed.netloc or parsed.path
        host = host.split("/")[0].strip()
        return host, value
    if "/" in value:
        host = value.split("/")[0].strip()
        return host, value
    return value, ""


def extract_domain_url(item: Any) -> tuple[str, str]:
    if isinstance(item, dict):
        for key in ("domain", "host", "hostname", "url", "link", "href", "site", "website"):
            raw = item.get(key)
            if raw:
                return normalize_candidate(str(raw))
    if isinstance(item, str):
        return normalize_candidate(item)
    return "", ""


def dedupe_key(domain: str, url: str) -> str:
    if domain:
        return f"d:{domain.lower().rstrip('/')}"
    if url:
        return f"u:{url.lower().rstrip('/')}"
    return ""


def build_entry(
    item: Any,
    domain: str,
    url: str,
    page: int,
    fetched_at: str,
    include_raw: bool,
) -> dict[str, Any]:
    entry: dict[str, Any] = {
        "source": "carson_api",
        "page": page,
        "fetched_at": fetched_at,
    }
    if domain:
        entry["domain"] = domain
    if url:
        entry["url"] = url
    if include_raw:
        entry["raw"] = item
    return entry


def write_output(path: Path, items: Iterable[dict[str, Any]]) -> None:
    data = list(items)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Fetch paginated Carson ClickFix results and create a unified JSON list."
    )
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="API base URL")
    parser.add_argument("--q", default=".", help="Search query")
    parser.add_argument("--limit", type=int, default=100, help="Results per page")
    parser.add_argument("--start-page", type=int, default=1, help="Start page number")
    parser.add_argument("--max-pages", type=int, default=500, help="Safety cap on pages")
    parser.add_argument("--sleep", type=float, default=0.25, help="Delay between pages (seconds)")
    parser.add_argument("--timeout", type=int, default=20, help="Request timeout (seconds)")
    parser.add_argument("--output", default="explorar.json", help="Output JSON path")
    parser.add_argument(
        "--include-raw",
        action="store_true",
        help="Include raw API item in each entry.",
    )
    args = parser.parse_args()

    headers = dict(DEFAULT_HEADERS)
    output_path = Path(args.output)
    fetched_at = datetime.now(timezone.utc).isoformat()

    unified: list[dict[str, Any]] = []
    seen: set[str] = set()

    page = args.start_page
    pages_fetched = 0
    while page <= args.start_page + args.max_pages - 1:
        url = build_url(args.base_url, args.q, page, args.limit)
        status, payload, raw_text = fetch_json(url, headers, args.timeout)
        if status != 200:
            print(f"[STOP] Bad response status {status} at page {page}")
            break
        if payload is None:
            print(f"[STOP] Invalid JSON at page {page}")
            break
        if payload_has_error(payload):
            print(f"[STOP] Payload error at page {page}")
            break

        items = extract_items(payload)
        if not items:
            print(f"[STOP] Empty results at page {page}")
            break

        added = 0
        for item in items:
            domain, url_value = extract_domain_url(item)
            key = dedupe_key(domain, url_value)
            if not key or key in seen:
                continue
            entry = build_entry(item, domain, url_value, page, fetched_at, args.include_raw)
            unified.append(entry)
            seen.add(key)
            added += 1

        print(f"[OK] page={page} items={len(items)} added={added}")
        pages_fetched += 1
        page += 1
        if args.sleep > 0:
            time.sleep(args.sleep)

    write_output(output_path, unified)
    print(f"[DONE] pages={pages_fetched} total={len(unified)} output={output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
