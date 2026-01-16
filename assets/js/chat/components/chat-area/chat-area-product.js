/**
 * OrderChatz 聊天區域商品傳送模組
 *
 * 管理商品選擇和傳送功能
 *
 * @package OrderChatz
 * @since 1.0.1
 */

(function ($) {
    'use strict';

    /**
     * 聊天區域商品處理管理器
     */
    window.ChatAreaProduct = {
        /**
         * 商品選擇 modal 實例
         */
        modal: null,

        /**
         * Select2 實例
         */
        select2Instance: null,

        /**
         * 當前選中的商品
         */
        selectedProduct: null,

        /**
         * 聊天區域實例
         */
        chatArea: null,

        /**
         * 初始化商品傳送功能
         * @param {object} chatAreaInstance - 聊天區域實例
         */
        init: function (chatAreaInstance) {
            this.chatArea = chatAreaInstance;
            this.setupModal();
            this.setupEvents();
            this.initSelect2();
        },

        /**
         * 設置 modal
         */
        setupModal: function () {
            this.modal = $('#product-select-modal');
        },

        /**
         * 設置事件監聽器
         */
        setupEvents: function () {
            const self = this;

            // 商品傳送按鈕點擊
            $(document).on('click', '#product-send-btn', function () {
                self.openProductModal();
            });

            // Modal 關閉按鈕
            $(document).on('click', '#product-select-modal .modal-close, #product-select-modal .modal-cancel', function () {
                self.closeProductModal();
            });

            // Modal 背景點擊關閉
            $(document).on('click', '#product-select-modal .modal-backdrop', function () {
                self.closeProductModal();
            });

            // 傳送確認按鈕
            $(document).on('click', '#product-select-modal .product-send-confirm', function () {
                self.sendSelectedProduct();
            });

            // ESC 鍵關閉 modal
            $(document).on('keydown', function (e) {
                if (e.keyCode === 27 && self.modal.is(':visible')) {
                    self.closeProductModal();
                }
            });
        },

        /**
         * 初始化 Select2
         */
        initSelect2: function () {
            const self = this;

            if (typeof $.fn.select2 === 'undefined') {
                console.error('Select2 not loaded');
                return;
            }

            const $select = $('#product-search-select');

            let isComposing = false;

            this.select2Instance = $select.select2({
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

                    // 完整的安全處理，防止任何 null/undefined 錯誤
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
            });

            // 當選擇商品時
            $select.on('select2:select', function (e) {
                const data = e.params.data;
                self.selectedProduct = data.product;
                $('#product-select-modal .product-send-confirm').prop('disabled', false);
            });

            // 當清除選擇時
            $select.on('select2:clear', function (e) {
                self.selectedProduct = null;
                $('#product-select-modal .product-send-confirm').prop('disabled', true);
            });

            $select.on('select2:open', function () {
                const $search = $('.select2-search__field');
                $search.on('compositionstart', () => isComposing = true);
                $search.on('compositionend', () => isComposing = false);
            });
        },

        /**
         * 打開商品選擇 modal
         */
        openProductModal: function () {
            if (!this.chatArea.currentFriendId || !this.chatArea.currentLineUserId) {
                alert('請先選擇一位好友');
                return;
            }

            // 重置選擇
            this.selectedProduct = null;
            if (this.select2Instance) {
                this.select2Instance.val(null).trigger('change');
            }
            $('#product-select-modal .product-send-confirm').prop('disabled', true);

            // 顯示 modal
            this.modal.fadeIn(300);

            // 聚焦到搜尋框
            setTimeout(() => {
                $('#product-search-select').select2('open');
            }, 100);
        },

        /**
         * 關閉商品選擇 modal
         */
        closeProductModal: function () {
            this.modal.fadeOut(300);
            this.selectedProduct = null;
            if (this.select2Instance) {
                this.select2Instance.val(null).trigger('change');
            }
        },

        /**
         * 傳送選中的商品
         */
        sendSelectedProduct: function () {
            if (!this.selectedProduct) {
                alert('請先選擇一個商品');
                return;
            }

            if (!this.chatArea.currentLineUserId) {
                alert('請先選擇一位好友');
                return;
            }

            // 在關閉 modal 前先保存商品資料
            const productToSend = Object.assign({}, this.selectedProduct);

            // 關閉 modal
            this.closeProductModal();

            // 顯示簡化的發送中訊息
            const tempMessageId = this.addSimpleTempProductMessage(productToSend);

            // 發送商品訊息到後端
            this.sendProductToServer(productToSend, tempMessageId);
        },

        /**
         * 添加臨時商品訊息
         * @param {object} product - 商品資料
         * @return {string} 臨時訊息 ID
         */
        addTempProductMessage: function (product) {
            const tempId = 'temp_' + Date.now();

            // 完整的安全處理，防止任何 null/undefined 錯誤
            try {
                const safeProduct = product || {};
                const imageUrl = (safeProduct.image && typeof safeProduct.image === 'string') ? safeProduct.image : '';
                const title = safeProduct.title || '未命名商品';
                const price = safeProduct.price || '0';
                const permalink = safeProduct.permalink || '#';

                const imageHtml = imageUrl ?
                    `<img src="${imageUrl}" alt="${title}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" />` :
                    `<div style="width: 60px; height: 60px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">無圖</div>`;

                const productHtml = `
                <div class="message-row outgoing temp" data-temp-id="${tempId}">
                    <div class="message-bubble outgoing">
                        <div class="message-content">
                            <div class="message-product">
                                <div class="product-card">
                                    <div class="product-image">
                                        ${imageHtml}
                                    </div>
                                    <div class="product-details">
                                        <h4 class="product-title">${ChatAreaUtils.escapeHtml(title)}</h4>
                                        <div class="product-price">NT$${price}</div>
                                        <div class="product-link">
                                            <a href="${permalink}" target="_blank">查看商品</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="message-time">發送中...</div>
                    </div>
                    <div class="message-avatar">
                        <img src="${ChatAreaUtils.getCurrentUserAvatar()}" alt="avatar" />
                    </div>
                </div>
                `;

                this.chatArea.chatMessages.append(productHtml);
                ChatAreaUI.scrollToBottom(this.chatArea.chatMessages);
                return tempId;
            } catch (error) {
                console.error('Error in addTempProductMessage:', error);
                // 在出錯時顯示簡單的錯誤訊息
                const errorHtml = `
                    <div class="message-row outgoing temp" data-temp-id="${tempId}">
                        <div class="message-bubble outgoing error">
                            <div class="message-content">商品資料載入出錯</div>
                        </div>
                    </div>
                `;
                this.chatArea.chatMessages.append(errorHtml);
                ChatAreaUI.scrollToBottom(this.chatArea.chatMessages);
                return tempId;
            }
        },

        /**
         * 加入簡化的臨時商品訊息
         * @param {object} product - 商品資料
         * @return {string} 臨時訊息 ID
         */
        addSimpleTempProductMessage: function (product) {
            const tempId = 'temp_' + Date.now();

            try {
                const safeProduct = product || {};
                const title = safeProduct.title || '未命名商品';
                const permalink = safeProduct.permalink || '#';

                // 簡化的訊息 HTML，只顯示連結
                const productHtml = `
                    <div class="message-row outgoing temp" data-temp-id="${tempId}">
                        <div class="message-bubble outgoing">
                            <div class="message-content">
                                <div class="product-link-simple">
                                    [商品推薦] <a href="${permalink}" target="_blank">${ChatAreaUtils.escapeHtml(title)}</a>
                                </div>
                            </div>
                            <div class="message-time">發送中...</div>
                        </div>
                        <div class="message-avatar">
                            <img src="${ChatAreaUtils.getCurrentUserAvatar()}" alt="avatar" />
                        </div>
                    </div>
                `;

                this.chatArea.chatMessages.append(productHtml);
                ChatAreaUI.scrollToBottom(this.chatArea.chatMessages);
                return tempId;
            } catch (error) {
                console.error('Error in addSimpleTempProductMessage:', error);
                // 在出錯時顯示簡單的錯誤訊息
                const errorHtml = `
                    <div class="message-row outgoing temp" data-temp-id="${tempId}">
                        <div class="message-bubble outgoing error">
                            <div class="message-content">商品連結載入出錯</div>
                        </div>
                    </div>
                `;
                this.chatArea.chatMessages.append(errorHtml);
                ChatAreaUI.scrollToBottom(this.chatArea.chatMessages);
                return tempId;
            }
        },

        /**
         * 發送商品訊息到伺服器
         * @param {object} product - 商品資料
         * @param {string} tempMessageId - 臨時訊息 ID
         */
        sendProductToServer: function (product, tempMessageId) {
            try {
                // 完整的安全處理，防止任何 null/undefined 錯誤
                const safeProduct = product || {};
                const productImage = (safeProduct.image && typeof safeProduct.image === 'string') ? safeProduct.image : '';
                const productId = safeProduct.id || 0;
                const productTitle = safeProduct.title || '未命名商品';
                const productPrice = safeProduct.price || '0';
                const productUrl = safeProduct.permalink || '';

                const data = {
                    action: 'otz_send_product_message',
                    line_user_id: this.chatArea.currentLineUserId,
                    product_id: productId,
                    product_title: productTitle,
                    product_price: productPrice,
                    product_price_raw: safeProduct.price_raw || '',
                    product_url: productUrl,
                    product_image: productImage,
                    nonce: otzChatConfig.nonce
                };

                $.ajax({
                    url: otzChatConfig.ajax_url,
                    type: 'POST',
                    data: data,
                    success: (response) => {
                        if (response.success) {
                            // 檢查是否成功儲存到資料庫
                            if (response.data && response.data.db_saved) {
                                // 移除臨時訊息
                                $(`.message-row.temp[data-temp-id="${tempMessageId}"]`).remove();

                                // 重新載入訊息
                                setTimeout(() => {
                                    ChatAreaMessages.loadMessages(this.chatArea, false);
                                }, 1000);
                            } else {
                                // 更新臨時訊息狀態為警告
                                const $tempMessage = $(`.message-row.temp[data-temp-id="${tempMessageId}"]`);
                                $tempMessage.removeClass('temp').addClass('warning');
                                $tempMessage.find('.message-time').text('LINE 已發送，但歷史記錄失敗');
                            }

                            // 觸發商品發送成功事件
                            $(document).trigger('product:sent:success', [{
                                friendId: this.chatArea.currentFriendId,
                                product: safeProduct
                            }]);
                        } else {
                            // 發送失敗，更新臨時訊息狀態
                            ChatAreaInput.updateTempMessageError(this.chatArea, tempMessageId, response.data?.message || '發送失敗');
                        }
                    },
                    error: (xhr, status, error) => {
                        // 網路錯誤，更新臨時訊息狀態
                        ChatAreaInput.updateTempMessageError(this.chatArea, tempMessageId, '網路連線錯誤，請稍後再試');
                    }
                });
            } catch (error) {
                console.error('Error in sendProductToServer:', error);
                ChatAreaInput.updateTempMessageError(this.chatArea, tempMessageId, '商品資料處理出錯');
            }
        }
    };

})(jQuery);