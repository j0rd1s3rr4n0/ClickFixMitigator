const DEFAULT_SETTINGS = {
  enabled: true,
  blockAllClipboard: true,
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
  reportQueue: [],
  alertCount: 0,
  blockCount: 0,
  blocklistUpdatedAt: 0,
  allowlistUpdatedAt: 0
};

const CLICKFIX_BLOCKLIST_URL = "https://jordiserrano.me/ClickFix/clickfixlist";
const CLICKFIX_ALLOWLIST_URL = "https://jordiserrano.me/ClickFix/clickfixallowlist";
const CLICKFIX_REPORT_URL = "https://jordiserrano.me/ClickFix/clickfix-report.php";
const LIST_REFRESH_MINUTES = 3;
const REPORT_FLUSH_MINUTES = 5;
const REPORT_DEDUPE_WINDOW_MS = 15 * 60 * 1000;
const REPORT_QUEUE_LIMIT = 300;
const CLIPBOARD_BACKUP_LIMIT = 50;
const CLICKFIX_ICON_DATA_URL = "data:image/png;base64,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";

const COMMAND_REGEX =
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|bash|sh|zsh|curl|wget|rundll32|regsvr32|msbuild|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic|explorer(\.exe)?|reg\s+add|p[\s^`]*o[\s^`]*w[\s^`]*e[\s^`]*r[\s^`]*s[\s^`]*h[\s^`]*e[\s^`]*l[\s^`]*l|c[\s^`]*m[\s^`]*d)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|invoke-restmethod|\biwr\b|\birm\b|curl\s+|wget\s+|downloadstring|frombase64string|start-bitstransfer|add-mppreference|invoke-expression|\biex\b|\biex\s*\(|encodedcommand|\-enc\b|\-encodedcommand\b|powershell\s+\-|cmd\s+\/c|bash\s+\-c|sh\s+\-c|rundll32\s+[^\s,]+,[^\s]+|regsvr32\s+\/i|certutil\s+\-urlcache|bitsadmin\s+\/transfer)/i;
const EVASION_REGEXES = [
  /\\x[0-9a-f]{2}/i,
  /\\u[0-9a-f]{4}/i,
  /%[0-9a-f]{2}/i,
  /[\^`]{2,}/,
  /[A-Za-z0-9+/]{80,}={0,2}/
];
const CLIPBOARD_SNIPPET_LIMIT = 160;
const CLIPBOARD_THROTTLE_MS = 30000;
const BLOCKLIST_CACHE_MS = 10 * 60 * 1000;
const FULL_CONTEXT_LIMIT = 40000;

