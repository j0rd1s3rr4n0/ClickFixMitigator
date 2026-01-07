const CLICKFIX_WIN_R_REGEX = /(win\s*\+\s*r|windows\s*\+\s*r|run\s+dialog|cuadro\s+de\s+ejecutar)/i;
const MAX_SELECTION_LENGTH = 2000;

let lastSelectionText = "";
let winRDetected = false;

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
    return false;
  }
  const bodyText = document.body.innerText || "";
  return CLICKFIX_WIN_R_REGEX.test(bodyText);
}

function notifyWinRDetected() {
  const detected = scanForWinR();
  if (detected && !winRDetected) {
    winRDetected = true;
    chrome.runtime.sendMessage({
      type: "pageHint",
      hint: "winr",
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
  const observer = new MutationObserver(() => notifyWinRDetected());
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
