# CHANGELOG

## Unreleased - 2026-01-28

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
- `browser-extension/ui/popup.html` + `browser-extension/ui/options.html`: Fixed Catalán label rendering.
- `browser-extension/background.js`: Restored service worker and re-applied locale defaults + reason payloads after antivirus quarantine.
- `browser-extension/manifest.json`: Exposed locale message bundles as web-accessible resources for content-script locale loading; version bump to `0.3.14`.

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