async function getSettings() {
  const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: stored.enabled ?? true,
    blockAllClipboard: stored.blockAllClipboard ?? true,
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
    reportQueue: stored.reportQueue ?? [],
    alertCount: stored.alertCount ?? 0,
    blockCount: stored.blockCount ?? 0,
    blocklistUpdatedAt: stored.blocklistUpdatedAt ?? 0,
    allowlistUpdatedAt: stored.allowlistUpdatedAt ?? 0
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
  if (area === "local" && changes.uiLanguage) {
    localeReady = initLocale();
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

function computeDetectionScore(details) {
  const weights = [
    ["commandMatch", 35],
    ["shellHint", 20],
    ["evasionHint", 15],
    ["mismatch", 10],
    ["clipboardWarning", 10],
    ["winRHint", 10],
    ["winXHint", 10],
    ["pasteSequenceHint", 10],
    ["consoleHint", 8],
    ["fileExplorerHint", 6],
    ["copyTriggerHint", 6],
    ["browserErrorHint", 5],
    ["fixActionHint", 5],
    ["captchaHint", 5]
  ];
  const score = weights.reduce((total, [key, weight]) => {
    return details[key] ? total + weight : total;
  }, 0);
  return Math.max(0, Math.min(100, Math.round(score)));
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

function formatAlertMessage(parts) {
  if (!Array.isArray(parts) || parts.length === 0) {
    return "";
  }
  if (parts.length === 1) {
    return parts[0];
  }
  return parts.map((part) => `• ${part}`).join("\n");
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
  return formatAlertMessage(buildAlertReasons(details));
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

async function triggerAlert(details) {
  await ensureLocaleReady();
  const confidenceScore = computeDetectionScore(details);
  const settings = await getSettings();
  const muteNotifications = Boolean(settings.muteDetectionNotifications);
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
      browserErrorHint: details.browserErrorHint,
      fixActionHint: details.fixActionHint,
      captchaHint: details.captchaHint,
      consoleHint: details.consoleHint,
      shellHint: details.shellHint,
      pasteSequenceHint: details.pasteSequenceHint,
      fileExplorerHint: details.fileExplorerHint,
      copyTriggerHint: details.copyTriggerHint,
      evasionHint: details.evasionHint,
      confidenceScore
    }
  });
  const detailsWithScore = { ...details, confidenceScore };
  await incrementAlertCount();
  if (details.incrementBlockCount !== false) {
    await incrementBlockCount();
  }
  const reasons = buildAlertReasons(detailsWithScore);
  const reasonsEs = buildAlertReasonsEs(detailsWithScore);
  const reasonEntries = buildAlertReasonEntries(detailsWithScore);
  const snippets = buildAlertSnippets(detailsWithScore);
  const message = formatAlertMessage(reasons);
  const messageEs = formatAlertMessage(reasonsEs);
  const hostname = extractHostname(details.url);
  const timestamp = new Date(details.timestamp).toISOString();
  const reportHostname = details.reportHostname === false ? "" : hostname;
  const reportUrl = details.reportHostname === false ? "" : details.url;
  const reportPreviousUrl =
    details.reportHostname === false ? "" : details.previousUrl || "";
  const allowlisted = await isAllowlisted(details.url);
  const shouldBlockPage = !details.suppressPageBlock;
  const allowClipboardRestore = details.allowClipboardRestore !== false;

  await saveHistory({
    message,
    url: reportUrl,
    hostname: reportHostname || (details.reportHostname === false ? t("historyClipboardOnly") : hostname),
    timestamp,
    reportHostname: details.reportHostname !== false,
    reasonEntries
  });
  enqueueReport({
    type: "alert",
    url: reportUrl,
    hostname: reportHostname,
    timestamp: details.timestamp,
    message,
    detectedContent: details.detectedContent || "",
    full_context: details.fullContext || "",
    previous_url: reportPreviousUrl,
    clipboard_source: details.clipboardSource || null,
    signals: {
      mismatch: details.mismatch,
      commandMatch: details.commandMatch,
      winRHint: details.winRHint,
      winXHint: details.winXHint,
      browserErrorHint: details.browserErrorHint,
      fixActionHint: details.fixActionHint,
      captchaHint: details.captchaHint,
      consoleHint: details.consoleHint,
      shellHint: details.shellHint,
      pasteSequenceHint: details.pasteSequenceHint,
      fileExplorerHint: details.fileExplorerHint,
      copyTriggerHint: details.copyTriggerHint,
      evasionHint: details.evasionHint,
      confidenceScore
    },
    blocked: shouldBlockPage && !allowlisted
  });

  const iconUrl = CLICKFIX_ICON_DATA_URL;

  const notificationId = muteNotifications
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
    if (!muteNotifications) {
      chrome.tabs.sendMessage(targetTabId, {
        type: "showBanner",
        text: message
      });
    }
    if (shouldBlockPage && !allowlisted) {
      chrome.tabs.sendMessage(targetTabId, {
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
  } else {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      const tabId = tabs?.[0]?.id;
      if (tabId) {
        if (!muteNotifications) {
          chrome.tabs.sendMessage(tabId, {
            type: "showBanner",
            text: message
          });
        }
        if (shouldBlockPage && !allowlisted) {
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

  return notificationId;
}

function extractBase64Candidates(text) {
  if (!text) {
    return [];
  }
  const candidates = new Set();
  const matches = text.match(/[A-Za-z0-9+/=]{24,}/g) || [];
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
  const trimmed = text?.trim();
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
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: LIST_REFRESH_MINUTES });
  chrome.alarms.create("clickfix-reports", { periodInMinutes: REPORT_FLUSH_MINUTES });
  restoreReportQueue();
});

chrome.runtime.onStartup.addListener(() => {
  refreshBlocklist();
  refreshAllowlist();
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: LIST_REFRESH_MINUTES });
  chrome.alarms.create("clickfix-reports", { periodInMinutes: REPORT_FLUSH_MINUTES });
  restoreReportQueue();
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "clickfix-refresh") {
    refreshBlocklist();
    refreshAllowlist();
  }
  if (alarm.name === "clickfix-reports") {
    flushReportQueue();
  }
});

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
  try {
    await fetch(CLICKFIX_REPORT_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(details)
    });
    return true;
  } catch (error) {
    // Ignore reporting errors to avoid breaking the user flow.
  }
  return false;
}

