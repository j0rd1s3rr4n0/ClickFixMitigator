# CHANGELOG

## Unreleased - 2026-03-22

### Windows Agent GUI hardening
- `windows-agent/agent.py`: Promoted the agent GUI to the primary alert surface, added panel-driven alert delivery, settings toggles for GUI behavior, and bumped the runtime version to `agent-2026-03-22-gui-alerts`.
- `windows-agent/control_panel.py`: Rebuilt the desktop panel into a real console with live alert banner, in-panel alert feed, custom popup alerts, and direct exit/minimize/operator actions.
- `windows-agent/config.json`: Added GUI behavior flags for panel-on-alert, optional Windows toasts, and tray-first close behavior.
- `windows-agent/README.md` + `windows-agent/CHANGELOG.md`: Updated the Windows Agent docs and release notes for the new GUI-first workflow.

### Firefox extension packaging
- `browser-extension/manifest.json`: Version bump to `0.4.10`.
- `browser-extension/manifest.firefox.json`: Added a Firefox-specific MV3 manifest using `background.scripts` plus `browser_specific_settings.gecko` metadata.
- `browser-extension/build-firefox.ps1`: Added a local build script that stages a Firefox package and emits a `.xpi`.
- `browser-extension/README.md`: Added Firefox build/install/store packaging instructions.

## Unreleased - 2026-03-15

### Windows Agent uplift
- `windows-agent/agent.py`: Promoted the Windows agent to a desktop defensive console with native panel support, periodic host telemetry capture, richer local evidence, tray action improvements, and version bump `agent-2026-03-15-panel`.
- `windows-agent/control_panel.py`: Added the desktop UI surface for alerts, trends, telemetry, settings, and embedded terms review.
- `windows-agent/host_telemetry.py`: Added local defensive collection for process snapshot, command lines, DNS state, antivirus status, CPU, and memory.
- `windows-agent/consent_dialog.py` + `windows-agent/TERMS_AND_CONDITIONS.txt`: Added a separate mandatory Windows Agent terms workflow with explicit acceptance required before use.
- `windows-agent/config.json` + `windows-agent/data/clickfix.sql`: Expanded agent configuration and SQLite schema for panel/consent/host telemetry.
- `windows-agent/dashboard.php`: Surfaced host-health status and latest local host telemetry snapshot in the optional local dashboard.
- `windows-agent/README.md` + `windows-agent/CHANGELOG.md`: Updated agent documentation and component-level release notes.

## Unreleased - 2026-03-12

### Documentation refresh
- `README.md`: Rewritten with clean UTF-8 text, fixed GitHub badge/repository links (`j0rd1s3rr4n0`), and updated capability overview (evidence workflow, investigation, analytics, host exclusions).
- `PrivacyPolicy.md`: Reworked privacy policy with current data handling scope (screenshots, user-scoped API keys, explicit third-party intel queries, no external tracking by default in hardened deployments).
- `browser-extension/README.md`: Revision/date and privacy references aligned with current release behavior.

### Web docs/policy sync
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.28 - 2026-03-12` for docs/privacy alignment.
- `Web/ClickFix/PrivacyPolicy.html`: Last updated date synchronized to `2026-03-12` across locales and metadata.

### API integration security (user accounts)
- `Web/ClickFix/src/clickfix_core.php`: Added secure user platform API-key model (hash-only storage, expiration, revocation, per-key rate bucket/scopes) and hybrid auth (`Bearer` or `X-API-Key`).
- `Web/ClickFix/api/intel.php` + `Web/ClickFix/api/lookup.php`: Added API-key auth support and hardened context access for low-privilege keys.
- `Web/ClickFix/dashboard.php`: Added platform API-key management UI in Investigation (create/revoke/status + one-time key reveal).
- `Web/ClickFix/api/INTEGRATIONS.md` + `Web/ClickFix/api/INTEGRACIONES_ES.md`: Added updated integration docs for user API-key flow and hardening practices.

### About page refinement
- `Web/ClickFix/dashboard.php`: Updated `Acerca/About` messaging to better project positioning and professionalism (mission, real-ops focus, security principles, and resilience outcomes).

### Dashboard language expansion
- `Web/ClickFix/dashboard.php`: Added dashboard language support for `ca`, `de`, and `fr` (UI selector, user/admin language editors, and i18n fallback mapping `ca->es`, `de/fr->en`).

## Unreleased - 2026-03-10

### Version bumps
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.27 - 2026-03-10`.

