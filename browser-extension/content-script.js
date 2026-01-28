const CLICKFIX_WIN_R_REGEX =
  /(win\s*\+\s*r|w\s*i\s*n\s*\+\s*r|win\s+r|windows\s*\+\s*r|windows\s+key|windows\s+logo\s+key|windows\s+button|logo\s+de\s+windows|presiona\s+windows|tecla\s+windows|tecla\s+de\s+windows|tecla\s+⊞|⊞\s*\+\s*r|run\s+dialog|run\s*box|runbox|rundialog|open\s+run|abre\s+ejecutar|cuadro\s+de\s+ejecutar|ventana\s+de\s+ejecutar|simbolo\s+del\s+sistema|símbolo\s+del\s+sistema|abr(e|ir)\s+cmd|abr(e|ir)\s+powershell|abr(e|ir)\s+terminal|abre\s+la\s+consola)/i;
const CLICKFIX_WIN_X_REGEX =
  /(win\s*\+\s*x|w\s*i\s*n\s*\+\s*x|win\s+x|windows\s*\+\s*x|menu\s+de\s+acceso\s+rápido|quick\s+link\s+menu|power\s+user\s+menu|menu\s+winx|abre\s+winx|open\s+winx)/i;
const CLICKFIX_CAPTCHA_REGEX =
  /(captcha|no\s+soy\s+un\s+robot|no\s+eres\s+un\s+robot|i('?| a)?m\s+not\s+a\s+robot|verify\s+you('?| a)?re\s+human|human\s+verification|verifica\s+que\s+eres\s+humano|confirmar\s+que\s+eres\s+humano)/i;
const CLICKFIX_FAKE_ERROR_REGEX =
  /(algo\s+sal(i|ió)\s+mal|something\s+went\s+wrong|error\s+al\s+mostrar\s+esta\s+p(a|á)gina|error\s+displaying\s+this\s+page|browser\s+update|actualizaci(o|ó)n\s+del\s+navegador|suspicious\s+activity|actividad\s+sospechosa|security\s+check|chequeo\s+de\s+seguridad|verify\s+your\s+browser|verifica\s+tu\s+navegador)/i;
const CLICKFIX_FIX_ACTION_REGEX =
  /(fix\s+it|fix\s+this|copy\s+fix|copy\s+solution|copiar\s+soluci(o|ó)n|copiar\s+solucion|resolver\s+el\s+problema|how\s+to\s+fix|soluci(o|ó)n\s+r(a|á)pida)/i;
const CLICKFIX_CONSOLE_REGEX =
  /(devtools\s+console|consola\s+de\s+devtools|console\s+de\s+desarrolladores|developer\s+console|browser\s+console|web\s+console|console\s+of\s+the\s+browser|allow\s+pasting|permitir\s+pegar|pega\s+esto\s+en\s+la\s+consola|paste\s+this\s+into\s+the\s+console|paste\s+in(to)?\s+console|pega\s+en\s+la\s+consola|pegar\s+en\s+consola|open\s+devtools|abre\s+devtools|open\s+developer\s+tools|abre\s+las\s+herramientas\s+de\s+desarrollador)/i;
const CLICKFIX_SHELL_PASTE_REGEX =
  /(pega\s+en\s+(cmd|powershell|terminal|consola|ejecutar)|pega\s+esto\s+en\s+(cmd|powershell|terminal|consola|ejecutar)|pegalo\s+en\s+(cmd|powershell|terminal|consola|ejecutar)|paste\s+in(to)?\s+(cmd|powershell|terminal|run|command\s+prompt)|paste\s+this\s+in(to)?\s+(cmd|powershell|terminal|run|command\s+prompt)|open\s+(cmd|powershell|terminal|command\s+prompt)\s+and\s+paste|open\s+the\s+run\s+dialog|abre\s+el\s+cuadro\s+de\s+ejecutar|abre\s+ejecutar|open\s+cmd|open\s+powershell|open\s+terminal|abre\s+cmd|abre\s+powershell|abre\s+terminal)/i;
const CLICKFIX_PASTE_SEQUENCE_REGEX =
  /(ctrl\s*\+\s*v|control\s*\+\s*v|pega\s+con\s+ctrl\s*\+\s*v|press\s+ctrl\s*\+\s*v|then\s+press\s+enter|pulsa\s+enter|presiona\s+enter|hit\s+enter|ctrl\s*\+\s*shift\s*\+\s*enter|control\s*\+\s*shift\s*\+\s*enter)/i;
const CLICKFIX_FILE_EXPLORER_REGEX =
  /(file\s+explorer|explorador\s+de\s+archivos|address\s+bar|barra\s+de\s+direcciones|ctrl\s*\+\s*l|control\s*\+\s*l|alt\s*\+\s*d|pega\s+la\s+ruta|paste\s+the\s+path|open\s+file\s+explorer|abre\s+el\s+explorador)/i;
