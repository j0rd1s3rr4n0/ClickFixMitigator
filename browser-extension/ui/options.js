const DEFAULT_SETTINGS = {
  enabled: true,
  whitelist: [],
  history: [],
  blocklistSources: []
};

const toggleEnabled = document.getElementById("toggle-enabled");
const whitelistInput = document.getElementById("whitelist-input");
const addDomainButton = document.getElementById("add-domain");
const whitelistList = document.getElementById("whitelist-list");
const blocklistInput = document.getElementById("blocklist-input");
const addBlocklistButton = document.getElementById("add-blocklist");
const blocklistList = document.getElementById("blocklist-list");
const historyContainer = document.getElementById("history");
const clearHistoryButton = document.getElementById("clear-history");

function t(key, substitutions) {
  return chrome.i18n.getMessage(key, substitutions) || key;
}

function applyTranslations() {
  document.querySelectorAll("[data-i18n]").forEach((element) => {
    element.textContent = t(element.dataset.i18n);
  });
  document.querySelectorAll("[data-i18n-placeholder]").forEach((element) => {
    element.setAttribute("placeholder", t(element.dataset.i18nPlaceholder));
  });
}

async function loadSettings() {
  const settings = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: settings.enabled ?? true,
    whitelist: settings.whitelist ?? [],
    history: settings.history ?? [],
    blocklistSources: settings.blocklistSources ?? []
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

clearHistoryButton.addEventListener("click", async () => {
  await chrome.storage.local.set({ history: [] });
  renderHistory([]);
});

toggleEnabled.addEventListener("change", async () => {
  await chrome.storage.local.set({ enabled: toggleEnabled.checked });
});

(async () => {
  applyTranslations();
  const settings = await loadSettings();
  toggleEnabled.checked = settings.enabled;
  renderWhitelist(settings.whitelist);
  renderBlocklistSources(settings.blocklistSources);
  renderHistory(settings.history);
})();
