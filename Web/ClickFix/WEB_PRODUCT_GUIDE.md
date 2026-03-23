# ClickFix Web Product Guide (Fusion Edition)

## Objective
Build a fusion between the previous and current dashboard so it feels enterprise-grade and trustworthy: clear public intel view, powerful authenticated workspace, strict role governance, and evidence-first investigation UX.

## Product Principles
1. Evidence before aesthetics: every alert view must explain exactly why it fired.
2. Public + private separation: unauthenticated users see aggregate intelligence only; sensitive workflows require auth.
3. Progressive trust model: capabilities scale by role level.
4. No infrastructure leakage: never expose DB filenames/paths in UI.
5. Operational clarity: important actions are obvious, auditable, and reversible where possible.

## Access Model (RBAC)
- `Analista Jr`:
  - Access: overview, search, ops (read-only), intel (create/edit own investigations).
  - No list management, no request adjudication, no user admin.
- `Analista Mid`:
  - Everything from Jr.
  - Can review report status (`pending/accepted/rejected`).
- `Analista Sr`:
  - Everything from Mid.
  - Can manage block/allow/alert lists.
  - Can resolve appeals and access requests.
- `Administrador`:
  - Full access.
  - User administration (create/update role/verification/password reset).

## Experience Architecture
- Public Zone:
  - Hero summary + KPIs + recent trends.
  - Search and investigation share links.
  - Access request + login forms.
- Authenticated Zone:
  - Operations workbench with feed + enriched event detail.
  - Investigation graph studio.
  - List operations + audit trail.
  - Requests triage.
  - User administration (admin only).

## Visual Direction
- Command-center visual language:
  - Dense but readable telemetry layout.
  - Strong hierarchy with role/context badges.
  - High-contrast panels and forensic mono typography for data.
- Consistent terminology:
  - "Alert", "Evidence", "Review", "Verdict", "Investigation", "Policy Lists", "Requests", "Users".

## Technical Implementation Plan
1. Normalize roles and add rank/label helpers in core auth utilities.
2. Enforce RBAC on POST actions and page routing.
3. Add admin user management service functions and UI.
4. Refresh dashboard shell:
  - Professional hero/header.
  - Role-aware navigation.
  - Better unauthenticated narrative.
5. Preserve existing detection/reporting features:
  - Multi-reason and snippet highlighting.
  - Investigation graph workflows.
6. Remove DB filename from UI while retaining metrics visibility.
7. Validate syntax + run strict preflight.

## Acceptance Criteria
- Public pages render without login.
- Restricted pages/actions are blocked by role and show clear feedback.
- Multiple reasons/snippets remain visible in one event report.
- Investigation graph CRUD + sharing continue working.
- No DB filename/path is visible in UI.
- Dashboard appears coherent, premium, and operationally credible.
