# Testing Checklist

This checklist covers end-to-end verification for the extension, Windows agent, and backend dashboard.

## Browser extension (MV3)

### Load + permissions
- [ ] Load `browser-extension/` via **chrome://extensions** → **Load unpacked**.
- [ ] Confirm permissions include clipboard + notifications.
- [ ] Enable **Allow access to file URLs** to validate `file://` pages.
- [ ] Open a regular web page and confirm the extension icon is visible.

### ClickFix detection flows
- [ ] Open a demo page from `demo/` (e.g. `demo/attacker-sample.html`, `demo/attacker-winlogo.html`, `demo/demo-cloudflare.html`).
- [ ] Also validate the `browser-extension/test/attacker-sample.html` page to confirm extension-specific flows.
- [ ] Open local files (`file://`) with `.pdf`, `.webp`, or other formats that include ClickFix text and confirm detections fire.
- [ ] Add text containing **Win + R** or **Run dialog** patterns; confirm alert/banner.
- [ ] Add text containing command-like patterns (powershell/cmd) and confirm alert/banner.
- [ ] Insert a fake captcha message and confirm alert/banner.
- [ ] Trigger the blocked-page flow (from a blocklisted demo) and verify:
  - Title, reason, detections, context appear.
  - Buttons respond (report/allow once/allow session/allow always/back).
  - Block page fully covers the viewport.

### Clipboard mismatch flows
- [ ] Copy text on a page → change clipboard externally → copy again → confirm mismatch alert.
- [ ] Confirm clipboard read/write behavior on sites that **allow** clipboard APIs.
- [ ] Confirm no console errors on sites that **block** clipboard APIs via Permissions-Policy.
- [ ] Confirm notifications appear (and include the packaged icon) when a mismatch is detected.

### Whitelist / allowlist
- [ ] Add a domain to whitelist in popup/options.
- [ ] Confirm the blocked page does **not** appear for whitelisted sites.
- [ ] Remove a domain from whitelist and re-check that the block triggers again.

## Windows agent (optional)

### Environment
- [ ] Create venv and install dependencies (`windows-agent/README.md`).
- [ ] Run the agent and confirm it starts without errors.

### Detection
- [ ] Copy/paste suspicious command lines and confirm agent logs/report.
- [ ] Confirm benign clipboard events do not trigger false positives.
- [ ] Verify the agent can recover after a clipboard permission denial.

## Backend + dashboard (optional)

### Setup
- [ ] Initialize SQLite DB (see `Web/ClickFix/data/clickfix.sql`).
- [ ] Ensure `Web/ClickFix/data/` is writable by PHP.

### Reports + UI
- [ ] POST a sample report to `clickfix-report.php`.
- [ ] Confirm new entry appears in `dashboard.php`.
- [ ] Validate accept/reject and appeal flows.
- [ ] Trigger the intel cache refresh and confirm `intel-cache.json` updates.
