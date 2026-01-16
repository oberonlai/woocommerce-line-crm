/**
 * OrderChatz 聊天介面主控制器
 *
 * 統籌所有聊天介面元件的初始化、生命週期管理和元件間通訊
 * 作為聊天系統的核心協調器，處理全域事件和狀態同步
 *
 * @package OrderChatz
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * 聊天介面管理器
     * 統一管理所有聊天介面元件的主控制器
     */
    window.ChatInterfaceManager = function (options) {
        // 預設配置
        this.defaults = {
            container: '.orderchatz-chat-interface',
            autoInit: true,
            enableLogging: true,
            errorRetryCount: 3,
            errorRetryDelay: 2000
        };

        // 合併配置
        this.options = $.extend({}, this.defaults, options || {});

        // 元件實例
        this.components = {
            friendList: null,
            chatArea: null,
            customerInfo: null,
            responsiveHandler: null,
            pollingManager: null,
            panelResizer: null
        };

        // 狀態管理
        this.state = {
            initialized: false,
            currentFriend: null,
            isLoading: false,
            hasErrors: false,
            retryCount: 0
        };

        // DOM 元素
        this.container = $(this.options.container);
        this.loadingOverlay = $('#chat-loading-overlay');

        // 如果啟用自動初始化
        if (this.options.autoInit) {
            this.init();
        }
    };

    ChatInterfaceManager.prototype = {
        /**
         * 初始化聊天介面系統
         */
        init: function () {
            // 防止重複初始化
            if (this.state.initialized) {
                return;
            }


            try {
                // 檢查必要條件
                if (!this.checkPrerequisites()) {
                    throw new Error('系統初始化前置條件檢查失敗');
                }

                // 顯示載入狀態
                this.showLoading('初始化聊天介面...');

                // 初始化元件
                this.initializeComponents();

                // 綁定全域事件
                this.bindEvents();

                // 設置初始狀態
                this.setupInitialState();

                // 標記初始化完成
                this.state.initialized = true;
                this.hideLoading();


                // 觸發初始化完成事件
                $(document).trigger('chat:initialized');

            } catch (error) {
                this.handleInitializationError(error);
            }
        },

        /**
         * 檢查系統前置條件
         * @returns {boolean} 是否滿足前置條件
         */
        checkPrerequisites: function () {
            const checks = [
                {
                    condition: typeof jQuery !== 'undefined',
                    message: 'jQuery 未載入'
                },
                {
                    condition: typeof FriendListComponent !== 'undefined',
                    message: 'FriendListComponent 未載入'
                },
                {
                    condition: typeof ChatAreaComponent !== 'undefined',
                    message: 'ChatAreaComponent 未載入'
                },
                {
                    condition: typeof CustomerInfoComponent !== 'undefined',
                    message: 'CustomerInfoComponent 未載入'
                },
                {
                    condition: typeof ResponsiveHandler !== 'undefined',
                    message: 'ResponsiveHandler 未載入'
                },
                {
                    condition: typeof PollingManager !== 'undefined',
                    message: 'PollingManager 未載入'
                },
                {
                    condition: this.container.length > 0,
                    message: '聊天介面容器未找到'
                }
            ];

            for (let check of checks) {
                if (!check.condition) {
                    console.error('ChatInterfaceManager: 前置條件檢查失敗 -', check.message);
                    return false;
                }
            }

            return true;
        },

        /**
         * 初始化所有元件
         */
        initializeComponents: function () {

            try {
                // 初始化好友列表元件
                this.components.friendList = new FriendListComponent('#friend-list-panel');

                // 初始化聊天區域元件
                this.components.chatArea = new ChatAreaComponent('#chat-area-panel');

                // 初始化貼圖選擇器 (可選模組)
                if (typeof window.ChatAreaSticker !== 'undefined') {
                    window.ChatAreaSticker.init();
                }

                // 設定全域變數供燈箱使用
                window.chatAreaInstance = this.components.chatArea;

                // 初始化客戶資訊元件
                this.components.customerInfo = new CustomerInfoComponent('#customer-info-panel');

                // 初始化響應式處理器
                this.components.responsiveHandler = new ResponsiveHandler();

                // 初始化輪詢管理器
                this.components.pollingManager = new PollingManager({
                    enabled: true,
                    enableLogging: this.options.enableLogging
                });

                // 初始化面板拖曳功能
                this.components.panelResizer = new PanelResizer();
                this.components.panelResizer.init();

                // 只在前端移動版設定額外的全域變數供 mobile component integrator 使用
                if (document.body.classList.contains('otz-frontend-chat')) {
                    window.otzFriendList = this.components.friendList;
                    window.friendList = this.components.friendList;
                    window.otzChatArea = this.components.chatArea;
                    window.chatArea = this.components.chatArea;
                    window.otzCustomerInfo = this.components.customerInfo;
                    window.customerInfo = this.components.customerInfo;
                    window.otzPollingManager = this.components.pollingManager;
                    window.pollingManager = this.components.pollingManager;
                }

            } catch (error) {
                throw new Error('元件初始化失敗: ' + error.message);
            }
        },

        /**
         * 綁定全域事件監聽
         */
        bindEvents: function () {

            // 好友選擇事件
            $(document).on('friend:selected', this.handleFriendSelected.bind(this));

            // 訊息發送事件
            $(document).on('message:sent', this.handleMessageSent.bind(this));

            // 螢幕尺寸變化事件
            $(document).on('screen:resize', this.handleScreenResize.bind(this));

            // 頁面可見性變化
            $(document).on('visibilitychange', this.handleVisibilityChange.bind(this));

            // 錯誤處理事件
            $(document).on('chat:error', this.handleChatError.bind(this));

            // 頁面載入和卸載
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));

            // 鍵盤快捷鍵
            $(document).on('keydown', this.handleGlobalKeydown.bind(this));
        },

        /**
         * 設置初始狀態
         */
        setupInitialState: function () {

            // 檢查 URL 參數是否指定了預設好友、群組或聊天室.
            const urlParams = new URLSearchParams(window.location.search);
            const friendId = urlParams.get('friend');
            const groupId = urlParams.get('group');
            const roomId = urlParams.get('room');

            // 根據參數類型選擇對應的實體.
            if (friendId) {
                // 個人好友.
                setTimeout(() => {
                    this.selectFriend(friendId, 'friend');
                }, 500);
            } else if (groupId) {
                // 群組.
                setTimeout(() => {
                    this.selectFriend(groupId, 'group');
                }, 500);
            } else if (roomId) {
                // 聊天室.
                setTimeout(() => {
                    this.selectFriend(roomId, 'room');
                }, 500);
            }

            // 設定定期檢查
            this.setupPeriodicChecks();
        },

        /**
         * 設定定期檢查機制
         */
        setupPeriodicChecks: function () {
            // 每30秒檢查系統狀態
            setInterval(() => {
                this.performHealthCheck();
            }, 30000);

            // 每5分鐘清理過期的狀態
            setInterval(() => {
                this.cleanupExpiredStates();
            }, 300000);
        },

        /**
         * 處理好友選擇事件
         * @param {Event} event - 自定義事件
         * @param {object} friendData - 好友資料物件
         */
        handleFriendSelected: function (event, friendData) {

            try {
                // 更新當前好友狀態 - 儲存好友 ID
                this.state.currentFriend = friendData.id;

                // 儲存群組 ID（如果是群組）.
                this.state.currentGroupId = friendData.group_id || null;

                // 儲存完整的好友資料供元件使用
                this.state.friendData = friendData;

                // 同步所有相關元件的狀態
                this.syncComponentStates();

                // 更新 URL（不重新載入頁面）
                this.updateURL(friendData);

                // 觸發狀態變化事件
                $(document).trigger('chat:friend-changed', [friendData.id]);

            } catch (error) {
                this.log('ChatInterfaceManager: 處理好友選擇失敗', error);
                $(document).trigger('chat:error', [error]);
            }
        },

        /**
         * 處理訊息發送事件
         * @param {Event} event - 自定義事件
         * @param {object} messageData - 訊息資料
         */
        handleMessageSent: function (event, messageData) {


            try {
                // 通知相關元件更新
                if (messageData.friendId === this.state.currentFriend) {
                    // 更新好友列表的最後訊息顯示
                    $(document).trigger('friend:updated', [messageData.friendId]);
                }

                // 記錄統計資訊
                this.recordMessageStat(messageData);

            } catch (error) {
                this.log('ChatInterfaceManager: 處理訊息發送失敗', error);
            }
        },

        /**
         * 處理螢幕尺寸變化事件
         * @param {Event} event - 自定義事件
         * @param {string} screenSize - 螢幕尺寸
         */
        handleScreenResize: function (event, screenSize) {

            try {
                // 根據螢幕尺寸調整佈局
                this.handleResponsiveLayout(screenSize);

                // 觸發布局變化事件
                $(document).trigger('chat:layout-changed', [screenSize]);

            } catch (error) {
                this.log('ChatInterfaceManager: 處理螢幕尺寸變化失敗', error);
            }
        },

        /**
         * 處理響應式佈局變化
         * @param {string} screenSize - 螢幕尺寸
         */
        handleResponsiveLayout: function (screenSize) {
            // 根據螢幕尺寸更新介面狀態
            switch (screenSize) {
                case 'mobile':
                    this.optimizeForMobile();
                    break;
                case 'tablet':
                    this.optimizeForTablet();
                    break;
                case 'desktop':
                    this.optimizeForDesktop();
                    break;
            }
        },

        /**
         * 手機版優化
         */
        optimizeForMobile: function () {
            // 確保輸入框在鍵盤彈出時仍可見
            if ('visualViewport' in window) {
                window.visualViewport.addEventListener('resize', () => {
                    this.adjustForKeyboard();
                });
            }
        },

        /**
         * 平板版優化
         */
        optimizeForTablet: function () {
            // 平板版特定優化
        },

        /**
         * 桌面版優化
         */
        optimizeForDesktop: function () {
            // 桌面版特定優化
        },

        /**
         * 調整鍵盤彈出時的佈局
         */
        adjustForKeyboard: function () {
            const viewport = window.visualViewport;
            if (viewport) {
                const keyboardHeight = window.innerHeight - viewport.height;
                if (keyboardHeight > 100) {
                    // 鍵盤已彈出
                    this.container.css('padding-bottom', keyboardHeight + 'px');
                } else {
                    // 鍵盤已收起
                    this.container.css('padding-bottom', '');
                }
            }
        },

        /**
         * 處理頁面可見性變化
         */
        handleVisibilityChange: function () {
            if (document.hidden) {
                this.pauseActivities();
            } else {
                this.resumeActivities();
            }
        },

        /**
         * 暫停活動
         */
        pauseActivities: function () {
            // 暫停定期檢查等活動
        },

        /**
         * 恢復活動
         */
        resumeActivities: function () {
            // 恢復定期檢查等活動
            this.performHealthCheck();
        },

        /**
         * 處理聊天錯誤
         * @param {Event} event - 錯誤事件
         * @param {Error} error - 錯誤物件
         */
        handleChatError: function (event, error) {


            this.state.hasErrors = true;

            // 根據錯誤類型決定處理方式
            if (error.recoverable) {
                this.attemptErrorRecovery(error);
            } else {
                this.showCriticalError(error);
            }
        },

        /**
         * 嘗試錯誤恢復
         * @param {Error} error - 錯誤物件
         */
        attemptErrorRecovery: function (error) {
            if (this.state.retryCount < this.options.errorRetryCount) {
                this.state.retryCount++;

                setTimeout(() => {
                    this.performRecoveryAction(error);
                }, this.options.errorRetryDelay);
            } else {
                this.showCriticalError(error);
            }
        },

        /**
         * 執行恢復動作
         * @param {Error} error - 錯誤物件
         */
        performRecoveryAction: function (error) {
            try {
                // 重新初始化有問題的元件
                if (error.component) {
                    this.reinitializeComponent(error.component);
                }

                this.state.hasErrors = false;
                this.state.retryCount = 0;


            } catch (recoveryError) {
                this.showCriticalError(recoveryError);
            }
        },

        /**
         * 重新初始化元件
         * @param {string} componentName - 元件名稱
         */
        reinitializeComponent: function (componentName) {
            if (this.components[componentName]) {
                // 銷毀舊元件
                if (typeof this.components[componentName].destroy === 'function') {
                    this.components[componentName].destroy();
                }

                // 重新創建元件
                switch (componentName) {
                    case 'friendList':
                        this.components.friendList = new FriendListComponent('#friend-list-panel');
                        break;
                    case 'chatArea':
                        this.components.chatArea = new ChatAreaComponent('#chat-area-panel');
                        window.chatAreaInstance = this.components.chatArea;
                        break;
                    case 'customerInfo':
                        this.components.customerInfo = new CustomerInfoComponent('#customer-info-panel');
                        break;
                    case 'responsiveHandler':
                        this.components.responsiveHandler = new ResponsiveHandler();
                        break;
                    case 'pollingManager':
                        this.components.pollingManager = new PollingManager({
                            enabled: true,
                            enableLogging: this.options.enableLogging
                        });
                        break;
                }
            }
        },

        /**
         * 處理頁面卸載前事件
         */
        handleBeforeUnload: function () {
            this.cleanup();
        },

        /**
         * 處理全域鍵盤事件
         * @param {Event} event - 鍵盤事件
         */
        handleGlobalKeydown: function (event) {
            // 鍵盤快捷鍵處理
            if (event.ctrlKey || event.metaKey) {
                switch (event.keyCode) {
                    case 70: // Ctrl/Cmd + F - 聚焦搜尋框
                        event.preventDefault();
                        $('#friend-search').focus();
                        break;

                    case 77: // Ctrl/Cmd + M - 聚焦訊息輸入框
                        event.preventDefault();
                        $('#message-input').focus();
                        break;
                }
            }

            // ESC 鍵處理
            if (event.keyCode === 27) {
                this.handleEscapeKey();
            }
        },

        /**
         * 處理 ESC 鍵
         */
        handleEscapeKey: function () {
            // 關閉任何開啟的面板或對話框
            if (this.components.responsiveHandler) {
                this.components.responsiveHandler.closeMobilePanels();
            }
        },

        /**
         * 同步元件狀態
         */
        syncComponentStates: function () {

            try {
                // 確保所有元件的狀態都是同步的
                const currentFriend = this.state.currentFriend;
                const currentGroupId = this.state.currentGroupId;
                const friendData = this.state.friendData;

                if (currentFriend) {
                    // 通知所有元件當前選中的好友
                    Object.keys(this.components).forEach(componentName => {
                        const component = this.components[componentName];
                        if (component && typeof component.syncState === 'function') {
                            component.syncState({
                                currentFriend: currentFriend,
                                currentGroupId: currentGroupId,
                                friendData: friendData
                            });
                        }
                    });
                }

            } catch (error) {
                this.log('ChatInterfaceManager: 同步元件狀態失敗', error);
            }
        },

        /**
         * 選擇好友
         * @param {string} entityId - 實體 ID（好友、群組或聊天室的 ID）
         * @param {string} entityType - 實體類型（'friend', 'group', 'room'）
         */
        selectFriend: function (entityId, entityType) {
            if (!this.components.friendList) {
                return;
            }

            // 根據類型找到對應的好友項目.
            let friendItem;

            if (entityType === 'friend') {
                // 個人好友：使用 data-friend-id 屬性.
                friendItem = this.container.find(`[data-friend-id="${entityId}"]`);
            } else if (entityType === 'group' || entityType === 'room') {
                // 群組或聊天室：需要透過好友列表找到具有對應 id 且 source_type 符合的項目.
                // 先嘗試直接用 data-friend-id 找（因為群組也使用這個屬性儲存其 id）.
                const items = this.container.find(`[data-friend-id="${entityId}"]`);

                // 如果找到多個，需要過濾出正確的類型.
                if (items.length > 0) {
                    items.each(function() {
                        const $item = $(this);
                        // 檢查是否有 data-source-type 屬性.
                        const sourceType = $item.data('source-type');

                        if (entityType === 'room' && sourceType === 'room') {
                            friendItem = $item;
                            return false; // Break the loop.
                        } else if (entityType === 'group' && sourceType === 'group') {
                            friendItem = $item;
                            return false; // Break the loop.
                        }
                    });
                }

                // 如果沒找到，使用第一個匹配的項目.
                if (!friendItem && items.length > 0) {
                    friendItem = items.first();
                }
            }

            // 觸發點擊事件.
            if (friendItem && friendItem.length) {
                friendItem.trigger('click');
            }
        },

        /**
         * 更新 URL
         * @param {object} friendData - 好友資料物件
         */
        updateURL: function (friendData) {
            try {
                const url = new URL(window.location);

                // 清除所有相關參數.
                url.searchParams.delete('friend');
                url.searchParams.delete('group');
                url.searchParams.delete('room');

                if (friendData && friendData.id) {
                    if (friendData.source_type === 'room') {
                        url.searchParams.set('room', friendData.id);
                    } else if (friendData.source_type === 'group') {
                        url.searchParams.set('group', friendData.id);
                    } else {
                        url.searchParams.set('friend', friendData.id);
                    }
                }

                window.history.replaceState({}, '', url);
            } catch (error) {
                this.log('ChatInterfaceManager: 更新 URL 失敗', error);
            }
        },

        /**
         * 記錄訊息統計
         * @param {object} messageData - 訊息資料
         */
        recordMessageStat: function (messageData) {
            // 可以在這裡記錄訊息統計資訊
            // 例如：發送時間、訊息長度、好友 ID 等
        },

        /**
         * 執行健康檢查
         */
        performHealthCheck: function () {
            try {
                // 檢查各元件狀態
                const health = {
                    friendList: this.components.friendList !== null,
                    chatArea: this.components.chatArea !== null,
                    customerInfo: this.components.customerInfo !== null,
                    responsiveHandler: this.components.responsiveHandler !== null,
                    pollingManager: this.components.pollingManager !== null
                };

                const unhealthyComponents = Object.keys(health).filter(key => !health[key]);

                if (unhealthyComponents.length > 0) {
                    // 可以在這裡執行修復動作
                }

            } catch (error) {
                this.log('ChatInterfaceManager: 健康檢查失敗', error);
            }
        },

        /**
         * 清理過期狀態
         */
        cleanupExpiredStates: function () {
            // 清理過期的狀態資料
            this.state.retryCount = 0;

            if (!this.state.hasErrors) {
                this.state.hasErrors = false;
            }
        },

        /**
         * 顯示載入狀態
         * @param {string} message - 載入訊息
         */
        showLoading: function (message) {
            this.state.isLoading = true;

            if (this.loadingOverlay.length) {
                this.loadingOverlay.find('p').text(message || otzChatL10n.loading);
                this.loadingOverlay.show();
            }
        },

        /**
         * 隱藏載入狀態
         */
        hideLoading: function () {
            this.state.isLoading = false;

            if (this.loadingOverlay.length) {
                this.loadingOverlay.hide();
            }
        },

        /**
         * 顯示嚴重錯誤
         * @param {Error} error - 錯誤物件
         */
        showCriticalError: function (error) {
            const errorMessage = error.message || '系統發生未知錯誤';

            const errorHtml = `
                <div class="chat-critical-error">
                    <div class="error-content">
                        <div class="error-icon">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <h3>系統錯誤</h3>
                        <p>${this.escapeHtml(errorMessage)}</p>
                        <div class="error-actions">
                            <button type="button" class="button button-primary" onclick="location.reload()">
                                重新載入頁面
                            </button>
                        </div>
                    </div>
                </div>
            `;

            this.container.html(errorHtml);
        },

        /**
         * 處理初始化錯誤
         * @param {Error} error - 錯誤物件
         */
        handleInitializationError: function (error) {
            this.hideLoading();
            this.showCriticalError(error);
        },

        /**
         * 清理資源
         */
        cleanup: function () {

            try {
                // 銷毀所有元件
                Object.keys(this.components).forEach(componentName => {
                    const component = this.components[componentName];
                    if (component && typeof component.destroy === 'function') {
                        component.destroy();
                    }
                });

                // 清除事件監聽器
                $(document).off('friend:selected');
                $(document).off('message:sent');
                $(document).off('screen:resize');
                $(document).off('visibilitychange');
                $(document).off('chat:error');
                $(document).off('keydown');
                $(window).off('beforeunload');

            } catch (error) {
                this.log('ChatInterfaceManager: 清理資源時發生錯誤', error);
            }
        },

        /**
         * HTML 跳脫
         * @param {string} text - 要跳脫的文字
         * @returns {string} 跳脫後的文字
         */
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * 日誌記錄
         * @param {...any} args - 日誌參數
         */
        log: function (...args) {
            if (this.options.enableLogging && console.log) {
                console.log(...args);
            }
        },

        /**
         * 取得當前狀態
         * @returns {object} 目前狀態
         */
        getState: function () {
            return {...this.state};
        },

        /**
         * 取得元件實例
         * @param {string} componentName - 元件名稱
         * @returns {object|null} 元件實例
         */
        getComponent: function (componentName) {
            return this.components[componentName] || null;
        },

        /**
         * 檢查是否已初始化
         * @returns {boolean} 是否已初始化
         */
        isInitialized: function () {
            return this.state.initialized;
        }
    };

    // 頁面載入完成後自動初始化
    $(document).ready(function () {
        // 防止重複初始化的全域標記
        if (window.otzChatInterfaceInitialized) {
            console.log('ChatInterface: 聊天介面已經初始化，跳過重複初始化');
            return;
        }

        // 檢查聊天介面容器是否存在
        const chatContainers = $('.orderchatz-chat-interface');
        if (chatContainers.length === 0) {
            return;
        }

        // 如果有多個容器，只初始化第一個
        if (chatContainers.length > 1) {
            console.warn('ChatInterface: 發現多個聊天介面容器，只初始化第一個');
            chatContainers.not(':first').remove();
        }

        // 檢查是否已有實例
        if (!window.otzChatInterface) {
            // 標記開始初始化
            window.otzChatInterfaceInitialized = true;

            // 創建全域實例
            window.otzChatInterface = new ChatInterfaceManager({
                enableLogging: typeof console !== 'undefined' && !!console.log
            });

            // 添加除錯用的全域函數
            window.testPolling = function () {
                if (window.otzChatInterface && window.otzChatInterface.components.pollingManager) {
                    console.log('手動觸發輪詢測試...');
                    window.otzChatInterface.components.pollingManager.triggerImmediatePoll();
                } else {
                    console.log('輪詢管理器未找到');
                }
            };
        }
    });

})(jQuery);