async function sendStatsReport() {
  const settings = await getSettings();
  const alertSites = buildAlertSites(settings.history ?? []);
  const country = settings.sendCountry ? chrome.i18n.getUILanguage() : "";
  enqueueReport({
    type: "stats",
    timestamp: Date.now(),
    data: {
      enabled: settings.enabled ?? true,
      manualSites: settings.whitelist ?? [],
      alertSites,
      alertCount: settings.alertCount ?? 0,
      blockCount: settings.blockCount ?? 0,
      country
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
  const now = Date.now();
  cleanReportHashes(now);
  const hash = buildReportHash(details);
  if (reportHashes.has(hash)) {
    return;
  }
  reportHashes.set(hash, now);
  reportQueue.push(details);
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

  if (message.type === "manualReport") {
    (async () => {
      await ensureLocaleReady();
      const rawReason = typeof message.reason === "string" ? message.reason.trim() : "";
      const cleanReason = rawReason.replace(/\s+/g, " ").slice(0, 160);
      const reasonLabel = t("manualReportReason");
      const reasonText = cleanReason ? `${reasonLabel}: ${cleanReason}` : reasonLabel;
      enqueueReport({
        url: message.url,
        hostname: message.hostname || extractHostname(message.url),
        timestamp: message.timestamp ?? Date.now(),
        reason: reasonText,
        message: reasonText,
        blocked: true,
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
    (async () => {
      if (await shouldIgnore(message.url)) {
        return;
      }
      const snippets = message.snippet ? [message.snippet] : [];
      const notificationId = await triggerAlert({
        url: message.url,
        timestamp: message.timestamp ?? Date.now(),
        mismatch: false,
        commandMatch: message.alertType === "command",
        winRHint: message.alertType === "winr",
        winXHint: message.alertType === "winx",
        browserErrorHint: message.alertType === "browser-error",
        fixActionHint: message.alertType === "fix-action",
        captchaHint: message.alertType === "captcha",
        consoleHint: message.alertType === "console",
        shellHint: message.alertType === "shell",
        pasteSequenceHint: message.alertType === "paste-sequence",
        fileExplorerHint: message.alertType === "file-explorer",
        copyTriggerHint: message.alertType === "copy-trigger",
        snippets,
        blockedClipboardText: "",
        detectedContent: message.snippet || "",
        fullContext: message.fullContext || "",
        previousUrl: message.previousUrl || "",
        tabId: sender?.tab?.id ?? null
      });

      if (notificationId) {
        lastPageHint = null;
      }
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
        commandMatch: Boolean(analysis.commandMatch),
        shellHint: Boolean(analysis.shellHint),
        evasionHint: Boolean(analysis.evasionHint),
        clipboardWarning: true,
        winRHint: false,
        winXHint: false,
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
      const clipboardAnalysis = analyzeText(clipboardText);
      const mismatch =
        message.eventType === "copy" &&
        message.clipboardAvailable &&
        selectionText &&
        clipboardText &&
        selectionText.trim() !== clipboardText.trim();

      const clipboardSignals =
        clipboardAnalysis.commandMatch ||
        clipboardAnalysis.shellHint ||
        clipboardAnalysis.evasionHint;
      const commandMatch = clipboardAnalysis.commandMatch || clipboardAnalysis.shellHint;
      const evasionHint = clipboardAnalysis.evasionHint;
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
          commandMatch,
          winRHint: false,
          winXHint: false,
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
