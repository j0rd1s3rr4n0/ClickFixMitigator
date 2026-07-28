import hashlib
import json
from pathlib import Path
from tkinter import BooleanVar, Tk, messagebox
from tkinter import ttk
from tkinter.scrolledtext import ScrolledText
from typing import Dict


DEFAULT_TERMS = """ClickFix Mitigator Windows Agent - Terms & Conditions

This Windows agent is a defensive local application designed to detect suspicious clipboard and guided-execution behavior on Windows.

By accepting and using this application, you explicitly acknowledge and accept that:

1. The agent may inspect clipboard content locally to detect suspicious command sequences.
2. The agent may collect host telemetry required for defensive analysis, including active process context, process command line, DNS configuration/cache summary, antivirus status, alert history, and local security events.
3. This data may be stored locally in logs and SQLite files for investigation, triage, trend analysis, and evidence review.
4. You must use the agent only for defensive, administrative, or authorized security operations.
5. Detection results are operational signals, not automatic attribution or legal conclusions.
6. If your organization forwards agent telemetry elsewhere, you are responsible for notifying users and complying with applicable law and policy.

If you do not explicitly accept these terms, you must not use the Windows agent.
"""


def _read_terms(terms_path: Path) -> str:
    if terms_path.exists():
        try:
            return terms_path.read_text(encoding="utf-8")
        except Exception:
            return DEFAULT_TERMS
    return DEFAULT_TERMS


def _load_state(state_path: Path) -> Dict[str, object]:
    if not state_path.exists():
        return {}
    try:
        return json.loads(state_path.read_text(encoding="utf-8"))
    except Exception:
        return {}


def _write_state(state_path: Path, payload: Dict[str, object]) -> None:
    state_path.parent.mkdir(parents=True, exist_ok=True)
    state_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")


def ensure_agent_terms_acceptance(config: Dict[str, object], base_dir: Path) -> bool:
    consent_cfg = config.get("consent", {})
    if not isinstance(consent_cfg, dict):
        consent_cfg = {}
    if not bool(consent_cfg.get("required", True)):
        return True

    terms_version = str(consent_cfg.get("terms_version", "2026-03-15-agent"))
    state_path = Path(consent_cfg.get("state_path", "data/agent_state.json"))
    terms_path = Path(consent_cfg.get("terms_path", "TERMS_AND_CONDITIONS.txt"))
    if not state_path.is_absolute():
        state_path = (base_dir / state_path).resolve()
    if not terms_path.is_absolute():
        terms_path = (base_dir / terms_path).resolve()

    terms_text = _read_terms(terms_path)
    terms_hash = hashlib.sha256(terms_text.encode("utf-8", errors="ignore")).hexdigest()
    state = _load_state(state_path)
    if (
        str(state.get("accepted_terms_version", "")) == terms_version
        and str(state.get("accepted_terms_hash", "")) == terms_hash
        and bool(state.get("accepted", False))
    ):
        return True

    accepted = {"value": False}

    root = Tk()
    root.title("ClickFix Mitigator Agent - Terms")
    root.geometry("820x680")
    root.minsize(720, 580)

    outer = ttk.Frame(root, padding=18)
    outer.pack(fill="both", expand=True)

    ttk.Label(
        outer,
        text="Windows Agent Terms & Conditions",
        font=("Segoe UI", 17, "bold"),
    ).pack(anchor="w")
    ttk.Label(
        outer,
        text="Explicit acceptance is required before the agent can run.",
        font=("Segoe UI", 10),
    ).pack(anchor="w", pady=(4, 10))

    text = ScrolledText(outer, wrap="word", height=24)
    text.insert("1.0", terms_text)
    text.configure(state="disabled")
    text.pack(fill="both", expand=True)

    checkbox_var = BooleanVar(master=root, value=False)
    ttk.Checkbutton(
        outer,
        variable=checkbox_var,
        text=(
            "I have read and explicitly accept the Windows Agent Terms & Conditions, "
            "including the local defensive telemetry described above."
        ),
    ).pack(anchor="w", pady=(12, 12))

    action_row = ttk.Frame(outer)
    action_row.pack(fill="x")

    def accept() -> None:
        if not checkbox_var.get():
            messagebox.showerror(
                "Acceptance required",
                "You must explicitly confirm acceptance before using the Windows agent.",
                parent=root,
            )
            return
        _write_state(
            state_path,
            {
                "accepted": True,
                "accepted_terms_version": terms_version,
                "accepted_terms_hash": terms_hash,
                "accepted_at": __import__("time").strftime("%Y-%m-%dT%H:%M:%SZ", __import__("time").gmtime()),
            },
        )
        accepted["value"] = True
        root.destroy()

    def decline() -> None:
        accepted["value"] = False
        root.destroy()

    ttk.Button(action_row, text="Exit", command=decline).pack(side="right")
    ttk.Button(action_row, text="Accept and continue", command=accept).pack(side="right", padx=(0, 10))

    root.protocol("WM_DELETE_WINDOW", decline)
    root.mainloop()
    return accepted["value"]
