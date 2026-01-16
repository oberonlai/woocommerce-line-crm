/**
 * OrderChatz 訊息匯入功能
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * LINE 訊息匯入器類別
     */
    class LineMessageImporter {
        constructor(ajaxUrl, nonce) {
            this.ajaxUrl = ajaxUrl;
            this.nonce = nonce;
            this.parsedData = null;
            this.currentPage = 1;
            this.perPage = 20;
            this.filteredData = null;
            this.selectedFile = null;
            this.friendId = null;
            this.lineUserId = null;
            this.displayName = null;
            this.currentParsedLineUserId = null; // 記錄當前已解析資料對應的 LINE 使用者 ID

            this.init();
        }

        /**
         * 初始化
         */
        init() {
            this.bindEvents();
        }

        /**
         * 綁定事件
         */
        bindEvents() {
            // 匯入訊息按鈕點擊事件.
            $(document).on('click', '.import-messages-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                this.friendId = $btn.data('friend-id');
                this.lineUserId = $btn.data('line-user-id');
                this.displayName = $btn.data('display-name');
                this.openModal();
            });

            // 燈箱關閉事件.
            $(document).on('click', '.otz-modal-close, .otz-modal-backdrop', () => {
                this.closeModal();
            });

            // 檔案選擇事件.
            $('#select-file-btn').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $('#csv-file-input').click();
            });

            // 檔案拖拽事件.
            $('#file-drop-zone')
                .on('dragover', (e) => {
                    e.preventDefault();
                    $(e.currentTarget).addClass('dragover');
                })
                .on('dragleave', (e) => {
                    e.preventDefault();
                    $(e.currentTarget).removeClass('dragover');
                })
                .on('drop', (e) => {
                    e.preventDefault();
                    $(e.currentTarget).removeClass('dragover');
                    const files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        this.handleFileSelect(files[0]);
                    }
                })

            // 檔案輸入改變事件.
            $('#csv-file-input').on('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelect(file);
                }
            });

            // 移除檔案按鈕.
            $('#remove-file-btn').on('click', () => {
                this.removeFile();
            });

            // 預覽按鈕.
            $('#upload-and-preview-btn').on('click', () => {
                this.previewMessages();
            });

            // 返回上一步按鈕.
            $('#back-to-upload-btn').on('click', () => {
                this.showStep('upload');
            });

            // 套用篩選按鈕.
            $('#apply-filter-btn').on('click', () => {
                this.applyFilters();
            });

            // 重設篩選按鈕.
            $('#reset-filter-btn').on('click', () => {
                this.resetFilters();
            });

            // 全選訊息按鈕.
            $('#select-all-messages').on('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });

            // 開始匯入按鈕.
            $('#start-import-btn').on('click', () => {
                this.startImport();
            });

            // 取消匯入按鈕.
            $('#cancel-import-btn').on('click', () => {
                this.cancelImport();
            });

            // 繼續匯入按鈕.
            $('#continue-import-btn').on('click', () => {
                this.continueImport();
            });

            // 訊息選擇改變事件.
            $(document).on('change', '.message-checkbox', () => {
                this.updateSelectionCount();
                this.updateImportButton();
            });

        }

        /**
         * 開啟燈箱
         */
        openModal() {
            // 設定好友資訊.
            $('#friend-display-name').text(this.displayName);
            $('#friend-line-id').text(`LINE ID: ${this.lineUserId}`);

            // 檢查是否已有相同用戶的解析資料.
            const hasSameUserData = this.currentParsedLineUserId === this.lineUserId &&
                                   this.parsedData &&
                                   this.filteredData;

            if (hasSameUserData) {
                // 如果有相同用戶的資料，直接跳到預覽步驟.
                this.showStep('preview');
                $('#preview-loading').hide();
                $('#preview-results').show();
                $('#preview-error').hide();
            } else {
                // 如果沒有資料或是不同用戶，重置表單.
                this.resetModal();
            }

            // 顯示燈箱.
            $('#message-import-modal').show();
        }

        /**
         * 關閉燈箱
         */
        closeModal() {
            $('#message-import-modal').hide();
            // 關閉時不重置資料，以便用戶可以重新打開並繼續使用已解析的資料.
        }

        /**
         * 重置燈箱
         */
        resetModal(fullReset = true) {
            if (fullReset) {
                // 完全重置：清空所有資料.
                this.selectedFile = null;
                this.parsedData = null;
                this.filteredData = null;
                this.currentParsedLineUserId = null;

                // 重置檔案選擇.
                $('#csv-file-input').val('');
                $('#file-selected-info').hide();
                $('#file-drop-zone').show();
                $('#upload-and-preview-btn').prop('disabled', true);
            }

            // 顯示第一步.
            this.showStep('upload');

            // 隱藏錯誤訊息.
            $('#preview-error').hide();
            $('#preview-results').hide();
        }

        /**
         * 處理檔案選擇
         */
        handleFileSelect(file) {
            // 驗證檔案類型.
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert(otz_message_import.messages.invalid_file_type);
                return;
            }

            // 驗證檔案大小 (10MB).
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert(otz_message_import.messages.file_too_large);
                return;
            }

            this.selectedFile = file;

            // 顯示檔案資訊.
            $('#selected-file-name').text(file.name);
            $('#selected-file-size').text(this.formatFileSize(file.size));
            $('#file-drop-zone').hide();
            $('#file-selected-info').show();
            $('#upload-and-preview-btn').prop('disabled', false);
        }

        /**
         * 移除檔案
         */
        removeFile() {
            this.selectedFile = null;
            $('#csv-file-input').val('');
            $('#file-selected-info').hide();
            $('#file-drop-zone').show();
            $('#upload-and-preview-btn').prop('disabled', true);
        }

        /**
         * 格式化檔案大小
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        /**
         * 預覽訊息
         */
        async previewMessages() {
            if (!this.selectedFile) return;

            this.showStep('preview');
            $('#preview-loading').show();
            $('#preview-results').hide();
            $('#preview-error').hide();

            try {
                const result = await this.callPreviewAPI();
                this.parsedData = result.parsed_data;
                this.filteredData = [...this.parsedData];
                this.currentParsedLineUserId = this.lineUserId; // 記錄當前解析的用戶 ID

                this.displayStatistics(result.statistics);
                this.displayMessages();

                $('#preview-loading').hide();
                $('#preview-results').show();
            } catch (error) {
                console.error('預覽失敗:', error);
                this.showError(error.message);
                $('#preview-loading').hide();
                $('#preview-error').show();
            }
        }

        /**
         * 呼叫預覽 API
         */
        async callPreviewAPI() {
            const formData = new FormData();
            formData.append('action', 'otz_preview_csv_messages');
            formData.append('csv_file', this.selectedFile);
            formData.append('line_user_id', this.lineUserId);
            formData.append('nonce', this.nonce);

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.data.message || otz_message_import.messages.preview_failed);
            }
        }

        /**
         * 顯示統計資訊
         */
        displayStatistics(statistics) {
            const html = `
                <div class="stat-item">
                    <div class="stat-number">${statistics.total_messages}</div>
                    <div class="stat-label">${otz_message_import.messages.total_messages}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${statistics.user_messages}</div>
                    <div class="stat-label">${otz_message_import.messages.user_messages}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${statistics.account_messages}</div>
                    <div class="stat-label">${otz_message_import.messages.account_messages}</div>
                </div>
            `;
            $('#preview-statistics').html(html);

            // 設定日期篩選預設值.
            if (statistics.date_range) {
                $('#filter-start-date').val(statistics.date_range.start);
                $('#filter-end-date').val(statistics.date_range.end);
            }
        }

        /**
         * 顯示訊息列表
         */
        displayMessages() {
            // 先排序 filteredData (從新到舊).
            const sortedData = [...this.filteredData].sort((a, b) => {
                return new Date(b.sent_date + ' ' + b.sent_time) - new Date(a.sent_date + ' ' + a.sent_time);
            });

            let html = '';
            sortedData.forEach((message, index) => {
                const senderClass = message.sender_type === 'user' ? 'sender-user' : 'sender-account';

                html += `
                    <div class="message-item" data-index="${index}">
                        <div class="message-checkbox-container">
                            <input type="checkbox" class="message-checkbox" data-index="${index}" ${!message.is_duplicate ? 'checked' : ''}>
                        </div>
                        <div class="message-content">
                            <div class="message-header">
                                <div class="message-text">${this.escapeHtml(message.message_content)}</div>
                                <div>
                                    <span class="message-datetime">${message.sent_date} ${message.sent_time}</span>
                                </div>
                            </div>
                            <span class="message-sender ${senderClass}">${message.sender_name}</span>
                        </div>
                    </div>
                `;
            });

            $('#messages-list').html(html);
            this.updateSelectionCount();
            this.updateImportButton();
        }

        /**
         * 套用篩選
         */
        applyFilters() {
            const startDate = $('#filter-start-date').val();
            const endDate = $('#filter-end-date').val();
            const keyword = $('#filter-keyword').val();
            const includeUser = $('#filter-user').is(':checked');
            const includeAccount = $('#filter-account').is(':checked');

            let filtered = [...this.parsedData];


            // 日期篩選.
            if (startDate) {
                filtered = filtered.filter(msg => {
                    // 統一日期格式：將 / 替換為 -
                    const normalizedMsgDate = msg.sent_date.replace(/\//g, '-');
                    const msgDate = new Date(normalizedMsgDate);
                    const filterDate = new Date(startDate);
                    return msgDate >= filterDate;
                });
            }
            if (endDate) {
                filtered = filtered.filter(msg => {
                    // 統一日期格式：將 / 替換為 -
                    const normalizedMsgDate = msg.sent_date.replace(/\//g, '-');
                    const msgDate = new Date(normalizedMsgDate);
                    const filterDate = new Date(endDate);
                    return msgDate <= filterDate;
                });
            }

            // 關鍵字篩選.
            if (keyword && keyword.trim()) {
                const keywords = keyword.trim().toLowerCase().split(/\s+/);
                filtered = filtered.filter(msg => {
                    const content = msg.message_content.toLowerCase();
                    return keywords.every(kw => content.includes(kw));
                });
            }

            // 傳送者類型篩選.
            const senderTypes = [];
            if (includeUser) senderTypes.push('user');
            if (includeAccount) senderTypes.push('account');

            if (senderTypes.length > 0) {
                filtered = filtered.filter(msg => senderTypes.includes(msg.sender_type.toLowerCase()));
            }

            // 排序篩選結果 (從新到舊).
            filtered.sort((a, b) => {
                return new Date(b.sent_date + ' ' + b.sent_time) - new Date(a.sent_date + ' ' + a.sent_time);
            });

            this.filteredData = filtered;
            this.displayMessages();
        }

        /**
         * 重設篩選條件
         */
        resetFilters() {
            // 清除日期篩選.
            $('#filter-start-date').val('');
            $('#filter-end-date').val('');

            // 清除關鍵字篩選.
            $('#filter-keyword').val('');

            // 重新勾選所有傳送者類型.
            $('#filter-user').prop('checked', true);
            $('#filter-account').prop('checked', true);

            // 恢復顯示所有原始資料.
            this.filteredData = [...this.parsedData];

            // 重新顯示訊息.
            this.displayMessages();
        }

        /**
         * 更新分頁
         */
        updatePagination() {
            const totalPages = Math.ceil(this.filteredData.length / this.perPage);
            const start = (this.currentPage - 1) * this.perPage + 1;
            const end = Math.min(this.currentPage * this.perPage, this.filteredData.length);

            let html = `
                <button class="pagination-btn" data-page="1" ${this.currentPage === 1 ? 'disabled' : ''}>首頁</button>
                <button class="pagination-btn" data-page="${this.currentPage - 1}" ${this.currentPage === 1 ? 'disabled' : ''}>上一頁</button>
                <span class="pagination-info">第 ${start}-${end} 項，共 ${this.filteredData.length} 項</span>
                <button class="pagination-btn" data-page="${this.currentPage + 1}" ${this.currentPage === totalPages ? 'disabled' : ''}>下一頁</button>
                <button class="pagination-btn" data-page="${totalPages}" ${this.currentPage === totalPages ? 'disabled' : ''}>末頁</button>
            `;

            $('#pagination-controls').html(html);
        }

        /**
         * 跳轉到指定頁面
         */
        goToPage(page) {
            const totalPages = Math.ceil(this.filteredData.length / this.perPage);
            if (page < 1 || page > totalPages) return;

            this.currentPage = page;
            this.displayMessages();
            this.updatePagination();
        }

        /**
         * 切換全選
         */
        toggleSelectAll(checked) {
            $('.message-checkbox').prop('checked', checked);
            this.updateSelectionCount();
            this.updateImportButton();
        }

        /**
         * 更新選擇計數
         */
        updateSelectionCount() {
            const totalSelected = $('.message-checkbox:checked').length;
            $('#selection-count').text(`已選擇：${totalSelected} 則訊息`);

            // 更新全選按鈕狀態.
            const totalCheckboxes = $('.message-checkbox').length;
            const allChecked = totalSelected === totalCheckboxes && totalCheckboxes > 0;
            $('#select-all-messages').prop('checked', allChecked);
        }

        /**
         * 更新匯入按鈕狀態
         */
        updateImportButton() {
            const hasSelection = $('.message-checkbox:checked').length > 0;
            $('#start-import-btn').prop('disabled', !hasSelection);
        }

        /**
         * 開始匯入
         */
        async startImport() {
            // 先取得當前顯示的排序資料（與 displayMessages 中的邏輯一致）
            const sortedData = [...this.filteredData].sort((a, b) => {
                return new Date(b.sent_date + ' ' + b.sent_time) - new Date(a.sent_date + ' ' + a.sent_time);
            });

            const selectedIndices = $('.message-checkbox:checked').map((i, element) => {
                const filteredIndex = parseInt($(element).data('index'));
                const selectedMessage = sortedData[filteredIndex];

                if (!selectedMessage) {
                    console.warn('找不到索引對應的訊息:', filteredIndex);
                    return -1;
                }

                // 在原始資料中找到對應的索引
                const originalIndex = this.parsedData.findIndex(msg =>
                    msg.sent_date === selectedMessage.sent_date &&
                    msg.sent_time === selectedMessage.sent_time &&
                    msg.message_content === selectedMessage.message_content &&
                    msg.sender_name === selectedMessage.sender_name
                );
                return originalIndex;
            }).get().filter(index => index !== -1);

            if (selectedIndices.length === 0) {
                alert(otz_message_import.messages.no_messages_selected);
                return;
            }

            this.showStep('import');
            $('#import-progress-bar').css('width', '0%');
            $('#import-status-text').text(otz_message_import.messages.preparing);

            try {
                const result = await this.callImportAPI(selectedIndices);
                this.showImportResults(result);
                this.showStep('complete');
            } catch (error) {
                console.error('匯入失敗:', error);
                alert(otz_message_import.messages.import_failed + ': ' + error.message);
                this.showStep('preview');
            }
        }

        /**
         * 呼叫匯入 API
         */
        async callImportAPI(selectedIndices) {
            const data = {
                action: 'otz_import_messages',
                parsed_messages: JSON.stringify(this.parsedData),
                line_user_id: this.lineUserId,
                nonce: this.nonce,
                selected_indices: selectedIndices
            };

            // 更新進度.
            $('#import-status-text').text(otz_message_import.messages.importing);
            $('#import-progress-bar').css('width', '50%');

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            });

            // 更新進度.
            $('#import-progress-bar').css('width', '80%');

            const result = await response.json();

            if (result.success) {
                $('#import-progress-bar').css('width', '100%');
                $('#import-status-text').text(otz_message_import.messages.completed);
                return result.data;
            } else {
                throw new Error(result.data.message || otz_message_import.messages.import_failed);
            }
        }

        /**
         * 顯示匯入結果
         */
        showImportResults(results) {
            const html = `
                <div class="result-stat">
                    <div class="result-number">${results.total_processed}</div>
                    <div class="result-label">${otz_message_import.messages.total_processed}</div>
                </div>
                <div class="result-stat">
                    <div class="result-number">${results.imported}</div>
                    <div class="result-label">${otz_message_import.messages.imported}</div>
                </div>
                <div class="result-stat">
                    <div class="result-number">${results.skipped}</div>
                    <div class="result-label">${otz_message_import.messages.skipped}</div>
                </div>
                <div class="result-stat">
                    <div class="result-number">${results.errors}</div>
                    <div class="result-label">${otz_message_import.messages.errors}</div>
                </div>
            `;
            $('#import-results').html(html);

            // 顯示錯誤訊息（如果有）.
            if (results.error_messages && results.error_messages.length > 0) {
                const errorHtml = '<div style="margin-top: 15px;"><h5>錯誤詳情：</h5><ul>' +
                    results.error_messages.map(msg => `<li>${this.escapeHtml(msg)}</li>`).join('') +
                    '</ul></div>';
                $('#import-results').append(errorHtml);
            }
        }

        /**
         * 取消匯入
         */
        cancelImport() {
            if (confirm(otz_message_import.messages.cancel_confirm)) {
                this.showStep('preview');
            }
        }

        /**
         * 繼續匯入
         */
        continueImport() {
            // 重置匯入進度相關的UI狀態.
            $('#import-progress-bar').css('width', '0%');
            $('#import-status-text').text(otz_message_import.messages.preparing);

            // 切換回預覽步驟.
            this.showStep('preview');

            // 重新更新訊息選擇狀態和按鈕.
            this.updateSelectionCount();
            this.updateImportButton();
        }

        /**
         * 顯示步驟
         */
        showStep(step) {
            // 隱藏所有步驟.
            $('.import-step').hide();
            $('.modal-actions').hide();

            // 顯示對應步驟.
            switch (step) {
                case 'upload':
                    $('#upload-step').show();
                    $('#upload-actions').show();
                    break;
                case 'preview':
                    $('#preview-step').show();
                    $('#preview-actions').show();
                    break;
                case 'import':
                    $('#import-step').show();
                    $('#import-actions').show();
                    break;
                case 'complete':
                    $('#complete-step').show();
                    $('#complete-actions').show();
                    break;
            }
        }

        /**
         * 顯示錯誤
         */
        showError(message) {
            $('#error-message').text(message);
        }

        /**
         * HTML 跳脫
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // 初始化.
    $(document).ready(function () {
        if (typeof otz_message_import !== 'undefined') {
            new LineMessageImporter(otz_message_import.ajax_url, otz_message_import.nonce);
        }
    });

})(jQuery);