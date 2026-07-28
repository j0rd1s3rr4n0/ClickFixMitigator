const DEFAULT_SETTINGS = {
  enabled: true,
  blockAllClipboard: true,
  familySafe: false,
  uiTheme: "system",
  muteDetectionNotifications: false,
  whitelist: [],
  exceptionlist: [],
  history: [],
  blocklistSources: [],
  allowlistSources: [],
  saveClipboardBackup: true,
  sendCountry: true,
  protectionProfile: "balanced",
  privacyBaselineEnabled: true,
  privacyBaselineShareSummary: true,
  detectionScreenshotCapture: false,
  alertMinSeverity: "red",
  scoreConfig: null,
  scoreConfigManagedBy: "local",
  scoreConfigServerUpdatedAt: 0
};

const SCORE_RULE_DEFINITIONS = {
  signals: [
    { flag: "commandMatch", key: "scoreSignalCommandMatch", defaultPoints: 18 },
    { flag: "shellHint", key: "scoreSignalShellHint", defaultPoints: 14 },
    { flag: "evasionHint", key: "scoreSignalEvasionHint", defaultPoints: 4 },
    { flag: "mismatch", key: "scoreSignalMismatch", defaultPoints: 8 },
    { flag: "clipboardWarning", key: "scoreSignalClipboardWarning", defaultPoints: 3 },
    { flag: "winRHint", key: "scoreSignalWinR", defaultPoints: 6 },
    { flag: "winXHint", key: "scoreSignalWinX", defaultPoints: 4 },
    { flag: "pasteSequenceHint", key: "scoreSignalPasteSequence", defaultPoints: 6 },
    { flag: "consoleHint", key: "scoreSignalConsole", defaultPoints: 5 },
    { flag: "fileExplorerHint", key: "scoreSignalFileExplorer", defaultPoints: 4 },
    { flag: "copyTriggerHint", key: "scoreSignalCopyTrigger", defaultPoints: 3 },
    { flag: "browserErrorHint", key: "scoreSignalBrowserError", defaultPoints: 4 },
    { flag: "fixActionHint", key: "scoreSignalFixAction", defaultPoints: 4 },
    { flag: "captchaHint", key: "scoreSignalCaptcha", defaultPoints: 2 }
  ],
  clipboard: [
    { flag: "hasCommand", key: "scoreClipboardCommand", defaultPoints: 18 },
    { flag: "hasExecutionHint", key: "scoreClipboardExecutionHint", defaultPoints: 14 },
    { flag: "hasUrl", key: "scoreClipboardUrl", defaultPoints: 8 },
    { flag: "hasBase64", key: "scoreClipboardBase64", defaultPoints: 4 },
    { flag: "hasHighEntropy", key: "scoreClipboardHighEntropy", defaultPoints: 2 },
    { flag: "hasShellMeta", key: "scoreClipboardShellMeta", defaultPoints: 4 },
    { flag: "isLong", key: "scoreClipboardLong", defaultPoints: 3 },
    { flag: "hasLeadingWhitespace", key: "scoreClipboardLeadingWhitespace", defaultPoints: 2 },
    { flag: "looksLikeCommand", key: "scoreClipboardLooksLikeCommand", defaultPoints: 5 }
  ],
  context: [
    { flag: "isAllowlisted", key: "scoreContextAllowlisted", defaultPoints: -35 },
    { flag: "isTrustedHost", key: "scoreContextTrustedHost", defaultPoints: -22 },
    { flag: "isCodeContext", key: "scoreContextCodeContext", defaultPoints: -18 },
    { flag: "isIframe", key: "scoreContextIframe", defaultPoints: 5 },
    { flag: "opaqueIframes", key: "scoreContextOpaqueIframes", defaultPoints: 5 },
    { flag: "opaqueIframesHigh", key: "scoreContextOpaqueIframes", defaultPoints: 10 }
  ]
};

function buildDefaultScoreRules() {
  const defaults = {};
  Object.entries(SCORE_RULE_DEFINITIONS).forEach(([group, rules]) => {
    defaults[group] = {};
    rules.forEach((rule) => {
      defaults[group][rule.flag] = rule.defaultPoints;
    });
  });
  return defaults;
}

const DEFAULT_SCORE_CONFIG = {
  weights: {
    signals: 0.44,
    clipboard: 0.26,
    context: 0.30
  },
  contextBaseScore: 42,
  rules: buildDefaultScoreRules()
};

