# ClickFix Mitigator

ClickFix Mitigator is a professional, end-to-end reference project for understanding, detecting, and mitigating ClickFix-style social engineering attacks—campaigns that trick users into running commands (typically via **Win + R**, terminal prompts, or “copy/paste to fix” workflows). It combines a browser extension, a Windows agent, and a lightweight PHP + SQLite backend with a modern dashboard.

## Comic strip explaining the full flow:
<center><p>
<img src="assets/1_en.png" alt="The deception" width="500"/>
<img src="assets/2_en.png" alt="The interruption" width="500"/>
<img src="assets/3_new_en.png" alt="The block before execution" width="500"/>
</p></center>

## Purpose and scope

- **Education-first**: provides a safe, documented reference for defenders to learn how ClickFix attacks work and how to mitigate them.
- **Operationally useful**: includes telemetry collection, dashboards, and review workflows to support real-world triage.
- **Modular deployment**: each component can run independently or together, depending on your environment.

## Components

- **Browser extension (MV3)**
  - Detects ClickFix patterns (suspicious command prompts, clipboard/selection mismatches, copy triggers).
  - Monitors signals such as “Win + R” hints or command-shaped text that indicates social engineering.
- **Windows agent (Python)**
  - Observes clipboard changes, paste events, and process command lines.
  - Flags suspicious commands and generates telemetry for the dashboard.
- **Backend endpoint + dashboard (PHP + SQLite)**
  - `Web/ClickFix/clickfix-report.php` receives reports and statistics.
  - `Web/ClickFix/dashboard.php` provides a multi-language operator dashboard with detection review, appeals, and intel feeds.

> Note: the `agent.py` mitigator is under maintenance (testing).

## What the dashboard provides

- **Live summaries**: total alerts, total blocks, recent detections, and top countries.
- **Operational workflows**:
  - Mark detections as accepted.
  - Clear unaccepted detections.
  - Approve or reject appeal submissions.
- **Appeals pipeline**: external parties can file a request when a domain is blocked.
- **Analyst suggestions**: non-admin users can suggest list changes for review.
- **Structured logs**: JSON log lines are rendered in a readable table for quick triage.
- **Intel feeds**: external references are scraped and cached (with an admin refresh button) to surface highlighted patterns and techniques.
- **Multi-language UI**: Spanish and English toggles with consistent labels.

## Intelligence sources

The dashboard ingests a lightweight, cached summary of external references to keep analysts aligned with known patterns:

- Hudson Rock (blog reference)
- ClickFix Carson (reference landing)
- ClickGrab Techniques (techniques catalog)
- ClickFix Patterns (pattern collection)

These are fetched with a TTL cache in `Web/ClickFix/data/intel-cache.json` and can be refreshed by admins via the dashboard.

## Data flow overview

- **Extension / agent** → POSTs to `clickfix-report.php` → **SQLite** → **Dashboard**
- **Dashboard** reads:
  - `clickfix.sqlite` (reports, stats, users, appeals)
  - `clickfix-report.log` / `clickfix-debug.log` (structured event logs)
  - local intel cache (scraped references)

## Repository layout

- `browser-extension/` — MV3 extension and test pages.
- `windows-agent/` — Python agent and rule configuration.
- `Web/ClickFix/` — PHP endpoint, dashboard, and SQLite data.
- `assets/` — comic strip images for documentation.
- `demo/` — ClickFix proof-of-concepts and example pages.

---

# Quick start

## 1) Browser extension

1. Open `chrome://extensions` (Edge/Brave also work).
2. Enable **Developer mode**.
3. Select **Load unpacked** and point to `browser-extension/`.
4. Open a test page (see `demo/`) and verify alerts for clipboard mismatches or suspicious prompts.

More details in `browser-extension/README.md`.

## 2) Windows agent

```powershell
cd windows-agent
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python agent.py
```

More details in `windows-agent/README.md`.

## 3) PHP backend + dashboard

The endpoint uses SQLite at `Web/ClickFix/data/clickfix.sqlite`. Ensure the `Web/ClickFix/data/` folder is writable by your PHP process. The schema lives in `Web/ClickFix/data/clickfix.sql` and is auto-initialized if the database is missing.

```bash
mkdir -p Web/ClickFix/data
sqlite3 Web/ClickFix/data/clickfix.sqlite < Web/ClickFix/data/clickfix.sql
chmod 775 Web/ClickFix/data
```

