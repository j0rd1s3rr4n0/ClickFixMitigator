# Changelog (Web ClickFix)

All notable changes to the ClickFix web dashboard are documented in this file.

## 0.9.29 - 2026-03-12
- Secure user API-key integration mode:
  - Added user-owned platform API keys (`api_user_keys`) with one-time reveal, hash-only storage, expiration, per-key RPM, and revocation.
  - API auth now supports `X-API-Key` / `Authorization: ApiKey` for intel endpoints.
  - `api/intel.php` and `api/lookup.php` accept secure API-key auth and apply per-key rate buckets.
  - Added role-aware protection for `include_context` when using low-privilege user API keys.
- Investigation UX:
  - New dashboard section in `Investigacion -> Fuentes de investigacion` to create/revoke/view status of platform API keys.
  - Added one-time copy panel for newly generated API keys.
- Documentation:
  - `api/INTEGRATIONS.md` updated with API-key-first flow and hardening guidance.
  - Added Spanish quick guide `api/INTEGRACIONES_ES.md`.
- About page content uplift:
  - Rewrote `Acerca/About` copy with a more professional product narrative (mission, operational scope, security principles, and business objective).
- Dashboard i18n coverage update:
  - Enabled `ca`, `de`, and `fr` as selectable dashboard languages.
  - Added language support to access/profile/admin language selectors.
  - Added consistent language fallback behavior (`ca -> es`, `de/fr -> en`) for dashboard dictionary labels.

## 0.9.28 - 2026-03-12
- Documentation and privacy alignment:
  - Updated web privacy policy metadata and locale dates to reflect current policy version (`2026-03-12`).
  - Synced public documentation references (repository, analyst portal, privacy policy pointers) with current deployment and ownership links.
- Governance clarification:
  - Policy/docs now explicitly document user-scoped investigation API keys and explicit on-demand third-party intel lookups.
  - Evidence workflow wording aligned with current behavior (`after` from extension, `before` generated server-side).

## 0.9.27 - 2026-03-10
- Alert triage speed improvements:
  - Added bulk review workflow for alerts in classic table (`pending/accepted/rejected`) with multi-select, `select all`, `only pending`, and CSRF-protected backend action.
- Monetization visibility update:
  - Support/ads side panel is now visible for guests and `analyst_jr`/`analyst_mid` roles (kept hidden for `analyst_sr`/`admin` to reduce investigation noise).
  - Added explicit configurable ad placeholder when AdSense is not yet enabled.
- Home map reliability fix:
  - Replaced external Leaflet CDN dependency with self-hosted local assets under `assets/vendor/leaflet/`.
  - Dashboard now loads Leaflet CSS/JS from local `self` origin, avoiding CSP/tracking restrictions that previously caused `window.L` to be missing.

## 0.9.26 - 2026-03-09
- Screenshot evidence pipeline changed for reliability:
  - `after` image is now accepted from extension as the only extension-side capture.
  - `before` image is generated server-side after receiving `after`, using Site-Shot API against the alert URL.
  - Added Site-Shot integration in report ingestion with response validation and bounded image size.
  - Added env variable `CLICKFIX_SITESHOT_API_KEY` for server-side screenshot capture.
- Updated dashboard/public evidence copy to reflect new semantics:
  - Before = server snapshot from reported URL.
  - After = extension snapshot at alert trigger.
- Release metadata sync with extension `0.4.9`.

## 0.9.25 - 2026-03-09
- Messaging governance and lifecycle controls:
  - Added `Detener entrega` behavior clarity (deactivate without further extension delivery).
  - Added per-message rectification from history (`title/body/severity/expiration/active`).
  - Added permanent deletion action (`Eliminar de plataforma`) to remove message rows from dashboard history/database.
  - Added history cleanup actions (`inactive/expired` only or `all`).
- API keys UX hardening in Investigation:
  - Keys are shown masked by default (prefix/suffix visible, middle obfuscated).
  - Full key is revealed only on explicit `ver` action.
  - Added submit protection so masked placeholder values do not overwrite the stored secret.
