// wrap everything in an anonymous function, so the page itself does not have
// access to oldWrite and oldWriteText, which could be used to bypass this extension
(function () {
  let oldWriteText = null;
  let oldWrite = null;
  let oldExecCommand = null;

  function handleClipboardEvent(event) {
    try {
      // detect normal copy issued with keyboard, which has a short stack trace
      if (Error().stack.split('\n').length < 3) return;
      const textToCopy = event.target.value || event.target.textContent;
      const result = testForClickFix(textToCopy);
      // cancel copy event in case of ClickFix-like keyword
      if (result) {
        event.preventDefault();
      }
    } catch (e) {
      console.log("error: ", e);
    }
  }

  document.addEventListener("copy", handleClipboardEvent);
  document.addEventListener("cut", handleClipboardEvent);

  // only hook these functions if it is possible to do so, as this object is not available in insecure contexts
  if (navigator.clipboard) {
    try {
      oldWriteText = navigator.clipboard.writeText.bind(navigator.clipboard);
      navigator.clipboard.writeText = async function (textToCopy) {
        // if we detect any kind of ClickFix-like keyword, do not copy 
        // to the clipboard, simply return
        if (testForClickFix(textToCopy)) {
          return;
        }
        return oldWriteText(textToCopy);
      }
    } catch (e) {
      console.log("error: ", e);
    }

    try {
      oldWrite = navigator.clipboard.write.bind(navigator.clipboard);
      navigator.clipboard.write = async function (clipboardItems) {
        for (clipboardItem of clipboardItems) {
          let blob = await clipboardItem.getType("text/plain");
          const textToCopy = await blob.text();
          // if we detect any kind of ClickFix-like keyword, do not copy 
          // to the clipboard, simply return
          if (testForClickFix(textToCopy)) {
            return;
          }
        }
        // only do this if there is no clickfix detected
        return oldWrite(clipboardItems);
      }
    } catch (e) {
      console.log("error: ", e);
    }
  }

  // get settings and update hooks accordingly
  chrome.runtime.sendMessage("kicmpbbbloliabpbfkfcmflbmlcakeck", { "operation": "getVars" }, {})
    .then(setVars)
    .then(theRest);

  // inject variables as immutable objects into page so they cannot be tampered with
  function setVars(settings) {
    try {
      Object.keys(settings).map(key => {
        let value = settings[key]
        let firstLetter = key.charAt(0).toUpperCase();
        let restOfWord = key.slice(1);
        // use clickFixBlockVariable as format
        let objectName = `clickFixBlock${firstLetter}${restOfWord}`;
        Object.defineProperty(window, objectName, {
          get: () => value,
          set: () => { throw TypeError('Assignment to constant variable.') },
        });
      });
      return Promise.resolve({});
    } catch (e) {
      console.log("error: ", e);
    }
    return true;
  }

  function removeHooks() {
    try {
      if (oldExecCommand) document.execCommand = oldExecCommand;
      if (oldWrite) navigator.clipboard.write = oldWrite;
      if (oldWriteText) navigator.clipboard.writeText = oldWriteText;
    } catch (e) {
      console.log("error: ", e)
    }
  }

  // the settings are now available, so adjust the behaviour of the extension accordingly
  function theRest() {
    // remove hooks if the extension is disabled or the domain is on the allowlist
    if (
      (typeof (clickFixBlockEnabled) !== "undefined") && (clickFixBlockEnabled === false) ||
      (typeof (clickFixBlockAllowlist) !== "undefined") && (clickFixBlockAllowlist === true) && (typeof (clickFixBlockBlockall !== "undefined")) && (clickFixBlockBlockall !== false)
    ) {
      removeHooks();
      return Promise.resolve({});
    }
    // if the user wishes to block all copy-to-clipboard functions, we need to hook execCommand
    if ((typeof (clickFixBlockBlockall) !== "undefined") && (clickFixBlockBlockall === true)) {
      try {
        oldExecCommand = document.execCommand.bind(document);

        document.execCommand = function (commandId) {
          // only consider commands that influence the clipboard contents
          if (!(commandId.toLowerCase() === "copy") && !(commandId.toLowerCase() === "cut")) {
            return oldExecCommand(commandId);
          }
          const focusedElement = document.activeElement;
          let textToCopy = focusedElement.value;

          if (typeof (textToCopy) === "undefined") {
            textToCopy = window.getSelection().toString()
          }
          const result = testForClickFix(textToCopy);
          return result;
        }
        return Promise.resolve({});
      } catch (e) {
        console.log("error: ", e);
      }
    }
    return true;
  }

  function testForClickFix(text) {
    if (typeof (text) === "undefined") {
      return false;
    }
    if (
      (typeof (clickFixBlockEnabled) !== "undefined") &&
      (typeof (clickFixBlockBlockall) !== "undefined") &&
      (typeof (clickFixBlockAllowlist) !== "undefined")
    ) {
      try {
        if (!clickFixBlockEnabled || clickFixBlockAllowlist) {
          return false;
        }
      } catch (e) {
        console.log("error: ", e);
      }
      try {
        if (clickFixBlockBlockall) {
          showModal();
          return true;
        }
      } catch (e) {
        console.log("error: ", e);
      }
    }
    const badStuff = new RegExp("bash|zsh|curl|wmic|cmd|msiexec|powershell|pwsh|iex|process call create|mshta|not a robot|captcha|m a human", "i");
    const matches = text.replaceAll("'", "").replaceAll('"', '').match(badStuff);
    const result = matches === null ? false : true;
    if (result) {
      showModal();
    }
    return result;
  }

  const modalStyles = `
    .clickfix-modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      z-index: 2147483647;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      backdrop-filter: blur(4px);
    }

    .clickfix-modal-container {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 500px;
      width: 90%;
      max-height: 85vh;
      overflow-y: auto;
      animation: clickfix-modal-fadeIn 0.3s ease-out;
      display: flex;
      flex-direction: column;
    }

    @keyframes clickfix-modal-fadeIn {
      from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    .clickfix-modal-header {
      background: #dc3545;
      color: white;
      padding: 20px 24px 16px;
      border-radius: 12px 12px 0 0;
      display: flex;
      align-items: center;
      gap: 12px;
      position: relative;
    }

    .clickfix-modal-icon {
      width: 24px;
      height: 24px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: bold;
      background-image: url('data:image/svg+xml,<%3Fxml version="1.0" encoding="UTF-8"%3F><svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="375" height="375"><path d="M0 0 C1.1674601 -0.01052147 1.1674601 -0.01052147 2.35850525 -0.02125549 C4.85885461 -0.03867514 7.35896718 -0.04510844 9.859375 -0.046875 C10.71318756 -0.04754974 11.56700012 -0.04822449 12.44668579 -0.04891968 C25.06045283 -0.03306262 37.2710364 0.34582553 49.609375 3.203125 C50.79104248 3.46851074 50.79104248 3.46851074 51.99658203 3.73925781 C81.35804817 10.48674299 108.73529884 23.44089313 131.609375 43.203125 C132.40085937 43.88503906 133.19234375 44.56695312 134.0078125 45.26953125 C136.5827245 47.53984009 139.10301147 49.85737791 141.609375 52.203125 C142.54652344 53.07453125 143.48367188 53.9459375 144.44921875 54.84375 C170.55266609 79.68461549 188.06792713 114.17256856 195.609375 149.203125 C195.8571167 150.35047119 195.8571167 150.35047119 196.10986328 151.52099609 C204.08173874 190.67139583 198.35993244 233.59101958 180.609375 269.203125 C180.23441895 269.97849609 179.85946289 270.75386719 179.47314453 271.55273438 C174.03473915 282.76024367 167.78719725 292.76408299 159.99072266 302.47949219 C158.75522638 304.021133 157.54570915 305.58351063 156.33984375 307.1484375 C145.53283614 320.90281082 132.80426615 332.07507946 118.609375 342.203125 C117.71347656 342.84507812 116.81757813 343.48703125 115.89453125 344.1484375 C101.14951168 354.04217835 84.65217994 362.20190383 67.609375 367.203125 C66.49731689 367.53666992 66.49731689 367.53666992 65.36279297 367.87695312 C59.82054172 369.51478111 54.24745527 370.93533632 48.609375 372.203125 C47.87541504 372.36973633 47.14145508 372.53634766 46.38525391 372.70800781 C0.6706189 382.53670521 -50.1284359 371.93529755 -89.33618164 347.25878906 C-100.01690218 340.31057174 -109.75091223 332.36349628 -118.95849609 323.56787109 C-120.33761553 322.25364033 -121.73297713 320.95651839 -123.12890625 319.66015625 C-151.52288629 292.49891051 -169.88570418 253.9069529 -175.390625 215.203125 C-175.57367188 213.93855469 -175.75671875 212.67398438 -175.9453125 211.37109375 C-176.75364537 203.74920859 -176.63502788 196.04730123 -176.640625 188.390625 C-176.64129974 187.56298645 -176.64197449 186.7353479 -176.64266968 185.88262939 C-176.62653067 173.42361742 -176.17239092 161.39525456 -173.390625 149.203125 C-173.14119141 148.0591626 -173.14119141 148.0591626 -172.88671875 146.89208984 C-165.5441796 113.96624069 -149.77709643 82.74589389 -125.90136719 58.72851562 C-124.31754279 57.12933417 -122.76375132 55.50427456 -121.2109375 53.875 C-90.58836684 22.06736675 -44.49302384 0.26923228 0 0 Z " fill="%2318243E" transform="translate(176.390625,-0.203125)"/><path d="M0 0 C16.53917167 14.26745965 26.13804332 32.39370956 29.9375 53.6875 C30.18935059 55.03968628 30.18935059 55.03968628 30.44628906 56.41918945 C32.15804834 67.15095889 31.5625 76.92004261 31.5625 88.125 C-9.3575 88.125 -50.2775 88.125 -92.4375 88.125 C-87.52602108 106.98443696 -87.52602108 106.98443696 -74.4375 120.125 C-67.20463785 123.92397944 -61.74683838 125.20174018 -53.4375 126.125 C-53.4375 139.325 -53.4375 152.525 -53.4375 166.125 C-75.58023099 164.74107931 -93.92599914 160.27502623 -110.4375 145.125 C-111.19289062 144.46113281 -111.94828125 143.79726563 -112.7265625 143.11328125 C-128.16615151 128.76136088 -138.31957753 106.05817098 -139.37890625 85 C-139.40791016 83.576875 -139.40791016 83.576875 -139.4375 82.125 C-139.51291016 80.2378125 -139.51291016 80.2378125 -139.58984375 78.3125 C-140.33489654 52.50411266 -132.8804458 27.15922856 -115.16796875 7.91796875 C-85.2269091 -22.70843705 -34.00554459 -25.89653011 0 0 Z " fill="%23FEFEFE" transform="translate(241.4375,112.875)"/><path d="M0 0 C5.58455335 3.9519923 9.24852136 8.96656025 11.19140625 15.55078125 C12.25532963 24.94220368 10.4995057 31.58863207 5.31640625 39.36328125 C-0.18249763 45.0239176 -6.2981339 47.51259675 -14.12109375 47.92578125 C-21.6340558 47.74598387 -28.31589823 44.5716569 -33.80859375 39.55078125 C-39.00537099 32.06561838 -41.40018361 24.59835743 -39.80859375 15.55078125 C-37.41926585 7.59773266 -32.78820636 2.8411166 -25.80859375 -1.44921875 C-17.64343919 -5.62251997 -7.75715022 -4.42020599 0 0 Z " fill="%23F29419" transform="translate(261.80859375,235.44921875)"/><path d="M0 0 C8.71553378 6.36904392 13.55045904 14.08703911 16.3984375 24.45703125 C16.3984375 25.44703125 16.3984375 26.43703125 16.3984375 27.45703125 C-9.0115625 27.45703125 -34.4215625 27.45703125 -60.6015625 27.45703125 C-59.14058112 17.2301616 -54.8031163 8.91494763 -46.6015625 2.45703125 C-32.47427281 -7.38649963 -15.23256086 -9.03432664 0 0 Z " fill="%231A2641" transform="translate(210.6015625,140.54296875)"/></svg>');
      background-size: 18px 18px;
      background-repeat: no-repeat;
      background-position: center;
    }

    .clickfix-modal-title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
    }

    .clickfix-modal-body {
      padding: 20px 24px;
      line-height: 1.5;
      color: #333;
      font-size: 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .clickfix-modal-message {
      margin: 0;
      word-wrap: break-word;
    }

    .clickfix-modal-actions {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px 20px;
      gap: 16px;
    }

    .clickfix-modal-button {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      min-width: 140px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .clickfix-modal-button-primary {
      background: #dc3545;
      color: white;
    }

    .clickfix-modal-button-primary:hover {
      background: #c82333;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }

    .clickfix-modal-button-primary:active {
      transform: translateY(0);
      box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .clickfix-modal-continue-link {
      color: #6c757d;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      padding: 8px 12px;
      border-radius: 6px;
      transition: all 0.2s ease;
      cursor: pointer;
      border: 1px solid transparent;
    }

    .clickfix-modal-continue-link:hover {
      color: #495057;
      background-color: #f8f9fa;
      border-color: #dee2e6;
      text-decoration: none;
    }

    .clickfix-modal-continue-link:active {
      background-color: #e9ecef;
    }

    .clickfix-modal-close {
      position: absolute;
      top: 12px;
      right: 12px;
      background: none;
      border: none;
      font-size: 20px;
      color: rgba(255, 255, 255, 0.8);
      cursor: pointer;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s ease;
    }

    .clickfix-modal-close:hover {
      background: rgba(255, 255, 255, 0.1);
      color: white;
    }

    @media (prefers-color-scheme: dark) {
      .clickfix-modal-container {
        background: #2d3748;
        color: #e2e8f0;
      }
      
      .clickfix-modal-body {
        color: #e2e8f0;
      }
    }

    @media (max-width: 600px) {
      .clickfix-modal-container {
        width: 95%;
        margin: 20px;
      }
      
      .clickfix-modal-header {
        padding: 16px 20px 12px;
      }
      
      .clickfix-modal-body {
        padding: 16px 20px;
        font-size: 15px;
      }
      
      .clickfix-modal-actions {
        padding: 12px 20px 16px;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }
      
      .clickfix-modal-button {
        width: 100%;
        order: 1;
      }

      .clickfix-modal-continue-link {
        order: 2;
        text-align: center;
        align-self: center;
      }
    }
  `;

  function createModalHTML(message, title, safetyText, continueText) {
    return `
      <div class="clickfix-modal-overlay" id="clickfix-modal-overlay">
        <div class="clickfix-modal-container">
          <div class="clickfix-modal-header">
            <div class="clickfix-modal-icon"></div>
            <span class="clickfix-modal-title">${title}</span>
            <button class="clickfix-modal-close" id="clickfix-modal-close" aria-label="Close">&times;</button>
          </div>
          <div class="clickfix-modal-body">
            <p class="clickfix-modal-message">${message}</p>
          </div>
          <div class="clickfix-modal-actions">
            <button class="clickfix-modal-button clickfix-modal-button-primary" id="clickfix-modal-safety">${safetyText}</button>
            <a href="#" class="clickfix-modal-continue-link" id="clickfix-modal-continue">${continueText}</a>
          </div>
        </div>
      </div>
    `;
  }

  function showModal() {
    let message;
    let title;
    let safetyText;
    let continueText;

    try {
      message = clickFixBlockMessage;
    } catch {
      message = "Potential ClickFix / Fake CAPTCHA detected: the page tried to copy to the clipboard using native JavaScript functions. The contents of the text to copy contained one or more suspicious keywords that possibly indicate malicious intent. If the page is asking you to run commands, navigate away now!";
    }
    try {
      title = clickFixBlockTitle;
    } catch {
      title = "ClickFix Block - Security Warning";
    }
    try {
      safetyText = clickFixBlockSafetyBtn;
    } catch {
      safetyText = "Get me to safety";
    }
    try {
      continueText = clickFixBlockContinueBtn;
    } catch {
      continueText = "Dismiss";
    }
    if (document.getElementById('clickfix-modal-styles')) {
      document.getElementById('clickfix-modal-styles').remove();
    }

    const styleSheet = document.createElement('style');
    styleSheet.id = 'clickfix-modal-styles';
    styleSheet.textContent = modalStyles;
    document.head.appendChild(styleSheet);

    const existingModal = document.getElementById('clickfix-modal-overlay');
    if (existingModal) {
      existingModal.remove();
    }
    const modalHTML = createModalHTML(message, title, safetyText, continueText);
    try {
      document.body.insertAdjacentHTML('beforeend', modalHTML);
    } catch {
      window.alert(`${title}\n\n${clickFixBlockMessage}`);
      return;
    }

    const modal = document.getElementById('clickfix-modal-overlay');
    const closeBtn = document.getElementById('clickfix-modal-close');
    const safetyBtn = document.getElementById('clickfix-modal-safety');
    const continueLink = document.getElementById('clickfix-modal-continue');
    function closeModal() {
      modal.style.animation = 'clickfix-modal-fadeOut 0.2s ease-in forwards';
      setTimeout(() => {
        if (modal.parentNode) {
          modal.remove();
        }
      }, 200);
    }
    if (!document.getElementById('clickfix-modal-fadeOut-styles')) {
      const fadeOutStyle = document.createElement('style');
      fadeOutStyle.id = 'clickfix-modal-fadeOut-styles';
      fadeOutStyle.textContent = `
        @keyframes clickfix-modal-fadeOut {
          from {
            opacity: 1;
            transform: scale(1) translateY(0);
          }
          to {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
          }
        }
      `;
      document.head.appendChild(fadeOutStyle);
    }
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        closeModal();
      }
    };

    safetyBtn.focus();
    document.body.style.overflow = 'hidden';
    const originalCloseModal = closeModal;
    closeModal = function () {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', handleEscape);
      originalCloseModal();
    };

    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeModal();
    });
    continueLink.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeModal();
    });

    safetyBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeModal();

      // navigate away from the page to our domain
      window.location.href = "https://www.eye.security/blog/clickfix-block-fake-captcha-attacks?cfb=1";

    });
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
      }
    });

    document.addEventListener('keydown', handleEscape);

    return modal;
  }
})();