If PHP cannot write, check permissions/ownership of `data/` and the `.sqlite` file.

### Example: sending a report (Python)

```python
import requests
from datetime import datetime, timezone

CLICKFIX_REPORT_URL = "https://your-domain.com/clickfix-report.php"

payload = {
    "url": "https://example.com/alert",
    "hostname": "example.com",
    "timestamp": datetime.now(timezone.utc).isoformat(),
    "message": "Suspicious command pattern detected",
    "detectedContent": "powershell -nop -w hidden -enc ...",
    "full_context": "Full captured context ...",
    "signals": {
        "mismatch": False,
        "commandMatch": True,
        "winRHint": False,
        "winXHint": False,
        "browserErrorHint": False,
        "fixActionHint": False,
        "captchaHint": False,
        "consoleHint": True,
        "shellHint": True,
        "pasteSequenceHint": False,
        "fileExplorerHint": False,
        "copyTriggerHint": False,
        "evasionHint": False,
    },
}

response = requests.post(CLICKFIX_REPORT_URL, json=payload, timeout=10)
print(response.status_code, response.text)
```

---

# Security, permissions, and privacy

- The **extension** requests access to `<all_urls>` and clipboard permissions to validate selection/paste mismatches and detect deceptive prompts.
- The **Windows agent** reads clipboard and process command lines to identify malicious patterns.
- The **dashboard** stores data in SQLite and exposes admin-only actions (approval/cleanup). Protect it with server access control.

If you need to reduce scope, review:

- `browser-extension/manifest.json`
- `windows-agent/config.json`
- `Web/ClickFix/dashboard.php` and `clickfix-report.php`

---

# Testing

- **Extension**: use `demo/` pages and `browser-extension/demo-*.html` to validate alerts.
- **Agent**: tune `windows-agent/config.json` rules and run simulated commands.
- **Backend**: POST sample data and confirm it appears in the dashboard.

---

# Project status

This repository is a high-quality educational reference. For production, consider:

- Automated tests and CI
- Auth hardening and least-privilege access
- A dedicated secrets/config system
- A secure deployment pipeline

Contributions are welcome if they align with the project’s defensive goals.

---

# ClickFix Mitigator (ES)

ClickFix Mitigator es un proyecto profesional y completo para entender, detectar y mitigar ataques de ingeniería social tipo ClickFix: campañas que persuaden a usuarios para ejecutar comandos (por ejemplo con **Win + R** o “copiar/pegar para arreglar”). Integra una extensión de navegador, un agente de Windows y un backend ligero en PHP + SQLite con un dashboard moderno.

## Cómic que explica el flujo completo:
<center><p>
<img src="assets/1_es.png" alt="El engaño" width="500"/>
<img src="assets/2_es.png" alt="La interrupción" width="500"/>
<img src="assets/3_new_es.png" alt="El bloqueo antes de la ejecución" width="500"/>
</p></center>

## Propósito y alcance

- **Enfoque educativo**: referencia segura para entender ClickFix y mitigaciones defensivas.
- **Útil operativamente**: telemetría, paneles y flujos de revisión para equipos defensivos.
- **Modular**: cada componente puede desplegarse de forma independiente.

## Componentes

- **Extensión de navegador (MV3)**
  - Detecta patrones ClickFix, discrepancias entre selección y portapapeles, y señales de “copiar y ejecutar”.
- **Agente de Windows (Python)**
  - Observa portapapeles, eventos de pegado y procesos nuevos.
  - Alerta ante comandos sospechosos y genera telemetría.
- **Endpoint + dashboard (PHP + SQLite)**
  - `Web/ClickFix/clickfix-report.php` recibe reportes/estadísticas.
  - `Web/ClickFix/dashboard.php` muestra detecciones, revisiones y fuentes de inteligencia.

> Nota: el mitigador `agent.py` está en mantenimiento (en pruebas).

## Qué ofrece el dashboard

- **Resumen operativo**: alertas, bloqueos, países y detecciones recientes.
- **Flujos de revisión**:
  - Marcar detecciones como aceptadas.
  - Limpiar detecciones no aceptadas.
  - Aprobar o rechazar desistimientos.
- **Desistimientos**: solicitud de revisión si un dominio está bloqueado.
- **Sugerencias**: analistas pueden proponer cambios y un admin aprueba.
- **Logs estructurados**: tabla legible desde `clickfix-report.log`.
- **Fuentes de inteligencia**: scraping cacheado de referencias externas con refresco admin.
- **Interfaz multiidioma**: español e inglés.

