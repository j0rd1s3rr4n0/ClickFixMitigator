const DEFAULT_SETTINGS = {
  enabled: true,
  whitelist: [],
  allowlist: [],
  history: [],
  blocklist: [],
  blocklistSources: [],
  allowlistSources: [],
  saveClipboardBackup: true,
  alertCount: 0,
  blockCount: 0,
  blocklistUpdatedAt: 0,
  allowlistUpdatedAt: 0
};

const CLICKFIX_BLOCKLIST_URL = "https://jordiserrano.me/ClickFix/clickfixlist";
const CLICKFIX_ALLOWLIST_URL = "https://jordiserrano.me/ClickFix/clickfixallowlist";
const CLICKFIX_REPORT_URL = "https://jordiserrano.me/ClickFix/clickfix-report.php";
const BLOCKLIST_REFRESH_MINUTES = 0.5;
const STATS_REPORT_MINUTES = 60;

const COMMAND_REGEX =
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|p[\s^`]*o[\s^`]*w[\s^`]*e[\s^`]*r[\s^`]*s[\s^`]*h[\s^`]*e[\s^`]*l[\s^`]*l|c[\s^`]*m[\s^`]*d|reg\s+add|rundll32|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|invoke-restmethod|\biwr\b|\birm\b|curl\s+|wget\s+|downloadstring|frombase64string|add-mppreference|invoke-expression|iex\b|iex\s*\(|encodedcommand|\-enc\b)/i;
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
    whitelist: stored.whitelist ?? [],
    allowlist: stored.allowlist ?? [],
    history: stored.history ?? [],
    blocklist: stored.blocklist ?? [],
    blocklistSources: stored.blocklistSources ?? [],
    allowlistSources: stored.allowlistSources ?? [],
    saveClipboardBackup: stored.saveClipboardBackup ?? true,
    alertCount: stored.alertCount ?? 0,
    blockCount: stored.blockCount ?? 0,
    blocklistUpdatedAt: stored.blocklistUpdatedAt ?? 0,
    allowlistUpdatedAt: stored.allowlistUpdatedAt ?? 0
  };
}

function t(key, substitutions) {
  return chrome.i18n.getMessage(key, substitutions) || key;
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
  return parts;
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

async function triggerAlert(details) {
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
      evasionHint: details.evasionHint
    }
  });
  await incrementAlertCount();
  if (details.incrementBlockCount !== false) {
    await incrementBlockCount();
  }
  const reasons = buildAlertReasons(details);
  const snippets = buildAlertSnippets(details);
  const message = reasons.join(" ");
  const hostname = extractHostname(details.url);
  const timestamp = new Date(details.timestamp).toISOString();
  const allowlisted = await isAllowlisted(details.url);
  const shouldBlockPage = !details.suppressPageBlock;

  await saveHistory({
    message,
    url: details.url,
    hostname,
    timestamp
  });

  const svgIcon = [
    "data:image/svg+xml;utf8,",
    "<svg xmlns='http://www.w3.org/2000/svg' width='128' height='128' viewBox='0 0 128 128'>",
    "<rect width='128' height='128' rx='20' fill='%23b91c1c'/>",
    "<path d='M36 36h56v12H36zM36 58h56v12H36zM36 80h56v12H36z' fill='white'/>",
    "</svg>"
  ].join("");

  const notificationId = await new Promise((resolve) => {
    chrome.notifications.create(
      {
        type: "basic",
        iconUrl: svgIcon,
        title: t("appName"),
        message,
        buttons: details.blockedClipboardText
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
    chrome.tabs.sendMessage(targetTabId, {
      type: "showBanner",
      text: message
    });
    if (shouldBlockPage && !allowlisted) {
      chrome.tabs.sendMessage(targetTabId, {
        type: "blockPage",
        hostname,
        reason: message,
        reasons,
        contextText: details.detectedContent || "",
        snippets
      });
    }
  } else {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      const tabId = tabs?.[0]?.id;
      if (tabId) {
        chrome.tabs.sendMessage(tabId, {
          type: "showBanner",
          text: message
        });
        if (shouldBlockPage && !allowlisted) {
          chrome.tabs.sendMessage(tabId, {
            type: "blockPage",
            hostname,
            reason: message,
            reasons,
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
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: BLOCKLIST_REFRESH_MINUTES });
  chrome.alarms.create("clickfix-stats", { periodInMinutes: STATS_REPORT_MINUTES });
});

chrome.runtime.onStartup.addListener(() => {
  refreshBlocklist();
  refreshAllowlist();
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: BLOCKLIST_REFRESH_MINUTES });
  chrome.alarms.create("clickfix-stats", { periodInMinutes: STATS_REPORT_MINUTES });
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "clickfix-refresh") {
    refreshBlocklist();
    refreshAllowlist();
  }
  if (alarm.name === "clickfix-stats") {
    sendStatsReport();
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

async function reportClickfix(details) {
  try {
    await fetch(CLICKFIX_REPORT_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(details)
    });
  } catch (error) {
    // Ignore reporting errors to avoid breaking the user flow.
  }
}

async function sendStatsReport() {
  const settings = await getSettings();
  const alertSites = buildAlertSites(settings.history ?? []);
  reportClickfix({
    type: "stats",
    timestamp: Date.now(),
    data: {
      enabled: settings.enabled ?? true,
      manualSites: settings.whitelist ?? [],
      alertSites,
      alertCount: settings.alertCount ?? 0,
      blockCount: settings.blockCount ?? 0
    }
  });
}

function buildAlertSites(history) {
  const sites = [];
  const seen = new Set();
  if (!Array.isArray(history)) {
    return sites;
  }
  for (const entry of history) {
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
      if (await isAllowlisted(message.url)) {
        sendResponse({ blocked: false });
        return;
      }
      const hostname = extractHostname(message.url);
      const items = await getBlocklistItems();
      const blocked = items.some((entry) => entry === hostname || hostname.endsWith(`.${entry}`));
      sendResponse({ blocked, hostname });
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
    reportClickfix({
      url: message.url,
      hostname: message.hostname || extractHostname(message.url),
      timestamp: message.timestamp ?? Date.now(),
      reason: t("manualReportReason"),
      blocked: true,
      manualReport: true,
      detectedContent: ""
    });
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
        tabId: sender?.tab?.id ?? null
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

      const selectionAnalysis = analyzeText(message.selectionText);
      const clipboardAnalysis = analyzeText(message.clipboardText);
      const mismatch =
        message.eventType === "copy" &&
        message.clipboardAvailable &&
        message.selectionText &&
        message.clipboardText &&
        message.selectionText.trim() !== message.clipboardText.trim();

      const commandMatch =
        selectionAnalysis.commandMatch ||
        clipboardAnalysis.commandMatch ||
        selectionAnalysis.shellHint ||
        clipboardAnalysis.shellHint;
      const evasionHint = selectionAnalysis.evasionHint || clipboardAnalysis.evasionHint;
      const clipboardCommandWarning =
        message.eventType === "paste" &&
        (clipboardAnalysis.commandMatch || clipboardAnalysis.shellHint || clipboardAnalysis.evasionHint);

      const hints = lastPageHint?.hints || [];
      const snippets = lastPageHint?.snippets || [];
      const winRHint = hints.includes("winr");
      const winXHint = hints.includes("winx");
      const browserErrorHint = hints.includes("browser-error");
      const fixActionHint = hints.includes("fix-action");
      const captchaHint = hints.includes("captcha");
      const consoleHint = hints.includes("console");
      const shellHint = hints.includes("shell");
      const pasteSequenceHint = hints.includes("paste-sequence");
      const fileExplorerHint = hints.includes("file-explorer");
      const copyTriggerHint = hints.includes("copy-trigger");
      const actionHint =
        winRHint ||
        winXHint ||
        pasteSequenceHint ||
        consoleHint ||
        shellHint ||
        fileExplorerHint;
      const contextHint = browserErrorHint || fixActionHint || captchaHint;
      const hasPageHints =
        winRHint ||
        winXHint ||
        browserErrorHint ||
        fixActionHint ||
        captchaHint ||
        consoleHint ||
        shellHint ||
        pasteSequenceHint ||
        fileExplorerHint ||
        copyTriggerHint;
      const signalScore = [
        mismatch,
        commandMatch || evasionHint,
        copyTriggerHint,
        actionHint,
        contextHint
      ].filter(Boolean).length;
      const shouldAlert =
        (hasPageHints && signalScore >= 2) ||
        (hasPageHints && commandMatch) ||
        (hasPageHints && mismatch);
      const clipboardWarningOnly =
        clipboardCommandWarning && !hasPageHints && !mismatch && signalScore < 2;

      if (shouldAlert) {
        const settings = await getSettings();
        const saveClipboardBackup = settings.saveClipboardBackup ?? true;
        const clipboardText = message.clipboardText?.trim();
        const detectedContent = clipboardText || message.selectionText || "";
        const isClipboardWatch = message.eventType === "clipboard-watch";
        const shouldBlockClipboard =
          isClipboardWatch &&
          clipboardText &&
          (
            commandMatch ||
            evasionHint ||
            winRHint ||
            captchaHint ||
            consoleHint ||
            shellHint ||
            pasteSequenceHint
          );

        if (shouldBlockClipboard && shouldThrottleClipboardBlock(clipboardText)) {
          return;
        }

        let blockedClipboardText = "";
        if (shouldBlockClipboard) {
          setClipboardBlock(clipboardText);
          if (saveClipboardBackup) {
            blockedClipboardText = clipboardText;
          }
          requestClipboardReplace(sender?.tab?.id, "");
        }

        const notificationId = await triggerAlert({
          url: message.url,
          timestamp: message.timestamp,
          mismatch,
          commandMatch,
          winRHint,
          winXHint,
          browserErrorHint,
          fixActionHint,
          captchaHint,
          consoleHint,
          shellHint,
          pasteSequenceHint,
          fileExplorerHint,
          copyTriggerHint,
          evasionHint,
          snippets,
          clipboardWarning: clipboardWarningOnly,
          blockedClipboardText,
          detectedContent,
          fullContext: message.fullContext || "",
          tabId: sender?.tab?.id ?? null,
          suppressPageBlock: clipboardWarningOnly,
          incrementBlockCount: !clipboardWarningOnly
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
