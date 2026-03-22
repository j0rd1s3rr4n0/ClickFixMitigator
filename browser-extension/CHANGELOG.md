# Changelog (ClickFix Mitigator Extension)

All notable changes to the extension are documented in this file.

## 0.4.10 - 2026-03-22
- Added a Firefox-specific Manifest V3 package path with `background.scripts` instead of a Chrome-only service worker background.
- Added `manifest.firefox.json` with `browser_specific_settings.gecko` metadata for Firefox distribution/review.
- Added `build-firefox.ps1` to generate a staged Firefox build and `.xpi` package from the shared extension codebase.
- Added a dedicated Firefox Android manifest/package path with `gecko_android` metadata and its own build script.
- Updated extension docs with Firefox build, temporary install, and packaging instructions.

## 0.4.9 - 2026-03-09
- Screenshot semantics update:
  - Extension now sends only `after` screenshot evidence for alert events.
  - `before` capture flow in extension is disabled to avoid inconsistent timing snapshots.
- Added explicit host exclusions so extension logic does not run on:
  - `jordiserrano.me`, `*.jordiserrano.me`
  - `any.run`, `*.any.run`
- Updated extension package version in `manifest.json`.

## 0.4.8 - 2026-03-09
- Host-level hard exclusion added so the extension does not activate on:
  - `jordiserrano.me`
  - `*.jordiserrano.me`
  - `any.run`
  - `*.any.run`
- Added background-side safeguard to ignore residual report/event flows from excluded hosts.
- Messaging sync now clears stale in-browser notifications for messages removed/deactivated on backend.
- Updated extension package version in `manifest.json`.

## 0.4.7 - 2026-03-09
- Added script-execution lock for blocked pages:
  - Per-tab network blocking for script resources using session DNR rules.
  - CSP override (`script-src 'none'`) on blocked main-frame loads.
  - Runtime in-page hardening in injected guard (`eval`, `Function`, timers, dynamic script insertion).
- Added temporary unlock flow tied to blocked-page actions:
  - `Permitir una vez` and `Permitir por sesion` now disable script lock before reload for the current host/session.
  - `Permitir siempre` disables script lock and persists allowlist entry.
- Manual report queue now tags events explicitly as `event_type = manual_report` for downstream dashboard/reporting consistency.
- Updated extension package version in `manifest.json` and revision date in `README.md`.

## 0.4.6 - 2026-03-08
- Fixed screenshot timing race so `before` capture is resolved before UI block/alert flow.
- Added per-tab pending capture synchronization and fallback `before` capture to avoid `before` showing post-alert state.
- `pageVisit` now includes capture phases (`dom_ready`, `visible`, `url_change`, `load_complete`) and forces refresh on `load_complete`.
- Updated extension package version in `manifest.json`.

## 0.4.5 - 2026-03-05
- Release metadata sync with Web `0.9.22`.
- Updated extension package version in `manifest.json`.

## 0.4.4 - 2026-03-01
- Release metadata sync.
- Enforced release discipline: every extension change must include version bump + changelog entry.

## 0.4.3 - 2026-03-01
- Switched public score-config source from direct `clickfix-score-config.json` to `api/score-config.php` to avoid WAF/ModSecurity false positives on `config.json` URL patterns.
- Added extension polling for backend operator messages (`api/messages.php`) with local dedupe and in-browser notifications.
- Added local state key `extensionMessageSeenIds` to avoid repeated notification delivery.
- Added optional (default OFF) screenshot capture for ClickFix detections (`before/after`) with privacy warning in Options, plus backend support to store images in `data/scans/`.

## 0.4.2 - 2026-03-01
- Default deploy origin changed to `https://clickfix.jordiserrano.me`.
- Default deploy base path changed to root (`""`), so API/report URLs resolve directly from the subdomain.
- Updated extension docs/examples to use subdomain-root endpoints.

## 0.4.1 - 2026-03-01
- Switched backend URLs to deploy-origin/path composition (`CLICKFIX_DEPLOY_ORIGIN` + `CLICKFIX_DEPLOY_BASE_PATH`) to avoid Linux path case issues.
- Updated trusted endpoint validation to follow the configured deploy path prefix.
- Default deploy target now points to `https://clickfix.jordiserrano.me/` (root path).

## 0.4.0 - 2026-03-01
- Added premium API auth flow with short-lived access token + refresh rotation.
- Added signed premium score-config retrieval and client-side signature verification.
- Added report sending with bearer token support and automatic token refresh retry.
- Added endpoint hardening to only trust configured `<deploy-origin>/<deploy-path>/*` reporting targets.
- Added unsafe download blocking engine and Shadow AI monitoring event reporting.

## 0.3.49 - 2026-02-28
- Added configurable alert filter by color (`green`, `yellow`, `orange`, `red`) in popup and options.
- Alerts below the selected color threshold are now suppressed (banner, notification, and block page UI).

## 0.3.48 - 2026-02-28
- Updated score-to-color thresholds to: `>40 red`, `30-40 orange`, `16-29 yellow`, `0-15 green`.
- Applied the same thresholds across alert banner, popup history, and options history.

## 0.3.47 - 2026-02-28
- Fixed live alert banner refresh so confidence ring and score label update on subsequent detections.

## 0.3.46 - 2026-02-28
- Added progressive confidence score escalation when repeated/new detections continue on the same site/session.
- Included progressive scoring factor in score breakdown.

## 0.3.45 - 2026-02-28
- Added server-managed scoring sync from `clickfix-score-config.json`.
- Locked local score editing when config is managed from server.

## 0.3.44 ? 2026-02-28
- Added configurable scoring weights and rule points in the options page.
- Synced scoring configuration with background calculations.

## 0.3.43 — 2026-02-28
- Kept the alert score ring visible when messages are long (banner body scrolls, score stays in view).
- Minor UI hardening for large alert payloads.

## 0.3.42 — 2026-02-28
- Added multi-component scoring (signals, clipboard, context) and surfaced score breakdown.
- Included install channel metadata in reporting and analytics.
- Updated alerts UI with severity color and score presentation.
- Added i18n keys for scoring across locales.
