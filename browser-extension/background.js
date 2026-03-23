const SCORE_RULE_DEFINITIONS = {
  signals: [
    { flag: "commandMatch", key: "scoreSignalCommandMatch", defaultPoints: 20 },
    { flag: "shellHint", key: "scoreSignalShellHint", defaultPoints: 14 },
    { flag: "evasionHint", key: "scoreSignalEvasionHint", defaultPoints: 12 },
    { flag: "mismatch", key: "scoreSignalMismatch", defaultPoints: 8 },
    { flag: "clipboardWarning", key: "scoreSignalClipboardWarning", defaultPoints: 6 },
    { flag: "winRHint", key: "scoreSignalWinR", defaultPoints: 6 },
    { flag: "winXHint", key: "scoreSignalWinX", defaultPoints: 4 },
    { flag: "winXTerminalHint", key: "scoreSignalWinXI", defaultPoints: 6 },
    { flag: "pasteSequenceHint", key: "scoreSignalPasteSequence", defaultPoints: 6 },
    { flag: "consoleHint", key: "scoreSignalConsole", defaultPoints: 5 },
    { flag: "fileExplorerHint", key: "scoreSignalFileExplorer", defaultPoints: 4 },
    { flag: "copyTriggerHint", key: "scoreSignalCopyTrigger", defaultPoints: 4 },
    { flag: "browserErrorHint", key: "scoreSignalBrowserError", defaultPoints: 4 },
    { flag: "fixActionHint", key: "scoreSignalFixAction", defaultPoints: 4 },
    { flag: "captchaHint", key: "scoreSignalCaptcha", defaultPoints: 3 }
  ],
  clipboard: [
    { flag: "hasCommand", key: "scoreClipboardCommand", defaultPoints: 22 },
    { flag: "hasExecutionHint", key: "scoreClipboardExecutionHint", defaultPoints: 18 },
    { flag: "hasUrl", key: "scoreClipboardUrl", defaultPoints: 12 },
    { flag: "hasBase64", key: "scoreClipboardBase64", defaultPoints: 12 },
    { flag: "hasHighEntropy", key: "scoreClipboardHighEntropy", defaultPoints: 10 },
    { flag: "hasShellMeta", key: "scoreClipboardShellMeta", defaultPoints: 6 },
    { flag: "isLong", key: "scoreClipboardLong", defaultPoints: 5 },
    { flag: "hasLeadingWhitespace", key: "scoreClipboardLeadingWhitespace", defaultPoints: 5 },
    { flag: "looksLikeCommand", key: "scoreClipboardLooksLikeCommand", defaultPoints: 10 }
  ],
  context: [
    { flag: "isAllowlisted", key: "scoreContextAllowlisted", defaultPoints: -25 },
    { flag: "isTrustedHost", key: "scoreContextTrustedHost", defaultPoints: -15 },
    { flag: "isCodeContext", key: "scoreContextCodeContext", defaultPoints: -10 },
    { flag: "isIframe", key: "scoreContextIframe", defaultPoints: 5 },
    { flag: "opaqueIframes", key: "scoreContextOpaqueIframes", defaultPoints: 5 },
    { flag: "opaqueIframesHigh", key: "scoreContextOpaqueIframes", defaultPoints: 10 }
  ]
};

function buildDefaultScoreRules() {
  const defaults = {};
  Object.entries(SCORE_RULE_DEFINITIONS).forEach(([group, rules]) => {
    defaults[group] = {};
    rules.forEach((rule) => {
      defaults[group][rule.flag] = rule.defaultPoints;
    });
  });
  return defaults;
}

const DEFAULT_SCORE_CONFIG = {
  weights: {
    signals: 0.5,
    clipboard: 0.35,
    context: 0.15
  },
  contextBaseScore: 50,
  rules: buildDefaultScoreRules()
};

let cachedScoreConfig = DEFAULT_SCORE_CONFIG;

const DEFAULT_SETTINGS = {
  enabled: true,
  blockAllClipboard: true,
  blockUnsafeDownloads: true,
  shadowAiMonitoring: true,
  shadowAiWarnUser: false,
  runtimeThreatScoring: true,
  apiLicenseKey: "LIC-6CD9EE-8EDFC1-6ACB22-3DB8C5",
  apiAccessToken: "",
  apiRefreshToken: "",
  apiAccessTokenExpiresAt: 0,
  apiTokenScope: "",
  apiTokenTier: "basic",
  whitelist: [],
  allowlist: [],
  history: [],
  blocklist: [],
  blocklistSources: [],
  allowlistSources: [],
  uiTheme: "system",
  familySafe: false,
  muteDetectionNotifications: false,
  saveClipboardBackup: true,
  sendCountry: true,
  detectionScreenshotCapture: false,
  reportQueue: [],
  alertCount: 0,
  blockCount: 0,
  blocklistUpdatedAt: 0,
  allowlistUpdatedAt: 0,
  scoreConfig: DEFAULT_SCORE_CONFIG,
  scoreConfigManagedBy: "local",
  scoreConfigServerUpdatedAt: 0,
  alertMinSeverity: "green",
  extensionMessageSeenIds: []
};

const CLICKFIX_DEPLOY_ORIGIN = "https://clickfix.jordiserrano.me";
const CLICKFIX_DEPLOY_BASE_PATH = "";

function normalizeDeployBasePath(path) {
  const cleaned = String(path || "").trim().replace(/^\/+|\/+$/g, "");
  return cleaned ? `/${cleaned}` : "";
}

function buildDeployUrl(relativePath) {
  const origin = String(CLICKFIX_DEPLOY_ORIGIN || "").trim().replace(/\/+$/g, "");
  const normalizedPath = normalizeDeployBasePath(CLICKFIX_DEPLOY_BASE_PATH);
  const tail = String(relativePath || "").trim().replace(/^\/+/g, "");
  return `${origin}${normalizedPath}/${tail}`;
}

function deployTrustedHost(origin) {
  try {
    return new URL(origin).hostname;
  } catch (error) {
    return "clickfix.jordiserrano.me";
  }
}

const CLICKFIX_BLOCKLIST_URL = buildDeployUrl("clickfixlist");
const CLICKFIX_ALLOWLIST_URL = buildDeployUrl("clickfixallowlist");
const CLICKFIX_REPORT_URL = buildDeployUrl("clickfix-report.php");
const CLICKFIX_SCORE_CONFIG_URL = buildDeployUrl("api/score-config.php");
const CLICKFIX_API_TOKEN_URL = buildDeployUrl("api/token.php");
const CLICKFIX_API_REFRESH_URL = buildDeployUrl("api/refresh.php");
const CLICKFIX_PREMIUM_SCORE_CONFIG_URL = buildDeployUrl("api/premium/score-config.php");
const CLICKFIX_API_MESSAGES_URL = buildDeployUrl("api/messages.php");
const TRUSTED_REPORT_HOST = deployTrustedHost(CLICKFIX_DEPLOY_ORIGIN);
const TRUSTED_REPORT_PATH_PREFIX = `${normalizeDeployBasePath(CLICKFIX_DEPLOY_BASE_PATH) || ""}/`;
const SCORE_CONFIG_PUBLIC_KEY_PEM = `-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAyKByF/9SMi0TaiYK7Z7z
A1JzNfBu9gDX1L9GgedSNfHX1vS1PdxS8rFnA4Q9JEfsa3inU92+iwmi38eATvxx
K46YNtinlny4sJXYOdO1DpvaimnuosDPmKYvX2DSiLXzfIYu9c3+S0hFkkumI5Sy
khUy+KU3GnjE6Jt+KdqbRA1dIazR2x6Jh4QovWOVsmkG/X/FvdN4c+dLerrnYT3x
QiTyO4zIw5llapcaOaoUcbxx6JMSqDo2uhHMrH0LlFfUIJ5x1Tjec3XH/QkM0IGi
2IfX426GUROWU8ctHOX70U8tvfdgvLZmbECMM6zx3fQik/3cvkie+gi4CwUNmk1k
KxT/2LY0OBsC5Hwqh8YPDipltFiQFzuYkb+7gFTYHxYwczmaNnr+SivESt+UR+RQ
0zXsJjxVe6p5yyg5xt5VplbjDW2EMVycdBBOdISpbOXCEBlthOS0jvDAFoG9PEkt
xuGPPAWKHsZNru1+dN/fqMlHQARa+/9XFutNGUVy5SG7AgMBAAE=
-----END PUBLIC KEY-----`;
const DOWNLOAD_DANGEROUS_EXTENSIONS = new Set([
  "exe",
  "msi",
  "msix",
  "scr",
  "com",
  "pif",
  "lnk",
  "bat",
  "cmd",
  "ps1",
  "vbs",
  "js",
  "jar",
  "hta",
  "dll"
]);
const DOWNLOAD_SCRIPT_EXTENSIONS = new Set(["bat", "cmd", "ps1", "vbs", "js", "hta"]);
const DOWNLOAD_DOUBLE_EXTENSION_REGEX =
  /\.(txt|pdf|docx?|xlsx?|pptx?|jpg|jpeg|png|gif|webp|zip|rar|7z)\.(exe|msi|bat|cmd|ps1|js|vbs|scr|com|jar|lnk)$/i;
const TRUSTED_DOWNLOAD_HOSTS = [
  "microsoft.com",
  "github.com",
  "google.com",
  "apple.com",
  "mozilla.org",
  "adobe.com",
  "docker.com",
  "nodejs.org",
  "python.org",
  "jetbrains.com",
  "cloudflare.com"
];
const SHADOW_AI_HOSTS = [
  "chat.openai.com",
  "chatgpt.com",
  "claude.ai",
  "gemini.google.com",
  "copilot.microsoft.com",
  "poe.com",
  "perplexity.ai",
  "you.com",
  "mistral.ai"
];
const CLICKFIX_DISABLED_HOSTS = ["jordiserrano.me", "any.run"];
const BRAND_REPUTATION_RULES = [
  { name: "microsoft", hosts: ["microsoft.com", "live.com", "office.com", "outlook.com"] },
  { name: "google", hosts: ["google.com", "youtube.com", "gmail.com"] },
  { name: "apple", hosts: ["apple.com", "icloud.com"] },
  { name: "amazon", hosts: ["amazon.com", "aws.amazon.com"] },
  { name: "paypal", hosts: ["paypal.com"] },
  { name: "meta", hosts: ["facebook.com", "instagram.com", "whatsapp.com"] },
  { name: "github", hosts: ["github.com"] },
  { name: "cloudflare", hosts: ["cloudflare.com"] }
];
const LIST_REFRESH_MINUTES = 3;
const REPORT_FLUSH_MINUTES = 5;
const PAGE_ALERT_DEBOUNCE_MS = 900;
const REPORT_DEDUPE_WINDOW_MS = 15 * 60 * 1000;
const REPORT_QUEUE_LIMIT = 300;
const CLIPBOARD_BACKUP_LIMIT = 50;
const BEFORE_CAPTURE_MAX_AGE_MS = 20 * 60 * 1000;
const BEFORE_CAPTURE_REFRESH_MS = 4000;
// Internal distribution marker; update this with the Chrome Web Store ID for production.
const WEBSTORE_EXTENSION_ID = "nmldafmgfcfopjoigbmmlmcnininifaa";
const DEFAULT_EXTENSION_DISTRIBUTION = "other";
let cachedClientId = "";
const beforeCaptureByTab = new Map();
const beforeCaptureInFlight = new Set();
const beforeCapturePromiseByTab = new Map();
const TAB_SCRIPT_BLOCK_RULE_ID_BASE = 1_000_000;

