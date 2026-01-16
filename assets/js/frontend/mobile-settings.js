/**
 * OrderChatz Mobile Settings Panel
 *
 * Handles mobile app settings functionality including push notifications,
 * app refresh, cache clearing, and PWA installation
 *
 * @package OrderChatz
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * Mobile Settings Manager
     */
    window.MobileSettings = {
        
        /**
         * Initialize settings panel
         */
        init: function() {
            this.setupEventListeners();
            this.loadSettings();
            this.checkNotificationStatus();
            this.checkPWAInstallability();
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            // Push notification toggle
            $('#push-notifications-toggle').on('change', this.handlePushToggle.bind(this));
            
            // Settings buttons
            $('#notification-test-btn').on('click', this.testNotification.bind(this));
            $('#notification-enable-btn').on('click', this.enableNotifications.bind(this));
            $('#refresh-app-btn').on('click', this.refreshApp.bind(this));
            $('#clear-cache-btn').on('click', this.clearCache.bind(this));
            $('#install-pwa-btn').on('click', this.installPWA.bind(this));
            
            // Settings panel visibility
            $(document).on('panel:changed', this.handlePanelChange.bind(this));
        },

        /**
         * Load saved settings
         */
        loadSettings: function() {
            const pushEnabled = localStorage.getItem('otz_push_notifications') === 'true';
            $('#push-notifications-toggle').prop('checked', pushEnabled);
        },

        /**
         * Save settings to localStorage
         */
        saveSettings: function() {
            const pushEnabled = $('#push-notifications-toggle').prop('checked');
            localStorage.setItem('otz_push_notifications', pushEnabled.toString());
        },

        /**
         * Handle push notification toggle
         */
        handlePushToggle: function(e) {
            const enabled = $(e.target).prop('checked');
            
            if (enabled) {
                this.enableNotifications();
            } else {
                this.disableNotifications();
            }
        },

        /**
         * Enable push notifications
         */
        enableNotifications: function() {
            const $btn = $('#notification-enable-btn');
            $btn.addClass('loading').prop('disabled', true);
            
            // Check if push notifications are supported
            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.showMessage('您的瀏覽器不支援推播通知功能', 'error');
                $('#push-notifications-toggle').prop('checked', false);
                $btn.removeClass('loading').prop('disabled', false);
                return;
            }
            
            // Request notification permission
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    this.subscribeToNotifications();
                } else {
                    this.showMessage('需要允許通知權限才能啟用推播通知', 'error');
                    $('#push-notifications-toggle').prop('checked', false);
                    this.updateNotificationStatus();
                }
                $btn.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Disable push notifications
         */
        disableNotifications: function() {
            this.unsubscribeFromNotifications();
            this.saveSettings();
            this.updateNotificationStatus();
            this.showMessage('推播通知已關閉', 'info');
        },

        /**
         * Subscribe to push notifications
         */
        subscribeToNotifications: function() {
            if (!navigator.serviceWorker) {
                this.showMessage('Service Worker 不可用', 'error');
                return;
            }

            navigator.serviceWorker.ready.then((registration) => {
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(window.otzPushConfig?.vapidPublicKey)
                });
            }).then((subscription) => {
                // Send subscription to server
                return this.sendSubscriptionToServer(subscription);
            }).then(() => {
                $('#push-notifications-toggle').prop('checked', true);
                this.saveSettings();
                this.updateNotificationStatus();
                this.showMessage('推播通知已啟用', 'success');
            }).catch((error) => {
                console.error('推播通知訂閱失敗:', error);
                $('#push-notifications-toggle').prop('checked', false);
                this.showMessage('啟用推播通知時發生錯誤', 'error');
                this.updateNotificationStatus();
            });
        },

        /**
         * Unsubscribe from push notifications
         */
        unsubscribeFromNotifications: function() {
            if (!navigator.serviceWorker) {
                return;
            }

            navigator.serviceWorker.ready.then((registration) => {
                return registration.pushManager.getSubscription();
            }).then((subscription) => {
                if (subscription) {
                    return subscription.unsubscribe();
                }
            }).then(() => {
                // Remove subscription from server
                return this.removeSubscriptionFromServer();
            }).catch((error) => {
                console.error('推播通知取消訂閱失敗:', error);
            });
        },

        /**
         * Send subscription to server
         */
        sendSubscriptionToServer: function(subscription) {
            const subscriptionData = {
                endpoint: subscription.endpoint,
                p256dh: arrayBufferToBase64(subscription.getKey('p256dh')),
                auth: arrayBufferToBase64(subscription.getKey('auth')),
                user_agent: navigator.userAgent
            };

            return $.ajax({
                url: window.otzPushConfig?.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'otz_subscribe_push',
                    subscription: JSON.stringify(subscriptionData),
                    nonce: window.otzPushConfig?.nonce
                }
            });
        },

        /**
         * Remove subscription from server
         */
        removeSubscriptionFromServer: function() {
            return $.ajax({
                url: window.otzPushConfig?.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'otz_unsubscribe_push',
                    nonce: window.otzPushConfig?.nonce
                }
            });
        },

        /**
         * Test notification
         */
        testNotification: function() {
            const $btn = $('#notification-test-btn');
            $btn.addClass('loading').prop('disabled', true);
            
            $.ajax({
                url: window.otzPushConfig?.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'otz_test_push_notification',
                    nonce: window.otzPushConfig?.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('測試通知已發送', 'success');
                    } else {
                        this.showMessage('發送測試通知失敗', 'error');
                    }
                },
                error: () => {
                    this.showMessage('網路錯誤，請稍後再試', 'error');
                },
                complete: () => {
                    $btn.removeClass('loading').prop('disabled', false);
                }
            });
        },

        /**
         * Check notification status
         */
        checkNotificationStatus: function() {
            if (!('Notification' in window)) {
                this.setNotificationStatus('unsupported', '不支援推播通知');
                return;
            }

            const permission = Notification.permission;
            switch (permission) {
                case 'granted':
                    this.setNotificationStatus('enabled', '已啟用');
                    $('#notification-test-btn').show();
                    $('#notification-enable-btn').hide();
                    break;
                case 'denied':
                    this.setNotificationStatus('blocked', '已被封鎖');
                    $('#notification-test-btn').hide();
                    $('#notification-enable-btn').hide();
                    break;
                case 'default':
                    this.setNotificationStatus('disabled', '未啟用');
                    $('#notification-test-btn').hide();
                    $('#notification-enable-btn').show();
                    break;
            }
        },

        /**
         * Update notification status display
         */
        updateNotificationStatus: function() {
            setTimeout(() => {
                this.checkNotificationStatus();
            }, 500);
        },

        /**
         * Set notification status
         */
        setNotificationStatus: function(status, text) {
            const $statusText = $('.otz-notification-status-text');
            $statusText.removeClass('enabled disabled blocked unsupported')
                      .addClass(status)
                      .text(text);
        },

        /**
         * Check PWA installability
         */
        checkPWAInstallability: function() {
            // Show install button if PWA can be installed
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                window.deferredPrompt = e;
                $('#install-app-item').show();
            });
        },

        /**
         * Install PWA
         */
        installPWA: function() {
            if (!window.deferredPrompt) {
                this.showMessage('無法安裝應用程式', 'error');
                return;
            }

            const $btn = $('#install-pwa-btn');
            $btn.addClass('loading').prop('disabled', true);

            window.deferredPrompt.prompt();
            window.deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    this.showMessage('應用程式安裝成功', 'success');
                    $('#install-app-item').hide();
                } else {
                    this.showMessage('已取消安裝', 'info');
                }
                window.deferredPrompt = null;
                $btn.removeClass('loading').prop('disabled', false);
            });
        },

        /**
         * Refresh app
         */
        refreshApp: function() {
            const $btn = $('#refresh-app-btn');
            $btn.addClass('loading').prop('disabled', true);
            
            this.showMessage('正在重新整理...', 'info');
            
            setTimeout(() => {
                window.location.reload();
            }, 500);
        },

        /**
         * Clear cache
         */
        clearCache: function() {
            const $btn = $('#clear-cache-btn');
            $btn.addClass('loading').prop('disabled', true);
            
            // Clear various caches
            if ('caches' in window) {
                caches.keys().then((cacheNames) => {
                    return Promise.all(
                        cacheNames.map(cacheName => caches.delete(cacheName))
                    );
                }).then(() => {
                    this.showMessage('快取已清除，正在重新載入...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }).catch(() => {
                    this.showMessage('清除快取時發生錯誤', 'error');
                    $btn.removeClass('loading').prop('disabled', false);
                });
            } else {
                // Fallback: just clear localStorage and reload
                localStorage.clear();
                this.showMessage('快取已清除，正在重新載入...', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        },

        /**
         * Handle panel change
         */
        handlePanelChange: function(event, panel) {
            if (panel === 'settings') {
                // 隱藏所有其他面板
                $('.friend-list-panel, .chat-area-panel, .customer-info-panel').hide();
                $('#otz-main-content').hide();
                
                // 顯示設定面板
                $('#otz-settings-panel').show().css('display', 'block');
            } else {
                // 隱藏設定面板
                $('#otz-settings-panel').hide();
                
                // 顯示主內容
                $('#otz-main-content').show();
            }
        },

        /**
         * Show message to user
         */
        showMessage: function(text, type = 'info') {
            // Remove existing message
            $('.otz-settings-message').remove();
            
            // Create new message
            const $message = $('<div class="otz-settings-message ' + type + '">' + text + '</div>');
            $('.otz-settings-content').prepend($message);
            
            // Show with animation
            setTimeout(() => {
                $message.addClass('show');
            }, 10);
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                $message.removeClass('show');
                setTimeout(() => {
                    $message.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Convert URL-safe base64 to Uint8Array
         */
        urlBase64ToUint8Array: function(base64String) {
            if (!base64String) {
                return null;
            }
            
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
    };

    /**
     * Helper function to convert ArrayBuffer to base64
     */
    function arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        bytes.forEach((b) => binary += String.fromCharCode(b));
        return window.btoa(binary);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // 延遲初始化，確保移動導航系統已載入
        setTimeout(function() {
            if (typeof window.MobileSettings !== 'undefined') {
                window.MobileSettings.init();
            }
        }, 500);
    });

})(jQuery);