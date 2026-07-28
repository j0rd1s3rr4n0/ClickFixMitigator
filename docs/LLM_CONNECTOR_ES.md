# Conector LLM para investigaciones automatizadas

Este conector lee alertas desde la API de ClickFix, pide a un LLM un analisis defensivo y crea una investigacion con resumen, veredicto, etiquetas y grafo.

## Flujo

1. `GET /api/alerts.php` obtiene alertas pendientes.
2. `GET /api/alert.php` recupera el detalle de cada alerta.
3. El LLM devuelve JSON estructurado con veredicto, evidencias y nodos.
4. `POST /api/investigations.php` guarda la investigacion.
5. Opcionalmente, `POST /api/review.php` aplica el estado recomendado por el LLM.

## Scopes necesarios

Para API key de usuario:

- `alerts:read`
- `investigations:write`
- `reviews:write` solo si se usa `--apply-review`

La API key se crea en:

`Dashboard -> Investigacion -> Fuentes de investigacion -> API de plataforma`

## Variables de entorno

```powershell
$env:CLICKFIX_BASE_URL = "https://tu-dominio-clickfix"
$env:CLICKFIX_API_KEY = "cfk_tu_api_key"
$env:LLM_API_KEY = "tu_llm_api_key"
```

Opcionales:

```powershell
$env:LLM_BASE_URL = "https://api.openai.com/v1"
$env:LLM_MODEL = "gpt-4.1-mini"
$env:CLICKFIX_LLM_LIMIT = "10"
$env:CLICKFIX_LLM_REVIEW_STATUS = "pending"
$env:CLICKFIX_LLM_STATE_FILE = ".clickfix-llm-investigator-state.json"
```

Tambien se puede usar `CLICKFIX_BEARER_TOKEN` en vez de `CLICKFIX_API_KEY`.

## Ejecucion

Prueba sin escribir cambios:

```powershell
python tools\llm_investigator.py --dry-run --limit 3
```

Crear investigaciones:

```powershell
python tools\llm_investigator.py --limit 10
```

Crear investigaciones y aplicar el estado de revision recomendado:

```powershell
python tools\llm_investigator.py --limit 10 --apply-review
```

## Automatizacion

En Windows Task Scheduler, ejecuta cada 5 o 10 minutos:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "cd C:\ruta\ClickFixMitigator; python tools\llm_investigator.py --limit 10"
```

## Seguridad operativa

- Empieza con `--dry-run`.
- Usa una API key con minimo privilegio.
- No actives `--apply-review` hasta validar varios resultados manualmente.
- El estado local evita reprocesar alertas ya analizadas.
- El conector no modifica listas de bloqueo ni allowlist; solo crea investigaciones y, si se solicita, reviews.