const CLICKFIX_COPY_TRIGGER_REGEX =
  /(execCommand\(['"]copy['"]\)|navigator\.clipboard\.writeText|clipboard\.writeText)/i;
const CLICKFIX_RECAPTCHA_ID_REGEX = /reCAPTCHA Verification ID/i;
const COMMAND_REGEX =
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|bash|sh|zsh|curl|wget|rundll32|regsvr32|msbuild|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic|explorer(\.exe)?|reg\s+add|p[\s^`]*o[\s^`]*w[\s^`]*e[\s^`]*r[\s^`]*s[\s^`]*h[\s^`]*e[\s^`]*l[\s^`]*l|c[\s^`]*m[\s^`]*d)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|invoke-restmethod|\biwr\b|\birm\b|curl\s+|wget\s+|downloadstring|frombase64string|start-bitstransfer|add-mppreference|invoke-expression|\biex\b|\biex\s*\(|encodedcommand|\-enc\b|\-encodedcommand\b|powershell\s+\-|cmd\s+\/c|bash\s+\-c|sh\s+\-c|rundll32\s+[^\s,]+,[^\s]+|regsvr32\s+\/i|certutil\s+\-urlcache|bitsadmin\s+\/transfer)/i;
const URL_REGEX = /\bhttps?:\/\/[^\s"']+/i;
const SHELL_META_REGEX = /[;&|`]/;
const BASE64_CHUNK_REGEX = /[A-Za-z0-9+/]{40,}={0,2}/g;
const ZERO_WIDTH_REGEX = /[\u200B-\u200D\u2060\uFEFF]/g;
const CONTROL_CHAR_REGEX = /[\u0000-\u001F\u007F]/g;
const TRUSTED_CODE_HOSTS = [
  "github.com",
  "gist.github.com",
  "stackoverflow.com",
  "stackexchange.com",
  "superuser.com",
  "serverfault.com",
  "learn.microsoft.com",
  "developer.mozilla.org"
];
const CLIPBOARD_ANALYSIS_LIMIT = 12000;
const CLIPBOARD_ALERT_THROTTLE_MS = 15000;
const CLIPBOARD_THREAT_TYPE = "CLICKFIX_CLIPBOARD_THREAT";
const MAX_SELECTION_LENGTH = 8000;
const FULL_CONTEXT_LIMIT = 40000;
const MAX_SCAN_LENGTH = 200000;
const MAX_IFRAME_SCAN_DEPTH = 6;
const MAX_IFRAME_SOURCE_SAMPLES = 3;

let lastSelectionText = "";
let winRDetected = false;
let winXDetected = false;
let captchaDetected = false;
let browserErrorDetected = false;
let fixActionDetected = false;
let consoleDetected = false;
let shellDetected = false;
let pasteSequenceDetected = false;
let fileExplorerDetected = false;
let commandDetected = false;
let copyTriggerDetected = false;
let lastClipboardSnapshot = "";
let clipboardWatchRunning = false;
let lastScanSnapshot = { text: "", html: "", timestamp: 0 };
let blockAllInjected = false;
let blockAllActive = false;
let blockAllInjectionChecked = false;
let blockAllRequested = false;
let blockAllUnavailableNotified = false;

let currentAllowlisted = false;
let familySafeEnabled = false;
let uiThemePreference = "system";
let muteDetectionNotifications = false;
let lastClipboardThreat = { text: "", timestamp: 0 };
let iframeSnapshot = { total: 0, opaque: 0, opaqueSources: [] };
let iframeScanPending = false;
let clipboardPostCheckTimer = null;
let lastUrlSnapshot = window.location.href;

const isTopFrame = window === window.top;
try {
  blockAllUnavailableNotified =
    window.sessionStorage?.getItem("clickfixBlockAllUnavailableShown") === "true";
} catch (error) {
  blockAllUnavailableNotified = false;
}

const BLOCK_ALL_UPDATE_TYPE = "CLICKFIX_BLOCK_ALL_UPDATE";
const BLOCK_ALL_BLOCKED_TYPE = "CLICKFIX_BLOCK_ALL_BLOCKED";

function getHostname(url) {
  try {
    return new URL(url).hostname;
  } catch (error) {
    return "";
  }
}

function getReferrerUrl() {
  const referrer = document.referrer;
  if (!referrer) {
    return "";
  }
  try {
    const parsed = new URL(referrer);
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
      return "";
    }
    return referrer;
  } catch (error) {
    return "";
  }
}

function t(key, substitutions) {
  if (activeMessages?.[key]?.message) {
    return formatMessage(activeMessages[key].message, substitutions);
  }
  return chrome.i18n.getMessage(key, substitutions) || key;
}

function tBlocked(key, substitutions) {
  return t(key, substitutions);
}

function buildLocalizedReasons(reasonEntries, fallbackReasons = []) {
  if (Array.isArray(reasonEntries) && reasonEntries.length) {
    return reasonEntries
      .map((entry) => {
        if (!entry || !entry.key) {
          return "";
        }
        const value =
          entry.value === undefined || entry.value === null ? undefined : entry.value;
        return value === undefined ? tBlocked(entry.key) : tBlocked(entry.key, value);
      })
      .filter(Boolean);
  }
  return Array.isArray(fallbackReasons) ? fallbackReasons.filter(Boolean) : [];
}

const SUPPORTED_LOCALES = ["en", "es", "ca", "de", "fr", "nl", "he", "ru", "zh", "ko", "ja", "pt", "ar", "hi"];
const DEFAULT_LOCALE = "en";
let activeMessages = null;
let localeReady = Promise.resolve();
const RTL_LOCALES = new Set(["ar"]);
let currentLocale = DEFAULT_LOCALE;

function formatMessage(message, substitutions) {
  if (!substitutions) {
    return message;
  }
  const values = Array.isArray(substitutions) ? substitutions : [substitutions];
  return values.reduce((result, value, index) => {
    return result.replaceAll(`$${index + 1}`, value);
  }, message);
}

function normalizeLocale(locale) {
  if (!locale) {
    return DEFAULT_LOCALE;
  }
  const lower = locale.toLowerCase();
  if (SUPPORTED_LOCALES.includes(lower)) {
    return lower;
  }
  const base = lower.split("-")[0];
  return SUPPORTED_LOCALES.includes(base) ? base : DEFAULT_LOCALE;
}

async function loadLocaleMessages(locale) {
  try {
    const response = await fetch(chrome.runtime.getURL(`_locales/${locale}/messages.json`));
    if (!response.ok) {
      activeMessages = null;
      return;
    }
    activeMessages = await response.json();
  } catch (error) {
    activeMessages = null;
  }
}

async function initLocale() {
  const { uiLanguage } = await chrome.storage.local.get({ uiLanguage: "" });
  const selectedLocale = normalizeLocale(uiLanguage || "en");
  await loadLocaleMessages(selectedLocale);
  currentLocale = selectedLocale;
}

function ensureLocaleReady() {
  return localeReady.catch(() => undefined);
}

function injectBlockAllScript() {
  if (blockAllInjected || !document.documentElement) {
    return;
  }
  document.documentElement.dataset.clickfixBlockallInjected = "false";
  const script = document.createElement("script");
  script.src = chrome.runtime.getURL("block-all-inject.js");
  script.onload = () => {
    blockAllInjected = true;
    script.remove();
    checkBlockAllInjection();
  };
  script.onerror = () => {
    script.remove();
    checkBlockAllInjection();
  };
  (document.head || document.documentElement).appendChild(script);
}

function checkBlockAllInjection() {
  if (!document.documentElement || blockAllInjectionChecked) {
    return;
  }
  blockAllInjectionChecked = true;
  const injected = document.documentElement.dataset.clickfixBlockallInjected === "true";
  if (!injected) {
    console.info("[ClickFix Mitigator] Block All script could not be injected on this page.");
    const protocol = window.location.protocol || "";
    if (
      blockAllRequested &&
      isTopFrame &&
      (protocol === "http:" || protocol === "https:") &&
      !blockAllUnavailableNotified
    ) {
      renderBanner(t("blockAllClipboardUnavailable"), "subtle");
      blockAllUnavailableNotified = true;
      try {
        window.sessionStorage?.setItem("clickfixBlockAllUnavailableShown", "true");
      } catch (error) {
        // Ignore storage failures.
      }
    }
  }
}

async function updateBlockAllClipboardState() {
  const { blockAllClipboard, enabled } = await chrome.storage.local.get({
    blockAllClipboard: true,
    enabled: true
  });
  const allowlisted = await new Promise((resolve) => {
    safeSendMessage({ type: "checkAllowlist", url: window.location.href }, (response) => {
      resolve(Boolean(response?.allowlisted));
    });
  });
  currentAllowlisted = allowlisted;
  updatePageContextFlags();
  blockAllRequested = Boolean(blockAllClipboard && enabled && !allowlisted);
  if (blockAllRequested && !blockAllInjected) {
    injectBlockAllScript();
  }
  if (blockAllRequested !== blockAllActive) {
    blockAllActive = blockAllRequested;
    window.postMessage({ type: BLOCK_ALL_UPDATE_TYPE, enabled: blockAllActive }, "*");
  }
}

async function updateFamilySafeState() {
  const { familySafe } = await chrome.storage.local.get({ familySafe: false });
  familySafeEnabled = Boolean(familySafe);
}

function normalizeTheme(value) {
  return value === "dark" || value === "light" ? value : "system";
}

function resolveTheme(value) {
  const normalized = normalizeTheme(value);
  if (normalized !== "system") {
    return normalized;
  }
  if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
    return "dark";
  }
  return "light";
}

async function updateThemeState() {
  const { uiTheme } = await chrome.storage.local.get({ uiTheme: "system" });
  uiThemePreference = normalizeTheme(uiTheme);
}

async function updateNotificationPreference() {
  const { muteDetectionNotifications: mute } = await chrome.storage.local.get({
    muteDetectionNotifications: false
  });
  muteDetectionNotifications = Boolean(mute);
}

function showBanner(text) {
  const existing = document.getElementById("clickfix-mitigator-banner");
  if (existing) {
    const messageNode = existing.querySelector("[data-clickfix-message]");
    if (messageNode) {
      messageNode.textContent = text;
    }
    return;
  }
  const root = document.body || document.documentElement;
  if (!root) {
    return;
  }
  const banner = document.createElement("div");
  banner.id = "clickfix-mitigator-banner";
  banner.style.cssText = [
    "position:fixed",
    "top:16px",
    "right:16px",
    "z-index:2147483647",
    "background:linear-gradient(135deg, #2563eb 0%, #1e40af 100%)",
    "color:#fff",
    "padding:16px 18px",
    "font-family:system-ui, sans-serif",
    "font-size:14px",
    "border-radius:16px",
    "box-shadow:0 18px 40px rgba(15, 23, 42, 0.35)",
    "display:flex",
    "gap:14px",
    "align-items:flex-start",
    "max-width:360px",
    "border:1px solid rgba(255,255,255,0.2)"
  ].join(";");

  const icon = document.createElement("div");
  icon.style.cssText = [
    "width:40px",
    "height:40px",
    "border-radius:12px",
    "background:rgba(255,255,255,0.15)",
    "display:flex",
    "align-items:center",
    "justify-content:center",
    "flex-shrink:0"
  ].join(";");
  icon.innerHTML =
    "<svg width='22' height='22' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'>" +
    "<path d='M12 3l9 16H3L12 3z' fill='white'/>" +
    "<path d='M12 8.5v5.5' stroke='#1e40af' stroke-width='2' stroke-linecap='round'/>" +
    "<circle cx='12' cy='17' r='1.4' fill='#1e40af'/>" +
    "</svg>";

  const content = document.createElement("div");
  content.style.cssText = [
    "display:flex",
    "flex-direction:column",
    "gap:6px",
    "flex:1"
  ].join(";");

  const title = document.createElement("div");
  title.textContent = t("bannerTitle");
  title.style.cssText = [
    "font-weight:700",
    "font-size:13px",
    "letter-spacing:0.3px",
    "text-transform:uppercase"
  ].join(";");

  const message = document.createElement("div");
  message.textContent = text;
  message.setAttribute("data-clickfix-message", "true");
  message.style.cssText = [
    "line-height:1.4",
    "font-size:14px"
  ].join(";");

  const closeButton = document.createElement("button");
  closeButton.textContent = t("closeButton");
  closeButton.style.cssText = [
    "background:rgba(255,255,255,0.15)",
    "color:#fff",
    "border:none",
    "padding:6px 10px",
    "border-radius:999px",
    "cursor:pointer",
    "font-size:12px",
    "font-weight:600"
  ].join(";");
  closeButton.addEventListener("click", () => banner.remove());

  content.appendChild(title);
  content.appendChild(message);
  banner.appendChild(icon);
  banner.appendChild(content);
  banner.appendChild(closeButton);
  root.appendChild(banner);
}

function safeSendMessage(message, callback) {
  if (!chrome?.runtime?.id) {
    if (callback) {
      callback();
    }
    return;
  }
  try {
    chrome.runtime.sendMessage(message, (response) => {
      if (chrome.runtime.lastError) {
        if (callback) {
          callback();
        }
        return;
      }
      if (callback) {
        callback(response);
      }
    });
  } catch (error) {
    if (callback) {
      callback();
    }
  }
}

window.addEventListener("message", (event) => {
  if (event.source !== window) {
    return;
  }
  if (event.data?.type === BLOCK_ALL_BLOCKED_TYPE) {
    showBanner(t("blockAllClipboardBlocked"));
    return;
  }
  if (event.data?.type === CLIPBOARD_THREAT_TYPE) {
    handleClipboardThreatMessage(event.data);
  }
});

chrome.storage.onChanged.addListener((changes, area) => {
  if (area === "local" && changes.uiLanguage) {
    localeReady = initLocale();
  }
  if (
    area === "local" &&
    (changes.blockAllClipboard ||
      changes.enabled ||
      changes.whitelist ||
      changes.allowlist ||
      changes.allowlistUpdatedAt)
  ) {
    updateBlockAllClipboardState();
  }
  if (area === "local" && changes.familySafe) {
    updateFamilySafeState();
  }
  if (area === "local" && changes.uiTheme) {
    uiThemePreference = normalizeTheme(changes.uiTheme.newValue);
  }
  if (area === "local" && changes.muteDetectionNotifications) {
    muteDetectionNotifications = Boolean(changes.muteDetectionNotifications.newValue);
  }
});

localeReady = initLocale();

async function getBlocklistStatus() {
  return new Promise((resolve) => {
    safeSendMessage(
      { type: "checkBlocklist", url: window.location.href },
      (response) => resolve(response)
    );
  });
}

function sendPageAlert(alertType, snippet) {
  const fullContext = collectFullContext();
  safeSendMessage({
    type: "pageAlert",
    alertType,
    snippet,
    url: window.location.href,
    timestamp: Date.now(),
    fullContext,
    previousUrl: getReferrerUrl()
  });
}


function collectFullContext() {
  if (!document.body) {
    return "";
  }
  const rawText = document.body.innerText || document.body.textContent || "";
  const trimmed = rawText.trim();
  if (!trimmed) {
    return "";
  }
  if (trimmed.length <= FULL_CONTEXT_LIMIT) {
    return trimmed;
  }
  return trimmed.slice(0, FULL_CONTEXT_LIMIT);
}

function escapeHtml(value) {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function renderBanner(text, variant = "alert") {
  const existing = document.getElementById("clickfix-mitigator-banner");
  if (existing) {
    const messageNode = existing.querySelector("[data-clickfix-message]");
    if (messageNode) {
      messageNode.textContent = text;
    }
    return;
  }
  const root = document.body || document.documentElement;
  if (!root) {
    return;
  }
  const banner = document.createElement("div");
  banner.id = "clickfix-mitigator-banner";
  const isSubtle = variant === "subtle";
  banner.style.cssText = [
    "position:fixed",
    "top:16px",
    "right:16px",
    "z-index:2147483647",
    isSubtle
      ? "background:rgba(15, 23, 42, 0.92)"
      : "background:linear-gradient(135deg, #dc2626 0%, #991b1b 100%)",
    "color:#fff",
    isSubtle ? "padding:10px 12px" : "padding:16px 18px",
    "font-family:system-ui, sans-serif",
    isSubtle ? "font-size:12px" : "font-size:14px",
    "border-radius:12px",
    "box-shadow:0 14px 30px rgba(15, 23, 42, 0.28)",
    "display:flex",
    "gap:12px",
    "align-items:flex-start",
    "max-width:320px",
    isSubtle ? "border:1px solid rgba(148, 163, 184, 0.35)" : "border:1px solid rgba(255,255,255,0.2)"
  ].join(";");

  const icon = document.createElement("div");
  icon.style.cssText = [
    "width:32px",
    "height:32px",
    "border-radius:10px",
    isSubtle ? "background:rgba(148, 163, 184, 0.18)" : "background:rgba(255,255,255,0.15)",
    "display:flex",
    "align-items:center",
    "justify-content:center",
    "flex-shrink:0"
  ].join(";");
  const bannerIconUrl = chrome.runtime?.getURL ? chrome.runtime.getURL("icons/icon-48.png") : "";
  if (bannerIconUrl) {
    const img = document.createElement("img");
    img.src = bannerIconUrl;
    img.alt = "ClickFix Mitigator";
    img.width = 18;
    img.height = 18;
    img.style.borderRadius = "6px";
    icon.appendChild(img);
  } else {
    icon.innerHTML =
      "<svg width='18' height='18' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'>" +
      "<path d='M12 3l9 16H3L12 3z' fill='white'/>" +
      "<path d='M12 8.5v5.5' stroke='#991b1b' stroke-width='2' stroke-linecap='round'/>" +
      "<circle cx='12' cy='17' r='1.4' fill='#991b1b'/>" +
      "</svg>";
  }

  const content = document.createElement("div");
  content.style.cssText = [
    "display:flex",
    "flex-direction:column",
    "gap:4px",
    "flex:1"
  ].join(";");

  const title = document.createElement("div");
  title.textContent = t("bannerTitle");
  title.style.cssText = [
    "font-weight:700",
    "font-size:11px",
    "letter-spacing:0.3px",
    "text-transform:uppercase"
  ].join(";");

  const message = document.createElement("div");
  message.textContent = text;
  message.setAttribute("data-clickfix-message", "true");
  message.style.cssText = [
    "line-height:1.4",
    "font-size:12px"
  ].join(";");

  const closeButton = document.createElement("button");
  closeButton.textContent = t("closeButton");
  closeButton.style.cssText = [
    "background:rgba(255,255,255,0.15)",
    "color:#fff",
    "border:none",
    "padding:4px 8px",
    "border-radius:999px",
    "cursor:pointer",
    "font-size:11px",
    "font-weight:600"
  ].join(";");
  closeButton.addEventListener("click", () => banner.remove());

  content.appendChild(title);
  content.appendChild(message);
  banner.appendChild(icon);
  banner.appendChild(content);
  banner.appendChild(closeButton);
  root.appendChild(banner);
}

function buildHighlightedHtml(text, snippets) {
  if (!text) {
    return "";
  }
  const lowerText = text.toLowerCase();
  const ranges = [];
  const uniqueSnippets = [...new Set(snippets.filter(Boolean))]
    .map((snippet) => snippet.trim())
    .filter(Boolean)
    .sort((a, b) => b.length - a.length);

  uniqueSnippets.forEach((snippet) => {
    const lowerSnippet = snippet.toLowerCase();
    let index = 0;
    while (index < lowerText.length) {
      const found = lowerText.indexOf(lowerSnippet, index);
      if (found === -1) {
        break;
      }
      ranges.push({ start: found, end: found + lowerSnippet.length });
      index = found + lowerSnippet.length;
    }
  });

  if (!ranges.length) {
    return escapeHtml(text);
  }

  ranges.sort((a, b) => a.start - b.start || b.end - a.end);
  const merged = [];
  ranges.forEach((range) => {
    const last = merged[merged.length - 1];
    if (last && range.start <= last.end) {
      last.end = Math.max(last.end, range.end);
    } else {
      merged.push({ ...range });
    }
  });

  let output = "";
  let cursor = 0;
  merged.forEach((range) => {
    output += escapeHtml(text.slice(cursor, range.start));
    output += `<mark class="clickfix-highlight">${escapeHtml(text.slice(range.start, range.end))}</mark>`;
    cursor = range.end;
  });
  output += escapeHtml(text.slice(cursor));
  return output;
}

function buildBlockedPage(hostname, reasonText, reasons = [], contextText = "", snippets = [], options = {}) {
  try {
    window.stop();
  } catch (error) {
    // Ignore stop failures.
  }
  const familySafe = options.familySafe ?? familySafeEnabled;
  const resolvedTheme = resolveTheme(options.theme ?? uiThemePreference);
  const title = tBlocked("blockedTitle");
  const filteredReasons = reasons.filter(Boolean);
  const reason = filteredReasons.length
    ? tBlocked("blockedReasonDefault")
    : reasonText
      ? tBlocked("blockedReasonWithDetail", reasonText)
      : tBlocked("blockedReasonDefault");
  const container = document.createElement("div");
  container.className = "clickfix-blocked";

  const card = document.createElement("div");
  card.className = "clickfix-card";

  const iconWrap = document.createElement("div");
  iconWrap.className = "clickfix-icon";
  const iconDataUrl = "data:image/png;base64,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";
  if (iconDataUrl) {
    const iconImg = document.createElement("img");
    iconImg.src = iconDataUrl;
    iconImg.alt = "ClickFix Mitigator";
    iconImg.width = 36;
    iconImg.height = 36;
    iconWrap.appendChild(iconImg);
  } else {
    iconWrap.innerHTML =
      "<svg width='30' height='30' viewBox='0 0 24 24' fill='none' xmlns='http://www.w3.org/2000/svg'>" +
      "<path d='M12 3l9 16H3L12 3z' fill='white'/>" +
      "<path d='M12 8.5v5.5' stroke='#1e40af' stroke-width='2' stroke-linecap='round'/>" +
      "<circle cx='12' cy='17' r='1.4' fill='#1e40af'/>" +
      "</svg>";
  }

  const brand = document.createElement("div");
  brand.className = "clickfix-brand";
  brand.textContent = "ClickFix Mitigator";

  const heading = document.createElement("div");
  heading.textContent = title;
  heading.className = "clickfix-title";

  const subtitle = document.createElement("div");
  subtitle.textContent = reason;
  subtitle.className = "clickfix-subtitle";

  const familyNotice = document.createElement("div");
  familyNotice.className = "clickfix-family-note";
  familyNotice.textContent = t("blockedFamilySafeNotice");

  const reasonsTitle = document.createElement("div");
  reasonsTitle.textContent = t("blockedReasonsTitle");
  reasonsTitle.className = "clickfix-section-title";

  const reasonList = document.createElement("ul");
  reasonList.className = "clickfix-reason-list";

  filteredReasons.forEach((entry) => {
    const item = document.createElement("li");
    item.textContent = entry;
    item.className = "clickfix-reason-chip";
    reasonList.appendChild(item);
  });

  const hostText = document.createElement("div");
  hostText.textContent = hostname ? t("blockedHost", hostname) : "";
  hostText.className = "clickfix-host";

  const contextLabel = document.createElement("div");
  contextLabel.textContent = t("blockedContextTitle");
  contextLabel.className = "clickfix-section-title";

  const contextBox = document.createElement("div");
  contextBox.setAttribute("role", "textbox");
  contextBox.setAttribute("aria-readonly", "true");
  contextBox.className = "clickfix-context";

  if (contextText) {
    contextBox.innerHTML = buildHighlightedHtml(contextText, snippets);
  }

  const buttonRow = document.createElement("div");
  buttonRow.className = "clickfix-actions";

  const backRow = document.createElement("div");
  backRow.className = "clickfix-back-actions";

  const reportButton = document.createElement("button");
  reportButton.type = "button";
  reportButton.textContent = t("blockedReport");
  reportButton.className = "clickfix-btn clickfix-btn-outline warning";

  const allowOnceButton = document.createElement("button");
  allowOnceButton.type = "button";
  allowOnceButton.textContent = t("blockedAllowOnce");
  allowOnceButton.className = "clickfix-btn clickfix-btn-outline danger";

  const allowSessionButton = document.createElement("button");
  allowSessionButton.type = "button";
  allowSessionButton.textContent = t("blockedAllowSession");
  allowSessionButton.className = "clickfix-btn clickfix-btn-outline danger";

  const allowAlwaysButton = document.createElement("button");
  allowAlwaysButton.type = "button";
  allowAlwaysButton.textContent = t("blockedAllowAlways");
  allowAlwaysButton.className = "clickfix-btn clickfix-btn-solid danger";

  const backButton = document.createElement("button");
  backButton.type = "button";
  backButton.textContent = t("blockedBack");
  backButton.className = "clickfix-btn clickfix-btn-solid primary";

  allowOnceButton.addEventListener("click", () => {
    const currentHost = hostname || getHostname(window.location.href);
    if (currentHost) {
      sessionStorage.setItem("clickfix-allow-host", currentHost);
    }
    window.location.reload();
  });

  allowSessionButton.addEventListener("click", () => {
    const currentHost = hostname || getHostname(window.location.href);
    if (currentHost) {
      const raw = sessionStorage.getItem("clickfix-allow-session") || "[]";
      const list = new Set(JSON.parse(raw));
      list.add(currentHost);
      sessionStorage.setItem("clickfix-allow-session", JSON.stringify([...list]));
    }
    window.location.reload();
  });

  allowAlwaysButton.addEventListener("click", async () => {
    const currentHost = hostname || getHostname(window.location.href);
    await new Promise((resolve) => {
      safeSendMessage(
        { type: "allowSite", hostname: currentHost },
        () => resolve()
      );
    });
    window.location.reload();
  });

  reportButton.addEventListener("click", () => {
    safeSendMessage({
      type: "manualReport",
      url: window.location.href,
      hostname: hostname || getHostname(window.location.href),
      timestamp: Date.now(),
      previousUrl: getReferrerUrl()
    });
    reportButton.disabled = true;
    reportButton.textContent = tBlocked("blockedReported");
  });

  backButton.addEventListener("click", () => {
    if (window.history.length > 1) {
      window.history.back();
    } else {
      window.location.href = "https://www.google.com";
    }
  });

  if (familySafe) {
    backRow.appendChild(backButton);
  } else {
    buttonRow.appendChild(reportButton);
    buttonRow.appendChild(allowOnceButton);
    buttonRow.appendChild(allowSessionButton);
    buttonRow.appendChild(allowAlwaysButton);
    backRow.appendChild(backButton);
  }

  const header = document.createElement("div");
  header.className = "clickfix-header";
  header.appendChild(iconWrap);
  header.appendChild(brand);
  header.appendChild(heading);

  card.appendChild(header);
  card.appendChild(subtitle);
  if (familySafe) {
    card.appendChild(familyNotice);
  }
  if (filteredReasons.length) {
    card.appendChild(reasonsTitle);
    card.appendChild(reasonList);
  }
  if (contextText) {
    card.appendChild(contextLabel);
    card.appendChild(contextBox);
  }
  if (hostname) {
    card.appendChild(hostText);
  }
  card.appendChild(buttonRow);
  card.appendChild(backRow);
  container.appendChild(card);

  const html = document.documentElement;
  if (!html) {
    return;
  }
  if (currentLocale) {
    html.lang = currentLocale;
    html.dir = RTL_LOCALES.has(currentLocale) ? "rtl" : "ltr";
  }
  html.dataset.theme = resolvedTheme;
  const head = document.createElement("head");
  const style = document.createElement("style");
  style.textContent = `
    :root {
      color-scheme: light;
      --page-bg: radial-gradient(circle at top, #eff6ff 0%, #dbeafe 45%, #c7d2fe 100%);
      --card-bg: #ffffff;
      --card-border: rgba(59, 130, 246, 0.25);
      --card-shadow: 0 30px 80px rgba(37, 99, 235, 0.2);
      --card-glow: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(59, 130, 246, 0));
      --icon-bg: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      --icon-shadow: 0 16px 30px rgba(37, 99, 235, 0.3);
      --icon-img-bg: rgba(255, 255, 255, 0.18);
      --brand: #1d4ed8;
      --title: #1e3a8a;
      --subtitle: #1e40af;
      --text: #1f2937;
      --family-bg: rgba(37, 99, 235, 0.08);
      --family-border: rgba(37, 99, 235, 0.2);
      --family-text: #1e293b;
      --chip-bg: #eff6ff;
      --chip-border: rgba(59, 130, 246, 0.35);
      --chip-text: #1e40af;
      --context-bg: #f1f5ff;
      --context-border: rgba(59, 130, 246, 0.25);
      --context-text: #0f172a;
      --host: #1e40af;
      --btn-outline-bg: #ffffff;
      --btn-primary: #2563eb;
      --btn-primary-text: #ffffff;
      --btn-danger: #dc2626;
      --btn-warning-border: #38bdf8;
      --btn-warning-text: #0ea5e9;
      --btn-warning-bg: rgba(56, 189, 248, 0.15);
      --highlight-bg: #bfdbfe;
      --highlight-text: #1e40af;
    }
    :root[data-theme="dark"] {
      color-scheme: dark;
      --page-bg: radial-gradient(circle at top, rgba(37, 99, 235, 0.35) 0%, rgba(10, 16, 30, 0.95) 55%, #0b1220 100%);
      --card-bg: #0f172a;
      --card-border: rgba(96, 165, 250, 0.3);
      --card-shadow: 0 30px 80px rgba(2, 6, 23, 0.7);
      --card-glow: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0));
      --icon-bg: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
      --icon-shadow: 0 16px 30px rgba(2, 6, 23, 0.5);
      --icon-img-bg: rgba(15, 23, 42, 0.55);
      --brand: #93c5fd;
      --title: #bfdbfe;
      --subtitle: #93c5fd;
      --text: #e2e8f0;
      --family-bg: rgba(96, 165, 250, 0.15);
      --family-border: rgba(96, 165, 250, 0.35);
      --family-text: #e2e8f0;
      --chip-bg: rgba(59, 130, 246, 0.18);
      --chip-border: rgba(96, 165, 250, 0.35);
      --chip-text: #bfdbfe;
      --context-bg: #0b1220;
      --context-border: rgba(96, 165, 250, 0.25);
      --context-text: #e2e8f0;
      --host: #93c5fd;
      --btn-outline-bg: rgba(15, 23, 42, 0.4);
      --btn-primary: #3b82f6;
      --btn-primary-text: #ffffff;
      --btn-danger: #f87171;
      --btn-warning-border: #38bdf8;
      --btn-warning-text: #7dd3fc;
      --btn-warning-bg: rgba(56, 189, 248, 0.12);
      --highlight-bg: rgba(59, 130, 246, 0.35);
      --highlight-text: #bfdbfe;
    }
    html, body {
      height: 100%;
      width: 100%;
      margin: 0;
    }
    .clickfix-blocked {
      min-height: 100vh;
      height: 100vh;
      width: 100vw;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 16px;
      background: var(--page-bg);
      color: var(--text);
      font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
    }
    .clickfix-card {
      width: min(960px, 100%);
      background: var(--card-bg);
      border-radius: 32px;
      padding: clamp(28px, 4vw, 44px);
      box-shadow: var(--card-shadow);
      text-align: start;
      border: 1px solid var(--card-border);
      position: relative;
      overflow: hidden;
    }
    .clickfix-card::before {
      content: "";
      position: absolute;
      inset: 0;
      background: var(--card-glow);
      pointer-events: none;
    }
    .clickfix-header {
      display: flex;
      align-items: center;
      gap: 16px;
      position: relative;
      z-index: 1;
    }
    .clickfix-icon {
      width: 64px;
      height: 64px;
      border-radius: 20px;
      background: var(--icon-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: var(--icon-shadow);
    }
    .clickfix-icon img {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      object-fit: contain;
      background: var(--icon-img-bg);
      padding: 6px;
    }
    .clickfix-brand {
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.28em;
      text-transform: uppercase;
      color: var(--brand);
    }
    .clickfix-title {
      font-size: clamp(24px, 3vw, 34px);
      font-weight: 800;
      color: var(--title);
      margin-left: auto;
      line-height: 1.1;
    }
    .clickfix-subtitle {
      margin-top: 18px;
      font-size: 16px;
      font-weight: 600;
      color: var(--subtitle);
      position: relative;
      z-index: 1;
    }
    .clickfix-family-note {
      margin-top: 12px;
      padding: 10px 12px;
      border-radius: 12px;
      background: var(--family-bg);
      border: 1px dashed var(--family-border);
      color: var(--family-text);
      font-size: 13px;
      line-height: 1.4;
      position: relative;
      z-index: 1;
    }
    .clickfix-section-title {
      margin-top: 24px;
      font-size: 15px;
      font-weight: 700;
      color: var(--subtitle);
      position: relative;
      z-index: 1;
    }
    .clickfix-reason-list {
      margin: 12px 0 0;
      padding: 0;
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
      text-align: start;
      position: relative;
      z-index: 1;
    }
    .clickfix-reason-chip {
      padding: 12px 14px;
      border-radius: 14px;
      background: var(--chip-bg);
      border: 1px solid var(--chip-border);
      color: var(--chip-text);
      font-size: 14px;
      line-height: 1.4;
    }
    .clickfix-context {
      margin-top: 12px;
      padding: 12px 14px;
      border-radius: 16px;
      background: var(--context-bg);
      border: 1px solid var(--context-border);
      color: var(--context-text);
      font-size: 13px;
      line-height: 1.5;
      max-height: 320px;
      overflow: auto;
      width: 100%;
      text-align: start;
      white-space: pre-wrap;
      position: relative;
      z-index: 1;
    }
    .clickfix-host {
      margin-top: 10px;
      font-size: 13px;
      color: var(--host);
      position: relative;
      z-index: 1;
    }
    .clickfix-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 28px;
      position: relative;
      z-index: 1;
    }
    .clickfix-back-actions {
      display: flex;
      justify-content: center;
      margin-top: 14px;
      position: relative;
      z-index: 1;
    }
    .clickfix-btn {
      padding: 12px 20px;
      border-radius: 999px;
      font-weight: 700;
      cursor: pointer;
      font-size: 14px;
      border: none;
    }
    .clickfix-btn-outline {
      background: var(--btn-outline-bg);
      border: 2px solid;
      color: var(--title);
    }
    .clickfix-btn-solid.primary {
      background: var(--btn-primary);
      color: var(--btn-primary-text);
    }
    .clickfix-btn-outline.danger {
      border-color: var(--btn-danger);
      color: var(--btn-danger);
    }
    .clickfix-btn-solid.danger {
      background: var(--btn-danger);
      color: #fff;
    }
    .clickfix-btn-outline.warning {
      border-color: var(--btn-warning-border);
      color: var(--btn-warning-text);
      background: var(--btn-warning-bg);
    }
    .clickfix-highlight {
      background: var(--highlight-bg);
      color: var(--highlight-text);
      border-radius: 4px;
      padding: 0 2px;
    }
    .clickfix-btn:disabled {
      opacity: 0.6;
      cursor: default;
    }
    @media (max-width: 640px) {
      .clickfix-card { padding: 24px; text-align: start; }
      .clickfix-actions { gap: 8px; }
      .clickfix-btn { width: 100%; }
      .clickfix-header { flex-direction: column; align-items: flex-start; }
      .clickfix-title { margin-left: 0; }
    }
  `;
  head.appendChild(style);
  const body = document.createElement("body");
  body.appendChild(container);
  const existingHead = document.head;
  const existingBody = document.body;
  Array.from(html.childNodes).forEach((node) => {
    if (node.nodeType !== Node.ELEMENT_NODE) {
      node.remove();
      return;
    }
    const tag = node.tagName;
    if (tag !== "HEAD" && tag !== "BODY") {
      node.remove();
    }
  });
  if (existingHead && existingHead.parentNode) {
    existingHead.replaceWith(head);
  } else {
    html.appendChild(head);
  }
  if (existingBody && existingBody.parentNode) {
    existingBody.replaceWith(body);
  } else {
    html.appendChild(body);
  }
}

async function checkBlocklistAndBlock() {
  if (!isTopFrame) {
    return false;
  }
  await ensureLocaleReady();
  const currentHost = getHostname(window.location.href);
  if (!familySafeEnabled) {
    const allowOnce = sessionStorage.getItem("clickfix-allow-host");
    if (allowOnce && allowOnce === currentHost) {
      sessionStorage.removeItem("clickfix-allow-host");
      return false;
    }
    const sessionAllow = sessionStorage.getItem("clickfix-allow-session");
    if (sessionAllow) {
      try {
        const allowedHosts = JSON.parse(sessionAllow);
        if (Array.isArray(allowedHosts) && allowedHosts.includes(currentHost)) {
          return false;
        }
      } catch (error) {
        sessionStorage.removeItem("clickfix-allow-session");
      }
    }
  }
  const status = await getBlocklistStatus();
  if (status?.blocked) {
    const hostname = status.hostname || getHostname(window.location.href);
    safeSendMessage({
      type: "blocklisted",
      url: window.location.href,
      hostname,
      timestamp: Date.now()
    });
    try {
      window.stop();
    } catch (error) {
      // Ignore stop failures.
    }
    buildBlockedPage(hostname, tBlocked("blocklistReason"), [tBlocked("blocklistReason")], "", [], {
      familySafe: familySafeEnabled,
      theme: uiThemePreference
    });
    return true;
  }
  return false;
}

function normalizeSnippet(text) {
  return text.replace(/\s+/g, " ").trim();
}

function trimClipboardText(value) {
  if (!value) {
    return "";
  }
  const text = String(value);
  return text.length > CLIPBOARD_ANALYSIS_LIMIT ? text.slice(0, CLIPBOARD_ANALYSIS_LIMIT) : text;
}

function normalizeClipboardText(value) {
  const raw = trimClipboardText(value);
  const withoutInvisible = raw.replace(ZERO_WIDTH_REGEX, "");
  const withoutControl = withoutInvisible.replace(CONTROL_CHAR_REGEX, " ");
  const leadingWhitespaceMatch = withoutControl.match(/^\s+/);
  const leadingWhitespace = leadingWhitespaceMatch ? leadingWhitespaceMatch[0].length : 0;
  const cleaned = withoutControl.replace(/\s+/g, " ").trim();
  return { raw, cleaned, leadingWhitespace, normalized: withoutControl };
}

function isTrustedCodeHost(url) {
  const host = getHostname(url);
  if (!host) {
    return false;
  }
  return TRUSTED_CODE_HOSTS.some((entry) => host === entry || host.endsWith(`.${entry}`));
}

function isSelectionInCodeContext() {
  const selection = window.getSelection();
  const node = selection?.anchorNode || selection?.focusNode;
  const element = node && node.nodeType === 1 ? node : node?.parentElement;
  const active = document.activeElement;
  const target = element || active;
  if (!target) {
    return false;
  }
  if (target.closest && target.closest("pre, code, textarea, kbd, samp")) {
    return true;
  }
  if (active && (active.tagName === "TEXTAREA" || active.tagName === "INPUT")) {
    return true;
  }
  if (target.isContentEditable) {
    return true;
  }
  return false;
}

function computeEntropy(value) {
  if (!value) {
    return 0;
  }
  const counts = new Map();
  for (const char of value) {
    counts.set(char, (counts.get(char) || 0) + 1);
  }
  const length = value.length;
  if (!length) {
    return 0;
  }
  let entropy = 0;
  for (const count of counts.values()) {
    const ratio = count / length;
    entropy -= ratio * Math.log2(ratio);
  }
  return entropy;
}

function hasHighEntropy(value) {
  if (!value) {
    return false;
  }
  const tokens = value.split(/\s+/).filter((token) => token.length >= 32);
  return tokens.some((token) => computeEntropy(token) >= 4.2);
}

function extractBase64Candidates(text) {
  if (!text) {
    return [];
  }
  const candidates = new Set();
  const matches = text.match(BASE64_CHUNK_REGEX) || [];
  matches.forEach((value) => {
    const cleaned = value.replace(/=+$/, "");
    if (cleaned.length < 24 || cleaned.length % 4 === 1) {
      return;
    }
    candidates.add(value);
  });
  return [...candidates];
}

function decodeBase64Candidates(text) {
  const decoded = [];
  const candidates = extractBase64Candidates(text);
  candidates.forEach((value) => {
    try {
      const padded = value.length % 4 === 0 ? value : value + "=".repeat(4 - (value.length % 4));
      const result = atob(padded);
      if (result && /[\w\s]/.test(result)) {
        decoded.push(result);
      }
    } catch (error) {
      // Ignore invalid base64.
    }
  });
  return decoded;
}

function analyzeClipboardText(rawText, context) {
  const normalized = normalizeClipboardText(rawText);
  const cleaned = normalized.cleaned;
  if (!cleaned) {
    return {
      block: false,
      score: 0,
      normalizedText: "",
      features: {
        hasCommand: false,
        hasExecutionHint: false,
        hasUrl: false,
        hasBase64: false,
        hasHighEntropy: false,
        hasShellMeta: false,
        isLong: false,
        hasLeadingWhitespace: false,
        looksLikeCommand: false
      },
      context
    };
  }

  const decodedChunks = decodeBase64Candidates(cleaned);
  const combined = [cleaned, ...decodedChunks].join("\n");
  const hasCommand = COMMAND_REGEX.test(combined);
  const hasExecutionHint = SHELL_HINT_REGEX.test(combined);
  const hasUrl = URL_REGEX.test(combined);
  const hasShellMeta = SHELL_META_REGEX.test(combined);
  const base64Candidates = extractBase64Candidates(cleaned);
  const hasBase64 = base64Candidates.length > 0;
  const base64LooksBinary = base64Candidates.some((value) => value.startsWith("TVqQ") || value.startsWith("TVpQ"));
  const hasHighEntropyFlag = hasHighEntropy(combined);
  const isLong = cleaned.length > 280;
  const hasLeadingWhitespace = normalized.leadingWhitespace >= 6;
  const looksLikeCommand =
    /^[\w.-]+\s+[-/]/.test(cleaned) ||
    /^\s*(sudo\s+)?(powershell|cmd|bash|sh|zsh|curl|wget)\b/i.test(cleaned);

  let score = 0;
  if (hasCommand) {
    score += 4;
  }
  if (hasExecutionHint) {
    score += 3;
  }
  if (hasUrl && (hasCommand || hasExecutionHint)) {
    score += 2;
  }
  if (hasBase64) {
    score += 2;
  }
  if (base64LooksBinary) {
    score += 2;
  }
  if (hasHighEntropyFlag) {
    score += 1;
  }
  if (hasShellMeta) {
    score += 1;
  }
  if (isLong) {
    score += 1;
  }
  if (hasLeadingWhitespace) {
    score += 1;
  }
  if (looksLikeCommand) {
    score += 1;
  }

  let threshold = 6;
  if (context?.isTrustedHost) {
    threshold += 2;
  }
  if (context?.isCodeContext) {
    threshold += 2;
  }
  if (context?.isAllowlisted) {
    threshold += 3;
  }

  const strongBlock = hasCommand && (hasExecutionHint || hasUrl || hasBase64 || hasHighEntropyFlag);
  const strongObfuscation = hasBase64 && hasHighEntropyFlag && isLong;
  const block = strongBlock || strongObfuscation || score >= threshold;

  return {
    block,
    score,
    normalizedText: cleaned,
    features: {
      hasCommand,
      hasExecutionHint,
      hasUrl,
      hasBase64: hasBase64 || base64LooksBinary,
      hasHighEntropy: hasHighEntropyFlag,
      hasShellMeta,
      isLong,
      hasLeadingWhitespace,
      looksLikeCommand
    },
    context
  };
}

function shouldThrottleClipboardAlert(text) {
  const snippet = normalizeSnippet(text).slice(0, 200);
  const now = Date.now();
  if (lastClipboardThreat.text === snippet && now - lastClipboardThreat.timestamp < CLIPBOARD_ALERT_THROTTLE_MS) {
    return true;
  }
  lastClipboardThreat = { text: snippet, timestamp: now };
  return false;
}

function getActiveElementTag() {
  const active = document.activeElement;
  if (!active) {
    return "";
  }
  return active.tagName ? active.tagName.toLowerCase() : "";
}

function buildClipboardSource(event) {
  const target = event?.target;
  const elementTag = target?.tagName ? target.tagName.toLowerCase() : getActiveElementTag();
  const rawText = target?.innerText || target?.value || "";
  const elementText = rawText ? normalizeSnippet(String(rawText)).slice(0, 80) : "";
  return { elementTag, elementText };
}

function getClipboardContext() {
  return {
    isTrustedHost: isTrustedCodeHost(window.location.href),
    isCodeContext: isSelectionInCodeContext(),
    isAllowlisted: currentAllowlisted
  };
}

function updatePageContextFlags() {
  if (!document.documentElement) {
    return;
  }
  document.documentElement.dataset.clickfixTrustedHost = isTrustedCodeHost(window.location.href)
    ? "true"
    : "false";
  document.documentElement.dataset.clickfixAllowlisted = currentAllowlisted ? "true" : "false";
}

function scheduleIframeScan() {
  if (iframeScanPending) {
    return;
  }
  iframeScanPending = true;
  setTimeout(() => {
    iframeScanPending = false;
    scanIframes();
  }, 400);
}

function scanIframes() {
  const opaqueSources = [];
  const visitedDocs = new WeakSet();
  let totalCount = 0;
  let opaqueCount = 0;

  const collectOpaqueSource = (frame) => {
    if (frame?.src && opaqueSources.length < MAX_IFRAME_SOURCE_SAMPLES) {
      opaqueSources.push(frame.src.slice(0, 200));
    }
  };

  const scanDocumentFrames = (doc, depth) => {
    if (!doc || visitedDocs.has(doc) || depth > MAX_IFRAME_SCAN_DEPTH) {
      return;
    }
    visitedDocs.add(doc);
    const frames = Array.from(doc.querySelectorAll("iframe, frame"));
    frames.forEach((frame) => {
      totalCount += 1;
      try {
        const childDoc = frame.contentDocument;
        if (childDoc) {
          scanDocumentFrames(childDoc, depth + 1);
        } else {
          opaqueCount += 1;
          collectOpaqueSource(frame);
        }
      } catch (error) {
        opaqueCount += 1;
        collectOpaqueSource(frame);
      }
    });
  };

  scanDocumentFrames(document, 0);
  iframeSnapshot = { total: totalCount, opaque: opaqueCount, opaqueSources };
}

function handleUrlChange() {
  const current = window.location.href;
  if (current === lastUrlSnapshot) {
    return;
  }
  lastUrlSnapshot = current;
  lastSelectionText = "";
  lastClipboardSnapshot = "";
  currentAllowlisted = false;
  updatePageContextFlags();
  scheduleIframeScan();
  updateBlockAllClipboardState();
  if (isTopFrame) {
    checkBlocklistAndBlock();
  }
}

function reportClipboardThreat({ text, analysis, method, source }) {
  if (!text) {
    return;
  }
  if (shouldThrottleClipboardAlert(text)) {
    return;
  }
  if (!muteDetectionNotifications) {
    showBanner(t("clipboardThreatWarning"));
  }
  const snippet = normalizeSnippet(text).slice(0, 200);
  const features = analysis?.features || {};
  safeSendMessage({
    type: "clipboardIncident",
    url: window.location.href,
    timestamp: Date.now(),
    previousUrl: getReferrerUrl(),
    method: method || "copy",
    blocked: true,
    snippet,
    textLength: String(text).length,
    analysis: {
      commandMatch: Boolean(features.hasCommand),
      shellHint: Boolean(features.hasExecutionHint),
      evasionHint: Boolean(features.hasBase64 || features.hasHighEntropy),
      base64: Boolean(features.hasBase64),
      highEntropy: Boolean(features.hasHighEntropy),
      url: Boolean(features.hasUrl),
      score: analysis?.score || 0
    },
    source: {
      frameType: isTopFrame ? "top" : "iframe",
      method: method || "copy",
      elementTag: source?.elementTag || "",
      elementText: source?.elementText || "",
      isCodeContext: Boolean(analysis?.context?.isCodeContext),
      isTrustedHost: Boolean(analysis?.context?.isTrustedHost),
      isAllowlisted: Boolean(analysis?.context?.isAllowlisted),
      opaqueIframes: iframeSnapshot.opaque,
      totalIframes: iframeSnapshot.total,
      opaqueSources: iframeSnapshot.opaqueSources || []
    },
    detectedContent: analysis?.normalizedText || snippet
  });
}


function scheduleClipboardPostCheck(context, source, method) {
  if (clipboardPostCheckTimer) {
    clearTimeout(clipboardPostCheckTimer);
  }
  clipboardPostCheckTimer = setTimeout(async () => {
    clipboardPostCheckTimer = null;
    const clipboard = await readClipboardText();
    if (!clipboard.available || !clipboard.text) {
      return;
    }
    const analysis = analyzeClipboardText(clipboard.text, context);
    if (!analysis.block) {
      return;
    }
    await writeClipboardText("");
    reportClipboardThreat({
      text: clipboard.text,
      analysis,
      method: method ? `${method}-post` : "clipboard",
      source
    });
  }, 120);
}

function mergeClipboardAnalysis(baseAnalysis, incoming) {
  if (!incoming) {
    return baseAnalysis;
  }
  const features = baseAnalysis.features || {};
  features.hasCommand = features.hasCommand || Boolean(incoming.commandMatch);
  features.hasExecutionHint = features.hasExecutionHint || Boolean(incoming.shellHint || incoming.executionHint);
  features.hasBase64 = features.hasBase64 || Boolean(incoming.base64);
  features.hasHighEntropy = features.hasHighEntropy || Boolean(incoming.highEntropy);
  features.hasUrl = features.hasUrl || Boolean(incoming.url);
  features.hasShellMeta = features.hasShellMeta || Boolean(incoming.shellMeta);
  features.isLong = features.isLong || Boolean(incoming.isLong);
  features.hasLeadingWhitespace = features.hasLeadingWhitespace || Boolean(incoming.leadingWhitespace);
  features.looksLikeCommand = features.looksLikeCommand || Boolean(incoming.looksLikeCommand);
  return {
    ...baseAnalysis,
    score: Math.max(baseAnalysis.score || 0, incoming.score || 0),
    features
  };
}

function handleClipboardThreatMessage(data) {
  const text = String(data?.text || "");
  if (!text) {
    return;
  }
  const context = getClipboardContext();
  let analysis = analyzeClipboardText(text, context);
  analysis = mergeClipboardAnalysis(analysis, data?.analysis);
  analysis.block = true;
  reportClipboardThreat({
    text,
    analysis,
    method: data?.method || "script",
    source: buildClipboardSource()
  });
}

function buildSnippet(text, index, matchText) {
  const start = Math.max(0, index - 40);
  const end = Math.min(text.length, index + matchText.length + 40);
  return normalizeSnippet(text.slice(start, end));
}

function findMatchSnippet(regex, text) {
  const match = regex.exec(text);
  if (!match) {
    return "";
  }
  return buildSnippet(text, match.index ?? 0, match[0]);
}

function getSelectionText() {
  const selection = window.getSelection();
  if (!selection) {
    return "";
  }
  return selection.toString().slice(0, MAX_SELECTION_LENGTH);
}

async function readClipboardText() {
  try {
    if (!navigator.clipboard || !navigator.clipboard.readText) {
      return { text: "", available: false };
    }
    const policy = document.permissionsPolicy || document.featurePolicy;
    if (policy?.allowsFeature && !policy.allowsFeature("clipboard-read")) {
      return { text: "", available: false };
    }
    const text = await navigator.clipboard.readText();
    return { text: text.slice(0, CLIPBOARD_ANALYSIS_LIMIT), available: true };
  } catch (error) {
    return { text: "", available: false };
  }
}

async function writeClipboardText(text) {
  try {
    if (!navigator.clipboard || !navigator.clipboard.writeText) {
      return false;
    }
    const policy = document.permissionsPolicy || document.featurePolicy;
    if (policy?.allowsFeature && !policy.allowsFeature("clipboard-write")) {
      return false;
    }
    await navigator.clipboard.writeText(text);
    lastClipboardSnapshot = text;
    return true;
  } catch (error) {
    return false;
  }
}

function trimScanValue(value) {
  if (!value) {
    return "";
  }
  return value.length > MAX_SCAN_LENGTH ? value.slice(0, MAX_SCAN_LENGTH) : value;
}

function getScanSnapshot() {
  const now = Date.now();
  if (now - lastScanSnapshot.timestamp < 250 && lastScanSnapshot.text) {
    return lastScanSnapshot;
  }
  const root = document.documentElement;
  const textContent = root?.textContent || "";
  const htmlContent = root?.innerHTML || "";
  const pageTitle = document.title || "";
  let pageUrl = "";
  let pagePath = "";
  try {
    const url = new URL(window.location.href || "");
    pageUrl = decodeURIComponent(url.href);
    pagePath = decodeURIComponent(url.pathname || "");
  } catch (error) {
    pageUrl = window.location.href || "";
  }
  const inlineAssets = Array.from(document.querySelectorAll("script,style"))
    .map((node) => node.textContent || "")
    .filter(Boolean)
    .join("\n");
  const combinedText = [textContent, pageTitle, pageUrl, pagePath, inlineAssets]
    .filter(Boolean)
    .join("\n");
  lastScanSnapshot = {
    text: trimScanValue(combinedText),
    html: trimScanValue(htmlContent),
    timestamp: now
  };
  return lastScanSnapshot;
}

function scanForWinR() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_WIN_R_REGEX, snapshot.text);
}

