const DEFAULT_SETTINGS = {
  enabled: true,
  whitelist: [],
  history: []
};

const COMMAND_REGEX =
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|reg\s+add|rundll32|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|iwr|curl\s+|wget\s+|downloadstring|frombase64string|add-mppreference|invoke-expression|iex\b|encodedcommand|\-enc\b)/i;

async function getSettings() {
  const stored = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: stored.enabled ?? true,
    whitelist: stored.whitelist ?? [],
    history: stored.history ?? []
  };
}

function extractHostname(url) {
  try {
    return new URL(url).hostname;
  } catch (error) {
    return "";
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

let lastPageHint = null;

chrome.runtime.onMessage.addListener((message, sender) => {
  if (!message || !message.type) {
    return;
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
      if (mismatch || commandMatch || winRHint || captchaHint) {
        await triggerAlert({
          url: message.url,
          timestamp: message.timestamp,
          mismatch,
          commandMatch,
          winRHint,
          captchaHint,
          hintSnippet: lastPageHint?.snippet || ""
        });
        lastPageHint = null;
      }
    })();
  }
});
