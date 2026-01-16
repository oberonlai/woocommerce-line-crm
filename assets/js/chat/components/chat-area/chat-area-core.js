/**
 * OrderChatz 聊天區域核心模組
 *
 * 管理聊天區域的核心功能，包含初始化、好友選擇、狀態管理
 *
 * @package OrderChatz
 * @since 1.0.2
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域核心管理器
     */
    window.ChatAreaCore = {
        /**
         * 初始化聊天區域組件
         * @param {string} containerSelector - 容器選擇器
         * @return {object} 聊天區域實例
         */
        createInstance: function (containerSelector) {
            const instance = {
                container: $(containerSelector),
                chatHeader: null,
                chatMessages: null,
                messageInput: null,
                sendButton: null,
                inputForm: null,
                currentFriendId: null,
                currentLineUserId: null,
                messages: [],
                currentFriend: null,
                isComposing: false,
                isLoadingMessages: false,
                hasMoreMessages: true,
                oldestMessageDate: null,
                // 跳轉模式相關狀態
                isJumpedMode: false,
                referenceTimestamp: null,
                hasMoreMessagesBefore: true,
                hasMoreMessagesAfter: true,
                targetMessageIndex: null
            };

            // 初始化 DOM 元素
            this.initDOMElements(instance);

            // 初始化功能模組
            this.initModules(instance);

            // 綁定事件
            this.bindEvents(instance);

            // 設置初始狀態
            this.showNoSelectionState(instance);

            return instance;
        },

        /**
         * 初始化 DOM 元素
         * @param {object} instance - 聊天區域實例
         */
        initDOMElements: function (instance) {
            instance.chatHeader = instance.container.find('#chat-header');
            instance.chatMessages = instance.container.find('#chat-messages');
            instance.messageInput = instance.container.find('#message-input');
            instance.sendButton = instance.container.find('#send-button');
            instance.inputForm = instance.container.find('#chat-input-form');
        },

        /**
         * 初始化功能模組
         * @param {object} instance - 聊天區域實例
         */
        initModules: function (instance) {
            // 初始化各個功能模組
            ChatAreaMessages.init(instance);
            ChatAreaInput.init(instance);
            ChatAreaUI.init(instance);

            // 初始化商品傳送模組 (如果已載入)
            if (typeof ChatAreaProduct !== 'undefined') {
                ChatAreaProduct.init(instance);
            }

            // 初始化範本模組 (如果已載入)
            if (typeof ChatAreaTemplate !== 'undefined') {
                ChatAreaTemplate.init(instance);
            }

            // 設置拖曳上傳
            ChatAreaInput.setupDragDropUpload(instance);
        },

        /**
         * 綁定事件監聽器
         * @param {object} instance - 聊天區域實例
         */
        bindEvents: function (instance) {
            // 訊息輸入相關
            instance.messageInput.on('input', (e) => ChatAreaInput.handleMessageInput(instance, e));
            instance.messageInput.on('keydown', (e) => ChatAreaInput.handleMessageKeydown(instance, e));
            instance.sendButton.on('click', () => ChatAreaInput.handleSendMessage(instance));
            instance.inputForm.on('submit', (e) => ChatAreaInput.handleFormSubmit(instance, e));

            // 監聽好友選擇事件
            $(document).on('friend:selected', (event, friendData) => this.handleFriendSelected(instance, friendData));

            // 監聽訊息容器的捲動事件（無限捲動）
            instance.chatMessages.on('scroll', () => ChatAreaMessages.handleMessagesScroll(instance));

            // 監聽輪詢新訊息事件
            $(document).on('polling:new-messages', (event, newMessages) => ChatAreaMessages.handlePollingNewMessages(instance, newMessages));

            // 文件上傳相關
            instance.container.find('#image-upload-btn').on('click', () => ChatAreaInput.handleImageUploadClick(instance));
            instance.container.find('#image-upload-input').on('change', (e) => ChatAreaInput.handleImageUploadChange(instance, e));

            // 防止頁面預設的拖曳行為
            $(document).on('dragover drop', function (e) {
                e.preventDefault();
            });
        },

        /**
         * 處理好友選擇
         * @param {object} instance - 聊天區域實例
         * @param {object} friendData - 好友資料物件
         */
        handleFriendSelected: function (instance, friendData) {
            this.loadFriendChat(instance, friendData);
        },

        /**
         * 載入好友聊天記錄
         * @param {object} instance - 聊天區域實例
         * @param {object} friendData - 好友資料物件
         */
        loadFriendChat: function (instance, friendData) {

            instance.currentFriendId = friendData.id;
            instance.currentLineUserId = friendData.line_user_id;
            instance.currentFriend = friendData;
            instance.messages = [];
            instance.hasMoreMessages = true;
            instance.oldestMessageDate = null;

            // 設置 source_type 和 group_id (用於群組訊息傳送).
            instance.currentSourceType = friendData.source_type || '';
            instance.currentGroupId = friendData.group_id || '';

            // 重置跳轉模式狀態.
            instance.isJumpedMode = false;
            instance.referenceTimestamp = null;
            instance.hasMoreMessagesBefore = true;
            instance.hasMoreMessagesAfter = true;
            instance.targetMessageIndex = null;

            this.showChatArea(instance);
            this.updateChatHeader(instance, friendData);
            ChatAreaUI.showLoadingMessages(instance.chatMessages);
            ChatAreaInput.enableInput(instance);

            // 通知範本模組好友選擇改變.
            if (typeof ChatAreaTemplate !== 'undefined') {
                ChatAreaTemplate.onFriendChanged(friendData.id);
            }

            // 載入初始訊息.
            ChatAreaMessages.loadMessages(instance, false);
        },

        /**
         * 顯示無選擇狀態
         * @param {object} instance - 聊天區域實例
         */
        showNoSelectionState: function (instance) {
            ChatAreaUI.showNoSelectionState(instance.chatHeader, instance.chatMessages);
            ChatAreaInput.disableInput(instance);

            // 通知範本模組隱藏範本區域
            if (typeof ChatAreaTemplate !== 'undefined') {
                ChatAreaTemplate.onFriendChanged(null);
            }
        },

        /**
         * 顯示聊天區域
         * @param {object} instance - 聊天區域實例
         */
        showChatArea: function (instance) {
            instance.container.removeClass('no-friend-selected').addClass('friend-selected');
        },

        /**
         * 更新聊天標題
         * @param {object} instance - 聊天區域實例
         * @param {object} friend - 好友資料
         */
        updateChatHeader: function (instance, friend) {
            const html = `
                <div class="chat-header-info">
                    <div class="friend-avatar" style="display: flex; align-items: center;">
                        <img style="width:44px; margin-right: 5px; border-radius:100%" loading="lazy" decoding="async" src="${friend.avatar || ChatAreaUtils.getDefaultAvatar()}" />
                        <p class="friend-name">${ChatAreaUtils.escapeHtml(friend.name || '未知好友')}</p>
                    </div>
                </div>
            `;
            instance.chatHeader.html(html);
        },

        /**
         * 同步狀態 (供外部元件調用)
         * @param {object} instance - 聊天區域實例
         * @param {object} state - 狀態物件
         */
        syncState: function (instance, state) {
            if (state.currentFriend && state.currentFriend !== instance.currentFriendId) {
                // 好友切換，清除新訊息通知
                instance.chatMessages.find('.new-message-notification').remove();
            }

            // 從 friendData 中取得 source_type 和 group_id
            if (state.friendData) {
                instance.currentSourceType = state.friendData.source_type || state.friendData.sourceType || '';
                instance.currentGroupId = state.friendData.group_id || state.friendData.groupId || '';
            }
        },

        /**
         * 銷毀元件
         * @param {object} instance - 聊天區域實例
         */
        destroy: function (instance) {
            instance.messageInput.off();
            instance.sendButton.off();
            instance.inputForm.off();
            instance.container.find('#image-upload-btn').off();
            instance.container.find('#image-upload-input').off();
            $(document).off('friend:selected');
            $(document).off('polling:new-messages');
        }
    };

})(jQuery);