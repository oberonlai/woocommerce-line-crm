/**
 * OrderChatz 客戶標籤管理模組
 *
 * 處理客戶標籤的新增、刪除、搜尋功能
 *
 * @package OrderChatz
 * @since 1.0.20
 */

(function ($) {
    'use strict';

    /**
     * 客戶標籤管理器
     */
    window.CustomerTagsManager = function (container) {
        this.container = container;
        this.currentFriend = null;
        this.tagsData = {}; // 儲存完整的標籤資料 { "tagName": { count, times } }.
    };

    CustomerTagsManager.prototype = {
        /**
         * 渲染標籤區塊
         * @param {Array} tags - 標籤資料 [{"tag_name": "VIP", "count": 3, "times": [...]}]
         * @returns {string} HTML 字串
         */
        renderTagsSection: function (tags = []) {
            const template = $('#customer-tags-template').html();
            if (!template) {
                console.error('CustomerTagsManager: 客戶標籤模板未找到');
                return '';
            }

            let tagsHtml = '';

            // 現有標籤顯示
            if (tags.length > 0) {
                tagsHtml += '<div class="tagsdiv">';
                tagsHtml += '<div class="tagchecklist">';
                tags.forEach(tag => {
                    // 支援舊格式 (字串) 和新格式 (物件).
                    let tagName, tagCount, tagTimes;
                    if (typeof tag === 'string') {
                        tagName = tag;
                        tagCount = 1;
                        tagTimes = [];
                    } else {
                        tagName = tag.tag_name || tag.name || tag;
                        tagCount = tag.count || 1;
                        tagTimes = tag.times || [];
                    }

                    // 儲存完整標籤資料供 tooltip 使用.
                    this.tagsData[tagName] = {
                        count: tagCount,
                        times: tagTimes
                    };

                    // 顯示格式: 標籤名稱 ×次數 (次數大於1時才顯示).
                    const displayText = tagCount > 1
                        ? `${this.escapeHtml(tagName)} <span style="font-size:11px;">x ${tagCount}</span>`
                        : this.escapeHtml(tagName);

                    tagsHtml += `<span class="customer-tag" data-tag-name="${this.escapeHtml(tagName)}">`;
                    tagsHtml += `${displayText}`;
                    tagsHtml += `</span>`;
                });
                tagsHtml += '</div>';
                tagsHtml += '</div>';
            }

            // 新增標籤區域
            tagsHtml += `
                <div class="ajaxtag">
                    <label class="screen-reader-text" for="new-tag-customer_tag">新增標籤</label>
                    <select id="new-tag-customer_tag" name="newtag[customer_tag]" class="newtag-select" multiple="multiple" style="width: 100%;">
                    </select>
                </div>
                <p class="howto">搜尋並選擇既有標籤，或輸入新標籤名稱</p>
                <div class="tags-message"></div>
            `;

            return template.replace(/\{tagsHtml\}/g, tagsHtml);
        },

        /**
         * 初始化標籤功能
         */
        init: function () {
            // 先移除舊的事件綁定，避免重複綁定.
            this.container.off('.customer-tags-manager');

            // 初始化標籤 Select2.
            this.initTagsSelect2();

            // 點擊標籤顯示 tooltip.
            this.container.on('click.customer-tags-manager', '.customer-tag', (e) => {
                e.preventDefault();
                const tagName = $(e.currentTarget).data('tag-name');
                this.showTagTooltip(tagName, e.currentTarget);
            });
        },

        /**
         * 初始化標籤 Select2
         */
        initTagsSelect2: function () {
            const tagSelect = this.container.find('#new-tag-customer_tag');

            if (tagSelect.length === 0) {
                return;
            }

            let isComposing = false;

            // Select2 配置
            const selectConfig = {
                placeholder: '搜尋或新增標籤...',
                allowClear: true,
                tokenSeparators: [','], // 支援逗號分隔
                ajax: {
                    url: otzChatConfig.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: (params) => {
                        return {
                            action: 'otz_search_customer_tags',
                            nonce: otzChatConfig.nonce,
                            search: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: (data, params) => {
                        if (isComposing) {
                            return {results: []};
                        }
                        if (!data.success) {
                            return {results: []};
                        }

                        const results = data.data.results || [];
                        const searchTerm = params.term ? params.term.trim() : '';

                        // 檢查搜尋詞是否已存在於結果中.
                        if (searchTerm) {
                            const exactMatch = results.some(item => item.text === searchTerm);

                            // 如果搜尋詞不存在,在結果最前面加入新標籤選項.
                            if (!exactMatch) {
                                results.unshift({
                                    id: searchTerm,
                                    text: searchTerm + ' (新增)',
                                    isNew: true
                                });
                            }
                        }

                        return {
                            results: results,
                            pagination: data.data.pagination || {more: false}
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0,
                escapeMarkup: (markup) => markup, // 直接返回，不轉義
                templateResult: (tag) => {
                    if (tag.loading) return tag.text;
                    return `<div>${tag.text}</div>`;
                },
                templateSelection: (tag) => tag.text || tag.id
            };

            // 優先使用 selectWoo，fallback 到 select2
            if (typeof $.fn.selectWoo !== 'undefined') {
                tagSelect.selectWoo(selectConfig);
            } else if (typeof $.fn.select2 !== 'undefined') {
                tagSelect.select2(selectConfig);
            }

            tagSelect.on('select2:open', function () {
                const $search = $('.select2-search__field');
                $search.on('compositionstart', () => isComposing = true);
                $search.on('compositionend', () => isComposing = false);
            });

            // 監聽選擇變化事件
            tagSelect.on('change', () => {
                const selectedValues = tagSelect.val();
                if (selectedValues && selectedValues.length > 0) {
                    this.handleTagSelection(selectedValues);
                }
            });
        },

        /**
         * 處理標籤選擇
         * @param {Array} selectedValues - 選中的標籤值
         */
        handleTagSelection: function (selectedValues) {
            const tagSelect = this.container.find('#new-tag-customer_tag');

            // 新增選中的標籤
            selectedValues.forEach(tagName => {
                if (tagName && tagName.trim()) {
                    this.addTag(tagName.trim());
                }
            });

            // 清空選擇
            tagSelect.val(null).trigger('change');
        },

        /**
         * 新增標籤
         * @param {string} tagName - 標籤名稱
         */
        addTag: function (tagName) {
            if (!this.currentFriend) {
                console.error('CustomerTagsManager: 未設定當前好友');
                return;
            }

            // 取得正確的 LINE User ID
            const lineUserId = this.currentFriend.line_user_id || this.currentFriend.id;

            if (!lineUserId) {
                console.error('CustomerTagsManager: 無法取得 LINE User ID', this.currentFriend);
                this.showMessage('無法識別用戶，請重新選擇好友', 'error');
                return;
            }

            const data = {
                action: 'otz_add_customer_tag',
                nonce: otzChatConfig.tags_nonce,
                line_user_id: lineUserId,
                tag_name: tagName
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 直接在前端更新標籤顯示,傳入時間戳記.
                        this.addTagToDisplay(response.data.tag, response.data.tagged_at);
                        this.showMessage('標籤已新增', 'success');

                        // 觸發標籤更新事件.
                        $(document).trigger('customer:tags-updated', [this.currentFriend.id]);
                    } else {
                        const errorMsg = response.data?.message || '未知錯誤';
                        this.showMessage('新增標籤失敗：' + errorMsg, 'error');
                    }
                },
                error: () => {
                    this.showMessage('新增標籤時發生網路錯誤', 'error');
                }
            });
        },

        /**
         * 移除標籤
         * @param {string} tagName - 標籤名稱
         */
        removeTag: function (tagName) {
            if (!this.currentFriend) {
                console.error('CustomerTagsManager: 未設定當前好友');
                return;
            }

            // 取得正確的 LINE User ID
            const lineUserId = this.currentFriend.line_user_id || this.currentFriend.id;

            if (!lineUserId) {
                console.error('CustomerTagsManager: 無法取得 LINE User ID', this.currentFriend);
                this.showMessage('無法識別用戶，請重新選擇好友', 'error');
                return;
            }

            const data = {
                action: 'otz_remove_customer_tag',
                nonce: otzChatConfig.tags_nonce,
                line_user_id: lineUserId,
                tag_name: tagName
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 直接在前端移除標籤顯示
                        this.removeTagFromDisplay(tagName);
                        this.showMessage('標籤已移除', 'success');

                        // 觸發標籤更新事件
                        $(document).trigger('customer:tags-updated', [this.currentFriend.id]);
                    } else {
                        const errorMsg = response.data?.message || '未知錯誤';
                        this.showMessage('移除標籤失敗：' + errorMsg, 'error');
                    }
                },
                error: () => {
                    this.showMessage('移除標籤時發生網路錯誤', 'error');
                }
            });
        },

        /**
         * 動態新增標籤到顯示區域
         * @param {string} tagName - 標籤名稱
         * @param {string} taggedAt - 貼標時間 (可選,從後端取得)
         */
        addTagToDisplay: function (tagName, taggedAt) {
            if (!tagName || typeof tagName !== 'string') {
                console.error('CustomerTagsManager: 無效的標籤資料', tagName);
                return;
            }

            // 如果沒有傳入時間,使用當前時間.
            if (!taggedAt) {
                taggedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
            }

            // 檢查標籤是否已存在,如果存在則需要更新次數.
            const existingTagButton = this.container.find(`[data-tag-name="${tagName}"]`);
            if (existingTagButton.length > 0) {
                // 標籤已存在,需要更新顯示的次數和時間記錄.

                // 更新 tagsData 中的資料.
                if (this.tagsData[tagName]) {
                    this.tagsData[tagName].count++;
                    // 加入新的時間記錄.
                    this.tagsData[tagName].times.push(taggedAt);
                } else {
                    // 如果 tagsData 中沒有,初始化它.
                    this.tagsData[tagName] = {
                        count: 2,
                        times: [taggedAt]
                    };
                }

                // 更新顯示文字.
                const displayText = this.tagsData[tagName].count > 1
                    ? `${this.escapeHtml(tagName)} ×${this.tagsData[tagName].count}`
                    : this.escapeHtml(tagName);

                const tagSpan = this.container.find(`.customer-tag[data-tag-name="${tagName}"]`);
                tagSpan.html(displayText);
                return;
            }

            // 第一次新增標籤,初始化 tagsData.
            this.tagsData[tagName] = {
                count: 1,
                times: [taggedAt]
            };

            // 確保標籤容器存在.
            let tagsList = this.container.find('.tagchecklist');
            if (tagsList.length === 0) {
                // 如果沒有標籤容器，創建完整的標籤區域結構.
                const tagsContent = this.container.find('.tags-content');
                if (tagsContent.length > 0) {
                    tagsContent.prepend(`
                        <div class="tagsdiv" style="display: block;">
                            <div class="tagchecklist"></div>
                        </div>
                    `);
                    tagsList = tagsContent.find('.tagchecklist');
                }
            } else {
                // 確保標籤容器可見.
                tagsList.closest('.tagsdiv').show();
            }

            // 創建新標籤 HTML (新標籤不顯示次數).
            const newTagHtml = `
                <span class="customer-tag" data-tag-name="${this.escapeHtml(tagName)}">
                    ${this.escapeHtml(tagName)}
                </span>
            `;

            // 添加到標籤列表.
            tagsList.append(newTagHtml);
        },

        /**
         * 從顯示區域移除標籤
         * @param {string} tagName - 標籤名稱
         */
        removeTagFromDisplay: function (tagName) {
            const tagElement = this.container.find(`[data-tag-name="${tagName}"]`).closest('.customer-tag');
            if (tagElement.length > 0) {
                tagElement.fadeOut(300, () => {
                    tagElement.remove();

                    // 檢查是否還有其他標籤
                    const remainingTags = this.container.find('.tagchecklist .customer-tag');
                    if (remainingTags.length === 0) {
                        // 如果沒有標籤了，隱藏標籤容器
                        this.container.find('.tagsdiv').hide();
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
            const messageContainer = this.container.find('.tags-message');
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
         * 顯示標籤 Tooltip
         * @param {string} tagName - 標籤名稱
         * @param {Element} targetElement - 目標元素
         */
        showTagTooltip: function (tagName, targetElement) {
            // 關閉已存在的 tooltip.
            this.hideTagTooltip();

            const tagData = this.tagsData[tagName];
            if (!tagData || !tagData.times || tagData.times.length === 0) {
                return;
            }

            // 創建 tooltip HTML.
            let tooltipHtml = '<div class="tag-tooltip" id="tag-tooltip">';
            tooltipHtml += '<div class="tag-tooltip-header">';
            tooltipHtml += `<span class="tag-tooltip-title">${this.escapeHtml(tagName)} 的貼標記錄</span>`;
            tooltipHtml += '<button type="button" class="tag-tooltip-close">×</button>';
            tooltipHtml += '</div>';
            tooltipHtml += '<div class="tag-tooltip-times">';

            tagData.times.forEach(time => {
                tooltipHtml += '<div class="tag-time-item">';
                tooltipHtml += `<span class="tag-time">${this.escapeHtml(time)}</span>`;
                tooltipHtml += `<button type="button" class="tag-time-delete" data-tag-name="${this.escapeHtml(tagName)}" data-tagged-at="${this.escapeHtml(time)}">`;
                tooltipHtml += '<span class="dashicons dashicons-trash"></span>';
                tooltipHtml += '</button>';
                tooltipHtml += '</div>';
            });

            tooltipHtml += '</div>';
            tooltipHtml += '</div>';

            // 添加到 DOM.
            $('body').append(tooltipHtml);

            const $tooltip = $('#tag-tooltip');
            const $target = $(targetElement);

            // 計算位置 (一律顯示在標籤上方).
            const targetOffset = $target.offset();
            const tooltipWidth = $tooltip.outerWidth();
            const tooltipHeight = $tooltip.outerHeight();

            // 顯示在上方.
            let top = targetOffset.top - tooltipHeight - 5;
            let left = targetOffset.left;

            // 如果超出視窗頂部,則往下移動.
            if (top < $(window).scrollTop()) {
                top = $(window).scrollTop() + 10;
            }

            // 如果超出視窗右側,則向左對齊.
            if (left + tooltipWidth > $(window).width()) {
                left = $(window).width() - tooltipWidth - 10;
            }

            // 如果超出視窗左側,則向右對齊.
            if (left < 0) {
                left = 10;
            }

            $tooltip.css({
                top: top + 'px',
                left: left + 'px'
            });

            // 綁定關閉按鈕.
            $tooltip.find('.tag-tooltip-close').on('click', () => {
                this.hideTagTooltip();
            });

            // 綁定刪除按鈕.
            $tooltip.find('.tag-time-delete').on('click', (e) => {
                const $btn = $(e.currentTarget);
                const tagName = $btn.data('tag-name');
                const taggedAt = $btn.data('tagged-at');
                this.removeTagByTime(tagName, taggedAt);
            });

            // 點擊外部關閉.
            setTimeout(() => {
                $(document).on('click.tag-tooltip', (e) => {
                    if (!$(e.target).closest('.tag-tooltip, .customer-tag').length) {
                        this.hideTagTooltip();
                    }
                });
            }, 100);
        },

        /**
         * 隱藏標籤 Tooltip
         */
        hideTagTooltip: function () {
            $('#tag-tooltip').remove();
            $(document).off('click.tag-tooltip');
        },

        /**
         * 根據時間刪除標籤
         * @param {string} tagName - 標籤名稱
         * @param {string} taggedAt - 貼標時間
         */
        removeTagByTime: function (tagName, taggedAt) {
            if (!this.currentFriend) {
                console.error('CustomerTagsManager: 未設定當前好友');
                return;
            }

            // 取得正確的 LINE User ID.
            const lineUserId = this.currentFriend.line_user_id || this.currentFriend.id;

            if (!lineUserId) {
                console.error('CustomerTagsManager: 無法取得 LINE User ID', this.currentFriend);
                this.showMessage('無法識別用戶，請重新選擇好友', 'error');
                return;
            }

            if (!confirm(`確定要刪除此標籤記錄嗎?\n標籤: ${tagName}\n時間: ${taggedAt}`)) {
                return;
            }

            const data = {
                action: 'otz_remove_customer_tag_by_time',
                nonce: otzChatConfig.tags_nonce,
                line_user_id: lineUserId,
                tag_name: tagName,
                tagged_at: taggedAt
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // 從 tagsData 中移除該時間記錄.
                        if (this.tagsData[tagName] && this.tagsData[tagName].times) {
                            const timeIndex = this.tagsData[tagName].times.indexOf(taggedAt);
                            if (timeIndex > -1) {
                                this.tagsData[tagName].times.splice(timeIndex, 1);
                                this.tagsData[tagName].count--;
                            }

                            // 如果沒有記錄了,從顯示中移除標籤.
                            if (this.tagsData[tagName].times.length === 0) {
                                this.removeTagFromDisplay(tagName);
                                delete this.tagsData[tagName];
                                this.hideTagTooltip();
                            } else {
                                // 更新標籤顯示的次數.
                                this.updateTagDisplay(tagName);
                                // 更新 tooltip 顯示.
                                const $tagSpan = this.container.find(`.customer-tag[data-tag-name="${tagName}"]`);
                                if ($tagSpan.length > 0) {
                                    this.showTagTooltip(tagName, $tagSpan[0]);
                                }
                            }
                        }

                        this.showMessage('標籤記錄已刪除', 'success');

                        // 觸發標籤更新事件.
                        $(document).trigger('customer:tags-updated', [this.currentFriend.id]);
                    } else {
                        const errorMsg = response.data?.message || '未知錯誤';
                        this.showMessage('刪除標籤記錄失敗：' + errorMsg, 'error');
                    }
                },
                error: () => {
                    this.showMessage('刪除標籤記錄時發生網路錯誤', 'error');
                }
            });
        },

        /**
         * 更新標籤顯示的次數
         * @param {string} tagName - 標籤名稱
         */
        updateTagDisplay: function (tagName) {
            const tagData = this.tagsData[tagName];
            if (!tagData) return;

            const $tagSpan = this.container.find(`.customer-tag[data-tag-name="${tagName}"]`);
            if ($tagSpan.length === 0) return;

            const displayText = tagData.count > 1
                ? `${this.escapeHtml(tagName)} ×${tagData.count}`
                : this.escapeHtml(tagName);

            $tagSpan.html(displayText);
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