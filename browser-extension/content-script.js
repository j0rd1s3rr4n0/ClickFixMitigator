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
  /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|p[\s^`]*o[\s^`]*w[\s^`]*e[\s^`]*r[\s^`]*s[\s^`]*h[\s^`]*e[\s^`]*l[\s^`]*l|c[\s^`]*m[\s^`]*d|reg\s+add|rundll32|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic)\b/i;
const SHELL_HINT_REGEX =
  /(invoke-webrequest|iwr|curl\s+|wget\s+|downloadstring|frombase64string|add-mppreference|invoke-expression|iex\b|encodedcommand|\-enc\b)/i;
const MAX_SELECTION_LENGTH = 2000;
const FULL_CONTEXT_LIMIT = 40000;

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

function getHostname(url) {
  try {
    return new URL(url).hostname;
  } catch (error) {
    return "";
  }
}

function t(key, substitutions) {
  return chrome.i18n.getMessage(key, substitutions) || key;
}

async function getBlocklistStatus() {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(
      { type: "checkBlocklist", url: window.location.href },
      (response) => resolve(response)
    );
  });
}

function sendPageAlert(alertType, snippet) {
  const fullContext = collectFullContext();
  chrome.runtime.sendMessage({
    type: "pageAlert",
    alertType,
    snippet,
    url: window.location.href,
    timestamp: Date.now(),
    fullContext
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
    output += `<mark style="background:#fde68a;color:#7f1d1d;border-radius:4px;padding:0 2px;">${escapeHtml(text.slice(range.start, range.end))}</mark>`;
    cursor = range.end;
  });
  output += escapeHtml(text.slice(cursor));
  return output;
}

function buildBlockedPage(hostname, reasonText, reasons = [], contextText = "", snippets = []) {
  const title = t("blockedTitle");
  const filteredReasons = reasons.filter(Boolean);
  const reason = filteredReasons.length
    ? t("blockedReasonDefault")
    : reasonText
      ? t("blockedReasonWithDetail", reasonText)
      : t("blockedReasonDefault");
  const container = document.createElement("div");
  container.style.cssText = [
    "min-height:100vh",
    "display:flex",
    "flex-direction:column",
    "justify-content:center",
    "align-items:center",
    "gap:20px",
    "padding:32px 24px",
    "background:linear-gradient(180deg, #fff1f2 0%, #fee2e2 100%)",
    "font-family:system-ui, sans-serif",
    "text-align:center"
  ].join(";");

  const card = document.createElement("div");
  card.style.cssText = [
    "background:#ffffff",
    "border-radius:24px",
    "padding:32px",
    "box-shadow:0 24px 60px rgba(185, 28, 28, 0.18)",
    "max-width:720px",
    "width:100%"
  ].join(";");

  const heading = document.createElement("div");
  heading.textContent = title;
  heading.style.cssText = [
    "font-size:30px",
    "font-weight:800",
    "color:#b91c1c",
    "text-transform:uppercase"
  ].join(";");

  const subtitle = document.createElement("div");
  subtitle.textContent = reason;
  subtitle.style.cssText = [
    "font-size:16px",
    "font-weight:600",
    "color:#b91c1c",
    "margin-top:12px"
  ].join(";");

  const reasonsTitle = document.createElement("div");
  reasonsTitle.textContent = t("blockedReasonsTitle");
  reasonsTitle.style.cssText = [
    "margin-top:18px",
    "font-size:15px",
    "font-weight:700",
    "color:#7f1d1d"
  ].join(";");

  const reasonList = document.createElement("ul");
  reasonList.style.cssText = [
    "margin:12px 0 0",
    "padding:0",
    "list-style:none",
    "display:flex",
    "flex-direction:column",
    "gap:10px",
    "text-align:left"
  ].join(";");

  filteredReasons.forEach((entry) => {
    const item = document.createElement("li");
    item.textContent = entry;
    item.style.cssText = [
      "padding:12px 14px",
      "border-radius:14px",
      "background:#fff1f2",
      "border:1px solid #fecdd3",
      "color:#7f1d1d",
      "font-size:14px",
      "line-height:1.4"
    ].join(";");
    reasonList.appendChild(item);
  });

  const hostText = document.createElement("div");
  hostText.textContent = hostname ? t("blockedHost", hostname) : "";
  hostText.style.cssText = [
    "font-size:14px",
    "color:#7f1d1d"
  ].join(";");

  const contextLabel = document.createElement("div");
  contextLabel.textContent = t("blockedContextTitle");
  contextLabel.style.cssText = [
    "margin-top:18px",
    "font-size:15px",
    "font-weight:700",
    "color:#7f1d1d"
  ].join(";");

  const contextBox = document.createElement("div");
  contextBox.setAttribute("role", "textbox");
  contextBox.setAttribute("aria-readonly", "true");
  contextBox.style.cssText = [
    "margin-top:12px",
    "padding:12px 14px",
    "border-radius:16px",
    "background:#f8fafc",
    "border:1px solid #e2e8f0",
    "color:#0f172a",
    "font-size:13px",
    "line-height:1.5",
    "max-height:400pt",
    "overflow:auto",
    "width:60%",
    "text-align:left",
    "white-space:pre-wrap"
  ].join(";");

  if (contextText) {
    contextBox.innerHTML = buildHighlightedHtml(contextText, snippets);
  }

  const buttonRow = document.createElement("div");
  buttonRow.style.cssText = [
    "display:flex",
    "gap:16px",
    "flex-wrap:wrap",
    "justify-content:center"
  ].join(";");

  const reportButton = document.createElement("button");
  reportButton.type = "button";
  reportButton.textContent = t("blockedReport");
  reportButton.style.cssText = [
    "padding:12px 20px",
    "border-radius:999px",
    "border:2px solid #f97316",
    "background:#fff7ed",
    "color:#ea580c",
    "font-weight:700",
    "cursor:pointer",
    "font-size:14px"
  ].join(";");

  const stayButton = document.createElement("button");
  stayButton.type = "button";
  stayButton.textContent = t("blockedStay");
  stayButton.style.cssText = [
    "padding:12px 20px",
    "border-radius:999px",
    "border:2px solid #dc2626",
    "background:#fff",
    "color:#dc2626",
    "font-weight:700",
    "cursor:pointer",
    "font-size:14px"
  ].join(";");

  const backButton = document.createElement("button");
  backButton.type = "button";
  backButton.textContent = t("blockedBack");
  backButton.style.cssText = [
    "padding:12px 20px",
    "border-radius:999px",
    "border:none",
    "background:#dc2626",
    "color:#fff",
    "font-weight:700",
    "cursor:pointer",
    "font-size:14px"
  ].join(";");

  stayButton.addEventListener("click", async () => {
    const currentHost = hostname || getHostname(window.location.href);
    await new Promise((resolve) => {
      chrome.runtime.sendMessage(
        { type: "allowSite", hostname: currentHost },
        () => resolve()
      );
    });
    window.location.reload();
  });

  reportButton.addEventListener("click", () => {
    chrome.runtime.sendMessage({
      type: "manualReport",
      url: window.location.href,
      hostname: hostname || getHostname(window.location.href),
      timestamp: Date.now()
    });
    reportButton.disabled = true;
    reportButton.textContent = t("blockedReported");
  });

  backButton.addEventListener("click", () => {
    if (window.history.length > 1) {
      window.history.back();
    } else {
      window.location.href =
        "https://www.ecosia.org/search?method=index&q=Google.com";
    }
  });

  buttonRow.appendChild(reportButton);
  buttonRow.appendChild(stayButton);
  buttonRow.appendChild(backButton);

  card.appendChild(heading);
  card.appendChild(subtitle);
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
  container.appendChild(card);

  document.documentElement.innerHTML = "";
  const head = document.createElement("head");
  const body = document.createElement("body");
  body.appendChild(container);
  document.documentElement.appendChild(head);
  document.documentElement.appendChild(body);
}

async function checkBlocklistAndBlock() {
  const status = await getBlocklistStatus();
  if (status?.blocked) {
    const hostname = status.hostname || getHostname(window.location.href);
    chrome.runtime.sendMessage({
      type: "blocklisted",
      url: window.location.href,
      hostname,
      timestamp: Date.now()
    });
    buildBlockedPage(hostname);
    return true;
  }
  return false;
}

function normalizeSnippet(text) {
  return text.replace(/\s+/g, " ").trim();
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
    const text = await navigator.clipboard.readText();
    return { text: text.slice(0, MAX_SELECTION_LENGTH), available: true };
  } catch (error) {
    return { text: "", available: false };
  }
}

async function writeClipboardText(text) {
  try {
    if (!navigator.clipboard || !navigator.clipboard.writeText) {
      return false;
    }
    await navigator.clipboard.writeText(text);
    lastClipboardSnapshot = text;
    return true;
  } catch (error) {
    return false;
  }
}

function scanForWinR() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_WIN_R_REGEX, bodyText);
}

function scanForWinX() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_WIN_X_REGEX, bodyText);
}

function scanForFakeError() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_FAKE_ERROR_REGEX, bodyText);
}

function scanForFixAction() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_FIX_ACTION_REGEX, bodyText);
}

function scanForFakeCaptcha() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return (
    findMatchSnippet(CLICKFIX_CAPTCHA_REGEX, bodyText) ||
    findMatchSnippet(CLICKFIX_RECAPTCHA_ID_REGEX, bodyText)
  );
}

function notifyWinRDetected() {
  const snippet = scanForWinR();
  if (snippet && !winRDetected) {
    winRDetected = true;
    chrome.runtime.sendMessage({
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
    chrome.runtime.sendMessage({
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
    chrome.runtime.sendMessage({
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
    chrome.runtime.sendMessage({
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
    chrome.runtime.sendMessage({
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
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_CONSOLE_REGEX, bodyText);
}

function notifyConsoleDetected() {
  const snippet = scanForConsolePaste();
  if (snippet && !consoleDetected) {
    consoleDetected = true;
    chrome.runtime.sendMessage({
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
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_SHELL_PASTE_REGEX, bodyText);
}

function notifyShellDetected() {
  const snippet = scanForShellPaste();
  if (snippet && !shellDetected) {
    shellDetected = true;
    chrome.runtime.sendMessage({
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
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_PASTE_SEQUENCE_REGEX, bodyText);
}

function notifyPasteSequenceDetected() {
  const snippet = scanForPasteSequence();
  if (snippet && !pasteSequenceDetected) {
    pasteSequenceDetected = true;
    chrome.runtime.sendMessage({
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
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_FILE_EXPLORER_REGEX, bodyText);
}

function scanForCopyTrigger() {
  const htmlText = document.documentElement?.innerHTML || "";
  return findMatchSnippet(CLICKFIX_COPY_TRIGGER_REGEX, htmlText);
}

function scanForCommandPatterns() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return (
    findMatchSnippet(COMMAND_REGEX, bodyText) ||
    findMatchSnippet(SHELL_HINT_REGEX, bodyText)
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
    chrome.runtime.sendMessage({
      type: "pageHint",
      hint: "copy-trigger",
      snippet,
      url: window.location.href,
      timestamp: Date.now()
    });
    sendPageAlert("copy-trigger", snippet);
  }
}

function renderBlockedPage(hostname) {
  buildBlockedPage(hostname || window.location.hostname, t("blockedReasonReported"));
}

function checkReportedSite() {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(
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
    chrome.runtime.sendMessage({
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
  chrome.runtime.sendMessage({
    type: "clipboardEvent",
    url: window.location.href,
    timestamp: Date.now(),
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

async function handleCopyCut(eventType) {
  const selectionText = getSelectionText();
  lastSelectionText = selectionText;
  const clipboard = await readClipboardText();

  sendClipboardEvent({
    eventType,
    selectionText,
    clipboardText: clipboard.text,
    clipboardAvailable: clipboard.available
  });
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
  document.addEventListener("copy", () => handleCopyCut("copy"));
  document.addEventListener("cut", () => handleCopyCut("cut"));
  document.addEventListener("paste", () => handlePaste());
  document.addEventListener("selectionchange", handleSelectionChange);

  const initMonitoring = () => {
    notifyWinRDetected();
    notifyWinXDetected();
    notifyCaptchaDetected();
    notifyBrowserErrorDetected();
    notifyFixActionDetected();
    notifyConsoleDetected();
    notifyShellDetected();
    notifyPasteSequenceDetected();
    notifyFileExplorerDetected();
    notifyCommandDetected();
    notifyCopyTriggerDetected();
    monitorClipboardChanges();
    setInterval(monitorClipboardChanges, 1000);
    const observer = new MutationObserver(() => {
      notifyWinRDetected();
      notifyWinXDetected();
      notifyCaptchaDetected();
      notifyBrowserErrorDetected();
      notifyFixActionDetected();
      notifyConsoleDetected();
      notifyShellDetected();
      notifyPasteSequenceDetected();
      notifyFileExplorerDetected();
      notifyCommandDetected();
      notifyCopyTriggerDetected();
    });
    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initMonitoring);
  } else {
    initMonitoring();
  }
}

(async () => {
  await checkBlocklistAndBlock();
  startMonitoring();
})();

chrome.runtime.onMessage.addListener((message) => {
  if (message?.type === "replaceClipboard") {
    writeClipboardText(message.text ?? "");
    return;
  }
  if (message?.type === "restoreClipboard") {
    writeClipboardText(message.text ?? "");
    return;
  }
  if (message?.type === "blockPage") {
    buildBlockedPage(
      message.hostname || getHostname(window.location.href),
      message.reason,
      message.reasons || [],
      message.contextText || "",
      message.snippets || []
    );
    return;
  }
  if (message?.type === "showBanner") {
    const existing = document.getElementById("clickfix-mitigator-banner");
    if (existing) {
      const messageNode = existing.querySelector("[data-clickfix-message]");
      if (messageNode) {
        messageNode.textContent = message.text;
      }
      return;
    }
    const banner = document.createElement("div");
    banner.id = "clickfix-mitigator-banner";
    banner.style.cssText = [
      "position:fixed",
      "top:16px",
      "right:16px",
      "z-index:2147483647",
      "background:linear-gradient(135deg, #dc2626 0%, #991b1b 100%)",
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
      "<path d='M12 8.5v5.5' stroke='#991b1b' stroke-width='2' stroke-linecap='round'/>" +
      "<circle cx='12' cy='17' r='1.4' fill='#991b1b'/>" +
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

    const text = document.createElement("div");
    text.textContent = message.text;
    text.setAttribute("data-clickfix-message", "true");
    text.style.cssText = [
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
    content.appendChild(text);
    banner.appendChild(icon);
    banner.appendChild(content);
    banner.appendChild(closeButton);
    document.body.appendChild(banner);
  }
});
