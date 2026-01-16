/**
 * OrderChatz 聊天區域元件 (模組化重構版)
 *
 * 管理中間聊天對話的顯示和訊息輸入功能
 * 此檔案為入口檔案，載入所有相關模組並提供統一的 API 介面
 *
 * @package OrderChatz
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    // 確保所有依賴模組已載入
    if (typeof ChatAreaCore === 'undefined' ||
        typeof ChatAreaMessages === 'undefined' ||
        typeof ChatAreaInput === 'undefined' ||
        typeof ChatAreaUI === 'undefined' ||
        typeof ChatAreaUtils === 'undefined') {
        console.error('ChatAreaComponent: 缺少必要的依賴模組');
        return;
    }

    /**
     * 聊天區域元件 (向後相容性包裝器)
     * 
     * 這個類別保持與原始 API 的相容性，同時使用新的模組化架構
     */
    window.ChatAreaComponent = function (containerSelector) {
        // 使用核心模組創建實例
        this.instance = ChatAreaCore.createInstance(containerSelector);
        
        // 為了向後相容性，將實例屬性直接暴露到 this
        this.container = this.instance.container;
        this.chatHeader = this.instance.chatHeader;
        this.chatMessages = this.instance.chatMessages;
        this.messageInput = this.instance.messageInput;
        this.sendButton = this.instance.sendButton;
        this.inputForm = this.instance.inputForm;
        this.currentFriendId = this.instance.currentFriendId;
        this.currentLineUserId = this.instance.currentLineUserId;
        this.messages = this.instance.messages;
        this.currentFriend = this.instance.currentFriend;
        this.isComposing = this.instance.isComposing;
        this.isLoadingMessages = this.instance.isLoadingMessages;
        this.hasMoreMessages = this.instance.hasMoreMessages;
        this.oldestMessageDate = this.instance.oldestMessageDate;

        // 設置全域實例引用 (為了圖片燈箱等功能)
        window.chatAreaInstance = this;
    };

    // 為了向後相容性，保持原始的 prototype 方法
    ChatAreaComponent.prototype = {
        /**
         * 初始化元件 (向後相容性)
         */
        init: function () {
            // 新架構中初始化在 constructor 中完成
        },

        /**
         * 綁定事件監聽器 (向後相容性)
         */
        bindEvents: function () {
            // 新架構中事件綁定在 constructor 中完成
        },

        /**
         * 處理好友選擇 (向後相容性)
         */
        handleFriendSelected: function (event, friendData) {
            ChatAreaCore.handleFriendSelected(this.instance, friendData);
            this.syncInstanceProperties();
        },

        /**
         * 載入好友聊天記錄 (向後相容性)
         */
        loadFriendChat: function (friendData) {
            ChatAreaCore.loadFriendChat(this.instance, friendData);
            this.syncInstanceProperties();
        },

        /**
         * 顯示無選擇狀態 (向後相容性)
         */
        showNoSelectionState: function () {
            ChatAreaCore.showNoSelectionState(this.instance);
        },

        /**
         * 顯示聊天區域 (向後相容性)
         */
        showChatArea: function () {
            ChatAreaCore.showChatArea(this.instance);
        },

        /**
         * 顯示載入中的訊息 (向後相容性)
         */
        showLoadingMessages: function () {
            ChatAreaUI.showLoadingMessages(this.instance.chatMessages);
        },

        /**
         * 更新聊天標題 (向後相容性)
         */
        updateChatHeader: function (friend) {
            ChatAreaCore.updateChatHeader(this.instance, friend);
        },

        /**
         * 滾動到底部 (向後相容性)
         */
        scrollToBottom: function () {
            ChatAreaUI.scrollToBottom(this.instance.chatMessages);
        },

        /**
         * 顯示圖片燈箱 (向後相容性)
         */
        showImageLightbox: function(imageUrl) {
            ChatAreaUI.showImageLightbox(imageUrl);
        },

        /**
         * HTML 跳脫 (向後相容性)
         */
        escapeHtml: function (text) {
            return ChatAreaUtils.escapeHtml(text);
        },

        /**
         * 取得目前使用者頭像 (向後相容性)
         */
        getCurrentUserAvatar: function () {
            return ChatAreaUtils.getCurrentUserAvatar();
        },

        /**
         * 取得預設頭像 (向後相容性)
         */
        getDefaultAvatar: function () {
            return ChatAreaUtils.getDefaultAvatar();
        },

        /**
         * 啟用輸入 (向後相容性)
         */
        enableInput: function () {
            ChatAreaInput.enableInput(this.instance);
        },

        /**
         * 禁用輸入 (向後相容性)
         */
        disableInput: function () {
            ChatAreaInput.disableInput(this.instance);
        },

        /**
         * 載入訊息 (向後相容性)
         */
        loadMessages: function (loadMore = false) {
            ChatAreaMessages.loadMessages(this.instance, loadMore);
            this.syncInstanceProperties();
        },

        /**
         * 同步狀態 (向後相容性)
         */
        syncState: function (state) {
            ChatAreaCore.syncState(this.instance, state);
        },

        /**
         * 銷毀元件 (向後相容性)
         */
        destroy: function () {
            ChatAreaCore.destroy(this.instance);
        },

        /**
         * 同步實例屬性 (內部使用)
         * 確保外部代碼可以訪問到最新的狀態
         */
        syncInstanceProperties: function () {
            this.currentFriendId = this.instance.currentFriendId;
            this.currentLineUserId = this.instance.currentLineUserId;
            this.messages = this.instance.messages;
            this.currentFriend = this.instance.currentFriend;
            this.isComposing = this.instance.isComposing;
            this.isLoadingMessages = this.instance.isLoadingMessages;
            this.hasMoreMessages = this.instance.hasMoreMessages;
            this.oldestMessageDate = this.instance.oldestMessageDate;
        }
    };

})(jQuery);