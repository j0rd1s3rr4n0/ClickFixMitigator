# Windows Agent - ClickFix Mitigator

Revision: 2026-03-22
Version: agent-2026-03-22-gui-alerts

The Windows Agent is a separate desktop defensive application for ClickFix Mitigator. It is built for host-side visibility and intervention on Windows systems where clipboard abuse, guided execution, and suspicious paste-then-run flows need to be interrupted before execution.

Unlike the browser extension, the Windows Agent can inspect local execution context and host telemetry to support stronger defensive review.

## What it does

- Monitors clipboard changes for suspicious command payloads.
- Detects guided execution flows such as:
  - `Win + R -> paste -> Enter`
  - `Win + E / address bar -> paste -> Enter`
  - clipboard-command patterns commonly used in ClickFix-style lures
- Lets the user explicitly allow or block suspicious clipboard execution.
- Replaces blocked clipboard content with a safe placeholder until restored.
- Exposes a desktop control panel with:
  - alert overview
  - recent alerts
  - trend view
  - host telemetry view
  - settings editor
  - embedded agent terms view
- Uses its own GUI alert surface:
  - live alert banner in the console
  - in-panel alert feed
  - custom popup alerts owned by the app
  - optional Windows toast notifications only if explicitly enabled
- Stores local evidence for operational review in SQLite and JSON logs.

## Additional host telemetry

The Windows Agent can collect more local defensive context than the browser extension, including:

- active process name
- process command lines
- window title / execution context
- DNS configuration and recent DNS cache records
- antivirus product status
- CPU and memory state
- local alert history and trend data

This is intended for defensive triage and local incident review.

## Mandatory terms acceptance

The agent now requires explicit acceptance of its own Windows Agent Terms and Conditions before it can be used.

- Terms file: `windows-agent/TERMS_AND_CONDITIONS.txt`
- Acceptance state: `windows-agent/data/agent_state.json`
- If the user does not explicitly accept the terms, the agent exits.

This is separate from the browser extension legal flow.

## Requirements

- Windows 10/11
- Python 3.10+
- Permissions to inspect clipboard and local process state

## Installation

```powershell
cd windows-agent
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## Configuration

Edit `config.json` to control rules, hotkeys, local telemetry, UI behavior, and consent enforcement.

Key sections:

- `rules.*`: suspicious patterns, exclusions, allowlists
- `sensitivity.*`: polling, thresholds, clipboard placeholder, timeout values
- `hotkeys.*`: monitored sequences and restore shortcut
- `telemetry.*`: SQLite/log paths and host snapshot interval
- `ui.*`: desktop panel startup behavior and refresh interval
- `ui.open_panel_on_alert`: bring the panel to front when a detection is raised
- `ui.use_system_notifications`: optional fallback to Windows toast notifications
- `ui.close_to_tray`: keep the app living in the tray when the window is closed/minimized
- `consent.*`: required terms version, terms file path, acceptance state path

## Run

```powershell
python agent.py
```

On startup the agent will:

1. Require explicit acceptance of the Windows Agent terms.
2. Start clipboard and hotkey monitoring.
3. Start the desktop control panel.
4. Collect periodic host telemetry snapshots.
5. Raise alerts through the agent GUI itself and expose tray actions for opening the panel, restoring blocked clipboard content, and exiting.

## Local storage

The agent writes local defensive data to:

- `windows-agent/data/clickfix.sqlite`
- `windows-agent/agent.log`
- `windows-agent/agent-debug.log`
- `windows-agent/alertsites`

SQLite now includes:

- `reports`
- `stats`
- `host_snapshots`

## Local dashboard

A lightweight PHP dashboard is still available for local review:

```powershell
php -S 0.0.0.0:8010 -t windows-agent
```

Then open:

- `http://localhost:8010/dashboard.php`

## Notes

- The `keyboard` hook library may require elevated execution in some environments.
- The tray shortcut `Ctrl+Shift+U` restores the last blocked clipboard item by default.
- The Windows Agent is a defensive monitoring component. Detections are operational signals and still require human review.
