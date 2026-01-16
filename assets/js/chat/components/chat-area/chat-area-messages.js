/**
 * OrderChatz 聊天區域訊息處理模組
 *
 * 管理訊息的載入、顯示、格式化等功能
 *
 * @package OrderChatz
 * @since 1.0.5
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域訊息處理管理器
     */
    window.ChatAreaMessages = {
        /**
         * 初始化訊息處理功能
         * @param {object} chatAreaInstance - 聊天區域實例
         */
        init: function (chatAreaInstance) {
            this.chatArea = chatAreaInstance;
        },

        /**
         * 載入訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {boolean} loadMore - 是否載入更多（false: 載入初始訊息, true: 載入更多）
         */
        loadMessages: function (chatArea, loadMore = false) {
            if (chatArea.isLoadingMessages || !chatArea.currentLineUserId) {
                return;
            }

            if (loadMore && !chatArea.hasMoreMessages) {
                return;
            }

            chatArea.isLoadingMessages = true;
            if (window.chatAreaInstance) {
                window.chatAreaInstance.isLoadingMessages = true;
            }

            const data = {
                action: loadMore ? 'otz_load_more_messages' : 'otz_get_messages',
                line_user_id: chatArea.currentLineUserId,
                nonce: otzChatConfig.nonce
            };

            if (loadMore) {
                ChatAreaUI.showLoadMoreIndicator(chatArea.chatMessages);
                if (chatArea.oldestMessageDate) {
                    data.before_date = chatArea.oldestMessageDate;
                }
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    chatArea.isLoadingMessages = false;
                    if (window.chatAreaInstance) {
                        window.chatAreaInstance.isLoadingMessages = false;
                    }

                    if (response.success) {
                        chatArea.hasMoreMessages = response.data.has_more;

                        if (response.data.oldest_date) {
                            chatArea.oldestMessageDate = response.data.oldest_date;
                        }

                        if (loadMore) {
                            this.prependMessages(chatArea, response.data.messages);
                            ChatAreaUI.hideLoadMoreIndicator(chatArea.chatMessages);
                        } else {
                            this.displayMessages(chatArea, response.data.messages);

                            // 檢查是否包含圖片訊息，決定捲動延遲時間
                            const hasImageMessages = response.data.messages.some(msg => msg.message_type === 'image');
                            const scrollDelay = hasImageMessages ? 500 : 200;

                            // 延遲執行以避開其他函數的干擾
                            setTimeout(() => {
                                this.ensureScrollToBottom(chatArea.chatMessages);
                            }, scrollDelay);
                        }

                    } else {
                        ChatAreaUI.showErrorMessage(chatArea.chatMessages, response.data.message || '訊息載入失敗');
                        if (loadMore) {
                            ChatAreaUI.hideLoadMoreIndicator(chatArea.chatMessages);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    chatArea.isLoadingMessages = false;
                    if (window.chatAreaInstance) {
                        window.chatAreaInstance.isLoadingMessages = false;
                    }
                    ChatAreaUI.showErrorMessage(chatArea.chatMessages, '網路連線錯誤，請稍後再試');
                    if (loadMore) {
                        ChatAreaUI.hideLoadMoreIndicator(chatArea.chatMessages);
                    }
                }
            });
        },

        /**
         * 顯示訊息列表
         * @param {object} chatArea - 聊天區域實例
         * @param {array} messages - 訊息陣列
         */
        displayMessages: function (chatArea, messages) {

            // 確保訊息按時間順序排序（舊到新）
            chatArea.messages = messages.sort((a, b) => {
                return new Date(a.timestamp) - new Date(b.timestamp);
            });

            const messagesHtml = this.renderMessages(chatArea, chatArea.messages);
            chatArea.chatMessages.html(messagesHtml);

            // 綁定引用訊息點擊事件
            this.bindQuotedMessageEvents(chatArea);

            // 清除所有臨時訊息，因為新訊息已載入
            chatArea.chatMessages.find('.message-row.temp').remove();

            // 隱藏重新載入按鈕（因為這是正常的訊息載入）
            this.hideReloadConversationButton();

            // 標記這是初始載入，需要捲動到底部
            chatArea._shouldScrollToBottom = true;

            // 通知輪詢管理器更新游標，避免重複載入
            if (chatArea.messages.length > 0) {
                const lastMessage = chatArea.messages[chatArea.messages.length - 1];
                $(document).trigger('chat:messages-loaded', [{
                    friendId: chatArea.currentFriendId,
                    lineUserId: chatArea.currentLineUserId,
                    lastMessageTime: lastMessage.timestamp,
                    messageCount: chatArea.messages.length
                }]);
            }
        },

        /**
         * 移除重複的訊息（根據訊息 ID）
         * @param {array} messages - 訊息陣列
         * @returns {array} 去重後的訊息陣列
         */
        removeDuplicateMessages: function (messages) {
            const uniqueMessages = new Map();

            messages.forEach(message => {
                const messageId = String(message.event_id);
                if (!uniqueMessages.has(messageId)) {
                    uniqueMessages.set(messageId, message);
                }
            });

            return Array.from(uniqueMessages.values());
        },

        /**
         * 在前面添加訊息（載入更多時使用）
         * @param {object} chatArea - 聊天區域實例
         * @param {array} messages - 訊息陣列
         */
        prependMessages: function (chatArea, messages) {
            if (messages && messages.length > 0) {
                // 記錄當前第一條訊息的 ID 和容器狀態
                const container = chatArea.chatMessages[0];
                const firstVisibleMessageId = chatArea.messages.length > 0 ? chatArea.messages[0].id : null;
                const wasAtTop = ChatAreaUI.isScrollAtTop(chatArea.chatMessages);

                chatArea.messages = [...messages, ...chatArea.messages];

                // 移除重複訊息
                chatArea.messages = this.removeDuplicateMessages(chatArea.messages);

                // 確保訊息按時間順序排序
                chatArea.messages.sort((a, b) => {
                    return new Date(a.timestamp) - new Date(b.timestamp);
                });

                // 重新渲染整個訊息列表，確保日期分隔器正確
                const messagesHtml = this.renderMessages(chatArea, chatArea.messages);
                chatArea.chatMessages.html(messagesHtml);

                // 綁定引用訊息點擊事件
                this.bindQuotedMessageEvents(chatArea);

                // 調整滾動位置以避免重複觸發載入
                if (firstVisibleMessageId) {
                    const targetElement = chatArea.chatMessages.find(`[data-message-id="${firstVisibleMessageId}"]`);
                    if (targetElement.length > 0) {
                        // 計算目標位置，並添加 buffer 避免立即觸發下次載入
                        const targetScrollTop = targetElement[0].offsetTop - 100;
                        const bufferDistance = wasAtTop ? 50 : 0; // 如果之前在頂部，添加額外 buffer

                        container.style.scrollBehavior = 'auto';
                        container.scrollTop = Math.max(0, targetScrollTop + bufferDistance);
                    }
                } else if (wasAtTop && messages.length > 0) {
                    // 如果沒有先前訊息但用戶之前在頂部，確保不會立即觸發下次載入
                    container.style.scrollBehavior = 'auto';
                    container.scrollTop = 50; // 添加 50px buffer
                }
            }
        },

        /**
         * 渲染訊息HTML
         * @param {object} chatArea - 聊天區域實例
         * @param {array} messages - 訊息陣列
         * @returns {string} 訊息HTML
         */
        renderMessages: function (chatArea, messages) {
            if (!messages || messages.length === 0) {
                return '<div class="no-messages"><p>尚無對話記錄</p></div>';
            }

            let html = '';
            let currentDate = '';

            messages.forEach((message) => {
                // 日期分隔線
                const messageDate = new Date(message.timestamp).toLocaleDateString();
                if (currentDate !== messageDate) {
                    currentDate = messageDate;
                    html += `<div class="date-separator">
                        <span class="date-text">${messageDate}</span>
                    </div>`;
                }

                const messageClass = message.is_outbound ? 'outgoing' : 'incoming';

                // 根據 sender_name 動態選擇頭像和顯示名稱
                let senderAvatar, senderName;
                if (message.is_outbound) {
                    // 外發訊息：根據 sender_name 選擇對應頭像
                    senderAvatar = message.sender_name ?
                        ChatAreaUtils.getSenderAvatar(message.sender_name) :
                        ChatAreaUtils.getCurrentUserAvatar();
                    senderName = message.sender_name || ChatAreaUtils.getCurrentUserDisplayName() || '我';
                } else {
                    // 內發訊息：檢查是否為群組訊息
                    if (message.group_id && message.sender_avatar_url) {
                        // 群組訊息：使用發送者個人頭像
                        senderAvatar = message.sender_avatar_url;
                        senderName = message.sender_display_name || '群組成員';
                    } else {
                        // 個人訊息：使用好友頭像
                        senderAvatar = chatArea.currentFriend.avatar || ChatAreaUtils.getDefaultAvatar();
                        senderName = chatArea.currentFriend.name || '好友';
                    }
                }

                // 根據訊息類型添加特殊的 CSS 類別
                let messageContentClass = 'message-content';
                if (message.message_type === 'sticker') {
                    messageContentClass += ' sticker-message';
                } else if (message.message_type === 'image') {
                    messageContentClass += ' image-message';
                }

                // 決定是否顯示回覆者名稱
                const shouldShowSenderName = message.is_outbound && message.sender_name &&
                    message.sender_name !== ChatAreaUtils.getCurrentUserDisplayName();

                // 渲染引用訊息（如果存在）
                const quotedMessageHtml = message.quoted_message ?
                    ChatAreaUtils.renderQuotedContent(message.quoted_message) : '';

                html += `
                    <div class="message-row ${messageClass}" data-message-id="${message.id}">
                        ${!message.is_outbound ? `<div class="message-avatar">
                            <img src="${senderAvatar}" alt="${senderName}" title="${senderName}" />
                        </div>` : ''}
                        <div class="message-bubble ${messageClass}">
                            <div class="message-actions">
                                <button class="message-actions-trigger" type="button" title="更多選項">
                                    <span class="actions-dots">⋯</span>
                                </button>
                                <div class="message-actions-menu">
                                   <div class="message-action-item" data-action="reply">回覆</div>
                                    <div class="message-action-item" data-action="add-note">新增備註</div>
                                </div>
                            </div>
                            <div class="${messageContentClass}">
                                ${quotedMessageHtml}
                                <div class="message-text">${ChatAreaUtils.formatMessageContent(message.content, message.message_type)}</div>
                                 <span class="message-time" style="position:absolute;bottom:-18px;white-space:nowrap">${message.formatted_time}</span>
                            </div>
                        </div>
                        ${message.is_outbound ? `<div class="message-avatar">
                            <img src="${senderAvatar}" alt="${senderName}" title="${senderName}" />
                        </div>` : ''}
                    </div>
                `;
            });

            return html;
        },

        /**
         * 處理輪詢新訊息事件
         * @param {object} chatArea - 聊天區域實例
         * @param {array} newMessages - 新訊息列表
         */
        handlePollingNewMessages: function (chatArea, newMessages) {
            if (chatArea._isJumpRendering) {
                console.log('[ChatAreaMessages] 正在跳轉渲染中，跳過新訊息處理');
                return;
            }
            if (!Array.isArray(newMessages) || newMessages.length === 0) {
                console.log('[ChatAreaMessages] 無新訊息或格式錯誤，跳過處理');
                return;
            }

            // 只處理當前聊天對象的訊息（個人好友或群組）.
            const isGroupChat = chatArea.currentGroupId && chatArea.currentGroupId !== '';
            const isPersonalChat = chatArea.currentLineUserId && chatArea.currentLineUserId !== '';

            if (!isGroupChat && !isPersonalChat) {
                return;
            }

            // 過濾出當前聊天對象的訊息.
            const currentFriendMessages = newMessages.filter(msg => {
                if (isGroupChat) {
                    // 群組聊天：比對 group_id.
                    return msg.group_id === chatArea.currentGroupId;
                } else {
                    // 個人聊天：比對 line_user_id 且排除群組訊息.
                    return msg.line_user_id === chatArea.currentLineUserId &&
                           (!msg.group_id || msg.group_id === '');
                }
            });

            if (currentFriendMessages.length === 0) {
                return;
            }

            // 強化去重邏輯：使用 ID 和內容雙重檢查
            const existingMessageIds = chatArea.messages.map(msg => msg.id);
            const existingMessageContents = chatArea.messages.map(msg => `${msg.content}_${msg.timestamp}_${msg.is_outbound}`);

            const uniqueNewMessages = currentFriendMessages.filter(msg => {
                const isDuplicateId = existingMessageIds.includes(msg.id);
                const contentKey = `${msg.content}_${msg.timestamp}_${msg.is_outbound}`;
                const isDuplicateContent = existingMessageContents.includes(contentKey);

                // 只要 ID 或內容重複就視為重複訊息
                const isDuplicate = isDuplicateId || isDuplicateContent;


                return !isDuplicate;
            });


            if (uniqueNewMessages.length === 0) {

                return;
            }

            // 暫時禁用訊息捲動檢測，避免重新渲染過程中意外觸發載入更多訊息
            chatArea._isRerenderingMessages = true;

            // 將新訊息添加到現有訊息列表
            chatArea.messages = [...chatArea.messages, ...uniqueNewMessages];

            // 確保訊息按時間順序排序
            chatArea.messages.sort((a, b) => {
                return new Date(a.timestamp) - new Date(b.timestamp);
            });

            // 重新渲染整個訊息列表，但禁用自動捲動以避免衝突
            const scrollState = ChatAreaUI.rerenderAllMessages(
                chatArea.chatMessages,
                this.renderMessages.bind(this, chatArea),
                chatArea.messages,
                false  // 禁用自動捲動
            );

            // 延遲執行統一捲動處理，確保 DOM 更新完成
            requestAnimationFrame(() => {
                // 重新啟用訊息捲動檢測
                chatArea._isRerenderingMessages = false;

                this.bindQuotedMessageEvents(chatArea)

                // 如果用戶在底部，使用強化版捲動方法確保到底部；否則顯示新訊息提示
                if (scrollState.wasAtBottom && !chatArea.isJumpedMode) {
                    // 檢查新訊息中是否包含圖片
                    const hasImageMessages = uniqueNewMessages.some(msg => msg.message_type === 'image');

                    if (hasImageMessages) {
                        // 如果包含圖片訊息，使用增強版捲動方法，增加延遲確保圖片開始載入
                        setTimeout(() => {
                            this.ensureScrollToBottom(chatArea.chatMessages);
                        }, 300); // 增加延遲時間確保圖片開始載入
                    } else {
                        // 普通訊息，正常捲動
                        this.ensureScrollToBottom(chatArea.chatMessages);
                    }
                } else {
                    ChatAreaUI.showNewMessageNotification(chatArea.chatMessages, uniqueNewMessages.length);
                }
            });
        },

        /**
         * 處理訊息容器捲動事件（無限捲動）
         * @param {object} chatArea - 聊天區域實例
         */
        handleMessagesScroll: function (chatArea) {
            const container = chatArea.chatMessages[0];
            if (!container) {
                return;
            }

            // 如果正在重新渲染訊息，跳過處理避免意外觸發
            if (chatArea._isRerenderingMessages) {
                return;
            }

            // 當捲動到底部時標記訊息為已讀
            if (ChatAreaUI.isScrollAtBottom(chatArea.chatMessages)) {
                this.markMessagesAsRead(chatArea);

                // 跳轉模式下，檢查是否需要載入更新的訊息
                if (chatArea.isJumpedMode &&
                    chatArea.hasMoreMessagesAfter &&
                    (!chatArea._lastLoadMoreTime || (Date.now() - chatArea._lastLoadMoreTime > 1000))) {
                    chatArea._lastLoadMoreTime = Date.now();
                    this.loadMessagesFromTimestamp(chatArea, 'after', 10);
                }
            }

            // 當捲動到頂部時載入更多舊訊息
            if (ChatAreaUI.isScrollAtTop(chatArea.chatMessages) &&
                !chatArea.isLoadingMessages &&
                !chatArea._isRerenderingMessages &&
                chatArea.currentLineUserId &&
                // 加入時間限制，避免頻繁觸發
                (!chatArea._lastLoadMoreTime || (Date.now() - chatArea._lastLoadMoreTime > 1000))) {
                chatArea._lastLoadMoreTime = Date.now();

                // 根據模式選擇載入方法
                if (chatArea.isJumpedMode) {
                    // 跳轉模式：使用時間戳基礎的載入
                    if (chatArea.hasMoreMessagesBefore) {
                        this.loadMessagesFromTimestamp(chatArea, 'before', 10);
                    }
                } else {
                    // 正常模式：使用原有的載入方法
                    if (chatArea.hasMoreMessages) {
                        this.loadMessages(chatArea, true);
                    }
                }
            }
        },

        /**
         * 標記訊息為已讀（當用戶滾動到底部時）
         * @param {object} chatArea - 聊天區域實例
         */
        markMessagesAsRead: function (chatArea) {
            if (ChatAreaUI.isScrollAtBottom(chatArea.chatMessages) && chatArea.currentLineUserId) {
                // 觸發已讀事件給好友列表組件
                $(document).trigger('messages:marked-as-read', [chatArea.currentFriendId]);

                // 直接同步到後端資料庫
                this.syncReadStatusToServer(chatArea.currentLineUserId);
            }
        },

        /**
         * 同步已讀狀態到伺服器
         * @param {string} lineUserId - LINE 使用者 ID
         */
        syncReadStatusToServer: function (lineUserId) {
            if (!lineUserId) {
                return;
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_mark_messages_read',
                    line_user_id: lineUserId,
                    nonce: otzChatConfig.nonce
                },
                success: (response) => {
                    if (!response.success) {
                        console.error('ChatAreaMessages: 同步已讀狀態失敗', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('ChatAreaMessages: 同步已讀狀態網路錯誤', error);
                }
            });
        },

        /**
         * 確保捲動到底部（多重嘗試，支援圖片載入檢測）
         * @param {jQuery} chatMessages - 聊天訊息容器
         */
        ensureScrollToBottom: function (chatMessages) {
            const maxAttempts = 15; // 增加最大重試次數，應對圖片載入
            let attempt = 0;

            // 檢查是否有正在載入的圖片
            const hasLoadingImages = () => {
                const images = chatMessages.find('img');
                for (let i = 0; i < images.length; i++) {
                    const img = images[i];
                    if (!img.complete || img.naturalHeight === 0) {
                        return true;
                    }
                }
                return false;
            };

            const tryScroll = () => {
                attempt++;
                const container = chatMessages[0];

                // 執行捲動
                const targetScrollTop = container.scrollHeight - container.clientHeight;

                // 使用更強制的方法
                container.style.scrollBehavior = 'auto';
                container.scrollTop = targetScrollTop;

                // 使用 requestAnimationFrame 確保在下一個渲染幀執行
                requestAnimationFrame(() => {
                    container.scrollTop = targetScrollTop;

                    // 再次使用 requestAnimationFrame 進行第二次確認
                    requestAnimationFrame(() => {
                        container.scrollTop = targetScrollTop;

                        // 驗證捲動是否成功
                        setTimeout(() => {
                            const currentScrollDiff = Math.abs(container.scrollTop - targetScrollTop);
                            const shouldRetry = currentScrollDiff > 10 && attempt < maxAttempts;

                            // 如果有圖片正在載入，延長重試間隔
                            const hasImages = hasLoadingImages();
                            const retryDelay = hasImages ? 500 : 200;

                            if (shouldRetry) {
                                // 如果有圖片載入中，設置圖片載入監聽器
                                if (hasImages && attempt < 8) {
                                    this.waitForImagesAndScroll(chatMessages, tryScroll);
                                } else {
                                    setTimeout(tryScroll, retryDelay);
                                }
                            }
                        }, hasLoadingImages() ? 300 : 100);
                    });
                });
            };

            tryScroll();
        },

        /**
         * 等待圖片載入完成後執行捲動
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @param {Function} scrollCallback - 捲動回調函數
         */
        waitForImagesAndScroll: function (chatMessages, scrollCallback) {
            const images = chatMessages.find('img').not(':data(scroll-watched)');

            if (images.length === 0) {
                scrollCallback();
                return;
            }

            let loadedCount = 0;
            const totalImages = images.length;

            const onImageLoadOrError = () => {
                loadedCount++;
                if (loadedCount >= totalImages) {
                    // 所有圖片載入完成，延遲一點再捲動確保渲染完成
                    setTimeout(scrollCallback, 100);
                }
            };

            images.each(function () {
                const $img = $(this);
                $img.data('scroll-watched', true);

                if (this.complete) {
                    onImageLoadOrError();
                } else {
                    $img.on('load error', onImageLoadOrError);

                    // 設置超時，避免無限等待
                    setTimeout(onImageLoadOrError, 2000);
                }
            });
        },

        /**
         * 跳轉到引用訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {string} targetTimestamp - 目標時間戳
         * @param {number} contextSize - 上下文訊息數量（預設5）
         */
        jumpToQuotedMessage: function (chatArea, targetTimestamp, contextSize = 5) {
            if (chatArea.isLoadingMessages || !chatArea.currentLineUserId || !targetTimestamp) {
                return;
            }

            chatArea.isLoadingMessages = true;
            if (window.chatAreaInstance) {
                window.chatAreaInstance.isLoadingMessages = true;
            }

            // 標記為跳轉模式
            chatArea.isJumpedMode = true;
            chatArea.referenceTimestamp = targetTimestamp;

            const data = {
                action: 'otz_jump_to_quoted_message',
                line_user_id: chatArea.currentLineUserId,
                target_timestamp: targetTimestamp,
                context_size: contextSize,
                nonce: otzChatConfig.nonce
            };

            // 顯示載入指示器
            ChatAreaUI.showLoadingMessages(chatArea.chatMessages);

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    chatArea.isLoadingMessages = false;
                    if (window.chatAreaInstance) {
                        window.chatAreaInstance.isLoadingMessages = false;
                    }

                    if (response.success) {
                        this.displayJumpedMessages(chatArea, response.data, targetTimestamp);
                    } else {
                        ChatAreaUI.showErrorMessage(chatArea.chatMessages, response.data.message || '跳轉到引用訊息失敗');
                        // 跳轉失敗，退出跳轉模式
                        chatArea.isJumpedMode = false;
                        chatArea.referenceTimestamp = null;
                    }
                },
                error: (xhr, status, error) => {
                    chatArea.isLoadingMessages = false;
                    if (window.chatAreaInstance) {
                        window.chatAreaInstance.isLoadingMessages = false;
                    }
                    ChatAreaUI.showErrorMessage(chatArea.chatMessages, '網路連線錯誤，請稍後再試');
                    // 跳轉失敗，退出跳轉模式
                    chatArea.isJumpedMode = false;
                    chatArea.referenceTimestamp = null;
                    this.hideReloadConversationButton();
                }
            });
        },

        /**
         * 顯示跳轉後的訊息列表
         * @param {object} chatArea - 聊天區域實例
         * @param {object} responseData - 後端回應資料
         * @param {string} targetTimestamp - 目標時間戳
         */
        displayJumpedMessages: function (chatArea, responseData, targetTimestamp) {
            chatArea._isJumpRendering = true;
            const messages = responseData.messages || [];

            if (messages.length === 0) {
                ChatAreaUI.showErrorMessage(chatArea.chatMessages, '找不到相關訊息');
                chatArea.isJumpedMode = false;
                chatArea.referenceTimestamp = null;
                this.hideReloadConversationButton();
                return;
            }

            // 確保訊息按時間順序排序（舊到新）
            chatArea.messages = messages.sort((a, b) => {
                return new Date(a.timestamp) - new Date(b.timestamp);
            });

            // 更新跳轉模式的狀態
            chatArea.hasMoreMessagesBefore = responseData.has_more_before;
            chatArea.hasMoreMessagesAfter = responseData.has_more_after;
            chatArea.targetMessageIndex = responseData.target_index;

            const messagesHtml = this.renderMessages(chatArea, chatArea.messages);
            chatArea.chatMessages.html(messagesHtml);

            // 標記目標訊息並高亮顯示
            this.highlightTargetMessage(chatArea, targetTimestamp);

            // 滾動到目標訊息位置
            this.scrollToTargetMessage(chatArea, targetTimestamp);

            // 綁定引用訊息點擊事件
            this.bindQuotedMessageEvents(chatArea);

            // 清除所有臨時訊息
            chatArea.chatMessages.find('.message-row.temp').remove();

            // 顯示重新載入對話串按鈕
            this.showReloadConversationButton(chatArea);

            setTimeout(() => {
                chatArea._isJumpRendering = false;
            }, 200);
        },

        /**
         * 高亮目標訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {string} targetTimestamp - 目標時間戳
         */
        highlightTargetMessage: function (chatArea, targetTimestamp) {
            // 找到目標訊息元素
            const targetMessages = chatArea.messages.filter(msg => msg.timestamp === targetTimestamp);
            if (targetMessages.length === 0) return;

            // 如果有多個相同時間戳的訊息，取 ID 最大的那一個
            const targetMessage = targetMessages.reduce((prev, curr) => {
                return (parseInt(curr.id) > parseInt(prev.id)) ? curr : prev;
            });

            const $targetElement = chatArea.chatMessages.find(`[data-message-id="${targetMessage.id}"]`);

            if ($targetElement.length > 0) {
                // 添加高亮樣式
                $targetElement.addClass('target-message-highlight');

                // 3秒後移除高亮效果
                setTimeout(() => {
                    $targetElement.removeClass('target-message-highlight');
                }, 2000);
            }
        },

        /**
         * 滾動到目標訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {string} targetTimestamp - 目標時間戳
         */
        scrollToTargetMessage: function (chatArea, targetTimestamp) {
            // 找到目標訊息元素
            const targetMessages = chatArea.messages.filter(msg => msg.timestamp === targetTimestamp);
            if (targetMessages.length === 0) return;

            const targetMessage = targetMessages.reduce((prev, curr) => {
                return (parseInt(curr.id) > parseInt(prev.id)) ? curr : prev;
            });

            const $targetElement = chatArea.chatMessages.find(`[data-message-id="${targetMessage.id}"]`);

            if ($targetElement.length > 0) {
                // 延遲一點確保 DOM 更新完成
                setTimeout(() => {
                    const container = chatArea.chatMessages[0];
                    const targetTop = $targetElement[0].offsetTop;
                    const containerHeight = container.clientHeight;
                    const scrollTop = targetTop - (containerHeight / 2) + ($targetElement[0].offsetHeight / 2);

                    // 平滑滾動到目標位置（居中）
                    container.style.scrollBehavior = 'smooth';
                    container.scrollTop = Math.max(0, scrollTop);
                }, 300);
            }
        },

        /**
         * 基於時間戳載入更多訊息（跳轉模式專用）
         * @param {object} chatArea - 聊天區域實例
         * @param {string} direction - 載入方向 ('before' 或 'after')
         * @param {number} limit - 載入數量限制
         */
        loadMessagesFromTimestamp: function (chatArea, direction = 'before', limit = 10) {
            if (chatArea.isLoadingMessages || !chatArea.currentLineUserId || !chatArea.referenceTimestamp) {
                return;
            }

            // 檢查是否還有更多訊息
            if (direction === 'before' && !chatArea.hasMoreMessagesBefore) {
                return;
            }
            if (direction === 'after' && !chatArea.hasMoreMessagesAfter) {
                return;
            }

            chatArea.isLoadingMessages = true;
            if (window.chatAreaInstance) {
                window.chatAreaInstance.isLoadingMessages = true;
            }

            const data = {
                action: 'otz_load_messages_from_timestamp',
                line_user_id: chatArea.currentLineUserId,
                reference_timestamp: chatArea.referenceTimestamp,
                direction: direction,
                limit: limit,
                nonce: otzChatConfig.nonce
            };

            ChatAreaUI.showLoadMoreIndicator(chatArea.chatMessages);

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    chatArea.isLoadingMessages = false;
                    if (window.chatAreaInstance) {
                        window.chatAreaInstance.isLoadingMessages = false;
                    }

                    if (response.success) {
                        const messages = response.data.messages || [];
                        const hasMore = response.data.has_more;

                        // 更新狀態
                        if (direction === 'before') {
                            chatArea.hasMoreMessagesBefore = hasMore;
                            ChatAreaUI.hideLoadMoreIndicator(chatArea.chatMessages);
                        } else {
                            chatArea.hasMoreMessagesAfter = hasMore;
                        }

                        if (messages.length > 0) {
                            // 更新參考時間戳為新載入的訊息範圍
                            if (direction === 'before') {
                                this.prependMessages(chatArea, messages);
                                // 更新參考時間戳為最舊訊息的時間戳
                                chatArea.referenceTimestamp = response.data.oldest_timestamp;
                            } else {
                                this.appendMessages(chatArea, messages);
                                // 更新參考時間戳為最新訊息的時間戳
                                chatArea.referenceTimestamp = response.data.newest_timestamp;
                            }
                        }
                    } else {
                        ChatAreaUI.showErrorMessage(chatArea.chatMessages, response.data.message || '訊息載入失敗');
                        if (direction === 'before') {
                            ChatAreaUI.hideLoadMoreIndicator(chatArea.chatMessages);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    chatArea.isLoadingMessages = false;
                    if (window.chatAreaInstance) {
                        window.chatAreaInstance.isLoadingMessages = false;
                    }
                    ChatAreaUI.showErrorMessage(chatArea.chatMessages, '網路連線錯誤，請稍後再試');
                    ChatAreaUI.hideLoadMoreIndicator(chatArea.chatMessages);

                }
            });
        },

        /**
         * 在後面添加訊息（載入更新訊息時使用）
         * @param {object} chatArea - 聊天區域實例
         * @param {array} messages - 訊息陣列
         */
        appendMessages: function (chatArea, messages) {
            if (messages && messages.length > 0) {
                // 記錄當前滾動位置和狀態
                const container = chatArea.chatMessages[0];
                const wasAtBottom = ChatAreaUI.isScrollAtBottom(chatArea.chatMessages);
                const currentScrollTop = container.scrollTop;
                const currentScrollHeight = container.scrollHeight;

                chatArea.messages = [...chatArea.messages, ...messages];

                // 移除重複訊息
                chatArea.messages = this.removeDuplicateMessages(chatArea.messages);

                // 確保訊息按時間順序排序
                chatArea.messages.sort((a, b) => {
                    return new Date(a.timestamp) - new Date(b.timestamp);
                });

                // 重新渲染整個訊息列表
                const messagesHtml = this.renderMessages(chatArea, chatArea.messages);
                chatArea.chatMessages.html(messagesHtml);

                // 綁定引用訊息點擊事件
                this.bindQuotedMessageEvents(chatArea);

                // 調整滾動位置
                if (wasAtBottom) {
                    // 如果用戶原本在底部，滾動到接近底部但留一點 buffer 避免重複觸發
                    const newScrollHeight = container.scrollHeight;
                    const bufferDistance = chatArea.isJumpedMode ? 100 : 50;
                    const targetScrollTop = newScrollHeight - container.clientHeight - bufferDistance;

                    container.style.scrollBehavior = 'auto';
                    container.scrollTop = Math.max(0, targetScrollTop);
                } else {
                    // 如果用戶不在底部，維持相對位置（考慮新增內容的高度變化）
                    const heightDifference = container.scrollHeight - currentScrollHeight;
                    container.style.scrollBehavior = 'auto';
                    container.scrollTop = currentScrollTop + heightDifference;
                }
            }
        },

        /**
         * 為所有訊息綁定引用訊息點擊事件
         * @param {object} chatArea - 聊天區域實例
         */
        bindQuotedMessageEvents: function (chatArea) {
            chatArea.chatMessages.find('.message-row').each(function () {
                const $messageElement = $(this);
                const messageId = $messageElement.data('message-id');

                // 找到對應的訊息資料
                const messageData = chatArea.messages.find(msg => msg.id === String(messageId));
                if (messageData) {
                    // 將訊息資料存儲到元素的 data 中，供點擊事件使用
                    $messageElement.data('message', messageData);

                    // 綁定引用訊息點擊事件
                    ChatAreaUtils.bindQuotedMessageClick($messageElement, chatArea);
                }
            });

            // 綁定功能選單事件
            ChatAreaMenu.bindMessageActionsEvents(chatArea);
        },


        /**
         * 顯示重新載入對話串按鈕
         * @param {object} chatArea - 聊天區域實例
         */
        showReloadConversationButton: function (chatArea) {
            const $button = $('#reload-conversation-btn-container');
            if ($button.length) {
                $button.show();

                // 綁定點擊事件（避免重複綁定）
                $('#reload-conversation-btn').off('click.reloadConversation').on('click.reloadConversation', () => {
                    this.reloadFullConversation(chatArea);
                });
            }
        },

        /**
         * 隱藏重新載入對話串按鈕
         */
        hideReloadConversationButton: function () {
            const $button = $('#reload-conversation-btn-container');
            if ($button.length) {
                $button.hide();

                // 移除點擊事件
                $('#reload-conversation-btn').off('click.reloadConversation');
            }
        },

        /**
         * 重新載入完整對話串
         * @param {object} chatArea - 聊天區域實例
         */
        reloadFullConversation: function (chatArea) {
            if (!chatArea.currentLineUserId) {
                return;
            }

            // 退出跳轉模式
            chatArea.isJumpedMode = false;
            chatArea.referenceTimestamp = null;
            chatArea.hasMoreMessagesBefore = false;
            chatArea.hasMoreMessagesAfter = false;
            chatArea.targetMessageIndex = null;

            // 隱藏重新載入按鈕
            this.hideReloadConversationButton();

            // 清空當前訊息
            chatArea.messages = [];
            chatArea.oldestMessageDate = null;
            chatArea.hasMoreMessages = true;

            // 載入最新的完整對話串
            this.loadMessages(chatArea, false);
        },

        /**
         * 取得當前回覆的訊息資料（供發送訊息時使用）
         * @param {object} chatArea - 聊天區域實例
         * @returns {object|null} 回覆的訊息資料
         */
        getCurrentReplyData: function (chatArea) {
            return ChatAreaMenu.getCurrentReplyData(chatArea);
        },

        /**
         * 清除回覆狀態（發送訊息後調用）
         * @param {object} chatArea - 聊天區域實例
         */
        clearReplyState: function (chatArea) {
            ChatAreaMenu.clearReplyState(chatArea);
        }
    };

})(jQuery);