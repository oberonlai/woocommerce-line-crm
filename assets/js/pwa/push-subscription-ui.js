/**
 * OrderChatz Push Subscription UI Manager
 *
 * Manages the user interface for push notification subscription,
 * including buttons, status indicators, and user prompts.
 */

class PushSubscriptionUI {
    constructor() {
        this.isInitialized = false;
        this.subscriptionButton = null;
        this.statusIndicator = null;
        this.subscriptionPrompt = null;
        this.pushManager = null;

        // Bind methods
        this.init = this.init.bind(this);
        this.createSubscriptionButton = this.createSubscriptionButton.bind(this);
        this.createStatusIndicator = this.createStatusIndicator.bind(this);
        this.showSubscriptionPrompt = this.showSubscriptionPrompt.bind(this);
        this.hideSubscriptionPrompt = this.hideSubscriptionPrompt.bind(this);
        this.updateUI = this.updateUI.bind(this);
        this.handleSubscriptionToggle = this.handleSubscriptionToggle.bind(this);
    }

    async init() {
        if (this.isInitialized) {
            return;
        }

        // Wait for push manager to be available
        if (window.otzPushManager && window.otzPushManager.isSupported !== undefined) {
            this.pushManager = window.otzPushManager;
        } else {
            const pushManagerReady = await this.waitForPushManager();
            if (!pushManagerReady || !this.pushManager) {
                document.body.classList.add('push-not-supported');
                return;
            }
        }

        this.createSubscriptionButton();
        this.createStatusIndicator();
        this.setupEventListeners();
        this.updateUI();

        this.isInitialized = true;
    }

    async waitForPushManager() {
        let attempts = 0;
        const maxAttempts = 100;

        return new Promise((resolve) => {
            const checkPushManager = () => {
                if (window.otzPushManager && window.otzPushManager.isSupported !== undefined) {
                    this.pushManager = window.otzPushManager;
                    resolve(true);
                    return;
                }

                attempts++;
                if (attempts >= maxAttempts) {
                    resolve(false);
                    return;
                }

                setTimeout(checkPushManager, 100);
            };
            checkPushManager();
        });
    }

    createSubscriptionButton() {
        const existingButton = document.getElementById('otz-push-subscription-btn');
        if (existingButton) {
            this.subscriptionButton = existingButton;
            return;
        }

        const chatHeader = this.findChatHeader();
        if (!chatHeader) {
            return;
        }

        const buttonContainer = document.createElement('div');
        buttonContainer.className = 'otz-push-controls';
        buttonContainer.style.cssText = `
            display: inline-flex !important;
            align-items: center;
            margin-left: 8px;
            position: relative;
            z-index: 1000;
        `;
        buttonContainer.innerHTML = `
            <button id="otz-push-subscription-btn" class="otz-push-btn" title="管理推播通知" style="
                display: flex !important;
                align-items: center;
                gap: 6px;
                background: #007cba !important;
                border: 1px solid #005a87 !important;
                border-radius: 6px;
                padding: 8px 12px !important;
                font-size: 13px;
                color: #ffffff !important;
                cursor: pointer;
                transition: all 0.2s ease;
                white-space: nowrap;
                min-width: 100px;
                box-shadow: 0 2px 4px rgba(0,124,186,0.2);
            ">
                <span class="otz-push-icon" style="font-size: 16px; line-height: 1;">🔔</span>
                <span class="otz-push-text" style="font-weight: 500;">推播通知</span>
                <span class="otz-push-status" style="font-size: 14px; opacity: 0.9;">💤</span>
            </button>
        `;

        chatHeader.appendChild(buttonContainer);
        this.subscriptionButton = document.getElementById('otz-push-subscription-btn');

        if (this.subscriptionButton) {
            this.subscriptionButton.addEventListener('click', this.handleSubscriptionToggle);
        }
    }