### Bulk review UX for alerts
- `Web/ClickFix/dashboard.php`: Added multi-select bulk review flow (`review_bulk`) in classic alerts table with quick selectors and batch status updates.

### Monetization panel targeting
- `Web/ClickFix/dashboard.php`: Expanded support/ad sidebar visibility to guest + `analyst_jr`/`analyst_mid`, keeping senior/admin views cleaner.
- Added explicit ad placeholder UI when AdSense is not configured.

### Home map load fix (CSP-safe)
- `Web/ClickFix/dashboard.php`: Switched Leaflet includes from CDN to local self-hosted files.
- `Web/ClickFix/assets/vendor/leaflet/*`: Added Leaflet 1.9.4 vendor assets (JS/CSS + required images) so maps load under strict `self` CSP.

## Unreleased - 2026-03-09

### Version bumps
- `browser-extension/manifest.json`: Version bump to `0.4.9`.
- `browser-extension/CHANGELOG.md`: Added release entry `0.4.9 - 2026-03-09`.
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.26 - 2026-03-09`.

### Screenshot pipeline rework (before/after)
- `browser-extension/background.js`: Extension now sends `scan_after_image` only for detections (`scan_before_image = null`).
- `Web/ClickFix/clickfix-report.php`: Added server-side `before` capture via Site-Shot after receiving `after`, with validation and size limits.
- `Web/ClickFix/.env.security.example`: Added `CLICKFIX_SITESHOT_API_KEY` configuration entry.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/index.php`: Updated UI copy to match new capture semantics.

## Unreleased - 2026-03-09

### Version bumps
- `browser-extension/manifest.json`: Version bump to `0.4.8`.
- `browser-extension/CHANGELOG.md`: Added release entry `0.4.8 - 2026-03-09`.
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.25 - 2026-03-09`.

### Messaging lifecycle controls (web + extension)
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Added message rectification, selective/full history cleanup, and permanent delete from platform history.
- `browser-extension/background.js`: Added stale-notification cleanup so backend-removed/deactivated messages are cleared from extension notifications on refresh.

### Host exclusions + API key hardening
- `browser-extension/content-script.js` + `browser-extension/background.js`: Added hard extension exclusion for `jordiserrano.me` and `any.run` (including subdomains).
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: API keys now render obfuscated by default and only show in clear text after explicit user action.

## Unreleased - 2026-03-09

### Version bumps
- `browser-extension/manifest.json`: Version bump to `0.4.7`.
- `browser-extension/README.md`: Revision updated to `2026-03-09`.
- `browser-extension/CHANGELOG.md`: Added release entry `0.4.7 - 2026-03-09`.
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.24 - 2026-03-09`.

### Script execution lock on blocked pages
- `browser-extension/background.js`: Added per-tab DNR session rules to block script resources and enforce CSP `script-src 'none'` while a page is blocked.
- `browser-extension/content-script.js` + `browser-extension/block-all-inject.js`: Added in-page execution lock/hardening and explicit unlock path for `allow once`/`allow session`/`allow always` actions.
- `browser-extension/manifest.json`: Added `declarativeNetRequest` permission required for per-tab script-block rules.

### Manual report telemetry + recurrence markers (dashboard)
- `browser-extension/background.js` + `Web/ClickFix/clickfix-report.php`: Manual reports now normalize to `event_type=manual_report` for consistent downstream handling.
- `Web/ClickFix/src/clickfix_core.php` + `Web/ClickFix/dashboard.php`: Added manual report IP/extension visibility and blocked-history recurrence markers (`REINCIDENTE`) by domain/IP.

