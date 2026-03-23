# ClickFix Mitigator

<p align="center">
  <a href="https://github.com/j0rd1s3rr4n0/ClickFixMitigator/stargazers">
    <img src="https://img.shields.io/github/stars/j0rd1s3rr4n0/ClickFixMitigator?style=for-the-badge" alt="GitHub stars" />
  </a>
  <a href="https://github.com/j0rd1s3rr4n0/ClickFixMitigator/issues">
    <img src="https://img.shields.io/github/issues/j0rd1s3rr4n0/ClickFixMitigator?style=for-the-badge" alt="Open issues" />
  </a>
  <a href="https://github.com/j0rd1s3rr4n0/ClickFixMitigator/tags">
    <img src="https://img.shields.io/github/v/tag/j0rd1s3rr4n0/ClickFixMitigator?style=for-the-badge" alt="Latest tag" />
  </a>
  <a href="LICENSE">
    <img src="https://img.shields.io/github/license/j0rd1s3rr4n0/ClickFixMitigator?style=for-the-badge" alt="License" />
  </a>
</p>

ClickFix Mitigator is a defensive platform built to detect, block, explain, and investigate ClickFix-style social-engineering campaigns from the browser all the way to analyst workflows.

It combines browser-side protection, an operations dashboard, an investigation cockpit, an integration API, validation tooling, and optional endpoint-side visibility into one project.

## Why this exists

ClickFix attacks are effective because they do not need a classic exploit chain. They persuade the user to execute commands manually through fake prompts such as:

- `Win + R`
- `Win + X`, then `I`
- paste into PowerShell / Terminal
- execute copied payloads that were never clearly visible to the user

That means the problem is not only malicious code. It is also:

- deceptive interaction flow
- clipboard abuse
- command obfuscation
- lack of visibility across repeated campaigns
- slow analyst triage after the first alert

ClickFix Mitigator is designed to reduce accidental execution, improve evidence quality, and speed up incident response.

## Product at a glance

### 1. Browser-side prevention
- Detects ClickFix-like interaction patterns in real time.
- Analyzes suspicious clipboard payloads and obfuscation.
- Blocks or limits risky behavior on flagged pages.
- Supports allow/block controls and trusted host exclusions.
- Supports manual reporting from the extension.

### 2. Web operations and triage
- Central dashboard for alerts, verdicts, events, metrics, and policy actions.
- Role-based access control for junior, mid, senior, and admin users.
- Bulk review workflows and recurrent-domain visibility.
- Screenshot review and evidence approval pipeline.

### 3. Investigation cockpit
- Dedicated investigation workspace focused on one case at a time.
- Relational graph editor with entities, notes, relationships, layouts, zoom, fullscreen, and quick navigation.
- IOC extraction, enrichment pivots, related-alert visibility, and timeline tracking.
- Export to IOC formats and MISP JSON.

### 4. Integration and analyst tooling
- Personal platform API keys with expiration, revocation, and per-key rate limits.
- User-scoped provider credentials for enrichment workflows.
- Integrations designed for VirusTotal, AbuseIPDB, URLScan, MISP-like export workflows, OpenCTI-style use cases, and external consumers of platform data.

### 5. Lab and validation tooling
- Controlled validation workflows for suspicious URLs and campaign replay.
- Optional endpoint-side visibility for higher-confidence investigations.
- Demo pages for safe validation and stakeholder walkthroughs.

## What makes it different

- Evidence-first: alerts are not just counters; they carry context, snippets, reasons, and screenshots.
- Public and private separation: the public surface can expose aggregate intelligence without leaking sensitive operator or user data.
- Investigation-native: the project does not stop at blocking; it gives analysts a case workspace.
- Role-aware: junior users can work productively while sensitive data can be redacted or restricted.
- Defensive by design: built for monitoring, prevention, triage, and analyst operations.

## Core capabilities

### Detection and blocking
- ClickFix interaction-flow detection.
- Clipboard mismatch and suspicious-command analysis.
- Command and obfuscation scoring.
- Blocklist and allowlist support.
- Optional script execution lock on blocked pages.
- Session-based temporary allow actions such as `allow once` and `allow this session`.
- Trusted-domain exclusions for internal or known-safe environments.

### Evidence workflow
- `after` screenshot captured by the extension when the alert triggers.
- `before` screenshot generated server-side from the alert URL.
- Screenshot moderation and approval workflow.
- Support for manual reassignment of `before` and `after`.
- Support for manual screenshot uploads where needed.

### SOC workflow
- Pending / accepted / rejected review pipeline.
- Recent events feed grouped by domain.
- Recurrence markers and related-alert exploration.
- Domain and IP policy actions from the dashboard.
- Message delivery to managed extensions with lifecycle control.

### Investigation workflow
- One-case-at-a-time investigation cockpit.
- Graph studio with nodes, edges, notes, tags, and multiple layouts.
- Fullscreen map actions and quick section navigation.
- IOC extraction from case content.
- Provider pivots directly from investigation targets.
- Timeline of analyst actions and graph changes.
- Sharing controls and public investigation links where enabled.

### Analytics and intelligence
- Alert, block, and trend charts.
- Per-domain and per-country visibility.
- Keyword extraction from recent and historical activity.
- Recurrent-domain and anomaly-focused views.
- VirusTotal result visualization for reported domains where data is available.

