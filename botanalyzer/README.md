# Botanalyzer

Automates visiting URLs listed in `urls.txt`, clicking `<button>` elements, and waiting for the page to close.

## Setup
```bash
pip install selenium
```

Selenium uses Selenium Manager to download ChromeDriver automatically. Make sure Chrome is installed.

## Run
```bash
python botanalyzer.py
```

Optional flags:
```bash
python botanalyzer.py --headful --wait-close 8 --page-timeout 45
```

Persistent profile + extensions + language:
```bash
python botanalyzer.py --headful --profile-dir .\chrome-profile --extension .\my-ext --lang es-ES --accept-languages es-ES,es,en
```