- Analytics ML scope expansion:
  - Added explicit split between `ultimas 300` and `historico total` summaries.
  - Added keyword windows for `total historico`, `ultima semana`, `ultimo mes`, `ultimos 3 meses`, `ultimos 6 meses`.
- Release metadata sync with extension `0.4.8`.

## 0.9.24 - 2026-03-09
- Manual report telemetry visibility improvements:
  - Ingestion normalizes `manualReport=true` alerts to `event_type=manual_report` server-side for canonical storage.
  - Recent/filtered/report-by-id query paths now expose `event_type`, `ip`, `user_agent`, `client_id` and derived `extension_version`.
  - Dashboard `Eventos recientes` detail and classic table now show `IP` and `Extension` fields for manual reports.
- Recurrence marking for blocked history:
  - Added blocked-history aggregation by domain and IP from historical `reports` data.
  - Dashboard now marks repeated offenders with explicit `REINCIDENTE` labels in event feed and classic table.
  - Event detail now includes `Dominio ya bloqueado` and `IP ya bloqueada` summaries (role-aware for IP visibility).
- Release metadata sync with extension `0.4.7`.

## 0.9.23 - 2026-03-08
- Screenshot evidence semantics clarified across dashboard and public index:
  - `Before` = capture taken on page load.
  - `After` = capture taken when alert is triggered.
- Updated evidence panel copy in `dashboard.php` and `index.php` to make capture timing explicit for operators and public viewers.
- Added privacy hard-stop for outbound third-party web tracking/widgets:
  - New env flag `CLICKFIX_DISABLE_EXTERNAL_TRACKING=1` (default safe behavior).
  - When enabled, monetization external widgets are disabled and CSP is locked to self-origin only.
- Performance optimization without feature loss:
  - Added short-lived server-side caching layer (APCu + in-request fallback) for heavy data builders:
    - `clickfix_live_metrics`
    - `clickfix_recent_reports` (no-search mode)
    - `clickfix_analytics_overview`
    - `clickfix_ml_insights`
    - `clickfix_home_maps_dataset`
    - `clickfix_recent_list_actions`
    - `clickfix_recent_appeals`
    - `clickfix_recent_access_requests`
    - `clickfix_recent_extension_clients`
    - `clickfix_extension_user_links`
    - `clickfix_recent_extension_messages`
    - `clickfix_recent_users`
    - `clickfix_recent_investigations`
    - `clickfix_community_investigations`
    - `clickfix_data_center_snapshot`
    - `clickfix_table_recent`
    - `clickfix_list_report_schedules`
    - `clickfix_generate_period_report`
  - Refactored extension client aggregation to remove N+1 SQL lookups against `stats`.
  - Added migration `20260309_020_performance_indexes` with new read-path indexes for reports/stats/access_requests/user_extension_links/investigation sorting patterns.
  - Dashboard data loading now fetches page-specific datasets only (instead of eagerly loading all modules on every request).
  - Removed duplicate user-directory query in `dashboard.php` (admin list now reuses SR directory dataset).
  - Result: less repeated SQL/compute load under dashboard live refresh and map/analytics views.
- Release metadata sync with extension `0.4.6`.

## 0.9.22 - 2026-03-05
- Extension messaging expiration window:
  - Added explicit dashboard end-date input (`msg_expires_at`) for extension notifications.
  - Backend now normalizes and persists `expires_at` per message and excludes expired messages from delivery.
- Screenshot evidence review workflow hardening:
  - Added migration/table `scan_image_reviews` with statuses `pending/approved/rejected`.
  - `clickfix-report.php` now marks new `before/after` captures as `pending` and requires admin review.
  - Added `scan-image.php` controlled serving endpoint with approval-aware behavior (public only approved; admin manual preview).
  - Added deny rules for direct access to `data/scans` assets.
- Recent events and dashboard visibility improvements:
  - Recent events ordering now uses latest activity (`COALESCE(last_seen, received_at)`) so fresh activity on known domains resurfaces.
  - Added event `activity_at` support in feed/detail data and UI.
  - Added grouped view `Eventos por dominio (agrupados)` in dashboard.
  - Added dashboard block `Capturas web (before/after)` and public `index.php` preview of latest approved evidence.

