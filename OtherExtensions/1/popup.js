// BinHex.Ninja Security - Popup Script
// Displays protection status, encryption status, and recent detections

document.addEventListener('DOMContentLoaded', async () => {
    await loadStatus();
    setupEventListeners();
});

// Load and display current status
async function loadStatus() {
    try {
        // Load encryption status
        await checkEncryptionStatus();

        // Load detection statistics
        await loadStatistics();

        // Load recent detections
        await loadRecentDetections();
    } catch (error) {
        console.error('[BinHex.Ninja Security] Error loading popup status:', error);
    }
}

// Check encryption and key exchange status
async function checkEncryptionStatus() {
    try {
        const storage = await chrome.storage.local.get([
            'dataCollectionMode', 
            'serverConnected', 
            'connectionStatus',
            'cryptoInitialized',
            'clientKeyHash'
        ]);
        
        const dataMode = storage.dataCollectionMode || 'none';
        const serverConnected = storage.serverConnected || false;
        const cryptoInitialized = storage.cryptoInitialized || false;
        const connectionStatus = storage.connectionStatus || 'disconnected';

        const statusElement = document.getElementById('encryption-status');
        const iconElement = document.getElementById('encryption-icon');

        if (dataMode === 'none') {
            statusElement.textContent = 'Data collection disabled';
            statusElement.className = 'status-value';
            iconElement.className = 'status-icon';
        } else if (serverConnected && cryptoInitialized) {
            statusElement.textContent = `🟢 Connected (${dataMode.charAt(0).toUpperCase() + dataMode.slice(1)} mode)`;
            statusElement.className = 'status-value success';
            iconElement.className = 'status-icon active';
        } else if (connectionStatus === 'connecting') {
            statusElement.textContent = 'Connecting...';
            statusElement.className = 'status-value';
            iconElement.className = 'status-icon warning';
        } else if (connectionStatus === 'error') {
            statusElement.textContent = 'Connection failed';
            statusElement.className = 'status-value error';
            iconElement.className = 'status-icon warning';
        } else {
            statusElement.textContent = 'Key exchange pending...';
            statusElement.className = 'status-value error';
            iconElement.className = 'status-icon warning';
        }
    } catch (error) {
        console.error('[BinHex.Ninja Security] Error checking encryption status:', error);
        document.getElementById('encryption-status').textContent = 'Error checking status';
        document.getElementById('encryption-status').className = 'status-value error';
    }
}

// Load detection statistics
async function loadStatistics() {
    try {
        const storage = await chrome.storage.local.get(['detectionStats']);
        const stats = storage.detectionStats || {
            totalBlocked: 0,
            sessionBlocked: 0,
            lastReset: Date.now()
        };

        document.getElementById('total-blocked').textContent = stats.totalBlocked || 0;
        document.getElementById('session-blocked').textContent = stats.sessionBlocked || 0;
    } catch (error) {
        console.error('[BinHex.Ninja Security] Error loading statistics:', error);
    }
}

// Load recent detections
async function loadRecentDetections() {
    try {
        const storage = await chrome.storage.local.get(['recentDetections']);
        const allDetections = storage.recentDetections || [];
        
        // Filter out the extension's own UI (about:blank, about:srcdoc)
        const EXTENSION_UI_URLS = ['about:blank', 'about:srcdoc'];
        const detections = allDetections.filter(detection => {
            const url = detection.url || '';
            return !EXTENSION_UI_URLS.some(u => url === u || url.startsWith(u));
        });

        console.log('[BinHex.Ninja Security] Loading detections:', detections.length, '(filtered from', allDetections.length, ')');

        const container = document.getElementById('recent-detections');

        if (detections.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <p>No threats detected</p>
                </div>
            `;
            return;
        }

        // Show last 5 detections
        const recentFive = detections.slice(-5).reverse();
        container.innerHTML = recentFive.map(detection => {
            try {
                const timeAgo = formatTimeAgo(detection.timestamp);
                const type = detection.type || 'page';
                const typeLabel = type === 'clipboard' ? '📋 Clipboard' : '🌐 Page';
                const badgeClass = type === 'clipboard' ? 'detection-badge-clipboard' : 'detection-badge-page';

                let displayText;
                if (type === 'clipboard' && detection.detectedCommand) {
                    // Show clipboard content (truncate if too long)
                    displayText = detection.detectedCommand.length > 100 
                        ? detection.detectedCommand.substring(0, 97) + '...'
                        : detection.detectedCommand;
                } else {
                    // Show full URL/path for page detections
                    try {
                        const url = new URL(detection.url);
                        // Show hostname + path (not just hostname)
                        displayText = url.hostname + url.pathname;
                        if (url.search) displayText += url.search;
                    } catch (e) {
                        displayText = detection.url || 'Unknown';
                    }
                }

                return `
                    <div class="detection-item">
                        <div class="detection-header">
                            <div class="detection-time">${timeAgo}</div>
                            <div class="detection-badge ${badgeClass}">${typeLabel}</div>
                        </div>
                        <div class="detection-url">${escapeHtml(displayText)}</div>
                    </div>
                `;
            } catch (error) {
                console.error('[BinHex.Ninja Security] Error rendering detection:', error, detection);
                return '';
            }
        }).join('');
    } catch (error) {
        console.error('[BinHex.Ninja Security] Error loading recent detections:', error);
        console.error('Full error:', error);
    }
}

// Format timestamp as relative time
function formatTimeAgo(timestamp) {
    const now = Date.now();
    const diff = now - timestamp;

    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
    if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    if (minutes > 0) return `${minutes} min${minutes > 1 ? 's' : ''} ago`;
    return 'Just now';
}

// Escape HTML to prevent XSS
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Setup event listeners
function setupEventListeners() {
    // Settings button
    document.getElementById('settings-btn').addEventListener('click', () => {
        chrome.runtime.openOptionsPage();
    });

    // Refresh button
    document.getElementById('refresh-btn').addEventListener('click', async () => {
        const btn = document.getElementById('refresh-btn');
        const originalText = btn.textContent;

        btn.textContent = 'Refreshing...';
        btn.disabled = true;

        await loadStatus();

        setTimeout(() => {
            btn.textContent = originalText;
            btn.disabled = false;
        }, 500);
    });

    // Theme toggle button
    const themeToggle = document.getElementById('theme-toggle-btn');
    if (themeToggle && typeof ThemeManager !== 'undefined') {
        ThemeManager.setupToggle(themeToggle);
    }
}

// Listen for updates from background script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === 'detection_update') {
        // Reload statistics and recent detections
        loadStatistics();
        loadRecentDetections();
    }
});
