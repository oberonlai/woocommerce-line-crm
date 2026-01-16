/**
 * OrderChatz 客戶備註管理模組
 *
 * 處理客戶備註的新增、刪除、編輯、分類功能
 *
 * @package OrderChatz
 * @since 1.0.23
 */

(function ($) {
    'use strict';

    /**
     * 客戶備註管理器
     */
    window.CustomerNotesManager = function (container) {
        this.container = container;
        this.currentFriend = null;
        this.categoryColorMap = null; // 分類顏色快取
        this.relatedMessageData = null; // 關聯訊息資料快取
    };

    CustomerNotesManager.prototype = {
        /**
         * 渲染備註區塊
         * @param {Array} notes - 備註陣列
         * @returns {string} HTML 字串
         */
        renderNotesSection: function (notes = []) {
            const template = $('#customer-notes-template').html();
            if (!template) {
                console.error('CustomerNotesManager: 客戶備註模板未找到');
                return '';
            }

            let notesHtml = `
                <div class="notes-content">
                    <!-- 新增備註按鈕 -->
                    <div class="add-note-section" style="margin-bottom: 12px;">
                        <button type="button" class="button button-secondary trigger-note-lightbox" style="font-size: 12px;">新增備註</button>
                        <span class="notes-message"></span>
                    </div>
                    <!-- 現有備註列表 -->
                    <div class="existing-notes-section">
                        ${this.renderExistingNotes(notes)}
                    </div>
                </div>
            `;

            return template.replace(/\{notesHtml\}/g, notesHtml);
        },

        /**
         * 渲染現有備註列表
         * @param {Array} notes - 備註陣列
         * @returns {string} HTML 字串
         */
        renderExistingNotes: function (notes = []) {
            if (!notes || notes.length === 0) {
                return '';
            }

            let html = `<div class="notes-cards-container">`;

            notes.forEach((note, index) => {
                const createdAt = new Date(note.created_at).toLocaleDateString('zh-TW', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const noteId = note.id || `temp_${index}`;
                const noteCategory = note.category || '';
                const relatedProductId = note.related_product_id || 0;
                const relatedProductName = note.related_product_name || '';
                const relatedProductUrl = note.related_product_url || '';
                const relatedMessage = note.related_message || null;

                // 準備關聯訊息預覽
                let relatedMessagePreview = '';
                if (relatedMessage && relatedMessage.content && relatedMessage.type) {
                    relatedMessagePreview = this.formatRelatedMessagePreview(relatedMessage, true);
                }

                // 準備商品連結
                let productLinkHtml = '';
                if (relatedProductId && relatedProductName && relatedProductUrl) {
                    productLinkHtml = `<div class="note-product-link">
                        <span>關聯商品 - </span>
                        <a class="button-link" href="${this.escapeHtml(relatedProductUrl)}" target="_blank" rel="noopener">${this.escapeHtml(relatedProductName)}</a>
                    </div>`;
                }

                html += `
                    <div class="note-card" data-note-id="${noteId}" data-category="${this.escapeHtml(noteCategory)}" data-related-product-id="${relatedProductId}" ${relatedMessage ? `data-related-message='${JSON.stringify(relatedMessage).replace(/'/g, '&#39;')}'` : ''} style="
                        position: relative; 
                        background: #fff; 
                        border: 1px solid #e0e0e0; 
                        border-radius: 8px; 
                        padding: 12px; 
                        margin-bottom: 8px; 
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                        transition: all 0.2s ease;
                        cursor: default;
                    ">
                        <div class="note-content-display">
                            ${noteCategory ? `<div class="note-category" data-category="${this.escapeHtml(noteCategory)}" style="
                                font-size: 11px;
                                color: #fff;
                                padding: 2px 6px;
                                border-radius: 3px;
                                margin-bottom: 6px;
                                display: inline-block;
                                background-color: ${this.getCategoryColor(noteCategory)};
                            ">${this.escapeHtml(noteCategory)}</div>` : ''}
                            ${relatedMessagePreview ? `<div>${relatedMessagePreview}</div>` : ''}
                            <div class="note-text">${this.convertLinksToHtml(note.note)}</div>
                            ${productLinkHtml}
                            <div class="note-meta">${createdAt}</div>
                        </div>
                        ${note.id ? `
                            <div class="note-actions" style="
                                position: absolute; 
                                bottom: 8px; 
                                right: 0; 
                                opacity: 0;
                                transition: opacity 0.2s ease;
                                display: flex;
                                gap: 4px;
                            ">
                                <button type="button" class="button button-small edit-note" data-note-id="${noteId}" title="編輯" style="
                                    font-size: 10px;  
                                    min-height: auto;
                                    line-height: 1.4;
                                    background: none;
                                    width: 22px;
                                    border: none;
                                "><span class="dashicons dashicons-edit"></span></button>
                                <button type="button" class="button button-small delete-note" data-note-id="${noteId}" title="刪除" style="
                                    font-size: 10px;  
                                    min-height: auto;
                                    line-height: 1.4;
                                    background: none;
                                    border: none; 
                                    color: #d32d39;
                                "><span class="dashicons dashicons-trash"></span></button>
                            </div>
                        ` : `
                            <div class="note-no-actions" style="
                                position: absolute; 
                                top: 8px; 
                                right: 12px; 
                                font-size: 10px; 
                                color: #999;
                            ">無法編輯</div>
                        `}
                    </div>
                `;
            });

            html += `</div>`;
            return html;
        },

        /**
         * 初始化備註功能
         */
        init: function () {
            // 先移除舊的事件綁定，避免重複綁定
            this.container.off('.customer-notes-manager');

            // 開啟新增備註燈箱
            this.container.on('click.customer-notes-manager', '.trigger-note-lightbox', (e) => {
                e.preventDefault();
                this.showAddNoteLightbox();
            });

            // 編輯備註
            this.container.on('click.customer-notes-manager', '.edit-note', (e) => {
                e.preventDefault();
                const button = $(e.target).closest('.edit-note');
                const noteId = button.data('note-id');
                this.editNote(noteId);
            });

            // 刪除備註
            this.container.on('click.customer-notes-manager', '.delete-note', (e) => {
                e.preventDefault();
                const button = $(e.target).closest('.delete-note');
                const noteId = button.data('note-id');
                this.deleteNote(noteId);
            });

            // hover 效果 - 顯示/隱藏操作按鈕
            this.container.on('mouseenter.customer-notes-manager', '.note-card', function () {
                $(this).find('.note-actions').css('opacity', '1');
                $(this).css({
                    'box-shadow': '0 2px 8px rgba(0,0,0,0.15)',
                    'border-color': '#ccc'
                });
            });

            this.container.on('mouseleave.customer-notes-manager', '.note-card', function () {
                $(this).find('.note-actions').css('opacity', '0');
                $(this).css({
                    'box-shadow': '0 1px 3px rgba(0,0,0,0.1)',
                    'border-color': '#e0e0e0'
                });
            });

            // 點擊備註中的原始訊息預覽跳轉到對話
            this.container.on('click.customer-notes-manager', '.note-card .related-message-preview.clickable-message-preview', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleRelatedMessageClick($(e.currentTarget));
            });
        },

        /**
         * 開啟新增備註燈箱
         * @param {object} relatedMessageData - 關聯訊息資料
         */
        openAddNoteLightbox: function (relatedMessageData = null) {
            // 儲存關聯訊息資料供後續使用
            this.relatedMessageData = relatedMessageData;
            // 重置選中的產品 ID
            this.selectedProductId = 0;

            const lightboxHtml = `
                <div class="add-note-lightbox">
                    <div class="lightbox-content">
                        <h3>新增備註</h3>

                        <div class="lightbox-field">
                            ${this.formatRelatedMessagePreview(relatedMessageData)}
                        </div>

                        <div class="lightbox-field">
                            <label>備註分類</label>
                            <select class="lightbox-note-category-select">
                                <option value="">請選擇分類...</option>
                            </select>
                        </div>

                        <div class="lightbox-field">
                            <label>關聯商品</label>
                            <select class="lightbox-product-select" style="width: 100%;">
                                <option value="">請選擇商品...</option>
                            </select>
                        </div>

                        <div class="lightbox-field">
                            <label>備註內容</label>
                            <textarea class="lightbox-add-note-textarea" placeholder="請輸入備註內容..."></textarea>
                        </div>

                        <div class="lightbox-actions">
                            <button type="button" class="button cancel-add-lightbox">取消</button>
                            <button type="button" class="button button-primary save-add-lightbox">新增</button>
                        </div>
                        <button class="lightbox-close" title="關閉">×</button>
                    </div>
                </div>
            `;

            // 添加燈箱到頁面
            $('body').append(lightboxHtml);

            // 初始化分類下拉選單
            this.initNoteCategorySelect2($('.lightbox-note-category-select'));

            // 初始化產品下拉選單
            this.initProductSelect2($('.lightbox-product-select'));

            // 聚焦到文字框
            setTimeout(() => {
                $('.lightbox-add-note-textarea').focus();
            }, 100);

            // 綁定事件
            this.bindLightboxEvents('.add-note-lightbox', 'add');

            // ESC 鍵關閉
            $(document).on('keydown.add-note-lightbox', (e) => {
                if (e.key === 'Escape') {
                    this.closeAddNoteLightbox();
                }
            });
        },

        /**
         * 顯示新增備註燈箱（向後相容方法）
         * @param {object} relatedMessageData - 關聯訊息資料
         */
        showAddNoteLightbox: function (relatedMessageData = null) {
            // 調用原本的方法，並傳遞關聯訊息資料
            return this.openAddNoteLightbox(relatedMessageData);
        },


        /**
         * 開啟編輯備註燈箱
         * @param {string} noteId - 備註 ID
         */
        editNote: function (noteId) {
            const noteCard = this.container.find(`.note-card[data-note-id="${noteId}"]`);
            if (noteCard.length === 0) return;

            const currentContent = noteCard.find('.note-text').text();
            const currentCategory = noteCard.data('category') || '';
            const currentProductId = noteCard.data('related-product-id') || 0;
            const relatedMessageData = noteCard.data('related-message') || null;

            // 重置選中的產品 ID
            this.selectedProductId = currentProductId;

            // 準備關聯訊息預覽
            let relatedMessagePreview = '';
            if (relatedMessageData && relatedMessageData.content && relatedMessageData.type) {
                relatedMessagePreview = this.formatRelatedMessagePreview(relatedMessageData);
            }

            const lightboxHtml = `
                <div class="note-edit-lightbox">
                    <div class="lightbox-content">
                        <h3>編輯備註</h3>

                        ${relatedMessagePreview ? `<div class="lightbox-field">${relatedMessagePreview}</div>` : ''}

                        <div class="lightbox-field">
                            <label>備註分類</label>
                            <select class="lightbox-note-category-select" data-current-category="${this.escapeHtml(currentCategory)}">
                                <option value="">請選擇分類...</option>
                            </select>
                        </div>

                        <div class="lightbox-field">
                            <label>關聯商品</label>
                            <select class="lightbox-product-select" data-current-product-id="${currentProductId}" style="width: 100%;">
                                <option value="">請選擇商品...</option>
                            </select>
                        </div>

                        <div class="lightbox-field">
                            <label>備註內容</label>
                            <textarea class="lightbox-note-textarea" placeholder="請輸入備註內容...">${this.escapeHtml(currentContent)}</textarea>
                        </div>

                        <div class="lightbox-actions">
                            <button type="button" class="button cancel-edit-lightbox">取消</button>
                            <button type="button" class="button button-primary save-edit-lightbox" data-note-id="${noteId}">儲存</button>
                        </div>
                        <button class="lightbox-close" title="關閉">×</button>
                    </div>
                </div>
            `;

            // 添加燈箱到頁面
            $('body').append(lightboxHtml);

            // 初始化分類下拉選單
            this.initNoteCategorySelect2($('.lightbox-note-category-select'));

            // 初始化產品下拉選單
            this.initProductSelect2($('.lightbox-product-select'), currentProductId);

            // 聚焦到文字框並設定游標位置
            setTimeout(() => {
                const textarea = $('.lightbox-note-textarea').focus().get(0);
                if (textarea && textarea.setSelectionRange) {
                    textarea.setSelectionRange(currentContent.length, currentContent.length);
                }
            }, 100);

            // 綁定事件
            this.bindLightboxEvents('.note-edit-lightbox', 'edit');

            // ESC 鍵關閉
            $(document).on('keydown.edit-lightbox', (e) => {
                if (e.key === 'Escape') {
                    this.closeLightbox();
                }
            });
        },

        /**
         * 綁定燈箱事件
         * @param {string} lightboxSelector - 燈箱選擇器
         * @param {string} type - 類型 ('add' 或 'edit')
         */
        bindLightboxEvents: function (lightboxSelector, type) {
            const $lightbox = $(lightboxSelector);

            // 關閉事件
            const closeClass = type === 'add' ? '.cancel-add-lightbox' : '.cancel-edit-lightbox';
            $lightbox.on('click', `.lightbox-close, ${closeClass}`, (e) => {
                e.preventDefault();
                if (type === 'add') {
                    this.closeAddNoteLightbox();
                } else {
                    this.closeLightbox();
                }
            });

            // 點擊背景關閉
            $lightbox.on('click', (e) => {
                if (e.target === e.currentTarget) {
                    if (type === 'add') {
                        this.closeAddNoteLightbox();
                    } else {
                        this.closeLightbox();
                    }
                }
            });

            // 儲存事件
            const saveClass = type === 'add' ? '.save-add-lightbox' : '.save-edit-lightbox';
            $lightbox.on('click', saveClass, (e) => {
                e.preventDefault();
                if (type === 'add') {
                    this.saveAddNoteLightbox();
                } else {
                    const noteId = $(e.target).data('note-id');
                    this.saveEditLightbox(noteId);
                }
            });
        },

        /**
         * 關閉新增備註燈箱
         */
        closeAddNoteLightbox: function () {
            $('.add-note-lightbox').fadeOut(200, function () {
                $(this).remove();
            });
            $(document).off('keydown.add-note-lightbox');
            // 清除關聯訊息資料
            this.relatedMessageData = null;
        },

        /**
         * 關閉編輯備註燈箱
         */
        closeLightbox: function () {
            $('.note-edit-lightbox').fadeOut(200, function () {
                $(this).remove();
            });
            $(document).off('keydown.edit-lightbox');
        },

        /**
         * 儲存新增備註
         */
        saveAddNoteLightbox: function () {
            const notes = $('.lightbox-add-note-textarea').val().trim();
            const category = $('.lightbox-note-category-select').val();
            const productId = $('.lightbox-product-select').val() || 0;

            if (!notes) {
                alert('備註內容不能為空');
                return;
            }

            // 從 Select2 獲取最新的產品 ID 並更新.
            this.selectedProductId = parseInt(productId) || 0;

            this.saveNote(notes, category, 'add', null, this.relatedMessageData);
        },

        /**
         * 儲存編輯備註
         * @param {string} noteId - 備註 ID
         */
        saveEditLightbox: function (noteId) {
            const newContent = $('.lightbox-note-textarea').val().trim();
            const category = $('.lightbox-note-category-select').val();
            const productId = $('.lightbox-product-select').val() || 0;

            if (!newContent) {
                alert('備註內容不能為空');
                return;
            }

            // 從 Select2 獲取最新的產品 ID 並更新.
            this.selectedProductId = parseInt(productId) || 0;

            this.saveNote(newContent, category, 'edit', noteId);
        },

        /**
         * 儲存備註的通用方法
         * @param {string} noteContent - 備註內容
         * @param {string} category - 分類
         * @param {string} type - 操作類型 ('add' 或 'edit')
         * @param {string} noteId - 備註 ID (編輯時需要)
         * @param {object} relatedMessageData - 關聯訊息資料 (新增時使用)
         */
        saveNote: function (noteContent, category, type, noteId = null, relatedMessageData = null) {
            if (!this.currentFriend) {
                this.showMessage('無法取得好友資訊', 'error');
                return;
            }

            // 判斷是否為群組.
            const isGroup = this.currentFriend.source_type === 'group' || this.currentFriend.source_type === 'room';
            const sourceType = this.currentFriend.source_type || 'user';
            const lineUserId = this.currentFriend.line_user_id || this.currentFriend.id;
            const groupId = isGroup ? (this.currentFriend.group_id || this.currentFriend.id) : '';

            let action, data;

            if (type === 'add') {
                action = 'otz_save_customer_notes';
                data = {
                    action: action,
                    nonce: otzChatConfig.notes_nonce,
                    line_user_id: lineUserId,
                    source_type: sourceType,
                    group_id: groupId,
                    notes: noteContent,
                    category: category,
                    related_product_id: this.selectedProductId || 0
                };

                // 如果有關聯訊息資料，加入到請求中.
                if (relatedMessageData && relatedMessageData.datetime && relatedMessageData.content && relatedMessageData.type) {
                    data.related_message = relatedMessageData;
                }
            } else {
                action = 'otz_edit_customer_note';
                data = {
                    action: action,
                    nonce: otzChatConfig.notes_nonce,
                    line_user_id: lineUserId,
                    source_type: sourceType,
                    group_id: groupId,
                    note_id: noteId,
                    note_content: noteContent,
                    category: category,
                    related_product_id: this.selectedProductId || 0
                };
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        if (type === 'add') {
                            this.closeAddNoteLightbox();
                            this.showMessage('備註已新增', 'success');
                        } else {
                            this.closeLightbox();
                            this.showMessage('備註已更新', 'success');
                            // 更新卡片顯示
                            this.updateNoteCard(noteId, noteContent, category);
                        }

                        // 更新備註顯示
                        this.updateNotesDisplay(response.data.notes);

                        // 觸發備註更新事件
                        $(document).trigger('customer:notes-updated', [this.currentFriend.id, response.data.notes]);
                    } else {
                        const errorMsg = response.data?.message || '未知錯誤';
                        alert(`${type === 'add' ? '儲存' : '更新'}備註失敗：${errorMsg}`);
                    }
                },
                error: () => {
                    alert(`${type === 'add' ? '儲存' : '更新'}備註時發生網路錯誤`);
                }
            });
        },

        /**
         * 更新備註卡片顯示
         * @param {string} noteId - 備註 ID
         * @param {string} content - 新內容
         * @param {string} category - 新分類
         */
        updateNoteCard: function (noteId, content, category) {
            const noteCard = this.container.find(`.note-card[data-note-id="${noteId}"]`);
            if (noteCard.length > 0) {
                noteCard.attr('data-category', category);
                noteCard.find('.note-text').html(this.convertLinksToHtml(content));

                // 更新分類顯示
                const categoryDiv = noteCard.find('.note-category');
                if (category) {
                    if (categoryDiv.length === 0) {
                        noteCard.find('.note-content-display').prepend(`
                            <div class="note-category" data-category="${this.escapeHtml(category)}" style="
                                font-size: 11px; 
                                color: #fff; 
                                padding: 2px 6px;
                                border-radius: 3px;
                                margin-bottom: 6px;
                                display: inline-block;
                                background-color: ${this.getCategoryColor(category)};
                            ">${this.escapeHtml(category)}</div>
                        `);
                    } else {
                        categoryDiv.text(category);
                    }
                } else {
                    categoryDiv.remove();
                }
            }
        },

        /**
         * 刪除備註
         * @param {string} noteId - 備註 ID
         */
        deleteNote: function (noteId) {
            if (!this.currentFriend) {
                this.showMessage('無法取得好友資訊', 'error');
                return;
            }

            if (!confirm('確定要刪除這條備註嗎？')) {
                return;
            }

            // 判斷是否為群組.
            const isGroup = this.currentFriend.source_type === 'group' || this.currentFriend.source_type === 'room';
            const sourceType = this.currentFriend.source_type || 'user';
            const lineUserId = this.currentFriend.line_user_id || this.currentFriend.id;
            const groupId = isGroup ? (this.currentFriend.group_id || this.currentFriend.id) : '';

            const data = {
                action: 'otz_delete_customer_note',
                nonce: otzChatConfig.notes_nonce,
                line_user_id: lineUserId,
                source_type: sourceType,
                group_id: groupId,
                note_id: noteId
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 從界面中移除備註卡片
                        const noteCard = this.container.find(`.note-card[data-note-id="${noteId}"]`);
                        noteCard.fadeOut(300, () => {
                            noteCard.remove();

                            // 檢查是否還有備註
                            const remainingNotes = this.container.find('.note-card');
                            if (remainingNotes.length === 0) {
                                this.updateNotesDisplay([]);
                            }
                        });

                        this.showMessage('備註已刪除', 'success');

                        // 觸發備註更新事件
                        $(document).trigger('customer:notes-updated', [this.currentFriend.id, response.data.notes || []]);
                    } else {
                        const errorMsg = response.data?.message || '未知錯誤';
                        this.showMessage('刪除備註失敗：' + errorMsg, 'error');
                    }
                },
                error: () => {
                    this.showMessage('刪除備註時發生網路錯誤', 'error');
                }
            });
        },

        /**
         * 更新備註顯示
         * @param {Array} notes - 備註陣列
         */
        updateNotesDisplay: function (notes) {
            const existingNotesSection = this.container.find('.existing-notes-section');
            if (existingNotesSection.length > 0) {
                existingNotesSection.html(this.renderExistingNotes(notes));
            }
        },

        /**
         * 初始化備註分類 Select2
         * @param {jQuery} selectElement - select 元素
         */
        initNoteCategorySelect2: function (selectElement) {
            if (selectElement.length === 0) {
                return;
            }

            let isComposing = false;

            const selectConfig = {
                placeholder: '搜尋或新增分類...',
                allowClear: true,
                tags: true, // 允許新增分類
                tokenSeparators: [','],
                minimumInputLength: 0,
                ajax: {
                    url: otzChatConfig.ajax_url,
                    dataType: 'json',
                    data: (params) => {
                        return {
                            action: 'otz_search_note_categories',
                            nonce: otzChatConfig.nonce,
                            search: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: (data, params) => {
                        if (isComposing) {
                            return {};
                        }
                        if (!data || !data.success) {
                            return {results: []};
                        }
                        return {
                            results: data.data.results || [],
                            pagination: data.data.pagination || {more: false}
                        };
                    },
                    cache: true
                },
                escapeMarkup: (markup) => markup,
                templateResult: (category) => {
                    if (category.loading) return category.text;
                    return `<div>${category.text}</div>`;
                },
                templateSelection: (category) => category.text || category.id
            };

            if (typeof $.fn.select2 !== 'undefined') {
                selectElement.select2(selectConfig);
                selectElement.on('select2:open', function () {
                    const $search = $('.select2-search__field');
                    $search.on('compositionstart', () => isComposing = true);
                    $search.on('compositionend', () => isComposing = false);
                });
            }


            // 檢查是否有預設選中的分類
            const currentCategory = selectElement.data('current-category');
            if (currentCategory) {
                // 創建一個 option 並設為選中
                const option = new Option(currentCategory, currentCategory, true, true);
                selectElement.append(option).trigger('change');
            }
        },

        /**
         * 初始化產品 Select2
         * @param {jQuery} selectElement - select 元素
         * @param {number} currentProductId - 當前產品 ID (編輯時使用)
         */
        initProductSelect2: function (selectElement, currentProductId = 0) {
            if (selectElement.length === 0) {
                return;
            }

            if (typeof $.fn.select2 === 'undefined') {
                console.error('Select2 not loaded');
                return;
            }

            const self = this;
            let isComposing = false;

            const selectConfig = {
                placeholder: '請輸入商品名稱進行搜尋...',
                allowClear: true,
                minimumInputLength: 0,
                language: {
                    inputTooShort: function () {
                        return '請輸入至少 2 個字元';
                    },
                    noResults: function () {
                        return '找不到相關商品';
                    },
                    searching: function () {
                        return '搜尋中...';
                    }
                },
                ajax: {
                    url: otzChatConfig.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'otz_search_products',
                            search: params.term,
                            nonce: otzChatConfig.nonce
                        };
                    },
                    processResults: function (data) {
                        if (isComposing) {
                            return {};
                        }

                        if (data.success) {
                            return {
                                results: data.data.products.map(function (product) {
                                    return {
                                        id: product.id,
                                        text: product.title,
                                        product: product
                                    };
                                })
                            };
                        }
                        return {results: []};
                    },
                    cache: true
                },
                templateResult: function (product) {
                    if (product.loading) {
                        return product.text;
                    }

                    if (!product.product) {
                        return product.text;
                    }

                    try {
                        const productData = product.product || {};
                        const imageUrl = (productData.image && typeof productData.image === 'string') ? productData.image : '';
                        const title = productData.title || '未命名商品';
                        const price = productData.price || '0';

                        const imageHtml = imageUrl ?
                            '<img src="' + imageUrl + '" alt="' + title + '" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px;" />' :
                            '<div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px; margin-right: 10px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">無圖</div>';

                        const $container = $(
                            '<div class="product-option">' +
                            '<div class="product-image">' +
                            imageHtml +
                            '</div>' +
                            '<div class="product-info">' +
                            '<div class="product-title">' + title + '</div>' +
                            '<div class="product-price">' + price + '</div>' +
                            '</div>' +
                            '</div>'
                        );

                        return $container;
                    } catch (error) {
                        console.error('Error in templateResult:', error);
                        return product.text || '商品載入錯誤';
                    }
                },
                templateSelection: function (product) {
                    return product.text;
                }
            };

            selectElement.select2(selectConfig);

            // 當選擇商品時
            selectElement.on('select2:select', function (e) {
                const data = e.params.data;
                self.selectedProductId = data.id || 0;
            });

            // 當清除選擇時
            selectElement.on('select2:clear', function (e) {
                self.selectedProductId = 0;
            });

            selectElement.on('select2:open', function () {
                const $search = $('.select2-search__field');
                $search.on('compositionstart', () => isComposing = true);
                $search.on('compositionend', () => isComposing = false);
            });

            // 如果有預設的產品 ID，載入並設定
            if (currentProductId > 0) {
                $.ajax({
                    url: otzChatConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'otz_get_product_by_id',
                        product_id: currentProductId,
                        nonce: otzChatConfig.nonce
                    },
                    success: function (response) {
                        if (response.success && response.data.product) {
                            const product = response.data.product;
                            const option = new Option(product.title, product.id, true, true);
                            selectElement.append(option).trigger('change');
                            self.selectedProductId = product.id;
                        }
                    }
                });
            }
        },

        /**
         * 顯示訊息
         * @param {string} message - 訊息內容
         * @param {string} type - 訊息類型 (success, error)
         */
        showMessage: function (message, type = 'success') {
            const messageContainer = this.container.find('.notes-message');
            if (messageContainer.length === 0) {
                return;
            }

            // 清除之前的訊息
            messageContainer.removeClass('success error').empty();

            // 添加新訊息
            const icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-dismiss';
            messageContainer.addClass(type).html(`
                <span class="dashicons ${icon}"></span>
                ${this.escapeHtml(message)}
            `);

            // 3秒後自動清除
            setTimeout(() => {
                messageContainer.fadeOut(300, function () {
                    $(this).removeClass('success error').empty().show();
                });
            }, 3000);
        },

        /**
         * 處理關聯訊息預覽點擊事件
         * @param {jQuery} $clickedElement - 點擊的元素
         */
        handleRelatedMessageClick: function ($clickedElement) {
            const messageDateTime = $clickedElement.data('message-datetime');

            if (!messageDateTime) {
                console.error('CustomerNotesManager: 找不到訊息時間戳');
                this.showMessage('找不到原始訊息時間戳', 'error');
                return;
            }

            // 檢查當前好友資訊
            if (!this.currentFriend) {
                console.error('CustomerNotesManager: 當前好友資訊不可用');
                this.showMessage('請先選擇好友', 'error');
                return;
            }

            // 檢測是否為前台模式
            const isFrontendMode = window.otzMobileTabNavigation && typeof window.otzMobileTabNavigation.switchToPanel === 'function';
            if (isFrontendMode) {
                // 前台模式：先切換到聊天分頁，再執行跳轉
                this.handleFrontendModeJump($clickedElement, messageDateTime);
            } else {
                // 後台模式：直接執行跳轉
                this.handleBackendModeJump($clickedElement, messageDateTime);
            }
        },

        /**
         * 處理前台模式的訊息跳轉
         * @param {jQuery} $clickedElement - 點擊的元素
         * @param {string} messageDateTime - 訊息時間戳
         */
        handleFrontendModeJump: function ($clickedElement, messageDateTime) {

            // 加入載入狀態
            $clickedElement.addClass('loading');

            // 先切換到聊天分頁
            try {

                // 切換到聊天分頁
                window.otzMobileTabNavigation.switchToPanel('chat');

                // 延遲執行跳轉，確保分頁切換完成
                setTimeout(() => {
                    this.executeMessageJump($clickedElement, messageDateTime);
                }, 800);

            } catch (error) {
                console.error('CustomerNotesManager: 前台分頁切換失敗', error);
                $clickedElement.removeClass('loading');
                this.showMessage('切換到聊天分頁失敗', 'error');
            }
        },

        /**
         * 處理後台模式的訊息跳轉
         * @param {jQuery} $clickedElement - 點擊的元素
         * @param {string} messageDateTime - 訊息時間戳
         */
        handleBackendModeJump: function ($clickedElement, messageDateTime) {

            // 加入載入狀態
            $clickedElement.addClass('loading');

            // 直接執行跳轉
            this.executeMessageJump($clickedElement, messageDateTime);
        },

        /**
         * 執行訊息跳轉的核心邏輯
         * @param {jQuery} $clickedElement - 點擊的元素
         * @param {string} messageDateTime - 訊息時間戳
         */
        executeMessageJump: function ($clickedElement, messageDateTime) {
            // 檢查全局變數是否可用
            if (!window.ChatAreaMessages || !window.ChatAreaMessages.jumpToQuotedMessage) {
                console.error('CustomerNotesManager: ChatAreaMessages.jumpToQuotedMessage 方法未找到');
                $clickedElement.removeClass('loading');
                return;
            }

            if (!window.chatAreaInstance) {
                console.error('CustomerNotesManager: chatAreaInstance 未找到');
                $clickedElement.removeClass('loading');
                return;
            }

            // 確保聊天區域有正確的好友資訊
            const lineUserId = this.currentFriend.line_user_id || this.currentFriend.id;
            if (!lineUserId) {
                console.error('CustomerNotesManager: 找不到好友的 LINE User ID');
                $clickedElement.removeClass('loading');
                this.showMessage('找不到好友的 LINE User ID', 'error');
                return;
            }

            // 確保聊天區域實例有正確的狀態
            window.chatAreaInstance.currentLineUserId = lineUserId;
            window.chatAreaInstance.currentFriend = this.currentFriend;

            try {
                // 調用跳轉功能
                window.chatAreaInstance.isLoadingMessages = false;
                window.ChatAreaMessages.jumpToQuotedMessage(window.chatAreaInstance, messageDateTime, 5);
                // 延遲移除載入狀態和退出跳轉模式
                setTimeout(() => {
                    $clickedElement.removeClass('loading');
                }, 1000);

            } catch (error) {
                console.error('CustomerNotesManager: 跳轉到原始訊息時發生錯誤', error);
                $clickedElement.removeClass('loading');

                // 退出跳轉模式
                if (window.otzMobileTabNavigation && window.otzMobileTabNavigation.exitJumpMode) {
                    window.otzMobileTabNavigation.exitJumpMode();
                }

                this.showMessage('跳轉到原始訊息失敗', 'error');
            }
        },

        /**
         * HTML 轉義方法
         * @param {string} str - 要轉義的字串
         * @returns {string} 轉義後的字串
         */
        escapeHtml: function (str) {
            if (typeof str !== 'string') return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        /**
         * 將純文字中的連結轉換為可點擊的超連結
         * @param {string} text - 原始文字內容
         * @returns {string} 轉換後的 HTML
         */
        convertLinksToHtml: function (text) {
            if (typeof text !== 'string') return '';

            // URL 正則表達式，支援 http、https、www 開頭的連結
            const urlRegex = /(https?:\/\/[^\s<>"]+)|(www\.[^\s<>"]+)/gi;

            // 先進行 HTML 轉義確保安全性
            const escapedText = this.escapeHtml(text);

            // 保留換行符號，將 \n 轉換為 <br>
            const textWithLineBreaks = escapedText.replace(/\n/g, '<br>');

            // 將連結轉換為 HTML
            return textWithLineBreaks.replace(urlRegex, (url) => {
                const fullUrl = url.startsWith('www.') ? 'https://' + url : url;
                return `<a href="${fullUrl}" target="_blank" rel="noopener noreferrer" style="color: #0073aa; text-decoration: underline;">${url}</a>`;
            });
        },

        /**
         * 根據分類名稱取得顏色（優先使用資料庫設定的顏色）
         * @param {string} categoryName - 分類名稱
         * @returns {string} 顏色代碼
         */
        getCategoryColor: function (categoryName) {
            if (!categoryName) return '#f5f5f5';

            // 初始化分類顏色對應表（快取）
            if (!this.categoryColorMap) {
                this.categoryColorMap = {};

                // 從後端載入的分類資料建立顏色對應表
                if (typeof otzChatConfig !== 'undefined' && otzChatConfig.note_categories) {
                    otzChatConfig.note_categories.forEach(category => {
                        if (category.name && category.color) {
                            this.categoryColorMap[category.name] = category.color;
                        }
                    });
                }
            }

            // 優先使用資料庫中設定的顏色
            if (this.categoryColorMap[categoryName]) {
                return this.categoryColorMap[categoryName];
            }

            // 回退到預設顏色邏輯
            const defaultColors = [
                '#3498db', // 藍色
                '#e74c3c', // 紅色
                '#2ecc71', // 綠色
                '#f39c12', // 橙色
                '#9b59b6', // 紫色
                '#1abc9c', // 青綠色
                '#f1c40f', // 黃色
                '#e67e22', // 深橙色
                '#8e44ad', // 深紫色
                '#27ae60', // 深綠色
                '#2980b9', // 深藍色
                '#c0392b', // 深紅色
            ];

            // 使用分類名稱的 hash 來決定顏色，確保同名分類總是得到相同顏色
            let hash = 0;
            for (let i = 0; i < categoryName.length; i++) {
                hash = ((hash << 5) - hash) + categoryName.charCodeAt(i);
                hash = hash & hash; // 轉為 32-bit integer
            }

            const index = Math.abs(hash) % defaultColors.length;
            return defaultColors[index];
        },

        /**
         * 格式化關聯訊息預覽
         * @param {object} messageData - 訊息資料
         * @param {boolean} isInNoteCard - 是否在備註卡片中使用
         * @returns {string} 格式化後的預覽 HTML
         */
        formatRelatedMessagePreview: function (messageData, isInNoteCard = false) {
            if (!messageData) return '';

            const messageType = messageData.type || 'text';

            let previewContent = this.generatePreviewContent(messageData, messageType);

            // 根據是否在備註卡片中使用，決定是否加入點擊功能
            const previewClass = isInNoteCard ? 'related-message-preview clickable-message-preview' : 'related-message-preview';
            const dataAttribute = isInNoteCard && messageData.datetime ? `data-message-datetime="${messageData.datetime}"` : '';

            return `
                <div class="${previewClass}" ${dataAttribute}>
                    <div class="related-message-header">
                        <label style="font-weight: 600; color: #333;">原始訊息</label>
                    </div>
                    <div class="related-message-content">
                        ${previewContent}
                    </div>
                </div>
            `;
        },

        /**
         * 生成預覽內容
         * @param {object} messageData - 訊息資料
         * @param {string} messageType - 訊息類型
         * @returns {string} 預覽內容 HTML
         */
        generatePreviewContent: function (messageData, messageType) {
            switch (messageType) {
                case 'image':
                    return this.generateImagePreview(messageData);
                case 'sticker':
                    return this.generateStickerPreview(messageData);
                case 'file':
                    return this.generateFilePreview(messageData);
                case 'video':
                    return this.generateVideoPreview(messageData);
                case 'text':
                default:
                    return this.generateTextPreview(messageData);
            }
        },

        /**
         * 生成圖片預覽
         * @param {object} messageData - 訊息資料
         * @returns {string} 圖片預覽 HTML
         */
        generateImagePreview: function (messageData) {
            const imageUrl = messageData.preview_url || messageData.image_url || messageData.content;

            if (imageUrl && this.isValidImageUrl(imageUrl)) {
                return `
                    <div class="related-message-image">
                        <img src="${this.escapeHtml(imageUrl)}" alt="圖片預覽" />
                    </div>
                `;
            }

            return '<div class="related-message-image"><span class="image-label">📷 圖片</span></div>';
        },

        /**
         * 生成貼圖預覽
         * @param {object} messageData - 訊息資料
         * @returns {string} 貼圖預覽 HTML
         */
        generateStickerPreview: function (messageData) {
            const stickerUrl = messageData.sticker_url || messageData.content;

            if (stickerUrl && this.isValidImageUrl(stickerUrl)) {
                return `
                    <div class="related-message-sticker">
                        <img src="${this.escapeHtml(stickerUrl)}" alt="貼圖預覽" />
                        <span class="sticker-label">🎭 貼圖</span>
                    </div>
                `;
            }

            return '<div class="related-message-sticker"><span class="sticker-label">🎭 貼圖</span></div>';
        },

        /**
         * 生成檔案預覽
         * @param {object} messageData - 訊息資料
         * @returns {string} 檔案預覽 HTML
         */
        generateFilePreview: function (messageData) {
            const fileName = messageData.file_name || '未知檔案';
            const fileUrl = messageData.file_url || messageData.content;

            const fileInfo = `
                <div class="file-info">
                    <span class="file-icon">📁</span>
                    <span class="file-name">${this.escapeHtml(fileName)}</span>
                </div>
            `;

            const downloadLink = fileUrl ?
                `<a href="${this.escapeHtml(fileUrl)}" target="_blank" class="related-message-download">
                    <span class="dashicons dashicons-download"></span>
                    下載檔案
                </a>` : '';

            return `
                <div class="related-message-file">
                    ${fileInfo}
                    ${downloadLink}
                </div>
            `;
        },

        /**
         * 生成影片預覽
         * @param {object} messageData - 訊息資料
         * @returns {string} 影片預覽 HTML
         */
        generateVideoPreview: function (messageData) {
            const fileName = messageData.file_name || '影片檔案';
            const videoUrl = messageData.video_url || messageData.file_url || messageData.content;

            const fileInfo = `
                <div class="file-info">
                    <span class="file-icon">🎬</span>
                    <span class="file-name">${this.escapeHtml(fileName)}</span>
                </div>
            `;

            const playLink = videoUrl ?
                `<a href="${this.escapeHtml(videoUrl)}" target="_blank" class="related-message-download">
                    <span class="dashicons dashicons-controls-play"></span>
                    播放影片
                </a>` : '';

            return `
                <div class="related-message-file">
                    ${fileInfo}
                    ${playLink}
                </div>
            `;
        },

        /**
         * 生成文字預覽
         * @param {object} messageData - 訊息資料
         * @returns {string} 文字預覽 HTML
         */
        generateTextPreview: function (messageData) {
            const content = messageData.content || '';
            const truncatedContent = content.length > 150 ? content.substring(0, 150) + '...' : content;
            return `<div class="related-message-text">${this.escapeHtml(truncatedContent)}</div>`;
        },

        /**
         * 檢查是否為有效的圖片 URL
         * @param {string} url - URL 字串
         * @returns {boolean} 是否為有效圖片 URL
         */
        isValidImageUrl: function (url) {
            if (!url || typeof url !== 'string') return false;

            // 檢查是否以 http/https 開頭
            if (!url.match(/^https?:\/\//)) return false;

            // 檢查常見圖片副檔名
            const imageExtensions = /\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i;
            if (imageExtensions.test(url)) return true;

            // 檢查是否包含圖片相關關鍵字
            const imageKeywords = /(image|img|photo|picture|preview)/i;
            return imageKeywords.test(url);
        },

        /**
         * 設定當前好友
         * @param {object} friend - 好友資料
         */
        setCurrentFriend: function (friend) {
            this.currentFriend = friend;
        }
    };

})(jQuery);