## Unreleased - 2026-03-08

### Version bumps
- `browser-extension/manifest.json`: Version bump to `0.4.6`.
- `browser-extension/README.md`: Revision updated to `2026-03-08`.
- `browser-extension/CHANGELOG.md`: Added release entry `0.4.6 - 2026-03-08`.
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.23 - 2026-03-08`.

### Screenshot timeline fix (before/after)
- `browser-extension/background.js`: Synchronized pending `before` capture per tab and added a fallback pre-block capture so `before` is not taken after alert rendering.
- `browser-extension/content-script.js`: `pageVisit` now sends capture phases (including `load_complete`) so the final pre-alert page state can be captured reliably.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/index.php`: Updated evidence copy to explicitly describe `before` (page load) and `after` (alert trigger) semantics.

### Privacy hard-stop (no third-party tracking widgets)
- `Web/ClickFix/src/clickfix_core.php`: Added `CLICKFIX_DISABLE_EXTERNAL_TRACKING` gate (safe default ON) to disable monetization third-party widgets and enforce self-only CSP when active.
- `Web/ClickFix/.env.security.example`: Added `CLICKFIX_DISABLE_EXTERNAL_TRACKING=1` to the server env template.

### Backend performance (web)
- `Web/ClickFix/src/clickfix_core.php`: Added short-lived caching (APCu + in-request fallback) for expensive dashboard datasets (`live_metrics`, `recent_reports` no-search, `analytics_overview`, `ml_insights`, `home_maps_dataset`) to reduce repeated SQL and CPU under live refresh.
- `Web/ClickFix/dashboard.php`: Removed duplicate `clickfix_recent_users` query by reusing the preloaded directory dataset for admin views.
- `Web/ClickFix/src/clickfix_core.php`: Added additional short-lived caching and optimized query paths for operational datasets (`recent_list_actions`, `recent_appeals`, `recent_access_requests`, `recent_extension_clients`, `extension_user_links`, `recent_extension_messages`, `recent_users`, `recent_investigations`, `community_investigations`, `data_center_snapshot`, `table_recent`, `report_schedules`, `period_report`).
- `Web/ClickFix/src/clickfix_core.php`: Refactored `clickfix_recent_extension_clients` to remove N+1 metadata queries against `stats`.
- `Web/ClickFix/src/clickfix_core.php`: Added migration `20260309_020_performance_indexes` with additional indexes for frequent dashboard read patterns.
- `Web/ClickFix/dashboard.php`: Switched to page-scoped dataset loading so each route queries only the modules it renders.

## Unreleased - 2026-03-05

### Version bumps
- `browser-extension/manifest.json`: Version bump to `0.4.5`.
- `browser-extension/README.md`: Revision updated to `2026-03-05`.
- `browser-extension/CHANGELOG.md`: Added release entry `0.4.5 - 2026-03-05`.
- `Web/ClickFix/CHANGELOG.md`: Added release entry `0.9.22 - 2026-03-05`.

### Web dashboard + messaging
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Extension messaging now accepts explicit end date (`msg_expires_at`) so broadcasts can expire exactly on operator-defined dates.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Recent events now prioritize latest activity (`COALESCE(last_seen, received_at)`), and event payloads include `activity_at` for consistent feed/detail rendering.
- `Web/ClickFix/dashboard.php`: Added grouped analytics table `Eventos por dominio (agrupados)` with activity-aware ordering and domain-level event/block summaries.

