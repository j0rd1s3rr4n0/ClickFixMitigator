// BinHex.Ninja Security - Background Service Worker
// Handles key exchange, encrypted logging, and security events

// Load required scripts
self.importScripts('config.js', 'crypto.js');

console.log(
  "BinHex.Ninja Security - Background service worker started - v1.0.0",
);

// Initialize crypto on startup if data collection is enabled
(async () => {
  try {
    const settings = await chrome.storage.local.get(["dataCollectionMode"]);
    const dataMode = settings.dataCollectionMode || "none";
    
    if (dataMode !== "none") {
      console.log("[Background] Data collection enabled on startup, initializing crypto...");
      await initializeCrypto(dataMode);
    } else {
      console.log("[Background] Data collection disabled, crypto not initialized");
    }
  } catch (error) {
    console.log("[Background] Error during startup initialization:", error);
  }
})();

// Track potentially malicious sites
const suspiciousSites = new Set();

// Crypto instance (initialized after key exchange)
let crypto = null;
let cryptoReady = false;

/**
 * Initialize cryptography and perform key exchange
 * Only called when data collection is enabled
 */
async function initializeCrypto() {
  try {
    console.log("[Background] Initializing crypto...");

    // Check data collection mode first
    const settings = await chrome.storage.local.get(["dataCollectionMode"]);
    const dataMode = settings.dataCollectionMode || "none";

    if (dataMode === "none") {
      console.log(
        "[Background] Data collection disabled, skipping crypto initialization",
      );
      cryptoReady = false;

      // Update connection status
      await chrome.storage.local.set({
        serverConnected: false,
        connectionStatus: "disabled",
      });

      return false;
    }

    // Always perform key exchange on startup (CryptoKeys cannot be persisted)
    // The server will update the existing session if one exists
    console.log("[Background] Performing key exchange with server...");
    crypto = new BinHexCrypto();

    // Update status to connecting
    await chrome.storage.local.set({
      serverConnected: false,
      connectionStatus: "connecting",
    });

    const result = await crypto.performKeyExchange(
      CONFIG.API_KEY,
      CONFIG.SERVER_URL,
      dataMode,
    );

    if (result.success) {
      // Store session info with data mode
      await chrome.storage.local.set({
        clientKeyHash: result.clientKeyHash,
        keyExpiresAt: result.expiresAt,
        sessionDataMode: result.dataMode,
        cryptoInitialized: true,
        serverConnected: true,
        connectionStatus: "connected",
      });

      cryptoReady = true;
      console.log("[Background] ✅ Crypto initialized successfully");
      return true;
    } else {
      console.log("[Background] ❌ Key exchange failed:", result.error);
      cryptoReady = false;

      // Update connection status
      await chrome.storage.local.set({
        serverConnected: false,
        connectionStatus: "error",
        connectionError: result.error,
      });

      return false;
    }
  } catch (error) {
    console.log("[Background] ❌ Crypto initialization error:", error);
    cryptoReady = false;

    // Update connection status
    await chrome.storage.local.set({
      serverConnected: false,
      connectionStatus: "error",
      connectionError: error.message,
    });

    return false;
  }
}

