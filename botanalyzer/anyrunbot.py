#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
import sys
import time
from pathlib import Path
from typing import Iterable
from urllib.parse import urlparse

try:
    from selenium import webdriver
    from selenium.common.exceptions import WebDriverException
    from selenium.webdriver.chrome.options import Options
except Exception as exc:  # pragma: no cover - import fallback path
    print(f"[ERROR] Selenium no disponible: {exc}", file=sys.stderr)
    print("Instala dependencia: pip install selenium", file=sys.stderr)
    raise SystemExit(1) from exc


DEFAULT_TARGET_URL = "https://app.any.run/submissions/"
DEFAULT_WAIT_TIMEOUT = 25.0
DEFAULT_PAGE_CHANGE_TIMEOUT = 20.0
DEFAULT_SETTLE_SECONDS = 1.2
DEFAULT_MAX_PAGES = 0
DEFAULT_INITIAL_DATA_TIMEOUT = 45.0
DEFAULT_REDIRECT_STABLE_SECONDS = 1.4

DOMAIN_RE = re.compile(r"(?i)\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\b")
URL_RE = re.compile(r"(?i)\bhttps?://[^\s\"'<>]+")

ANYRUN_HOSTS = {
    "any.run",
    "www.any.run",
    "app.any.run",
}


def canonical_key(value: str) -> str:
    text = value.strip()
    if not text:
        return ""
    lower = text.lower().rstrip("/")
    if lower.startswith(("http://", "https://")):
        parsed = urlparse(text)
        host = (parsed.hostname or "").lower().strip()
        if not host:
            return lower
        path = (parsed.path or "").rstrip("/")
        query = f"?{parsed.query}" if parsed.query else ""
        return f"{host}{path}{query}"
    return lower


def load_existing_lines(path: Path) -> list[str]:
    if not path.exists():
        return []
    lines: list[str] = []
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        lines.append(line)
    return lines


def append_lines(path: Path, lines: Iterable[str]) -> int:
    payload = [line.strip() for line in lines if line and line.strip()]
    if not payload:
        return 0
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.exists():
        previous = path.read_text(encoding="utf-8")
        needs_newline = previous != "" and not previous.endswith("\n")
    else:
        needs_newline = False
    with path.open("a", encoding="utf-8", newline="\n") as handle:
        if needs_newline:
            handle.write("\n")
        for line in payload:
            handle.write(line + "\n")
    return len(payload)


def is_anyrun_host(host: str) -> bool:
    normalized = host.strip().lower().rstrip(".")
    if normalized in ANYRUN_HOSTS:
        return True
    return normalized.endswith(".any.run")


def normalize_domain(value: str) -> str:
    text = value.strip().lower()
    if not text:
        return ""
    text = text.strip(" \t\r\n<>[](){}\"'`,;")
    if not text:
        return ""
    if text.startswith(("http://", "https://")):
        parsed = urlparse(text)
        host = (parsed.hostname or "").strip().lower().rstrip(".")
    else:
        host = text.split("/", 1)[0].split("?", 1)[0].split("#", 1)[0].rstrip(".")
    if not host:
        return ""
    if is_anyrun_host(host):
        return ""
    if len(host) > 253 or "." not in host:
        return ""
    if not all(part and len(part) <= 63 and re.fullmatch(r"[a-z0-9-]+", part) and not part.startswith("-") and not part.endswith("-") for part in host.split(".")):
        return ""
    return host


def domains_from_text(raw: str) -> list[str]:
    text = " ".join((raw or "").split()).strip()
    if not text:
        return []

    found: list[str] = []
    seen: set[str] = set()

    direct = normalize_domain(text)
    if direct:
        seen.add(direct)
        found.append(direct)

    for match in URL_RE.finditer(text):
        candidate = normalize_domain(match.group(0))
        if not candidate or candidate in seen:
            continue
        seen.add(candidate)
        found.append(candidate)

    for match in DOMAIN_RE.finditer(text):
        candidate = normalize_domain(match.group(0))
        if not candidate or candidate in seen:
            continue
        seen.add(candidate)
        found.append(candidate)

    return found


