# Changelog (Windows Agent)

All notable changes to the ClickFix Mitigator Windows Agent are documented in this file.

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
