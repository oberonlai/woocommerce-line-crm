/**
 * OrderChatz Push Notification Manager
 *
 * Handles push notification subscription and management for PWA functionality.
 */

class PushNotificationManager {
    constructor(serviceWorkerRegistration = null, config = {}) {
        this.vapidPublicKey = '';
        this.subscription = null;
        this.isSupported = this.checkSupport();
        this.serviceWorkerRegistration = serviceWorkerRegistration;
        this.config = config;

        // Bind methods
        this.init = this.init.bind(this);
        this.requestPermission = this.requestPermission.bind(this);
        this.subscribe = this.subscribe.bind(this);
        this.unsubscribe = this.unsubscribe.bind(this);

    }

    /**
     * Initialize the push notification manager
     */
    async init() {
        if (!this.isSupported) {
            return false;
        }

        try {
            // Get VAPID public key from config or WordPress localized data
            const config = this.config.vapid_public_key ? this.config : window.otzPWAConfig;
            if (config && config.vapid_public_key) {
                this.vapidPublicKey = config.vapid_public_key;
            } else {
                console.error('[PushManager] VAPID public key not found');
                return false;
            }
            // Wait for service worker registration
            await this.waitForServiceWorker();

            // Check existing subscription
            await this.checkExistingSubscription();

            // Setup automatic prompting logic
            this.setupAutomaticPrompting();
            return true;
        } catch (error) {
            console.error('[PushManager] Initialization failed:', error);
            return false;
        }
    }

    /**
     * Wait for service worker to be ready
     */
    async waitForServiceWorker() {
        if (!navigator.serviceWorker) {
            throw new Error('Service Worker not supported');
        }

        // If we already have a registration from PWA Manager, use it
        if (this.serviceWorkerRegistration) {
            return this.serviceWorkerRegistration;
        }

        // Otherwise, wait for existing registration
        this.serviceWorkerRegistration = await navigator.serviceWorker.ready;

        if (!this.serviceWorkerRegistration) {
            throw new Error('Service Worker registration failed');
        }

        return this.serviceWorkerRegistration;
    }

    /**
     * Check for existing push subscription
     */
    async checkExistingSubscription() {
        try {
            this.subscription = await this.serviceWorkerRegistration.pushManager.getSubscription();

            if (this.subscription) {
                // Verify subscription is still valid on server
                await this.verifySubscriptionOnServer();
            } else {
                console.log('[PushManager] No existing subscription found');
            }
        } catch (error) {
            console.error('[PushManager] Error checking existing subscription:', error);
        }
    }

    /**
     * Request notification permission from user
     */
    async requestPermission() {
        if (!this.isSupported) {
            throw new Error('Push notifications not supported');
        }

        let permission = Notification.permission;

        if (permission === 'default') {
            permission = await Notification.requestPermission();
        }

        if (permission !== 'granted') {
            throw new Error('Notification permission denied');
        }

        return permission;
    }

    /**
     * Subscribe to push notifications
     */
    async subscribe() {
        try {
            // Request permission first
            await this.requestPermission();

            // Check if already subscribed
            if (this.subscription) {
                console.log('[PushManager] Already subscribed');
                return this.subscription;
            }

            // Create new subscription
            const vapidKeyArray = this.urlBase64ToUint8Array(this.vapidPublicKey);

            this.subscription = await this.serviceWorkerRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidKeyArray
            });

            // Send subscription to server
            await this.sendSubscriptionToServer(this.subscription);

            // Trigger custom event
            this.dispatchSubscriptionEvent('subscribed', this.subscription);