### Screenshot evidence governance
- `Web/ClickFix/src/clickfix_core.php` + `Web/ClickFix/scripts/migrate.php` + `Web/ClickFix/data/clickfix.sql`: Added `scan_image_reviews` approval workflow (`pending/approved/rejected`) for `before/after` captures.
- `Web/ClickFix/clickfix-report.php`: New captures are stored as `pending` until reviewed by admin.
- `Web/ClickFix/scan-image.php`: New controlled image endpoint; public views get only approved captures while admin uses manual open for review.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/index.php`: Added before/after evidence panels (dashboard + public home) backed by latest approved scan assets.
- `Web/ClickFix/data/scans/.htaccess`: Added direct-access deny policy for raw scan files.

## Unreleased - 2026-03-01

### Version bumps
- `browser-extension/manifest.json`: Version bump to `0.4.4`.
- `browser-extension/README.md`: Revision updated to `2026-03-01`.
- `Web/ClickFix/CHANGELOG.md`: Web release lines added through `0.9.21`.

### Security hardening (web + extension)
- `Web/ClickFix/src/clickfix_core.php`: Added API auth primitives (JWT short-lived tokens, refresh rotation, scope checks), rate-limiting buckets, env loader, and payload signing helpers.
- `Web/ClickFix/clickfix-report.php`: Added per-IP/per-token rate limits, optional auth enforcement, trusted-source tracking, server-side verdict scoring, and new report columns (`event_type`, `runtime_verdict_json`, `server_score_total`, `server_verdict`, `trusted_signal_source`).
- `Web/ClickFix/api/token.php`: New token issuance endpoint for license-authenticated clients.
- `Web/ClickFix/api/refresh.php`: New refresh endpoint with rotation/revocation behavior.
- `Web/ClickFix/api/premium/score-config.php`: New premium signed config endpoint.
- `Web/ClickFix/scripts/generate_keys.php`: New key/bootstrap generator for signing keys, server env secrets, license seed, and extension public-key update.
- `Web/ClickFix/data/clickfix.sql` + migrations: Added API tables (`api_clients`, `api_refresh_tokens`, `api_rate_limits`) and new report-security fields.
- `browser-extension/background.js`: Added token lifecycle handling, premium config fetch with signature verification, bearer-auth report submission, and secure endpoint trust checks.
- `browser-extension/background.js`: Added extension-message polling + notification delivery, and switched public config fetch to `api/score-config.php`.
- `.gitignore`: Added secret/security artifacts to ignore list (`.env.security`, signing keys, premium config).
- `browser-extension/background.js`: Replaced hardcoded `/ClickFix/` endpoint paths with deploy-origin/path composition to prevent Linux case-sensitive route failures.
- `browser-extension/background.js` + deploy scripts: Default backend origin moved to `https://clickfix.jordiserrano.me` with root base path.
- `Web/ClickFix/src/clickfix_core.php`: Added safer DB path resolution + readonly fallback, and PHP compatibility updates for broader Apache hosts.
- `Web/ClickFix/dashboard.php`: Added explicit deployment error UI with migration/permission guidance and reduced live payload size.
- `Web/ClickFix/scripts/server_install.sh`: Added single-command server bootstrap for copy/paste deployment.
- `Web/ClickFix/scripts/preflight.php`: Added strict deployment checks for env/perms/db/extensions.
- `Web/ClickFix/.htaccess` + `Web/ClickFix/data|keys/**/.htaccess`: Added Apache rules to block direct access to secrets, keys, sqlite and logs.
- `Web/ClickFix/api/score-config.php`: Added public config endpoint to avoid ModSecurity false positives on direct `config.json` path access.
- `Web/ClickFix/api/messages.php`: Added authenticated extension messages endpoint.
- `Web/ClickFix/dashboard.php`: Added analytics/ext telemetry/list bulk/messaging/data-center/config-editor/report-scheduling modules with role enforcement.
- `Web/ClickFix/src/clickfix_core.php`: Hardened session security defaults (strict mode, secure/httpOnly/sameSite cookies) and added security headers (`X-Frame-Options`, restrictive `Permissions-Policy`).
- `Web/ClickFix/src/clickfix_core.php`: CORS handling moved to fail-closed by default with explicit origin allow-list + extension-origin support.
- `Web/ClickFix/api/token.php`, `api/refresh.php`, `api/premium/score-config.php`, `api/messages.php`: Added explicit `origin_not_allowed` enforcement.
- `Web/ClickFix/api/messages.php`: Bound `client_id` query to token device identity to prevent cross-client message reads.
- `Web/ClickFix/clickfix-report.php`: Enforced origin policy and changed auth fallback to secure default (`CLICKFIX_REPORT_REQUIRE_AUTH=1`).
- `Web/ClickFix/dashboard.php`: Added login rate-limiting and session ID regeneration on login/logout.
- `Web/ClickFix/index.php`: Added strict session cookie policy.
- `Web/ClickFix/.htaccess`: Blocked direct access to `clickfix-score-config*.json` and `access_requests.ndjson`.
- `Web/ClickFix/clickfix-report.php` + `browser-extension/background.js`: Added optional detection screenshot evidence flow (`before/after`) with bounded payload controls and server-side validation/storage.
- `Web/ClickFix/index.php` + `Web/ClickFix/dashboard.php`: Switched default UI language fallback to English (`en`).