    createStatusIndicator() {
        this.statusIndicator = document.getElementById('otz-push-status-indicator');

        if (!this.statusIndicator) {
            return;
        }

        const actionBtn = document.getElementById('otz-status-action');
        const closeBtn = document.getElementById('otz-status-close');

        if (actionBtn) {
            actionBtn.addEventListener('click', this.handleSubscriptionToggle);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.hideStatusIndicator();
            });
        }
    }

    findChatHeader() {
        const selectors = [
            '#chat-header',
            '.chat-header',
            '.otz-chat-header',
            '#otz-chat-container .header',
            '.otz-mobile-header',
            '.otz-header-controls',
            '#otz-chat-interface .header-actions'
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
                return element;
            }
        }

        const containerSelectors = ['#otz-chat-container', '.otz-chat-interface'];

        for (const containerSelector of containerSelectors) {
            const chatContainer = document.querySelector(containerSelector);
            if (chatContainer) {
                const header = document.createElement('div');
                header.className = 'otz-push-header';
                chatContainer.insertBefore(header, chatContainer.firstChild);
                return header;
            }
        }

        return null;
    }

    setupEventListeners() {
        if (!this.pushManager) return;

        document.addEventListener('push-subscribed', () => {
            this.updateUI();
            this.showSubscriptionSuccess();
        });

        document.addEventListener('push-unsubscribed', () => {
            this.updateUI();
            this.showUnsubscriptionSuccess();
        });
    }

    updateUI() {
        if (!this.pushManager || typeof this.pushManager.isSubscribed !== 'function') {
            return;
        }

        const isSupported = this.pushManager.isSupported;
        const isSubscribed = this.pushManager.isSubscribed();
        const permission = 'Notification' in window ? Notification.permission : 'default';

        if (this.subscriptionButton) {
            const icon = this.subscriptionButton.querySelector('.otz-push-icon');
            const text = this.subscriptionButton.querySelector('.otz-push-text');
            const status = this.subscriptionButton.querySelector('.otz-push-status');

            if (!isSupported) {
                this.subscriptionButton.style.display = 'none';
                return;
            }

            if (isSubscribed) {
                icon.textContent = '🔔';
                text.textContent = '推播已開啟';
                status.textContent = '✅';
                this.subscriptionButton.style.backgroundColor = '#28a745';
                this.subscriptionButton.style.borderColor = '#1e7e34';
                this.subscriptionButton.style.boxShadow = '0 2px 4px rgba(40,167,69,0.2)';
                this.subscriptionButton.classList.add('subscribed');
                this.subscriptionButton.classList.remove('denied');
                this.subscriptionButton.title = '點擊關閉推播通知';
            } else if (permission === 'denied') {
                icon.textContent = '🔕';
                text.textContent = '推播已拒絕';
                status.textContent = '❌';
                this.subscriptionButton.style.backgroundColor = '#dc3545';
                this.subscriptionButton.style.borderColor = '#c82333';
                this.subscriptionButton.style.boxShadow = '0 2px 4px rgba(220,53,69,0.2)';
                this.subscriptionButton.classList.add('denied');
                this.subscriptionButton.classList.remove('subscribed');
                this.subscriptionButton.title = '推播通知已被拒絕，請在瀏覽器設定中手動開啟';
            } else {
                icon.textContent = '🔔';
                text.textContent = '開啟推播';
                status.textContent = '💤';
                this.subscriptionButton.style.backgroundColor = '#007cba';
                this.subscriptionButton.style.borderColor = '#005a87';
                this.subscriptionButton.style.boxShadow = '0 2px 4px rgba(0,124,186,0.2)';
                this.subscriptionButton.classList.remove('subscribed', 'denied');
                this.subscriptionButton.title = '點擊開啟推播通知';
            }
        }
    }

    async handleSubscriptionToggle() {
        if (!this.pushManager || typeof this.pushManager.isSubscribed !== 'function') {
            this.showError('推播通知功能暫時無法使用，請重新整理頁面');
            return;
        }

        try {
            const isSubscribed = this.pushManager.isSubscribed();
            const permission = 'Notification' in window ? Notification.permission : 'default';

            if (isSubscribed) {
                await this.pushManager.unsubscribe();
            } else if (permission === 'denied') {
                this.showPermissionDeniedGuide();
            } else {
                this.showSubscriptionPrompt();
            }
        } catch (error) {
            this.showError(`推播通知設定失敗: ${error.message || '請稍後再試'}`);
        }
    }

    showSubscriptionPrompt() {
        this.subscriptionPrompt = document.getElementById('otz-subscription-prompt');

        if (!this.subscriptionPrompt) {
            return;
        }

        this.subscriptionPrompt.style.display = 'block';

        const closeBtn = document.getElementById('otz-prompt-close');
        const laterBtn = document.getElementById('otz-prompt-later');
        const enableBtn = document.getElementById('otz-prompt-enable');

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.hideSubscriptionPrompt();
            });
        }

        if (laterBtn) {
            laterBtn.addEventListener('click', () => {
                this.hideSubscriptionPrompt();
                localStorage.setItem('otz-push-prompt-dismissed', Date.now().toString());
            });
        }

        if (enableBtn) {
            enableBtn.addEventListener('click', async () => {
                this.hideSubscriptionPrompt();
                try {
                    if (!this.pushManager) {
                        throw new Error('Push manager not available');
                    }
                    await this.pushManager.subscribe();
                } catch (error) {
                    this.showError(`推播通知開啟失敗: ${error.message || '請檢查瀏覽器設定'}`);
                }
            });
        }

        const overlay = this.subscriptionPrompt.querySelector('.otz-prompt-overlay');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target.classList.contains('otz-prompt-overlay')) {
                    this.hideSubscriptionPrompt();
                }
            });
        }
    }

    hideSubscriptionPrompt() {
        if (this.subscriptionPrompt) {
            this.subscriptionPrompt.style.display = 'none';
        }
    }

    showPermissionDeniedGuide() {
        const guide = document.getElementById('otz-permission-guide');

        if (!guide) {
            return;
        }

        guide.style.display = 'block';

        const closeGuide = () => {
            guide.style.display = 'none';
        };

        const closeBtn = document.getElementById('otz-guide-close');
        const understandBtn = document.getElementById('otz-guide-understand');

        if (closeBtn) {
            closeBtn.addEventListener('click', closeGuide);
        }

        if (understandBtn) {
            understandBtn.addEventListener('click', closeGuide);
        }

        const overlay = guide.querySelector('.otz-guide-overlay');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target.classList.contains('otz-guide-overlay')) {
                    closeGuide();
                }
            });
        }
    }

    showSubscriptionSuccess() {
        this.showStatusIndicator('推播通知已開啟！您現在會收到即時訊息提醒。', 'success');
    }

    showUnsubscriptionSuccess() {
        this.showStatusIndicator('推播通知已關閉。您將不再收到訊息提醒。', 'info');
    }

    showError(message) {
        this.showStatusIndicator(message, 'error');
    }

    showStatusIndicator(message, type = 'info') {
        if (!this.statusIndicator) return;

        const messageElement = this.statusIndicator.querySelector('.otz-status-message');
        const actionBtn = this.statusIndicator.querySelector('#otz-status-action');

        messageElement.textContent = message;
        actionBtn.style.display = 'none';

        this.statusIndicator.className = `otz-push-status-indicator ${type}`;
        this.statusIndicator.classList.remove('hidden');

        setTimeout(() => {
            this.hideStatusIndicator();
        }, 5000);
    }

    hideStatusIndicator() {
        if (this.statusIndicator) {
            this.statusIndicator.classList.add('hidden');
        }
    }

    maybeShowAutomaticPrompt() {
        if (!this.pushManager || !this.pushManager.isSupported) {
            return;
        }


        const isSubscribed = this.pushManager.isSubscribed();
        const permission = 'Notification' in window ? Notification.permission : 'default';

        if (isSubscribed || permission === 'denied') {
            return;
        }

        const lastDismissed = localStorage.getItem('otz-push-prompt-dismissed');
        if (lastDismissed) {
            const dismissTime = parseInt(lastDismissed);
            const hoursSinceDismiss = (Date.now() - dismissTime) / (1000 * 60 * 60);
            if (hoursSinceDismiss < 24) {
                return;
            }
        }

        setTimeout(() => {
            this.showSubscriptionPrompt();
        }, 3000);
    }

    shouldShowAutomaticPrompt() {
        return this.pushManager &&
            this.pushManager.isSupported &&
            !this.pushManager.isSubscribed() &&
            Notification.permission === 'default';
    }

    destroy() {
        if (this.subscriptionButton) {
            this.subscriptionButton.removeEventListener('click', this.handleSubscriptionToggle);
        }

        this.hideSubscriptionPrompt();
        this.hideStatusIndicator();
    }
}

