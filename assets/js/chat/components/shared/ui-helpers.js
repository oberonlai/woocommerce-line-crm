/**
 * OrderChatz UI 工具模組
 *
 * 提供通用的 UI 工具函數和元件
 *
 * @package OrderChatz
 * @since 1.0.0
 * @version 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * UI 工具類別
     */
    window.UIHelpers = {
        /**
         * HTML 跳脫
         * @param {string} text - 要跳脫的文字
         * @returns {string} 跳脫後的文字
         */
        escapeHtml: function (text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * 顯示載入狀態
         * @param {jQuery} container - 容器元素
         * @param {string} message - 載入訊息
         */
        showLoadingState: function (container, message = '載入中...') {
            // 如果已經有載入提示，不重複添加
            if (container.find('.customer-info-loading').length > 0) {
                return;
            }

            const loadingHtml = `
                <div class="customer-info-loading">
                    <div class="loading-content">
                        <div class="loading-spinner">
                            <div class="spinner-circle"></div>
                        </div>
                        <p class="loading-text">${this.escapeHtml(message)}</p>
                    </div>
                </div>
            `;

            // 檢查是否已有實際內容（非載入狀態）
            const hasRealContent = container.children().length > 0 &&
                container.children().not('.customer-info-loading').length > 0;

            if (hasRealContent) {
                // 如果已有實際內容，不清空，只添加載入提示
                container.prepend(loadingHtml);
            } else {
                // 如果沒有實際內容，直接設置載入狀態
                container.html(loadingHtml);
            }
        },

        /**
         * 隱藏載入狀態
         * @param {jQuery} container - 容器元素
         */
        hideLoadingState: function (container) {
            container.find('.customer-info-loading').remove();
        },

        /**
         * 顯示成功訊息
         * @param {jQuery} container - 容器元素
         * @param {string} message - 成功訊息
         */
        showSuccessMessage: function (container, message) {
            const successMessage = $(`
                <div class="customer-success-message">
                    <span class="dashicons dashicons-yes-alt"></span>
                    ${this.escapeHtml(message)}
                </div>
            `);

            container.prepend(successMessage);

            // 3秒後自動移除
            setTimeout(() => {
                successMessage.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * 顯示錯誤燈箱
         * @param {string} message - 錯誤訊息
         * @param {Function} retryCallback - 重試回調函數
         */
        showErrorModal: function (message, retryCallback = null) {
            const modalHtml = `
                <div class="customer-error-modal" id="customer-error-modal">
                    <div class="modal-backdrop"></div>
                    <div class="modal-container">
                        <div class="modal-header">
                            <h3 class="modal-title">錯誤</h3>
                            <button type="button" class="modal-close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="modal-content">
                            <div class="error-icon">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <p class="error-message">${this.escapeHtml(message)}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button button-secondary modal-close">關閉</button>
                            ${retryCallback ? '<button type="button" class="button button-primary retry-load">重新載入</button>' : ''}
                        </div>
                    </div>
                </div>
            `;

            // 移除已存在的燈箱
            $('#customer-error-modal').remove();

            // 添加燈箱到頁面
            $('body').append(modalHtml);

            const modal = $('#customer-error-modal');

            // 綁定關閉事件
            modal.find('.modal-close').on('click', () => {
                this.hideErrorModal();
            });

            // 綁定重新載入事件
            if (retryCallback) {
                modal.find('.retry-load').on('click', () => {
                    this.hideErrorModal();
                    retryCallback();
                });
            }

            // 點擊背景關閉
            modal.find('.modal-backdrop').on('click', () => {
                this.hideErrorModal();
            });

            // 顯示燈箱
            modal.fadeIn(200);
        },

        /**
         * 隱藏錯誤燈箱
         */
        hideErrorModal: function () {
            $('#customer-error-modal').fadeOut(200, function () {
                $(this).remove();
            });
        },

        /**
         * 初始化展開收合功能
         * @param {jQuery} container - 容器元素
         */
        initCollapsibleSections: function (container) {

            // 綁定展開收合事件
            container.find('.collapsible-header').off('click.collapsible').on('click.collapsible', (e) => {

                // 如果點擊的是拖曳圖示，忽略此事件且不阻止事件
                const target = $(e.target);
                if (target.hasClass('dashicons-menu') || target.closest('.dashicons-menu').length) {
                    return;
                }

                // 只有在非拖曳圖示點擊時才阻止預設行為
                e.preventDefault();
                e.stopPropagation();

                const header = $(e.currentTarget);
                const section = header.data('section');
                const content = container.find(`[data-section="${section}"].collapsible-content`);
                const toggleIcon = header.find('.toggle-icon');


                if (content.is(':visible')) {
                    // 收合
                    content.slideUp(300);
                    toggleIcon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    header.removeClass('expanded');
                } else {
                    // 展開
                    content.slideDown(300);
                    toggleIcon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    header.addClass('expanded');
                }
            });

            // 設定初始狀態 - 預設展開所有區塊
            container.find('.collapsible-content').show();
            container.find('.toggle-icon')
                .removeClass('dashicons-arrow-down-alt2')
                .addClass('dashicons-arrow-up-alt2');
            container.find('.collapsible-header').addClass('expanded');
        },

        /**
         * 儲存區塊排序到 localStorage
         * @param {jQuery} container - 容器元素
         * @param {string} storageKey - 儲存鍵名
         */
        saveSectionOrder: function (container, storageKey) {
            const order = [];
            container.find('.info-section').each(function () {
                const sectionType = $(this).attr('class').match(/customer-(\w+)/);
                if (sectionType) {
                    order.push(sectionType[1]);
                }
            });

            localStorage.setItem(storageKey, JSON.stringify(order));
        },

        /**
         * 載入並應用已儲存的區塊排序
         * @param {jQuery} container - 容器元素
         * @param {string} storageKey - 儲存鍵名
         */
        applySavedSectionOrder: function (container, storageKey) {
            const savedOrder = localStorage.getItem(storageKey);

            if (!savedOrder) {
                return; // 沒有儲存的順序，使用預設
            }

            try {
                const order = JSON.parse(savedOrder);
                const sections = container.find('.info-section').detach();

                // 按照儲存的順序重新排列
                order.forEach(sectionType => {
                    const section = sections.filter(`.customer-${sectionType}`);
                    if (section.length) {
                        container.append(section);
                    }
                });

                // 添加任何不在排序中的新區塊
                sections.each(function () {
                    if (!$(this).parent().length) {
                        container.append($(this));
                    }
                });
                

            } catch (e) {
                console.error('UI: 載入區塊排序失敗', e);
            }
        }
    };

})(jQuery);