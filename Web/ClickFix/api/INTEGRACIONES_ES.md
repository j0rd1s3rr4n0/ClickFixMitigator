# API de Integraci?n ClickFix (Usuario + API key / Bearer)

Esta gu?a cubre integraciones de usuario con API key y automatizaciones con bearer token.

## 1) Crear tu API key de usuario

Ruta en la web:

`Dashboard -> Investigaci?n -> Fuentes de investigaci?n -> API de plataforma`

Desde ah? puedes:

- Crear API keys personales.
- Definir expiraci?n y RPM m?ximo.
- Revocar claves comprometidas.

Notas de seguridad:

- La clave completa se muestra solo una vez.
- El servidor guarda solo el hash de la clave.
- Cada clave tiene caducidad y revocaci?n independiente.

## 2) Autenticaci?n

### A) API key de usuario

Cabeceras soportadas:

- `X-API-Key: TU_API_KEY`
- `Authorization: ApiKey TU_API_KEY`

### B) Bearer token

1. `POST /api/token.php` con `license_key` y `device_id`
2. Usa `Authorization: Bearer <access_token>`
3. Refresca con `POST /api/refresh.php`

## 3) Descubrimiento de API

- `GET /api/docs.php`

Devuelve:

- m?todos de autenticaci?n soportados
- modelo de rate limit
- lista de endpoints
- scopes requeridos

## 4) Endpoints principales

### Feed de inteligencia

- `GET /api/intel.php`

Par?metros ?tiles:

- `view=alerts|iocs|events|stix`
- `limit=1..2000`
- `since_id=<id>`
- `since=<iso8601>`
- `review_status=all|pending|accepted|rejected|allowlisted`
- `blocked=all|0|1`
- `type=all|domain|ip|url`

### Listado directo de alertas

- `GET /api/alerts.php`

Par?metros ?tiles:

- `limit=1..500`
- `since_id=<id>`
- `since=<iso8601>`
- `review_status=all|pending|accepted|rejected|allowlisted`
- `blocked=all|0|1`
- `include_context=0|1`

### Detalle de una alerta

- `GET /api/alert.php?id=<report_id>`

### Lookup puntual

- `GET /api/lookup.php?indicator=<dominio|ip|url>`

Opcionales:

- `limit=1..120`
- `format=json|stix`

### M?tricas operativas

- `GET /api/stats.php`

Scopes v?lidos:

- `stats:read`
- o `intel:read`
- o `config:read`

### Acci?n de revisi?n

- `POST /api/review.php`

```json
{
  "report_id": 123,
  "review_status": "accepted"
}
```

Valores permitidos para `review_status`:

- `pending`
- `accepted`
- `rejected`
- `allowlisted`

Scopes v?lidos:

- bearer token: `report:write`
- API key de usuario: `reviews:write`

### Gesti?n de listas

- `GET /api/lists.php`
- `POST /api/lists.php`

Lectura:

- devuelve `allowlist`, `blocklist`, `investigatelist` y acciones recientes
- requiere `config:read`

Escritura:

- requiere `lists:write`
- acci?n simple:

```json
{
  "list_type": "allowlist",
  "operation": "add",
  "domain": "example.com",
  "reason": "api"
}
```

- acci?n masiva:

```json
{
  "list_type": "blocklist",
  "operation": "add",
  "domains": ["evil1.tld", "evil2.tld"],
  "reason": "campaign-2026-04"
}
```

### Investigaciones

- `GET /api/investigations.php`
- `POST /api/investigations.php`

Lectura:

- `graph_id=<id>` para detalle
- `limit=1..120` para recientes
- requiere `investigations:read`

Creaci?n/actualizaci?n:

- requiere `investigations:write`

```json
{
  "title": "Investigaci?n campa?a April",
  "site_domain": "example.com",
  "verdict": "investigating",
  "summary": "Pivot inicial desde alerta 123",
  "tags": "campaign, april",
  "graph": {"nodes": [], "edges": []},
  "source_report_id": 123
}
```

### Mensajer?a operativa hacia extensiones

- `GET /api/messages.php`
- `POST /api/message-dispatch.php`

`GET /api/messages.php`:

- lo usa la extensi?n para recoger mensajes
- requiere `report:write`

`POST /api/message-dispatch.php`:

- requiere `messages:write`
- soporta `scope=all|client|clients`

```json
{
  "scope": "clients",
  "client_ids": ["abc123", "def456"],
  "title": "Nueva directiva",
  "body": "Revisad el dominio campaign.tld",
  "severity": "warning",
  "expires_days": 7
}
```

## 5) Ejemplos cURL

```bash
curl -H "X-API-Key: TU_API_KEY"   "https://tu-dominio-clickfix/api/intel.php?view=iocs&limit=100"
```

```bash
curl -H "X-API-Key: TU_API_KEY"   "https://tu-dominio-clickfix/api/alerts.php?review_status=pending&limit=50"
```

```bash
curl -H "X-API-Key: TU_API_KEY"   "https://tu-dominio-clickfix/api/stats.php"
```

```bash
curl -X POST   -H "X-API-Key: TU_API_KEY"   -H "Content-Type: application/json"   -d '{"list_type":"allowlist","operation":"add","domain":"trusted.example","reason":"false-positive"}'   "https://tu-dominio-clickfix/api/lists.php"
```

```bash
curl -X POST   -H "Authorization: Bearer ACCESS_TOKEN"   -H "Content-Type: application/json"   -d '{"report_id":123,"review_status":"allowlisted"}'   "https://tu-dominio-clickfix/api/review.php"
```

## 6) Recomendaciones de seguridad

- Rota claves cada 30-90 d?as.
- Usa m?nimo privilegio y RPM ajustado por integraci?n.
- Guarda la API key en un vault, nunca en texto plano dentro del repositorio.
- Si hay fuga, revoca inmediatamente.