### Community workflow + user preferences
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Added per-user default language preferences and self-service password change flow.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Added `Community` investigation pipeline (JR submit, Mid verify/escalate, Sr final verification/publication).
- `Web/ClickFix/src/clickfix_core.php`: Added investigation voting model (`+1/-1`) with malware/legit thresholding (`>1 malware`, `<1 legit`).
- `Web/ClickFix/src/clickfix_core.php` + `Web/ClickFix/data/clickfix.sql`: Added REP (reputation) tracking and persistence (`users.reputation`, `user_reputation_events`).
- `Web/ClickFix/scripts/migrate.php`: Updated migration report counters for new community/reputation tables.
- `Web/ClickFix/dashboard.php`: Added compact visual function markers in Community controls (`[M+]`, `[L-]`, `[MID]`, `[MID->SR]`, `[SR][PUB]`, `[SR][INT]`, `[SR][X]`) to improve at-a-glance UX.
- `Web/ClickFix/src/clickfix_core.php` + `Web/ClickFix/data/clickfix.sql`: Added user profile model with visibility flags and external account handles/IDs (Threat.rip, VirusTotal, AbuseIPDB, GitHub).
- `Web/ClickFix/dashboard.php`: Added profile page with tabs (Investigations/Reports), self-service profile privacy/account editor, and clickable username links to profiles across the dashboard.
- `Web/ClickFix/src/clickfix_core.php` + `Web/ClickFix/dashboard.php`: Updated ML prediction label thresholds to `low_risk < 15`, `suspicious 15..38`, `malicious > 38`, and surfaced the thresholds in Analytics UI.
- `Web/ClickFix/src/clickfix_core.php` + `Web/ClickFix/data/clickfix.sql` + `Web/ClickFix/scripts/migrate.php`: Added dominant-keyword enrichment cache and secure HTML/resource extraction for events with `score > 20` (public-host only, bounded fetch, cached results).
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Fixed manual report execution to force-run all enabled schedules and hardened period-report date filtering for mixed `received_at` formats.
- `Web/ClickFix/dashboard.php`: Updated `About` copy (ES/EN) to target all user types and removed the exposed technical stack line.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Fixed Recent Events actions so review/quick-action submits validate selected event id, keep focus on the acted report, and reflect `Bloquear dominio` immediately by setting `reports.blocked=1`.
- `Web/ClickFix/MONETIZATION_PLAYBOOK.md`: Added persistent monetization guide with revenue channels, tier proposal, KPIs, and rollout plan.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Added extension messaging scope for unlinked clients (`scope=unlinked`) so notifications can target extension installations without user association.
- `Web/ClickFix/dashboard.php` + `Web/ClickFix/src/clickfix_core.php`: Extended extension messaging to support multi-target dispatch for `client` and `user` scopes plus global `linked`/`unlinked` targeting modes.