const COMMAND_REGEX =
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|bash|sh|zsh|curl|wget|rundll32|regsvr32|msbuild|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic|explorer(\.exe)?|reg\s+add|net\.exe\s+use|net\s+use|p[\s^`]*o[\s^`]*w[\s^`]*e[\s^`]*r[\s^`]*s[\s^`]*h[\s^`]*e[\s^`]*l[\s^`]*l|c[\s^`]*m[\s^`]*d|n[\s^`]*e[\s^`]*t[\s^`]*\s+u[\s^`]*s[\s^`]*e)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|invoke-restmethod|\biwr\b|\birm\b|curl\s+|wget\s+|downloadstring|frombase64string|start-bitstransfer|add-mppreference|invoke-expression|\biex\b|\biex\s*\(|encodedcommand|\-enc\b|\-encodedcommand\b|\-e\b|powershell\s+\-|cmd\s+\/c|bash\s+\-c|sh\s+\-c|rundll32\s+[^\s,]+,[^\s]+|regsvr32\s+\/i|certutil\s+\-urlcache|bitsadmin\s+\/transfer|net\.exe\s+use|net\s+use|net\s+use\s+\\\\)/i;
const EVASION_REGEXES = [
  /\\x[0-9a-f]{2}/i,
  /\\u[0-9a-f]{4}/i,
  /%[0-9a-f]{2}/i,
  /[\^`]{2,}/,
  /[A-Za-z0-9+/]{80,}={0,2}/
];
const ZERO_WIDTH_REGEX = /[\u200B-\u200D\u2060\uFEFF]/g;
const CONTROL_CHAR_REGEX = /[\u0000-\u001F\u007F]/g;
const DASH_REGEX = /[\u2010-\u2015\u2212\uFE63\uFF0D]/g;
const CLIPBOARD_SNIPPET_LIMIT = 160;
const CLIPBOARD_THROTTLE_MS = 30000;
const BLOCKLIST_CACHE_MS = 10 * 60 * 1000;
const FULL_CONTEXT_LIMIT = 40000;
const DETECTION_PROGRESS_WINDOW_MS = 10 * 60 * 1000;
const DETECTION_PROGRESS_MAX_BONUS = 25;
const ALERT_SEVERITY_ORDER = {
  green: 0,
  yellow: 1,
  orange: 2,
  red: 3
};

function tabScriptBlockRuleIds(tabId) {
  const normalizedTabId = Number(tabId);
  if (!Number.isInteger(normalizedTabId) || normalizedTabId < 0) {
    return [];
  }
  const base = TAB_SCRIPT_BLOCK_RULE_ID_BASE + normalizedTabId * 2;
  return [base, base + 1];
}

async function setTabScriptBlocking(tabId, hostname, enabled) {
  if (!chrome?.declarativeNetRequest?.updateSessionRules) {
    return false;
  }
  const ruleIds = tabScriptBlockRuleIds(tabId);
  if (ruleIds.length !== 2) {
    return false;
  }
  const [scriptRuleId, cspRuleId] = ruleIds;
  const updates = { removeRuleIds: [scriptRuleId, cspRuleId] };
  if (!enabled) {
    try {
      await chrome.declarativeNetRequest.updateSessionRules(updates);
      return true;
    } catch (error) {
      return false;
    }
  }
  const normalizedHost = normalizeHostname(String(hostname || ""));
  if (!normalizedHost) {
    try {
      await chrome.declarativeNetRequest.updateSessionRules(updates);
      return false;
    } catch (error) {
      return false;
    }
  }
  updates.addRules = [
    {
      id: scriptRuleId,
      priority: 1,
      action: { type: "block" },
      condition: {
        tabIds: [Number(tabId)],
        resourceTypes: ["script"],
        initiatorDomains: [normalizedHost]
      }
    },
    {
      id: cspRuleId,
      priority: 1,
      action: {
        type: "modifyHeaders",
        responseHeaders: [
          {
            header: "content-security-policy",
            operation: "set",
            value: "default-src 'self' 'unsafe-inline' data: blob:; script-src 'none'; object-src 'none'; base-uri 'none'"
          }
        ]
      },
      condition: {
        tabIds: [Number(tabId)],
        resourceTypes: ["main_frame"],
        requestDomains: [normalizedHost]
      }
    }
  ];
  try {
    await chrome.declarativeNetRequest.updateSessionRules(updates);
    return true;
  } catch (error) {
    return false;
  }
}

function normalizeAlertMinSeverity(value) {
  const normalized = String(value || "").toLowerCase();
  if (Object.prototype.hasOwnProperty.call(ALERT_SEVERITY_ORDER, normalized)) {
    return normalized;
  }
  return "green";
}

function scoreToAlertSeverity(score) {
  const numeric = Number(score);
  if (!Number.isFinite(numeric)) {
    return "green";
  }
  if (numeric > 40) {
    return "red";
  }
  if (numeric >= 30) {
    return "orange";
  }
  if (numeric > 15) {
    return "yellow";
  }
  return "green";
}

function shouldSuppressByAlertSeverity(score, minimumSeverity) {
  const minSeverity = normalizeAlertMinSeverity(minimumSeverity);
  const scoreSeverity = scoreToAlertSeverity(score);
  return ALERT_SEVERITY_ORDER[scoreSeverity] < ALERT_SEVERITY_ORDER[minSeverity];
}

function resolveInstallChannel(installType, installSource, fallbackChannel = "other") {
  const type = String(installType || "").toLowerCase();
  const source = String(installSource || "").toLowerCase();
  if (source.includes("webstore") || source.includes("chrome_webstore")) {
    return "chrome_webstore";
  }
  if (type === "development") {
    return "development";
  }
  if (type === "sideload" || source === "local") {
    return "sideload";
  }
  if (type === "normal" && source === "policy") {
    return "policy";
  }
  if (type === "normal") {
    return "normal";
  }
  return fallbackChannel || "other";
}

function resolveExtensionDistribution(installType, installSource) {
  const runtimeId = chrome?.runtime?.id || "";
  if (runtimeId && runtimeId === WEBSTORE_EXTENSION_ID) {
    return "chrome_store";
  }
  const type = String(installType || "").toLowerCase();
  const source = String(installSource || "").toLowerCase();
  if (type === "development" || type === "sideload" || source === "local") {
    return "github";
  }
  if (source.includes("webstore") || source.includes("chrome_webstore")) {
    return "chrome_store";
  }
  return DEFAULT_EXTENSION_DISTRIBUTION;
}

async function getInstallInfo() {
  const fallbackChannel =
    chrome?.runtime?.id === WEBSTORE_EXTENSION_ID ? "chrome_webstore" : "other";
  if (!chrome?.management?.getSelf) {
    return {
      installType: "",
      installSource: "",
      installChannel: fallbackChannel,
      extensionDistribution: resolveExtensionDistribution("", "")
    };
  }
  return new Promise((resolve) => {
    chrome.management.getSelf((info) => {
      if (chrome.runtime.lastError || !info) {
        resolve({
          installType: "",
          installSource: "",
          installChannel: fallbackChannel,
          extensionDistribution: resolveExtensionDistribution("", "")
        });
        return;
      }
      const installType = info.installType || "";
      const installSource = info.installSource || "";
      resolve({
        installType,
        installSource,
        installChannel: resolveInstallChannel(installType, installSource, fallbackChannel),
        extensionDistribution: resolveExtensionDistribution(installType, installSource)
      });
    });
  });
}

function generateClientId() {
  try {
    if (crypto?.randomUUID) {
      return crypto.randomUUID();
    }
  } catch (error) {
    // Ignore UUID errors.
  }
  return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2, 12)}`;
}

async function getClientId() {
  if (cachedClientId) {
    return cachedClientId;
  }
  const stored = await chrome.storage.local.get({ clientId: "" });
  if (stored.clientId) {
    cachedClientId = stored.clientId;
    return cachedClientId;
  }
  const newId = generateClientId();
  cachedClientId = newId;
  await chrome.storage.local.set({ clientId: newId });
  return cachedClientId;
}

async function getSettings() {
  const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: stored.enabled ?? true,
    blockAllClipboard: stored.blockAllClipboard ?? false,
    blockUnsafeDownloads: stored.blockUnsafeDownloads ?? true,
    shadowAiMonitoring: stored.shadowAiMonitoring ?? true,
    shadowAiWarnUser: stored.shadowAiWarnUser ?? false,
    runtimeThreatScoring: stored.runtimeThreatScoring ?? true,
    apiLicenseKey: typeof stored.apiLicenseKey === "string" ? stored.apiLicenseKey : "",
    apiAccessToken: typeof stored.apiAccessToken === "string" ? stored.apiAccessToken : "",
    apiRefreshToken: typeof stored.apiRefreshToken === "string" ? stored.apiRefreshToken : "",
    apiAccessTokenExpiresAt: Number(stored.apiAccessTokenExpiresAt || 0),
    apiTokenScope: typeof stored.apiTokenScope === "string" ? stored.apiTokenScope : "",
    apiTokenTier: typeof stored.apiTokenTier === "string" ? stored.apiTokenTier : "basic",
    whitelist: stored.whitelist ?? [],
    allowlist: stored.allowlist ?? [],
    history: stored.history ?? [],
    blocklist: stored.blocklist ?? [],
    blocklistSources: stored.blocklistSources ?? [],
    allowlistSources: stored.allowlistSources ?? [],
    uiTheme: stored.uiTheme ?? "system",
    familySafe: stored.familySafe ?? false,
    muteDetectionNotifications: stored.muteDetectionNotifications ?? false,
    saveClipboardBackup: stored.saveClipboardBackup ?? true,
    sendCountry: stored.sendCountry ?? true,
    detectionScreenshotCapture: stored.detectionScreenshotCapture ?? false,
    reportQueue: stored.reportQueue ?? [],
    alertCount: stored.alertCount ?? 0,
    blockCount: stored.blockCount ?? 0,
    blocklistUpdatedAt: stored.blocklistUpdatedAt ?? 0,
    allowlistUpdatedAt: stored.allowlistUpdatedAt ?? 0,
    scoreConfig: normalizeScoreConfig(stored.scoreConfig),
    scoreConfigManagedBy: stored.scoreConfigManagedBy === "server" ? "server" : "local",
    scoreConfigServerUpdatedAt: Number(stored.scoreConfigServerUpdatedAt || 0),
    alertMinSeverity: normalizeAlertMinSeverity(stored.alertMinSeverity)
  };
}

function t(key, substitutions) {
  if (activeMessages?.[key]?.message) {
    return formatMessage(activeMessages[key].message, substitutions);
  }
  return chrome.i18n.getMessage(key, substitutions) || key;
}

const SUPPORTED_LOCALES = ["en", "es", "ca", "de", "fr", "nl", "he", "ru", "zh", "ko", "ja", "pt", "ar", "hi"];
const DEFAULT_LOCALE = "en";
let activeMessages = null;
let localeReady = Promise.resolve();
let scoreConfigReady = loadScoreConfig()
  .then(() => refreshServerScoreConfig())
  .catch(() => false);

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
}

chrome.storage.onChanged.addListener((changes, area) => {
  if (area !== "local") {
    return;
  }
  if (changes.uiLanguage) {
    localeReady = initLocale();
  }
  if (changes.scoreConfig) {
    cachedScoreConfig = normalizeScoreConfig(changes.scoreConfig.newValue);
  }
});

function ensureLocaleReady() {
  return localeReady.catch(() => undefined);
}

function extractHostname(url) {
  try {
    return new URL(url).hostname;
  } catch (error) {
    return "";
  }
}

function isClickFixDisabledHost(hostname) {
  const host = String(hostname || "").trim().toLowerCase();
  if (!host) {
    return false;
  }
  return CLICKFIX_DISABLED_HOSTS.some((entry) => host === entry || host.endsWith(`.${entry}`));
}

function isClickFixDisabledUrl(url) {
  return isClickFixDisabledHost(extractHostname(String(url || "")));
}

function normalizeHostname(value) {
  const trimmed = value.trim();
  if (!trimmed) {
    return "";
  }
  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    return extractHostname(trimmed);
  }
  if (trimmed.includes("/")) {
    return extractHostname(`https://${trimmed}`);
  }
  return trimmed.replace(/^\*\./, "");
}

function dedupeStrings(values) {
  const out = [];
  (values || []).forEach((value) => {
    const normalized = String(value || "").trim();
    if (!normalized || out.includes(normalized)) {
      return;
    }
    out.push(normalized);
  });
  return out;
}

function isIpLikeHostname(hostname) {
  if (!hostname) {
    return false;
  }
  if (/^\d{1,3}(\.\d{1,3}){3}$/.test(hostname)) {
    return true;
  }
  return hostname.includes(":");
}

function isTrustedDownloadHost(hostname) {
  if (!hostname) {
    return false;
  }
  return TRUSTED_DOWNLOAD_HOSTS.some((entry) => matchesHostname(hostname, entry));
}

function isShadowAiHost(hostname) {
  if (!hostname) {
    return false;
  }
  return SHADOW_AI_HOSTS.some((entry) => matchesHostname(hostname, entry));
}

function isTrustedReportEndpoint(url) {
  try {
    const parsed = new URL(url);
    return (
      parsed.protocol === "https:" &&
      parsed.hostname === TRUSTED_REPORT_HOST &&
      parsed.pathname.startsWith(TRUSTED_REPORT_PATH_PREFIX)
    );
  } catch (error) {
    return false;
  }
}

function normalizeDownloadFilename(value) {
  const raw = String(value || "").trim();
  if (!raw) {
    return "";
  }
  const withForwardSlash = raw.replace(/\\/g, "/");
  const parts = withForwardSlash.split("/");
  return parts[parts.length - 1] || "";
}

function extractFileExtension(filename) {
  const normalized = normalizeDownloadFilename(filename).toLowerCase();
  if (!normalized.includes(".")) {
    return "";
  }
  return normalized.split(".").pop() || "";
}

function hasConfiguredScoreSigningKey() {
  return (
    SCORE_CONFIG_PUBLIC_KEY_PEM.includes("BEGIN PUBLIC KEY") &&
    !SCORE_CONFIG_PUBLIC_KEY_PEM.includes("REPLACE_WITH_YOUR_SIGNING_PUBLIC_KEY")
  );
}

function decodeBase64ToBytes(value) {
  const binary = atob(String(value || ""));
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i += 1) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

function pemToSpkiArrayBuffer(pem) {
  const cleaned = String(pem || "")
    .replace(/-----BEGIN PUBLIC KEY-----/g, "")
    .replace(/-----END PUBLIC KEY-----/g, "")
    .replace(/\s+/g, "");
  return decodeBase64ToBytes(cleaned);
}

async function verifySignedPayload(signedPayload, signatureBase64) {
  if (!hasConfiguredScoreSigningKey()) {
    return false;
  }
  try {
    const key = await crypto.subtle.importKey(
      "spki",
      pemToSpkiArrayBuffer(SCORE_CONFIG_PUBLIC_KEY_PEM),
      { name: "RSASSA-PKCS1-v1_5", hash: "SHA-256" },
      false,
      ["verify"]
    );
    return await crypto.subtle.verify(
      "RSASSA-PKCS1-v1_5",
      key,
      decodeBase64ToBytes(signatureBase64),
      new TextEncoder().encode(String(signedPayload || ""))
    );
  } catch (error) {
    return false;
  }
}

function parseJwtPayload(token) {
  const value = String(token || "");
  const parts = value.split(".");
  if (parts.length !== 3) {
    return null;
  }
  try {
    const payload = parts[1].replace(/-/g, "+").replace(/_/g, "/");
    const pad = payload.length % 4 === 0 ? "" : "=".repeat(4 - (payload.length % 4));
    const json = atob(payload + pad);
    const parsed = JSON.parse(json);
    return parsed && typeof parsed === "object" ? parsed : null;
  } catch (error) {
    return null;
  }
}

function parseExpiresAtMillis(expiresAtIso, fallbackSeconds = 600) {
  const parsed = Date.parse(String(expiresAtIso || ""));
  if (Number.isFinite(parsed) && parsed > Date.now()) {
    return parsed;
  }
  return Date.now() + fallbackSeconds * 1000;
}

async function storeApiTokens(tokenPayload, fallbackTier = "basic") {
  const accessToken = String(tokenPayload?.access_token || "").trim();
  const refreshToken = String(tokenPayload?.refresh_token || "").trim();
  const expiresAt = parseExpiresAtMillis(
    tokenPayload?.expires_at,
    Number(tokenPayload?.expires_in || 600)
  );
  const payloadClaims = parseJwtPayload(accessToken);
  const scope =
    String(tokenPayload?.scope || "") ||
    String(payloadClaims?.scope || "");
  const tier =
    String(tokenPayload?.tier || "") ||
    String(payloadClaims?.tier || "") ||
    fallbackTier;

  await chrome.storage.local.set({
    apiAccessToken: accessToken,
    apiRefreshToken: refreshToken,
    apiAccessTokenExpiresAt: expiresAt,
    apiTokenScope: scope,
    apiTokenTier: tier
  });
  return accessToken;
}

