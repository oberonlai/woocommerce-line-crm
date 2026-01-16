/**
 * OrderChatz 訂單管理模組
 *
 * 處理訂單搜尋、列表顯示、詳細資訊和自訂欄位管理
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 訂單管理器
     */
    window.OrderManager = function (container) {
        this.container = container;
        this.customerData = null;
        this.remainingOrders = [];
        this.currentOrderId = null;
        this.customFieldCount = 0;
    };

    OrderManager.prototype = {
        /**
         * 渲染訂單資訊區塊
         * @param {Array} orders - 訂單資料
         * @returns {string} HTML 字串
         */
        renderOrderInfo: function (orders = []) {
            const template = $('#customer-orders-template').html();
            if (!template) {
                console.error('OrderManager: 客戶訂單模板未找到');
                return '';
            }

            // 只顯示前5筆訂單
            const displayOrders = orders.slice(0, 5);
            this.remainingOrders = orders.slice(5);

            let ordersHtml = '';

            if (displayOrders.length === 0) {
                ordersHtml = '<div class="no-orders"><p>尚無訂單記錄</p></div>';
            } else {
                ordersHtml = '<div class="orders-list">';
                displayOrders.forEach(order => {
                    ordersHtml += this.renderOrderItem(order);
                });
                ordersHtml += '</div>';
            }

            return template.replace(/\{ordersHtml\}/g, ordersHtml);
        },

        /**
         * 渲染單個訂單項目
         * @param {object} order - 訂單資料
         * @returns {string} HTML 字串
         */
        renderOrderItem: function (order) {
            return `
                <div class="order-item" data-order-id="${UIHelpers.escapeHtml(order.id)}">
                    <div class="order-header">
                        <span class="order-number clickable">#${UIHelpers.escapeHtml(order.order_number || order.id)}</span>
                        <span class="order-status status-${UIHelpers.escapeHtml(order.status)}">${UIHelpers.escapeHtml(order.status_name || order.status)}</span>
                    </div>
                    <div class="order-details">
                        <p class="order-total" style="margin-bottom:5px">訂單金額：$${UIHelpers.escapeHtml(order.total || '0')}</p>
                        <p class="order-date" style="margin:0">訂單日期：${UIHelpers.escapeHtml(order.date_created || '')}</p>
                    </div>
                </div>
            `;
        },

        /**
         * 初始化訂單功能
         */
        init: function () {
            // 初始化訂單搜尋
            this.initOrderSearch();

            // 初始化載入更多按鈕
            this.initLoadMoreOrders();

            // 綁定訂單點擊事件
            this.bindOrderClickEvents();

            // 檢查是否需要顯示載入更多按鈕
            if (this.remainingOrders && this.remainingOrders.length > 0) {
                this.container.find('.load-more-orders').show();
            }
        },

        /**
         * 初始化訂單搜尋功能
         */
        initOrderSearch: function () {
            const searchInput = this.container.find('.order-search-input');
            const searchBtn = this.container.find('.order-search-btn');
            const clearBtn = this.container.find('.order-search-clear');

            // 搜尋按鈕點擊
            searchBtn.on('click', () => {
                const searchTerm = searchInput.val().trim();
                this.searchOrders(searchTerm);
            });

            // Enter 鍵搜尋
            searchInput.on('keypress', (e) => {
                if (e.which === 13) {
                    const searchTerm = searchInput.val().trim();
                    this.searchOrders(searchTerm);
                }
            });

            // 輸入框變化
            searchInput.on('input', () => {
                const hasValue = searchInput.val().trim().length > 0;
                clearBtn.toggle(hasValue);

                if (!hasValue) {
                    this.resetOrdersList();
                }
            });

            // 清除按鈕
            clearBtn.on('click', () => {
                searchInput.val('');
                clearBtn.hide();
                this.resetOrdersList();
            });
        },

        /**
         * 初始化載入更多訂單功能
         */
        initLoadMoreOrders: function () {
            const loadMoreBtn = this.container.find('.load-more-orders-btn');

            loadMoreBtn.on('click', () => {
                this.loadMoreOrders();
            });
        },

        /**
         * 搜尋訂單
         * @param {string} searchTerm - 搜尋詞
         */
        searchOrders: function (searchTerm) {
            if (!searchTerm) {
                this.resetOrdersList();
                return;
            }

            if (!this.customerData || !this.customerData.wp_user_id) {
                return;
            }

            this.showOrdersLoading();

            const data = {
                action: 'otz_search_customer_orders',
                nonce: otzChatConfig.nonce,
                wp_user_id: this.customerData.wp_user_id,
                search_term: searchTerm
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    this.hideOrdersLoading();

                    if (response.success) {
                        this.renderSearchResults(response.data.orders || []);
                    } else {
                        this.showOrdersError('搜尋失敗：' + (response.data?.message || '未知錯誤'));
                    }
                },
                error: () => {
                    this.hideOrdersLoading();
                    this.showOrdersError('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 載入更多訂單
         */
        loadMoreOrders: function () {
            if (!this.remainingOrders || this.remainingOrders.length === 0) {
                return;
            }

            const nextBatch = this.remainingOrders.slice(0, 5);
            this.remainingOrders = this.remainingOrders.slice(5);

            const ordersList = this.container.find('.orders-list');

            nextBatch.forEach(order => {
                ordersList.append(this.renderOrderItem(order));
            });

            // 重新綁定訂單點擊事件
            this.bindOrderClickEvents();

            // 檢查是否還有更多訂單
            if (this.remainingOrders.length === 0) {
                this.container.find('.load-more-orders').hide();
            }
        },

        /**
         * 重置訂單列表
         */
        resetOrdersList: function () {
            if (!this.customerData) return;

            const allOrders = this.customerData.orders || [];
            const orders = allOrders.slice(0, 5);
            this.remainingOrders = allOrders.slice(5);

            let ordersHtml = '<div class="orders-list">';
            orders.forEach(order => {
                ordersHtml += this.renderOrderItem(order);
            });
            ordersHtml += '</div>';

            this.container.find('.orders-list-container').html(ordersHtml);

            // 重新初始化功能
            this.initLoadMoreOrders();
            this.bindOrderClickEvents();

            // 顯示/隱藏載入更多按鈕
            if (this.remainingOrders.length > 0) {
                this.container.find('.orders-list-container').append(`
                    <div class="load-more-orders">
                        <button type="button" class="button load-more-orders-btn">載入更多訂單</button>
                    </div>
                    <div class="orders-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        <p>載入訂單中...</p>
                    </div>
                `);
                this.initLoadMoreOrders();
            }
        },

        /**
         * 渲染搜尋結果
         * @param {array} orders - 搜尋到的訂單
         */
        renderSearchResults: function (orders) {
            let ordersHtml = '<div class="orders-list">';

            if (orders.length === 0) {
                ordersHtml += '<div class="no-orders"><p>未找到符合的訂單</p></div>';
            } else {
                orders.forEach(order => {
                    ordersHtml += this.renderOrderItem(order);
                });
            }

            ordersHtml += '</div>';

            this.container.find('.orders-list-container').html(ordersHtml);

            // 重新綁定訂單點擊事件
            this.bindOrderClickEvents();
        },

        /**
         * 綁定訂單點擊事件
         */
        bindOrderClickEvents: function () {
            this.container.find('.order-number').off('click.orderClick').on('click.orderClick', (e) => {
                e.preventDefault();
                const orderElement = $(e.target).closest('.order-item');
                const orderId = orderElement.data('order-id');

                if (e.ctrlKey || e.metaKey) {
                    // Ctrl/Cmd + 點擊：開啟訂單編輯頁面
                    if (orderId) {
                        this.openOrderEditPage(orderId);
                    }
                } else {
                    // 普通點擊：顯示訂單詳細資訊
                    if (orderId) {
                        this.showOrderDetail(orderId);
                    }
                }
            });
        },

        /**
         * 顯示訂單詳細資訊
         * @param {string} orderId - 訂單 ID
         */
        showOrderDetail: function (orderId) {
            // 顯示載入狀態
            this.showOrderDetailModal();

            const data = {
                action: 'otz_get_order_detail',
                nonce: otzChatConfig.nonce,
                order_id: orderId
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.renderOrderDetailContent(response.data);
                    } else {
                        this.showOrderDetailError(response.data?.message || '無法載入訂單詳細資訊');
                    }
                },
                error: () => {
                    this.showOrderDetailError('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 顯示訂單詳細資訊浮動視窗
         */
        showOrderDetailModal: function () {
            const modal = $('#order-detail-modal');
            if (modal.length === 0) {
                console.error('OrderManager: 訂單詳細資訊浮動視窗元素未找到');
                return;
            }

            // 重置內容
            modal.find('.order-detail-loading').show();
            modal.find('.order-detail-iframe-container').hide();

            // 顯示浮動視窗
            modal.fadeIn(300);

            // 綁定關閉事件（避免重複綁定）
            modal.off('click.orderDetail').on('click.orderDetail', '.modal-close, .modal-backdrop', (e) => {
                if ($(e.target).hasClass('modal-backdrop') || $(e.target).closest('.modal-close').length > 0) {
                    this.hideOrderDetailModal();
                }
            });

            // 綁定 ESC 鍵關閉事件
            $(document).off('keydown.orderDetail').on('keydown.orderDetail', (e) => {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    this.hideOrderDetailModal();
                }
            });
        },

        /**
         * 隱藏訂單詳細資訊浮動視窗
         */
        hideOrderDetailModal: function () {
            const modal = $('#order-detail-modal');

            // 移除事件監聽器
            $(document).off('keydown.orderDetail');
            modal.off('click.orderDetail');

            // 清理 iframe src 以停止載入
            const iframe = modal.find('#order-detail-iframe');
            iframe.attr('src', 'about:blank');

            // 添加關閉動畫類別
            modal.addClass('closing');

            // 動畫完成後隱藏 modal
            setTimeout(() => {
                modal.removeClass('closing').hide();
                // 重置 iframe 容器狀態
                modal.find('.order-detail-iframe-container').hide();
                modal.find('.order-detail-loading').show();
            }, 300); // 與 CSS 動畫時長一致
        },

        /**
         * 渲染訂單詳細內容
         * @param {object} orderData - 訂單資料
         */
        renderOrderDetailContent: function (orderData) {
            // 直接使用 iframe 載入 WooCommerce 訂單編輯頁面
            const orderId = orderData.id || orderData.order_number;
            if (!orderId) {
                this.showOrderDetailError('無法取得訂單 ID');
                return;
            }

            // 建構 WooCommerce 訂單編輯頁面 URL，添加參數隱藏不必要的界面元素
            const editUrl = `${window.location.origin}/wp-admin/post.php?post=${orderId}&action=edit&otz_iframe=1`;

            const modal = $('#order-detail-modal');
            const iframe = modal.find('#order-detail-iframe');
            const iframeContainer = modal.find('.order-detail-iframe-container');

            // 設定 iframe src
            iframe.attr('src', editUrl);

            // 隱藏載入狀態，顯示 iframe
            iframeContainer.fadeIn(200);

            // 監聽 iframe 載入完成
            iframe.off('load').on('load', function () {
                // iframe 載入完成後的處理
                try {
                    // 可以在這裡添加一些載入完成後的邏輯
                    modal.find('.order-detail-loading').fadeOut();
                } catch (error) {
                    // 跨域限制可能會導致無法訪問 iframe 內容
                }
            });
        },

        /**
         * 初始化自訂欄位管理功能
         * @param {string|number} orderId - 訂單 ID
         */
        initCustomFieldManagement: function (orderId) {
            const modal = $('#order-detail-modal');
            this.currentOrderId = orderId;
            this.customFieldCount = 0;

            // 載入已儲存的自訂欄位設定
            this.loadCustomFieldSettings();

            // 綁定新增按鈕事件
            modal.find('#add-custom-field').off('click').on('click', () => {
                this.addCustomFieldRow();
            });

            // 綁定儲存按鈕事件
            modal.find('#save-order-settings').off('click').on('click', () => {
                this.saveCustomFieldSettings();
            });
        },

        /**
         * 載入自訂欄位設定
         */
        loadCustomFieldSettings: function () {
            const data = {
                action: 'otz_get_order_settings',
                nonce: otzChatConfig.nonce
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success && response.data) {
                        this.renderCustomFieldSettings(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('載入自訂欄位設定失敗:', error);
                }
            });
        },

        /**
         * 渲染自訂欄位設定
         * @param {Array} settings - 設定資料
         */
        renderCustomFieldSettings: function (settings) {
            const container = $('#custom-fields-repeater');
            container.empty();

            if (!settings || settings.length === 0) {
                this.addCustomFieldRow();
                return;
            }

            settings.forEach(field => {
                this.addCustomFieldRow(field.name, field.key);
            });
        },

        /**
         * 新增自訂欄位設定行
         * @param {string} name - 欄位名稱
         * @param {string} key - 欄位鍵值
         */
        addCustomFieldRow: function (name = '', key = '') {
            this.customFieldCount++;
            const rowId = 'custom-field-' + this.customFieldCount;

            const html = `
                <div class="custom-field-row-setting" data-row-id="${rowId}">
                    <div class="custom-field-input-group">
                        <label>欄位名稱</label>
                        <input type="text" class="custom-field-name" value="${UIHelpers.escapeHtml(name)}" placeholder="例如：配送說明">
                    </div>
                    <div class="custom-field-input-group">
                        <label>欄位鍵值</label>
                        <input type="text" class="custom-field-key" value="${UIHelpers.escapeHtml(key)}" placeholder="例如：delivery_note">
                    </div>
                    <button type="button" class="remove-custom-field" title="移除欄位">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            `;

            const container = $('#custom-fields-repeater');
            container.append(html);

            // 綁定移除按鈕事件
            container.find(`[data-row-id="${rowId}"] .remove-custom-field`).on('click', (e) => {
                $(e.currentTarget).closest('.custom-field-row-setting').fadeOut(300, function () {
                    $(this).remove();
                });
            });
        },

        /**
         * 儲存自訂欄位設定
         */
        saveCustomFieldSettings: function () {
            const button = $('#save-order-settings');
            const originalText = button.text();

            // 顯示載入狀態
            button.addClass('loading').prop('disabled', true).text('儲存中...');

            // 收集設定資料
            const settings = [];
            $('#custom-fields-repeater .custom-field-row-setting').each(function () {
                const row = $(this);
                const name = row.find('.custom-field-name').val().trim();
                const key = row.find('.custom-field-key').val().trim();

                if (name && key) {
                    settings.push({name: name, key: key});
                }
            });

            const data = {
                action: 'otz_save_order_settings',
                nonce: otzChatConfig.nonce,
                settings: JSON.stringify(settings)
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    button.removeClass('loading').prop('disabled', false).text(originalText);

                    if (response.success) {
                        UIHelpers.showSuccessMessage($('#order-detail-modal .order-detail-content'), '自訂欄位設定已儲存');
                        // 重新載入訂單詳情以顯示新的自訂欄位
                        if (this.currentOrderId) {
                            this.showOrderDetail(this.currentOrderId);
                        }
                    } else {
                        alert('儲存失敗：' + (response.data?.message || '未知錯誤'));
                    }
                },
                error: (xhr, status, error) => {
                    button.removeClass('loading').prop('disabled', false).text(originalText);
                    console.error('儲存自訂欄位設定失敗:', error);
                    alert('儲存失敗：網路連線發生問題');
                }
            });
        },

        /**
         * 顯示訂單詳細資訊載入錯誤
         * @param {string} message - 錯誤訊息
         */
        showOrderDetailError: function (message) {
            const errorHtml = `
                <div class="order-detail-error">
                    <div class="error-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <p class="error-message">${UIHelpers.escapeHtml(message)}</p>
                    <button type="button" class="button button-secondary retry-order-detail">重新載入</button>
                </div>
            `;

            const modal = $('#order-detail-modal');
            modal.find('.order-detail-loading').hide();
            modal.find('.order-detail-content').html(errorHtml).show();

            // 綁定重試按鈕事件
            modal.find('.retry-order-detail').on('click', () => {
                this.hideOrderDetailModal();
            });
        },

        /**
         * 開啟 WordPress 訂單編輯頁面
         * @param {string|number} orderId - 訂單 ID
         */
        openOrderEditPage: function (orderId) {
            if (!orderId) {
                console.warn('OrderManager: 無效的訂單 ID');
                return;
            }

            // 構建 WordPress 管理後台基礎 URL
            let adminUrl;
            const currentUrl = window.location.href;

            // 方法 1: 從當前 URL 中提取 admin URL
            if (currentUrl.includes('/wp-admin/')) {
                const adminIndex = currentUrl.indexOf('/wp-admin/');
                adminUrl = currentUrl.substring(0, adminIndex + '/wp-admin/'.length);
            } else {
                // 方法 2: 從 domain 構建 (fallback)
                adminUrl = window.location.origin + '/wp-admin/';
            }

            // 構建 WooCommerce 訂單編輯頁面 URL
            const editUrl = adminUrl + 'post.php?post=' + encodeURIComponent(orderId) + '&action=edit';

            console.log('Opening order edit page:', editUrl);

            // 在新視窗中開啟訂單編輯頁面
            window.open(editUrl, '_blank', 'noopener,noreferrer');
        },

        /**
         * 顯示訂單載入狀態
         */
        showOrdersLoading: function () {
            this.container.find('.orders-loading').show();
        },

        /**
         * 隱藏訂單載入狀態
         */
        hideOrdersLoading: function () {
            this.container.find('.orders-loading').hide();
        },

        /**
         * 顯示訂單錯誤
         * @param {string} message - 錯誤訊息
         */
        showOrdersError: function (message) {
            const errorHtml = `<div class="orders-error"><p>${UIHelpers.escapeHtml(message)}</p></div>`;
            this.container.find('.orders-list-container').html(errorHtml);
        },

        /**
         * 設定客戶資料
         * @param {object} customerData - 客戶資料
         */
        setCustomerData: function (customerData) {
            this.customerData = customerData;
        }
    };

})(jQuery);