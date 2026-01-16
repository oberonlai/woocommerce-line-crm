/**
 * OrderChatz 客戶資訊元件 (重構版)
 *
 * 使用模組化架構，整合各個子模組來管理客戶資訊
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 客戶資訊元件
     * 管理右側客戶資訊面板的顯示和互動功能
     */
    window.CustomerInfoComponent = function (containerSelector) {
        this.container = $(containerSelector);
        this.customerInfoCore = null;
        this.dragManager = null;

        this.init();
    };

    CustomerInfoComponent.prototype = {
        /**5k4k
         * 初始化元件
         */
        init: function () {
            // 嘗試等待依賴載入
            this.initWithRetry();
        },

        /**
         * 帶重試機制的初始化
         */
        initWithRetry: function (retryCount = 0) {
            const maxRetries = 10;
            const retryDelay = 300;

            // 檢查 DOM 是否準備好
            const domReady = $('#customer-info').length > 0 && $('#no-customer-selected').length > 0;
            const moduleReady = typeof CustomerInfoCore !== 'undefined';

            if (domReady && moduleReady) {
                this.performInit();
                return;
            }

            // 如果還沒準備好且未達最大重試次數
            if (retryCount < maxRetries) {
                setTimeout(() => {
                    this.initWithRetry(retryCount + 1);
                }, retryDelay);
                return;
            }

            // 超過重試次數，使用最小功能模式
            this.customerInfoCore = this.createMinimalCore();
            this.customerInfoCore.init();
            this.setupBackwardCompatibility();
        },

        /**
         * 執行實際初始化
         */
        performInit: function () {
            try {
                this.customerInfoCore = new CustomerInfoCore(this.container.selector);
            } catch (error) {
                this.customerInfoCore = this.createMinimalCore();
                this.customerInfoCore.init();
            }

            this.setupBackwardCompatibility();
        },


        /**
         * 設定向後相容性方法
         * 為了保證現有代碼不受影響，提供原有方法的代理
         */
        setupBackwardCompatibility: function () {
            // 將 CustomerInfoCore 的公共方法代理到這個元件上
            const methodsToProxy = [
                'showCustomerInfo',
                'hideCustomerInfo',
                'loadCustomerInfo',
                'renderCustomerInfo',
                'syncState',
                'destroy'
            ];

            methodsToProxy.forEach(method => {
                if (typeof this.customerInfoCore[method] === 'function') {
                    this[method] = this.customerInfoCore[method].bind(this.customerInfoCore);
                }
            });

            // 代理屬性訪問
            Object.defineProperty(this, 'currentFriend', {
                get: function () {
                    return this.customerInfoCore ? this.customerInfoCore.currentFriend : null;
                },
                set: function (value) {
                    if (this.customerInfoCore) {
                        this.customerInfoCore.currentFriend = value;
                    }
                }
            });

            Object.defineProperty(this, 'customerData', {
                get: function () {
                    return this.customerInfoCore ? this.customerInfoCore.customerData : null;
                },
                set: function (value) {
                    if (this.customerInfoCore) {
                        this.customerInfoCore.customerData = value;
                    }
                }
            });

            Object.defineProperty(this, 'customerInfoContent', {
                get: function () {
                    return this.customerInfoCore ? this.customerInfoCore.customerInfoContent : null;
                }
            });

            Object.defineProperty(this, 'noCustomerSelected', {
                get: function () {
                    return this.customerInfoCore ? this.customerInfoCore.noCustomerSelected : null;
                }
            });
        },

        /**
         * 銷毀元件
         */
        destroy: function () {
            // 由於已禁用舊的拖曳管理器，不需要銷毀
            // if (this.dragManager && typeof this.dragManager.destroy === 'function') {
            //     this.dragManager.destroy();
            //     this.dragManager = null;
            // }

            // 銷毀核心元件
            if (this.customerInfoCore && typeof this.customerInfoCore.destroy === 'function') {
                this.customerInfoCore.destroy();
                this.customerInfoCore = null;
            }
        },

        /**
         * 取得核心元件實例（供調試或高級使用）
         * @returns {CustomerInfoCore} 核心元件實例
         */
        getCore: function () {
            return this.customerInfoCore;
        },

        /**
         * 創建最小功能的核心元件
         * 當依賴項不足時提供基本功能
         */
        createMinimalCore: function () {
            return {
                container: this.container,
                customerInfoContent: $('#customer-info'),
                noCustomerSelected: $('#no-customer-selected'),
                currentFriend: null,
                customerData: null,

                init: function () {
                    this.hideCustomerInfo();
                },

                showCustomerInfo: function () {
                    this.customerInfoContent.show();
                    this.noCustomerSelected.hide();
                },

                hideCustomerInfo: function () {
                    this.customerInfoContent.hide();
                    this.noCustomerSelected.show();
                },

                loadCustomerInfo: function (friendData) {
                    this.showCustomerInfo();
                },

                syncState: function (state) {
                    if (state.currentFriend) {
                        this.currentFriend = state.currentFriend;
                        this.loadCustomerInfo(state.currentFriend);
                    } else {
                        this.hideCustomerInfo();
                    }
                },

                destroy: function () {
                }
            };
        },

        /**
         * 檢查元件是否已正確初始化
         * @returns {boolean} 是否已初始化
         */
        isInitialized: function () {
            return this.customerInfoCore !== null && typeof this.customerInfoCore.init === 'function';
        }
    };

    /**
     * 客戶資訊拖曳排序管理器
     * 提供客戶資訊面板區塊的拖曳排序功能
     */
    window.CustomerInfoDragManager = function () {
        this.dragState = {
            isDragging: false,
            draggedElement: null,
            startY: 0,
            currentY: 0,
            placeholder: null
        };

        this.init();
    };

    CustomerInfoDragManager.prototype = {
        /**
         * 初始化拖曳功能
         */
        init: function () {

            // 延遲初始化確保DOM已載入
            setTimeout(() => {
                this.setupDragHandles();
                this.bindEvents();
                this.injectStyles();
            }, 1500);
        },

        /**
         * 綁定全局事件
         */
        bindEvents: function () {
            // 監聽客戶資訊更新
            let debounceTimer;
            $(document).on('DOMNodeInserted', (e) => {
                if ($(e.target).find('.info-section').length || $(e.target).hasClass('info-section')) {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => this.setupDragHandles(), 200);
                }
            });

            // ESC 鍵取消拖曳
            $(document).on('keydown.customerDrag', (e) => {
                if (e.key === 'Escape' && this.dragState.isDragging) {
                    this.cleanup();
                }
            });

            // 點擊空白處取消拖曳
            $(document).on('click.customerDrag', (e) => {
                if (!$(e.target).closest('.info-section, .collapsible-header').length) {
                    if (this.dragState.isDragging) {
                        this.cleanup();
                    }
                }
            });
        },

        /**
         * 設置拖曳控制器
         */
        setupDragHandles: function () {

            $('.info-section').each((index, element) => {
                const $section = $(element);
                const $handle = $section.find('.collapsible-header');

                if ($handle.length === 0) return;

                // 清理舊事件
                $handle.off('.customerDrag');
                $section.off('.customerDrag');

                // 拖曳控制器事件 - 只在標題區域觸發
                $handle.on('mousedown.customerDrag touchstart.customerDrag', (e) => {
                    // 如果點擊的是展開/收合圖示，不要開始拖曳
                    if ($(e.target).hasClass('toggle-icon') || $(e.target).closest('.toggle-icon').length) {
                        return;
                    }
                    e.preventDefault();
                    this.startDrag(e, $section);
                });

                // 為整個標題區域添加視覺提示
                $handle.css({
                    'cursor': 'move',
                });

                // 保留展開/收合圖示的正常游標
                $handle.find('.toggle-icon').css({
                    'cursor': 'pointer'
                });
            });
        },

        /**
         * 開始拖曳
         */
        startDrag: function (e, $element) {
            if (this.dragState.isDragging) return;

            this.dragState.isDragging = true;
            this.dragState.draggedElement = $element;

            const startEvent = e.type === 'touchstart' ? e.originalEvent.touches[0] : e.originalEvent;
            this.dragState.startY = startEvent.clientY;

            // 視覺效果
            $element.addClass('dragging');
            $element.css('opacity', '0.7');

            // 創建佔位符
            this.dragState.placeholder = $('<div class="drag-placeholder"></div>');
            this.dragState.placeholder.css({
                'height': $element.outerHeight() + 'px',
                'background': 'rgba(0, 115, 170, 0.1)',
                'border': '2px dashed #0073aa',
                'margin': '10px 0',
                'border-radius': '4px'
            });
            $element.after(this.dragState.placeholder);

            // 綁定移動和結束事件
            $(document).on('mousemove.customerDrag touchmove.customerDrag', (e) => this.handleDragMove(e));
            $(document).on('mouseup.customerDrag touchend.customerDrag', (e) => this.handleDragEnd(e));

            // 防止頁面滾動
            $('body').addClass('drag-active').css('overflow', 'hidden');
        },

        /**
         * 處理拖曳移動
         */
        handleDragMove: function (e) {
            if (!this.dragState.isDragging) return;

            const moveEvent = e.type === 'touchmove' ? e.originalEvent.touches[0] : e.originalEvent;
            this.dragState.currentY = moveEvent.clientY;

            const deltaY = this.dragState.currentY - this.dragState.startY;

            // 移動被拖曳的元素
            this.dragState.draggedElement.css('transform', `translateY(${deltaY}px)`);

            // 尋找插入位置
            this.findInsertPosition(moveEvent.clientY);
        },

        /**
         * 尋找插入位置
         */
        findInsertPosition: function (clientY) {
            const sections = $('.info-section').not(this.dragState.draggedElement);
            let insertBefore = null;

            sections.each(function () {
                const rect = this.getBoundingClientRect();
                const middle = rect.top + rect.height / 2;

                if (clientY < middle && !insertBefore) {
                    insertBefore = $(this);
                    return false;
                }
            });

            // 移動佔位符
            if (insertBefore) {
                insertBefore.before(this.dragState.placeholder);
            } else {
                $('.customer-info').append(this.dragState.placeholder);
            }
        },

        /**
         * 處理拖曳結束
         */
        handleDragEnd: function (e) {
            if (!this.dragState.isDragging) return;

            // 清理事件
            $(document).off('.customerDrag');
            $('body').removeClass('drag-active').css('overflow', '');

            // 執行重新排序
            if (this.dragState.placeholder && this.dragState.placeholder.parent().length) {
                this.dragState.placeholder.before(this.dragState.draggedElement);
                this.saveOrder();
            }

            // 清理樣式和狀態
            this.cleanup();
        },

        /**
         * 清理拖曳狀態
         */
        cleanup: function () {

            if (this.dragState.draggedElement) {
                this.dragState.draggedElement
                    .removeClass('dragging')
                    .css({
                        'opacity': '',
                        'transform': ''
                    });
            }

            if (this.dragState.placeholder) {
                this.dragState.placeholder.remove();
            }

            $('.info-section').removeClass('drag-over');

            // 重置狀態
            this.dragState = {
                isDragging: false,
                draggedElement: null,
                startY: 0,
                currentY: 0,
                placeholder: null
            };

            // 重新設置控制器
            setTimeout(() => this.setupDragHandles(), 100);
        },

        /**
         * 保存排序
         */
        saveOrder: function () {
            const order = [];
            $('.info-section').each(function () {
                const match = $(this).attr('class').match(/customer-(\w+)/);
                if (match) order.push(match[1]);
            });

            const userId = window.otzChatConfig?.current_user?.id || 'default';
            localStorage.setItem(`otz_customer_sections_order_${userId}`, JSON.stringify(order));
        },

        /**
         * 注入拖曳樣式
         */
        injectStyles: function () {
            if ($('#customer-drag-styles').length > 0) return;

            $('<style id="customer-drag-styles">')
                .prop('type', 'text/css')
                .html(`
                    .drag-active {
                        cursor: move !important;
                    }
                    .drag-active * {
                        pointer-events: none;
                    }
                    .drag-active .info-section.dragging {
                        pointer-events: auto;
                        z-index: 9999;
                        position: relative;
                    }
                    .info-section.dragging {
                        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                        border: 2px solid #0073aa;
                    }
                    .drag-placeholder {
                        transition: all 0.2s ease;
                    }
                    .collapsible-header {
                        transition: background-color 0.2s ease;
                    }
                    .collapsible-header:hover {
                        background-color: #e9ecef;
                    }
                `)
                .appendTo('head');
        },

        /**
         * 銷毀拖曳功能
         */
        destroy: function () {
            console.log('銷毀客戶資訊拖曳功能');

            // 清理事件
            $(document).off('.customerDrag');
            $('.info-section').off('.customerDrag');
            $('.collapsible-header').off('.customerDrag');

            // 清理樣式
            this.cleanup();
            $('#customer-drag-styles').remove();

            // 重置狀態
            this.dragState = {
                isDragging: false,
                draggedElement: null,
                startY: 0,
                currentY: 0,
                placeholder: null
            };
        }
    };


})(jQuery);