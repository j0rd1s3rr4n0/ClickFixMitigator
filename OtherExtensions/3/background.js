function sendSettings(request, sender, sendResponse) {
  if (request.operation == "getVars") {

    // get all settings from storage, ready to convert and inject into the page
    chrome.storage.local.get(null, function (result) {
      const message = result.blockall ?
        chrome.i18n.getMessage("blockallMessage") :
        chrome.i18n.getMessage("blockMessage");
      const title = chrome.i18n.getMessage("modalTitle");
      const safetyBtn = chrome.i18n.getMessage("safetyBtn");
      const continueBtn = chrome.i18n.getMessage("continueBtn");
      // disable all checks if the current page is in the allowlist
      const url = new URL(sender.tab.url);
      const domain = url.hostname;
      const allowlist = result.allowlistdomains.includes(domain);
      // create settings that will be injected into page
      const settings = {
        enabled: result.enabled,
        blockall: result.blockall,
        message: message,
        allowlist: allowlist,
        title: title,
        safetyBtn: safetyBtn,
        continueBtn: continueBtn
      };
      sendResponse(settings);
    })
  } else {
    sendResponse({});
  }
}

// synchronous version
chrome.runtime.onMessageExternal.addListener(sendSettings);


// set default settings when plugin is installed
chrome.runtime.onInstalled.addListener(function () {
  let settings = { enabled: true, blockall: false, allowlistdomains: [] };
  chrome.storage.local.set(settings);
});
