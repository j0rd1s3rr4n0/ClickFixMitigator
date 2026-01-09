const DEFAULT_SETTINGS = {
  enabled: true,
  whitelist: [],
  history: [],
  blocklistSources: [],
  allowlistSources: []
};

const toggleEnabled = document.getElementById("toggle-enabled");
const whitelistInput = document.getElementById("whitelist-input");
const addDomainButton = document.getElementById("add-domain");
const whitelistList = document.getElementById("whitelist-list");
const blocklistInput = document.getElementById("blocklist-input");
const addBlocklistButton = document.getElementById("add-blocklist");
const blocklistList = document.getElementById("blocklist-list");
const allowlistInput = document.getElementById("allowlist-input");
const addAllowlistButton = document.getElementById("add-allowlist");
const allowlistList = document.getElementById("allowlist-list");
const historyContainer = document.getElementById("history");
const clearHistoryButton = document.getElementById("clear-history");
const languageSelect = document.getElementById("language-select");

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
  const settings = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: settings.enabled ?? true,
    whitelist: settings.whitelist ?? [],
    history: settings.history ?? [],
    blocklistSources: settings.blocklistSources ?? [],
    allowlistSources: settings.allowlistSources ?? []
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

function renderBlocklistSources(sources) {
  blocklistList.innerHTML = "";
  if (!sources.length) {
    const item = document.createElement("li");
    item.textContent = t("optionsBlocklistEmpty");
    item.classList.add("empty");
    blocklistList.appendChild(item);
    return;
  }

  sources.forEach((source) => {
    const item = document.createElement("li");
    item.textContent = source;
    const removeButton = document.createElement("button");
    removeButton.textContent = t("optionsRemove");
    removeButton.addEventListener("click", async () => {
      const settings = await loadSettings();
      const next = settings.blocklistSources.filter((entry) => entry !== source);
      await chrome.storage.local.set({ blocklistSources: next });
      renderBlocklistSources(next);
    });
    item.appendChild(removeButton);
    blocklistList.appendChild(item);
  });
}

function renderAllowlistSources(sources) {
  allowlistList.innerHTML = "";
  if (!sources.length) {
    const item = document.createElement("li");
    item.textContent = t("optionsAllowlistEmpty");
    item.classList.add("empty");
    allowlistList.appendChild(item);
    return;
  }

  sources.forEach((source) => {
    const item = document.createElement("li");
    item.textContent = source;
    const removeButton = document.createElement("button");
    removeButton.textContent = t("optionsRemove");
    removeButton.addEventListener("click", async () => {
      const settings = await loadSettings();
      const next = settings.allowlistSources.filter((entry) => entry !== source);
      await chrome.storage.local.set({ allowlistSources: next });
      renderAllowlistSources(next);
    });
    item.appendChild(removeButton);
    allowlistList.appendChild(item);
  });
}

async function addBlocklistSource(source) {
  if (!source) {
    return;
  }
  const settings = await loadSettings();
  if (!settings.blocklistSources.includes(source)) {
    const next = [...settings.blocklistSources, source].sort();
    await chrome.storage.local.set({ blocklistSources: next });
    renderBlocklistSources(next);
  }
}

async function addAllowlistSource(source) {
  if (!source) {
    return;
  }
  const settings = await loadSettings();
  if (!settings.allowlistSources.includes(source)) {
    const next = [...settings.allowlistSources, source].sort();
    await chrome.storage.local.set({ allowlistSources: next });
    renderAllowlistSources(next);
  }
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

addDomainButton.addEventListener("click", async () => {
  const domain = whitelistInput.value.trim();
  if (domain) {
    await addDomain(domain);
    whitelistInput.value = "";
  }
});

addBlocklistButton.addEventListener("click", async () => {
  const source = blocklistInput.value.trim();
  if (source) {
    await addBlocklistSource(source);
    blocklistInput.value = "";
  }
});

addAllowlistButton.addEventListener("click", async () => {
  const source = allowlistInput.value.trim();
  if (source) {
    await addAllowlistSource(source);
    allowlistInput.value = "";
  }
});

clearHistoryButton.addEventListener("click", async () => {
  await chrome.storage.local.set({ history: [] });
  renderHistory([]);
});

toggleEnabled.addEventListener("change", async () => {
  await chrome.storage.local.set({ enabled: toggleEnabled.checked });
});

(async () => {
  await initLanguageSelector();
  const settings = await loadSettings();
  toggleEnabled.checked = settings.enabled;
  renderWhitelist(settings.whitelist);
  renderBlocklistSources(settings.blocklistSources);
  renderAllowlistSources(settings.allowlistSources);
  renderHistory(settings.history);
})();