const toggleEnabled = document.getElementById("toggle-enabled");
const toggleBlockAll = document.getElementById("toggle-block-all");
const toggleFamilySafe = document.getElementById("toggle-family-safe");
const toggleMuteNotifications = document.getElementById("toggle-mute-notifications");
const whitelistInput = document.getElementById("whitelist-input");
const addDomainButton = document.getElementById("add-domain");
const whitelistList = document.getElementById("whitelist-list");
const exceptionlistInput = document.getElementById("exceptionlist-input");
const addExceptionButton = document.getElementById("add-exception");
const exceptionlistList = document.getElementById("exceptionlist-list");
const blocklistInput = document.getElementById("blocklist-input");
const addBlocklistButton = document.getElementById("add-blocklist");
const blocklistList = document.getElementById("blocklist-list");
const toggleClipboardBackup = document.getElementById("toggle-clipboard-backup");
const toggleSendCountry = document.getElementById("toggle-send-country");
const protectionProfileSelect = document.getElementById("protection-profile");
const protectionProfileSummary = document.getElementById("protection-profile-summary");
const togglePrivacyBaseline = document.getElementById("toggle-privacy-baseline");
const toggleDetectionScreenshots = document.getElementById("toggle-detection-screenshots");
const alertMinSeveritySelect = document.getElementById("alert-min-severity");
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
const scoreWeightSignals = document.getElementById("score-weight-signals");
const scoreWeightClipboard = document.getElementById("score-weight-clipboard");
const scoreWeightContext = document.getElementById("score-weight-context");
const scoreContextBase = document.getElementById("score-context-base");
const scoreRulesContainer = document.getElementById("score-rules");
const scoreSaveButton = document.getElementById("score-save");
const scoreResetButton = document.getElementById("score-reset");
const scoreStatus = document.getElementById("score-settings-status");
const onboardingOverlay = document.getElementById("onboarding-overlay");
const onboardingTitle = document.getElementById("onboarding-title");
const onboardingDesc = document.getElementById("onboarding-desc");
const onboardingBody = document.getElementById("onboarding-body");
const onboardingPrev = document.getElementById("onboarding-prev");
const onboardingNext = document.getElementById("onboarding-next");
const onboardingSkip = document.getElementById("onboarding-skip");
const onboardingLanguage = document.getElementById("onboarding-language");
let onboardingStep = 0;
let onboardingFocus = null;
let onboardingBusy = false;

const SUPPORTED_LOCALES = ["en", "es", "ca", "de", "fr", "nl", "he", "ru", "zh", "ko", "ja", "pt", "ar", "hi"];
const DEFAULT_LOCALE = "en";
let activeMessages = null;
const RTL_LOCALES = new Set(["ar"]);
let applyingProtectionProfile = false;

const PROTECTION_PROFILE_PRESETS = {
  balanced: {
    enabled: true,
    blockAllClipboard: true,
    familySafe: false,
    muteDetectionNotifications: false,
    saveClipboardBackup: true,
    sendCountry: true,
    privacyBaselineEnabled: true,
    privacyBaselineShareSummary: true,
    detectionScreenshotCapture: false,
    alertMinSeverity: "orange",
    popupViewMode: "simple"
  },
  strict: {
    enabled: true,
    blockAllClipboard: true,
    familySafe: true,
    muteDetectionNotifications: false,
    saveClipboardBackup: true,
    sendCountry: true,
    privacyBaselineEnabled: true,
    privacyBaselineShareSummary: true,
    detectionScreenshotCapture: false,
    alertMinSeverity: "yellow",
    popupViewMode: "simple"
  },
  quiet: {
    enabled: true,
    blockAllClipboard: true,
    familySafe: false,
    muteDetectionNotifications: true,
    saveClipboardBackup: false,
    sendCountry: true,
    privacyBaselineEnabled: true,
    privacyBaselineShareSummary: true,
    detectionScreenshotCapture: false,
    alertMinSeverity: "red",
    popupViewMode: "simple"
  },
  analyst: {
    enabled: true,
    blockAllClipboard: false,
    familySafe: false,
    muteDetectionNotifications: false,
    saveClipboardBackup: true,
    sendCountry: true,
    privacyBaselineEnabled: false,
    privacyBaselineShareSummary: false,
    detectionScreenshotCapture: true,
    alertMinSeverity: "green",
    popupViewMode: "advanced"
  }
};

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

function extractHostname(value) {
  if (!value) {
    return "";
  }
  try {
    return new URL(value).hostname;
  } catch (error) {
    return "";
  }
}

function normalizeHostname(value) {
  const trimmed = String(value || "").trim();
  if (!trimmed) {
    return "";
  }
  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    return extractHostname(trimmed);
  }
  if (trimmed.includes("/")) {
    return extractHostname(`https://${trimmed}`);
  }
  return trimmed.replace(/^\*\./, "");
}

function normalizeAlertMinSeverity(value) {
  const normalized = String(value || "").toLowerCase();
  if (normalized === "yellow" || normalized === "orange" || normalized === "red") {
    return normalized;
  }
  return "green";
}

function normalizeProtectionProfile(value) {
  const normalized = String(value || "").toLowerCase();
  if (normalized === "strict" || normalized === "quiet" || normalized === "analyst" || normalized === "custom") {
    return normalized;
  }
  return "balanced";
}

function getProtectionProfileMeta(profile) {
  switch (normalizeProtectionProfile(profile)) {
    case "strict":
      return {
        label: "Estricto",
        summary: "Bloquea antes, alerta antes y fuerza un modo más cerrado para puestos de mayor riesgo."
      };
    case "quiet":
      return {
        label: "Discreto",
        summary: "Mantiene la protección base con menos ruido visual y menos datos locales guardados."
      };
    case "analyst":
      return {
        label: "Analista",
        summary: "Expone más detalle técnico, conserva evidencias y evita suavizar la detección con baseline local."
      };
    case "custom":
      return {
        label: "Personalizado",
        summary: "Has modificado opciones manualmente. Mantén este perfil si quieres conservar ajustes propios."
      };
    default:
      return {
        label: "Equilibrado",
        summary: "Protege sin generar tanto ruido y mantiene activado el aprendizaje local para reducir falsos positivos."
      };
  }
}

