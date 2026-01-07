# Windows Agent - ClickFix Mitigator

Este agente de Windows monitoriza cambios del portapapeles, eventos de pegado y la creación de procesos para detectar comandos sospechosos.
Incluye notificaciones tipo toast con detalles del proceso/ventana activa.

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
- `sensitivity.process_poll_interval_s`: intervalo de escaneo de procesos.

## Uso

```powershell
python agent.py
```

El agente escuchará:

- Cambios en el portapapeles (copia).
- Pegado (Ctrl+V) usando un hook de teclado.
- Nuevos procesos y sus líneas de comando.

Cuando detecte un comando sospechoso o un pegado distinto al último texto copiado, mostrará un toast con detalles.

## Integración opcional (IPC)

Si existe una extensión de navegador, puedes extender este agente para recibir el dominio de origen (por ejemplo via IPC local o HTTP).
El campo de origen puede añadirse al `AlertContext` antes de mostrar la notificación.

## Notas

- El hook de teclado usa la librería `keyboard`, que puede requerir ejecutar como administrador en algunos entornos.
- El servicio se ejecuta como proceso en primer plano. Para ejecutarlo como servicio de Windows, se puede envolver con `nssm` o `sc.exe`.
