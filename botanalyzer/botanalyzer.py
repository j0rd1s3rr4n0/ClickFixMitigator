#!/usr/bin/env python3
import argparse
import time
from pathlib import Path
from typing import Iterable

from selenium import webdriver
from selenium.common.exceptions import WebDriverException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By


DEFAULT_PAGE_TIMEOUT = 120
DEFAULT_WAIT_CLOSE = 60
DEFAULT_BETWEEN_CLICKS = 3.25
DEFAULT_BUTTON_TIMEOUT = 10.0
DEFAULT_POST_LOAD_WAIT = 10.5
DEFAULT_MAX_FRAME_DEPTH = 5

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


def normalize_url(value: str) -> str:
    line = value.strip()
    if not line or line.startswith("#"):
        return ""
    if "://" not in line:
        line = f"https://{line}"
    return line


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


def collect_clickables(driver: webdriver.Chrome) -> list:
    elements = []
    for by, selector in BUTTON_SELECTORS + CLEANUP_CAPTCHA_SELECTORS:
        try:
            elements.extend(driver.find_elements(by, selector))
        except WebDriverException:
            continue
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


def wait_for_buttons(driver: webdriver.Chrome, timeout: float) -> int:
    deadline = time.time() + timeout
    last_count = 0
    while time.time() < deadline:
        try:
            last_count = count_clickables_in_frames(driver)
            if last_count:
                return last_count
        except WebDriverException:
            pass
        time.sleep(0.25)
    return last_count


def click_clickables(driver: webdriver.Chrome, delay: float) -> int:
    elements = collect_clickables(driver)
    clicked = 0
    seen = set()
    for element in elements:
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
    depth: int = 0,
    max_depth: int = DEFAULT_MAX_FRAME_DEPTH,
) -> int:
    if depth > max_depth:
        return 0
    clicked = 0
    try:
        clicked += click_clickables(driver, delay)
        frames = driver.find_elements(By.CSS_SELECTOR, "iframe, frame")
    except WebDriverException:
        return clicked
    for frame in frames:
        try:
            driver.switch_to.frame(frame)
        except WebDriverException:
            continue
        try:
            clicked += click_clickables_in_frames(driver, delay, depth + 1, max_depth)
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


def main() -> int:
    parser = argparse.ArgumentParser(description="Click all buttons on URLs listed in urls.txt (Selenium).")
    parser.add_argument("--urls", default="urls.txt", help="Path to urls.txt")
    parser.add_argument("--done", default="done.txt", help="Path to done.txt")
    parser.add_argument("--headful", action="store_true", help="Run with visible browser window")
    parser.add_argument("--page-timeout", type=int, default=DEFAULT_PAGE_TIMEOUT)
    parser.add_argument("--wait-close", type=int, default=DEFAULT_WAIT_CLOSE)
    parser.add_argument("--between-clicks", type=float, default=DEFAULT_BETWEEN_CLICKS)
    parser.add_argument("--button-timeout", type=float, default=DEFAULT_BUTTON_TIMEOUT)
    parser.add_argument("--post-load-wait", type=float, default=DEFAULT_POST_LOAD_WAIT)
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
    parser.add_argument("--lang", help="Chrome UI language, e.g. es-ES")
    parser.add_argument("--accept-languages", help="Chrome accept_languages list, e.g. es-ES,es,en")
    args = parser.parse_args()

    urls_path = Path(args.urls)
    done_path = Path(args.done)
    if not args.extension:
        default_extension = Path(__file__).resolve().parent.parent / "browser-extension"
        if default_extension.exists():
            args.extension.append(str(default_extension))
    urls = load_urls(urls_path)
    if not urls:
        print(f"No URLs found in {args.urls}")
        return 1

    profile_dir = Path(args.profile_dir) if args.profile_dir else None
    driver = init_driver(
        args.headful,
        profile_dir,
        args.extension,
        args.lang,
        args.accept_languages,
        args.page_timeout,
    )
    try:
        print("[*] Initialized BotAnalyzer")
        time.sleep(30)
        for url in urls:
            print(f"[OPEN] {url}")
            try:
                try:
                    driver.get(url)
                except WebDriverException:
                    print(f"[TIMEOUT] {url}")
                wait_for_dom_ready(driver, args.page_timeout)
                if args.post_load_wait:
                    time.sleep(args.post_load_wait)
                found = wait_for_buttons(driver, args.button_timeout)
                if found:
                    print(f"[BUTTONS] {found} detected after JS load")
                clicked = click_clickables_in_frames(driver, args.between_clicks)
                print(f"[CLICKED] {clicked} buttons")
                closed = wait_for_close(driver, args.wait_close)
                if not closed:
                    try:
                        driver.close()
                    except WebDriverException:
                        pass
                    try:
                        driver.switch_to.window(driver.window_handles[0])
                    except WebDriverException:
                        driver = reset_driver(
                            driver,
                            args.headful,
                            profile_dir,
                            args.extension,
                            args.lang,
                            args.accept_languages,
                            args.page_timeout,
                        )
            except Exception as error:
                error_type = type(error).__name__
                print(f"[ERROR] {url} -> {error_type}: {error}")
                try:
                    driver = reset_driver(
                        driver,
                        args.headful,
                        profile_dir,
                        args.extension,
                        args.lang,
                        args.accept_languages,
                        args.page_timeout,
                    )
                except Exception as reset_error:
                    reset_type = type(reset_error).__name__
                    print(f"[FATAL] driver reset failed ({reset_type}): {reset_error}")
                    return 1
            finally:
                append_line(done_path, url)
                remove_url_from_file(urls_path, url)
    finally:
        try:
            driver.quit()
        except WebDriverException:
            pass
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
