#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import time
import threading
import subprocess
import os
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urlparse
from urllib.request import Request, urlopen
import shutil
from dataclasses import dataclass
from queue import Queue, Empty
from zipfile import ZipFile, ZIP_DEFLATED
from pathlib import Path
from typing import Iterable

SELENIUM_IMPORT_ERROR: Exception | None = None
try:
    from selenium import webdriver
    from selenium.common.exceptions import WebDriverException
    from selenium.webdriver.chrome.options import Options
    from selenium.webdriver.common.by import By
except Exception as exc:  # pragma: no cover - import fallback path
    webdriver = None  # type: ignore[assignment]

    class WebDriverException(Exception):
        pass

    class _ByFallback:
        TAG_NAME = "tag name"
        CSS_SELECTOR = "css selector"

    By = _ByFallback()  # type: ignore[assignment]
    Options = None  # type: ignore[assignment]
    SELENIUM_IMPORT_ERROR = exc


DEFAULT_PAGE_TIMEOUT = 60
DEFAULT_WAIT_CLOSE = 15
DEFAULT_BETWEEN_CLICKS = 1.25
DEFAULT_BUTTON_TIMEOUT = 10.0
DEFAULT_POST_LOAD_WAIT = 10.5
DEFAULT_MAX_FRAME_DEPTH = 5
DEFAULT_MAX_DIV_CLICKS = 1000
DEFAULT_MAX_URL_SECONDS = 90
DEFAULT_MAX_CLICKS = 160
DEFAULT_BLOCKED_OUTPUT = "blocked.txt"
RUNTIME_MAX_DIV_CLICKS = DEFAULT_MAX_DIV_CLICKS

BUTTON_SELECTORS = [
    (By.TAG_NAME, "button"),
    (By.CSS_SELECTOR, "input[type='button']"),
    (By.CSS_SELECTOR, "input[type='submit']"),
    (By.CSS_SELECTOR, "[role='button']"),
]
CLEANUP_CAPTCHA_SELECTORS = [
    (By.CSS_SELECTOR, "div.captcha-check"),
    (By.CSS_SELECTOR, "body > section.recaptcha-section > main > div > div.captcha-check"),
]

DEFAULT_SNAPSHOT_PREFIX = "botanalyzer_snapshot"
EXCLUDED_SNAPSHOT_DIRS = {"venv", "chrome-profile", "__pycache__"}
DEFAULT_PRECHECK_TIMEOUT = 8
DEFAULT_PRECHECK_WORKERS = 60
DEFAULT_PRECHECK_MAX_WORKERS = 256
DEFAULT_THREATFOX_DAYS = 7
DEFAULT_THREATFOX_LIMIT = 400
DEFAULT_THREATFOX_TIMEOUT = 12
DEFAULT_THREATFOX_TAG = "clickfix, IClickFix, stealer, ErrTraffic, ClearFake, ClickChain"
BLOCKED_SOCIAL_HOSTS = {
    "facebook.com",
    "www.facebook.com",
    "m.facebook.com",
    "tiktok.com",
    "www.tiktok.com",
    "m.tiktok.com",
    "vt.tiktok.com",
}
ALLOW_SESSION_SCORE_THRESHOLD = 38
MAX_REPEAT_VISITS = 3
MAX_DUPLICATE_TABS_PER_URL = 1
QUEUE_SENTINEL = "__BOTANALYZER_STOP__"


def load_urls(path: Path) -> list[str]:
    if not path.exists():
        return []
    urls: list[str] = []
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if "://" not in line:
            line = f"https://{line}"
        urls.append(line)
    return urls


def load_done_urls(path: Path) -> set[str]:
    if not path.exists():
        return set()
    done: set[str] = set()
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        normalized = normalize_url(line)
        if normalized:
            done.add(normalized)
    return done


def is_http_url(value: str) -> bool:
    try:
        parsed = urlparse(value)
    except Exception:
        return False
    return parsed.scheme in {"http", "https"}


def normalize_url(value: str) -> str:
    line = value.strip()
    if not line or line.startswith("#"):
        return ""
    if "://" not in line:
        line = f"https://{line}"
    return line


def parse_threatfox_tags(raw: str | None) -> list[str]:
    if not raw:
        return []
    parts = []
    for chunk in str(raw).replace(";", ",").replace("\n", ",").split(","):
        tag = chunk.strip()
        if not tag:
            continue
        if tag not in parts:
            parts.append(tag)
    return parts


def fetch_threatfox_iocs(auth_key: str, days: int, limit: int, timeout: int, tag: str | None) -> list[str]:
    auth_key = auth_key.strip()
    if not auth_key:
        return []
    days = max(1, min(int(days), 30))
    limit = max(1, min(int(limit), 5000))
    timeout = max(3, min(int(timeout), 30))
    tag_values = parse_threatfox_tags(tag)
    payloads: list[tuple[str, bytes]] = []
    if tag_values:
        per_tag = max(1, min(limit, max(1, int(limit / max(1, len(tag_values))))))
        for tag_value in tag_values:
            payloads.append((tag_value, json.dumps({"query": "taginfo", "tag": tag_value, "limit": per_tag}).encode("utf-8")))
    else:
        payloads.append(("", json.dumps({"query": "get_iocs", "days": days}).encode("utf-8")))

    results: list[str] = []
    seen: set[str] = set()
    for tag_label, payload in payloads:
        req = Request(
            "https://threatfox-api.abuse.ch/api/v1/",
            data=payload,
            headers={
                "Content-Type": "application/json",
                "Auth-Key": auth_key,
                "User-Agent": "botanalyzer/1.0",
            },
            method="POST",
        )
        try:
            with urlopen(req, timeout=timeout) as resp:
                data = resp.read().decode("utf-8", errors="replace")
        except Exception:
            continue

        try:
            payload_json = json.loads(data)
        except json.JSONDecodeError:
            continue
        if not isinstance(payload_json, dict):
            continue
        query_status = str(payload_json.get("query_status", ""))
        if query_status not in {"ok", "no_results"}:
            continue

        rows = payload_json.get("data", [])
        if not isinstance(rows, list):
            continue

        for row in rows:
            if not isinstance(row, dict):
                continue
            ioc_type = str(row.get("ioc_type", "")).lower()
            ioc = str(row.get("ioc", "")).strip()
            if not ioc:
                continue

            url_value = ""
            if ioc_type in {"url", "uri"}:
                url_value = ioc
            elif ioc_type in {"domain", "hostname"}:
                url_value = f"https://{ioc}"
            else:
                continue

            normalized = normalize_url(url_value)
            if not normalized:
                continue
            if normalized in seen:
                continue
            seen.add(normalized)
            results.append(normalized)
            if len(results) >= limit:
                break
        if len(results) >= limit:
            break

    return results


