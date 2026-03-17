import json
import socket
import subprocess
from pathlib import Path
from typing import Dict, List

import psutil


def _safe_run(command: List[str], timeout: int = 5) -> str:
    try:
        completed = subprocess.run(
            command,
            capture_output=True,
            text=True,
            timeout=timeout,
            check=False,
            encoding="utf-8",
            errors="replace",
        )
        return (completed.stdout or "").strip()
    except Exception:
        return ""


def collect_process_snapshot(limit: int = 12) -> List[Dict[str, object]]:
    entries: List[Dict[str, object]] = []
    for process in psutil.process_iter(["pid", "name", "cmdline", "memory_info"]):
        try:
            info = process.info
            memory = int(getattr(info.get("memory_info"), "rss", 0) or 0)
            cmdline = " ".join(info.get("cmdline") or [])[:280]
            entries.append(
                {
                    "pid": int(info.get("pid") or 0),
                    "name": str(info.get("name") or "unknown"),
                    "cmdline": cmdline,
                    "memory_mb": round(memory / (1024 * 1024), 2),
                }
            )
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            continue
    entries.sort(key=lambda item: float(item.get("memory_mb") or 0.0), reverse=True)
    return entries[: max(1, limit)]


def collect_dns_snapshot(limit: int = 18) -> Dict[str, object]:
    dns_config = _safe_run(["ipconfig", "/all"], timeout=5)
    dns_cache = _safe_run(["ipconfig", "/displaydns"], timeout=6)

    servers: List[str] = []
    if dns_config:
        for raw_line in dns_config.splitlines():
            line = raw_line.strip()
            if not line:
                continue
            if "DNS Servers" in line:
                value = line.split(":", 1)[-1].strip()
                if value:
                    servers.append(value)
                continue
            if servers and raw_line.startswith(" " * 10):
                value = line.strip()
                if value:
                    servers.append(value)
                continue
            if servers and not raw_line.startswith(" " * 10):
                break

    recent_records: List[str] = []
    if dns_cache:
        for raw_line in dns_cache.splitlines():
            line = raw_line.strip()
            if line.lower().startswith("record name"):
                value = line.split(":", 1)[-1].strip()
                if value and value not in recent_records:
                    recent_records.append(value)
                if len(recent_records) >= limit:
                    break

    return {
        "servers": servers[:6],
        "recent_records": recent_records[:limit],
    }


def collect_antivirus_status() -> List[Dict[str, object]]:
    powershell = (
        "Get-CimInstance -Namespace root/SecurityCenter2 -ClassName AntiVirusProduct "
        "| Select-Object displayName,productState,pathToSignedProductExe "
        "| ConvertTo-Json -Compress"
    )
    raw = _safe_run(["powershell", "-NoProfile", "-Command", powershell], timeout=6)
    if not raw:
        return []
    try:
        decoded = json.loads(raw)
    except Exception:
        return []
    rows = decoded if isinstance(decoded, list) else [decoded]
    output: List[Dict[str, object]] = []
    for row in rows:
        if not isinstance(row, dict):
            continue
        output.append(
            {
                "display_name": str(row.get("displayName") or "unknown"),
                "product_state": str(row.get("productState") or ""),
                "path": str(row.get("pathToSignedProductExe") or ""),
            }
        )
    return output


def collect_host_snapshot(process_limit: int = 12) -> Dict[str, object]:
    return {
        "hostname": socket.gethostname(),
        "dns": collect_dns_snapshot(),
        "antivirus": collect_antivirus_status(),
        "processes": collect_process_snapshot(limit=process_limit),
        "boot_time": int(psutil.boot_time()),
        "cpu_percent": psutil.cpu_percent(interval=0.2),
        "memory_percent": round(float(psutil.virtual_memory().percent), 2),
    }

