import json
import queue
import threading
from pathlib import Path
from tkinter import BooleanVar, Canvas, PhotoImage, StringVar, Tk, Toplevel
from tkinter import ttk
from tkinter.scrolledtext import ScrolledText
from typing import Any, Dict, List


class AgentControlPanel:
    def __init__(self, controller: Any, logo_path: Path, terms_path: Path, refresh_interval_s: float = 3.0) -> None:
        self.controller = controller
        self.logo_path = logo_path
        self.terms_path = terms_path
        self.refresh_interval_ms = max(1000, int(refresh_interval_s * 1000))
        self.command_queue: "queue.Queue[Any]" = queue.Queue()
        self.thread: threading.Thread | None = None
        self.root: Tk | None = None
        self.notebook = None
        self.alert_tree = None
        self.alert_detail = None
        self.telemetry_text = None
        self.terms_text = None
        self.trend_canvas = None
        self.telemetry_summary = None
        self.alert_feed_text = None
        self.logo_image = None
        self.visible_on_start = True
        self.alert_lookup: Dict[str, Dict[str, Any]] = {}
        self.alert_history: List[Dict[str, Any]] = []
        self.alert_popup: Toplevel | None = None
        self.kpi_vars: Dict[str, StringVar] = {}
        self.setting_vars: Dict[str, Any] = {}
        self.setting_bool_vars: Dict[str, BooleanVar] = {}
        self.profile_var: StringVar | None = None
        self.alert_banner_var: StringVar | None = None

    def start(self, show_on_start: bool = True) -> None:
        if self.thread and self.thread.is_alive():
            self.open()
            return
        self.visible_on_start = show_on_start
        self.thread = threading.Thread(target=self._run, name="agent_control_panel", daemon=True)
        self.thread.start()

    def open(self) -> None:
        self.command_queue.put("show")

    def stop(self) -> None:
        self.command_queue.put("stop")

    def push_alert(self, payload: Dict[str, Any]) -> None:
        self.command_queue.put(("alert", payload))

    def _run(self) -> None:
        self.root = Tk()
        self.profile_var = StringVar(master=self.root, value="balanced")
        self.alert_banner_var = StringVar(master=self.root, value="Defensive console active. Waiting for new alerts.")
        self.root.title("ClickFix Mitigator Agent")
        self.root.geometry("1260x820")
        self.root.minsize(1040, 720)
        self.root.configure(bg="#081019")
        self.root.protocol("WM_DELETE_WINDOW", self._hide_window)
        self._build_ui()
        if not self.visible_on_start:
            self.root.withdraw()
        self._process_commands()
        self._refresh()
        self.root.mainloop()

    def _build_ui(self) -> None:
        assert self.root is not None
        style = ttk.Style(self.root)
        style.theme_use("clam")
        style.configure(".", background="#081019", foreground="#dfe9f2", fieldbackground="#0d1622")
        style.configure("Card.TFrame", background="#0d1622")
        style.configure("CardTitle.TLabel", background="#0d1622", foreground="#9ecbe1", font=("Segoe UI", 10, "bold"))
        style.configure("HeroTitle.TLabel", background="#081019", foreground="#ecf5fb", font=("Segoe UI", 20, "bold"))
        style.configure("HeroSub.TLabel", background="#081019", foreground="#9bb0bf", font=("Segoe UI", 10))
        style.configure("TNotebook", background="#081019", borderwidth=0)
        style.configure("TNotebook.Tab", padding=(14, 8), background="#0d1622", foreground="#bed0de")
        style.map("TNotebook.Tab", background=[("selected", "#132433")], foreground=[("selected", "#f4fbff")])
        style.configure("Treeview", background="#0d1622", fieldbackground="#0d1622", foreground="#e6f0f7", rowheight=28)
        style.configure("Treeview.Heading", background="#132433", foreground="#dff2ff", font=("Segoe UI", 9, "bold"))
        style.configure("TButton", padding=(10, 8))

        shell = ttk.Frame(self.root, padding=16, style="Card.TFrame")
        shell.pack(fill="both", expand=True)

        hero = ttk.Frame(shell, style="Card.TFrame", padding=(16, 16, 16, 10))
        hero.pack(fill="x")

        logo = ttk.Label(hero, style="Card.TFrame")
        if self.logo_path.exists():
            try:
                self.logo_image = PhotoImage(file=str(self.logo_path))
                logo.configure(image=self.logo_image)
            except Exception:
                logo.configure(text="[logo]")
        else:
            logo.configure(text="[logo]")
        logo.grid(row=0, column=0, rowspan=2, sticky="nw", padx=(0, 14))

        ttk.Label(hero, text="ClickFix Mitigator Agent", style="HeroTitle.TLabel").grid(row=0, column=1, sticky="w")
        ttk.Label(
            hero,
            text="Desktop defensive console for alerts, trends, settings, host telemetry, and operator actions.",
            style="HeroSub.TLabel",
        ).grid(row=1, column=1, sticky="w")

        actions = ttk.Frame(hero, style="Card.TFrame")
        actions.grid(row=0, column=2, rowspan=2, sticky="e")
        ttk.Button(actions, text="Exit agent", command=self.controller.stop).pack(side="right")
        ttk.Button(actions, text="Minimize to tray", command=self._hide_window).pack(side="right", padx=(0, 8))
        ttk.Button(actions, text="Restore clipboard", command=self.controller.restore_last_clipboard).pack(side="right", padx=(0, 8))
        ttk.Button(actions, text="Refresh now", command=self._refresh).pack(side="right", padx=(0, 8))

        banner = ttk.Frame(shell, style="Card.TFrame", padding=(14, 10))
        banner.pack(fill="x", pady=(8, 10))
        ttk.Label(banner, text="Live alert console", style="CardTitle.TLabel").pack(anchor="w")
        ttk.Label(
            banner,
            textvariable=self.alert_banner_var,
            background="#0d1622",
            foreground="#f4fbff",
            wraplength=1080,
            font=("Segoe UI", 10, "bold"),
        ).pack(anchor="w", pady=(8, 10))
        banner_actions = ttk.Frame(banner, style="Card.TFrame")
        banner_actions.pack(fill="x")
        ttk.Button(banner_actions, text="Open alerts", command=self._focus_alerts_tab).pack(side="left")
        ttk.Button(banner_actions, text="Dismiss banner", command=self._dismiss_alert_banner).pack(side="left", padx=(8, 0))
        ttk.Button(banner_actions, text="Restore clipboard", command=self.controller.restore_last_clipboard).pack(side="right")

        kpi_row = ttk.Frame(shell, style="Card.TFrame")
        kpi_row.pack(fill="x", pady=(8, 10))
        for key, label in [
            ("total_alerts", "Alerts"),
            ("total_blocks", "Blocks"),
            ("recent_count", "Recent"),
            ("host_health", "Host health"),
        ]:
            card = ttk.Frame(kpi_row, style="Card.TFrame", padding=12)
            card.pack(side="left", fill="both", expand=True, padx=(0, 10))
            ttk.Label(card, text=label, style="CardTitle.TLabel").pack(anchor="w")
            var = StringVar(master=self.root, value="--")
            self.kpi_vars[key] = var
            ttk.Label(card, textvariable=var, font=("Segoe UI", 18, "bold"), background="#0d1622", foreground="#f5fbff").pack(anchor="w", pady=(8, 0))

        notebook = ttk.Notebook(shell)
        self.notebook = notebook
        notebook.pack(fill="both", expand=True)

        overview = ttk.Frame(notebook, style="Card.TFrame", padding=12)
        alerts = ttk.Frame(notebook, style="Card.TFrame", padding=12)
        telemetry = ttk.Frame(notebook, style="Card.TFrame", padding=12)
        settings = ttk.Frame(notebook, style="Card.TFrame", padding=12)
        terms = ttk.Frame(notebook, style="Card.TFrame", padding=12)

        notebook.add(overview, text="Overview")
        notebook.add(alerts, text="Alerts")
        notebook.add(telemetry, text="Telemetry")
        notebook.add(settings, text="Settings")
        notebook.add(terms, text="Terms")

        ttk.Label(overview, text="Alert trend", style="CardTitle.TLabel").pack(anchor="w")
        self.trend_canvas = Canvas(overview, height=250, bg="#09131d", highlightthickness=1, highlightbackground="#183146")
        self.trend_canvas.pack(fill="x", pady=(10, 14))

        ttk.Label(overview, text="Recent in-panel alerts", style="CardTitle.TLabel").pack(anchor="w")
        alert_feed = ScrolledText(overview, height=7, wrap="word", background="#0d1622", foreground="#dbeaf3", insertbackground="#dbeaf3")
        alert_feed.pack(fill="x", pady=(8, 14))
        alert_feed.configure(state="disabled")
        self.alert_feed_text = alert_feed

        summary = ScrolledText(overview, height=12, wrap="word", background="#0d1622", foreground="#dbeaf3", insertbackground="#dbeaf3")
        summary.pack(fill="both", expand=True)
        summary.configure(state="disabled")
        self.telemetry_summary = summary

        tree = ttk.Treeview(alerts, columns=("ts", "action", "process", "reason"), show="headings")
        for column, title, width in [
            ("ts", "Timestamp", 170),
            ("action", "Action", 100),
            ("process", "Process", 180),
            ("reason", "Reason", 420),
        ]:
            tree.heading(column, text=title)
            tree.column(column, width=width, anchor="w")
        tree.pack(fill="both", expand=True)
        tree.bind("<<TreeviewSelect>>", self._on_alert_selected)
        self.alert_tree = tree

        detail = ScrolledText(alerts, height=11, wrap="word", background="#0d1622", foreground="#dbeaf3", insertbackground="#dbeaf3")
        detail.pack(fill="x", pady=(12, 0))
        detail.configure(state="disabled")
        self.alert_detail = detail

        telemetry_box = ScrolledText(telemetry, wrap="word", background="#0d1622", foreground="#dbeaf3", insertbackground="#dbeaf3")
        telemetry_box.pack(fill="both", expand=True)
        telemetry_box.configure(state="disabled")
        self.telemetry_text = telemetry_box

        settings_form = ttk.Frame(settings, style="Card.TFrame")
        settings_form.pack(fill="x")
        ttk.Label(settings_form, text="User profile", style="CardTitle.TLabel").grid(row=0, column=0, sticky="w", pady=6, padx=(0, 12))
        profile_row = ttk.Frame(settings_form, style="Card.TFrame")
        profile_row.grid(row=0, column=1, sticky="ew", pady=6)
        self.profile_combo = ttk.Combobox(
            profile_row,
            textvariable=self.profile_var,
            values=["balanced", "strict", "quiet", "analyst"],
            state="readonly",
            width=18,
        )
        self.profile_combo.pack(side="left")
        ttk.Button(profile_row, text="Apply profile", command=self._apply_profile_to_form).pack(side="left", padx=(8, 0))
        for row, (key, label) in enumerate(
            [
                ("clipboard_poll_interval_s", "Clipboard poll interval (s)"),
                ("run_sequence_timeout_s", "Sequence timeout (s)"),
                ("allow_timeout_s", "Temporary allow timeout (s)"),
                ("min_clipboard_length", "Minimum clipboard length"),
                ("blocked_clipboard_placeholder", "Blocked clipboard placeholder"),
                ("remote_base_url", "Remote base URL"),
                ("remote_report_endpoint", "Remote alert endpoint"),
                ("remote_stats_endpoint", "Remote stats endpoint"),
                ("remote_timeout_s", "Remote timeout (s)"),
                ("remote_client_id", "Remote client_id"),
                ("remote_bearer_token", "Remote bearer token"),
                ("remote_api_key", "Remote API key"),
            ]
        ):
            ttk.Label(settings_form, text=label, style="CardTitle.TLabel").grid(row=row + 1, column=0, sticky="w", pady=6, padx=(0, 12))
            variable = StringVar(master=self.root, value="")
            self.setting_vars[key] = variable
            ttk.Entry(settings_form, textvariable=variable, width=42).grid(row=row + 1, column=1, sticky="ew", pady=6)

        bool_row = len(self.setting_vars) + 2
        for key, label in [
            ("show_panel_on_start", "Show panel on start"),
            ("open_panel_on_alert", "Bring panel to front on alert"),
            ("use_system_notifications", "Enable Windows toast notifications"),
            ("close_to_tray", "Close window to tray instead of stopping"),
            ("remote_enabled", "Enable remote sync"),
            ("remote_verify_tls", "Verify remote TLS certificates"),
            ("remote_include_host_snapshot", "Include host snapshot in remote alerts"),
        ]:
            variable = BooleanVar(master=self.root, value=False)
            self.setting_bool_vars[key] = variable
            ttk.Checkbutton(settings_form, variable=variable, text=label).grid(
                row=bool_row, column=0, columnspan=2, sticky="w", pady=4
            )
            bool_row += 1

        settings_form.columnconfigure(1, weight=1)
        action_row = ttk.Frame(settings_form, style="Card.TFrame")
        action_row.grid(row=bool_row + 1, column=0, columnspan=2, sticky="e", pady=(12, 0))
        ttk.Button(action_row, text="Minimize to tray", command=self._hide_window).pack(side="left")
        ttk.Button(action_row, text="Exit agent", command=self.controller.stop).pack(side="left", padx=(8, 0))
        ttk.Button(action_row, text="Save settings", command=self._save_settings).pack(side="left", padx=(8, 0))

        terms_box = ScrolledText(terms, wrap="word", background="#0d1622", foreground="#dbeaf3", insertbackground="#dbeaf3")
        terms_box.pack(fill="both", expand=True)
        terms_box.insert("1.0", self._load_terms_text())
        terms_box.configure(state="disabled")
        self.terms_text = terms_box

    def _load_terms_text(self) -> str:
        try:
            return self.terms_path.read_text(encoding="utf-8")
        except Exception:
            return "Terms file not available."

    def _hide_window(self) -> None:
        if self.root is not None:
            self.root.withdraw()

    def _dismiss_alert_banner(self) -> None:
        self.alert_banner_var.set("Defensive console active. Waiting for new alerts.")

    def _focus_alerts_tab(self) -> None:
        if self.notebook is not None:
            self.notebook.select(1)
        self.open()

    def _process_commands(self) -> None:
        if self.root is None:
            return
        while True:
            try:
                command = self.command_queue.get_nowait()
            except queue.Empty:
                break
            action = command
            payload = None
            if isinstance(command, tuple) and len(command) == 2:
                action, payload = command
            if action == "show":
                self.root.deiconify()
                self.root.lift()
                self.root.focus_force()
            elif action == "stop":
                self.root.destroy()
                return
            elif action == "alert" and isinstance(payload, dict):
                self._handle_alert(payload)
        self.root.after(300, self._process_commands)

    def _refresh(self) -> None:
        if self.root is None:
            return
        snapshot = self.controller.get_ui_snapshot()
        self._render_kpis(snapshot)
        self._render_trend(snapshot)
        self._render_alert_feed()
        self._render_summary(snapshot)
        self._render_alerts(snapshot)
        self._render_telemetry(snapshot)
        self._render_settings(snapshot)
        self.root.after(self.refresh_interval_ms, self._refresh)

    def _render_kpis(self, snapshot: Dict[str, Any]) -> None:
        counts = snapshot.get("counts", {})
        self.kpi_vars["total_alerts"].set(str(counts.get("total_alerts", 0)))
        self.kpi_vars["total_blocks"].set(str(counts.get("total_blocks", 0)))
        self.kpi_vars["recent_count"].set(str(counts.get("recent_count", 0)))
        self.kpi_vars["host_health"].set(snapshot.get("host_health", "--"))

    def _render_trend(self, snapshot: Dict[str, Any]) -> None:
        if self.trend_canvas is None:
            return
        trend = snapshot.get("trend", [])
        canvas = self.trend_canvas
        canvas.delete("all")
        width = max(canvas.winfo_width(), 640)
        height = max(canvas.winfo_height(), 250)
        canvas.create_text(14, 12, text="Alerts vs blocks trend", anchor="nw", fill="#9ecbe1", font=("Segoe UI", 10, "bold"))
        if not trend:
            canvas.create_text(width / 2, height / 2, text="Waiting for telemetry", fill="#8ea9bb", font=("Segoe UI", 11))
            return
        pad_x = 40
        pad_y = 34
        chart_w = width - pad_x * 2
        chart_h = height - pad_y * 2
        max_value = max(max(int(item.get("alerts", 0)), int(item.get("blocks", 0))) for item in trend) or 1
        for line in range(5):
            y = pad_y + (chart_h / 4) * line
            canvas.create_line(pad_x, y, width - pad_x, y, fill="#183146")

        def make_points(field: str) -> List[float]:
            points: List[float] = []
            for index, item in enumerate(trend):
                x = pad_x + (chart_w / max(1, len(trend) - 1)) * index
                value = int(item.get(field, 0))
                y = pad_y + chart_h - ((value / max_value) * chart_h)
                points.extend([x, y])
            return points

        alert_points = make_points("alerts")
        block_points = make_points("blocks")
        if len(alert_points) >= 4:
            canvas.create_line(*alert_points, fill="#44d5ff", width=3, smooth=True)
        if len(block_points) >= 4:
            canvas.create_line(*block_points, fill="#78efb4", width=3, smooth=True)

    def _render_alert_feed(self) -> None:
        if self.alert_feed_text is None:
            return
        lines: List[str] = []
        for item in self.alert_history[:8]:
            lines.append(
                f"[{item.get('received_at', '--')}] {item.get('action', '--')} | {item.get('process', '--')} | {item.get('reason', '--')}"
            )
            preview = str(item.get("preview", "")).strip()
            if preview:
                lines.append(f"  {preview}")
            lines.append("")
        if not lines:
            lines = ["No in-panel alerts yet."]
        self.alert_feed_text.configure(state="normal")
        self.alert_feed_text.delete("1.0", "end")
        self.alert_feed_text.insert("1.0", "\n".join(lines).strip())
        self.alert_feed_text.configure(state="disabled")

    def _render_summary(self, snapshot: Dict[str, Any]) -> None:
        text = self.telemetry_summary
        if text is None:
            return
        host = snapshot.get("host_snapshot", {})
        summary_lines = [
            f"Version: {snapshot.get('version', '--')}",
            f"Profile: {snapshot.get('settings', {}).get('ui', {}).get('user_profile', 'balanced')}",
            f"Host: {host.get('hostname', '--')}",
            f"CPU: {host.get('cpu_percent', '--')}%",
            f"Memory: {host.get('memory_percent', '--')}%",
            f"Recent DNS records: {len((host.get('dns') or {}).get('recent_records', []))}",
            f"Antivirus products: {len(host.get('antivirus') or [])}",
        ]
        remote = snapshot.get("remote", {})
        if isinstance(remote, dict):
            summary_lines.extend(
                [
                    "",
                    f"Remote sync: {'enabled' if remote.get('enabled') else 'disabled'}",
                    f"Remote base URL: {remote.get('base_url', '--') or '--'}",
                    f"Remote auth: {remote.get('auth_mode', '--')}",
                    f"Remote client_id: {remote.get('client_id', '--')}",
                ]
            )
        text.configure(state="normal")
        text.delete("1.0", "end")
        text.insert("1.0", "\n".join(summary_lines))
        text.configure(state="disabled")

    def _render_alerts(self, snapshot: Dict[str, Any]) -> None:
        if self.alert_tree is None:
            return
        self.alert_lookup = {}
        tree = self.alert_tree
        for item in tree.get_children():
            tree.delete(item)
        for row in snapshot.get("recent_alerts", []):
            item_id = tree.insert(
                "",
                "end",
                values=(
                    row.get("received_at", ""),
                    row.get("action_taken", ""),
                    row.get("active_process", ""),
                    row.get("message", ""),
                ),
            )
            self.alert_lookup[item_id] = row

    def _render_telemetry(self, snapshot: Dict[str, Any]) -> None:
        if self.telemetry_text is None:
            return
        host = snapshot.get("host_snapshot", {})
        payload = {
            "antivirus": host.get("antivirus", []),
            "dns": host.get("dns", {}),
            "processes": host.get("processes", []),
        }
        self.telemetry_text.configure(state="normal")
        self.telemetry_text.delete("1.0", "end")
        self.telemetry_text.insert("1.0", json.dumps(payload, indent=2, ensure_ascii=False))
        self.telemetry_text.configure(state="disabled")

    def _render_settings(self, snapshot: Dict[str, Any]) -> None:
        settings = snapshot.get("settings", {})
        sensitivity = settings.get("sensitivity", {})
        ui = settings.get("ui", {})
        remote = settings.get("remote", {})
        telemetry = settings.get("telemetry", {})
        self.profile_var.set(str(ui.get("user_profile", "balanced") or "balanced"))
        for key in self.setting_vars:
            if key in sensitivity:
                self.setting_vars[key].set(str(sensitivity.get(key, "")))
            elif key == "remote_client_id":
                self.setting_vars[key].set(str(telemetry.get("client_id", "")))
            elif key.startswith("remote_"):
                self.setting_vars[key].set(str(remote.get(key.replace("remote_", ""), "")))
        for key in self.setting_bool_vars:
            if key.startswith("remote_"):
                self.setting_bool_vars[key].set(bool(remote.get(key.replace("remote_", ""), False)))
            else:
                self.setting_bool_vars[key].set(bool(ui.get(key, False)))

    def _on_alert_selected(self, _event: Any) -> None:
        if self.alert_tree is None or self.alert_detail is None:
            return
        selected = self.alert_tree.selection()
        if not selected:
            return
        row = self.alert_lookup.get(selected[0], {})
        self.alert_detail.configure(state="normal")
        self.alert_detail.delete("1.0", "end")
        self.alert_detail.insert("1.0", json.dumps(row, indent=2, ensure_ascii=False))
        self.alert_detail.configure(state="disabled")

    def _save_settings(self) -> None:
        updates = {key: value.get().strip() for key, value in self.setting_vars.items()}
        updates["user_profile"] = self.profile_var.get().strip() or "balanced"
        for key, value in self.setting_bool_vars.items():
            updates[key] = "1" if value.get() else "0"
        self.controller.save_settings(updates)
        self._refresh()

    def _apply_profile_to_form(self) -> None:
        profile = self.profile_var.get().strip() or "balanced"
        presets = self.controller.list_user_profiles()
        preset = presets.get(profile) if isinstance(presets, dict) else None
        if not isinstance(preset, dict):
            return
        sensitivity = preset.get("sensitivity", {}) if isinstance(preset.get("sensitivity"), dict) else {}
        ui = preset.get("ui", {}) if isinstance(preset.get("ui"), dict) else {}
        remote = preset.get("remote", {}) if isinstance(preset.get("remote"), dict) else {}
        for key, variable in self.setting_vars.items():
            if key in sensitivity:
                variable.set(str(sensitivity.get(key, "")))
            elif key.startswith("remote_") and key.replace("remote_", "") in remote:
                variable.set(str(remote.get(key.replace("remote_", ""), "")))
        for key, variable in self.setting_bool_vars.items():
            if key in ui:
                variable.set(bool(ui.get(key, False)))
            elif key.startswith("remote_") and key.replace("remote_", "") in remote:
                variable.set(bool(remote.get(key.replace("remote_", ""), False)))

    def _handle_alert(self, payload: Dict[str, Any]) -> None:
        if self.root is None:
            return
        self.alert_history.insert(0, payload)
        self.alert_history = self.alert_history[:30]
        self.alert_banner_var.set(
            f"{payload.get('action', 'Alert')} | {payload.get('reason', 'Suspicious activity detected')} | {payload.get('process', 'Unknown process')}"
        )
        self._render_alert_feed()
        self._show_alert_popup(payload)

    def _show_alert_popup(self, payload: Dict[str, Any]) -> None:
        if self.root is None:
            return
        if self.alert_popup is not None:
            try:
                self.alert_popup.destroy()
            except Exception:
                pass
            self.alert_popup = None

        popup = Toplevel(self.root)
        popup.title("ClickFix Mitigator Alert")
        popup.configure(bg="#081019")
        popup.attributes("-topmost", True)
        popup.resizable(False, False)
        popup.geometry("520x220+980+120")
        popup.transient(self.root)

        frame = ttk.Frame(popup, padding=14, style="Card.TFrame")
        frame.pack(fill="both", expand=True)
        ttk.Label(frame, text="Suspicious activity intercepted", style="CardTitle.TLabel").pack(anchor="w")
        ttk.Label(
            frame,
            text=f"{payload.get('action', '--')} | {payload.get('reason', '--')}",
            background="#0d1622",
            foreground="#f4fbff",
            wraplength=460,
            font=("Segoe UI", 10, "bold"),
        ).pack(anchor="w", pady=(8, 6))
        ttk.Label(
            frame,
            text=f"Process: {payload.get('process', '--')} | Window: {payload.get('window', '--')}",
            background="#0d1622",
            foreground="#bcd2df",
            wraplength=460,
        ).pack(anchor="w")
        ttk.Label(
            frame,
            text=str(payload.get("preview", "")),
            background="#0d1622",
            foreground="#dbeaf3",
            wraplength=460,
        ).pack(anchor="w", pady=(8, 10))

        actions = ttk.Frame(frame, style="Card.TFrame")
        actions.pack(fill="x")
        ttk.Button(actions, text="Open alerts", command=lambda: [self._focus_alerts_tab(), popup.destroy()]).pack(side="left")
        ttk.Button(actions, text="Restore clipboard", command=self.controller.restore_last_clipboard).pack(side="left", padx=(8, 0))
        ttk.Button(actions, text="Dismiss", command=popup.destroy).pack(side="right")

        self.alert_popup = popup
        popup.after(12000, lambda: popup.winfo_exists() and popup.destroy())
