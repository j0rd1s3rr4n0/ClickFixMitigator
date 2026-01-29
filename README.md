# ClickFix Mitigator

<p align="center">
  <a href="https://github.com/jordiserrano/ClickFixMitigator/stargazers">
    <img src="https://img.shields.io/github/stars/jordiserrano/ClickFixMitigator?style=for-the-badge" alt="GitHub stars" />
  </a>
  <a href="https://github.com/jordiserrano/ClickFixMitigator/issues">
    <img src="https://img.shields.io/github/issues/jordiserrano/ClickFixMitigator?style=for-the-badge" alt="Open issues" />
  </a>
  <a href="https://github.com/jordiserrano/ClickFixMitigator/pulls">
    <img src="https://img.shields.io/github/issues-pr/jordiserrano/ClickFixMitigator?style=for-the-badge" alt="Open pull requests" />
  </a>
  <a href="https://github.com/jordiserrano/ClickFixMitigator/graphs/contributors">
    <img src="https://img.shields.io/github/contributors/jordiserrano/ClickFixMitigator?style=for-the-badge" alt="Contributors" />
  </a>
  <a href="https://github.com/jordiserrano/ClickFixMitigator/tags">
    <img src="https://img.shields.io/github/v/tag/jordiserrano/ClickFixMitigator?style=for-the-badge" alt="Latest tag" />
  </a>
  <a href="LICENSE">
    <img src="https://img.shields.io/github/license/jordiserrano/ClickFixMitigator?style=for-the-badge" alt="License" />
  </a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/defense--first-security-blue?style=flat-square" alt="Defense-first" />
  <img src="https://img.shields.io/badge/mv3-extension-orange?style=flat-square" alt="MV3 extension" />
  <img src="https://img.shields.io/badge/php%2Bsqlite-backend-purple?style=flat-square" alt="PHP + SQLite" />
  <img src="https://img.shields.io/badge/windows-agent-green?style=flat-square" alt="Windows agent" />
</p>

ClickFix Mitigator is a complete, defense‑first reference implementation for understanding, detecting, and mitigating **ClickFix-style social engineering** campaigns. These attacks trick users into running commands (commonly via **Win + R**, terminal prompts, or “copy/paste to fix” workflows). This repository provides:

- A **browser extension** that detects suspicious flows and clipboard anomalies.
- A **Windows agent** that observes clipboard/process activity for command‑like payloads.
- A **PHP + SQLite backend** and an **operations dashboard** to review alerts, triage reports, and manage lists.

> This project is designed for education, blue‑team analysis, and controlled deployments. Treat it as a reference implementation and adapt it to your organization’s security policies.

---

## Visual overview

<p align="center">
  <img src="assets/1_en.png" alt="The deception" width="450" />
  <img src="assets/2_en.png" alt="The interruption" width="450" />
  <img src="assets/3_new_en.png" alt="The block before execution" width="450" />
</p>

---

## Why ClickFix Mitigator exists

ClickFix campaigns weaponize trust and urgency. They lure users into executing commands under the guise of “verification,” “fixing an error,” or “completing a CAPTCHA.” This project helps defenders:

- **Understand attacker tradecraft** through curated signals and intel references.
- **Detect suspicious behavior** in real-time (clipboard, command prompts, and social engineering cues).
- **Triage and respond** using an operator dashboard with roles, workflows, and audit‑friendly logs.

---

## Key features

### Detection & prevention
- Command pattern recognition (PowerShell, cmd, mshta, rundll32, etc.).
- Clipboard/selection mismatch detection.
- Page context signals (e.g., fake CAPTCHA, Win+R guidance, “fix action” prompts).
- Optional clipboard blocking for high‑confidence detections.
- Fullscreen safety: blocks fullscreen attempts on detected pages and shows an extension‑branded exit prompt.
- Nested iframe context scanning to improve opaque‑frame reporting.