function scanForWinX() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_WIN_X_REGEX, snapshot.text);
}

function scanForFakeError() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_FAKE_ERROR_REGEX, snapshot.text);
}

function scanForFixAction() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_FIX_ACTION_REGEX, snapshot.text);
}

function scanForFakeCaptcha() {
  const snapshot = getScanSnapshot();
  return (
    findMatchSnippet(CLICKFIX_CAPTCHA_REGEX, snapshot.text) ||
    findMatchSnippet(CLICKFIX_RECAPTCHA_ID_REGEX, snapshot.text)
  );
}

function notifyWinRDetected() {
  const snippet = scanForWinR();
  if (snippet && !winRDetected) {
    winRDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "winr",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("winr", snippet);
  }
}

function notifyWinXDetected() {
  const snippet = scanForWinX();
  if (snippet && !winXDetected) {
    winXDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "winx",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("winx", snippet);
  }
}

function notifyCaptchaDetected() {
  const snippet = scanForFakeCaptcha();
  if (snippet && !captchaDetected) {
    captchaDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "captcha",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("captcha", snippet);
  }
}

function notifyBrowserErrorDetected() {
  const snippet = scanForFakeError();
  if (snippet && !browserErrorDetected) {
    browserErrorDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "browser-error",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("browser-error", snippet);
  }
}

