# ClickFix Mitigator

ClickFix Mitigator es un proyecto de referencia para entender y mitigar ataques de ingeniería social que empujan a ejecutar comandos (ClickFix). Incluye una extensión de navegador y un agente para Windows que pueden usarse por separado o juntos.

## Componentes

- **Extensión de navegador (MV3)**: detecta patrones típicos de ClickFix, discrepancias entre selección y portapapeles, y contenido que intenta inducir el uso de **Win + R**.
- **Agente de Windows (Python)**: vigila cambios del portapapeles, eventos de pegado y nuevos procesos para alertar sobre comandos sospechosos.
- **Endpoint y dashboard**: `clickfix-report.php` recibe reportes/estadísticas y `dashboard.php` muestra un resumen público con detecciones recientes.

## Inicio rápido

### Extensión

1. Ve a `chrome://extensions` (Edge/Brave también funciona).
2. Activa **modo desarrollador**.
3. Selecciona **Cargar sin empaquetar** y apunta a `browser-extension/`.
4. Abre una página de pruebas, copia texto y verifica las alertas cuando el contenido del portapapeles no coincide.

Más detalles en `browser-extension/README.md`.

### Agente de Windows

```powershell
cd windows-agent
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python agent.py
```

Más detalles en `windows-agent/README.md`.

## Permisos y privacidad

- La extensión solicita acceso a `<all_urls>` para inspeccionar texto y eventos en las páginas visitadas, y permisos de portapapeles para validar discrepancias entre selección y pegado.
- El agente de Windows lee el portapapeles y la línea de comandos de procesos para detectar patrones maliciosos.

Si necesitas limitar el alcance, revisa los permisos en `browser-extension/manifest.json` y las reglas en `windows-agent/config.json`.

## Pruebas

- **Extensión**: usa los HTML de prueba en `browser-extension/` (por ejemplo, `demo-*.html`) y valida que las notificaciones aparezcan cuando el texto copiado/pegado cambia o coincide con reglas sospechosas.
- **Agente**: ajusta las reglas en `windows-agent/config.json` y ejecuta comandos simulados para verificar los toasts.
- **PoCs**: la carpeta `demo/` contiene PoCs de ClickFix adicionales para simular campañas reales.

## Estado del proyecto

Este repositorio es una referencia educativa. Para un entorno real, conviene añadir pruebas automatizadas, un flujo de despliegue seguro y políticas claras de uso. Cualquier mejora es bienvenida siempre que tenga sentido para el objetivo del proyecto.
