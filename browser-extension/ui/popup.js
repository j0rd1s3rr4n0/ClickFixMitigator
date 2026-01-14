const DEFAULT_SETTINGS = {
  enabled: true,
  whitelist: [],
  history: []
};

const toggleEnabled = document.getElementById("toggle-enabled");
const whitelistInput = document.getElementById("whitelist-input");
const addDomainButton = document.getElementById("add-domain");
const whitelistList = document.getElementById("whitelist-list");
const historyContainer = document.getElementById("history");
const clearHistoryButton = document.getElementById("clear-history");
const clipboardHistoryContainer = document.getElementById("clipboard-history");
const clearClipboardHistoryButton = document.getElementById("clear-clipboard-history");
const reportInput = document.getElementById("report-input");
const reportButton = document.getElementById("report-site");
const reportStatus = document.getElementById("report-status");
const languageSelect = document.getElementById("language-select");
const allowlistStatus = document.getElementById("allowlist-status");

const SUPPORTED_LOCALES = ["en", "es"];
const DEFAULT_LOCALE = "en";
let activeMessages = null;

function t(key, substitutions) {
  if (activeMessages?.[key]?.message) {
    return formatMessage(activeMessages[key].message, substitutions);
  }
  return chrome.i18n.getMessage(key, substitutions) || key;
}

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

function applyTranslations() {
  document.querySelectorAll("[data-i18n]").forEach((element) => {
    element.textContent = t(element.dataset.i18n);
  });
  document.querySelectorAll("[data-i18n-placeholder]").forEach((element) => {
    element.setAttribute("placeholder", t(element.dataset.i18nPlaceholder));
  });
  document.title = t("popupTitle");
}

function sendRuntimeMessage(message) {
  return new Promise((resolve) => {
    chrome.runtime.sendMessage(message, (response) => {
      resolve(response);
    });
  });
}

function removeDuplicateReportSections() {
  const reportInputs = document.querySelectorAll("#report-input");
  const reportButtons = document.querySelectorAll("#report-site");
  const reportStatuses = document.querySelectorAll("#report-status");
  if (reportInputs.length <= 1 && reportButtons.length <= 1 && reportStatuses.length <= 1) {
    return;
  }
  const keepElements = new Set([reportInput, reportButton, reportStatus]);
  const duplicates = [...reportInputs, ...reportButtons, ...reportStatuses].filter((element) => {
    return element && !keepElements.has(element);
  });
  duplicates.forEach((element) => {
    const section = element.closest("section");
    if (section) {
      section.remove();
    } else {
      element.remove();
    }
  });
}

async function updateAllowlistStatus() {
  if (!allowlistStatus) {
    return;
  }
  allowlistStatus.hidden = true;
  const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
  const url = tabs?.[0]?.url;
  if (!url) {
    return;
  }
  const response = await sendRuntimeMessage({ type: "checkAllowlist", url });
  if (response?.allowlisted) {
    allowlistStatus.hidden = false;
  }
}

async function loadLocaleMessages(locale) {
  const response = await fetch(chrome.runtime.getURL(`_locales/${locale}/messages.json`));
  if (!response.ok) {
    throw new Error(`Failed to load locale ${locale}`);
  }
  activeMessages = await response.json();
  document.documentElement.lang = locale;
  applyTranslations();
}

async function initLanguageSelector() {
  if (!languageSelect) {
    return;
  }
  const { uiLanguage } = await chrome.storage.local.get({ uiLanguage: "" });
  const selectedLocale = normalizeLocale(uiLanguage || chrome.i18n.getUILanguage());
  languageSelect.value = selectedLocale;
  await loadLocaleMessages(selectedLocale);
  languageSelect.addEventListener("change", async () => {
    const nextLocale = normalizeLocale(languageSelect.value);
    await chrome.storage.local.set({ uiLanguage: nextLocale });
    await loadLocaleMessages(nextLocale);
  });
}

async function loadSettings() {
  const settings = await chrome.storage.local.get({ ...DEFAULT_SETTINGS, clipboardBackups: [] });
  return {
    enabled: settings.enabled ?? true,
    whitelist: settings.whitelist ?? [],
    history: settings.history ?? [],
    clipboardBackups: settings.clipboardBackups ?? []
  };
}

function renderWhitelist(domains) {
  whitelistList.innerHTML = "";
  if (!domains.length) {
    const item = document.createElement("li");
    item.textContent = t("popupWhitelistEmpty");
    item.classList.add("empty");
    whitelistList.appendChild(item);
    return;
  }

  domains.forEach((domain) => {
    const item = document.createElement("li");
    item.textContent = domain;
    const removeButton = document.createElement("button");
    removeButton.textContent = t("optionsRemove");
    removeButton.addEventListener("click", async () => {
      const settings = await loadSettings();
      const next = settings.whitelist.filter((entry) => entry !== domain);
      await chrome.storage.local.set({ whitelist: next });
      renderWhitelist(next);
    });
    item.appendChild(removeButton);
    whitelistList.appendChild(item);
  });
}

