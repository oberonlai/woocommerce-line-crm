/**
 * OrderChatz 聊天範本模組
 *
 * 管理訊息範本的顯示和插入功能
 *
 * @package OrderChatz
 * @since 1.0.2
 */

(function ($) {
    'use strict';

    /**
     * 聊天範本管理器
     */
    window.ChatAreaTemplate = {
        /**
         * 範本資料
         */
        templates: [],

        /**
         * 聊天區域實例參考
         */
        chatArea: null,

        /**
         * DOM 元素快取
         */
        templatesContainer: null,
        templatesList: null,
        manageModal: null,
        manageButton: null,

        /**
         * 自動完成相關元素
         */
        autocompleteDropdown: null,
        autocompleteList: null,
        selectedIndex: -1,
        isAutocompleteOpen: false,
        currentHashPosition: -1,
        currentQuery: '',

        /**
         * 管理 Modal 相關元素
         */
        listSection: null,
        formSection: null,
        listTable: null,
        listTbody: null,
        form: null,
        formTitle: null,
        formId: null,
        formCode: null,
        formContent: null,
        formCharCount: null,

        /**
         * 編輯狀態
         */
        isEditing: false,
        editingId: null,

        /**
         * 初始化範本模組
         * @param {object} chatAreaInstance - 聊天區域實例
         */
        init: function (chatAreaInstance) {
            this.chatArea = chatAreaInstance;
            this.initDOMElements();
            this.loadTemplates();
            this.bindEvents();
        },

        /**
         * 初始化 DOM 元素
         */
        initDOMElements: function () {
            this.templatesContainer = $('#message-templates-container');
            this.templatesList = $('#message-templates-list');
            this.manageButton = $('#template-manage-btn');
            this.manageModal = $('#template-manage-modal');

            // 自動完成相關元素
            this.autocompleteDropdown = $('#template-autocomplete-dropdown');
            this.autocompleteList = $('#autocomplete-list');

            // 管理 Modal 相關元素
            this.listSection = $('#template-list-section');
            this.formSection = $('#template-form-section');
            this.listTable = $('#template-list-table');
            this.listTbody = $('#template-list-tbody');
            this.form = $('#template-form');
            this.formTitle = $('#template-form-title');
            this.formId = $('#template-form-id');
            this.formCode = $('#template-form-code');
            this.formContent = $('#template-form-content');
            this.formCharCount = $('#template-form-char-count');
        },

        /**
         * 載入範本資料
         * @param {function} callback - 載入完成後的回調函數
         */
        loadTemplates: function (callback) {
            this.apiGetTemplates(callback);
        },

        /**
         * 渲染範本列表
         */
        renderTemplates: function () {
            if (!this.templates || this.templates.length === 0) {
                this.hideTemplatesContainer();
                return;
            }

            const templateItems = this.templates.map(template =>
                `<div class="template-item" data-template-id="${template.id}" data-content="${template.content}" title="${template.content}">
                    ${template.content}
                </div>`
            ).join('');

            this.templatesList.html(templateItems);
            this.showTemplatesContainer();
        },

        /**
         * 顯示範本容器
         */
        showTemplatesContainer: function () {
            if (this.chatArea && this.chatArea.currentFriendId) {
                this.templatesContainer.show();
            }
        },

        /**
         * 隱藏範本容器
         */
        hideTemplatesContainer: function () {
            this.templatesContainer.hide();
        },

        /**
         * 綁定事件
         */
        bindEvents: function () {
            // 點擊範本項目
            $(document).on('click', '.template-item', (e) => {
                const $item = $(e.currentTarget);
                const content = $item.data('content');

                if (content) {
                    this.insertTemplate(content);
                }
            });

            // 自動完成相關事件 - 不直接綁定，而是通過現有事件系統集成

            // 點擊自動完成項目
            $(document).on('click', '.autocomplete-item', (e) => {
                e.preventDefault();
                const $item = $(e.currentTarget);
                const templateId = parseInt($item.data('template-id'));
                this.selectTemplate(templateId);
            });

            // 點擊外部關閉自動完成
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#message-input, #template-autocomplete-dropdown').length) {
                    this.closeAutocomplete();
                }
            });

            // 範本管理按鈕
            this.manageButton.on('click', () => {
                this.openManageModal();
            });

            // 關閉 Modal
            this.manageModal.on('click', '.modal-close, .modal-backdrop', () => {
                this.closeManageModal();
            });

            // 新增範本按鈕
            this.manageModal.on('click', '.template-add-new-btn', () => {
                this.showAddForm();
            });

            // 取消表單
            this.manageModal.on('click', '.template-form-cancel-btn', () => {
                this.hideForm();
            });

            // 編輯範本
            this.manageModal.on('click', '.template-edit-btn', (e) => {
                const templateId = parseInt($(e.currentTarget).data('id'));
                this.editTemplate(templateId);
            });

            // 刪除範本
            this.manageModal.on('click', '.template-delete-btn', (e) => {
                const templateId = parseInt($(e.currentTarget).data('id'));
                this.deleteTemplate(templateId);
            });

            // 表單提交
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.saveTemplate();
            });

            // 字數統計
            this.formContent.on('input', () => {
                this.updateCharCount();
            });

            // 阻止 Modal 內容點擊時關閉
            this.manageModal.on('click', '.modal-container', (e) => {
                e.stopPropagation();
            });
        },

        /**
         * 插入範本內容到訊息輸入框
         * @param {string} templateContent - 範本內容
         */
        insertTemplate: function (templateContent) {
            if (!this.chatArea || !this.chatArea.messageInput) {
                return;
            }

            const messageInput = this.chatArea.messageInput[0];
            const currentValue = messageInput.value;
            const cursorPosition = messageInput.selectionStart;

            // 在游標位置插入範本內容
            const newValue = currentValue.substring(0, cursorPosition) +
                templateContent +
                currentValue.substring(cursorPosition);

            messageInput.value = newValue;

            // 設定游標位置到插入內容之後
            const newCursorPosition = cursorPosition + templateContent.length;
            messageInput.setSelectionRange(newCursorPosition, newCursorPosition);
            messageInput.focus();

            // 觸發輸入事件以更新發送按鈕狀態
            if (window.ChatAreaInput && this.chatArea) {
                window.ChatAreaInput.handleMessageInput(this.chatArea, {target: messageInput});
            }
        },

        /**
         * 當好友選擇改變時的處理
         * @param {string} friendId - 好友 ID
         */
        onFriendChanged: function (friendId) {
            if (friendId) {
                this.showTemplatesContainer();
            } else {
                this.hideTemplatesContainer();
                this.closeAutocomplete();
            }
        },

        /**
         * 處理訊息輸入 (供外部調用)
         * @param {Event} e - 輸入事件
         */
        onMessageInput: function (e) {
            if (this.chatArea && this.chatArea.currentFriendId) {
                this.handleMessageInput(e);
            }
        },

        /**
         * 處理鍵盤按鍵 (供外部調用)
         * @param {Event} e - 鍵盤事件
         * @return {boolean} 是否已處理該事件
         */
        onKeyDown: function (e) {
            if (this.isAutocompleteOpen) {
                const handled = this.handleKeyDown(e);
                // 如果 handleKeyDown 返回 true 或自動完成仍開啟，表示已處理
                return handled === true || this.isAutocompleteOpen;
            }
            return false;
        },

        /**
         * 重新載入範本
         */
        reload: function () {
            this.loadTemplates();
        },

        // ===== API 整合方法 =====

        /**
         * 取得所有範本
         * @param {function} callback - 載入完成後的回調函數
         */
        apiGetTemplates: function (callback) {
            this.showLoading();

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_templates',
                    nonce: otzChatConfig.nonce
                },
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.templates = response.data.templates || [];
                        this.renderTemplates();
                        if (callback) callback();
                    } else {
                        this.showError(response.data.message || '取得範本失敗');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    this.showError('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 儲存範本 (新增或更新)
         * @param {object} templateData - 範本資料
         * @param {function} callback - 成功回調函數
         */
        apiSaveTemplate: function (templateData, callback) {
            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_save_template',
                    nonce: otzChatConfig.nonce,
                    template_id: templateData.id || 0,
                    content: templateData.content,
                    code: templateData.code
                },
                success: (response) => {
                    if (response.success) {
                        if (callback) callback(response.data);
                    } else {
                        alert(response.data.message || '儲存範本失敗');
                    }
                },
                error: (xhr, status, error) => {
                    alert('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 刪除範本
         * @param {number} templateId - 範本 ID
         * @param {function} callback - 成功回調函數
         */
        apiDeleteTemplate: function (templateId, callback) {
            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_delete_template',
                    nonce: otzChatConfig.nonce,
                    template_id: templateId
                },
                success: (response) => {
                    if (response.success) {
                        if (callback) callback(response.data);
                    } else {
                        alert(response.data.message || '刪除範本失敗');
                    }
                },
                error: (xhr, status, error) => {
                    alert('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 搜尋範本
         * @param {string} searchTerm - 搜尋關鍵字
         * @param {function} callback - 成功回調函數
         */
        apiSearchTemplates: function (searchTerm, callback) {
            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_search_templates',
                    nonce: otzChatConfig.nonce,
                    search: searchTerm,
                    limit: 20
                },
                success: (response) => {
                    if (response.success) {
                        if (callback) callback(response.data.templates || []);
                    } else {
                        if (callback) callback([]);
                    }
                },
                error: (xhr, status, error) => {
                    if (callback) callback([]);
                }
            });
        },

        /**
         * 顯示載入狀態
         */
        showLoading: function () {
            this.templatesList.html('<div class="template-loading"><div class="dashicons dashicons-update-alt"></div>載入中...</div>');
        },

        /**
         * 隱藏載入狀態
         */
        hideLoading: function () {
            $('.template-loading').remove();
        },

        /**
         * 顯示管理列表載入狀態
         */
        showManageLoading: function () {
            this.listTbody.html('<tr><td colspan="3" class="template-loading"><div class="dashicons dashicons-update-alt"></div>更新中...</td></tr>');
        },

        /**
         * 隱藏管理列表載入狀態
         */
        hideManageLoading: function () {
            this.listTbody.find('.template-loading').closest('tr').remove();
        },

        /**
         * 顯示錯誤訊息
         * @param {string} message - 錯誤訊息
         */
        showError: function (message) {
            const errorHtml = `
                <div class="template-error">
                    <div class="dashicons dashicons-warning"></div>
                    <p>${message}</p>
                    <button type="button" class="template-retry-btn">重試</button>
                </div>
            `;
            this.templatesList.html(errorHtml);

            $(document).off('click.template-retry').on('click.template-retry', '.template-retry-btn', () => {
                this.loadTemplates();
            });
        },

        /**
         * 顯示成功訊息
         * @param {string} message - 成功訊息
         */
        showSuccessMessage: function (message) {
            // 建立成功訊息元素
            const successMsg = $(`
                <div class="template-success-message">
                    <div class="dashicons dashicons-yes-alt"></div>
                    <span>${message}</span>
                </div>
            `);

            // 在 Modal 中顯示
            this.manageModal.find('.modal-content').prepend(successMsg);

            // 3秒後自動隱藏
            setTimeout(() => {
                successMsg.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        },

        // ===== Modal 管理方法 =====

        /**
         * 開啟管理 Modal
         */
        openManageModal: function () {
            this.renderManageList();
            this.hideForm();
            this.manageModal.fadeIn(300);
        },

        /**
         * 關閉管理 Modal
         */
        closeManageModal: function () {
            this.manageModal.fadeOut(300);
            this.hideForm();
            this.resetForm();
        },

        /**
         * 渲染管理列表
         */
        renderManageList: function () {
            if (!this.templates || this.templates.length === 0) {
                this.showEmptyState();
                return;
            }

            // 按快速代碼英文首字排序
            const sortedTemplates = this.templates.slice().sort((a, b) => {
                return a.code.toLowerCase().localeCompare(b.code.toLowerCase());
            });

            const rows = sortedTemplates.map(template => `
                <tr>
                    <td>
                        <span class="template-code-cell">${template.code}</span>
                    </td>
                    <td class="template-content-cell">${template.content}</td>
                    <td class="template-actions-cell">
                        <button type="button" class="template-action-btn edit-btn template-edit-btn" data-id="${template.id}" title="編輯">
                            <span class="dashicons dashicons-edit"></span>
                            編輯
                        </button>
                        <button type="button" class="template-action-btn delete-btn template-delete-btn" data-id="${template.id}" title="刪除">
                            <span class="dashicons dashicons-trash"></span>
                            刪除
                        </button>
                    </td>
                </tr>
            `).join('');

            this.listTbody.html(rows);
            this.listSection.show();
        },

        /**
         * 顯示空狀態
         */
        showEmptyState: function () {
            const emptyHtml = `
                <tr>
                    <td colspan="3" class="template-empty-state">
                        <div class="dashicons dashicons-editor-table"></div>
                        <p>尚未建立任何範本</p>
                    </td>
                </tr>
            `;
            this.listTbody.html(emptyHtml);
            this.listSection.show();
        },

        // ===== 表單管理方法 =====

        /**
         * 顯示新增表單
         */
        showAddForm: function () {
            this.isEditing = false;
            this.editingId = null;
            this.formTitle.text('新增範本');
            this.resetForm();
            this.showForm();
        },

        /**
         * 顯示編輯表單
         * @param {number} templateId - 範本 ID
         */
        showEditForm: function (templateId) {
            const template = this.templates.find(t => parseInt(t.id) === templateId);
            if (!template) {
                alert('找不到指定的範本');
                return;
            }

            this.isEditing = true;
            this.editingId = templateId;
            this.formTitle.text('編輯範本');
            this.formId.val(templateId);
            this.formCode.val(template.code);
            this.formContent.val(template.content);
            this.updateCharCount();
            this.showForm();
        },

        /**
         * 顯示表單區域
         */
        showForm: function () {
            this.listSection.hide();
            this.formSection.show();
        },

        /**
         * 隱藏表單區域
         */
        hideForm: function () {
            this.formSection.hide();
            this.listSection.show();
        },

        /**
         * 重置表單
         */
        resetForm: function () {
            this.form[0].reset();
            this.formId.val('');
            this.clearFormErrors();
            this.updateCharCount();
        },

        /**
         * 更新字數統計
         */
        updateCharCount: function () {
            const count = this.formContent.val().length;
            this.formCharCount.text(count);

            if (count > 500) {
                this.formCharCount.css('color', '#f44336');
            } else {
                this.formCharCount.css('color', '#666');
            }
        },

        // ===== CRUD 操作方法 =====

        /**
         * 儲存範本 (新增或更新)
         */
        saveTemplate: function () {
            if (!this.validateForm()) {
                return;
            }

            const formData = {
                id: this.isEditing ? this.editingId : 0,
                code: this.formCode.val().trim(),
                content: this.formContent.val().trim()
            };

            // 顯示載入狀態
            this.showManageLoading();

            this.apiSaveTemplate(formData, (response) => {
                this.loadTemplates(() => {
                    this.hideManageLoading();
                    this.renderManageList();
                    this.hideForm();
                    this.resetForm();

                    // 使用更友善的提示方式
                    const message = response.message || (this.isEditing ? '範本已更新' : '範本已新增');
                    this.showSuccessMessage(message);
                });
            });
        },


        /**
         * 編輯範本
         * @param {number} templateId - 範本 ID
         */
        editTemplate: function (templateId) {
            this.showEditForm(templateId);
        },

        /**
         * 刪除範本
         * @param {number} templateId - 範本 ID
         */
        deleteTemplate: function (templateId) {
            const template = this.templates.find(t => parseInt(t.id) === templateId);
            if (!template) {
                return;
            }

            if (confirm(`確定要刪除範本「${template.code}」嗎？`)) {
                // 顯示載入狀態
                this.showManageLoading();

                this.apiDeleteTemplate(templateId, (response) => {
                    this.loadTemplates(() => {
                        this.hideManageLoading();
                        this.renderManageList();

                        // 使用更友善的提示方式
                        const message = response.message || '範本已刪除';
                        this.showSuccessMessage(message);
                    });
                });
            }
        },

        // ===== 工具方法 =====


        /**
         * 表單驗證
         * @return {boolean} 驗證結果
         */
        validateForm: function () {
            this.clearFormErrors();
            let isValid = true;

            const code = this.formCode.val().trim();
            const content = this.formContent.val().trim();

            // 驗證快速代碼
            if (!code) {
                this.showFormError('code', '請輸入快速代碼');
                isValid = false;
            } else if (!/^[a-zA-Z0-9_/-]+$/.test(code)) {
                this.showFormError('code', '快速代碼只能包含英數字、底線和橫線');
                isValid = false;
            } else {
                // 檢查代碼是否重複
                const existingTemplate = this.templates.find(t =>
                    t.code === code && (!this.isEditing || parseInt(t.id) !== this.editingId)
                );
                if (existingTemplate) {
                    this.showFormError('code', '此快速代碼已存在');
                    isValid = false;
                }
            }

            // 驗證範本內容
            if (!content) {
                this.showFormError('content', '請輸入範本內容');
                isValid = false;
            } else if (content.length > 500) {
                this.showFormError('content', '範本內容不能超過 500 字');
                isValid = false;
            }

            return isValid;
        },

        /**
         * 顯示表單錯誤
         * @param {string} field - 欄位名稱
         * @param {string} message - 錯誤訊息
         */
        showFormError: function (field, message) {
            $(`#template-form-${field}-error`).text(message).show();
        },

        /**
         * 清除表單錯誤
         */
        clearFormErrors: function () {
            $('.template-form-error').hide().text('');
        },


        // ===== 自動完成功能方法 =====

        /**
         * 處理訊息輸入事件
         * @param {Event} e - 輸入事件
         */
        handleMessageInput: function (e) {
            const input = this.chatArea.messageInput[0];
            const value = input.value;
            const cursorPosition = input.selectionStart;

            // 檢查是否有井字號觸發
            const hashMatch = this.findHashTrigger(value, cursorPosition);

            if (hashMatch) {
                this.currentHashPosition = hashMatch.start;
                this.currentQuery = hashMatch.query;
                this.showAutocomplete(hashMatch.query);
            } else {
                this.closeAutocomplete();
            }
        },

        /**
         * 尋找井字號觸發
         * @param {string} text - 輸入文字
         * @param {number} cursorPosition - 游標位置
         * @return {object|null} 匹配結果
         */
        findHashTrigger: function (text, cursorPosition) {
            // 從游標位置往前找最近的井字號
            let hashIndex = -1;
            for (let i = cursorPosition - 1; i >= 0; i--) {
                if (text[i] === '#') {
                    hashIndex = i;
                    break;
                }
                // 如果遇到空白字符，停止搜尋
                if (/\s/.test(text[i])) {
                    break;
                }
            }

            if (hashIndex === -1) {
                return null;
            }

            // 確保井字號前面是空白或開始位置
            if (hashIndex > 0 && !/\s/.test(text[hashIndex - 1])) {
                return null;
            }

            // 提取查詢字串
            const query = text.substring(hashIndex + 1, cursorPosition);

            return {
                start: hashIndex,
                end: cursorPosition,
                query: query
            };
        },

        /**
         * 顯示自動完成下拉選單
         * @param {string} query - 搜尋查詢
         */
        showAutocomplete: function (query) {
            const filteredTemplates = this.filterTemplates(query);
            this.renderAutocomplete(filteredTemplates);
            this.positionAutocomplete();

            // 自動選中第一個範本
            if (filteredTemplates.length > 0) {
                this.selectedIndex = 0;
                // 為第一個項目添加選中樣式
                this.autocompleteList.find('.autocomplete-item').first().addClass('selected');
            } else {
                this.selectedIndex = -1;
            }

            this.isAutocompleteOpen = true;
            this.autocompleteDropdown.show();
        },

        /**
         * 過濾範本
         * @param {string} query - 搜尋查詢
         * @return {Array} 過濾後的範本列表
         */
        filterTemplates: function (query) {
            let filteredTemplates;

            if (!query) {
                filteredTemplates = this.templates.slice(); // 複製所有範本
            } else {
                filteredTemplates = this.templates.filter(template => {
                    return template.code.toLowerCase().includes(query.toLowerCase()) ||
                        template.content.toLowerCase().includes(query.toLowerCase());
                });
            }

            // 按快速代碼英文首字排序
            filteredTemplates.sort((a, b) => {
                return a.code.toLowerCase().localeCompare(b.code.toLowerCase());
            });

            return filteredTemplates.slice(0, 8); // 顯示前8個範本
        },

        /**
         * 渲染自動完成選單
         * @param {Array} templates - 範本列表
         */
        renderAutocomplete: function (templates) {
            if (templates.length === 0) {
                this.autocompleteList.html(`
                    <div class="autocomplete-empty">
                        <span class="dashicons dashicons-editor-table"></span>
                        找不到匹配的範本
                    </div>
                `);
                return;
            }

            const items = templates.map((template, index) => `
                <div class="autocomplete-item" data-template-id="${template.id}" data-index="${index}">
                    <div class="autocomplete-content">
                        <div class="autocomplete-title">#${this.escapeHtml(template.code)}</div>
                        <div class="autocomplete-description">${this.escapeHtml(template.content)}</div>
                    </div>
                </div>
            `).join('');

            this.autocompleteList.html(items);
        },

        /**
         * 定位自動完成下拉選單到游標位置下方
         */
        positionAutocomplete: function () {
            if (!this.chatArea || !this.chatArea.messageInput) {
                return;
            }

            const $input = this.chatArea.messageInput;
            const input = $input[0];
            const $inputContainer = $input.closest('.input-container');

            // 獲取相對於 input-container 的位置
            const inputContainerOffset = $inputContainer.offset();
            const inputOffset = $input.offset();
            const relativeLeft = inputOffset.left - inputContainerOffset.left;
            const relativeTop = inputOffset.top - inputContainerOffset.top;

            // 估算每個字符的寬度（基於字體大小）
            const fontSize = parseInt($input.css('fontSize')) || 14;
            const charWidth = fontSize * 0.6; // 大概估算

            // 計算游標前的字符數（從最後一個換行符開始）
            const textBeforeCursor = input.value.substring(0, input.selectionStart);
            const lastLineText = textBeforeCursor.split('\n').pop();

            // 估算游標相對位置
            const paddingLeft = parseInt($input.css('paddingLeft')) || 0;
            const cursorLeft = relativeLeft + paddingLeft + (lastLineText.length * charWidth);
            const lineHeight = parseInt($input.css('lineHeight')) || fontSize * 1.2;
            const lineNumber = textBeforeCursor.split('\n').length - 1;
            const paddingTop = parseInt($input.css('paddingTop')) || 0;
            const cursorTop = relativeTop + paddingTop + (lineNumber * lineHeight) + lineHeight;

            // 計算下拉選單寬度
            const containerWidth = $inputContainer.width();
            const dropdownWidth = Math.min(400, containerWidth - cursorLeft - 20);

            // 如果游標太靠右，調整下拉選單位置
            let finalLeft = cursorLeft;
            if (cursorLeft + dropdownWidth > containerWidth - 20) {
                finalLeft = containerWidth - dropdownWidth - 20;
                if (finalLeft < 0) finalLeft = 0;
            }

            // 定位下拉選單（相對於 input-container）
            this.autocompleteDropdown.css({
                position: 'absolute',
                top: cursorTop + 5,
                left: finalLeft,
                width: dropdownWidth,
                zIndex: 1000
            });
        },

        /**
         * 關閉自動完成
         */
        closeAutocomplete: function () {
            this.isAutocompleteOpen = false;
            this.selectedIndex = -1;
            this.currentHashPosition = -1;
            this.currentQuery = '';
            this.autocompleteDropdown.hide();
        },

        /**
         * 處理鍵盤按鍵
         * @param {Event} e - 鍵盤事件
         * @return {boolean} 是否已處理該事件
         */
        handleKeyDown: function (e) {
            if (!this.isAutocompleteOpen) {
                return false;
            }

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.moveSelection(1);
                    return true;
                case 'ArrowUp':
                    e.preventDefault();
                    this.moveSelection(-1);
                    return true;
                case 'Enter':
                    if (this.selectedIndex >= 0) {
                        e.preventDefault();
                        e.stopPropagation(); // 阻止事件冒泡到外部處理器
                        this.selectCurrentTemplate();
                        return true; // 表示事件已被處理
                    }
                    return false;
                case 'Escape':
                    e.preventDefault();
                    this.closeAutocomplete();
                    return true;
                default:
                    return false;
            }
        },

        moveSelection: function (direction) {
            const items = this.autocompleteList.find('.autocomplete-item');
            const maxIndex = items.length - 1;

            // 更新選擇索引
            this.selectedIndex += direction;

            if (this.selectedIndex > maxIndex) {
                this.selectedIndex = 0;
            } else if (this.selectedIndex < 0) {
                this.selectedIndex = maxIndex;
            }

            // 更新視覺效果
            items.removeClass('selected');
            if (this.selectedIndex >= 0) {
                const $selectedItem = $(items[this.selectedIndex]);
                $selectedItem.addClass('selected');

                // 自動滾動到選中項目 - 傳遞 jQuery 元素
                this.scrollToSelectedItem($selectedItem);
            }
        },

        scrollToSelectedItem: function ($selectedItem) {
            if (!$selectedItem.length) {
                return;
            }

            const $dropdown = this.autocompleteDropdown;
            const dropdownHeight = $dropdown.height();
            const dropdownScrollTop = $dropdown.scrollTop();

            // 獲取項目相對於下拉選單的位置
            const itemTop = $selectedItem.position().top;
            const itemHeight = $selectedItem.outerHeight();
            const itemBottom = itemTop + itemHeight;

            // 檢查是否需要滾動
            if (itemTop < 0) {
                // 項目在可視範圍上方，向上滾動
                $dropdown.scrollTop(dropdownScrollTop + itemTop);
            } else if (itemBottom > dropdownHeight) {
                // 項目在可視範圍下方，向下滾動
                $dropdown.scrollTop(dropdownScrollTop + itemBottom - dropdownHeight);
            }
            // 如果項目在可視範圍內，不需要滾動
        },

        /**
         * 選擇當前範本
         */
        selectCurrentTemplate: function () {
            const selectedItem = this.autocompleteList.find('.autocomplete-item.selected');
            if (selectedItem.length) {
                const templateId = parseInt(selectedItem.data('template-id'));
                this.selectTemplate(templateId);
            }
        },

        /**
         * 選擇範本
         * @param {number} templateId - 範本 ID
         */
        selectTemplate: function (templateId) {
            const template = this.templates.find(t => parseInt(t.id) === templateId);
            if (!template) {
                return;
            }

            this.replaceHashWithTemplate(template.content);
            this.closeAutocomplete();
        },

        /**
         * 將井字號替換為範本內容
         * @param {string} templateContent - 範本內容
         */
        replaceHashWithTemplate: function (templateContent) {
            if (!this.chatArea || !this.chatArea.messageInput || this.currentHashPosition === -1) {
                return;
            }

            const input = this.chatArea.messageInput[0];
            const value = input.value;
            const cursorPosition = input.selectionStart;

            // 替換文字
            const newValue = value.substring(0, this.currentHashPosition) +
                templateContent +
                value.substring(cursorPosition);

            input.value = newValue;

            // 設定游標位置
            const newCursorPosition = this.currentHashPosition + templateContent.length;
            input.setSelectionRange(newCursorPosition, newCursorPosition);
            input.focus();

            // 觸發輸入事件以更新發送按鈕狀態
            if (window.ChatAreaInput && this.chatArea) {
                window.ChatAreaInput.handleMessageInput(this.chatArea, {target: input});
            }
        },

        /**
         * HTML 跳脫
         * @param {string} text - 要跳脫的文字
         * @return {string} 跳脫後的文字
         */
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

})(jQuery);