#!/usr/bin/env python3
from __future__ import annotations

import queue
import subprocess
import sys
import threading
import traceback
from pathlib import Path
import json
from urllib.request import Request, urlopen
import tkinter as tk
from tkinter import filedialog, messagebox, ttk
from typing import Optional
from urllib.parse import urlparse


BASE_DIR = Path(__file__).resolve().parent
BOT_SCRIPT = BASE_DIR / "botanalyzer.py"
ANYRUN_SCRIPT = BASE_DIR / "anyrunbot.py"
CONFIG_SCRIPT = BASE_DIR / "config.py"
SETTINGS_PATH = BASE_DIR / "settings.json"
DEFAULT_PYTHON = sys.executable


class ProcessManager:
    def __init__(self, on_output, on_done):
        self._process: Optional[subprocess.Popen] = None
        self._reader_thread: Optional[threading.Thread] = None
        self._queue: queue.Queue[str] = queue.Queue()
        self._on_output = on_output
        self._on_done = on_done
        self._pump_job: Optional[str] = None

    @property
    def is_running(self) -> bool:
        return self._process is not None and self._process.poll() is None

    def start(self, root: tk.Tk, command: list[str], cwd: Path) -> bool:
        if self.is_running:
            return False
        try:
            self._process = subprocess.Popen(
                command,
                cwd=str(cwd),
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                bufsize=1,
            )
        except Exception as exc:
            self._on_output(f"[ERROR] No se pudo iniciar proceso: {exc}\n")
            self._process = None
            return False

        self._reader_thread = threading.Thread(target=self._reader_loop, daemon=True)
        self._reader_thread.start()
        self._schedule_pump(root)
        return True

    def stop(self) -> None:
        if not self.is_running:
            return
        proc = self._process
        if proc is None:
            return
        try:
            proc.terminate()
        except Exception:
            pass

    def _reader_loop(self) -> None:
        proc = self._process
        if proc is None:
            return
        assert proc.stdout is not None
        try:
            for line in proc.stdout:
                self._queue.put(line)
        except Exception as exc:
            self._queue.put(f"[ERROR] Fallo leyendo salida: {exc}\n")

    def _schedule_pump(self, root: tk.Tk) -> None:
        self._pump_job = root.after(120, self._pump, root)

    def _pump(self, root: tk.Tk) -> None:
        while True:
            try:
                line = self._queue.get_nowait()
            except queue.Empty:
                break
            self._on_output(line)

        proc = self._process
        if proc is None:
            self._on_done(-1)
            return

        code = proc.poll()
        if code is None:
            self._schedule_pump(root)
            return

        while True:
            try:
                line = self._queue.get_nowait()
            except queue.Empty:
                break
            self._on_output(line)
        self._on_done(code)
        self._process = None
        self._reader_thread = None
        self._pump_job = None


