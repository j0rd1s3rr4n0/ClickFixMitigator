const DEFAULT_SETTINGS = {
  enabled: true,
  blockAllClipboard: true,
  familySafe: false,
  uiTheme: "system",
  muteDetectionNotifications: false,
  whitelist: [],
  history: [],
  blocklistSources: [],
  allowlistSources: [],
  saveClipboardBackup: true,
  sendCountry: true
};

const toggleEnabled = document.getElementById("toggle-enabled");
const toggleBlockAll = document.getElementById("toggle-block-all");
const toggleFamilySafe = document.getElementById("toggle-family-safe");
const toggleMuteNotifications = document.getElementById("toggle-mute-notifications");
const whitelistInput = document.getElementById("whitelist-input");
const addDomainButton = document.getElementById("add-domain");
const whitelistList = document.getElementById("whitelist-list");
const blocklistInput = document.getElementById("blocklist-input");
const addBlocklistButton = document.getElementById("add-blocklist");
const blocklistList = document.getElementById("blocklist-list");
const toggleClipboardBackup = document.getElementById("toggle-clipboard-backup");
const toggleSendCountry = document.getElementById("toggle-send-country");
const allowlistInput = document.getElementById("allowlist-input");
const addAllowlistButton = document.getElementById("add-allowlist");
const allowlistList = document.getElementById("allowlist-list");
const historyContainer = document.getElementById("history");
const clearHistoryButton = document.getElementById("clear-history");
const languageSelect = document.getElementById("language-select");
const themeSelect = document.getElementById("theme-select");
const statsTotalAlerts = document.getElementById("stats-total-alerts");
const statsTotalBlocked = document.getElementById("stats-total-blocked");
const statsBlocklistCount = document.getElementById("stats-blocklist-count");
const statsAllowlistCount = document.getElementById("stats-allowlist-count");
const statsBlockRateValue = document.getElementById("stats-block-rate-value");
const statsBlockRateRing = document.getElementById("stats-block-rate-ring");
const statsDetectionsChart = document.getElementById("stats-detections-chart");
const statsDetectionsTotal = document.getElementById("stats-detections-total");

const SUPPORTED_LOCALES = ["en", "es", "ca", "de", "fr", "nl", "he", "ru", "zh", "ko", "ja", "pt", "ar", "hi"];
const DEFAULT_LOCALE = "en";
let activeMessages = null;
const RTL_LOCALES = new Set(["ar"]);

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
  document.title = t("optionsTitle");
}

function applyDirection(locale) {
  document.documentElement.dir = RTL_LOCALES.has(locale) ? "rtl" : "ltr";
}

async function loadLocaleMessages(locale) {
  try {
    const response = await fetch(chrome.runtime.getURL(`_locales/${locale}/messages.json`));
    if (!response.ok) {
      throw new Error(`Failed to load locale ${locale}`);
    }
    activeMessages = await response.json();
  } catch (error) {
    activeMessages = null;
  }
  document.documentElement.lang = locale;
  applyDirection(locale);
  applyTranslations();
}

async function initLanguageSelector() {
  if (!languageSelect) {
    return;
  }
  const { uiLanguage } = await chrome.storage.local.get({ uiLanguage: "" });
  const selectedLocale = normalizeLocale(uiLanguage || "en");
  languageSelect.value = selectedLocale;
  await loadLocaleMessages(selectedLocale);
  languageSelect.addEventListener("change", async () => {
    const nextLocale = normalizeLocale(languageSelect.value);
    await chrome.storage.local.set({ uiLanguage: nextLocale });
    await loadLocaleMessages(nextLocale);
  });
}

function normalizeTheme(value) {
  return value === "dark" || value === "light" ? value : "system";
}

function applyTheme(value) {
  const theme = normalizeTheme(value);
  if (theme === "system") {
    document.documentElement.removeAttribute("data-theme");
  } else {
    document.documentElement.dataset.theme = theme;
  }
  return theme;
}

async function initThemeSelector() {
  if (!themeSelect) {
    return;
  }
  const { uiTheme } = await chrome.storage.local.get({ uiTheme: "system" });
  const selectedTheme = applyTheme(uiTheme);
  themeSelect.value = selectedTheme;
  themeSelect.addEventListener("change", async () => {
    const nextTheme = normalizeTheme(themeSelect.value);
    await chrome.storage.local.set({ uiTheme: nextTheme });
    applyTheme(nextTheme);
  });
}

async function loadSettings() {
  const settings = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: settings.enabled ?? true,
    blockAllClipboard: settings.blockAllClipboard ?? false,
    familySafe: settings.familySafe ?? false,
    uiTheme: settings.uiTheme ?? "system",
    muteDetectionNotifications: settings.muteDetectionNotifications ?? false,
    whitelist: settings.whitelist ?? [],
    history: settings.history ?? [],
    alertCount: settings.alertCount ?? 0,
    blockCount: settings.blockCount ?? 0,
    blocklist: settings.blocklist ?? [],
    blocklistSources: settings.blocklistSources ?? [],
    allowlistSources: settings.allowlistSources ?? [],
    saveClipboardBackup: settings.saveClipboardBackup ?? true,
    sendCountry: settings.sendCountry ?? true
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
    const message = buildLocalizedAlertMessage(entry);
    const card = document.createElement("div");
    card.classList.add("history-item");
    const time = new Date(entry.timestamp).toLocaleString();
    const title = document.createElement("strong");
    title.textContent = entry.hostname;
    const body = document.createElement("div");
    body.classList.add("history-message");
    body.textContent = message;
    const meta = document.createElement("small");
    meta.textContent = time;
    card.appendChild(title);
    card.appendChild(body);
    card.appendChild(meta);
    historyContainer.appendChild(card);
  });
}

