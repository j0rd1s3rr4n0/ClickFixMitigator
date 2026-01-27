#!/usr/bin/env python3
import argparse
import json
from pathlib import Path
from typing import Iterable
from urllib.parse import urlparse


def normalize_for_compare(value: str) -> str:
    value = value.strip()
    if not value:
        return ""
    if "://" in value:
        parsed = urlparse(value)
        host = parsed.netloc or parsed.path
        return host.strip().rstrip("/").lower()
    return value.strip().rstrip("/").lower()


def pick_entry(item: dict) -> str | None:
    domain = str(item.get("domain") or "").strip()
    if domain:
        return domain
    url = str(item.get("url") or "").strip()
    if not url:
        return None
    if "://" in url:
        parsed = urlparse(url)
        if parsed.netloc:
            return parsed.netloc
    return url


def load_existing(path: Path) -> tuple[list[str], set[str]]:
    if not path.exists():
        return [], set()
    lines = [line.strip() for line in path.read_text(encoding="utf-8").splitlines()]
    ordered = [line for line in lines if line and not line.startswith("#")]
    normalized = {normalize_for_compare(line) for line in ordered if normalize_for_compare(line)}
    return ordered, normalized


def load_entries(path: Path) -> list[str]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, list):
        raise ValueError("explorar.json must be a list of objects")
    entries: list[str] = []
    for item in data:
        if not isinstance(item, dict):
            continue
        entry = pick_entry(item)
        if entry:
            entries.append(entry)
    return entries


def append_new_lines(path: Path, lines: Iterable[str]) -> int:
    new_lines = list(lines)
    if not new_lines:
        return 0
    if path.exists():
        existing_text = path.read_text(encoding="utf-8")
        needs_newline = existing_text and not existing_text.endswith("\n")
    else:
        needs_newline = False
    with path.open("a", encoding="utf-8", newline="\n") as handle:
        if needs_newline:
            handle.write("\n")
        for line in new_lines:
            handle.write(line + "\n")
    return len(new_lines)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Append domains/urls from explorar.json into urls.txt without removing existing lines."
    )
    parser.add_argument("--input", default="explorar.json", help="Path to explorar.json")
    parser.add_argument("--output", default="urls.txt", help="Path to urls.txt")
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Only show how many new lines would be appended.",
    )
    args = parser.parse_args()

    input_path = Path(args.input)
    output_path = Path(args.output)

    existing_lines, existing_norm = load_existing(output_path)
    entries = load_entries(input_path)

    to_append: list[str] = []
    seen_new: set[str] = set()
    for entry in entries:
        norm = normalize_for_compare(entry)
        if not norm or norm in existing_norm or norm in seen_new:
            continue
        to_append.append(entry)
        seen_new.add(norm)

    if args.dry_run:
        print(f"[DRY RUN] New lines to append: {len(to_append)}")
        return 0

    appended = append_new_lines(output_path, to_append)
    print(f"[OK] Appended {appended} new lines to {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