// Listen for messages from content scripts
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  console.log(
    "[Background] 📨 Message received:",
    JSON.stringify({ type: message.type, hasPayload: !!message.payload }),
  );

  // Handle theme change - update extension icon
  if (message.type === "THEME_CHANGED") {
    console.log("[Background] ✅ Matched THEME_CHANGED handler");
    updateExtensionIcon().then(() => {
      sendResponse({ success: true });
    }).catch((error) => {
      console.error("[Background] Failed to update icon:", error);
      sendResponse({ success: false });
    });
    return true; // Keep channel open for async response
  }

  // Handle crypto initialization request from onboarding
  if (message.type === "INITIALIZE_CRYPTO") {
    console.log("[Background] ✅ Matched INITIALIZE_CRYPTO handler");
    initializeCrypto()
      .then((result) => {
        sendResponse({ success: result });
      })
      .catch((error) => {
        console.log("[Background] Crypto initialization failed:", error);
        sendResponse({ success: false, error: error.message });
      });
    return true; // Keep channel open for async response
  }

  // Handle logging requests with encryption
  if (message.type === "SEND_LOG") {
    console.log("[Background] ✅ Matched SEND_LOG handler");

    handleEncryptedLog(message.payload, sendResponse);
    return true; // Keep channel open for async response
  }

  if (message.type === "malware_detected") {
    console.log("[Background] ✅ Matched malware_detected handler");
    const url = message.url || sender.tab?.url || "unknown";
    
    // NEVER track or log the extension's own UI pages
    const EXTENSION_UI_URLS = ['about:blank', 'about:srcdoc'];
    const isExtensionUI = EXTENSION_UI_URLS.some(u => url === u || url.startsWith(u));
    
    if (isExtensionUI) {
      console.log("[Background] ⚠️ Ignoring detection from extension UI:", url);
      sendResponse({ received: true, ignored: true, reason: 'extension_ui' });
      return true;
    }
    
    suspiciousSites.add(url);

    console.warn("[BinHex.Ninja Security] ClickFix attack detected on:", url);
    console.warn("[BinHex.Ninja Security] Detection reasons:", message.reasons);

    // Determine detection type based on reasons
    const detectionType = (message.reasons || []).some(r => 
      r.toLowerCase().includes('clipboard')
    ) ? 'clipboard' : 'page';

    // Track the detection in statistics
    trackDetection(url, message.reasons || [], detectionType, message.detectedCommand).catch(err => {
      console.log("[Background] Failed to track detection:", err);
    });

    // Send detection log to server with detected command and match details if available
    sendDetectionLogToServer(url, message.reasons || [], detectionType, message.detectedCommand, message.matchDetails).catch(err => {
      console.log("[Background] Failed to send detection log to server:", err);
    });

    if (sender.tab?.id) {
      chrome.action.setBadgeText({
        text: "⚠️",
        tabId: sender.tab.id,
      });
      chrome.action.setBadgeBackgroundColor({
        color: "#ff0000",
        tabId: sender.tab.id,
      });
    }

    sendResponse({ received: true });
    return true;
  }

  // Handle iframe detection - forward to top frame
  if (message.type === "show_warning_in_top_frame") {
    console.log("[Background] ✅ Matched show_warning_in_top_frame handler");
    console.log("[Background] Iframe URL:", message.iframeUrl);
    console.log("[Background] Detection reasons:", message.reasons);

    // Get the tab ID from the sender
    const tabId = sender.tab?.id;
    if (tabId) {
      // Send message to all frames in the tab (top frame will handle it)
      chrome.tabs.sendMessage(
        tabId,
        {
          type: "display_warning",
          iframeUrl: message.iframeUrl,
          reasons: message.reasons,
          triggerType: message.triggerType || "iframe-detection"
        },
        { frameId: 0 } // frameId: 0 targets the top frame specifically
      ).then(response => {
        console.log("[Background] Top frame response:", response);
        sendResponse({ success: true, forwarded: true });
      }).catch(err => {
        console.log("[Background] Failed to forward to top frame:", err);
        sendResponse({ success: false, error: err.message });
      });
    } else {
      console.log("[Background] No tab ID available to forward warning");
      sendResponse({ success: false, error: "No tab ID" });
    }

    return true; // Keep channel open for async response
  }

  // Handle clipboard alert from iframe - forward to top frame
  if (message.type === "show_clipboard_alert_in_top_frame") {
    console.log("[Background] ✅ Matched show_clipboard_alert_in_top_frame handler");
    console.log("[Background] Iframe URL:", message.iframeUrl);
    console.log("[Background] Alert message:", message.message?.substring(0, 100));

    // Get the tab ID from the sender
    const tabId = sender.tab?.id;
    if (tabId) {
      // Send message to top frame to display the clipboard alert
      chrome.tabs.sendMessage(
        tabId,
        {
          type: "display_clipboard_alert",
          message: message.message,
          iframeUrl: message.iframeUrl
        },
        { frameId: 0 } // frameId: 0 targets the top frame specifically
      ).then(response => {
        console.log("[Background] Top frame clipboard alert response:", response);
        sendResponse({ success: true, forwarded: true });
      }).catch(err => {
        console.log("[Background] Failed to forward clipboard alert to top frame:", err);
        sendResponse({ success: false, error: err.message });
      });
    } else {
      console.log("[Background] No tab ID available to forward clipboard alert");
      sendResponse({ success: false, error: "No tab ID" });
    }

    return true; // Keep channel open for async response
  }

  // Unknown message type
  console.log("[Background] ⚠️ Unknown message type");
  sendResponse({ received: false });
  return false;
});

/**
 * Track detection statistics and recent detections
 */
