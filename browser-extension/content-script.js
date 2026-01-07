const CLICKFIX_WIN_R_REGEX =
  /(win\s*\+\s*r|win\s+r|windows\s*\+\s*r|presiona\s+windows|tecla\s+windows|tecla\s+de\s+windows|run\s+dialog|open\s+run|cuadro\s+de\s+ejecutar|ventana\s+de\s+ejecutar|simbolo\s+del\s+sistema|símbolo\s+del\s+sistema|abr(e|ir)\s+cmd|abr(e|ir)\s+powershell|abr(e|ir)\s+terminal|abre\s+la\s+consola)/i;
const CLICKFIX_CAPTCHA_REGEX =
  /(captcha|no\s+soy\s+un\s+robot|no\s+eres\s+un\s+robot|i('?| a)?m\s+not\s+a\s+robot|verify\s+you('?| a)?re\s+human|human\s+verification|verifica\s+que\s+eres\s+humano|confirmar\s+que\s+eres\s+humano)/i;
const MAX_SELECTION_LENGTH = 2000;

let lastSelectionText = "";
let winRDetected = false;
let captchaDetected = false;

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

function sendClipboardEvent(payload) {
  chrome.runtime.sendMessage({
    type: "clipboardEvent",
    url: window.location.href,
    timestamp: Date.now(),
    ...payload
  });
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

document.addEventListener("DOMContentLoaded", () => {
  notifyWinRDetected();
  notifyCaptchaDetected();
  const observer = new MutationObserver(() => {
    notifyWinRDetected();
    notifyCaptchaDetected();
  });
  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
  }
});

chrome.runtime.onMessage.addListener((message) => {
  if (message?.type === "showBanner") {
    const existing = document.getElementById("clickfix-mitigator-banner");
    if (existing) {
      existing.querySelector("span").textContent = message.text;
      return;
    }
    const banner = document.createElement("div");
    banner.id = "clickfix-mitigator-banner";
    banner.style.cssText = [
      "position:fixed",
      "top:0",
      "left:0",
      "right:0",
      "z-index:2147483647",
      "background:#b91c1c",
      "color:#fff",
      "padding:10px 16px",
      "font-family:system-ui, sans-serif",
      "font-size:14px",
      "display:flex",
      "justify-content:space-between",
      "align-items:center"
    ].join(";");

    const text = document.createElement("span");
    text.textContent = message.text;

    const closeButton = document.createElement("button");
    closeButton.textContent = "Cerrar";
    closeButton.style.cssText = [
      "background:#fff",
      "color:#b91c1c",
      "border:none",
      "padding:4px 10px",
      "border-radius:4px",
      "cursor:pointer"
    ].join(";");
    closeButton.addEventListener("click", () => banner.remove());

    banner.appendChild(text);
    banner.appendChild(closeButton);
    document.body.appendChild(banner);
  }
});