## 0.9.21 - 2026-03-01
- Extension messaging targeting overhaul:
  - Added full targeting modes:
    - `all` (todas)
    - `client` (uno o varios extension IDs)
    - `user` (uno o varios usuarios web asociados)
    - `linked` (todas las extensiones con usuario)
    - `unlinked` (todas las extensiones sin usuario)
  - Dashboard messaging UI now supports multi-target input:
    - batch `client_id` input (comma/space separated)
    - multi-select users for `scope=user`
  - Backend dispatch now resolves and deduplicates target clients for multi-user and linked scopes, with detailed delivery feedback (`resolved_clients`, `sent`).

## 0.9.20 - 2026-03-01
- Extension messaging scope upgrade:
  - Added new messaging target scope `unlinked` (extension clients without active user-web association).
  - Dashboard messaging UI now includes option `No asociadas (sin usuario web)`.
  - Added runtime counter in UI for currently detected unlinked clients.
  - Backend dispatch now resolves unlinked client IDs from `reports` + `stats` and sends per-client notifications.
  - Added explicit success/error flash messages for `scope=unlinked`.

## 0.9.19 - 2026-03-01
- Added monetization strategy documentation:
  - New file `MONETIZATION_PLAYBOOK.md` with concrete revenue model, pricing starter tiers, rollout plan (90 days), KPI framework, and environment setup guide aligned with existing ClickFix licensing/donations/ads capabilities.

## 0.9.18 - 2026-03-01
- Recent Events action reliability fix:
  - Fixed review updates to validate `report_id` and fail clearly when the selected event is invalid.
  - Improved review persistence checks so backend no longer reports success on no-op updates.
  - Quick action `Bloquear dominio` now also marks the target report as `blocked=1` in `reports`, so UI state reflects the action immediately.
  - Added event-focus redirect (`report_id` in query) so after `Actualizar revision` / quick actions the same event remains selected.
  - Added frontend guards to prevent submitting review/quick-action forms when no valid event is selected.
  - Synced review selector with the currently selected event status in both enriched and legacy views.

## 0.9.17 - 2026-03-01
- About page content update:
  - Removed the technical stack sentence from the public-facing `Acerca de` section.
  - Rewrote mission text (ES/EN) to clarify that the platform is for all user types, not only customers.
  - Updated value proposition wording for general users, analysts, and security teams.

## 0.9.16 - 2026-03-01
- Report execution fix:
  - `Ejecutar ahora` now forces execution of all `enabled` schedules (not only due schedules), matching operator expectation for manual runs.
  - Improved feedback message: now reports `executed_ok/total` and warns when there are no enabled schedules.
- Report preview/count robustness:
  - Period report SQL now normalizes mixed `received_at` timestamp formats before filtering (`ISO8601` and `Y-m-d H:i:s` style), preventing false zero-count previews on legacy datasets.

## 0.9.15 - 2026-03-01
- Dominant keywords enrichment upgrade:
  - `ML Insights` now enriches keyword extraction for events with `score > 20` by fetching:
    - page HTML
    - linked resources loaded from that page (bounded)
  - Added safety controls for enrichment:
    - only `http/https`
    - host must resolve to public IP space (anti-SSRF private/local targets)
    - strict timeout, response size limits, and per-run fetch budget
  - Added SQLite cache table `ml_keyword_enrichment_cache` to avoid repeated remote fetches on each dashboard refresh.
- Analytics UI:
  - Added note in `Keywords dominantes` describing enrichment behavior (`score > 20`, cached).

## 0.9.14 - 2026-03-01
- ML prediction threshold update (`Predicciones de riesgo`):
  - `low_risk`: score `< 15`
  - `suspicious`: score `15..38`
  - `malicious`: score `> 38`
- Added visible threshold legend in Analytics `Predicciones de riesgo (Top)` section to avoid operator confusion.

## 0.9.13 - 2026-03-01
- User profile system:
  - Added dedicated `profile` page with default avatar, username, display name, role, REP, and language.
  - Profile tabs added:
    - `Investigaciones`
    - `Reportes` (private to owner/admin)
  - Added profile visibility controls for user-owned contact/accounts:
    - public email toggle
    - Threat.rip user id + public toggle
    - VirusTotal handle + public toggle
    - AbuseIPDB user id + public toggle
    - GitHub handle + public toggle
