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
python botanalyzer.py --workers 3
python botanalyzer.py --shared-profile
python botanalyzer.py --zip .\snapshot.zip
python botanalyzer.py --no-zip
python botanalyzer.py --sync-extensions
python botanalyzer.py --no-precheck
python botanalyzer.py --precheck-workers 16 --precheck-timeout 10
```

Persistent profile + extensions + language:
```bash
python botanalyzer.py --headful --profile-dir .\chrome-profile --extension .\my-ext --lang es-ES --accept-languages es-ES,es,en
```

## Snapshot zip
By default Botanalyzer saves a snapshot zip of the current `botanalyzer/` folder (excluding `venv/` and `chrome-profile/`).
Disable with `--no-zip` or override the output path with `--zip`.

## Parallel workers
Use `--workers N` to process multiple URLs in parallel (one Chrome instance per worker).

## Shared profile
Use `--shared-profile` to reuse the same Chrome profile and extensions across runs.
Warning: using it with `--workers > 1` may corrupt the profile.

## Sync extensions
When using multiple workers with separate profiles, Botanalyzer syncs extensions from the base profile by default.
You can force it on with `--sync-extensions`.

## Precheck curl
By default Botanalyzer runs a multi-threaded curl precheck and skips dead URLs.
Use `--no-precheck` to disable, or tune with `--precheck-workers` and `--precheck-timeout`.

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