### Operational workflows
- Admin/analyst roles and verification.
- Accept/reject detections and appeals.
- Manage allowlist/blocklist with audit logs.
- Redaction of credential‑like content for non‑admin viewers.
- Popup dashboard with alert history, manual report reason, and local stats.

### Telemetry & analytics
- Structured JSON logs and report ingestion.
- Country‑level activity summaries.
- Daily/hourly charts and signal distribution.
- Local popup charts for detections, blocked sites, allowlist, and blocklist.

---

## Components

| Component | Path | Purpose |
|---|---|---|
| Browser extension (MV3) | `browser-extension/` | Detects ClickFix patterns in browser context and monitors clipboard signals. |
| Windows agent | `windows-agent/` | Observes clipboard changes, paste events, and command‑line activity. |
| Backend + Dashboard | `Web/ClickFix/` | Receives reports, stores data in SQLite, and presents the operator UI. |
| Botanalyzer (Selenium) | `botanalyzer/` | Automates URL visits and interaction in controlled environments. |
| Demos / PoCs | `demo/` | Example pages to reproduce ClickFix patterns safely. |
| Documentation | `docs/` | Feature ledger, notes, and legacy reintegration plan. |

---

## Official links

- GitHub repository: `https://github.com/j0rd1s3rr4n0/ClickFixMitigator`
- Chrome Web Store: `https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa`

---

## System architecture

```
[Browser Extension / Windows Agent]
            │
            ▼
   clickfix-report.php (POST JSON)
            │
            ▼
       SQLite database
            │
            ▼
         Dashboard UI
```

### Data flow at a glance
- **Signals → report**: extension/agent emits JSON payloads.
- **Backend**: validates input and stores reports/statistics.
- **Dashboard**: reads SQLite and logs for human triage.

---

## Repository layout

```
browser-extension/   # MV3 extension code and demos
windows-agent/       # Python agent, config, and setup
Web/ClickFix/         # PHP backend + dashboard + SQLite data
botanalyzer/          # Selenium automation helper
assets/               # Illustration images
demo/                 # Example ClickFix workflows
docs/                 # Feature ledger and research notes
```

---

## Quick start

### 1) Browser extension

1. Open `chrome://extensions` (Edge/Brave also work).
2. Enable **Developer mode**.
3. Click **Load unpacked** and select `browser-extension/`.
4. Open a page in `demo/` to simulate ClickFix behavior.

By default the extension loads in **English** with **Detection active** and **Block all clipboard by JavaScript** enabled.
More details: `browser-extension/README.md`

### 2) Windows agent (optional)

```powershell
cd windows-agent
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python agent.py
```

More details: `windows-agent/README.md`

### 3) Backend + dashboard

The backend uses SQLite at `Web/ClickFix/data/clickfix.sqlite`. Ensure the `Web/ClickFix/data/` directory is writable by PHP.

```bash
mkdir -p Web/ClickFix/data
sqlite3 Web/ClickFix/data/clickfix.sqlite < Web/ClickFix/data/clickfix.sql
chmod 775 Web/ClickFix/data
```

Start a local server for evaluation:

```bash
php -S 0.0.0.0:8000 -t /path/to/ClickFixMitigator
```

Open: `http://localhost:8000/dashboard.php`

### 4) Botanalyzer (optional, lab use)

```powershell
cd botanalyzer
python botanalyzer.py
```

Botanalyzer reads `urls.txt`, visits each URL, and moves processed entries to `done.txt`. It also loads the repo extension by default when present.

---

## Configuration & customization

### Extension
- Manifest permissions live in `browser-extension/manifest.json`.
- Detection patterns and UI behaviors are in `browser-extension/background.js` and content scripts.
- Supported languages: English, Spanish, Catalan, German, French, Dutch, Portuguese, Russian, Chinese, Japanese, Korean, Hindi, Arabic, and Hebrew (RTL for Arabic/Hebrew).

### Agent
- Rules and heuristics are in `windows-agent/config.json`.
- Run in a controlled environment to validate signal tuning.