def is_blocked_social_url(value: str) -> bool:
    try:
        parsed = urlparse(value)
    except Exception:
        return False
    host = (parsed.hostname or "").lower()
    if not host:
        return False
    if host in BLOCKED_SOCIAL_HOSTS:
        return True
    return host.endswith(".facebook.com") or host.endswith(".tiktok.com")


def normalize_loop_url(value: str) -> str:
    try:
        parsed = urlparse(value)
    except Exception:
        return value
    if not parsed.scheme or not parsed.netloc:
        return value
    host = parsed.hostname.lower() if parsed.hostname else parsed.netloc.lower()
    path = parsed.path or "/"
    return f"{parsed.scheme.lower()}://{host}{path}"


def append_line(path: Path, line: str) -> None:
    if path.exists():
        existing_text = path.read_text(encoding="utf-8")
        needs_newline = existing_text and not existing_text.endswith("\n")
    else:
        needs_newline = False
    with path.open("a", encoding="utf-8", newline="\n") as handle:
        if needs_newline:
            handle.write("\n")
        handle.write(line + "\n")


def remove_url_from_file(path: Path, url: str) -> bool:
    if not path.exists():
        return False
    target = normalize_url(url)
    if not target:
        return False
    lines = path.read_text(encoding="utf-8").splitlines(keepends=True)
    removed = False
    updated: list[str] = []
    for line in lines:
        stripped = line.strip()
        if not stripped or stripped.startswith("#"):
            updated.append(line)
            continue
        if not removed and normalize_url(stripped) == target:
            removed = True
            continue
        updated.append(line)
    if removed:
        path.write_text("".join(updated), encoding="utf-8")
    return removed


def normalize_extension_paths(values: Iterable[str]) -> tuple[list[Path], list[Path]]:
    crx_files: list[Path] = []
    unpacked_dirs: list[Path] = []
    for raw in values:
        if not raw:
            continue
        path = Path(raw).expanduser()
        if not path.exists():
            continue
        if path.is_dir():
            unpacked_dirs.append(path)
            continue
        if path.suffix.lower() == ".crx":
            crx_files.append(path)
    return crx_files, unpacked_dirs


def create_snapshot_zip(base_dir: Path, output_path: Path) -> None:
    output_path.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(output_path, "w", ZIP_DEFLATED) as zipf:
        for path in base_dir.rglob("*"):
            if path.is_dir():
                continue
            if path == output_path:
                continue
            if path.name.startswith(DEFAULT_SNAPSHOT_PREFIX):
                continue
            if any(part in EXCLUDED_SNAPSHOT_DIRS for part in path.parts):
                continue
            try:
                zipf.write(path, path.relative_to(base_dir))
            except OSError:
                continue


def sync_extensions_from_profile(source_root: Path, target_root: Path) -> None:
    source_profile = source_root / "Default"
    target_profile = target_root / "Default"
    if not source_profile.exists():
        print(f"[WARN] Base profile not found at {source_profile}")
        return
    target_profile.mkdir(parents=True, exist_ok=True)

    copy_dirs = [
        "Extensions",
        "Local Extension Settings",
        "Extension State",
    ]
    copy_files = [
        "Preferences",
        "Secure Preferences",
    ]
    for name in copy_dirs:
        src = source_profile / name
        dst = target_profile / name
        if not src.exists():
            continue
        try:
            shutil.copytree(src, dst, dirs_exist_ok=True)
        except OSError:
            continue
    for name in copy_files:
        src = source_profile / name
        dst = target_profile / name
        if not src.exists():
            continue
        try:
            shutil.copy2(src, dst)
        except OSError:
            continue

    local_state = source_root / "Local State"
    if local_state.exists():
        try:
            shutil.copy2(local_state, target_root / "Local State")
        except OSError:
            pass


def curl_health_check(url: str, timeout: int) -> tuple[str, bool, str]:
    if not is_http_url(url):
        return url, True, "non-http"
    timeout = max(1, int(timeout))
    connect_timeout = max(1, min(timeout, 6))
    command = [
        "curl",
        "-L",
        "--connect-timeout",
        str(connect_timeout),
        "-m",
        str(timeout),
        "-s",
        "-k",
        "-o",
        os.devnull,
        "-w",
        "%{http_code}",
        url,
    ]
    try:
        completed = subprocess.run(command, capture_output=True, text=True, timeout=timeout + 3)
        code = (completed.stdout or "").strip()
        ok = completed.returncode == 0 and code and code != "000"
        return url, ok, code or "000"
    except Exception:
        return url, False, "error"


