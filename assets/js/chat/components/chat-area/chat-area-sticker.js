/**
 * OrderChatz 貼圖選擇器
 *
 * 處理貼圖選擇和發送功能
 *
 * @package OrderChatz
 * @since 1.0.0
 */

window.ChatAreaSticker = (function ($) {
    'use strict';

    // LINE 官方貼圖資料
    const STICKER_PACKAGES = {
        moon: [
            {packageId: '446', stickerId: '1988'},
            {packageId: '446', stickerId: '1989'},
            {packageId: '446', stickerId: '1990'},
            {packageId: '446', stickerId: '1991'},
            {packageId: '446', stickerId: '1992'},
            {packageId: '446', stickerId: '1993'},
            {packageId: '446', stickerId: '1994'},
            {packageId: '446', stickerId: '1995'},
            {packageId: '446', stickerId: '1996'},
            {packageId: '446', stickerId: '1997'},
            {packageId: '446', stickerId: '1998'},
            {packageId: '446', stickerId: '2000'},
            {packageId: '446', stickerId: '2001'},
            {packageId: '446', stickerId: '2002'},
            {packageId: '446', stickerId: '2003'},
            {packageId: '446', stickerId: '2004'},
            {packageId: '446', stickerId: '2005'},
            {packageId: '446', stickerId: '2006'},
            {packageId: '446', stickerId: '2007'},
            {packageId: '446', stickerId: '2008'},
            {packageId: '446', stickerId: '2009'},
            {packageId: '446', stickerId: '2010'},
            {packageId: '446', stickerId: '2011'},
            {packageId: '446', stickerId: '2012'},
            {packageId: '446', stickerId: '2013'},
            {packageId: '446', stickerId: '2014'},
            {packageId: '446', stickerId: '2015'},
            {packageId: '446', stickerId: '2016'},
            {packageId: '446', stickerId: '2017'},
            {packageId: '446', stickerId: '2018'},
            {packageId: '446', stickerId: '2019'},
            {packageId: '446', stickerId: '2020'},
            {packageId: '446', stickerId: '2021'},
            {packageId: '446', stickerId: '2022'},
            {packageId: '446', stickerId: '2023'},
            {packageId: '446', stickerId: '2024'},
            {packageId: '446', stickerId: '2025'},
            {packageId: '446', stickerId: '2026'},
            {packageId: '446', stickerId: '2027'},
        ],

        seasonal: [
            {packageId: '8525', stickerId: '16581290'},
            {packageId: '8525', stickerId: '16581291'},
            {packageId: '8525', stickerId: '16581292'},
            {packageId: '8525', stickerId: '16581293'},
            {packageId: '8525', stickerId: '16581294'},
            {packageId: '8525', stickerId: '16581295'},
            {packageId: '8525', stickerId: '16581296'},
            {packageId: '8525', stickerId: '16581297'},
            {packageId: '8525', stickerId: '16581298'},
            {packageId: '8525', stickerId: '16581299'},
            {packageId: '8525', stickerId: '16581300'},
            {packageId: '8525', stickerId: '16581301'},
            {packageId: '8525', stickerId: '16581302'},
            {packageId: '8525', stickerId: '16581303'},
            {packageId: '8525', stickerId: '16581304'},
            {packageId: '8525', stickerId: '16581305'},
            {packageId: '8525', stickerId: '16581306'},
            {packageId: '8525', stickerId: '16581307'},
            {packageId: '8525', stickerId: '16581308'},
            {packageId: '8525', stickerId: '16581309'},
            {packageId: '8525', stickerId: '16581310'},
            {packageId: '8525', stickerId: '16581311'},
            {packageId: '8525', stickerId: '16581312'},
            {packageId: '8525', stickerId: '16581313'},
        ],

        gif: [
            {packageId: '11538', stickerId: '51626494'},
            {packageId: '11538', stickerId: '51626495'},
            {packageId: '11538', stickerId: '51626496'},
            {packageId: '11538', stickerId: '51626497'},
            {packageId: '11538', stickerId: '51626498'},
            {packageId: '11538', stickerId: '51626499'},
            {packageId: '11538', stickerId: '51626500'},
            {packageId: '11538', stickerId: '51626501'},
            {packageId: '11538', stickerId: '51626502'},
            {packageId: '11538', stickerId: '51626503'},
            {packageId: '11538', stickerId: '51626504'},
            {packageId: '11538', stickerId: '51626505'},
            {packageId: '11538', stickerId: '51626506'},
            {packageId: '11538', stickerId: '51626507'},
            {packageId: '11538', stickerId: '51626508'},
            {packageId: '11538', stickerId: '51626509'},
            {packageId: '11538', stickerId: '51626510'},
            {packageId: '11538', stickerId: '51626511'},
            {packageId: '11538', stickerId: '51626512'},
            {packageId: '11538', stickerId: '51626513'},
            {packageId: '11538', stickerId: '51626514'},
            {packageId: '11538', stickerId: '51626515'},
            {packageId: '11538', stickerId: '51626516'},
            {packageId: '11538', stickerId: '51626517'},
            {packageId: '11538', stickerId: '51626518'},
            {packageId: '11538', stickerId: '51626519'},
            {packageId: '11538', stickerId: '51626520'},
            {packageId: '11538', stickerId: '51626521'},
            {packageId: '11538', stickerId: '51626522'},
            {packageId: '11538', stickerId: '51626523'},
            {packageId: '11538', stickerId: '51626524'},
            {packageId: '11538', stickerId: '51626525'},
            {packageId: '11538', stickerId: '51626526'},
            {packageId: '11538', stickerId: '51626527'},
            {packageId: '11538', stickerId: '51626528'},
            {packageId: '11538', stickerId: '51626529'},
            {packageId: '11538', stickerId: '51626530'},
            {packageId: '11538', stickerId: '51626531'},
            {packageId: '11538', stickerId: '51626532'},
            {packageId: '11538', stickerId: '51626533'},
        ]
    };

    // 私有變數
    let isInitialized = false;
    let currentCategory = 'moon';
    let isPickerVisible = false;

    // DOM 元素
    let $stickerBtn = null;
    let $stickerPanel = null;
    let $stickerCategories = null;
    let $stickerGrid = null;

    /**
     * 初始化貼圖選擇器
     */
    function init() {
        if (isInitialized) {
            return;
        }

        // 快取 DOM 元素
        $stickerBtn = $('#sticker-picker-btn');
        $stickerPanel = $('#sticker-picker-panel');
        $stickerCategories = $('#sticker-categories');
        $stickerGrid = $('#sticker-grid');

        if ($stickerBtn.length === 0 || $stickerPanel.length === 0) {
            console.warn('ChatAreaSticker: 找不到必要的 DOM 元素');
            return;
        }

        // 綁定事件
        bindEvents();

        // 載入預設分類
        loadCategory(currentCategory);

        isInitialized = true;
    }

    /**
     * 綁定事件處理器
     */
    function bindEvents() {
        // 貼圖按鈕點擊事件
        $stickerBtn.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            togglePicker();
        });

        // 分類標籤點擊事件
        $stickerCategories.on('click', '.sticker-category-tab', function () {
            const category = $(this).data('category');
            switchCategory(category);
        });

        // 貼圖項目點擊事件
        $stickerGrid.on('click', '.sticker-item', function () {
            const packageId = $(this).data('package-id');
            const stickerId = $(this).data('sticker-id');
            const stickerName = $(this).data('sticker-name');
            selectSticker(packageId, stickerId, stickerName);
        });

        // 點擊面板外部關閉選擇器
        $(document).on('click', function (e) {
            if (isPickerVisible && !$stickerPanel.is(e.target) && $stickerPanel.has(e.target).length === 0 && !$stickerBtn.is(e.target)) {
                hidePicker();
            }
        });

        // ESC 鍵關閉選擇器
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && isPickerVisible) { // ESC
                hidePicker();
            }
        });
    }

    /**
     * 切換貼圖選擇器顯示/隱藏
     */
    function togglePicker() {
        if (isPickerVisible) {
            hidePicker();
        } else {
            showPicker();
        }
    }

    /**
     * 顯示貼圖選擇器
     */
    function showPicker() {
        if (isPickerVisible) {
            return;
        }

        $stickerPanel.addClass('show');
        $stickerBtn.addClass('active');
        isPickerVisible = true;

        // 觸發自訂事件
        $(document).trigger('sticker-picker:show');
        $('#message-input,#message-templates-list').hide()
    }

    /**
     * 隱藏貼圖選擇器
     */
    function hidePicker() {
        if (!isPickerVisible) {
            return;
        }

        $stickerPanel.removeClass('show');
        $stickerBtn.removeClass('active');
        isPickerVisible = false;

        $('#message-input,#message-templates-list').show()

        // 觸發自訂事件
        $(document).trigger('sticker-picker:hide');
    }

    /**
     * 切換貼圖分類
     */
    function switchCategory(category) {
        if (category === currentCategory) {
            return;
        }

        // 更新分類標籤狀態
        $stickerCategories.find('.sticker-category-tab').removeClass('active');
        $stickerCategories.find('[data-category="' + category + '"]').addClass('active');

        // 載入新分類
        currentCategory = category;
        loadCategory(category);
    }

    /**
     * 載入指定分類的貼圖
     */
    function loadCategory(category) {
        const stickers = STICKER_PACKAGES[category] || [];

        if (stickers.length === 0) {
            showEmptyState();
            return;
        }

        renderStickers(stickers);
    }

    /**
     * 渲染貼圖網格
     */
    function renderStickers(stickers) {
        const $grid = $stickerGrid;
        $grid.empty();

        stickers.forEach(function (sticker) {
            const $item = createStickerItem(sticker);
            $grid.append($item);
        });
    }

    /**
     * 建立貼圖項目 DOM 元素
     */
    function createStickerItem(sticker) {
        const previewUrl = getStickerPreviewUrl(sticker.stickerId);

        return $('<button>')
            .addClass('sticker-item')
            .attr({
                'type': 'button',
                'data-package-id': sticker.packageId,
                'data-sticker-id': sticker.stickerId,
                'title': sticker.name,
                'aria-label': '傳送貼圖：' + sticker.name
            })
            .append(
                $('<img>')
                    .attr({
                        'src': previewUrl,
                        'loading': 'lazy'
                    })
                    .on('error', function () {
                        // 圖片載入失敗時的處理
                        $(this).closest('.sticker-item').addClass('sticker-error');
                    })
            );
    }

    /**
     * 生成貼圖預覽圖片 URL
     */
    function getStickerPreviewUrl(stickerId, platform) {
        platform = platform || 'android';
        return `https://stickershop.line-scdn.net/stickershop/v1/sticker/${stickerId}/${platform}/sticker.png`;
    }

    /**
     * 顯示空狀態
     */
    function showEmptyState() {
        $stickerGrid.html(
            '<div class="sticker-empty">' +
            '<p>此分類暫無可用貼圖</p>' +
            '</div>'
        );
    }

    /**
     * 添加暫時貼圖訊息
     */
    function addTempStickerMessage(packageId, stickerId) {
        if (!window.chatAreaInstance || !window.chatAreaInstance.chatMessages) {
            return null;
        }

        const tempId = 'temp-sticker-' + Date.now();
        const stickerUrl = getStickerPreviewUrl(stickerId);
        const currentTime = new Date().toLocaleTimeString('zh-TW', {
            hour: '2-digit',
            minute: '2-digit'
        });

        const stickerHtml = `
            <div class="message-bubble outgoing" style="opacity:0" data-temp-id="${tempId}">
                <div class="message-content">
                    <div class="message-sticker">
                        <img src="${stickerUrl}" alt="貼圖" style="max-width: 150px; max-height: 150px;" />
                    </div>
                </div>
                <div class="message-time">${currentTime}</div>
                <div class="message-status sending">發送中...</div>
            </div>
        `;

        window.chatAreaInstance.chatMessages.append(stickerHtml);

        // 立即捲動到底部
        if (window.ChatAreaUI && typeof window.ChatAreaUI.scrollToBottom === 'function') {
            window.ChatAreaUI.scrollToBottom(window.chatAreaInstance.chatMessages);
        }

        return tempId;
    }

    /**
     * 選擇並發送貼圖
     */
    function selectSticker(packageId, stickerId, stickerName) {
        // 隱藏選擇器
        hidePicker();

        // 立即添加暫時貼圖訊息
        const tempId = addTempStickerMessage(packageId, stickerId);

        // 觸發自訂事件，讓其他模組可以監聽
        $(document).trigger('sticker-picker:select', {
            packageId: packageId,
            stickerId: stickerId,
        });

        // 發送貼圖（傳入 tempId 用於後續處理）
        sendSticker(packageId, stickerId, tempId);
    }

    /**
     * 發送貼圖訊息
     */
    function sendSticker(packageId, stickerId, tempId = null) {
        const lineUserId = getCurrentFriendId();

        if (!lineUserId) {
            // 移除暫時訊息
            if (tempId) {
                $(`[data-temp-id="${tempId}"]`).remove();
            }
            if (window.ChatAreaUI && typeof window.ChatAreaUI.showMessage === 'function') {
                window.ChatAreaUI.showMessage('請先選擇一個好友', 'error');
            }
            return;
        }

        $.ajax({
            url: otzChatConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'otz_send_sticker_message',
                nonce: otzChatConfig.nonce,
                line_user_id: lineUserId,
                package_id: packageId,
                sticker_id: stickerId
            },
            success: function (response) {
                if (response.success) {
                    // 發送成功，移除暫時訊息
                    if (tempId) {
                        $(`[data-temp-id="${tempId}"]`).remove();
                    }

                    // 觸發訊息更新事件
                    $(document).trigger('chat:message-sent', {
                        type: 'sticker',
                        packageId: packageId,
                        stickerId: stickerId
                    });
                } else {
                    // 處理錯誤，標記暫時訊息為失敗
                    if (tempId) {
                        const $tempMessage = $(`[data-temp-id="${tempId}"]`);
                        $tempMessage.find('.message-status').text('發送失敗').removeClass('sending').addClass('failed');
                    }

                    const errorMessage = response.data && response.data.message ? response.data.message : '貼圖發送失敗';
                    if (window.ChatAreaUI && typeof window.ChatAreaUI.showMessage === 'function') {
                        window.ChatAreaUI.showMessage(errorMessage, 'error');
                    }
                }
            },
            error: function (xhr, status, error) {
                // 處理網路錯誤，標記暫時訊息為失敗
                if (tempId) {
                    const $tempMessage = $(`[data-temp-id="${tempId}"]`);
                    $tempMessage.find('.message-status').text('網路錯誤').removeClass('sending').addClass('failed');
                }

                console.error('發送貼圖時發生網路錯誤:', error);
                if (window.ChatAreaUI && typeof window.ChatAreaUI.showMessage === 'function') {
                    window.ChatAreaUI.showMessage('網路錯誤，請重試', 'error');
                }
            }
        });
    }

    /**
     * 獲取當前選中的好友 ID
     */
    function getCurrentFriendId() {
        if (window.chatAreaInstance &&
            window.chatAreaInstance.syncInstanceProperties) {
            window.chatAreaInstance.syncInstanceProperties();
        }
        if (window.chatAreaInstance && window.chatAreaInstance.currentLineUserId) {
            return window.chatAreaInstance.currentLineUserId;
        }

        console.warn('ChatAreaSticker: 無法取得當前好友 ID');
        return null;
    }

    /**
     * 銷毀貼圖選擇器
     */
    function destroy() {
        if (!isInitialized) {
            return;
        }

        // 解除事件綁定
        $stickerBtn.off('click');
        $stickerCategories.off('click');
        $stickerGrid.off('click');
        $(document).off('click keydown');

        // 隱藏面板
        hidePicker();

        // 重置狀態
        isInitialized = false;
        currentCategory = 'basic';
        isPickerVisible = false;

        console.log('ChatAreaSticker: 已銷毀');
    }

    // 公開 API
    return {
        init: init,
        destroy: destroy,
        toggle: togglePicker,
        show: showPicker,
        hide: hidePicker,
        switchCategory: switchCategory,
        sendSticker: sendSticker,
        getStickerPreviewUrl: getStickerPreviewUrl
    };

})(jQuery);