function notifyFixActionDetected() {
  const snippet = scanForFixAction();
  if (snippet && !fixActionDetected) {
    fixActionDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "fix-action",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("fix-action", snippet);
  }
}

function scanForConsolePaste() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_CONSOLE_REGEX, snapshot.text);
}

function notifyConsoleDetected() {
  const snippet = scanForConsolePaste();
  if (snippet && !consoleDetected) {
    consoleDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "console",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("console", snippet);
  }
}

function scanForShellPaste() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_SHELL_PASTE_REGEX, snapshot.text);
}

function notifyShellDetected() {
  const snippet = scanForShellPaste();
  if (snippet && !shellDetected) {
    shellDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "shell",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("shell", snippet);
  }
}

function scanForPasteSequence() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_PASTE_SEQUENCE_REGEX, snapshot.text);
}

function notifyPasteSequenceDetected() {
  const snippet = scanForPasteSequence();
  if (snippet && !pasteSequenceDetected) {
    pasteSequenceDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "paste-sequence",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("paste-sequence", snippet);
  }
}

function scanForFileExplorer() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_FILE_EXPLORER_REGEX, snapshot.text);
}

function scanForCopyTrigger() {
  const snapshot = getScanSnapshot();
  return findMatchSnippet(CLICKFIX_COPY_TRIGGER_REGEX, snapshot.html);
}