def curl_runtime_healthy(timeout: int = 5) -> bool:
    probe_timeout = max(2, int(timeout))
    probes = [
        "https://example.com",
        "https://www.cloudflare.com",
    ]
    for probe in probes:
        _, ok, _ = curl_health_check(probe, probe_timeout)
        if ok:
            return True
    return False


def precheck_urls(
    urls: list[str],
    workers: int,
    timeout: int,
) -> tuple[list[str], list[tuple[str, str]]]:
    if not urls:
        return [], []
    alive: list[str] = []
    dead: list[tuple[str, str]] = []
    max_workers = max(1, min(workers, DEFAULT_PRECHECK_MAX_WORKERS))
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [executor.submit(curl_health_check, url, timeout) for url in urls]
        for future in as_completed(futures):
            url, ok, code = future.result()
            if ok:
                alive.append(url)
            else:
                dead.append((url, code))
    return alive, dead


def stream_precheck_to_queue(
    urls: list[str],
    workers: int,
    timeout: int,
    url_queue: Queue[str],
    urls_path: Path,
    dead_path: Path,
    file_lock: threading.Lock,
    remove_dead_from_urls: bool = False,
    progress_every: int = 200,
) -> tuple[int, int]:
    if not urls:
        return 0, 0
    alive_count = 0
    dead_count = 0
    completed_count = 0
    total = len(urls)
    max_workers = max(1, min(workers, DEFAULT_PRECHECK_MAX_WORKERS))
    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [executor.submit(curl_health_check, url, timeout) for url in urls]
        for future in as_completed(futures):
            url, ok, code = future.result()
            completed_count += 1
            if ok:
                url_queue.put(url)
                alive_count += 1
            else:
                dead_count += 1
                with file_lock:
                    append_line(dead_path, f"{url} # {code}")
                    if remove_dead_from_urls:
                        remove_url_from_file(urls_path, url)
            if (
                completed_count == 1
                or completed_count == total
                or (progress_every > 0 and completed_count % progress_every == 0)
            ):
                print(
                    f"[PRECHECK] {completed_count}/{total} checked | "
                    f"alive={alive_count} dead={dead_count}"
                )
    return alive_count, dead_count


def build_driver(
    headful: bool,
    profile_dir: Path | None,
    extensions: Iterable[str],
    lang: str | None,
    accept_languages: str | None,
) -> webdriver.Chrome:
    options = Options()
    if not headful:
        pass # options.add_argument("--headless=new")
    # options.add_argument("--disable-gpu")
    options.add_argument("--no-first-run")
    options.add_argument("--no-default-browser-check")
    options.add_argument("--remote-debugging-port=0")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--window-size=1280,800")
    prefs = {"extensions.ui.developer_mode": True}
    if profile_dir is not None:
        profile_dir.mkdir(parents=True, exist_ok=True)
        options.add_argument(f"--user-data-dir={profile_dir}")
    if lang:
        options.add_argument(f"--lang={lang}")
    if accept_languages:
        prefs["intl.accept_languages"] = accept_languages
    if prefs:
        options.add_experimental_option("prefs", prefs)

    crx_files, unpacked_dirs = normalize_extension_paths(extensions)
    for crx in crx_files:
        options.add_extension(str(crx))
    if unpacked_dirs:
        options.add_argument("--load-extension=" + ",".join(str(path) for path in unpacked_dirs))
    return webdriver.Chrome(options=options)


def init_driver(
    headful: bool,
    profile_dir: Path | None,
    extensions: Iterable[str],
    lang: str | None,
    accept_languages: str | None,
    page_timeout: int,
) -> webdriver.Chrome:
    driver = build_driver(headful, profile_dir, extensions, lang, accept_languages)
    driver.set_page_load_timeout(page_timeout)
    return driver


def resolve_worker_profile_dir(
    base_dir: Path | None,
    worker_id: int,
    total_workers: int,
    shared_profile: bool,
) -> Path | None:
    if base_dir is None:
        return None
    if shared_profile or total_workers <= 1:
        return base_dir
    worker_dir = base_dir / f"worker-{worker_id}"
    worker_dir.mkdir(parents=True, exist_ok=True)
    return worker_dir


def reset_driver(
    driver: webdriver.Chrome | None,
    headful: bool,
    profile_dir: Path | None,
    extensions: Iterable[str],
    lang: str | None,
    accept_languages: str | None,
    page_timeout: int,
) -> webdriver.Chrome:
    if driver is not None:
        try:
            driver.quit()
        except WebDriverException:
            pass
    return init_driver(headful, profile_dir, extensions, lang, accept_languages, page_timeout)


def clear_browser_state(driver: webdriver.Chrome) -> None:
    try:
        driver.execute_cdp_cmd("Network.clearBrowserCache", {})
    except WebDriverException:
        pass
    try:
        driver.execute_cdp_cmd("Network.clearBrowserCookies", {})
    except WebDriverException:
        pass
    try:
        driver.execute_cdp_cmd("Page.resetNavigationHistory", {})
    except WebDriverException:
        pass