## Fuentes de inteligencia

Se resumen referencias externas para acelerar el análisis:

- Hudson Rock (artículo de referencia)
- ClickFix Carson (landing de referencia)
- ClickGrab Techniques (catálogo de técnicas)
- ClickFix Patterns (patrones documentados)

La cache vive en `Web/ClickFix/data/intel-cache.json` con TTL y refresco manual.

## Flujo de datos

- **Extensión / agente** → POST a `clickfix-report.php` → **SQLite** → **Dashboard**
- El dashboard lee:
  - `clickfix.sqlite` (reportes, stats, usuarios, apelaciones)
  - `clickfix-report.log` / `clickfix-debug.log` (logs)
  - cache de inteligencia (scraping)

## Estructura del repo

- `browser-extension/` — extensión MV3 y páginas de prueba.
- `windows-agent/` — agente Python y reglas.
- `Web/ClickFix/` — backend PHP, dashboard y SQLite.
- `assets/` — cómics de documentación.
- `demo/` — PoCs y ejemplos.

---

# Inicio rápido

## 1) Extensión

1. Ve a `chrome://extensions` (Edge/Brave también funciona).
2. Activa **modo desarrollador**.
3. Selecciona **Cargar sin empaquetar** y apunta a `browser-extension/`.
4. Abre páginas de prueba (`demo/`) y verifica alertas.

Más detalles en `browser-extension/README.md`.

## 2) Agente de Windows

```powershell
cd windows-agent
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python agent.py
```

Más detalles en `windows-agent/README.md`.

## 3) Servidor (PHP)

SQLite vive en `Web/ClickFix/data/clickfix.sqlite`. El directorio `data/` debe ser escribible por PHP.

```bash
mkdir -p Web/ClickFix/data
sqlite3 Web/ClickFix/data/clickfix.sqlite < Web/ClickFix/data/clickfix.sql
chmod 775 Web/ClickFix/data
```

Si hay errores, revisa permisos/propietario del directorio y del archivo `.sqlite`.

### Ejemplo de envío en Python

```python
import requests
from datetime import datetime, timezone

CLICKFIX_REPORT_URL = "https://tu-dominio.com/clickfix-report.php"

payload = {
    "url": "https://ejemplo.com/alerta",
    "hostname": "ejemplo.com",
    "timestamp": datetime.now(timezone.utc).isoformat(),
    "message": "Detección de comando sospechoso",
    "detectedContent": "powershell -nop -w hidden -enc ...",
    "full_context": "Contenido completo detectado ...",
    "signals": {
        "mismatch": False,
        "commandMatch": True,
        "winRHint": False,
        "winXHint": False,
        "browserErrorHint": False,
        "fixActionHint": False,
        "captchaHint": False,
        "consoleHint": True,
        "shellHint": True,
        "pasteSequenceHint": False,
        "fileExplorerHint": False,
        "copyTriggerHint": False,
        "evasionHint": False,
    },
}

response = requests.post(CLICKFIX_REPORT_URL, json=payload, timeout=10)
print(response.status_code, response.text)
```

---

# Seguridad, permisos y privacidad

- La **extensión** solicita `<all_urls>` y permisos de portapapeles para validar discrepancias y detectar prompts engañosos.
- El **agente** lee portapapeles y línea de comandos de procesos para detectar patrones maliciosos.
- El **dashboard** es un panel operativo: protégelo con controles de acceso.

Si quieres limitar el alcance, revisa:

- `browser-extension/manifest.json`
- `windows-agent/config.json`
- `Web/ClickFix/dashboard.php` y `clickfix-report.php`

---

# Pruebas

- **Extensión**: usa `demo/` y `browser-extension/demo-*.html` para validar notificaciones.
- **Agente**: ajusta reglas en `windows-agent/config.json` y ejecuta comandos simulados.
- **Backend**: envía reportes de prueba y valida en el dashboard.

---

# Estado del proyecto

Este repositorio es una referencia educativa de alta calidad. Para uso real, se recomienda:

- Tests automatizados y CI
- Endurecimiento de auth y permisos
- Gestión de configuración/secretos
- Pipeline de despliegue seguro

Las mejoras son bienvenidas si respetan los objetivos defensivos del proyecto.