async function requestApiTokenWithLicense(settings) {
  const licenseKey = String(settings?.apiLicenseKey || "").trim();
  if (!licenseKey) {
    return "";
  }
  const deviceId = await getClientId();
  try {
    const response = await fetch(CLICKFIX_API_TOKEN_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        license_key: licenseKey,
        device_id: deviceId
      })
    });
    if (!response.ok) {
      return "";
    }
    const payload = await response.json();
    if (!payload || payload.status !== "ok" || !payload.access_token) {
      return "";
    }
    return storeApiTokens(payload);
  } catch (error) {
    return "";
  }
}

async function refreshApiToken(settings) {
  const refreshToken = String(settings?.apiRefreshToken || "").trim();
  if (!refreshToken) {
    return "";
  }
  const deviceId = await getClientId();
  try {
    const response = await fetch(CLICKFIX_API_REFRESH_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        refresh_token: refreshToken,
        device_id: deviceId
      })
    });
    if (!response.ok) {
      return "";
    }
    const payload = await response.json();
    if (!payload || payload.status !== "ok" || !payload.access_token) {
      return "";
    }
    return storeApiTokens(payload, settings?.apiTokenTier || "basic");
  } catch (error) {
    return "";
  }
}

async function ensureApiAccessToken() {
  const settings = await getSettings();
  const now = Date.now();
  const currentToken = String(settings.apiAccessToken || "").trim();
  const currentExp = Number(settings.apiAccessTokenExpiresAt || 0);
  if (currentToken && currentExp > now + 60 * 1000) {
    return currentToken;
  }

  const refreshed = await refreshApiToken(settings);
  if (refreshed) {
    return refreshed;
  }
  const issued = await requestApiTokenWithLicense(settings);
  if (issued) {
    return issued;
  }

  if (currentToken && currentExp > now) {
    return currentToken;
  }
  return "";
}

function matchesHostname(hostname, entry) {
  if (!hostname || !entry) {
    return false;
  }
  if (hostname === entry) {
    return true;
  }
  return hostname.endsWith(`.${entry}`);
}

let blocklistCache = { items: [], fetchedAt: 0 };
let allowlistCache = { items: [], fetchedAt: 0 };
let reportQueue = [];
const reportHashes = new Map();

localeReady = initLocale();

async function refreshBlocklist() {
  const settings = await getSettings();
  const sources = [CLICKFIX_BLOCKLIST_URL, ...(settings.blocklistSources || [])];
  const seen = new Set();
  try {
    const entries = [];
    for (const source of sources) {
      if (!source) {
        continue;
      }
      const response = await fetch(source, { cache: "no-store" });
      if (!response.ok) {
        continue;
      }
      const text = await response.text();
      const sourceEntries = text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line && !line.startsWith("#"))
        .map(normalizeHostname)
        .filter(Boolean);
      for (const entry of sourceEntries) {
        if (!seen.has(entry)) {
          seen.add(entry);
          entries.push(entry);
        }
      }
    }
    if (entries.length) {
      blocklistCache = { items: entries, fetchedAt: Date.now() };
      await chrome.storage.local.set({
        blocklist: entries,
        blocklistUpdatedAt: Date.now()
      });
    }
  } catch (error) {
    // Ignore network errors.
  }
}

async function refreshAllowlist() {
  const settings = await getSettings();
  const sources = [CLICKFIX_ALLOWLIST_URL, ...(settings.allowlistSources || [])];
  const seen = new Set();
  try {
    const entries = [];
    for (const source of sources) {
      if (!source) {
        continue;
      }
      const response = await fetch(source, { cache: "no-store" });
      if (!response.ok) {
        continue;
      }
      const text = await response.text();
      const sourceEntries = text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line && !line.startsWith("#"))
        .map(normalizeHostname)
        .filter(Boolean);
      for (const entry of sourceEntries) {
        if (!seen.has(entry)) {
          seen.add(entry);
          entries.push(entry);
        }
      }
    }
    if (entries.length) {
      allowlistCache = { items: entries, fetchedAt: Date.now() };
      await chrome.storage.local.set({
        allowlist: entries,
        allowlistUpdatedAt: Date.now()
      });
    }
  } catch (error) {
    // Ignore network errors.
  }
}

async function refreshServerScoreConfig() {
  const applyConfig = async (rawConfig) => {
    const normalized = normalizeScoreConfig(rawConfig);
    cachedScoreConfig = normalized;
    await chrome.storage.local.set({
      scoreConfig: normalized,
      scoreConfigManagedBy: "server",
      scoreConfigServerUpdatedAt: Date.now()
    });
    return true;
  };

  try {
    const token = await ensureApiAccessToken();
    if (token) {
      const premiumResponse = await fetch(CLICKFIX_PREMIUM_SCORE_CONFIG_URL, {
        cache: "no-store",
        headers: { Authorization: `Bearer ${token}` }
      });
      if (premiumResponse.ok) {
        const premiumPayload = await premiumResponse.json();
        const signedPayload = String(premiumPayload?.signed_payload || "");
        const signature = String(premiumPayload?.signature || "");
        if (signedPayload && signature) {
          const verified = await verifySignedPayload(signedPayload, signature);
          if (verified) {
            const decoded = JSON.parse(signedPayload);
            const expiresAt = Date.parse(String(decoded?.expires_at || ""));
            if (!Number.isFinite(expiresAt) || expiresAt > Date.now()) {
              if (decoded && typeof decoded === "object" && decoded.scoreConfig) {
                return applyConfig(decoded.scoreConfig);
              }
            }
          }
        }
      }
    }
  } catch (error) {
    // Ignore premium config retrieval errors and fallback to public config.
  }

  try {
    const response = await fetch(CLICKFIX_SCORE_CONFIG_URL, { cache: "no-store" });
    if (!response.ok) {
      return false;
    }
    const payload = await response.json();
    const rawConfig =
      payload && typeof payload === "object" && payload.scoreConfig && typeof payload.scoreConfig === "object"
        ? payload.scoreConfig
        : payload;
    return applyConfig(rawConfig);
  } catch (error) {
    return false;
  }
}

function normalizeMessageSeverity(value) {
  const severity = String(value || "").toLowerCase();
  if (severity === "critical" || severity === "warning" || severity === "info") {
    return severity;
  }
  return "info";
}

function extensionMessageNotificationTitle(message) {
  const severity = normalizeMessageSeverity(message?.severity);
  const title = String(message?.title || "").trim();
  const prefix =
    severity === "critical" ? "CRITICAL" : severity === "warning" ? "WARNING" : "INFO";
  return `${t("appName")} [${prefix}]${title ? ` ${title}` : ""}`.slice(0, 120);
}

async function clearStaleExtensionMessageNotifications(activeMessageIds) {
  if (!chrome?.notifications?.getAll || !chrome?.notifications?.clear) {
    return;
  }
  const all = await chrome.notifications.getAll();
  const ids = Object.keys(all || {});
  for (const notificationId of ids) {
    if (!notificationId.startsWith("clickfix-message-")) {
      continue;
    }
    const messageId = notificationId.slice("clickfix-message-".length);
    if (activeMessageIds.has(messageId)) {
      continue;
    }
    try {
      await chrome.notifications.clear(notificationId);
    } catch (error) {
      // Ignore clear failures for stale notifications.
    }
  }
}

async function refreshExtensionMessages() {
  try {
    const token = await ensureApiAccessToken();
    if (!token) {
      return false;
    }
    const clientId = await getClientId();
    const url = `${CLICKFIX_API_MESSAGES_URL}?client_id=${encodeURIComponent(clientId)}`;
    const response = await fetch(url, {
      cache: "no-store",
      headers: { Authorization: `Bearer ${token}` }
    });
    if (!response.ok) {
      return false;
    }
    const payload = await response.json();
    const incoming = Array.isArray(payload?.messages) ? payload.messages : [];
    const activeMessageIds = new Set(
      incoming
        .map((message) => String(message?.id ?? "").trim())
        .filter((id) => id !== "")
    );
    await clearStaleExtensionMessageNotifications(activeMessageIds);
    if (!incoming.length) {
      return true;
    }
    const settings = await getSettings();
    const seenIds = Array.isArray(settings.extensionMessageSeenIds)
      ? settings.extensionMessageSeenIds.map((id) => String(id))
      : [];
    const seenSet = new Set(seenIds);
    const nextSeen = [...seenIds];
    const iconUrl = chrome.runtime.getURL("icons/icon-128.png");

    for (const message of incoming) {
      const id = String(message?.id ?? "");
      if (!id || seenSet.has(id)) {
        continue;
      }
      const body = String(message?.body || "").trim();
      if (!body) {
        continue;
      }
      const severity = normalizeMessageSeverity(message?.severity);
      chrome.notifications.create(`clickfix-message-${id}`, {
        type: "basic",
        iconUrl,
        title: extensionMessageNotificationTitle(message),
        message: body.slice(0, 360),
        priority: severity === "critical" ? 2 : 1
      });
      seenSet.add(id);
      nextSeen.push(id);
    }

    const capped = nextSeen.slice(-500);
    await chrome.storage.local.set({ extensionMessageSeenIds: capped });
    return true;
  } catch (error) {
    return false;
  }
}

function pushHistory(history, entry) {
  const next = [entry, ...history];
  return next.slice(0, 50);
}

async function saveHistory(entry) {
  const settings = await getSettings();
  const history = pushHistory(settings.history, entry);
  await chrome.storage.local.set({ history });
}

async function incrementAlertCount() {
  const settings = await getSettings();
  await chrome.storage.local.set({ alertCount: (settings.alertCount ?? 0) + 1 });
}

async function incrementBlockCount() {
  const settings = await getSettings();
  await chrome.storage.local.set({ blockCount: (settings.blockCount ?? 0) + 1 });
}

async function addClipboardBackupEntry({ text, url, malicious }) {
  if (!text) {
    return;
  }
  const trimmed = text.trim();
  if (!trimmed) {
    return;
  }
  const entry = {
    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    text: trimmed,
    url: url || "",
    timestamp: Date.now(),
    malicious: Boolean(malicious)
  };
  const stored = await chrome.storage.local.get({ clipboardBackups: [] });
  const existing = Array.isArray(stored.clipboardBackups) ? stored.clipboardBackups : [];
  const next = [entry, ...existing].slice(0, CLIPBOARD_BACKUP_LIMIT);
  await chrome.storage.local.set({ clipboardBackups: next });
}

function clampScorePoints(value, fallback) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }
  return Math.max(-100, Math.min(100, Math.round(numeric)));
}

function clampWeight(value, fallback) {
  let numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }
  if (numeric > 1) {
    if (numeric <= 100) {
      numeric = numeric / 100;
    } else {
      return fallback;
    }
  }
  numeric = Math.max(0, Math.min(1, numeric));
  return numeric;
}

function normalizeScoreConfig(rawConfig) {
  const base = DEFAULT_SCORE_CONFIG;
  const config = rawConfig && typeof rawConfig === "object" ? rawConfig : {};
  const weights = config.weights && typeof config.weights === "object" ? config.weights : {};
  const normalizedWeights = {
    signals: clampWeight(weights.signals, base.weights.signals),
    clipboard: clampWeight(weights.clipboard, base.weights.clipboard),
    context: clampWeight(weights.context, base.weights.context)
  };

  const weightSum = normalizedWeights.signals + normalizedWeights.clipboard + normalizedWeights.context;
  if (weightSum <= 0) {
    normalizedWeights.signals = base.weights.signals;
    normalizedWeights.clipboard = base.weights.clipboard;
    normalizedWeights.context = base.weights.context;
  }

  const contextBaseScore = clampScore(
    Number.isFinite(Number(config.contextBaseScore))
      ? Number(config.contextBaseScore)
      : base.contextBaseScore
  );

  const normalizedRules = buildDefaultScoreRules();
  const rawRules = config.rules && typeof config.rules === "object" ? config.rules : {};
  Object.entries(SCORE_RULE_DEFINITIONS).forEach(([group, rules]) => {
    const groupRules = rawRules[group] && typeof rawRules[group] === "object" ? rawRules[group] : {};
    rules.forEach((rule) => {
      normalizedRules[group][rule.flag] = clampScorePoints(
        groupRules[rule.flag],
        rule.defaultPoints
      );
    });
  });

  return {
    weights: normalizedWeights,
    contextBaseScore,
    rules: normalizedRules
  };
}

async function loadScoreConfig() {
  const { scoreConfig } = await chrome.storage.local.get({ scoreConfig: DEFAULT_SCORE_CONFIG });
  cachedScoreConfig = normalizeScoreConfig(scoreConfig);
  return cachedScoreConfig;
}

function getScoreConfig() {
  return cachedScoreConfig || DEFAULT_SCORE_CONFIG;
}

function getScoreWeight(group) {
  const config = getScoreConfig();
  return config.weights?.[group] ?? DEFAULT_SCORE_CONFIG.weights[group];
}

function getScoreRules(group) {
  const config = getScoreConfig();
  const overrides = config.rules?.[group] || {};
  return (SCORE_RULE_DEFINITIONS[group] || []).map((rule) => ({
    flag: rule.flag,
    key: rule.key,
    points: clampScorePoints(overrides[rule.flag], rule.defaultPoints)
  }));
}

function getContextBaseScore() {
  const config = getScoreConfig();
  const baseScore = Number(config.contextBaseScore);
  if (!Number.isFinite(baseScore)) {
    return DEFAULT_SCORE_CONFIG.contextBaseScore;
  }
  return Math.max(0, Math.min(100, Math.round(baseScore)));
}

function clampScore(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return 0;
  }
  return Math.max(0, Math.min(100, Math.round(numeric)));
}

function buildScoreComponent({ id, labelKey, score, weight, contributions, available = true }) {
  return {
    id,
    labelKey,
    score: clampScore(score),
    weight,
    contributions,
    available
  };
}

function computeSignalScore(details) {
  const contributions = [];
  let score = 0;
  getScoreRules("signals").forEach((rule) => {
    if (details[rule.flag]) {
      score += rule.points;
      contributions.push({ key: rule.key, points: rule.points });
    }
  });
  return buildScoreComponent({
    id: "signals",
    labelKey: "scoreComponentSignals",
    score,
    weight: getScoreWeight("signals"),
    contributions,
    available: true
  });
}