def build_driver(profile_dir: Path, headless: bool) -> webdriver.Chrome:
    options = Options()
    options.add_argument(f"--user-data-dir={profile_dir.resolve()}")
    options.add_argument("--profile-directory=Default")
    options.add_argument("--disable-blink-features=AutomationControlled")
    options.add_argument("--no-first-run")
    options.add_argument("--no-default-browser-check")
    if headless:
        options.add_argument("--headless=new")
    return webdriver.Chrome(options=options)


def wait_document_ready(driver: webdriver.Chrome, timeout: float) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            state = driver.execute_script("return document.readyState;")
            if state == "complete":
                return True
        except WebDriverException:
            pass
        time.sleep(0.2)
    return False


def wait_url_stable(driver: webdriver.Chrome, timeout: float, stable_seconds: float) -> str:
    deadline = time.time() + timeout
    last_url = ""
    last_change_at = time.time()
    while time.time() < deadline:
        try:
            current = str(driver.current_url or "")
        except WebDriverException:
            current = ""
        if current != last_url:
            last_url = current
            last_change_at = time.time()
        if current and (time.time() - last_change_at) >= stable_seconds:
            return current
        time.sleep(0.2)
    return last_url


def extract_page_raw_candidates(driver: webdriver.Chrome) -> list[str]:
    script = """
        const out = [];
        const push = (value) => {
            const text = String(value || '').trim();
            if (!text) return;
            out.push(text);
        };
        const rows = Array.from(document.querySelectorAll('div.object-info__info'));
        for (const row of rows) {
            const nameEl = row.querySelector('p.object-info__info-name');
            if (!nameEl) continue;
            const typeEl = row.querySelector('p.object-info__info-type');
            if (typeEl) {
                const t = String(typeEl.textContent || '').trim().toLowerCase();
                if (t && t !== 'open in browser') continue;
            }
            push(nameEl.textContent);
        }
        const fallback = Array.from(document.querySelectorAll('p.object-info__info-name'));
        for (const el of fallback) {
            push(el.textContent);
        }
        const anchors = Array.from(document.querySelectorAll('a[href]'));
        for (const a of anchors) {
            push(a.getAttribute('href'));
            push(a.href);
            push(a.textContent);
        }
        const typed = Array.from(document.querySelectorAll('p.object-info__info-type'));
        for (const p of typed) {
            const t = String(p.textContent || '').trim().toLowerCase();
            if (t === 'open in browser') {
                const parent = p.closest('div.object-info__info');
                if (parent) {
                    push(parent.textContent);
                }
            }
        }
        if (out.length === 0 && document.body) {
            push(document.body.innerText);
        }
        return out.slice(0, 20000);
    """
    try:
        result = driver.execute_script(script)
    except WebDriverException:
        return []
    if not isinstance(result, list):
        return []
    return [str(item) for item in result if isinstance(item, str) or item is not None]


def extract_page_candidates(driver: webdriver.Chrome) -> list[str]:
    raw_candidates = extract_page_raw_candidates(driver)
    domains: list[str] = []
    seen: set[str] = set()
    for raw in raw_candidates:
        for domain in domains_from_text(raw):
            if domain in seen:
                continue
            seen.add(domain)
            domains.append(domain)
    return domains


def has_pagination(driver: webdriver.Chrome) -> bool:
    script = """
        const root = document.querySelector('body > div > main > div > div > main > div > div.analysis-public-pagination');
        if (!root) return false;
        return !!(root.textContent || '').trim();
    """
    try:
        return bool(driver.execute_script(script))
    except WebDriverException:
        return False


def wait_initial_data(driver: webdriver.Chrome, timeout: float) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        wait_document_ready(driver, 2.0)
        if extract_page_candidates(driver):
            return True
        if has_pagination(driver):
            return True
        try:
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        except WebDriverException:
            pass
        time.sleep(0.45)
        try:
            driver.execute_script("window.scrollTo(0, 0);")
        except WebDriverException:
            pass
        time.sleep(0.35)
    return False