function updateProtectionProfileUi(profile) {
  const normalized = normalizeProtectionProfile(profile);
  if (protectionProfileSelect) {
    protectionProfileSelect.value = normalized;
  }
  if (protectionProfileSummary) {
    protectionProfileSummary.textContent = getProtectionProfileMeta(normalized).summary;
  }
}

function applySettingsToStateControls(settings) {
  toggleEnabled.checked = Boolean(settings.enabled);
  if (toggleBlockAll) {
    toggleBlockAll.checked = Boolean(settings.blockAllClipboard);
  }
  if (toggleFamilySafe) {
    toggleFamilySafe.checked = Boolean(settings.familySafe);
  }
  if (toggleMuteNotifications) {
    toggleMuteNotifications.checked = Boolean(settings.muteDetectionNotifications);
  }
  toggleClipboardBackup.checked = Boolean(settings.saveClipboardBackup);
  toggleSendCountry.checked = Boolean(settings.sendCountry);
  if (togglePrivacyBaseline) {
    togglePrivacyBaseline.checked = settings.privacyBaselineEnabled !== false;
  }
  if (toggleDetectionScreenshots) {
    toggleDetectionScreenshots.checked = Boolean(settings.detectionScreenshotCapture);
  }
  if (alertMinSeveritySelect) {
    alertMinSeveritySelect.value = normalizeAlertMinSeverity(settings.alertMinSeverity);
  }
  updateProtectionProfileUi(settings.protectionProfile);
}

async function saveSettingPatch(patch, options = {}) {
  const nextPatch = { ...patch };
  if (!applyingProtectionProfile && !options.keepProfile) {
    nextPatch.protectionProfile = "custom";
  }
  await chrome.storage.local.set(nextPatch);
  if (nextPatch.protectionProfile) {
    updateProtectionProfileUi(nextPatch.protectionProfile);
  }
}

async function applyProtectionProfile(profile) {
  const normalized = normalizeProtectionProfile(profile);
  if (normalized === "custom") {
    updateProtectionProfileUi("custom");
    await chrome.storage.local.set({ protectionProfile: "custom" });
    return;
  }
  const preset = PROTECTION_PROFILE_PRESETS[normalized];
  if (!preset) {
    return;
  }
  applyingProtectionProfile = true;
  try {
    const nextSettings = { ...preset, protectionProfile: normalized };
    await chrome.storage.local.set(nextSettings);
    applySettingsToStateControls(nextSettings);
    updateProtectionProfileUi(normalized);
  } finally {
    applyingProtectionProfile = false;
  }
}

