// update settings text
function updatePopupText(language) {
  Object.keys(language).map(item => {
    console.log(`${item}: ${language[item]}`)
    document.getElementById(item).innerText = language[item];
  })
}
// update the state of the sliders in the popup
async function update() {
  let lang = {
    "enable-text": chrome.i18n.getMessage("enableSetting"),
    "blockall-text": chrome.i18n.getMessage("blockallSetting"),
    "allowlist-text": chrome.i18n.getMessage("allowlistSetting")
  }
  updatePopupText(lang);
  chrome.storage.local.get("enabled", function (result) {
    document.getElementById("enabled").checked = result.enabled;
  });
  chrome.storage.local.get("blockall", function (result) {
    document.getElementById("blockall").checked = result.blockall;
  });
  // set the allowlist sliders based on the presence of the domain in the domain allowlist
  chrome.storage.local.get("allowlistdomains", function (result) {
    chrome.tabs.query({ active: true, currentWindow: true }, function (tabs) {
      try {
        // there can be only one
        const url = new URL(tabs[0].url);
        const domain = url.hostname;
        const state = result.allowlistdomains.includes(domain) ? true : false;
        document.getElementById("allowlist").checked = state;
      } catch (e) {
        console.log("error: ", e)
      }
    });
  });
}
function reloadPage() {
  chrome.tabs.query({ active: true, currentWindow: true }, function (tabs) {
    chrome.tabs.reload(tabs[0].id);
  });
}
document.getElementById("enabled").addEventListener("click", function () {
  const value = document.getElementById("enabled").checked;
  chrome.storage.local.set({ enabled: value });
  // if the extension is disabled, also disable the value for blockall
  if (!value) {
    chrome.storage.local.set({ blockall: value });
  }
  update();
  reloadPage();
});
document.getElementById("blockall").addEventListener("click", function () {
  const value = document.getElementById("blockall").checked;
  chrome.storage.local.get("enabled", function (result) {
    if (result.enabled) {
      chrome.storage.local.set({ blockall: value });
    }
    update();
    reloadPage();
  });
});
document.getElementById("allowlist").addEventListener("click", function () {
  const value = document.getElementById("allowlist").checked;
  chrome.tabs.query({ active: true, currentWindow: true }, function (tabs) {
    try {
      // extract domain from url of current page
      const url = new URL(tabs[0].url);
      const domain = url.hostname;
      chrome.storage.local.get("allowlistdomains", function (result) {
        let allowlist = result.allowlistdomains;
        if (!value) {
          // remove the current domain from the allowlist
          allowlist = allowlist.filter(item => item !== domain);
        }
        else {
          // add the current domain to the allowlist
          if (!allowlist.includes(domain)) {
            allowlist.push(domain);
          }
        }
        // update allowlist
        chrome.storage.local.set({ allowlistdomains: allowlist }, function () {
          console.log("allowlist updated:", allowlist);
        })
      });
      reloadPage();
    } catch (e) {
      // reset slider
      document.getElementById("allowlist").checked = false;
      console.log("error: ", e);
    }
  });
});
// update when first opening the popup
update();