class BotanalyzerGUI:
    def __init__(self, root: tk.Tk):
        self.root = root
        self.root.title("BotAnalyzer Control Panel")
        self.root.geometry("1080x760")
        self.root.minsize(920, 640)

        self.manager = ProcessManager(self._append_log, self._on_process_done)

        self.python_var = tk.StringVar(value=DEFAULT_PYTHON)
        self.script_mode_var = tk.StringVar(value="bot")

        self._build_header()
        self._build_tabs()
        self._build_footer()
        self._build_log()
        self._load_settings()
        self._refresh_buttons()

    def _load_settings(self) -> None:
        if not SETTINGS_PATH.exists():
            return
        try:
            data = json.loads(SETTINGS_PATH.read_text(encoding="utf-8"))
        except Exception:
            return
        if not isinstance(data, dict):
            return
        self.cfg_threatfox_key.set(str(data.get("threatfox_key", "")))
        self.cfg_anyrun_email.set(str(data.get("anyrun_email", "")))
        self.cfg_anyrun_password.set(str(data.get("anyrun_password", "")))

    def _save_settings(self) -> None:
        payload = {
            "threatfox_key": self.cfg_threatfox_key.get().strip(),
            "anyrun_email": self.cfg_anyrun_email.get().strip(),
            "anyrun_password": self.cfg_anyrun_password.get().strip(),
        }
        SETTINGS_PATH.write_text(json.dumps(payload, indent=2), encoding="utf-8")

    def _build_header(self) -> None:
        frame = ttk.Frame(self.root, padding=(10, 10, 10, 6))
        frame.pack(fill="x")

        ttk.Label(frame, text="Python:").grid(row=0, column=0, sticky="w")
        entry = ttk.Entry(frame, textvariable=self.python_var)
        entry.grid(row=0, column=1, sticky="ew", padx=(6, 6))
        ttk.Button(frame, text="Buscar...", command=self._pick_python).grid(row=0, column=2, sticky="ew")
        ttk.Button(frame, text="Usar este Python", command=self._use_current_python).grid(row=0, column=3, sticky="ew", padx=(6, 0))
        frame.columnconfigure(1, weight=1)

    def _build_tabs(self) -> None:
        notebook = ttk.Notebook(self.root)
        notebook.pack(fill="both", expand=True, padx=10, pady=(0, 8))

        self.bot_frame = ttk.Frame(notebook, padding=10)
        self.anyrun_frame = ttk.Frame(notebook, padding=10)
        self.threatfox_frame = ttk.Frame(notebook, padding=10)
        self.config_frame = ttk.Frame(notebook, padding=10)

        notebook.add(self.bot_frame, text="BotAnalyzer")
        notebook.add(self.anyrun_frame, text="AnyRun")
        notebook.add(self.threatfox_frame, text="ThreatFox")
        notebook.add(self.config_frame, text="Config")

        self._build_bot_tab()
        self._build_anyrun_tab()
        self._build_threatfox_tab()
        self._build_config_tab()

    def _build_footer(self) -> None:
        frame = ttk.Frame(self.root, padding=(10, 0, 10, 8))
        frame.pack(fill="x")

        ttk.Label(frame, text="Modo:").pack(side="left")
        self.mode_combo = ttk.Combobox(
            frame,
            textvariable=self.script_mode_var,
            values=["bot", "anyrun", "config"],
            width=12,
            state="readonly",
        )
        self.mode_combo.pack(side="left", padx=(6, 10))
        self.mode_combo.bind("<<ComboboxSelected>>", lambda _e: self._refresh_buttons())

        self.run_button = ttk.Button(frame, text="Ejecutar", command=self._run_selected)
        self.run_button.pack(side="left")
        self.stop_button = ttk.Button(frame, text="Detener", command=self._stop_process)
        self.stop_button.pack(side="left", padx=(8, 0))
        ttk.Button(frame, text="Limpiar log", command=self._clear_log).pack(side="left", padx=(8, 0))

    def _build_log(self) -> None:
        wrap = ttk.Frame(self.root, padding=(10, 0, 10, 10))
        wrap.pack(fill="both", expand=True)
        ttk.Label(wrap, text="Salida").pack(anchor="w")
        self.log_text = tk.Text(wrap, height=15, wrap="word")
        self.log_text.pack(fill="both", expand=True, side="left")
        sb = ttk.Scrollbar(wrap, orient="vertical", command=self.log_text.yview)
        sb.pack(side="right", fill="y")
        self.log_text.configure(yscrollcommand=sb.set)

    def _build_bot_tab(self) -> None:
        self.bot_urls = tk.StringVar(value=str(BASE_DIR / "urls.txt"))
        self.bot_done = tk.StringVar(value=str(BASE_DIR / "done.txt"))
        self.bot_dead = tk.StringVar(value=str(BASE_DIR / "dead.txt"))
        self.bot_blocked = tk.StringVar(value=str(BASE_DIR / "blocked.txt"))
        self.bot_workers = tk.IntVar(value=3)
        self.bot_precheck_workers = tk.IntVar(value=80)
        self.bot_precheck_timeout = tk.IntVar(value=8)
        self.bot_page_timeout = tk.IntVar(value=60)
        self.bot_wait_close = tk.IntVar(value=15)
        self.bot_button_timeout = tk.DoubleVar(value=10.0)
        self.bot_post_load_wait = tk.DoubleVar(value=10.5)
        self.bot_max_url_seconds = tk.IntVar(value=90)
        self.bot_max_clicks = tk.IntVar(value=160)
        self.bot_max_frame_depth = tk.IntVar(value=5)
        self.bot_max_div_clicks = tk.IntVar(value=1000)
        self.bot_no_precheck = tk.BooleanVar(value=False)
        self.bot_no_zip = tk.BooleanVar(value=False)
        self.bot_headful = tk.BooleanVar(value=True)
        self.bot_shared_profile = tk.BooleanVar(value=False)
        self.bot_auto = tk.BooleanVar(value=False)
        self.bot_threatfox = tk.BooleanVar(value=False)
        self.bot_threatfox_days = tk.IntVar(value=7)
        self.bot_threatfox_limit = tk.IntVar(value=400)
        self.bot_threatfox_tag = tk.StringVar(value="clickfix, IClickFix, stealer, ErrTraffic, ClearFake, ClickChain")

        self.bot_profile = tk.StringVar(value=str(BASE_DIR / "chrome-profile"))
        self.bot_extension = tk.StringVar(value=str(BASE_DIR.parent / "browser-extension"))

        self._add_path_row(self.bot_frame, 0, "urls.txt", self.bot_urls, "file")
        self._add_path_row(self.bot_frame, 1, "done.txt", self.bot_done, "file")
        self._add_path_row(self.bot_frame, 2, "dead.txt", self.bot_dead, "file")
        self._add_path_row(self.bot_frame, 3, "blocked.txt", self.bot_blocked, "file")
        self._add_path_row(self.bot_frame, 4, "profile-dir", self.bot_profile, "dir")
        self._add_path_row(self.bot_frame, 5, "extension", self.bot_extension, "path")

        settings = ttk.LabelFrame(self.bot_frame, text="Parametros", padding=8)
        settings.grid(row=6, column=0, columnspan=3, sticky="ew", pady=(8, 0))
        for i in range(8):
            settings.columnconfigure(i, weight=1)

        ttk.Label(settings, text="workers").grid(row=0, column=0, sticky="w")
        ttk.Entry(settings, textvariable=self.bot_workers, width=8).grid(row=0, column=1, sticky="w", padx=(4, 10))
        ttk.Label(settings, text="precheck-workers").grid(row=0, column=2, sticky="w")
        ttk.Entry(settings, textvariable=self.bot_precheck_workers, width=8).grid(row=0, column=3, sticky="w", padx=(4, 10))
        ttk.Label(settings, text="precheck-timeout").grid(row=0, column=4, sticky="w")
        ttk.Entry(settings, textvariable=self.bot_precheck_timeout, width=8).grid(row=0, column=5, sticky="w", padx=(4, 10))

        ttk.Label(settings, text="page-timeout").grid(row=1, column=0, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_page_timeout, width=8).grid(row=1, column=1, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="wait-close").grid(row=1, column=2, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_wait_close, width=8).grid(row=1, column=3, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="button-timeout").grid(row=1, column=4, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_button_timeout, width=8).grid(row=1, column=5, sticky="w", padx=(4, 10), pady=(8, 0))

        ttk.Label(settings, text="post-load-wait").grid(row=2, column=0, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_post_load_wait, width=8).grid(row=2, column=1, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="max-url-seconds").grid(row=2, column=2, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_max_url_seconds, width=8).grid(row=2, column=3, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="max-clicks").grid(row=2, column=4, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_max_clicks, width=8).grid(row=2, column=5, sticky="w", padx=(4, 10), pady=(8, 0))

        ttk.Label(settings, text="max-frame-depth").grid(row=3, column=0, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_max_frame_depth, width=8).grid(row=3, column=1, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="max-div-clicks").grid(row=3, column=2, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.bot_max_div_clicks, width=8).grid(row=3, column=3, sticky="w", padx=(4, 10), pady=(8, 0))

        checks = ttk.Frame(settings)
        checks.grid(row=4, column=0, columnspan=8, sticky="w", pady=(10, 0))
        ttk.Checkbutton(checks, text="headful", variable=self.bot_headful).pack(side="left")
        ttk.Checkbutton(checks, text="no-precheck", variable=self.bot_no_precheck).pack(side="left", padx=(10, 0))
        ttk.Checkbutton(checks, text="no-zip", variable=self.bot_no_zip).pack(side="left", padx=(10, 0))
        ttk.Checkbutton(checks, text="shared-profile", variable=self.bot_shared_profile).pack(side="left", padx=(10, 0))
        ttk.Checkbutton(checks, text="auto", variable=self.bot_auto).pack(side="left", padx=(10, 0))

        threatfox = ttk.LabelFrame(self.bot_frame, text="ThreatFox", padding=8)
        threatfox.grid(row=7, column=0, columnspan=3, sticky="ew", pady=(8, 0))
        threatfox.columnconfigure(2, weight=1)
        ttk.Checkbutton(threatfox, text="activar", variable=self.bot_threatfox).grid(row=0, column=0, sticky="w")
        ttk.Label(threatfox, text="tag").grid(row=0, column=1, sticky="w", padx=(10, 0))
        ttk.Entry(threatfox, textvariable=self.bot_threatfox_tag, width=14).grid(row=0, column=2, sticky="ew", padx=(4, 0))
        ttk.Label(threatfox, text="days").grid(row=1, column=0, sticky="w", pady=(6, 0))
        ttk.Entry(threatfox, textvariable=self.bot_threatfox_days, width=8).grid(row=1, column=1, sticky="w", padx=(4, 10), pady=(6, 0))
        ttk.Label(threatfox, text="limit").grid(row=1, column=2, sticky="w", padx=(0, 0), pady=(6, 0))
        ttk.Entry(threatfox, textvariable=self.bot_threatfox_limit, width=8).grid(row=1, column=3, sticky="w", padx=(4, 0), pady=(6, 0))

        self.bot_frame.columnconfigure(1, weight=1)

    def _build_anyrun_tab(self) -> None:
        self.anyrun_url = tk.StringVar(value="https://app.any.run/submissions/")
        self.anyrun_output = tk.StringVar(value=str(BASE_DIR / "urls.txt"))
        self.anyrun_profile = tk.StringVar(value=str(BASE_DIR / "chrome-profile"))
        self.anyrun_headless = tk.BooleanVar(value=False)
        self.anyrun_wait_timeout = tk.DoubleVar(value=25.0)
        self.anyrun_page_change_timeout = tk.DoubleVar(value=20.0)
        self.anyrun_settle_seconds = tk.DoubleVar(value=1.2)
        self.anyrun_max_pages = tk.IntVar(value=0)
        self.anyrun_initial_timeout = tk.DoubleVar(value=45.0)
        self.anyrun_redirect_stable = tk.DoubleVar(value=1.4)

        self._add_simple_row(self.anyrun_frame, 0, "URL", self.anyrun_url)
        self._add_path_row(self.anyrun_frame, 1, "output", self.anyrun_output, "file")
        self._add_path_row(self.anyrun_frame, 2, "profile-dir", self.anyrun_profile, "dir")

        settings = ttk.LabelFrame(self.anyrun_frame, text="Parametros", padding=8)
        settings.grid(row=3, column=0, columnspan=3, sticky="ew", pady=(8, 0))
        for i in range(8):
            settings.columnconfigure(i, weight=1)

        ttk.Label(settings, text="wait-timeout").grid(row=0, column=0, sticky="w")
        ttk.Entry(settings, textvariable=self.anyrun_wait_timeout, width=8).grid(row=0, column=1, sticky="w", padx=(4, 10))
        ttk.Label(settings, text="page-change-timeout").grid(row=0, column=2, sticky="w")
        ttk.Entry(settings, textvariable=self.anyrun_page_change_timeout, width=8).grid(row=0, column=3, sticky="w", padx=(4, 10))
        ttk.Label(settings, text="settle-seconds").grid(row=0, column=4, sticky="w")
        ttk.Entry(settings, textvariable=self.anyrun_settle_seconds, width=8).grid(row=0, column=5, sticky="w", padx=(4, 10))

        ttk.Label(settings, text="max-pages").grid(row=1, column=0, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.anyrun_max_pages, width=8).grid(row=1, column=1, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="initial-data-timeout").grid(row=1, column=2, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.anyrun_initial_timeout, width=8).grid(row=1, column=3, sticky="w", padx=(4, 10), pady=(8, 0))
        ttk.Label(settings, text="redirect-stable-seconds").grid(row=1, column=4, sticky="w", pady=(8, 0))
        ttk.Entry(settings, textvariable=self.anyrun_redirect_stable, width=8).grid(row=1, column=5, sticky="w", padx=(4, 10), pady=(8, 0))

        ttk.Checkbutton(settings, text="headless", variable=self.anyrun_headless).grid(row=2, column=0, sticky="w", pady=(10, 0))
        self.anyrun_frame.columnconfigure(1, weight=1)

    def _build_threatfox_tab(self) -> None:
        self.tf_days = tk.IntVar(value=7)
        self.tf_limit = tk.IntVar(value=300)
        self.tf_timeout = tk.IntVar(value=12)
        self.tf_tag = tk.StringVar(value="clickfix, IClickFix, stealer, ErrTraffic, ClearFake, ClickChain")
        self.tf_output = tk.StringVar(value=str(BASE_DIR / "urls.txt"))
        self.tf_status = tk.StringVar(value="Listo.")

        output_wrap = ttk.Frame(self.threatfox_frame)
        output_wrap.pack(fill="x", pady=(0, 8))
        self._add_path_row(output_wrap, 0, "output", self.tf_output, "file")
        output_wrap.columnconfigure(1, weight=1)

        controls = ttk.Frame(self.threatfox_frame)
        controls.pack(fill="x", pady=(0, 8))

        ttk.Label(controls, text="tag").grid(row=0, column=0, sticky="w")
        ttk.Entry(controls, textvariable=self.tf_tag, width=14).grid(row=0, column=1, sticky="w", padx=(6, 12))
        ttk.Label(controls, text="days").grid(row=0, column=2, sticky="w")
        ttk.Entry(controls, textvariable=self.tf_days, width=6).grid(row=0, column=3, sticky="w", padx=(6, 12))
        ttk.Label(controls, text="limit").grid(row=0, column=4, sticky="w")
        ttk.Entry(controls, textvariable=self.tf_limit, width=8).grid(row=0, column=5, sticky="w", padx=(6, 12))
        ttk.Label(controls, text="timeout").grid(row=0, column=6, sticky="w")
        ttk.Entry(controls, textvariable=self.tf_timeout, width=6).grid(row=0, column=7, sticky="w", padx=(6, 12))
        ttk.Button(controls, text="Cargar ThreatFox", command=self._fetch_threatfox).grid(row=0, column=8, sticky="w")
        ttk.Button(controls, text="Guardar URLs", command=self._export_threatfox_urls).grid(row=0, column=9, sticky="w", padx=(6, 0))
        ttk.Button(controls, text="Limpiar tabla", command=self._clear_threatfox_table).grid(row=0, column=10, sticky="w", padx=(6, 0))
        controls.columnconfigure(11, weight=1)

        status = ttk.Label(self.threatfox_frame, textvariable=self.tf_status)
        status.pack(anchor="w", pady=(0, 6))

        table_wrap = ttk.Frame(self.threatfox_frame)
        table_wrap.pack(fill="both", expand=True)

        self.threatfox_table = ttk.Treeview(table_wrap, columns=(), show="headings")
        self.threatfox_table.pack(fill="both", expand=True, side="left")
        yscroll = ttk.Scrollbar(table_wrap, orient="vertical", command=self.threatfox_table.yview)
        yscroll.pack(side="right", fill="y")
        xscroll = ttk.Scrollbar(self.threatfox_frame, orient="horizontal", command=self.threatfox_table.xview)
        xscroll.pack(fill="x")
        self.threatfox_table.configure(yscrollcommand=yscroll.set, xscrollcommand=xscroll.set)

    def _build_config_tab(self) -> None:
        self.cfg_profile = tk.StringVar(value=str(BASE_DIR / "chrome-profile"))
        self.cfg_downloads = tk.StringVar(value=str(BASE_DIR / "downloads"))
        self.cfg_extension = tk.StringVar(value=str(BASE_DIR.parent / "browser-extension"))
        self.cfg_lang = tk.StringVar(value="es-ES")
        self.cfg_accept_lang = tk.StringVar(value="es-ES,es,en")
        self.cfg_open_url = tk.StringVar(value="https://app.any.run/submissions/")
        self.cfg_detach = tk.BooleanVar(value=True)
        self.cfg_threatfox_key = tk.StringVar(value="")
        self.cfg_anyrun_email = tk.StringVar(value="")
        self.cfg_anyrun_password = tk.StringVar(value="")

        self._add_path_row(self.config_frame, 0, "profile-dir", self.cfg_profile, "dir")
        self._add_path_row(self.config_frame, 1, "downloads-dir", self.cfg_downloads, "dir")
        self._add_path_row(self.config_frame, 2, "extension", self.cfg_extension, "path")
        self._add_simple_row(self.config_frame, 3, "lang", self.cfg_lang)
        self._add_simple_row(self.config_frame, 4, "accept-languages", self.cfg_accept_lang)
        self._add_simple_row(self.config_frame, 5, "open-url", self.cfg_open_url)
        ttk.Checkbutton(self.config_frame, text="detach", variable=self.cfg_detach).grid(row=6, column=0, sticky="w", pady=(8, 0))
        api_frame = ttk.LabelFrame(self.config_frame, text="API Keys", padding=8)
        api_frame.grid(row=7, column=0, columnspan=3, sticky="ew", pady=(10, 0))
        api_frame.columnconfigure(1, weight=1)
        ttk.Label(api_frame, text="ThreatFox key").grid(row=0, column=0, sticky="w")
        ttk.Entry(api_frame, textvariable=self.cfg_threatfox_key).grid(row=0, column=1, sticky="ew", padx=(6, 0))
        ttk.Label(api_frame, text="Any.run email").grid(row=1, column=0, sticky="w", pady=(6, 0))
        ttk.Entry(api_frame, textvariable=self.cfg_anyrun_email).grid(row=1, column=1, sticky="ew", padx=(6, 0), pady=(6, 0))
        ttk.Label(api_frame, text="Any.run password").grid(row=2, column=0, sticky="w", pady=(6, 0))
        ttk.Entry(api_frame, textvariable=self.cfg_anyrun_password, show="*").grid(row=2, column=1, sticky="ew", padx=(6, 0), pady=(6, 0))
        ttk.Button(api_frame, text="Guardar keys", command=self._save_settings).grid(row=3, column=0, sticky="w", pady=(8, 0))
        self.config_frame.columnconfigure(1, weight=1)

    def _add_simple_row(self, parent: ttk.Frame, row: int, label: str, var: tk.StringVar) -> None:
        ttk.Label(parent, text=label).grid(row=row, column=0, sticky="w", pady=(0, 6))
        ttk.Entry(parent, textvariable=var).grid(row=row, column=1, sticky="ew", padx=(6, 0), pady=(0, 6))

    def _add_path_row(self, parent: ttk.Frame, row: int, label: str, var: tk.StringVar, kind: str) -> None:
        ttk.Label(parent, text=label).grid(row=row, column=0, sticky="w", pady=(0, 6))
        ttk.Entry(parent, textvariable=var).grid(row=row, column=1, sticky="ew", padx=(6, 6), pady=(0, 6))
        ttk.Button(parent, text="...", width=4, command=lambda: self._pick_path(var, kind)).grid(row=row, column=2, sticky="ew", pady=(0, 6))

    def _clear_threatfox_table(self) -> None:
        if not hasattr(self, "threatfox_table"):
            return
        self.threatfox_table.delete(*self.threatfox_table.get_children())
        self.threatfox_table["columns"] = ()
        self.tf_status.set("Tabla limpia.")

    def _fetch_threatfox(self) -> None:
        self.tf_status.set("Cargando ThreatFox...")
        threading.Thread(target=self._fetch_threatfox_worker, daemon=True).start()

    def _fetch_threatfox_worker(self) -> None:
        try:
            columns, rows = self._fetch_threatfox_data()
        except Exception as exc:
            message = f"Error ThreatFox: {exc}"
            self.root.after(0, lambda: self.tf_status.set(message))
            return
        self.root.after(0, lambda: self._render_threatfox_table(columns, rows))

    def _export_threatfox_urls(self) -> None:
        self.tf_status.set("Guardando URLs ThreatFox...")
        threading.Thread(target=self._export_threatfox_worker, daemon=True).start()

    def _export_threatfox_worker(self) -> None:
        try:
            _, rows = self._fetch_threatfox_data()
            urls = self._extract_threatfox_urls(rows)
            output_path = Path(self.tf_output.get().strip() or BASE_DIR / "urls.txt")
            added = self._append_unique_urls(output_path, urls)
        except Exception as exc:
            message = f"Error ThreatFox: {exc}"
            self.root.after(0, lambda: self.tf_status.set(message))
            return
        self.root.after(0, lambda: self.tf_status.set(f"URLs guardadas: {added} nuevas."))

    def _fetch_threatfox_data(self) -> tuple[list[str], list[dict]]:
        days = max(1, min(int(self.tf_days.get() or 1), 30))
        limit = max(1, min(int(self.tf_limit.get() or 1), 5000))
        timeout = max(3, min(int(self.tf_timeout.get() or 5), 30))
        key = self.cfg_threatfox_key.get().strip() if hasattr(self, "cfg_threatfox_key") else ""
        tag_raw = self.tf_tag.get().strip()
        tags = [t.strip() for t in tag_raw.replace(";", ",").split(",") if t.strip()]

        headers = {
            "Content-Type": "application/json",
            "User-Agent": "botanalyzer-gui/1.0",
        }
        if key:
            headers["Auth-Key"] = key

        rows: list[dict] = []
        if tags:
            per_tag = max(1, min(limit, max(1, int(limit / max(1, len(tags))))))
            for tag in tags:
                payload = json.dumps({"query": "taginfo", "tag": tag, "limit": per_tag}).encode("utf-8")
                req = Request("https://threatfox-api.abuse.ch/api/v1/", data=payload, headers=headers, method="POST")
                with urlopen(req, timeout=timeout) as resp:
                    raw = resp.read().decode("utf-8", errors="replace")
                data = json.loads(raw)
                if not isinstance(data, dict):
                    continue
                query_status = str(data.get("query_status", "query_status != ok"))
                if query_status not in {"ok", "no_results"}:
                    continue
                payload_rows = data.get("data", [])
                if isinstance(payload_rows, list):
                    rows.extend(payload_rows)
                if len(rows) >= limit:
                    break
        else:
            payload = json.dumps({"query": "get_iocs", "days": days}).encode("utf-8")
            req = Request("https://threatfox-api.abuse.ch/api/v1/", data=payload, headers=headers, method="POST")
            with urlopen(req, timeout=timeout) as resp:
                raw = resp.read().decode("utf-8", errors="replace")
            data = json.loads(raw)
            if not isinstance(data, dict):
                raise ValueError("Respuesta ThreatFox invalida.")
            query_status = str(data.get("query_status", "query_status != ok"))
            if query_status not in {"ok", "no_results"}:
                raise ValueError(query_status)
            payload_rows = data.get("data", [])
            if not isinstance(payload_rows, list):
                raise ValueError("Respuesta ThreatFox sin data valida.")
            rows = payload_rows

        rows = rows[:limit]
        keys: set[str] = set()
        for row in rows:
            if isinstance(row, dict):
                keys.update(row.keys())

        preferred = [
            "ioc",
            "ioc_type",
            "threat_type",
            "malware",
            "confidence_level",
            "first_seen",
            "last_seen",
            "reference",
            "tags",
            "reporter",
            "id",
        ]
        ordered = [k for k in preferred if k in keys]
        ordered.extend(sorted(k for k in keys if k not in ordered))
        return ordered, rows

    def _render_threatfox_table(self, columns: list[str], rows: list[dict]) -> None:
        self.threatfox_table.delete(*self.threatfox_table.get_children())
        self.threatfox_table["columns"] = columns
        for col in columns:
            self.threatfox_table.heading(col, text=col)
            self.threatfox_table.column(col, width=140, minwidth=80, anchor="w")

        for row in rows:
            values = []
            for col in columns:
                value = ""
                if isinstance(row, dict):
                    value = row.get(col, "")
                if isinstance(value, (list, tuple)):
                    value = ", ".join(str(item) for item in value)
                elif isinstance(value, dict):
                    value = json.dumps(value, ensure_ascii=False)
                values.append("" if value is None else str(value))
            self.threatfox_table.insert("", "end", values=values)

        self.tf_status.set(f"ThreatFox cargado: {len(rows)} registros.")

    def _extract_threatfox_urls(self, rows: list[dict]) -> list[str]:
        results: list[str] = []
        seen: set[str] = set()
        for row in rows:
            if not isinstance(row, dict):
                continue
            ioc_type = str(row.get("ioc_type", "")).lower()
            ioc = str(row.get("ioc", "")).strip()
            if not ioc:
                continue
            url_value = ""
            if ioc_type in {"url", "uri"}:
                url_value = ioc
            elif ioc_type in {"domain", "hostname"}:
                url_value = f"https://{ioc}"
            else:
                continue
            normalized = self._normalize_url(url_value)
            if not normalized or normalized in seen:
                continue
            seen.add(normalized)
            results.append(normalized)
        return results

    def _append_unique_urls(self, path: Path, urls: list[str]) -> int:
        if not urls:
            return 0
        existing: set[str] = set()
        if path.exists():
            for raw in path.read_text(encoding="utf-8").splitlines():
                norm = self._normalize_url(raw)
                if norm:
                    existing.add(norm)
        added = 0
        lines: list[str] = []
        for url in urls:
            norm = self._normalize_url(url)
            if not norm or norm in existing:
                continue
            existing.add(norm)
            lines.append(norm)
            added += 1
        if not added:
            return 0
        needs_newline = False
        if path.exists():
            existing_text = path.read_text(encoding="utf-8")
            needs_newline = existing_text and not existing_text.endswith("\n")
        path.parent.mkdir(parents=True, exist_ok=True)
        with path.open("a", encoding="utf-8", newline="\n") as handle:
            if needs_newline:
                handle.write("\n")
            for line in lines:
                handle.write(line + "\n")
        return added

    @staticmethod
    def _normalize_url(value: str) -> str:
        line = value.strip()
        if not line or line.startswith("#"):
            return ""
        if "://" not in line:
            line = f"https://{line}"
        try:
            parsed = urlparse(line)
        except Exception:
            return ""
        if parsed.scheme not in {"http", "https"}:
            return ""
        if not parsed.netloc:
            return ""
        return line

    def _pick_python(self) -> None:
        selected = filedialog.askopenfilename(
            title="Selecciona Python ejecutable",
            filetypes=[("Python", "python.exe" if sys.platform.startswith("win") else "python*"), ("Todos", "*.*")],
        )
        if selected:
            self.python_var.set(selected)

    def _use_current_python(self) -> None:
        self.python_var.set(sys.executable)

    def _pick_path(self, var: tk.StringVar, kind: str) -> None:
        current = var.get().strip()
        initial = str(Path(current).parent) if current else str(BASE_DIR)
        if kind == "dir":
            path = filedialog.askdirectory(initialdir=initial, title="Seleccionar carpeta")
        elif kind == "file":
            path = filedialog.askopenfilename(initialdir=initial, title="Seleccionar archivo")
        else:
            path = filedialog.askopenfilename(initialdir=initial, title="Seleccionar ruta (.crx o carpeta)")
            if not path:
                path = filedialog.askdirectory(initialdir=initial, title="Seleccionar carpeta extension")
        if path:
            var.set(path)

    def _append_log(self, text: str) -> None:
        self.log_text.insert("end", text)
        self.log_text.see("end")

    def _clear_log(self) -> None:
        self.log_text.delete("1.0", "end")

    def _refresh_buttons(self) -> None:
        running = self.manager.is_running
        self.run_button.configure(state="disabled" if running else "normal")
        self.stop_button.configure(state="normal" if running else "disabled")

    def _on_process_done(self, code: int) -> None:
        self._append_log(f"\n[EXIT] Proceso finalizado con codigo {code}\n")
        self._refresh_buttons()

    def _run_selected(self) -> None:
        try:
            command = self._build_command()
        except ValueError as exc:
            messagebox.showerror("Error de configuracion", str(exc))
            return

        self._append_log("\n[CMD] " + " ".join(command) + "\n")
        started = self.manager.start(self.root, command, BASE_DIR)
        if not started:
            messagebox.showwarning("Proceso en curso", "Ya hay un proceso ejecutandose.")
            return
        self._refresh_buttons()

    def _stop_process(self) -> None:
        self.manager.stop()
        self._append_log("[INFO] Señal de parada enviada.\n")

    def _build_command(self) -> list[str]:
        python_exe = self.python_var.get().strip()
        if not python_exe:
            raise ValueError("Debes indicar un Python ejecutable.")

        mode = self.script_mode_var.get().strip().lower()
        if mode == "bot":
            return self._build_bot_command(python_exe)
        if mode == "anyrun":
            return self._build_anyrun_command(python_exe)
        if mode == "config":
            return self._build_config_command(python_exe)
        raise ValueError(f"Modo desconocido: {mode}")

    def _build_bot_command(self, python_exe: str) -> list[str]:
        cmd = [python_exe, str(BOT_SCRIPT)]
        cmd += ["--urls", self.bot_urls.get().strip()]
        cmd += ["--done", self.bot_done.get().strip()]
        cmd += ["--dead", self.bot_dead.get().strip()]
        cmd += ["--blocked", self.bot_blocked.get().strip()]
        cmd += ["--workers", str(int(self.bot_workers.get()))]
        cmd += ["--precheck-workers", str(int(self.bot_precheck_workers.get()))]
        cmd += ["--precheck-timeout", str(int(self.bot_precheck_timeout.get()))]
        cmd += ["--page-timeout", str(int(self.bot_page_timeout.get()))]
        cmd += ["--wait-close", str(int(self.bot_wait_close.get()))]
        cmd += ["--button-timeout", str(float(self.bot_button_timeout.get()))]
        cmd += ["--post-load-wait", str(float(self.bot_post_load_wait.get()))]
        cmd += ["--max-url-seconds", str(int(self.bot_max_url_seconds.get()))]
        cmd += ["--max-clicks", str(int(self.bot_max_clicks.get()))]
        cmd += ["--max-frame-depth", str(int(self.bot_max_frame_depth.get()))]
        cmd += ["--max-div-clicks", str(int(self.bot_max_div_clicks.get()))]
        profile = self.bot_profile.get().strip()
        if profile:
            cmd += ["--profile-dir", profile]
        extension = self.bot_extension.get().strip()
        if extension:
            cmd += ["--extension", extension]
        if self.bot_headful.get():
            cmd.append("--headful")
        if self.bot_no_precheck.get():
            cmd.append("--no-precheck")
        if self.bot_no_zip.get():
            cmd.append("--no-zip")
        if self.bot_shared_profile.get():
            cmd.append("--shared-profile")
        if self.bot_auto.get():
            cmd.append("--auto")
        if self.bot_threatfox.get():
            cmd.append("--threatfox")
            tag_value = self.bot_threatfox_tag.get().strip()
            if tag_value:
                cmd += ["--threatfox-tag", tag_value]
            cmd += ["--threatfox-days", str(int(self.bot_threatfox_days.get()))]
            cmd += ["--threatfox-limit", str(int(self.bot_threatfox_limit.get()))]
            key = self.cfg_threatfox_key.get().strip()
            if key:
                cmd += ["--threatfox-key", key]
        return cmd

    def _build_anyrun_command(self, python_exe: str) -> list[str]:
        cmd = [python_exe, str(ANYRUN_SCRIPT)]
        cmd += ["--url", self.anyrun_url.get().strip()]
        cmd += ["--output", self.anyrun_output.get().strip()]
        cmd += ["--profile-dir", self.anyrun_profile.get().strip()]
        cmd += ["--wait-timeout", str(float(self.anyrun_wait_timeout.get()))]
        cmd += ["--page-change-timeout", str(float(self.anyrun_page_change_timeout.get()))]
        cmd += ["--settle-seconds", str(float(self.anyrun_settle_seconds.get()))]
        cmd += ["--max-pages", str(int(self.anyrun_max_pages.get()))]
        cmd += ["--initial-data-timeout", str(float(self.anyrun_initial_timeout.get()))]
        cmd += ["--redirect-stable-seconds", str(float(self.anyrun_redirect_stable.get()))]
        if self.anyrun_headless.get():
            cmd.append("--headless")
        return cmd

    def _build_config_command(self, python_exe: str) -> list[str]:
        cmd = [python_exe, str(CONFIG_SCRIPT)]
        cmd += ["--profile-dir", self.cfg_profile.get().strip()]
        cmd += ["--downloads-dir", self.cfg_downloads.get().strip()]
        extension = self.cfg_extension.get().strip()
        if extension:
            cmd += ["--extension", extension]
        lang = self.cfg_lang.get().strip()
        if lang:
            cmd += ["--lang", lang]
        accept_lang = self.cfg_accept_lang.get().strip()
        if accept_lang:
            cmd += ["--accept-languages", accept_lang]
        open_url = self.cfg_open_url.get().strip()
        if open_url:
            cmd += ["--open-url", open_url]
        if self.cfg_detach.get():
            cmd.append("--detach")
        return cmd


def main() -> int:
    try:
        root = tk.Tk()
        BotanalyzerGUI(root)
        root.mainloop()
        return 0
    except Exception:
        error_path = BASE_DIR / "gui_error.log"
        error_path.write_text(traceback.format_exc(), encoding="utf-8")
        print(f"[ERROR] Fallo iniciando GUI. Revisa: {error_path}")
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