def page_fingerprint(driver: webdriver.Chrome) -> str:
    script = """
        const names = Array.from(document.querySelectorAll('p.object-info__info-name'))
            .slice(0, 40)
            .map((el) => String(el.textContent || '').trim())
            .filter(Boolean);
        const root = document.querySelector('body > div > main > div > div > main > div > div.analysis-public-pagination');
        const pager = root ? String(root.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 180) : '';
        return `${location.href}::${pager}::${names.join('|')}`;
    """
    try:
        value = driver.execute_script(script)
    except WebDriverException:
        return ""
    return str(value or "")


def click_next_page(driver: webdriver.Chrome) -> bool:
    script = """
        const isClickable = (node) => {
            if (!(node instanceof Element)) return false;
            const ariaDisabled = String(node.getAttribute('aria-disabled') || '').toLowerCase();
            const cls = String(node.className || '').toLowerCase();
            const style = window.getComputedStyle(node);
            if (node.disabled || ariaDisabled === 'true' || cls.includes('disabled')) return false;
            if (style.display === 'none' || style.visibility === 'hidden' || style.pointerEvents === 'none') return false;
            return true;
        };
        const root = document.querySelector('body > div > main > div > div > main > div > div.analysis-public-pagination');
        if (!root) return false;
        const level1 = root.childNodes && root.childNodes.length > 0 ? root.childNodes[0] : null;
        const level2 = level1 && level1.childNodes && level1.childNodes.length > 0 ? level1.childNodes[0] : null;
        const exactButton = level2 && level2.childNodes && level2.childNodes.length > 4 ? level2.childNodes[4] : null;
        if (isClickable(exactButton)) {
            exactButton.click();
            return true;
        }
        const candidates = Array.from(root.querySelectorAll('button, [role="button"], a'));
        const clickable = candidates.filter((el) => isClickable(el));
        if (!clickable.length) return false;
        const preferred = clickable.find((el) => {
            const t = String(el.textContent || '').trim().toLowerCase();
            const cls = String(el.className || '').toLowerCase();
            return t === 'next' || t === '>' || t === '>>' || t.includes('siguiente') || cls.includes('next');
        });
        const target = preferred || clickable[clickable.length - 1];
        target.click();
        return true;
    """
    try:
        return bool(driver.execute_script(script))
    except WebDriverException:
        return False


def wait_page_changed(driver: webdriver.Chrome, previous_fingerprint: str, timeout: float) -> bool:
    before_url = ""
    try:
        before_url = str(driver.current_url or "")
    except WebDriverException:
        before_url = ""
    deadline = time.time() + timeout
    while time.time() < deadline:
        wait_document_ready(driver, 2.0)
        now_fp = page_fingerprint(driver)
        try:
            now_url = str(driver.current_url or "")
        except WebDriverException:
            now_url = ""
        if now_url and now_url != before_url:
            return True
        if now_fp and now_fp != previous_fingerprint:
            return True
        time.sleep(0.35)
    return False


