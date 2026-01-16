/**
 * OrderChatz 增量輪詢管理器
 *
 * 管理好友列表、未讀訊息、聊天訊息的增量輪詢更新
 * 實現高效的即時更新機制，避免重複載入資料
 *
 * @package OrderChatz
 * @since 1.0.2
 */

(function ($) {
    'use strict';

    /**
     * 輪詢管理器
     */
    window.PollingManager = function (options) {
        // 預設配置
        this.defaults = {
            // 輪詢間隔 (毫秒)
            friendListInterval: 8000,     // 好友列表更新: 8秒
            friendUpdatesInterval: 5000,  // 好友狀態更新: 5秒
            chatMessagesInterval: 1000,   // 聊天訊息更新 1000ms:
            backgroundInterval: 30000,    // 背景模式: 30秒

            // 輪詢端點
            pollingEndpoint: otzChat.ajax_url,
            nonce: otzChat.nonce,

            // 啟用狀態
            enabled: true,
            enableLogging: true
        };

        // 合併配置
        this.options = $.extend({}, this.defaults, options || {});

        // 輪詢狀態
        this.state = {
            isActive: false,
            isBackground: false,
            currentFriendId: null,
            currentGroupId: null,
            cursors: {
                last_friend_id: 0,
                last_friend_updated_at: '',
                last_message_time: ''
            }
        };

        // 計時器
        this.timers = {
            mainPolling: null
        };

        // 事件回調
        this.callbacks = {
            onNewFriends: null,
            onFriendUpdates: null,
            onNewMessages: null,
            onError: null
        };

        // 自動初始化
        this.init();
    };

    PollingManager.prototype = {
        /**
         * 初始化輪詢管理器
         */
        init: function () {
            if (!this.options.enabled) {
                return;
            }

            // 設置頁面可見性變化監聽
            this.setupVisibilityHandling();

            // 設置全域事件監聽
            this.setupEventListeners();

        },

        /**
         * 設置頁面可見性處理
         */
        setupVisibilityHandling: function () {
            $(document).on('visibilitychange', () => {
                if (document.hidden) {
                    this.enterBackgroundMode();
                } else {
                    this.exitBackgroundMode();
                }
            });

            // 初始檢查頁面狀態
            if (document.hidden) {
                this.enterBackgroundMode();
            }
        },

        /**
         * 設置事件監聽器
         */
        setupEventListeners: function () {
            // 監聽好友選擇事件
            $(document).on('friend:selected', (event, friendData) => {
                this.setCurrentFriend(friendData);
            });

            // 監聽好友列表初始化完成事件.
            $(document).on('friendlist:initialized', (event, friends) => {
                this.initializeFriendIdCursor(friends);
            });

            // 監聽聊天初始化完成事件
            $(document).on('chat:initialized', () => {
                this.start();
            });

            // 監聽訊息載入完成事件，更新最後訊息時間
            $(document).on('chat:messages-loaded', (event, data) => {
                if (data && data.lineUserId === this.state.currentFriendId && data.lastMessageTime) {
                    this.state.cursors.last_message_time = data.lastMessageTime;
                }
            });

            // 頁面卸載時停止輪詢
            $(window).on('beforeunload', () => {
                this.stop();
            });
        },

        /**
         * 開始輪詢
         */
        start: function () {
            if (this.state.isActive) {
                return;
            }

            this.state.isActive = true;
            this.scheduleNextPoll();

        },

        /**
         * 停止輪詢
         */
        stop: function () {
            if (!this.state.isActive) {
                return;
            }

            this.state.isActive = false;

            if (this.timers.mainPolling) {
                clearTimeout(this.timers.mainPolling);
                this.timers.mainPolling = null;
            }

        },

        /**
         * 進入背景模式
         */
        enterBackgroundMode: function () {
            this.state.isBackground = true;
        },

        /**
         * 退出背景模式
         */
        exitBackgroundMode: function () {
            this.state.isBackground = false;

            // 立即執行一次輪詢
            if (this.state.isActive) {
                this.performPolling();
            }

        },

        /**
         * 設置當前聊天好友
         */
        setCurrentFriend: function (friendData) {
            // friendData 可能是物件或 ID.
            let lineUserId = null;
            let groupId = null;

            if (typeof friendData === 'object') {
                lineUserId = friendData.line_user_id || null;
                groupId = friendData.group_id || null;
            } else {
                // 如果是字串，需判斷是 line_user_id 還是 group_id.
                lineUserId = friendData;
            }

            // 檢查是否切換了聊天對象（個人或群組）.
            const friendChanged = this.state.currentFriendId !== lineUserId;
            const groupChanged = this.state.currentGroupId !== groupId;

            if (friendChanged || groupChanged) {
                this.state.currentFriendId = lineUserId;
                this.state.currentGroupId = groupId;
                // 當切換聊天對象時，重置訊息游標.
                this.state.cursors.last_message_time = '';
            }
        },

        /**
         * 初始化好友 ID 游標（避免重複載入已存在的好友）
         * @param {array} friendsList - 好友列表陣列
         */
        initializeFriendIdCursor: function (friendsList) {
            if (!Array.isArray(friendsList) || friendsList.length === 0) {
                return;
            }

            const maxId = Math.max(...friendsList.map(f => parseInt(f.id) || 0));
            if (maxId > this.state.cursors.last_friend_id) {
                this.state.cursors.last_friend_id = maxId;
            }
        },

        /**
         * 安排下一次輪詢
         */
        scheduleNextPoll: function () {
            if (!this.state.isActive) {
                return;
            }

            let interval;
            if (this.state.isBackground) {
                interval = this.options.backgroundInterval;
            } else if (this.state.currentFriendId) {
                // 有選擇好友時，使用快速輪詢
                interval = this.options.chatMessagesInterval;
            } else {
                // 沒有選擇好友時，使用一般輪詢
                interval = this.options.friendUpdatesInterval; // 5000ms
            }

            this.timers.mainPolling = setTimeout(() => {
                this.performPolling();
            }, interval);
        },

        /**
         * 執行輪詢
         */
        performPolling: function () {
            if (!this.state.isActive) {
                return;
            }

            const requestData = {
                action: 'otz_polling_updates',
                nonce: this.options.nonce,
                last_friend_id: this.state.cursors.last_friend_id,
                last_friend_updated_at: this.state.cursors.last_friend_updated_at,
                current_friend_id: this.state.currentFriendId || '',
                last_message_time: this.state.cursors.last_message_time || ''
            };

            $.ajax({
                url: this.options.pollingEndpoint,
                type: 'POST',
                data: requestData,
                timeout: 15000,
                dataType: 'json'
            })
                .done((response) => {
                    this.handlePollingResponse(response);
                })
                .fail((xhr, status, error) => {
                    this.handlePollingError(xhr, status, error);
                })
                .always(() => {
                    // 安排下一次輪詢
                    this.scheduleNextPoll();
                });
        },

        /**
         * 處理輪詢響應
         */
        handlePollingResponse: function (response) {
            if (!response.success) {
                this.handlePollingError(null, 'server_error', response.data?.message || '伺服器錯誤');
                return;
            }

            const data = response.data;


            // 更新游標
            if (data.cursors) {
                this.updateCursors(data.cursors);
            }

            // 處理更新
            if (data.has_updates && data.updates) {
                this.processUpdates(data.updates);
            }
        },

        /**
         * 更新游標
         */
        updateCursors: function (cursors) {
            if (cursors.last_friend_id) {
                this.state.cursors.last_friend_id = cursors.last_friend_id;
            }
            if (cursors.last_friend_updated_at) {
                this.state.cursors.last_friend_updated_at = cursors.last_friend_updated_at;
            }
            if (cursors.last_message_time) {
                this.state.cursors.last_message_time = cursors.last_message_time;
            }
        },

        /**
         * 處理各種類型的更新
         */
        processUpdates: function (updates) {
            // 處理新好友
            if (updates.new_friends && updates.new_friends.length > 0) {
                this.handleNewFriends(updates.new_friends);
            }

            // 處理好友更新
            if (updates.friend_updates && updates.friend_updates.length > 0) {
                this.handleFriendUpdates(updates.friend_updates);
            }

            // 處理新訊息
            if (updates.new_messages && updates.new_messages.length > 0) {
                this.handleNewMessages(updates.new_messages);
            }
        },

        /**
         * 處理新好友
         */
        handleNewFriends: function (newFriends) {

            // 觸發新好友事件
            $(document).trigger('polling:new-friends', [newFriends]);

            // 執行回調
            if (this.callbacks.onNewFriends) {
                this.callbacks.onNewFriends(newFriends);
            }
        },

        /**
         * 處理好友更新
         */
        handleFriendUpdates: function (friendUpdates) {

            // 檢查當前選中好友的 bot_status 變更
            if (this.state.currentFriendId) {
                const currentFriendUpdate = friendUpdates.find(
                    u => u.line_user_id === this.state.currentFriendId
                );

                if (currentFriendUpdate && typeof currentFriendUpdate.bot_status !== 'undefined') {
                    $(document).trigger('polling:bot-status-changed', {
                        line_user_id: currentFriendUpdate.line_user_id,
                        bot_status: currentFriendUpdate.bot_status
                    });
                }
            }

            // 觸發好友更新事件
            $(document).trigger('polling:friend-updates', [friendUpdates]);

            // 執行回調
            if (this.callbacks.onFriendUpdates) {
                this.callbacks.onFriendUpdates(friendUpdates);
            }
        },

        /**
         * 處理新訊息
         */
        handleNewMessages: function (newMessages) {

            // 觸發新訊息事件
            $(document).trigger('polling:new-messages', [newMessages]);

            // 執行回調
            if (this.callbacks.onNewMessages) {
                this.callbacks.onNewMessages(newMessages);
            }
        },

        /**
         * 處理輪詢錯誤
         */
        handlePollingError: function (xhr, status, error) {
            const errorInfo = {
                xhr: xhr,
                status: status,
                error: error,
                timestamp: new Date()
            };


            // 觸發錯誤事件
            $(document).trigger('polling:error', [errorInfo]);

            // 執行回調
            if (this.callbacks.onError) {
                this.callbacks.onError(errorInfo);
            }

            // 網路錯誤時增加下次輪詢間隔
            if (status === 'timeout' || status === 'error') {
                this.options.friendUpdatesInterval = Math.min(
                    this.options.friendUpdatesInterval * 1.5,
                    30000
                );
            }
        },

        /**
         * 註冊回調函數
         */
        onNewFriends: function (callback) {
            this.callbacks.onNewFriends = callback;
            return this;
        },

        onFriendUpdates: function (callback) {
            this.callbacks.onFriendUpdates = callback;
            return this;
        },

        onNewMessages: function (callback) {
            this.callbacks.onNewMessages = callback;
            return this;
        },

        onError: function (callback) {
            this.callbacks.onError = callback;
            return this;
        },

        /**
         * 立即觸發一次輪詢
         */
        triggerImmediatePoll: function () {
            if (this.state.isActive) {
                // 取消當前計時器
                if (this.timers.mainPolling) {
                    clearTimeout(this.timers.mainPolling);
                }

                // 立即執行輪詢
                this.performPolling();
            }
        },

        /**
         * 重置游標 (強制重新同步)
         */
        resetCursors: function () {
            this.state.cursors = {
                last_friend_id: 0,
                last_friend_updated_at: '',
                last_message_time: ''
            };

        },

        /**
         * 取得當前狀態
         */
        getState: function () {
            return {
                isActive: this.state.isActive,
                isBackground: this.state.isBackground,
                currentFriendId: this.state.currentFriendId,
                cursors: {...this.state.cursors}
            };
        },

        /**
         * 更新輪詢間隔
         */
        updateIntervals: function (intervals) {
            if (intervals.friendListInterval) {
                this.options.friendListInterval = intervals.friendListInterval;
            }
            if (intervals.friendUpdatesInterval) {
                this.options.friendUpdatesInterval = intervals.friendUpdatesInterval;
            }
            if (intervals.chatMessagesInterval) {
                this.options.chatMessagesInterval = intervals.chatMessagesInterval;
            }
            if (intervals.backgroundInterval) {
                this.options.backgroundInterval = intervals.backgroundInterval;
            }

        },


        /**
         * 銷毀管理器
         */
        destroy: function () {
            this.stop();

            // 清除事件監聽器
            $(document).off('visibilitychange');
            $(document).off('friend:selected');
            $(document).off('friendlist:initialized');
            $(document).off('chat:initialized');
            $(document).off('chat:messages-loaded');
            $(window).off('beforeunload');

            // 清除回調
            this.callbacks = {};

        }
    };

})(jQuery);