function normalizeClipboardAnalysis(analysis) {
  if (!analysis || typeof analysis !== "object") {
    return null;
  }
  const hasCommand = Boolean(analysis.hasCommand ?? analysis.commandMatch);
  const hasExecutionHint = Boolean(analysis.hasExecutionHint ?? analysis.shellHint ?? analysis.executionHint);
  const hasUrl = Boolean(analysis.hasUrl ?? analysis.url);
  const hasBase64 = Boolean(analysis.hasBase64 ?? analysis.base64);
  const hasHighEntropy = Boolean(analysis.hasHighEntropy ?? analysis.highEntropy);
  const hasShellMeta = Boolean(analysis.hasShellMeta ?? analysis.shellMeta);
  const isLong = Boolean(analysis.isLong);
  const hasLeadingWhitespace = Boolean(analysis.hasLeadingWhitespace ?? analysis.leadingWhitespace);
  const looksLikeCommand = Boolean(analysis.looksLikeCommand);

  return {
    hasCommand,
    hasExecutionHint,
    hasUrl,
    hasBase64,
    hasHighEntropy,
    hasShellMeta,
    isLong,
    hasLeadingWhitespace,
    looksLikeCommand
  };
}

function computeClipboardScore(analysis) {
  const normalized = normalizeClipboardAnalysis(analysis);
  if (!normalized) {
    return buildScoreComponent({
      id: "clipboard",
      labelKey: "scoreComponentClipboard",
      score: 0,
      weight: getScoreWeight("clipboard"),
      contributions: [],
      available: false
    });
  }
  const contributions = [];
  let score = 0;
  getScoreRules("clipboard").forEach((rule) => {
    if (normalized[rule.flag]) {
      score += rule.points;
      contributions.push({ key: rule.key, points: rule.points });
    }
  });
  return buildScoreComponent({
    id: "clipboard",
    labelKey: "scoreComponentClipboard",
    score,
    weight: getScoreWeight("clipboard"),
    contributions,
    available: true
  });
}

function computeContextScore(context) {
  if (!context || typeof context !== "object") {
    return buildScoreComponent({
      id: "context",
      labelKey: "scoreComponentContext",
      score: 0,
      weight: getScoreWeight("context"),
      contributions: [],
      available: false
    });
  }
  const contributions = [];
  let score = getContextBaseScore();
  const opaqueCount = Number(context.opaqueIframes || 0);
  const flags = {
    isAllowlisted: Boolean(context.isAllowlisted),
    isTrustedHost: Boolean(context.isTrustedHost),
    isCodeContext: Boolean(context.isCodeContext),
    isIframe: context.frameType === "iframe",
    opaqueIframes: opaqueCount >= 2 && opaqueCount < 4,
    opaqueIframesHigh: opaqueCount >= 4
  };
  getScoreRules("context").forEach((rule) => {
    if (flags[rule.flag]) {
      score += rule.points;
      contributions.push({ key: rule.key, points: rule.points });
    }
  });
  return buildScoreComponent({
    id: "context",
    labelKey: "scoreComponentContext",
    score,
    weight: getScoreWeight("context"),
    contributions,
    available: true
  });
}

function buildScoreBreakdown(details) {
  const signalComponent = computeSignalScore(details);
  const clipboardComponent = computeClipboardScore(details.clipboardAnalysis);
  const contextComponent = computeContextScore(details.context || details.clipboardSource);

  const components = [signalComponent, clipboardComponent, contextComponent];
  const available = components.filter((component) => component.available);
  const weightSum = available.reduce((total, component) => total + component.weight, 0) || 1;

  const normalizedComponents = components.map((component) => {
    if (!component.available) {
      return component;
    }
    return {
      ...component,
      weight: component.weight / weightSum
    };
  });

  const total = clampScore(
    normalizedComponents
      .filter((component) => component.available)
      .reduce((sum, component) => sum + component.score * component.weight, 0)
  );

  return {
    total,
    method: "weighted",
    components: normalizedComponents
  };
}

function computeDetectionScore(details) {
  return buildScoreBreakdown(details);
}

function computeUrlRiskSignals(details) {
  const reasons = [];
  let score = 0;
  const url = String(details?.url || "");
  const hostname = extractHostname(url).toLowerCase();

  if (!hostname) {
    return { score: 0, reasons };
  }
  if (url.startsWith("http://")) {
    score += 8;
    reasons.push("URL uses insecure HTTP.");
  }
  if (hostname.includes("xn--")) {
    score += 20;
    reasons.push("Punycode domain detected.");
  }
  if (isIpLikeHostname(hostname)) {
    score += 18;
    reasons.push("Domain is an IP address.");
  }
  const labels = hostname.split(".");
  const longestLabel = labels.reduce((max, label) => Math.max(max, label.length), 0);
  if (longestLabel >= 24) {
    score += 8;
    reasons.push("Very long subdomain label.");
  }
  const hyphenCount = (hostname.match(/\-/g) || []).length;
  if (hyphenCount >= 3) {
    score += 6;
    reasons.push("Domain has multiple hyphens.");
  }
  return { score, reasons };
}

function computeContentRiskSignals(details) {
  const reasons = [];
  let score = 0;
  const add = (active, value, reason) => {
    if (!active) {
      return;
    }
    score += value;
    reasons.push(reason);
  };

  add(details?.commandMatch, 14, "Command execution pattern found.");
  add(details?.shellHint, 12, "Shell execution hint found.");
  add(details?.evasionHint, 12, "Obfuscation/evasion pattern found.");
  add(details?.clipboardWarning, 9, "Clipboard manipulation indicator found.");
  add(details?.mismatch, 8, "Clipboard mismatch detected.");
  add(details?.winRHint || details?.winXHint, 8, "System shortcut instruction found.");
  add(details?.winXTerminalHint, 6, "Win+X then I/Terminal execution flow found.");
  add(details?.consoleHint, 8, "Developer console instruction found.");
  add(details?.pasteSequenceHint, 6, "Step-by-step paste execution flow found.");
  add(details?.copyTriggerHint, 6, "Forced clipboard copy trigger found.");
  add(details?.fileExplorerHint, 5, "File Explorer path execution instruction found.");
  add(details?.browserErrorHint, 5, "Fake browser error social engineering pattern found.");
  add(details?.fixActionHint, 5, "Fix/repair bait wording found.");
  add(details?.captchaHint, 4, "Fake CAPTCHA lure pattern found.");
  return { score, reasons };
}

function computeReputationRiskSignals(listDecision) {
  const reasons = [];
  let score = 0;
  if (!listDecision) {
    return { score, reasons };
  }
  if (listDecision.blocked && !listDecision.allowlisted) {
    score += 35;
    reasons.push("Host appears in active blocklist.");
  }
  if (listDecision.conflict) {
    score += 8;
    reasons.push("Host appears in both allowlist and blocklist.");
  }
  if (listDecision.allowlisted) {
    score -= 25;
    reasons.push("Host appears in allowlist.");
  }
  return { score, reasons };
}

function computeBrandRiskSignals(details) {
  const reasons = [];
  let score = 0;
  const hostname = extractHostname(details?.url || "").toLowerCase();
  if (!hostname) {
    return { score, reasons };
  }
  const textPool = `${details?.detectedContent || ""}\n${details?.fullContext || ""}`
    .toLowerCase()
    .slice(0, 6000);
  for (const brand of BRAND_REPUTATION_RULES) {
    if (!textPool.includes(brand.name)) {
      continue;
    }
    const brandMatchesHost = brand.hosts.some((entry) => matchesHostname(hostname, entry));
    if (!brandMatchesHost) {
      score += 10;
      reasons.push(`Brand mismatch: mentions ${brand.name} but host is ${hostname}.`);
      break;
    }
  }
  return { score, reasons };
}

function computeLureRiskSignals(details) {
  const reasons = [];
  let score = 0;
  if (details?.captchaHint && (details?.commandMatch || details?.shellHint || details?.downloadHint)) {
    score += 12;
    reasons.push("CAPTCHA + execution chain detected.");
  }
  if (details?.browserErrorHint && details?.fixActionHint) {
    score += 10;
    reasons.push("Browser error + fix-now social engineering flow detected.");
  }
  if (details?.downloadRisk?.unsafe) {
    score += 20;
    reasons.push("Download risk engine classified artifact as unsafe.");
  }
  return { score, reasons };
}

function buildRuntimeThreatVerdict(details, listDecision) {
  const urlModel = computeUrlRiskSignals(details);
  const contentModel = computeContentRiskSignals(details);
  const reputationModel = computeReputationRiskSignals(listDecision);
  const brandModel = computeBrandRiskSignals(details);
  const lureModel = computeLureRiskSignals(details);

  const total = clampScore(
    urlModel.score +
      contentModel.score +
      reputationModel.score +
      brandModel.score +
      lureModel.score
  );
  const level = total >= 65 ? "unsafe" : total >= 38 ? "suspicious" : "low";

  return {
    total,
    level,
    models: {
      url: urlModel.score,
      content: contentModel.score,
      reputation: reputationModel.score,
      brand: brandModel.score,
      vision: lureModel.score
    },
    reasons: dedupeStrings([
      ...urlModel.reasons,
      ...contentModel.reasons,
      ...reputationModel.reasons,
      ...brandModel.reasons,
      ...lureModel.reasons
    ])
  };
}

function buildRuntimeVerdictSnippets(verdict) {
  if (!verdict) {
    return [];
  }
  const scoreLine = `Runtime verdict: ${verdict.level} (${verdict.total}/100)`;
  const modelLine = `Models URL:${verdict.models.url} Content:${verdict.models.content} Reputation:${verdict.models.reputation} Brand:${verdict.models.brand} Vision:${verdict.models.vision}`;
  const reasons = (verdict.reasons || []).slice(0, 4).map((reason) => `Runtime reason: ${reason}`);
  return dedupeStrings([scoreLine, modelLine, ...reasons]);
}

function buildAlertReasons(details) {
  const parts = [];
  const addReason = (message) => {
    if (!message || parts.includes(message)) {
      return;
    }
    parts.push(message);
  };
  if (details.mismatch) {
    addReason(t("alertMismatch"));
  }
  if (details.clipboardWarning) {
    addReason(t("alertClipboardCommand"));
  }
  if (details.commandMatch) {
    addReason(t("alertCommand"));
  }
  if (details.winRHint) {
    addReason(t("alertWinR"));
  }
  if (details.winXHint) {
    addReason(t("alertWinX"));
  }
  if (details.winXTerminalHint) {
    addReason(t("alertWinXI"));
  }
  if (details.browserErrorHint) {
    addReason(t("alertBrowserError"));
  }
  if (details.fixActionHint) {
    addReason(t("alertFixAction"));
  }
  if (details.captchaHint) {
    addReason(t("alertCaptcha"));
  }
  if (details.consoleHint) {
    addReason(t("alertConsole"));
  }
  if (details.shellHint) {
    addReason(t("alertShell"));
  }
  if (details.pasteSequenceHint) {
    addReason(t("alertPasteSequence"));
  }
  if (details.fileExplorerHint) {
    addReason(t("alertFileExplorer"));
  }
  if (details.copyTriggerHint) {
    addReason(t("alertCopyTrigger"));
  }
  if (details.evasionHint) {
    addReason(t("alertEvasion"));
  }
  const snippets = details.snippets || [];
  snippets.forEach((snippetText) => {
    if (!snippetText) {
      return;
    }
    const snippet =
      snippetText.length > 160
        ? `${snippetText.slice(0, 157)}...`
        : snippetText;
    addReason(t("alertSnippet", snippet));
  });
  if (details.blockedClipboardText) {
    const snippet =
      details.blockedClipboardText.length > CLIPBOARD_SNIPPET_LIMIT
        ? `${details.blockedClipboardText.slice(0, CLIPBOARD_SNIPPET_LIMIT - 3)}...`
        : details.blockedClipboardText;
    addReason(t("alertClipboardBlocked", snippet));
  }
  if (Number.isFinite(details.confidenceScore)) {
    addReason(t("alertConfidenceScore", details.confidenceScore));
  }
  return parts;
}

function tEsMessage(key, substitutions) {
  return t(key, substitutions);
}

function buildAlertReasonsEs(details) {
  return buildAlertReasons(details);
}

function buildAlertReasonEntries(details) {
  const entries = [];
  const addEntry = (key, value) => {
    if (!key) {
      return;
    }
    const normalizedValue = value === undefined || value === null ? undefined : String(value);
    const exists = entries.some(
      (entry) => entry.key === key && entry.value === normalizedValue
    );
    if (!exists) {
      entries.push(
        normalizedValue === undefined ? { key } : { key, value: normalizedValue }
      );
    }
  };
  if (details.mismatch) {
    addEntry("alertMismatch");
  }
  if (details.clipboardWarning) {
    addEntry("alertClipboardCommand");
  }
  if (details.commandMatch) {
    addEntry("alertCommand");
  }
  if (details.winRHint) {
    addEntry("alertWinR");
  }
  if (details.winXHint) {
    addEntry("alertWinX");
  }
  if (details.winXTerminalHint) {
    addEntry("alertWinXI");
  }
  if (details.browserErrorHint) {
    addEntry("alertBrowserError");
  }
  if (details.fixActionHint) {
    addEntry("alertFixAction");
  }
  if (details.captchaHint) {
    addEntry("alertCaptcha");
  }
  if (details.consoleHint) {
    addEntry("alertConsole");
  }
  if (details.shellHint) {
    addEntry("alertShell");
  }
  if (details.pasteSequenceHint) {
    addEntry("alertPasteSequence");
  }
  if (details.fileExplorerHint) {
    addEntry("alertFileExplorer");
  }
  if (details.copyTriggerHint) {
    addEntry("alertCopyTrigger");
  }
  if (details.evasionHint) {
    addEntry("alertEvasion");
  }
  const snippets = details.snippets || [];
  snippets.forEach((snippetText) => {
    if (!snippetText) {
      return;
    }
    const snippet =
      snippetText.length > 160
        ? `${snippetText.slice(0, 157)}...`
        : snippetText;
    addEntry("alertSnippet", snippet);
  });
  if (details.blockedClipboardText) {
    const snippet =
      details.blockedClipboardText.length > CLIPBOARD_SNIPPET_LIMIT
        ? `${details.blockedClipboardText.slice(0, CLIPBOARD_SNIPPET_LIMIT - 3)}...`
        : details.blockedClipboardText;
    addEntry("alertClipboardBlocked", snippet);
  }
  if (Number.isFinite(details.confidenceScore)) {
    addEntry("alertConfidenceScore", details.confidenceScore);
  }
  return entries;
}

