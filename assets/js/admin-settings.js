/**
 * OrderChatz Admin Settings JavaScript
 * 
 * Handles admin interface interactions for LINE API configuration,
 * webhook registration, and status updates.
 */

(function($) {
    'use strict';

    /**
     * Admin settings object
     */
    const otzAdminSettings = {
        
        /**
         * Initialize admin settings functionality
         */
        init: function() {
            this.bindEvents();
            this.initPasswordToggle();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            $('#verify-webhook').on('click', this.verifyWebhook.bind(this));
            $('#test-connection').on('click', this.testConnection.bind(this));
            $('#register-webhook').on('click', this.registerWebhook.bind(this));
            
            // Handle save button click
            $('#save-settings').on('click', this.saveSettings.bind(this));
        },

        /**
         * Initialize password field toggle functionality
         */
        initPasswordToggle: function() {
            window.togglePasswordVisibility = function(fieldId) {
                const field = document.getElementById(fieldId);
                const button = field.nextElementSibling;
                
                if (field.type === 'password') {
                    field.type = 'text';
                    button.textContent = '隱藏';
                } else {
                    field.type = 'password';
                    button.textContent = '顯示';
                }
            };

            window.copyToClipboard = function(text) {
                navigator.clipboard.writeText(text).then(function() {
                    otzAdminSettings.showMessage('已複製到剪貼簿', 'success');
                }).catch(function(err) {
                    console.error('無法複製到剪貼簿: ', err);
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    otzAdminSettings.showMessage('已複製到剪貼簿', 'success');
                });
            };
        },

        /**
         * Save settings via AJAX
         */
        saveSettings: function(e) {
            e.preventDefault(); // Prevent any default behavior
            
            const submitButton = $('#save-settings');
            const originalText = submitButton.text();
            
            // Disable submit button and show loading state
            submitButton.prop('disabled', true).text('正在保存...');
            
            this.showMessage('正在保存設定...', 'info');

            // Collect form data
            const formData = {
                action: 'otz_save_settings',
                nonce: otzAdmin.nonce,
                otz_access_token: $('#otz_access_token').val(),
                otz_channel_secret: $('#otz_channel_secret').val()
            };

            $.ajax({
                url: otzAdmin.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        otzAdminSettings.showMessage(response.data.message, 'success');
                    } else {
                        otzAdminSettings.showMessage(
                            '保存失敗: ' + response.data.message, 
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    otzAdminSettings.showMessage(
                        'AJAX 請求失敗: ' + error, 
                        'error'
                    );
                },
                complete: function() {
                    submitButton.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Register webhook via AJAX
         */
        registerWebhook: function() {
            const button = $('#register-webhook');
            const originalText = button.text();
            const statusSpan = $('#webhook-registration-status');
            
            // Disable button and show loading state
            button.prop('disabled', true).text('註冊中...');
            statusSpan.html('<span style="color: #0073aa;">註冊中...</span>');
            
            this.showMessage('正在註冊 Webhook URL...', 'info');

            $.ajax({
                url: otzAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_register_webhook',
                    nonce: otzAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        otzAdminSettings.showMessage(response.data.message, 'success');
                        statusSpan.html('<span style="color: #46b450;">✓ 註冊成功</span>');
                        
                        if (response.data.manual_action_required) {
                            otzAdminSettings.showMessage(
                                '⚠️ 重要：請手動到 LINE Console 啟用 "Use webhook" 選項！',
                                'warning'
                            );
                        }
                    } else {
                        otzAdminSettings.showMessage(
                            '註冊失敗: ' + response.data.message, 
                            'error'
                        );
                        statusSpan.html('<span style="color: #dc3232;">✗ 註冊失敗</span>');
                    }
                },
                error: function(xhr, status, error) {
                    otzAdminSettings.showMessage(
                        'AJAX 請求失敗: ' + error, 
                        'error'
                    );
                    statusSpan.html('<span style="color: #dc3232;">✗ 請求失敗</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Verify webhook registration status
         */
        verifyWebhook: function() {
            const button = $('#verify-webhook');
            const originalText = button.text();
            
            button.prop('disabled', true).text(otzAdmin.strings.verifying);
            
            this.showMessage('正在驗證 Webhook 狀態...', 'info');

            $.ajax({
                url: otzAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_verify_webhook',
                    nonce: otzAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const status = response.data.is_valid ? '驗證成功' : '驗證失敗';
                        const type = response.data.is_valid ? 'success' : 'warning';
                        
                        otzAdminSettings.showMessage(
                            status + ': ' + response.data.message, 
                            type
                        );
                        
                        if (response.data.status) {
                            otzAdminSettings.updateWebhookStatus(response.data.status);
                        }
                    } else {
                        otzAdminSettings.showMessage(
                            '驗證失敗: ' + response.data.message, 
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    otzAdminSettings.showMessage(
                        'AJAX 請求失敗: ' + error, 
                        'error'
                    );
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Test LINE API connection
         */
        testConnection: function() {
            const button = $('#test-connection');
            const originalText = button.text();
            
            button.prop('disabled', true).text(otzAdmin.strings.testing);
            
            this.showMessage('正在測試 LINE API 連線...', 'info');

            $.ajax({
                url: otzAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_test_api_connection',
                    nonce: otzAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        otzAdminSettings.showMessage(
                            '連線測試成功: ' + response.data.message, 
                            'success'
                        );
                    } else {
                        otzAdminSettings.showMessage(
                            '連線測試失敗: ' + response.data.message, 
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    otzAdminSettings.showMessage(
                        'AJAX 請求失敗: ' + error, 
                        'error'
                    );
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Update webhook status display
         */
        updateWebhookStatus: function(status) {
            if (!status.success) {
                return;
            }

            const container = $('#webhook-status-container');
            let statusClass = '';
            let statusText = '';

            switch (status.status) {
                case 'registered':
                    statusClass = 'otz-status-success';
                    statusText = '已註冊';
                    break;
                case 'pending':
                    statusClass = 'otz-status-warning';
                    statusText = '待註冊';
                    break;
                case 'failed':
                    statusClass = 'otz-status-error';
                    statusText = '註冊失敗';
                    break;
                case 'invalid_token':
                    statusClass = 'otz-status-error';
                    statusText = 'Token 無效';
                    break;
                default:
                    statusClass = 'otz-status-unknown';
                    statusText = '未設定';
            }

            let html = '<span class="otz-status ' + statusClass + '">' + statusText + '</span>';

            if (status.webhook_url) {
                html += '<p class="description">註冊 URL: ' + status.webhook_url + '</p>';
            }

            if (status.last_check) {
                html += '<p class="description">最後檢查: ' + status.last_check + '</p>';
            }

            container.html(html);
        },

        /**
         * Update webhook status text (simplified version)
         */
        updateWebhookStatusText: function(status) {
            const container = $('#webhook-status-container');
            let statusClass = '';
            let statusText = '';

            switch (status) {
                case 'registered':
                    statusClass = 'otz-status-success';
                    statusText = '已註冊';
                    break;
                case 'failed':
                    statusClass = 'otz-status-error';
                    statusText = '註冊失敗';
                    break;
                default:
                    statusClass = 'otz-status-unknown';
                    statusText = '未設定';
            }

            const html = '<span class="otz-status ' + statusClass + '">' + statusText + '</span>';
            container.html(html);
        },

        /**
         * Show status message to user
         */
        showMessage: function(message, type) {
            const messagesContainer = $('#otz-status-messages');
            const messageClass = 'notice notice-' + (type === 'success' ? 'success' : 
                                                      type === 'error' ? 'error' : 
                                                      type === 'warning' ? 'warning' : 'info');
            
            const messageHtml = '<div class="' + messageClass + ' is-dismissible">' +
                               '<p>' + message + '</p>' +
                               '<button type="button" class="notice-dismiss">' +
                               '<span class="screen-reader-text">Dismiss this notice.</span>' +
                               '</button></div>';
            
            messagesContainer.html(messageHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                messagesContainer.find('.notice').fadeOut();
            }, 5000);
            
            // Handle dismiss button
            messagesContainer.find('.notice-dismiss').on('click', function() {
                $(this).parent().fadeOut();
            });
            
            // Scroll to messages
            $('html, body').animate({
                scrollTop: messagesContainer.offset().top - 100
            }, 500);
        }
    };

    /**
     * 訂單 Meta 設定管理器
     */
    const OrderMetaSettingsManager = {
        
        fieldCounter: 0,
        
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initializeExistingFields();
            this.updateSaveButtonState();
        },

        /**
         * 綁定事件
         */
        bindEvents: function() {
            // 新增欄位按鈕
            $(document).on('click', '.add-meta-field', this.addField.bind(this));
            
            // 刪除欄位按鈕
            $(document).on('click', '.remove-field', this.removeField.bind(this));
            
            // 欄位輸入變化
            $(document).on('input', '.field-name, .field-key', this.handleFieldChange.bind(this));
            
            // 表單提交
            $('#otz-order-meta-form').on('submit', this.handleFormSubmit.bind(this));
        },

        /**
         * 初始化現有欄位
         */
        initializeExistingFields: function() {
            const existingFields = $('.meta-field-row');
            this.fieldCounter = existingFields.length;
            
            // 為現有欄位設置索引
            existingFields.each((index, element) => {
                $(element).attr('data-index', index);
            });
        },

        /**
         * 添加新欄位
         */
        addField: function(e) {
            e.preventDefault();
            
            const fieldHtml = this.createFieldHtml(this.fieldCounter, '', '');
            const fieldsContainer = $('.meta-fields-list');
            
            // 移除空狀態訊息
            fieldsContainer.find('.no-fields-message').remove();
            
            // 添加新欄位
            fieldsContainer.append(fieldHtml);
            
            // 聚焦到第一個輸入框
            fieldsContainer.find(`[data-index="${this.fieldCounter}"] .field-name`).focus();
            
            this.fieldCounter++;
            this.updateSaveButtonState();
        },

        /**
         * 創建欄位 HTML
         */
        createFieldHtml: function(index, name, key) {
            return `
                <div class="meta-field-row" data-index="${index}">
                    <div class="field-inputs">
                        <div class="input-group">
                            <label>欄位名稱</label>
                            <input type="text" name="meta_fields[${index}][name]" value="${this.escapeHtml(name)}" 
                                   placeholder="例如：付款方式" class="field-name" />
                        </div>
                        <div class="input-group">
                            <label>Meta Key</label>
                            <input type="text" name="meta_fields[${index}][key]" value="${this.escapeHtml(key)}" 
                                   placeholder="例如：_payment_method_title" class="field-key" />
                        </div>
                    </div>
                    <div class="field-actions">
                        <button type="button" class="button button-small remove-field" title="刪除欄位">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * 移除欄位
         */
        removeField: function(e) {
            e.preventDefault();
            
            if (!confirm((typeof otzSettings !== 'undefined' && otzSettings.strings.confirm_delete) || '確定要刪除這個欄位嗎？')) {
                return;
            }
            
            const fieldRow = $(e.target).closest('.meta-field-row');
            fieldRow.fadeOut(300, () => {
                fieldRow.remove();
                this.checkEmptyState();
                this.updateSaveButtonState();
            });
        },

        /**
         * 檢查空狀態
         */
        checkEmptyState: function() {
            const fieldsContainer = $('.meta-fields-list');
            const remainingFields = fieldsContainer.find('.meta-field-row');
            
            if (remainingFields.length === 0) {
                const emptyMessage = `
                    <div class="no-fields-message">
                        <p>尚未設定任何自訂欄位。點擊「新增欄位」按鈕開始設定。</p>
                    </div>
                `;
                fieldsContainer.append(emptyMessage);
            }
        },

        /**
         * 處理欄位變化
         */
        handleFieldChange: function() {
            this.updateSaveButtonState();
        },

        /**
         * 更新儲存按鈕狀態
         */
        updateSaveButtonState: function() {
            const saveButton = $('.save-settings');
            const hasValidFields = this.hasValidFields();
            
            saveButton.prop('disabled', !hasValidFields);
        },

        /**
         * 檢查是否有有效欄位
         */
        hasValidFields: function() {
            const fields = $('.meta-field-row');
            let hasValid = false;
            
            fields.each(function() {
                const name = $(this).find('.field-name').val().trim();
                const key = $(this).find('.field-key').val().trim();
                
                if (name && key) {
                    hasValid = true;
                    return false; // 跳出 each 循環
                }
            });
            
            return hasValid;
        },

        /**
         * 處理表單提交
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            // 驗證欄位
            if (!this.validateFields()) {
                return;
            }
            
            this.saveSettings();
        },

        /**
         * 驗證欄位
         */
        validateFields: function() {
            let isValid = true;
            const fields = $('.meta-field-row');
            
            // 清除之前的錯誤狀態
            fields.find('input').removeClass('error');
            
            fields.each(function() {
                const row = $(this);
                const name = row.find('.field-name').val().trim();
                const key = row.find('.field-key').val().trim();
                
                if (!name) {
                    row.find('.field-name').addClass('error');
                    isValid = false;
                }
                
                if (!key) {
                    row.find('.field-key').addClass('error');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                this.showStatus('error', '請填寫所有必填欄位');
            }
            
            return isValid;
        },

        /**
         * 儲存設定
         */
        saveSettings: function() {
            const saveButton = $('.save-settings');
            const originalText = saveButton.text();
            
            // 顯示載入狀態
            saveButton.prop('disabled', true).text('儲存中...');
            this.hideStatus();
            
            // 收集欄位資料
            const formData = this.collectFormData();
            
            $.ajax({
                url: (typeof otzSettings !== 'undefined' ? otzSettings.ajax_url : ajaxurl),
                type: 'POST',
                data: formData,
                success: (response) => {
                    saveButton.prop('disabled', false).text(originalText);
                    
                    if (response.success) {
                        this.showStatus('success', response.data.message || '設定已儲存');
                        this.updateSaveButtonState();
                    } else {
                        this.showStatus('error', response.data?.message || '儲存失敗');
                    }
                },
                error: (xhr, status, error) => {
                    saveButton.prop('disabled', false).text(originalText);
                    console.error('AJAX 錯誤:', { xhr, status, error });
                    
                    let errorMessage = '儲存失敗，請重試';
                    if (xhr.responseJSON?.data?.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    
                    this.showStatus('error', errorMessage);
                }
            });
        },

        /**
         * 收集表單資料
         */
        collectFormData: function() {
            const formData = {
                action: 'otz_save_order_settings',
                nonce: (typeof otzSettings !== 'undefined' ? otzSettings.nonce : ''),
                meta_fields: {}
            };
            
            $('.meta-field-row').each(function(index) {
                const name = $(this).find('.field-name').val().trim();
                const key = $(this).find('.field-key').val().trim();
                
                if (name && key) {
                    formData.meta_fields[index] = {
                        name: name,
                        key: key
                    };
                }
            });
            
            return formData;
        },

        /**
         * 顯示狀態訊息
         */
        showStatus: function(type, message) {
            const statusElement = $('.save-status');
            
            statusElement
                .removeClass('success error')
                .addClass(type)
                .text(message)
                .fadeIn(300);
            
            // 成功訊息 3 秒後自動隱藏
            if (type === 'success') {
                setTimeout(() => {
                    this.hideStatus();
                }, 3000);
            }
        },

        /**
         * 隱藏狀態訊息
         */
        hideStatus: function() {
            $('.save-status').fadeOut(300);
        },

        /**
         * HTML 跳脫
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        otzAdminSettings.init();
        
        // 只在訂單 meta 設定頁面初始化
        if ($('.otz-order-meta-settings').length) {
            OrderMetaSettingsManager.init();
        }
    });

})(jQuery);