async function trackDetection(url, detectionReasons, detectionType = "page", detectedCommand = null) {
  try {
    // NEVER track the extension's own UI
    const EXTENSION_UI_URLS = ['about:blank', 'about:srcdoc'];
    const isExtensionUI = EXTENSION_UI_URLS.some(u => url === u || url.startsWith(u));
    
    if (isExtensionUI) {
      console.log("[Background] ⚠️ Skipping tracking for extension UI:", url);
      return;
    }
    
    // Update statistics
    const storage = await chrome.storage.local.get([
      "detectionStats",
      "recentDetections",
    ]);

    const stats = storage.detectionStats || {
      totalBlocked: 0,
      sessionBlocked: 0,
      lastReset: Date.now(),
    };

    stats.totalBlocked += 1;
    stats.sessionBlocked += 1;

    // Determine detection type from reasons if not explicitly provided
    const isClipboard = detectionReasons.some(r => 
      r.includes("clipboard") || r.includes("Clipboard")
    );
    const type = isClipboard ? "clipboard" : detectionType;

    // Store recent detections (keep last 20)
    const recent = storage.recentDetections || [];
    const detection = {
      url: url,
      timestamp: Date.now(),
      reasons: detectionReasons,
      type: type, // "page" or "clipboard"
    };
    
    // Add detected command/content for clipboard detections
    if (detectedCommand && type === "clipboard") {
      detection.detectedCommand = detectedCommand.substring(0, 200); // Truncate to 200 chars for storage
    }
    
    recent.push(detection);

    // Keep only last 20 detections
    const trimmed = recent.slice(-20);

    await chrome.storage.local.set({
      detectionStats: stats,
      recentDetections: trimmed,
    });

    // Notify popup if it's open
    chrome.runtime
      .sendMessage({
        type: "detection_update",
        stats: stats,
        recent: trimmed,
      })
      .catch(() => {
        // Popup not open, ignore error
      });

    console.log("[Background] Detection tracked:", url);
  } catch (error) {
    console.log("[Background] Failed to track detection:", error);
  }
}

/**
 * Send detection log to server (called from malware_detected handler)
 */
