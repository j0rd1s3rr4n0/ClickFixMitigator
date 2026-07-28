# ClickFix Integration API (User API key / Bearer)

This guide covers user API-key integrations and bearer-token automation flows.

## 1) Create a user API key

Dashboard path:

`Dashboard -> Investigation -> Platform API`

From there you can:

- Create personal API keys.
- Set expiration and max RPM.
- Revoke compromised keys.

Security notes:

- The full key is shown only once.
- The server stores only the key hash.
- Each key has independent expiration and revocation.

## 2) Authentication

### A) User API key

Supported headers:

- `X-API-Key: YOUR_API_KEY`
- `Authorization: ApiKey YOUR_API_KEY`

### B) Bearer token

1. `POST /api/token.php` with `license_key` and `device_id`
2. Use `Authorization: Bearer <access_token>`
3. Refresh with `POST /api/refresh.php`

## 3) API discovery

- `GET /api/docs.php`

Returns:

- supported authentication methods
- rate-limit model
- endpoint inventory
- required scopes

## 4) Main endpoints

### Intelligence feed

- `GET /api/intel.php`

Useful parameters:

- `view=alerts|iocs|events|stix`
- `limit=1..2000`
- `since_id=<id>`
- `since=<iso8601>`
- `review_status=all|pending|accepted|rejected|allowlisted`
- `blocked=all|0|1`
- `type=all|domain|ip|url`

### Direct alert listing

- `GET /api/alerts.php`

Useful parameters:

- `limit=1..500`
- `since_id=<id>`
- `since=<iso8601>`
- `review_status=all|pending|accepted|rejected|allowlisted`
- `blocked=all|0|1`
- `include_context=0|1`

### Alert detail

- `GET /api/alert.php?id=<report_id>`

### Indicator lookup

- `GET /api/lookup.php?indicator=<domain|ip|url>`

Optional:

- `limit=1..120`
- `format=json|stix`

### Operational metrics

- `GET /api/stats.php`

Valid scopes:

- `stats:read`
- or `intel:read`
- or `config:read`

### Review action

- `POST /api/review.php`

```json
{
  "report_id": 123,
  "review_status": "accepted"
}
```

Allowed `review_status` values:

- `pending`
- `accepted`
- `rejected`
- `allowlisted`

Valid scopes:

- bearer token: `report:write`
- user API key: `reviews:write`

### List management

- `GET /api/lists.php`
- `POST /api/lists.php`

Read:

- returns `allowlist`, `blocklist`, `investigatelist`, and recent list actions
- requires `config:read`

Write:

- requires `lists:write`
- single action:

```json
{
  "list_type": "allowlist",
  "operation": "add",
  "domain": "example.com",
  "reason": "api"
}
```

- bulk action:

```json
{
  "list_type": "blocklist",
  "operation": "add",
  "domains": ["evil1.tld", "evil2.tld"],
  "reason": "campaign-2026-04"
}
```

### Investigations

- `GET /api/investigations.php`
- `POST /api/investigations.php`

Read:

- `graph_id=<id>` for detail
- `limit=1..120` for recent list
- requires `investigations:read`

Create/update:

- requires `investigations:write`

```json
{
  "title": "April campaign investigation",
  "site_domain": "example.com",
  "verdict": "investigating",
  "summary": "Initial pivot from alert 123",
  "tags": "campaign, april",
  "graph": {"nodes": [], "edges": []},
  "source_report_id": 123
}
```

### Operational messaging to extensions

- `GET /api/messages.php`
- `POST /api/message-dispatch.php`

`GET /api/messages.php`:

- used by the browser extension to pull queued messages
- requires `report:write`

`POST /api/message-dispatch.php`:

- requires `messages:write`
- supports `scope=all|client|clients`

```json
{
  "scope": "clients",
  "client_ids": ["abc123", "def456"],
  "title": "New directive",
  "body": "Review domain campaign.tld",
  "severity": "warning",
  "expires_days": 7
}
```

## 5) cURL examples

```bash
curl -H "X-API-Key: YOUR_API_KEY"   "https://your-clickfix-domain/api/intel.php?view=iocs&limit=100"
```

```bash
curl -H "X-API-Key: YOUR_API_KEY"   "https://your-clickfix-domain/api/alerts.php?review_status=pending&limit=50"
```

```bash
curl -H "X-API-Key: YOUR_API_KEY"   "https://your-clickfix-domain/api/stats.php"
```

```bash
curl -X POST   -H "X-API-Key: YOUR_API_KEY"   -H "Content-Type: application/json"   -d '{"list_type":"allowlist","operation":"add","domain":"trusted.example","reason":"false-positive"}'   "https://your-clickfix-domain/api/lists.php"
```

```bash
curl -X POST   -H "Authorization: Bearer ACCESS_TOKEN"   -H "Content-Type: application/json"   -d '{"report_id":123,"review_status":"allowlisted"}'   "https://your-clickfix-domain/api/review.php"
```

## 6) Security recommendations

- Rotate keys every 30-90 days.
- Use least privilege and per-integration RPM limits.
- Store API keys in a vault, never in plaintext in the repo.
- Revoke immediately on exposure.
