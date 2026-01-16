/**
 * OrderChatz Admin Menu JavaScript
 *
 * 處理管理介面的互動功能，包含頁籤切換、載入狀態等
 *
 * @package OrderChatz
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * OrderChatz 管理介面主要類別
     */
    class OrderChatzAdmin {

        /**
         * 建構函式
         */
        constructor() {
            this.currentPage = this.getCurrentPage();
            this.tabNavigation = null;
            this.loadingStates = new Map();

            this.init();
        }

        /**
         * 初始化
         */
        init() {
            try {
                this.initTabNavigation();
                this.bindEvents();
                this.initializeCurrentTab();
                this.handleUrlChange();


            } catch (error) {
                console.error('OrderChatz Admin initialization failed:', error);
                this.showErrorMessage('管理介面初始化失敗');
            }
        }

        /**
         * 初始化頁籤導航
         */
        initTabNavigation() {
            this.tabNavigation = new TabNavigation();
        }

        /**
         * 綁定事件
         */
        bindEvents() {
            // 頁籤點擊事件
            $(document).on('click', '.nav-tab-wrapper .nav-tab', this.handleTabClick.bind(this));

            // 視窗大小改變事件
            $(window).on('resize', this.handleResize.bind(this));

            // 瀏覽器返回/前進事件
            $(window).on('popstate', this.handlePopState.bind(this));

            // 表單提交事件
            $(document).on('submit', '.orderchatz-admin-page form', this.handleFormSubmit.bind(this));
        }

        /**
         * 處理頁籤點擊
         */
        handleTabClick(event) {
            event.preventDefault();

            const $tab = $(event.target);
            const targetPage = $tab.data('tab');

            if (!targetPage || $tab.hasClass('nav-tab-active')) {
                return;
            }

            this.switchToTab(targetPage, $tab.attr('href'));
        }

        /**
         * 切換頁籤
         */
        switchToTab(targetPage, url = null) {
            try {
                // 更新 active 狀態
                this.updateActiveTab(targetPage);

                // 更新 URL
                if (url) {
                    this.updateURL(url);
                }

                // 觸發頁籤切換事件
                $(document).trigger('orderchatz:tab-switched', {
                    previousPage: this.currentPage,
                    currentPage: targetPage
                });

                this.currentPage = targetPage;

            } catch (error) {
                console.error('Tab switching failed:', error);
                this.resetToDefaultTab();
            }
        }

        /**
         * 更新 active 頁籤
         */
        updateActiveTab(targetPage) {
            const $tabs = $('.nav-tab-wrapper .nav-tab');

            $tabs.removeClass('nav-tab-active');
            $tabs.filter(`[data-tab="${targetPage}"]`).addClass('nav-tab-active');
        }

        /**
         * 更新 URL
         */
        updateURL(url) {
            if (history.pushState) {
                history.pushState(null, null, url);
            }
        }

        /**
         * 初始化當前頁籤
         */
        initializeCurrentTab() {
            const currentPage = this.getCurrentPage();
            if (currentPage) {
                this.updateActiveTab(currentPage);
            }
        }

        /**
         * 取得當前頁面
         */
        getCurrentPage() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('page') || 'chat';
        }

        /**
         * 處理 URL 改變
         */
        handleUrlChange() {
            // 監聽 URL 參數變化
            if (window.URLSearchParams) {
                const currentPage = this.getCurrentPage();
                if (currentPage !== this.currentPage) {
                    this.updateActiveTab(currentPage);
                    this.currentPage = currentPage;
                }
            }
        }

        /**
         * 處理瀏覽器返回/前進
         */
        handlePopState(event) {
            const currentPage = this.getCurrentPage();
            this.updateActiveTab(currentPage);
            this.currentPage = currentPage;
        }

        /**
         * 處理視窗大小改變
         */
        handleResize() {
            // 響應式處理邏輯
            this.adjustLayoutForMobile();
        }

        /**
         * 調整手機版佈局
         */
        adjustLayoutForMobile() {
            const isMobile = $(window).width() <= 782;
            $('.orderchatz-admin-page').toggleClass('is-mobile', isMobile);
        }

        /**
         * 處理表單提交
         */
        handleFormSubmit(event) {
            const $form = $(event.target);
            const $submitButton = $form.find('[type="submit"]');

            // 顯示載入狀態
            this.showLoadingState($submitButton);

            // 3 秒後隱藏載入狀態 (實際應該在 AJAX 完成後)
            setTimeout(() => {
                this.hideLoadingState($submitButton);
            }, 3000);
        }

        /**
         * 顯示載入狀態
         */
        showLoadingState($element) {
            const elementId = $element.attr('id') || 'element-' + Date.now();

            if (!$element.attr('id')) {
                $element.attr('id', elementId);
            }

            const originalText = $element.text();
            this.loadingStates.set(elementId, originalText);

            $element
                .prop('disabled', true)
                .text('處理中...')
                .addClass('updating-message');
        }

        /**
         * 隱藏載入狀態
         */
        hideLoadingState($element) {
            const elementId = $element.attr('id');
            const originalText = this.loadingStates.get(elementId) || $element.text();

            $element
                .prop('disabled', false)
                .text(originalText)
                .removeClass('updating-message');

            this.loadingStates.delete(elementId);
        }

        /**
         * 重設為預設頁籤
         */
        resetToDefaultTab() {
            const defaultTab = 'chat';
            this.updateActiveTab(defaultTab);
            this.currentPage = defaultTab;
        }

        /**
         * 顯示錯誤訊息
         */
        showErrorMessage(message) {
            const $notice = $(`
                <div class="notice notice-error is-dismissible">
                    <p><strong>OrderChatz:</strong> ${message}</p>
                </div>
            `);

            $('.orderchatz-admin-page').prepend($notice);

            // 3 秒後自動隱藏
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 3000);
        }

        /**
         * 顯示成功訊息
         */
        showSuccessMessage(message) {
            const $notice = $(`
                <div class="notice notice-success is-dismissible">
                    <p><strong>OrderChatz:</strong> ${message}</p>
                </div>
            `);

            $('.orderchatz-admin-page').prepend($notice);

            // 3 秒後自動隱藏
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 3000);
        }
    }

    /**
     * 頁籤導航類別
     */
    class TabNavigation {

        constructor() {
            this.activeClass = 'nav-tab-active';
            this.tabSelector = '.nav-tab-wrapper .nav-tab';
        }

        /**
         * 切換頁籤
         */
        switchTab(targetTab) {
            try {
                this.updateActiveTab(targetTab);
                this.updateURL(targetTab);
            } catch (error) {
                console.error('Tab switching failed:', error);
                this.resetToDefaultTab();
            }
        }

        /**
         * 更新 active 頁籤
         */
        updateActiveTab(targetTab) {
            const $tabs = $(this.tabSelector);

            $tabs.removeClass(this.activeClass);
            $tabs.filter(`[data-tab="${targetTab}"]`).addClass(this.activeClass);
        }

        /**
         * 更新 URL
         */
        updateURL(targetTab) {
            const url = this.buildTabURL(targetTab);
            if (history.pushState && url) {
                history.pushState(null, null, url);
            }
        }

        /**
         * 建立頁籤 URL
         */
        buildTabURL(targetTab) {
            const baseUrl = window.location.origin + window.location.pathname;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', targetTab);

            return `${baseUrl}?${urlParams.toString()}`;
        }

        /**
         * 重設為預設頁籤
         */
        resetToDefaultTab() {
            this.updateActiveTab('chat');
        }
    }

    /**
     * 工具函式
     */
    const Utils = {

        /**
         * 防抖函式
         */
        debounce(func, wait, immediate) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    timeout = null;
                    if (!immediate) func.apply(this, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(this, args);
            };
        },

        /**
         * 節流函式
         */
        throttle(func, limit) {
            let inThrottle;
            return function (...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * 檢查是否為手機裝置
         */
        isMobile() {
            return $(window).width() <= 782;
        }
    };

    /**
     * 當 DOM 準備完成時初始化
     */
    $(document).ready(function () {
        // 確保只在 OrderChatz 頁面執行
        if ($('.orderchatz-admin-page').length > 0) {
            window.OrderChatzAdmin = new OrderChatzAdmin();
        }
    });

    /**
     * 暴露到全域作用域
     */
    window.OrderChatzUtils = Utils;

})(jQuery);