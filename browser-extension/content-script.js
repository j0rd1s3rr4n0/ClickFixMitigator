const CLICKFIX_WIN_R_REGEX =
  /(win\s*\+\s*r|win\s+r|windows\s*\+\s*r|windows\s+key|windows\s+button|presiona\s+windows|tecla\s+windows|tecla\s+de\s+windows|run\s+dialog|open\s+run|cuadro\s+de\s+ejecutar|ventana\s+de\s+ejecutar|simbolo\s+del\s+sistema|símbolo\s+del\s+sistema|abr(e|ir)\s+cmd|abr(e|ir)\s+powershell|abr(e|ir)\s+terminal|abre\s+la\s+consola)/i;
const CLICKFIX_CAPTCHA_REGEX =
  /(captcha|no\s+soy\s+un\s+robot|no\s+eres\s+un\s+robot|i('?| a)?m\s+not\s+a\s+robot|verify\s+you('?| a)?re\s+human|human\s+verification|verifica\s+que\s+eres\s+humano|confirmar\s+que\s+eres\s+humano)/i;
const CLICKFIX_CONSOLE_REGEX =
  /(devtools\s+console|consola\s+de\s+devtools|console\s+de\s+desarrolladores|developer\s+console|browser\s+console|web\s+console|console\s+of\s+the\s+browser|allow\s+pasting|permitir\s+pegar|pega\s+esto\s+en\s+la\s+consola|paste\s+this\s+into\s+the\s+console|paste\s+in(to)?\s+console|pega\s+en\s+la\s+consola|pegar\s+en\s+consola|open\s+devtools|abre\s+devtools|open\s+developer\s+tools|abre\s+las\s+herramientas\s+de\s+desarrollador)/i;
const CLICKFIX_SHELL_PASTE_REGEX =
  /(pega\s+en\s+(cmd|powershell|terminal|consola|ejecutar)|pega\s+esto\s+en\s+(cmd|powershell|terminal|consola|ejecutar)|pegalo\s+en\s+(cmd|powershell|terminal|consola|ejecutar)|paste\s+in(to)?\s+(cmd|powershell|terminal|run|command\s+prompt)|paste\s+this\s+in(to)?\s+(cmd|powershell|terminal|run|command\s+prompt)|open\s+(cmd|powershell|terminal|command\s+prompt)\s+and\s+paste|open\s+the\s+run\s+dialog|abre\s+el\s+cuadro\s+de\s+ejecutar|abre\s+ejecutar|open\s+cmd|open\s+powershell|open\s+terminal|abre\s+cmd|abre\s+powershell|abre\s+terminal)/i;
const CLICKFIX_PASTE_SEQUENCE_REGEX =
  /(ctrl\s*\+\s*v|control\s*\+\s*v|pega\s+con\s+ctrl\s*\+\s*v|press\s+ctrl\s*\+\s*v|then\s+press\s+enter|pulsa\s+enter|presiona\s+enter|hit\s+enter|ctrl\s*\+\s*shift\s*\+\s*enter|control\s*\+\s*shift\s*\+\s*enter)/i;
const CLICKFIX_FILE_EXPLORER_REGEX =
  /(file\s+explorer|explorador\s+de\s+archivos|address\s+bar|barra\s+de\s+direcciones|ctrl\s*\+\s*l|control\s*\+\s*l|alt\s*\+\s*d|pega\s+la\s+ruta|paste\s+the\s+path|open\s+file\s+explorer|abre\s+el\s+explorador)/i;
const MAX_SELECTION_LENGTH = 2000;

let lastSelectionText = "";
let winRDetected = false;
let captchaDetected = false;
let consoleDetected = false;
let shellDetected = false;
let pasteSequenceDetected = false;
let fileExplorerDetected = false;
let lastClipboardSnapshot = "";
let clipboardWatchRunning = false;

function getHostname(url) {
  try {
    return new URL(url).hostname;
  } catch (error) {
    return "";
  }
}

async function getBlocklistStatus() {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(
      { type: "checkBlocklist", url: window.location.href },
      (response) => resolve(response)
    );
  });
}

function buildBlockedPage(hostname) {
  const title = "Sitio web reportado anteriormente";
  const reason = "Motivo: ClickFix Report";
  const container = document.createElement("div");
  container.style.cssText = [
    "min-height:100vh",
    "display:flex",
    "flex-direction:column",
    "justify-content:center",
    "align-items:center",
    "gap:24px",
    "padding:32px",
    "background:#fff5f5",
    "font-family:system-ui, sans-serif",
    "text-align:center"
  ].join(";");

  const heading = document.createElement("div");
  heading.textContent = title;
  heading.style.cssText = [
    "font-size:36px",
    "font-weight:800",
    "color:#b91c1c",
    "text-transform:uppercase"
  ].join(";");

  const subtitle = document.createElement("div");
  subtitle.textContent = reason;
  subtitle.style.cssText = [
    "font-size:20px",
    "font-weight:700",
    "color:#dc2626"
  ].join(";");

  const hostText = document.createElement("div");
  hostText.textContent = hostname ? `Sitio: ${hostname}` : "";
  hostText.style.cssText = [
    "font-size:16px",
    "color:#7f1d1d"
  ].join(";");

  const buttonRow = document.createElement("div");
  buttonRow.style.cssText = [
    "display:flex",
    "gap:16px",
    "flex-wrap:wrap",
    "justify-content:center"
  ].join(";");

  const stayButton = document.createElement("button");
  stayButton.type = "button";
  stayButton.textContent = "Permanecer en web";
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
  backButton.textContent = "Volver atrás";
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

  backButton.addEventListener("click", () => {
    if (window.history.length > 1) {
      window.history.back();
    } else {
      window.location.href =
        "https://www.ecosia.org/search?method=index&q=Google.com";
    }
  });

  buttonRow.appendChild(stayButton);
  buttonRow.appendChild(backButton);

  container.appendChild(heading);
  container.appendChild(subtitle);
  if (hostname) {
    container.appendChild(hostText);
  }
  container.appendChild(buttonRow);

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
    buildBlockedPage(status.hostname || getHostname(window.location.href));
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

function scanForFakeCaptcha() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_CAPTCHA_REGEX, bodyText);
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
  }
}

