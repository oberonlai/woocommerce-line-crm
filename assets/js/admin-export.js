/**
 * OrderChatz 匯出頁面 JavaScript
 *
 * 處理匯出頁面的互動功能，包含 AJAX 請求、進度顯示等
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 匯出頁面主類別
     */
    class OrderChatzExport {

        constructor() {
            this.init();
        }

        /**
         * 初始化
         */
        init() {
            this.initDateInputs();
            this.bindEvents();
            this.loadStorageInfo();
            this.loadFileInfo();
        }

        /**
         * 初始化原生日期輸入
         */
        initDateInputs() {
            const $startDate = $('#export-start-date');
            const $endDate = $('#export-end-date');
            const $button = $('#btn-export-messages');

            // 綁定日期變化事件
            $startDate.on('change', () => {
                const startValue = $startDate.val();
                if (startValue) {
                    $endDate.attr('min', startValue);
                }
                this.validateDateRange();
            });

            $endDate.on('change', () => {
                const endValue = $endDate.val();
                if (endValue) {
                    $startDate.attr('max', endValue);
                }
                this.validateDateRange();
            });

            // 初始設定按鈕為禁用
            $button.prop('disabled', true);
        }

        /**
         * 驗證日期範圍
         */
        validateDateRange() {
            const startDate = $('#export-start-date').val();
            const endDate = $('#export-end-date').val();
            const $button = $('#btn-export-messages');

            if (startDate && endDate) {
                // 驗證日期範圍不超過 31 天
                const start = new Date(startDate);
                const end = new Date(endDate);
                const diffTime = Math.abs(end - start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays <= 31) {
                    $button.prop('disabled', false);
                    this.clearDateError();
                } else {
                    $button.prop('disabled', true);
                    this.showDateError('日期範圍不能超過 31 天');
                }
            } else {
                $button.prop('disabled', true);
                this.clearDateError();
            }
        }

        /**
         * 顯示日期錯誤
         */
        showDateError(message) {
            let $error = $('.date-error');
            if (!$error.length) {
                $('.date-range-inputs').after('<p class="date-error" style="color: #d63638; font-size: 13px; margin: 5px 0 0 0;"></p>');
                $error = $('.date-error');
            }
            $error.text(message);
        }

        /**
         * 清除日期錯誤
         */
        clearDateError() {
            $('.date-error').remove();
        }

        /**
         * 綁定事件
         */
        bindEvents() {
            // 匯出訊息
            $('#btn-export-messages').on('click', (e) => {
                e.preventDefault();
                this.exportMessages();
            });

            // 重新整理存儲資訊
            $('#btn-refresh-storage').on('click', (e) => {
                e.preventDefault();
                this.loadStorageInfo();
                this.loadFileInfo();
            });

            // 打包下載
            $('#btn-download-uploads').on('click', (e) => {
                e.preventDefault();
                this.downloadUploads();
            });

            // 清除檔案
            $('#btn-clear-uploads').on('click', (e) => {
                e.preventDefault();
                this.clearUploads();
            });
        }

        /**
         * 匯出訊息
         */
        exportMessages() {
            const startDate = $('#export-start-date').val();
            const endDate = $('#export-end-date').val();
            const format = $('#export-format').val();
            const $button = $('#btn-export-messages');
            const $status = $('.export-status');

            // 驗證選擇
            if (!startDate || !endDate) {
                this.showError($status, otzExport.strings.error + ': ' + '請選擇日期範圍');
                return;
            }

            // 驗證日期範圍不能超過 31 天
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays > 31) {
                this.showError($status, otzExport.strings.error + ': ' + '日期範圍不能超過 31 天');
                return;
            }

            // 開始匯出
            $button.prop('disabled', true);
            $status.removeClass('success error').addClass('loading')
                .html('<span class="loading-spinner"></span>' + otzExport.strings.exporting);

            $.ajax({
                url: otzExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_export_messages',
                    nonce: otzExport.nonce,
                    start_date: startDate,
                    end_date: endDate,
                    format: format
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess($status, response.data.message);
                        this.createDownloadLink($status, response.data.file_url, '匯出檔案');
                    } else {
                        this.showError($status, response.data || otzExport.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError($status, otzExport.strings.error + ': ' + error);
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        }

        /**
         * 載入存儲資訊
         */
        loadStorageInfo() {
            const $container = $('#storage-info');

            $container.html('<div class="loading">' + otzExport.strings.loading + '</div>');

            $.ajax({
                url: otzExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_storage_info',
                    nonce: otzExport.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderStorageInfo($container, response.data);
                    } else {
                        $container.html('<div class="error">' + (response.data || otzExport.strings.error) + '</div>');
                    }
                },
                error: () => {
                    $container.html('<div class="error">' + otzExport.strings.error + '</div>');
                }
            });
        }

        /**
         * 載入檔案資訊
         */
        loadFileInfo() {
            // 檔案資訊會在載入存儲資訊時一併更新
            // 這個方法保留以維持介面一致性
        }

        /**
         * 打包下載上傳檔案
         */
        downloadUploads() {
            const $button = $('#btn-download-uploads');
            const $status = $button.siblings('.export-status');

            $button.prop('disabled', true);

            if (!$status.length) {
                $button.after('<span class="export-status loading">' +
                    '<span class="loading-spinner"></span>打包中...</span>');
            } else {
                $status.removeClass('success error').addClass('loading')
                    .html('<span class="loading-spinner"></span>打包中...');
            }

            $.ajax({
                url: otzExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_download_uploads',
                    nonce: otzExport.nonce
                },
                success: (response) => {
                    const $statusEl = $button.siblings('.export-status');
                    if (response.success) {
                        this.showSuccess($statusEl, response.data.message);
                        this.createDownloadLink($statusEl, response.data.zip_url, 'ZIP 檔案');
                    } else {
                        this.showError($statusEl, response.data || otzExport.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    const $statusEl = $button.siblings('.export-status');
                    this.showError($statusEl, otzExport.strings.error + ': ' + error);
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        }

        /**
         * 清除上傳檔案
         */
        clearUploads() {
            if (!confirm(otzExport.strings.confirm_clear)) {
                return;
            }

            const $button = $('#btn-clear-uploads');
            const $status = $button.siblings('.export-status');

            $button.prop('disabled', true);

            if (!$status.length) {
                $button.after('<span class="export-status loading">' +
                    '<span class="loading-spinner"></span>清除中...</span>');
            } else {
                $status.removeClass('success error').addClass('loading')
                    .html('<span class="loading-spinner"></span>清除中...');
            }

            $.ajax({
                url: otzExport.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_clear_uploads',
                    nonce: otzExport.nonce
                },
                success: (response) => {
                    const $statusEl = $button.siblings('.export-status');
                    if (response.success) {
                        this.showSuccess($statusEl, response.data.message +
                            ' (釋放空間: ' + response.data.freed_space + ')');
                        // 重新載入資訊
                        setTimeout(() => {
                            this.loadStorageInfo();
                        }, 1000);
                    } else {
                        this.showError($statusEl, response.data || otzExport.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    const $statusEl = $button.siblings('.export-status');
                    this.showError($statusEl, otzExport.strings.error + ': ' + error);
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        }

        /**
         * 渲染存儲資訊
         */
        renderStorageInfo($container, data) {
            let html = '';

            // 資料表資訊
            if (data.tables && data.tables.length > 0) {
                html += '<h4>資料表大小</h4>';
                html += '<table class="storage-table">';
                html += '<thead><tr>';
                html += '<th>資料表名稱</th>';
                html += '<th>記錄數</th>';
                html += '<th>大小</th>';
                html += '</tr></thead>';
                html += '<tbody>';

                data.tables.forEach(table => {
                    html += '<tr>';
                    html += '<td data-label="資料表名稱">' + this.escapeHtml(table.name) + '</td>';
                    html += '<td data-label="記錄數">' + this.formatNumber(table.rows) + '</td>';
                    html += '<td data-label="大小">' + this.escapeHtml(table.size_formatted) + '</td>';
                    html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';
            }

            // 摘要資訊
            html += '<div class="storage-summary">';
            html += '<div class="storage-summary-item">';
            html += '<span class="storage-summary-label">資料庫總大小:</span>';
            html += '<span class="storage-summary-value">' + this.escapeHtml(data.total_db_size) + '</span>';
            html += '</div>';
            html += '</div>';

            $container.html(html);

            // 更新檔案管理區塊 - 只顯示檔案相關資訊
            this.updateFileManagementInfo(data);
        }

        /**
         * 更新檔案管理區塊資訊
         */
        updateFileManagementInfo(data) {
            let html = '';

            html += '<div class="file-info-grid">';
            html += '<div class="file-info-item">';
            html += '<div class="file-info-value">' + this.formatNumber(data.file_count) + '</div>';
            html += '<div class="file-info-label">檔案數量</div>';
            html += '</div>';
            html += '<div class="file-info-item">';
            html += '<div class="file-info-value">' + this.escapeHtml(data.upload_size) + '</div>';
            html += '<div class="file-info-label">檔案大小</div>';
            html += '</div>';
            html += '</div>';

            // 只更新檔案資訊區塊
            $('#file-info').html(html);
        }

        /**
         * 顯示成功訊息
         */
        showSuccess($element, message) {
            $element.removeClass('loading error').addClass('success').html(message);
        }

        /**
         * 顯示錯誤訊息
         */
        showError($element, message) {
            $element.removeClass('loading success').addClass('error').html(message);
        }

        /**
         * 創建下載連結
         */
        createDownloadLink($element, url, linkText) {
            const currentHtml = $element.html();
            const downloadHtml = ' - <a href="' + this.escapeHtml(url) + '" ' +
                'class="download-link" target="_blank" download>' +
                '<span class="dashicons dashicons-download"></span>' +
                linkText + '</a>';
            $element.html(currentHtml + downloadHtml);
        }

        /**
         * HTML 轉義
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * 格式化數字
         */
        formatNumber(num) {
            // 檢查 num 是否為 null、undefined 或非數字
            if (num === null || num === undefined || isNaN(num)) {
                return '0';
            }
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    }

    /**
     * 頁面載入完成後初始化
     */
    $(document).ready(function () {
        // 檢查是否在匯出頁面
        if ($('.orderchatz-export-page').length > 0) {
            new OrderChatzExport();
        }
    });

})(jQuery);