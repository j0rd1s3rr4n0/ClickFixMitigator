# Windows Agent - ClickFix Mitigator

Revision: 2026-01-27

Este agente de Windows monitoriza cambios del portapapeles, eventos de pegado y patrones típicos de ClickFix (Win + R, pegar y ejecutar).
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

Edita `config.json` para ajustar reglas, exclusiones y sensibilidad.

- `rules.suspicious_regexes`: regexs para detectar comandos peligrosos.
- `rules.exclusions`: regexs que se deben ignorar.
- `sensitivity.clipboard_poll_interval_s`: intervalo de lectura del portapapeles.
- `sensitivity.run_sequence_timeout_s`: ventana en segundos para detectar Win + R → pegar → ejecutar.
- `sensitivity.blocked_clipboard_placeholder`: texto seguro que reemplaza el portapapeles bloqueado.

## Uso

```powershell
python agent.py
```

El agente escuchará:

- Cambios en el portapapeles (copia).
- Patrón Win + R → pegar (Ctrl+V o click derecho) → Enter/Ctrl+Shift+Enter.

Cuando detecte un comando sospechoso, mostrará una alerta para que el usuario decida si permite o deniega el comando. Si se deniega, reemplazará el portapapeles por un marcador seguro y permitirá restaurarlo desde el icono de bandeja.

## Integración opcional (IPC)

Si existe una extensión de navegador, puedes extender este agente para recibir el dominio de origen (por ejemplo via IPC local o HTTP).
El campo de origen puede añadirse al `AlertContext` antes de mostrar la notificación.

## Notas

- El hook de teclado usa la librería `keyboard`, que puede requerir ejecutar como administrador en algunos entornos.
- El servicio se ejecuta como proceso en primer plano con icono en bandeja. Para ejecutarlo como servicio de Windows, se puede envolver con `nssm` o `sc.exe`.
- El atajo `Ctrl+Shift+U` restaura el último portapapeles bloqueado.
