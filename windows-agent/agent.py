import ctypes
from ctypes import wintypes
import json
import logging
import logging.handlers
import os
import re
import sqlite3
import socket
import ssl
import sys
import threading
import time
import urllib.error
import urllib.request
import uuid
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Dict, Optional, Callable, Iterable, Tuple

import psutil
import win32gui
import win32process
import keyboard
import pystray
from PIL import Image, ImageDraw

from consent_dialog import ensure_agent_terms_acceptance
from control_panel import AgentControlPanel
from host_telemetry import collect_host_snapshot


# ---- Build/version marker (to confirm you're running the right file) ----
AGENT_VERSION = "agent-2026-05-18-paste-intercept"

BASE_DIR = Path(sys.executable).resolve().parent if getattr(sys, "frozen", False) else Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.json"
LOG_PATH = BASE_DIR / "agent-debug.log"
EVENT_LOG_PATH = BASE_DIR / "agent.log"
ALERTSITES_PATH = BASE_DIR / "alertsites"
DEFAULT_DB_PATH = BASE_DIR / "data" / "clickfix.sqlite"
CLIPBOARD_CF_UNICODETEXT = 13
TRAY_ICON_PATH = BASE_DIR / "logo.png"
TERMS_PATH = BASE_DIR / "TERMS_AND_CONDITIONS.txt"
MESSAGEBOX_YES = 6
MESSAGEBOX_NO = 7
ERROR_ALREADY_EXISTS = 183
INSTANCE_MUTEX_NAME = "Global\\ClickFixMitigatorAgent"
INSTANCE_MUTEX_HANDLE = None

ctypes.windll.user32.OpenClipboard.argtypes = [wintypes.HWND]
ctypes.windll.user32.OpenClipboard.restype = wintypes.BOOL
ctypes.windll.user32.CloseClipboard.argtypes = []
ctypes.windll.user32.CloseClipboard.restype = wintypes.BOOL
ctypes.windll.user32.IsClipboardFormatAvailable.argtypes = [wintypes.UINT]
ctypes.windll.user32.IsClipboardFormatAvailable.restype = wintypes.BOOL
ctypes.windll.user32.GetClipboardData.argtypes = [wintypes.UINT]
ctypes.windll.user32.GetClipboardData.restype = wintypes.HANDLE
ctypes.windll.user32.EmptyClipboard.argtypes = []
ctypes.windll.user32.EmptyClipboard.restype = wintypes.BOOL
ctypes.windll.user32.SetClipboardData.argtypes = [wintypes.UINT, wintypes.HANDLE]
ctypes.windll.user32.SetClipboardData.restype = wintypes.HANDLE
ctypes.windll.kernel32.GlobalAlloc.argtypes = [wintypes.UINT, ctypes.c_size_t]
ctypes.windll.kernel32.GlobalAlloc.restype = wintypes.HGLOBAL
ctypes.windll.kernel32.GlobalLock.argtypes = [wintypes.HGLOBAL]
ctypes.windll.kernel32.GlobalLock.restype = wintypes.LPVOID
ctypes.windll.kernel32.GlobalUnlock.argtypes = [wintypes.HGLOBAL]
ctypes.windll.kernel32.GlobalUnlock.restype = wintypes.BOOL
ctypes.windll.kernel32.GlobalFree.argtypes = [wintypes.HGLOBAL]
ctypes.windll.kernel32.GlobalFree.restype = wintypes.HGLOBAL

USER_PROFILE_PRESETS: Dict[str, Dict[str, Dict[str, object]]] = {
    "balanced": {
        "sensitivity": {
            "clipboard_poll_interval_s": 0.5,
            "run_sequence_timeout_s": 8.0,
            "allow_timeout_s": 15.0,
            "min_clipboard_length": 8,
        },
        "ui": {
            "show_panel_on_start": True,
            "open_panel_on_alert": True,
            "use_system_notifications": False,
            "close_to_tray": True,
        },
        "remote": {
            "include_host_snapshot": False,
        },
    },
    "strict": {
        "sensitivity": {
            "clipboard_poll_interval_s": 0.35,
            "run_sequence_timeout_s": 10.0,
            "allow_timeout_s": 8.0,
            "min_clipboard_length": 6,
        },
        "ui": {
            "show_panel_on_start": True,
            "open_panel_on_alert": True,
            "use_system_notifications": True,
            "close_to_tray": True,
        },
        "remote": {
            "include_host_snapshot": False,
        },
    },
    "quiet": {
        "sensitivity": {
            "clipboard_poll_interval_s": 0.75,
            "run_sequence_timeout_s": 8.0,
            "allow_timeout_s": 20.0,
            "min_clipboard_length": 12,
        },
        "ui": {
            "show_panel_on_start": False,
            "open_panel_on_alert": False,
            "use_system_notifications": False,
            "close_to_tray": True,
        },
        "remote": {
            "include_host_snapshot": False,
        },
    },
    "analyst": {
        "sensitivity": {
            "clipboard_poll_interval_s": 0.35,
            "run_sequence_timeout_s": 12.0,
            "allow_timeout_s": 30.0,
            "min_clipboard_length": 4,
        },
        "ui": {
            "show_panel_on_start": True,
            "open_panel_on_alert": True,
            "use_system_notifications": True,
            "close_to_tray": False,
        },
        "remote": {
            "include_host_snapshot": True,
        },
    },
}

DB_SCHEMA_SQL = """
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    user_agent TEXT,
    ip TEXT,
    country TEXT,
    active_process TEXT,
    active_window TEXT,
    action_taken TEXT,
    host_snapshot_json TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT,
    host_health TEXT
);

CREATE TABLE IF NOT EXISTS host_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recorded_at TEXT NOT NULL,
    hostname TEXT,
    cpu_percent REAL,
    memory_percent REAL,
    dns_json TEXT,
    antivirus_json TEXT,
    processes_json TEXT
);
"""


@dataclass
class AlertContext:
    reason: str
    text: str
    active_window: str
    active_process: str
    action: str


def utc_now_iso() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())


def normalize_client_id(raw: str) -> str:
    value = re.sub(r"[^a-z0-9_-]+", "", (raw or "").strip().lower())
    if 8 <= len(value) <= 80:
        return value
    return ""


def normalize_user_profile(raw: object) -> str:
    value = str(raw or "").strip().lower()
    if value in USER_PROFILE_PRESETS:
        return value
    return "balanced"


def safe_truncate(text: str, limit: int) -> str:
    if limit <= 0:
        return ""
    if len(text) <= limit:
        return text
    if limit <= 3:
        return text[:limit]
    return text[: max(0, limit - 3)] + "..."


