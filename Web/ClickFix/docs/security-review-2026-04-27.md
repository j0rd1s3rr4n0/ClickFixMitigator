# Security Review - 2026-04-27

## Scope

- `C:/Users/kunakawi/Documents/GitHub/ClickFixMitigator/Web/ClickFix/dashboard.php`
- `C:/Users/kunakawi/Documents/GitHub/ClickFixMitigator/Web/ClickFix/partials/dashboard_scripts.php`
- `C:/Users/kunakawi/Documents/GitHub/ClickFixMitigator/Web/ClickFix/src/clickfix_core.php`
- `C:/Users/kunakawi/Documents/GitHub/ClickFixMitigator/Web/ClickFix/api/*.php`
- `C:/Users/kunakawi/Documents/GitHub/ClickFixMitigator/windows-agent/agent.py`
- `C:/Users/kunakawi/Documents/GitHub/ClickFixMitigator/windows-agent/control_panel.py`

## Findings

### 1. Inline script corruption by output post-processing

Severity: High

Observed issue:
- The dashboard output normalizer/translator was mutating inline `<script>` blocks.
- This broke the event workbench on `dashboard.php?page=ops` and caused JavaScript fragments to leak into the rendered HTML.

Impact:
- Event clicks stopped working.
- Client-side code leaked into the page body.
- Any future inline script became fragile under the same post-processing path.

Fix applied:
- Added block protection and restoration around `<script>` and `<style>` in `dashboard.php`.
- Translation/normalization now applies only to visible HTML, not executable code.

Validation:
- `php -l Web/ClickFix/dashboard.php`
- live HTML fetch confirmed the leaked fragment disappeared and the event workbench script remained intact.

### 2. API action surface needed explicit least-privilege enforcement

Severity: High

Observed issue:
- New operational API actions are sensitive by nature: list changes, extension messaging, and investigation writes.
- Scope checks alone are not enough if a user key is over-scoped or mis-issued.

Impact:
- A lower-trust user API key could have modified operational state if role checks were omitted.

Fix applied:
- Added dedicated scopes in `clickfix_core.php`:
  - `config:read`
  - `lists:write`
  - `messages:write`
  - `investigations:read`
  - `investigations:write`
- Enforced API-key role gates:
  - `lists.php`: minimum `analyst_sr`
  - `message-dispatch.php`: minimum `analyst_sr`
  - `investigations.php` write: minimum `analyst_mid`

Validation:
- `php -l` on all new API files
- local endpoint tests with API keys covering read/write paths

### 3. Remote agent sync needed transport hardening

Severity: Medium

Observed issue:
- The Windows agent previously had no synchronized remote path.
- After adding remote sync, allowing arbitrary plaintext HTTP targets would create an avoidable exfiltration risk through misconfiguration.

Impact:
- A misconfigured deployment could send alert telemetry to an insecure remote endpoint.

Fix applied:
- Remote sync is opt-in.
- The agent now refuses non-HTTPS remote targets, except localhost / 127.0.0.1 for local development.
- Remote sync requires either bearer token or API key.

Validation:
- `python -m py_compile windows-agent/agent.py windows-agent/control_panel.py`
- remote settings surfaced in the control panel snapshot and persisted in config.

### 4. URL sanitization accepted risky forms

Severity: Medium

Observed issue:
- `clickfix_sanitize_http_url()` accepted any `http/https` URL without rejecting control characters or embedded credentials.

Impact:
- Risk of unsafe URLs being accepted into downstream fetch/render flows.
- URLs containing userinfo (`user:pass@host`) are not needed and should be rejected by policy.

Fix applied:
- Reject control characters.
- Reject URLs containing `user` or `pass` components.
- Require a non-empty host after parsing.

Validation:
- `php -l Web/ClickFix/src/clickfix_core.php`
- manual code review of fetch callers using `clickfix_sanitize_http_url()` / `clickfix_ml_url_allowed()`.

## Additional hardening and quality work completed

- Fixed ISO-2 / ISO-3 handling in the top-countries map.
- Added action endpoints and docs:
  - `api/lists.php`
  - `api/investigations.php`
  - `api/message-dispatch.php`
  - `api/openapi.yaml`
- Added optional remote sync from the Windows agent to `clickfix-report.php`.
- Added the missing baseline toggle to extension option pages so the feature can actually be disabled in UI.
- Rebalanced layout so the dashboard uses more available width and does not over-allocate the side rail.

## Residual risks

- `dashboard.php` remains a very large file; maintainability risk is still high until more logic is moved out into focused partials/modules.
- Public access to approved scan images via direct URLs appears intentional. If those images should become non-public, `scan-image.php` needs an additional authorization gate.
- There is still legacy text debt in some UI sources; the runtime output is much safer now, but the source tree should continue to be normalized.

## Verification commands used

```powershell
php -l Web\ClickFix\dashboard.php
php -l Web\ClickFix\src\clickfix_core.php
php -l Web\ClickFix\api\docs.php
php -l Web\ClickFix\api\alerts.php
php -l Web\ClickFix\api\alert.php
php -l Web\ClickFix\api\review.php
php -l Web\ClickFix\api\stats.php
php -l Web\ClickFix\api\lists.php
php -l Web\ClickFix\api\investigations.php
php -l Web\ClickFix\api\message-dispatch.php
python -m py_compile windows-agent\agent.py windows-agent\control_panel.py
node --check browser-extension\ui\options.js
node --check browser-extension\Chromium\ui\options.js
node --check browser-extension\Firefox\ui\options.js
node --check browser-extension\Firefox-Android\ui\options.js
```
