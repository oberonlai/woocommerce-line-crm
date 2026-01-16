/**
 * OrderChatz 客戶資訊核心元件
 *
 * 管理客戶資訊的載入、渲染和協調各個子模組
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 客戶資訊核心元件
     * 整合各個子模組，負責主要的客戶資訊管理邏輯
     */
    window.CustomerInfoCore = function (containerSelector) {
        this.container = $(containerSelector);

        // 直接使用全域選擇器，避免容器查找問題
        this.customerInfoContent = $('#customer-info');
        this.noCustomerSelected = $('#no-customer-selected');

        // 如果全域選擇器找不到，再嘗試在容器內查找
        if (this.customerInfoContent.length === 0) {
            this.customerInfoContent = this.container.find('#customer-info');
        }
        if (this.noCustomerSelected.length === 0) {
            this.noCustomerSelected = this.container.find('#no-customer-selected');
        }

        this.currentFriend = null;
        this.customerData = null;

        // 子模組實例（延遲初始化）
        this.orderManager = null;
        this.tagsNotesManager = null;
        this.memberBindingManager = null;

        this.init();
    };

    CustomerInfoCore.prototype = {
        /**
         * 初始化元件
         */
        init: function () {
            this.bindEvents();
            this.hideCustomerInfo();
            this.initializeSubModules();
            // 初始化拖曳功能（一次性設置事件委派）
            this.initSortableEvents();
        },

        /**
         * 初始化子模組（延遲且安全）
         */
        initializeSubModules: function () {
            try {
                // 初始化 OrderManager
                if (typeof OrderManager !== 'undefined') {
                    this.orderManager = new OrderManager(this.customerInfoContent);
                }

                // 初始化 TagsNotesManager
                if (typeof TagsNotesManager !== 'undefined') {
                    this.tagsNotesManager = new TagsNotesManager(this.customerInfoContent);
                }

                // 初始化 MemberBindingManager
                if (typeof MemberBindingManager !== 'undefined') {
                    this.memberBindingManager = new MemberBindingManager(this.customerInfoContent);
                }

            } catch (error) {
                console.error('CustomerInfoCore: 子模組初始化失敗', error);
            }
        },

        /**
         * 綁定事件監聽器
         */
        bindEvents: function () {
            // 監聽好友選擇事件
            $(document).on('friend:selected', this.handleFriendSelected.bind(this));

            // 監聽會員綁定更新事件
            $(document).on('customer:binding-updated', this.handleBindingUpdated.bind(this));

            // 監聽標籤更新事件
            $(document).on('customer:tags-updated', this.handleTagsUpdated.bind(this));

            // 監聽備註更新事件
            $(document).on('customer:notes-updated', this.handleNotesUpdated.bind(this));

            // 監聽重新載入要求事件
            $(document).on('customer:reload-required', this.handleReloadRequired.bind(this));
        },

        /**
         * 處理好友選擇事件
         * @param {Event} event - 自定義事件
         * @param {object} friendData - 好友資料
         */
        handleFriendSelected: function (event, friendData) {
            if (!friendData) {
                this.hideCustomerInfo();
                return;
            }

            // 檢查是否為群組.
            const isGroup = friendData.source_type === 'group' || friendData.source_type === 'room';
            if (isGroup) {
                // 群組只顯示備註功能.
                this.currentFriend = friendData;
                this.loadGroupNotes(friendData);
                return;
            }

            this.currentFriend = friendData;
            this.loadCustomerInfo(friendData);
        },

        /**
         * 載入客戶資訊
         * @param {object} friendData - 好友資料
         */
        loadCustomerInfo: function (friendData) {

            // 先顯示載入狀態
            if (typeof UIHelpers !== 'undefined' && UIHelpers.showLoadingState) {
                UIHelpers.showLoadingState(this.customerInfoContent, '載入客戶資訊中...');
            }
            this.showCustomerInfo();

            // 確保 line_user_id 是字串
            let lineUserId = this.extractLineUserId(friendData);

            // 如果無法取得有效的 line_user_id，靜默跳過
            if (!lineUserId || lineUserId === 'undefined' || lineUserId === 'null') {
                if (typeof UIHelpers !== 'undefined' && UIHelpers.hideLoadingState) {
                    UIHelpers.hideLoadingState(this.customerInfoContent);
                }
                return;
            }

            const data = {
                action: 'otz_get_customer_info',
                nonce: otzChatConfig.nonce,
                line_user_id: lineUserId
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    UIHelpers.hideLoadingState(this.customerInfoContent);

                    if (response.success && response.data) {
                        this.customerData = response.data;
                        this.renderCustomerInfo();

                        // 發送客戶資訊載入完成事件，包含 bot_status
                        $(document).trigger('customer:info-loaded', {
                            line_user_id: lineUserId,
                            bot_status: response.data.bot_status || 'disable'
                        });
                    } else {
                        console.error('CustomerInfoCore: 載入客戶資訊失敗', response);
                        UIHelpers.showErrorModal(
                            response.data?.message || '載入客戶資訊時發生錯誤',
                            () => this.loadCustomerInfo(friendData)
                        );
                        this.hideCustomerInfo();
                    }
                },
                error: (xhr, status, error) => {
                    UIHelpers.hideLoadingState(this.customerInfoContent);
                    console.error('CustomerInfoCore: AJAX 請求失敗', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    UIHelpers.showErrorModal(
                        '網路連線發生問題，請稍後再試',
                        () => this.loadCustomerInfo(friendData)
                    );
                    this.hideCustomerInfo();
                }
            });
        },

        /**
         * 載入群組備註
         * @param {object} friendData - 群組資料
         */
        loadGroupNotes: function (friendData) {
            this.showCustomerInfo();

            // 準備群組的簡化資料結構.
            this.customerData = {
                notes: [] // 初始為空，由 tagsNotesManager 載入.
            };

            // 只渲染備註區塊.
            this.renderGroupNotes();
        },

        /**
         * 渲染群組備註
         */
        renderGroupNotes: function () {
            let html = '';

            // 只顯示備註區塊.
            if (this.tagsNotesManager) {
                const notesSection = this.tagsNotesManager.renderNotesSection(this.customerData.notes || []);
                html += notesSection;
            } else {
                console.warn('[CustomerInfoCore] tagsNotesManager 未初始化');
            }

            this.customerInfoContent.html(html);

            // 初始化備註功能.
            if (this.tagsNotesManager) {
                this.tagsNotesManager.setCurrentFriend(this.currentFriend);
                this.tagsNotesManager.init();
            }

            // 初始化展開收合功能.
            UIHelpers.initCollapsibleSections(this.customerInfoContent);

            this.showCustomerInfo();
        },

        /**
         * 從好友資料中提取 LINE User ID
         * @param {object} friendData - 好友資料
         * @returns {string} LINE User ID
         */
        extractLineUserId: function (friendData) {
            // 檢查是否為群組，群組不應該有 line_user_id.
            const isGroup = friendData.source_type === 'group' || friendData.source_type === 'room';
            if (isGroup) {
                return '';
            }

            let lineUserId = '';

            // 只處理 line_user_id，不使用 id 作為後備值.
            if (friendData.line_user_id) {
                if (typeof friendData.line_user_id === 'object') {
                    // 如果是物件，嘗試轉換為字串.
                    lineUserId = String(friendData.line_user_id.toString() !== '[object Object]'
                        ? friendData.line_user_id.toString()
                        : '');
                } else {
                    lineUserId = String(friendData.line_user_id);
                }
            }

            return lineUserId;
        },

        /**
         * 渲染客戶資訊
         */
        renderCustomerInfo: function () {

            if (!this.customerData) {
                this.hideCustomerInfo();
                return;
            }

            let html = '';

            // 根據是否有 wp_user_id 決定顯示內容
            if (this.customerData.wp_user_id) {
                // 顯示會員基本資訊
                const basicInfo = this.renderBasicInfo();
                html += basicInfo;

                // 顯示訂單資訊
                if (this.orderManager) {
                    const orderInfo = this.orderManager.renderOrderInfo(this.customerData.orders || []);
                    html += orderInfo;
                } else {
                    console.warn('[CustomerInfoCore] orderManager 未初始化');
                }
            } else {
                // 顯示會員綁定下拉選單
                if (this.memberBindingManager) {
                    const memberBinding = this.memberBindingManager.renderMemberBinding();
                    html += memberBinding;
                } else {
                    console.warn('[CustomerInfoCore] memberBindingManager 未初始化');
                }
            }

            // 不管是否有 wp_user_id，都顯示標籤與備註區塊
            if (this.tagsNotesManager) {
                const tagsSection = this.tagsNotesManager.renderTagsSection(this.customerData.tags || []);
                const notesSection = this.tagsNotesManager.renderNotesSection(this.customerData.notes || []);
                html += tagsSection;
                html += notesSection;
            } else {
                console.warn('[CustomerInfoCore] tagsNotesManager 未初始化');
            }


            // 平滑切換內容
            if (this.customerInfoContent.is(':visible') && this.customerInfoContent.children().length > 0) {
                this.customerInfoContent.fadeOut(150, () => {
                    this.customerInfoContent.html(html).fadeIn(150, () => {
                        // 在內容顯示後初始化功能
                        this.initializeAfterRender();
                    });
                });
            } else {
                this.customerInfoContent.html(html);
                this.initializeAfterRender();
            }

            this.showCustomerInfo();
        },

        /**
         * 渲染基本資訊區塊
         * @returns {string} HTML 字串
         */
        renderBasicInfo: function () {
            const template = $('#customer-basic-info-template').html();
            if (!template) {
                console.error('CustomerInfoCore: 客戶基本資訊模板未找到');
                return '';
            }

            const userData = this.customerData.user_data || {};

            return template
                .replace(/\{name\}/g, UIHelpers.escapeHtml(userData.display_name || ''))
                .replace(/\{email\}/g, UIHelpers.escapeHtml(userData.user_email || ''))
                .replace(/\{phone\}/g, UIHelpers.escapeHtml(userData.billing_phone || userData.phone || ''))
                .replace(/\{joinDate\}/g, UIHelpers.escapeHtml(userData.user_registered || ''));
        },

        /**
         * 渲染完成後的初始化工作
         */
        initializeAfterRender: function () {

            // 設定各子模組的當前好友和客戶資料
            if (this.orderManager) {
                this.orderManager.setCustomerData(this.customerData);
            }

            if (this.tagsNotesManager) {
                this.tagsNotesManager.setCurrentFriend(this.currentFriend);
            }

            if (this.memberBindingManager) {
                this.memberBindingManager.setCurrentFriend(this.currentFriend);
            }

            // 初始化會員綁定功能
            if (!this.customerData.wp_user_id && this.memberBindingManager) {
                this.memberBindingManager.init();
            }

            // 初始化標籤和備註功能
            if (this.tagsNotesManager) {
                this.tagsNotesManager.init();
            }

            // 初始化訂單功能
            if (this.customerData.wp_user_id && this.orderManager) {
                this.orderManager.init();
            }

            // 初始化展開收合功能
            UIHelpers.initCollapsibleSections(this.customerInfoContent);

            // 設置拖曳屬性
            this.setupSortableAttributes();

            // 載入已儲存的區塊排序
            this.applySavedSectionOrder();

        },

        /**
         * 初始化拖曳排序事件（一次性設置）
         */
        initSortableEvents: function () {
            // 先清理舊事件，避免重複綁定
            $(document).off('dragstart.customer-sortable')
                .off('dragend.customer-sortable')
                .off('dragenter.customer-sortable')
                .off('dragleave.customer-sortable')
                .off('dragover.customer-sortable')
                .off('drop.customer-sortable');

            // 使用 document 作為事件委派容器，確保事件不會丟失
            const eventContainer = $(document);


            // 拖曳開始 - 只允許從標題開始拖曳
            eventContainer.off('dragstart.customer-sortable').on('dragstart.customer-sortable', '.collapsible-header', (e) => {
                const draggedHeader = $(e.currentTarget);
                const draggedElement = draggedHeader.closest('.info-section');
                draggedElement.addClass('dragging');

                // 儲存被拖曳的元素資訊
                e.originalEvent.dataTransfer.setData('text/html', draggedElement[0].outerHTML);
                e.originalEvent.dataTransfer.effectAllowed = 'move';

                // 設定拖曳時的視覺效果
                setTimeout(() => {
                    draggedElement.css('opacity', '0.5');
                }, 0);
            });

            // 拖曳結束
            eventContainer.off('dragend.customer-sortable').on('dragend.customer-sortable', '.collapsible-header', (e) => {
                const draggedElement = $(e.currentTarget).closest('.info-section');
                draggedElement.removeClass('dragging').css('opacity', '');
                $('.info-section').removeClass('drag-over');
            });

            // 拖曳進入其他元素
            eventContainer.off('dragenter.customer-sortable').on('dragenter.customer-sortable', '.collapsible-header', (e) => {
                e.preventDefault();
                const targetElement = $(e.currentTarget).closest('.info-section');
                if (!targetElement.hasClass('dragging')) {
                    targetElement.addClass('drag-over');
                }
            });

            // 拖曳離開
            eventContainer.off('dragleave.customer-sortable').on('dragleave.customer-sortable', '.collapsible-header', (e) => {
                const targetElement = $(e.currentTarget).closest('.info-section');
                targetElement.removeClass('drag-over');
            });

            // 拖曳經過
            eventContainer.off('dragover.customer-sortable').on('dragover.customer-sortable', '.collapsible-header', (e) => {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
            });

            // 放下 - 使用箭頭函數保持 this 上下文
            eventContainer.off('drop.customer-sortable').on('drop.customer-sortable', '.collapsible-header', (e) => {
                e.preventDefault();
                const draggedElement = $('.info-section.dragging');
                const targetElement = $(e.currentTarget).closest('.info-section');

                if (draggedElement.length && !targetElement.hasClass('dragging')) {
                    // 計算插入位置
                    const rect = targetElement[0].getBoundingClientRect();
                    const mouseY = e.originalEvent.clientY;
                    const elementMiddle = rect.top + rect.height / 2;

                    if (mouseY < elementMiddle) {
                        // 插入到目標元素前面
                        targetElement.before(draggedElement);
                    } else {
                        // 插入到目標元素後面
                        targetElement.after(draggedElement);
                    }

                    // 儲存新的排序 - 確保 this 上下文正確
                    if (typeof this.saveSectionOrder === 'function') {
                        this.saveSectionOrder();
                    } else {
                        console.error('CustomerInfoCore: saveSectionOrder 不是函數，this 上下文錯誤');
                    }
                }

                // 清理樣式
                $('.info-section').removeClass('drag-over dragging').css('opacity', '');
            });
        },

        /**
         * 設置拖曳屬性（每次渲染後調用）
         */
        setupSortableAttributes: function () {
            // 為每個 info-section 添加拖曳屬性
            const sections = this.customerInfoContent.find('.info-section');

            sections.each(function (index) {
                const $section = $(this);
                $section.addClass('sortable-item');

                // 為標題區域添加拖曳樣式
                const header = $section.find('.collapsible-header');
                if (header.length > 0) {
                    // 先解除舊的事件綁定，避免累積
                    header.off('mouseenter.drag-header mouseleave.drag-header');

                    // 只在標題添加拖曳屬性和提示
                    header.attr('draggable', 'true')
                        .attr('title', '拖曳此區塊來重新排序')
                        .css({
                            'cursor': 'grab',
                            'transition': 'background-color 0.2s ease'
                        })
                        .on('mouseenter.drag-header', function () {
                            $(this).css('background-color', 'rgba(0, 123, 255, 0.05)');
                        })
                        .on('mouseleave.drag-header', function () {
                            $(this).css('background-color', '');
                        });

                    // 為拖曳圖示添加視覺提示樣式
                    const menuIcon = $section.find('.dashicons-menu');
                    if (menuIcon.length > 0) {
                        menuIcon.css({
                            'padding': '3px',
                            'margin-right': '8px',
                            'border-radius': '3px',
                            'background-color': 'rgba(0, 123, 255, 0.1)',
                            'color': '#007cba'
                        });
                    }
                }
            });
        },

        /**
         * 儲存區塊排序
         */
        saveSectionOrder: function () {
            const userId = (typeof otzChatConfig !== 'undefined' && otzChatConfig.current_user) ?
                otzChatConfig.current_user.id : 'default';
            const storageKey = `otz_customer_sections_order_${userId}`;

            UIHelpers.saveSectionOrder(this.customerInfoContent, storageKey);
        },

        /**
         * 載入並應用已儲存的區塊排序
         */
        applySavedSectionOrder: function () {
            const userId = (typeof otzChatConfig !== 'undefined' && otzChatConfig.current_user) ?
                otzChatConfig.current_user.id : 'default';
            const storageKey = `otz_customer_sections_order_${userId}`;

            UIHelpers.applySavedSectionOrder(this.customerInfoContent, storageKey);
        },

        /**
         * 處理綁定更新事件
         * @param {Event} event - 自定義事件
         * @param {string} friendId - 好友 ID
         * @param {string} wpUserId - WordPress 使用者 ID
         */
        handleBindingUpdated: function (event, friendId, wpUserId) {

            if (this.currentFriend && this.currentFriend.id == friendId) {
                this.currentFriend.wp_user_id = wpUserId;
                this.loadCustomerInfo(this.currentFriend);
            }
        },

        /**
         * 處理標籤更新事件
         * @param {Event} event - 自定義事件
         * @param {string} friendId - 好友 ID
         */
        handleTagsUpdated: function (event, friendId) {
            // 可以在這裡處理標籤更新的後續動作
        },

        /**
         * 處理備註更新事件
         * @param {Event} event - 自定義事件
         * @param {string} friendId - 好友 ID
         * @param {Array} notes - 備註陣列
         */
        handleNotesUpdated: function (event, friendId, notes) {
            // 更新本地客戶資料中的備註
            if (this.customerData) {
                this.customerData.notes = Array.isArray(notes) ? notes : [];
            }
        },

        /**
         * 處理重新載入要求事件
         */
        handleReloadRequired: function () {
            if (this.currentFriend) {
                this.loadCustomerInfo(this.currentFriend);
            }
        },

        /**
         * 顯示客戶資訊面板
         */
        showCustomerInfo: function () {
            this.noCustomerSelected.hide();
            this.customerInfoContent.show();
        },

        /**
         * 隱藏客戶資訊面板
         */
        hideCustomerInfo: function () {
            this.customerInfoContent.hide();
            this.noCustomerSelected.show();
            this.currentFriend = null;
            this.customerData = null;
        },

        /**
         * 同步狀態 (供外部元件調用)
         * @param {object} state - 狀態物件
         */
        syncState: function (state) {
            if (state.currentFriend && (!this.currentFriend || state.currentFriend !== this.currentFriend.id)) {
                // 如果選中的好友發生變化，重新載入客戶資訊
                const friendData = state.friendData || {id: state.currentFriend};
                this.handleFriendSelected(null, friendData);
            }
        },

        /**
         * 銷毀元件
         */
        destroy: function () {
            $(document).off('friend:selected', this.handleFriendSelected);
            $(document).off('customer:binding-updated', this.handleBindingUpdated);
            $(document).off('customer:tags-updated', this.handleTagsUpdated);
            $(document).off('customer:notes-updated', this.handleNotesUpdated);
            $(document).off('customer:reload-required', this.handleReloadRequired);

            // 清理拖曳事件
            $(document).off('dragstart.customer-sortable');
            $(document).off('dragend.customer-sortable');
            $(document).off('dragenter.customer-sortable');
            $(document).off('dragleave.customer-sortable');
            $(document).off('dragover.customer-sortable');
            $(document).off('drop.customer-sortable');
        }
    };

})(jQuery);