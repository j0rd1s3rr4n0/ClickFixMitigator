const DEFAULT_SETTINGS = {
  enabled: true,
  whitelist: [],
  history: [],
  blocklist: [],
  blocklistUpdatedAt: 0
};

const CLICKFIX_LIST_URL = "https://jordiserrano.me/clickfixlist";
const CLICKFIX_REPORT_URL = "https://jordiserrano.me/clickfix-report.php";
const BLOCKLIST_REFRESH_MINUTES = 30;

const COMMAND_REGEX =
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|reg\s+add|rundll32|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|iwr|curl\s+|wget\s+|downloadstring|frombase64string|add-mppreference|invoke-expression|iex\b|encodedcommand|\-enc\b)/i;

async function getSettings() {
  const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: stored.enabled ?? true,
    whitelist: stored.whitelist ?? [],
    history: stored.history ?? [],
    blocklist: stored.blocklist ?? [],
    blocklistUpdatedAt: stored.blocklistUpdatedAt ?? 0
  };
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

async function fetchBlocklist() {
  try {
    const response = await fetch(CLICKFIX_LIST_URL, { cache: "no-store" });
    if (!response.ok) {
      return;
    }
    const text = await response.text();
    const entries = text
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => line && !line.startsWith("#"))
      .map(normalizeHostname)
      .filter(Boolean);
    await chrome.storage.local.set({
      blocklist: entries,
      blocklistUpdatedAt: Date.now()
    });
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

function buildAlertMessage(details) {
  const parts = [];
  if (details.mismatch) {
    parts.push("Discrepancia entre selección y portapapeles.");
  }
  if (details.commandMatch) {
    parts.push("Patrón de comando sospechoso detectado.");
  }
  if (details.winRHint) {
    parts.push("La página sugiere usar Win+R/Run dialog.");
  }
  if (details.captchaHint) {
    parts.push("Posible captcha falso detectado.");
  }
  if (details.consoleHint) {
    parts.push("La página intenta que pegues en la consola de DevTools.");
  }
  if (details.shellHint) {
    parts.push("La página te pide pegar comandos en CMD/PowerShell/Ejecutar.");
  }
  if (details.pasteSequenceHint) {
    parts.push("La página guía el pegado con Ctrl+V/Enter.");
  }
  if (details.fileExplorerHint) {
    parts.push("La página pide pegar una ruta en el Explorador de archivos.");
  }
  if (details.hintSnippet) {
    const snippet =
      details.hintSnippet.length > 160
        ? `${details.hintSnippet.slice(0, 157)}...`
        : details.hintSnippet;
    parts.push(`Fragmento detectado: "${snippet}"`);
  }
  return parts.join(" ");
}

async function triggerAlert(details) {
  const message = buildAlertMessage(details);
  const hostname = extractHostname(details.url);
  const timestamp = new Date(details.timestamp).toISOString();

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

  chrome.notifications.create({
    type: "basic",
    iconUrl: svgIcon,
    title: "ClickFix Mitigator",
    message
  });

  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    const tabId = tabs?.[0]?.id;
    if (tabId) {
      chrome.tabs.sendMessage(tabId, {
        type: "showBanner",
        text: message
      });
    }
  });

  try {
    await fetch(CLICKFIX_REPORT_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        url: details.url,
        hostname,
        timestamp,
        message,
        signals: {
          mismatch: details.mismatch,
          commandMatch: details.commandMatch,
          winRHint: details.winRHint,
          captchaHint: details.captchaHint,
          consoleHint: details.consoleHint,
          shellHint: details.shellHint,
          pasteSequenceHint: details.pasteSequenceHint,
          fileExplorerHint: details.fileExplorerHint
        }
      })
    });
  } catch (error) {
    // Ignore reporting errors.
  }
}

function analyzeText(text) {
  const trimmed = text?.trim();
  if (!trimmed) {
    return { commandMatch: false, shellHint: false };
  }
  return {
    commandMatch: COMMAND_REGEX.test(trimmed),
    shellHint: SHELL_HINT_REGEX.test(trimmed)
  };
}

async function shouldIgnore(url) {
  const settings = await getSettings();
  if (!settings.enabled) {
    return true;
  }
  const hostname = extractHostname(url);
  return settings.whitelist.includes(hostname);
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
  fetchBlocklist();
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: BLOCKLIST_REFRESH_MINUTES });
});

chrome.runtime.onStartup.addListener(() => {
  fetchBlocklist();
  chrome.alarms.create("clickfix-refresh", { periodInMinutes: BLOCKLIST_REFRESH_MINUTES });
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "clickfix-refresh") {
    fetchBlocklist();
  }
});

let lastPageHint = null;

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!message || !message.type) {
    return;
  }

  if (message.type === "checkBlocklist") {
    (async () => {
      const hostname = extractHostname(message.url);
      const blocked = await isBlocked(message.url);
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

  if (message.type === "pageHint" && message.hint) {
    lastPageHint = {
      hint: message.hint,
      snippet: message.snippet || ""
    };
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

      const winRHint = lastPageHint?.hint === "winr";
      const captchaHint = lastPageHint?.hint === "captcha";
      const consoleHint = lastPageHint?.hint === "console";
      const shellHint = lastPageHint?.hint === "shell";
      const pasteSequenceHint = lastPageHint?.hint === "paste-sequence";
      const fileExplorerHint = lastPageHint?.hint === "file-explorer";
      if (
        mismatch ||
        commandMatch ||
        winRHint ||
        captchaHint ||
        consoleHint ||
        shellHint ||
        pasteSequenceHint ||
        fileExplorerHint
      ) {
        await triggerAlert({
          url: message.url,
          timestamp: message.timestamp,
          mismatch,
          commandMatch,
          winRHint,
          captchaHint,
          consoleHint,
          shellHint,
          pasteSequenceHint,
          fileExplorerHint,
          hintSnippet: lastPageHint?.snippet || ""
        });
        lastPageHint = null;
      }
    })();
  }
});