### Web visual redesign
- `Web/ClickFix/dashboard.php`: Rebuilt the authenticated UX with a high-density SOC-style visual system (active nav states, sticky operational header, improved table/forms readability, responsive behavior, and clearer module context).

### Web dashboard fusion + RBAC
- `Web/ClickFix/WEB_PRODUCT_GUIDE.md`: Added fusion implementation guide (public/private split, RBAC, UX, acceptance criteria).
- `Web/ClickFix/src/clickfix_core.php`: Added role normalization/rank/labels and admin user management helpers.
- `Web/ClickFix/dashboard.php`: Implemented role-aware routing/nav/actions, admin `users` page, and stricter review/list/request UI gating.
- `Web/ClickFix/dashboard.php`: Removed DB filename visibility from UI and fixed UTF-8/BOM issues causing `strict_types` parse failures.

## Unreleased - 2026-01-29

### Browser extension (i18n)
- `browser-extension/_locales/ar/messages.json`: Added full Arabic UI translation.
- `browser-extension/_locales/de|fr|nl/messages.json`: Filled missing i18n strings so all UI text stays localized.
- `browser-extension/content-script.js` + `browser-extension/background.js`: Blocked-page reasons now localize using the extension language.
- `browser-extension/background.js`: Alerts now wait for locale loading so banners/notifications follow the selected language.
- `browser-extension/content-script.js`: Blocked-page detail text now prefers localized reasons over fixed-language payloads.
- `browser-extension/content-script.js`: Blocked-page HTML now sets lang/dir and respects RTL-friendly alignment.
- `browser-extension/_locales/ca/messages.json`: Added full Catalan UI translation and language selector entry.
- `browser-extension/background.js` + `browser-extension/ui/popup.js` + `browser-extension/ui/options.js` + `browser-extension/content-script.js`: Default language is English and Block All clipboard starts enabled.
- `browser-extension/_locales/es/messages.json`: Fixed Spanish diacritics in mute notification copy.
- `browser-extension/_locales/hi/messages.json`: Added full Hindi UI translation and language selector entry.
- `browser-extension/ui/popup.html` + `browser-extension/ui/options.html`: Fixed Catalan label rendering.
- `browser-extension/ui/options.html` + `browser-extension/ui/options.js` + `browser-extension/ui/style.css`: Added stats cards and detections chart for alerts, blocked, allowlist, and blocklist.
- `browser-extension/_locales/*/messages.json`: Added stats strings plus report-reason copy for all locales.
- `browser-extension/background.js`: Restored service worker and re-applied locale defaults + reason payloads after antivirus quarantine.
- `browser-extension/ui/popup.html` + `browser-extension/ui/popup.js` + `browser-extension/ui/style.css`: Reordered popup layout and added stats dashboard + report reason.
- `Web/ClickFix/clickfix-report.php`: Hardened report input sanitization for XSS/SQLi safety on manual reasons.
- `browser-extension/manifest.json`: Exposed locale message bundles as web-accessible resources for content-script locale loading; version bump to `0.3.16`.
- `browser-extension/manifest.json`: Version bump to `0.3.17`.
- `browser-extension/_locales/*/messages.json`: Fixed corrupted diacritics and non-Latin glyphs (question-mark replacements) in translated UI strings.
- `browser-extension/ui/popup.js` + `browser-extension/ui/options.js` + `browser-extension/background.js`: Store alert reason keys and render alert history with the currently selected language so notifications and history stay localized.
- `browser-extension/manifest.json`: Version bump to `0.3.18`.
- `browser-extension/manifest.json`: Version bump to `0.3.19`.
- `browser-extension/background.js` + `browser-extension/content-script.js` + `browser-extension/ui/popup.js` + `browser-extension/ui/options.js` + `browser-extension/ui/style.css`: Alerts now render as multi-line bullet lists for readability in notifications, banners, and history.
- `browser-extension/manifest.json`: Version bump to `0.3.20`.
- `browser-extension/content-script.js` + `browser-extension/_locales/*/messages.json`: Added a fullscreen safety notice that tells users to press Esc/F11, clearly branded as ClickFix Mitigator.
- `browser-extension/content-script.js` + `browser-extension/_locales/*/messages.json`: Block fullscreen attempts on pages with detections and show a branded exit notice.
- `browser-extension/manifest.json`: Version bump to `0.3.21`.
- `browser-extension/manifest.json`: Version bump to `0.3.22`.
- `browser-extension/content-script.js` + `browser-extension/background.js` + `browser-extension/ui/popup.html`: ClickFix logos now load from embedded base64 data URLs so banners and notifications render reliably.
- `browser-extension/manifest.json`: Version bump to `0.3.23`.

