# ClickFix Advertising and Sponsorship Plan

## Scope
This document defines how to execute the advertising and sponsorship layer for ClickFix Mitigator without degrading trust, response speed, or analyst usability.

It complements `MONETIZATION_PLAYBOOK.md` and focuses on ad inventory, sponsor packages, operational controls, rollout, and measurement.

## Product rule
Ads are secondary.
The primary monetization path remains:
- subscriptions
- licenses
- enterprise support
- professional services

Advertising and sponsorship must only amplify the public-facing product and selected low-privilege surfaces. They must never undermine security trust or operational UX.

## Core principles
- No intrusive ads in analyst-critical workflows.
- No third-party ad scripts in high-trust or high-sensitivity views by default.
- Public site and low-privilege product surfaces are the only valid default placement areas.
- Senior, mid-senior, and admin users should not see public ad placements while working.
- Direct sponsorship packages are preferred over low-quality ad network revenue.
- Every ad must be removable, pausable, targetable, and auditable from the admin console.

## Monetization model
Use a 3-layer model.

### Layer 1: Direct sponsors
Best revenue quality.
Best brand control.
Best fit for ClickFix.

Examples:
- security training providers
- blue-team tooling vendors
- DFIR consultancies
- trusted threat intel services
- browser hardening vendors
- managed detection / MSSP partners

Recommended value props:
- visible sponsor placement on `index.php`
- sponsor card in public investigations area
- newsletter or release-note mention if later added
- sponsored demo placement
- "supported by" branding in selected public modules

### Layer 2: Internal sponsored slots
Already aligned with your current architecture.
Use admin-managed internal ads first.

Good for:
- testing placements
- house ads
- partner campaigns
- upsell to premium/team/enterprise
- self-promotion of analyst access, demos, API, enterprise contact

### Layer 3: Ad network fallback
Use only as a fallback and only on public or low-privilege surfaces.

Examples:
- public landing
- public demo catalog
- public investigations index if later created

Avoid ad networks in:
- authenticated investigation cockpit
- ops workflow
- alert triage
- evidence review
- user management
- API docs behind login

## Inventory map

### Public inventory
#### A. Hero-adjacent sponsor CTA
Placement:
- `index.php` support / sponsor section

Goal:
- convert sponsors directly
- create a premium sponsorship lane

Format:
- sponsor card
- no script required
- direct contact CTA

#### B. Sponsored research slots
Placement:
- public landing blocks
- public feature strips
- below investigations spotlight

Goal:
- direct paid placements
- non-intrusive partner visibility

Format:
- internal ad cards
- title, short body, CTA, theme

#### C. Support / monetization section
Placement:
- public landing lower section

Goal:
- donations + sponsor lead capture + ad fallback

Format:
- donations card
- sponsor card
- ad slot / placeholder card

#### D. Demo sponsorship
Placement:
- demo index
- safe demo pages

Goal:
- sponsor educational traffic
- monetize product discovery pages

Format:
- static sponsor ribbon
- internal slot
- optional ad network fallback

### Authenticated low-privilege inventory
Only for `guest`, `analyst_jr`, `analyst_mid`.

Placements:
- dashboard side rail
- community / public intelligence modules
- non-investigation discovery pages

Never in:
- full-screen investigation workspace
- verdict form focus areas
- screenshot review workflow
- settings/security-critical flows

### Authenticated no-ad roles
Do not show ads to:
- `analyst_sr`
- `analyst_midsenior`
- `admin`

Reason:
- trust
- focus
- higher-value users should not see commodity ad clutter

## Sponsor packages

### Package 1: Public Sponsor
For small partners.

Includes:
- one sponsor card on public landing
- visible CTA
- rotation in sponsor slots

Suggested pricing:
- monthly fixed fee
- discounted quarterly package

### Package 2: Research Sponsor
For security vendors.

Includes:
- landing sponsor slot
- sponsor mention in public investigations block
- demo catalog placement

Suggested pricing:
- mid-tier recurring monthly

### Package 3: Strategic Sponsor
For enterprise-aligned partners.

