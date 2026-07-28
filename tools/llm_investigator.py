#!/usr/bin/env python3
"""Automated ClickFix investigation connector backed by an OpenAI-compatible LLM."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Any


DEFAULT_LLM_BASE_URL = "https://api.openai.com/v1"
DEFAULT_MODEL = "gpt-4.1-mini"
DEFAULT_STATE_FILE = ".clickfix-llm-investigator-state.json"


class ConnectorError(RuntimeError):
    pass


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def json_dumps(data: Any) -> str:
    return json.dumps(data, ensure_ascii=False, separators=(",", ":"))


def load_state(path: Path) -> dict[str, Any]:
    if not path.is_file():
        return {"processed_report_ids": [], "last_since_id": 0}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {"processed_report_ids": [], "last_since_id": 0}
    return data if isinstance(data, dict) else {"processed_report_ids": [], "last_since_id": 0}


def save_state(path: Path, state: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(state, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def http_json(
    method: str,
    url: str,
    headers: dict[str, str] | None = None,
    body: Any | None = None,
    timeout: int = 45,
) -> dict[str, Any]:
    payload = None
    final_headers = {"Accept": "application/json", **(headers or {})}
    if body is not None:
        payload = json_dumps(body).encode("utf-8")
        final_headers["Content-Type"] = "application/json"

    request = urllib.request.Request(url, data=payload, method=method.upper(), headers=final_headers)
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            raw = response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        try:
            error_body = json.loads(raw)
        except json.JSONDecodeError:
            error_body = {"raw": raw[:1000]}
        raise ConnectorError(f"HTTP {exc.code} from {url}: {error_body}") from exc
    except urllib.error.URLError as exc:
        raise ConnectorError(f"Request failed for {url}: {exc.reason}") from exc

    try:
        decoded = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise ConnectorError(f"Non-JSON response from {url}: {raw[:1000]}") from exc
    if not isinstance(decoded, dict):
        raise ConnectorError(f"Unexpected JSON response from {url}")
    return decoded


class ClickFixClient:
    def __init__(self, base_url: str, api_key: str = "", bearer_token: str = "") -> None:
        self.base_url = base_url.rstrip("/")
        self.api_base = self.base_url if self.base_url.endswith("/api") else self.base_url + "/api"
        if not api_key and not bearer_token:
            raise ConnectorError("Set CLICKFIX_API_KEY or CLICKFIX_BEARER_TOKEN.")
        self.headers = {"User-Agent": "ClickFix-LLM-Investigator/1.0"}
        if bearer_token:
            self.headers["Authorization"] = f"Bearer {bearer_token}"
        else:
            self.headers["X-API-Key"] = api_key

    def url(self, path: str, params: dict[str, Any] | None = None) -> str:
        query = urllib.parse.urlencode({k: v for k, v in (params or {}).items() if v is not None})
        return f"{self.api_base}/{path.lstrip('/')}" + (f"?{query}" if query else "")

    def list_alerts(self, limit: int, since_id: int, review_status: str, include_context: bool) -> list[dict[str, Any]]:
        data = http_json(
            "GET",
            self.url(
                "alerts.php",
                {
                    "limit": limit,
                    "since_id": since_id,
                    "review_status": review_status,
                    "include_context": "1" if include_context else "0",
                },
            ),
            self.headers,
        )
        alerts = data.get("alerts", [])
        return alerts if isinstance(alerts, list) else []

    def get_alert(self, report_id: int, include_context: bool = True) -> dict[str, Any]:
        data = http_json(
            "GET",
            self.url("alert.php", {"id": report_id, "include_context": "1" if include_context else "0"}),
            self.headers,
        )
        alert = data.get("alert")
        if not isinstance(alert, dict):
            raise ConnectorError(f"Alert {report_id} was not returned by the API.")
        return alert

    def save_investigation(self, investigation: dict[str, Any]) -> dict[str, Any]:
        return http_json("POST", self.url("investigations.php"), self.headers, investigation)

    def review_alert(self, report_id: int, review_status: str) -> dict[str, Any]:
        return http_json("POST", self.url("review.php"), self.headers, {"report_id": report_id, "review_status": review_status})


class LlmClient:
    def __init__(self, api_key: str, model: str, base_url: str = DEFAULT_LLM_BASE_URL) -> None:
        if not api_key:
            raise ConnectorError("Set LLM_API_KEY.")
        self.api_key = api_key
        self.model = model
        self.base_url = base_url.rstrip("/")

    def analyze(self, alert: dict[str, Any]) -> dict[str, Any]:
        prompt = build_prompt(alert)
        body = {
            "model": self.model,
            "temperature": 0.1,
            "response_format": {"type": "json_object"},
            "messages": [
                {
                    "role": "system",
                    "content": (
                        "You are a defensive SOC analyst for ClickFix social-engineering detections. "
                        "Return compact valid JSON only. Do not invent evidence. If evidence is weak, say so."
                    ),
                },
                {"role": "user", "content": prompt},
            ],
        }
        headers = {"Authorization": f"Bearer {self.api_key}"}
        data = http_json("POST", f"{self.base_url}/chat/completions", headers, body, timeout=90)
        choices = data.get("choices", [])
        if not choices or not isinstance(choices[0], dict):
            raise ConnectorError("LLM response did not include choices.")
        message = choices[0].get("message", {})
        content = message.get("content") if isinstance(message, dict) else ""
        if not isinstance(content, str) or content.strip() == "":
            raise ConnectorError("LLM response content was empty.")
        return parse_llm_json(content)


def build_prompt(alert: dict[str, Any]) -> str:
    compact_alert = {
        "id": first_value(alert, ["id", "report_id"]),
        "received_at": first_value(alert, ["received_at", "created_at", "timestamp"]),
        "url": first_value(alert, ["url", "page_url"]),
        "hostname": first_value(alert, ["hostname", "domain", "site_domain"]),
        "blocked": alert.get("blocked"),
        "event_type": alert.get("event_type"),
        "message": first_value(alert, ["message", "reason", "description"]),
        "client_id": alert.get("client_id"),
        "country": alert.get("country"),
        "review_status": alert.get("review_status"),
        "server_verdict": alert.get("server_verdict"),
        "context": shrink(alert.get("context", alert), 7000),
    }
    schema = {
        "verdict": "malicious|suspicious|clean|unknown|investigating",
        "confidence": 0.0,
        "summary": "short analyst summary",
        "evidence": ["facts observed in the alert/context"],
        "recommended_review_status": "accepted|rejected|allowlisted|pending",
        "recommended_actions": ["manual analyst next steps"],
        "tags": ["clickfix", "llm"],
        "nodes": [{"id": "short_stable_id", "label": "node label", "color": "#5dc8ff", "tags": ["tag"], "notes": "why it matters"}],
        "edges": [{"id": "short_stable_id", "from": "node_id", "to": "node_id", "label": "relationship", "color": "#94a3b8"}],
    }
    return (
        "Analyze this ClickFix alert and produce an investigation graph. "
        "Use the schema exactly and keep nodes/edges concise.\n\n"
        f"Schema:\n{json.dumps(schema, ensure_ascii=False, indent=2)}\n\n"
        f"Alert:\n{json.dumps(compact_alert, ensure_ascii=False, indent=2)}"
    )


def parse_llm_json(content: str) -> dict[str, Any]:
    text = content.strip()
    if text.startswith("```"):
        text = re.sub(r"^```(?:json)?\s*", "", text)
        text = re.sub(r"\s*```$", "", text)
    try:
        decoded = json.loads(text)
    except json.JSONDecodeError:
        match = re.search(r"\{.*\}", text, flags=re.DOTALL)
        if not match:
            raise
        decoded = json.loads(match.group(0))
    if not isinstance(decoded, dict):
        raise ConnectorError("LLM JSON root must be an object.")
    return decoded


def first_value(data: dict[str, Any], keys: list[str]) -> Any:
    for key in keys:
        value = data.get(key)
        if value not in (None, ""):
            return value
    return ""


def shrink(value: Any, max_chars: int) -> Any:
    text = json.dumps(value, ensure_ascii=False, default=str)
    if len(text) <= max_chars:
        return value
    return text[:max_chars] + "...[truncated]"


def stable_id(prefix: str, value: Any) -> str:
    digest = hashlib.sha1(str(value).encode("utf-8", errors="ignore")).hexdigest()[:12]
    return f"{prefix}_{digest}"


def normalize_verdict(value: Any) -> str:
    verdict = str(value or "investigating").lower().strip()
    return verdict if verdict in {"malicious", "suspicious", "clean", "unknown", "investigating"} else "investigating"


def normalize_review_status(value: Any) -> str:
    status = str(value or "pending").lower().strip()
    return status if status in {"accepted", "rejected", "allowlisted", "pending"} else "pending"


def normalize_color(value: Any, fallback: str) -> str:
    color = str(value or "").strip()
    return color if re.fullmatch(r"#[0-9a-fA-F]{6}", color) else fallback


def as_text_list(value: Any, limit: int = 12) -> list[str]:
    if isinstance(value, list):
        return [str(item).strip()[:220] for item in value[:limit] if str(item).strip()]
    if isinstance(value, str) and value.strip():
        return [value.strip()[:220]]
    return []


def build_investigation(alert: dict[str, Any], llm: dict[str, Any]) -> dict[str, Any]:
    report_id = int(first_value(alert, ["id", "report_id"]) or 0)
    domain = str(first_value(alert, ["hostname", "domain", "site_domain"]) or "")
    url = str(first_value(alert, ["url", "page_url"]) or "")
    verdict = normalize_verdict(llm.get("verdict"))
    evidence = as_text_list(llm.get("evidence"), 10)
    actions = as_text_list(llm.get("recommended_actions"), 8)
    tags = ["llm", "automated", "clickfix"]
    tags.extend(as_text_list(llm.get("tags"), 12))
    tags = sorted({tag.lower().replace(",", " ")[:40] for tag in tags if tag})

    alert_node_id = f"alert_{report_id}" if report_id > 0 else stable_id("alert", url or domain or time.time())
    nodes = [
        {
            "id": alert_node_id,
            "label": f"Alert {report_id}" if report_id > 0 else "ClickFix alert",
            "color": "#ff8fab" if verdict in {"malicious", "suspicious"} else "#58d68d",
            "x": 80,
            "y": 120,
            "tags": ["alert", "clickfix", verdict],
            "notes": str(first_value(alert, ["message", "reason", "description"]) or "")[:400],
        }
    ]
    edges: list[dict[str, Any]] = []

    if domain:
        domain_node = stable_id("domain", domain)
        nodes.append({"id": domain_node, "label": domain, "color": "#58d68d", "x": 330, "y": 80, "tags": ["domain"], "notes": "Alert hostname"})
        edges.append({"id": stable_id("edge", alert_node_id + domain_node), "from": alert_node_id, "to": domain_node, "label": "reported host", "color": "#94a3b8"})
    if url and url != domain:
        url_node = stable_id("url", url)
        nodes.append({"id": url_node, "label": url[:120], "color": "#5dc8ff", "x": 330, "y": 210, "tags": ["url"], "notes": url})
        edges.append({"id": stable_id("edge", alert_node_id + url_node), "from": alert_node_id, "to": url_node, "label": "observed url", "color": "#94a3b8"})

    for index, item in enumerate(evidence[:6], start=1):
        evidence_id = stable_id("evidence", f"{report_id}:{index}:{item}")
        nodes.append({"id": evidence_id, "label": item[:90], "color": "#ffd166", "x": 600, "y": 60 + index * 80, "tags": ["evidence", "llm"], "notes": item})
        edges.append({"id": stable_id("edge", alert_node_id + evidence_id), "from": alert_node_id, "to": evidence_id, "label": "supports", "color": "#4ad7d1"})

    for raw_node in llm.get("nodes", []) if isinstance(llm.get("nodes"), list) else []:
        if not isinstance(raw_node, dict):
            continue
        label = str(raw_node.get("label") or "").strip()
        if not label:
            continue
        node_id = str(raw_node.get("id") or stable_id("llm", label))[:64]
        if any(node["id"] == node_id for node in nodes):
            continue
        nodes.append(
            {
                "id": node_id,
                "label": label[:120],
                "color": normalize_color(raw_node.get("color"), "#b794f4"),
                "x": raw_node.get("x", 820),
                "y": raw_node.get("y", 120 + len(nodes) * 34),
                "tags": as_text_list(raw_node.get("tags"), 8),
                "notes": str(raw_node.get("notes") or "")[:400],
            }
        )

    node_ids = {str(node["id"]) for node in nodes}
    for raw_edge in llm.get("edges", []) if isinstance(llm.get("edges"), list) else []:
        if not isinstance(raw_edge, dict):
            continue
        from_id = str(raw_edge.get("from") or "")
        to_id = str(raw_edge.get("to") or "")
        if from_id not in node_ids or to_id not in node_ids or from_id == to_id:
            continue
        edges.append(
            {
                "id": str(raw_edge.get("id") or stable_id("edge", from_id + to_id + str(raw_edge.get("label", ""))))[:64],
                "from": from_id,
                "to": to_id,
                "label": str(raw_edge.get("label") or "related")[:120],
                "color": normalize_color(raw_edge.get("color"), "#94a3b8"),
            }
        )

    summary_parts = [str(llm.get("summary") or "").strip()]
    confidence = llm.get("confidence")
    if confidence not in (None, ""):
        summary_parts.append(f"LLM confidence: {confidence}")
    if evidence:
        summary_parts.append("Evidence:\n- " + "\n- ".join(evidence))
    if actions:
        summary_parts.append("Recommended actions:\n- " + "\n- ".join(actions))
    summary = "\n\n".join(part for part in summary_parts if part).strip()

    return {
        "title": f"LLM investigation: {domain or url or ('alert ' + str(report_id))}",
        "site_domain": domain,
        "verdict": verdict,
        "summary": summary[:5000],
        "tags": ", ".join(tags),
        "source_report_id": report_id if report_id > 0 else None,
        "graph": {"nodes": nodes[:80], "edges": edges[:120]},
        "_recommended_review_status": normalize_review_status(llm.get("recommended_review_status")),
    }


def should_skip(alert: dict[str, Any], processed: set[int]) -> bool:
    report_id = int(first_value(alert, ["id", "report_id"]) or 0)
    return report_id > 0 and report_id in processed


def process_alerts(args: argparse.Namespace) -> int:
    client = ClickFixClient(env("CLICKFIX_BASE_URL"), env("CLICKFIX_API_KEY"), env("CLICKFIX_BEARER_TOKEN"))
    llm_client = LlmClient(env("LLM_API_KEY"), env("LLM_MODEL", DEFAULT_MODEL), env("LLM_BASE_URL", DEFAULT_LLM_BASE_URL))
    state_path = Path(args.state_file)
    state = load_state(state_path)
    processed = {int(item) for item in state.get("processed_report_ids", []) if str(item).isdigit()}
    since_id = args.since_id if args.since_id >= 0 else int(state.get("last_since_id", 0) or 0)

    alerts = client.list_alerts(args.limit, since_id, args.review_status, include_context=False)
    wrote = 0
    for alert_ref in alerts:
        report_id = int(first_value(alert_ref, ["id", "report_id"]) or 0)
        if report_id <= 0 or should_skip(alert_ref, processed):
            continue
        alert = client.get_alert(report_id, include_context=True)
        llm_result = llm_client.analyze(alert)
        investigation = build_investigation(alert, llm_result)
        review_status = investigation.pop("_recommended_review_status", "pending")

        print(json.dumps({"report_id": report_id, "verdict": investigation["verdict"], "review_status": review_status}, ensure_ascii=False))
        if not args.dry_run:
            saved = client.save_investigation(investigation)
            print(json.dumps({"report_id": report_id, "graph_id": saved.get("graph_id")}, ensure_ascii=False))
            if args.apply_review and review_status != "pending":
                client.review_alert(report_id, review_status)
            processed.add(report_id)
            wrote += 1
            state["processed_report_ids"] = sorted(processed)[-5000:]
            state["last_since_id"] = max(int(state.get("last_since_id", 0) or 0), report_id)
            save_state(state_path, state)
        if args.sleep > 0:
            time.sleep(args.sleep)
    return wrote


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run automated ClickFix alert investigations with an LLM.")
    parser.add_argument("--limit", type=int, default=int(env("CLICKFIX_LLM_LIMIT", "10")), help="Maximum alerts to fetch per run.")
    parser.add_argument("--since-id", type=int, default=-1, help="Override state and only fetch alerts after this report id.")
    parser.add_argument("--review-status", default=env("CLICKFIX_LLM_REVIEW_STATUS", "pending"), help="ClickFix review_status filter.")
    parser.add_argument("--state-file", default=env("CLICKFIX_LLM_STATE_FILE", DEFAULT_STATE_FILE), help="Local state JSON path.")
    parser.add_argument("--sleep", type=float, default=float(env("CLICKFIX_LLM_SLEEP", "0")), help="Delay between alerts.")
    parser.add_argument("--dry-run", action="store_true", help="Analyze alerts but do not write investigations or reviews.")
    parser.add_argument("--apply-review", action="store_true", help="Apply LLM recommended review status when not pending.")
    return parser.parse_args(argv)


def main(argv: list[str]) -> int:
    args = parse_args(argv)
    if not env("CLICKFIX_BASE_URL"):
        raise ConnectorError("Set CLICKFIX_BASE_URL, for example https://clickfix.example.com")
    wrote = process_alerts(args)
    print(json.dumps({"status": "ok", "written_investigations": wrote, "dry_run": args.dry_run}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main(sys.argv[1:]))
    except ConnectorError as exc:
        print(json.dumps({"status": "error", "message": str(exc)}, ensure_ascii=False), file=sys.stderr)
        raise SystemExit(1)
