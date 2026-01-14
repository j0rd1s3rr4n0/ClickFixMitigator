// BinHex.Ninja Security - Onboarding Script
console.log("[Onboarding] Script loaded");

document.addEventListener('DOMContentLoaded', () => {
  console.log("[Onboarding] DOM loaded, initializing...");

  // Setup theme toggle
  const themeToggle = document.getElementById('theme-toggle-btn');
  if (themeToggle && typeof ThemeManager !== 'undefined') {
    ThemeManager.setupToggle(themeToggle);
  }

  // Handle radio button selection UI
  const radioOptions = document.querySelectorAll('.radio-option');
  radioOptions.forEach(option => {
    option.addEventListener('click', () => {
      radioOptions.forEach(opt => opt.classList.remove('selected'));
      option.classList.add('selected');
      const radio = option.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    });
  });

  const continueBtn = document.getElementById('continueBtn');
  
  if (continueBtn) {
    console.log("[Onboarding] Continue button found");
    
    continueBtn.addEventListener('click', async () => {
      console.log("[Onboarding] Continue button clicked");
      
      try {
        // Get selected data collection mode
        const selectedRadio = document.querySelector('input[name="dataCollection"]:checked');
        const dataCollectionMode = selectedRadio ? selectedRadio.value : 'none';
        
        console.log("[Onboarding] User selected data collection mode:", dataCollectionMode);
        
        // Save data collection preference
        await chrome.storage.local.set({ 
          onboardingCompleted: true,
          dataCollectionMode: dataCollectionMode
        });
        console.log("[Onboarding] Saved settings");
        
        // Initialize crypto if user selected anonymous or full
        if (dataCollectionMode !== 'none') {
          console.log("[Onboarding] Initializing crypto for mode:", dataCollectionMode);
          continueBtn.textContent = 'Connecting to server...';
          continueBtn.disabled = true;
          
          try {
            const response = await chrome.runtime.sendMessage({ 
              type: 'INITIALIZE_CRYPTO' 
            });
            
            if (response && response.success) {
              console.log("[Onboarding] ✅ Crypto initialized successfully");
              continueBtn.textContent = 'Connected! Closing...';
            } else {
              console.warn("[Onboarding] ⚠️ Crypto initialization failed, but continuing");
              continueBtn.textContent = 'Closing...';
            }
          } catch (error) {
            console.warn("[Onboarding] ⚠️ Crypto initialization error:", error);
            continueBtn.textContent = 'Closing...';
          }
          
          // Small delay to show status
          await new Promise(resolve => setTimeout(resolve, 500));
        }
        
        // Close this tab
        const tab = await chrome.tabs.getCurrent();
        if (tab && tab.id) {
          console.log("[Onboarding] Closing onboarding tab");
          await chrome.tabs.remove(tab.id);
        } else {
          // Fallback: redirect to extension page
          console.log("[Onboarding] Redirecting to extension popup");
          window.location.href = chrome.runtime.getURL('popup.html');
        }
      } catch (error) {
        console.error("[Onboarding] Error:", error);
        
        // Show error to user
        continueBtn.textContent = 'Error - Please try again';
        continueBtn.disabled = true;
        
        setTimeout(() => {
          continueBtn.textContent = 'Continue to Extension';
          continueBtn.disabled = false;
        }, 2000);
      }
    });
  } else {
    console.error("[Onboarding] Continue button not found!");
  }
});

