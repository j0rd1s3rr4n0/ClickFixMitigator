// BinHex.Ninja Security - Theme Management System

const ThemeManager = {
    STORAGE_KEY: 'theme',
    THEMES: {
        LIGHT: 'light',
        DARK: 'dark',
        AUTO: 'auto'
    },

    // Get system preference
    getSystemPreference() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? this.THEMES.DARK : this.THEMES.LIGHT;
    },

    // Get stored preference or default to auto
    async getStoredTheme() {
        return new Promise((resolve) => {
            chrome.storage.local.get([this.STORAGE_KEY], (result) => {
                resolve(result[this.STORAGE_KEY] || this.THEMES.AUTO);
            });
        });
    },

    // Save theme preference
    async saveTheme(theme) {
        return new Promise((resolve) => {
            chrome.storage.local.set({ [this.STORAGE_KEY]: theme }, () => {
                console.log('[Theme] Saved theme:', theme);
                
                // Also save to localStorage so MAIN world scripts can access it
                try {
                    localStorage.setItem(this.STORAGE_KEY, theme);
                } catch (e) {
                    console.warn('[Theme] Could not save to localStorage:', e);
                }
                
                // Notify background script to update extension icon
                try {
                    chrome.runtime.sendMessage({ type: 'THEME_CHANGED', theme: theme }, (response) => {
                        if (chrome.runtime.lastError) {
                            console.warn('[Theme] Could not notify background:', chrome.runtime.lastError);
                        } else {
                            console.log('[Theme] Notified background script of theme change');
                        }
                    });
                } catch (e) {
                    console.warn('[Theme] Could not send message to background:', e);
                }
                
                resolve();
            });
        });
    },

    // Get effective theme (resolving 'auto' to actual theme)
    async getEffectiveTheme() {
        const stored = await this.getStoredTheme();
        if (stored === this.THEMES.AUTO) {
            return this.getSystemPreference();
        }
        return stored;
    },

    // Apply theme to document
    async applyTheme() {
        const theme = await this.getEffectiveTheme();
        document.documentElement.setAttribute('data-theme', theme);
        this.updateLogos(theme);
        
        // Also save to localStorage so MAIN world scripts can access it
        try {
            const stored = await this.getStoredTheme();
            localStorage.setItem(this.STORAGE_KEY, stored);
        } catch (e) {
            console.warn('[Theme] Could not save to localStorage:', e);
        }
        
        console.log('[Theme] Applied theme:', theme);
        return theme;
    },

    // Update all logos based on theme
    updateLogos(theme) {
        const isDark = theme === this.THEMES.DARK;
        const logoSuffix = isDark ? 'light' : 'dark'; // dark.svg shows on light mode, light.svg shows on dark mode
        
        // Update all img elements with logo class
        document.querySelectorAll('img.logo, img.sq-logo').forEach(img => {
            const currentSrc = img.getAttribute('src');
            if (currentSrc) {
                // Determine if it's SVG or PNG and the size
                if (currentSrc.includes('.svg')) {
                    img.setAttribute('src', `icons/${logoSuffix}.svg`);
                } else if (currentSrc.includes('128')) {
                    img.setAttribute('src', `icons/${logoSuffix}128.png`);
                } else if (currentSrc.includes('48')) {
                    img.setAttribute('src', `icons/${logoSuffix}48.png`);
                } else if (currentSrc.includes('16')) {
                    img.setAttribute('src', `icons/${logoSuffix}16.png`);
                } else {
                    // Default to SVG
                    img.setAttribute('src', `icons/${logoSuffix}.svg`);
                }
            }
        });
    },

    // Set up theme toggle button
    setupToggle(buttonElement) {
        if (!buttonElement) return;

        const updateButtonText = async () => {
            const stored = await this.getStoredTheme();
            const effective = await this.getEffectiveTheme();
            
            let text = '';
            if (stored === this.THEMES.AUTO) {
                text = `Theme: Auto (${effective === this.THEMES.DARK ? 'Dark' : 'Light'})`;
            } else {
                text = `Theme: ${stored === this.THEMES.DARK ? 'Dark' : 'Light'}`;
            }
            
            buttonElement.textContent = text;
        };

        buttonElement.addEventListener('click', async () => {
            const current = await this.getStoredTheme();
            let next;
            
            // Cycle: auto -> light -> dark -> auto
            if (current === this.THEMES.AUTO) {
                next = this.THEMES.LIGHT;
            } else if (current === this.THEMES.LIGHT) {
                next = this.THEMES.DARK;
            } else {
                next = this.THEMES.AUTO;
            }
            
            await this.saveTheme(next);
            await this.applyTheme();
            await updateButtonText();
        });

        updateButtonText();
    },

    // Listen for system theme changes
    watchSystemTheme() {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', async (e) => {
            const stored = await this.getStoredTheme();
            if (stored === this.THEMES.AUTO) {
                console.log('[Theme] System preference changed, reapplying theme');
                await this.applyTheme();
            }
        });
    },

    // Initialize theme system
    async init() {
        await this.applyTheme();
        this.watchSystemTheme();
        console.log('[Theme] Theme system initialized');
    }
};

// Auto-initialize when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
} else {
    ThemeManager.init();
}

