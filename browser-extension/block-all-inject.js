(() => {
  const UPDATE_TYPE = "CLICKFIX_BLOCK_ALL_UPDATE";
  const BLOCKED_TYPE = "CLICKFIX_BLOCK_ALL_BLOCKED";
  const THREAT_TYPE = "CLICKFIX_CLIPBOARD_THREAT";

  let enabled = false;
  let inCopyEvent = false;
  let pendingCopyBlock = null;
  let lastThreat = { text: "", timestamp: 0 };

  const COMMAND_REGEX =
    /\b(powershell(\.exe)?|pwsh|cmd(\.exe)?|bash|sh|zsh|curl|wget|rundll32|regsvr32|msbuild|mshta|wscript|cscript|bitsadmin|certutil|msiexec|schtasks|wmic|explorer(\.exe)?|reg\s+add|p[\s^`]*o[\s^`]*w[\s^`]*e[\s^`]*r[\s^`]*s[\s^`]*h[\s^`]*e[\s^`]*l[\s^`]*l|c[\s^`]*m[\s^`]*d)\b/i;
  const SHELL_HINT_REGEX =
    /(invoke-webrequest|invoke-restmethod|\biwr\b|\birm\b|curl\s+|wget\s+|downloadstring|frombase64string|start-bitstransfer|add-mppreference|invoke-expression|\biex\b|\biex\s*\(|encodedcommand|\-enc\b|\-encodedcommand\b|powershell\s+\-|cmd\s+\/c|bash\s+\-c|sh\s+\-c|rundll32\s+[^\s,]+,[^\s]+|regsvr32\s+\/i|certutil\s+\-urlcache|bitsadmin\s+\/transfer)/i;
  const URL_REGEX = /\bhttps?:\/\/[^\s"']+/i;
  const SHELL_META_REGEX = /[;&|`]/;
  const BASE64_CHUNK_REGEX = /[A-Za-z0-9+/]{40,}={0,2}/g;
  const ZERO_WIDTH_REGEX = /[\u200B-\u200D\u2060\uFEFF]/g;
  const CONTROL_CHAR_REGEX = /[\u0000-\u001F\u007F]/g;
  const MAX_ANALYSIS_LENGTH = 12000;
  const THREAT_THROTTLE_MS = 12000;

  const originalWriteText = navigator.clipboard?.writeText?.bind(navigator.clipboard);
  const originalWrite = navigator.clipboard?.write?.bind(navigator.clipboard);
  const originalExecCommand = document.execCommand?.bind(document);
  const originalSetData = DataTransfer?.prototype?.setData;

  function shouldThrottleThreat(text) {
    const snippet = String(text || "").slice(0, 200);
    const now = Date.now();
    if (lastThreat.text === snippet && now - lastThreat.timestamp < THREAT_THROTTLE_MS) {
      return true;
    }
    lastThreat = { text: snippet, timestamp: now };
    return false;
  }

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

  function notifyThreat(method, text, analysis) {
    const snippet = text ? String(text).slice(0, 600) : "";
    if (!snippet) {
      return;
    }
    if (shouldThrottleThreat(snippet)) {
      return;
    }
    window.postMessage(
      {
        type: THREAT_TYPE,
        method,
        text: snippet,
        analysis
      },
      "*"
    );
  }

  function normalizeClipboardText(value) {
    const raw = String(value || "").slice(0, MAX_ANALYSIS_LENGTH);
    const withoutInvisible = raw.replace(ZERO_WIDTH_REGEX, "");
    const withoutControl = withoutInvisible.replace(CONTROL_CHAR_REGEX, " ");
    const leadingWhitespaceMatch = withoutControl.match(/^\s+/);
    const leadingWhitespace = leadingWhitespaceMatch ? leadingWhitespaceMatch[0].length : 0;
    const cleaned = withoutControl.replace(/\s+/g, " ").trim();
    return { raw, cleaned, leadingWhitespace, normalized: withoutControl };
  }

  function getPageContextFlags() {
    const dataset = document.documentElement?.dataset;
    return {
      isTrustedHost: dataset?.clickfixTrustedHost === "true",
      isAllowlisted: dataset?.clickfixAllowlisted === "true"
    };
  }

  function computeEntropy(value) {
    if (!value) {
      return 0;
    }
    const counts = new Map();
    for (const char of value) {
      counts.set(char, (counts.get(char) || 0) + 1);
    }
    const length = value.length;
    if (!length) {
      return 0;
    }
    let entropy = 0;
    for (const count of counts.values()) {
      const ratio = count / length;
      entropy -= ratio * Math.log2(ratio);
    }
    return entropy;
  }

  function hasHighEntropy(value) {
    if (!value) {
      return false;
    }
    const tokens = value.split(/\s+/).filter((token) => token.length >= 32);
    return tokens.some((token) => computeEntropy(token) >= 4.2);
  }

  function extractBase64Candidates(text) {
    if (!text) {
      return [];
    }
    const candidates = new Set();
    const matches = text.match(BASE64_CHUNK_REGEX) || [];
    matches.forEach((value) => {
      const cleaned = value.replace(/=+$/, "");
      if (cleaned.length < 24 || cleaned.length % 4 === 1) {
        return;
      }
      candidates.add(value);
    });
    return [...candidates];
  }

  function decodeBase64Candidates(text) {
    const decoded = [];
    const candidates = extractBase64Candidates(text);
    candidates.forEach((value) => {
      try {
        const padded = value.length % 4 === 0 ? value : value + "=".repeat(4 - (value.length % 4));
        const result = atob(padded);
        if (result && /[\w\s]/.test(result)) {
          decoded.push(result);
        }
      } catch (error) {
        // Ignore invalid base64.
      }
    });
    return decoded;
  }

  function analyzeClipboardText(rawText) {
    const normalized = normalizeClipboardText(rawText);
    const cleaned = normalized.cleaned;
    if (!cleaned) {
      return { block: false, analysis: { score: 0 } };
    }

    const decodedChunks = decodeBase64Candidates(cleaned);
    const combined = [cleaned, ...decodedChunks].join("\n");
    const commandMatch = COMMAND_REGEX.test(combined);
    const shellHint = SHELL_HINT_REGEX.test(combined);
    const url = URL_REGEX.test(combined);
    const shellMeta = SHELL_META_REGEX.test(combined);
    const base64Candidates = extractBase64Candidates(cleaned);
    const base64LooksBinary = base64Candidates.some((value) => value.startsWith("TVqQ") || value.startsWith("TVpQ"));
    const base64 = base64Candidates.length > 0 || base64LooksBinary;
    const highEntropy = hasHighEntropy(combined);
    const isLong = cleaned.length > 280;
    const leadingWhitespace = normalized.leadingWhitespace >= 6;
    const looksLikeCommand =
      /^[\w.-]+\s+[-/]/.test(cleaned) ||
      /^\s*(sudo\s+)?(powershell|cmd|bash|sh|zsh|curl|wget)\b/i.test(cleaned);

    let score = 0;
    if (commandMatch) {
      score += 4;
    }
    if (shellHint) {
      score += 3;
    }
    if (url && (commandMatch || shellHint)) {
      score += 2;
    }
    if (base64) {
      score += 2;
    }
    if (highEntropy) {
      score += 1;
    }
    if (shellMeta) {
      score += 1;
    }
    if (isLong) {
      score += 1;
    }
    if (leadingWhitespace) {
      score += 1;
    }
    if (looksLikeCommand) {
      score += 1;
    }

    const strongBlock = commandMatch && (shellHint || url || base64 || highEntropy);
    const strongObfuscation = base64 && highEntropy && isLong;
    const context = getPageContextFlags();
    let threshold = 6;
    if (context.isTrustedHost) {
      threshold += 2;
    }
    if (context.isAllowlisted) {
      threshold += 3;
    }
    const block = strongBlock || strongObfuscation || score >= threshold;

    return {
      block,
      analysis: {
        commandMatch,
        shellHint,
        url,
        shellMeta,
        base64,
        highEntropy,
        isLong,
        leadingWhitespace,
        looksLikeCommand,
        score
      }
    };
  }

  function shouldBlockAll() {
    return enabled;
  }

  if (originalWriteText) {
    navigator.clipboard.writeText = function (text) {
      const value = String(text ?? "");
      if (shouldBlockAll()) {
        notifyBlocked("writeText", value);
        return Promise.reject(new Error("ClickFix Mitigator blocked clipboard writeText"));
      }
      const result = analyzeClipboardText(value);
      if (result.block) {
        notifyThreat("writeText", value, result.analysis);
        return Promise.reject(new Error("ClickFix Mitigator blocked clipboard writeText"));
      }
      return originalWriteText(text);
    };
  }

  if (originalWrite) {
    navigator.clipboard.write = function (items) {
      if (shouldBlockAll()) {
        notifyBlocked("write", "");
        return Promise.reject(new Error("ClickFix Mitigator blocked clipboard write"));
      }
      return originalWrite(items);
    };
  }

  if (originalExecCommand) {
    document.execCommand = function (command, ...args) {
      if (String(command).toLowerCase() !== "copy") {
        return originalExecCommand(command, ...args);
      }
      const selection = window.getSelection ? window.getSelection() : null;
      const selectionText = selection ? selection.toString() : "";
      if (shouldBlockAll()) {
        notifyBlocked("execCommand", selectionText);
        return false;
      }
      const result = analyzeClipboardText(selectionText);
      pendingCopyBlock = null;
      if (result.block) {
        notifyThreat("execCommand", selectionText, result.analysis);
        return false;
      }
      const executed = originalExecCommand(command, ...args);
      if (pendingCopyBlock?.text) {
        if (!pendingCopyBlock.notified) {
          notifyThreat(pendingCopyBlock.method || "clipboardData", pendingCopyBlock.text, pendingCopyBlock.analysis);
        }
        if (originalWriteText) {
          originalWriteText("").catch(() => {});
        }
        pendingCopyBlock = null;
        return false;
      }
      return executed;
    };
  }

  if (originalSetData) {
    DataTransfer.prototype.setData = function (type, data) {
      const typeValue = String(type || "").toLowerCase();
      const textValue = String(data ?? "");
      if (inCopyEvent && typeValue.includes("text")) {
        if (shouldBlockAll()) {
          notifyBlocked("setData", textValue);
          return false;
        }
        const result = analyzeClipboardText(textValue);
        if (result.block) {
          pendingCopyBlock = {
            text: textValue,
            analysis: result.analysis,
            method: "clipboardData",
            notified: true
          };
          notifyThreat("clipboardData", textValue, result.analysis);
          return false;
        }
      }
      return originalSetData.call(this, type, data);
    };
  }

  document.addEventListener(
    "copy",
    () => {
      inCopyEvent = true;
      setTimeout(() => {
        inCopyEvent = false;
        pendingCopyBlock = null;
      }, 0);
    },
    true
  );

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
