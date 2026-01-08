# ClickFix Mitigator

## English

### Overview
ClickFix Mitigator is a defense-in-depth project focused on detecting and disrupting “ClickFix” social‑engineering flows. It monitors for suspicious clipboard activity, misleading instructions, and pages that try to coerce users into running commands. The browser extension can alert, sanitize the clipboard, and block access to domains reported for ClickFix abuse.

### Key capabilities
- **Real-time clipboard monitoring** to detect suspicious command patterns and intervene before they are pasted.
- **ClickFix hint detection** (e.g., “Win+R”, fake human verification, console paste instructions).
- **Clipboard sanitization** with restore/keep-clean options on notifications.
- **Remote reporting** of detections to a server endpoint.
- **Remote blocklist enforcement** with a full-page warning, plus “stay” or “go back” actions.

### Project structure
- `browser-extension/` — Chrome/Chromium extension (MV3).
  - `content-script.js` — page signals, clipboard monitoring, and block page UI.
  - `background.js` — detection logic, notifications, reporting, and blocklist checks.
- `windows-agent/` — Windows automation/agent components (if used in your deployment).

### Browser extension setup (Chrome/Chromium)
1. Open `chrome://extensions/`.
2. Enable **Developer mode**.
3. Click **Load unpacked** and select `browser-extension/`.
4. Pin the extension for quick access.

### Configuration notes
The extension uses two remote endpoints:
- `https://jordiserrano.me/clickfix-report.php` — detection reporting.
- `https://jordiserrano.me/clickfixlist` — blocklist domain feed.

Ensure your environment allows outbound HTTPS requests if you expect reporting and blocklist features to work.

### Privacy & data handling
Reporting sends detection metadata (URL, hostname, timestamp, and reason text). Adjust the server endpoint or remove reporting if you need a local‑only deployment.

### Development
No build step is required for the extension. Edit files under `browser-extension/`, then reload the extension from `chrome://extensions/`.

### License
See [LICENSE](LICENSE).

---

## Español

### Descripción general
ClickFix Mitigator es un proyecto de defensa en profundidad enfocado en detectar y frenar flujos de ingeniería social tipo “ClickFix”. Supervisa el portapapeles, instrucciones engañosas y páginas que intentan forzar al usuario a ejecutar comandos. La extensión del navegador puede alertar, limpiar el portapapeles y bloquear dominios reportados por abuso ClickFix.

### Capacidades principales
- **Monitorización en tiempo real del portapapeles** para detectar patrones sospechosos e intervenir antes del pegado.
- **Detección de señales ClickFix** (p. ej., “Win+R”, verificación humana falsa, instrucciones para pegar en consola).
- **Limpieza del portapapeles** con opciones para restaurar o mantener limpio desde la notificación.
- **Reporte remoto** de detecciones a un servidor.
- **Bloqueo por lista remota** con aviso a pantalla completa y acciones para permanecer o volver atrás.

### Estructura del proyecto
- `browser-extension/` — Extensión para Chrome/Chromium (MV3).
  - `content-script.js` — señales de página, monitorización del portapapeles y UI de bloqueo.
  - `background.js` — lógica de detección, notificaciones, reporte y lista de bloqueo.
- `windows-agent/` — Componentes del agente para Windows (si se usa en tu despliegue).

### Instalación de la extensión (Chrome/Chromium)
1. Abre `chrome://extensions/`.
2. Activa el **Modo desarrollador**.
3. Pulsa **Cargar descomprimida** y selecciona `browser-extension/`.
4. Ancla la extensión para acceso rápido.

### Notas de configuración
La extensión utiliza dos endpoints remotos:
- `https://jordiserrano.me/clickfix-report.php` — reporte de detecciones.
- `https://jordiserrano.me/clickfixlist` — feed de dominios bloqueados.

Asegúrate de permitir conexiones HTTPS salientes si necesitas reporte y lista de bloqueo.

### Privacidad y datos
El reporte envía metadatos de la detección (URL, hostname, timestamp y motivo). Ajusta el endpoint o elimina el reporte si necesitas un despliegue totalmente local.

### Desarrollo
La extensión no requiere build. Edita los archivos en `browser-extension/` y recarga la extensión desde `chrome://extensions/`.

### Licencia
Consulta [LICENSE](LICENSE).
