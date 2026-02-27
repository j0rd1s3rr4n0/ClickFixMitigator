# Botanalyzer

Automates visiting URLs listed in `urls.txt`, clicking buttons and captcha divs (including nested iframes), and recording progress in `done.txt`.
It keeps the main tab open, clears cache/cookies/history between URLs, and exits cleanly on Ctrl+C.

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
python botanalyzer.py --headful --wait-close 8 --page-timeout 45 --done done.txt
```

Persistent profile + extensions + language:
```bash
python botanalyzer.py --headful --profile-dir .\chrome-profile --extension .\my-ext --lang es-ES --accept-languages es-ES,es,en
```

If `--extension` is omitted and `../browser-extension` exists, Botanalyzer loads it by default and forces Chrome developer mode.

## Inputs / outputs
- `urls.txt`: queue of URLs to process (one per line).
- `done.txt`: processed URLs appended here and removed from `urls.txt`.
- `explorar.json`: optional source list (use `explorar_to_urls.py` to append into `urls.txt`).

## Helpers
```bash
python explorar_to_urls.py --dry-run
python explorar_to_urls.py --input explorar.json --output urls.txt
```

## Carson importer (unified JSON)
Fetches paginated results and builds a unified JSON list (compatible with `explorar_to_urls.py`).
```bash
python carson_unified_importer.py --q . --limit 100 --output explorar.json
python carson_unified_importer.py --q . --limit 100 --output carson_unified.json --include-raw
```