def main() -> int:
    parser = argparse.ArgumentParser(description="Extrae dominios 'Open in browser' de ANY.RUN submissions.")
    parser.add_argument("--url", default=DEFAULT_TARGET_URL, help="Pagina objetivo de ANY.RUN")
    parser.add_argument("--output", default="urls.txt", help="Fichero destino donde anadir lineas")
    parser.add_argument("--profile-dir", default="chrome-profile", help="Perfil Chrome persistente")
    parser.add_argument("--headless", action="store_true", help="Ejecutar Chrome en modo headless")
    parser.add_argument("--wait-timeout", type=float, default=DEFAULT_WAIT_TIMEOUT, help="Timeout de carga inicial")
    parser.add_argument(
        "--page-change-timeout",
        type=float,
        default=DEFAULT_PAGE_CHANGE_TIMEOUT,
        help="Timeout para detectar cambio de pagina tras click",
    )
    parser.add_argument("--settle-seconds", type=float, default=DEFAULT_SETTLE_SECONDS, help="Pausa tras cambio de pagina")
    parser.add_argument("--max-pages", type=int, default=DEFAULT_MAX_PAGES, help="0 = sin limite")
    parser.add_argument(
        "--initial-data-timeout",
        type=float,
        default=DEFAULT_INITIAL_DATA_TIMEOUT,
        help="Timeout maximo para esperar datos dinamicos en la primera pagina",
    )
    parser.add_argument(
        "--redirect-stable-seconds",
        type=float,
        default=DEFAULT_REDIRECT_STABLE_SECONDS,
        help="Segundos que la URL debe permanecer estable para asumir fin de redirects",
    )
    args = parser.parse_args()

    base_dir = Path(__file__).resolve().parent
    output_path = (base_dir / args.output).resolve()
    profile_dir = (base_dir / args.profile_dir).resolve()

    existing_lines = load_existing_lines(output_path)
    seen_keys = {canonical_key(line) for line in existing_lines}
    collected_ordered: list[str] = []
    collected_keys: set[str] = set()

    driver: webdriver.Chrome | None = None
    try:
        print(f"[INFO] Abriendo {args.url}")
        driver = build_driver(profile_dir=profile_dir, headless=args.headless)
        driver.get(args.url)
        wait_document_ready(driver, args.wait_timeout)
        stable_url = wait_url_stable(driver, args.wait_timeout, args.redirect_stable_seconds)
        if stable_url:
            print(f"[INFO] URL estable tras redirects: {stable_url}")
        has_data = wait_initial_data(driver, args.initial_data_timeout)
        if not has_data:
            print("[WARN] No se detectaron filas ni paginacion dentro del timeout inicial.")
        time.sleep(max(0.0, args.settle_seconds))

        page_number = 0
        seen_page_fingerprints: set[str] = set()
        while True:
            page_number += 1
            names = extract_page_candidates(driver)
            page_added = 0
            for domain in names:
                key = canonical_key(domain)
                if not key:
                    continue
                if key in collected_keys:
                    continue
                collected_keys.add(key)
                collected_ordered.append(domain)
                page_added += 1
            print(f"[INFO] Pagina {page_number}: {len(names)} dominios extraidos, {page_added} nuevos")

            if args.max_pages > 0 and page_number >= args.max_pages:
                print("[INFO] Limite de paginas alcanzado por --max-pages")
                break

            before = page_fingerprint(driver)
            if before:
                if before in seen_page_fingerprints:
                    print("[WARN] Fingerprint de pagina repetido. Se detiene para evitar bucle.")
                    break
                seen_page_fingerprints.add(before)
            clicked = click_next_page(driver)
            if not clicked:
                print("[INFO] No existe boton siguiente (o esta deshabilitado). Fin de paginacion.")
                break

            changed = wait_page_changed(driver, before, args.page_change_timeout)
            if not changed:
                print("[WARN] Click sin cambio detectado; reintento unico tras esperar contenido dinamico.")
                time.sleep(max(1.2, args.settle_seconds))
                changed_retry = wait_page_changed(driver, before, max(3.0, args.page_change_timeout / 2.0))
                if not changed_retry:
                    print("[WARN] No hubo cambio tras reintento. Fin para evitar bucle.")
                    break
            wait_url_stable(driver, min(args.wait_timeout, args.page_change_timeout + 6.0), args.redirect_stable_seconds)
            wait_initial_data(driver, min(args.initial_data_timeout, args.page_change_timeout + 8.0))
            time.sleep(max(0.0, args.settle_seconds))

    except KeyboardInterrupt:
        print("[WARN] Interrumpido por usuario (Ctrl+C). Guardando lo recopilado.")
    finally:
        if driver is not None:
            try:
                driver.quit()
            except Exception:
                pass

    to_append: list[str] = []
    for entry in collected_ordered:
        key = canonical_key(entry)
        if not key or key in seen_keys:
            continue
        seen_keys.add(key)
        to_append.append(entry)

    appended = append_lines(output_path, to_append)
    print(f"[OK] Nuevas lineas anadidas en {output_path}: {appended}")
    print(f"[OK] Total extraido en ejecucion: {len(collected_ordered)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