async function initOnboarding() {
  if (!onboardingOverlay) {
    return;
  }
  const stored = await chrome.storage.local.get({ onboardingCompleted: false, uiLanguage: "" });
  const browserLocale = normalizeLocale((chrome.i18n.getUILanguage && chrome.i18n.getUILanguage()) || "");
  if (stored.onboardingCompleted) {
    return;
  }
  const selectedLocale = normalizeLocale(stored.uiLanguage || browserLocale || DEFAULT_LOCALE);
  await loadLocaleMessages(selectedLocale);

  const steps = [
    {
      titleKey: "onboardingStep1Title",
      descKey: "onboardingStep1Desc",
      target: "#language-section",
      showLang: true
    },
    {
      titleKey: "onboardingStep2Title",
      descKey: "onboardingStep2Desc",
      target: "#theme-section"
    },
    {
      titleKey: "onboardingStep3Title",
      descKey: "onboardingStep3Desc",
      target: "#state-section"
    },
    {
      titleKey: "onboardingStep4Title",
      descKey: "onboardingStep4Desc",
      target: "#state-section"
    },
    {
      titleKey: "onboardingStep5Title",
      descKey: "onboardingStep5Desc",
      target: "#score-settings-section"
    },
    {
      titleKey: "onboardingStep6Title",
      descKey: "onboardingStep6Desc",
      target: "#lists-section"
    }
  ];

  const focusTarget = (selector) => {
    if (onboardingFocus) {
      onboardingFocus.classList.remove("onboarding-focus");
    }
    const target = document.querySelector(selector);
    if (target) {
      onboardingFocus = target;
      target.classList.add("onboarding-focus");
      setTimeout(() => {
        target.scrollIntoView({ behavior: "smooth", block: "center" });
      }, 80);
    }
  };

  const renderStep = () => {
    const step = steps[onboardingStep];
    if (!step) {
      return;
    }
    onboardingTitle.textContent = step.titleKey ? t(step.titleKey) : "";
    onboardingDesc.textContent = step.descKey ? t(step.descKey) : "";
    const langWrap = document.getElementById("onboarding-language-wrap");
    if (langWrap) {
      langWrap.style.display = step.showLang ? "block" : "none";
    }
    onboardingPrev.disabled = onboardingStep === 0;
    onboardingNext.disabled = false;
    onboardingPrev.textContent = t("onboardingPrev");
    onboardingNext.textContent = onboardingStep === steps.length - 1 ? t("onboardingFinish") : t("onboardingNext");
    if (step.showLang && onboardingLanguage && languageSelect) {
      onboardingLanguage.value = stored.uiLanguage || browserLocale || languageSelect.value || DEFAULT_LOCALE;
    }
    focusTarget(step.target);
  };

  const completeOnboarding = () => {
    onboardingOverlay.hidden = true;
    onboardingOverlay.style.display = "none";
    document.body.classList.remove("onboarding-active");
    if (onboardingFocus) {
      onboardingFocus.classList.remove("onboarding-focus");
      onboardingFocus = null;
    }
    const savePromise = chrome.storage.local.set({ onboardingCompleted: true });
    if (savePromise?.catch) {
      savePromise.catch(() => {});
    }
  };

  const forceCompleteOnboarding = () => {
    try {
      completeOnboarding();
    } finally {
      onboardingOverlay.hidden = true;
      onboardingOverlay.style.display = "none";
      document.body.classList.remove("onboarding-active");
    }
  };

  const handleSkipClick = (event) => {
    try {
      event?.preventDefault?.();
      event?.stopPropagation?.();
      forceCompleteOnboarding();
    } finally {
      onboardingBusy = false;
    }
  };

  const handleNextClick = async (event) => {
    if (onboardingBusy) {
      return;
    }
    onboardingBusy = true;
    try {
      event?.preventDefault?.();
      event?.stopPropagation?.();
      const step = steps[onboardingStep];
      let localeSyncPromise = Promise.resolve();
      if (step?.showLang && onboardingLanguage && languageSelect) {
        const nextLocale = normalizeLocale(onboardingLanguage.value || browserLocale || DEFAULT_LOCALE);
        languageSelect.value = nextLocale;
        localeSyncPromise = Promise.resolve(chrome.storage.local.set({ uiLanguage: nextLocale }))
          .catch(() => {})
          .then(() => loadLocaleMessages(nextLocale))
          .catch((error) => {
            console.error("[ClickFix onboarding] Failed to sync locale", error);
          });
      }
      if (onboardingStep >= steps.length - 1) {
        forceCompleteOnboarding();
        await localeSyncPromise;
        return;
      }
      onboardingStep += 1;
      renderStep();
      await localeSyncPromise;
    } catch (error) {
      console.error("[ClickFix onboarding] Failed to advance tutorial", error);
      onboardingStep = Math.min(onboardingStep + 1, steps.length - 1);
      renderStep();
    } finally {
      onboardingBusy = false;
    }
  };

  window.__clickfixAdvanceOnboarding = handleNextClick;
  window.__clickfixSkipOnboarding = handleSkipClick;

  onboardingSkip?.addEventListener("click", handleSkipClick);
  onboardingSkip?.addEventListener("pointerdown", (event) => event.stopPropagation());
  onboardingOverlay?.addEventListener("click", (event) => {
    const target = event.target;
    if (target === onboardingOverlay || target?.classList?.contains("onboarding-backdrop")) {
      forceCompleteOnboarding();
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !onboardingOverlay.hidden) {
      forceCompleteOnboarding();
    }
  });
  onboardingPrev?.addEventListener("click", () => {
    if (onboardingStep > 0) {
      onboardingStep -= 1;
      renderStep();
    }
  });
  onboardingNext?.addEventListener("click", handleNextClick);
  onboardingNext?.addEventListener("pointerdown", (event) => event.stopPropagation());

  onboardingOverlay.hidden = false;
  document.body.classList.add("onboarding-active");
  renderStep();
}

function clampScorePoints(value, fallback) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }
  return Math.max(-100, Math.min(100, Math.round(numeric)));
}

function clampWeightPercent(value, fallback) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }
  return Math.max(0, Math.min(1, numeric / 100));
}

function normalizeWeight(value, fallback) {
  let numeric = Number(value);
  if (!Number.isFinite(numeric)) {
    return fallback;
  }
  if (numeric > 1) {
    if (numeric <= 100) {
      numeric = numeric / 100;
    } else {
      return fallback;
    }
  }
  return Math.max(0, Math.min(1, numeric));
}

function normalizeScoreConfig(rawConfig) {
  const base = DEFAULT_SCORE_CONFIG;
  const config = rawConfig && typeof rawConfig === "object" ? rawConfig : {};
  const weights = config.weights && typeof config.weights === "object" ? config.weights : {};
  const normalizedWeights = {
    signals: normalizeWeight(weights.signals, base.weights.signals),
    clipboard: normalizeWeight(weights.clipboard, base.weights.clipboard),
    context: normalizeWeight(weights.context, base.weights.context)
  };

  const weightSum = normalizedWeights.signals + normalizedWeights.clipboard + normalizedWeights.context;
  if (weightSum <= 0) {
    normalizedWeights.signals = base.weights.signals;
    normalizedWeights.clipboard = base.weights.clipboard;
    normalizedWeights.context = base.weights.context;
  }

  const contextBaseScore = Math.max(
    0,
    Math.min(100, Number.isFinite(Number(config.contextBaseScore)) ? Number(config.contextBaseScore) : base.contextBaseScore)
  );

  const normalizedRules = buildDefaultScoreRules();
  const rawRules = config.rules && typeof config.rules === "object" ? config.rules : {};
  Object.entries(SCORE_RULE_DEFINITIONS).forEach(([group, rules]) => {
    const groupRules = rawRules[group] && typeof rawRules[group] === "object" ? rawRules[group] : {};
    rules.forEach((rule) => {
      normalizedRules[group][rule.flag] = clampScorePoints(
        groupRules[rule.flag],
        rule.defaultPoints
      );
    });
  });

  return {
    weights: normalizedWeights,
    contextBaseScore,
    rules: normalizedRules
  };
}