function buildAlertMessage(details) {
  return buildAlertReasons(details).join(" ");
}

function buildAlertSnippets(details) {
  const snippets = [];
  const addSnippet = (value) => {
    if (!value || snippets.includes(value)) {
      return;
    }
    snippets.push(value);
  };
  (details.snippets || []).forEach(addSnippet);
  if (details.blockedClipboardText) {
    addSnippet(details.blockedClipboardText);
  }
  if (details.detectedContent) {
    addSnippet(details.detectedContent);
  }
  return snippets;
}

const pageAlertBatchState = new Map();

function cleanupTabTransientState(tabId) {
  if (!Number.isInteger(tabId)) {
    return;
  }
  setTabScriptBlocking(tabId, "", false).catch(() => undefined);
  beforeCaptureByTab.delete(tabId);
  beforeCaptureInFlight.delete(tabId);
  beforeCapturePromiseByTab.delete(tabId);
  const prefix = `${tabId}|`;
  for (const [key, state] of pageAlertBatchState.entries()) {
    if (!String(key).startsWith(prefix)) {
      continue;
    }
    if (state?.timer) {
      clearTimeout(state.timer);
    }
    pageAlertBatchState.delete(key);
  }
  for (const key of detectionProgressCache.keys()) {
    if (String(key).startsWith(prefix)) {
      detectionProgressCache.delete(key);
    }
  }
}

function applyPageAlertTypeToDetails(details, alertType) {
  if (!details || !alertType) {
    return;
  }
  if (alertType === "command") details.commandMatch = true;
  if (alertType === "winr") details.winRHint = true;
  if (alertType === "winx") details.winXHint = true;
  if (alertType === "winx-i") {
    details.winXHint = true;
    details.winXTerminalHint = true;
    details.shellHint = true;
    details.pasteSequenceHint = true;
  }
  if (alertType === "browser-error") details.browserErrorHint = true;
  if (alertType === "fix-action") details.fixActionHint = true;
  if (alertType === "captcha") details.captchaHint = true;
  if (alertType === "console") details.consoleHint = true;
  if (alertType === "shell") details.shellHint = true;
  if (alertType === "paste-sequence") details.pasteSequenceHint = true;
  if (alertType === "file-explorer") details.fileExplorerHint = true;
  if (alertType === "copy-trigger") details.copyTriggerHint = true;
}

function mergePageSignalState(details, signalState) {
  if (!details || !signalState || typeof signalState !== "object") {
    return;
  }
  const mappings = [
    "commandMatch",
    "winRHint",
    "winXHint",
    "winXTerminalHint",
    "browserErrorHint",
    "fixActionHint",
    "captchaHint",
    "consoleHint",
    "shellHint",
    "pasteSequenceHint",
    "fileExplorerHint",
    "copyTriggerHint",
    "evasionHint",
    "mismatch",
    "clipboardWarning"
  ];
  mappings.forEach((key) => {
    if (signalState[key]) {
      details[key] = true;
    }
  });
}

async function flushBatchedPageAlert(key) {
  const state = pageAlertBatchState.get(key);
  if (!state) {
    return;
  }
  pageAlertBatchState.delete(key);
  if (!state.url || (await shouldIgnore(state.url))) {
    return;
  }

  const notificationId = await triggerAlert({
    ...state.details,
    snippets: [...state.snippets],
    tabId: state.tabId
  });
  if (notificationId) {
    lastPageHint = null;
  }
}

function queueBatchedPageAlert(message, sender) {
  const url = String(message?.url || "");
  if (!url) {
    return;
  }
  const tabId = Number.isInteger(sender?.tab?.id) ? sender.tab.id : null;
  const keyHost = extractHostname(url) || url.slice(0, 120);
  const key = `${tabId ?? "na"}|${keyHost}`;
  let state = pageAlertBatchState.get(key);

  if (!state) {
    state = {
      tabId,
      snippets: new Set(),
      timer: null,
      details: {
        url,
        timestamp: message?.timestamp ?? Date.now(),
        mismatch: false,
        commandMatch: false,
        winRHint: false,
        winXHint: false,
        winXTerminalHint: false,
        browserErrorHint: false,
        fixActionHint: false,
        captchaHint: false,
        consoleHint: false,
        shellHint: false,
        pasteSequenceHint: false,
        fileExplorerHint: false,
        copyTriggerHint: false,
        evasionHint: false,
        clipboardWarning: false,
        snippets: [],
        blockedClipboardText: "",
        detectedContent: "",
        fullContext: "",
        previousUrl: "",
        context: null
      }
    };
  }

  state.details.timestamp = Math.max(
    Number(state.details.timestamp || 0),
    Number(message?.timestamp || Date.now())
  );
  state.details.url = url;
  state.details.context = message?.context || state.details.context;
  if (message?.previousUrl && !state.details.previousUrl) {
    state.details.previousUrl = String(message.previousUrl);
  }
  if (message?.detectedContent) {
    const incoming = String(message.detectedContent);
    if (incoming.length >= String(state.details.detectedContent || "").length) {
      state.details.detectedContent = incoming;
    }
  }
  if (message?.fullContext) {
    const incoming = String(message.fullContext);
    if (incoming.length >= String(state.details.fullContext || "").length) {
      state.details.fullContext = incoming;
    }
  }

  applyPageAlertTypeToDetails(state.details, message?.alertType);
  mergePageSignalState(state.details, message?.signalState);

  if (message?.snippet) {
    state.snippets.add(String(message.snippet));
    if (!state.details.detectedContent) {
      state.details.detectedContent = String(message.snippet);
    }
  }
  if (Array.isArray(message?.snippets)) {
    message.snippets.forEach((snippet) => {
      if (snippet) {
        state.snippets.add(String(snippet));
      }
    });
  }

  if (state.timer) {
    clearTimeout(state.timer);
  }
  state.timer = setTimeout(() => {
    flushBatchedPageAlert(key).catch(() => undefined);
  }, PAGE_ALERT_DEBOUNCE_MS);

  pageAlertBatchState.set(key, state);
}

const detectionProgressCache = new Map();

function buildDetectionProgressKey(details) {
  const tabId = Number.isInteger(details?.tabId) ? String(details.tabId) : "na";
  const hostname = extractHostname(details?.url || "");
  if (hostname) {
    return `${tabId}|${hostname}`;
  }
  const fallbackUrl = String(details?.url || "").slice(0, 120) || "unknown";
  return `${tabId}|${fallbackUrl}`;
}

function extractDetectionSignals(details) {
  const signals = new Set();
  const addSignal = (name, active) => {
    if (active) {
      signals.add(name);
    }
  };

  addSignal("mismatch", details?.mismatch);
  addSignal("clipboardWarning", details?.clipboardWarning);
  addSignal("commandMatch", details?.commandMatch);
  addSignal("shellHint", details?.shellHint);
  addSignal("evasionHint", details?.evasionHint);
  addSignal("winRHint", details?.winRHint);
  addSignal("winXHint", details?.winXHint);
  addSignal("winXTerminalHint", details?.winXTerminalHint);
  addSignal("browserErrorHint", details?.browserErrorHint);
  addSignal("fixActionHint", details?.fixActionHint);
  addSignal("captchaHint", details?.captchaHint);
  addSignal("consoleHint", details?.consoleHint);
  addSignal("pasteSequenceHint", details?.pasteSequenceHint);
  addSignal("fileExplorerHint", details?.fileExplorerHint);
  addSignal("copyTriggerHint", details?.copyTriggerHint);
  addSignal("blockedClipboardText", Boolean(details?.blockedClipboardText));

  const clipboard = details?.clipboardAnalysis;
  if (clipboard && typeof clipboard === "object") {
    addSignal("clipboardCommand", clipboard.hasCommand ?? clipboard.commandMatch);
    addSignal("clipboardExecution", clipboard.hasExecutionHint ?? clipboard.shellHint);
    addSignal("clipboardUrl", clipboard.hasUrl ?? clipboard.url);
    addSignal("clipboardBase64", clipboard.hasBase64 ?? clipboard.base64);
    addSignal("clipboardEntropy", clipboard.hasHighEntropy ?? clipboard.highEntropy);
    addSignal("clipboardShellMeta", clipboard.hasShellMeta ?? clipboard.shellMeta);
    addSignal("clipboardLong", clipboard.isLong);
    addSignal(
      "clipboardLeadingWhitespace",
      clipboard.hasLeadingWhitespace ?? clipboard.leadingWhitespace
    );
    addSignal("clipboardLooksLikeCommand", clipboard.looksLikeCommand);
  }

  return signals;
}

function cleanupDetectionProgress(now) {
  for (const [key, state] of detectionProgressCache.entries()) {
    if (!state || now - state.lastSeen > DETECTION_PROGRESS_WINDOW_MS) {
      detectionProgressCache.delete(key);
    }
  }
}

function computeProgressiveBonus(details) {
  const now = Date.now();
  cleanupDetectionProgress(now);
  const key = buildDetectionProgressKey(details);
  const currentSignals = extractDetectionSignals(details);
  let state = detectionProgressCache.get(key);
  if (!state || now - state.lastSeen > DETECTION_PROGRESS_WINDOW_MS) {
    state = {
      count: 0,
      signals: new Set(),
      lastSeen: now
    };
  }

  let newSignals = 0;
  for (const signal of currentSignals) {
    if (!state.signals.has(signal)) {
      state.signals.add(signal);
      newSignals += 1;
    }
  }
  state.count += 1;
  state.lastSeen = now;
  detectionProgressCache.set(key, state);

  if (state.count <= 1) {
    return {
      bonus: 0,
      eventCount: state.count,
      newSignals,
      totalSignals: state.signals.size
    };
  }

  const eventBonus = Math.min(10, (state.count - 1) * 2);
  const noveltyBonus = Math.min(12, newSignals * 3);
  const breadthBonus = state.signals.size >= 6 ? 3 : state.signals.size >= 4 ? 1 : 0;
  const bonus = Math.min(
    DETECTION_PROGRESS_MAX_BONUS,
    eventBonus + noveltyBonus + breadthBonus
  );

  return {
    bonus,
    eventCount: state.count,
    newSignals,
    totalSignals: state.signals.size
  };
}

function applyProgressiveBonus(scoreDetails, progress) {
  if (!scoreDetails || !progress || !Number.isFinite(progress.bonus) || progress.bonus <= 0) {
    return 0;
  }
  const applied = Math.max(0, Math.min(DETECTION_PROGRESS_MAX_BONUS, Math.round(progress.bonus)));
  if (!applied) {
    return 0;
  }
  scoreDetails.total = clampScore((scoreDetails.total || 0) + applied);
  if (Array.isArray(scoreDetails.components)) {
    const signalComponent = scoreDetails.components.find(
      (component) => component && component.id === "signals" && component.available !== false
    );
    if (signalComponent) {
      signalComponent.score = clampScore((signalComponent.score || 0) + applied);
      if (!Array.isArray(signalComponent.contributions)) {
        signalComponent.contributions = [];
      }
      signalComponent.contributions.push({
        key: "scoreSignalProgressive",
        points: applied
      });
    }
  }
  return applied;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, Math.max(0, Number(ms) || 0)));
}

async function resolveCaptureWindowId(tabId) {
  if (!tabId) {
    return chrome.windows.WINDOW_ID_CURRENT;
  }
  try {
    const tab = await new Promise((resolve) => {
      chrome.tabs.get(tabId, (result) => {
        if (chrome.runtime.lastError || !result) {
          resolve(null);
          return;
        }
        resolve(result);
      });
    });
    if (tab && Number.isInteger(tab.windowId)) {
      return tab.windowId;
    }
  } catch (error) {
    // Ignore capture window resolution errors.
  }
  return chrome.windows.WINDOW_ID_CURRENT;
}

async function captureVisibleTabDataUrl(windowId, format = "jpeg", quality = 52) {
  return new Promise((resolve) => {
    chrome.tabs.captureVisibleTab(
      windowId,
      { format, quality: Math.max(1, Math.min(100, Number(quality) || 52)) },
      (dataUrl) => {
        if (chrome.runtime.lastError || !dataUrl || typeof dataUrl !== "string") {
          resolve("");
          return;
        }
        resolve(dataUrl);
      }
    );
  });
}

function dataUrlByteLength(dataUrl) {
  if (!dataUrl || typeof dataUrl !== "string") {
    return 0;
  }
  const commaIndex = dataUrl.indexOf(",");
  if (commaIndex < 0) {
    return dataUrl.length;
  }
  const base64 = dataUrl.slice(commaIndex + 1);
  return Math.floor((base64.length * 3) / 4);
}

async function compressImageDataUrl(dataUrl, options = {}) {
  const maxWidth = Math.max(240, Math.min(1920, Number(options.maxWidth) || 720));
  const maxHeight = Math.max(180, Math.min(1920, Number(options.maxHeight) || 480));
  const quality = Math.max(0.2, Math.min(0.9, Number(options.quality) || 0.58));
  try {
    const response = await fetch(dataUrl);
    const blob = await response.blob();
    if (!blob || !blob.size) {
      return dataUrl;
    }
    const bitmap = await createImageBitmap(blob);
    const ratio = Math.min(maxWidth / bitmap.width, maxHeight / bitmap.height, 1);
    const targetWidth = Math.max(1, Math.round(bitmap.width * ratio));
    const targetHeight = Math.max(1, Math.round(bitmap.height * ratio));
    const canvas = new OffscreenCanvas(targetWidth, targetHeight);
    const ctx = canvas.getContext("2d", { alpha: false });
    if (!ctx) {
      return dataUrl;
    }
    ctx.drawImage(bitmap, 0, 0, targetWidth, targetHeight);
    const outBlob = await canvas.convertToBlob({ type: "image/jpeg", quality });
    const b64 = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(typeof reader.result === "string" ? reader.result : "");
      reader.onerror = reject;
      reader.readAsDataURL(outBlob);
    });
    return typeof b64 === "string" ? b64 : dataUrl;
  } catch (error) {
    return dataUrl;
  }
}