### Data governance and security
- RBAC with `analyst_jr`, `analyst_mid`, `analyst_sr`, and `admin`.
- Optional redaction of emails, passwords, usernames, and phone numbers for lower roles.
- User-scoped provider API keys.
- Personal platform API keys with safe storage and revocation.
- Defensive-only positioning and no third-party analytics requirement by default.

## Who this is for

- Blue teams that need browser-layer visibility into ClickFix campaigns.
- Managed security teams that want a lightweight but capable analyst workflow.
- Researchers validating suspicious landing pages and user-interaction traps.
- Organizations that want better context than "the extension blocked something."
- Security product demos, labs, and controlled testing environments.

## Typical workflow

1. A user lands on a suspicious page.
2. The extension detects a ClickFix-like interaction pattern or suspicious clipboard behavior.
3. The extension blocks or limits risky actions and reports the event.
4. The backend stores the alert, evidence, and triage metadata.
5. Analysts review the case in the dashboard.
6. The case can be promoted into the investigation cockpit.
7. Analysts pivot through IOCs, provider enrichment, graph relationships, screenshots, and exports.
8. The result can drive policy actions, sharing, reporting, or downstream integrations.

## Architecture

| Component | Path | Purpose |
| --- | --- | --- |
| Browser protection | `browser-extension/` | User-facing protection, reporting, and risk reduction during suspicious interaction flows. |
| Operations platform | `Web/ClickFix/` | Alert handling, evidence review, investigations, access control, analytics, and integrations. |
| Endpoint visibility | `windows-agent/` | Optional host context to strengthen investigations and operational awareness. |
| Validation tooling | `botanalyzer/` | Controlled review workflows for suspicious targets and internal validation. |
| Demo experience | `demo/` | Safe showcase flows for training, testing, and presentations. |
| Docs | `docs/`, `*.md` | Product, migration, policy, testing, and integration material. |

## Official links

- Repository: https://github.com/j0rd1s3rr4n0/ClickFixMitigator
- Chrome Web Store: https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa
- Firefox package: `browser-extension/build-firefox.ps1`
- Analyst access: https://clickfix.jordiserrano.me
- Privacy policy: `PrivacyPolicy.md`

## Quick start

### Browser extension
1. Open `chrome://extensions`.
2. Enable `Developer mode`.
3. Load `browser-extension/`.

Firefox local package:
1. Open `about:debugging#/runtime/this-firefox`.
2. Load `browser-extension/dist/firefox/manifest.json` after running `browser-extension/build-firefox.ps1`.

For distribution or store packaging, see:
- `browser-extension/README.md`
- `ClickFixMitigaror_ChromeWebStore.md`

### Web platform
1. Ensure `Web/ClickFix/data/` is writable.
2. Initialize the local datastore from `Web/ClickFix/data/clickfix.sql`.
3. Serve `Web/ClickFix/` from your web runtime.
4. Open `Web/ClickFix/dashboard.php`.

Useful web-side docs:
- `Web/ClickFix/WEB_PRODUCT_GUIDE.md`
- `Web/ClickFix/MIGRATION.md`
- `Web/ClickFix/api/INTEGRATIONS.md`
- `Web/ClickFix/api/INTEGRACIONES_ES.md`

### Optional tooling
- Endpoint visibility guide: `windows-agent/README.md`
- Validation tooling: run from `botanalyzer/` in a controlled environment
- Testing notes: `TESTING.md`

## Integrations and exports

The platform is designed to work with analyst enrichment and downstream workflows.

Examples:
- VirusTotal
- AbuseIPDB
- URLScan
- IOC export in TXT / CSV / JSON
- MISP JSON export
- Platform API with personal `X-API-Key`

The goal is not to replace every external platform. The goal is to make ClickFix-specific detection and evidence operational, then let teams export or integrate where needed.

## Privacy and security notes

- Built for defensive security operations.
- Sensitive data handling can be role-restricted and redacted.
- Provider keys are scoped per user where configured.
- Platform API keys support revocation and expiry.
- Public pages can expose aggregate intelligence without revealing private operator data.
- External analytics are not required for core operation.

Policy and privacy references:
- `PrivacyPolicy.md`
- `Web/ClickFix/PrivacyPolicy.html`

## Documentation map

- Main product overview: `README.md`
- Browser extension guide: `browser-extension/README.md`
- Windows agent guide: `windows-agent/README.md`
- Testing: `TESTING.md`
- Feature inventory: `docs/FeatureLedger.md`
- Reintegration notes: `docs/ReintegrationPlan.md`
- Web product positioning: `Web/ClickFix/WEB_PRODUCT_GUIDE.md`
- Monetization notes: `Web/ClickFix/MONETIZATION_PLAYBOOK.md`
- API integrations: `Web/ClickFix/api/INTEGRATIONS.md`

## Project positioning

ClickFix Mitigator is not just an extension and not just a dashboard.

It is a full defensive workflow for:

- prevention at the browser layer
- evidence capture at the moment of risk
- centralized triage and review
- case-centric investigation
- export and integration with broader security operations

If you want a lightweight, explainable, investigation-ready platform for ClickFix-style abuse, this repository is the full stack.

## License

See `LICENSE`.
