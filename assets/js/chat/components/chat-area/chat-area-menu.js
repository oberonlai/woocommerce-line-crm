/**
 * OrderChatz 聊天區域功能選單模組
 *
 * 管理聊天泡泡的功能選單，包含回覆等操作
 *
 * @package OrderChatz
 * @since 1.0.4
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域功能選單管理器
     */
    window.ChatAreaMenu = {
        /**
         * 綁定訊息功能選單事件
         * @param {object} chatArea - 聊天區域實例
         */
        bindMessageActionsEvents: function (chatArea) {
            // 綁定功能選單觸發按鈕點擊事件
            chatArea.chatMessages.off('click.messageActions', '.message-actions-trigger')
                .on('click.messageActions', '.message-actions-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const $trigger = $(this);
                    const $menu = $trigger.siblings('.message-actions-menu');
                    const $messageRow = $trigger.closest('.message-row');

                    // 關閉其他開啟的選單
                    chatArea.chatMessages.find('.message-actions-menu').not($menu).removeClass('show');

                    // 切換當前選單
                    $menu.toggleClass('show');
                });

            // 綁定功能選單項目點擊事件
            chatArea.chatMessages.off('click.messageActionItem', '.message-action-item')
                .on('click.messageActionItem', '.message-action-item', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const $item = $(e.currentTarget);
                    const action = $item.data('action');
                    const $messageRow = $item.parents('.message-row').first();
                    const messageId = $messageRow.data('message-id');
                    const messageData = chatArea.messages.find(msg => msg.id === String(messageId));

                    // 關閉選單
                    $item.closest('.message-actions-menu').removeClass('show');

                    // 執行對應的動作
                    switch (action) {
                        case 'reply':
                            this.handleReplyAction(chatArea, messageData);
                            break;
                        case 'add-note':
                            this.handleAddNoteAction(chatArea, messageData);
                            break;
                    }
                }.bind(this));

            // 點擊其他地方關閉選單
            $(document).off('click.messageActionsOutside')
                .on('click.messageActionsOutside', function (e) {
                    if (!$(e.target).closest('.message-actions').length) {
                        chatArea.chatMessages.find('.message-actions-menu').removeClass('show');
                    }
                });
        },

        /**
         * 處理回覆動作
         * @param {object} chatArea - 聊天區域實例
         * @param {object} messageData - 要回覆的訊息資料
         */
        handleReplyAction: function (chatArea, messageData) {
            if (!messageData) {
                console.error('ChatAreaMenu: 無法找到要回覆的訊息資料');
                return;
            }

            // 儲存回覆的訊息資料
            chatArea.replyToMessage = messageData;

            // 顯示回覆預覽
            this.showReplyPreview(chatArea, messageData);

            // 聚焦到輸入框
            const $messageInput = $('#message-input');
            if ($messageInput.length) {
                $messageInput.focus();
            }
        },

        /**
         * 顯示回覆預覽
         * @param {object} chatArea - 聊天區域實例
         * @param {object} messageData - 要回覆的訊息資料
         */
        showReplyPreview: function (chatArea, messageData) {
            const $replyContainer = $('#reply-preview-container');
            const $senderName = $replyContainer.find('.reply-sender-name');
            const $previewText = $replyContainer.find('.reply-preview-text');

            // 設定發送者名稱
            let senderName = messageData.is_outbound ?
                (messageData.sender_name || '我') :
                (chatArea.currentFriend?.name || '好友');

            $senderName.text(senderName);

            // 格式化預覽文字
            let previewText = this.formatReplyPreviewText(messageData);
            $previewText.text(previewText);

            // 顯示回覆預覽容器
            $replyContainer.show();

            // 綁定取消按鈕事件
            this.bindReplyCancelEvent(chatArea);
        },

        /**
         * 格式化回覆預覽文字
         * @param {object} messageData - 訊息資料
         * @returns {string} 格式化後的預覽文字
         */
        formatReplyPreviewText: function (messageData) {
            let text = '';

            switch (messageData.message_type) {
                case 'image':
                    text = '📷 圖片';
                    break;
                case 'sticker':
                    text = '🎭 貼圖';
                    break;
                case 'file':
                     const parsedFileContent = this.parseMessageContent(messageData.content);
                     text = '📁 ' + (parsedFileContent?.file_name || '檔案');
                     break;
                case 'text':
                default:
                    text = messageData.content || '';
                    // 移除 HTML 標籤並限制長度.
                    text = text.replace(/<[^>]*>/g, '').trim();
                    if (text.length > 50) {
                        text = text.substring(0, 50) + '...';
                    }
                    break;
            }

            return text;
        },

        /**
         * 綁定回覆取消按鈕事件
         * @param {object} chatArea - 聊天區域實例
         */
        bindReplyCancelEvent: function (chatArea) {
            const $cancelBtn = $('#reply-preview-container .reply-cancel-btn');

            $cancelBtn.off('click.replyCancel').on('click.replyCancel', () => {
                this.hideReplyPreview(chatArea);
            });
        },

        /**
         * 隱藏回覆預覽
         * @param {object} chatArea - 聊天區域實例
         */
        hideReplyPreview: function (chatArea) {
            const $replyContainer = $('#reply-preview-container');
            $replyContainer.hide();

            // 清除回覆資料
            chatArea.replyToMessage = null;

            // 移除取消按鈕事件
            const $cancelBtn = $replyContainer.find('.reply-cancel-btn');
            $cancelBtn.off('click.replyCancel');
        },

        /**
         * 取得當前回覆的訊息資料（供發送訊息時使用）
         * @param {object} chatArea - 聊天區域實例
         * @returns {object|null} 回覆的訊息資料
         */
        getCurrentReplyData: function (chatArea) {
            return chatArea.replyToMessage || null;
        },

        /**
         * 解析訊息內容 JSON
         * @param {string} content - 訊息內容
         * @returns {object|null} 解析後的內容或 null
         */
        parseMessageContent: function (content) {
            if (!content || typeof content !== 'string') {
                return null;
            }

            try {
                // 嘗試解析 JSON
                return JSON.parse(content);
            } catch (e) {
                return null;
            }
        },

        /**
         * 從訊息資料中提取媒體資訊
         * @param {object} messageData - 原始訊息資料
         * @returns {object} 提取的媒體資訊
         */
        extractMediaInfo: function (messageData) {
            const messageType = messageData.message_type;
            const content = messageData.content;

            // 嘗試解析 JSON 內容
            const parsedContent = this.parseMessageContent(content);

            const mediaInfo = {
                preview_url: null,
                image_url: null,
                file_url: null,
                file_name: null,
                sticker_url: null,
                video_url: null,
                original_content: content
            };

            if (parsedContent) {
                switch (messageType) {
                    case 'image':
                        mediaInfo.image_url = parsedContent.originalContentUrl || null;
                        mediaInfo.preview_url = parsedContent.previewImageUrl || parsedContent.originalContentUrl || null;
                        break;

                    case 'sticker':
                        mediaInfo.sticker_url = parsedContent.originalContentUrl || null;
                        break;

                    case 'file':
                        mediaInfo.file_url = parsedContent.originalContentUrl || null;
                        mediaInfo.file_name = parsedContent.fileName || null;
                        break;

                    case 'video':
                        mediaInfo.video_url = parsedContent.originalContentUrl || null;
                        mediaInfo.file_name = parsedContent.fileName || null;
                        mediaInfo.preview_url = parsedContent.previewImageUrl || null;
                        break;
                }
            } else if (messageType === 'text') {
                // 文字訊息直接使用 content
                mediaInfo.original_content = content;
            }

            return mediaInfo;
        },

        /**
         * 處理新增備註動作
         * @param {object} chatArea - 聊天區域實例
         * @param {object} messageData - 要關聯的訊息資料
         */
        handleAddNoteAction: function (chatArea, messageData) {
            if (!messageData) {
                console.error('ChatAreaMenu: 無法找到要關聯的訊息資料');
                return;
            }

            // 提取媒體資訊
            const mediaInfo = this.extractMediaInfo(messageData);

            // 準備關聯訊息資料，格式符合後端要求
            let content = '';
            switch (messageData.message_type) {
                case 'text':
                    content = messageData.content;
                    break;
                case 'image':
                    content = mediaInfo.image_url || mediaInfo.preview_url || '';
                    break;
                case 'file':
                    content = mediaInfo.file_url || '';
                    break;
                case 'sticker':
                    content = mediaInfo.sticker_url || '';
                    break;
                case 'video':
                    content = mediaInfo.video_url || '';
                    break;
                default:
                    content = mediaInfo.original_content || messageData.content || '';
            }

            const relatedMessageData = {
                datetime: messageData.timestamp,
                content: content,
                type: messageData.message_type
            };

            // 直接建立 CustomerNotesManager 實例
            const $customerInfoPanel = $('#customer-info-panel');
            const container = $customerInfoPanel.length ? $customerInfoPanel : $('body');
            const notesManager = new CustomerNotesManager(container);

            // 設定當前好友資料
            const currentFriend = chatArea.currentFriend;
            if (currentFriend) {
                notesManager.setCurrentFriend(currentFriend);
            }

            // 調用顯示燈箱方法
            notesManager.showAddNoteLightbox(relatedMessageData);
        },

        /**
         * 清除回覆狀態（發送訊息後調用）
         * @param {object} chatArea - 聊天區域實例
         */
        clearReplyState: function (chatArea) {
            this.hideReplyPreview(chatArea);
        }
    };

})(jQuery);