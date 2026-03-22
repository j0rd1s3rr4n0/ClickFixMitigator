# Changelog (Windows Agent)

All notable changes to the ClickFix Mitigator Windows Agent are documented in this file.

## agent-2026-03-22-gui-alerts - 2026-03-22
- Turned the desktop panel into the primary alert surface:
  - Added a live alert banner in the main window.
  - Added an in-panel alert feed for recent detections.
  - Added a custom popup alert window owned by the agent GUI instead of relying on Windows notifications.
- Expanded control actions in the GUI:
  - Added `Exit agent` and `Minimize to tray` actions in the panel.
  - Added direct `Open alerts` and `Restore clipboard` actions from the live alert area.
- Extended settings management:
  - Added panel-controlled toggles for `show_panel_on_start`, `open_panel_on_alert`, `use_system_notifications`, and `close_to_tray`.
  - Default behavior now prefers the agent GUI and keeps Windows toasts disabled unless explicitly enabled.

## agent-2026-03-15-panel - 2026-03-15
- Desktop control panel introduced:
  - Added a native control panel with logo/header, overview KPIs, alert trend chart, recent alerts, telemetry viewer, settings editor, and embedded terms tab.
  - Tray menu now exposes `Open panel`, `Restore last blocked clipboard`, and `Exit`.
- Mandatory legal acceptance:
  - Added separate Windows Agent terms file `TERMS_AND_CONDITIONS.txt`.
  - Agent now blocks startup until the user explicitly accepts the Windows Agent terms.
  - Acceptance state is versioned and stored locally in `data/agent_state.json`.
- Extended host telemetry:
  - Added periodic host telemetry collection for process snapshot, command-line context, DNS snapshot, antivirus status, CPU, and memory state.
  - Added local persistence of host snapshots for trend and review.
- Local data model update:
  - Expanded `reports` with process/window/action/host snapshot fields.
  - Expanded `stats` with host health.
  - Added `host_snapshots` table.
- Local review surface update:
  - Updated `windows-agent/dashboard.php` to surface host health and latest host telemetry snapshot.
- Release metadata:
  - Version bumped to `agent-2026-03-15-panel`.
  - `README.md` revised to reflect panel UX, telemetry scope, and consent requirements.
