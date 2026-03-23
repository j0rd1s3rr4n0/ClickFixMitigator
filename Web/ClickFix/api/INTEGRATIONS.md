# ClickFix Integration API

This API allows third-party platforms (OpenCTI, SIEM, TIPs, browser tools) to consume ClickFix intelligence.

## Authentication methods

### A) User API key (recommended)

Use your personal key generated in dashboard:

`Dashboard -> Investigacion -> Fuentes de investigacion -> API de plataforma`

Send it in:

- `X-API-Key: <your_key>`
- or `Authorization: ApiKey <your_key>`

Security model:

- Keys are stored hashed (never in clear text).
- Full key is shown only once at creation.
- Per-key expiration and revocation.
- Per-key rate limiting (`max_rpm`).
- Scope-limited access (`intel:read`).

### B) License + Bearer token (legacy/connector mode)

1. `POST /api/token.php` with `license_key` + `device_id`
2. Use `Authorization: Bearer <access_token>`
3. Refresh with `POST /api/refresh.php`

## Endpoints

### 1) Intelligence feed

`GET /api/intel.php`

Query params:

- `view=alerts|iocs|events|stix`
- `limit=1..2000`
- `since_id=<id>` incremental sync
- `since=<iso8601>`
- `review_status=all|pending|accepted|rejected`
- `blocked=all|0|1`
- `type=all|domain|ip|url` (IOC feeds)
- `graph_id=<id>` (for `view=events`)
- `include_context=1` (restricted for low-privilege user API keys)

Examples:

- `/api/intel.php?view=iocs&type=all&limit=500`
- `/api/intel.php?view=stix&limit=500`
- `/api/intel.php?view=alerts&since_id=1200`
- `/api/intel.php?view=events&limit=200`

### 2) Single indicator lookup

`GET /api/lookup.php`

Query params:

- `indicator=<domain|ip|url>`
- `limit=1..120`
- `format=json|stix`

Examples:

- `/api/lookup.php?indicator=example.com`
- `/api/lookup.php?indicator=1.2.3.4`
- `/api/lookup.php?indicator=https://evil.example/path&format=stix`

## cURL examples (user API key)

```bash
curl -H "X-API-Key: YOUR_API_KEY" \
  "https://your-clickfix-domain/api/intel.php?view=iocs&limit=100"
```

```bash
curl -H "X-API-Key: YOUR_API_KEY" \
  "https://your-clickfix-domain/api/lookup.php?indicator=example.com"
```

## Suggested integrations

- OpenCTI: ingest `/api/intel.php?view=stix`
- Mitaka / Sputnik: query `/api/lookup.php?indicator=...`
- Threat.rip / TIPs: ingest `/api/intel.php?view=iocs`
- SIEM/SOC tools: ingest `/api/intel.php?view=alerts` and `/api/intel.php?view=events`

## Hardening recommendations

- Rotate API keys periodically (e.g. every 30-90 days).
- Set minimum needed `max_rpm` per integration.
- Revoke keys immediately if leaked.
- Keep API keys in a secrets manager (never plaintext in repos).
- Restrict inbound origins/IPs at reverse proxy or WAF.