function scanForCommandPatterns() {
  const snapshot = getScanSnapshot();
  return (
    findMatchSnippet(COMMAND_REGEX, snapshot.text) ||
    findMatchSnippet(SHELL_HINT_REGEX, snapshot.text)
  );
}

function notifyCommandDetected() {
  const snippet = scanForCommandPatterns();
  if (snippet && !commandDetected) {
    commandDetected = true;
    sendPageAlert("command", snippet);
  }
}

function notifyCopyTriggerDetected() {
  const snippet = scanForCopyTrigger();
  if (snippet && !copyTriggerDetected) {
    copyTriggerDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "copy-trigger",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("copy-trigger", snippet);
  }
}

async function renderBlockedPage(hostname) {
  await ensureLocaleReady();
  buildBlockedPage(hostname || window.location.hostname, tBlocked("blockedReasonReported"), [], "", [], {
    familySafe: familySafeEnabled,
    theme: uiThemePreference
  });
}

function checkReportedSite() {
  return new Promise((resolve) => {
    safeSendMessage(
      {
        type: "checkBlocklist",
        url: window.location.href
      },
      (response) => {
        if (response?.blocked) {
          renderBlockedPage(response.hostname);
          resolve(true);
          return;
        }
        resolve(false);
      }
    );
  });
}