function shouldCaptureDetectionScreenshots(details, settings) {
  if (!settings?.detectionScreenshotCapture) {
    return false;
  }
  const eventType = String(details?.eventType || "clickfix_alert").toLowerCase();
  if (eventType === "shadow_ai" || eventType === "unsafe_download") {
    return false;
  }
  return true;
}

async function optimizeDetectionScreenshot(dataUrl) {
  const raw = typeof dataUrl === "string" ? dataUrl : "";
  if (!raw) {
    return "";
  }
  let optimized = await compressImageDataUrl(raw, {
    maxWidth: 720,
    maxHeight: 480,
    quality: 0.58
  });
  if (dataUrlByteLength(optimized) > 240 * 1024) {
    optimized = await compressImageDataUrl(optimized, {
      maxWidth: 640,
      maxHeight: 420,
      quality: 0.45
    });
  }
  if (dataUrlByteLength(optimized) > 240 * 1024) {
    return "";
  }
  return optimized;
}

function getCachedBeforeScreenshot(tabId, pageUrl = "") {
  if (!Number.isInteger(tabId)) {
    return "";
  }
  const entry = beforeCaptureByTab.get(tabId);
  if (!entry || !entry.dataUrl) {
    return "";
  }
  const age = Date.now() - Number(entry.capturedAt || 0);
  if (age > BEFORE_CAPTURE_MAX_AGE_MS) {
    beforeCaptureByTab.delete(tabId);
    return "";
  }
  const currentHost = extractHostname(String(pageUrl || ""));
  if (currentHost && entry.hostname && currentHost !== entry.hostname) {
    return "";
  }
  return entry.dataUrl;
}

async function captureAndCacheBeforeScreenshot(tabId, pageUrl, captureWindowId = null) {
  if (!Number.isInteger(tabId) || !/^https?:/i.test(String(pageUrl || ""))) {
    return "";
  }
  const now = Date.now();
  const resolvedWindowId =
    Number.isInteger(captureWindowId) && captureWindowId >= 0
      ? captureWindowId
      : await resolveCaptureWindowId(tabId);
  const rawBefore = await captureVisibleTabDataUrl(resolvedWindowId, "jpeg", 54);
  const beforeScreenshot = await optimizeDetectionScreenshot(rawBefore);
  if (!beforeScreenshot) {
    return "";
  }
  beforeCaptureByTab.set(tabId, {
    url: pageUrl,
    hostname: extractHostname(pageUrl),
    dataUrl: beforeScreenshot,
    capturedAt: now
  });
  return beforeScreenshot;
}

async function waitForBeforeScreenshotCapture(tabId, pageUrl = "", timeoutMs = 900) {
  if (!Number.isInteger(tabId)) {
    return "";
  }
  const pendingCapture = beforeCapturePromiseByTab.get(tabId);
  if (!pendingCapture) {
    return getCachedBeforeScreenshot(tabId, pageUrl);
  }
  try {
    await Promise.race([pendingCapture, sleep(timeoutMs)]);
  } catch (error) {
    // Ignore capture wait errors.
  }
  return getCachedBeforeScreenshot(tabId, pageUrl);
}

function shouldForceBeforeCapture(phase = "") {
  const normalized = String(phase || "").toLowerCase();
  return normalized === "load_complete" || normalized === "tab_complete";
}

async function resolveTabActiveState(tabId, knownValue) {
  if (typeof knownValue === "boolean") {
    return knownValue;
  }
  if (!Number.isInteger(tabId)) {
    return false;
  }
  try {
    const tab = await new Promise((resolve) => {
      chrome.tabs.get(tabId, (result) => {
        if (chrome.runtime.lastError || !result) {
          resolve(null);
          return;
        }
        resolve(result);
      });
    });
    return Boolean(tab?.active);
  } catch (error) {
    return false;
  }
}

async function maybeCaptureBeforeScreenshotForTab(tabId, pageUrl, options = {}) {
  if (!Number.isInteger(tabId)) {
    return;
  }
  if (!/^https?:/i.test(String(pageUrl || ""))) {
    return;
  }
  if (options?.hasDetectionsOnPage) {
    return;
  }
  const settings = await getSettings();
  if (!settings?.detectionScreenshotCapture) {
    beforeCaptureByTab.delete(tabId);
    return;
  }
  const isActiveTab = await resolveTabActiveState(tabId, options?.active);
  if (!isActiveTab) {
    return;
  }
  const forceRefresh = shouldForceBeforeCapture(options?.capturePhase);
  if (beforeCaptureInFlight.has(tabId)) {
    return;
  }
  const existing = beforeCaptureByTab.get(tabId);
  if (
    !forceRefresh &&
    existing &&
    existing.url === pageUrl &&
    Date.now() - Number(existing.capturedAt || 0) < BEFORE_CAPTURE_REFRESH_MS
  ) {
    return;
  }
  const capturePromise = (async () => {
    beforeCaptureInFlight.add(tabId);
    try {
      await captureAndCacheBeforeScreenshot(tabId, pageUrl, options?.windowId ?? null);
    } catch (error) {
      // Ignore page-visit capture errors.
    } finally {
      beforeCaptureInFlight.delete(tabId);
    }
  })();
  beforeCapturePromiseByTab.set(tabId, capturePromise);
  try {
    await capturePromise;
  } finally {
    if (beforeCapturePromiseByTab.get(tabId) === capturePromise) {
      beforeCapturePromiseByTab.delete(tabId);
    }
  }
}

async function maybeCaptureBeforeScreenshotForPage(message, sender) {
  const tabId = Number.isInteger(sender?.tab?.id) ? sender.tab.id : null;
  if (!Number.isInteger(tabId)) {
    return;
  }
  const pageUrl = String(message?.url || sender?.tab?.url || "");
  await maybeCaptureBeforeScreenshotForTab(tabId, pageUrl, {
    capturePhase: String(message?.capturePhase || ""),
    active: typeof sender?.tab?.active === "boolean" ? sender.tab.active : undefined,
    windowId: Number.isInteger(sender?.tab?.windowId) ? sender.tab.windowId : null,
    hasDetectionsOnPage: Boolean(message?.hasDetectionsOnPage)
  });
}

async function triggerAlert(details) {
  await ensureLocaleReady();
  const scoreDetails = computeDetectionScore(details);
  const progressive = computeProgressiveBonus(details);
  const progressiveBonus = applyProgressiveBonus(scoreDetails, progressive);
  const settings = await getSettings();
  const listDecision =
    details.reportHostname === false ? null : await resolveListDecision(details.url);
  const runtimeVerdict =
    details.runtimeVerdict ||
    (settings.runtimeThreatScoring ? buildRuntimeThreatVerdict(details, listDecision) : null);
  const baseConfidenceScore = scoreDetails.total;
  const confidenceScore = runtimeVerdict
    ? clampScore(Math.max(baseConfidenceScore, Number(runtimeVerdict.total || 0)))
    : baseConfidenceScore;
  const runtimeSnippets = buildRuntimeVerdictSnippets(runtimeVerdict);
  const mergedSnippets = dedupeStrings([...(details.snippets || []), ...runtimeSnippets]);

  const alertSeverity = scoreToAlertSeverity(confidenceScore);
  const alertMinSeverity = normalizeAlertMinSeverity(settings.alertMinSeverity);
  const suppressAlertUi = shouldSuppressByAlertSeverity(confidenceScore, alertMinSeverity);
  const muteNotifications = Boolean(settings.muteDetectionNotifications);
  const shouldRenderAlertUi = !details.suppressNotification && !muteNotifications && !suppressAlertUi;
  console.debug("[ClickFix] triggerAlert start", {
    url: details.url,
    tabId: details.tabId,
    timestamp: details.timestamp,
    hasDetectedContent: Boolean(details.detectedContent),
    signals: {
      mismatch: details.mismatch,
      commandMatch: details.commandMatch,
      winRHint: details.winRHint,
      winXHint: details.winXHint,
      winXTerminalHint: details.winXTerminalHint,
      browserErrorHint: details.browserErrorHint,
      fixActionHint: details.fixActionHint,
      captchaHint: details.captchaHint,
      consoleHint: details.consoleHint,
      shellHint: details.shellHint,
      pasteSequenceHint: details.pasteSequenceHint,
      fileExplorerHint: details.fileExplorerHint,
      copyTriggerHint: details.copyTriggerHint,
      evasionHint: details.evasionHint,
      progressiveBonus,
      alertSeverity,
      alertMinSeverity,
      suppressAlertUi,
      confidenceScore,
      runtimeVerdict
    }
  });
  const detailsWithScore = {
    ...details,
    confidenceScore,
    scoreDetails,
    progressiveBonus,
    runtimeVerdict,
    snippets: mergedSnippets
  };
  if (details.incrementAlertCount !== false) {
    await incrementAlertCount();
  }
  if (details.incrementBlockCount !== false) {
    await incrementBlockCount();
  }
  const reasons = buildAlertReasons(detailsWithScore);
  const reasonsEs = buildAlertReasonsEs(detailsWithScore);
  const reasonEntries = buildAlertReasonEntries(detailsWithScore);
  const snippets = buildAlertSnippets(detailsWithScore);
  const message = reasons.join(" ");
  const messageEs = reasonsEs.join(" ");
  const hostname = extractHostname(details.url);
  const timestamp = new Date(details.timestamp).toISOString();
  const reportHostname = details.reportHostname === false ? "" : hostname;
  const reportUrl = details.reportHostname === false ? "" : details.url;
  const reportPreviousUrl =
    details.reportHostname === false ? "" : details.previousUrl || "";
  const allowlisted =
    details.reportHostname === false
      ? false
      : Boolean(listDecision ? listDecision.allowlisted : await isAllowlisted(details.url));
  const shouldBlockPage = !details.suppressPageBlock;
  const shouldCaptureScreenshots = shouldCaptureDetectionScreenshots(details, settings);
  const captureWindowId = shouldCaptureScreenshots
    ? await resolveCaptureWindowId(details.tabId)
    : chrome.windows.WINDOW_ID_CURRENT;
  let afterScreenshot = "";
  const allowClipboardRestore = details.allowClipboardRestore !== false;

  await saveHistory({
    message,
    reasonEntries,
    snippets,
    url: reportUrl,
    hostname: reportHostname || (details.reportHostname === false ? t("historyClipboardOnly") : hostname),
    timestamp,
    reportHostname: details.reportHostname !== false,
    confidenceScore,
    scoreDetails,
    detectedContent: details.detectedContent || ""
  });
  let queuedReport = {
    type: "alert",
    url: reportUrl,
    hostname: reportHostname,
    timestamp: details.timestamp,
    message,
    reason_entries: reasonEntries,
    snippets,
    detectedContent: details.detectedContent || "",
    full_context: details.fullContext || "",
    previous_url: reportPreviousUrl,
    clipboard_source: details.clipboardSource || null,
    event_type: details.eventType || "clickfix_alert",
    score_total: confidenceScore,
    score_details: scoreDetails,
    runtime_verdict: runtimeVerdict || null,
    signals: {
      mismatch: details.mismatch,
      commandMatch: details.commandMatch,
      winRHint: details.winRHint,
      winXHint: details.winXHint,
      winXTerminalHint: details.winXTerminalHint,
      browserErrorHint: details.browserErrorHint,
      fixActionHint: details.fixActionHint,
      captchaHint: details.captchaHint,
      consoleHint: details.consoleHint,
      shellHint: details.shellHint,
      pasteSequenceHint: details.pasteSequenceHint,
      fileExplorerHint: details.fileExplorerHint,
      copyTriggerHint: details.copyTriggerHint,
      evasionHint: details.evasionHint,
      runtimeLevel: runtimeVerdict?.level || "",
      runtimeScore: runtimeVerdict?.total || 0,
      confidenceScore
    },
    blocked: shouldBlockPage && !allowlisted
  };

  const iconUrl = chrome.runtime.getURL("icons/icon-128.png");

  const notificationId = !shouldRenderAlertUi
    ? null
    : await new Promise((resolve) => {
        chrome.notifications.create(
          {
            type: "basic",
            iconUrl,
            title: t("appName"),
            message,
            buttons: details.blockedClipboardText && allowClipboardRestore
              ? [
                  { title: t("notificationRestoreClipboard") },
                  { title: t("notificationKeepClean") }
                ]
              : undefined
          },
          (id) => resolve(id)
        );
      });

  const targetTabId = details.tabId;
    if (targetTabId) {
      if (shouldRenderAlertUi) {
        chrome.tabs.sendMessage(targetTabId, {
          type: "showBanner",
          text: message,
          reasonEntries,
          scoreDetails,
          confidenceScore,
          snippets
        });
      }
    if (shouldBlockPage && !allowlisted && !suppressAlertUi) {
      chrome.tabs.sendMessage(targetTabId, {
        type: "blockPage",
        hostname,
        reason: message,
        reasons,
        reasonEs: messageEs,
        reasonsEs,
        reasonEntries,
        contextText: details.detectedContent || "",
        snippets,
        scoreDetails
      });
      if (shouldCaptureScreenshots) {
        await sleep(1150);
        const rawAfter = await captureVisibleTabDataUrl(captureWindowId, "jpeg", 54);
        afterScreenshot = await optimizeDetectionScreenshot(rawAfter);
      }
    }
  } else {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      const tabId = tabs?.[0]?.id;
      if (tabId) {
        if (shouldRenderAlertUi) {
          chrome.tabs.sendMessage(tabId, {
            type: "showBanner",
            text: message,
            reasonEntries,
            scoreDetails,
            confidenceScore,
            snippets
          });
        }
        if (shouldBlockPage && !allowlisted && !suppressAlertUi) {
          chrome.tabs.sendMessage(tabId, {
            type: "blockPage",
            hostname,
            reason: message,
            reasons,
            reasonEs: messageEs,
            reasonsEs,
            reasonEntries,
            contextText: details.detectedContent || "",
            snippets
          });
        }
      }
    });
  }

  if (
    shouldCaptureScreenshots &&
    !afterScreenshot &&
    shouldBlockPage &&
    !allowlisted &&
    !suppressAlertUi
  ) {
    await sleep(1150);
    const rawAfter = await captureVisibleTabDataUrl(captureWindowId, "jpeg", 54);
    afterScreenshot = await optimizeDetectionScreenshot(rawAfter);
  }

  if (shouldCaptureScreenshots) {
    queuedReport = {
      ...queuedReport,
      scan_capture_opt_in: true,
      scan_before_image: null,
      scan_after_image: afterScreenshot || null
    };
  }
  enqueueReport(queuedReport);

  return notificationId;
}

