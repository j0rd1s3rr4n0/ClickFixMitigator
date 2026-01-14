// BinHex.Ninja Security - Content Script
// Detects and blocks base64-encoded commands and fake verification dialogs
// NOTE: Clipboard protection now runs via inject-protection.js with world: "MAIN"

(function () {
  "use strict";

  console.log(
    "[BinHex.Ninja Security] Content script v1.4.0 loaded on:",
    window.location.href,
  );

  // PERMANENT INTERNAL WHITELIST - Only block the extension's own UI pages
  const EXTENSION_UI_URLS = ['about:blank', 'about:srcdoc'];
  const isExtensionUI = EXTENSION_UI_URLS.some(url => window.location.href === url || window.location.href.startsWith(url));
  
  if (isExtensionUI) {
    console.log("[BinHex.Ninja Security] ⚠️ Extension UI detected, skipping ALL protection:", window.location.href);
    
    // Tell clipboard protection to skip too
    window.postMessage({
      type: "BINHEX_WHITELIST_STATUS",
      isWhitelisted: true
    }, "*");
    
    return; // Exit immediately, don't initialize anything
  }

  let warningShown = false;
  let detectionReasons = [];
  let matchDetails = null; // Store detailed match information for logging
  let userWhitelistChecked = false;
  let isWhitelisted = false;
  let isTrustedUrl = false; // Cache for trusted URL check

  // Patterns for detecting malicious commands
  const MALICIOUS_PATTERNS = {
    // Base64 command patterns
    base64Commands: [
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
    ],

    // Dangerous plain PowerShell/bash commands (new!)
    dangerousCommands: [
      // Windows Script Host (WSH) attacks - from Latrodectus analysis
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
      
      // Windows scheduled tasks (schtasks) - common in malware persistence
      /schtasks\s+\/create.*\/tr.*\.(bat|exe|ps1|vbs|js)/gi,
      /schtasks\s+\/create.*\/tn.*\/sc\s+(minute|onlogon|onstartup)/gi,
      
      // Windows environment variables used in malicious commands
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
      
      // MSI installer attacks - from Latrodectus
      /msiexec\s+(\/i|\/package)\s+http/gi,
      /WindowsInstaller\.Installer/gi,
      /\.Install\s*\(\s*['"']http/gi,
      /\.InstallProduct\s*\(/gi,
      
      // Windows PowerShell attacks
      /powershell\.exe?\s+.*(-w\s+h|-windowstyle\s+hidden).*(-nop|-noprofile).*(-ep\s+bypass|-executionpolicy\s+bypass)/gi,
      /powershell\s+.*(-c|-command|-com)\s+.*iex\s*\(\s*(iwr|invoke-webrequest)/gi,
      /powershell\s+.*-com\s+['"]/gi,
      /pwsh\.exe?\s+.*(-c|-command)/gi,
      /iex\s*\(\s*(iwr|wget|curl|invoke-webrequest)\s+.*(-uri|http)/gi,
      /(iwr|invoke-webrequest)\s+.*(-useb|-usebasicparsing).*-outf.*%tmp%/gi,
      /(iwr|invoke-webrequest)\s+.*-outf.*%temp%/gi,
      /\(iwr.*(-useb|-usebasicparsing).*\)\.Content/gi, // Downloading binary content
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
      /bitsadmin\s+\/transfer/gi,
      /certutil\.exe?\s+.*-urlcache\s+.*http/gi,
      /certutil\.exe?\s+.*-decode/gi,
      /mshta\s+(http|vbscript|javascript)/gi,
      /mshta\.exe\s+http/gi,
      /mshta\s+http/gi,
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
      /\[Byte\[\]\]\s*\$\w+\s*=.*0x[0-9A-Fa-f]{2}/gi, // Byte array with hex values (shellcode)
      /UnsafeNativeMethods/gi,
      /GetProcAddress/gi,
      /GetModuleHandle/gi,

      // Windows curl/wget attacks (Windows 10+ has curl.exe)
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
      /powershell\.exe?\s+.*&&\s*.*\.(exe|bat|ps1)/gi,
      /start\s+.*&&\s*.*(powershell|cmd|wscript|cscript)/gi,
    ],

    // ClickFix-related text patterns
    clickfixPhrases: [
      /verify\s+you\s+are\s+human/gi,
      /press\s+(windows\s+key\s*\+\s*r|win\s*\+\s*r|⊞\s*\+\s*r)/gi,
      /press\s+(command\s*\+\s*space|cmd\s*\+\s*space|⌘\s*\+\s*space)/gi,
      /open\s+(run|terminal|powershell|command\s+prompt)/gi,
      /(ctrl|control)\s*\+\s*v\s+to\s+paste/gi,
      /paste\s+(the\s+)?(following\s+)?(command|code)/gi,
      /human\s+verification\s+(required|needed|failed)/gi,
      /cloudflare\s+verification/gi,
      /recaptcha\s+verification/gi,
      /to\s+continue,\s+please\s+(run|execute|paste)/gi,
      /fix\s+it\s+by\s+(running|executing|pasting)/gi,
      /step\s+\d+:\s*(open|press|paste|run)/gi,
      /browser\s+(not\s+supported|error|verification)/gi,
    ],

    // Suspicious keyboard shortcut instructions
    keyboardInstructions: [
      /windows\s*\+\s*r/gi,
      /win\s*\+\s*r/gi,
      /⊞\s*\+\s*r/gi,
      /command\s*\+\s*space/gi,
      /cmd\s*\+\s*space/gi,
      /⌘\s*\+\s*space/gi,
      /ctrl\s*\+\s*shift\s*\+\s*(esc|t|n)/gi,
      /alt\s*\+\s*f4/gi,
    ],

    // Long base64 strings (potential encoded payloads)
    base64Strings: [/[A-Za-z0-9+/]{100,}={0,2}/g],
  };

  // Get all text content from EVERYWHERE on the page
  function getAllPageText() {
    // Exclude our own warning overlay from scanning
    const warningOverlay = document.getElementById('clickfix-malware-warning');
    if (warningOverlay) {
      warningOverlay.style.display = 'none'; // Temporarily hide
    }
    
    let allText = document.body ? document.body.innerText || "" : "";
    let allHtml = document.documentElement
      ? document.documentElement.innerHTML || ""
      : "";
    
    // Restore warning overlay
    if (warningOverlay) {
      warningOverlay.style.display = 'flex';
    }

    // 1. Check iframe content (including srcdoc)
    const iframes = document.querySelectorAll("iframe:not([data-binhex-security])");
    iframes.forEach((iframe) => {
      try {
        // Skip iframes inside our warning overlay
        if (warningOverlay && warningOverlay.contains(iframe)) {
          return; // Skip this iframe
        }
        
        // Check srcdoc attribute
        if (iframe.srcdoc) {
          const srcdocLower = iframe.srcdoc.toLowerCase();
          
          // Debug: Log first 200 chars of srcdoc
          console.log("[BinHex.Ninja Security] Found iframe srcdoc, preview:", iframe.srcdoc.substring(0, 200));
          
          // Skip if it's our own warning page content - check multiple markers
          const ownContentMarkers = [
            'binhex',
            'clickfix-malware-warning',
            'malware-warning',
            'data-binhex-security',
            'security alert',
            'potential threat detected',
            'threat detected',
            'clickfix attack'
          ];
          
          const isOwnContent = ownContentMarkers.some(marker => srcdocLower.includes(marker));
          
          if (isOwnContent) {
            console.log("[BinHex.Ninja Security] ✅ Skipping own warning overlay srcdoc");
            return;
          }
          
          allHtml += " " + iframe.srcdoc;
          console.log("[BinHex.Ninja Security] ⚠️ Scanning external iframe srcdoc");
        }
        // Try to access iframe content (will fail for cross-origin iframes)
        if (iframe.contentDocument && iframe.contentDocument.body) {
          const iframeText = iframe.contentDocument.body.innerText || "";
          const iframeHtml =
            iframe.contentDocument.documentElement.innerHTML || "";
          allText += " " + iframeText;
          allHtml += " " + iframeHtml;
          console.log("[BinHex.Ninja Security] Scanning iframe content");
        }
      } catch (e) {
        // Cross-origin iframe, can't access
      }
    });

    // 2. Check Shadow DOM (Web Components)
    document.querySelectorAll("*").forEach((element) => {
      // Skip elements inside our warning overlay
      if (warningOverlay && (element === warningOverlay || warningOverlay.contains(element))) {
        return;
      }
      
      if (element.shadowRoot) {
        try {
          const shadowText = element.shadowRoot.textContent || "";
          const shadowHtml = element.shadowRoot.innerHTML || "";
          allText += " " + shadowText;
          allHtml += " " + shadowHtml;
          console.log("[BinHex.Ninja Security] Found Shadow DOM content");
        } catch (e) {}
      }
    });

    // 3. Check inline script content
    document.querySelectorAll("script").forEach((script) => {
      if (script.textContent) {
        allHtml += " " + script.textContent;
      }
    });

    // 4. Check all HTML attributes (onclick, data-*, etc.)
    document.querySelectorAll("*").forEach((element) => {
      Array.from(element.attributes).forEach((attr) => {
        allHtml += " " + attr.value;
      });
    });

    // 5. Check template tags
    document.querySelectorAll("template").forEach((template) => {
      if (template.content) {
        allHtml += " " + template.innerHTML;
      }
    });

    // 6. Check object and embed tags
    document.querySelectorAll("object, embed").forEach((obj) => {
      if (obj.data) allHtml += " " + obj.data;
    });

    // 7. Check SVG text content
    document.querySelectorAll("svg text, svg tspan").forEach((text) => {
      allText += " " + text.textContent;
    });

    // 8. Check meta tags
    document.querySelectorAll("meta").forEach((meta) => {
      if (meta.content) allText += " " + meta.content;
    });

    // 9. Check localStorage and sessionStorage
    try {
      for (let i = 0; i < localStorage.length; i++) {
        const key = localStorage.key(i);
        const value = localStorage.getItem(key);
        allText += " " + value;
      }
      for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        const value = sessionStorage.getItem(key);
        allText += " " + value;
      }
    } catch (e) {}

    // 10. Check window.name
    if (window.name) {
      allText += " " + window.name;
    }

    // 11. Check URL and hash
    allText += " " + window.location.href;
    allText += " " + window.location.hash;
    allText += " " + window.location.search;

    // 12. Check CSS content (::before, ::after can contain text)
    document.querySelectorAll("style").forEach((style) => {
      allHtml += " " + style.textContent;
    });

    // 13. Check ARIA labels and descriptions
    document
      .querySelectorAll("[aria-label], [aria-description], [aria-labelledby]")
      .forEach((el) => {
        if (el.getAttribute("aria-label"))
          allText += " " + el.getAttribute("aria-label");
        if (el.getAttribute("aria-description"))
          allText += " " + el.getAttribute("aria-description");
      });

    // 14. Check title, alt, placeholder attributes
    document.querySelectorAll("[title], [alt], [placeholder]").forEach((el) => {
      if (el.title) allText += " " + el.title;
      if (el.alt) allText += " " + el.alt;
      if (el.placeholder) allText += " " + el.placeholder;
    });

    // 15. Check hidden inputs and textareas
    document.querySelectorAll("input, textarea").forEach((input) => {
      if (input.value) allText += " " + input.value;
    });

    // 16. Check HTML comments (extract from HTML)
    const commentRegex = /<!--([\s\S]*?)-->/g;
    let match;
    while ((match = commentRegex.exec(allHtml)) !== null) {
      allText += " " + match[1];
    }

    // 17. Check document title
    allText += " " + document.title;

    // 18. Check noscript tags
    document.querySelectorAll("noscript").forEach((noscript) => {
      allText += " " + noscript.textContent;
    });

    return { text: allText, html: allHtml };
  }

  // Whitelist of legitimate domains that shouldn't trigger detection
  const WHITELIST = [
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

  // Check if current site is whitelisted (built-in whitelist with toggles)
  async function isWhitelistedDomain() {
    const currentHostname = window.location.hostname;

    // Find matching built-in domain
    const matchedDomain = WHITELIST.find(
      (domain) =>
        currentHostname === domain || currentHostname.endsWith("." + domain),
    );

    if (!matchedDomain) {
      return false; // Not in built-in whitelist
    }

    // Check if this domain is toggled on
    try {
      // Check if extension context is valid
      if (!chrome.runtime?.id) {
        return true; // Default to enabled if extension context is invalid
      }
      
      const settings = await chrome.storage.local.get(["domainToggles"]);
      const domainToggles = settings.domainToggles || {};

      // Default to enabled (true) if not explicitly set
      const isEnabled = domainToggles[matchedDomain] !== false;

      if (isEnabled) {
        console.log(
          "[BinHex.Ninja Security] ✅ Built-in whitelisted domain (enabled), skipping scans:",
          currentHostname,
          "matched:",
          matchedDomain,
        );
      } else {
        console.log(
          "[BinHex.Ninja Security] ⚙️ Built-in domain found but disabled, will scan:",
          currentHostname,
          "matched:",
          matchedDomain,
        );
      }

      return isEnabled;
    } catch (error) {
      // Only log if it's not an extension context error
      if (!error.message?.includes("Extension context invalidated") && chrome.runtime?.id) {
        console.error(
          "[BinHex.Ninja Security] Failed to check domain toggles:",
          error,
        );
      }
      return true; // Default to enabled on error
    }
  }

  // Check if current site is in user's custom whitelist (with toggles)
  async function checkUserWhitelist() {
    if (userWhitelistChecked) {
      return isWhitelisted;
    }

    try {
      // Check if extension context is valid
      if (!chrome.runtime?.id) {
        userWhitelistChecked = true;
        isWhitelisted = false;
        return false;
      }
      
      const settings = await chrome.storage.local.get([
        "customWhitelist",
        "userWhitelist",
        "domainToggles",
      ]);

      // Support both new (customWhitelist) and legacy (userWhitelist) fields
      const customWhitelist =
        settings.customWhitelist || settings.userWhitelist || [];
      const domainToggles = settings.domainToggles || {};
      const currentHostname = window.location.hostname;

      // Find matching custom domain
      const matchedDomain = customWhitelist.find(
        (domain) =>
          currentHostname === domain ||
          currentHostname.endsWith("." + domain) ||
          currentHostname === "www." + domain,
      );

      if (!matchedDomain) {
        isWhitelisted = false;
        userWhitelistChecked = true;
        return false;
      }

      // Check if this domain is toggled on
      // Default to enabled (true) if not explicitly set
      isWhitelisted = domainToggles[matchedDomain] !== false;

      if (isWhitelisted) {
        console.log(
          "[BinHex.Ninja Security] ✅ Custom whitelisted domain (enabled), skipping scans:",
          currentHostname,
          "matched:",
          matchedDomain,
        );
      } else {
        console.log(
          "[BinHex.Ninja Security] ⚙️ Custom domain found but disabled, will scan:",
          currentHostname,
          "matched:",
          matchedDomain,
        );
      }

      userWhitelistChecked = true;
      return isWhitelisted;
    } catch (error) {
      // Only log if it's not an extension context error
      if (!error.message?.includes("Extension context invalidated") && chrome.runtime?.id) {
        console.error(
          "[BinHex.Ninja Security] Failed to check user whitelist:",
          error,
        );
      }
      userWhitelistChecked = true;
      isWhitelisted = false;
      return false;
    }
  }

  // Check if URL is trusted (with caching)
  async function checkTrustedUrl() {
    if (isTrustedUrl) {
      return true;
    }
    
    try {
      // Check if extension context is valid (silently skip if invalid)
      if (!chrome.runtime?.id) {
        return false;
      }
      
      const currentUrl = window.location.href;
      const settings = await chrome.storage.local.get(["trustedUrls"]);
      const trustedUrls = settings.trustedUrls || [];
      console.log("[BinHex.Ninja Security] 🔍 Checking trusted URLs:", trustedUrls);
      console.log("[BinHex.Ninja Security] 🔍 Current URL:", currentUrl);
      
      if (trustedUrls.includes(currentUrl)) {
        console.log("[BinHex.Ninja Security] ✅ Trusted URL, skipping all scans");
        isTrustedUrl = true;
        
        // Tell clipboard protection to skip too
        window.postMessage({
          type: "BINHEX_WHITELIST_STATUS",
          isWhitelisted: true
        }, "*");
        
        return true;
      }
    } catch (error) {
      // Silently ignore errors (likely extension context invalidated)
      return false;
    }
    return false;
  }

  // Check for malicious patterns in text content
  async function checkForMaliciousContent() {
    // FIRST: Check if this specific URL is trusted
    if (await checkTrustedUrl()) {
      return false;
    }

    // Check user's personal whitelist first
    console.log("[BinHex.Ninja Security] 🔍 Checking custom domain whitelist...");
    const userWhitelisted = await checkUserWhitelist();
    console.log("[BinHex.Ninja Security] 🔍 Custom whitelist result:", userWhitelisted);
    if (userWhitelisted) {
      console.log("[BinHex.Ninja Security] ✅ Custom whitelisted domain, skipping scans");
      return false; // Skip all detection
    }

    // Skip ALL detection on built-in whitelisted domains (only clipboard protection stays active)
    console.log("[BinHex.Ninja Security] 🔍 Checking built-in whitelist...");
    const builtInWhitelisted = await isWhitelistedDomain();
    console.log("[BinHex.Ninja Security] 🔍 Built-in whitelist result:", builtInWhitelisted);
    if (builtInWhitelisted) {
      console.log(
        "[BinHex.Ninja Security] ✅ Built-in whitelisted domain, skipping scans:",
        window.location.hostname,
      );
      return false;
    }

    const { text: bodyText, html: htmlContent } = getAllPageText();

    // Debug: Check if PowerShell command is in the content
    if (htmlContent.toLowerCase().includes("powershell")) {
      console.log(
        "[BinHex.Ninja Security] 🔍 Found PowerShell in page content",
      );
    }
    if (htmlContent.toLowerCase().includes("start-bitstransfer")) {
      console.log(
        "[BinHex.Ninja Security] 🔍 Found Start-BitsTransfer in page content",
      );
    }

    // Check for base64 commands
    for (const pattern of MALICIOUS_PATTERNS.base64Commands) {
      pattern.lastIndex = 0; // Reset regex state
      let match = bodyText.match(pattern);
      let location = 'body text';
      let sourceText = bodyText;
      if (!match) {
        pattern.lastIndex = 0;
        match = htmlContent.match(pattern);
        location = 'HTML content';
        sourceText = htmlContent;
      }
      
      if (match) {
        const matchedText = match[0];
        const matchIndex = match.index || 0;
        const contextStart = Math.max(0, matchIndex - 50);
        const contextEnd = Math.min(sourceText.length, matchIndex + matchedText.length + 50);
        const context = sourceText.substring(contextStart, contextEnd);
        
        console.group("%c🚨 MALWARE DETECTION - Base64 Command", "color: #ff0000; font-weight: bold; font-size: 14px;");
        console.log("%c📍 Location:", "font-weight: bold;", location);
        console.log("%c🔍 Pattern:", "font-weight: bold;", pattern.source.substring(0, 100));
        console.log("%c✂️ Matched Text:", "font-weight: bold; color: #ff6600;", matchedText.substring(0, 200));
        console.log("%c📄 Context:", "font-weight: bold;", `...${context}...`);
        console.log("%c📊 Position:", "font-weight: bold;", `Character ${matchIndex} of ${sourceText.length}`);
        console.groupEnd();
        
        // Store match details for logging
        matchDetails = {
          detectionCategory: "base64_command",
          location: location,
          pattern: pattern.source.substring(0, 200),
          matchedText: matchedText.substring(0, 500),
          context: context.substring(0, 300),
          position: matchIndex,
          contentLength: sourceText.length
        };
        
        detectionReasons.push(`Detected base64-encoded command pattern: ${matchedText.substring(0, 50)}...`);
        return true;
      }
    }

    // Check for dangerous plain commands (PowerShell, bash, etc.)
    console.log("[BinHex.Ninja Security] Checking for dangerous commands...");
    for (const pattern of MALICIOUS_PATTERNS.dangerousCommands) {
      pattern.lastIndex = 0; // Reset regex state
      let match = bodyText.match(pattern);
      let location = 'body text';
      let sourceText = bodyText;
      if (!match) {
        pattern.lastIndex = 0;
        match = htmlContent.match(pattern);
        location = 'HTML content';
        sourceText = htmlContent;
      }
      
      if (match) {
        const matchedText = match[0];
        const matchIndex = match.index || 0;
        const contextStart = Math.max(0, matchIndex - 50);
        const contextEnd = Math.min(sourceText.length, matchIndex + matchedText.length + 50);
        const context = sourceText.substring(contextStart, contextEnd);
        
        console.group("%c🚨 MALWARE DETECTION - Dangerous Command", "color: #ff0000; font-weight: bold; font-size: 14px;");
        console.log("%c📍 Location:", "font-weight: bold;", location);
        console.log("%c🔍 Pattern:", "font-weight: bold;", pattern.source.substring(0, 100));
        console.log("%c✂️ Matched Text:", "font-weight: bold; color: #ff6600;", matchedText.substring(0, 200));
        console.log("%c📄 Context:", "font-weight: bold;", `...${context}...`);
        console.log("%c📊 Position:", "font-weight: bold;", `Character ${matchIndex} of ${sourceText.length}`);
        console.log("%c🌐 URL:", "font-weight: bold;", window.location.href);
        console.groupEnd();
        
        // Store match details for logging
        matchDetails = {
          detectionCategory: "dangerous_command",
          location: location,
          pattern: pattern.source.substring(0, 200),
          matchedText: matchedText.substring(0, 500),
          context: context.substring(0, 300),
          position: matchIndex,
          contentLength: sourceText.length
        };
        
        detectionReasons.push(`Detected dangerous command: ${matchedText.substring(0, 50)}...`);
        return true;
      }
    }

    // Check for ClickFix phrases
    let clickfixMatches = 0;
    const matchedPhrases = [];

    for (const pattern of MALICIOUS_PATTERNS.clickfixPhrases) {
      pattern.lastIndex = 0; // Reset regex state
      if (pattern.test(bodyText)) {
        clickfixMatches++;
        matchedPhrases.push(pattern.source.substring(0, 30));
      }
    }

    // Smart detection: require 3+ matches OR specific dangerous combinations
    const hasVerifyHuman = /verify\s+you\s+are\s+human/gi.test(bodyText);

    // Windows patterns
    const hasWinR =
      /press\s+(windows\s+key\s*\+\s*r|win\s*\+\s*r|⊞\s*\+\s*r)/gi.test(
        bodyText,
      );

    // macOS patterns
    const hasCmdSpace =
      /press\s+(command\s*\+\s*space|cmd\s*\+\s*space|⌘\s*\+\s*space)/gi.test(
        bodyText,
      );
    const hasOpenTerminalMac =
      /(open|launch)\s+(terminal|iterm|console)\.app/gi.test(bodyText);
    const hasCmdV = /(command|cmd)\s*\+\s*v/gi.test(bodyText);

    // Linux patterns
    const hasCtrlAltT = /(ctrl|control)\s*\+\s*alt\s*\+\s*t/gi.test(bodyText);
    const hasCtrlShiftV = /(ctrl|control)\s*\+\s*shift\s*\+\s*v/gi.test(
      bodyText,
    );
    const hasOpenTerminalLinux =
      /(open|launch)\s+(terminal|konsole|gnome-terminal|xterm)/gi.test(
        bodyText,
      );

    // Universal patterns
    const hasCtrlV = /(ctrl|control)\s*\+\s*v/gi.test(bodyText);
    const hasPasteCommand =
      /paste\s+(the\s+)?(following\s+)?(command|code)/gi.test(bodyText);
    const hasOpenTerminal =
      /open\s+(the\s+)?(terminal|command\s+prompt|powershell|run\s+dialog)/gi.test(
        bodyText,
      );

    // Check for any OS-specific keyboard combo
    const hasKeyboardCombo =
      hasWinR ||
      hasCmdSpace ||
      hasCtrlAltT ||
      hasCtrlV ||
      hasCmdV ||
      hasCtrlShiftV;
    const hasTerminalInstruction =
      hasOpenTerminalMac || hasOpenTerminalLinux || hasOpenTerminal;
    const hasPasteInstruction =
      hasPasteCommand || hasCtrlV || hasCmdV || hasCtrlShiftV;

    // Dangerous combination: "verify you are human" + (keyboard combo OR terminal instruction) + paste
    if (
      hasVerifyHuman &&
      (hasKeyboardCombo || hasTerminalInstruction) &&
      hasPasteInstruction &&
      clickfixMatches >= 2
    ) {
      console.log(
        "[BinHex.Ninja Security] ⚠️ Detected ClickFix attack pattern (combination - any OS)",
      );
      
      // Set match details for logging
      matchDetails = {
        detectionCategory: "clickfix_social_engineering",
        location: "body text",
        pattern: "verify_human + keyboard_combo + paste_instruction",
        matchedText: `Found ${clickfixMatches} ClickFix indicators`,
        context: bodyText.substring(0, 300),
        position: 0,
        contentLength: bodyText.length
      };
      
      detectionReasons.push(
        "Detected ClickFix social engineering attack pattern",
      );
      return true;
    }

    // High threshold for generic detection
    if (clickfixMatches >= 4) {
      console.log(
        "[BinHex.Ninja Security] ⚠️ Detected ClickFix attack pattern (high count)",
      );
      
      // Set match details for logging
      matchDetails = {
        detectionCategory: "clickfix_social_engineering",
        location: "body text",
        pattern: "high_threshold_clickfix_indicators",
        matchedText: `Found ${clickfixMatches} ClickFix indicators (threshold: 4+)`,
        context: bodyText.substring(0, 300),
        position: 0,
        contentLength: bodyText.length
      };
      
      detectionReasons.push(
        "Detected multiple ClickFix social engineering phrases",
      );
      return true;
    }

    // Check for keyboard instructions combined with base64
    const hasKeyboardInstructions =
      MALICIOUS_PATTERNS.keyboardInstructions.some((pattern) => {
        pattern.lastIndex = 0;
        return pattern.test(bodyText);
      });
    const hasBase64Strings = MALICIOUS_PATTERNS.base64Strings.some(
      (pattern) => {
        pattern.lastIndex = 0;
        return pattern.test(htmlContent);
      },
    );

    if (hasKeyboardInstructions && hasBase64Strings) {
      // Set match details for logging
      matchDetails = {
        detectionCategory: "keyboard_instructions_with_base64",
        location: "body text + HTML content",
        pattern: "keyboard_instructions + base64_strings",
        matchedText: "Keyboard instructions detected with base64 content",
        context: bodyText.substring(0, 300),
        position: 0,
        contentLength: bodyText.length + htmlContent.length
      };
      
      detectionReasons.push(
        "Detected keyboard instructions with suspicious base64 content",
      );
      return true;
    }

    return false;
  }

  // Block clipboard copy events containing base64 commands
  async function blockMaliciousClipboard(event) {
    const clipboardData = event.clipboardData || window.clipboardData;
    const copiedText = clipboardData.getData("text") || "";

    // Check if copied text contains malicious patterns
    for (const pattern of MALICIOUS_PATTERNS.base64Commands) {
      if (pattern.test(copiedText)) {
        event.preventDefault();
        event.stopPropagation();

        if (!warningShown) {
          detectionReasons.push("Blocked malicious command from clipboard");
          await showMalwareWarning("clipboard");
        }

        alert(
          "⚠️ SECURITY WARNING ⚠️\n\nThis page attempted to copy a potentially malicious command to your clipboard!\n\nThis is a known ClickFix scam technique used to infect computers.\n\nDO NOT paste this command into your terminal or Run dialog!",
        );
        return false;
      }
    }
  }

  // Override clipboard API to prevent malicious clipboard manipulation
  function protectClipboard() {
    // Monitor copy events
    document.addEventListener("copy", blockMaliciousClipboard, true);

    // Monitor clipboard write attempts
    const originalWriteText = navigator.clipboard.writeText;
    navigator.clipboard.writeText = async function (text) {
      for (const pattern of MALICIOUS_PATTERNS.base64Commands) {
        if (pattern.test(text)) {
          console.error(
            "[BinHex.Ninja Security] Blocked malicious clipboard write attempt",
          );
          if (!warningShown) {
            detectionReasons.push("Blocked clipboard.writeText() hijacking attempt");
            await showMalwareWarning("clipboard-api");
          }
          return Promise.reject(new Error("Malicious content blocked"));
        }
      }
      return originalWriteText.call(this, text);
    };

    // Monitor clipboard write with ClipboardItem
    if (navigator.clipboard.write) {
      const originalWrite = navigator.clipboard.write;
      navigator.clipboard.write = async function (data) {
        // Check clipboard items for malicious content
        for (const item of data) {
          const textBlob = await item.getType("text/plain");
          const text = await textBlob.text();

          for (const pattern of MALICIOUS_PATTERNS.base64Commands) {
            if (pattern.test(text)) {
              console.error(
                "[BinHex.Ninja Security] Blocked malicious clipboard write attempt",
              );
              if (!warningShown) {
                detectionReasons.push("Blocked clipboard.write() hijacking attempt");
                await showMalwareWarning("clipboard-api");
              }
              return Promise.reject(new Error("Malicious content blocked"));
            }
          }
        }
        return originalWrite.call(this, data);
      };
    }
  }

  // Helper function to make detection reasons more user-friendly
  function formatDetectionReason(reason) {
    const explanations = {
      "Detected base64-encoded command pattern": {
        icon: "🔐",
        title: "Base64-Encoded Commands Detected",
        description:
          "Found suspicious encoded commands that could execute malicious code when decoded",
      },
      "Detected dangerous PowerShell/system command": {
        icon: "⚠️",
        title: "Dangerous System Commands Found",
        description:
          "Detected PowerShell or system commands that can compromise your computer's security",
      },
      "Detected ClickFix social engineering attack pattern": {
        icon: "🎣",
        title: "ClickFix Attack Pattern Identified",
        description:
          "Found multiple indicators of a ClickFix social engineering attack attempting to trick you",
      },
      "Clipboard hijacking attempt": {
        icon: "📋",
        title: "Clipboard Hijacking Detected",
        description:
          "This page tried to secretly copy malicious commands to your clipboard",
      },
    };

    const match = explanations[reason];
    if (match) {
      return `
        <li class="detection-item">
          <span class="detection-icon">${match.icon}</span>
          <div class="detection-details">
            <strong>${match.title}</strong>
            <p>${match.description}</p>
          </div>
        </li>
      `;
    }
    // Fallback for generic reasons
    return `<li class="detection-item"><span class="detection-icon">🛡️</span><div class="detection-details"><strong>${reason}</strong></div></li>`;
  }

  // Show prominent malware warning overlay
  async function showMalwareWarning(triggerType = "content") {
    // Don't show overlay in iframes - only in top frame
    if (window !== window.top) {
      console.log(
        "[BinHex.Ninja Security] ⚠️ In iframe - skipping overlay display (detection still logged)",
      );
      return;
    }
    
    if (warningShown) return;
    warningShown = true;

    // Get theme FIRST before creating overlay
    const themeSettings = await chrome.storage.local.get(['theme']);
    const theme = themeSettings.theme || 'auto';
    let effectiveTheme = theme;
    
    if (theme === 'auto') {
      // Use system preference
      effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
    // Apply theme to document IMMEDIATELY
    document.documentElement.setAttribute('data-theme', effectiveTheme);
    console.log('[BinHex.Ninja Security] 🎨 Applying theme to warning overlay:', effectiveTheme);

    // Ensure we have at least one detection reason
    if (detectionReasons.length === 0) {
      console.warn("[BinHex.Ninja Security] ⚠️ Warning triggered with no detection reasons! Trigger type:", triggerType);
      detectionReasons.push("Suspicious activity detected on this page");
    }

    // Send detection to background script for logging
    console.log("[BinHex.Ninja Security] Sending malware detection to background...");
    console.log("[BinHex.Ninja Security] Detection reasons:", detectionReasons);
    console.log("[BinHex.Ninja Security] Trigger type:", triggerType);

    try {
      chrome.runtime.sendMessage({
        type: "malware_detected",
        url: window.location.href,
        reasons: detectionReasons,
        triggerType: triggerType,
        matchDetails: matchDetails // Include detailed match information
      }).then(response => {
        console.log("[BinHex.Ninja Security] ✅ Detection logged successfully");
      }).catch(err => {
        console.error("[BinHex.Ninja Security] Failed to send detection:", err);
      });
    } catch (e) {
      console.error("[BinHex.Ninja Security] Failed to log detection:", e);
    }

    const overlay = document.createElement("div");
    overlay.id = "clickfix-malware-warning";
    overlay.setAttribute("data-binhex-security", "true"); // Mark as our own element

    // Get the extension URL for the logo (SVG version)
    // Use light.svg for dark mode, dark.svg for light mode
    const logoSuffix = effectiveTheme === 'dark' ? 'light' : 'dark';
    const logoUrl =
      typeof chrome !== "undefined" && chrome.runtime
        ? chrome.runtime.getURL(`icons/${logoSuffix}.svg`)
        : typeof browser !== "undefined" && browser.runtime
          ? browser.runtime.getURL(`icons/${logoSuffix}.svg`)
          : "";

    overlay.innerHTML = `
      <div class="warning-backdrop">
        <div class="warning-content">
          <div class="warning-header">
            ${logoUrl ? `<img src="${logoUrl}" alt="BinHex.Ninja Security" class="sq-logo logo" />` : ""}
            <h1>Security Alert</h1>
            <p class="subtitle">Potential threat detected on this page</p>
          </div>

          <div class="warning-body">
            <div class="threat-badge">
              <svg class="shield-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 8V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="0.5" fill="currentColor" stroke="currentColor" stroke-width="1.5"/>
              </svg>
              <span>Threat Detected</span>
            </div>

            <p class="main-message">
              This website is attempting a <strong>ClickFix attack</strong> — a social engineering
              technique designed to trick you into executing malicious commands on your computer.
            </p>

            <div class="detection-section">
              <h3>🔍 What we found on this page</h3>
              <ul class="threat-list">
                ${detectionReasons.map((reason) => formatDetectionReason(reason)).join("")}
              </ul>
            </div>

            <div class="info-section">
              <h3>How this attack works</h3>
              <p>ClickFix attacks impersonate legitimate verification pages to deceive you into:</p>
              <ul class="info-list">
                <li>Opening your system's command interface (Terminal, PowerShell, or Run dialog)</li>
                <li>Pasting and executing encoded malicious commands</li>
                <li>Installing malware that compromises your system, passwords, and sensitive data</li>
              </ul>
            </div>

            <div class="action-section">
              <button id="close-page-btn" class="btn-primary">
                <span>Close This Page</span>
                <span class="btn-subtext">Recommended action</span>
              </button>
              <div class="secondary-actions">
                ${
                  window.location.protocol !== "file:"
                    ? `<button id="whitelist-domain-btn" class="btn-secondary">
                  <span>Trust This Domain</span>
                  <span class="btn-subtext">Trust all pages on ${window.location.hostname}</span>
                </button>`
                    : ""
                }
                <button id="whitelist-url-btn" class="btn-secondary">
                  <span>Trust This ${window.location.protocol === "file:" ? "File" : "URL"}</span>
                  <span class="btn-subtext">Trust only this exact ${
                    window.location.protocol === "file:" ? "file" : "URL"
                  }</span>
                </button>
                <button id="dismiss-warning-btn" class="btn-text">
                  I understand the risks, continue anyway
                </button>
              </div>
            </div>

            <div class="footer-tips">
              <h4>Security best practices</h4>
              <ul class="tips-list">
                <li>Never execute commands from untrusted websites</li>
                <li>Legitimate sites don't require terminal access</li>
                <li>Be suspicious of base64-encoded content</li>
                <li>When in doubt, close the page</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    `;

    document.documentElement.appendChild(overlay);

    // Add event listeners
    document.getElementById("close-page-btn").addEventListener("click", () => {
      window.location.href = "about:blank";
      window.close();
    });

    // Only add domain button listener if it exists (not for file:// URLs)
    const domainBtn = document.getElementById("whitelist-domain-btn");
    if (domainBtn) {
      domainBtn.addEventListener("click", async () => {
        const currentHostname = window.location.hostname;
        try {
          const settings = await chrome.storage.local.get(["customWhitelist", "domainToggles"]);
          const customWhitelist = settings.customWhitelist || [];
          const domainToggles = settings.domainToggles || {};

          console.log("[BinHex.Ninja Security] 🔍 Current hostname:", currentHostname);
          console.log("[BinHex.Ninja Security] 🔍 Current customWhitelist:", customWhitelist);
          
          if (!customWhitelist.includes(currentHostname)) {
            customWhitelist.push(currentHostname);
            domainToggles[currentHostname] = true; // Enable by default
            await chrome.storage.local.set({ customWhitelist, domainToggles });
            console.log(
              `[BinHex.Ninja Security] ✅ Added ${currentHostname} to custom whitelist`,
            );
            console.log("[BinHex.Ninja Security] ✅ New customWhitelist:", customWhitelist);
            console.log("[BinHex.Ninja Security] ✅ New domainToggles:", domainToggles);

            // Update caches and notify clipboard protection
            isWhitelisted = true;
            window.postMessage({
              type: "BINHEX_WHITELIST_STATUS",
              isWhitelisted: true
            }, "*");
            console.log("[BinHex.Ninja Security] ✅ Sent whitelist status after domain trust");

            // Show confirmation and reload
            const btn = document.getElementById("whitelist-domain-btn");
            btn.innerHTML =
              '<span>✓ Added to Whitelist</span><span class="btn-subtext">Reloading page...</span>';
            btn.disabled = true;

            setTimeout(() => {
              window.location.reload();
            }, 1500);
          }
        } catch (error) {
          console.error(
            "[BinHex.Ninja Security] Failed to add to whitelist:",
            error,
          );
          const btn = document.getElementById("whitelist-domain-btn");
          btn.innerHTML =
            '<span>❌ Failed</span><span class="btn-subtext">Please try again</span>';
        }
      });
    }

    document
      .getElementById("whitelist-url-btn")
      .addEventListener("click", async () => {
        const currentUrl = window.location.href;
        try {
          // Only block the extension's own UI from being trusted
          const blockedUrls = ['about:blank', 'about:srcdoc'];
          const isBlocked = blockedUrls.some(url => currentUrl === url || currentUrl.startsWith(url));
          
          if (isBlocked) {
            const btn = document.getElementById("whitelist-url-btn");
            btn.innerHTML =
              '<span>❌ Cannot Trust</span><span class="btn-subtext">Extension UI cannot be trusted</span>';
            setTimeout(() => {
              const subtext = window.location.protocol === "file:" ? "file" : "URL";
              btn.innerHTML =
                `<span>Trust This ${window.location.protocol === "file:" ? "File" : "URL"}</span><span class="btn-subtext">Trust only this exact ${subtext}</span>`;
            }, 2000);
            return;
          }
          
          console.log("[BinHex.Ninja Security] 🔍 Adding URL to trusted list:", currentUrl);
          const settings = await chrome.storage.local.get(["trustedUrls"]);
          const trustedUrls = settings.trustedUrls || [];
          console.log("[BinHex.Ninja Security] 🔍 Current trustedUrls:", trustedUrls);

          if (!trustedUrls.includes(currentUrl)) {
            trustedUrls.push(currentUrl);
            const saveResult = await chrome.storage.local.set({ trustedUrls });
            console.log("[BinHex.Ninja Security] ✅ Save result:", saveResult);
            console.log(`[BinHex.Ninja Security] ✅ Added ${currentUrl} to trusted URLs`);
            console.log("[BinHex.Ninja Security] ✅ New trustedUrls:", trustedUrls);
            
            // Verify it was saved
            const verification = await chrome.storage.local.get(["trustedUrls"]);
            console.log("[BinHex.Ninja Security] ✅ Verification - trustedUrls in storage:", verification.trustedUrls);

            // Update cache and notify clipboard protection
            isTrustedUrl = true;
            window.postMessage({
              type: "BINHEX_WHITELIST_STATUS",
              isWhitelisted: true
            }, "*");

            // Show confirmation and reload
            const btn = document.getElementById("whitelist-url-btn");
            btn.innerHTML =
              '<span>✓ Added to Trusted URLs</span><span class="btn-subtext">Reloading page...</span>';
            btn.disabled = true;

            setTimeout(() => {
              window.location.reload();
            }, 1500);
          } else {
            console.log("[BinHex.Ninja Security] ℹ️ URL already in trusted list");
            
            // Update cache and notify clipboard protection
            isTrustedUrl = true;
            window.postMessage({
              type: "BINHEX_WHITELIST_STATUS",
              isWhitelisted: true
            }, "*");
            
            // Show confirmation and reload
            const btn = document.getElementById("whitelist-url-btn");
            btn.innerHTML =
              '<span>✓ Already Trusted</span><span class="btn-subtext">Reloading page...</span>';
            btn.disabled = true;
            
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          }
        } catch (error) {
          console.error(
            "[BinHex.Ninja Security] Failed to add URL to trusted list:",
            error,
          );
          const btn = document.getElementById("whitelist-url-btn");
          btn.innerHTML =
            '<span>❌ Failed</span><span class="btn-subtext">Please try again</span>';
        }
      });

    document
      .getElementById("dismiss-warning-btn")
      .addEventListener("click", () => {
        overlay.style.display = "none";
      });
  }

  // Monitor DOM mutations for dynamically loaded content
  function monitorDynamicContent() {
    // Throttle mechanism to prevent excessive scanning
    let scanPending = false;
    let lastScanTime = 0;
    const SCAN_COOLDOWN = 500; // milliseconds between scans

    function throttledScan(reason) {
      const now = Date.now();
      if (scanPending || now - lastScanTime < SCAN_COOLDOWN) {
        return; // Skip if scan is pending or in cooldown
      }

      scanPending = true;
      setTimeout(async () => {
        if (!warningShown) {
          const hasThreat = await checkForMaliciousContent();
          if (hasThreat) {
            await showMalwareWarning(reason);
          }
        }
        lastScanTime = Date.now();
        scanPending = false;
      }, 50);
    }

    const observer = new MutationObserver(async (mutations) => {
      // Skip if trusted URL
      if (await checkTrustedUrl()) {
        return;
      }
      
      // Skip if whitelisted domain
      const whitelisted = await isWhitelistedDomain();
      if (whitelisted) {
        return;
      }

      // Check if any iframes were added or modified
      let iframeChanged = false;
      mutations.forEach((mutation) => {
        // Check for added nodes
        mutation.addedNodes.forEach((node) => {
          if (node.nodeName === "IFRAME") {
            iframeChanged = true;
            console.log(
              "[BinHex.Ninja Security] New iframe detected, monitoring for content",
            );

            // Watch for iframe load events
            node.addEventListener("load", () => {
              console.log(
                "[BinHex.Ninja Security] Iframe loaded, scanning content",
              );
              throttledScan("iframe-loaded");
            });

            // Check after delays (reduced from 4 to 2 checks)
            [500, 2000].forEach((delay) => {
              setTimeout(async () => {
                if (!warningShown) {
                  const hasThreat = await checkForMaliciousContent();
                  if (hasThreat) {
                    await showMalwareWarning("iframe-delayed-scan");
                  }
                }
              }, delay);
            });
          }
        });

        // Check for attribute changes (like srcdoc being set)
        if (
          mutation.type === "attributes" &&
          mutation.target.nodeName === "IFRAME"
        ) {
          iframeChanged = true;
          console.log(
            "[BinHex.Ninja Security] Iframe attribute changed:",
            mutation.attributeName,
          );
          throttledScan("iframe-attribute-change");
        }
      });

      // Regular check for other content changes (throttled)
      if (!iframeChanged && !warningShown) {
        throttledScan("dynamic-content");
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: ["src", "srcdoc"],
    });
  }

  // Listen for clipboard block messages from inject-protection.js
  window.addEventListener("message", (event) => {
    // Only accept messages from same origin
    if (event.source !== window) return;

    if (event.data.type === "CLICKFIX_CLIPBOARD_BLOCKED") {
      console.log("[BinHex.Ninja Security] Received clipboard block message");

      // Send to background script for logging
      try {
        chrome.runtime.sendMessage({
          type: "malware_detected",
          url: event.data.url,
          reasons: ["Clipboard hijacking attempt"],
          detectedCommand: event.data.content,
          matchDetails: event.data.matchDetails, // Include match details from clipboard protection
          triggerType: "clipboard"
        }).then(response => {
          console.log("[BinHex.Ninja Security] ✅ Clipboard block logged successfully");
        }).catch(err => {
          console.error("[BinHex.Ninja Security] Failed to log clipboard block:", err);
        });
      } catch (e) {
        console.error("[BinHex.Ninja Security] Failed to log clipboard block:", e);
      }
    }
    
    // Handle iframe clipboard alert request - forward to background script
    if (event.data.type === "CLICKFIX_SHOW_CLIPBOARD_ALERT_IN_TOP") {
      console.log("[BinHex.Ninja Security] Iframe requesting to show clipboard alert in top frame");
      
      // Forward to background script to relay to top frame
      try {
        chrome.runtime.sendMessage({
          type: "show_clipboard_alert_in_top_frame",
          message: event.data.message,
          iframeUrl: event.data.iframeUrl
        }).then(response => {
          console.log("[BinHex.Ninja Security] ✅ Clipboard alert request forwarded");
        }).catch(err => {
          console.error("[BinHex.Ninja Security] Failed to forward clipboard alert:", err);
        });
      } catch (e) {
        console.error("[BinHex.Ninja Security] Failed to forward clipboard alert:", e);
      }
    }
  });

  // Listen for messages from background script to display clipboard alert
  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === "display_clipboard_alert") {
      console.log("[BinHex.Ninja Security] Received request to display clipboard alert from background");
      
      // Forward to inject-protection.js in MAIN world
      window.postMessage({
        type: "CLICKFIX_DISPLAY_CLIPBOARD_ALERT",
        message: message.message,
        iframeUrl: message.iframeUrl
      }, "*");
      
      sendResponse({ success: true });
      return true;
    }
  });

  // Initialize protection
  async function init() {
    console.log("[BinHex.Ninja Security] 🔍 Init: Starting initialization...");
    console.log("[BinHex.Ninja Security] 🔍 Init: Current URL:", window.location.href);
    console.log("[BinHex.Ninja Security] 🔍 Init: Current isTrustedUrl cache:", isTrustedUrl);
    console.log("[BinHex.Ninja Security] 🔍 Init: Checking if URL is trusted...");
    const isTrusted = await checkTrustedUrl();
    console.log("[BinHex.Ninja Security] 🔍 Init: Trusted URL result:", isTrusted);
    console.log("[BinHex.Ninja Security] 🔍 Init: Updated isTrustedUrl cache:", isTrustedUrl);
    
    // Also check if domain is whitelisted (check BOTH built-in AND custom)
    const builtInWhitelisted = await isWhitelistedDomain();
    console.log("[BinHex.Ninja Security] 🔍 Init: Built-in domain whitelisted result:", builtInWhitelisted);
    
    const customWhitelisted = await checkUserWhitelist();
    console.log("[BinHex.Ninja Security] 🔍 Init: Custom domain whitelisted result:", customWhitelisted);
    
    const domainWhitelisted = builtInWhitelisted || customWhitelisted;
    console.log("[BinHex.Ninja Security] 🔍 Init: Overall domain whitelisted result:", domainWhitelisted);
    
    // URL trust OR domain whitelist should disable ALL protection including clipboard
    const shouldSkipProtection = isTrusted || domainWhitelisted;
    console.log("[BinHex.Ninja Security] 🔍 Init: Should skip protection?", shouldSkipProtection, "(isTrusted:", isTrusted, "domainWhitelisted:", domainWhitelisted, ")");
    
    // Send whitelist status to inject-protection.js (MAIN world)
    console.log("[BinHex.Ninja Security] 📤 Sending BINHEX_WHITELIST_STATUS message to inject-protection.js...");
    window.postMessage({
      type: "BINHEX_WHITELIST_STATUS",
      isWhitelisted: shouldSkipProtection
    }, "*");
    
    // Send theme and logo information to inject-protection.js (MAIN world)
    chrome.storage.local.get(['theme'], (result) => {
      const theme = result.theme || 'auto';
      let effectiveTheme = theme;
      
      if (theme === 'auto') {
        effectiveTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      
      const isDark = effectiveTheme === 'dark';
      const logoSuffix = isDark ? 'light' : 'dark';
      const logoUrl = chrome.runtime.getURL(`icons/${logoSuffix}.svg`);
      
      // Fetch the SVG and convert to data URL
      fetch(logoUrl)
        .then(response => response.text())
        .then(svgText => {
          const dataUrl = 'data:image/svg+xml;base64,' + btoa(svgText);
          
          window.postMessage({
            type: "BINHEX_THEME_DATA",
            theme: effectiveTheme,
            logoDataUrl: dataUrl
          }, "*");
          
          console.log("[BinHex.Ninja Security] 📤 Sent theme data to inject-protection.js:", effectiveTheme);
        })
        .catch(err => {
          console.warn("[BinHex.Ninja Security] Could not load logo:", err);
          // Send theme without logo
          window.postMessage({
            type: "BINHEX_THEME_DATA",
            theme: effectiveTheme,
            logoDataUrl: null
          }, "*");
        });
    });
    console.log("[BinHex.Ninja Security] ✅ Sent whitelist status to clipboard protection:", shouldSkipProtection);
    
    if (shouldSkipProtection) {
      console.log("[BinHex.Ninja Security] ✅ Init: URL/Domain is trusted, skipping ALL protection for this session");
      return; // Skip ALL initialization if URL is trusted or domain is whitelisted
    }
    
    // If we're in an iframe, check if the TOP frame is trusted
    if (window !== window.top) {
      try {
        const topUrl = window.top.location.href;
        console.log("[BinHex.Ninja Security] 🔍 Init: We're in an iframe, checking if top frame is trusted...");
        console.log("[BinHex.Ninja Security] 🔍 Init: Top frame URL:", topUrl);
        
        const settings = await chrome.storage.local.get(["trustedUrls"]);
        const trustedUrls = settings.trustedUrls || [];
        
        if (trustedUrls.includes(topUrl)) {
          console.log("[BinHex.Ninja Security] ✅ Init: Top frame is trusted, skipping iframe protection");
          
          // Also tell clipboard protection to skip
          window.postMessage({
            type: "BINHEX_WHITELIST_STATUS",
            isWhitelisted: true
          }, "*");
          
          return; // Skip protection if parent is trusted
        }
      } catch (e) {
        // Cross-origin iframe, can't access top URL
        console.log("[BinHex.Ninja Security] ℹ️ Init: Can't check top frame (cross-origin)");
      }
    }
    
    console.log("[BinHex.Ninja Security] 🔍 Init: URL not trusted, starting protection...");
    
    // Early check
    if (document.body) {
      const hasThreat = await checkForMaliciousContent();
      if (hasThreat) {
        await showMalwareWarning("initial-scan");
      }
    }

    // Protect clipboard
    protectClipboard();

    // Monitor dynamic content
    if (document.body) {
      monitorDynamicContent();
    } else {
      document.addEventListener("DOMContentLoaded", async () => {
        const hasThreat = await checkForMaliciousContent();
        if (hasThreat) {
          await showMalwareWarning("dom-ready");
        }
        monitorDynamicContent();
      });
    }

    // Final check after page load
    window.addEventListener("load", async () => {
      if (!warningShown) {
        const hasThreat = await checkForMaliciousContent();
        if (hasThreat) {
          await showMalwareWarning("page-load");
        }
      }
    });

    // Recurring scans to catch delayed malicious content
    // Skip recurring scans on whitelisted domains/URLs for performance
    const trustedUrl = await checkTrustedUrl();
    const whitelisted = await isWhitelistedDomain();
    if (!trustedUrl && !whitelisted) {
      console.log(
        "[BinHex.Ninja Security] Setting up recurring scans for delayed threats",
      );
      // Reduced from 7 scans to 3 for better performance
      const scanIntervals = [1000, 3000, 8000];
      scanIntervals.forEach((delay) => {
        setTimeout(async () => {
          if (!warningShown) {
            console.log(`[BinHex.Ninja Security] Recurring scan at ${delay}ms`);
            const hasThreat = await checkForMaliciousContent();
            if (hasThreat) {
              await showMalwareWarning(`recurring-scan-${delay}ms`);
            }
          }
        }, delay);
      });
    } else {
      console.log(
        "[BinHex.Ninja Security] ✅ Whitelisted domain, skipping recurring scans for performance",
      );
    }
  }

  // Start protection
  init();
})();