            return this.subscription;
        } catch (error) {
            console.error('[PushManager] Subscription failed:', error);
            throw error;
        }
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        try {
            if (!this.subscription) {
                console.log('[PushManager] No active subscription to unsubscribe');
                return true;
            }

            // Unsubscribe from push manager
            const unsubscribed = await this.subscription.unsubscribe();

            if (unsubscribed) {
                // Remove from server
                await this.removeSubscriptionFromServer(this.subscription);

                this.subscription = null;
                console.log('[PushManager] Unsubscribed successfully');

                // Trigger custom event
                this.dispatchSubscriptionEvent('unsubscribed');
            }

            return unsubscribed;
        } catch (error) {
            console.error('[PushManager] Unsubscribe failed:', error);
            throw error;
        }
    }

    /**
     * Send subscription to server
     */
    async sendSubscriptionToServer(subscription) {
        const subscriptionData = {
            endpoint: subscription.endpoint,
            p256dh_key: this.arrayBufferToBase64(subscription.getKey('p256dh')),
            auth_key: this.arrayBufferToBase64(subscription.getKey('auth')),
            user_agent: navigator.userAgent,
            wp_user_id: this.getCurrentUserId(),
            line_user_id: this.getCurrentLineUserId()
        };

        const response = await fetch(window.otzPWAConfig.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'otz_push_subscribe',
                nonce: window.otzPWAConfig.push_nonce,
                subscription: JSON.stringify(subscriptionData)
            })
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data?.message || 'Failed to save subscription');
        }

        console.log('[PushManager] Subscription saved to server');
        return result;
    }

    /**
     * Remove subscription from server
     */
    async removeSubscriptionFromServer(subscription) {
        const response = await fetch(window.otzPWAConfig.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'otz_push_unsubscribe',
                nonce: window.otzPWAConfig.push_nonce,
                endpoint: subscription.endpoint
            })
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        const result = await response.json();

        if (!result.success) {
            console.warn('[PushManager] Failed to remove subscription from server:', result);
        } else {
            console.log('[PushManager] Subscription removed from server');
        }

        return result;
    }

    /**
     * Verify subscription is still valid on server
     */
    async verifySubscriptionOnServer() {
        if (!this.subscription) return false;

        try {
            const response = await fetch(window.otzPWAConfig.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'otz_push_verify',
                    nonce: window.otzPWAConfig.push_nonce,
                    endpoint: this.subscription.endpoint
                })
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            console.error('[PushManager] Error verifying subscription:', error);
            return false;
        }
    }

    /**
     * Check subscription status
     */
    isSubscribed() {
        return this.subscription !== null && Notification.permission === 'granted';
    }

    /**
     * Get subscription info
     */
    getSubscriptionInfo() {
        if (!this.subscription) return null;

        return {
            endpoint: this.subscription.endpoint,
            hasKeys: !!this.subscription.getKey,
            permission: Notification.permission
        };
    }

    /**
     * Convert URL-safe base64 to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    /**
     * Convert ArrayBuffer to base64
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    }

    /**
     * Get current WordPress user ID
     */
    getCurrentUserId() {
        // Try to get from global WordPress variables
        if (window.otzPWAConfig && window.otzPWAConfig.current_user_id) {
            return window.otzPWAConfig.current_user_id;
        }

        // Fallback: try to get from WordPress admin bar or other sources
        const adminBar = document.getElementById('wp-admin-bar-my-account');
        if (adminBar) {
            const userLink = adminBar.querySelector('a[href*="user-edit.php?user_id="]');
            if (userLink) {
                const match = userLink.href.match(/user_id=(\d+)/);
                if (match) return parseInt(match[1]);
            }
        }

        return 0; // Default to 0 if can't determine
    }

    /**
     * Get current LINE user ID (if available)
     */
    getCurrentLineUserId() {
        // This would be set if user is linked to LINE account
        if (window.otzPWAConfig && window.otzPWAConfig.current_line_user_id) {
            return window.otzPWAConfig.current_line_user_id;
        }
        return null;

    }

    /**
     * Dispatch custom subscription events
     */
    dispatchSubscriptionEvent(type, subscription = null) {
        const event = new CustomEvent(`push-${type}`, {
            detail: {
                subscription: subscription,
                manager: this
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * Send test notification
     */
    async sendTestNotification() {
        if (!this.isSubscribed()) {
            throw new Error('Not subscribed to push notifications');
        }

        const response = await fetch(window.otzPWAConfig.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'otz_push_test',
                nonce: window.otzPWAConfig.push_nonce
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data?.message || 'Failed to send test notification');
        }

        return result;
    }

    /**
     * Handle subscription state changes
     */
    onSubscriptionChange(callback) {
        document.addEventListener('push-subscribed', callback);
        document.addEventListener('push-unsubscribed', callback);
    }

    /**
     * Setup automatic prompting logic
     */
    setupAutomaticPrompting() {
        // Track user interactions for automatic prompting
        this.userInteractionCount = 0;
        this.setupInteractionTracking();

        // Check for appropriate timing to show prompts
        this.scheduleAutomaticPrompt();

    }

    /**
     * Setup user interaction tracking
     */
    setupInteractionTracking() {
        let interactionEvents = ['click', 'touchstart', 'keydown', 'scroll'];

        const trackInteraction = () => {
            this.userInteractionCount++;
            localStorage.setItem('otz-user-interactions', this.userInteractionCount.toString());
        };

        interactionEvents.forEach(event => {
            document.addEventListener(event, trackInteraction, {passive: true, once: false});
        });

        // Track page time
        this.pageLoadTime = Date.now();
    }

    /**
     * Schedule automatic prompt if conditions are met
     */
    scheduleAutomaticPrompt() {
        // Don't prompt if already subscribed or permission denied
        if (this.isSubscribed() || Notification.permission === 'denied') {
            return;
        }

        // Wait for user to interact and spend some time on page
        setTimeout(() => {
            this.maybeShowAutomaticPrompt();
        }, 45000); // Wait 45 seconds

        // Also check after significant user interaction
        setTimeout(() => {
            if (this.userInteractionCount >= 5) {
                this.maybeShowAutomaticPrompt();
            }
        }, 20000); // Wait 20 seconds if user is very active
    }

    /**
     * Maybe show automatic prompt based on conditions
     */
    maybeShowAutomaticPrompt() {
        // Check all conditions
        if (!this.shouldShowAutomaticPrompt()) {
            return;
        }

        // Trigger UI manager to show prompt
        if (window.otzPushSubscriptionUI) {
            window.otzPushSubscriptionUI.maybeShowAutomaticPrompt();
        } else {
            // Fallback: try basic subscription
            console.log('[PushManager] UI manager not available, attempting direct subscription');
            this.promptForSubscription();
        }
    }

    /**
     * Check if should show automatic prompt
     */
    shouldShowAutomaticPrompt() {
        // Don't show if not supported
        if (!this.isSupported) {
            return false;
        }

        // Don't show if already subscribed or denied
        if (this.isSubscribed() || Notification.permission === 'denied') {
            return false;
        }

        // Check if user dismissed recently
        const lastDismissed = localStorage.getItem('otz-push-prompt-dismissed');
        if (lastDismissed) {
            const dismissTime = parseInt(lastDismissed);
            const hoursSinceDismiss = (Date.now() - dismissTime) / (1000 * 60 * 60);
            if (hoursSinceDismiss < 24) {
                return false;
            }
        }

        // Check user engagement
        const timeOnPage = Date.now() - this.pageLoadTime;
        const hasInteracted = this.userInteractionCount >= 3;
        const hasSpentTime = timeOnPage >= 30000; // 30 seconds

        return hasInteracted && hasSpentTime && document.visibilityState === 'visible';
    }

    /**
     * Prompt for subscription (fallback method)
     */
    async promptForSubscription() {
        try {
            console.log('[PushManager] Prompting for push subscription');
            await this.subscribe();
        } catch (error) {
            console.error('[PushManager] Auto-prompt subscription failed:', error);
        }
    }

    /**
     * Enhanced iOS detection and support
     */
    isIOSDevice() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }

    /**
     * Check if iOS supports push notifications
     */
    isIOSPushSupported() {
        if (!this.isIOSDevice()) {
            return true; // Not iOS, assume supported if basic checks pass
        }

        // iOS 16.4+ supports Web Push in Safari
        const isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);
        const isHomeScreen = window.navigator.standalone === true;

        // For iOS, check if it's Safari 16.4+ or added to home screen (PWA)
        return isSafari || isHomeScreen;
    }

    /**
     * Enhanced support check including iOS specifics
     */
    checkSupport() {
        const basicSupport = 'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window &&
            'fetch' in window;

        if (!basicSupport) {
            return false;
        }

        // Additional iOS checks
        if (this.isIOSDevice() && !this.isIOSPushSupported()) {
            console.log('[PushManager] iOS device detected but Web Push not supported in this browser');
            return false;
        }

        return true;
    }

    /**
     * Get user interaction score for prompting decisions
     */
    getUserInteractionScore() {
        return this.userInteractionCount || parseInt(localStorage.getItem('otz-user-interactions') || '0');
    }

    /**
     * Reset interaction tracking (useful for testing)
     */
    resetInteractionTracking() {
        this.userInteractionCount = 0;
        localStorage.removeItem('otz-user-interactions');
        localStorage.removeItem('otz-push-prompt-dismissed');
        console.log('[PushManager] Interaction tracking reset');
    }

    /**
     * Clean up event listeners
     */
    destroy() {
        // Remove any event listeners if needed
        console.log('[PushManager] Push manager destroyed');
    }
}

// Export for use in other scripts
window.PushNotificationManager = PushNotificationManager;