async function sendDetectionLogToServer(url, reasons, detectionType = 'page', detectedCommand = null, matchDetails = null) {
  try {
    // NEVER send the extension's own UI to server
    const EXTENSION_UI_URLS = ['about:blank', 'about:srcdoc'];
    const isExtensionUI = EXTENSION_UI_URLS.some(u => url === u || url.startsWith(u));
    
    if (isExtensionUI) {
      console.log("[Background] ⚠️ Skipping server log for extension UI:", url);
      return;
    }
    
    // Get user preferences
    const settings = await chrome.storage.local.get([
      "dataCollectionMode",
      "clientKeyHash",
    ]);

    const dataCollectionMode = settings.dataCollectionMode || "none";

    // Check if logging is enabled
    if (dataCollectionMode === "none") {
      console.log("[Background] Data collection disabled, skipping server log");
      return;
    }

    // Ensure crypto is initialized
    if (!cryptoReady) {
      console.log("[Background] Crypto not ready, skipping server log");
      return;
    }

    if (!settings.clientKeyHash) {
      console.log("[Background] No client key hash found, skipping server log");
      return;
    }

    // Prepare log payload based on data collection mode
    let payload = {
      timestamp: new Date().toISOString(),
      detectionType: detectionType,
      dataCollectionMode: dataCollectionMode
    };

    // Extract OS from user agent
    const userAgent = navigator.userAgent;
    let os = "Unknown";
    if (userAgent.includes("Windows")) os = "Windows";
    else if (userAgent.includes("Mac OS X")) os = "macOS";
    else if (userAgent.includes("Linux")) os = "Linux";
    else if (userAgent.includes("Android")) os = "Android";
    else if (userAgent.includes("iOS")) os = "iOS";

    if (dataCollectionMode === "anonymous") {
      // Anonymous mode: OS, Detected Command, Detection Reason, and URL
      // NO IP address, NO location, NO user identification
      payload.operatingSystem = os;
      payload.url = url;
      payload.detectionReasons = reasons;
      
      // Add detected command if available (sanitize by truncating)
      if (detectedCommand && detectionType === "clipboard") {
        payload.detectedCommand = detectedCommand.substring(0, 1000); // Max 1000 chars
      }
      
      // Add detailed match information if available
      if (matchDetails) {
        payload.matchDetails = matchDetails;
      }
    } else if (dataCollectionMode === "full") {
      // Full mode: Anonymous data + IP address + geolocation
      payload.operatingSystem = os;
      payload.url = url;
      payload.detectionReasons = reasons;
      payload.userAgent = userAgent;
      
      // Add detected command if available (sanitize by truncating)
      if (detectedCommand && detectionType === "clipboard") {
        payload.detectedCommand = detectedCommand.substring(0, 1000); // Max 1000 chars
      }
      
      // Add detailed match information if available
      if (matchDetails) {
        payload.matchDetails = matchDetails;
      }
      
      // Note: IP address and geolocation will be added server-side
      // as the client doesn't have direct access to its public IP
      payload.requestIPCollection = true;
      payload.requestGeolocation = true;
    }

    console.log("[Background] Sending detection log to server:", {
      detectionType,
      mode: dataCollectionMode,
      includesUrl: dataCollectionMode === "full"
    });

    // Encrypt the payload
    const encryptedData = await crypto.encrypt(JSON.stringify(payload));

    if (!encryptedData) {
      console.log("[Background] Failed to encrypt detection log");
      return;
    }

    // Send to server
    const response = await fetch(CONFIG.SERVER_URL + "/log", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-API-Key": CONFIG.API_KEY,
        "X-Client-Key-Hash": settings.clientKeyHash,
      },
      body: encryptedData,
    });

    if (response.ok) {
      const data = await response.json();
      console.log("[Background] ✅ Detection log sent to server successfully, logId:", data.logId);
    } else {
      const errorText = await response.text();
      console.log("[Background] Failed to send detection log:", response.status, errorText);

      // If session expired, try to reinitialize and retry once
      if (response.status === 401) {
        console.log("[Background] Session expired, reinitializing crypto and retrying...");
        await initializeCrypto(dataCollectionMode);
        
        // Retry sending the log once after reinitialization
        try {
          console.log("[Background] Retrying log send after reinitialization...");
          const retrySettings = await chrome.storage.local.get(['clientKeyHash', 'cryptoInitialized']);
          if (retrySettings.cryptoInitialized && retrySettings.clientKeyHash) {
            // Re-encrypt with new session
            const retryEncryptedData = await crypto.encrypt(JSON.stringify(payload));
            if (!retryEncryptedData) {
              console.log("[Background] Failed to encrypt log on retry");
              return;
            }
            
            const retryResponse = await fetch(CONFIG.SERVER_URL + "/log", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
                "X-API-Key": CONFIG.API_KEY,
                "X-Client-Key-Hash": retrySettings.clientKeyHash,
              },
              body: retryEncryptedData,
            });
            
            if (retryResponse.ok) {
              const retryData = await retryResponse.json();
              console.log("[Background] ✅ Detection log sent successfully on retry, logId:", retryData.logId);
            } else {
              const retryErrorText = await retryResponse.text();
              console.log("[Background] Retry failed:", retryResponse.status, retryErrorText);
            }
          } else {
            console.log("[Background] Crypto not initialized after retry");
          }
        } catch (retryError) {
          console.log("[Background] Retry error:", retryError);
        }
      }
    }
  } catch (error) {
    console.log("[Background] Error sending detection log to server:", error);
  }
}

/**
 * Handle encrypted log submission
 */
async function handleEncryptedLog(payload, sendResponse) {
  try {
    // Determine detection type from reasons
    const detectionType = (payload.detectionReasons || []).some(r => 
      r.toLowerCase().includes('clipboard')
    ) ? 'clipboard' : 'page';
    
    // Track this detection with detected command if available
    await trackDetection(payload.url, payload.detectionReasons || [], detectionType, payload.detectedCommand);

    // Get user preferences
    const settings = await chrome.storage.local.get([
      "dataCollectionMode",
      "loggingEndpoint",
      "clientKeyHash",
    ]);

    const dataCollectionMode =
      settings.dataCollectionMode || CONFIG.DEFAULTS.dataCollection;
    const loggingEndpoint =
      settings.loggingEndpoint || CONFIG.DEFAULTS.loggingEndpoint;
    const clientKeyHash = settings.clientKeyHash;

    // Check if logging is enabled
    if (dataCollectionMode === "none") {
      console.log("[Background] Data collection disabled, skipping log");
      sendResponse({ success: true, skipped: true, reason: "disabled" });
      return;
    }

    // Ensure crypto is initialized
    if (!cryptoReady) {
      console.log("[Background] Crypto not ready, initializing...");
      const initialized = await initializeCrypto();
      if (!initialized) {
        console.log("[Background] Failed to initialize crypto");
        sendResponse({ success: false, error: "crypto_init_failed" });
        return;
      }
    }

    if (!clientKeyHash) {
      console.log("[Background] No client key hash found");
      sendResponse({ success: false, error: "no_session" });
      return;
    }

    // Prepare payload based on data collection mode
    if (dataCollectionMode === "anonymous") {
      // Remove IP and location for anonymous mode
      delete payload.ip;
      delete payload.location;
    }

    // Encrypt the payload
    const encryptedData = await crypto.encrypt(JSON.stringify(payload));

    if (!encryptedData) {
      console.log("[Background] Encryption failed");
      sendResponse({ success: false, error: "encryption_failed" });
      return;
    }

    // Send to server
    const response = await fetch(loggingEndpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-API-Key": CONFIG.API_KEY,
        "X-Client-Key-Hash": clientKeyHash,
      },
      body: encryptedData,
    });

    console.log("[Background] Log response status:", response.status);

    if (response.ok) {
      const data = await response.json();
      console.log("[Background] ✅ Log sent successfully");
      sendResponse({ success: true, logId: data.logId });
    } else {
      const errorText = await response.text();
      console.log("[Background] Log error:", response.status, errorText);

      // If session expired, try to reinitialize
      if (response.status === 401) {
        console.log("[Background] Session expired, reinitializing crypto...");
        await initializeCrypto();
      }

      sendResponse({ success: false, error: `HTTP ${response.status}` });
    }
  } catch (error) {
    console.log("[Background] ❌ Error sending log:", error);
    sendResponse({ success: false, error: error.message });
  }
}

