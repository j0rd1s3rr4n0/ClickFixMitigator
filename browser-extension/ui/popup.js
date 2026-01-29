const DEFAULT_SETTINGS = {
  enabled: true,
  blockAllClipboard: true,
  familySafe: false,
  uiTheme: "system",
  muteDetectionNotifications: false,
  whitelist: [],
  history: []
};

const toggleEnabled = document.getElementById("toggle-enabled");
const toggleBlockAll = document.getElementById("toggle-block-all");
const toggleFamilySafe = document.getElementById("toggle-family-safe");
const toggleMuteNotifications = document.getElementById("toggle-mute-notifications");
const whitelistInput = document.getElementById("whitelist-input");
const addDomainButton = document.getElementById("add-domain");
const whitelistList = document.getElementById("whitelist-list");
const historyContainer = document.getElementById("history");
const clearHistoryButton = document.getElementById("clear-history");
const clipboardHistoryContainer = document.getElementById("clipboard-history");
const clearClipboardHistoryButton = document.getElementById("clear-clipboard-history");
const reportInput = document.getElementById("report-input");
const reportReasonInput = document.getElementById("report-reason");
const reportButton = document.getElementById("report-site");
const reportStatus = document.getElementById("report-status");
const languageSelect = document.getElementById("language-select");
const themeSelect = document.getElementById("theme-select");
const allowlistStatus = document.getElementById("allowlist-status");
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
  document.title = t("popupTitle");
}

function applyDirection(locale) {
  document.documentElement.dir = RTL_LOCALES.has(locale) ? "rtl" : "ltr";
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
  const settings = await chrome.storage.local.get({ ...DEFAULT_SETTINGS, clipboardBackups: [] });
  return {
    enabled: settings.enabled ?? true,
    blockAllClipboard: settings.blockAllClipboard ?? false,
    familySafe: settings.familySafe ?? false,
    uiTheme: settings.uiTheme ?? "system",
    muteDetectionNotifications: settings.muteDetectionNotifications ?? false,
    whitelist: settings.whitelist ?? [],
    history: settings.history ?? [],
    clipboardBackups: settings.clipboardBackups ?? [],
    alertCount: settings.alertCount ?? 0,
    blockCount: settings.blockCount ?? 0,
    blocklist: settings.blocklist ?? []
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
  resizePopupToContent();
}

function renderHistory(history) {
  historyContainer.innerHTML = "";
  if (!history.length) {
    historyContainer.textContent = t("optionsHistoryEmpty");
    historyContainer.classList.add("empty");
    resizePopupToContent();
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
  resizePopupToContent();
}

function renderClipboardHistory(entries) {
  if (!clipboardHistoryContainer) {
    return;
  }
  clipboardHistoryContainer.innerHTML = "";
  if (!entries.length) {
    clipboardHistoryContainer.textContent = t("popupClipboardEmpty");
    clipboardHistoryContainer.classList.add("empty");
    resizePopupToContent();
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
  resizePopupToContent();
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString();
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

function resizePopupToContent() {
  requestAnimationFrame(() => {
    const height = Math.min(700, Math.max(360, document.documentElement.scrollHeight));
    const contentWidth = Math.max(
      document.documentElement.scrollWidth,
      document.body.scrollWidth
    );
    const width = Math.min(520, Math.max(320, contentWidth));
    document.documentElement.style.height = `${height}px`;
    document.body.style.height = `${height}px`;
    document.documentElement.style.width = `${width}px`;
    document.body.style.width = `${width}px`;
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

toggleBlockAll?.addEventListener("change", async () => {
  await chrome.storage.local.set({ blockAllClipboard: toggleBlockAll.checked });
});

toggleFamilySafe?.addEventListener("change", async () => {
  await chrome.storage.local.set({ familySafe: toggleFamilySafe.checked });
});

toggleMuteNotifications?.addEventListener("change", async () => {
  await chrome.storage.local.set({ muteDetectionNotifications: toggleMuteNotifications.checked });
});

async function reportSite(targetUrl) {
  if (!targetUrl) {
    reportStatus.textContent = t("popupReportStatusMissing");
    return;
  }
  try {
    const parsedUrl = new URL(targetUrl);
    const reason = reportReasonInput?.value
      ? reportReasonInput.value.trim().replace(/\s+/g, " ").slice(0, 160)
      : "";
    chrome.runtime.sendMessage({
      type: "manualReport",
      url: parsedUrl.href,
      hostname: parsedUrl.hostname,
      timestamp: Date.now(),
      reason
    });
    reportStatus.textContent = t("popupReportStatusSent", parsedUrl.hostname);
    if (reportReasonInput) {
      reportReasonInput.value = "";
    }
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
  renderWhitelist(settings.whitelist);
  renderHistory(settings.history);
  renderClipboardHistory(settings.clipboardBackups);
  renderStats(settings);
  await updateAllowlistStatus();
  resizePopupToContent();
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
    loadSettings().then((settings) => {
      renderStats(settings);
      resizePopupToContent();
    });
  }
});
