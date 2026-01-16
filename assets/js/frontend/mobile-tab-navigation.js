/**
 * Mobile Tab Navigation Controller
 * OrderChatz Frontend Mobile Chat Interface
 *
 * Manages three-panel navigation system (Friends/Chat/Customer)
 * Integrates with existing chat components and maintains state
 *
 * @version 1.0.10
 */

(function ($) {
    'use strict';

    /**
     * Mobile Tab Navigation Controller
     */
    window.otzMobileTabNavigation = {

        // Current active panel
        currentPanel: 'friends',

        // Panel stack for navigation history
        panelHistory: ['friends'],

        // Panel elements cache
        panels: {
            friends: null,
            chat: null,
            customer: null,
            settings: null
        },

        // Tab buttons cache
        tabs: {
            friends: null,
            chat: null,
            customer: null,
            settings: null
        },

        // Selected friend data
        selectedFriend: null,

        // Saved scroll position for friends list
        savedScrollPosition: 0,

        // Network status
        isOnline: true,

        // Jump mode state
        isInJumpMode: false,

        /**
         * Initialize the tab navigation system
         */
        init: function () {
            this.cacheElements();
            this.bindEvents();
            this.setupInitialState();
            this.integrateWithExistingComponents();
            this.setupNetworkStatusHandling();

            // 檢測 chat=1 參數，處理從推播通知進入的情況
            this.handlePushNotificationEntry();
        },

        /**
         * Cache DOM elements for performance
         */
        cacheElements: function () {
            // Cache panels - 使用後台實際的面板元素，支援 ID 和 class
            this.panels.friends = $('.friend-list-panel');
            this.panels.chat = $('.chat-area-panel');
            this.panels.customer = $('.customer-info-panel, #customer-info-panel');
            this.panels.settings = $('#otz-settings-panel');

            // Cache tab buttons - 下方導覽按鈕
            this.tabs.friends = $('.otz-tab-btn[data-panel="friends"]');
            this.tabs.chat = $('.otz-tab-btn[data-panel="chat"]');
            this.tabs.customer = $('.otz-tab-btn[data-panel="customer"]');
            this.tabs.settings = $('.otz-tab-btn[data-panel="settings"]');

            // Cache other elements
            this.$backToFriends = $('#otz-back-to-friends');
            this.$backToChat = $('#otz-back-to-chat');
            this.$showCustomerInfo = $('#otz-show-customer-info');
            this.$chatTitle = $('#current-friend-name');
            this.$loadingScreen = $('#otz-loading-screen');
            this.$mainContent = $('#otz-main-content');
            this.$networkStatus = $('#otz-network-status');
            this.$mobileApp = $('#otz-mobile-chat-app');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            var self = this;

            // Tab button clicks
            $('.otz-tab-btn').on('click', function (e) {
                e.preventDefault();
                var panel = $(this).data('panel');
                if (!$(this).prop('disabled')) {
                    self.switchToPanel(panel);
                }
            });

            // Back button clicks
            this.$backToFriends.on('click', function (e) {
                e.preventDefault();
                self.switchToPanel('friends');
            });

            this.$backToChat.on('click', function (e) {
                e.preventDefault();
                self.switchToPanel('chat');
            });

            // Customer info button click
            this.$showCustomerInfo.on('click', function (e) {
                e.preventDefault();
                self.switchToPanel('customer');
            });

            // Android back button support (if available)
            $(document).on('backbutton', function (e) {
                e.preventDefault();
                self.handleBackButton();
            });

            // Keyboard navigation support
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    self.handleBackButton();
                }
            });

            // Handle browser back/forward
            $(window).on('popstate', function (e) {
                // We don't use browser history for navigation
                // but we prevent it from affecting our app
                e.preventDefault();
                history.replaceState(null, '', window.location.href);
            });
        },

        /**
         * Setup initial application state
         */
        setupInitialState: function () {
            // Start with friends panel
            this.currentPanel = 'friends';
            this.panelHistory = ['friends'];

            // 確保初始狀態正確 - 先隱藏所有面板
            Object.values(this.panels).forEach(function ($panel) {
                if ($panel && $panel.length > 0) {
                    $panel.removeClass('active').hide();
                }
            });

            // 顯示好友列表面板
            if (this.panels.friends && this.panels.friends.length > 0) {
                this.panels.friends.addClass('active').show();
            }

            // Set initial active states
            this.updateTabStates();

            // Hide loading screen after a short delay
            setTimeout(() => {
                this.hideLoadingScreen();
            }, 500);
        },

        /**
         * Switch to specified panel with animation
         */
        switchToPanel: function (panelName, options) {
            options = options || {};

            if (panelName === this.currentPanel) {
                return; // Already on this panel
            }

            var previousPanel = this.currentPanel;
            var direction = this.getAnimationDirection(previousPanel, panelName);

            // Update panel history
            if (!options.fromHistory) {
                this.panelHistory.push(panelName);
                // Limit history length
                if (this.panelHistory.length > 10) {
                    this.panelHistory = this.panelHistory.slice(-10);
                }
            }

            // Animate panels
            this.animatePanelTransition(previousPanel, panelName, direction);

            // Update current panel
            this.currentPanel = panelName;

            // Update UI states
            this.updateTabStates();
            this.updatePanelStates();
            this.updateChatTitle();

            // Trigger panel-specific actions
            this.onPanelActivated(panelName, previousPanel);

            // Trigger custom event
            $(document).trigger('otz-panel-changed', {
                from: previousPanel,
                to: panelName,
                direction: direction
            });
        },

        /**
         * Determine animation direction between panels
         */
        getAnimationDirection: function (from, to) {
            var order = ['friends', 'chat', 'customer', 'settings'];
            var fromIndex = order.indexOf(from);
            var toIndex = order.indexOf(to);

            if (toIndex > fromIndex) {
                return 'right'; // Moving forward
            } else {
                return 'left'; // Moving backward
            }
        },

        /**
         * Animate panel transition
         */
        animatePanelTransition: function (fromPanel, toPanel, direction) {
            // 簡化面板轉換 - 直接切換不使用動畫
            // updatePanelStates 會處理實際的顯示/隱藏
        },

        /**
         * Update tab button states
         */
        updateTabStates: function () {
            // Reset all tabs
            $('.otz-tab-btn').removeClass('active');

            // Activate current tab (check if tab exists before calling addClass)
            if (this.tabs[this.currentPanel] && this.tabs[this.currentPanel].length > 0) {
                this.tabs[this.currentPanel].addClass('active');
            }

            // 在前端移動版中，允許用戶自由切換所有面板
            // 不再基於好友選擇狀態禁用按鈕
            if (this.tabs.friends && this.tabs.friends.length > 0) {
                this.tabs.friends.prop('disabled', false);
            }
            if (this.tabs.chat && this.tabs.chat.length > 0) {
                this.tabs.chat.prop('disabled', false);
            }
            if (this.tabs.customer && this.tabs.customer.length > 0) {
                this.tabs.customer.prop('disabled', false);
            }
            if (this.tabs.settings && this.tabs.settings.length > 0) {
                this.tabs.settings.prop('disabled', false);
            }
        },

        /**
         * Update panel active states (for CSS)
         */
        updatePanelStates: function () {
            // Hide all panels and remove active class
            Object.values(this.panels).forEach(function ($panel) {
                if ($panel && $panel.length > 0) {
                    $panel.removeClass('active').hide();
                }
            });

            // Show and add active class to current panel
            if (this.panels[this.currentPanel] && this.panels[this.currentPanel].length > 0) {
                this.panels[this.currentPanel].addClass('active').show();
            }
        },

        /**
         * Update chat title based on selected friend
         */
        updateChatTitle: function () {
            if (this.currentPanel === 'chat' && this.selectedFriend) {
                var friendName = this.selectedFriend.displayName || this.selectedFriend.name || '未知好友';
                this.$chatTitle.text(friendName);
            } else if (this.currentPanel === 'chat') {
                this.$chatTitle.text('聊天室');
            }
        },

        /**
         * Handle panel activation events
         */
        onPanelActivated: function (panelName, fromPanel) {
            switch (panelName) {
                case 'friends':
                    this.onFriendsPanelActivated();
                    break;
                case 'chat':
                    this.onChatPanelActivated(fromPanel);
                    break;
                case 'customer':
                    this.onCustomerPanelActivated();
                    break;
                case 'settings':
                    this.onSettingsPanelActivated();
                    break;
            }
        },

        /**
         * Friends panel activation handler
         */
        onFriendsPanelActivated: function () {
            var self = this;

            // 移動版不需要自動刷新好友列表，避免捲動位置被重置
            // 註解掉自動刷新，讓用戶手動控制

            // 與後台組件整合 - 使用全域變數
            // if (window.friendList && typeof window.friendList.refreshList === 'function') {
            //     window.friendList.refreshList();
            // }

            // 備用整合方式
            // if (window.otzFriendList && typeof window.otzFriendList.refreshList === 'function') {
            //     window.otzFriendList.refreshList();
            // }

            // 恢復滾動位置
            if (self.savedScrollPosition !== undefined) {
                setTimeout(function () {
                    var $friendListContainer = $('.friend-list-container');
                    if ($friendListContainer.length > 0) {
                        $friendListContainer.scrollTop(self.savedScrollPosition);
                    }
                }, 100);
            }
        },

        /**
         * Chat panel activation handler
         */
        onChatPanelActivated: function (fromPanel) {
            // 在前端移動版允許進入聊天面板，即使沒有選擇好友
            if (this.selectedFriend) {
                // 如果有選擇好友，載入對話
                if (window.chatArea && typeof window.chatArea.loadMessages === 'function') {
                    window.chatArea.loadMessages(this.selectedFriend.userId);
                }

                if (window.otzChatArea && typeof window.otzChatArea.loadMessages === 'function') {
                    window.otzChatArea.loadMessages(this.selectedFriend.userId);
                }

                // Focus message input if coming from friends panel
                if (fromPanel === 'friends') {
                    setTimeout(function () {
                        $('#message-input').focus();
                    }, 350);
                }
            }
        },

        /**
         * Customer panel activation handler
         */
        onCustomerPanelActivated: function () {

            // 在前端移動版允許進入客戶資訊面板，即使沒有選擇好友
            if (this.selectedFriend) {
                // 檢查是否已有客戶資料，避免重複載入
                const customerContainer = $('#customer-info');
                const hasExistingData = customerContainer.length > 0 &&
                    customerContainer.children().length > 0 &&
                    !customerContainer.find('.customer-info-loading').length;

                if (hasExistingData) {
                    return;
                }

                // 只有在沒有資料時才載入
                setTimeout(() => {
                    if (window.customerInfo && typeof window.customerInfo.loadCustomerInfo === 'function') {
                        console.log('Loading customer data via window.customerInfo');
                        window.customerInfo.loadCustomerInfo(this.selectedFriend);
                    }

                    if (window.otzCustomerInfo && typeof window.otzCustomerInfo.loadCustomerInfo === 'function') {
                        console.log('Loading customer data via window.otzCustomerInfo');
                        window.otzCustomerInfo.loadCustomerInfo(this.selectedFriend);
                    }

                    if (window.CustomerInfoComponent && typeof window.CustomerInfoComponent.loadCustomerInfo === 'function') {
                        console.log('Loading customer data via window.CustomerInfoComponent');
                        window.CustomerInfoComponent.loadCustomerInfo(this.selectedFriend);
                    }

                    // 觸發客戶資訊更新事件
                    $(document).trigger('otz-refresh-customer-info', [this.selectedFriend]);
                }, 100);
            }
            // 如果沒有選擇好友，仍然顯示客戶資訊面板（會顯示"請選擇好友"的提示）
        },

        /**
         * Settings panel activation handler
         */
        onSettingsPanelActivated: function () {
            // Trigger custom event for settings panel
            $(document).trigger('panel:changed', 'settings');

            // Initialize mobile settings if available
            if (window.MobileSettings && typeof window.MobileSettings.init === 'function') {
                window.MobileSettings.init();
            }
        },

        /**
         * Handle back button press
         */
        handleBackButton: function () {
            if (this.panelHistory.length <= 1) {
                // No history or only current panel, stay where we are
                return;
            }

            // Remove current panel from history
            this.panelHistory.pop();

            // Go to previous panel
            var previousPanel = this.panelHistory[this.panelHistory.length - 1];
            this.switchToPanel(previousPanel, {fromHistory: true});
        },

        /**
         * Set selected friend and enable chat/customer tabs
         */
        selectFriend: function (friendData) {
            this.selectedFriend = friendData;
            this.updateTabStates();
            this.updateChatTitle();

            // Switch to chat panel automatically
            this.switchToPanel('chat');

            // 預載客戶資訊到客戶面板
            this.preloadCustomerInfo(friendData);
        },

        /**
         * Preload customer information for selected friend
         */
        preloadCustomerInfo: function (friendData) {
            if (!friendData || !friendData.userId) {
                return;
            }

            // 延遲執行，確保聊天面板切換完成後再載入客戶資訊
            setTimeout(() => {
                // 預載客戶資訊 - 與後台組件整合
                if (window.customerInfo && typeof window.customerInfo.loadCustomerInfo === 'function') {
                    window.customerInfo.loadCustomerInfo(friendData);
                }

                // 備用整合方式
                if (window.otzCustomerInfo && typeof window.otzCustomerInfo.loadCustomerInfo === 'function') {
                    window.otzCustomerInfo.loadCustomerInfo(friendData);
                }

                // 檢查是否有其他客戶資訊載入方法
                if (window.CustomerInfoComponent && typeof window.CustomerInfoComponent.loadCustomerInfo === 'function') {
                    window.CustomerInfoComponent.loadCustomerInfo(friendData);
                }

                // 如果有其他客戶資訊載入方法，也可以在這裡調用
                $(document).trigger('otz-preload-customer-info', [friendData]);
            }, 500);
        },

        /**
         * Clear friend selection
         */
        clearFriendSelection: function () {
            this.selectedFriend = null;
            this.updateTabStates();
            this.updateChatTitle();

            // If we're on chat or customer panel, go back to friends
            if (this.currentPanel === 'chat' || this.currentPanel === 'customer') {
                this.switchToPanel('friends');
            }
        },

        /**
         * Integration with existing chat components
         */
        integrateWithExistingComponents: function () {
            var self = this;

            // Listen for friend selection events from existing friend list component
            $(document).on('friend-selected', function (e, friendData) {
                self.selectFriend(friendData);
            });

            // Listen for friend deselection
            $(document).on('friend-deselected', function (e) {
                self.clearFriendSelection();
            });

            // 直接監聽好友項目的點擊事件
            $(document).on('click', '.friend-item', function (e) {
                e.preventDefault();
                e.stopPropagation(); // 防止事件冒泡

                var $friendItem = $(this);
                var friendId = $friendItem.data('friend-id');

                if (friendId) {
                    // 從 DOM 中提取好友資料
                    var friendData = {
                        userId: friendId,
                        friendId: friendId,
                        name: $friendItem.find('.friend-name').text() || '未知好友',
                        displayName: $friendItem.find('.friend-name').text() || '未知好友',
                        avatar: $friendItem.find('.friend-avatar img').attr('src') || '',
                        lastMessage: $friendItem.find('.last-message').text() || '',
                        lastMessageTime: $friendItem.find('.last-message-time').text() || ''
                    };

                    // 記住目前的滾動位置
                    var $friendListContainer = $('.friend-list-container');
                    if ($friendListContainer.length > 0) {
                        var scrollPosition = $friendListContainer.scrollTop();
                        self.savedScrollPosition = scrollPosition;
                    }

                    self.selectFriend(friendData);

                    // 觸發 friend-selected 事件給其他組件
                    $(document).trigger('friend-selected', [friendData]);
                }
            });

            // Listen for chat events
            $(document).on('chat-loaded', function (e, chatData) {
                // Chat loaded, ensure we're on chat panel
                if (self.currentPanel !== 'chat') {
                    self.switchToPanel('chat');
                }
            });

            // 攔截原本的桌面版按鈕點擊事件，改用移動版導覽
            $(document).on('click', '.mobile-toggle-friend-list', function (e) {
                e.preventDefault();
                self.switchToPanel('friends');
            });

            $(document).on('click', '.mobile-toggle-customer-info', function (e) {
                e.preventDefault();
                self.switchToPanel('customer');
            });

            // Override existing panel switching if it exists
            if (window.otzChatInterface && window.otzChatInterface.switchPanel) {
                window.otzChatInterface.switchPanel = function (panelName) {
                    self.switchToPanel(panelName);
                };
            }
        },

        /**
         * Setup network status handling
         */
        setupNetworkStatusHandling: function () {
            var self = this;

            // Listen for online/offline events
            $(window).on('online', function () {
                self.setNetworkStatus(true);
            });

            $(window).on('offline', function () {
                self.setNetworkStatus(false);
            });

            // Listen for custom network status events from polling manager
            $(document).on('otz-network-status-changed', function (e, isOnline) {
                self.setNetworkStatus(isOnline);
            });
        },

        /**
         * Set network status and update UI
         */
        setNetworkStatus: function (isOnline) {
            this.isOnline = isOnline;

            if (isOnline) {
                this.$networkStatus.hide();
                this.$mobileApp.removeClass('network-offline');
            } else {
                this.$networkStatus.show();
                this.$mobileApp.addClass('network-offline');
            }

            // Trigger custom event
            $(document).trigger('otz-mobile-network-status', {isOnline: isOnline});
        },

        /**
         * Hide loading screen and show main content
         */
        hideLoadingScreen: function () {
            this.$loadingScreen.fadeOut(300, () => {
                this.$mainContent.show();
            });
        },

        /**
         * Show loading screen
         */
        showLoadingScreen: function () {
            this.$mainContent.hide();
            this.$loadingScreen.show();
        },

        /**
         * Get current panel name
         */
        getCurrentPanel: function () {
            return this.currentPanel;
        },

        /**
         * Get selected friend data
         */
        getSelectedFriend: function () {
            return this.selectedFriend;
        },

        /**
         * Check if network is online
         */
        isNetworkOnline: function () {
            return this.isOnline;
        },

        /**
         * Handle push notification entry with chat=1 parameter
         */
        handlePushNotificationEntry: function () {
            const urlParams = new URLSearchParams(window.location.search);
            const isChatEntry = urlParams.get('chat') === '1';
            const friendId = urlParams.get('friend');

            if (isChatEntry && friendId) {
                // 延遲處理，確保所有元件都已載入完成
                setTimeout(() => {
                    this.switchToFriendChat(friendId);
                }, 1500);
            }
        },

        /**
         * Switch to specific friend's chat from push notification
         */
        switchToFriendChat: function (friendId) {
            try {
                friendId = parseInt(friendId);

                // 查找好友列表中對應的好友項目.
                const $friendItem = $('.friend-list').find(`[data-friend-id="${friendId}"]`);

                if ($friendItem.length > 0) {
                    // 模擬點擊好友項目.
                    $friendItem.trigger('click');
                } else {
                    // 好友項目尚未載入,延遲重試.
                    setTimeout(() => {
                        this.switchToFriendChat(friendId);
                    }, 500);
                }
            } catch (error) {
                console.error('[Mobile] Error switching to friend chat:', error);
            }
        },

        /**
         * Enter jump mode for message navigation
         */
        enterJumpMode: function () {
            this.isInJumpMode = true;
            this.$mobileApp.addClass('jump-mode');

            // 觸發跳轉模式事件
            $(document).trigger('otz-jump-mode-entered');
        },

        /**
         * Exit jump mode
         */
        exitJumpMode: function () {
            this.isInJumpMode = false;
            this.$mobileApp.removeClass('jump-mode');

            // 隱藏跳轉模式指示器
            this.hideJumpModeIndicator();

            // 觸發跳轉模式退出事件
            $(document).trigger('otz-jump-mode-exited');
        },

        /**
         * Check if currently in jump mode
         */
        isJumpMode: function () {
            return this.isInJumpMode;
        },

        /**
         * Hide jump mode indicator
         */
        hideJumpModeIndicator: function () {
            $('.otz-jump-mode-indicator').fadeOut(300, function () {
                $(this).remove();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        // 等待後台組件載入完成後再初始化移動版導航
        setTimeout(function () {
            // Initialize tab navigation
            window.otzMobileTabNavigation.init();

            // Make it globally accessible for debugging
            window.tabNav = window.otzMobileTabNavigation;

        }, 1000);
    });

})(jQuery);