class TelemetryStore:
    def __init__(self, base_dir: Path, config: Dict[str, object]) -> None:
        telemetry = config.get("telemetry", {})
        if not isinstance(telemetry, dict):
            telemetry = {}

        self.enable_db = bool(telemetry.get("enable_local_db", True))
        self.db_path = Path(telemetry.get("db_path", DEFAULT_DB_PATH))
        if not self.db_path.is_absolute():
            self.db_path = (base_dir / self.db_path).resolve()

        self.log_json = bool(telemetry.get("log_json", True))
        self.log_path = Path(telemetry.get("log_path", EVENT_LOG_PATH))
        if not self.log_path.is_absolute():
            self.log_path = (base_dir / self.log_path).resolve()

        self.alertsites_path = Path(telemetry.get("alertsites_path", ALERTSITES_PATH))
        if not self.alertsites_path.is_absolute():
            self.alertsites_path = (base_dir / self.alertsites_path).resolve()

        self.stats_flush_interval = float(telemetry.get("stats_flush_interval_s", 30))
        self.hostname = socket.gethostname()
        self.user_agent = f"windows-agent/{AGENT_VERSION}"
        self.client_id = normalize_client_id(str(telemetry.get("client_id", "")))
        if self.client_id == "":
            self.client_id = "wa-" + uuid.uuid4().hex[:24]
            telemetry["client_id"] = self.client_id
            config["telemetry"] = telemetry
            try:
                CONFIG_PATH.write_text(json.dumps(config, indent=2, ensure_ascii=False), encoding="utf-8")
            except Exception:
                logging.exception("Failed to persist generated client_id")

        remote = config.get("remote", {})
        if not isinstance(remote, dict):
            remote = {}
        self.remote_enabled = bool(remote.get("enabled", False))
        self.remote_base_url = str(remote.get("base_url", "")).strip().rstrip("/")
        self.remote_report_endpoint = str(remote.get("report_endpoint", "clickfix-report.php")).strip().lstrip("/")
        self.remote_stats_endpoint = str(remote.get("stats_endpoint", "clickfix-report.php")).strip().lstrip("/")
        self.remote_bearer_token = str(remote.get("bearer_token", "")).strip()
        self.remote_api_key = str(remote.get("api_key", "")).strip()
        self.remote_timeout_s = max(2.0, min(30.0, float(remote.get("timeout_s", 8.0))))
        self.remote_verify_tls = bool(remote.get("verify_tls", True))
        self.remote_include_host_snapshot = bool(remote.get("include_host_snapshot", False))

        self.alert_count = 0
        self.block_count = 0
        self.last_stats_flush = 0.0

        self.alertsites_cache = self._load_alertsites()
        self.last_host_snapshot: Dict[str, object] = {}
        self._db_lock = threading.Lock()

        self._ensure_db()

    def _ensure_db(self) -> None:
        if not self.enable_db:
            return
        try:
            self.db_path.parent.mkdir(parents=True, exist_ok=True)
            with sqlite3.connect(self.db_path, timeout=3) as conn:
                conn.executescript(DB_SCHEMA_SQL)
                self._ensure_column(conn, "reports", "active_process", "TEXT")
                self._ensure_column(conn, "reports", "active_window", "TEXT")
                self._ensure_column(conn, "reports", "action_taken", "TEXT")
                self._ensure_column(conn, "reports", "host_snapshot_json", "TEXT")
                self._ensure_column(conn, "stats", "host_health", "TEXT")
        except Exception:
            logging.exception("Failed to ensure local SQLite schema at %s", self.db_path)

    def _ensure_column(self, conn: sqlite3.Connection, table: str, column: str, column_type: str) -> None:
        rows = conn.execute(f"PRAGMA table_info({table})").fetchall()
        existing = {str(row[1]) for row in rows}
        if column in existing:
            return
        conn.execute(f"ALTER TABLE {table} ADD COLUMN {column} {column_type}")

    def _load_alertsites(self) -> set:
        if not self.alertsites_path.exists():
            return set()
        try:
            lines = self.alertsites_path.read_text(encoding="utf-8").splitlines()
        except Exception:
            logging.exception("Failed to read alertsites file")
            return set()
        items = set()
        for line in lines:
            value = line.strip()
            if value and not value.startswith("#"):
                items.add(value)
        return items

    def add_alert_site(self, value: str) -> None:
        value = value.strip()
        if not value or value in self.alertsites_cache:
            return
        self.alertsites_cache.add(value)
        try:
            with self.alertsites_path.open("a", encoding="utf-8") as handle:
                handle.write(value + "\n")
        except Exception:
            logging.exception("Failed to write alertsites")

    def log_event(self, event_type: str, payload: Dict[str, object]) -> None:
        if not self.log_json:
            return
        entry = {"event": event_type, "ts": utc_now_iso(), "version": AGENT_VERSION}
        entry.update(payload)
        try:
            self.log_path.parent.mkdir(parents=True, exist_ok=True)
            with self.log_path.open("a", encoding="utf-8") as handle:
                handle.write(json.dumps(entry, ensure_ascii=False) + "\n")
        except Exception:
            logging.exception("Failed to write JSON event log")

    def remote_status(self) -> Dict[str, object]:
        return {
            "enabled": self.remote_enabled,
            "base_url": self.remote_base_url,
            "auth_mode": "bearer" if self.remote_bearer_token else ("api_key" if self.remote_api_key else "none"),
            "client_id": self.client_id,
            "verify_tls": self.remote_verify_tls,
            "include_host_snapshot": self.remote_include_host_snapshot,
        }

    def _remote_headers(self) -> Dict[str, str]:
        headers = {
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": self.user_agent,
        }
        if self.remote_bearer_token:
            headers["Authorization"] = f"Bearer {self.remote_bearer_token}"
        elif self.remote_api_key:
            headers["X-API-Key"] = self.remote_api_key
        return headers

    def _remote_url(self, endpoint: str) -> str:
        return f"{self.remote_base_url}/{endpoint.lstrip('/')}"

    def _remote_context(self) -> Optional[ssl.SSLContext]:
        if self.remote_verify_tls:
            return None
        return ssl._create_unverified_context()

    def _remote_target_allowed(self) -> bool:
        base = self.remote_base_url.lower()
        if base.startswith("https://"):
            return True
        return base.startswith("http://localhost") or base.startswith("http://127.0.0.1")

    def send_remote_payload(self, endpoint: str, payload: Dict[str, object], event_type: str) -> None:
        if not self.remote_enabled or self.remote_base_url == "":
            return
        if not self.remote_bearer_token and not self.remote_api_key:
            logging.warning("Remote sync enabled but no bearer/api key configured")
            return
        if not self._remote_target_allowed():
            logging.warning("Remote sync refused for non-HTTPS non-local target: %s", self.remote_base_url)
            return
        try:
            data = json.dumps(payload, ensure_ascii=False).encode("utf-8")
            request = urllib.request.Request(
                self._remote_url(endpoint),
                data=data,
                headers=self._remote_headers(),
                method="POST",
            )
            with urllib.request.urlopen(
                request,
                timeout=self.remote_timeout_s,
                context=self._remote_context(),
            ) as response:
                body = response.read(2048).decode("utf-8", errors="replace")
            self.log_event(
                "remote_sync",
                {
                    "target": endpoint,
                    "event_type": event_type,
                    "status": "ok",
                    "http_status": getattr(response, "status", 200),
                    "body_preview": safe_truncate(body, 240),
                },
            )
        except urllib.error.HTTPError as exc:
            body = exc.read(2048).decode("utf-8", errors="replace")
            self.log_event(
                "remote_sync",
                {
                    "target": endpoint,
                    "event_type": event_type,
                    "status": "error",
                    "http_status": exc.code,
                    "body_preview": safe_truncate(body, 240),
                },
            )
            logging.warning("Remote sync HTTP error for %s: %s", endpoint, exc)
        except Exception:
            logging.exception("Remote sync failed for %s", endpoint)

    def record_report(self, record: Dict[str, object]) -> None:
        if not self.enable_db:
            return
        try:
            with self._db_lock, sqlite3.connect(self.db_path, timeout=3) as conn:
                conn.execute(
                    """
                    INSERT INTO reports (
                        received_at, url, hostname, message, detected_content,
                        full_context, signals_json, blocked, user_agent, ip, country,
                        active_process, active_window, action_taken, host_snapshot_json
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        record.get("received_at"),
                        record.get("url"),
                        record.get("hostname"),
                        record.get("message"),
                        record.get("detected_content"),
                        record.get("full_context"),
                        record.get("signals_json"),
                        record.get("blocked"),
                        record.get("user_agent"),
                        record.get("ip"),
                        record.get("country"),
                        record.get("active_process"),
                        record.get("active_window"),
                        record.get("action_taken"),
                        record.get("host_snapshot_json"),
                    ),
                )
        except Exception:
            logging.exception("Failed to write report to SQLite")

    def record_stats(self, force: bool = False, host_health: Optional[str] = None) -> None:
        if not self.enable_db and not self.remote_enabled:
            return
        now = time.time()
        if not force and (now - self.last_stats_flush) < self.stats_flush_interval:
            return
        try:
            ts = utc_now_iso()
            manual_sites_json = json.dumps(sorted(self.alertsites_cache), ensure_ascii=False)
            if self.enable_db:
                with self._db_lock, sqlite3.connect(self.db_path, timeout=3) as conn:
                    conn.execute(
                        """
                        INSERT INTO stats (
                            received_at, enabled, alert_count, block_count, manual_sites_json, country, host_health
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        """,
                        (
                            ts,
                            1,
                            self.alert_count,
                            self.block_count,
                            manual_sites_json,
                            None,
                            host_health,
                        ),
                    )
            self.last_stats_flush = now
            self.send_remote_payload(
                self.remote_stats_endpoint,
                {
                    "type": "stats",
                    "client_id": self.client_id,
                    "timestamp": ts,
                    "data": {
                        "enabled": True,
                        "alertCount": self.alert_count,
                        "blockCount": self.block_count,
                        "manualSites": sorted(self.alertsites_cache),
                        "country": "",
                        "hostHealth": host_health or self.derive_host_health(),
                        "baselineHosts": [],
                    },
                },
                "stats",
            )
        except Exception:
            logging.exception("Failed to write stats to SQLite")

    def record_host_snapshot(self, snapshot: Dict[str, object]) -> None:
        self.last_host_snapshot = snapshot
        if not self.enable_db:
            return
        try:
            with self._db_lock, sqlite3.connect(self.db_path, timeout=3) as conn:
                conn.execute(
                    """
                    INSERT INTO host_snapshots (
                        recorded_at, hostname, cpu_percent, memory_percent, dns_json, antivirus_json, processes_json
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    """,
                    (
                        utc_now_iso(),
                        snapshot.get("hostname"),
                        snapshot.get("cpu_percent"),
                        snapshot.get("memory_percent"),
                        json.dumps(snapshot.get("dns", {}), ensure_ascii=False),
                        json.dumps(snapshot.get("antivirus", []), ensure_ascii=False),
                        json.dumps(snapshot.get("processes", []), ensure_ascii=False),
                    ),
                )
        except Exception:
            logging.exception("Failed to write host snapshot to SQLite")

    def record_detection(
        self,
        *,
        reason: str,
        text: str,
        blocked: bool,
        active_process: str,
        active_window: str,
        signals: Dict[str, object],
    ) -> None:
        self.alert_count += 1
        if blocked:
            self.block_count += 1

        record = {
            "received_at": utc_now_iso(),
            "url": f"process://{active_process}",
            "hostname": self.hostname,
            "message": reason,
            "detected_content": safe_truncate(text, 2000),
            "full_context": json.dumps(
                {
                    "active_process": active_process,
                    "active_window": active_window,
                },
                ensure_ascii=False,
            ),
            "signals_json": json.dumps(signals, ensure_ascii=False),
            "blocked": 1 if blocked else 0,
            "user_agent": self.user_agent,
            "ip": None,
            "country": None,
            "active_process": active_process,
            "active_window": active_window,
            "action_taken": "blocked" if blocked else "allowed",
            "host_snapshot_json": json.dumps(self.last_host_snapshot, ensure_ascii=False),
        }

        self.log_event(
            "detection",
            {
                "reason": reason,
                "blocked": blocked,
                "active_process": active_process,
                "active_window": active_window,
                "signals": signals,
                "text_preview": safe_truncate(text, 240),
            },
        )
        self.record_report(record)
        remote_context = {
            "active_process": active_process,
            "active_window": active_window,
            "action_taken": "blocked" if blocked else "allowed",
        }
        if self.remote_include_host_snapshot:
            remote_context["host_snapshot"] = self.last_host_snapshot
        self.send_remote_payload(
            self.remote_report_endpoint,
            {
                "type": "alert",
                "client_id": self.client_id,
                "event_type": "manual_report",
                "timestamp": record["received_at"],
                "hostname": self.hostname,
                "url": record["url"],
                "message": reason,
                "detectedContent": safe_truncate(text, 2000),
                "full_context": json.dumps(remote_context, ensure_ascii=False),
                "signals": dict(signals),
                "blocked": blocked,
                "manualReport": True,
                "trusted_signal_source": True,
                "score_total": 0,
            },
            "alert",
        )
        self.record_stats(host_health=self.derive_host_health())

    def derive_host_health(self) -> str:
        snapshot = self.last_host_snapshot or {}
        cpu = float(snapshot.get("cpu_percent") or 0.0)
        memory = float(snapshot.get("memory_percent") or 0.0)
        if cpu >= 90 or memory >= 92:
            return "stressed"
        if cpu >= 70 or memory >= 80:
            return "elevated"
        return "healthy"

    def query_rows(self, sql: str, params: Tuple[Any, ...] = ()) -> list[sqlite3.Row]:
        if not self.enable_db:
            return []
        with self._db_lock, sqlite3.connect(self.db_path, timeout=3) as conn:
            conn.row_factory = sqlite3.Row
            return conn.execute(sql, params).fetchall()


class ClipboardMonitor:
    def __init__(self, config: Dict[str, object]) -> None:
        self.config = config
        self.config_lock = threading.Lock()

        rules = config.get("rules", {})
        self.suspicious_patterns = [
            re.compile(p, re.IGNORECASE) for p in rules.get("suspicious_regexes", [])
        ]
        self.exclusions = [re.compile(p, re.IGNORECASE) for p in rules.get("exclusions", [])]
        self.process_allowlist = [
            re.compile(p, re.IGNORECASE) for p in rules.get("process_allowlist", [])
        ]
        self.window_allowlist = [
            re.compile(p, re.IGNORECASE) for p in rules.get("window_allowlist", [])
        ]

        sensitivity = config.get("sensitivity", {})
        self.poll_interval = float(sensitivity.get("clipboard_poll_interval_s", 0.5))
        self.run_sequence_timeout = float(sensitivity.get("run_sequence_timeout_s", 8))
        self.toast_app_id = "ClickFix Mitigator"
        self.blocked_placeholder = str(
            sensitivity.get("blocked_clipboard_placeholder", "[Clipboard bloqueado]")
        )
        self.allow_timeout = float(sensitivity.get("allow_timeout_s", 15))
        self.min_clipboard_length = int(sensitivity.get("min_clipboard_length", 8))
        self.max_clipboard_preview = int(sensitivity.get("max_clipboard_preview", 240))

        self.last_clipboard_text: Optional[str] = None
        self.last_blocked_clipboard: Optional[str] = None
        self.last_blocked_reason: Optional[str] = None
        self.allowed_clipboard_text: Optional[str] = None
        self.allowed_clipboard_until: Optional[float] = None

        self.ignore_next_clipboard_change = False
        self.sequence_started_at: Optional[float] = None
        self.sequence_type: Optional[str] = None
        self.sequence_last_paste: Optional[str] = None
        self.sequence_steps: list[str] = []
        self.paste_hotkey_handles: list[object] = []
        self.replaying_paste_hotkey = False
        self.hotkey_registration_lock = threading.RLock()

        self.running = True
        self.tray_icon: Optional[pystray.Icon] = None
        self.stop_event = threading.Event()
        self.telemetry = TelemetryStore(BASE_DIR, config)
        self.hotkeys = self._load_hotkeys(config)
        telemetry_cfg = config.get("telemetry", {})
        if not isinstance(telemetry_cfg, dict):
            telemetry_cfg = {}
        ui_cfg = config.get("ui", {})
        if not isinstance(ui_cfg, dict):
            ui_cfg = {}
            config["ui"] = ui_cfg
        ui_cfg["user_profile"] = normalize_user_profile(ui_cfg.get("user_profile", "balanced"))
        self.host_snapshot_interval = float(telemetry_cfg.get("host_snapshot_interval_s", 45))
        self.enable_host_telemetry = bool(telemetry_cfg.get("enable_host_telemetry", False))
        self.panel = AgentControlPanel(
            self,
            TRAY_ICON_PATH,
            TERMS_PATH,
            refresh_interval_s=float(ui_cfg.get("refresh_interval_s", 3.0)),
        )
        self.show_panel_on_start = bool(ui_cfg.get("show_panel_on_start", True))
        self.open_panel_on_alert = bool(ui_cfg.get("open_panel_on_alert", True))
        self.use_system_notifications = bool(ui_cfg.get("use_system_notifications", False))
        self.close_to_tray = bool(ui_cfg.get("close_to_tray", True))
        self.enable_panel = bool(ui_cfg.get("enable_panel", False))
        self.enable_tray = bool(ui_cfg.get("enable_tray", False))
        self.enable_keyboard_hooks = bool(ui_cfg.get("enable_keyboard_hooks", False))
        self.last_host_snapshot: Dict[str, object] = {}
        self.last_host_snapshot_lock = threading.Lock()
        self._refresh_runtime_settings()

        logging.debug(
            "ClipboardMonitor initialized. poll_interval=%s allow_timeout=%s run_sequence_timeout=%s",
            self.poll_interval,
            self.allow_timeout,
            self.run_sequence_timeout,
        )

    # ---------------- Clipboard low-level ----------------

    def load_clipboard_text(self) -> Optional[str]:
        if not ctypes.windll.user32.OpenClipboard(0):
            logging.debug("OpenClipboard failed (locked by other process?)")
            return None
        try:
            if not ctypes.windll.user32.IsClipboardFormatAvailable(CLIPBOARD_CF_UNICODETEXT):
                logging.debug("Clipboard format UNICODETEXT not available")
                return None
            handle = ctypes.windll.user32.GetClipboardData(CLIPBOARD_CF_UNICODETEXT)
            if not handle:
                logging.debug("GetClipboardData returned NULL")
                return None
            pointer = ctypes.windll.kernel32.GlobalLock(handle)
            if not pointer:
                logging.debug("GlobalLock returned NULL")
                return None
            try:
                text = ctypes.wstring_at(pointer)
                logging.debug("Clipboard read OK (len=%s)", len(text) if text else 0)
                return text
            finally:
                ctypes.windll.kernel32.GlobalUnlock(handle)
        finally:
            ctypes.windll.user32.CloseClipboard()

    def set_clipboard_text(self, text: str) -> None:
        logging.debug("Setting clipboard text (len=%s)", len(text) if text else 0)
        if not ctypes.windll.user32.OpenClipboard(0):
            logging.warning("OpenClipboard failed on set (locked?)")
            return
        try:
            ctypes.windll.user32.EmptyClipboard()
            data = ctypes.create_unicode_buffer(text)
            size = ctypes.sizeof(data)
            handle = ctypes.windll.kernel32.GlobalAlloc(0x0002, size)
            if not handle:
                logging.error("GlobalAlloc failed")
                return
            locked = ctypes.windll.kernel32.GlobalLock(handle)
            if not locked:
                logging.error("GlobalLock failed (set)")
                ctypes.windll.kernel32.GlobalFree(handle)
                return
            ctypes.memmove(locked, ctypes.addressof(data), size)
            ctypes.windll.kernel32.GlobalUnlock(handle)
            ctypes.windll.user32.SetClipboardData(CLIPBOARD_CF_UNICODETEXT, handle)
            logging.debug("Clipboard set OK")
        finally:
            ctypes.windll.user32.CloseClipboard()

    # ---------------- Active window/process ----------------

    def get_active_window(self) -> str:
        hwnd = win32gui.GetForegroundWindow()
        if hwnd == 0:
            return "Unknown window"
        title = win32gui.GetWindowText(hwnd)
        return title or "Untitled window"

    def get_active_process(self) -> str:
        hwnd = win32gui.GetForegroundWindow()
        if hwnd == 0:
            return "Unknown process"
        _, pid = win32process.GetWindowThreadProcessId(hwnd)
        try:
            process = psutil.Process(pid)
            return process.name()
        except (psutil.NoSuchProcess, psutil.AccessDenied) as e:
            logging.debug("get_active_process failed pid=%s err=%s", pid, e)
            return f"PID {pid}"

    # ---------------- Matching / rules ----------------

    def is_excluded(self, text: str) -> bool:
        for pattern in self.exclusions:
            if pattern.search(text):
                logging.debug("Excluded by pattern: %s", pattern.pattern)
                return True
        return False

    def match_suspicious(self, text: str) -> Optional[str]:
        for pattern in self.suspicious_patterns:
            if pattern.search(text):
                logging.debug("Suspicious match by pattern: %s", pattern.pattern)
                return pattern.pattern
        return None

    def is_allowed_context(self, active_process: str, active_window: str) -> bool:
        for pattern in self.process_allowlist:
            if pattern.search(active_process):
                logging.debug("Allowed process matched: %s", pattern.pattern)
                return True
        for pattern in self.window_allowlist:
            if pattern.search(active_window):
                logging.debug("Allowed window matched: %s", pattern.pattern)
                return True
        return False

    def capture_context(self) -> Tuple[str, str]:
        return self.get_active_window(), self.get_active_process()

    def _load_hotkeys(self, config: Dict[str, object]) -> Dict[str, list[str]]:
        hotkeys = config.get("hotkeys", {})
        if not isinstance(hotkeys, dict):
            hotkeys = {}

        def normalize(value: object, fallback: Iterable[str]) -> list[str]:
            if isinstance(value, list) and value:
                return [str(v) for v in value if str(v).strip()]
            if isinstance(value, str) and value.strip():
                return [value.strip()]
            return list(fallback)

        return {
            "run_dialog": normalize(hotkeys.get("run_dialog"), ["windows+r", "win+r"]),
            "explorer": normalize(hotkeys.get("explorer"), ["windows+e", "win+e"]),
            "address_bar": normalize(hotkeys.get("address_bar"), ["alt+l", "alt+d", "ctrl+l"]),
            "paste": normalize(hotkeys.get("paste"), ["ctrl+v", "shift+insert", "ctrl+shift+v"]),
            "execute": normalize(hotkeys.get("execute"), ["enter", "ctrl+shift+enter"]),
            "restore": normalize(hotkeys.get("restore"), ["ctrl+shift+u"]),
        }

    def list_user_profiles(self) -> Dict[str, Dict[str, object]]:
        return json.loads(json.dumps(USER_PROFILE_PRESETS))

    def apply_user_profile(self, profile: object, persist: bool = True) -> str:
        normalized = normalize_user_profile(profile)
        preset = USER_PROFILE_PRESETS.get(normalized, USER_PROFILE_PRESETS["balanced"])
        with self.config_lock:
            sensitivity = self.config.setdefault("sensitivity", {})
            if not isinstance(sensitivity, dict):
                sensitivity = {}
                self.config["sensitivity"] = sensitivity
            ui = self.config.setdefault("ui", {})
            if not isinstance(ui, dict):
                ui = {}
                self.config["ui"] = ui
            remote = self.config.setdefault("remote", {})
            if not isinstance(remote, dict):
                remote = {}
                self.config["remote"] = remote
            sensitivity.update(preset.get("sensitivity", {}))
            ui.update(preset.get("ui", {}))
            ui["user_profile"] = normalized
            for key, value in preset.get("remote", {}).items():
                remote[key] = value
            if persist:
                CONFIG_PATH.write_text(json.dumps(self.config, indent=2, ensure_ascii=False), encoding="utf-8")
        self._refresh_runtime_settings()
        return normalized

    def _refresh_runtime_settings(self) -> None:
        self.poll_interval = float(self.config.get("sensitivity", {}).get("clipboard_poll_interval_s", self.poll_interval))
        self.run_sequence_timeout = float(self.config.get("sensitivity", {}).get("run_sequence_timeout_s", self.run_sequence_timeout))
        self.allow_timeout = float(self.config.get("sensitivity", {}).get("allow_timeout_s", self.allow_timeout))
        self.min_clipboard_length = int(self.config.get("sensitivity", {}).get("min_clipboard_length", self.min_clipboard_length))
        self.blocked_placeholder = str(
            self.config.get("sensitivity", {}).get("blocked_clipboard_placeholder", self.blocked_placeholder)
        )
        self.show_panel_on_start = bool(self.config.get("ui", {}).get("show_panel_on_start", self.show_panel_on_start))
        self.open_panel_on_alert = bool(self.config.get("ui", {}).get("open_panel_on_alert", self.open_panel_on_alert))
        self.use_system_notifications = bool(self.config.get("ui", {}).get("use_system_notifications", self.use_system_notifications))
        self.close_to_tray = bool(self.config.get("ui", {}).get("close_to_tray", self.close_to_tray))
        self.telemetry.remote_enabled = bool(self.config.get("remote", {}).get("enabled", self.telemetry.remote_enabled))
        self.telemetry.remote_base_url = str(self.config.get("remote", {}).get("base_url", self.telemetry.remote_base_url)).strip().rstrip("/")
        self.telemetry.remote_report_endpoint = str(self.config.get("remote", {}).get("report_endpoint", self.telemetry.remote_report_endpoint)).strip().lstrip("/")
        self.telemetry.remote_stats_endpoint = str(self.config.get("remote", {}).get("stats_endpoint", self.telemetry.remote_stats_endpoint)).strip().lstrip("/")
        self.telemetry.remote_bearer_token = str(self.config.get("remote", {}).get("bearer_token", self.telemetry.remote_bearer_token)).strip()
        self.telemetry.remote_api_key = str(self.config.get("remote", {}).get("api_key", self.telemetry.remote_api_key)).strip()
        self.telemetry.remote_timeout_s = max(2.0, min(30.0, float(self.config.get("remote", {}).get("timeout_s", self.telemetry.remote_timeout_s))))
        self.telemetry.remote_verify_tls = bool(self.config.get("remote", {}).get("verify_tls", self.telemetry.remote_verify_tls))
        self.telemetry.remote_include_host_snapshot = bool(self.config.get("remote", {}).get("include_host_snapshot", self.telemetry.remote_include_host_snapshot))

    def set_host_snapshot(self, snapshot: Dict[str, object]) -> None:
        with self.last_host_snapshot_lock:
            self.last_host_snapshot = snapshot
        self.telemetry.record_host_snapshot(snapshot)

    def get_host_snapshot(self) -> Dict[str, object]:
        with self.last_host_snapshot_lock:
            return dict(self.last_host_snapshot)

    def open_panel(self, _=None) -> None:
        if not self.enable_panel:
            logging.info("Control panel is disabled in config")
            return
        self.panel.open()

    def get_ui_snapshot(self) -> Dict[str, object]:
        host_snapshot = self.get_host_snapshot()
        recent_rows = self.telemetry.query_rows(
            """
            SELECT received_at, message, detected_content, blocked, active_process,
                   active_window, action_taken, host_snapshot_json, signals_json
            FROM reports
            ORDER BY id DESC
            LIMIT 50
            """
        )
        recent_alerts = []
        for row in recent_rows:
            signals = {}
            host_context = {}
            try:
                signals = json.loads(str(row["signals_json"] or "")) if row["signals_json"] else {}
            except Exception:
                signals = {}
            try:
                host_context = (
                    json.loads(str(row["host_snapshot_json"] or "")) if row["host_snapshot_json"] else {}
                )
            except Exception:
                host_context = {}
            recent_alerts.append(
                {
                    "received_at": row["received_at"],
                    "message": row["message"],
                    "detected_content": row["detected_content"],
                    "blocked": int(row["blocked"] or 0),
                    "active_process": row["active_process"],
                    "active_window": row["active_window"],
                    "action_taken": row["action_taken"] or ("blocked" if int(row["blocked"] or 0) else "allowed"),
                    "signals": signals,
                    "host_context": host_context,
                }
            )

        stats_rows = self.telemetry.query_rows(
            """
            SELECT received_at, alert_count, block_count
            FROM stats
            ORDER BY id DESC
            LIMIT 14
            """
        )
        trend = []
        for row in reversed(stats_rows):
            label = str(row["received_at"] or "")[11:16] or str(row["received_at"] or "")[:10]
            trend.append(
                {
                    "label": label,
                    "alerts": int(row["alert_count"] or 0),
                    "blocks": int(row["block_count"] or 0),
                }
            )
        counts = {
            "total_alerts": self.telemetry.alert_count,
            "total_blocks": self.telemetry.block_count,
            "recent_count": len(recent_alerts[:10]),
        }
        return {
            "version": AGENT_VERSION,
            "counts": counts,
            "host_health": self.telemetry.derive_host_health(),
            "host_snapshot": host_snapshot,
            "recent_alerts": recent_alerts,
            "trend": trend,
            "remote": self.telemetry.remote_status(),
            "settings": json.loads(json.dumps(self.config)),
        }

    def save_settings(self, updates: Dict[str, str]) -> None:
        numeric_float = {"clipboard_poll_interval_s", "run_sequence_timeout_s", "allow_timeout_s"}
        numeric_int = {"min_clipboard_length"}
        boolean_keys = {"show_panel_on_start", "open_panel_on_alert", "use_system_notifications", "close_to_tray"}
        remote_boolean_keys = {"remote_enabled", "remote_verify_tls", "remote_include_host_snapshot"}
        remote_text_keys = {"remote_base_url", "remote_report_endpoint", "remote_stats_endpoint", "remote_bearer_token", "remote_api_key", "remote_client_id"}
        remote_numeric_float = {"remote_timeout_s"}
        with self.config_lock:
            sensitivity = self.config.setdefault("sensitivity", {})
            if not isinstance(sensitivity, dict):
                sensitivity = {}
                self.config["sensitivity"] = sensitivity
            ui = self.config.setdefault("ui", {})
            if not isinstance(ui, dict):
                ui = {}
                self.config["ui"] = ui
            telemetry = self.config.setdefault("telemetry", {})
            if not isinstance(telemetry, dict):
                telemetry = {}
                self.config["telemetry"] = telemetry
            remote = self.config.setdefault("remote", {})
            if not isinstance(remote, dict):
                remote = {}
                self.config["remote"] = remote
            requested_profile = None
            for key, raw_value in updates.items():
                if key == "user_profile":
                    requested_profile = normalize_user_profile(raw_value)
                    continue
                if key in boolean_keys:
                    ui[key] = str(raw_value).strip() in {"1", "true", "yes", "on"}
                    continue
                if key in remote_boolean_keys:
                    remote[key.replace("remote_", "")] = str(raw_value).strip() in {"1", "true", "yes", "on"}
                    continue
                if key in remote_numeric_float:
                    try:
                        remote[key.replace("remote_", "")] = float(raw_value)
                    except Exception:
                        continue
                    continue
                if key in remote_text_keys:
                    next_value = str(raw_value).strip()
                    if key == "remote_client_id":
                        normalized = normalize_client_id(next_value)
                        if normalized:
                            telemetry["client_id"] = normalized
                            self.telemetry.client_id = normalized
                        continue
                    remote[key.replace("remote_", "")] = next_value
                    continue
                if key not in sensitivity and key != "blocked_clipboard_placeholder":
                    continue
                if key in numeric_float:
                    try:
                        sensitivity[key] = float(raw_value)
                    except Exception:
                        continue
                elif key in numeric_int:
                    try:
                        sensitivity[key] = int(raw_value)
                    except Exception:
                        continue
                else:
                    sensitivity[key] = raw_value
            if requested_profile:
                ui["user_profile"] = requested_profile
            CONFIG_PATH.write_text(json.dumps(self.config, indent=2, ensure_ascii=False), encoding="utf-8")
        self._refresh_runtime_settings()
        self.telemetry.log_event("settings_update", {"updated_keys": sorted(updates.keys())})

    # ---------------- Alerts / UI ----------------

    def raise_alert(self, context: AlertContext) -> None:
        preview = safe_truncate(context.text, self.max_clipboard_preview)
        payload = {
            "received_at": utc_now_iso(),
            "reason": context.reason,
            "action": context.action,
            "process": context.active_process,
            "window": context.active_window,
            "preview": preview,
        }
        message = (
            f"Motivo: {context.reason}\n"
            f"Accion: {context.action}\n"
            f"Proceso: {context.active_process}\n"
            f"Ventana: {context.active_window}\n"
            f"Texto: {preview}"
        )
        logging.info(
            "ALERT: %s | action=%s | proc=%s | win=%s | text_preview=%r",
            context.reason,
            context.action,
            context.active_process,
            context.active_window,
            preview,
        )

        if self.enable_panel:
            try:
                self.panel.push_alert(payload)
                if self.open_panel_on_alert:
                    self.panel.open()
            except Exception:
                logging.exception("Failed to push alert into control panel")
        else:
            logging.debug("Control panel disabled; alert kept in telemetry only")

        if self.use_system_notifications:
            try:
                from winotify import Notification

                notification = Notification(
                    app_id=self.toast_app_id,
                    title="ClickFix Mitigator",
                    msg=message,
                    duration="short",
                )
                notification.show()
                logging.debug("Toast notification shown")
            except Exception:
                logging.exception("Failed to show toast notification; falling back to print")
                print("[ALERTA]", message)
        else:
            print("[ALERTA]", message)

    def prompt_user_decision(self, reason: str, text: str) -> bool:
        message = (
            "Se detecto un comando sospechoso.\n\n"
            f"Motivo: {reason}\n"
            f"Texto: {safe_truncate(text, self.max_clipboard_preview)}\n\n"
            "Permitir este comando?"
        )
        logging.debug("Prompting user decision. reason=%s text_preview=%r", reason, text[:200])

        response = ctypes.windll.user32.MessageBoxW(
            0,
            message,
            "ClickFix Mitigator",
            0x00000004 | 0x00000030 | 0x00001000,
        )
        allowed = response == MESSAGEBOX_YES
        logging.info("User decision: %s", "ALLOW" if allowed else "BLOCK")
        return allowed

    # ---------------- Allow / quarantine / restore ----------------

    def allow_clipboard_temporarily(self, text: str) -> None:
        self.allowed_clipboard_text = text
        self.allowed_clipboard_until = time.time() + self.allow_timeout
        self.ignore_next_clipboard_change = True
        logging.info("Temporarily allowing clipboard for %ss", self.allow_timeout)
        self.set_clipboard_text(text)

    def is_temporarily_allowed(self, text: str) -> bool:
        if not self.allowed_clipboard_text or not self.allowed_clipboard_until:
            return False
        if time.time() > self.allowed_clipboard_until:
            logging.debug("Temporary allow expired")
            self.allowed_clipboard_text = None
            self.allowed_clipboard_until = None
            return False
        return text == self.allowed_clipboard_text

    def quarantine_clipboard(
        self,
        text: str,
        reason: str,
        signals: Optional[Dict[str, object]] = None,
        close_run_dialog: bool = False,
    ) -> bool:
        logging.warning(
            "Quarantine triggered. reason=%s close_run_dialog=%s text_preview=%r",
            reason,
            close_run_dialog,
            text[:200],
        )

        active_window, active_process = self.capture_context()
        if self.is_allowed_context(active_process, active_window):
            logging.debug("Context allowed; skipping quarantine")
            return True

        self.last_blocked_clipboard = text
        self.last_blocked_reason = reason

        self.ignore_next_clipboard_change = True
        self.set_clipboard_text(self.blocked_placeholder)

        allow = self.prompt_user_decision(reason, text)
        if allow:
            self.allow_clipboard_temporarily(text)
            self.telemetry.record_detection(
                reason=reason,
                text=text,
                blocked=False,
                active_process=active_process,
                active_window=active_window,
                signals=signals or {},
            )
            self.raise_alert(
                AlertContext(
                    reason=reason,
                    text=text,
                    active_window=active_window,
                    active_process=active_process,
                    action="Permitido",
                )
            )
            return True

        if close_run_dialog:
            try:
                keyboard.send("esc")
                logging.debug("Sent ESC to close run dialog")
            except Exception:
                logging.exception("Failed to send ESC")

        self.telemetry.record_detection(
            reason=reason,
            text=text,
            blocked=True,
            active_process=active_process,
            active_window=active_window,
            signals=signals or {},
        )
        self.telemetry.add_alert_site(active_process)
        self.raise_alert(
            AlertContext(
                reason=f"{reason}. Clipboard reemplazado; puedes restaurarlo desde la bandeja.",
                text=text,
                active_window=active_window,
                active_process=active_process,
                action="Bloqueado",
            )
        )
        return False

    def restore_last_clipboard(self, _=None) -> None:
        if not self.last_blocked_clipboard:
            logging.info("Restore requested but no blocked clipboard available")
            return

        logging.info("Restoring last blocked clipboard. reason_was=%s", self.last_blocked_reason)
        self.ignore_next_clipboard_change = True
        self.set_clipboard_text(self.last_blocked_clipboard)

        restored = self.last_blocked_clipboard
        reason_was = self.last_blocked_reason
        self.last_blocked_clipboard = None
        self.last_blocked_reason = None

        active_window, active_process = self.capture_context()
        self.telemetry.log_event(
            "clipboard_restore",
            {
                "active_process": active_process,
                "active_window": active_window,
                "reason": reason_was,
                "text_preview": safe_truncate(restored, self.max_clipboard_preview),
            },
        )

        self.raise_alert(
            AlertContext(
                reason="Clipboard restaurado por el usuario",
                text=restored,
                active_window=active_window,
                active_process=active_process,
                action="Restaurado",
            )
        )

    # ---------------- Event handlers ----------------

    def handle_clipboard_change(self, text: str) -> None:
        logging.debug("Clipboard changed (len=%s)", len(text) if text else 0)

        if self.is_excluded(text):
            logging.debug("Clipboard text excluded; ignoring")
            return
        if self.is_temporarily_allowed(text):
            logging.debug("Clipboard text temporarily allowed; ignoring")
            return
        if len(text.strip()) < self.min_clipboard_length:
            logging.debug("Clipboard text shorter than min length; ignoring")
            return

        self.last_clipboard_text = text
        active_window, active_process = self.capture_context()
        if self.is_allowed_context(active_process, active_window):
            logging.debug("Context allowed; ignoring clipboard change")
            return

        match = self.match_suspicious(text)
        if match:
            self.quarantine_clipboard(
                text,
                f"Texto copiado coincide con regla '{match}'",
                signals={
                    "source": "clipboard_change",
                    "match": match,
                    "sequence": None,
                },
            )
        else:
            logging.debug("Clipboard text not suspicious")

    def sequence_active(self) -> bool:
        if self.sequence_started_at is None:
            return False
        active = (time.time() - self.sequence_started_at) <= self.run_sequence_timeout
        logging.debug("sequence_active=%s type=%s", active, self.sequence_type)
        return active

    def start_sequence(self, sequence_type: str, trigger: str) -> None:
        self.sequence_started_at = time.time()
        self.sequence_type = sequence_type
        self.sequence_last_paste = None
        self.sequence_steps = [trigger]
        logging.info("Sequence started type=%s trigger=%s", sequence_type, trigger)

    def record_sequence_step(self, step: str) -> None:
        if self.sequence_steps:
            self.sequence_steps.append(step)

    def _inspect_clipboard_for_paste(self, source: str) -> Tuple[bool, str, Optional[str]]:
        text = self.load_clipboard_text() or ""
        logging.info("%s paste inspection (len=%s)", source, len(text) if text else 0)

        if not text:
            return True, text, None
        if self.is_excluded(text):
            logging.debug("%s paste text excluded; allowing paste", source)
            return True, text, None
        if self.is_temporarily_allowed(text):
            logging.debug("%s paste text temporarily allowed; allowing paste", source)
            return True, text, None
        if len(text.strip()) < self.min_clipboard_length:
            logging.debug("%s paste text shorter than min length; allowing paste", source)
            return True, text, None

        match = self.match_suspicious(text)
        return match is None, text, match

    def _register_paste_hotkeys(self) -> None:
        with self.hotkey_registration_lock:
            if self.paste_hotkey_handles:
                return
            for key in self.hotkeys["paste"]:
                handle = keyboard.add_hotkey(
                    key,
                    lambda k=key: self.handle_sequence_paste(k),
                    suppress=True,
                )
                self.paste_hotkey_handles.append(handle)

    def _unregister_paste_hotkeys(self) -> None:
        with self.hotkey_registration_lock:
            handles = list(self.paste_hotkey_handles)
            self.paste_hotkey_handles = []
        for handle in handles:
            try:
                keyboard.remove_hotkey(handle)
            except Exception:
                logging.exception("Failed to remove paste hotkey handle")

    def replay_paste_hotkey(self, method: str) -> None:
        if self.replaying_paste_hotkey:
            return
        self.replaying_paste_hotkey = True
        try:
            self._unregister_paste_hotkeys()
            keyboard.send(method)
        except Exception:
            logging.exception("Failed to replay paste hotkey: %s", method)
        finally:
            self._register_paste_hotkeys()
            self.replaying_paste_hotkey = False

    def preflight_sequence_clipboard(self, sequence_type: str, trigger: str) -> None:
        allowed, text, match = self._inspect_clipboard_for_paste(f"{sequence_type}:{trigger}")
        if allowed or not match:
            return
        self.quarantine_clipboard(
            text,
            f"Clipboard sospechoso antes de {trigger} con regla '{match}'",
            signals={
                "source": "sequence_preflight",
                "sequence": sequence_type,
                "steps": list(self.sequence_steps),
                "match": match,
            },
            close_run_dialog=sequence_type == "run",
        )

    def handle_run_hotkey(self) -> None:
        self.start_sequence("run", "win+r")
        self.preflight_sequence_clipboard("run", "win+r")

    def handle_explorer_hotkey(self) -> None:
        self.start_sequence("explorer", "win+e")
        self.preflight_sequence_clipboard("explorer", "win+e")

    def handle_address_hotkey(self, key: str) -> None:
        self.start_sequence("address", key)
        self.preflight_sequence_clipboard("address", key)

    def _handle_sequence_paste_legacy(self, method: str) -> None:
        if not self.sequence_active():
            logging.debug("Paste ignored (no active sequence)")
            return

        self.sequence_last_paste = method
        self.record_sequence_step(method)
        text = self.load_clipboard_text() or ""
        logging.info("Paste detected during sequence (len=%s)", len(text) if text else 0)

        if not text or self.is_excluded(text) or self.is_temporarily_allowed(text):
            logging.debug("Paste text empty/excluded/allowed; ignoring")
            return
        if len(text.strip()) < self.min_clipboard_length:
            logging.debug("Paste text shorter than min length; ignoring")
            return

        match = self.match_suspicious(text)
        if match:
            seq_label = self.sequence_type or "secuencia"
            self.quarantine_clipboard(
                text,
                f"Patrón {seq_label} + pegar ({method}) con regla '{match}'",
                signals={
                    "source": "sequence_paste",
                    "sequence": self.sequence_type,
                    "steps": list(self.sequence_steps),
                    "match": match,
                },
                close_run_dialog=self.sequence_type == "run",
            )

    def handle_sequence_paste(self, method: str) -> None:
        if self.replaying_paste_hotkey:
            return

        sequence_was_active = self.sequence_active()
        allowed, text, match = self._inspect_clipboard_for_paste(
            self.sequence_type or "direct"
        )

        if not sequence_was_active:
            if allowed or not match:
                self.replay_paste_hotkey(method)
                return
            user_allowed = self.quarantine_clipboard(
                text,
                f"Intento de pegar texto sospechoso con regla '{match}'",
                signals={
                    "source": "paste_hotkey",
                    "sequence": None,
                    "steps": [method],
                    "match": match,
                },
            )
            if user_allowed:
                self.replay_paste_hotkey(method)
            return

        self.sequence_last_paste = method
        self.record_sequence_step(method)
        if allowed or not match:
            self.replay_paste_hotkey(method)
            return

        seq_label = self.sequence_type or "secuencia"
        user_allowed = self.quarantine_clipboard(
            text,
            f"Patron {seq_label} + pegar ({method}) con regla '{match}'",
            signals={
                "source": "sequence_paste",
                "sequence": self.sequence_type,
                "steps": list(self.sequence_steps),
                "match": match,
            },
            close_run_dialog=self.sequence_type == "run",
        )
        if user_allowed:
            self.replay_paste_hotkey(method)

    def handle_sequence_execute(self, method: str) -> None:
        if not self.sequence_active():
            logging.debug("Execute ignored (no active sequence). method=%s", method)
            return

        text = self.load_clipboard_text() or ""
        logging.info(
            "Execute detected during sequence. method=%s len=%s",
            method,
            len(text) if text else 0,
        )

        if not text or self.is_excluded(text):
            logging.debug("Execute: no text or excluded; clearing sequence")
            self.sequence_started_at = None
            self.sequence_type = None
            self.sequence_steps = []
            return
        if len(text.strip()) < self.min_clipboard_length:
            logging.debug("Execute: text shorter than min length; clearing sequence")
            self.sequence_started_at = None
            self.sequence_type = None
            self.sequence_steps = []
            return

        if self.is_temporarily_allowed(text):
            logging.debug("Execute: temporarily allowed; clearing sequence")
            self.sequence_started_at = None
            self.sequence_type = None
            self.sequence_steps = []
            return

        match = self.match_suspicious(text)
        if match:
            seq_label = self.sequence_type or "secuencia"
            paste_note = self.sequence_last_paste or "pegado (click derecho o desconocido)"
            self.quarantine_clipboard(
                text,
                f"Patrón {seq_label} + {paste_note} + {method} con regla '{match}'",
                signals={
                    "source": "sequence_execute",
                    "sequence": self.sequence_type,
                    "steps": list(self.sequence_steps),
                    "match": match,
                },
                close_run_dialog=self.sequence_type == "run",
            )

        self.sequence_started_at = None
        self.sequence_type = None
        self.sequence_steps = []

    # ---------------- Monitors ----------------

    def monitor_clipboard(self) -> None:
        logging.info("monitor_clipboard started")
        while self.running:
            try:
                text = self.load_clipboard_text()
                if text and text != self.last_clipboard_text:
                    if self.ignore_next_clipboard_change:
                        logging.debug("Ignoring next clipboard change (self-induced)")
                        self.ignore_next_clipboard_change = False
                        self.last_clipboard_text = text
                    else:
                        self.handle_clipboard_change(text)
            except Exception:
                logging.exception("monitor_clipboard loop error")
            time.sleep(self.poll_interval)
        logging.info("monitor_clipboard stopped")

    def monitor_paste_hotkey(self) -> None:
        logging.info("monitor_paste_hotkey started (registering hotkeys)")
        try:
            for key in self.hotkeys["run_dialog"]:
                keyboard.add_hotkey(key, self.handle_run_hotkey)
            for key in self.hotkeys["explorer"]:
                keyboard.add_hotkey(key, self.handle_explorer_hotkey)
            for key in self.hotkeys["address_bar"]:
                keyboard.add_hotkey(key, lambda k=key: self.handle_address_hotkey(k))
            self._register_paste_hotkeys()
            for key in self.hotkeys["execute"]:
                keyboard.add_hotkey(key, lambda k=key: self.handle_sequence_execute(k))
            for key in self.hotkeys["restore"]:
                keyboard.add_hotkey(key, self.restore_last_clipboard)

            logging.info("Hotkeys registered: %s", self.hotkeys)
        except Exception:
            logging.exception("Failed registering hotkeys")

        while self.running:
            time.sleep(0.5)

    def monitor_host_telemetry(self) -> None:
        logging.info("monitor_host_telemetry started")
        while self.running:
            try:
                snapshot = collect_host_snapshot(process_limit=14)
                self.set_host_snapshot(snapshot)
                self.telemetry.record_stats(host_health=self.telemetry.derive_host_health())
            except Exception:
                logging.exception("monitor_host_telemetry loop error")
            sleep_for = max(10.0, self.host_snapshot_interval)
            for _ in range(int(sleep_for * 2)):
                if not self.running:
                    break
                time.sleep(0.5)
        logging.info("monitor_host_telemetry stopped")

    # ---------------- Tray icon ----------------

    def create_tray_icon(self) -> pystray.Icon:
        image = self.load_tray_image()
        menu = pystray.Menu(
            pystray.MenuItem("Open panel", self.open_panel),
            pystray.MenuItem("Restore last blocked clipboard", self.restore_last_clipboard),
            pystray.MenuItem("Exit", self.stop),
        )
        return pystray.Icon("ClickFixMitigator", image, "ClickFix Mitigator", menu)

    def load_tray_image(self) -> Image.Image:
        if TRAY_ICON_PATH.exists():
            logging.debug("Loading tray icon from %s", TRAY_ICON_PATH)
            return Image.open(TRAY_ICON_PATH).convert("RGBA")

        logging.debug("Tray icon file not found; generating fallback icon")
        size = 64
        image = Image.new("RGBA", (size, size), color=(30, 30, 30, 255))
        draw = ImageDraw.Draw(image)
        draw.rectangle((12, 12, size - 12, size - 12), outline=(0, 180, 255, 255), width=4)
        draw.line((20, size // 2, size // 2, size - 20), fill=(0, 180, 255, 255), width=4)
        draw.line((size // 2, size - 20, size - 20, 20), fill=(0, 180, 255, 255), width=4)
        return image

    def run_tray_icon(self) -> None:
        logging.info("Starting tray icon (pystray)")
        self.tray_icon = self.create_tray_icon()
        self.tray_icon.run()
        logging.warning("Tray icon returned (this is unusual on success)")

    # ---------------- Lifecycle ----------------

    def stop(self, _=None) -> None:
        logging.info("Stop requested")
        self.running = False
        self.stop_event.set()
        try:
            keyboard.clear_all_hotkeys()
            keyboard.unhook_all()
            logging.debug("Keyboard hooks cleared")
        except Exception:
            logging.exception("Failed to clear keyboard hooks")
        try:
            self.telemetry.record_stats(force=True, host_health=self.telemetry.derive_host_health())
        except Exception:
            logging.exception("Failed to flush telemetry stats")
        try:
            if self.enable_panel:
                self.panel.stop()
        except Exception:
            logging.exception("Failed to stop control panel")
        try:
            if self.tray_icon:
                self.tray_icon.stop()
                logging.debug("Tray icon stop invoked")
        except Exception:
            logging.exception("Failed to stop tray icon")

    def run(self) -> None:
        logging.info("ClipboardMonitor run() starting")
        try:
            self.set_host_snapshot(collect_host_snapshot(process_limit=14))
        except Exception:
            logging.exception("Initial host snapshot failed")
        if self.enable_panel:
            self.panel.start(show_on_start=self.show_panel_on_start)
        else:
            logging.info("Control panel disabled; running monitor-only mode")
        self.telemetry.record_stats(force=True, host_health=self.telemetry.derive_host_health())
        self.telemetry.log_event("agent_start", {"pid": os.getpid()})

        # Start everything under watchdogs (including tray).
        threads = [
            threading.Thread(
                target=self._run_thread_watchdog,
                args=(self.monitor_clipboard, "monitor_clipboard"),
                name="monitor_clipboard_watchdog",
                daemon=False,
            ),
        ]
        if self.enable_host_telemetry:
            threads.append(
                threading.Thread(
                    target=self._run_thread_watchdog,
                    args=(self.monitor_host_telemetry, "monitor_host_telemetry"),
                    name="monitor_host_telemetry_watchdog",
                    daemon=False,
                )
            )
        else:
            logging.info("Host telemetry disabled; clipboard monitor remains active")
        if self.enable_keyboard_hooks:
            threads.append(
                threading.Thread(
                    target=self._run_thread_watchdog,
                    args=(self.monitor_paste_hotkey, "monitor_paste_hotkey"),
                    name="monitor_paste_hotkey_watchdog",
                    daemon=False,
                )
            )
        else:
            logging.warning("Keyboard hooks disabled; monitoring clipboard content without global hotkey interception")
        if self.enable_tray:
            threads.append(
                threading.Thread(
                    target=self._run_thread_watchdog,
                    args=(self.run_tray_icon, "run_tray_icon"),
                    name="tray_watchdog",
                    daemon=False,
                )
            )
        else:
            logging.info("Tray icon disabled; running without system tray")

        for t in threads:
            logging.debug("Starting thread %s", t.name)
            t.start()

        # Keep main alive no matter what.
        try:
            logging.info("Main keepalive loop started")
            print("ClickFix Mitigator monitorizando continuamente. Pulsa Ctrl+C o usa Exit en la bandeja para parar.", flush=True)
            while self.running:
                self.stop_event.wait(1)
        except KeyboardInterrupt:
            logging.info("KeyboardInterrupt; stopping")
            self.stop()

        logging.info("Joining threads")
        for t in threads:
            try:
                t.join(timeout=5)
            except Exception:
                logging.exception("Failed joining thread %s", t.name)

        logging.info("ClipboardMonitor run() finished")

    def _run_thread_watchdog(self, target: Callable[[], None], name: str) -> None:
        backoff = 1
        while self.running:
            try:
                logging.info("Starting worker: %s", name)
                target()
                logging.warning("Worker %s exited normally; restarting in 1s", name)
                time.sleep(1)
                backoff = 1
            except Exception:
                logging.exception("Worker crashed: %s; restarting in %ss", name, backoff)
                time.sleep(backoff)
                backoff = min(backoff * 2, 30)


def load_config() -> Dict[str, object]:
    with CONFIG_PATH.open("r", encoding="utf-8") as handle:
        cfg = json.load(handle)
    logging.debug("Config loaded from %s", CONFIG_PATH)
    return cfg


def setup_logging(config: Optional[Dict[str, object]] = None) -> None:
    level = logging.DEBUG
    if config:
        try:
            lvl = str(config.get("logging", {}).get("level", "DEBUG")).upper()
            level = getattr(logging, lvl, logging.DEBUG)
        except Exception:
            level = logging.DEBUG

    logger = logging.getLogger()
    logger.setLevel(level)

    # Remove previous handlers to avoid duplicates
    if logger.handlers:
        for h in list(logger.handlers):
            logger.removeHandler(h)

    fmt = logging.Formatter("%(asctime)s %(levelname)s [%(threadName)s] %(message)s")

    file_handler = logging.handlers.RotatingFileHandler(
        LOG_PATH,
        maxBytes=2 * 1024 * 1024,
        backupCount=3,
        encoding="utf-8",
    )
    file_handler.setLevel(level)
    file_handler.setFormatter(fmt)

    console_handler = logging.StreamHandler()
    console_handler.setLevel(level)
    console_handler.setFormatter(fmt)

    logger.addHandler(file_handler)
    logger.addHandler(console_handler)


def acquire_single_instance_lock() -> bool:
    global INSTANCE_MUTEX_HANDLE
    kernel32 = ctypes.windll.kernel32
    handle = kernel32.CreateMutexW(None, False, INSTANCE_MUTEX_NAME)
    last_error = kernel32.GetLastError()
    if not handle:
        return True
    INSTANCE_MUTEX_HANDLE = handle
    return last_error != ERROR_ALREADY_EXISTS


def main() -> None:
    # These prints are intentional: to prove which file is running.
    import os
    import sys
    print("RUNNING:", os.path.abspath(__file__))
    print("PYTHON :", sys.executable)
    print("VERSION:", AGENT_VERSION)

    # Load config first; if it fails, still log to console.
    try:
        config = load_config()
    except Exception:
        setup_logging(None)
        logging.exception("Failed to load config from %s", CONFIG_PATH)
        raise

    setup_logging(config)

    if not acquire_single_instance_lock():
        logging.warning("Another ClickFix Mitigator agent instance is already running")
        print("ClickFix Mitigator ya esta monitorizando en otra instancia. Revisa la bandeja del sistema.")
        return

    logging.info("Starting ClickFix Mitigator agent")
    print("ClickFix Mitigator iniciado. Revisa la bandeja del sistema.")

    if not ensure_agent_terms_acceptance(config, BASE_DIR):
        logging.warning("Agent terms were not accepted; exiting")
        print("Windows Agent terms were not accepted. Exiting.")
        return

    monitor = ClipboardMonitor(config)
    try:
        monitor.run()
    except Exception:
        logging.exception("Agent crashed in main()")
        raise


if __name__ == "__main__":
    main()
