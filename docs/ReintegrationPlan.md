# Reintegration Plan — Removed Features

This plan reintroduces legacy features with minimal risk and maximal backward compatibility. Where a faithful restore conflicts with current architecture, an adapted approach is preferred.

## Prioritized Reintegration List
1. **FE-LEG-001** Root `dashboard.php` entrypoint (low risk)
2. **FE-LEG-002** Root `PrivacyPolicy.html` entrypoint (low risk)
3. **FE-LEG-003** Legacy SVG flow diagrams (low risk)

## Feature-by-Feature Plan

### FE-LEG-001 — Root `dashboard.php` entrypoint
- **Conflict**: Dashboard moved to `Web/ClickFix/dashboard.php`.
- **Approach A (faithful)**: Restore the full legacy `dashboard.php` in the repo root.
- **Approach B (adapted, chosen)**: Add a lightweight shim that delegates to `Web/ClickFix/dashboard.php`.
- **Implementation steps**:
  1. Add `dashboard.php` at repo root.
  2. Use `require` to load the canonical dashboard implementation.
- **Validation**:
  - `php -l dashboard.php` (syntax check)
  - Manual: open `/dashboard.php` and verify dashboard renders.
- **Definition of Done**:
  - Root entrypoint serves the current dashboard without code duplication.

### FE-LEG-002 — Root `PrivacyPolicy.html`
- **Conflict**: Policy moved to `Web/ClickFix/PrivacyPolicy.html`.
- **Approach A (faithful)**: Restore the original full HTML at repo root.
- **Approach B (adapted, chosen)**: Add a redirect page at repo root pointing to the canonical policy.
- **Implementation steps**:
  1. Add `PrivacyPolicy.html` at repo root.
  2. Add meta-refresh redirect + fallback link.
- **Validation**:
  - Manual: open `/PrivacyPolicy.html` and verify redirect.
- **Definition of Done**:
  - Legacy URL resolves to current policy.

### FE-LEG-003 — Legacy SVG flow diagrams
- **Conflict**: SVGs were replaced by PNGs.
- **Approach A (faithful, chosen)**: Restore SVGs in `assets/`.
- **Approach B (adapted)**: Update docs to only use PNG.
- **Implementation steps**:
  1. Restore `assets/flow-1-deception.svg`, `flow-2-interruption.svg`, `flow-3-block.svg`.
  2. Keep PNGs as current primary assets.
- **Validation**:
  - Manual: open SVGs in browser to confirm render.
- **Definition of Done**:
  - SVGs exist for legacy references without breaking current docs.

## Post-Implementation Checks
- Run PHP syntax check on legacy shim.
- Confirm no impact on existing dashboard route and assets.
- Ensure `assets/` includes both PNG and SVG variants.
