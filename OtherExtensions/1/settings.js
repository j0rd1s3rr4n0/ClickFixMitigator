/**
 * BinHex.Ninja Security - Settings Page Script
 * Handles data collection settings and domain whitelist management
 */

// Built-in trusted domains (matches content.js WHITELIST)
const BUILTIN_DOMAINS = [
  // Search engines & major tech
  "google.com",
  "bing.com",
  "yahoo.com",
  "duckduckgo.com",
  "baidu.com",
  "youtube.com",
  "facebook.com",
  "twitter.com",
  "x.com",
  "instagram.com",
  "linkedin.com",
  "tiktok.com",
  "pinterest.com",
  "reddit.com",
  "tumblr.com",
  // Developer sites
  "github.com",
  "gitlab.com",
  "bitbucket.org",
  "stackoverflow.com",
  "stackexchange.com",
  "dev.to",
  "medium.com",
  "hashnode.dev",
  "binhex.ninja",
  // Documentation & learning
  "wikipedia.org",
  "mozilla.org",
  "w3.org",
  "npmjs.com",
  "pypi.org",
  "docs.microsoft.com",
  "developer.apple.com",
  "developer.android.com",
  // Cloud & CDN
  "cloudflare.com",
  "aws.amazon.com",
  "azure.microsoft.com",
  "cloud.google.com",
  "heroku.com",
  "netlify.com",
  "vercel.app",
  // Major companies
  "microsoft.com",
  "apple.com",
  "amazon.com",
  "netflix.com",
  "spotify.com",
  "adobe.com",
  "salesforce.com",
  "oracle.com",
  // News & media
  "nytimes.com",
  "bbc.com",
  "cnn.com",
  "theguardian.com",
  "reuters.com",
  "bloomberg.com",
  "wsj.com",
  "forbes.com",
  "techcrunch.com",
  // Education
  "coursera.org",
  "udemy.com",
  "edx.org",
  "khanacademy.org",
  "mit.edu",
  "stanford.edu",
  "harvard.edu",
  // Others
  "wordpress.com",
  "blogger.com",
  "squarespace.com",
  "wix.com",
  "shopify.com",
  "etsy.com",
  "ebay.com",
  "paypal.com",
  "stripe.com",
];

/**
 * Check if extension context is valid
 */
function isExtensionContextValid() {
  try {
    return chrome.runtime && chrome.runtime.id;
  } catch (e) {
    return false;
  }
}

/**
 * Show reload prompt if extension context is invalidated
 */