function notifyFileExplorerDetected() {
  const snippet = scanForFileExplorer();
  if (snippet && !fileExplorerDetected) {
    fileExplorerDetected = true;
    safeSendMessage({
      type: "pageHint",
      hint: "file-explorer",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("file-explorer", snippet);
  }
}

function sendClipboardEvent(payload) {
  safeSendMessage({
    type: "clipboardEvent",
    url: window.location.href,
    timestamp: Date.now(),
    previousUrl: getReferrerUrl(),
    ...payload
  });
}

async function monitorClipboardChanges() {
  if (clipboardWatchRunning) {
    return;
  }
  clipboardWatchRunning = true;
  try {
    const clipboard = await readClipboardText();
    if (!clipboard.available) {
      return;
    }
    if (clipboard.text && clipboard.text !== lastClipboardSnapshot) {
      lastClipboardSnapshot = clipboard.text;
      sendClipboardEvent({
        eventType: "clipboard-watch",
        selectionText: "",
        clipboardText: clipboard.text,
        clipboardAvailable: true
      });
    }
  } finally {
    clipboardWatchRunning = false;
  }
}

function handleCopyCut(eventType, event) {
  const selectionText = getSelectionText();
  lastSelectionText = selectionText;
  let clipboardText = "";
  if (event?.clipboardData?.getData) {
    try {
      clipboardText = event.clipboardData.getData("text/plain");
    } catch (error) {
      clipboardText = "";
    }
  }
  const textToAnalyze = clipboardText || selectionText;
  const context = getClipboardContext();
  const analysis = analyzeClipboardText(textToAnalyze, context);
  const source = buildClipboardSource(event);

  if (analysis.block) {
    event?.preventDefault();
    event?.stopImmediatePropagation();
    if (event?.clipboardData?.setData) {
      try {
        event.clipboardData.setData("text/plain", "");
      } catch (error) {
        // Ignore clipboard reset errors.
      }
    }
    reportClipboardThreat({
      text: textToAnalyze,
      analysis,
      method: eventType,
      source
    });
    return;
  }

  if (!clipboardText && selectionText) {
    scheduleClipboardPostCheck(context, source, eventType);
  }
}

async function handlePaste() {
  const selectionText = getSelectionText();
  const clipboard = await readClipboardText();

  sendClipboardEvent({
    eventType: "paste",
    selectionText,
    clipboardText: clipboard.text,
    clipboardAvailable: clipboard.available
  });
}

function handleSelectionChange() {
  const selectionText = getSelectionText();
  if (selectionText && selectionText !== lastSelectionText) {
    lastSelectionText = selectionText;
    sendClipboardEvent({
      eventType: "selectionchange",
      selectionText,
      clipboardText: "",
      clipboardAvailable: false
    });
  }
}

function startMonitoring() {
  document.addEventListener("copy", (event) => handleCopyCut("copy", event), true);
  document.addEventListener("cut", (event) => handleCopyCut("cut", event), true);

  const initMonitoring = () => {
    scheduleIframeScan();
    handleUrlChange();
    const observer = new MutationObserver(() => {
      scheduleIframeScan();
    });
    if (document.body) {
      observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true
      });
    }
    setInterval(handleUrlChange, 1000);
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMonitoring);
  } else {
    initMonitoring();
  }
}

