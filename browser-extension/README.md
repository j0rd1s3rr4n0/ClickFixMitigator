# ClickFix Mitigator (Extensión)

Revision: 2026-01-27

Esta extensión MV3 detecta patrones comunes de ataques ClickFix, discrepancias entre selección y portapapeles y páginas que intentan inducir el uso de **Win + R**.

## Enlaces oficiales

- GitHub: https://github.com/j0rd1s3rr4n0/ClickFixMitigator
- Chrome Web Store: https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa

## Flujo de permisos

- **clipboardRead / clipboardWrite**: necesarios para leer el contenido real del portapapeles al copiar/pegar cuando el navegador lo permite.
- **notifications**: muestra alertas del sistema cuando se detectan patrones sospechosos.
- **storage**: guarda configuración, lista blanca e historial localmente.
- **host permissions (`<all_urls>`)**: permite inspeccionar texto y eventos en páginas visitadas.

## Cómo probar

1. Abre `chrome://extensions` (o equivalente en Edge/Brave) y habilita el *modo desarrollador*.
2. Selecciona **Cargar sin empaquetar** y elige la carpeta `browser-extension/`.
3. Abre una página de pruebas, selecciona texto y cópialo.
4. Cambia el contenido del portapapeles (por ejemplo, copiando un comando `powershell` desde otro lugar) y vuelve a copiar en la página. Se generará una alerta si hay discrepancia.
5. Escribe en la página instrucciones como "Win + R" o "Run dialog" para disparar la heurística de ClickFix.

## Configuración

- Usa el popup para activar o desactivar detecciones, gestionar la lista blanca por dominio y revisar alertas recientes.
- El popup incluye un formulario para reportar manualmente un sitio.
- Las opciones avanzadas muestran el historial completo, permiten limpiar datos y añadir listas externas de ClickFix para bloquear más dominios.
- Activa el modo **Bloquear todo** para impedir cualquier copia al portapapeles realizada por JavaScript (el copiado con teclado sigue funcionando).
- La extension inspecciona iframes anidados para mejorar el conteo de iframes opacos.
- Disponible en inglés, español, alemán, francés y neerlandés.