def collect_div_clickables(driver: webdriver.Chrome, limit: int = DEFAULT_MAX_DIV_CLICKS) -> list:
    script = """
    const max = arguments[0];
    const results = [];
    const tokenRegex = /(captcha|verify|check)/i;
    const isVisible = (el) => {
      const style = getComputedStyle(el);
      if (style.visibility === "hidden" || style.display === "none") return false;
      const rect = el.getBoundingClientRect();
      if (rect.width < 8 || rect.height < 8) return false;
      if (el.offsetParent) return true;
      return style.position === "fixed";
    };
    const nodes = document.querySelectorAll("div");
    for (const el of nodes) {
      if (results.length >= max) break;
      if (!isVisible(el)) continue;
      const role = (el.getAttribute("role") || "").toLowerCase();
      const tabIndex = el.tabIndex ?? -1;
      const label = (el.getAttribute("aria-label") || "").toLowerCase();
      const text = (el.className || "") + " " + (el.id || "") + " " + label;
      if (role === "button" || el.onclick || el.getAttribute("onclick") || tabIndex >= 0 || tokenRegex.test(text)) {
        results.push(el);
        continue;
      }
      const cursor = getComputedStyle(el).cursor;
      if (cursor === "pointer") {
        results.push(el);
      }
    }
    return results;
    """
    try:
        return list(driver.execute_script(script, int(limit)))
    except WebDriverException:
        return []


def collect_clickables(driver: webdriver.Chrome) -> list:
    elements = []
    for by, selector in BUTTON_SELECTORS + CLEANUP_CAPTCHA_SELECTORS:
        try:
            elements.extend(driver.find_elements(by, selector))
        except WebDriverException:
            continue
    elements.extend(collect_div_clickables(driver, RUNTIME_MAX_DIV_CLICKS))
    return elements


def wait_for_dom_ready(driver: webdriver.Chrome, timeout: float) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            state = driver.execute_script("return document.readyState")
            if state == "complete":
                return True
        except WebDriverException:
            pass
        time.sleep(0.1)
    return False


def count_clickables_in_frames(
    driver: webdriver.Chrome,
    depth: int = 0,
    max_depth: int = DEFAULT_MAX_FRAME_DEPTH,
) -> int:
    if depth > max_depth:
        return 0
    total = 0
    try:
        total += len(collect_clickables(driver))
        frames = driver.find_elements(By.CSS_SELECTOR, "iframe, frame")
    except WebDriverException:
        return total
    for frame in frames:
        try:
            driver.switch_to.frame(frame)
        except WebDriverException:
            continue
        try:
            total += count_clickables_in_frames(driver, depth + 1, max_depth)
        finally:
            try:
                driver.switch_to.parent_frame()
            except WebDriverException:
                try:
                    driver.switch_to.default_content()
                except WebDriverException:
                    pass
    return total


def wait_for_buttons(driver: webdriver.Chrome, timeout: float, max_depth: int) -> int:
    deadline = time.time() + timeout
    last_count = 0
    while time.time() < deadline:
        try:
            last_count = count_clickables_in_frames(driver, max_depth=max_depth)
            if last_count:
                return last_count
        except WebDriverException:
            pass
        time.sleep(0.25)
    return last_count


def click_clickables(driver: webdriver.Chrome, delay: float, max_clicks: int, deadline: float | None) -> int:
    elements = collect_clickables(driver)
    clicked = 0
    seen = set()
    for element in elements:
        if deadline and time.time() >= deadline:
            break
        if clicked >= max(1, int(max_clicks)):
            break
        try:
            if element in seen:
                continue
            seen.add(element)
            if not element.is_displayed() or not element.is_enabled():
                continue
            driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", element)
            try:
                element.click()
            except WebDriverException:
                driver.execute_script("arguments[0].click();", element)
            clicked += 1
            if delay:
                time.sleep(delay)
        except WebDriverException:
            continue
    return clicked


def click_clickables_in_frames(
    driver: webdriver.Chrome,
    delay: float,
    max_clicks: int,
    deadline: float | None,
    depth: int = 0,
    max_depth: int = DEFAULT_MAX_FRAME_DEPTH,
) -> int:
    if depth > max_depth:
        return 0
    clicked = 0
    try:
        clicked += click_clickables(driver, delay, max_clicks, deadline)
        frames = driver.find_elements(By.CSS_SELECTOR, "iframe, frame")
    except WebDriverException:
        return clicked
    for frame in frames:
        if deadline and time.time() >= deadline:
            break
        if clicked >= max(1, int(max_clicks)):
            break
        try:
            driver.switch_to.frame(frame)
        except WebDriverException:
            continue
        try:
            clicked += click_clickables_in_frames(driver, delay, max_clicks, deadline, depth + 1, max_depth)
        finally:
            try:
                driver.switch_to.parent_frame()
            except WebDriverException:
                try:
                    driver.switch_to.default_content()
                except WebDriverException:
                    pass
    return clicked


def wait_for_close(driver: webdriver.Chrome, wait_seconds: float) -> bool:
    start = time.time()
    while time.time() - start < wait_seconds:
        try:
            _ = driver.title
        except WebDriverException:
            return True
        time.sleep(0.2)
    return False


def detect_access_block(driver: webdriver.Chrome) -> str:
    try:
        current_url = (driver.current_url or "").lower()
    except WebDriverException:
        current_url = ""
    try:
        title = (driver.title or "").lower()
    except WebDriverException:
        title = ""
    if current_url.startswith(("chrome-error://", "edge://", "about:neterror")):
        return "browser_error"
    tokens = [
        "access denied",
        "site is blocked",
        "this site is blocked",
        "blocked",
        "denied",
        "denegado",
        "acceso denegado",
        "no se puede acceder",
        "err_access_denied",
        "err_blocked_by_client",
    ]
    for token in tokens:
        if token in title:
            return token
    return ""


