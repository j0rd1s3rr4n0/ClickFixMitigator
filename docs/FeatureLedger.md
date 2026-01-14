# Feature Ledger — ClickFix Mitigator

This ledger inventories features across the repository history, including active, replaced, and removed capabilities. Evidence references commit hashes and/or current file locations.

## Legend
- **Status**: Active, Removed, Replaced, Partial
- **Evidence**: commit hash(es) + key file(s)

## Feature Inventory

### Browser Extension (MV3)
| ID | Feature | Description | Status | Evidence | Dependencies / Risks | Compatibility Notes |
| --- | --- | --- | --- | --- | --- | --- |
| FE-EXT-001 | ClickFix heuristic detection | Detects ClickFix patterns (command prompts, “Win + R” hints, clipboard/selection mismatches). | Active | `7658334`, `592add7`; `browser-extension/README.md` | Clipboard access and `<all_urls>` permissions. | Stable in HEAD. |
| FE-EXT-002 | Clipboard mismatch alerts | Compares selection vs clipboard content and alerts on mismatch. | Active | `592add7`; `browser-extension/README.md` | Clipboard permissions required. | Stable in HEAD. |
| FE-EXT-003 | Popup UI controls | Enable/disable detection, manage whitelist, view history, manual reports. | Active | `2134a9d`, `f5780d1`; `browser-extension/ui/popup.html` | Relies on local storage. | Stable in HEAD. |
| FE-EXT-004 | Block-all clipboard mode | Blocks JS clipboard writes; keyboard copy remains. | Active | `177a2eb`; `browser-extension/README.md` | May affect UX on legitimate sites. | Stable in HEAD. |
| FE-EXT-005 | Remote blocklist/allowlist | Fetches blocklist/allowlist and supports external sources. | Active | `f5780d1`, `464d694`; `browser-extension/background.js` | Depends on external endpoints. | Stable in HEAD. |
| FE-EXT-006 | Report queue + dedupe | Queues reports and flushes on interval with dedupe window. | Active | `b2bed4a`; `browser-extension/background.js` | Depends on backend availability. | Stable in HEAD. |
| FE-EXT-007 | Localization | Multi-language UI and message loading. | Active | `2330df7`, `2fdca66`; `browser-extension/_locales/` | Requires complete message coverage. | Stable in HEAD. |

### Backend + Dashboard (PHP + SQLite)
| ID | Feature | Description | Status | Evidence | Dependencies / Risks | Compatibility Notes |
| --- | --- | --- | --- | --- | --- | --- |
| FE-BE-001 | Report endpoint | `clickfix-report.php` receives events and writes to SQLite. | Active | `f5780d1`, `77a159e`; `Web/ClickFix/clickfix-report.php` | Requires writable DB. | Stable in HEAD. |
| FE-BE-002 | Dashboard | Multi-language dashboard for detections, approvals, appeals, and logs. | Active | `88eb4ce`, `59cd655`; `Web/ClickFix/dashboard.php` | Requires auth hardening. | Stable in HEAD. |
| FE-BE-003 | Intel feeds | Scrape/cached intel references with TTL. | Active | `2b91c1b`; `Web/ClickFix/data/intel-cache.json` | External network dependency. | Stable in HEAD. |
| FE-BE-004 | SQLite bootstrap | Auto-create DB with fallback schema. | Active | `a152748`; `Web/ClickFix/data/clickfix.sql` | Requires filesystem access. | Stable in HEAD. |

### Windows Agent (Python)
| ID | Feature | Description | Status | Evidence | Dependencies / Risks | Compatibility Notes |
| --- | --- | --- | --- | --- | --- | --- |
| FE-AG-001 | Clipboard monitoring | Detects clipboard changes and suspicious commands. | Active | `ec7a4cd`; `windows-agent/agent.py` | Requires Windows clipboard APIs. | Stable in HEAD. |
| FE-AG-002 | Win+R sequence detection | Detects Run dialog sequences (Win+R → paste → execute). | Active | `ec7a4cd`; `windows-agent/README.md` | Hook reliability varies. | Stable in HEAD. |
| FE-AG-003 | Toast + tray restore | Toast notifications and tray icon to restore blocked clipboard. | Active | `9ed9c61`, `7fd47f4`; `windows-agent/agent.py` | Windows-specific libs. | Stable in HEAD. |
| FE-AG-004 | Configurable rules | Regex rules, exclusions, sensitivity in `config.json`. | Active | `ec7a4cd`; `windows-agent/config.json` | Needs tuning to avoid false positives. | Stable in HEAD. |

### Demo & Documentation
| ID | Feature | Description | Status | Evidence | Dependencies / Risks | Compatibility Notes |
| --- | --- | --- | --- | --- | --- | --- |
| FE-DEMO-001 | Attack demos | HTML/PHP demos for ClickFix test scenarios. | Active | `c65539d`, `b625d70`; `demo/` | Intended for testing only. | Stable in HEAD. |
| FE-DOC-001 | README & guides | Bilingual README, deployment and testing guidance. | Active | `7b342a`, `9cbc41e`; `README.md` | Keep aligned with code. | Stable in HEAD. |

## Removed / Replaced Features
| ID | Feature | Description | Status | Evidence | Notes |
| --- | --- | --- | --- | --- | --- |
| FE-LEG-001 | Root `dashboard.php` entrypoint | Original root dashboard entrypoint removed when dashboard moved to `Web/ClickFix/`. | Replaced | `a152748` (delete), replaced by `Web/ClickFix/dashboard.php` | Legacy path compatibility restored via shim. |
| FE-LEG-002 | Root `PrivacyPolicy.html` | Original privacy policy in repo root removed when moved under `Web/ClickFix/`. | Replaced | `7005e2d` (delete), `Web/ClickFix/PrivacyPolicy.html` added earlier | Legacy URL restored via redirect. |
| FE-LEG-003 | Flow SVG diagrams | Original SVG diagrams removed after image refresh. | Replaced | `03619e3` (delete), PNG replacements in `assets/` | SVGs restored for legacy documentation. |

## Partial / At-Risk Features
| ID | Feature | Description | Status | Evidence | Notes |
| --- | --- | --- | --- | --- | --- |
| FE-PART-001 | Agent maintenance note | README notes that `agent.py` is under maintenance/testing. | Partial | `README.md` | Requires additional validation for production use. |
