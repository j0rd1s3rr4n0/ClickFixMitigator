# ClickFix Mitigator (Extension)

Revision: 2026-03-12

Extension defensiva centrada en detectar flujos tipo ClickFix, alertar sobre indicios de interaccion de alto riesgo y reducir la probabilidad de ejecucion accidental.

## Enlaces oficiales

- GitHub: https://github.com/j0rd1s3rr4n0/ClickFixMitigator
- Chrome Web Store: https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa
- Politica de privacidad: ../PrivacyPolicy.md

## Funciones clave

- Deteccion en tiempo real de patrones ClickFix en pagina.
- Analisis de amenaza en portapapeles (comandos, ofuscacion, payloads sospechosos).
- Bloqueo opcional de ejecucion de scripts en paginas bloqueadas.
- Controles de excepcion: permitir una vez, permitir sesion, permitir siempre.
- Reporte manual de paginas/eventos sospechosos.
- Captura `after` al disparar alerta (el `before` se genera en servidor).

## Exclusiones de host

La extension no se activa en:
- `jordiserrano.me`
- `*.jordiserrano.me`
- `any.run`
- `*.any.run`

## Flujo de permisos

- `clipboardRead` / `clipboardWrite`: evaluacion de riesgos de copia/pegado.
- `notifications`: avisos locales de seguridad.
- `storage`: configuracion local, historial y listas.
- `management`: deteccion de canal de instalacion.
- `tabs`: gestion de acciones por pestana y contexto de bloqueo.
- `declarativeNetRequest`: reglas temporales de bloqueo de script en pestanas comprometidas.

## Integracion con plataforma web

- Envia eventos a `clickfix-report.php`.
- Recibe mensajeria administrada desde backend.
- Triage/veredictos y evidencia se gestionan en `Web/ClickFix/dashboard.php`.

## Privacidad

- No vende datos.
- Pensada para uso defensivo en operaciones SOC.
- Integraciones de investigacion externas dependen de acciones explicitas del analista y sus API keys privadas.