def close_blocked_social_tabs(
    driver: webdriver.Chrome,
    main_window: str | None,
    label: str,
) -> str | None:
    try:
        handles = driver.window_handles
    except WebDriverException:
        return main_window
    if not handles:
        return main_window
    current_main = main_window if main_window in handles else handles[0]
    for handle in list(handles):
        try:
            driver.switch_to.window(handle)
            current_url = driver.current_url or ""
        except WebDriverException:
            continue
        if not is_blocked_social_url(current_url):
            continue
        if handle == current_main and len(handles) == 1:
            try:
                driver.get("about:blank")
                print(f"[{label}] [BLOCKED] Social site detected -> blanked main tab")
            except WebDriverException:
                pass
            continue
        try:
            driver.close()
            print(f"[{label}] [BLOCKED] Social site tab closed: {current_url}")
        except WebDriverException:
            continue
    try:
        handles = driver.window_handles
    except WebDriverException:
        return current_main
    if not handles:
        return current_main
    if current_main not in handles:
        current_main = handles[0]
    try:
        driver.switch_to.window(current_main)
    except WebDriverException:
        pass
    return current_main


def close_duplicate_tabs(
    driver: webdriver.Chrome,
    main_window: str | None,
    label: str,
    max_per_url: int = MAX_DUPLICATE_TABS_PER_URL,
) -> str | None:
    try:
        handles = driver.window_handles
    except WebDriverException:
        return main_window
    if not handles:
        return main_window
    current_main = main_window if main_window in handles else handles[0]
    url_map: dict[str, list[str]] = {}
    for handle in handles:
        try:
            driver.switch_to.window(handle)
            current_url = driver.current_url or ""
        except WebDriverException:
            continue
        key = normalize_loop_url(current_url)
        url_map.setdefault(key, []).append(handle)
    for key, key_handles in url_map.items():
        if len(key_handles) <= max_per_url:
            continue
        kept = 0
        for handle in list(key_handles):
            if handle == current_main and kept < max_per_url:
                kept += 1
                continue
            if kept < max_per_url:
                kept += 1
                continue
            try:
                driver.switch_to.window(handle)
                driver.close()
                print(f"[{label}] [LOOP] Closed duplicate tab for {key}")
            except WebDriverException:
                continue
    try:
        handles = driver.window_handles
    except WebDriverException:
        return current_main
    if not handles:
        return current_main
    if current_main not in handles:
        current_main = handles[0]
    try:
        driver.switch_to.window(current_main)
    except WebDriverException:
        pass
    return current_main


def maybe_allow_clickfix_session(
    driver: webdriver.Chrome,
    label: str,
    threshold: int = ALLOW_SESSION_SCORE_THRESHOLD,
) -> bool:
    script = r"""
    const threshold = arguments[0];
    const root = document.querySelector('.clickfix-blocked');
    if (!root) return { detected: false };
    const text = (root.innerText || '').slice(0, 30000);
    const match = text.match(/(\d{1,3})\s*\/\s*100/);
    const score = match ? parseInt(match[1], 10) : null;
    let clicked = false;
    if (score !== null && score < threshold) {
      const buttons = Array.from(root.querySelectorAll('.clickfix-actions .clickfix-btn'));
      let sessionBtn = null;
      const tokens = [
        'session', 'sesion', 'sesión', 'sessao', 'sessão',
        'sitzung', 'сеанс', 'sessione', 'sessie',
        'セッション', '세션', 'سشن'
      ];
      for (const btn of buttons) {
        const label = (btn.textContent || '').toLowerCase();
        if (tokens.some((token) => label.includes(token))) {
          sessionBtn = btn;
          break;
        }
      }
      if (!sessionBtn) {
        const outlineDanger = buttons.filter((btn) =>
          btn.classList.contains('clickfix-btn-outline') && btn.classList.contains('danger')
        );
        if (outlineDanger.length >= 2) {
          sessionBtn = outlineDanger[1];
        }
      }
      if (!sessionBtn && buttons.length >= 3) {
        sessionBtn = buttons[2];
      }
      if (sessionBtn) {
        sessionBtn.scrollIntoView({ block: 'center' });
        sessionBtn.click();
        clicked = true;
      }
    }
    return { detected: true, score: score, clicked: clicked };
    """
    try:
        result = driver.execute_script(script, int(threshold))
    except WebDriverException:
        return False
    if not isinstance(result, dict) or not result.get("detected"):
        return False
    score = result.get("score")
    if result.get("clicked"):
        print(f"[{label}] [ALLOW] ClickFix allow session (score={score})")
        return True
    if score is not None:
        print(f"[{label}] [ALLOW] ClickFix detected score={score} (threshold={threshold})")
    return False


@dataclass
class WorkerState:
    worker_id: int
    driver: webdriver.Chrome
    profile_dir: Path | None
    main_window: str | None
    loop_counts: dict[str, int]