function extractBase64Candidates(text) {
  if (!text) {
    return [];
  }
  const candidates = new Set();
  const addCandidate = (value) => {
    if (!value) {
      return;
    }
    const cleaned = value.replace(/=+$/, "");
    if (cleaned.length < 24 || cleaned.length % 4 === 1) {
      return;
    }
    candidates.add(value);
  };
  const matches = text.match(/[A-Za-z0-9+/=]{24,}/g) || [];
  matches.forEach((value) => addCandidate(value));
  const looseMatches =
    text.match(/(?:[A-Za-z0-9+/=][\"'`\s\u2018\u2019\u201C\u201D]{0,3}){30,}/g) || [];
  looseMatches.forEach((value) => {
    const normalized = value.replace(/[^A-Za-z0-9+/=]/g, "");
    addCandidate(normalized);
  });
  return [...candidates];
}

function decodeBase64Candidates(text) {
  const decoded = [];
  const candidates = extractBase64Candidates(text);
  candidates.forEach((value) => {
    try {
      const normalized = value.length % 4 === 0 ? value : `${value}==`.slice(0, 4 - (value.length % 4));
      const result = atob(normalized);
      if (result && /[\w\s]/.test(result)) {
        decoded.push(result);
      }
    } catch (error) {
      // Ignore invalid base64.
    }
  });
  return decoded;
}

function analyzeText(text) {
  const cleaned = String(text || "")
    .replace(ZERO_WIDTH_REGEX, "")
    .replace(CONTROL_CHAR_REGEX, " ")
    .replace(DASH_REGEX, "-");
  const trimmed = cleaned.trim();
  if (!trimmed) {
    return { commandMatch: false, shellHint: false, evasionHint: false };
  }
  const decodedChunks = decodeBase64Candidates(trimmed);
  const combined = [trimmed, ...decodedChunks].join("\n");
  const evasionHint = EVASION_REGEXES.some((regex) => regex.test(combined));
  return {
    commandMatch: COMMAND_REGEX.test(combined),
    shellHint: SHELL_HINT_REGEX.test(combined),
    evasionHint
  };
}

async function shouldIgnore(url) {
  const settings = await getSettings();
  if (!settings.enabled) {
    return true;
  }
  return false;
}

async function isAllowlisted(url) {
  const settings = await getSettings();
  const hostname = extractHostname(url);
  if (!hostname) {
    return false;
  }
  if (settings.whitelist.includes(hostname)) {
    return true;
  }
  const items = await getAllowlistItems();
  return items.some((entry) => matchesHostname(hostname, entry));
}

async function isBlocked(url) {
  const settings = await getSettings();
  const hostname = extractHostname(url);
  if (!hostname || settings.whitelist.includes(hostname)) {
    return false;
  }
  return settings.blocklist.some((entry) => matchesHostname(hostname, entry));
}

async function analyzeDownloadRisk(downloadItem) {
  const url = String(downloadItem?.finalUrl || downloadItem?.url || "");
  const filename = normalizeDownloadFilename(downloadItem?.filename || downloadItem?.suggestedFilename || "");
  const hostname = extractHostname(url);
  const extension = extractFileExtension(filename);
  const snippets = [];
  let score = 0;

  if (!url || !hostname) {
    return {
      unsafe: false,
      score: 0,
      url,
      hostname,
      filename,
      snippets,
      signals: {}
    };
  }

  if (DOWNLOAD_DANGEROUS_EXTENSIONS.has(extension)) {
    score += DOWNLOAD_SCRIPT_EXTENSIONS.has(extension) ? 38 : 28;
    snippets.push(`Blocked extension: .${extension}`);
  }
  if (DOWNLOAD_DOUBLE_EXTENSION_REGEX.test(filename)) {
    score += 24;
    snippets.push("Double file extension indicates masquerading.");
  }
  if (url.startsWith("http://")) {
    score += 10;
    snippets.push("Download served over insecure HTTP.");
  }
  if (isIpLikeHostname(hostname)) {
    score += 12;
    snippets.push("Download host is an IP address.");
  }
  if (!isTrustedDownloadHost(hostname)) {
    score += 15;
    snippets.push(`Unfamiliar download host: ${hostname}`);
  } else {
    score -= 10;
  }

  const listDecision = await resolveListDecision(url);
  if (listDecision.blocked && !listDecision.allowlisted) {
    score += 40;
    snippets.push("Host matched blocklist.");
  }
  if (listDecision.allowlisted) {
    score -= 20;
  }

  const normalizedScore = Math.max(0, Math.min(100, score));
  const unsafe = normalizedScore >= 45;
  return {
    unsafe,
    score: normalizedScore,
    url,
    hostname,
    filename,
    snippets: dedupeStrings(snippets),
    signals: {
      dangerousExtension: DOWNLOAD_DANGEROUS_EXTENSIONS.has(extension),
      scriptExtension: DOWNLOAD_SCRIPT_EXTENSIONS.has(extension),
      doubleExtension: DOWNLOAD_DOUBLE_EXTENSION_REGEX.test(filename),
      insecureTransport: url.startsWith("http://"),
      ipHost: isIpLikeHostname(hostname),
      unfamiliarHost: !isTrustedDownloadHost(hostname),
      blocklistedHost: listDecision.blocked && !listDecision.allowlisted
    }
  };
}

function cancelDownload(downloadId) {
  return new Promise((resolve) => {
    if (!chrome?.downloads?.cancel) {
      resolve(false);
      return;
    }
    chrome.downloads.cancel(downloadId, () => {
      if (chrome.runtime.lastError) {
        resolve(false);
        return;
      }
      resolve(true);
    });
  });
}

async function handleDownloadCreated(downloadItem) {
  const settings = await getSettings();
  if (!settings.enabled || !settings.blockUnsafeDownloads) {
    return;
  }
  const risk = await analyzeDownloadRisk(downloadItem);
  if (!risk.unsafe) {
    return;
  }

  await cancelDownload(downloadItem.id);
  const snippets = dedupeStrings([
    `Unsafe download blocked: ${risk.filename || risk.url}`,
    ...risk.snippets
  ]);
  const runtimeVerdict = {
    total: risk.score,
    level: risk.score >= 65 ? "unsafe" : "suspicious",
    models: {
      url: risk.signals.insecureTransport || risk.signals.ipHost ? 14 : 0,
      content: risk.signals.dangerousExtension ? 18 : 0,
      reputation: risk.signals.blocklistedHost ? 35 : 0,
      brand: 0,
      vision: risk.signals.doubleExtension ? 14 : 0
    },
    reasons: snippets.slice(0, 4)
  };

  await triggerAlert({
    url: risk.url,
    timestamp: Date.now(),
    mismatch: false,
    commandMatch: Boolean(risk.signals.scriptExtension),
    winRHint: false,
    winXHint: false,
    winXTerminalHint: false,
    browserErrorHint: false,
    fixActionHint: false,
    captchaHint: false,
    consoleHint: false,
    shellHint: Boolean(risk.signals.scriptExtension),
    pasteSequenceHint: false,
    fileExplorerHint: false,
    copyTriggerHint: false,
    evasionHint: Boolean(risk.signals.doubleExtension),
    clipboardWarning: false,
    snippets,
    blockedClipboardText: "",
    detectedContent: risk.filename || risk.url,
    fullContext: JSON.stringify({
      downloadRisk: risk.signals,
      filename: risk.filename,
      download_filename: risk.filename,
      download_url: risk.url,
      download_host: risk.hostname,
      download_path: String(downloadItem?.filename || "")
    }),
    previousUrl: "",
    clipboardAnalysis: null,
    context: {
      source: "download",
      hostname: risk.hostname
    },
    tabId: Number.isInteger(downloadItem?.tabId) ? downloadItem.tabId : null,
    incrementBlockCount: true,
    allowClipboardRestore: false,
    suppressPageBlock: true,
    reportHostname: true,
    eventType: "unsafe_download",
    downloadRisk: risk,
    runtimeVerdict
  });
}

async function addToWhitelist(hostname) {
  if (!hostname) {
    return;
  }
  const settings = await getSettings();
  if (settings.whitelist.includes(hostname)) {
    return;
  }
  const whitelist = [...settings.whitelist, hostname];
  await chrome.storage.local.set({ whitelist });
}

chrome.runtime.onInstalled.addListener(() => {
  refreshBlocklist();
  refreshAllowlist();
  refreshServerScoreConfig();
  refreshExtensionMessages();
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: LIST_REFRESH_MINUTES });
  chrome.alarms.create("clickfix-reports", { periodInMinutes: REPORT_FLUSH_MINUTES });
  restoreReportQueue();
});

chrome.runtime.onStartup.addListener(() => {
  refreshBlocklist();
  refreshAllowlist();
  refreshServerScoreConfig();
  refreshExtensionMessages();
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: LIST_REFRESH_MINUTES });
  chrome.alarms.create("clickfix-reports", { periodInMinutes: REPORT_FLUSH_MINUTES });
  restoreReportQueue();
});

if (chrome?.downloads?.onCreated) {
  chrome.downloads.onCreated.addListener((downloadItem) => {
    handleDownloadCreated(downloadItem).catch(() => undefined);
  });
}

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "clickfix-refresh") {
    refreshBlocklist();
    refreshAllowlist();
    refreshServerScoreConfig();
    refreshExtensionMessages();
  }
  if (alarm.name === "clickfix-reports") {
    flushReportQueue();
  }
});

if (chrome?.tabs?.onRemoved) {
  chrome.tabs.onRemoved.addListener((tabId) => {
    cleanupTabTransientState(tabId);
  });
}

if (chrome?.tabs?.onUpdated) {
  chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
    if (!tab || changeInfo?.status !== "complete") {
      return;
    }
    const pageUrl = String(tab.url || "");
    if (!/^https?:/i.test(pageUrl)) {
      return;
    }
    if (isClickFixDisabledUrl(pageUrl)) {
      return;
    }
  });
}

let lastPageHint = null;
let lastClipboardBlock = { text: "", timestamp: 0 };
const blockedClipboardByNotification = new Map();

async function getBlocklistItems() {
  const now = Date.now();
  if (blocklistCache.items.length && now - blocklistCache.fetchedAt < BLOCKLIST_CACHE_MS) {
    return blocklistCache.items;
  }
  const settings = await getSettings();
  if (settings.blocklist.length) {
    blocklistCache = { items: settings.blocklist, fetchedAt: now };
    return settings.blocklist;
  }
  return blocklistCache.items;
}

async function getAllowlistItems() {
  const now = Date.now();
  if (allowlistCache.items.length && now - allowlistCache.fetchedAt < BLOCKLIST_CACHE_MS) {
    return allowlistCache.items;
  }
  const settings = await getSettings();
  if (settings.allowlist.length) {
    allowlistCache = { items: settings.allowlist, fetchedAt: now };
    return settings.allowlist;
  }
  return allowlistCache.items;
}

async function resolveListDecision(url) {
  const hostname = extractHostname(url);
  if (!hostname) {
    return { hostname: "", allowlisted: false, blocked: false, conflict: false };
  }
  const allowlisted = await isAllowlisted(url);
  const items = await getBlocklistItems();
  const blocked = items.some((entry) => entry === hostname || hostname.endsWith(`.${entry}`));
  const conflict = allowlisted && blocked;
  if (conflict) {
    console.warn("[ClickFix] List conflict detected", { hostname, url });
  }
  return { hostname, allowlisted, blocked, conflict };
}

async function sendReport(details) {
  if (!isTrustedReportEndpoint(CLICKFIX_REPORT_URL)) {
    return false;
  }
  try {
    const token = await ensureApiAccessToken();
    const headers = { "Content-Type": "application/json" };
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
    let response = await fetch(CLICKFIX_REPORT_URL, {
      method: "POST",
      headers,
      body: JSON.stringify(details)
    });
    if (response.status === 401 && token) {
      await chrome.storage.local.set({
        apiAccessToken: "",
        apiAccessTokenExpiresAt: 0
      });
      const retryToken = await ensureApiAccessToken();
      const retryHeaders = { "Content-Type": "application/json" };
      if (retryToken) {
        retryHeaders.Authorization = `Bearer ${retryToken}`;
      }
      response = await fetch(CLICKFIX_REPORT_URL, {
        method: "POST",
        headers: retryHeaders,
        body: JSON.stringify(details)
      });
    }
    return response.ok;
  } catch (error) {
    // Ignore reporting errors to avoid breaking the user flow.
  }
  return false;
}

async function sendStatsReport() {
  const settings = await getSettings();
  const alertSites = buildAlertSites(settings.history ?? []);
  const country = settings.sendCountry ? chrome.i18n.getUILanguage() : "";
  const installInfo = await getInstallInfo();
  const clientId = await getClientId();
  enqueueReport({
    type: "stats",
    timestamp: Date.now(),
    client_id: clientId,
    data: {
      enabled: settings.enabled ?? true,
      manualSites: settings.whitelist ?? [],
      alertSites,
      alertCount: settings.alertCount ?? 0,
      blockCount: settings.blockCount ?? 0,
      country,
      installType: installInfo.installType || "",
      installSource: installInfo.installSource || "",
      installChannel: installInfo.installChannel || "",
      extensionDistribution: installInfo.extensionDistribution || "",
      clientId
    }
  });
}

function stableStringify(value) {
  if (Array.isArray(value)) {
    return `[${value.map((item) => stableStringify(item)).join(",")}]`;
  }
  if (value && typeof value === "object") {
    const keys = Object.keys(value).sort();
    return `{${keys.map((key) => `"${key}":${stableStringify(value[key])}`).join(",")}}`;
  }
  return JSON.stringify(value ?? null);
}

function cleanReportHashes(now) {
  for (const [hash, timestamp] of reportHashes.entries()) {
    if (now - timestamp > REPORT_DEDUPE_WINDOW_MS) {
      reportHashes.delete(hash);
    }
  }
}

