# ClickFix Mitigator for Dummies

Revision: 2026-01-27

Guía práctica para reconocer, detectar y prevenir ataques de ClickFix. La extensión y el agente son **apoyos**, no sustituyen el criterio humano ni las buenas prácticas.

## 1) Qué es ClickFix (en pocas palabras)

ClickFix es una técnica de ingeniería social que busca **convencerte de ejecutar comandos** (PowerShell, CMD o el cuadro Ejecutar) con la excusa de “arreglar” algo. El truco suele ser que el comando que te hacen copiar y pegar **no es el que crees** o hace más de lo que parece.

## 2) Señales de alerta (sin herramientas)

- Te piden abrir **Win + R** y pegar un comando “para arreglar un problema”.
- El sitio muestra instrucciones de copiar/pegar que no puedes seleccionar con precisión.
- Te piden ejecutar comandos muy largos o con texto codificado.
- Se menciona “PowerShell”, “cmd”, “enc”, “encodedcommand” o “fromBase64String”.
- La página mete prisa con mensajes de urgencia (“tu equipo está en riesgo, actúa ahora”).

## 3) Prevención básica (sin herramientas)

- **No pegues comandos** si no entiendes exactamente qué hacen.
- Si necesitas soporte, usa canales oficiales y evita seguir instrucciones de sitios desconocidos.
- **Desconfía de comandos codificados** (Base64) y de instrucciones con mucha ofuscación.
- Cuando tengas dudas, **consulta a un experto** antes de ejecutar algo.

## 4) Cómo ayuda la extensión del navegador

La extensión detecta:

- Discrepancias entre lo que seleccionas y lo que realmente queda en el portapapeles.
- Patrones comunes de ClickFix (por ejemplo, instrucciones que inducen a “Win + R”).
- Comandos sospechosos copiados/pegados desde páginas web.

**Úsala como señal de advertencia**, no como garantía. Si aparece una alerta, detente y revisa.

## 5) Cómo ayuda el agente de Windows

El agente de Windows monitorea:

- Cambios del portapapeles.
- Eventos de pegado (Ctrl+V).
- Procesos nuevos y sus líneas de comando.

Si detecta algo sospechoso, muestra un toast con el motivo, el proceso y la ventana activa.

## 6) Flujo recomendado si algo huele mal

1. **No ejecutes** el comando.
2. Verifica el origen: ¿es un sitio oficial? ¿estás en el dominio correcto?
3. Busca referencias del comando en fuentes confiables.
4. Si es en un entorno corporativo, reporta al equipo de seguridad.

## 7) Buenas prácticas para equipos

- Capacitación breve y recurrente sobre ingeniería social.
- Políticas para ejecutar scripts solo desde fuentes confiables.
- Uso de listas blancas y reglas de detección ajustadas.

## 8) Recursos prácticos

- Revisa los HTML de prueba en `browser-extension/` (por ejemplo, `demo-*.html`) para simular escenarios.
- La carpeta `demo/` contiene PoCs adicionales de ClickFix para practicar con campañas más realistas.
- Ajusta reglas en `windows-agent/config.json` si necesitas reducir falsos positivos.

## 9) Recordatorio final

Las herramientas **no reemplazan** el juicio humano. Si algo te parece raro, **detente** y verifica antes de ejecutar cualquier comando. Cualquier mejora es bienvenida siempre que tenga sentido.
