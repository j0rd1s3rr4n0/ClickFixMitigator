import ctypes
from ctypes import wintypes
import json
import logging
import re
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Optional

import psutil
import win32gui
import win32process
from winotify import Notification
import keyboard
import pystray
from PIL import Image, ImageDraw


CONFIG_PATH = Path(__file__).with_name("config.json")
LOG_PATH = Path(__file__).with_name("agent.log")
CLIPBOARD_CF_UNICODETEXT = 13


@dataclass
class AlertContext:
    reason: str
    text: str
    active_window: str
    active_process: str


class ClipboardMonitor:
    def __init__(self, config: Dict[str, object]) -> None:
        self.config = config
        self.suspicious_patterns = [re.compile(p, re.IGNORECASE) for p in config["rules"]["suspicious_regexes"]]
        self.exclusions = [re.compile(p, re.IGNORECASE) for p in config["rules"]["exclusions"]]
        self.poll_interval = float(config["sensitivity"]["clipboard_poll_interval_s"])
        self.run_sequence_timeout = float(config["sensitivity"].get("run_sequence_timeout_s", 8))
        self.toast_app_id = "ClickFix Mitigator"
        self.blocked_placeholder = str(config["sensitivity"].get("blocked_clipboard_placeholder", "[Clipboard bloqueado]"))
        self.last_clipboard_text: Optional[str] = None
        self.last_blocked_clipboard: Optional[str] = None
        self.last_blocked_reason: Optional[str] = None
        self.ignore_next_clipboard_change = False
        self.run_sequence_started_at: Optional[float] = None
        self.run_sequence_last_paste: Optional[str] = None
        self.running = True
        self.tray_icon: Optional[pystray.Icon] = None

    def load_clipboard_text(self) -> Optional[str]:
        if not ctypes.windll.user32.OpenClipboard(0):
            return None
        try:
            if not ctypes.windll.user32.IsClipboardFormatAvailable(CLIPBOARD_CF_UNICODETEXT):
                return None
            handle = ctypes.windll.user32.GetClipboardData(CLIPBOARD_CF_UNICODETEXT)
            if not handle:
                return None
            pointer = ctypes.windll.kernel32.GlobalLock(handle)
            if not pointer:
                return None
            try:
                data = wintypes.LPCWSTR(pointer)
                return data.value
            finally:
                ctypes.windll.kernel32.GlobalUnlock(handle)
        finally:
            ctypes.windll.user32.CloseClipboard()

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
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            return f"PID {pid}"

    def is_excluded(self, text: str) -> bool:
        return any(pattern.search(text) for pattern in self.exclusions)

    def match_suspicious(self, text: str) -> Optional[str]:
        for pattern in self.suspicious_patterns:
            if pattern.search(text):
                return pattern.pattern
        return None

    def raise_alert(self, context: AlertContext) -> None:
        message = (
            f"Motivo: {context.reason}\n"
            f"Proceso: {context.active_process}\n"
            f"Ventana: {context.active_window}\n"
            f"Texto: {context.text[:200]}"
        )
        try:
            notification = Notification(
                app_id=self.toast_app_id,
                title="ClickFix Mitigator",
                msg=message,
                duration="short",
            )
            notification.show()
        except Exception:
            print("[ALERTA]", message)

    def set_clipboard_text(self, text: str) -> None:
        if not ctypes.windll.user32.OpenClipboard(0):
            return
        try:
            ctypes.windll.user32.EmptyClipboard()
            data = ctypes.create_unicode_buffer(text)
            size = ctypes.sizeof(data)
            handle = ctypes.windll.kernel32.GlobalAlloc(0x0002, size)
            if not handle:
                return
            locked = ctypes.windll.kernel32.GlobalLock(handle)
            if not locked:
                ctypes.windll.kernel32.GlobalFree(handle)
                return
            ctypes.memmove(locked, ctypes.addressof(data), size)
            ctypes.windll.kernel32.GlobalUnlock(handle)
            ctypes.windll.user32.SetClipboardData(CLIPBOARD_CF_UNICODETEXT, handle)
        finally:
            ctypes.windll.user32.CloseClipboard()

    def quarantine_clipboard(self, text: str, reason: str) -> None:
        self.last_blocked_clipboard = text
        self.last_blocked_reason = reason
        self.ignore_next_clipboard_change = True
        self.set_clipboard_text(self.blocked_placeholder)
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
            return
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

    def handle_clipboard_change(self, text: str) -> None:
        if self.is_excluded(text):
            return
        self.last_clipboard_text = text
        match = self.match_suspicious(text)
        if match:
            self.quarantine_clipboard(text, f"Texto copiado coincide con regla '{match}'")

    def run_sequence_active(self) -> bool:
        if self.run_sequence_started_at is None:
            return False
        return (time.time() - self.run_sequence_started_at) <= self.run_sequence_timeout

    def handle_run_hotkey(self) -> None:
        self.run_sequence_started_at = time.time()
        self.run_sequence_last_paste = None

    def handle_run_paste(self) -> None:
        if not self.run_sequence_active():
            return
        self.run_sequence_last_paste = "ctrl+v"
        text = self.load_clipboard_text() or ""
        if not text or self.is_excluded(text):
            return
        match = self.match_suspicious(text)
        if match:
            self.quarantine_clipboard(text, f"Patrón Win+R + pegar ({self.run_sequence_last_paste}) con regla '{match}'")

    def handle_run_execute(self, method: str) -> None:
        if not self.run_sequence_active():
            return
        text = self.load_clipboard_text() or ""
        if not text or self.is_excluded(text):
            self.run_sequence_started_at = None
            return
        match = self.match_suspicious(text)
        if match:
            paste_note = self.run_sequence_last_paste or "pegado (click derecho o desconocido)"
            self.quarantine_clipboard(text, f"Patrón Win+R + {paste_note} + {method} con regla '{match}'")
        self.run_sequence_started_at = None

    def monitor_clipboard(self) -> None:
        while self.running:
            text = self.load_clipboard_text()
            if text and text != self.last_clipboard_text:
                if self.ignore_next_clipboard_change:
                    self.ignore_next_clipboard_change = False
                    self.last_clipboard_text = text
                else:
                    self.handle_clipboard_change(text)
            time.sleep(self.poll_interval)

    def monitor_paste_hotkey(self) -> None:
        keyboard.add_hotkey("windows+r", self.handle_run_hotkey)
        keyboard.add_hotkey("ctrl+v", self.handle_run_paste)
        keyboard.add_hotkey("enter", lambda: self.handle_run_execute("enter"))
        keyboard.add_hotkey("ctrl+shift+enter", lambda: self.handle_run_execute("ctrl+shift+enter"))
        keyboard.add_hotkey("ctrl+shift+u", self.restore_last_clipboard)
        keyboard.wait()

    def create_tray_icon(self) -> pystray.Icon:
        size = 64
        image = Image.new("RGB", (size, size), color=(30, 30, 30))
        draw = ImageDraw.Draw(image)
        draw.rectangle((12, 12, size - 12, size - 12), outline=(0, 180, 255), width=4)
        draw.line((20, size // 2, size // 2, size - 20), fill=(0, 180, 255), width=4)
        draw.line((size // 2, size - 20, size - 20, 20), fill=(0, 180, 255), width=4)
        menu = pystray.Menu(
            pystray.MenuItem("Restaurar último portapapeles bloqueado", self.restore_last_clipboard),
            pystray.MenuItem("Salir", self.stop),
        )
        return pystray.Icon("ClickFixMitigator", image, "ClickFix Mitigator", menu)

    def run_tray_icon(self) -> None:
        self.tray_icon = self.create_tray_icon()
        self.tray_icon.run()

    def stop(self, _=None) -> None:
        self.running = False
        if self.tray_icon:
            self.tray_icon.stop()

    def run(self) -> None:
        threads = [
            threading.Thread(
                target=self._run_thread,
                args=(self.monitor_clipboard, "monitor_clipboard"),
                daemon=True,
            ),
            threading.Thread(
                target=self._run_thread,
                args=(self.monitor_paste_hotkey, "monitor_paste_hotkey"),
                daemon=True,
            ),
        ]
        for thread in threads:
            thread.start()
        try:
            self._run_thread(self.run_tray_icon, "run_tray_icon")
        except KeyboardInterrupt:
            self.stop()

    def _run_thread(self, target, name: str) -> None:
        try:
            logging.info("Starting thread: %s", name)
            target()
        except Exception:
            logging.exception("Thread crashed: %s", name)
            self.stop()


def load_config() -> Dict[str, object]:
    with CONFIG_PATH.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def main() -> None:
    logging.basicConfig(
        filename=LOG_PATH,
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )
    logging.info("Starting ClickFix Mitigator agent")
    print("ClickFix Mitigator iniciado. Revisa la bandeja del sistema.")
    try:
        config = load_config()
    except Exception:
        logging.exception("Failed to load config")
        raise
    monitor = ClipboardMonitor(config)
    try:
        monitor.run()
    except Exception:
        logging.exception("Agent crashed")
        raise


if __name__ == "__main__":
    main()