Includes:
- premium placement on public site
- category exclusivity if sold
- custom CTA and tracking UTM support
- optional analyst-access lead handoff

Suggested pricing:
- premium monthly or quarterly

## Ad object model
Your current internal ad system should support this model.

Required fields:
- `title`
- `body`
- `cta_label`
- `cta_url`
- `theme`
- `placement`
- `role_target`
- `active`
- `starts_at`
- `ends_at`
- `priority`
- `created_by`
- `updated_by`

Recommended extra fields to add later:
- `sponsor_name`
- `campaign_id`
- `utm_source`
- `utm_medium`
- `utm_campaign`
- `impressions`
- `clicks`
- `ctr`
- `notes`
- `requires_approval`

## Placement taxonomy
Standardize placements to avoid chaos.

Recommended keys:
- `index_hero_support`
- `index_sponsor_strip`
- `index_featured_investigations`
- `index_demo_catalog`
- `dashboard_sidebar`
- `dashboard_public_modules`
- `dashboard_community`
- `house_upgrade_banner`

## Sponsor governance
Every sponsor/ad must pass these checks.

### Allowed
- defensive security vendors
- training providers
- research tools
- consulting firms
- browser/security hardening products
- your own upsell and product notices

### Disallowed
- gambling
- adult
- crypto spam
- aggressive affiliate offers
- low-trust redirects
- deceptive lead forms
- anything that looks like malware delivery or fake support

### Security controls
- sanitize all outgoing URLs
- enforce allowlisted protocols only
- no inline arbitrary HTML from sponsors
- prefer platform-rendered cards over pasted embed code
- ad network scripts only where explicitly enabled
- log every create/edit/delete/pause action

## UX rules
- sponsor content must look intentional, not scammy
- never mimic alerts, verdict buttons, or security UI
- clearly label sponsored content
- keep cards compact and non-blocking
- preserve mobile usability
- maintain visual separation from investigations and alerts

## Recommended copy blocks
### Public CTA
- Want to sponsor?
- Support the project and gain visible placement on the public site.
- Fund defensive research without intrusive advertising.

### Internal house ads
- Upgrade to Team
- Request enterprise demo
- Need managed onboarding?
- Sponsor this intelligence feed

## Metrics to track
### Revenue metrics
- sponsor MRR
- ad revenue by placement
- sponsor revenue share vs subscriptions

### Performance metrics
- impressions
- clicks
- CTR
- sponsor lead submissions
- contact-email conversions

### Product safety metrics
- bounce increase after ads enabled
- time-on-page delta
- alert review friction
- complaint rate
- ad disable rate by admins

## Rollout plan

### Phase 1: Direct sponsor-first
- enable sponsor section on `index.php`
- use only internal sponsor cards
- no ad network dependency
- sell first 3-5 sponsor slots manually

### Phase 2: Controlled public inventory
- add rotation by placement and priority
- add impression/click counters
- add sponsor reporting view in admin

### Phase 3: Limited ad network fallback
- enable only if direct sponsor demand is low
- keep restricted to public surfaces
- measure trust/performance impact before scaling

## Admin workflow
Admin must be able to:
- create sponsor card
- edit sponsor card
- schedule start/end
- target placements
- target audiences/roles
- pause globally
- pause per placement
- delete permanently
- review impression/click metrics

## Technical implementation checklist
- Keep direct sponsor cards server-rendered.
- Keep ad network loading behind env flags.
- Do not load external ad scripts for excluded roles.
- Add click logging endpoint for internal sponsor cards.
- Add impression logging on render for internal sponsor cards.
- Add admin metrics page for sponsor performance.
- Add sponsor package documentation and pricing sheet.

## Recommended next files
- `PRICING.md`
- `SPONSORSHIP_PACKAGES.md`
- `AD_INVENTORY.md`
- `SALES_OUTREACH.md`

## Note about external shared planning
A shared ChatGPT link was referenced for this ad plan, but its contents were not accessible from the current environment.
This document is therefore based on the current ClickFix architecture, existing monetization playbook, and the sponsor/ad model already present in the platform.
When the shared text is pasted locally, merge its unique ideas into this document rather than replacing the operational rules above.