- Usability:
  - Usernames across operational views now link to profile pages (investigation timeline, community, extension links, audit, messaging history, user admin table, top operator chip).
  - Added `Perfil` navigation entry for authenticated users.
- Data model:
  - Added migration `20260301_017_user_profiles`.
  - Added user profile columns in schema (`full_name`, public visibility flags, external account identifiers).

## 0.9.12 - 2026-03-01
- Community UX clarity update:
  - Added visual action markers in Community workflow controls:
    - `[M+]` / `[L-]` voting buttons
    - `[MID]` validate, `[MID->SR]` escalate
    - `[SR][PUB]` publish verify, `[SR][INT]` internal verify, `[SR][X]` reject
  - Updated pipeline helper text to show role/state flow using compact visual tags.

## 0.9.11 - 2026-03-01
- Account self-service:
  - Logged-in users can now set their own default language preference.
  - Logged-in users can now change only their own password (current password required).
  - Admin still retains capability to reset/edit any user password.
- User profile model updates:
  - Added `users.preferred_lang` and `users.reputation` with migration support.
  - Admin users panel now exposes language and REP fields for operational management.
- Community investigation pipeline:
  - Added dedicated `Community` module/page in dashboard navigation.
  - Added staged workflow for investigations:
    - `draft` -> `jr_submitted` -> `mid_verified`/`sr_review` -> `verified_public` or `verified_internal` (or `rejected`).
  - JR can submit investigations to community from Investigation editor.
  - Mid/Sr reviewers can process community investigations; Mid can validate/escalate; Sr can verify final publication/internal closure.
  - Mid/Sr can open submitted community investigations in Investigation editor to enrich evidence.
- Community malware scoring:
  - Added per-user vote system (`+1 malware`, `-1 legit`) via new `investigation_votes` table.
  - Classification logic surfaced in UI:
    - score `> 1` => malware
    - score `< 1` => legit
    - score `= 1` => neutral
  - Added visual scoring markers in Community list/detail.
- Reputation system:
  - Added user REP update pipeline with audit table `user_reputation_events`.
  - REP deltas now apply on community milestones (submit/verify/reject paths).
- Schema and tooling:
  - Added migration `20260301_016_community_workflow`.
  - Updated base schema (`data/clickfix.sql`) and migration counters (`scripts/migrate.php`) for new community/reputation tables.

## 0.9.10 - 2026-03-01
- Default language policy update:
  - `index.php`: default render language switched to English (`<html lang="en">`, `body.lang-en`).
  - `index.php`: initial language bootstrap now defaults to `en` (browser locale no longer auto-overrides first load).
  - `dashboard.php`: session fallback language is now strictly `en`.
  - `dashboard.php`: access-request form defaults `request_lang` to `en`.

## 0.9.9 - 2026-03-01
- Report evidence pipeline update:
  - `clickfix-report.php` now accepts optional `scan_before_image` and `scan_after_image` payloads from the extension.
  - Added strict data-URL validation (png/jpg/webp), bounded decode limits, and controlled file persistence under `data/scans/`.
  - Added configurable request body limit via `CLICKFIX_REPORT_MAX_BYTES`.

## 0.9.8 - 2026-03-01
- Home dashboard geospatial intelligence:
  - Added new `format=home_geo` JSON feed in `dashboard.php` for map-centric telemetry.
  - Added two new Home maps:
    - Extension users map with red dots and users-per-country counts.
    - Detected websites map with IP/ISP/country/language context.
  - Added Home tables for:
    - per-country extension user counts (+ language hints),
    - per-website intelligence rows (hostname, IP, ISP, country, language, hits).
  - Added lightweight world map rendering with Leaflet (loaded only on Home authenticated view).
  - Added Home trend chart section (alerts/blocks over 14 days).
- Geo-intel backend + caching:
  - Added migration `20260301_014_geo_intel_cache`.
  - New cache tables:
    - `geo_country_cache`
    - `domain_intel_cache`
  - Added safe geo-intel helpers in `src/clickfix_core.php`:
    - country centroid/language cache lookup,
    - domain intelligence lookup (IP/ISP/country/language with cache),
    - Home dataset builder `clickfix_home_maps_dataset`.