function showReloadPromptIfNeeded() {
  if (!isExtensionContextValid()) {
    const banner = document.createElement('div');
    banner.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      background: #ff4444;
      color: white;
      padding: 15px;
      text-align: center;
      z-index: 10000;
      font-weight: bold;
    `;
    banner.innerHTML = '⚠️ Extension was reloaded. Please <a href="#" onclick="location.reload()" style="color: white; text-decoration: underline;">refresh this page</a>.';
    document.body.insertBefore(banner, document.body.firstChild);
    return true;
  }
  return false;
}

// Load settings on page load
document.addEventListener("DOMContentLoaded", async () => {
  // Check if extension context is valid
  if (showReloadPromptIfNeeded()) {
    return;
  }
  
  await loadSettings();
  await checkEncryptionStatus();
  setupEventListeners();
  
  // Setup theme toggle
  const themeToggle = document.getElementById('theme-toggle-btn');
  if (themeToggle && typeof ThemeManager !== 'undefined') {
    ThemeManager.setupToggle(themeToggle);
  }
});

/**
 * Load settings from storage
 */
async function loadSettings() {
  try {
    // Check if extension context is valid
    if (!isExtensionContextValid()) {
      console.error("[Settings] ❌ Extension context invalidated");
      showReloadPromptIfNeeded();
      return;
    }
    
    const settings = await chrome.storage.local.get([
      "dataCollectionMode",
      "dataCollection", // Legacy
      "userWhitelist",
      "customWhitelist",
      "domainToggles",
      "trustedUrls",
    ]);

    // Handle legacy dataCollection field
    const dataMode =
      settings.dataCollectionMode || settings.dataCollection || "none";

    // Set data collection radio
    const radio = document.querySelector(
      `input[name="dataCollection"][value="${dataMode}"]`,
    );
    if (radio) {
      radio.checked = true;
    }

    // Load domain toggles (which domains are enabled)
    const domainToggles = settings.domainToggles || {};

    // Render built-in domains
    renderBuiltInDomains(domainToggles);

    // Load custom whitelist
    const customWhitelist =
      settings.customWhitelist || settings.userWhitelist || [];
    renderCustomWhitelist(customWhitelist, domainToggles);

    // Load trusted URLs
    const trustedUrls = settings.trustedUrls || [];
    // Only clean up the extension's own UI pages (about:blank, about:srcdoc)
    const cleanedTrustedUrls = trustedUrls.filter(url => 
      url !== 'about:blank' && url !== 'about:srcdoc' && !url.startsWith('about:srcdoc')
    );
    if (cleanedTrustedUrls.length !== trustedUrls.length) {
      // Save cleaned list back
      await chrome.storage.local.set({ trustedUrls: cleanedTrustedUrls });
      console.log("[Settings] Cleaned up extension UI URLs from trusted list");
    }
    renderTrustedUrls(cleanedTrustedUrls);

    console.log("[Settings] Loaded settings:", settings);
  } catch (error) {
    console.error("[Settings] Failed to load settings:", error);
    
    // Check if it's an extension context error
    if (!isExtensionContextValid() || 
        error.message?.includes("Extension context invalidated")) {
      showReloadPromptIfNeeded();
      return;
    }
    
    showToast("Failed to load settings", "error");
  }
}

/**
 * Check encryption status
 */
async function checkEncryptionStatus() {
  try {
    // Check if extension context is valid
    if (!isExtensionContextValid()) {
      console.error("[Settings] ❌ Extension context invalidated");
      return;
    }
    
    const settings = await chrome.storage.local.get([
      "serverConnected",
      "connectionStatus",
      "dataCollectionMode",
    ]);

    const dataMode = settings.dataCollectionMode || "none";

    // Check if there's a status indicator element
    const statusDot = document.getElementById("encryptionStatus");
    const statusText = document.getElementById("encryptionText");

    if (!statusDot || !statusText) {
      return; // Elements don't exist in this version
    }

    if (dataMode === "none") {
      statusDot.classList.add("inactive");
      statusText.textContent = "Data collection disabled";
    } else if (settings.serverConnected) {
      statusDot.classList.remove("inactive");
      statusText.textContent = "Server: Connected (Encrypted)";
    } else {
      statusDot.classList.add("inactive");
      const status = settings.connectionStatus || "unknown";
      statusText.textContent = `Server: ${status}`;
    }
  } catch (error) {
    console.error("[Settings] Failed to check encryption status:", error);
    
    // Check if it's an extension context error
    if (!isExtensionContextValid() || 
        error.message?.includes("Extension context invalidated")) {
      console.log("[Settings] ❌ Failed to check trusted URLs: Extension context invalidated");
      return;
    }
  }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
  // Save button
  document.getElementById("saveBtn").addEventListener("click", saveSettings);

  // Add whitelist button
  document
    .getElementById("addWhitelistBtn")
    .addEventListener("click", addToWhitelist);

  // Enter key in whitelist input
  document
    .getElementById("whitelistInput")
    .addEventListener("keypress", (e) => {
      if (e.key === "Enter") {
        addToWhitelist();
      }
    });
}

/**
 * Render built-in trusted domains
 */
function renderBuiltInDomains(domainToggles) {
  const container = document.getElementById("builtInWhitelistList");

  container.innerHTML = BUILTIN_DOMAINS.map((domain) => {
    const isEnabled = domainToggles[domain] !== false; // Default to enabled

    return `
            <div class="whitelist-item ${!isEnabled ? "disabled" : ""}">
                <div class="whitelist-left">
                    <span class="whitelist-domain">${escapeHtml(domain)}</span>
                </div>
                <div class="whitelist-right">
                    <div class="toggle-switch ${isEnabled ? "active" : ""}" data-domain="${escapeHtml(domain)}" data-type="builtin">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
            </div>
        `;
  }).join("");

  // Add event listeners to toggles
  container.querySelectorAll(".toggle-switch").forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const domain = toggle.getAttribute("data-domain");
      toggleDomain(domain, "builtin", toggle);
    });
  });
}

/**
 * Render custom whitelist
 */
function renderCustomWhitelist(customWhitelist, domainToggles) {
  const container = document.getElementById("customWhitelistList");

  if (!customWhitelist || customWhitelist.length === 0) {
    container.innerHTML = `
            <div class="empty-state">
                No custom domains added yet. Add domains you want to exempt from scanning.
            </div>
        `;
    return;
  }

  container.innerHTML = customWhitelist
    .map((domain) => {
      const isEnabled = domainToggles[domain] !== false; // Default to enabled

      return `
            <div class="whitelist-item ${!isEnabled ? "disabled" : ""}">
                <div class="whitelist-left">
                    <span class="whitelist-domain">${escapeHtml(domain)}</span>
                </div>
                <div class="whitelist-right">
                    <div class="toggle-switch ${isEnabled ? "active" : ""}" data-domain="${escapeHtml(domain)}" data-type="custom">
                        <div class="toggle-slider"></div>
                    </div>
                    <button class="remove-btn" data-domain="${escapeHtml(domain)}">Remove</button>
                </div>
            </div>
        `;
    })
    .join("");

  // Add event listeners to toggles
  container.querySelectorAll(".toggle-switch").forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const domain = toggle.getAttribute("data-domain");
      toggleDomain(domain, "custom", toggle);
    });
  });

  // Add event listeners to remove buttons
  container.querySelectorAll(".remove-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const domain = btn.getAttribute("data-domain");
      removeFromWhitelist(domain);
    });
  });
}

/**
 * Render trusted URLs/Files list
 */
function renderTrustedUrls(trustedUrls) {
  const container = document.getElementById("trustedUrlsList");

  if (!container) {
    return; // Container doesn't exist yet
  }

  if (!trustedUrls || trustedUrls.length === 0) {
    container.innerHTML = `
            <div class="empty-state">
                No trusted URLs or files yet.
            </div>
        `;
    return;
  }

  container.innerHTML = trustedUrls
    .map((url) => {
      const displayUrl = url.length > 80 ? url.substring(0, 77) + '...' : url;
      const isFile = url.startsWith('file://');
      const label = isFile ? 'File' : 'URL';

      return `
            <div class="whitelist-item">
                <div class="whitelist-left">
                    <span class="whitelist-domain" title="${escapeHtml(url)}">${escapeHtml(displayUrl)}</span>
                </div>
                <div class="whitelist-right">
                    <span class="whitelist-source">${label}</span>
                    <button class="remove-btn" data-trusted-url="${escapeHtml(url)}">Remove</button>
                </div>
            </div>
        `;
    })
    .join("");

  // Add event listeners for remove buttons
  container.querySelectorAll(".remove-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const url = btn.getAttribute("data-trusted-url");
      removeFromTrustedUrls(url);
    });
  });
}

/**
 * Remove URL from trusted URLs list
 */
async function removeFromTrustedUrls(url) {
  const settings = await chrome.storage.local.get(["trustedUrls"]);
  let trustedUrls = settings.trustedUrls || [];

  trustedUrls = trustedUrls.filter((u) => u !== url);

  await chrome.storage.local.set({ trustedUrls });

  renderTrustedUrls(trustedUrls);

  showToast(`Removed from trusted URLs`, "success");

  console.log("[Settings] Removed from trusted URLs:", url);
}

/**
 * Toggle domain on/off
 */
async function toggleDomain(domain, type, toggleElement) {
  try {
    const settings = await chrome.storage.local.get(["domainToggles"]);
    const domainToggles = settings.domainToggles || {};

    // Toggle the state
    const currentState = domainToggles[domain] !== false;
    domainToggles[domain] = !currentState;

    // Save to storage
    await chrome.storage.local.set({ domainToggles });

    // Update UI
    const item = toggleElement.closest(".whitelist-item");
    if (domainToggles[domain]) {
      toggleElement.classList.add("active");
      item.classList.remove("disabled");
    } else {
      toggleElement.classList.remove("active");
      item.classList.add("disabled");
    }

    const status = domainToggles[domain] ? "enabled" : "disabled";
    showToast(`${domain}: ${status}`, "success");

    console.log("[Settings] Toggled domain:", domain, domainToggles[domain]);
  } catch (error) {
    console.error("[Settings] Failed to toggle domain:", error);
    showToast("Failed to toggle domain", "error");
  }
}

/**
 * Save all settings
 */
async function saveSettings() {
  try {
    const saveBtn = document.getElementById("saveBtn");
    saveBtn.disabled = true;
    saveBtn.textContent = "💾 Saving...";

    // Get current settings to compare
    const currentSettings = await chrome.storage.local.get(["dataCollectionMode"]);
    const oldMode = currentSettings.dataCollectionMode || "none";

    // Get new data collection mode
    const dataCollectionRadio = document.querySelector(
      'input[name="dataCollection"]:checked',
    );
    const newMode = dataCollectionRadio ? dataCollectionRadio.value : "none";

    console.log("[Settings] Mode change:", oldMode, "→", newMode);

    // Save to storage
    await chrome.storage.local.set({
      dataCollectionMode: newMode,
    });

    // If mode changed to anonymous or full, initialize crypto
    if (newMode !== "none" && oldMode !== newMode) {
      console.log("[Settings] Initializing crypto for new mode:", newMode);
      saveBtn.textContent = "🔄 Connecting to server...";
      
      try {
        // Check if extension context is valid
        if (!chrome.runtime?.id) {
          throw new Error("Extension context invalidated - please reload this page");
        }
        
        const response = await chrome.runtime.sendMessage({ 
          type: 'INITIALIZE_CRYPTO' 
        });
        
        if (response && response.success) {
          console.log("[Settings] ✅ Crypto initialized successfully");
          showToast("Settings saved and server connected!", "success");
        } else {
          console.warn("[Settings] ⚠️ Crypto initialization failed");
          showToast("Settings saved, but server connection failed", "warning");
        }
      } catch (error) {
        console.error("[Settings] Crypto initialization error:", error);
        
        // Check if it's an extension context error
        if (error.message?.includes("Extension context invalidated") || 
            error.message?.includes("message port closed") ||
            !chrome.runtime?.id) {
          showToast("⚠️ Extension reloaded - please refresh this page", "error");
          saveBtn.textContent = "🔄 Refresh Page";
          saveBtn.disabled = false;
          saveBtn.onclick = () => window.location.reload();
          return;
        }
        
        showToast("Settings saved, but server connection failed", "warning");
      }
    } else if (newMode === "none" && oldMode !== "none") {
      // Mode changed to disabled - clear connection status
      await chrome.storage.local.set({
        serverConnected: false,
        connectionStatus: "disabled",
        cryptoInitialized: false
      });
      showToast("Settings saved. Data collection disabled.", "success");
    } else {
      // No mode change or already initialized
      showToast("Settings saved successfully!", "success");
    }

    console.log("[Settings] Saved settings:", { newMode });

    saveBtn.disabled = false;
    saveBtn.textContent = "💾 Save All Settings";
    
    // Reload settings to update connection status display
    await loadSettings();
  } catch (error) {
    console.error("[Settings] Failed to save settings:", error);
    showToast("Failed to save settings", "error");

    const saveBtn = document.getElementById("saveBtn");
    saveBtn.disabled = false;
    saveBtn.textContent = "💾 Save All Settings";
  }
}

/**
 * Add domain to custom whitelist
 */
async function addToWhitelist() {
  const input = document.getElementById("whitelistInput");
  let domain = input.value.trim().toLowerCase();

  if (!domain) {
    showToast("Please enter a domain", "error");
    return;
  }

  // Remove protocol if present
  domain = domain.replace(/^(https?:\/\/)?(www\.)?/, "");

  // Remove trailing slash
  domain = domain.replace(/\/$/, "");

  // Validate domain format
  if (!isValidDomain(domain)) {
    showToast("Invalid domain format", "error");
    return;
  }

  // Check if it's a built-in domain
  if (BUILTIN_DOMAINS.includes(domain)) {
    showToast("This is already a built-in trusted domain", "error");
    return;
  }

  // Get current custom whitelist
  const settings = await chrome.storage.local.get([
    "customWhitelist",
    "domainToggles",
  ]);
  const customWhitelist = settings.customWhitelist || [];
  const domainToggles = settings.domainToggles || {};

  // Check if already exists
  if (customWhitelist.includes(domain)) {
    showToast("Domain already in whitelist", "error");
    return;
  }

  // Add to whitelist
  customWhitelist.push(domain);

  // Enable by default
  domainToggles[domain] = true;

  // Save and update UI
  await chrome.storage.local.set({
    customWhitelist,
    domainToggles,
  });

  renderCustomWhitelist(customWhitelist, domainToggles);

  // Clear input
  input.value = "";

  showToast(`Added ${domain} to whitelist`, "success");

  console.log("[Settings] Added to whitelist:", domain);
}

/**
 * Remove domain from custom whitelist
 */
async function removeFromWhitelist(domain) {
  const settings = await chrome.storage.local.get([
    "customWhitelist",
    "domainToggles",
  ]);
  let customWhitelist = settings.customWhitelist || [];
  let domainToggles = settings.domainToggles || {};

  customWhitelist = customWhitelist.filter((d) => d !== domain);
  delete domainToggles[domain];

  await chrome.storage.local.set({
    customWhitelist,
    domainToggles,
  });

  renderCustomWhitelist(customWhitelist, domainToggles);

  showToast(`Removed ${domain} from whitelist`, "success");

  console.log("[Settings] Removed from whitelist:", domain);
}

/**
 * Validate domain format
 */
function isValidDomain(domain) {
  // Basic domain validation
  const domainRegex = /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/;
  return domainRegex.test(domain);
}

/**
 * Show toast notification
 */
function showToast(message, type = "success") {
  const toast = document.getElementById("toast");
  const toastMessage = document.getElementById("toastMessage");
  const toastIcon = toast.querySelector(".toast-icon");

  toastMessage.textContent = message;

  if (type === "success") {
    toastIcon.textContent = "✅";
    toast.style.borderColor = "#4ade80";
  } else {
    toastIcon.textContent = "❌";
    toast.style.borderColor = "#ff4444";
  }

  toast.classList.add("show");

  setTimeout(() => {
    toast.classList.remove("show");
  }, 3000);
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}
