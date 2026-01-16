/**
 * OrderChatz 聊天區域排程訊息模組
 *
 * 處理排程訊息的建立、管理和執行功能
 * 包含排程列表、新增/編輯表單、API 交互等完整功能
 *
 * @package OrderChatz
 * @since 1.1.0
 */

(function ($) {
    'use strict';
    /**
     * 排程訊息管理器
     */
    window.ChatAreaMessageCron = {
        /**
         * 建立排程訊息實例
         * @param {object} chatAreaInstance - 聊天區域實例
         * @return {object} 排程訊息實例
         */
        createInstance: function (chatAreaInstance) {
            const instance = {
                chatArea: chatAreaInstance,
                container: chatAreaInstance.container,
                scheduleButton: null,
                modal: null,
                isInitialized: false,
                currentCronList: [],
                editingCronId: null,

                // DOM 元素
                elements: {
                    scheduleBtn: null,
                    modal: null,
                    modalBackdrop: null,
                    modalContainer: null,
                    cronList: null,
                    cronForm: null,
                    loadingOverlay: null
                }
            };

            // 初始化模組
            this.initModule(instance);

            return instance;
        },

        /**
         * 初始化模組
         * @param {object} instance - 實例
         */
        initModule: function (instance) {
            if (instance.isInitialized) {
                return;
            }

            try {
                // 初始化排程按鈕
                this.initScheduleButton(instance);

                // 初始化 Modal
                this.initModal(instance);

                // 綁定事件
                this.bindEvents(instance);

                instance.isInitialized = true;

            } catch (error) {
                console.error('ChatAreaMessageCron 初始化失敗:', error);
            }
        },

        /**
         * 初始化排程按鈕
         * @param {object} instance - 實例
         */
        initScheduleButton: function (instance) {
            // 尋找靜態 HTML 中的排程按鈕
            const $scheduleBtn = instance.container.find('#schedule-message-btn');

            if ($scheduleBtn.length === 0) {
                console.error('找不到排程按鈕元素');
                return;
            }

            instance.elements.scheduleBtn = $scheduleBtn;
        },

        /**
         * 初始化 Modal 結構
         * @param {object} instance - 實例
         */
        initModal: function (instance) {
            // 尋找靜態 HTML 中的 Modal 元素
            const $modal = $('#message-cron-modal');

            if ($modal.length === 0) {
                console.error('找不到排程訊息 Modal 元素');
                return;
            }

            instance.elements.modal = $modal;
            instance.elements.modalBackdrop = $modal.find('.modal-backdrop');
            instance.elements.modalContainer = $modal.find('.modal-container');
        },

        /**
         * 綁定事件
         * @param {object} instance - 實例
         */
        bindEvents: function (instance) {
            const self = this;

            // 排程按鈕點擊事件
            instance.elements.scheduleBtn.on('click', function () {
                self.openModal(instance);
            });

            // Modal 關閉事件
            instance.elements.modal.on('click', '.modal-close, .modal-backdrop', function () {
                self.closeModal(instance);
            });

            // 新增排程按鈕
            instance.elements.modal.on('click', '#cron-add-new-btn', function () {
                self.showFormSection(instance);
            });

            // 返回列表按鈕
            instance.elements.modal.on('click', '.cron-form-back-btn', function () {
                self.showListSection(instance);
            });

            // 排程類型變更
            instance.elements.modal.on('change', '#cron-form-type', function () {
                self.toggleRecurringFields(instance);
            });

            // 重複間隔變更
            instance.elements.modal.on('change', '#cron-form-interval', function () {
                self.handleIntervalChange(instance);
            });

            // 訊息類型變更
            instance.elements.modal.on('change', 'input[name="cron_message_type"]', function () {
                self.handleMessageTypeChange(instance);
            });

            // 檔案選擇按鈕
            instance.elements.modal.on('click', '.cron-file-select-btn', function () {
                self.handleFileSelect(instance);
            });

            // 檔案移除按鈕
            instance.elements.modal.on('click', '.cron-file-remove-btn', function () {
                self.handleFileRemove(instance);
            });

            // 檔案輸入變更
            instance.elements.modal.on('change', '#cron-form-file', function () {
                self.handleFileInputChange(instance, this.files[0]);
            });

            // 初始化拖曳上傳
            this.initDragDropUpload(instance);

            // 初始化日期時間限制
            this.initDateTimeConstraints(instance);

            // 表單提交
            instance.elements.modal.on('submit', '#cron-form', function (e) {
                e.preventDefault();
                self.submitCronForm(instance);
            });

            // 日期變更驗證
            instance.elements.modal.on('change', '#cron-form-date', function () {
                self.validateScheduleDateTime(instance);
            });

            // 時間變更驗證
            instance.elements.modal.on('change', '#cron-form-time', function () {
                self.validateScheduleDateTime(instance);
            });

            // 字數統計
            instance.elements.modal.on('input', '#cron-form-content', function () {
                self.updateCharCount(instance);
            });

            // 排程列表操作按鈕
            instance.elements.modal.on('click', '.cron-action-btn', function () {
                const action = $(this).data('action');
                const cronId = $(this).data('cron-id');
                self.handleCronAction(instance, action, cronId);
            });

            // 表單取消按鈕
            instance.elements.modal.on('click', '.cron-form-cancel-btn', function () {
                self.resetForm(instance);
                self.showListSection(instance);
            });
        },

        /**
         * 獲取當前選擇的 LINE User ID
         * @return {string|null} LINE User ID
         */
        getCurrentLineUserId: function() {
            // 先同步 chatAreaInstance 狀態
            if (window.chatAreaInstance && window.chatAreaInstance.syncInstanceProperties) {
                window.chatAreaInstance.syncInstanceProperties();
            }
            return window.chatAreaInstance ? window.chatAreaInstance.currentLineUserId : null;
        },

        /**
         * 開啟 Modal
         * @param {object} instance - 實例
         */
        openModal: function (instance) {
            // 檢查是否選擇了好友
            const currentLineUserId = this.getCurrentLineUserId();
            if (!currentLineUserId) {
                alert('請先選擇一位好友');
                return;
            }

            instance.elements.modal.show();
            this.showListSection(instance);
            this.loadCronList(instance);
        },

        /**
         * 關閉 Modal
         * @param {object} instance - 實例
         */
        closeModal: function (instance) {
            instance.elements.modal.hide();
            this.resetForm(instance);
        },

        /**
         * 顯示列表區域
         * @param {object} instance - 實例
         */
        showListSection: function (instance) {
            instance.elements.modal.find('#cron-list-section').show();
            instance.elements.modal.find('#cron-form-section').hide();
        },

        /**
         * 顯示表單區域
         * @param {object} instance - 實例
         */
        showFormSection: function (instance) {
            const uploadContainer = instance.elements.modal.find('.file-upload-container');
            const previewContainer = instance.elements.modal.find('.file-preview-container');

            instance.elements.modal.find('#cron-list-section').hide();
            instance.elements.modal.find('#cron-form-section').show();

            // 確保重置上傳介面狀態
            uploadContainer.show();
            previewContainer.hide();

            this.resetForm(instance);
        },

        /**
         * 切換重複排程欄位
         * @param {object} instance - 實例
         */
        toggleRecurringFields: function (instance) {
            const type = instance.elements.modal.find('#cron-form-type').val();
            const recurringField = instance.elements.modal.find('#cron-recurring-field');

            if (type === 'recurring') {
                recurringField.show();
                // 觸發間隔變更處理
                this.handleIntervalChange(instance);
            } else {
                recurringField.hide();
                // 重置所有特殊欄位顯示
                this.resetDateFields(instance);
            }
        },

        /**
         * 處理重複間隔變更
         * @param {object} instance - 實例
         */
        handleIntervalChange: function (instance) {
            const interval = instance.elements.modal.find('#cron-form-interval').val();
            const dateField = instance.elements.modal.find('#cron-date-field');
            const weeklyField = instance.elements.modal.find('#cron-weekly-field');
            const monthlyField = instance.elements.modal.find('#cron-monthly-field');

            // 取得表單輸入元素
            const dateInput = instance.elements.modal.find('#cron-form-date');
            const weekdayInput = instance.elements.modal.find('#cron-form-weekday');
            const dayInput = instance.elements.modal.find('#cron-form-day');

            // 重置所有欄位
            dateField.show();
            weeklyField.hide();
            monthlyField.hide();

            // 重置 required 屬性
            dateInput.prop('required', false);
            weekdayInput.prop('required', false);
            dayInput.prop('required', false);

            switch (interval) {
                case 'daily':
                    // 每日：隱藏日期選擇
                    dateField.hide();
                    break;
                case 'weekly':
                    // 每週：隱藏日期選擇，顯示星期選擇
                    dateField.hide();
                    weeklyField.show();
                    weekdayInput.prop('required', true);
                    break;
                case 'monthly':
                    // 每月：隱藏日期選擇，顯示月日選擇
                    dateField.hide();
                    monthlyField.show();
                    dayInput.prop('required', true);
                    break;
                default:
                    // 預設顯示日期選擇
                    dateField.show();
                    dateInput.prop('required', true);
            }
        },

        /**
         * 重置日期欄位顯示
         * @param {object} instance - 實例
         */
        resetDateFields: function (instance) {
            const dateField = instance.elements.modal.find('#cron-date-field');
            const weeklyField = instance.elements.modal.find('#cron-weekly-field');
            const monthlyField = instance.elements.modal.find('#cron-monthly-field');

            // 取得表單輸入元素
            const dateInput = instance.elements.modal.find('#cron-form-date');
            const weekdayInput = instance.elements.modal.find('#cron-form-weekday');
            const dayInput = instance.elements.modal.find('#cron-form-day');

            // 重置顯示狀態
            dateField.show();
            weeklyField.hide();
            monthlyField.hide();

            // 重置 required 屬性
            dateInput.prop('required', true);
            weekdayInput.prop('required', false);
            dayInput.prop('required', false);
        },


        /**
         * 載入排程列表
         * @param {object} instance - 實例
         */
        loadCronList: function (instance) {
            const self = this;
            const $loading = instance.elements.modal.find('#cron-list-loading');
            const $empty = instance.elements.modal.find('#cron-list-empty');
            const $table = instance.elements.modal.find('#cron-list-table');

            $loading.show();
            $empty.hide();
            $table.hide();

            // 準備 AJAX 資料
            const ajaxData = {
                action: 'otz_get_cron_history',
                nonce: window.otzChatConfig?.message_cron_nonce || '',
                line_user_id: this.getCurrentLineUserId(),
                page: 1,
                per_page: 50
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: function (response) {
                    $loading.hide();

                    if (response.success && response.data.messages) {
                        instance.currentCronList = response.data.messages;

                        if (response.data.messages.length > 0) {
                            self.renderCronList(instance, response.data.messages);
                            $table.show();
                        } else {
                            $empty.show();
                        }
                    } else {
                        console.error('載入排程列表失敗:', response);
                        $empty.show();
                    }
                },
                error: function (xhr, status, error) {
                    $loading.hide();
                    $empty.show();
                    console.error('AJAX 請求失敗:', error);
                }
            });
        },

        /**
         * 渲染排程列表
         * @param {object} instance - 實例
         * @param {array} cronList - 排程列表
         */
        renderCronList: function (instance, cronList) {
            const self = this;
            const $tbody = instance.elements.modal.find('#cron-list-tbody');
            $tbody.empty();

            cronList.forEach(function (cron) {
                const statusClass = self.getStatusClass(cron.local_status);
                const statusText = self.getStatusText(cron.local_status);

                const row = `
                    <tr data-cron-id="${cron.id}">
                        <td class="cron-content">
                            <div class="cron-content-preview">
                                ${self.formatMessageContent(cron)}
                            </div>
                        </td>
                        <td class="cron-schedule">
                             ${self.formatScheduleDisplay(cron.schedule)}
                        </td>
                        <td class="cron-actual-sent">
                            ${cron.actual_sent_time || '-'}
                        </td>
                        <td class="cron-status">
                            <span class="status-badge status-${statusClass}">${statusText}</span>
                        </td>
                        <td class="cron-actions">
                            <div class="action-buttons">
                                ${(cron.local_status === 'pending' || cron.schedule.type === 'recurring') ? `
                                    <button type="button" class="button button-small cron-action-btn"
                                            data-action="edit" data-cron-id="${cron.id}" title="編輯">
                                        編輯
                                    </button>
                                ` : ''}
                                <button type="button" class="button button-small button-link-delete cron-action-btn"
                                        data-action="delete" data-cron-id="${cron.id}" title="刪除">
                                    刪除
                                </button>
                            </div>
                        </td>
                    </tr>
                `;

                $tbody.append(row);
            });
        },

        /**
         * 處理排程操作
         * @param {object} instance - 實例
         * @param {string} action - 操作類型
         * @param {number} cronId - 排程 ID
         */
        handleCronAction: function (instance, action, cronId) {
            const self = this;

            switch (action) {

                case 'edit':
                    self.editCron(instance, cronId);
                    break;

                case 'delete':
                    if (confirm('確定要刪除這個排程嗎？此操作無法復原。')) {
                        self.deleteCron(instance, cronId);
                    }
                    break;
            }
        },

        /**
         * 編輯排程
         * @param {object} instance - 實例
         * @param {number} cronId - 排程 ID
         */
        editCron: function (instance, cronId) {
            const cron = instance.currentCronList.find(c => c.id == cronId);
            if (!cron) return;

            // 設定編輯狀態
            instance.editingCronId = cronId;

            // 先顯示表單區域和進行重置（這會清空所有內容）
            this.showFormSection(instance);

            instance.elements.modal.find('#cron-form-title').text('編輯排程');

            // 表單重置完成後，再設定所有編輯資料

            // 填入基本資料
            instance.elements.modal.find('#cron-form-id').val(cron.id);

            // 設定訊息類型
            const messageType = cron.message_type || 'text';
            instance.elements.modal.find(`input[name="cron_message_type"][value="${messageType}"]`).prop('checked', true);

            // 先處理介面狀態切換
            this.handleMessageTypeChange(instance);

            // 處理訊息內容
            if (messageType === 'text') {
                // 文字訊息：直接填入內容
                instance.elements.modal.find('#cron-form-content').val(cron.message_content || '');
            } else {
                // 檔案類型訊息：解析 JSON 內容
                try {
                    const fileData = JSON.parse(cron.message_content || '{}');
                    if (fileData.file_url && fileData.file_name) {
                        instance.elements.modal.find('#cron-form-file-url').val(fileData.file_url);
                        instance.elements.modal.find('#cron-form-file-name').val(fileData.file_name);

                        // 顯示檔案預覽
                        this.showEditFilePreview(instance, fileData, messageType);
                    }
                } catch (e) {
                    console.error('解析檔案資料失敗:', e);
                }
            }

            // 解析排程資料
            if (cron.schedule && typeof cron.schedule === 'object') {
                instance.elements.modal.find('#cron-form-type').val(cron.schedule.type || 'once');
                instance.elements.modal.find('#cron-form-date').val(cron.schedule.sent_date || '');
                instance.elements.modal.find('#cron-form-time').val(cron.schedule.sent_time || '');

                // 處理重複排程設定
                if (cron.schedule.type === 'recurring' && cron.schedule.interval) {
                    instance.elements.modal.find('#cron-form-interval').val(cron.schedule.interval);

                    // 根據間隔類型設定對應欄位
                    if (cron.schedule.interval === 'weekly' && cron.schedule.weekday !== undefined) {
                        instance.elements.modal.find('#cron-form-weekday').val(cron.schedule.weekday);
                    }
                    if (cron.schedule.interval === 'monthly' && cron.schedule.day_of_month !== undefined) {
                        instance.elements.modal.find('#cron-form-day').val(cron.schedule.day_of_month);
                    }
                }
            }

            // 最後更新表單狀態和介面顯示
            this.updateCharCount(instance);
            this.toggleRecurringFields(instance);
        },

        /**
         * 刪除排程
         * @param {object} instance - 實例
         * @param {number} cronId - 排程 ID
         */
        deleteCron: function (instance, cronId) {
            const self = this;

            const ajaxData = {
                action: 'otz_delete_message_cron',
                nonce: window.otzChatConfig?.message_cron_nonce || '',
                cron_id: cronId
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        self.loadCronList(instance);
                    } else {
                        alert('刪除失敗：' + (response.data || '未知錯誤'));
                    }
                },
                error: function (xhr, status, error) {
                    alert('刪除失敗，請稍後再試');
                    console.error('刪除排程失敗:', error);
                }
            });
        },

        /**
         * 提交排程表單
         * @param {object} instance - 實例
         */
        submitCronForm: function (instance) {
            const self = this;
            const $form = instance.elements.modal.find('#cron-form');
            const cronId = $form.find('#cron-form-id').val();
            const isEdit = cronId && cronId !== '';

            // 取得選擇的訊息類型
            const messageType = $form.find('input[name="cron_message_type"]:checked').val();

            // 收集表單資料
            const formData = {
                line_user_id: this.getCurrentLineUserId(),
                message_type: messageType,
                source_type: 'user',
                schedule: JSON.stringify({
                    type: $form.find('#cron-form-type').val(),
                    sent_date: $form.find('#cron-form-date').val(),
                    sent_time: $form.find('#cron-form-time').val(),
                    interval: $form.find('#cron-form-interval').val(),
                    weekday: $form.find('#cron-form-weekday').val() ? parseInt($form.find('#cron-form-weekday').val()) : null,
                    day_of_month: $form.find('#cron-form-day').val() ? parseInt($form.find('#cron-form-day').val()) : null
                })
            };

            // 根據訊息類型設定內容
            if (messageType === 'text') {
                const textContent = $form.find('#cron-form-content').val().trim();
                if (!textContent) {
                    alert('請輸入訊息內容');
                    return;
                }
                formData.message_content = textContent;
            } else {
                // 檔案類型訊息
                const fileUrl = $form.find('#cron-form-file-url').val();
                const fileName = $form.find('#cron-form-file-name').val();

                if (!fileUrl || !fileName) {
                    alert('請選擇要上傳的檔案');
                    return;
                }

                // 將檔案資訊以 JSON 格式儲存
                formData.message_content = JSON.stringify({
                    type: messageType,
                    file_url: fileUrl,
                    file_name: fileName
                });
            }

            // 驗證排程時間
            if (!formData.schedule) {
                alert('請設定排程時間');
                return;
            }

            // 驗證日期時間是否有效
            if (!this.validateScheduleDateTime(instance)) {
                return; // 驗證失敗，不繼續提交
            }

            // 準備 AJAX 資料
            const ajaxData = {
                action: isEdit ? 'otz_update_message_cron' : 'otz_create_message_cron',
                nonce: window.otzChatConfig?.message_cron_nonce || '',
                ...formData
            };

            if (isEdit) {
                ajaxData.cron_id = cronId;
            }

            // 禁用提交按鈕
            const $submitBtn = $form.find('#cron-form-save-btn');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true);

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        alert(isEdit ? '排程已更新' : '排程已建立');
                        self.resetForm(instance);
                        self.loadCronList(instance);
                        self.showListSection(instance);
                        $submitBtn.prop('disabled', false).text(originalText);
                    } else {
                        alert('操作失敗：' + (response.data || '未知錯誤'));
                    }
                },
                error: function (xhr, status, error) {
                    alert('操作失敗，請稍後再試');
                    console.error('提交排程失敗:', error);
                    $submitBtn.prop('disabled', false).text(originalText);
                },
            });
        },

        /**
         * 重置表單
         * @param {object} instance - 實例
         */
        resetForm: function (instance) {
            const $form = instance.elements.modal.find('#cron-form');
            const uploadContainer = instance.elements.modal.find('.file-upload-container');
            const previewContainer = instance.elements.modal.find('.file-preview-container');

            $form[0].reset();
            $form.find('#cron-form-id').val('');

            // 重置編輯狀態
            instance.editingCronId = null;

            // 重置訊息類型為文字
            $form.find('input[name="cron_message_type"][value="text"]').prop('checked', true);

            // 重置檔案相關欄位
            $form.find('#cron-form-file-url').val('');
            $form.find('#cron-form-file-name').val('');

            // 重置上傳介面狀態
            uploadContainer.show();
            previewContainer.hide();

            instance.elements.modal.find('#cron-form-title').text('新增排程');
            this.updateCharCount(instance);
            this.toggleRecurringFields(instance);
            this.handleMessageTypeChange(instance);
            this.clearFormErrors(instance);
        },

        /**
         * 更新字數統計
         * @param {object} instance - 實例
         */
        updateCharCount: function (instance) {
            const content = instance.elements.modal.find('#cron-form-content').val();
            const count = content.length;
            instance.elements.modal.find('#cron-form-char-count').text(count);
        },

        /**
         * 截取文字
         * @param {string} text - 原始文字
         * @param {number} length - 截取長度
         * @return {string} 截取後的文字
         */
        truncateText: function (text, length) {
            if (text.length <= length) {
                return text;
            }
            return text.substring(0, length) + '...';
        },

        /**
         * 格式化排程顯示
         * @param {object} schedule - 排程資料
         * @return {string} 格式化後的顯示文字
         */
        formatScheduleDisplay: function (schedule) {
            if (!schedule) {
                return '無排程時間';
            }

            const type = schedule.type;
            const time = schedule.sent_time || '';
            const interval = schedule.interval;

            // 格式化時間顯示（加上秒數）
            const formattedTime = time + (time.length === 5 ? ':00' : '');

            switch (type) {
                case 'once':
                    // 單次排程：顯示完整日期時間
                    return `${schedule.sent_date} ${formattedTime}`;

                case 'recurring':
                    switch (interval) {
                        case 'daily':
                            return `每日 ${formattedTime}`;

                        case 'weekly':
                            const weekdays = ['週日', '週一', '週二', '週三', '週四', '週五', '週六'];
                            const weekdayName = weekdays[schedule.weekday] || '週一';
                            return `每${weekdayName} ${formattedTime}`;

                        case 'monthly':
                            const dayOfMonth = schedule.day_of_month || 1;
                            return `每月${dayOfMonth}日 ${formattedTime}`;

                        default:
                            return `重複排程 ${formattedTime}`;
                    }

                default:
                    return `${schedule.sent_date} ${formattedTime}`;
            }
        },

        /**
         * 格式化訊息內容顯示
         * @param {object} cron - 排程資料
         * @return {string} 格式化後的 HTML
         */
        formatMessageContent: function (cron) {
            const messageType = cron.message_type;
            const content = cron.message_content;

            if (messageType === 'text') {
                // 文字訊息：截斷顯示
                return this.truncateText(content, 50);
            } else {
                // 檔案類型訊息：解析 JSON 內容
                try {
                    const fileData = JSON.parse(content);

                    if (messageType === 'image') {
                        // 圖片：顯示縮圖
                        return `<img src="${fileData.file_url}" alt="圖片預覽" class="cron-image-preview" loading="lazy">`;
                    } else {
                        // 檔案/影片：顯示檔名並提供下載連結
                        const truncatedFileName = this.truncateText(fileData.file_name, 30);
                        return `<a href="${fileData.file_url}" download="${fileData.file_name}" class="cron-file-link" title="${fileData.file_name}">${truncatedFileName}</a>`;
                    }
                } catch (e) {
                    return '檔案內容錯誤';
                }
            }
        },

        /**
         * 取得狀態樣式類別
         * @param {string} status - 狀態
         * @return {string} 樣式類別
         */
        getStatusClass: function (status) {
            const statusMap = {
                'pending': 'pending',
                'completed': 'success',
                'failed': 'error',
                'manual': 'warning',
                'cancelled': 'secondary'
            };

            return statusMap[status] || 'secondary';
        },

        /**
         * 取得狀態文字
         * @param {string} status - 狀態
         * @return {string} 狀態文字
         */
        getStatusText: function (status) {
            const statusMap = {
                'pending': '等待中',
                'completed': '已送出',
                'failed': '失敗',
                'manual': '手動觸發',
                'cancelled': '已取消'
            };

            return statusMap[status] || '未知';
        },

        /**
         * 處理訊息類型變更
         * @param {object} instance - 實例
         */
        handleMessageTypeChange: function (instance) {
            const selectedType = instance.elements.modal.find('input[name="cron_message_type"]:checked').val();
            const textEditor = instance.elements.modal.find('.text-editor');
            const fileEditor = instance.elements.modal.find('.file-editor');
            const uploadContainer = instance.elements.modal.find('.file-upload-container');
            const previewContainer = instance.elements.modal.find('.file-preview-container');
            const fileSizeLimit = instance.elements.modal.find('.file-size-limit');

            // 檢查是否處於編輯模式且有檔案資料
            const isEditMode = instance.editingCronId !== null && instance.editingCronId !== undefined;
            const hasFileData = instance.elements.modal.find('#cron-form-file-url').val() !== '';

            // 隱藏所有編輯器
            textEditor.hide();
            fileEditor.hide();

            // 根據選擇的類型顯示對應編輯器
            if (selectedType === 'text') {
                textEditor.show();
                textEditor.find('#cron-form-content').prop('required', true);
                instance.elements.modal.find('#cron-form-file').prop('required', false);
            } else {
                fileEditor.show();
                textEditor.find('#cron-form-content').prop('required', false);
                // 移除檔案輸入的 required 屬性，改為手動驗證
                instance.elements.modal.find('#cron-form-file').prop('required', false);

                // 設定檔案類型限制
                this.setFileTypeRestrictions(instance, selectedType);

                // 更新檔案大小限制提示
                this.updateFileSizeLimit(fileSizeLimit, selectedType);

                // 在編輯模式下且有檔案資料時，保持預覽狀態；否則重置為上傳狀態
                if (isEditMode && hasFileData) {
                    // 編輯模式：保持預覽區塊顯示，隱藏上傳區塊
                    uploadContainer.hide();
                    previewContainer.show();
                } else {
                    // 新增模式：顯示上傳區塊，隱藏預覽區塊
                    uploadContainer.show();
                    previewContainer.hide();
                    // 清除之前的檔案和錯誤訊息
                    this.clearFilePreview(instance);
                }
            }

            // 清除錯誤訊息
            this.clearFormErrors(instance);
        },

        /**
         * 設定檔案類型限制
         * @param {object} instance - 實例
         * @param {string} messageType - 訊息類型
         */
        setFileTypeRestrictions: function (instance, messageType) {
            const fileInput = instance.elements.modal.find('#cron-form-file');

            switch (messageType) {
                case 'image':
                    fileInput.attr('accept', 'image/*');
                    break;
                case 'video':
                    fileInput.attr('accept', 'video/*');
                    break;
                case 'file':
                    fileInput.attr('accept', '.zip,.rar');
                    break;
                default:
                    fileInput.removeAttr('accept');
            }
        },

        /**
         * 更新檔案大小限制提示
         * @param {jQuery} element - 提示元素
         * @param {string} messageType - 訊息類型
         */
        updateFileSizeLimit: function (element, messageType) {
            let limitText = '';

            switch (messageType) {
                case 'image':
                    limitText = '支援 JPG, PNG, GIF, WebP 格式，最大 5MB';
                    break;
                case 'video':
                    limitText = '支援 MP4, AVI, MOV 等格式，最大 50MB';
                    break;
                case 'file':
                    limitText = '支援 ZIP, RAR 壓縮檔，最大 20MB';
                    break;
            }

            element.text(limitText);
        },

        /**
         * 處理檔案選擇按鈕點擊
         * @param {object} instance - 實例
         */
        handleFileSelect: function (instance) {
            instance.elements.modal.find('#cron-form-file').click();
        },

        /**
         * 處理檔案輸入變更
         * @param {object} instance - 實例
         * @param {File} file - 選擇的檔案
         */
        handleFileInputChange: function (instance, file) {
            if (!file) {
                return;
            }

            const messageType = instance.elements.modal.find('input[name="cron_message_type"]:checked').val();

            // 驗證檔案
            if (!this.validateFile(file, messageType)) {
                instance.elements.modal.find('#cron-form-file').val('');
                return;
            }

            // 顯示檔案預覽
            this.showFilePreview(instance, file);

            // 開始上傳檔案
            this.uploadFile(instance, file, messageType);
        },

        /**
         * 驗證檔案
         * @param {File} file - 檔案對象
         * @param {string} messageType - 訊息類型
         * @return {boolean} 驗證結果
         */
        validateFile: function (file, messageType) {
            // 重用 ChatAreaInput 的驗證邏輯
            if (typeof window.ChatAreaInput !== 'undefined') {
                return window.ChatAreaInput.validateFileSecurely(file);
            }

            // 簡化版驗證
            const maxSizes = {
                'image': 5 * 1024 * 1024,  // 5MB
                'video': 50 * 1024 * 1024, // 50MB
                'file': 20 * 1024 * 1024   // 20MB
            };

            if (file.size > maxSizes[messageType]) {
                const sizeMB = Math.round(maxSizes[messageType] / 1024 / 1024);
                alert(`檔案大小超過限制 (${sizeMB}MB)`);
                return false;
            }

            return true;
        },

        /**
         * 顯示檔案預覽
         * @param {object} instance - 實例
         * @param {File} file - 檔案對象
         */
        showFilePreview: function (instance, file) {
            const previewContainer = instance.elements.modal.find('.file-preview-container');
            const uploadContainer = instance.elements.modal.find('.file-upload-container');
            const fileName = instance.elements.modal.find('.file-name');
            const fileSize = instance.elements.modal.find('.file-size');
            const messageType = instance.elements.modal.find('input[name="cron_message_type"]:checked').val();

            fileName.text(file.name);
            fileSize.text(this.formatFileSize(file.size));

            // 如果是圖片類型，顯示預覽圖
            if (messageType === 'image' && file.type.startsWith('image/')) {
                this.showImagePreview(instance, file);
            }

            // 隱藏上傳區塊，顯示預覽區塊
            uploadContainer.hide();
            previewContainer.show();
        },

        /**
         * 格式化檔案大小
         * @param {number} bytes - 位元組數
         * @return {string} 格式化後的大小
         */
        formatFileSize: function (bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        },

        /**
         * 顯示編輯時的檔案預覽
         * @param {object} instance - 實例
         * @param {object} fileData - 檔案資料
         * @param {string} messageType - 訊息類型
         */
        showEditFilePreview: function (instance, fileData, messageType) {
            const previewContainer = instance.elements.modal.find('.file-preview-container');
            const uploadContainer = instance.elements.modal.find('.file-upload-container');
            const fileName = instance.elements.modal.find('.file-name');
            const fileSize = instance.elements.modal.find('.file-size');

            fileName.text(fileData.file_name || '未知檔案名稱');
            fileSize.text('已上傳檔案');

            // 如果是圖片類型，顯示預覽圖
            if (messageType === 'image' && fileData.file_url) {
                this.showEditImagePreview(instance, fileData.file_url);
            }

            // 隱藏上傳區塊，顯示預覽區塊
            uploadContainer.hide();
            previewContainer.show();
        },

        /**
         * 顯示編輯時的圖片預覽
         * @param {object} instance - 實例
         * @param {string} imageUrl - 圖片 URL
         */
        showEditImagePreview: function (instance, imageUrl) {
            const previewContainer = instance.elements.modal.find('.file-preview-container');

            // 移除現有的圖片預覽
            previewContainer.find('.image-preview').remove();

            // 建立圖片預覽元素
            const imagePreview = $(`
                <div class="image-preview">
                    <img src="${imageUrl}" alt="圖片預覽" class="preview-image">
                </div>
            `);

            // 插入到檔案預覽容器內部，檔案資訊之後
            previewContainer.find('.file-preview').after(imagePreview);
        },

        /**
         * 顯示圖片預覽
         * @param {object} instance - 實例
         * @param {File} file - 圖片檔案
         */
        showImagePreview: function (instance, file) {
            const fileReader = new FileReader();
            const previewContainer = instance.elements.modal.find('.file-preview-container');

            fileReader.onload = function (e) {
                // 移除現有的圖片預覽
                previewContainer.find('.image-preview').remove();

                // 建立圖片預覽元素
                const imagePreview = $(`
                    <div class="image-preview">
                        <img src="${e.target.result}" alt="圖片預覽" class="preview-image">
                    </div>
                `);

                // 插入到檔案資訊之後
                previewContainer.find('.file-preview').before(imagePreview);
            };

            fileReader.onerror = function () {
                console.error('圖片預覽載入失敗');
            };

            // 讀取圖片檔案為 data URL
            fileReader.readAsDataURL(file);
        },

        /**
         * 上傳檔案
         * @param {object} instance - 實例
         * @param {File} file - 檔案對象
         * @param {string} messageType - 訊息類型
         */
        uploadFile: function (instance, file, messageType) {
            const uploadProgress = instance.elements.modal.find('.upload-progress');
            const progressFill = instance.elements.modal.find('.upload-progress-fill');
            const progressText = instance.elements.modal.find('.upload-progress-text');

            // 顯示上傳進度
            uploadProgress.show();
            progressText.text('上傳中...');

            // 準備上傳資料
            const formData = new FormData();
            let action = '';
            let fileFieldName = '';

            switch (messageType) {
                case 'image':
                    action = 'otz_upload_image';
                    fileFieldName = 'image';
                    break;
                case 'video':
                    action = 'otz_upload_video_file';
                    fileFieldName = 'video_file';
                    break;
                case 'file':
                    action = 'otz_upload_compressed_file';
                    fileFieldName = 'compressed_file';
                    break;
            }

            formData.append('action', action);
            formData.append(fileFieldName, file);
            formData.append('line_user_id', this.getCurrentLineUserId());
            formData.append('nonce', window.otzChatConfig?.nonce || '');

            // 發送上傳請求
            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    // 監聽上傳進度
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            progressFill.css('width', percentComplete + '%');
                            progressText.text(`上傳中... ${Math.round(percentComplete)}%`);
                        }
                    }, false);
                    return xhr;
                },
                success: (response) => {
                    uploadProgress.hide();

                    if (response.success) {
                        // 儲存檔案資訊
                        const fileUrl = response.data.image_url || response.data.file_url;
                        const fileName = response.data.filename || file.name;

                        instance.elements.modal.find('#cron-form-file-url').val(fileUrl);
                        instance.elements.modal.find('#cron-form-file-name').val(fileName);

                        progressText.text('上傳完成');
                        this.clearFormErrors(instance);
                    } else {
                        this.showFormError(instance, 'file', response.data.message || '檔案上傳失敗');
                    }
                },
                error: (xhr, status, error) => {
                    uploadProgress.hide();
                    this.showFormError(instance, 'file', '網路連線錯誤，請稍後再試');
                }
            });
        },

        /**
         * 處理檔案移除
         * @param {object} instance - 實例
         */
        handleFileRemove: function (instance) {
            const uploadContainer = instance.elements.modal.find('.file-upload-container');

            this.clearFilePreview(instance);
            instance.elements.modal.find('#cron-form-file').val('');
            instance.elements.modal.find('#cron-form-file-url').val('');
            instance.elements.modal.find('#cron-form-file-name').val('');
            this.clearFormErrors(instance);

            // 重新顯示上傳介面
            uploadContainer.show();
        },

        /**
         * 清除檔案預覽
         * @param {object} instance - 實例
         */
        clearFilePreview: function (instance) {
            const previewContainer = instance.elements.modal.find('.file-preview-container');
            const uploadProgress = instance.elements.modal.find('.upload-progress');

            // 清除圖片預覽
            previewContainer.find('.image-preview').remove();

            previewContainer.hide();
            uploadProgress.hide();
        },

        /**
         * 顯示表單錯誤
         * @param {object} instance - 實例
         * @param {string} field - 欄位名稱
         * @param {string} message - 錯誤訊息
         */
        showFormError: function (instance, field, message) {
            const errorElement = instance.elements.modal.find(`#cron-form-${field}-error`);
            errorElement.text(message).show();
        },

        /**
         * 清除表單錯誤
         * @param {object} instance - 實例
         */
        clearFormErrors: function (instance) {
            instance.elements.modal.find('.cron-form-error').hide().text('');
        },

        /**
         * 驗證排程日期時間
         * @param {object} instance - 實例
         * @return {boolean} 驗證結果
         */
        validateScheduleDateTime: function (instance) {
            const dateInput = instance.elements.modal.find('#cron-form-date');
            const timeInput = instance.elements.modal.find('#cron-form-time');
            const typeInput = instance.elements.modal.find('#cron-form-type');
            const selectedDate = dateInput.val();
            const selectedTime = timeInput.val();
            const scheduleType = typeInput.val();

            // 清除之前的錯誤
            this.clearDateTimeErrors(instance);

            if (!selectedDate || !selectedTime) {
                return true; // 如果沒有選擇日期時間，不進行驗證
            }

            // 建立選擇的日期時間物件
            const selectedDateTime = new Date(selectedDate + 'T' + selectedTime);
            const now = new Date();

            // 檢查是否為有效日期
            if (isNaN(selectedDateTime.getTime())) {
                this.showDateTimeError(instance, 'date', '請選擇有效的日期');
                return false;
            }

            // 對於重複排程，不檢查過去時間限制（可以安排明天執行）
            if (scheduleType !== 'recurring') {
                // 檢查是否選擇了過去的時間（僅適用於單次排程）
                if (selectedDateTime <= now) {
                    this.showDateTimeError(instance, 'time', '排程時間不能選擇過去的時間');
                    return false;
                }
            }

            // 檢查是否超過一年後（可選的限制）
            const oneYearLater = new Date();
            oneYearLater.setFullYear(oneYearLater.getFullYear() + 1);

            if (selectedDateTime > oneYearLater) {
                this.showDateTimeError(instance, 'date', '排程時間不能超過一年後');
                return false;
            }

            return true;
        },

        /**
         * 顯示日期時間錯誤
         * @param {object} instance - 實例
         * @param {string} field - 欄位類型 (date/time)
         * @param {string} message - 錯誤訊息
         */
        showDateTimeError: function (instance, field, message) {
            const errorElement = instance.elements.modal.find(`#cron-form-${field}-error`);
            if (errorElement.length === 0) {
                // 如果錯誤元素不存在，在輸入欄位後面建立
                const input = instance.elements.modal.find(`#cron-form-${field}`);
                const errorDiv = $(`<div class="cron-form-error" id="cron-form-${field}-error">${message}</div>`);
                input.parent().append(errorDiv);
            } else {
                errorElement.text(message).show();
            }
        },

        /**
         * 清除日期時間錯誤
         * @param {object} instance - 實例
         */
        clearDateTimeErrors: function (instance) {
            instance.elements.modal.find('#cron-form-date-error, #cron-form-time-error').hide().text('');
        },

        /**
         * 初始化拖曳上傳功能
         * @param {object} instance - 實例
         */
        initDragDropUpload: function (instance) {
            const self = this;
            const fileUploadContainer = instance.elements.modal.find('.file-upload-container');

            if (fileUploadContainer.length === 0) {
                return;
            }

            // 拖曳進入
            fileUploadContainer.on('dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // 只在檔案編輯器顯示時才處理拖曳
                if (!instance.elements.modal.find('.file-editor').is(':visible')) {
                    return;
                }

                $(this).addClass('drag-hover');
                self.updateDragDropText($(this), '鬆開滑鼠上傳檔案');
            });

            // 拖曳移動
            fileUploadContainer.on('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
            });

            // 拖曳離開
            fileUploadContainer.on('dragleave', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // 只有當離開整個容器時才移除 hover 狀態
                const rect = this.getBoundingClientRect();
                const x = e.originalEvent.clientX;
                const y = e.originalEvent.clientY;

                if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) {
                    $(this).removeClass('drag-hover');
                    self.resetDragDropText($(this));
                }
            });

            // 拖曳放下
            fileUploadContainer.on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();

                // 只在檔案編輯器顯示時才處理檔案
                if (!instance.elements.modal.find('.file-editor').is(':visible')) {
                    return;
                }

                $(this).removeClass('drag-hover');
                self.resetDragDropText($(this));

                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    // 只處理第一個檔案
                    self.handleFileInputChange(instance, files[0]);
                }
            });
        },

        /**
         * 更新拖曳提示文字
         * @param {jQuery} container - 容器元素
         * @param {string} text - 提示文字
         */
        updateDragDropText: function (container, text) {
            const button = container.find('.cron-file-select-btn');
            if (button.length > 0 && !button.data('original-text')) {
                button.data('original-text', button.text());
                button.text(text);
            }
        },

        /**
         * 重置拖曳提示文字
         * @param {jQuery} container - 容器元素
         */
        resetDragDropText: function (container) {
            const button = container.find('.cron-file-select-btn');
            const originalText = button.data('original-text');
            if (originalText) {
                button.text(originalText);
                button.removeData('original-text');
            }
        },

        /**
         * 初始化日期時間限制
         * @param {object} instance - 實例
         */
        initDateTimeConstraints: function (instance) {
            const dateInput = instance.elements.modal.find('#cron-form-date');
            const timeInput = instance.elements.modal.find('#cron-form-time');
            const typeInput = instance.elements.modal.find('#cron-form-type');

            if (dateInput.length === 0 || timeInput.length === 0) {
                return;
            }

            const self = this;

            // 設定最小日期為今天
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayString = `${year}-${month}-${day}`;

            dateInput.attr('min', todayString);

            // 設定最大日期為一年後
            const oneYearLater = new Date();
            oneYearLater.setFullYear(oneYearLater.getFullYear() + 1);
            const maxYear = oneYearLater.getFullYear();
            const maxMonth = String(oneYearLater.getMonth() + 1).padStart(2, '0');
            const maxDay = String(oneYearLater.getDate()).padStart(2, '0');
            const maxDateString = `${maxYear}-${maxMonth}-${maxDay}`;

            dateInput.attr('max', maxDateString);

            // 更新時間限制的函數
            function updateTimeConstraints() {
                const selectedDate = dateInput.val();
                const scheduleType = typeInput.val();

                // 重複排程不限制時間，單次排程才限制
                if (scheduleType === 'recurring') {
                    timeInput.removeAttr('min');
                } else if (selectedDate === todayString) {
                    // 如果是單次排程且選擇今天，設定最小時間為當前時間（向上取整到下一分鐘）
                    const now = new Date();
                    now.setMinutes(now.getMinutes() + 1); // 加一分鐘確保不會選到過去時間
                    const currentHour = String(now.getHours()).padStart(2, '0');
                    const currentMinute = String(now.getMinutes()).padStart(2, '0');
                    const minTimeString = `${currentHour}:${currentMinute}`;
                    timeInput.attr('min', minTimeString);
                } else {
                    // 如果選擇其他日期，移除時間限制
                    timeInput.removeAttr('min');
                }
            }

            // 當日期或排程類型變更時更新時間限制
            dateInput.on('change', updateTimeConstraints);
            typeInput.on('change', updateTimeConstraints);
        }
    };

    // 全域實例
    let cronInstance = null;

    // 當聊天區域初始化完成時，自動建立排程訊息實例
    $(document).on('chat:initialized', function () {
         if (window.chatAreaInstance) {
             cronInstance = window.ChatAreaMessageCron.createInstance(window.chatAreaInstance);
         }
     });

})(jQuery);

// Version 1.1.2 - 修正重複排程執行後仍顯示編輯按鈕.