function buildReportHash(details) {
  const normalized = { ...details };
  const timestamp =
    typeof normalized.timestamp === "number" ? normalized.timestamp : Date.now();
  normalized.timestamp_bucket = Math.floor(
    timestamp / (REPORT_FLUSH_MINUTES * 60 * 1000)
  );
  delete normalized.timestamp;
  delete normalized.scan_before_image;
  delete normalized.scan_after_image;
  return stableStringify(normalized);
}

async function restoreReportQueue() {
  const settings = await getSettings();
  reportQueue = Array.isArray(settings.reportQueue) ? settings.reportQueue : [];
  const now = Date.now();
  reportQueue.forEach((entry) => {
    reportHashes.set(buildReportHash(entry), now);
  });
}

async function persistReportQueue() {
  await chrome.storage.local.set({ reportQueue });
}

async function enqueueReport(details) {
  const clientId = await getClientId();
  const installInfo = await getInstallInfo();
  const withInstall =
    details?.type === "stats"
      ? details
      : {
          ...details,
          installType: details.installType ?? installInfo.installType ?? "",
          installSource: details.installSource ?? installInfo.installSource ?? "",
          installChannel: details.installChannel ?? installInfo.installChannel ?? "",
          extensionDistribution:
            details.extensionDistribution ?? installInfo.extensionDistribution ?? "",
          client_id: details.client_id ?? clientId
        };
  const now = Date.now();
  cleanReportHashes(now);
  const hash = buildReportHash(withInstall);
  if (reportHashes.has(hash)) {
    return;
  }
  reportHashes.set(hash, now);
  reportQueue.push(withInstall);
  if (reportQueue.length > REPORT_QUEUE_LIMIT) {
    reportQueue = reportQueue.slice(-REPORT_QUEUE_LIMIT);
  }
  await persistReportQueue();
}

async function flushReportQueue() {
  if (!reportQueue.length) {
    await restoreReportQueue();
  }
  if (!reportQueue.length) {
    await sendStatsReport();
    return;
  }
  const pending = [...reportQueue];
  const remaining = [];
  for (const entry of pending) {
    const ok = await sendReport(entry);
    if (!ok) {
      remaining.push(entry);
    }
  }
  reportQueue = remaining;
  await persistReportQueue();
  await sendStatsReport();
}

function buildAlertSites(history) {
  const sites = [];
  const seen = new Set();
  if (!Array.isArray(history)) {
    return sites;
  }
  for (const entry of history) {
    if (entry?.reportHostname === false) {
      continue;
    }
    const hostname = typeof entry?.hostname === "string" ? entry.hostname.trim() : "";
    if (!hostname || seen.has(hostname)) {
      continue;
    }
    seen.add(hostname);
    sites.push(hostname);
    if (sites.length >= 200) {
      break;
    }
  }
  return sites;
}

function shouldThrottleClipboardBlock(text) {
  return (
    lastClipboardBlock.text === text &&
    Date.now() - lastClipboardBlock.timestamp < CLIPBOARD_THROTTLE_MS
  );
}

function setClipboardBlock(text) {
  lastClipboardBlock = { text, timestamp: Date.now() };
}

function requestClipboardReplace(tabId, text) {
  if (!tabId) {
    return;
  }
  chrome.tabs.sendMessage(tabId, {
    type: "replaceClipboard",
    text
  });
}

function requestClipboardRestore(tabId, text) {
  if (!tabId) {
    return;
  }
  chrome.tabs.sendMessage(tabId, {
    type: "restoreClipboard",
    text
  });
}

chrome.notifications.onButtonClicked.addListener((notificationId, buttonIndex) => {
  const entry = blockedClipboardByNotification.get(notificationId);
  if (!entry) {
    return;
  }
  if (buttonIndex === 0) {
    requestClipboardRestore(entry.tabId, entry.text);
  }
  blockedClipboardByNotification.delete(notificationId);
});

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || !message.type) {
    return;
  }

  if (message.type === "pageVisit") {
    if (isClickFixDisabledUrl(message.url || sender?.tab?.url || "")) {
      return;
    }
    return;
  }

  if (message.type === "checkBlocklist") {
    (async () => {
      const decision = await resolveListDecision(message.url);
      sendResponse({
        blocked: decision.blocked && !decision.allowlisted,
        hostname: decision.hostname,
        allowlisted: decision.allowlisted,
        conflict: decision.conflict
      });
    })();
    return true;
  }

  if (message.type === "checkAllowlist") {
    (async () => {
      const decision = await resolveListDecision(message.url);
      sendResponse({ allowlisted: decision.allowlisted, conflict: decision.conflict });
    })();
    return true;
  }

  if (message.type === "allowSite") {
    (async () => {
      await addToWhitelist(message.hostname);
      sendResponse({ ok: true });
    })();
    return true;
  }

  if (message.type === "setTabScriptBlocking") {
    (async () => {
      const tabId = Number(sender?.tab?.id ?? message.tabId ?? -1);
      const enabled = Boolean(message.enabled);
      const hostname = String(message.hostname || message.url || "");
      const ok = await setTabScriptBlocking(tabId, hostname, enabled);
      sendResponse({ ok });
    })();
    return true;
  }

  if (message.type === "manualReport") {
    (async () => {
      if (isClickFixDisabledUrl(message.url || sender?.tab?.url || "")) {
        return;
      }
      await ensureLocaleReady();
      enqueueReport({
        url: message.url,
        hostname: message.hostname || extractHostname(message.url),
        timestamp: message.timestamp ?? Date.now(),
        reason: t("manualReportReason"),
        blocked: true,
        event_type: "manual_report",
        manualReport: true,
        detectedContent: "",
        previous_url: message.previousUrl || ""
      });
    })();
    return;
  }

  if (message.type === "blocklisted") {
    incrementBlockCount();
    return;
  }

  if (message.type === "pageHint" && message.hint) {
    if (!lastPageHint || lastPageHint.url !== message.url) {
      lastPageHint = { url: message.url, hints: [], snippets: [] };
    }
    if (!lastPageHint.hints.includes(message.hint)) {
      lastPageHint.hints.push(message.hint);
    }
    if (message.snippet && !lastPageHint.snippets.includes(message.snippet)) {
      lastPageHint.snippets.push(message.snippet);
    }
    return;
  }

  if (message.type === "pageAlert" && message.alertType) {
    if (isClickFixDisabledUrl(message.url || sender?.tab?.url || "")) {
      return;
    }
    queueBatchedPageAlert(message, sender);
    return;
  }

  if (message.type === "shadowAIEvent") {
    (async () => {
      if (isClickFixDisabledUrl(message.url || sender?.tab?.url || "")) {
        return;
      }
      const settings = await getSettings();
      if (!settings.enabled || !settings.shadowAiMonitoring) {
        return;
      }
      const url = String(message.url || sender?.tab?.url || "");
      const hostname = extractHostname(url);
      if (!hostname) {
        return;
      }
      const aiContext = Boolean(message.isAiContext || isShadowAiHost(hostname));
      if (!aiContext) {
        return;
      }

      const action = String(message.action || "interaction").toLowerCase();
      const promptSnippet = String(message.snippet || "").slice(0, 220);
      const uploadInfo = Array.isArray(message.fileNames)
        ? `Files: ${message.fileNames.slice(0, 4).join(", ")}`
        : "";
      const baseSnippet =
        action === "file_upload"
          ? `Shadow AI upload detected on ${hostname}.`
          : `Shadow AI prompt/paste detected on ${hostname}.`;
      const snippets = dedupeStrings([baseSnippet, promptSnippet, uploadInfo]);
      const runtimeVerdict = {
        total: action === "file_upload" ? 42 : 32,
        level: action === "file_upload" ? "suspicious" : "low",
        models: {
          url: 4,
          content: action === "file_upload" ? 16 : 10,
          reputation: 8,
          brand: 0,
          vision: 0
        },
        reasons: snippets.slice(0, 4)
      };

      await triggerAlert({
        url,
        timestamp: message.timestamp ?? Date.now(),
        mismatch: false,
        commandMatch: false,
        shellHint: false,
        evasionHint: false,
        clipboardWarning: action !== "file_upload",
        winRHint: false,
        winXHint: false,
        winXTerminalHint: false,
        browserErrorHint: false,
        fixActionHint: false,
        captchaHint: false,
        consoleHint: false,
        pasteSequenceHint: false,
        fileExplorerHint: false,
        copyTriggerHint: false,
        snippets,
        blockedClipboardText: "",
        detectedContent: promptSnippet || snippets[0] || "",
        fullContext: JSON.stringify({
          action,
          promptLength: Number(message.promptLength || 0),
          fileCount: Number(message.fileCount || 0),
          fileNames: Array.isArray(message.fileNames) ? message.fileNames.slice(0, 10) : []
        }),
        previousUrl: message.previousUrl || "",
        clipboardAnalysis: null,
        context: {
          source: "shadow_ai",
          action,
          host: hostname
        },
        tabId: sender?.tab?.id ?? null,
        incrementAlertCount: settings.shadowAiWarnUser,
        incrementBlockCount: false,
        allowClipboardRestore: false,
        suppressPageBlock: true,
        reportHostname: true,
        eventType: "shadow_ai",
        suppressNotification: !settings.shadowAiWarnUser,
        runtimeVerdict
      });
    })();
    return;
  }

  if (message.type === "clipboardIncident") {
    (async () => {
      if (await shouldIgnore(message.url)) {
        return;
      }

      const analysis = message.analysis || {};
      const snippet = message.snippet || "";
      const detectedContent = message.detectedContent || snippet;

      const notificationId = await triggerAlert({
        url: message.url,
        timestamp: message.timestamp ?? Date.now(),
        mismatch: false,
        commandMatch: Boolean(analysis.commandMatch ?? analysis.hasCommand),
        shellHint: Boolean(analysis.shellHint ?? analysis.hasExecutionHint),
        evasionHint: Boolean(
          analysis.evasionHint ??
          analysis.hasBase64 ??
          analysis.hasHighEntropy
        ),
        clipboardWarning: true,
        winRHint: false,
        winXHint: false,
        winXTerminalHint: false,
        browserErrorHint: false,
        fixActionHint: false,
        captchaHint: false,
        consoleHint: false,
        pasteSequenceHint: false,
        fileExplorerHint: false,
        copyTriggerHint: false,
        snippets: snippet ? [snippet] : [],
        blockedClipboardText: message.blocked ? snippet : "",
        detectedContent,
        fullContext: message.fullContext || "",
        previousUrl: message.previousUrl || "",
        clipboardSource: message.source || null,
        clipboardAnalysis: analysis,
        context: message.context || message.source || null,
        tabId: sender?.tab?.id ?? null,
        incrementBlockCount: Boolean(message.blocked),
        allowClipboardRestore: false,
        suppressPageBlock: true,
        reportHostname: true
      });

      if (notificationId) {
        lastPageHint = null;
      }
    })();
    return;
  }

  if (message.type === "clipboardEvent") {
    (async () => {
      if (await shouldIgnore(message.url)) {
        return;
      }

      const selectionText = message.selectionText || "";
      const clipboardText = message.clipboardText || "";
      const incomingAnalysis = message.analysis || null;
      const clipboardAnalysis = incomingAnalysis || analyzeText(clipboardText);
      const commandMatch = Boolean(
        clipboardAnalysis.commandMatch ?? clipboardAnalysis.hasCommand
      );
      const shellHint = Boolean(
        clipboardAnalysis.shellHint ?? clipboardAnalysis.hasExecutionHint
      );
      const evasionHint = Boolean(
        clipboardAnalysis.evasionHint ??
          clipboardAnalysis.hasBase64 ??
          clipboardAnalysis.hasHighEntropy
      );
      const mismatch =
        message.eventType === "copy" &&
        message.clipboardAvailable &&
        selectionText &&
        clipboardText &&
        selectionText.trim() !== clipboardText.trim();

      const clipboardSignals = commandMatch || shellHint || evasionHint;
      const commandMatchOrHint = commandMatch || shellHint;
      const isClipboardWatch = message.eventType === "clipboard-watch";
      const isPaste = message.eventType === "paste";
      const isCopy = message.eventType === "copy";
      const shouldAlert =
        (isCopy && mismatch) ||
        ((isPaste || isClipboardWatch) && clipboardSignals);
      const clipboardWarningOnly = clipboardSignals && !mismatch;

      if (shouldAlert) {
        const settings = await getSettings();
        const saveClipboardBackup = settings.saveClipboardBackup ?? true;
        const trimmedClipboard = clipboardText.trim();
        const detectedContent = trimmedClipboard || selectionText || "";
        const snippet = detectedContent ? detectedContent.slice(0, 200) : "";
        const snippets = snippet ? [snippet] : [];
        const shouldBlockClipboard =
          isClipboardWatch &&
          trimmedClipboard &&
          clipboardSignals;

        if (shouldBlockClipboard && shouldThrottleClipboardBlock(trimmedClipboard)) {
          return;
        }

        let blockedClipboardText = "";
        if (shouldBlockClipboard) {
          setClipboardBlock(trimmedClipboard);
          if (saveClipboardBackup) {
            blockedClipboardText = trimmedClipboard;
            await addClipboardBackupEntry({
              text: trimmedClipboard,
              url: message.url,
              malicious: true
            });
          }
          requestClipboardReplace(sender?.tab?.id, "");
        }

        const notificationId = await triggerAlert({
          url: message.url,
          timestamp: message.timestamp,
          mismatch,
          commandMatch: commandMatchOrHint,
          winRHint: false,
          winXHint: false,
          winXTerminalHint: false,
          browserErrorHint: false,
          fixActionHint: false,
          captchaHint: false,
          consoleHint: false,
          shellHint: false,
          pasteSequenceHint: false,
          fileExplorerHint: false,
          copyTriggerHint: false,
          evasionHint,
          snippets,
          clipboardWarning: clipboardWarningOnly,
          blockedClipboardText,
          detectedContent,
          fullContext: message.fullContext || "",
          previousUrl: message.previousUrl || "",
          clipboardAnalysis,
          context: message.context || null,
          tabId: sender?.tab?.id ?? null,
          incrementBlockCount: true,
          reportHostname: true
        });

        if (blockedClipboardText && notificationId) {
          blockedClipboardByNotification.set(notificationId, {
            text: blockedClipboardText,
            tabId: sender?.tab?.id ?? null
          });
        }
        lastPageHint = null;
      }
    })();
  }
});
