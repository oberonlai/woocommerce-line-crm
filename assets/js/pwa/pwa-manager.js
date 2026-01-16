/**
 * OrderChatz PWA Manager
 *
 * Main PWA initialization and Service Worker registration manager.
 * Coordinates all PWA functionality including service worker registration,
 * push notifications, install prompts, and offline capabilities.
 *
 * @version 1.0.02
 */

class PwaManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.isInstalled = false;
        this.serviceWorkerRegistration = null;
        this.config = window.otzPWAConfig || {};

        // Bind methods
        this.init = this.init.bind(this);
        this.registerServiceWorker = this.registerServiceWorker.bind(this);
        this.handleOnlineStatus = this.handleOnlineStatus.bind(this);
        this.handleOfflineStatus = this.handleOfflineStatus.bind(this);


    }

    /**
     * Initialize PWA functionality
     */
    async init() {
        // Check PWA support
        if (!this.isPwaSupported()) {
            return;
        }

        // Register service worker
        await this.registerServiceWorker();

        // Setup online/offline handling
        this.setupNetworkHandling();

        // Check if PWA is installed
        this.checkInstallStatus();

        // Initialize other PWA components
        this.initializeComponents();


    }

    /**
     * Check if PWA is supported
     */
    isPwaSupported() {
        return 'serviceWorker' in navigator && 'caches' in window;
    }

    /**
     * Register service worker
     */
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return null;
        }

        try {
            // Get service worker URL from config or use default
            const swUrl = this.getServiceWorkerUrl();

            const registration = await navigator.serviceWorker.register(swUrl);

            // Handle service worker updates
            registration.addEventListener('updatefound', () => {
                this.handleServiceWorkerUpdate(registration);
            });

            // Store registration
            this.serviceWorkerRegistration = registration;

            // Dispatch custom event
            this.dispatchPwaEvent('serviceworker-registered', {registration});

            return registration;

        } catch (error) {
            console.error('[PWA] Service worker registration failed:', error);
            this.dispatchPwaEvent('serviceworker-error', {error});
            return null;
        }
    }

    /**
     * Get service worker URL
     */
    getServiceWorkerUrl() {
        if (this.config.service_worker_url) {
            return this.config.service_worker_url;
        }

        // Default to plugin directory
        const pluginUrl = this.config.plugin_url || '/wp-content/plugins/order-chatz/';
        return pluginUrl + 'assets/js/pwa/sw.js';
    }

    /**
     * Handle service worker updates
     */
    handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;

        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // New service worker installed and ready

                this.showUpdateNotification();
            }
        });
    }

    /**
     * Show update notification
     */
    showUpdateNotification() {
        // Create update notification
        const updateNotification = document.createElement('div');
        updateNotification.className = 'otz-pwa-update-available';
        updateNotification.innerHTML = `
            <div class="otz-update-content">
                <div class="otz-update-icon">🔄</div>
                <div class="otz-update-text">
                    <h4>應用程式更新可用</h4>
                    <p>新版本已下載，點擊重新載入以套用更新</p>
                </div>
                <div class="otz-update-actions">
                    <button class="otz-update-btn" id="otz-update-reload">重新載入</button>
                    <button class="otz-update-btn secondary" id="otz-update-dismiss">稍後</button>
                </div>
            </div>
        `;

        // Add styles
        this.addUpdateNotificationStyles();

        // Add to page
        document.body.appendChild(updateNotification);

        // Add event listeners
        document.getElementById('otz-update-reload').addEventListener('click', () => {
            window.location.reload();
        });

        document.getElementById('otz-update-dismiss').addEventListener('click', () => {
            updateNotification.remove();
        });

        // Show notification
        setTimeout(() => {
            updateNotification.classList.add('visible');
        }, 100);


    }

    /**
     * Setup network status handling
     */
    setupNetworkHandling() {
        window.addEventListener('online', this.handleOnlineStatus);
        window.addEventListener('offline', this.handleOfflineStatus);

        // Initial status
        this.updateNetworkStatus(navigator.onLine);
    }

    /**
     * Handle online status
     */
    handleOnlineStatus() {

        this.isOnline = true;
        this.updateNetworkStatus(true);
        this.dispatchPwaEvent('network-online');
    }

    /**
     * Handle offline status
     */
    handleOfflineStatus() {

        this.isOnline = false;
        this.updateNetworkStatus(false);
        this.dispatchPwaEvent('network-offline');
    }

    /**
     * Update network status indicator
     */
    updateNetworkStatus(isOnline) {
        const indicator = document.getElementById('otz-network-status');
        if (indicator) {
            if (isOnline) {
                indicator.style.display = 'none';
                indicator.classList.remove('visible');
            } else {
                indicator.style.display = 'block';
                setTimeout(() => indicator.classList.add('visible'), 100);
            }
        }

        // Update body class
        document.body.classList.toggle('otz-offline', !isOnline);
        document.body.classList.toggle('otz-online', isOnline);
    }

    /**
     * Check installation status
     */
    checkInstallStatus() {
        // Check if running in standalone mode
        if (window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;

        }

        // Check if launched from home screen (iOS)
        if (window.navigator.standalone === true) {
            this.isInstalled = true;

        }

        // Update body class
        document.body.classList.toggle('otz-pwa-installed', this.isInstalled);
        document.body.classList.toggle('otz-pwa-browser', !this.isInstalled);

        this.dispatchPwaEvent('install-status-checked', {
            isInstalled: this.isInstalled
        });
    }

    /**
     * Initialize other PWA components
     */
    initializeComponents() {
        // Initialize push manager if available
        if (window.PushNotificationManager && this.serviceWorkerRegistration) {
            try {
                window.otzPushManager = new PushNotificationManager(
                    this.serviceWorkerRegistration,
                    this.config
                );

                // Initialize push manager
                window.otzPushManager.init().catch(error => {
                    console.error('[PWA] Push manager initialization failed:', error);
                });

            } catch (error) {
                console.error('[PWA] Failed to initialize push manager:', error);
            }
        }

        // Initialize install manager if available
        if (window.PwaInstallManager) {
            try {
                window.otzPwaInstallManager = new PwaInstallManager();
                window.otzPwaInstallManager.init();

            } catch (error) {
                console.error('[PWA] Failed to initialize install manager:', error);
            }
        }

    }

    /**
     * Check if device is mobile
     */
    isMobile() {
        return window.innerWidth <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    /**
     * Get service worker registration
     */
    getServiceWorkerRegistration() {
        return this.serviceWorkerRegistration;
    }

    /**
     * Check if app is online
     */
    getOnlineStatus() {
        return this.isOnline;
    }

    /**
     * Check if PWA is installed
     */
    getInstallStatus() {
        return this.isInstalled;
    }

    /**
     * Dispatch custom PWA events
     */
    dispatchPwaEvent(type, detail = {}) {
        const event = new CustomEvent(`pwa-${type}`, {
            detail: {
                manager: this,
                timestamp: Date.now(),
                ...detail
            }
        });
        document.dispatchEvent(event);

    }

    /**
     * Add CSS styles for update notification
     */
    addUpdateNotificationStyles() {
        if (document.getElementById('otz-update-notification-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'otz-update-notification-styles';
        styles.textContent = `
            .otz-pwa-update-available {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10000;
                max-width: 350px;
                transform: translateX(400px);
                transition: transform 0.3s ease;
            }
            .otz-pwa-update-available.visible {
                transform: translateX(0);
            }
            .otz-update-content {
                display: flex;
                align-items: center;
                padding: 16px;
                gap: 12px;
            }
            .otz-update-icon {
                font-size: 24px;
            }
            .otz-update-text h4 {
                margin: 0 0 4px 0;
                font-size: 14px;
                font-weight: 600;
            }
            .otz-update-text p {
                margin: 0;
                font-size: 12px;
                opacity: 0.9;
            }
            .otz-update-actions {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-left: auto;
            }
            .otz-update-btn {
                background: rgba(255,255,255,0.2);
                color: white;
                border: 1px solid rgba(255,255,255,0.3);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .otz-update-btn:hover {
                background: rgba(255,255,255,0.3);
            }
            .otz-update-btn.secondary {
                background: transparent;
            }
            @media (max-width: 768px) {
                .otz-pwa-update-available {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
                .otz-update-content {
                    flex-direction: column;
                    text-align: center;
                }
                .otz-update-actions {
                    flex-direction: row;
                    margin-left: 0;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Clean up PWA manager
     */
    destroy() {
        window.removeEventListener('online', this.handleOnlineStatus);
        window.removeEventListener('offline', this.handleOfflineStatus);

    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    // Only initialize on chat interface pages
    if (document.body.classList.contains('otz-frontend-chat') ||
        document.querySelector('#otz-chat-container') ||
        window.location.search.includes('otz_frontend_chat') ||
        window.location.pathname.includes('/order-chatz')) {


        window.otzPwaManager = new PwaManager();
        await window.otzPwaManager.init();
    }
});

// Export for use in other scripts
window.PwaManager = PwaManager;