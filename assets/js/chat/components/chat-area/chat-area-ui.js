/**
 * OrderChatz 聊天區域 UI 互動模組
 *
 * 管理聊天區域的各種 UI 互動功能，包含滾動、通知、燈箱等
 *
 * @package OrderChatz
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域 UI 互動管理器
     */
    window.ChatAreaUI = {
        /**
         * 初始化 UI 功能
         * @param {object} chatAreaInstance - 聊天區域實例
         */
        init: function (chatAreaInstance) {
            this.chatArea = chatAreaInstance;
        },

        /**
         * 滾動到底部（輕量版，具備重試機制和基本圖片檢測）
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @param {boolean} checkImages - 是否檢查圖片載入（預設：true）
         */
        scrollToBottom: function (chatMessages, checkImages = true) {
            const container = chatMessages[0];
            if (!container) {
                console.log('ChatAreaUI: scrollToBottom 失敗 - 找不到容器');
                return;
            }

            const maxAttempts = checkImages ? 8 : 5; // 如果檢查圖片，增加重試次數
            let attempt = 0;

            // 檢查是否有未載入完成的圖片（僅檢查最新的幾張）
            const hasRecentLoadingImages = () => {
                if (!checkImages) return false;
                
                const recentImages = chatMessages.find('img').slice(-3); // 只檢查最後3張圖片
                for (let i = 0; i < recentImages.length; i++) {
                    const img = recentImages[i];
                    if (!img.complete || img.naturalHeight === 0) {
                        return true;
                    }
                }
                return false;
            };

            const tryScroll = () => {
                attempt++;
                const targetScrollTop = container.scrollHeight - container.clientHeight;

                // 使用更強制的方法
                container.style.scrollBehavior = 'auto';
                container.scrollTop = targetScrollTop;

                // 使用 requestAnimationFrame 確保在下一個渲染幀執行
                requestAnimationFrame(() => {
                    container.scrollTop = targetScrollTop;

                    // 驗證捲動是否成功，如果失敗且未達到最大嘗試次數則重試
                    setTimeout(() => {
                        const scrollDiff = Math.abs(container.scrollTop - targetScrollTop);
                        const hasImages = hasRecentLoadingImages();
                        const retryDelay = hasImages ? 200 : 100;
                        
                        if (scrollDiff > 10 && attempt < maxAttempts) {
                            setTimeout(tryScroll, retryDelay);
                        }
                    }, hasRecentLoadingImages() ? 100 : 50);
                });
            };

            tryScroll();
        },

        /**
         * 檢查是否滾動到底部
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @returns {boolean}
         */
        isScrollAtBottom: function (chatMessages) {
            const container = chatMessages[0];
            if (!container) return true;

            const threshold = 50; // 容忍50px的誤差
            return (container.scrollHeight - container.scrollTop - container.clientHeight) <= threshold;
        },

        /**
         * 檢查是否滾動到頂部
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @returns {boolean}
         */
        isScrollAtTop: function (chatMessages) {
            const container = chatMessages[0];
            if (!container) return false;

            const threshold = 10;
            return container.scrollTop <= threshold;
        },

        /**
         * 顯示圖片燈箱
         * @param {string} imageUrl - 原始圖片 URL
         */
        showImageLightbox: function (imageUrl) {
            // 創建燈箱 HTML
            const lightboxHtml = `
                <div class="image-lightbox-overlay">
                    <button class="image-lightbox-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    <div class="image-lightbox-container">
                        <div class="image-lightbox-content">
                            <img src="${ChatAreaUtils.escapeHtml(imageUrl)}" alt="放大圖片" class="image-lightbox-img" />
                        </div>
                    </div>
                </div>
            `;

            // 插入到 body
            $(document.body).append(lightboxHtml);

            // 取得剛插入的燈箱元素
            const $lightbox = $('.image-lightbox-overlay').last();

            // 添加關閉功能到燈箱遮罩
            $lightbox.on('click', function (e) {
                if (e.target === this) {
                    $(this).remove();
                    $(document).off('keydown.lightbox');
                }
            });

            // 添加關閉按鈕事件
            $lightbox.find('.image-lightbox-close').on('click', function (e) {
                e.stopPropagation();
                $lightbox.remove();
                $(document).off('keydown.lightbox');
            });

            // 防止容器點擊時關閉燈箱
            $lightbox.find('.image-lightbox-container').on('click', function (e) {
                e.stopPropagation();
            });

            // ESC 鍵關閉
            $(document).off('keydown.lightbox').on('keydown.lightbox', function (e) {
                if (e.key === 'Escape') {
                    $lightbox.remove();
                    $(document).off('keydown.lightbox');
                }
            });
        },

        /**
         * 顯示新訊息通知
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @param {number} messageCount - 新訊息數量
         */
        showNewMessageNotification: function (chatMessages, messageCount) {
            // 移除現有通知
            chatMessages.find('.new-message-notification').remove();

            const notification = `
                <div class="new-message-notification" onclick="this.parentElement.scrollTo({top: this.parentElement.scrollHeight, behavior: 'smooth'}); this.remove();">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    ${messageCount} 則新訊息
                </div>
            `;

            chatMessages.append(notification);

            // 5秒後自動隱藏
            setTimeout(() => {
                chatMessages.find('.new-message-notification').fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * 強制滾動到底部並清除新訊息通知
         * @param {jQuery} chatMessages - 聊天訊息容器
         */
        scrollToBottomAndClearNotification: function (chatMessages) {
            chatMessages.find('.new-message-notification').remove();
            // 清除通知後強制捲動，檢查圖片載入狀態
            this.scrollToBottom(chatMessages, true);
        },

        /**
         * 顯示載入更多指示器
         * @param {jQuery} chatMessages - 聊天訊息容器
         */
        showLoadMoreIndicator: function (chatMessages) {
            const indicator = '<div class="load-more-indicator"><div class="loading-spinner"></div><span>載入更多訊息...</span></div>';
            chatMessages.prepend(indicator);
        },

        /**
         * 隱藏載入更多指示器
         * @param {jQuery} chatMessages - 聊天訊息容器
         */
        hideLoadMoreIndicator: function (chatMessages) {
            chatMessages.find('.load-more-indicator').remove();
        },

        /**
         * 顯示錯誤訊息
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @param {string} message - 錯誤訊息
         */
        showErrorMessage: function (chatMessages, message) {
            const errorHtml = `
                <div class="chat-error-message">
                    <div class="error-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <p>${ChatAreaUtils.escapeHtml(message)}</p>
                    <button type="button" class="retry-button" onclick="location.reload()">重試</button>
                </div>
            `;
            chatMessages.html(errorHtml);
        },

        /**
         * 顯示載入中的訊息
         * @param {jQuery} chatMessages - 聊天訊息容器
         */
        showLoadingMessages: function (chatMessages) {
            chatMessages.html(`
                <div class="loading-messages" style="height:100vh; flex-direction: column; display: flex; align-items: center; justify-content: center;">
                    <div class="loading-spinner"></div>
                    <p >載入對話記錄中...</p>
                </div>
            `);
        },

        /**
         * 顯示無選擇狀態
         * @param {jQuery} chatHeader - 聊天標題容器
         * @param {jQuery} chatMessages - 聊天訊息容器
         */
        showNoSelectionState: function (chatHeader, chatMessages) {
            chatHeader.html('<p>請選擇一位好友開始聊天</p>');
            chatMessages.html(`
                <div class="no-selection-state" style="text-align: center; padding: 20px;">
                    <div class="empty-chat-icon">
                        <span class="dashicons dashicons-format-chat"></span>
                    </div>
                    <p>選擇左側的好友開始對話</p>
                </div>
            `);
        },

        /**
         * 重新渲染所有訊息並保持滾動位置
         * @param {jQuery} chatMessages - 聊天訊息容器
         * @param {function} renderMessagesCallback - 渲染訊息的回調函數
         * @param {array} messages - 訊息列表
         * @param {boolean} autoScroll - 是否自動處理捲動（預設：true）
         */
        rerenderAllMessages: function (chatMessages, renderMessagesCallback, messages, autoScroll = true) {
            const container = chatMessages[0];
            if (!container) return;

            const scrollTop = chatMessages.scrollTop();
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            const wasAtBottom = (scrollHeight - scrollTop - clientHeight) <= 50;

            // 重新渲染所有訊息
            const messagesHtml = renderMessagesCallback(messages);
            chatMessages.html(messagesHtml);

            // 如果禁用自動捲動，直接返回不處理捲動
            if (!autoScroll) {
                return { wasAtBottom, scrollTop };
            }

            // 延遲處理捲動位置，確保 DOM 完全更新
            requestAnimationFrame(() => {
                if (wasAtBottom) {
                    // 使用強化版捲動方法確保到底部
                    this.scrollToBottom(chatMessages);
                } else {
                    // 保持原來的捲動位置
                    chatMessages.scrollTop(scrollTop);
                }
            });

            return { wasAtBottom, scrollTop };
        }
    };

})(jQuery);