(async () => {
  await ensureLocaleReady();
  await updateFamilySafeState();
  await updateThemeState();
  await updateNotificationPreference();
  await updateBlockAllClipboardState();
  await checkBlocklistAndBlock();
  startMonitoring();
})();

chrome.runtime.onMessage.addListener((message) => {
  if (message?.type === "replaceClipboard") {
    if (!isTopFrame) {
      return;
    }
    writeClipboardText(message.text ?? "");
    return;
  }
  if (message?.type === "restoreClipboard") {
    if (!isTopFrame) {
      return;
    }
    writeClipboardText(message.text ?? "");
    return;
  }
  if (message?.type === "blockPage") {
    if (!isTopFrame) {
      return;
    }
    const currentHost = getHostname(window.location.href);
    if (!familySafeEnabled) {
      const allowOnce = sessionStorage.getItem("clickfix-allow-host");
      if (allowOnce && allowOnce === currentHost) {
        sessionStorage.removeItem("clickfix-allow-host");
        return;
      }
      const sessionAllow = sessionStorage.getItem("clickfix-allow-session");
      if (sessionAllow) {
        try {
          const allowedHosts = JSON.parse(sessionAllow);
          if (Array.isArray(allowedHosts) && allowedHosts.includes(currentHost)) {
            return;
          }
        } catch (error) {
          sessionStorage.removeItem("clickfix-allow-session");
        }
      }
    }
    ensureLocaleReady().then(() => {
      const localizedReasons = buildLocalizedReasons(
        message.reasonEntries,
        message.reasons || message.reasonsEs || []
      );
      const localizedReasonText = localizedReasons.length
        ? localizedReasons.join(" ")
        : message.reason || message.reasonEs || "";
      buildBlockedPage(
        message.hostname || getHostname(window.location.href),
        localizedReasonText,
        localizedReasons,
        message.contextText || "",
        message.snippets || [],
        { familySafe: familySafeEnabled, theme: uiThemePreference }
      );
    });
    return;
  }
  if (message?.type === "showBanner") {
    if (!muteDetectionNotifications) {
      showBanner(message.text);
    }
  }
});
