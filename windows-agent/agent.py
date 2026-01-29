import ctypes
from ctypes import wintypes
import json
import logging
import logging.handlers
import os
import re
import sqlite3
import socket
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Optional, Callable, Iterable, Tuple

import psutil
import win32gui
import win32process
from winotify import Notification
import keyboard
import pystray
from PIL import Image, ImageDraw


# ---- Build/version marker (to confirm you're running the right file) ----
AGENT_VERSION = "agent-2026-01-29-full"

BASE_DIR = Path(__file__).resolve().parent
CONFIG_PATH = BASE_DIR / "config.json"
LOG_PATH = BASE_DIR / "agent-debug.log"
EVENT_LOG_PATH = BASE_DIR / "agent.log"
ALERTSITES_PATH = BASE_DIR / "alertsites"
DEFAULT_DB_PATH = BASE_DIR / "data" / "clickfix.sqlite"
CLIPBOARD_CF_UNICODETEXT = 13
TRAY_ICON_PATH = BASE_DIR / "logo.png"
MESSAGEBOX_YES = 6
MESSAGEBOX_NO = 7

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
    country TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT
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

        self.alert_count = 0
        self.block_count = 0
        self.last_stats_flush = 0.0

        self.alertsites_cache = self._load_alertsites()

        self._ensure_db()

    def _ensure_db(self) -> None:
        if not self.enable_db:
            return
        try:
            self.db_path.parent.mkdir(parents=True, exist_ok=True)
            with sqlite3.connect(self.db_path, timeout=3) as conn:
                conn.executescript(DB_SCHEMA_SQL)
        except Exception:
            logging.exception("Failed to ensure local SQLite schema at %s", self.db_path)

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

    def record_report(self, record: Dict[str, object]) -> None:
        if not self.enable_db:
            return
        try:
            with sqlite3.connect(self.db_path, timeout=3) as conn:
                conn.execute(
                    """
                    INSERT INTO reports (
                        received_at, url, hostname, message, detected_content,
                        full_context, signals_json, blocked, user_agent, ip, country
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    ),
                )
        except Exception:
            logging.exception("Failed to write report to SQLite")

    def record_stats(self, force: bool = False) -> None:
        if not self.enable_db:
            return
        now = time.time()
        if not force and (now - self.last_stats_flush) < self.stats_flush_interval:
            return
        try:
            with sqlite3.connect(self.db_path, timeout=3) as conn:
                conn.execute(
                    """
                    INSERT INTO stats (
                        received_at, enabled, alert_count, block_count, manual_sites_json, country
                    ) VALUES (?, ?, ?, ?, ?, ?)
                    """,
                    (
                        utc_now_iso(),
                        1,
                        self.alert_count,
                        self.block_count,
                        json.dumps(sorted(self.alertsites_cache), ensure_ascii=False),
                        None,
                    ),
                )
            self.last_stats_flush = now
        except Exception:
            logging.exception("Failed to write stats to SQLite")

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
        self.record_stats()


class ClipboardMonitor:
    def __init__(self, config: Dict[str, object]) -> None:
        self.config = config

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

        self.running = True
        self.tray_icon: Optional[pystray.Icon] = None
        self.stop_event = threading.Event()
        self.telemetry = TelemetryStore(BASE_DIR, config)
        self.hotkeys = self._load_hotkeys(config)

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
                data = wintypes.LPCWSTR(pointer)
                text = data.value
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

    # ---------------- Alerts / UI ----------------

    def raise_alert(self, context: AlertContext) -> None:
        preview = safe_truncate(context.text, self.max_clipboard_preview)
        message = (
            f"Motivo: {context.reason}\n"
            f"Acción: {context.action}\n"
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

        try:
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

    def prompt_user_decision(self, reason: str, text: str) -> bool:
        message = (
            "Se detectó un comando sospechoso.\n\n"
            f"Motivo: {reason}\n"
            f"Texto: {safe_truncate(text, self.max_clipboard_preview)}\n\n"
            "¿Permitir este comando?"
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
    ) -> None:
        logging.warning(
            "Quarantine triggered. reason=%s close_run_dialog=%s text_preview=%r",
            reason,
            close_run_dialog,
            text[:200],
        )

        active_window, active_process = self.capture_context()
        if self.is_allowed_context(active_process, active_window):
            logging.debug("Context allowed; skipping quarantine")
            return

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
            return

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

    def handle_run_hotkey(self) -> None:
        self.start_sequence("run", "win+r")

    def handle_explorer_hotkey(self) -> None:
        self.start_sequence("explorer", "win+e")

    def handle_address_hotkey(self, key: str) -> None:
        self.start_sequence("address", key)

    def handle_sequence_paste(self, method: str) -> None:
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
            for key in self.hotkeys["paste"]:
                keyboard.add_hotkey(key, lambda k=key: self.handle_sequence_paste(k))
            for key in self.hotkeys["execute"]:
                keyboard.add_hotkey(key, lambda k=key: self.handle_sequence_execute(k))
            for key in self.hotkeys["restore"]:
                keyboard.add_hotkey(key, self.restore_last_clipboard)

            logging.info("Hotkeys registered: %s", self.hotkeys)
        except Exception:
            logging.exception("Failed registering hotkeys")

        while self.running:
            time.sleep(0.5)

    # ---------------- Tray icon ----------------

    def create_tray_icon(self) -> pystray.Icon:
        image = self.load_tray_image()
        menu = pystray.Menu(
            pystray.MenuItem("Restaurar último portapapeles bloqueado", self.restore_last_clipboard),
            pystray.MenuItem("Salir", self.stop),
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
            self.telemetry.record_stats(force=True)
        except Exception:
            logging.exception("Failed to flush telemetry stats")
        try:
            if self.tray_icon:
                self.tray_icon.stop()
                logging.debug("Tray icon stop invoked")
        except Exception:
            logging.exception("Failed to stop tray icon")

    def run(self) -> None:
        logging.info("ClipboardMonitor run() starting")
        self.telemetry.record_stats(force=True)
        self.telemetry.log_event("agent_start", {"pid": os.getpid()})

        # Start everything under watchdogs (including tray).
        threads = [
            threading.Thread(
                target=self._run_thread_watchdog,
                args=(self.monitor_clipboard, "monitor_clipboard"),
                name="monitor_clipboard_watchdog",
                daemon=False,
            ),
            threading.Thread(
                target=self._run_thread_watchdog,
                args=(self.monitor_paste_hotkey, "monitor_paste_hotkey"),
                name="monitor_paste_hotkey_watchdog",
                daemon=False,
            ),
            threading.Thread(
                target=self._run_thread_watchdog,
                args=(self.run_tray_icon, "run_tray_icon"),
                name="tray_watchdog",
                daemon=False,
            ),
        ]

        for t in threads:
            logging.debug("Starting thread %s", t.name)
            t.start()

        # Keep main alive no matter what.
        try:
            logging.info("Main keepalive loop started")
            while self.running:
                time.sleep(1)
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

    logging.info("Starting ClickFix Mitigator agent")
    print("ClickFix Mitigator iniciado. Revisa la bandeja del sistema.")

    monitor = ClipboardMonitor(config)
    try:
        monitor.run()
    except Exception:
        logging.exception("Agent crashed in main()")
        raise


if __name__ == "__main__":
    main()
