/**
 * OrderChatz 面板拖曳調整功能
 *
 * 提供三欄式聊天介面的拖曳調整寬度功能
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 面板拖曳調整管理器構造函數
     */
    function PanelResizer() {
        return {
        /**
         * 初始化拖曳功能
         */
        init: function () {
            this.container = $('#chat-container');
            this.leftPanel = $('#friend-list-panel');
            this.centerPanel = $('#chat-area-panel');
            this.rightPanel = $('#customer-info-panel');
            this.leftResizer = $('#left-resizer');
            this.rightResizer = $('#right-resizer');

            // 拖曳狀態
            this.isResizing = false;
            this.currentResizer = null;
            this.startX = 0;
            this.startLeftWidth = 0;
            this.startRightWidth = 0;
            this.containerWidth = 0;

            // 綁定事件
            this.bindEvents();

            // 載入保存的寬度設定
            this.loadPanelWidths();
        },

        /**
         * 綁定事件監聽器
         */
        bindEvents: function () {
            const self = this;

            // 左側拖曳器事件
            this.leftResizer.on('mousedown', function (e) {
                self.startResize(e, 'left');
            });

            // 右側拖曳器事件
            this.rightResizer.on('mousedown', function (e) {
                self.startResize(e, 'right');
            });

            // 全域滑鼠事件
            $(document).on('mousemove', function (e) {
                self.doResize(e);
            });

            $(document).on('mouseup', function () {
                self.stopResize();
            });

            // 防止文字選取
            $(document).on('selectstart', function (e) {
                if (self.isResizing) {
                    e.preventDefault();
                }
            });

            // 視窗調整大小時重新計算
            $(window).on('resize', function () {
                self.updateContainerWidth();
            });
        },

        /**
         * 開始拖曳調整
         * @param {Event} e - 滑鼠事件
         * @param {string} direction - 拖曳方向 ('left' 或 'right')
         */
        startResize: function (e, direction) {
            e.preventDefault();
            
            this.isResizing = true;
            this.currentResizer = direction;
            this.startX = e.pageX;
            
            // 更新容器寬度
            this.updateContainerWidth();
            
            // 記錄初始寬度
            this.startLeftWidth = this.leftPanel.outerWidth();
            this.startRightWidth = this.rightPanel.outerWidth();
            
            // 添加拖曳樣式
            this.container.addClass('resizing');
            if (direction === 'left') {
                this.leftResizer.addClass('dragging');
            } else {
                this.rightResizer.addClass('dragging');
            }
            
            // 添加全域游標樣式
            $('body').addClass('col-resize');
        },

        /**
         * 執行拖曳調整
         * @param {Event} e - 滑鼠事件
         */
        doResize: function (e) {
            if (!this.isResizing) return;

            const deltaX = e.pageX - this.startX;
            const minWidth = 200;
            const maxWidthPercent = 50;

            if (this.currentResizer === 'left') {
                // 調整左側面板寬度
                let newLeftWidth = this.startLeftWidth + deltaX;
                const maxLeftWidth = this.containerWidth * maxWidthPercent / 100;
                
                // 限制最小和最大寬度
                newLeftWidth = Math.max(minWidth, Math.min(newLeftWidth, maxLeftWidth));
                
                // 計算百分比
                const leftPercent = (newLeftWidth / this.containerWidth) * 100;
                
                // 應用新寬度
                this.leftPanel.css('flex-basis', leftPercent + '%');
                
            } else if (this.currentResizer === 'right') {
                // 調整右側面板寬度
                let newRightWidth = this.startRightWidth - deltaX;
                const maxRightWidth = this.containerWidth * maxWidthPercent / 100;
                
                // 限制最小和最大寬度
                newRightWidth = Math.max(minWidth, Math.min(newRightWidth, maxRightWidth));
                
                // 計算百分比
                const rightPercent = (newRightWidth / this.containerWidth) * 100;
                
                // 應用新寬度
                this.rightPanel.css('flex-basis', rightPercent + '%');
            }
        },

        /**
         * 停止拖曳調整
         */
        stopResize: function () {
            if (!this.isResizing) return;

            this.isResizing = false;
            this.currentResizer = null;
            
            // 移除拖曳樣式
            this.container.removeClass('resizing');
            this.leftResizer.removeClass('dragging');
            this.rightResizer.removeClass('dragging');
            $('body').removeClass('col-resize');
            
            // 保存當前寬度設定
            this.savePanelWidths();
        },

        /**
         * 更新容器寬度
         */
        updateContainerWidth: function () {
            this.containerWidth = this.container.outerWidth();
        },

        /**
         * 保存面板寬度到 localStorage
         */
        savePanelWidths: function () {
            if (!this.containerWidth) return;

            const leftWidth = this.leftPanel.outerWidth();
            const rightWidth = this.rightPanel.outerWidth();
            
            const settings = {
                leftPercent: (leftWidth / this.containerWidth) * 100,
                rightPercent: (rightWidth / this.containerWidth) * 100,
                timestamp: Date.now()
            };
            
            try {
                localStorage.setItem('orderchatz_panel_widths', JSON.stringify(settings));
            } catch (e) {
                console.warn('無法保存面板寬度設定:', e);
            }
        },

        /**
         * 從 localStorage 載入面板寬度
         */
        loadPanelWidths: function () {
            try {
                const saved = localStorage.getItem('orderchatz_panel_widths');
                if (!saved) return;
                
                const settings = JSON.parse(saved);
                
                // 檢查設定是否過期（7天）
                if (Date.now() - settings.timestamp > 7 * 24 * 60 * 60 * 1000) {
                    localStorage.removeItem('orderchatz_panel_widths');
                    return;
                }
                
                // 應用保存的寬度
                if (settings.leftPercent) {
                    this.leftPanel.css('flex-basis', settings.leftPercent + '%');
                }
                if (settings.rightPercent) {
                    this.rightPanel.css('flex-basis', settings.rightPercent + '%');
                }
                
            } catch (e) {
                console.warn('無法載入面板寬度設定:', e);
                localStorage.removeItem('orderchatz_panel_widths');
            }
        },

        /**
         * 重設為預設寬度
         */
        resetToDefault: function () {
            this.leftPanel.css('flex-basis', '25%');
            this.rightPanel.css('flex-basis', '25%');
            localStorage.removeItem('orderchatz_panel_widths');
        }
        };
    }

    // 將構造函數暴露到全域
    window.PanelResizer = PanelResizer;

})(jQuery);