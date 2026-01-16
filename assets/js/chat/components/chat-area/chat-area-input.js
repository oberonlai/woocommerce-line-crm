/**
 * OrderChatz 聊天區域輸入處理模組
 *
 * 管理訊息輸入、發送、文件上傳等功能
 *
 * @package OrderChatz
 * @since 1.0.2
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域輸入處理管理器
     */
    window.ChatAreaInput = {
        /**
         * 初始化輸入處理功能
         * @param {object} chatAreaInstance - 聊天區域實例
         */
        init: function (chatAreaInstance) {
            this.chatArea = chatAreaInstance;
            this.setupPasteEventListener(chatAreaInstance);
            this.setupBotStatusListener(chatAreaInstance);
        },

        /**
         * 設置貼上事件監聽器
         * @param {object} chatArea - 聊天區域實例
         */
        setupPasteEventListener: function (chatArea) {
            const messageInput = chatArea.messageInput;
            if (messageInput && messageInput.length > 0) {
                messageInput.on('paste', (e) => {
                    this.handlePasteEvent(chatArea, e);
                });
            }
        },

        /**
         * 處理貼上事件
         * @param {object} chatArea - 聊天區域實例
         * @param {Event} event - 貼上事件
         */
        handlePasteEvent: function (chatArea, event) {
            const clipboardData = event.originalEvent.clipboardData || window.clipboardData;
            if (!clipboardData) {
                return;
            }

            const items = clipboardData.items;
            if (!items) {
                return;
            }

            // 檢查是否有圖片文件
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.type.indexOf('image') !== -1) {
                    // 防止預設的貼上行為
                    event.preventDefault();

                    // 獲取圖片文件
                    const file = item.getAsFile();
                    if (file) {
                        // 顯示確認對話框
                        if (!confirm('您即將貼上一張圖片，確定要上傳並發送嗎？')) {
                            return; // 用戶取消，不進行上傳
                        }

                        // 如果沒有檔名，生成一個基於時間戳和類型的檔名
                        if (!file.name || file.name === '') {
                            const timestamp = Date.now();
                            const extension = file.type.split('/')[1] || 'png';
                            Object.defineProperty(file, 'name', {
                                value: `paste_image_${timestamp}.${extension}`,
                                writable: false
                            });
                        }
                        // 創建 FileList 模擬對象以兼容現有的 handleFileUpload 方法
                        const fileList = [file];
                        this.handleFileUpload(chatArea, fileList);
                    }
                    return;
                }
            }
        },

        /**
         * 處理訊息輸入
         * @param {object} chatArea - 聊天區域實例
         * @param {Event} event - 輸入事件
         */
        handleMessageInput: function (chatArea, event) {
            const message = $(event.target).val().trim();
            this.toggleSendButton(chatArea, message.length > 0);

            // 處理範本自動完成
            if (typeof ChatAreaTemplate !== 'undefined') {
                ChatAreaTemplate.onMessageInput(event);
            }
        },

        /**
         * 處理鍵盤輸入
         * @param {object} chatArea - 聊天區域實例
         * @param {Event} event - 鍵盤事件
         */
        handleMessageKeydown: function (chatArea, event) {
            // 先檢查範本自動完成是否處理了此事件
            if (typeof ChatAreaTemplate !== 'undefined') {
                const templateHandled = ChatAreaTemplate.onKeyDown(event);
                if (templateHandled) {
                    return; // 如果範本功能已處理，不繼續執行
                }
            }

            if (event.keyCode === 13 && !event.shiftKey && !chatArea.isComposing) {
                event.preventDefault();
                this.handleSendMessage(chatArea);
            }
        },

        /**
         * 處理發送訊息
         * @param {object} chatArea - 聊天區域實例
         */
        handleSendMessage: function (chatArea) {
            if (!chatArea.currentFriendId || !chatArea.currentLineUserId) {
                return;
            }

            const message = chatArea.messageInput.val().trim();
            if (message.length === 0) {
                return;
            }

            // 清空輸入框
            chatArea.messageInput.val('');
            this.toggleSendButton(chatArea, false);

            // 顯示發送中的訊息
            const tempMessageId = this.addTempMessage(chatArea, message);

            // 發送訊息到後端
            this.sendMessageToServer(chatArea, message, tempMessageId);
        },

        /**
         * 處理表單提交
         * @param {object} chatArea - 聊天區域實例
         * @param {Event} event - 提交事件
         */
        handleFormSubmit: function (chatArea, event) {
            event.preventDefault();
            this.handleSendMessage(chatArea);
        },

        /**
         * 新增臨時訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {string} message - 訊息內容
         * @return {string} 臨時訊息 ID
         */
        addTempMessage: function (chatArea, message) {
            const tempId = 'temp_' + Date.now();
            const messageHtml = `
                <div class="message-row outgoing temp" data-temp-id="${tempId}">
                    <div class="message-bubble outgoing">
                        <div class="message-content">
                            <div class="message-text">${ChatAreaUtils.escapeHtml(message)}</div>
                        </div>
                        <div class="message-time">發送中...</div>
                    </div>
                    <div class="message-avatar">
                        <img src="${ChatAreaUtils.getCurrentUserAvatar()}" alt="avatar" />
                    </div>
                </div>
            `;

            chatArea.chatMessages.append(messageHtml);
            ChatAreaUI.scrollToBottom(chatArea.chatMessages);
            return tempId;
        },

        /**
         * 切換發送按鈕狀態
         * @param {object} chatArea - 聊天區域實例
         * @param {boolean} enabled - 是否啟用
         */
        toggleSendButton: function (chatArea, enabled) {
            chatArea.sendButton.prop('disabled', !enabled);
            if (enabled) {
                chatArea.sendButton.removeClass('disabled');
            } else {
                chatArea.sendButton.addClass('disabled');
            }
        },

        /**
         * 啟用輸入
         * @param {object} chatArea - 聊天區域實例
         */
        enableInput: function (chatArea) {
            chatArea.messageInput.prop('disabled', false);
            chatArea.sendButton.prop('disabled', false);
        },

        /**
         * 禁用輸入
         * @param {object} chatArea - 聊天區域實例
         */
        disableInput: function (chatArea) {
            chatArea.messageInput.prop('disabled', true);
            chatArea.sendButton.prop('disabled', true);
        },

        /**
         * 發送訊息到伺服器
         * @param {object} chatArea - 聊天區域實例
         * @param {string} message - 訊息內容
         * @param {string} tempMessageId - 臨時訊息 ID
         */
        sendMessageToServer: function (chatArea, message, tempMessageId) {
            const data = {
                action: 'otz_send_message',
                line_user_id: chatArea.currentLineUserId,
                message: message,
                reply_token: '', // 後端會自動從資料庫尋找可用的 reply token
                nonce: otzChatConfig.nonce,
                source_type: chatArea.currentSourceType || '',
                group_id: chatArea.currentGroupId || ''
            };

            // 檢查是否有要回覆的訊息，如果有則加入 quote_token 和 quoted_message_id
            const replyData = ChatAreaMessages.getCurrentReplyData(chatArea);

            if (replyData && replyData.quote_token) {
                data.quote_token = replyData.quote_token;
                data.quoted_message_id = replyData.line_message_id;
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        ChatAreaMessages.clearReplyState(chatArea);
                        setTimeout(() => {
                            ChatAreaMessages.loadMessages(chatArea, false);
                            // 額外確保在訊息載入完成後捲動到底部
                            setTimeout(() => {
                                ChatAreaMessages.ensureScrollToBottom(chatArea.chatMessages);
                            }, 200);
                        }, 500);

                        // 觸發訊息發送成功事件
                        $(document).trigger('message:sent:success', [{
                            friendId: chatArea.currentFriendId,
                            message: message,
                            apiUsed: response.data.api_used
                        }]);
                    } else {
                        // 發送失敗，更新臨時訊息狀態
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    // 網路錯誤，更新臨時訊息狀態
                    this.updateTempMessageError(chatArea, tempMessageId, '網路連線錯誤，請稍後再試');
                }
            });
        },

        /**
         * 更新臨時訊息為錯誤狀態
         * @param {object} chatArea - 聊天區域實例
         * @param {string} tempId - 臨時訊息 ID
         * @param {string} errorMessage - 錯誤訊息
         */
        updateTempMessageError: function (chatArea, tempId, errorMessage) {
            const tempMessage = chatArea.chatMessages.find(`[data-temp-id="${tempId}"]`);
            if (tempMessage.length > 0) {
                tempMessage.removeClass('temp').addClass('error');
                tempMessage.find('.message-time').text('發送失敗');
                tempMessage.attr('title', errorMessage);

                // 添加重試按鈕
                const retryButton = `<button class="retry-send-btn" data-temp-id="${tempId}" title="點擊重試">↻</button>`;
                tempMessage.find('.message-content').append(retryButton);

                // 綁定重試事件
                tempMessage.find('.retry-send-btn').on('click', (e) => {
                    const originalMessage = tempMessage.find('.message-text').text();
                    tempMessage.remove();
                    const newTempId = this.addTempMessage(chatArea, originalMessage);
                    this.sendMessageToServer(chatArea, originalMessage, newTempId);
                });
            }
        },

        /**
         * 設置拖曳上傳功能
         * @param {object} chatArea - 聊天區域實例
         */
        setupDragDropUpload: function (chatArea) {
            const dropArea = $('#chat-area-panel');
            const self = this;

            if (dropArea.length === 0) {
                return;
            }

            // 拖曳進入
            dropArea.on('dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-hover');
            });

            // 拖曳移動
            dropArea.on('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
            });

            // 拖曳離開
            dropArea.on('dragleave', function (e) {
                e.preventDefault();
                e.stopPropagation();
                // 只有當離開整個 dropArea 時才移除 drag-hover
                if (!$(e.relatedTarget).closest('.chat-area-panel').length) {
                    $(this).removeClass('drag-hover');
                }
            });

            // 拖曳放下
            dropArea.on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-hover');

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileUpload(chatArea, files);
                }
            });
        },

        /**
         * 處理圖片上傳按鈕點擊
         * @param {object} chatArea - 聊天區域實例
         */
        handleImageUploadClick: function (chatArea) {
            chatArea.container.find('#image-upload-input').click();
        },

        /**
         * 處理圖片上傳文件選擇
         * @param {object} chatArea - 聊天區域實例
         * @param {Event} event - 文件選擇事件
         */
        handleImageUploadChange: function (chatArea, event) {
            const files = event.target.files;
            if (files.length > 0) {
                this.handleFileUpload(chatArea, files);
            }
        },

        /**
         * 處理文件上傳
         * @param {object} chatArea - 聊天區域實例
         * @param {FileList} files - 文件列表
         */
        handleFileUpload: function (chatArea, files) {
            if (!chatArea.currentFriendId || !chatArea.currentLineUserId) {
                alert('請先選擇一位好友');
                return;
            }

            Array.from(files).forEach(file => {
                // 安全性驗證
                if (!this.validateFileSecurely(file)) {
                    return;
                }

                // 根據文件類型處理上傳
                if (file.type.startsWith('image/')) {
                    this.uploadImageFile(chatArea, file);
                } else if (this.isCompressedFile(file)) {
                    this.uploadCompressedFile(chatArea, file);
                } else if (this.isVideoFile(file)) {
                    this.uploadVideoFile(chatArea, file);
                } else {
                    alert('不支援的文件格式，僅支援圖片、壓縮檔 (ZIP/RAR) 和影片檔');
                    return;
                }
            });
        },

        /**
         * 安全性驗證文件
         * @param {File} file - 要驗證的文件
         * @return {boolean} 驗證結果
         */
        validateFileSecurely: function (file) {
            // 檢查文件名稱，防止惡意文件名
            const fileName = file.name;
            const dangerousExtensions = ['.exe', '.bat', '.cmd', '.com', '.pif', '.scr', '.vbs', '.js', '.jar', '.php'];
            const fileExtension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));

            if (dangerousExtensions.includes(fileExtension)) {
                alert('禁止上傳可執行文件或腳本文件');
                return false;
            }

            // 檢查文件名長度
            if (fileName.length > 255) {
                alert('文件名稱過長，請縮短文件名稱');
                return false;
            }

            // 檢查特殊字符
            const dangerousChars = /[<>:"|?*\x00-\x1f]/;
            if (dangerousChars.test(fileName)) {
                alert('文件名稱包含不允許的字符');
                return false;
            }

            // 檢查文件大小限制
            const maxSize = this.getMaxFileSize(file.type);
            if (file.size > maxSize) {
                const sizeMB = Math.round(maxSize / 1024 / 1024);
                alert(`文件大小超過限制 (${sizeMB}MB)`);
                return false;
            }

            // 檢查空文件
            if (file.size === 0) {
                alert('不能上傳空文件');
                return false;
            }

            return true;
        },

        /**
         * 獲取文件大小限制
         * @param {string} fileType - 文件類型
         * @return {number} 文件大小限制 (bytes)
         */
        getMaxFileSize: function (fileType) {
            if (fileType.startsWith('image/')) {
                return 5 * 1024 * 1024; // 5MB for images
            } else if (fileType.startsWith('video/')) {
                return 50 * 1024 * 1024; // 50MB for videos
            } else {
                return 20 * 1024 * 1024; // 20MB for compressed files
            }
        },

        /**
         * 檢查是否為壓縮檔
         * @param {File} file - 文件對象
         * @return {boolean} 是否為壓縮檔
         */
        isCompressedFile: function (file) {
            const compressedTypes = [
                'application/zip',
                'application/x-zip-compressed',
                'application/x-rar-compressed',
                'application/vnd.rar',
                'application/x-rar'
            ];

            const compressedExtensions = ['.zip', '.rar'];
            const fileName = file.name.toLowerCase();
            const hasValidExtension = compressedExtensions.some(ext => fileName.endsWith(ext));

            return compressedTypes.includes(file.type) || hasValidExtension;
        },

        /**
         * 檢查是否為影片檔
         * @param {File} file - 文件對象
         * @return {boolean} 是否為影片檔
         */
        isVideoFile: function (file) {
            const videoTypes = [
                'video/mp4',
                'video/mpeg',
                'video/quicktime',
                'video/x-msvideo',
                'video/webm',
                'video/ogg'
            ];

            const videoExtensions = ['.mp4', '.avi', '.mov', '.wmv', '.flv', '.webm', '.ogv', '.m4v'];
            const fileName = file.name.toLowerCase();
            const hasValidExtension = videoExtensions.some(ext => fileName.endsWith(ext));

            return videoTypes.includes(file.type) || hasValidExtension;
        },

        /**
         * 上傳圖片文件
         * @param {object} chatArea - 聊天區域實例
         * @param {File} file - 圖片文件
         */
        uploadImageFile: function (chatArea, file) {
            const tempMessageId = this.addTempImageMessage(chatArea, file);

            const formData = new FormData();
            formData.append('action', 'otz_upload_image');
            formData.append('image', file);
            formData.append('line_user_id', chatArea.currentLineUserId);
            formData.append('nonce', otzChatConfig.nonce);

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // 更新臨時訊息為已上傳狀態
                        this.updateTempImageMessage(chatArea, tempMessageId, response.data.image_url);

                        // 發送圖片訊息到 LINE
                        this.sendImageMessageToLine(chatArea, response.data.image_url, tempMessageId);
                    } else {
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message || '圖片上傳失敗');
                    }
                },
                error: (xhr, status, error) => {
                    this.updateTempMessageError(chatArea, tempMessageId, '網路連線錯誤，請稍後再試');
                }
            });
        },

        /**
         * 添加臨時圖片訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {File} file - 圖片文件
         * @return {string} 臨時訊息 ID
         */
        addTempImageMessage: function (chatArea, file) {
            const tempId = 'temp_' + Date.now();
            const imagePreview = URL.createObjectURL(file);

            const messageHtml = `
                <div class="message-row outgoing temp" data-temp-id="${tempId}">
                    <div class="message-bubble outgoing">
                        <div class="message-content">
                            <div class="message-image">
                                <img src="${imagePreview}" alt="上傳中的圖片" style="max-width: 200px; border-radius: 8px;" />
                                <div class="upload-progress">
                                    <div class="upload-spinner"></div>
                                    <span>上傳中...</span>
                                </div>
                            </div>
                        </div>
                        <div class="message-time">上傳中...</div>
                    </div>
                    <div class="message-avatar">
                        <img src="${ChatAreaUtils.getCurrentUserAvatar()}" alt="avatar" />
                    </div>
                </div>
            `;

            chatArea.chatMessages.append(messageHtml);
            ChatAreaUI.scrollToBottom(chatArea.chatMessages);
            return tempId;
        },

        /**
         * 更新臨時圖片訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {string} tempId - 臨時訊息 ID
         * @param {string} imageUrl - 圖片 URL
         */
        updateTempImageMessage: function (chatArea, tempId, imageUrl) {
            const tempMessage = chatArea.chatMessages.find(`[data-temp-id="${tempId}"]`);
            if (tempMessage.length > 0) {
                // 移除上傳中的樣式和進度指示器
                tempMessage.removeClass('temp');
                tempMessage.find('.upload-progress').remove();
                tempMessage.find('.message-time').text('已上傳');

                // 更新圖片 URL
                tempMessage.find('.message-image img').attr('src', imageUrl);
            }
        },

        /**
         * 發送圖片訊息到 LINE
         * @param {object} chatArea - 聊天區域實例
         * @param {string} imageUrl - 圖片 URL
         * @param {string} tempMessageId - 臨時訊息 ID
         */
        sendImageMessageToLine: function (chatArea, imageUrl, tempMessageId) {
            const data = {
                action: 'otz_send_image_message',
                line_user_id: chatArea.currentLineUserId,
                image_url: imageUrl,
                reply_token: '', // 後端會自動從資料庫尋找可用的 reply token
                nonce: otzChatConfig.nonce,
                source_type: chatArea.currentSourceType || '',
                group_id: chatArea.currentGroupId || ''
            };

            // 檢查是否有要回覆的訊息，如果有則加入 quote_token 和 quoted_message_id
            const replyData = ChatAreaMessages.getCurrentReplyData(chatArea);
            if (replyData && replyData.quote_token) {
                data.quote_token = replyData.quote_token;
                data.quoted_message_id = replyData.line_message_id;
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 發送成功，重新載入訊息
                        setTimeout(() => {
                            ChatAreaMessages.loadMessages(chatArea, false);
                        }, 500);
                    } else {
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.updateTempMessageError(chatArea, tempMessageId, '發送圖片失敗，請稍後再試');
                }
            });
        },

        /**
         * 上傳壓縮檔文件
         * @param {object} chatArea - 聊天區域實例
         * @param {File} file - 壓縮檔文件
         */
        uploadCompressedFile: function (chatArea, file) {
            const tempMessageId = this.addTempFileMessage(chatArea, file, 'compressed');

            const formData = new FormData();
            formData.append('action', 'otz_upload_compressed_file');
            formData.append('compressed_file', file);
            formData.append('line_user_id', chatArea.currentLineUserId);
            formData.append('nonce', otzChatConfig.nonce);

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // 更新臨時訊息為已上傳狀態
                        this.updateTempFileMessage(chatArea, tempMessageId, response.data.file_url, response.data.file_name);

                        // 發送文件訊息到 LINE
                        this.sendFileMessageToLine(chatArea, response.data.file_url, response.data.file_name, tempMessageId);
                    } else {
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message || '壓縮檔上傳失敗');
                    }
                },
                error: (xhr, status, error) => {
                    this.updateTempMessageError(chatArea, tempMessageId, '網路連線錯誤，請稍後再試');
                }
            });
        },

        /**
         * 上傳影片文件
         * @param {object} chatArea - 聊天區域實例
         * @param {File} file - 影片文件
         */
        uploadVideoFile: function (chatArea, file) {
            const tempMessageId = this.addTempFileMessage(chatArea, file, 'video');

            const formData = new FormData();
            formData.append('action', 'otz_upload_video_file');
            formData.append('video_file', file);
            formData.append('line_user_id', chatArea.currentLineUserId);
            formData.append('nonce', otzChatConfig.nonce);

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        // 更新臨時訊息為已上傳狀態
                        this.updateTempFileMessage(chatArea, tempMessageId, response.data.file_url, response.data.file_name);

                        // 發送文件訊息到 LINE
                        this.sendVideoMessageToLine(chatArea, response.data.file_url, response.data.file_name, tempMessageId);
                    } else {
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message || '影片檔上傳失敗');
                    }
                },
                error: (xhr, status, error) => {
                    this.updateTempMessageError(chatArea, tempMessageId, '網路連線錯誤，請稍後再試');
                }
            });
        },

        /**
         * 添加臨時文件訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {File} file - 文件對象
         * @param {string} fileType - 文件類型 (compressed/video)
         * @return {string} 臨時訊息 ID
         */
        addTempFileMessage: function (chatArea, file, fileType) {
            const tempId = 'temp_' + Date.now();
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);

            let iconClass = 'fa-file';
            if (fileType === 'compressed') {
                iconClass = 'fa-file-archive';
            } else if (fileType === 'video') {
                iconClass = 'fa-file-video';
            }

            const messageHtml = `
                <div class="message-row outgoing temp" data-temp-id="${tempId}">
                    <div class="message-bubble outgoing">
                        <div class="message-content">
                            <div class="message-file">
                                <div class="file-icon">
                                    <i class="fas ${iconClass}"></i>
                                </div>
                                <div class="file-info">
                                    <div class="file-name">${ChatAreaUtils.escapeHtml(file.name)}</div>
                                    <div class="file-size">${fileSizeMB} MB</div>
                                </div>
                                <div class="upload-progress">
                                    <div class="upload-spinner"></div>
                                    <span>上傳中...</span>
                                </div>
                            </div>
                        </div>
                        <div class="message-time">上傳中...</div>
                    </div>
                    <div class="message-avatar">
                        <img src="${ChatAreaUtils.getCurrentUserAvatar()}" alt="avatar" />
                    </div>
                </div>
            `;

            chatArea.chatMessages.append(messageHtml);
            ChatAreaUI.scrollToBottom(chatArea.chatMessages);
            return tempId;
        },

        /**
         * 更新臨時文件訊息
         * @param {object} chatArea - 聊天區域實例
         * @param {string} tempId - 臨時訊息 ID
         * @param {string} fileUrl - 文件 URL
         * @param {string} fileName - 文件名稱
         */
        updateTempFileMessage: function (chatArea, tempId, fileUrl, fileName) {
            const tempMessage = chatArea.chatMessages.find(`[data-temp-id="${tempId}"]`);
            if (tempMessage.length > 0) {
                // 移除上傳中的樣式和進度指示器
                tempMessage.removeClass('temp');
                tempMessage.find('.upload-progress').remove();
                tempMessage.find('.message-time').text('已上傳');

                // 添加下載鏈接
                tempMessage.find('.file-info').append(`<a href="${fileUrl}" target="_blank" class="file-download">下載文件</a>`);
            }
        },

        /**
         * 發送文件訊息到 LINE
         * @param {object} chatArea - 聊天區域實例
         * @param {string} fileUrl - 文件 URL
         * @param {string} fileName - 文件名稱
         * @param {string} tempMessageId - 臨時訊息 ID
         */
        sendFileMessageToLine: function (chatArea, fileUrl, fileName, tempMessageId) {
            const data = {
                action: 'otz_send_file_message',
                line_user_id: chatArea.currentLineUserId,
                file_url: fileUrl,
                file_name: fileName,
                nonce: otzChatConfig.nonce,
                source_type: chatArea.currentSourceType || '',
                group_id: chatArea.currentGroupId || ''
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 發送成功，重新載入訊息
                        setTimeout(() => {
                            ChatAreaMessages.loadMessages(chatArea, false);
                        }, 500);
                    } else {
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.updateTempMessageError(chatArea, tempMessageId, '發送文件失敗，請稍後再試');
                }
            });
        },

        /**
         * 發送影片訊息到 LINE
         * @param {object} chatArea - 聊天區域實例
         * @param {string} videoUrl - 影片 URL
         * @param {string} videoName - 影片名稱
         * @param {string} tempMessageId - 臨時訊息 ID
         */
        sendVideoMessageToLine: function (chatArea, videoUrl, videoName, tempMessageId) {
            const data = {
                action: 'otz_send_video_message',
                line_user_id: chatArea.currentLineUserId,
                video_url: videoUrl,
                video_name: videoName,
                nonce: otzChatConfig.nonce,
                source_type: chatArea.currentSourceType || '',
                group_id: chatArea.currentGroupId || ''
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 發送成功，重新載入訊息
                        setTimeout(() => {
                            ChatAreaMessages.loadMessages(chatArea, false);
                        }, 500);
                    } else {
                        this.updateTempMessageError(chatArea, tempMessageId, response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    this.updateTempMessageError(chatArea, tempMessageId, '發送影片失敗，請稍後再試');
                }
            });
        },

        /**
         * 設置 Bot 狀態監聽器
         * @param {object} chatArea - 聊天區域實例
         */
        setupBotStatusListener: function (chatArea) {
            // 監聽客戶資訊載入完成事件
            $(document).on('customer:info-loaded', (event, data) => {
                if (data && chatArea.currentLineUserId === data.line_user_id) {
                    chatArea.botStatus = data.bot_status;
                    this.checkAndShowBotOverlay(chatArea);
                }
            });

            // 監聽輪詢的 bot_status 變更事件
            $(document).on('polling:bot-status-changed', (event, data) => {
                if (data && chatArea.currentLineUserId === data.line_user_id) {
                    chatArea.botStatus = data.bot_status;
                    this.checkAndShowBotOverlay(chatArea);
                }
            });
        },

        /**
         * 檢查並顯示/隱藏 Bot 遮罩
         * @param {object} chatArea - 聊天區域實例
         */
        checkAndShowBotOverlay: function (chatArea) {
            if (chatArea.botStatus === 'enable') {
                this.showBotOverlay(chatArea);
            } else {
                this.hideBotOverlay(chatArea);
            }
        },

        /**
         * 顯示 Bot 遮罩
         * @param {object} chatArea - 聊天區域實例
         */
        showBotOverlay: function (chatArea) {
            // 如果遮罩已存在，不重複建立
            if (chatArea.container.find('.chat-input-overlay').length > 0) {
                return;
            }

            const botModeText = (typeof otzChatL10n !== 'undefined' && otzChatL10n.bot_mode_active)
                ? otzChatL10n.bot_mode_active
                : '目前為 AI 自動回應模式';
            const switchText = (typeof otzChatL10n !== 'undefined' && otzChatL10n.switch_to_manual)
                ? otzChatL10n.switch_to_manual
                : '切換為手動回覆';

            const overlayHtml = `
                <div class="chat-input-overlay">
                    <div class="manual-reply-prompt">
                        <div class="prompt-text">${botModeText}</div>
                        <button type="button" class="manual-reply-button">
                            <span class="dashicons dashicons-admin-comments"></span>
                            ${switchText}
                        </button>
                    </div>
                </div>
            `;

            // 將遮罩插入到輸入表單容器
            chatArea.inputForm.css('position', 'relative').append(overlayHtml);

            // 綁定按鈕點擊事件
            chatArea.container.find('.manual-reply-button').on('click', () => {
                this.handleManualReplyClick(chatArea);
            });
        },

        /**
         * 隱藏 Bot 遮罩
         * @param {object} chatArea - 聊天區域實例
         */
        hideBotOverlay: function (chatArea) {
            chatArea.container.find('.chat-input-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * 處理手動回覆按鈕點擊
         * @param {object} chatArea - 聊天區域實例
         */
        handleManualReplyClick: function (chatArea) {
            if (!chatArea.currentLineUserId) {
                return;
            }

            // 禁用按鈕，防止重複點擊
            const button = chatArea.container.find('.manual-reply-button');
            button.prop('disabled', true).text('切換中...');

            const data = {
                action: 'otz_switch_to_manual_reply',
                line_user_id: chatArea.currentLineUserId,
                nonce: otzChatConfig.nonce
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 更新 chatArea 的 bot_status
                        chatArea.botStatus = 'disable';
                        // 隱藏遮罩
                        this.hideBotOverlay(chatArea);
                    } else {
                        alert('切換失敗：' + (response.data?.message || '未知錯誤'));
                        button.prop('disabled', false).html('<span class="dashicons dashicons-admin-comments"></span>切換為手動回覆');
                    }
                },
                error: (xhr, status, error) => {
                    alert('網路連線錯誤，請稍後再試');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-admin-comments"></span>切換為手動回覆');
                }
            });
        }
    };

})(jQuery);