function buildLocalizedAlertMessage(entry) {
  const reasonEntries = Array.isArray(entry?.reasonEntries) ? entry.reasonEntries : [];
  if (reasonEntries.length) {
    const parts = reasonEntries
      .map((reason) => {
        if (!reason || !reason.key) {
          return null;
        }
        return reason.value === undefined ? t(reason.key) : t(reason.key, reason.value);
      })
      .filter(Boolean);
    if (parts.length) {
      return formatAlertMessage(parts);
    }
  }
  return entry?.message || "";
}

function formatAlertMessage(parts) {
  if (!Array.isArray(parts) || parts.length === 0) {
    return "";
  }
  if (parts.length === 1) {
    return parts[0];
  }
  return parts.map((part) => `• ${part}`).join("\n");
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

function formatNumber(value) {
  return Number(value || 0).toLocaleString();
}

function buildDailyCounts(history, days = 7) {
  const counts = Array.from({ length: days }, () => 0);
  if (!Array.isArray(history)) {
    return counts;
  }
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  history.forEach((entry) => {
    const timestamp = entry?.timestamp;
    if (!timestamp) {
      return;
    }
    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) {
      return;
    }
    const dayStart = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const diffDays = Math.floor((today - dayStart) / 86400000);
    if (diffDays >= 0 && diffDays < days) {
      counts[days - 1 - diffDays] += 1;
    }
  });
  return counts;
}

function renderDetectionsChart(counts) {
  if (!statsDetectionsChart) {
    return;
  }
  statsDetectionsChart.innerHTML = "";
  const max = Math.max(1, ...counts);
  counts.forEach((count) => {
    const bar = document.createElement("div");
    bar.className = "stats-bar";
    bar.style.height = `${Math.round((count / max) * 100)}%`;
    if (count === 0) {
      bar.dataset.empty = "true";
    }
    bar.title = formatNumber(count);
    statsDetectionsChart.appendChild(bar);
  });
}

function renderStats(settings) {
  if (!statsTotalAlerts) {
    return;
  }
  const alertCount = Number(settings.alertCount || 0);
  const blockCount = Number(settings.blockCount || 0);
  const blocklistCount = Array.isArray(settings.blocklist) ? settings.blocklist.length : 0;
  const allowlistCount = Array.isArray(settings.whitelist) ? settings.whitelist.length : 0;

  statsTotalAlerts.textContent = formatNumber(alertCount);
  if (statsTotalBlocked) {
    statsTotalBlocked.textContent = formatNumber(blockCount);
  }
  if (statsBlocklistCount) {
    statsBlocklistCount.textContent = formatNumber(blocklistCount);
  }
  if (statsAllowlistCount) {
    statsAllowlistCount.textContent = formatNumber(allowlistCount);
  }

  const rate = alertCount ? Math.min(1, Math.max(0, blockCount / alertCount)) : 0;
  const percent = Math.round(rate * 100);
  if (statsBlockRateValue) {
    statsBlockRateValue.textContent = `${percent}%`;
  }
  if (statsBlockRateRing) {
    statsBlockRateRing.style.setProperty("--rate", `${percent}%`);
  }

  const counts = buildDailyCounts(settings.history || [], 7);
  renderDetectionsChart(counts);
  if (statsDetectionsTotal) {
    const total = counts.reduce((sum, value) => sum + value, 0);
    statsDetectionsTotal.textContent = formatNumber(total);
  }
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

toggleBlockAll?.addEventListener("change", async () => {
  await chrome.storage.local.set({ blockAllClipboard: toggleBlockAll.checked });
});

toggleFamilySafe?.addEventListener("change", async () => {
  await chrome.storage.local.set({ familySafe: toggleFamilySafe.checked });
});

toggleMuteNotifications?.addEventListener("change", async () => {
  await chrome.storage.local.set({ muteDetectionNotifications: toggleMuteNotifications.checked });
});

toggleClipboardBackup.addEventListener("change", async () => {
  await chrome.storage.local.set({ saveClipboardBackup: toggleClipboardBackup.checked });
});

toggleSendCountry.addEventListener("change", async () => {
  await chrome.storage.local.set({ sendCountry: toggleSendCountry.checked });
});

(async () => {
  await initLanguageSelector();
  await initThemeSelector();
  const settings = await loadSettings();
  toggleEnabled.checked = settings.enabled;
  if (toggleBlockAll) {
    toggleBlockAll.checked = settings.blockAllClipboard;
  }
  if (toggleFamilySafe) {
    toggleFamilySafe.checked = settings.familySafe;
  }
  if (toggleMuteNotifications) {
    toggleMuteNotifications.checked = settings.muteDetectionNotifications;
  }
  toggleClipboardBackup.checked = settings.saveClipboardBackup;
  toggleSendCountry.checked = settings.sendCountry;
  renderWhitelist(settings.whitelist);
  renderBlocklistSources(settings.blocklistSources);
  renderAllowlistSources(settings.allowlistSources);
  renderHistory(settings.history);
  renderStats(settings);
})();

chrome.storage.onChanged.addListener((changes, area) => {
  if (area !== "local") {
    return;
  }
  const shouldUpdateStats =
    changes.history ||
    changes.alertCount ||
    changes.blockCount ||
    changes.blocklist ||
    changes.whitelist;
  if (shouldUpdateStats) {
    loadSettings().then(renderStats);
  }
});
