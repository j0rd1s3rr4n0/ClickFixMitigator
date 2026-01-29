# Windows Agent - ClickFix Mitigator

Revision: 2026-01-29

Este agente de Windows monitoriza cambios del portapapeles, eventos de pegado y patrones típicos de ClickFix.
Detecta secuencias de **Win + R**, **Win + E** y atajos de barra de dirección (**Alt + L**, **Alt + D**, **Ctrl + L**),
incluyendo variantes de pegado y ejecución (**Ctrl + V**, **Shift + Insert**, **Ctrl + Shift + V**, **Enter**, **Ctrl + Shift + Enter**).
Incluye notificaciones tipo toast con detalles del proceso/ventana activa, y un icono en la bandeja del sistema para restaurar el portapapeles bloqueado.

## Requisitos

- Windows 10/11
- Python 3.10+
- Permisos para leer el portapapeles y consultar procesos

## Instalación

```powershell
cd windows-agent
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## Configuración

Edita `config.json` para ajustar reglas, exclusiones, sensibilidad, hotkeys y telemetría local.

- `rules.suspicious_regexes`: regexs para detectar comandos peligrosos.
- `rules.exclusions`: regexs que se deben ignorar.
- `rules.process_allowlist` / `rules.window_allowlist`: procesos o ventanas que se omiten (regex).
- `sensitivity.clipboard_poll_interval_s`: intervalo de lectura del portapapeles.
- `sensitivity.run_sequence_timeout_s`: ventana en segundos para detectar secuencias (Win+R, Win+E, barra de dirección).
- `sensitivity.blocked_clipboard_placeholder`: texto seguro que reemplaza el portapapeles bloqueado.
- `hotkeys.*`: atajos monitorizados (configurables).
- `telemetry.*`: ruta del SQLite local, log JSON y alertsites para el dashboard del agente.

## Uso

```powershell
python agent.py
```

El agente escuchará:

- Cambios en el portapapeles (copia).
- Secuencias Win + R → pegar → ejecutar.
- Secuencias Win + E / barra de dirección → pegar → ejecutar.

Cuando detecte un comando sospechoso, mostrará una alerta para que el usuario decida si permite o deniega el comando. Si se deniega, reemplazará el portapapeles por un marcador seguro y permitirá restaurarlo desde el icono de bandeja.

## Dashboard local (opcional)

El agente escribe reportes y estadísticas en `windows-agent/data/clickfix.sqlite` y logs JSON en `windows-agent/agent.log`.
Puedes revisar el estado con `windows-agent/dashboard.php`:

```bash
php -S 0.0.0.0:8010 -t windows-agent
```

Abre `http://localhost:8010/dashboard.php`.

## Integración opcional (IPC)

Si existe una extensión de navegador, puedes extender este agente para recibir el dominio de origen (por ejemplo via IPC local o HTTP).
El campo de origen puede añadirse al `AlertContext` antes de mostrar la notificación.

## Notas

- El hook de teclado usa la librería `keyboard`, que puede requerir ejecutar como administrador en algunos entornos.
- El servicio se ejecuta como proceso en primer plano con icono en bandeja. Para ejecutarlo como servicio de Windows, se puede envolver con `nssm` o `sc.exe`.
- `agent-debug.log` guarda logs técnicos y `agent.log` guarda eventos JSON para el dashboard.
- El atajo `Ctrl+Shift+U` restaura el último portapapeles bloqueado (configurable).
