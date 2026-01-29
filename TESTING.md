# Testing Checklist

Last updated: 2026-01-29

This checklist covers end-to-end verification for the extension, Windows agent, and backend dashboard.

## Browser extension (MV3)

### Load + permissions
- [ ] Load `browser-extension/` via **chrome://extensions** → **Load unpacked**.
- [ ] Confirm permissions include clipboard + notifications.
- [ ] Enable **Allow access to file URLs** to validate `file://` pages.
- [ ] Open a regular web page and confirm the extension icon is visible.
- [ ] Open the popup and confirm the language selector includes all supported locales; default is English.
- [ ] Confirm **Detection active** and **Block all clipboard by JavaScript** are enabled by default.

### ClickFix detection flows
- [ ] Open a demo page from `demo/` (e.g. `demo/attacker-sample.html`, `demo/attacker-winlogo.html`, `demo/demo-cloudflare.html`).
- [ ] Also validate the `browser-extension/test/attacker-sample.html` page to confirm extension-specific flows.
- [ ] Open local files (`file://`) of any type (PDF, WebP, images, HTML, etc.) that include ClickFix patterns and confirm detections fire.
- [ ] Add text containing **Win + R** or **Run dialog** patterns; confirm alert/banner.
- [ ] Add text containing command-like patterns (powershell/cmd) and confirm alert/banner.
- [ ] Insert a fake captcha message and confirm alert/banner.
- [ ] Trigger the blocked-page flow (from a blocklisted demo) and verify:
  - Title, reason, detections, context appear.
  - Buttons respond (report/allow once/allow session/allow always/back).
  - Block page fully covers the viewport.
- [ ] Trigger a detection and attempt fullscreen on the page; confirm fullscreen is blocked and the extension exit prompt appears.

### Clipboard mismatch flows
- [ ] Copy text on a page → change clipboard externally → copy again → confirm mismatch alert.
- [ ] Confirm clipboard read/write behavior on sites that **allow** clipboard APIs.
- [ ] Confirm no console errors on sites that **block** clipboard APIs via Permissions-Policy.
- [ ] Confirm notifications appear (and include the embedded ClickFix icon) when a mismatch is detected.

### Whitelist / allowlist
- [ ] Add a domain to whitelist in popup/options.
- [ ] Confirm the blocked page does **not** appear for whitelisted sites.
- [ ] Remove a domain from whitelist and re-check that the block triggers again.
- [ ] Verify recent alerts list excludes domains already in allowlist or blocklist.

### Popup dashboard
- [ ] Verify stats cards show totals for alerts, blocked sites, allowlist, and blocklist.
- [ ] Confirm the detections chart loads and does not refresh-jump while navigating tabs.

## Windows agent (optional)

### Environment
- [ ] Create venv and install dependencies (`windows-agent/README.md`).
- [ ] Run the agent and confirm it starts without errors.

### Detection
- [ ] Copy/paste suspicious command lines and confirm agent logs/report.
- [ ] Confirm benign clipboard events do not trigger false positives.
- [ ] Verify the agent can recover after a clipboard permission denial.
- [ ] Press Win + R, paste a suspicious command, press Enter → expect prompt/block.
- [ ] Press Win + E, focus address bar (Alt + L) and paste a suspicious command, press Enter → expect prompt/block.
- [ ] Test paste variants (Shift + Insert, Ctrl + Shift + V) and execute variant (Ctrl + Shift + Enter).

## Backend + dashboard (optional)

### Setup
- [ ] Initialize SQLite DB (see `Web/ClickFix/data/clickfix.sql`).
- [ ] Ensure `Web/ClickFix/data/` is writable by PHP.

### Reports + UI
- [ ] POST a sample report to `clickfix-report.php`.
- [ ] Confirm new entry appears in `dashboard.php`.
- [ ] Validate accept/reject and appeal flows.
- [ ] Trigger the intel cache refresh and confirm `intel-cache.json` updates.

## Botanalyzer (optional)

### Queue + output
- [ ] Populate `botanalyzer/urls.txt` and run `python botanalyzer.py`.
- [ ] Confirm each URL is printed, processed, and moved to `done.txt`.
- [ ] Press Ctrl+C and verify a clean exit without stacktrace.

### Interaction
- [ ] Confirm buttons and captcha divs are clicked (including nested iframes).
- [ ] Confirm cache/cookies/history are cleared between URLs.

## Legacy entrypoints (backward compatibility)

- [ ] Open `/dashboard.php` at the repo root and confirm it loads the current dashboard UI.
- [ ] Open `/PrivacyPolicy.html` at the repo root and confirm it redirects to `Web/ClickFix/PrivacyPolicy.html`.
- [ ] Confirm the legacy SVGs render (`assets/flow-1-deception.svg`, `assets/flow-2-interruption.svg`, `assets/flow-3-block.svg`).