def process_url(
    state: WorkerState,
    url: str,
    args: argparse.Namespace,
) -> None:
    label = f"W{state.worker_id}"
    print(f"[{label}] [OPEN] {url}")
    start = time.time()
    deadline = start + max(10, int(args.max_url_seconds))
    try:
        try:
            try:
                state.driver.get(url)
            except WebDriverException:
                print(f"[{label}] [TIMEOUT] {url}")
            if time.time() >= deadline:
                print(f"[{label}] [ABORT] Max URL time reached (after load)")
                return
            wait_for_dom_ready(state.driver, args.page_timeout)
            if time.time() >= deadline:
                print(f"[{label}] [ABORT] Max URL time reached (after dom)")
                return
            if args.post_load_wait:
                time.sleep(args.post_load_wait)
            if time.time() >= deadline:
                print(f"[{label}] [ABORT] Max URL time reached (post-load)")
                return
            if maybe_allow_clickfix_session(state.driver, label, ALLOW_SESSION_SCORE_THRESHOLD):
                time.sleep(2.0)
                wait_for_dom_ready(state.driver, args.page_timeout)
            try:
                current_url = state.driver.current_url or ""
            except WebDriverException:
                current_url = ""
            block_reason = detect_access_block(state.driver)
            if block_reason:
                print(f"[{label}] [BLOCKED] Access blocked ({block_reason}) -> {current_url or url}")
                if args.blocked:
                    append_line(Path(args.blocked), url)
                return
            loop_key = normalize_loop_url(current_url)
            if loop_key:
                state.loop_counts[loop_key] = state.loop_counts.get(loop_key, 0) + 1
                if state.loop_counts[loop_key] >= MAX_REPEAT_VISITS:
                    print(f"[{label}] [LOOP] Repeated URL detected -> aborting {loop_key}")
                    state.main_window = close_duplicate_tabs(state.driver, state.main_window, label)
                    return
            if is_blocked_social_url(current_url):
                state.main_window = close_blocked_social_tabs(state.driver, state.main_window, label)
                print(f"[{label}] [SKIP] Social site blocked: {current_url}")
                return
            if time.time() >= deadline:
                print(f"[{label}] [ABORT] Max URL time reached (pre-click)")
                return
            found = wait_for_buttons(state.driver, args.button_timeout, args.max_frame_depth)
            if found:
                print(f"[{label}] [BUTTONS] {found} detected after JS load")
            clicked = click_clickables_in_frames(
                state.driver,
                args.between_clicks,
                args.max_clicks,
                deadline,
                max_depth=args.max_frame_depth,
            )
            print(f"[{label}] [CLICKED] {clicked} buttons")
            state.main_window = close_blocked_social_tabs(state.driver, state.main_window, label)
            state.main_window = close_duplicate_tabs(state.driver, state.main_window, label)
            if time.time() >= deadline:
                print(f"[{label}] [ABORT] Max URL time reached (post-click)")
                return
            closed = wait_for_close(state.driver, args.wait_close)
            if closed:
                try:
                    handles = state.driver.window_handles
                except WebDriverException:
                    handles = []
                if handles:
                    if state.main_window in handles:
                        state.driver.switch_to.window(state.main_window)
                    else:
                        state.main_window = handles[0]
                        state.driver.switch_to.window(state.main_window)
                else:
                    state.driver = reset_driver(
                        state.driver,
                        args.headful,
                        state.profile_dir,
                        args.extension,
                        args.lang,
                        args.accept_languages,
                        args.page_timeout,
                    )
                    state.main_window = state.driver.current_window_handle
                state.main_window = close_duplicate_tabs(state.driver, state.main_window, label)
                state.main_window = close_blocked_social_tabs(state.driver, state.main_window, label)
            else:
                try:
                    handles = state.driver.window_handles
                except WebDriverException:
                    handles = []
                if handles:
                    if state.main_window in handles:
                        state.driver.switch_to.window(state.main_window)
                    else:
                        state.main_window = handles[0]
                        state.driver.switch_to.window(state.main_window)
        except Exception as error:
            error_type = type(error).__name__
            print(f"[{label}] [ERROR] {url} -> {error_type}: {error}")
            try:
                state.driver = reset_driver(
                    state.driver,
                    args.headful,
                    state.profile_dir,
                    args.extension,
                    args.lang,
                    args.accept_languages,
                    args.page_timeout,
                )
                state.main_window = state.driver.current_window_handle
            except Exception as reset_error:
                reset_type = type(reset_error).__name__
                print(f"[{label}] [FATAL] driver reset failed ({reset_type}): {reset_error}")
                raise
    finally:
        clear_browser_state(state.driver)