function renderHistory(history) {
  historyContainer.innerHTML = "";
  if (!history.length) {
    historyContainer.textContent = t("optionsHistoryEmpty");
    historyContainer.classList.add("empty");
    return;
  }

  historyContainer.classList.remove("empty");
  history.forEach((entry) => {
    const card = document.createElement("div");
    card.classList.add("history-item");
    const time = new Date(entry.timestamp).toLocaleString();
    card.innerHTML = `<strong>${entry.hostname}</strong><div>${entry.message}</div><small>${time}</small>`;
    historyContainer.appendChild(card);
  });
}

function renderClipboardHistory(entries) {
  if (!clipboardHistoryContainer) {
    return;
  }
  clipboardHistoryContainer.innerHTML = "";
  if (!entries.length) {
    clipboardHistoryContainer.textContent = t("popupClipboardEmpty");
    clipboardHistoryContainer.classList.add("empty");
    return;
  }
  clipboardHistoryContainer.classList.remove("empty");
  entries.forEach((entry) => {
    const card = document.createElement("div");
    card.classList.add("history-item", "clipboard-item");
    if (entry.malicious) {
      card.classList.add("malicious");
    }

    const text = document.createElement("div");
    text.classList.add("clipboard-text");
    text.textContent = entry.text;

    const meta = document.createElement("small");
    const time = entry.timestamp ? new Date(entry.timestamp).toLocaleString() : "";
    meta.textContent = entry.url ? `${time} • ${entry.url}` : time;

    const actions = document.createElement("div");
    actions.classList.add("clipboard-actions");
    const copyButton = document.createElement("button");
    copyButton.type = "button";
    copyButton.textContent = t("popupClipboardCopy");
    copyButton.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(entry.text || "");
        copyButton.textContent = t("popupClipboardCopied");
        setTimeout(() => {
          copyButton.textContent = t("popupClipboardCopy");
        }, 1500);
      } catch (error) {
        copyButton.textContent = t("popupClipboardCopy");
      }
    });
    actions.appendChild(copyButton);

    card.appendChild(text);
    if (meta.textContent) {
      card.appendChild(meta);
    }
    card.appendChild(actions);
    clipboardHistoryContainer.appendChild(card);
  });
}

async function addDomain(domain) {
  if (!domain) {
    return;
  }
  const settings = await loadSettings();
  if (!settings.whitelist.includes(domain)) {
    const next = [...settings.whitelist, domain].sort();
    await chrome.storage.local.set({ whitelist: next });
    renderWhitelist(next);
  }
}

async function addCurrentDomain() {
  const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
  const url = tabs?.[0]?.url;
  if (!url) {
    return;
  }
  try {
    const hostname = new URL(url).hostname;
    await addDomain(hostname);
  } catch (error) {
    return;
  }
}

addDomainButton.addEventListener("click", async () => {
  const domain = whitelistInput.value.trim();
  if (domain) {
    await addDomain(domain);
    whitelistInput.value = "";
  } else {
    await addCurrentDomain();
  }
});

clearHistoryButton.addEventListener("click", async () => {
  await chrome.storage.local.set({ history: [] });
  renderHistory([]);
});

clearClipboardHistoryButton?.addEventListener("click", async () => {
  await chrome.storage.local.set({ clipboardBackups: [] });
  renderClipboardHistory([]);
});

toggleEnabled.addEventListener("change", async () => {
  await chrome.storage.local.set({ enabled: toggleEnabled.checked });
});

async function reportSite(targetUrl) {
  if (!targetUrl) {
    reportStatus.textContent = t("popupReportStatusMissing");
    return;
  }
  try {
    const parsedUrl = new URL(targetUrl);
    chrome.runtime.sendMessage({
      type: "manualReport",
      url: parsedUrl.href,
      hostname: parsedUrl.hostname,
      timestamp: Date.now()
    });
    reportStatus.textContent = t("popupReportStatusSent", parsedUrl.hostname);
  } catch (error) {
    reportStatus.textContent = t("popupReportStatusInvalid");
  }
}

reportButton.addEventListener("click", async () => {
  const value = reportInput.value.trim();
  if (value) {
    await reportSite(value);
    reportInput.value = "";
    return;
  }
  const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
  const url = tabs?.[0]?.url;
  if (url) {
    await reportSite(url);
  } else {
    reportStatus.textContent = t("popupReportStatusNoTab");
  }
});

(async () => {
  removeDuplicateReportSections();
  await initLanguageSelector();
  const settings = await loadSettings();
  toggleEnabled.checked = settings.enabled;
  renderWhitelist(settings.whitelist);
  renderHistory(settings.history);
  renderClipboardHistory(settings.clipboardBackups);
  await updateAllowlistStatus();
})();