function toPercent(value) {
  return Math.round(Number(value || 0) * 100);
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
    const settings = await loadSettings();
    renderHistory(settings.history || []);
    renderScoreSettings(settings.scoreConfig);
    applyScoreConfigMode(settings);
    if (settings.scoreConfigManagedBy !== "server") {
      setScoreStatus(scoreStatus?.dataset.statusKey || "");
    }
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
    exceptionlist: settings.exceptionlist ?? [],
    history: settings.history ?? [],
    alertCount: settings.alertCount ?? 0,
    blockCount: settings.blockCount ?? 0,
    blocklist: settings.blocklist ?? [],
    blocklistSources: settings.blocklistSources ?? [],
    allowlistSources: settings.allowlistSources ?? [],
    saveClipboardBackup: settings.saveClipboardBackup ?? true,
    sendCountry: settings.sendCountry ?? true,
    protectionProfile: normalizeProtectionProfile(settings.protectionProfile),
    privacyBaselineEnabled: settings.privacyBaselineEnabled !== false,
    privacyBaselineShareSummary: settings.privacyBaselineShareSummary !== false,
    detectionScreenshotCapture: settings.detectionScreenshotCapture ?? false,
    alertMinSeverity: normalizeAlertMinSeverity(settings.alertMinSeverity),
    scoreConfig: normalizeScoreConfig(settings.scoreConfig || DEFAULT_SCORE_CONFIG),
    scoreConfigManagedBy: settings.scoreConfigManagedBy === "server" ? "server" : "local",
    scoreConfigServerUpdatedAt: Number(settings.scoreConfigServerUpdatedAt || 0)
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

function renderExceptionlist(domains) {
  if (!exceptionlistList) {
    return;
  }
  exceptionlistList.innerHTML = "";
  if (!domains.length) {
    const item = document.createElement("li");
    item.textContent = t("optionsExceptionlistEmpty");
    item.classList.add("empty");
    exceptionlistList.appendChild(item);
    return;
  }

  domains.forEach((domain) => {
    const item = document.createElement("li");
    item.textContent = domain;
    const removeButton = document.createElement("button");
    removeButton.textContent = t("optionsRemove");
    removeButton.addEventListener("click", async () => {
      const settings = await loadSettings();
      const next = settings.exceptionlist.filter((entry) => entry !== domain);
      await chrome.storage.local.set({ exceptionlist: next });
      renderExceptionlist(next);
    });
    item.appendChild(removeButton);
    exceptionlistList.appendChild(item);
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
    const score = extractConfidenceScore(entry);
    const severity = scoreToSeverity(score);
    const card = document.createElement("div");
    card.classList.add("history-item");
    const time = new Date(entry.timestamp).toLocaleString();
    if (severity !== "neutral") {
      card.dataset.severity = severity;
    }
    if (score !== null && score !== undefined) {
      card.dataset.score = String(score);
    }
    const header = document.createElement("div");
    header.classList.add("history-header");
    const title = document.createElement("strong");
    title.classList.add("history-title");
    title.textContent = entry.hostname;
    header.appendChild(title);
    if (score !== null && score !== undefined) {
      const badge = document.createElement("span");
      badge.classList.add("history-score");
      badge.textContent = `${score}/100`;
      badge.title = t("alertConfidenceScore", score);
      header.appendChild(badge);
    }
    const body = document.createElement("div");
    body.classList.add("history-message");
    body.textContent = message;
    const reasonsList = buildReasonListElement(entry);
    const snippetsList = buildSnippetListElement(entry);
    const breakdown = buildScoreBreakdownElement(entry);
    const meta = document.createElement("small");
    meta.textContent = time;
    card.appendChild(header);
    card.appendChild(body);
    if (reasonsList) {
      card.appendChild(reasonsList);
    }
    if (snippetsList) {
      card.appendChild(snippetsList);
    }
    if (breakdown) {
      card.appendChild(breakdown);
    }
    card.appendChild(meta);
    historyContainer.appendChild(card);
  });
}

function buildReasonLabels(entry) {
  const reasonEntries = Array.isArray(entry?.reasonEntries) ? entry.reasonEntries : [];
  return reasonEntries
    .map((reason) => {
      if (
        !reason ||
        !reason.key ||
        reason.key === "alertConfidenceScore" ||
        reason.key === "alertSnippet"
      ) {
        return null;
      }
      const base = reason.value === undefined ? t(reason.key) : t(reason.key, reason.value);
      return base ? String(base).trim() : null;
    })
    .filter(Boolean);
}

function buildReasonListElement(entry) {
  const labels = buildReasonLabels(entry);
  if (!labels.length) {
    return null;
  }
  const list = document.createElement("ul");
  list.classList.add("score-breakdown-list");
  labels.forEach((label) => {
    const item = document.createElement("li");
    item.classList.add("score-breakdown-item");
    item.dataset.polarity = "pos";
    item.textContent = label;
    list.appendChild(item);
  });
  return list;
}

function extractEntrySnippets(entry) {
  const snippets = [];
  const addSnippet = (value) => {
    const normalized = String(value || "").trim();
    if (!normalized || snippets.includes(normalized)) {
      return;
    }
    snippets.push(normalized);
  };
  const directSnippets = Array.isArray(entry?.snippets) ? entry.snippets : [];
  directSnippets.forEach(addSnippet);
  const reasonEntries = Array.isArray(entry?.reasonEntries) ? entry.reasonEntries : [];
  reasonEntries.forEach((reason) => {
    if (reason?.key === "alertSnippet" && reason.value !== undefined) {
      addSnippet(reason.value);
    }
  });
  if (!snippets.length && entry?.detectedContent) {
    addSnippet(entry.detectedContent);
  }
  return snippets.map((snippet) => (snippet.length > 220 ? `${snippet.slice(0, 217)}...` : snippet));
}

function buildSnippetListElement(entry) {
  const snippets = extractEntrySnippets(entry);
  if (!snippets.length) {
    return null;
  }
  const container = document.createElement("div");
  container.classList.add("history-snippets");
  const title = document.createElement("div");
  title.classList.add("history-snippets-title");
  title.textContent = "Snippets detected";
  const list = document.createElement("ul");
  list.classList.add("history-snippet-list");
  snippets.forEach((snippet) => {
    const item = document.createElement("li");
    item.classList.add("history-snippet-item");
    const code = document.createElement("code");
    code.textContent = snippet;
    item.appendChild(code);
    list.appendChild(item);
  });
  container.appendChild(title);
  container.appendChild(list);
  return container;
}

function buildLocalizedAlertMessage(entry) {
  const reasonEntries = Array.isArray(entry?.reasonEntries) ? entry.reasonEntries : [];
  if (reasonEntries.length) {
    const parts = reasonEntries
      .map((reason) => {
        if (!reason || !reason.key) {
          return null;
        }
        if (reason.key === "alertConfidenceScore" || reason.key === "alertSnippet") {
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

function extractConfidenceScore(entry) {
  const directScore = entry?.confidenceScore;
  if (Number.isFinite(directScore)) {
    return Math.max(0, Math.min(100, Number(directScore)));
  }
  const reasonEntries = Array.isArray(entry?.reasonEntries) ? entry.reasonEntries : [];
  for (const reason of reasonEntries) {
    if (!reason || reason.key !== "alertConfidenceScore") {
      continue;
    }
    const parsed = Number.parseInt(reason.value, 10);
    if (!Number.isNaN(parsed)) {
      return Math.max(0, Math.min(100, parsed));
    }
  }
  const message = entry?.message || "";
  const match = message.match(/(\d{1,3})\s*\/\s*100/);
  if (match) {
    const parsed = Number.parseInt(match[1], 10);
    if (!Number.isNaN(parsed)) {
      return Math.max(0, Math.min(100, parsed));
    }
  }
  return null;
}

function getScoreDetails(entry) {
  if (!entry) {
    return null;
  }
  const raw = entry.scoreDetails ?? entry.score_details;
  if (!raw) {
    return null;
  }
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw);
      return parsed && Array.isArray(parsed.components) ? parsed : null;
    } catch (error) {
      return null;
    }
  }
  if (typeof raw === "object" && Array.isArray(raw.components)) {
    return raw;
  }
  return null;
}

function buildScoreBreakdownElement(entry) {
  const details = getScoreDetails(entry);
  if (!details) {
    return null;
  }
  const wrapper = document.createElement("details");
  wrapper.classList.add("score-breakdown");
  const summary = document.createElement("summary");
  summary.textContent = t("scoreBreakdownSummary", details.total ?? 0);
  wrapper.appendChild(summary);

  const components = Array.isArray(details.components) ? details.components : [];
  const weightParts = components
    .filter((component) => component && component.available !== false)
    .map((component) => {
      const label = component.labelKey ? t(component.labelKey) : component.id;
      const weight = Math.round((component.weight || 0) * 100);
      return `${label} ${weight}%`;
    });
  if (weightParts.length) {
    const meta = document.createElement("div");
    meta.classList.add("score-breakdown-meta");
    meta.textContent = t("scoreBreakdownWeights", weightParts.join(" · "));
    wrapper.appendChild(meta);
  }

  components.forEach((component) => {
    if (!component) {
      return;
    }
    const section = document.createElement("div");
    section.classList.add("score-breakdown-section");
    const title = document.createElement("div");
    title.classList.add("score-breakdown-title");
    const label = component.labelKey ? t(component.labelKey) : component.id;
    title.textContent = `${label} — ${component.score ?? 0}/100`;
    section.appendChild(title);

    const contributions = Array.isArray(component.contributions)
      ? component.contributions
      : [];
    if (component.available === false) {
      const empty = document.createElement("div");
      empty.classList.add("score-breakdown-empty");
      empty.textContent = t("scoreBreakdownUnavailable");
      section.appendChild(empty);
    } else if (!contributions.length) {
      const empty = document.createElement("div");
      empty.classList.add("score-breakdown-empty");
      empty.textContent = t("scoreBreakdownNoFactors");
      section.appendChild(empty);
    } else {
      const list = document.createElement("ul");
      list.classList.add("score-breakdown-list");
      contributions.forEach((entry) => {
        const item = document.createElement("li");
        item.classList.add("score-breakdown-item");
        const points = Number(entry.points) || 0;
        item.dataset.polarity = points >= 0 ? "pos" : "neg";
        const reasonLabel = entry.key ? t(entry.key) : "";
        const prefix = points >= 0 ? "+" : "";
        item.textContent = `${prefix}${points} ${reasonLabel}`.trim();
        list.appendChild(item);
      });
      section.appendChild(list);
    }
    wrapper.appendChild(section);
  });

  return wrapper;
}

function scoreToSeverity(score) {
  if (score === null || score === undefined) {
    return "neutral";
  }
  if (score > 40) {
    return "critical";
  }
  if (score >= 30) {
    return "high";
  }
  if (score > 15) {
    return "medium";
  }
  return "low";
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

function getScoreGroupTitleKey(group) {
  if (group === "signals") {
    return "scoreGroupSignals";
  }
  if (group === "clipboard") {
    return "scoreGroupClipboard";
  }
  return "scoreGroupContext";
}

function renderScoreSettings(scoreConfig) {
  if (!scoreRulesContainer) {
    return;
  }
  const normalized = normalizeScoreConfig(scoreConfig);

  if (scoreWeightSignals) {
    scoreWeightSignals.value = toPercent(normalized.weights.signals);
  }
  if (scoreWeightClipboard) {
    scoreWeightClipboard.value = toPercent(normalized.weights.clipboard);
  }
  if (scoreWeightContext) {
    scoreWeightContext.value = toPercent(normalized.weights.context);
  }
  if (scoreContextBase) {
    scoreContextBase.value = Math.round(normalized.contextBaseScore);
  }

  scoreRulesContainer.innerHTML = "";
  Object.entries(SCORE_RULE_DEFINITIONS).forEach(([group, rules]) => {
    const groupWrapper = document.createElement("div");
    groupWrapper.className = "score-group";
    const title = document.createElement("div");
    title.className = "score-group-title";
    title.textContent = t(getScoreGroupTitleKey(group));
    groupWrapper.appendChild(title);

    const grid = document.createElement("div");
    grid.className = "score-rule-grid";
    rules.forEach((rule) => {
      const row = document.createElement("label");
      row.className = "score-rule";
      const label = document.createElement("span");
      label.className = "score-rule-label";
      label.textContent = t(rule.key);
      const inputWrap = document.createElement("div");
      inputWrap.className = "score-rule-input";
      const input = document.createElement("input");
      input.type = "number";
      input.min = "-100";
      input.max = "100";
      input.step = "1";
      input.dataset.scoreGroup = group;
      input.dataset.scoreRule = rule.flag;
      input.value = normalized.rules[group]?.[rule.flag] ?? rule.defaultPoints;
      const unit = document.createElement("span");
      unit.className = "score-rule-unit";
      unit.textContent = t("scorePointsUnit");
      inputWrap.appendChild(input);
      inputWrap.appendChild(unit);
      row.appendChild(label);
      row.appendChild(inputWrap);
      grid.appendChild(row);
    });

    groupWrapper.appendChild(grid);
    scoreRulesContainer.appendChild(groupWrapper);
  });
}

function collectScoreConfigFromForm() {
  const rules = buildDefaultScoreRules();
  const inputs = scoreRulesContainer?.querySelectorAll("input[data-score-group]") || [];
  inputs.forEach((input) => {
    const group = input.dataset.scoreGroup;
    const rule = input.dataset.scoreRule;
    if (!group || !rule || !rules[group]) {
      return;
    }
    const parsed = Number(input.value);
    rules[group][rule] = clampScorePoints(
      parsed,
      rules[group][rule]
    );
  });

  const weights = {
    signals: clampWeightPercent(scoreWeightSignals?.value, DEFAULT_SCORE_CONFIG.weights.signals),
    clipboard: clampWeightPercent(scoreWeightClipboard?.value, DEFAULT_SCORE_CONFIG.weights.clipboard),
    context: clampWeightPercent(scoreWeightContext?.value, DEFAULT_SCORE_CONFIG.weights.context)
  };
  const contextBaseValue = Number(scoreContextBase?.value);
  const contextBaseScore = Number.isFinite(contextBaseValue)
    ? Math.max(0, Math.min(100, Math.round(contextBaseValue)))
    : DEFAULT_SCORE_CONFIG.contextBaseScore;

  return normalizeScoreConfig({
    weights,
    contextBaseScore,
    rules
  });
}

function setScoreStatus(messageKey) {
  if (!scoreStatus) {
    return;
  }
  if (!messageKey) {
    scoreStatus.textContent = "";
    delete scoreStatus.dataset.statusKey;
    return;
  }
  scoreStatus.dataset.statusKey = messageKey;
  scoreStatus.textContent = t(messageKey);
}

function setScoreControlsDisabled(disabled) {
  if (scoreWeightSignals) {
    scoreWeightSignals.disabled = disabled;
  }
  if (scoreWeightClipboard) {
    scoreWeightClipboard.disabled = disabled;
  }
  if (scoreWeightContext) {
    scoreWeightContext.disabled = disabled;
  }
  if (scoreContextBase) {
    scoreContextBase.disabled = disabled;
  }
  if (scoreSaveButton) {
    scoreSaveButton.disabled = disabled;
  }
  if (scoreResetButton) {
    scoreResetButton.disabled = disabled;
  }
  const ruleInputs = scoreRulesContainer?.querySelectorAll("input[data-score-group]") || [];
  ruleInputs.forEach((input) => {
    input.disabled = disabled;
  });
}

function applyScoreConfigMode(settings) {
  const serverManaged = settings?.scoreConfigManagedBy === "server";
  setScoreControlsDisabled(serverManaged);
  if (!serverManaged) {
    if (scoreStatus?.dataset?.statusKey === "scoreSettingsManagedByServer") {
      setScoreStatus("");
    }
    return;
  }
  const ts = Number(settings?.scoreConfigServerUpdatedAt || 0);
  const label = ts > 0 ? new Date(ts).toLocaleString() : "-";
  if (!scoreStatus) {
    return;
  }
  scoreStatus.dataset.statusKey = "scoreSettingsManagedByServer";
  scoreStatus.textContent = t("scoreSettingsManagedByServer", label);
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

async function addException(domain) {
  const normalized = normalizeHostname(domain);
  if (!normalized) {
    return;
  }
  const settings = await loadSettings();
  if (!settings.exceptionlist.includes(normalized)) {
    const next = [...settings.exceptionlist, normalized].sort();
    await chrome.storage.local.set({ exceptionlist: next });
    renderExceptionlist(next);
  }
}

addDomainButton.addEventListener("click", async () => {
  const domain = whitelistInput.value.trim();
  if (domain) {
    await addDomain(domain);
    whitelistInput.value = "";
  }
});

addExceptionButton?.addEventListener("click", async () => {
  const domain = exceptionlistInput?.value.trim();
  if (domain) {
    await addException(domain);
    if (exceptionlistInput) {
      exceptionlistInput.value = "";
    }
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

scoreSaveButton?.addEventListener("click", async () => {
  const nextConfig = collectScoreConfigFromForm();
  await chrome.storage.local.set({
    scoreConfig: nextConfig,
    scoreConfigManagedBy: "local",
    scoreConfigServerUpdatedAt: 0
  });
  setScoreStatus("scoreSettingsSaved");
  renderScoreSettings(nextConfig);
  applyScoreConfigMode({ scoreConfigManagedBy: "local", scoreConfigServerUpdatedAt: 0 });
});

scoreResetButton?.addEventListener("click", async () => {
  await chrome.storage.local.set({
    scoreConfig: DEFAULT_SCORE_CONFIG,
    scoreConfigManagedBy: "local",
    scoreConfigServerUpdatedAt: 0
  });
  setScoreStatus("scoreSettingsResetDone");
  renderScoreSettings(DEFAULT_SCORE_CONFIG);
  applyScoreConfigMode({ scoreConfigManagedBy: "local", scoreConfigServerUpdatedAt: 0 });
});

clearHistoryButton.addEventListener("click", async () => {
  await chrome.storage.local.set({ history: [] });
  renderHistory([]);
});

toggleEnabled.addEventListener("change", async () => {
  await saveSettingPatch({ enabled: toggleEnabled.checked });
});

toggleBlockAll?.addEventListener("change", async () => {
  await saveSettingPatch({ blockAllClipboard: toggleBlockAll.checked });
});

toggleFamilySafe?.addEventListener("change", async () => {
  await saveSettingPatch({ familySafe: toggleFamilySafe.checked });
});

toggleMuteNotifications?.addEventListener("change", async () => {
  await saveSettingPatch({ muteDetectionNotifications: toggleMuteNotifications.checked });
});

toggleClipboardBackup.addEventListener("change", async () => {
  await saveSettingPatch({ saveClipboardBackup: toggleClipboardBackup.checked });
});

toggleSendCountry.addEventListener("change", async () => {
  await saveSettingPatch({ sendCountry: toggleSendCountry.checked });
});

togglePrivacyBaseline?.addEventListener("change", async () => {
  const enabled = Boolean(togglePrivacyBaseline.checked);
  await saveSettingPatch({
    privacyBaselineEnabled: enabled,
    privacyBaselineShareSummary: enabled
  });
});

toggleDetectionScreenshots?.addEventListener("change", async () => {
  await saveSettingPatch({
    detectionScreenshotCapture: Boolean(toggleDetectionScreenshots.checked)
  });
});

alertMinSeveritySelect?.addEventListener("change", async () => {
  await saveSettingPatch({
    alertMinSeverity: normalizeAlertMinSeverity(alertMinSeveritySelect.value)
  });
});

protectionProfileSelect?.addEventListener("change", async () => {
  await applyProtectionProfile(protectionProfileSelect.value);
});

(async () => {
  await initOnboarding();
  await initLanguageSelector();
  await initThemeSelector();
  const settings = await loadSettings();
  applySettingsToStateControls(settings);
  renderWhitelist(settings.whitelist);
  renderExceptionlist(settings.exceptionlist);
  renderBlocklistSources(settings.blocklistSources);
  renderAllowlistSources(settings.allowlistSources);
  renderHistory(settings.history);
  renderStats(settings);
  renderScoreSettings(settings.scoreConfig);
  applyScoreConfigMode(settings);
  if (settings.scoreConfigManagedBy !== "server") {
    setScoreStatus("");
  }
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
  if (changes.exceptionlist) {
    loadSettings().then((settings) => renderExceptionlist(settings.exceptionlist));
  }
  if (changes.scoreConfig || changes.scoreConfigManagedBy || changes.scoreConfigServerUpdatedAt) {
    loadSettings().then((settings) => {
      renderScoreSettings(settings.scoreConfig);
      applyScoreConfigMode(settings);
    });
  }
  if (
    changes.protectionProfile ||
    changes.enabled ||
    changes.blockAllClipboard ||
    changes.familySafe ||
    changes.muteDetectionNotifications ||
    changes.saveClipboardBackup ||
    changes.sendCountry ||
    changes.privacyBaselineEnabled ||
    changes.detectionScreenshotCapture ||
    changes.alertMinSeverity
  ) {
    loadSettings().then((settings) => {
      applySettingsToStateControls(settings);
    });
  }
});
