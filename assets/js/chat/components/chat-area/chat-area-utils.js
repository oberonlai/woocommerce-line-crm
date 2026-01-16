/**
 * OrderChatz 聊天區域工具函數模組
 *
 * 提供聊天區域需要的各種工具函數，包含 HTML 處理、頭像處理等
 *
 * @package OrderChatz
 * @since 1.0.2
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域工具函數命名空間
     */
    window.ChatAreaUtils = {
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
         * 取得目前使用者頭像
         * @returns {string} 使用者頭像 URL
         */
        getCurrentUserAvatar: function () {
            return window.otzChatConfig?.current_user?.avatar || this.getDefaultAvatar();
        },

        /**
         * 取得目前使用者顯示名稱
         * @returns {string} 使用者顯示名稱
         */
        getCurrentUserDisplayName: function () {
            return window.otzChatConfig?.current_user?.display_name || 'OrderChatz Bot';
        },

        /**
         * 根據回覆者名稱取得對應頭像
         * @param {string} senderName - 回覆者名稱
         * @returns {string} 頭像 URL
         */
        getSenderAvatar: function (senderName) {
            // 如果沒有 sender_name，使用當前使用者頭像
            if (!senderName || senderName === 'OrderChatz Bot' || senderName === '') {
                return this.getCurrentUserAvatar();
            }

            // 如果是當前使用者，返回當前使用者頭像
            if (senderName === this.getCurrentUserDisplayName()) {
                return this.getCurrentUserAvatar();
            }

            // 檢查是否有快取的使用者頭像資訊
            if (window.otzChatConfig?.user_avatars && window.otzChatConfig.user_avatars[senderName]) {
                return window.otzChatConfig.user_avatars[senderName];
            }

            // 根據名稱生成不同的預設頭像（使用 Gravatar 或類似服務）
            const fallbackUrl = this.generateUserAvatar(senderName);
            return fallbackUrl || this.getDefaultAvatar();
        },

        /**
         * 根據使用者名稱生成頭像
         * @param {string} senderName - 使用者名稱
         * @returns {string} 頭像 URL
         */
        generateUserAvatar: function (senderName) {
            // 使用 WordPress Gravatar 服務根據名稱生成頭像
            const emailHash = this.md5(senderName.toLowerCase() + '@orderchatz.local');
            return `https://www.gravatar.com/avatar/${emailHash}?s=32&d=identicon&r=g`;
        },

        /**
         * 簡單的 MD5 雜湊函數（用於 Gravatar）
         * @param {string} str - 要雜湊的字串
         * @returns {string} MD5 雜湊值
         */
        md5: function (str) {
            // 簡化版 MD5，實際上使用字串雜湊
            let hash = 0;
            if (str.length === 0) return hash.toString(16);
            for (let i = 0; i < str.length; i++) {
                hash = ((hash << 5) - hash) + str.charCodeAt(i);
                hash = hash & hash; // 轉換為 32 位元整數
            }
            // 轉換為正數並格式化為 16 進位
            return Math.abs(hash).toString(16).padStart(8, '0').substring(0, 32).padEnd(32, '0');
        },

        /**
         * 取得預設頭像
         * @returns {string} 頭像 URL
         */
        getDefaultAvatar: function () {
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNEREQiLz4KPHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeD0iMTIiIHk9IjEyIj4KPHBhdGggZD0iTTggMEMzLjU4IDAgMCA0IDAgOFMzLjU4IDE2IDggMTZTMTYgMTIgMTYgOFMxMi40MiAwIDggMFpNOCAzQzEwLjIxIDMgMTIgNC43OSAxMiA3UzEwLjIxIDExIDggMTFTNCA5LjIxIDQgN1M1Ljc5IDMgOCAzWk04IDEzLjVDNS41IDEzLjUgMy4zNSAxMi4zNSAyLjUgMTAuNTVDMi41MSA4LjkgNS4xIDguMjUgOCA4LjI1UzEzLjQ5IDguOSAxMy41IDEwLjU1QzEyLjY1IDEyLjM1IDEwLjUgMTMuNSA4IDEzLjVaIiBmaWxsPSIjNjY2Ii8+Cjwvc3ZnPgo8L3N2Zz4=';
        },

        /**
         * 格式化圖片訊息
         * @param {string} content - 圖片 URL 或圖片數據
         * @returns {string} 格式化後的圖片 HTML
         */
        formatImageMessage: function (content) {
            try {
                // 如果 content 是 JSON 字符串，嘗試解析
                let imageData;
                if (typeof content === 'string' && content.startsWith('{')) {
                    imageData = JSON.parse(content);
                } else {
                    imageData = {originalContentUrl: content, previewImageUrl: content};
                }

                // 分別取得預覽圖和原始圖 URL
                let previewUrl = '';
                let originalUrl = '';

                // 檢查 contentProvider 結構
                if (imageData.contentProvider) {
                    previewUrl = imageData.contentProvider.previewImageUrl || '';
                    originalUrl = imageData.contentProvider.originalContentUrl || '';
                }

                // 回退到直接屬性（向後相容性）
                if (!previewUrl) {
                    previewUrl = imageData.previewImageUrl || imageData.originalContentUrl || content;
                }
                if (!originalUrl) {
                    originalUrl = imageData.originalContentUrl || previewUrl;
                }

                if (!previewUrl) {
                    console.error('ChatAreaUtils: 無法獲取有效的圖片 URL');
                    return '<div class="message-image-error">無法獲取圖片 URL</div>';
                }

                return `
                    <div class="message-image">
                        <img src="${this.escapeHtml(previewUrl)}" 
                             alt="圖片" 
                             style="max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer;"
                             onclick="window.chatAreaInstance.showImageLightbox('${this.escapeHtml(originalUrl)}')" 
                             onload="ChatAreaUtils.handleImageLoad(this)"
                             onerror="this.parentElement.innerHTML='<div class=\\'message-image-error\\'>圖片載入失敗</div>';" />
                    </div>
                `;
            } catch (error) {
                console.error('ChatAreaUtils: 格式化圖片訊息失敗:', error, '內容:', content);
                return '<div class="message-image-error">圖片格式錯誤</div>';
            }
        },

        /**
         * 格式化貼圖訊息
         * @param {string} content - 貼圖數據
         * @returns {string} 格式化後的貼圖 HTML
         */
        formatStickerMessage: function (content) {
            try {
                let stickerData;
                if (typeof content === 'string' && content.startsWith('{')) {
                    stickerData = JSON.parse(content);
                } else {
                    return '<div class="message-sticker">貼圖</div>';
                }

                const packageId = stickerData.packageId;
                const stickerId = stickerData.stickerId;

                // LINE 貼圖 URL 格式
                const stickerUrl = `https://stickershop.line-scdn.net/stickershop/v1/sticker/${stickerId}/android/sticker.png`;

                return `
                    <div class="message-sticker">
                        <img src="${stickerUrl}" 
                             alt="貼圖" 
                             style="max-width: 150px; max-height: 150px;"
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDEyMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiBmaWxsPSIjRjBGMEYwIiByeD0iOCIvPgo8dGV4dCB4PSI2MCIgeT0iNjAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPuiyqOWciTwvdGV4dD4KPC9zdmc+'; this.style.opacity='0.5';" />
                    </div>
                `;
            } catch (error) {
                console.error('ChatAreaUtils: 格式化貼圖訊息失敗:', error);
                return '<div class="message-sticker">貼圖</div>';
            }
        },

        /**
         * 處理包含 LINE emoji 的混合內容
         * @param {string} content - 包含 emoji HTML 的內容
         * @returns {string} 處理後的內容
         */
        processMixedEmojiContent: function (content) {
            try {
                // 將內容分割為文字和 emoji 部分
                const emojiRegex = /<img[^>]*class="line-emoji"[^>]*>/g;
                const parts = content.split(emojiRegex);
                const emojis = content.match(emojiRegex) || [];

                let result = '';
                for (let i = 0; i < parts.length; i++) {
                    // 轉義文字部分
                    if (parts[i]) {
                        result += this.escapeHtml(parts[i]).replace(/\n/g, '<br>');
                    }

                    // 添加 emoji HTML（如果存在）
                    if (emojis[i]) {
                        // 確保 emoji 圖片有進階錯誤處理和 URL fallback
                        result += emojis[i].replace('<img', '<img onerror="ChatAreaUtils.handleEmojiError(this)"');
                    }
                }

                return result;

            } catch (error) {
                console.error('ChatAreaUtils: 處理 emoji 內容失敗:', error);
                // 錯誤時回退到基本處理
                return this.escapeHtml(content).replace(/\n/g, '<br>');
            }
        },

        /**
         * 處理圖片載入完成事件
         * @param {HTMLImageElement} img - 載入完成的圖片元素
         */
        handleImageLoad: function (img) {
            // 圖片載入完成後，觸發捲動檢查（如果用戶在底部）
            const chatMessages = $(img).closest('.chat-messages');
            if (chatMessages.length > 0 && window.ChatAreaUI) {
                // 檢查是否需要捲動到底部
                const wasAtBottom = window.ChatAreaUI.isScrollAtBottom(chatMessages);
                if (wasAtBottom) {
                    // 延遲一點確保布局完全更新
                    setTimeout(() => {
                        window.ChatAreaUI.scrollToBottom(chatMessages);
                    }, 50);
                }
            }
        },

        /**
         * 處理表情符號載入錯誤
         * @param {HTMLImageElement} img - 失敗的圖片元素
         */
        handleEmojiError: function (img) {
            try {
                // 先嘗試 fallback URLs
                if (img.dataset.fallbackUrls && !img.dataset.triedFallback) {
                    const fallbackUrls = img.dataset.fallbackUrls.split(',');
                    if (fallbackUrls.length > 0) {
                        img.dataset.triedFallback = 'true';
                        img.src = fallbackUrls[0].trim();

                        // 如果還有更多 fallback URLs，更新 dataset
                        if (fallbackUrls.length > 1) {
                            img.dataset.fallbackUrls = fallbackUrls.slice(1).join(',');
                        } else {
                            delete img.dataset.fallbackUrls;
                        }
                        return;
                    }
                }

                // 所有 URL 都失敗，顯示文字替代
                img.style.display = 'none';

                // 創建替代文字元素
                const altText = document.createElement('span');
                altText.className = 'emoji-fallback';
                altText.textContent = img.alt || '(emoji)';
                altText.style.cssText = 'color: #666; font-size: 12px; background: #f0f0f0; padding: 2px 4px; border-radius: 3px; display: inline-block;';

                // 插入替代文字
                if (img.nextSibling) {
                    img.parentNode.insertBefore(altText, img.nextSibling);
                } else {
                    img.parentNode.appendChild(altText);
                }

                console.warn('LINE emoji failed to load:', img.alt, 'Original src:', img.src);

            } catch (error) {
                console.error('Error handling emoji fallback:', error);
                // 最後的降級處理
                img.style.display = 'none';
                if (img.parentNode) {
                    const textNode = document.createTextNode(img.alt || '(emoji)');
                    img.parentNode.insertBefore(textNode, img.nextSibling);
                }
            }
        },

        /**
         * 格式化文件訊息
         * @param {string} content - 文件資料 JSON 或文字
         * @returns {string} 格式化後的文件 HTML
         */
        formatFileMessage: function (content) {
            try {
                // 如果 content 是 JSON 字符串，嘗試解析.
                let fileData;
                if (typeof content === 'string' && content.startsWith('{')) {
                    fileData = JSON.parse(content);

                    // 從 JSON 資料中提取文件資訊（支援新舊格式）.
                    // 新格式：file_url, file_name, file_size
                    // 舊格式（後端）：originalContentUrl, fileName, fileSize
                    const fileUrl = fileData.file_url || fileData.originalContentUrl || '';
                    const fileName = fileData.file_name || fileData.fileName || '未知文件';
                    const fileSize = fileData.file_size || fileData.fileSize || 0;

                    if (!fileUrl) {
                        return '<div class="message-file-error">文件 URL 不完整</div>';
                    }

                    // 計算文件大小（如果有的話）.
                    let fileSizeText = '';
                    if (fileSize) {
                        const sizeInMB = (fileSize / 1024 / 1024).toFixed(2);
                        fileSizeText = `<div class="file-size">📊 大小：${sizeInMB} MB</div>`;
                    }

                    return `
                        <div class="message-file">
                            <div class="file-info">
                                <div class="file-name">${this.escapeHtml(fileName)}</div>
                                ${fileSizeText}
                                <div class="file-download">
                                    <a href="${this.escapeHtml(fileUrl)}" target="_blank" class="download-link">點擊下載</a>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // 如果不是 JSON，直接顯示文字內容.
                    return this.escapeHtml(content).replace(/\n/g, '<br>');
                }
            } catch (error) {
                console.error('ChatAreaUtils: 格式化文件訊息失敗:', error, '內容:', content);
                // 錯誤時顯示原始內容.
                return this.escapeHtml(content).replace(/\n/g, '<br>');
            }
        },

        /**
         * 格式化影片訊息
         * @param {string} content - 影片資料 JSON 或文字
         * @returns {string} 格式化後的影片 HTML
         */
        formatVideoMessage: function (content) {
            try {
                // 如果 content 是 JSON 字符串，嘗試解析
                let videoData;
                if (typeof content === 'string' && content.startsWith('{')) {
                    videoData = JSON.parse(content);

                    // 支援新格式 {video_url, preview_url, video_name} 和舊格式
                    let videoUrl = '';
                    let previewUrl = '';
                    let videoName = '';

                    // 新的統一格式
                    if (videoData.video_url) {
                        videoUrl = videoData.video_url;
                        previewUrl = videoData.preview_url || '';
                        videoName = videoData.video_name || '未知影片';
                    }
                    // 舊的 webhook 格式
                    else if (videoData.originalContentUrl) {
                        videoUrl = videoData.originalContentUrl;
                        previewUrl = videoData.previewImageUrl ||
                                   videoData.contentProvider?.previewImageUrl || '';

                        // 嘗試從不同來源提取檔名
                        if (videoData.video_name) {
                            videoName = videoData.video_name;
                        } else if (videoData.fileName && videoData.fileName.trim() !== '') {
                            videoName = videoData.fileName;
                        } else {
                            // 從 URL 提取檔名
                            const urlPath = videoUrl.split('/').pop();
                            videoName = urlPath || '未知影片';
                        }
                    }

                    if (!videoUrl) {
                        return '<div class="message-video-error">影片 URL 不完整</div>';
                    }

                    // 如果有預覽圖，顯示預覽圖 + 播放按鈕
                    if (previewUrl) {
                        return `
                            <div class="message-video">
                                <div class="video-preview-container" style="position: relative; display: inline-block; max-width: 200px; border-radius: 8px; overflow: hidden;">
                                    <img src="${this.escapeHtml(previewUrl)}"
                                         alt="${this.escapeHtml(videoName)}"
                                         style="max-width: 200px; max-height: 200px; display: block; cursor: pointer;"
                                         onclick="window.open('${this.escapeHtml(videoUrl)}', '_blank')"
                                         onload="ChatAreaUtils.handleImageLoad(this)"
                                         onerror="this.parentElement.innerHTML='<div class=\\'message-video-error\\'>預覽圖載入失敗</div>';" />
                                    <div class="video-play-button"
                                         style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                                                background: rgba(0,0,0,0.7); border-radius: 50%; width: 60px; height: 60px;
                                                display: flex; align-items: center; justify-content: center; cursor: pointer;
                                                color: white; font-size: 24px; transition: background 0.3s;"
                                         onclick="window.open('${this.escapeHtml(videoUrl)}', '_blank')"
                                         onmouseover="this.style.background='rgba(0,0,0,0.9)'"
                                         onmouseout="this.style.background='rgba(0,0,0,0.7)'">
                                        ▶
                                    </div>
                                </div>
                                <div class="video-info" style="margin-top: 5px; font-size: 12px; color: #666;">
                                    ${this.escapeHtml(videoName)}
                                </div>
                            </div>
                        `;
                    }
                    // 沒有預覽圖時，顯示文字連結（向後相容）
                    else {
                        return `
                            <div class="message-video">
                                <div class="file-info">
                                    <div class="file-name">${this.escapeHtml(videoName)}</div>
                                    <div class="file-download">
                                        <a href="${this.escapeHtml(videoUrl)}" target="_blank" class="download-link">點擊觀看</a>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    // 如果不是 JSON，直接顯示文字內容
                    return this.escapeHtml(content).replace(/\n/g, '<br>');
                }
            } catch (error) {
                console.error('ChatAreaUtils: 格式化影片訊息失敗:', error, '內容:', content);
                // 錯誤時顯示原始內容
                return this.escapeHtml(content).replace(/\n/g, '<br>');
            }
        },

        /**
         * 轉換文字中的 URL 為可點擊連結
         * @param {string} text - 包含 URL 的文字
         * @returns {string} 轉換後的 HTML
         */
        convertUrlsToLinks: function (text) {
            // URL 正則表達式
            const urlRegex = /(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/gi;

            return text.replace(urlRegex, function (url) {
                return `<a href="${url}" target="_blank" rel="noopener noreferrer" style="color: #007cff; text-decoration: underline;">${url}</a>`;
            });
        },

        /**
         * 格式化訊息內容
         * @param {string} content - 訊息內容
         * @param {string} messageType - 訊息類型
         * @returns {string} 格式化後的內容
         */
        formatMessageContent: function (content, messageType) {
            if (!content) return '';

            // 特殊處理：檢查是否是文字訊息但內容為圖片符號
            if (messageType === 'text' && content === '📷圖片') {
                // 嘗試從其他來源獲取圖片資訊，或顯示提示訊息
                return '<div class="message-image-placeholder">此訊息包含圖片，但圖片資料不完整</div>';
            }

            switch (messageType) {
                case 'image':
                    return this.formatImageMessage(content);
                case 'sticker':
                    return this.formatStickerMessage(content);
                case 'file':
                    return this.formatFileMessage(content);
                case 'video':
                    return this.formatVideoMessage(content);
                case 'text':
                default:
                    // 檢查是否包含 LINE emoji HTML
                    if (content.includes('<img') && content.includes('line-emoji')) {
                        // 允許 LINE emoji HTML，但對其他內容進行 HTML 轉義
                        return this.processMixedEmojiContent(content);
                    }
                    // 檢查是否為商品推薦訊息（包含 HTML 連結）
                    if (content.startsWith('[商品推薦]') && content.includes('<a href=')) {
                        // 對商品推薦訊息允許安全的 HTML 連結，但只允許 <a> 標籤
                        return content.replace(/\n/g, '<br>');
                    }

                    // 先進行 HTML 轉義，再轉換換行，最後轉換 URL 為連結
                    let processedContent = this.escapeHtml(content).replace(/\n/g, '<br>');
                    return this.convertUrlsToLinks(processedContent);
            }
        },

        /**
         * 渲染引用訊息內容
         * @param {object} quotedData - 引用訊息資料
         * @returns {string} 引用訊息 HTML
         */
        renderQuotedContent: function (quotedData) {
            if (!quotedData || !quotedData.quoted_display) {
                return '';
            }

            const displayData = quotedData.quoted_display;

            return `
                <div class="quoted-message">
                    <div class="quoted-content">
                        ${this.getQuotedPreview(displayData)}
                    </div>
                </div>
            `;
        },

        /**
         * 取得引用訊息預覽內容
         * @param {object} displayData - 引用顯示資料
         * @returns {string} 預覽內容 HTML
         */
        getQuotedPreview: function (displayData) {
            const messageType = displayData.type;

            switch (messageType) {
                case 'image':
                    if (displayData.preview_url) {
                        return `
                            <div class="quoted-preview quoted-image-preview">
                                <img src="${this.escapeHtml(displayData.preview_url)}" alt="引用圖片" />
                                <span class="quoted-text">圖片</span>
                            </div>
                        `;
                    }
                    return `<div class="quoted-text">圖片</div>`;

                case 'file':
                    return `
                        <div class="quoted-text">
                            <span class="quoted-file-name">${this.escapeHtml(displayData.file_name || '檔案')}</span>
                        </div>
                    `;

                case 'video':
                    return `
                        <div class="quoted-text">
                            <span class="quoted-file-name">${this.escapeHtml(displayData.file_name || '影片')}</span>
                        </div>
                    `;

                case 'sticker':
                    return `<div class="quoted-text">貼圖</div>`;

                case 'text':
                default:
                    return `
                        <div class="quoted-text">
                            ${this.escapeHtml(displayData.text || '訊息')}
                        </div>
                    `;
            }
        },

        /**
         * 格式化引用文字（截斷處理）
         * @param {string} text - 原始文字
         * @param {number} maxLength - 最大長度
         * @returns {string} 截斷後的文字
         */
        formatQuotedText: function (text, maxLength = 50) {
            if (!text || text.length <= maxLength) {
                return text || '';
            }
            return text.substring(0, maxLength) + '...';
        },

        /**
         * 綁定引用訊息點擊事件
         * @param {jQuery} $messageElement - 訊息元素
         * @param {object} chatAreaInstance - 聊天區域實例
         */
        bindQuotedMessageClick: function ($messageElement, chatAreaInstance) {
            const $quotedMessage = $messageElement.find('.quoted-message');
            if ($quotedMessage.length === 0) return;

            $quotedMessage.css('cursor', 'pointer');

            $quotedMessage.off('click.quotedMessage').on('click.quotedMessage', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const messageData = $messageElement.data('message');
                if (!messageData || !messageData.quoted_message) {
                    console.warn('找不到引用訊息資料');
                    return;
                }

                const quotedMessage = messageData.quoted_message;
                if (!quotedMessage.timestamp) {
                    console.warn('引用訊息缺少時間戳記');
                    return;
                }

                // 調用新的跳轉功能，重載整個對話串
                if (window.ChatAreaMessages && window.ChatAreaMessages.jumpToQuotedMessage && chatAreaInstance) {
                    window.ChatAreaMessages.jumpToQuotedMessage(chatAreaInstance, quotedMessage.timestamp, 5);
                } else {
                    console.error('ChatAreaMessages.jumpToQuotedMessage 方法未找到或缺少 chatAreaInstance');
                }
            });
        },
    };

})(jQuery);