function scanForFileExplorer() {
  if (!document.body) {
    return "";
  }
  const bodyText = document.body.innerText || "";
  return findMatchSnippet(CLICKFIX_FILE_EXPLORER_REGEX, bodyText);
}

function renderBlockedPage(hostname) {
  const safeHost = hostname || window.location.hostname;
  const html = `<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Sitio bloqueado</title>
    <style>
      :root {
        color-scheme: light;
      }
      body {
        margin: 0;
        font-family: "Segoe UI", system-ui, sans-serif;
        background: #fff5f5;
        color: #7f1d1d;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 24px;
      }
      .card {
        max-width: 760px;
        width: 100%;
        background: #fff;
        border: 3px solid #dc2626;
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 20px 45px rgba(127, 29, 29, 0.2);
      }
      .title {
        font-size: 36px;
        font-weight: 800;
        color: #b91c1c;
        margin-bottom: 12px;
      }
      .subtitle {
        font-size: 18px;
        margin-bottom: 28px;
      }
      .host {
        font-weight: 700;
      }
      .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
      }
      button {
        font-size: 15px;
        font-weight: 700;
        padding: 12px 18px;
        border-radius: 999px;
        border: none;
        cursor: pointer;
      }
      .stay {
        background: #dc2626;
        color: #fff;
      }
      .back {
        background: #fee2e2;
        color: #991b1b;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <div class="title">Sitio web reportado anteriormente</div>
      <div class="subtitle">
        Motivo: <strong>ClickFix Report</strong> en
        <span class="host">${safeHost}</span>
      </div>
      <div class="actions">
        <button class="stay" id="clickfix-stay">Permanecer en web</button>
        <button class="back" id="clickfix-back">Volver atrás</button>
      </div>
    </div>
  </body>
</html>`;

  document.open();
  document.write(html);
  document.close();

  const stayButton = document.getElementById("clickfix-stay");
  const backButton = document.getElementById("clickfix-back");
  if (stayButton) {
    stayButton.addEventListener("click", () => {
      chrome.storage.local.get({ whitelist: [] }, (data) => {
        const next = new Set(data.whitelist || []);
        next.add(safeHost);
        chrome.storage.local.set({ whitelist: Array.from(next) }, () => {
          window.location.reload();
        });
      });
    });
  }
  if (backButton) {
    backButton.addEventListener("click", () => {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = "https://www.ecosia.org/search?method=index&q=Google.com";
      }
    });
  }
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

document.addEventListener("copy", () => handleCopyCut("copy"));
document.addEventListener("cut", () => handleCopyCut("cut"));
document.addEventListener("paste", () => handlePaste());
document.addEventListener("selectionchange", handleSelectionChange);

document.addEventListener("DOMContentLoaded", async () => {
  const blocked = await checkReportedSite();
  if (blocked) {
    return;
  }
  notifyWinRDetected();
  notifyCaptchaDetected();
  notifyConsoleDetected();
  notifyShellDetected();
  notifyPasteSequenceDetected();
  notifyFileExplorerDetected();
  monitorClipboardChanges();
  setInterval(monitorClipboardChanges, 1000);
  const observer = new MutationObserver(() => {
function startMonitoring() {
  document.addEventListener("copy", () => handleCopyCut("copy"));
  document.addEventListener("cut", () => handleCopyCut("cut"));
  document.addEventListener("paste", () => handlePaste());
  document.addEventListener("selectionchange", handleSelectionChange);

  document.addEventListener("DOMContentLoaded", () => {
    notifyWinRDetected();
    notifyCaptchaDetected();
    notifyConsoleDetected();
    notifyShellDetected();
    notifyPasteSequenceDetected();
    notifyFileExplorerDetected();
    monitorClipboardChanges();
    setInterval(monitorClipboardChanges, 4000);
    const observer = new MutationObserver(() => {
      notifyWinRDetected();
      notifyCaptchaDetected();
      notifyConsoleDetected();
      notifyShellDetected();
      notifyPasteSequenceDetected();
      notifyFileExplorerDetected();
    });
    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    }
  });
}

(async () => {
  const isBlocked = await checkBlocklistAndBlock();
  if (!isBlocked) {
    startMonitoring();
  }
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
    title.textContent = "Alerta de ClickFixMitigator";
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
    closeButton.textContent = "Cerrar";
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
