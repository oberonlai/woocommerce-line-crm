/**
 * OrderChatz PWA Install Manager
 *
 * Handles PWA installation prompts and user interactions for installing the app to home screen.
 */

class PwaInstallManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.installButton = null;
        this.installBanner = null;

        // Bind methods
        this.init = this.init.bind(this);
        this.handleBeforeInstallPrompt = this.handleBeforeInstallPrompt.bind(this);
        this.handleAppInstalled = this.handleAppInstalled.bind(this);
        this.handleInstallClick = this.handleInstallClick.bind(this);
        this.showInstallPrompt = this.showInstallPrompt.bind(this);
        this.hideInstallPrompt = this.hideInstallPrompt.bind(this);

    }

    /**
     * Initialize the PWA install manager
     */
    init() {
        // Check if already installed
        this.checkIfInstalled();

        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt);

        // Listen for appinstalled event
        window.addEventListener('appinstalled', this.handleAppInstalled);

        // Setup manual install button
        this.setupInstallButton();

        // Auto-show install prompt if conditions are met
        this.scheduleInstallPrompt();
    }

    /**
     * Handle beforeinstallprompt event
     */
    handleBeforeInstallPrompt(event) {

        // Prevent the default browser install prompt
        event.preventDefault();

        // Store the event for later use
        this.deferredPrompt = event;

        // Show custom install prompt
        this.showInstallPrompt();
    }

    /**
     * Show custom install prompt banner
     */
    showInstallPrompt() {
        if (!this.deferredPrompt || this.isInstalled) {
            return;
        }

        // Don't show if user dismissed recently
        const lastDismissed = localStorage.getItem('otz-pwa-install-dismissed');
        if (lastDismissed) {
            const dismissTime = parseInt(lastDismissed);
            const daysSinceDismiss = (Date.now() - dismissTime) / (1000 * 60 * 60 * 24);
            if (daysSinceDismiss < 7) {
                return;
            }
        }

        // Use existing install banner from DOM
        this.installBanner = document.getElementById('otz-install-banner');

        if (!this.installBanner) {
            return;
        }

        // Show the banner
        this.installBanner.style.display = 'block';

        // Add event listeners
        document.getElementById('otz-install-now').addEventListener('click', this.handleInstallClick);
        document.getElementById('otz-install-close').addEventListener('click', () => {
            this.hideInstallPrompt();
            // Remember dismissal
            localStorage.setItem('otz-pwa-install-dismissed', Date.now().toString());
        });

        // Auto-hide after 15 seconds
        setTimeout(() => {
            if (this.installBanner && this.installBanner.style.display === 'block') {
                this.hideInstallPrompt();
            }
        }, 15000);
    }

    /**
     * Handle install button click
     */
    async handleInstallClick() {
        if (!this.deferredPrompt) {
            console.warn('[PWAInstall] No deferred prompt available');
            return;
        }

        // Hide the install prompt
        this.hideInstallPrompt();

        // Show the install dialog
        this.deferredPrompt.prompt();

        // Wait for the user to respond
        const {outcome} = await this.deferredPrompt.userChoice;

        console.log(`[PWAInstall] User response to install prompt: ${outcome}`);

        // Clear the deferred prompt
        this.deferredPrompt = null;

        // Track installation attempt
        this.trackInstallAttempt(outcome);

        if (outcome === 'accepted') {
            this.showInstallSuccess();
        }
    }

    /**
     * Hide install prompt banner
     */
    hideInstallPrompt() {
        if (this.installBanner) {
            this.installBanner.style.display = 'none';
        }
    }

    /**
     * Handle app installed event
     */
    handleAppInstalled() {
        console.log('[PWAInstall] PWA was installed successfully');

        this.isInstalled = true;
        this.hideInstallPrompt();
        this.hideManualInstallButton();

        // Show success message
        this.showInstallSuccess();

        // Clear dismissed timestamp since app is now installed
        localStorage.removeItem('otz-pwa-install-dismissed');

        // Prompt for push notifications after PWA installation
        this.promptForPushNotifications();

        // Dispatch custom event
        this.dispatchInstallEvent('installed');
    }

    /**
     * Check if PWA is already installed
     */
    checkIfInstalled() {
        // Check if running in standalone mode
        if (window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;
            console.log('[PWAInstall] Running in standalone mode - PWA installed');
        }

        // Check if launched from home screen (iOS)
        if (window.navigator.standalone === true) {
            this.isInstalled = true;
            console.log('[PWAInstall] Launched from home screen - PWA installed');
        }

        // Check if app is installed (newer browsers)
        if ('getInstalledRelatedApps' in navigator) {
            navigator.getInstalledRelatedApps().then(apps => {
                if (apps.length > 0) {
                    this.isInstalled = true;
                    console.log('[PWAInstall] Found installed related apps');
                }
            }).catch(err => {
                console.warn('[PWAInstall] Error checking installed apps:', err);
            });
        }
    }

    /**
     * Setup manual install button in chat header
     */
    setupInstallButton() {
        if (this.isInstalled) {
            return;
        }

        // Find chat header or appropriate location
        const chatHeader = document.querySelector('.otz-chat-header, .chat-header, #otz-chat-container .header');
        if (!chatHeader) {
            console.warn('[PWAInstall] Chat header not found, skipping manual install button');
            return;
        }

        // Create install button
        this.installButton = document.createElement('button');
        this.installButton.className = 'otz-manual-install-btn';
        this.installButton.innerHTML = '📱 安裝應用程式';
        this.installButton.title = '將 OrderChatz 安裝到主畫面';

        // Add event listener
        this.installButton.addEventListener('click', () => {
            if (this.deferredPrompt) {
                this.handleInstallClick();
            } else {
                this.showManualInstallGuide();
            }
        });

        // Add button to header
        chatHeader.appendChild(this.installButton);

        
    }

    /**
     * Hide manual install button
     */
    hideManualInstallButton() {
        if (this.installButton) {
            this.installButton.style.display = 'none';
        }
    }

    /**
     * Show manual installation guide for browsers that don't support beforeinstallprompt
     */
    showManualInstallGuide() {
        const guide = document.createElement('div');
        guide.className = 'otz-manual-install-guide';
        guide.innerHTML = `
            <div class="otz-guide-overlay" id="otz-guide-overlay">
                <div class="otz-guide-content">
                    <div class="otz-guide-header">
                        <h3>手動安裝 OrderChatz</h3>
                        <button class="otz-guide-close" id="otz-guide-close" aria-label="關閉">×</button>
                    </div>
                    <div class="otz-guide-steps">
                        <div class="otz-guide-step">
                            <strong>Chrome/Edge:</strong>
                            <p>點擊網址列右側的安裝圖示 📱</p>
                        </div>
                        <div class="otz-guide-step">
                            <strong>Safari (iOS):</strong>
                            <p>點擊分享按鈕 <span style="font-size: 18px;">⬆️</span> → 選擇「加入主畫面」</p>
                        </div>
                        <div class="otz-guide-step">
                            <strong>Firefox:</strong>
                            <p>點擊選單 ☰ → 選擇「安裝」</p>
                        </div>
                        <div class="otz-guide-step">
                            <strong>Samsung Internet:</strong>
                            <p>點擊選單 → 選擇「新增頁面至」→「主螢幕」</p>
                        </div>
                    </div>
                    <div class="otz-guide-footer">
                        <button class="otz-guide-understand" id="otz-guide-understand">我知道了</button>
                    </div>
                </div>
            </div>
        `;

        // Add styles
        this.addManualGuideStyles();

        // Add to page
        document.body.appendChild(guide);

        // Add event listeners
        const closeBtn = guide.querySelector('#otz-guide-close');
        const understandBtn = guide.querySelector('#otz-guide-understand');
        const overlay = guide.querySelector('#otz-guide-overlay');

        const closeGuide = () => {
            guide.remove();
        };

        closeBtn.addEventListener('click', closeGuide);
        understandBtn.addEventListener('click', closeGuide);
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeGuide();
            }
        });

        console.log('[PWAInstall] Manual install guide shown');
    }

    /**
     * Show install success message
     */
    showInstallSuccess() {
        const successMsg = document.createElement('div');
        successMsg.className = 'otz-install-success';
        successMsg.innerHTML = `
            <div class="otz-success-content">
                <div class="otz-success-icon">✅</div>
                <h3>安裝成功！</h3>
                <p>OrderChatz 已成功加入您的主畫面</p>
            </div>
        `;

        // Add styles
        this.addSuccessMessageStyles();

        // Add to page
        document.body.appendChild(successMsg);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            successMsg.remove();
        }, 3000);

        console.log('[PWAInstall] Install success message shown');
    }

    /**
     * Schedule automatic install prompt
     */
    scheduleInstallPrompt() {
        // Don't auto-prompt if already installed
        if (this.isInstalled) {
            return;
        }

        // Wait for page to load and user to interact
        setTimeout(() => {
            // Check if user has been on the chat interface for a while
            if (document.visibilityState === 'visible' && this.deferredPrompt) {
                // Only show if user hasn't dismissed recently and seems engaged
                const hasScrolled = window.scrollY > 100;
                const hasClicked = this.getUserInteractionScore() > 3;

                if (hasScrolled || hasClicked) {
                    this.showInstallPrompt();
                }
            }
        }, 30000); // Wait 30 seconds
    }

    /**
     * Get user interaction score (simple heuristic)
     */
    getUserInteractionScore() {
        // This is a simple implementation - in practice you might track more interactions
        return parseInt(localStorage.getItem('otz-user-interactions') || '0');
    }

    /**
     * Track install attempt for analytics
     */
    trackInstallAttempt(outcome) {
        // Send analytics event if analytics is available
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install_prompt', {
                outcome: outcome,
                source: 'custom_banner'
            });
        }

        // Store local analytics
        const attempts = JSON.parse(localStorage.getItem('otz-install-attempts') || '[]');
        attempts.push({
            timestamp: Date.now(),
            outcome: outcome,
            userAgent: navigator.userAgent
        });
        localStorage.setItem('otz-install-attempts', JSON.stringify(attempts));
    }

    /**
     * Get icon URL for install prompt
     */
    getIconUrl() {
        if (window.otzPWAConfig && window.otzPWAConfig.icon_url) {
            return window.otzPWAConfig.icon_url;
        }

        // Fallback to plugin directory
        const pluginUrl = window.otzPWAConfig?.plugin_url || '/wp-content/plugins/order-chatz/';
        return pluginUrl + 'assets/img/otz-icon-192.png';
    }

    /**
     * Dispatch custom install events
     */
    dispatchInstallEvent(type, data = {}) {
        const event = new CustomEvent(`pwa-${type}`, {
            detail: {
                manager: this,
                ...data
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * Add CSS styles for install banner
     */
    addInstallBannerStyles() {
        if (document.getElementById('otz-install-banner-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'otz-install-banner-styles';
        styles.textContent = `
            .otz-install-banner {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                max-width: 350px;
                animation: slideIn 0.3s ease-out;
            }
            .otz-install-content {
                display: flex;
                align-items: center;
                padding: 16px;
                gap: 12px;
            }
            .otz-install-icon-img {
                width: 48px;
                height: 48px;
                border-radius: 8px;
            }
            .otz-install-text h3 {
                margin: 0 0 4px 0;
                font-size: 16px;
                font-weight: 600;
                color: #333;
            }
            .otz-install-text p {
                margin: 0;
                font-size: 14px;
                color: #666;
                line-height: 1.4;
            }
            .otz-install-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-left: auto;
            }
            .otz-install-btn {
                background: #007cba;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: background 0.2s;
            }
            .otz-install-btn:hover {
                background: #005a87;
            }
            .otz-install-close {
                background: none;
                border: none;
                color: #999;
                font-size: 20px;
                cursor: pointer;
                padding: 4px;
                line-height: 1;
                align-self: flex-end;
            }
            .otz-install-close:hover {
                color: #666;
            }
            .otz-manual-install-btn {
                background: #007cba;
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 12px;
                cursor: pointer;
                margin-left: 8px;
            }
            .otz-manual-install-btn:hover {
                background: #005a87;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @media (max-width: 480px) {
                .otz-install-banner {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
                .otz-install-content {
                    flex-direction: column;
                    text-align: center;
                }
                .otz-install-actions {
                    flex-direction: row;
                    margin-left: 0;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Add CSS styles for manual install guide
     */
    addManualGuideStyles() {
        if (document.getElementById('otz-manual-guide-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'otz-manual-guide-styles';
        styles.textContent = `
            .otz-manual-install-guide .otz-guide-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .otz-guide-content {
                background: white;
                border-radius: 8px;
                max-width: 500px;
                width: 100%;
                max-height: 90vh;
                overflow-y: auto;
            }
            .otz-guide-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #eee;
            }
            .otz-guide-header h3 {
                margin: 0;
                color: #333;
            }
            .otz-guide-close {
                background: none;
                border: none;
                font-size: 24px;
                color: #999;
                cursor: pointer;
            }
            .otz-guide-steps {
                padding: 20px;
            }
            .otz-guide-step {
                margin-bottom: 20px;
                padding-bottom: 16px;
                border-bottom: 1px solid #f0f0f0;
            }
            .otz-guide-step:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            .otz-guide-step strong {
                color: #333;
                display: block;
                margin-bottom: 8px;
            }
            .otz-guide-step p {
                margin: 0;
                color: #666;
                line-height: 1.5;
            }
            .otz-guide-footer {
                padding: 20px;
                border-top: 1px solid #eee;
                text-align: center;
            }
            .otz-guide-understand {
                background: #007cba;
                color: white;
                border: none;
                padding: 10px 24px;
                border-radius: 4px;
                cursor: pointer;
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Add CSS styles for success message
     */
    addSuccessMessageStyles() {
        if (document.getElementById('otz-success-message-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'otz-success-message-styles';
        styles.textContent = `
            .otz-install-success {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 10002;
                animation: fadeIn 0.3s ease-out;
            }
            .otz-success-content {
                padding: 32px;
                text-align: center;
            }
            .otz-success-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }
            .otz-success-content h3 {
                margin: 0 0 8px 0;
                color: #333;
                font-size: 18px;
            }
            .otz-success-content p {
                margin: 0;
                color: #666;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
                to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Check if should show install prompt
     */
    shouldShowInstallPrompt() {
        return !this.isInstalled &&
            this.deferredPrompt !== null &&
            window.matchMedia('(display-mode: browser)').matches;
    }

    /**
     * Prompt for push notifications after PWA installation
     */
    promptForPushNotifications() {
        // Wait a bit for the install success message to be seen
        setTimeout(() => {
            if (window.otzPushSubscriptionUI) {
                console.log('[PWAInstall] Prompting for push notifications after PWA installation');
                window.otzPushSubscriptionUI.showSubscriptionPrompt();
            } else {
                // If UI manager not available, try direct push manager
                if (window.otzPushManager && window.otzPushManager.isSupported) {
                    console.log('[PWAInstall] Triggering push manager after PWA installation');
                    setTimeout(() => {
                        window.otzPushManager.maybeShowAutomaticPrompt();
                    }, 1000);
                }
            }
        }, 2000); // Wait 2 seconds after install success
    }

    /**
     * Enhanced install success with push notification integration
     */
    showInstallSuccess() {
        const successMsg = document.createElement('div');
        successMsg.className = 'otz-install-success';
        successMsg.innerHTML = `
            <div class="otz-success-content">
                <div class="otz-success-icon">✅</div>
                <h3>安裝成功！</h3>
                <p>OrderChatz 已成功加入您的主畫面</p>
                <div class="otz-success-note">
                    <small>💡 接下來我們將協助您開啟推播通知</small>
                </div>
            </div>
        `;

        // Add styles for the enhanced success message
        this.addEnhancedSuccessStyles();

        // Add to page
        document.body.appendChild(successMsg);

        // Auto-remove after 4 seconds (longer to show push notification note)
        setTimeout(() => {
            successMsg.remove();
        }, 4000);

        console.log('[PWAInstall] Enhanced install success message shown');
    }

    /**
     * Add enhanced success message styles
     */
    addEnhancedSuccessStyles() {
        if (document.getElementById('otz-enhanced-success-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'otz-enhanced-success-styles';
        styles.textContent = `
            .otz-install-success .otz-success-note {
                margin-top: 12px;
                padding: 8px 12px;
                background: rgba(255,255,255,0.1);
                border-radius: 6px;
            }
            .otz-install-success .otz-success-note small {
                color: rgba(255,255,255,0.8);
                font-size: 12px;
                line-height: 1.4;
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Check if push notifications should be prompted after install
     */
    shouldPromptForPushAfterInstall() {
        // Only if push manager is available and supported
        if (!window.otzPushManager || !window.otzPushManager.isSupported) {
            return false;
        }

        // Don't prompt if already subscribed or denied
        const isSubscribed = window.otzPushManager.isSubscribed();
        const permission = 'Notification' in window ? Notification.permission : 'default';

        return !isSubscribed && permission !== 'denied';
    }

    /**
     * Clean up event listeners
     */
    destroy() {
        window.removeEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt);
        window.removeEventListener('appinstalled', this.handleAppInstalled);
        this.hideInstallPrompt();
        console.log('[PWAInstall] PWA install manager destroyed');
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize on chat interface pages
    if (document.body.classList.contains('otz-chat-interface') ||
        document.querySelector('#otz-chat-container') ||
        window.location.search.includes('otz_frontend_chat')) {

        window.otzPwaInstallManager = new PwaInstallManager();
        window.otzPwaInstallManager.init();
    }
});

// Export for use in other scripts
window.PwaInstallManager = PwaInstallManager;