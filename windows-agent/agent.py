import ctypes
from ctypes import wintypes
import json
import logging
import logging.handlers
import re
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Optional, Callable

import psutil
import win32gui
import win32process
from winotify import Notification
import keyboard
import pystray
from PIL import Image, ImageDraw


# ---- Build/version marker (to confirm you're running the right file) ----
AGENT_VERSION = "agent-debug-2026-01-08-keepalive-1"

CONFIG_PATH = Path(__file__).with_name("config.json")
LOG_PATH = Path(__file__).with_name("agent.log")
CLIPBOARD_CF_UNICODETEXT = 13
TRAY_ICON_PATH = Path(__file__).with_name("logo.png")
MESSAGEBOX_YES = 6
MESSAGEBOX_NO = 7


@dataclass
class AlertContext:
    reason: str
    text: str
    active_window: str
    active_process: str


class ClipboardMonitor:
    def __init__(self, config: Dict[str, object]) -> None:
        self.config = config

        self.suspicious_patterns = [
            re.compile(p, re.IGNORECASE) for p in config["rules"]["suspicious_regexes"]
        ]
        self.exclusions = [
            re.compile(p, re.IGNORECASE) for p in config["rules"]["exclusions"]
        ]

        self.poll_interval = float(config["sensitivity"]["clipboard_poll_interval_s"])
        self.run_sequence_timeout = float(config["sensitivity"].get("run_sequence_timeout_s", 8))
        self.toast_app_id = "ClickFix Mitigator"
        self.blocked_placeholder = str(
            config["sensitivity"].get("blocked_clipboard_placeholder", "[Clipboard bloqueado]")
        )
        self.allow_timeout = float(config["sensitivity"].get("allow_timeout_s", 15))

        self.last_clipboard_text: Optional[str] = None
        self.last_blocked_clipboard: Optional[str] = None
        self.last_blocked_reason: Optional[str] = None
        self.allowed_clipboard_text: Optional[str] = None
        self.allowed_clipboard_until: Optional[float] = None

        self.ignore_next_clipboard_change = False
        self.run_sequence_started_at: Optional[float] = None
        self.run_sequence_last_paste: Optional[str] = None

        self.running = True
        self.tray_icon: Optional[pystray.Icon] = None

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

    # ---------------- Alerts / UI ----------------

    def raise_alert(self, context: AlertContext) -> None:
        message = (
            f"Motivo: {context.reason}\n"
            f"Proceso: {context.active_process}\n"
            f"Ventana: {context.active_window}\n"
            f"Texto: {context.text[:200]}"
        )
        logging.info(
            "ALERT: %s | proc=%s | win=%s | text_preview=%r",
            context.reason,
            context.active_process,
            context.active_window,
            context.text[:200],
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
            f"Texto: {text[:200]}\n\n"
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

    def quarantine_clipboard(self, text: str, reason: str, close_run_dialog: bool = False) -> None:
        logging.warning(
            "Quarantine triggered. reason=%s close_run_dialog=%s text_preview=%r",
            reason,
            close_run_dialog,
            text[:200],
        )

        self.last_blocked_clipboard = text
        self.last_blocked_reason = reason

        self.ignore_next_clipboard_change = True
        self.set_clipboard_text(self.blocked_placeholder)

        allow = self.prompt_user_decision(reason, text)
        if allow:
            self.allow_clipboard_temporarily(text)
            self.raise_alert(
                AlertContext(
                    reason="Usuario permitió el comando",
                    text=text,
                    active_window=self.get_active_window(),
                    active_process=self.get_active_process(),
                )
            )
            return

        if close_run_dialog:
            try:
                keyboard.send("esc")
                logging.debug("Sent ESC to close run dialog")
            except Exception:
                logging.exception("Failed to send ESC")

        self.raise_alert(
            AlertContext(
                reason=f"{reason}. Clipboard reemplazado; puedes restaurarlo desde la bandeja.",
                text=text,
                active_window=self.get_active_window(),
                active_process=self.get_active_process(),
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
        self.last_blocked_clipboard = None

        self.raise_alert(
            AlertContext(
                reason="Clipboard restaurado por el usuario",
                text=restored,
                active_window=self.get_active_window(),
                active_process=self.get_active_process(),
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

        self.last_clipboard_text = text
        match = self.match_suspicious(text)
        if match:
            self.quarantine_clipboard(text, f"Texto copiado coincide con regla '{match}'")
        else:
            logging.debug("Clipboard text not suspicious")

    def run_sequence_active(self) -> bool:
        if self.run_sequence_started_at is None:
            return False
        active = (time.time() - self.run_sequence_started_at) <= self.run_sequence_timeout
        logging.debug("run_sequence_active=%s", active)
        return active

    def handle_run_hotkey(self) -> None:
        self.run_sequence_started_at = time.time()
        self.run_sequence_last_paste = None
        logging.info("Win+R detected; run sequence started")

    def handle_run_paste(self) -> None:
        if not self.run_sequence_active():
            logging.debug("Ctrl+V ignored (no active run sequence)")
            return

        self.run_sequence_last_paste = "ctrl+v"
        text = self.load_clipboard_text() or ""
        logging.info("Paste detected during run sequence (len=%s)", len(text) if text else 0)

        if not text or self.is_excluded(text) or self.is_temporarily_allowed(text):
            logging.debug("Paste text empty/excluded/allowed; ignoring")
            return

        match = self.match_suspicious(text)
        if match:
            self.quarantine_clipboard(
                text,
                f"Patrón Win+R + pegar ({self.run_sequence_last_paste}) con regla '{match}'",
            )

    def handle_run_execute(self, method: str) -> None:
        if not self.run_sequence_active():
            logging.debug("Execute ignored (no active run sequence). method=%s", method)
            return

        text = self.load_clipboard_text() or ""
        logging.info(
            "Execute detected during run sequence. method=%s len=%s",
            method,
            len(text) if text else 0,
        )

        if not text or self.is_excluded(text):
            logging.debug("Execute: no text or excluded; clearing run sequence")
            self.run_sequence_started_at = None
            return

        if self.is_temporarily_allowed(text):
            logging.debug("Execute: temporarily allowed; clearing run sequence")
            self.run_sequence_started_at = None
            return

        match = self.match_suspicious(text)
        if match:
            paste_note = self.run_sequence_last_paste or "pegado (click derecho o desconocido)"
            self.quarantine_clipboard(
                text,
                f"Patrón Win+R + {paste_note} + {method} con regla '{match}'",
                close_run_dialog=True,
            )

        self.run_sequence_started_at = None

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
        keyboard.add_hotkey("windows+r", self.handle_run_hotkey)
        keyboard.add_hotkey("ctrl+v", self.handle_run_paste)
        keyboard.add_hotkey("enter", lambda: self.handle_run_execute("enter"))
        keyboard.add_hotkey("ctrl+shift+enter", lambda: self.handle_run_execute("ctrl+shift+enter"))
        keyboard.add_hotkey("ctrl+shift+u", self.restore_last_clipboard)

        keyboard.wait()
        logging.warning("monitor_paste_hotkey exited (keyboard.wait returned)")

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
        try:
            if self.tray_icon:
                self.tray_icon.stop()
                logging.debug("Tray icon stop invoked")
        except Exception:
            logging.exception("Failed to stop tray icon")

    def run(self) -> None:
        logging.info("ClipboardMonitor run() starting")

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
