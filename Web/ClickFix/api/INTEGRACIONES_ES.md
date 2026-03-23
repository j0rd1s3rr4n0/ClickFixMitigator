# API de Integracion ClickFix (Usuario + API key)

Esta guia es para usuarios con cuenta en la plataforma web.

## 1) Crear tu API key (seguro)

Entra en:

`Dashboard -> Investigacion -> Fuentes de investigacion -> API de plataforma`

Desde ahi puedes:

- Crear API keys personales.
- Definir expiracion (dias) y RPM maximo.
- Revocar claves comprometidas.

Notas de seguridad:

- La clave completa se muestra solo una vez.
- El servidor guarda solo hash de la clave.
- Cada clave tiene caducidad y revocacion independiente.

## 2) Autenticacion

Usa una de estas cabeceras:

- `X-API-Key: TU_API_KEY`
- `Authorization: ApiKey TU_API_KEY`

## 3) Endpoints principales

### Feed de inteligencia

`GET /api/intel.php`

Parametros utiles:

- `view=alerts|iocs|events|stix`
- `limit=1..2000`
- `since_id=<id>`
- `since=<iso8601>`
- `review_status=all|pending|accepted|rejected`
- `blocked=all|0|1`
- `type=all|domain|ip|url`

### Lookup puntual

`GET /api/lookup.php?indicator=<dominio|ip|url>`

Opcionales:

- `limit=1..120`
- `format=json|stix`

## 4) Ejemplos cURL

```bash
curl -H "X-API-Key: TU_API_KEY" \
  "https://tu-dominio-clickfix/api/intel.php?view=iocs&limit=100"
```

```bash
curl -H "X-API-Key: TU_API_KEY" \
  "https://tu-dominio-clickfix/api/lookup.php?indicator=example.com"
```

## 5) Recomendaciones de seguridad

- Rota claves cada 30-90 dias.
- Usa minimo privilegio y RPM ajustado por integracion.
- Guarda la API key en un vault (no en texto plano).
- Si hay fuga, revoca inmediatamente.