### Web policy
- `Web/ClickFix/PrivacyPolicy.html`: Rebuilt the privacy policy with full translations for all supported extension languages, updated the last-updated date, and added RTL handling for Arabic/Hebrew.

### Web dashboard (PHP)
- `Web/ClickFix/dashboard.php`: Live refresh now keeps the active tab stable and avoids swapping hidden tab content; version bump to `0.7.15`.

### Windows agent
- `windows-agent/agent.py`: Rebuilt hotkey monitoring to cover Win+R, Win+E, and address-bar sequences with paste/execute variants; added local telemetry logging and SQLite report/stats output.
- `windows-agent/config.json`: Added hotkey configuration, allowlists, and local telemetry settings.
- `windows-agent/data/clickfix.sql`: Added local SQLite schema for the agent dashboard.
- `windows-agent/README.md`: Updated usage, hotkeys, and local dashboard guidance.

### Docs
- `README.md`: Added official extension links, expanded feature coverage (including Windows agent sequences), and listed all supported UI languages.
- `browser-extension/README.md`: Documented defaults, stats dashboard, fullscreen safety, and full language list.
- `PrivacyPolicy.md`: Updated dates to match current privacy policy revision.
- `ClickFixMitigaror_ChromeWebStore.md`: Expanded language list for the Chrome Web Store listing checklist.
- `TESTING.md`: Added checks for default settings, stats dashboard, alert filtering, fullscreen blocking, and embedded icons.
- `docs/FeatureLedger.md`: Logged new extension features (fullscreen safety, stats dashboard, localized alerts).
- `ClickFix Mitigator for Dummies.md`: Noted fullscreen safety behavior and refreshed revision date.

## Unreleased - 2026-01-27

### Botanalyzer (Selenium)
- `botanalyzer/botanalyzer.py`: Queue processing now prints each URL, moves processed entries from `urls.txt` to `done.txt`, and exits cleanly on Ctrl+C.
- `botanalyzer/botanalyzer.py`: Driver lifecycle hardening (reset on errors, fallback to fresh profile) and Chrome startup stability flags.
- `botanalyzer/botanalyzer.py`: Click targets expanded to captcha-check selectors and heuristic div clicks; nested iframe traversal for counts/clicks.
- `botanalyzer/botanalyzer.py`: Keeps the main tab open (no forced tab close) and clears cache/cookies/history between URLs.
- `botanalyzer/botanalyzer.py`: Auto-loads the repo `browser-extension/` when no extension is provided and forces Chrome developer mode.
- `botanalyzer/explorar_to_urls.py`: New helper to append entries from `explorar.json` into `urls.txt` without duplicates.

### Browser extension (core)
- `browser-extension/content-script.js`: Iframe scan now walks nested frames (same-origin) for more accurate opaque iframe context.
- `browser-extension/manifest.json`: Version bump to `0.3.1`.

### Docs
- Updated README and markdown docs to reflect Botanalyzer workflow and recent behavior changes.

### Windows agent
- `windows-agent/agent.py`: Version marker updated to `agent-debug-2026-01-28-keepalive-2`.

### Web dashboard (PHP)
- `Web/ClickFix/dashboard.php`: Version string now `0.7.12`.