- Added secure WhatWeb integration with command-injection controls:
  - New migration `20260301_015_whatweb_cache` + cache table `whatweb_cache`.
  - Added safe runner `clickfix_whatweb_scan_safe()`:
    - strips any input down to normalized domain/subdomain only,
    - never executes URL path/query fragments,
    - builds command with shell escaping and bounded timeout.
  - Added parser + cache lookup:
    - `clickfix_parse_whatweb_line()`
    - `clickfix_parse_whatweb_output()` with verbose-output support
    - `clickfix_whatweb_cached_lookup()`
  - Added execution compatibility for legacy WhatWeb CLI (0.5.x):
    - uses short option format (`-a 1|3|4`) with safe fallback attempts
    - avoids unsupported `-a=3` style.
  - Home website map/table now includes detected backend services from WhatWeb output.
- Investigation UX + traceability improvements:
  - Added `investigating` as a valid investigation verdict status.
  - Improved graph editing ergonomics in `dashboard.php`:
    - node list selector for precise node targeting,
    - selected-node preview panel (label/tags/notes),
    - read-only shared graph now supports node selection and note viewing.
  - Added investigation timeline/audit log with timestamps (`what + when + who`) in Intel module.
  - Added backend event store table `investigation_events` (migration `20260301_013_investigation_events`).
  - Migration seeds a `snapshot` event for pre-existing investigations without timeline records.
  - Added investigation event logging for create/update/share on/off/delete actions with graph deltas and metrics.
  - Added quick operations from alerts in `ops`:
    - block domain,
    - send domain to investigatelist,
    - auto-generate investigation from selected alert.
  - Added `clickfix_report_by_id()` helper to support reliable report-to-investigation workflows.
- Added extension-user association model (`user_extension_links`) with migration `20260301_012_user_extension_links`:
  - Stores relation between web users and extension `client_id` values.
  - Supports reactivation/deactivation of links without deleting history.
- Added backend helpers in `src/clickfix_core.php` for association lifecycle:
  - `clickfix_link_user_extension_client`
  - `clickfix_unlink_user_extension_client`
  - `clickfix_extension_user_links`
  - `clickfix_extension_client_ids_for_user`
  - `clickfix_dispatch_extension_message`
- Upgraded extension messaging dispatch:
  - Existing modes preserved: `all` and `client_id`.
  - New mode: `user` (fan-out to all linked extension clients of that web user).
  - Delivery still uses extension notifications via existing `api/messages.php` polling + browser notifications.
- Extended `dashboard.php`:
  - `Extensiones` now shows web-user association per `client_id` and includes active association management UI.
  - Added association details in selected-client view.
  - `Mensajeria` now supports targeting by associated web user in addition to mass/client targeting.
  - Added safer UX in messaging form (scope-based field enable/disable).
- Updated operational tooling:
  - `scripts/migrate.php` now reports counts for `extension_messages`, `report_schedules`, and `user_extension_links`.
  - `data/clickfix.sql` aligned with latest schema additions (users email + ops/association tables).

## 0.9.7 - 2026-03-01
- Added optional monetization framework (donations + ads), controlled by `.env.security`:
  - New env keys in `.env.security.example` and `.env.security`:
    - `CLICKFIX_MONETIZATION_ENABLED`
    - `CLICKFIX_DONATION_PAYPAL_URL`
    - `CLICKFIX_DONATION_KOFI_URL`
    - `CLICKFIX_DONATION_STRIPE_URL`
    - `CLICKFIX_ADSENSE_ENABLED`
    - `CLICKFIX_ADSENSE_CLIENT`
    - `CLICKFIX_ADSENSE_SLOT`
- Added secure monetization config parsing in `src/clickfix_core.php`:
  - `clickfix_env_truthy()`
  - `clickfix_sanitize_http_url()`
  - `clickfix_monetization_config()`
  - Validates donation URLs (`http/https`) and AdSense identifiers before rendering.
- Added support widgets in public surfaces:
  - `index.php`: new "Support" section with donation buttons and optional AdSense slot.
  - `dashboard.php`: right-side support panel for public/guest view with donation links and optional AdSense slot.
