# ClickFix Mitigator (Extension)

Revision: 2026-03-22

Extension defensiva centrada en detectar flujos tipo ClickFix, alertar sobre indicios de interaccion de alto riesgo y reducir la probabilidad de ejecucion accidental.

## Enlaces oficiales

- GitHub: https://github.com/j0rd1s3rr4n0/ClickFixMitigator
- Chrome Web Store: https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa
- Firefox package: build locally with `browser-extension/build-firefox.ps1`
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

## Firefox

La base de codigo es la misma, pero Firefox usa un manifest propio para evitar depender de `service_worker` en el fondo.

### Construir paquete Firefox

```powershell
cd browser-extension
.\build-firefox.ps1
```

Salida esperada:

- `browser-extension/dist/firefox/`
- `browser-extension/dist/clickfix-mitigator-firefox-0.4.10.xpi`

### Probar en Firefox

1. Abre `about:debugging#/runtime/this-firefox`.
2. Pulsa `Load Temporary Add-on`.
3. Selecciona `browser-extension/dist/firefox/manifest.json`.

### Publicacion en Mozilla Add-ons

- Usa el paquete `.xpi` generado en `browser-extension/dist/`.
- El manifest Firefox incluye `browser_specific_settings.gecko` y permisos de recopilacion declarados para el proceso de revision.

### Construir paquete Firefox para Android

```powershell
cd browser-extension
.\build-firefox-android.ps1
```

Salida esperada:

- `browser-extension/dist/firefox-android/`
- `browser-extension/dist/clickfix-mitigator-firefox-android-0.4.10.xpi`

Este paquete declara `browser_specific_settings.gecko_android` para que AMO lo pueda marcar como compatible con Firefox para Android.

## Privacidad

- No vende datos.
- Pensada para uso defensivo en operaciones SOC.
- Integraciones de investigacion externas dependen de acciones explicitas del analista y sus API keys privadas.
