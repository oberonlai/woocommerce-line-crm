/**
 * OrderChatz 響應式處理器
 *
 * 管理介面的響應式布局切換，包含桌面版、平板版、手機版的佈局管理
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 響應式處理器
     * 管理介面的響應式布局切換
     */
    window.ResponsiveHandler = function () {
        this.currentLayout = 'desktop';
        this.breakpoints = {
            mobile: 767,
            tablet: 1023
        };
        this.mobileToggles = {
            friends: $('#toggle-friends'),
            customerInfo: $('#toggle-customer-info')
        };
        this.panels = {
            friends: $('#friend-list-panel'),
            customerInfo: $('#customer-info-panel')
        };
        this.overlay = $('#mobile-overlay');

        // 狀態記憶
        this.panelStates = {
            friendsPanelOpen: false,
            customerInfoPanelOpen: false,
            lastSelectedPanel: null
        };

        // 觸控支援
        this.touchSupport = {
            startX: 0,
            startY: 0,
            endX: 0,
            endY: 0,
            isTouch: false,
            minSwipeDistance: 100
        };

        // 動畫配置
        this.animationConfig = {
            panelSlide: 300,
            fade: 200,
            bounce: 150
        };

        this.init();
    };

    ResponsiveHandler.prototype = {
        /**
         * 初始化響應式處理器
         */
        init: function () {
            this.bindEvents();
            this.detectScreenSize();
        },

        /**
         * 綁定事件監聽器
         */
        bindEvents: function () {
            $(window).on('resize', this.throttle(this.handleResize.bind(this), 250));
            $(window).on('orientationchange', this.handleOrientationChange.bind(this));

            // 手機版切換按鈕
            this.mobileToggles.friends.on('click', this.toggleFriendsPanel.bind(this));
            this.mobileToggles.customerInfo.on('click', this.toggleCustomerInfoPanel.bind(this));

            // 遮罩點擊關閉
            this.overlay.on('click', this.closeMobilePanels.bind(this));

            // ESC 鍵關閉面板
            $(document).on('keydown', this.handleKeydown.bind(this));

            // 觸控手勢支援
            this.bindTouchEvents();

            // 媒體查詢變化監聽
            this.bindMediaQueryListeners();

            // 視窗可見性變化
            $(document).on('visibilitychange', this.handleVisibilityChange.bind(this));
        },

        /**
         * 檢測螢幕尺寸
         */
        detectScreenSize: function () {
            const width = $(window).width();
            let newLayout;

            if (width <= this.breakpoints.mobile) {
                newLayout = 'mobile';
            } else if (width <= this.breakpoints.tablet) {
                newLayout = 'tablet';
            } else {
                newLayout = 'desktop';
            }

            if (newLayout !== this.currentLayout) {
                this.currentLayout = newLayout;
                this.applyLayout();
                $(document).trigger('screen:resize', [newLayout]);
            }
        },

        /**
         * 應用布局
         */
        applyLayout: function () {
            // 清除之前的布局類別
            $('body').removeClass('layout-mobile layout-tablet layout-desktop layout-landscape layout-small-screen')
                .addClass('layout-' + this.currentLayout);

            if (this.currentLayout === 'mobile') {
                this.switchToMobileLayout();
            } else {
                this.switchToDesktopLayout();
            }

            // 執行智能佈局調整
            this.performIntelligentLayout();

            // 效能優化處理
            this.performanceOptimization();
        },

        /**
         * 切換至手機布局
         */
        switchToMobileLayout: function () {
            // 隱藏側邊面板
            this.panels.friends.addClass('mobile-hidden');
            this.panels.customerInfo.addClass('mobile-hidden');

            // 顯示切換按鈕
            $('#mobile-toggle-buttons').show();

            // 關閉任何開啟的面板
            this.closeMobilePanels();
        },

        /**
         * 切換至桌面布局
         */
        switchToDesktopLayout: function () {
            // 顯示側邊面板
            this.panels.friends.removeClass('mobile-hidden mobile-active');
            this.panels.customerInfo.removeClass('mobile-hidden mobile-active');

            // 隱藏切換按鈕和遮罩
            $('#mobile-toggle-buttons').hide();
            this.overlay.removeClass('active');
        },

        /**
         * 切換好友列表面板
         */
        toggleFriendsPanel: function () {
            if (this.currentLayout !== 'mobile') return;

            const isActive = this.panels.friends.hasClass('mobile-active');

            this.closeMobilePanels();

            if (!isActive) {
                this.openPanelWithAnimation(this.panels.friends, () => {
                    this.panelStates.friendsPanelOpen = true;
                    this.panelStates.lastSelectedPanel = 'friends';
                    this.updateToggleButtonState('friends', true);
                    this.overlay.addClass('active');

                    // 觸發自定義事件
                    $(document).trigger('panel:opened', ['friends']);
                });
            }
        },

        /**
         * 切換客戶資訊面板
         */
        toggleCustomerInfoPanel: function () {
            if (this.currentLayout !== 'mobile') return;

            const isActive = this.panels.customerInfo.hasClass('mobile-active');

            this.closeMobilePanels();

            if (!isActive) {
                this.openPanelWithAnimation(this.panels.customerInfo, () => {
                    this.panelStates.customerInfoPanelOpen = true;
                    this.panelStates.lastSelectedPanel = 'customerInfo';
                    this.updateToggleButtonState('customerInfo', true);
                    this.overlay.addClass('active');

                    // 觸發自定義事件
                    $(document).trigger('panel:opened', ['customerInfo']);
                });
            }
        },

        /**
         * 關閉手機版面板
         */
        closeMobilePanels: function () {
            this.closePanelWithAnimation(this.panels.friends, () => {
                this.panelStates.friendsPanelOpen = false;
                this.updateToggleButtonState('friends', false);
            });

            this.closePanelWithAnimation(this.panels.customerInfo, () => {
                this.panelStates.customerInfoPanelOpen = false;
                this.updateToggleButtonState('customerInfo', false);
            });

            this.overlay.removeClass('active');

            // 觸發自定義事件
            $(document).trigger('panel:closed');
        },

        /**
         * 帶動畫開啟面板
         * @param {jQuery} panel - 面板元素
         * @param {Function} callback - 完成回調
         */
        openPanelWithAnimation: function (panel, callback) {
            panel.addClass('mobile-active');

            // 添加彈跳動畫效果
            if (typeof callback === 'function') {
                setTimeout(callback, this.animationConfig.bounce);
            }
        },

        /**
         * 帶動畫關閉面板
         * @param {jQuery} panel - 面板元素
         * @param {Function} callback - 完成回調
         */
        closePanelWithAnimation: function (panel, callback) {
            if (panel.hasClass('mobile-active')) {
                panel.removeClass('mobile-active');

                if (typeof callback === 'function') {
                    setTimeout(callback, this.animationConfig.panelSlide);
                }
            } else if (typeof callback === 'function') {
                callback();
            }
        },

        /**
         * 自動佈局調整（智能佈局）
         */
        performIntelligentLayout: function () {
            const screenWidth = $(window).width();
            const screenHeight = $(window).height();
            const aspectRatio = screenWidth / screenHeight;

            // 根據螢幕比例和大小進行智能調整
            if (this.currentLayout === 'mobile') {
                // 橫向模式特殊處理
                if (aspectRatio > 1.5 && screenHeight < 500) {
                    this.applyLandscapeOptimizations();
                }

                // 小螢幕特殊處理
                if (screenWidth < 400) {
                    this.applySmallScreenOptimizations();
                }
            }
        },

        /**
         * 應用橫向優化
         */
        applyLandscapeOptimizations: function () {
            $('body').addClass('layout-landscape');

            // 調整面板高度
            this.panels.friends.css('height', '100vh');
            this.panels.customerInfo.css('height', '100vh');
        },

        /**
         * 應用小螢幕優化
         */
        applySmallScreenOptimizations: function () {
            $('body').addClass('layout-small-screen');

            // 增加面板寬度占比
            this.panels.friends.css('width', '100%');
            this.panels.customerInfo.css('width', '100%');
        },

        /**
         * 恢復布局狀態
         */
        restorePanelStates: function () {
            if (this.currentLayout === 'mobile' && this.panelStates.lastSelectedPanel) {
                setTimeout(() => {
                    if (this.panelStates.lastSelectedPanel === 'friends') {
                        this.toggleFriendsPanel();
                    } else if (this.panelStates.lastSelectedPanel === 'customerInfo') {
                        this.toggleCustomerInfoPanel();
                    }
                }, 200);
            }
        },

        /**
         * 效能優化處理
         */
        performanceOptimization: function () {
            // 在非活動狀態下暫停動畫
            if (document.hidden) {
                $('body').addClass('animations-paused');
            } else {
                $('body').removeClass('animations-paused');
            }

            // GPU 加速優化
            if (this.currentLayout === 'mobile') {
                this.panels.friends.css('will-change', 'transform');
                this.panels.customerInfo.css('will-change', 'transform');
            } else {
                this.panels.friends.css('will-change', 'auto');
                this.panels.customerInfo.css('will-change', 'auto');
            }
        },

        /**
         * 更新切換按鈕狀態
         * @param {string} panel - 面板名稱
         * @param {boolean} active - 是否啟用
         */
        updateToggleButtonState: function (panel, active) {
            const button = this.mobileToggles[panel];
            if (button && button.length) {
                button.toggleClass('active', active);
            }
        },

        /**
         * 處理視窗大小變化
         */
        handleResize: function () {
            this.detectScreenSize();
        },

        /**
         * 處理裝置旋轉
         */
        handleOrientationChange: function () {
            setTimeout(() => {
                this.detectScreenSize();
            }, 100);
        },

        /**
         * 處理鍵盤事件
         * @param {Event} event - 鍵盤事件
         */
        handleKeydown: function (event) {
            if (event.keyCode === 27) { // ESC
                this.closeMobilePanels();
            }
        },

        /**
         * 綁定觸控事件
         */
        bindTouchEvents: function () {
            const chatContainer = $('#chat-container');

            if (chatContainer.length && 'ontouchstart' in window) {
                chatContainer[0].addEventListener('touchstart', this.handleTouchStart.bind(this), {passive: true});
                chatContainer[0].addEventListener('touchmove', this.handleTouchMove.bind(this), {passive: true});
                chatContainer[0].addEventListener('touchend', this.handleTouchEnd.bind(this), {passive: true});
            }

            // 面板上的觸控事件
            this.panels.friends[0].addEventListener('touchstart', this.handlePanelTouchStart.bind(this), {passive: true});
            this.panels.customerInfo[0].addEventListener('touchstart', this.handlePanelTouchStart.bind(this), {passive: true});
        },

        /**
         * 處理觸控開始
         * @param {TouchEvent} event - 觸控事件
         */
        handleTouchStart: function (event) {
            if (this.currentLayout !== 'mobile') return;

            const touch = event.touches[0];
            this.touchSupport.startX = touch.clientX;
            this.touchSupport.startY = touch.clientY;
            this.touchSupport.isTouch = true;
        },

        /**
         * 處理觸控移動
         * @param {TouchEvent} event - 觸控事件
         */
        handleTouchMove: function (event) {
            if (!this.touchSupport.isTouch || this.currentLayout !== 'mobile') return;

            const touch = event.touches[0];
            this.touchSupport.endX = touch.clientX;
            this.touchSupport.endY = touch.clientY;
        },

        /**
         * 處理觸控結束
         * @param {TouchEvent} event - 觸控事件
         */
        handleTouchEnd: function (event) {
            if (!this.touchSupport.isTouch || this.currentLayout !== 'mobile') return;

            const deltaX = this.touchSupport.endX - this.touchSupport.startX;
            const deltaY = this.touchSupport.endY - this.touchSupport.startY;

            // 檢查是否為有效滑動手勢
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > this.touchSupport.minSwipeDistance) {
                if (deltaX > 0) {
                    // 向右滑動 - 開啟好友列表
                    if (this.touchSupport.startX < 50) { // 從左邊緣開始
                        this.toggleFriendsPanel();
                    }
                } else {
                    // 向左滑動 - 開啟客戶資訊或關閉面板
                    const screenWidth = $(window).width();
                    if (this.touchSupport.startX > screenWidth - 50) { // 從右邊緣開始
                        this.toggleCustomerInfoPanel();
                    } else if (this.panels.friends.hasClass('mobile-active')) {
                        this.closeMobilePanels();
                    }
                }
            }

            this.touchSupport.isTouch = false;
        },

        /**
         * 處理面板觸控開始
         * @param {TouchEvent} event - 觸控事件
         */
        handlePanelTouchStart: function (event) {
            // 防止面板內的觸控事件冒泡到容器
            event.stopPropagation();
        },

        /**
         * 綁定媒體查詢監聽器
         */
        bindMediaQueryListeners: function () {
            if ('matchMedia' in window) {
                const mobileQuery = window.matchMedia(`(max-width: ${this.breakpoints.mobile}px)`);
                const tabletQuery = window.matchMedia(`(max-width: ${this.breakpoints.tablet}px)`);

                // 現代瀏覽器使用 addEventListener
                if (mobileQuery.addEventListener) {
                    mobileQuery.addEventListener('change', this.handleMediaQueryChange.bind(this));
                    tabletQuery.addEventListener('change', this.handleMediaQueryChange.bind(this));
                }
                // 舊版瀏覽器使用 addListener
                else if (mobileQuery.addListener) {
                    mobileQuery.addListener(this.handleMediaQueryChange.bind(this));
                    tabletQuery.addListener(this.handleMediaQueryChange.bind(this));
                }
            }
        },

        /**
         * 處理媒體查詢變化
         * @param {MediaQueryListEvent} event - 媒體查詢事件
         */
        handleMediaQueryChange: function (event) {
            // 延遲檢測以避免過度觸發
            setTimeout(() => {
                this.detectScreenSize();
            }, 50);
        },

        /**
         * 處理頁面可見性變化
         */
        handleVisibilityChange: function () {
            if (!document.hidden && this.currentLayout === 'mobile') {
                // 頁面重新可見時，檢查布局是否需要調整
                setTimeout(() => {
                    this.detectScreenSize();
                }, 100);
            }
        },

        /**
         * 節流函數
         * @param {Function} func - 要節流的函數
         * @param {number} wait - 等待時間
         * @returns {Function} 節流後的函數
         */
        throttle: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * 取得當前布局
         * @returns {string} 布局名稱
         */
        getCurrentLayout: function () {
            return this.currentLayout;
        },

        /**
         * 銷毀響應式處理器
         */
        destroy: function () {
            $(window).off('resize');
            $(window).off('orientationchange');
            this.mobileToggles.friends.off();
            this.mobileToggles.customerInfo.off();
            this.overlay.off();
            $(document).off('keydown', this.handleKeydown);
            $(document).off('visibilitychange', this.handleVisibilityChange);

            // 清理觸控事件監聽器
            const chatContainer = $('#chat-container');
            if (chatContainer.length) {
                chatContainer[0].removeEventListener('touchstart', this.handleTouchStart);
                chatContainer[0].removeEventListener('touchmove', this.handleTouchMove);
                chatContainer[0].removeEventListener('touchend', this.handleTouchEnd);
            }

            if (this.panels.friends.length) {
                this.panels.friends[0].removeEventListener('touchstart', this.handlePanelTouchStart);
            }
            if (this.panels.customerInfo.length) {
                this.panels.customerInfo[0].removeEventListener('touchstart', this.handlePanelTouchStart);
            }

            // 清理媒體查詢監聽器
            if ('matchMedia' in window) {
                const mobileQuery = window.matchMedia(`(max-width: ${this.breakpoints.mobile}px)`);
                const tabletQuery = window.matchMedia(`(max-width: ${this.breakpoints.tablet}px)`);

                if (mobileQuery.removeEventListener) {
                    mobileQuery.removeEventListener('change', this.handleMediaQueryChange);
                    tabletQuery.removeEventListener('change', this.handleMediaQueryChange);
                } else if (mobileQuery.removeListener) {
                    mobileQuery.removeListener(this.handleMediaQueryChange);
                    tabletQuery.removeListener(this.handleMediaQueryChange);
                }
            }

            // 清理 CSS 屬性
            this.panels.friends.css('will-change', '');
            this.panels.customerInfo.css('will-change', '');

            // 重置狀態
            this.panelStates = {
                friendsPanelOpen: false,
                customerInfoPanelOpen: false,
                lastSelectedPanel: null
            };
        }
    };

})(jQuery);