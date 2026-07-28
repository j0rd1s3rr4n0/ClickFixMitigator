# Política de Privacidad - ClickFix Mitigator

**Última actualización:** 2026-04-24

## 1. Responsable
ClickFix Mitigator es una plataforma defensiva formada por extensión de navegador, backend web y componentes opcionales de análisis.

## 2. Datos tratados
Con fines estrictamente de seguridad, el sistema puede tratar:

- Eventos de detección: hostname o URL reportada, timestamp, señales detectadas, score y contexto técnico del evento.
- Evidencias: captura `after` enviada por la extensión y captura `before` generada por servidor para la URL reportada.
- Metadatos operativos: IP de origen, agente de usuario, identificador de cliente seudónimo, versión de extensión, estado y veredicto.
- Listas de seguridad: allowlist, blocklist y preferencias operativas.
- Mensajería operativa para extensiones, cuando aplica.
- Datos de investigación iniciados por el usuario, incluidas consultas y resultados guardados en su espacio.
- Credenciales de API de plataforma, si se usan: se almacenan como hash y se muestran en claro solo en el momento de creación.
- Solicitudes de acceso a la plataforma: correo profesional, idioma, IP de origen, user agent y fecha de envío.

No se vende información personal ni se comercializan datos de usuarios.

## 3. Baseline privado para reducir falsos positivos
La extensión puede mantener un baseline privado por instalación para reducir falsos positivos en hostnames habituales.

- El baseline se asocia a un `client_id` seudónimo, no a la identidad real de una persona.
- El aprendizaje del baseline se hace de forma local en la extensión.
- Para esta función se guardan contadores por hostname, como visitas, días vistos, alertas y resultados de revisión.
- Si el usuario mantiene activado el intercambio de baseline, la extensión puede enviar al servidor un resumen agregado por hostname para mejorar la reducción global de falsos positivos.
- Ese resumen no necesita historial completo de navegación, títulos de página, rutas completas ni query strings para la función de baseline.
- El baseline ayuda a bajar ruido en detecciones débiles o ambiguas, pero no debe anular señales fuertes de ejecución maliciosa.

## 4. Control del usuario
- El usuario puede desactivar el baseline privado desde las opciones de la extensión.
- Si se desactiva, la extensión deja de aprender hosts habituales para esa función y deja de enviar el resumen agregado de baseline.
- La plataforma sigue pudiendo procesar eventos de seguridad necesarios para detectar, revisar y responder a actividad sospechosa.

## 5. API keys de investigación
- Cada usuario puede registrar sus propias API keys, por ejemplo VirusTotal, AbuseIPDB o URLScan.
- Las API keys son de ámbito privado por usuario: solo su propietario puede verlas, modificarlas y usarlas.
- En la interfaz se muestran ofuscadas por defecto y solo se revelan tras una acción explícita de ver.

## 6. Finalidad
Los datos se usan para:

- Detectar y mitigar ataques ClickFix.
- Bloquear ejecución accidental de comandos o flujos maliciosos.
- Gestionar revisión de alertas, veredictos y relación entre incidentes.
- Reducir falsos positivos con señales locales o agregadas de baseline.
- Mejorar investigación y respuesta a incidentes.

## 7. Base legal
Interés legítimo en ciberseguridad, prevención de fraude y protección de sistemas y usuarios en entornos administrados.

## 8. Compartición y terceros
- Los datos se procesan en la plataforma ClickFix Mitigator.
- Integraciones de threat intel, como VirusTotal, AbuseIPDB, URLScan y similares, se ejecutan solo cuando el usuario lanza consultas explícitamente.
- No se habilita tracking publicitario externo por defecto en configuraciones endurecidas.
- El resumen de baseline, cuando está activado, se limita a datos agregados por hostname con finalidad defensiva.

## 9. Retención
La retención depende de la configuración operativa del despliegue. Los registros, evidencias y datos de baseline deben conservarse solo el tiempo necesario para análisis, seguridad operativa y cumplimiento interno.

## 10. Seguridad
Se aplican medidas razonables técnicas y organizativas, incluyendo:

- Controles de acceso por rol.
- Separación de datos por usuario en investigación.
- Validaciones de entrada y controles de tamaño y formato para evidencias.
- Registro de acciones administrativas relevantes.
- Restricciones de acceso y trazabilidad sobre datos sensibles.

## 11. Derechos y contacto
Para solicitudes de acceso, rectificación o eliminación, usar los canales del operador del despliegue o:

- Sitio: https://jordiserrano.me
- Repositorio: https://github.com/j0rd1s3rr4n0/ClickFixMitigator

## 12. Cambios
Esta política puede actualizarse. La fecha de "Última actualización" refleja la versión vigente del documento.