## Unreleased - 2026-01-26

### Web dashboard (PHP)
- `Web/ClickFix/dashboard.php`: Major UX/UI rebuild (full-width layout, left workspace + right investigation rail, tabs instead of dropdowns, max-height usage, sectioned layout), multi-select list actions, and investigation panel with quick actions.
- `Web/ClickFix/dashboard.php`: Role-based access (admin vs analyst), analyst-only views limited to public lists/intel/alert analytics/countries, and redaction of sensitive data plus URL sanitization (no query params) for analysts.
- `Web/ClickFix/dashboard.php`: Live-refresh system for sections (data-live-section polling) with chart rehydration and analytics dedupe.
- `Web/ClickFix/dashboard.php`: User management (create/verify users, assign roles, optional password updates) and improved login/session handling.
- `Web/ClickFix/dashboard.php`: Review workflows for reports (allow/block/investigate actions) and consolidated analytics charts section.
- `Web/ClickFix/dashboard.php`: Added investigation helpers, domain action hints/subtext, and consistent navigation so switching sections does not bounce back to alerts.
- `Web/ClickFix/dashboard.php`: Version string increments (now `0.7.6`) and UI copy/labels expanded (EN/ES).

### Data + reporting
- `Web/ClickFix/clickfix-report.php`: Added report review columns (accepted/accepted_by/accepted_at/review_status/reviewed_by/reviewed_at) with schema migration logic.
- `Web/ClickFix/data/clickfix.sql`: Updated base schema to include the same review/acceptance columns.
- `Web/ClickFix/clickfixallowlist`: Added `www.hhs.gov`.

### Scraper + assets
- `Web/ClickFix/scripts/scrape_carson_domains.php`: New Carson domains scraper with HTML-first and JSON fallback, pagination, env overrides, and outputs to `Web/ClickFix/data/`.
- `Web/ClickFix/favicon.ico`: Added dashboard favicon (extension logo).
- `favicon.ico`: Added root favicon (extension logo).

### Browser extension (core)
- `browser-extension/manifest.json`: Version bump to `0.1.9`, content scripts now run at `document_start`, with `all_frames` and `match_about_blank` enabled.
- `browser-extension/content-script.js`: Full-page blocking flow (stop page load, replace `<html>`), FamilySafe mode (no bypass), and improved blocklist/allowlist handling with session allowances only when FamilySafe is off.
- `browser-extension/content-script.js`: Injection control for `block-all-inject.js`, better banner placement, and new clipboard-threat handling/telemetry wiring.
- `browser-extension/block-all-inject.js`: Rebuilt clipboard analysis (command detection + entropy/base64/obfuscation heuristics), interception of multiple clipboard APIs, threat throttling, and allowlist/trusted-host scoring adjustments.
- `browser-extension/background.js`: Added FamilySafe setting, locale loading updates, list-source refresh improvements, reporting queue handling, and clipboard analysis helpers.

### Browser extension (UI)
- `browser-extension/ui/popup.html`: Redesigned popup layout with brand icon, toggle subtext, FamilySafe toggle, and re-ordered sections.
- `browser-extension/ui/popup.js`: Wired FamilySafe state to storage and UI.
- `browser-extension/ui/options.html`: Added FamilySafe toggle in advanced options.
- `browser-extension/ui/options.js`: Persisted FamilySafe option.
- `browser-extension/ui/style.css`: New styling for popup/options, toggles, danger states, and layout improvements.
- `browser-extension/_locales/en|es|de|fr|nl/messages.json`: Added strings for detection hints, clipboard threat warning, FamilySafe text, and options labels.
- `browser-extension/test/attacker-sample.html`: Removed test sample file.

### Repo/tooling
- `.gitignore`: Ignore sessions folder, intel cache JSON, and sqlite file under `Web/ClickFix/data`.
- `.vscode/settings.json`: Added Snyk auto-select organization setting.