function initializePushSubscriptionUI() {
    const pageChecks = {
        hasFrontendChatClass: document.body.classList.contains('otz-frontend-chat'),
        hasChatContainer: !!document.querySelector('#otz-chat-container'),
        hasUrlParam: window.location.search.includes('otz_frontend_chat'),
        hasPathname: window.location.pathname.includes('/order-chatz')
    };

    const isCorrectPage = pageChecks.hasFrontendChatClass ||
        pageChecks.hasChatContainer ||
        pageChecks.hasUrlParam ||
        pageChecks.hasPathname;

    if (!isCorrectPage) {
        return;
    }

    if (!window.otzPushSubscriptionUI) {
        try {
            window.otzPushSubscriptionUI = new PushSubscriptionUI();
            window.otzPushSubscriptionUI.init().then(() => {
                window.otzPushSubscriptionUI.maybeShowAutomaticPrompt();
            }).catch(error => {
                console.error('[PushUI] Initialization failed:', error);
            });
        } catch (error) {
            console.error('[PushUI] Failed to create PushSubscriptionUI instance:', error);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        initializePushSubscriptionUI();
    }, 500);

    setTimeout(() => {
        if (!window.otzPushSubscriptionUI || !window.otzPushSubscriptionUI.isInitialized) {
            initializePushSubscriptionUI();
        }
    }, 2000);

    setTimeout(() => {
        if (!window.otzPushSubscriptionUI || !window.otzPushSubscriptionUI.isInitialized) {
            initializePushSubscriptionUI();
        }
    }, 5000);
});

window.addEventListener('load', () => {
    setTimeout(() => {
        if (!window.otzPushSubscriptionUI || !window.otzPushSubscriptionUI.isInitialized) {
            initializePushSubscriptionUI();
        }
    }, 1000);
});

window.PushSubscriptionUI = PushSubscriptionUI;