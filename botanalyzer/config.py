#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys
from pathlib import Path
from typing import Iterable

try:
    from selenium import webdriver
    from selenium.webdriver.chrome.options import Options
except Exception as exc:  # pragma: no cover - import fallback path
    print(f"[ERROR] Selenium no disponible: {exc}", file=sys.stderr)
    print("Instala dependencia: pip install selenium", file=sys.stderr)
    raise SystemExit(1) from exc


DEFAULT_PROFILE_DIR = Path(__file__).resolve().parent / "chrome-profile"
DEFAULT_DOWNLOADS_DIR = Path(__file__).resolve().parent / "downloads"
DEFAULT_SETUP_TABS = [
    "chrome://extensions/",
    "chrome://settings/downloads",
    "chrome://settings/content/automaticDownloads",
]


def normalize_extension_paths(values: Iterable[str]) -> tuple[list[Path], list[Path]]:
    crx_files: list[Path] = []
    unpacked_dirs: list[Path] = []
    for raw in values:
        if not raw:
            continue
        path = Path(raw).expanduser().resolve()
        if not path.exists():
            continue
        if path.is_dir():
            unpacked_dirs.append(path)
            continue
        if path.suffix.lower() == ".crx":
            crx_files.append(path)
    return crx_files, unpacked_dirs


def build_driver(
    profile_dir: Path,
    downloads_dir: Path,
    extensions: Iterable[str],
    lang: str | None,
    accept_languages: str | None,
    detach: bool,
) -> webdriver.Chrome:
    options = Options()
    options.add_argument("--no-first-run")
    options.add_argument("--no-default-browser-check")
    options.add_argument("--window-size=1400,920")
    options.add_argument(f"--user-data-dir={profile_dir}")
    options.add_argument("--profile-directory=Default")

    if lang:
        options.add_argument(f"--lang={lang}")

    prefs: dict[str, object] = {
        "extensions.ui.developer_mode": True,
        "download.default_directory": str(downloads_dir),
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "safebrowsing.enabled": True,
        "plugins.always_open_pdf_externally": True,
    }
    if accept_languages:
        prefs["intl.accept_languages"] = accept_languages

    options.add_experimental_option("prefs", prefs)
    if detach:
        options.add_experimental_option("detach", True)

    crx_files, unpacked_dirs = normalize_extension_paths(extensions)
    for crx in crx_files:
        options.add_extension(str(crx))
    if unpacked_dirs:
        options.add_argument("--load-extension=" + ",".join(str(path) for path in unpacked_dirs))

    return webdriver.Chrome(options=options)


def open_setup_tabs(driver: webdriver.Chrome, tabs: list[str]) -> None:
    if not tabs:
        return
    driver.get(tabs[0])
    for tab in tabs[1:]:
        script = "window.open(arguments[0], '_blank');"
        driver.execute_script(script, tab)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Modo configuracion Botanalyzer: prepara perfil, extensiones y descargas."
    )
    parser.add_argument(
        "--profile-dir",
        default=str(DEFAULT_PROFILE_DIR),
        help="Directorio del perfil Chrome persistente",
    )
    parser.add_argument(
        "--downloads-dir",
        default=str(DEFAULT_DOWNLOADS_DIR),
        help="Carpeta para descargas automaticas",
    )
    parser.add_argument(
        "--extension",
        action="append",
        default=[],
        help="Ruta de extension (.crx o carpeta unpacked). Repetible.",
    )
    parser.add_argument("--lang", default="es-ES", help="Idioma de Chrome (ej: es-ES)")
    parser.add_argument(
        "--accept-languages",
        default="es-ES,es,en",
        help="Lista Accept-Language para Chrome",
    )
    parser.add_argument(
        "--open-url",
        action="append",
        default=[],
        help="URLs extra para abrir en pestanas de configuracion (repetible).",
    )
    parser.add_argument(
        "--detach",
        action="store_true",
        help="No cerrar Chrome al terminar el script (queda abierto).",
    )
    args = parser.parse_args()

    profile_dir = Path(args.profile_dir).expanduser().resolve()
    downloads_dir = Path(args.downloads_dir).expanduser().resolve()
    profile_dir.mkdir(parents=True, exist_ok=True)
    downloads_dir.mkdir(parents=True, exist_ok=True)

    if not args.extension:
        default_extension = Path(__file__).resolve().parent.parent / "browser-extension"
        if default_extension.exists():
            args.extension.append(str(default_extension))

    setup_tabs = list(DEFAULT_SETUP_TABS)
    setup_tabs.extend([u for u in args.open_url if u])

    print("[*] Iniciando modo configuracion de Botanalyzer")
    print(f"[*] Perfil: {profile_dir}")
    print(f"[*] Descargas: {downloads_dir}")
    if args.extension:
        print(f"[*] Extensiones a cargar: {len(args.extension)}")
    else:
        print("[*] Extensiones a cargar: 0")

    driver: webdriver.Chrome | None = None
    try:
        driver = build_driver(
            profile_dir=profile_dir,
            downloads_dir=downloads_dir,
            extensions=args.extension,
            lang=args.lang,
            accept_languages=args.accept_languages,
            detach=args.detach,
        )
        open_setup_tabs(driver, setup_tabs)
        print("[*] Chrome abierto para configuracion manual.")
        print("[*] Ajusta extensiones/permisos y prueba descargas.")
        if args.detach:
            print("[*] Modo detach activo: el script finaliza y Chrome queda abierto.")
            return 0
        input("[*] Pulsa ENTER para cerrar Chrome y guardar perfil... ")
        return 0
    finally:
        if driver is not None and not args.detach:
            try:
                driver.quit()
            except Exception:
                pass


if __name__ == "__main__":
    raise SystemExit(main())