- Enhanced `Acerca de` module in `dashboard.php`:
  - Added clear project mission and target audiences (everyday users + cybersecurity analysts).
  - Added explicit utility statement (protect clients from web attacks and disrupt ClickFix infrastructure).
  - Added "Who I am / Contact / Socials" block, configurable from `.env.security`:
    - `CLICKFIX_OWNER_NAME`
    - `CLICKFIX_CONTACT_EMAIL`
    - `CLICKFIX_CONTACT_WEBSITE`
    - `CLICKFIX_CONTACT_LINKEDIN`
    - `CLICKFIX_CONTACT_X`
    - `CLICKFIX_CONTACT_GITHUB`
- Added email linkage for web users:
  - New DB migration `20260301_011_users_email` adds `users.email` and unique index `idx_users_email_unique` (case-insensitive).
  - Fresh schema (`20260301_001_tables`) now includes `email` in `users`.
  - Updated backend user workflows to store and update user email (`clickfix_create_user`, `clickfix_update_user_profile`, `clickfix_recent_users`, `clickfix_authenticate`).
  - Updated Admin Users UI in `dashboard.php` to create/edit/display user email.

## 0.9.6 - 2026-03-01
- Security hardening pass across auth/session/API:
  - `src/clickfix_core.php`: added session strict-mode, hardened cookie params (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS), `X-Frame-Options: DENY`, and restrictive `Permissions-Policy`.
  - `src/clickfix_core.php`: CORS moved to fail-closed behavior unless origin is explicitly allowed or is an extension origin.
  - `api/token.php`, `api/refresh.php`, `api/premium/score-config.php`, `api/messages.php`: added explicit origin validation with `origin_not_allowed` response.
  - `api/messages.php`: prevented cross-client message reads by requiring `client_id` match with token device identity (`client_id_mismatch` guard).
  - `clickfix-report.php`: enforced origin checks and changed auth fallback to secure default (`CLICKFIX_REPORT_REQUIRE_AUTH=1`).
  - `dashboard.php`: added login rate-limiting and session regeneration on login/logout.
  - `index.php`: aligned with strict session-cookie hardening.
  - `.htaccess`: blocked direct access to `clickfix-score-config*.json` and `access_requests.ndjson`.
- UX redesign of authenticated control surface (`dashboard.php`):
  - New SOC-style visual system with improved hierarchy, typography, density, and responsive behavior.
  - Active-state navigation, sticky operational header, and module context chip.
  - Improved readability for forms/tables/cards while preserving existing backend workflows and RBAC logic.

## 0.9.5 - 2026-03-01
- Added new authenticated modules in `dashboard.php`:
  - `analytics`: trend metrics, new domains, advanced search filters, and latest scan before/after preview.
  - `extensions`: extension-client telemetry (versions, active days, IP history, event trail, blocked domains).
  - `lists`: allow/block/alert/investigate list management with individual and bulk actions.
  - `messaging`: mass/individual messages for extension clients.
  - `data_center`: table-volume snapshot and record viewer.
  - `configs`: admin editors for `clickfix-score-config.json` and `clickfix-score-config-premium.json`.
  - `reports`: daily/weekly/monthly schedule management + manual execution + preview.
- Added i18n-ready labels (`es`/`en`) in dashboard shell/nav with session-persisted language selection.
- Added backend support in `src/clickfix_core.php`:
  - `extension_messages` delivery query helper for per-client polling.
  - report scheduling/storage helpers and operations suite migration.
- Added extension-facing APIs:
  - `api/score-config.php` for public score config delivery (avoids direct `config.json` path issues behind WAF/ModSecurity).
  - `api/messages.php` for authenticated extension message retrieval.
- Added scheduled report runner script: `scripts/run_scheduled_reports.php`.

## 0.9.4 - 2026-03-01
- Implemented fusion dashboard shell (current + legacy style) with professional command-center header/hero layout.
- Added strict RBAC navigation and routing for roles:
  - `Analista Jr` -> `ops`, `intel`
  - `Analista Mid` -> report review actions
  - `Analista Sr` -> lists + requests
  - `Administrador` -> full access + user admin
