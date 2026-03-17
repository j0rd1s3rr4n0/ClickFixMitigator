# Politica de Privacidad - ClickFix Mitigator

**Ultima actualizacion:** 2026-03-12

## 1. Responsable
ClickFix Mitigator es una plataforma defensiva formada por extension de navegador, backend web y componentes opcionales de analisis.

## 2. Datos tratados
Con fines estrictamente de seguridad, el sistema puede tratar:

- Eventos de deteccion: URL/host, timestamp, senales detectadas, score y contexto tecnico del evento.
- Evidencias: captura `after` enviada por la extension y captura `before` generada por servidor para la URL reportada.
- Metadatos operativos: IP origen, agente de usuario, identificador de cliente, version de extension, estado/veredicto.
- Listas de seguridad: allowlist y blocklist.
- Mensajeria operativa para extensiones (cuando aplica).
- Datos de investigacion iniciados por el usuario (consultas y resultados guardados en su espacio).
- Credenciales de API de plataforma (si se usan): se almacenan como hash y se muestran en claro solo en el momento de creacion.

No se vende informacion personal ni se comercializan datos de usuarios.

## 3. API keys de investigacion
- Cada usuario puede registrar sus propias API keys (por ejemplo VirusTotal, AbuseIPDB, URLScan).
- Las API keys son de ambito privado por usuario: solo su propietario puede ver, modificar y usarlas.
- En la interfaz se muestran ofuscadas por defecto y solo se revelan tras accion explicita de "ver".

## 4. Finalidad
Los datos se usan para:

- Detectar y mitigar ataques ClickFix.
- Bloquear ejecucion accidental de comandos o flujos maliciosos.
- Gestionar revision de alertas, veredictos y relacion entre incidentes.
- Mejorar investigacion y respuesta a incidentes.

## 5. Base legal
Interes legitimo en ciberseguridad, prevencion de fraude y proteccion de sistemas/usuarios en entornos administrados.

## 6. Comparticion y terceros
- Los datos se procesan en la plataforma ClickFix Mitigator.
- Integraciones de threat intel (VirusTotal, AbuseIPDB, URLScan y similares) se ejecutan solo cuando el usuario lanza consultas explicitamente.
- No se habilita tracking publicitario externo por defecto en configuraciones endurecidas.

## 7. Retencion
La retencion depende de la configuracion operativa del despliegue. Los registros y evidencias deben conservarse solo el tiempo necesario para analisis y cumplimiento interno.

## 8. Seguridad
Se aplican medidas razonables tecnicas y organizativas, incluyendo:

- Controles de acceso por rol.
- Separacion de datos por usuario en investigacion.
- Validaciones de entrada y controles de tamano/formato para evidencias.
- Registro de acciones administrativas relevantes.

## 9. Derechos y contacto
Para solicitudes de acceso, rectificacion o eliminacion, usar los canales del operador del despliegue o:

- Sitio: https://jordiserrano.me
- Repositorio: https://github.com/j0rd1s3rr4n0/ClickFixMitigator

## 10. Cambios
Esta politica puede actualizarse. La fecha de "Ultima actualizacion" refleja la version vigente del documento.