// Clear badge when tab is updated
chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.status === "complete" && tab.url) {
    chrome.action.setBadgeText({
      text: "",
      tabId: tabId,
    });
  }
});

// Handle installation
// Function to update extension icon based on theme
async function updateExtensionIcon() {
  try {
    const { theme } = await chrome.storage.local.get(['theme']);
    let effectiveTheme = theme || 'auto';
    
    // Resolve 'auto' to actual theme
    if (effectiveTheme === 'auto') {
      // On Chrome/extension context, we can't access window.matchMedia
      // Default to dark for toolbar icon (works well in most toolbars)
      effectiveTheme = 'dark';
    }
    
    // Use dark icons for light theme, light icons for dark theme
    // This ensures good contrast with the browser toolbar
    const iconPrefix = effectiveTheme === 'dark' ? 'light' : 'dark';
    
    await chrome.action.setIcon({
      path: {
        "16": `icons/${iconPrefix}16.png`,
        "48": `icons/${iconPrefix}48.png`,
        "128": `icons/${iconPrefix}128.png`
      }
    });
    
    console.log(`[Background] Updated extension icon to ${iconPrefix} for ${effectiveTheme} theme`);
  } catch (error) {
    console.error('[Background] Failed to update extension icon:', error);
  }
}

// Update icon on browser startup
chrome.runtime.onStartup.addListener(async () => {
  console.log('[Background] Browser started, updating extension icon');
  await updateExtensionIcon();
});

chrome.runtime.onInstalled.addListener(async (details) => {
  if (details.reason === "install") {
    console.log("BinHex.Ninja Security installed successfully");

    // Set default preferences
    await chrome.storage.local.set({
      dataCollectionMode: CONFIG.DEFAULTS.dataCollection,
      loggingEndpoint: CONFIG.DEFAULTS.loggingEndpoint,
      userWhitelist: CONFIG.DEFAULTS.userWhitelist,
      customWhitelist: [],
      domainToggles: {}, // Track which domains are enabled
      trustedDomainsEnabled: {}, // Legacy - kept for compatibility
      cryptoInitialized: false,
      serverConnected: false,
      connectionStatus: "disabled",
      onboardingCompleted: false,
    });

    // Don't perform key exchange on install - only when user enables data collection

    // Open onboarding page for first-time users
    chrome.tabs.create({
      url: chrome.runtime.getURL("onboarding.html"),
    });
    
    // Set initial extension icon
    await updateExtensionIcon();
  } else if (details.reason === "update") {
    console.log(
      "BinHex.Ninja Security updated to version:",
      chrome.runtime.getManifest().version,
    );
    
    // Update extension icon on update
    await updateExtensionIcon();

    // Migrate old settings if needed
    const settings = await chrome.storage.local.get([
      "dataCollection",
      "dataCollectionMode",
      "onboardingCompleted",
    ]);
    
    if (settings.dataCollection && !settings.dataCollectionMode) {
      await chrome.storage.local.set({
        dataCollectionMode: settings.dataCollection,
      });
    }

    // Mark onboarding as completed for existing users
    if (settings.onboardingCompleted === undefined) {
      await chrome.storage.local.set({
        onboardingCompleted: true,
        onboardingDate: new Date().toISOString(),
      });
    }
  }
});

// Data collection mode is read on startup - no need for runtime updates

