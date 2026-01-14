// BinHex.Ninja Security - Injected Protection Script
// This script runs in the MAIN world (page context) to block clipboard manipulation
// Updated in v1.0.1 to use world: "MAIN" for Chrome MV3

(function () {
  "use strict";

  // Detect if we're in an iframe
  const isInIframe = window !== window.top;
  
  console.log(
    "[BinHex.Ninja Security] Clipboard protection v1.0.0 injected (MAIN world)",
    isInIframe ? "(in iframe)" : "(top frame)"
  );

  // Only skip on the extension's own UI pages (about:blank, about:srcdoc)
  const EXTENSION_UI_URLS = ['about:blank', 'about:srcdoc'];
  const isExtensionUI = EXTENSION_UI_URLS.some(url => window.location.href === url || window.location.href.startsWith(url));
  
  if (isExtensionUI || window.location.href === "") {
    console.log("[BinHex.Ninja Security] Skipping clipboard protection on extension UI");
    return;
  }

  // Track whitelist status and protection state
  let isWhitelisted = false;
  let protectionActive = true; // Start with protection ON
  let currentTheme = 'dark'; // Default theme
  let logoDataUrl = null; // Logo as data URL
  
  // Listen for message from content script about whitelist status and theme
  window.addEventListener("message", (event) => {
    // Log ALL messages for debugging
    if (event.source === window && event.data && event.data.type) {
      console.log("[BinHex.Ninja Security] 🔔 Received message:", event.data.type);
    }
    
    // Only accept messages from same window
    if (event.source !== window) return;
    
    if (event.data.type === "BINHEX_WHITELIST_STATUS") {
      isWhitelisted = event.data.isWhitelisted;
      console.log("[BinHex.Ninja Security] 📨 Received BINHEX_WHITELIST_STATUS:", isWhitelisted);
      console.log("[BinHex.Ninja Security] 📨 Current URL:", window.location.href);
      console.log("[BinHex.Ninja Security] 📨 Current protectionActive before:", protectionActive);
      
      if (isWhitelisted) {
        protectionActive = false; // Disable protection
        console.log("[BinHex.Ninja Security] ✅ URL/Domain is whitelisted, DISABLED clipboard protection");
        console.log("[BinHex.Ninja Security] ✅ New protectionActive:", protectionActive);
      } else {
        protectionActive = true; // Ensure protection is enabled
        console.log("[BinHex.Ninja Security] 🔒 URL/Domain is NOT whitelisted, protection remains ACTIVE");
        console.log("[BinHex.Ninja Security] 🔒 New protectionActive:", protectionActive);
      }
    }
    
    if (event.data.type === "BINHEX_THEME_DATA") {
      currentTheme = event.data.theme;
      logoDataUrl = event.data.logoDataUrl;
      console.log("[BinHex.Ninja Security] 🎨 Received theme data:", currentTheme, "Logo:", logoDataUrl ? "loaded" : "not available");
    }
  });
  
  // CRITICAL: Initialize protection IMMEDIATELY to catch fast attacks
  // The protectionActive flag will disable it if content script says it's whitelisted
  console.log("[BinHex.Ninja Security] 🚀 Initializing protection IMMEDIATELY (will be disabled if whitelisted)");
  
  // Initialize protection functions immediately (they check protectionActive flag)
  
  // Helper function to show security alert using custom modal (works in sandboxed contexts)
  // Matches the professional dark theme of the main warning overlay
  function showSecurityAlert(message) {
    // Log to console for debugging (using log instead of error since this is a successful block)
    console.log("%c🛡️ BinHex.Ninja Security", "color: #000000; font-size: 16px; font-weight: bold;");
    console.log("%cBlocked clipboard attack:", "color: #000000; font-weight: 600;", message.replace(/⚠️ SECURITY ALERT ⚠️\n\n/g, '').substring(0, 100) + "...");
    
    // If we're in an iframe, request the top frame to show the alert instead
    if (isInIframe) {
      console.log("[BinHex.Ninja Security] In iframe - requesting top frame to show clipboard alert");
      window.postMessage(
        {
          type: "CLICKFIX_SHOW_CLIPBOARD_ALERT_IN_TOP",
          message: message,
          iframeUrl: window.location.href
        },
        "*"
      );
      return; // Don't show in iframe
    }
    
    // Create custom modal that works in sandboxed contexts
    const existingModal = document.getElementById('binhex-security-alert-modal');
    if (existingModal) {
      return; // Already showing a modal
    }
    
    // Use theme from content script (or default to system preference)
    const isDark = currentTheme === 'dark';
    
    // Theme colors
    const colors = isDark ? {
      backdrop: 'rgba(0, 0, 0, 0.95)',
      background: '#000000',
      border: '#ffffff',
      text: '#ffffff',
      subtext: '#666666',
      buttonBg: '#ffffff',
      buttonText: '#000000',
      buttonHoverBg: '#000000',
      buttonHoverText: '#ffffff',
    } : {
      backdrop: 'rgba(255, 255, 255, 0.95)',
      background: '#ffffff',
      border: '#000000',
      text: '#000000',
      subtext: '#999999',
      buttonBg: '#000000',
      buttonText: '#ffffff',
      buttonHoverBg: '#ffffff',
      buttonHoverText: '#000000',
    };
    
    // Create backdrop
    const modal = document.createElement('div');
    modal.id = 'binhex-security-alert-modal';
    modal.style.cssText = `
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      background: ${colors.backdrop} !important;
      z-index: 2147483647 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif !important;
      padding: 20px !important;
      box-sizing: border-box !important;
    `;
    
    // Create alert box - flat minimal design
    const alertBox = document.createElement('div');
    alertBox.style.cssText = `
      background: ${colors.background} !important;
      padding: 40px !important;
      max-width: 480px !important;
      width: 90% !important;
      border: 1px solid ${colors.border} !important;
      text-align: center !important;
      position: relative !important;
    `;
    
    // Logo (only show if we have the data URL from content script)
    let logoContainer = null;
    if (logoDataUrl) {
      logoContainer = document.createElement('div');
      logoContainer.style.cssText = `
        margin-bottom: 20px !important;
      `;
      
      const logo = document.createElement('img');
      logo.src = logoDataUrl;
      logo.alt = 'BinHex.Ninja Security';
      logo.style.cssText = `
        width: 48px !important;
        height: 48px !important;
        display: inline-block !important;
      `;
      logoContainer.appendChild(logo);
    }
    
    // Title
    const title = document.createElement('h2');
    title.textContent = 'Clipboard Attack Blocked';
    title.style.cssText = `
      color: ${colors.text} !important;
      font-size: 20px !important;
      font-weight: 600 !important;
      margin: 0 0 8px 0 !important;
      line-height: 1.2 !important;
    `;
    
    // Subtitle
    const subtitle = document.createElement('p');
    subtitle.textContent = 'Malicious command intercepted';
    subtitle.style.cssText = `
      color: ${colors.subtext} !important;
      font-size: 12px !important;
      font-weight: 400 !important;
      margin: 0 0 24px 0 !important;
      text-transform: uppercase !important;
      letter-spacing: 0.05em !important;
    `;
    
    // Message
    const messageDiv = document.createElement('p');
    const cleanMessage = message
      .replace(/⚠️ SECURITY ALERT ⚠️\n\n/g, '')
      .replace(/BinHex\.Ninja Security blocked a malicious command[^\n]*\n\n/g, '')
      .trim();
    messageDiv.textContent = cleanMessage;
    messageDiv.style.cssText = `
      color: ${colors.text} !important;
      font-size: 13px !important;
      line-height: 1.6 !important;
      margin: 0 0 28px 0 !important;
      white-space: pre-wrap !important;
    `;
    
    // Button
    const button = document.createElement('button');
    button.textContent = 'I Understand';
    button.style.cssText = `
      background: ${colors.buttonBg} !important;
      color: ${colors.buttonText} !important;
      border: 1px solid ${colors.border} !important;
      padding: 12px 32px !important;
      font-size: 12px !important;
      font-weight: 500 !important;
      cursor: pointer !important;
      transition: all 0.15s ease !important;
    `;
    
    button.addEventListener('mouseover', () => {
      button.style.background = colors.buttonHoverBg + ' !important';
      button.style.color = colors.buttonHoverText + ' !important';
    });
    
    button.addEventListener('mouseout', () => {
      button.style.background = colors.buttonBg + ' !important';
      button.style.color = colors.buttonText + ' !important';
    });
    
    button.addEventListener('click', () => {
      modal.style.opacity = '0';
      modal.style.transition = 'opacity 0.15s ease-out';
      setTimeout(() => {
        if (document.body.contains(modal)) {
          document.body.removeChild(modal);
        }
      }, 150);
    });
    
    // Assemble the modal
    if (logoContainer) {
      alertBox.appendChild(logoContainer);
    }
    alertBox.appendChild(title);
    alertBox.appendChild(subtitle);
    alertBox.appendChild(messageDiv);
    alertBox.appendChild(button);
    modal.appendChild(alertBox);
    
    // Add animations via style tag
    const style = document.createElement('style');
    style.textContent = `
      #binhex-security-alert-modal {
        animation: none !important;
      }
    `;
    document.head.appendChild(style);
    
    // Append to body
    if (document.body) {
      document.body.appendChild(modal);
    } else {
      // If body doesn't exist yet, wait for it
      const observer = new MutationObserver(() => {
        if (document.body) {
          document.body.appendChild(modal);
          observer.disconnect();
        }
      });
      observer.observe(document.documentElement, { childList: true });
    }
  }
  
  // Listen for whitelist status from content script
  window.addEventListener("message", (event) => {
    if (event.source !== window) return;
    
    if (event.data.type === "BINHEX_WHITELIST_STATUS") {
      isWhitelisted = event.data.whitelisted;
      if (isWhitelisted) {
        console.log(
          "[BinHex.Ninja Security] Domain whitelisted - clipboard protection disabled for:",
          event.data.domain
        );
      }
    }
  });

  // Malicious command patterns (comprehensive ClickFix coverage)
  const MALICIOUS_PATTERNS = [
    // Base64-encoded commands
    /echo\s+["|']?[A-Za-z0-9+/=]{50,}["|']?\s*\|\s*base64\s+-d\s*\|\s*bash/gi,
    /echo\s+["|']?[A-Za-z0-9+/=]{50,}["|']?\s*\|\s*base64\s+--decode\s*\|\s*sh/gi,
    /base64\s+-d\s*<<<\s*["|']?[A-Za-z0-9+/=]{50,}/gi,
    /powershell\s+-e\s+[A-Za-z0-9+/=]{50,}/gi,
    /powershell\s+-enc\s+[A-Za-z0-9+/=]{50,}/gi,
    /powershell\s+-encodedcommand\s+[A-Za-z0-9+/=]{50,}/gi,
    /powershell\s+-windowstyle\s+hidden\s+-enc/gi,
    /IEX\s*\(\s*\[System\.Text\.Encoding\]::UTF8\.GetString\s*\(\s*\[System\.Convert\]::FromBase64String/gi,
    /curl\s+[^\s]+\s*\|\s*bash/gi,
    /wget\s+-qO-\s+[^\s]+\s*\|\s*bash/gi,
    /\/bin\/bash\s+-c\s+["|']?[A-Za-z0-9+/=]{50,}/gi,

    // Windows Script Host (WSH) attacks - ActiveXObject
    /new\s+ActiveXObject\s*\(\s*['"](Scripting\.FileSystemObject|WScript\.Shell|Shell\.Application)['"]\s*\)/gi,
    /new\s+ActiveXObject\s*\(\s*['"](WindowsInstaller\.Installer|MSXML2\.XMLHTTP|Microsoft\.XMLHTTP)['"]\s*\)/gi,
    /WScript\.ScriptFullName/gi,
    /WScript\.Shell/gi,
    /WScript\.CreateObject/gi,
    /\.Run\s*\(\s*['"](cmd|powershell|wscript|cscript)/gi,
    /cscript\.exe/gi,
    /wscript\.exe/gi,
    /wscript\s+(%tmp%|%temp%|%appdata%).*\.(js|vbs|wsf)/gi,
    /cscript\s+(%tmp%|%temp%|%appdata%).*\.(js|vbs|wsf)/gi,
    
    // Windows cmd.exe attacks
    /cmd\.exe\s+\/c\s+(curl|wget|powershell|start|schtasks|wscript|cscript|mshta)/gi,
    /%systemroot%.*cmd\.exe/gi,
    /%windir%.*cmd\.exe/gi,
    
    // Windows scheduled tasks (schtasks) - persistence
    /schtasks\s+\/create.*\/tr.*\.(bat|exe|ps1|vbs|js)/gi,
    /schtasks\s+\/create.*\/tn.*\/sc\s+(minute|onlogon|onstartup)/gi,
    
    // Windows environment variables with malicious files
    /%tmp%.*\.(bat|exe|ps1|vbs|js|cmd)/gi,
    /%temp%.*\.(bat|exe|ps1|vbs|js|cmd)/gi,
    /%appdata%.*\.(bat|exe|ps1|vbs|js|cmd)/gi,
    /%programdata%.*\.(bat|exe|ps1|vbs|js|cmd)/gi,
    
    // Windows start command attacks
    /start\s+\/min\s+(powershell|cmd|wscript|cscript|mshta)/gi,
    /start\s+['""][^'"]*\.(exe|bat|cmd|ps1|vbs|js)['"]/gi,
    /cmd\.exe\s+\/c\s+start/gi,
    
    // Shell execute patterns
    /ShellExecute\s*\(/gi,
    /CreateObject\s*\(\s*['"](WScript\.Shell|Shell\.Application)['"]\s*\)/gi,
    
    // MSI installer attacks
    /msiexec\s+(\/i|\/package)\s+http/gi,
    /WindowsInstaller\.Installer/gi,
    /\.Install\s*\(\s*['"']http/gi,
    /\.InstallProduct\s*\(/gi,

    // Windows PowerShell attacks (comprehensive)
    /powershell\.exe?\s+.*(-w\s+h|-windowstyle\s+hidden).*(-nop|-noprofile).*(-ep\s+bypass|-executionpolicy\s+bypass)/gi,
    /powershell\s+.*(-c|-command|-com)\s+.*iex\s*\(\s*(iwr|invoke-webrequest)/gi,
    /powershell\s+.*-com\s+['"]/gi,
    /pwsh\.exe?\s+.*(-c|-command)/gi,
    /iex\s*\(\s*(iwr|wget|curl|invoke-webrequest)\s+.*(-uri|http)/gi,
    /(iwr|invoke-webrequest)\s+.*(-useb|-usebasicparsing).*-outf.*%tmp%/gi,
    /(iwr|invoke-webrequest)\s+.*-outf.*%temp%/gi,
    /\(iwr.*(-useb|-usebasicparsing).*\)\.Content/gi,
    /\(Invoke-WebRequest.*\)\.Content/gi,
    /Start-BitsTransfer\s+.*-Source\s+.*-Destination/gi,
    /Invoke-WebRequest\s+.*\|\s*Invoke-Expression/gi,
    /IEX\s*\(\s*(New-Object\s+Net\.WebClient|Invoke-WebRequest|iwr)/gi,
    /powershell.*\|\s*iex/gi,
    /(iwr|invoke-webrequest).*\|\s*iex/gi,
    /downloadstring\s*\(['"']http/gi,
    /downloadfile\s*\(['"']http/gi,
    /\[Net\.WebClient\]::new\(\)\.DownloadString/gi,
    /\$wc\s*=\s*New-Object\s+System\.Net\.WebClient/gi,
    
    // PowerShell memory manipulation (shellcode injection)
    /VirtualAlloc/gi,
    /CreateThread/gi,
    /WaitForSingleObject/gi,
    /GetDelegateForFunctionPointer/gi,
    /AllocHGlobal/gi,
    /System\.Runtime\.InteropServices\.Marshal/gi,
    /\[System\.Reflection\.Emit\./gi,
    /DefineDynamicAssembly/gi,
    /DefineDynamicModule/gi,
    /\[Byte\[\]\]\s*\$\w+\s*=.*0x[0-9A-Fa-f]{2}/gi,
    /UnsafeNativeMethods/gi,
    /GetProcAddress/gi,
    /GetModuleHandle/gi,
    
    // Windows system utilities abuse
    /bitsadmin\s+\/transfer/gi,
    /certutil\.exe?\s+.*-urlcache\s+.*http/gi,
    /certutil\.exe?\s+.*-decode/gi,
    /mshta\s+(http|vbscript|javascript)/gi,
    /mshta\.exe\s+http/gi,
    /rundll32\.exe?\s+.*http/gi,
    /rundll32\.exe?\s+javascript/gi,
    /regsvr32\s+\/s\s+\/n\s+\/u\s+\/i:http/gi,
    /regsvr32\.exe?\s+.*\/i:http/gi,
    /wscript\.exe?\s+http/gi,
    /cscript\.exe?\s+http/gi,
    /odbcconf\.exe?\s+.*\/a\s*{/gi,
    /installutil\.exe?\s+.*http/gi,
    /regasm\.exe?\s+http/gi,
    /regsvcs\.exe?\s+http/gi,
    /\$env:APPDATA.*\.ps1/gi,
    /\$env:TEMP.*\.(exe|bat|ps1|vbs)/gi,
    /%TEMP%.*\.(exe|bat|ps1|vbs|cmd)/gi,
    /%APPDATA%.*\.(exe|bat|ps1|vbs|cmd)/gi,

    // Windows curl/wget attacks
    /curl\.exe\s+.*http[^\s]+\s*\|\s*powershell/gi,
    /curl\.exe\s+.*http[^\s]+.*-o\s+.*\.(exe|bat|ps1|vbs|cmd)/gi,
    /curl\s+.*http[^\s]+\s+&&\s+.*\.(exe|bat|ps1)/gi,
    /curl\s+.*http[^\s]+.*-o\s+%TEMP%/gi,
    /curl\s+.*http[^\s]+.*--output\s+%TEMP%/gi,
    /wget\.exe\s+.*http[^\s]+.*-O\s+.*\.(exe|bat|ps1|vbs|cmd)/gi,
    /wget\s+.*http[^\s]+\s+&&\s+.*\.(exe|bat|ps1)/gi,

    // macOS/Linux curl attacks (comprehensive)
    /curl\s+.*http[^\s]+\s*\|\s*(bash|sh|zsh|python|perl|ruby|php|node)/gi,
    /curl\s+.*(-s|-silent|-sSL|-fsSL)\s+.*http[^\s]+\s*\|\s*(bash|sh)/gi,
    /curl\s+.*http[^\s]+.*(-o|--output)\s+\/tmp\/.*\s*&&\s*(bash|sh|chmod)/gi,
    /curl\s+.*http[^\s]+.*(-o|--output)\s+\/dev\/shm\/.*\s*&&\s*(bash|sh)/gi,
    /bash\s+<\s*\(\s*curl\s+.*http/gi,
    /sh\s+<\s*\(\s*curl\s+.*http/gi,
    /zsh\s+<\s*\(\s*curl\s+.*http/gi,
    /curl\s+.*\|\s*sudo\s+(bash|sh)/gi,
    /curl\s+-L\s+.*http[^\s]+\s*\|\s*(bash|sh)/gi,

    // macOS/Linux wget attacks (comprehensive)
    /wget\s+.*http[^\s]+\s*\|\s*(bash|sh|zsh|python|perl|ruby|php|node)/gi,
    /wget\s+(-qO-|--quiet\s+-O\s+-|-O-)\s+.*http[^\s]+\s*\|\s*(bash|sh)/gi,
    /wget\s+.*http[^\s]+.*(-O|--output-document)\s+\/tmp\/.*\s*&&\s*(bash|sh|chmod)/gi,
    /wget\s+.*http[^\s]+.*(-O|--output-document)\s+\/dev\/shm\/.*\s*&&\s*(bash|sh)/gi,
    /bash\s+<\s*\(\s*wget\s+.*http/gi,
    /sh\s+<\s*\(\s*wget\s+.*http/gi,
    /wget\s+.*\|\s*sudo\s+(bash|sh)/gi,

    // Python download-execute
    /python.*-c.*import\s+(urllib|requests).*exec/gi,
    /python.*-c.*exec\s*\(\s*(urllib|requests)/gi,
    /python3?\s+-c\s+.*open\s*\(.*http/gi,
    /curl.*\|\s*python3?/gi,
    /wget.*\|\s*python3?/gi,

    // Node.js and other interpreters
    /node\s+-e\s+.*require\s*\(['"']https?:/gi,
    /perl\s+-e\s+.*get\s*\(['"']http/gi,
    /ruby\s+-e\s+.*Net::HTTP/gi,

    // Generic download-execute patterns
    /(curl|wget|fetch)\s+.*\|\s*sudo/gi,
    /chmod\s+\+x.*\/tmp\/.*&&\s*\/tmp\//gi,
    /chmod\s+\+x.*\/dev\/shm\/.*&&\s*\/dev\/shm\//gi,
    /\/tmp\/.*\.(sh|bin)\s*&&\s*bash/gi,
    /\/dev\/shm\/.*\.(sh|bin)\s*&&\s*bash/gi,
    
    // Command chaining patterns
    /cmd\.exe\s+\/c\s+.*&&\s*.*\.(exe|bat|vbs)/gi,
    /start\s+\/b\s+.*\.(exe|bat|vbs)/gi,
  ];

  // Helper function to check if malicious (respects protectionActive flag)
  // Returns false if safe, or an object with match details if malicious
  function isCommandMalicious(text) {
    // If protection is disabled (whitelisted), always return false
    if (!protectionActive) {
      console.log("[BinHex.Ninja Security] ⚪ Protection is disabled (protectionActive=false), allowing clipboard operation");
      return false;
    }
    // Don't block if whitelisted
    if (isWhitelisted) {
      console.log("[BinHex.Ninja Security] ⚪ Domain is whitelisted (isWhitelisted=true), allowing clipboard operation");
      return false;
    }
    
    if (!text || typeof text !== "string") return false;

    for (let i = 0; i < MALICIOUS_PATTERNS.length; i++) {
      const pattern = MALICIOUS_PATTERNS[i];
      pattern.lastIndex = 0; // Reset regex state
      const match = text.match(pattern);
      
      if (match) {
        const matchedText = match[0];
        const matchIndex = match.index;
        const contextStart = Math.max(0, matchIndex - 50);
        const contextEnd = Math.min(text.length, matchIndex + matchedText.length + 50);
        const context = text.substring(contextStart, contextEnd);
        
        // Log detailed match information
        console.group("%c🛡️ CLIPBOARD PROTECTION - Malicious Content Blocked", "color: #ff0000; font-weight: bold; font-size: 14px;");
        console.log("%c📍 Detection Type:", "font-weight: bold;", "Clipboard write attempt");
        console.log("%c🔍 Pattern:", "font-weight: bold;", pattern.source.substring(0, 100));
        console.log("%c✂️ Matched Text:", "font-weight: bold; color: #ff6600;", matchedText.substring(0, 200));
        console.log("%c📄 Context:", "font-weight: bold;", `...${context}...`);
        console.log("%c📊 Position:", "font-weight: bold;", `Character ${matchIndex} of ${text.length}`);
        console.log("%c📝 Full Clipboard Length:", "font-weight: bold;", `${text.length} characters`);
        console.log("%c🌐 URL:", "font-weight: bold;", window.location.href);
        console.groupEnd();
        
        return {
          matched: true,
          pattern: pattern.source.substring(0, 100),
          matchedText: matchedText,
          matchIndex: matchIndex,
          context: context
        };
      }
    }
    return false;
  }

  // Override navigator.clipboard.writeText in page context
  if (navigator.clipboard && navigator.clipboard.writeText) {
    const originalWriteText = navigator.clipboard.writeText.bind(
      navigator.clipboard,
    );

    navigator.clipboard.writeText = function (text) {
      const maliciousCheck = isCommandMalicious(text);
      if (maliciousCheck) {
        // Send message to content script for logging
        window.postMessage(
          {
            type: "CLICKFIX_CLIPBOARD_BLOCKED",
            url: window.location.href,
            content: text.substring(0, 500), // Truncate for logging
            matchDetails: maliciousCheck.matched ? {
              detectionCategory: "clipboard_protection",
              pattern: maliciousCheck.pattern,
              matchedText: maliciousCheck.matchedText.substring(0, 500),
              context: maliciousCheck.context.substring(0, 300),
              position: maliciousCheck.matchIndex
            } : null
          },
          "*",
        );

        showSecurityAlert(
          "⚠️ SECURITY ALERT ⚠️\n\nBinHex.Ninja Security blocked a malicious command from being copied to your clipboard!\n\nThis is a ClickFix scam attack. DO NOT run commands from websites in your terminal!",
        );
        
        console.log("[BinHex.Ninja Security] Clipboard attack blocked successfully");
        
        // CRITICAL: Overwrite clipboard with safe text to ensure nothing malicious got through
        try {
          originalWriteText("[BinHex.Ninja Security] Malicious content was blocked from your clipboard");
        } catch (e) {
          // Ignore errors from overwriting clipboard
        }
        
        return Promise.reject(
          new Error("Malicious content blocked by BinHex.Ninja Security"),
        );
      }
      return originalWriteText(text);
    };

    console.log("[BinHex.Ninja Security] Clipboard.writeText protected");
  }

  // Override clipboard.write API
  if (navigator.clipboard && navigator.clipboard.write) {
    const originalWrite = navigator.clipboard.write.bind(navigator.clipboard);

    navigator.clipboard.write = async function (data) {
      // Check clipboard items
      for (const item of data) {
        try {
          if (item.types.includes("text/plain")) {
            const blob = await item.getType("text/plain");
            const text = await blob.text();

            const maliciousCheck = isCommandMalicious(text);
            if (maliciousCheck) {
              // Send message to content script for logging
              window.postMessage(
                {
                  type: "CLICKFIX_CLIPBOARD_BLOCKED",
                  url: window.location.href,
                  content: text.substring(0, 500),
                  matchDetails: maliciousCheck.matched ? {
                    detectionCategory: "clipboard_protection",
                    pattern: maliciousCheck.pattern,
                    matchedText: maliciousCheck.matchedText.substring(0, 500),
                    context: maliciousCheck.context.substring(0, 300),
                    position: maliciousCheck.matchIndex
                  } : null
                },
                "*",
              );

              showSecurityAlert(
                "⚠️ SECURITY ALERT ⚠️\n\nBinHex.Ninja Security blocked a malicious command!\n\nThis is a ClickFix attack. DO NOT run terminal commands from websites!",
              );
              
              console.log("[BinHex.Ninja Security] Clipboard attack blocked successfully");
              
              return Promise.reject(new Error("Malicious content blocked"));
            }
          }
        } catch (e) {
          // Continue checking other items
        }
      }
      return originalWrite(data);
    };

    console.log("[BinHex.Ninja Security] Clipboard.write protected");
  }

  // Block document.execCommand('copy') attempts
  const originalExecCommand = document.execCommand.bind(document);
  document.execCommand = function (command, ...args) {
    if (command === "copy") {
      // Check what's being copied - could be from a hidden textarea
      const selection = window.getSelection().toString();
      const activeElement = document.activeElement;
      let textToCheck = selection;
      
      // Check if active element is a textarea/input with malicious content
      if (activeElement && (activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'INPUT')) {
        textToCheck = activeElement.value || selection;
      }
      
      const maliciousCheck = isCommandMalicious(textToCheck);
      if (maliciousCheck) {
        // CRITICAL: Clear selection AND blur active element IMMEDIATELY
        window.getSelection().removeAllRanges();
        if (activeElement && activeElement.blur) {
          activeElement.blur();
        }
        
        // Send message to content script for logging
        window.postMessage(
          {
            type: "CLICKFIX_CLIPBOARD_BLOCKED",
            url: window.location.href,
            content: textToCheck.substring(0, 500),
            matchDetails: maliciousCheck.matched ? {
              detectionCategory: "clipboard_protection",
              pattern: maliciousCheck.pattern,
              matchedText: maliciousCheck.matchedText.substring(0, 500),
              context: maliciousCheck.context.substring(0, 300),
              position: maliciousCheck.matchIndex
            } : null
          },
          "*",
        );

        showSecurityAlert(
          "⚠️ SECURITY ALERT ⚠️\n\nBinHex.Ninja Security blocked a malicious command!\n\nDO NOT paste commands from websites into your terminal!",
        );
        
        console.log("[BinHex.Ninja Security] execCommand('copy') blocked - malicious textarea detected");
        
        return false;
      }
    }
    return originalExecCommand(command, ...args);
  };

  console.log("[BinHex.Ninja Security] execCommand protected");

  // TRIPLE-LAYER COPY PROTECTION
  let maliciousContentDetected = false;
  
  // Layer 1: CAPTURE - runs BEFORE website handlers (HIGHEST PRIORITY)
  document.addEventListener(
    "copy",
    function (e) {
      const clipboardData = e.clipboardData || window.clipboardData;
      if (clipboardData) {
        const text =
          clipboardData.getData("text/plain") ||
          window.getSelection().toString();
        const maliciousCheck = isCommandMalicious(text);
        if (maliciousCheck) {
          // Set flag for other layers
          maliciousContentDetected = true;
          
          // CRITICAL: STOP EVERYTHING
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          
          // Clear clipboard data and set safe text
          if (e.clipboardData) {
            e.clipboardData.clearData();
            e.clipboardData.setData("text/plain", "[BinHex.Ninja Security] Malicious content was blocked");
          }
          
          // Clear any selection to prevent fallback copy
          window.getSelection().removeAllRanges();

          // Send message to content script for logging
          window.postMessage(
            {
              type: "CLICKFIX_CLIPBOARD_BLOCKED",
              url: window.location.href,
              content: text.substring(0, 500),
              matchDetails: maliciousCheck.matched ? {
                detectionCategory: "clipboard_protection",
                pattern: maliciousCheck.pattern,
                matchedText: maliciousCheck.matchedText.substring(0, 500),
                context: maliciousCheck.context.substring(0, 300),
                position: maliciousCheck.matchIndex
              } : null
            },
            "*",
          );

          showSecurityAlert(
            "⚠️ SECURITY ALERT ⚠️\n\nBinHex.Ninja Security blocked a malicious command!\n\nThis website is trying to trick you. DO NOT run terminal commands!",
          );
          
          console.log("[BinHex.Ninja Security] Copy event blocked in CAPTURE phase");
          
          return false;
        }
      }
    },
    true, // Capture phase
  );
  
  // Layer 2: BUBBLE - runs AFTER website handlers (catches overwrites) - HIGHEST PRIORITY
  document.addEventListener(
    "copy",
    function (e) {
      // If we flagged malicious content in capture, ALWAYS block
      if (maliciousContentDetected) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        if (e.clipboardData) {
          e.clipboardData.clearData();
          e.clipboardData.setData("text/plain", "[BinHex.Ninja Security] Malicious content was blocked");
        }
        
        maliciousContentDetected = false; // Reset flag
        console.log("[BinHex.Ninja Security] ⚠️ CRITICAL: Blocked flagged malicious content in BUBBLE phase!");
        return false;
      }
      
      const clipboardData = e.clipboardData || window.clipboardData;
      if (clipboardData) {
        // Re-check clipboard data - website might have overwritten it
        const text = clipboardData.getData("text/plain") || window.getSelection().toString();
        if (isCommandMalicious(text)) {
          // CRITICAL: Website tried to overwrite clipboard in bubble phase!
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();
          
          // Clear and set safe text AGAIN
          if (e.clipboardData) {
            e.clipboardData.clearData();
            e.clipboardData.setData("text/plain", "[BinHex.Ninja Security] Malicious content was blocked");
          }
          
          console.log("[BinHex.Ninja Security] ⚠️ CRITICAL: Website overwrote clipboard in BUBBLE phase - blocked!");
          
          return false;
        }
      }
    },
    false, // Bubble phase
  );
  
  // Layer 3: Additional bubble phase listener added LAST (runs after website's)
  // This catches any late-binding event listeners the website adds
  setTimeout(() => {
    document.addEventListener(
      "copy",
      function (e) {
        const clipboardData = e.clipboardData || window.clipboardData;
        if (clipboardData) {
          const text = clipboardData.getData("text/plain");
          if (isCommandMalicious(text)) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            if (e.clipboardData) {
              e.clipboardData.clearData();
              e.clipboardData.setData("text/plain", "[BinHex.Ninja Security] Malicious content was blocked");
            }
            
            console.log("[BinHex.Ninja Security] ⚠️ CRITICAL: Caught malicious content in DELAYED listener!");
            return false;
          }
        }
      },
      false
    );
    console.log("[BinHex.Ninja Security] Delayed copy listener installed (Layer 3)");
  }, 100);

  console.log("[BinHex.Ninja Security] Triple-layer copy event listeners installed");
  
  // Layer 4: POST-COPY VERIFICATION - Check actual clipboard contents after copy event
  // This is the nuclear option if all else fails
  document.addEventListener(
    "copy",
    function (e) {
      // Wait for all copy handlers to finish, then verify clipboard
      setTimeout(async () => {
        try {
          if (navigator.clipboard && navigator.clipboard.readText) {
            const clipboardText = await navigator.clipboard.readText();
            if (isCommandMalicious(clipboardText)) {
              console.log("[BinHex.Ninja Security] 🚨 NUCLEAR OPTION: Malicious content detected in clipboard AFTER copy event!");
              console.log("[BinHex.Ninja Security] 🚨 Overwriting clipboard now...");
              
              // Overwrite with safe text
              await navigator.clipboard.writeText("[BinHex.Ninja Security] Malicious content was detected and removed from your clipboard");
              
              console.log("[BinHex.Ninja Security] ✅ Clipboard sanitized successfully");
            }
          }
        } catch (err) {
          // Clipboard API might not be available or permission denied
          console.log("[BinHex.Ninja Security] ℹ️ Could not verify clipboard (permission denied or not available)");
        }
      }, 10); // Small delay to let all handlers finish
    },
    false
  );
  console.log("[BinHex.Ninja Security] Post-copy verification installed (Layer 4 - Nuclear Option)");
  
  // PROACTIVE PROTECTION: Monitor for malicious textareas/inputs being added to DOM
  const textareaMutationObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node.nodeType === 1) { // Element node
          // Check if it's a textarea or input
          if (node.tagName === 'TEXTAREA' || node.tagName === 'INPUT') {
            if (isCommandMalicious(node.value)) {
              console.log("[BinHex.Ninja Security] ⚠️ CRITICAL: Malicious textarea/input detected and neutralized!");
              node.value = "[BinHex.Ninja Security] Malicious content was blocked";
              node.setAttribute('readonly', 'true');
              node.style.display = 'none';
            }
          }
          // Also check children
          const textareas = node.querySelectorAll ? node.querySelectorAll('textarea, input') : [];
          textareas.forEach((textarea) => {
            if (isCommandMalicious(textarea.value)) {
              console.log("[BinHex.Ninja Security] ⚠️ CRITICAL: Malicious textarea/input detected and neutralized!");
              textarea.value = "[BinHex.Ninja Security] Malicious content was blocked";
              textarea.setAttribute('readonly', 'true');
              textarea.style.display = 'none';
            }
          });
        }
      });
    });
  });
  
  textareaMutationObserver.observe(document.body || document.documentElement, {
    childList: true,
    subtree: true
  });
  
  console.log("[BinHex.Ninja Security] Textarea monitoring active");

  // Listen for messages from content script to show clipboard alert in top frame
  window.addEventListener("message", (event) => {
    // Only accept messages from same window
    if (event.source !== window) return;
    
    // If we're the top frame, listen for requests to show clipboard alerts from iframes
    if (!isInIframe && event.data.type === "CLICKFIX_DISPLAY_CLIPBOARD_ALERT") {
      console.log("[BinHex.Ninja Security] Received request to display clipboard alert from iframe");
      showSecurityAlert(event.data.message);
    }
  });

  console.log("[BinHex.Ninja Security] ✅ Clipboard protection fully initialized");
} // End of IIFE
)();