### Backend
- Endpoint: `Web/ClickFix/clickfix-report.php`
- Dashboard: `Web/ClickFix/dashboard.php`
- SQLite schema: `Web/ClickFix/data/clickfix.sql`

---

## Security & privacy considerations

- The extension reads clipboard/selection context to detect ClickFix patterns.
- The agent inspects clipboard and process command lines.
- The dashboard stores reports and user actions in SQLite.

**Recommendations**:
- Restrict dashboard access via authentication and network controls.
- Use TLS for all endpoints receiving telemetry.
- Review your data retention policies and legal requirements.

---

## Logs & data storage

| File | Purpose |
|---|---|
| `Web/ClickFix/data/clickfix.sqlite` | Primary SQLite database for reports, users, stats. |
| `Web/ClickFix/clickfix-report.log` | Structured report log (JSON lines). |
| `Web/ClickFix/clickfix-debug.log` | Debug log for troubleshooting. |

---

## Legacy entrypoints

For backward compatibility, the repo root includes legacy routes:

- `dashboard.php` → delegates to `Web/ClickFix/dashboard.php`.
- `dashboard.php.bak` → preserves the original pre‑modified dashboard.
- `PrivacyPolicy.html` → forwards to the updated policy in `Web/ClickFix/`.

---

## Testing guidance

- **Extension**: validate with `demo/` pages and clipboard mismatch scenarios.
- **Agent**: simulate safe command snippets to confirm detections.
- **Backend**: POST JSON payloads and verify they appear in the dashboard.

---

## Contribution guidelines

Contributions are welcome if they align with the project’s defensive goals. Please:

- Open a discussion before large feature additions.
- Include clear documentation and a rationale for detection changes.
- Keep user privacy in mind when modifying telemetry.

---

## License

See `LICENSE` for details.

---

# ClickFix Mitigator (ES)

ClickFix Mitigator es una implementación de referencia completa y defensiva para entender, detectar y mitigar campañas de ingeniería social tipo **ClickFix**. Estas campañas engañan a los usuarios para ejecutar comandos (típicamente con **Win + R**, terminales o flujos de “copiar/pegar para arreglar”). El repositorio incluye:

- **Extensión de navegador** para detectar patrones sospechosos.
- **Agente de Windows** para observar portapapeles y comandos.
- **Backend en PHP + SQLite** con un **dashboard** de operación.

## Resumen visual

<p align="center">
  <img src="assets/1_es.png" alt="El engaño" width="450" />
  <img src="assets/2_es.png" alt="La interrupción" width="450" />
  <img src="assets/3_new_es.png" alt="El bloqueo antes de la ejecución" width="450" />
</p>

## Características principales

- Detección de comandos (PowerShell/cmd/mshta/etc.).
- Alertas por discrepancias de portapapeles y señales de contexto.
- Flujos operativos para aceptar/rechazar detecciones y apelaciones.
- Redacción de credenciales para usuarios no administradores.
- Analítica con resúmenes por país y gráficos por hora/día.
- Bloqueo de pantalla completa en páginas con detecciones y aviso claro de salida.
- Dashboard local en el popup con estadísticas y gráfico de detecciones.

## Enlaces oficiales

- GitHub: `https://github.com/j0rd1s3rr4n0/ClickFixMitigator`
- Chrome Web Store: `https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa`

## Idiomas disponibles

Inglés, español, catalán, alemán, francés, neerlandés, portugués, ruso, chino, japonés, coreano, hindi, árabe y hebreo (RTL).

## Inicio rápido (resumen)

1. **Extensión**: cargar `browser-extension/` en `chrome://extensions`.
2. **Agente**: ejecutar `windows-agent/agent.py` (ver README interno).
3. **Backend**: levantar PHP con SQLite en `Web/ClickFix/` y abrir `dashboard.php`.

## Consideraciones de seguridad

- Restringe el acceso al dashboard.
- Usa TLS para el endpoint.
- Define políticas de retención de datos.

---

Si deseas un despliegue productivo, adapta permisos, auditoría y políticas de privacidad a los requisitos de tu organización.
