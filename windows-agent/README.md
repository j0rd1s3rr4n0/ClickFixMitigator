# Windows Agent - ClickFix Mitigator

Revision: 2026-05-18
Version: agent-2026-05-18-paste-intercept

The Windows Agent is a separate desktop defensive application for ClickFix Mitigator. It is built for host-side visibility and intervention on Windows systems where clipboard abuse, guided execution, and suspicious paste-then-run flows need to be interrupted before execution.

Unlike the browser extension, the Windows Agent can inspect local execution context and host telemetry to support stronger defensive review.

## What it does

- Monitors clipboard changes for suspicious command payloads.
- Detects guided execution flows such as:
  - `Win + R -> paste -> Enter`
  - `Win + E / address bar -> paste -> Enter`
  - clipboard-command patterns commonly used in ClickFix-style lures
- Intercepts common paste hotkeys (`Ctrl+V`, `Shift+Insert`, `Ctrl+Shift+V`) before forwarding them. Safe clipboard text is pasted normally; suspicious command text is quarantined instead.
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
- `telemetry.client_id`: pseudonymous installation id used if remote sync is enabled
- `remote.*`: optional sync to the ClickFix web platform via bearer token or API key
- `ui.*`: desktop panel startup behavior and refresh interval
- `ui.user_profile`: base preset for different user types (`balanced`, `strict`, `quiet`, `analyst`)
- `ui.open_panel_on_alert`: bring the panel to front when a detection is raised
- `ui.use_system_notifications`: optional fallback to Windows toast notifications
- `ui.close_to_tray`: keep the app living in the tray when the window is closed/minimized
- `consent.*`: required terms version, terms file path, acceptance state path

### Built-in user profiles

The agent control panel now includes four presets so the same binary can fit different usage patterns:

- `balanced`: default for daily use
- `strict`: earlier alerting and tighter runtime posture
- `quiet`: less disruptive UI behavior with fewer interruptions
- `analyst`: more visible telemetry and more aggressive investigation defaults

The profile selector updates the settings form before saving, so operators can start from a sane preset and then adjust individual values.

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

## Optional remote sync with the ClickFix web platform

The agent can now forward alerts and periodic stats to the web platform.

Configuration keys:

- `remote.enabled`
- `remote.base_url`
- `remote.report_endpoint`
- `remote.stats_endpoint`
- `remote.bearer_token` or `remote.api_key`
- `remote.timeout_s`
- `remote.verify_tls`
- `remote.include_host_snapshot`

Notes:

- Remote sync is disabled by default.
- The agent generates a pseudonymous `telemetry.client_id` if one does not exist.
- Use `clickfix-report.php` as the default report/stats endpoint unless you front it differently.
- Prefer bearer or API keys with the minimum required scope.

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
