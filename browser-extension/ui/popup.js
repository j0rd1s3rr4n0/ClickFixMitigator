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
const reportInput = document.getElementById("report-input");
const reportButton = document.getElementById("report-site");
const reportStatus = document.getElementById("report-status");

async function loadSettings() {
  const settings = await chrome.storage.local.get(DEFAULT_SETTINGS);
  return {
    enabled: settings.enabled ?? true,
    whitelist: settings.whitelist ?? [],
    history: settings.history ?? []
  };
}

function renderWhitelist(domains) {
  whitelistList.innerHTML = "";
  if (!domains.length) {
    const item = document.createElement("li");
    item.textContent = "Sin dominios en lista blanca.";
    item.classList.add("empty");
    whitelistList.appendChild(item);
    return;
  }

  domains.forEach((domain) => {
    const item = document.createElement("li");
    item.textContent = domain;
    const removeButton = document.createElement("button");
    removeButton.textContent = "Quitar";
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
    historyContainer.textContent = "No hay alertas recientes.";
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

toggleEnabled.addEventListener("change", async () => {
  await chrome.storage.local.set({ enabled: toggleEnabled.checked });
});

async function reportSite(targetUrl) {
  if (!targetUrl) {
    reportStatus.textContent = "Introduce una URL o usa la pestaña actual.";
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
    reportStatus.textContent = `Reporte enviado: ${parsedUrl.hostname}`;
  } catch (error) {
    reportStatus.textContent = "URL no válida.";
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
    reportStatus.textContent = "No se pudo detectar la pestaña activa.";
  }
});

(async () => {
  const settings = await loadSettings();
  toggleEnabled.checked = settings.enabled;
  renderWhitelist(settings.whitelist);
  renderHistory(settings.history);
})();