- Added admin-only `Usuarios` panel with:
  - user creation (username/password/role/verification)
  - user updates (role/verification/optional password reset)
- Enforced role checks in both backend actions and UI controls (no review/list/request/user actions outside allowed roles).
- Removed DB filename exposure from dashboard header while preserving operational metrics.
- Fixed dashboard encoding/syntax issues for stable Apache deployment (UTF-8 no BOM, cleaned mojibake separators).
- Added resilient session bootstrap fallback to system temp sessions directory when `data/sessions` is not writable.

## 0.9.3 - 2026-03-01
- Updated default deployment target to subdomain root: `https://clickfix.jordiserrano.me/`.
- Updated generator/install defaults:
  - `scripts/generate_keys.php` default domain now `clickfix.jordiserrano.me` and default path `/`.
  - `scripts/server_install.sh` default domain/path aligned to subdomain root.
- Updated `.env.security` generation and `.env.security.example` CORS origin to `https://clickfix.jordiserrano.me`.
- Updated migration/readme/deploy docs and demo iframe URL to the new domain/base path.

## 0.9.2 - 2026-03-01
- Added one-step server deployment script: `scripts/server_install.sh` (keys/env, migration, permissions, strict checks).
- Added deployment verification script: `scripts/preflight.php`.
- Added Apache hardening files:
  - `.htaccess` in web root (blocks `.env`, sqlite/log/pem files, and sensitive directories)
  - `.htaccess` in `keys/`, `data/`, `data/backups/`, `data/sessions/`, and `data/logs/`.
- `clickfix-report.php` now writes logs into `data/logs/` to avoid root-path write issues in Apache deploys.
- `scripts/generate_keys.php` no longer fails when `browser-extension/background.js` is missing (server-only deploy support).
- Fixed list file consistency: dashboard allowlist now uses `clickfixallowlist` (same source used by extension endpoint).
- Updated migration guide for exact copy/paste deployment to `/var/www/jordiserrano/ClickFix`.

## 0.9.1 - 2026-03-01
- Added safer DB resolution in `src/clickfix_core.php`: prefers writable DB targets and seeds `data/clickfix.sqlite` from legacy DB when needed.
- Added readonly fallback for DB open when migration/write fails due permissions.
- Added deployment-safe dashboard fatal screen with concrete migration/permission steps instead of blank/fatal UI.
- Reduced `format=live` payload size by default (recent events now only in `format=json` or `include_recent=1`).
- Added PHP compatibility hardening (`never`/`match` removal and `str_starts_with` replacement helper) for broader Apache hosts.

## 0.9.0 - 2026-03-01
- Added security migration `20260301_009_api_security` with new tables:
  - `api_clients`
  - `api_refresh_tokens`
  - `api_rate_limits`
- Added secure API endpoints:
  - `api/token.php`
  - `api/refresh.php`
  - `api/premium/score-config.php`
- Added server-side JWT auth helpers, refresh-token rotation, and API rate limiting.
- Added signed premium config delivery (RSA-SHA256) and key-id support.
- Hardened `clickfix-report.php` with optional auth requirement, per-IP/per-token limits, and server-side verdict scoring.
- Added secure key generation bootstrap script: `scripts/generate_keys.php`.
- Added migration/docs updates for `.env.security`, signing keys, and premium config deployment.

## 0.8.21 - 2026-02-28
- Added new analytics charts: `Alerts vs Blocks`, `Average Score Trend`, and `Score Distribution`.
- Introduced score bucketing and average score calculations for reporting.
- Split private dashboard into multi-page navigation (`page=overview|alerts|lists|intel|users|data|access`) and synced tabs with `page` param.
- Improved live metrics payload to include new score chart data.

## 0.8.20 - 2026-02-28
- Public analytics endpoint stabilized with `format=live` and `format=json` modes.
- Added score breakdown rendering in detections and stored `score_total` / `score_details_json`.
- Deduplicated lists on load and attached duplicate counts to logs and detections.
- Added public preview sections, multi-language support, and mojibake fix for translations.

## 0.8.11 - 2026-02-28
- Initial public analytics layout with charts, recent domains, and access panel.
