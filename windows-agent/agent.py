import ctypes
from ctypes import wintypes
import json
import re
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional

import psutil
import win32con
import win32gui
import win32process
from win10toast import ToastNotifier
import keyboard


CONFIG_PATH = Path(__file__).with_name("config.json")
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
        self.process_poll_interval = float(config["sensitivity"]["process_poll_interval_s"])
        self.toast = ToastNotifier()
        self.last_clipboard_text: Optional[str] = None
        self.last_copy_time: Optional[float] = None
        self.known_pids: Dict[int, str] = {}
        self.running = True

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
            self.toast.show_toast("ClickFix Mitigator", message, duration=8, threaded=True)
        except Exception:
            print("[ALERTA]", message)

    def handle_clipboard_change(self, text: str) -> None:
        if self.is_excluded(text):
            return
        self.last_clipboard_text = text
        self.last_copy_time = time.time()
        match = self.match_suspicious(text)
        if match:
            self.raise_alert(
                AlertContext(
                    reason=f"Texto copiado coincide con regla '{match}'",
                    text=text,
                    active_window=self.get_active_window(),
                    active_process=self.get_active_process(),
                )
            )

    def handle_paste(self) -> None:
        text = self.load_clipboard_text() or ""
        if not text or self.is_excluded(text):
            return
        if self.last_clipboard_text is None:
            reason = "Texto pegado sin copia previa"
        elif text != self.last_clipboard_text:
            reason = "Texto pegado distinto al último copiado"
        else:
            reason = "Texto pegado coincide con último copiado"
        match = self.match_suspicious(text)
        if match:
            reason = f"Texto pegado coincide con regla '{match}'"
        if match or reason != "Texto pegado coincide con último copiado":
            self.raise_alert(
                AlertContext(
                    reason=reason,
                    text=text,
                    active_window=self.get_active_window(),
                    active_process=self.get_active_process(),
                )
            )

    def monitor_clipboard(self) -> None:
        while self.running:
            text = self.load_clipboard_text()
            if text and text != self.last_clipboard_text:
                self.handle_clipboard_change(text)
            time.sleep(self.poll_interval)

    def monitor_processes(self) -> None:
        while self.running:
            current = {}
            for proc in psutil.process_iter(["pid", "name", "cmdline"]):
                try:
                    cmdline = " ".join(proc.info.get("cmdline") or [])
                except (psutil.NoSuchProcess, psutil.AccessDenied):
                    continue
                current[proc.info["pid"]] = cmdline
                if proc.info["pid"] not in self.known_pids and cmdline:
                    if self.is_excluded(cmdline):
                        continue
                    match = self.match_suspicious(cmdline)
                    if match:
                        self.raise_alert(
                            AlertContext(
                                reason=f"Comando sospechoso detectado ('{match}')",
                                text=cmdline,
                                active_window=self.get_active_window(),
                                active_process=self.get_active_process(),
                            )
                        )
            self.known_pids = current
            time.sleep(self.process_poll_interval)

    def monitor_paste_hotkey(self) -> None:
        keyboard.add_hotkey("ctrl+v", self.handle_paste)
        keyboard.wait()

    def run(self) -> None:
        threads = [
            threading.Thread(target=self.monitor_clipboard, daemon=True),
            threading.Thread(target=self.monitor_processes, daemon=True),
            threading.Thread(target=self.monitor_paste_hotkey, daemon=True),
        ]
        for thread in threads:
            thread.start()
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            self.running = False


def load_config() -> Dict[str, object]:
    with CONFIG_PATH.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def main() -> None:
    config = load_config()
    monitor = ClipboardMonitor(config)
    monitor.run()


if __name__ == "__main__":
    main()
