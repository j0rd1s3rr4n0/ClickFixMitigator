(() => {
  const UPDATE_TYPE = "CLICKFIX_BLOCK_ALL_UPDATE";
  const BLOCKED_TYPE = "CLICKFIX_BLOCK_ALL_BLOCKED";
  let enabled = false;

  const originalWriteText = navigator.clipboard?.writeText?.bind(navigator.clipboard);
  const originalWrite = navigator.clipboard?.write?.bind(navigator.clipboard);
  const originalExecCommand = document.execCommand?.bind(document);

  function notifyBlocked(method, text) {
    window.postMessage(
      {
        type: BLOCKED_TYPE,
        method,
        text: text ? String(text).slice(0, 200) : ""
      },
      "*"
    );
  }

  function shouldBlock() {
    return enabled;
  }

  if (originalWriteText) {
    navigator.clipboard.writeText = function (text) {
      if (shouldBlock()) {
        notifyBlocked("writeText", text);
        return Promise.reject(new Error("ClickFix Mitigator blocked clipboard writeText"));
      }
      return originalWriteText(text);
    };
  }

  if (originalWrite) {
    navigator.clipboard.write = function (items) {
      if (shouldBlock()) {
        notifyBlocked("write", "");
        return Promise.reject(new Error("ClickFix Mitigator blocked clipboard write"));
      }
      return originalWrite(items);
    };
  }

  if (originalExecCommand) {
    document.execCommand = function (command, ...args) {
      if (shouldBlock() && String(command).toLowerCase() === "copy") {
        notifyBlocked("execCommand", "");
        return false;
      }
      return originalExecCommand(command, ...args);
    };
  }

  window.addEventListener("message", (event) => {
    if (event.source !== window) {
      return;
    }
    if (event.data?.type === UPDATE_TYPE) {
      enabled = Boolean(event.data.enabled);
    }
  });

  if (document.documentElement) {
    document.documentElement.dataset.clickfixBlockallInjected = "true";
  }
})();