def worker_loop(
    worker_id: int,
    url_queue: Queue[str],
    args: argparse.Namespace,
    urls_path: Path,
    done_path: Path,
    file_lock: threading.Lock,
) -> None:
    base_profile_dir = Path(args.profile_dir) if args.profile_dir else None
    profile_dir = resolve_worker_profile_dir(
        Path(args.profile_dir) if args.profile_dir else None,
        worker_id,
        args.workers,
        args.shared_profile,
    )
    # Force extension sync from the principal profile into every worker profile.
    # This guarantees all Selenium workers run with the same installed extensions.
    if base_profile_dir and profile_dir and profile_dir != base_profile_dir:
        sync_extensions_from_profile(base_profile_dir, profile_dir)
    try:
        driver = init_driver(
            args.headful,
            profile_dir,
            args.extension,
            args.lang,
            args.accept_languages,
            args.page_timeout,
        )
    except WebDriverException:
        fallback_dir = None
        if profile_dir is not None:
            stamp = time.strftime("%Y%m%d-%H%M%S")
            fallback_dir = profile_dir / f"runtime-{stamp}"
            print(f"[WARN] Chrome failed to start. Retrying with fresh profile: {fallback_dir}")
        if fallback_dir is None:
            raise
        if base_profile_dir and fallback_dir != base_profile_dir:
            sync_extensions_from_profile(base_profile_dir, fallback_dir)
        driver = init_driver(
            args.headful,
            fallback_dir,
            args.extension,
            args.lang,
            args.accept_languages,
            args.page_timeout,
        )
        profile_dir = fallback_dir
    try:
        main_window = None
        try:
            main_window = driver.current_window_handle
        except WebDriverException:
            main_window = None
        state = WorkerState(
            worker_id=worker_id,
            driver=driver,
            profile_dir=profile_dir,
            main_window=main_window,
            loop_counts={},
        )
        print(f"[*] Worker {worker_id} initialized")
        while True:
            try:
                url = url_queue.get(timeout=0.5)
            except Empty:
                continue
            if url == QUEUE_SENTINEL:
                url_queue.task_done()
                break
            try:
                process_url(state, url, args)
            except Exception:
                pass
            finally:
                with file_lock:
                    append_line(done_path, url)
                    remove_url_from_file(urls_path, url)
                url_queue.task_done()
    finally:
        try:
            driver.quit()
        except WebDriverException:
            pass


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Click all buttons on URLs listed in urls.txt (Selenium).",
        formatter_class=argparse.RawTextHelpFormatter,
        epilog=(
            "Examples:\n"
            "  python botanalyzer.py --help\n"
            "  python botanalyzer.py --urls urls.txt --done done.txt --headful\n"
            "  python botanalyzer.py --workers 3 --precheck-workers 80 --precheck-timeout 8\n"
            "  python botanalyzer.py --profile-dir ./chrome-profile --extension ../browser-extension\n"
            "  python botanalyzer.py --no-precheck --no-zip --workers 1\n"
            "  python botanalyzer.py --lang es-ES --accept-languages es-ES,es,en\n"
        ),
    )
    parser.add_argument("--urls", default="urls.txt", help="Path to urls.txt")
    parser.add_argument("--done", default="done.txt", help="Path to done.txt")
    parser.add_argument("--headful", action="store_true", help="Run with visible browser window")
    parser.add_argument("--page-timeout", type=int, default=DEFAULT_PAGE_TIMEOUT)
    parser.add_argument("--wait-close", type=int, default=DEFAULT_WAIT_CLOSE)
    parser.add_argument("--between-clicks", type=float, default=DEFAULT_BETWEEN_CLICKS)
    parser.add_argument("--button-timeout", type=float, default=DEFAULT_BUTTON_TIMEOUT)
    parser.add_argument("--post-load-wait", type=float, default=DEFAULT_POST_LOAD_WAIT)
    parser.add_argument("--max-url-seconds", type=int, default=DEFAULT_MAX_URL_SECONDS)
    parser.add_argument("--max-clicks", type=int, default=DEFAULT_MAX_CLICKS)
    parser.add_argument("--max-frame-depth", type=int, default=DEFAULT_MAX_FRAME_DEPTH)
    parser.add_argument("--max-div-clicks", type=int, default=DEFAULT_MAX_DIV_CLICKS)
    parser.add_argument(
        "--profile-dir",
        default=str(Path(__file__).resolve().parent / "chrome-profile"),
        help="Chrome user data dir (persist cookies/history/extensions).",
    )
    parser.add_argument(
        "--extension",
        action="append",
        default=[],
        help="Path to .crx file or unpacked extension folder (repeatable).",
    )
    parser.add_argument("--workers", type=int, default=1, help="Number of parallel browser workers.")
    parser.add_argument(
        "--shared-profile",
        action="store_true",
        help="Reuse the same Chrome profile for all workers (forces workers=1).",
    )
    parser.add_argument(
        "--sync-extensions",
        action="store_true",
        default=None,
        help="Sync extensions from the base profile into worker profiles.",
    )
    parser.add_argument(
        "--zip",
        dest="zip_output",
        default="",
        help="Snapshot zip path (default: botanalyzer_snapshot_YYYYmmdd-HHMMSS.zip)",
    )
    parser.add_argument("--no-zip", action="store_true", help="Disable snapshot zip generation")
    parser.add_argument(
        "--precheck",
        action="store_true",
        default=True,
        help="Pre-check URLs with multi-threaded curl before Selenium.",
    )
    parser.add_argument("--no-precheck", action="store_true", help="Disable pre-check curl filtering")
    parser.add_argument("--precheck-timeout", type=int, default=DEFAULT_PRECHECK_TIMEOUT)
    parser.add_argument("--precheck-workers", type=int, default=DEFAULT_PRECHECK_WORKERS)
    parser.add_argument("--dead", default="dead.txt", help="Path to dead URLs output file")
    parser.add_argument("--blocked", default=DEFAULT_BLOCKED_OUTPUT, help="Path to blocked URLs output file")
    parser.add_argument("--lang", help="Chrome UI language, e.g. es-ES")
    parser.add_argument("--accept-languages", help="Chrome accept_languages list, e.g. es-ES,es,en")
    parser.add_argument("--auto", action="store_true", help="Enable automatic defaults (ThreatFox + precheck + safe limits).")
    parser.add_argument("--threatfox", action="store_true", help="Pull fresh IOCs from ThreatFox")
    parser.add_argument(
        "--threatfox-key",
        default=os.environ.get("THREATFOX_AUTH_KEY", ""),
        help="ThreatFox Auth-Key (or set THREATFOX_AUTH_KEY env var)",
    )
    parser.add_argument("--threatfox-days", type=int, default=DEFAULT_THREATFOX_DAYS)
    parser.add_argument("--threatfox-limit", type=int, default=DEFAULT_THREATFOX_LIMIT)
    parser.add_argument("--threatfox-timeout", type=int, default=DEFAULT_THREATFOX_TIMEOUT)
    parser.add_argument(
        "--threatfox-tag",
        default=DEFAULT_THREATFOX_TAG,
        help="ThreatFox tag to query (empty to use recent IOCs instead).",
    )
    # Running without arguments should only show available options and examples.
    if len(sys.argv) == 1:
        parser.print_help()
        return 0

    args = parser.parse_args()
    if args.auto:
        args.threatfox = True
        args.precheck = True
        args.no_precheck = False
        if args.max_url_seconds < DEFAULT_MAX_URL_SECONDS:
            args.max_url_seconds = DEFAULT_MAX_URL_SECONDS
        if args.max_clicks < DEFAULT_MAX_CLICKS:
            args.max_clicks = DEFAULT_MAX_CLICKS
        if args.max_div_clicks < DEFAULT_MAX_DIV_CLICKS:
            args.max_div_clicks = DEFAULT_MAX_DIV_CLICKS
    args.max_url_seconds = max(10, min(int(args.max_url_seconds), 600))
    args.max_clicks = max(10, min(int(args.max_clicks), 3000))
    args.max_frame_depth = max(1, min(int(args.max_frame_depth), 12))

    if SELENIUM_IMPORT_ERROR is not None:
        print(
            "[ERROR] Selenium is not installed in this environment. "
            "Install dependencies before running scans."
        )
        print(f"Details: {SELENIUM_IMPORT_ERROR}")
        return 2

    urls_path = Path(args.urls)
    done_path = Path(args.done)
    if not args.extension:
        default_extension = Path(__file__).resolve().parent.parent / "browser-extension"
        if default_extension.exists():
            args.extension.append(str(default_extension))
    global RUNTIME_MAX_DIV_CLICKS
    RUNTIME_MAX_DIV_CLICKS = max(50, min(int(args.max_div_clicks), 5000))
    source_urls = load_urls(urls_path)
    done_urls = load_done_urls(done_path)
    if done_urls:
        source_urls = [u for u in source_urls if normalize_url(u) not in done_urls]

    if args.threatfox:
        auth_key = str(args.threatfox_key or "").strip()
        if not auth_key:
            print("[THREATFOX] Missing Auth-Key. Set THREATFOX_AUTH_KEY or pass --threatfox-key.")
        else:
            tf_urls = fetch_threatfox_iocs(
                auth_key=auth_key,
                days=args.threatfox_days,
                limit=args.threatfox_limit,
                timeout=args.threatfox_timeout,
                tag=args.threatfox_tag,
            )
            if tf_urls:
                merged: list[str] = []
                seen = set(done_urls)
                for url in source_urls:
                    norm = normalize_url(url)
                    if not norm or norm in seen:
                        continue
                    seen.add(norm)
                    merged.append(norm)
                added = 0
                added_urls: list[str] = []
                for url in tf_urls:
                    norm = normalize_url(url)
                    if not norm or norm in seen:
                        continue
                    seen.add(norm)
                    merged.append(norm)
                    added_urls.append(norm)
                    added += 1
                source_urls = merged
                if added_urls:
                    for url in added_urls:
                        append_line(urls_path, url)
                tag_value = str(args.threatfox_tag or "").strip()
                if tag_value:
                    print(f"[THREATFOX] Added {added} URLs (tags={tag_value}).")
                else:
                    print(f"[THREATFOX] Added {added} URLs (days={args.threatfox_days}).")
            else:
                print("[THREATFOX] No URLs returned or request failed.")
    if not source_urls:
        print(f"No URLs found in {args.urls}")
        return 1

    if args.no_precheck:
        args.precheck = False
    elif args.precheck:
        probe_timeout = max(3, min(args.precheck_timeout, 8))
        if not curl_runtime_healthy(probe_timeout):
            print(
                "[PRECHECK] curl health-check failed in this environment. "
                "Precheck disabled for this run; Selenium will consume all URLs directly."
            )
            args.precheck = False

    if not args.no_zip:
        timestamp = time.strftime("%Y%m%d-%H%M%S")
        default_zip = f"{DEFAULT_SNAPSHOT_PREFIX}_{timestamp}.zip"
        zip_path = Path(args.zip_output) if args.zip_output else (Path(__file__).resolve().parent / default_zip)
        create_snapshot_zip(Path(__file__).resolve().parent, zip_path)
        print(f"[*] Snapshot saved: {zip_path}")

    args.workers = max(1, int(args.workers))
    if args.sync_extensions is None:
        args.sync_extensions = bool(args.workers > 1 and args.profile_dir and not args.shared_profile)
    if args.shared_profile and args.workers > 1:
        print("[WARN] --shared-profile with multiple workers can corrupt Chrome profile data.")
    if args.workers > 6:
        print(f"[WARN] workers={args.workers} is high; Chrome instances may be heavy.")

    url_queue: Queue[str] = Queue()

    file_lock = threading.Lock()
    threads: list[threading.Thread] = []
    print(f"[*] Initialized BotAnalyzer with {args.workers} worker(s)")
    try:
        for worker_id in range(1, args.workers + 1):
            thread = threading.Thread(
                target=worker_loop,
                args=(worker_id, url_queue, args, urls_path, done_path, file_lock),
                daemon=True,
            )
            threads.append(thread)
            thread.start()

        if args.precheck:
            dead_path = Path(args.dead)
            print(
                f"[*] Pre-checking {len(source_urls)} URLs with curl "
                f"({args.precheck_workers} workers) and streaming alive URLs to Selenium queue..."
            )
            alive_count, dead_count = stream_precheck_to_queue(
                source_urls,
                args.precheck_workers,
                args.precheck_timeout,
                url_queue,
                urls_path,
                dead_path,
                file_lock,
                remove_dead_from_urls=False,
            )
            if dead_count and alive_count > 0:
                print(f"[PRECHECK] Skipped {dead_count} dead URLs (logged in {dead_path}).")
            if alive_count == 0:
                if dead_count:
                    print(
                        "[PRECHECK] curl marco todos los URLs como no vivos "
                        f"({dead_count}/{len(source_urls)}). Falling back to Selenium for all URLs."
                    )
                else:
                    print("[PRECHECK] No live URLs remaining by curl. Falling back to Selenium for all URLs.")
                for url in source_urls:
                    url_queue.put(url)
        else:
            for url in source_urls:
                url_queue.put(url)

        for _ in range(args.workers):
            url_queue.put(QUEUE_SENTINEL)

        url_queue.join()

        while any(thread.is_alive() for thread in threads):
            time.sleep(0.5)
    except KeyboardInterrupt:
        print("[CTRL+C] Exit requested. Stopping after current URL.")
        return 0
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
