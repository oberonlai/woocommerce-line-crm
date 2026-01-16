/**
 * OrderChatz 聊天介面元件載入器
 *
 * 統一管理所有聊天介面元件的載入和初始化
 * 取代原本的大型 chat-components.js 檔案
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 元件載入器
     * 確保所有元件按正確順序載入並初始化
     */
    window.ChatComponentsLoader = {
        /**
         * 載入狀態
         */
        loadState: {
            friendList: false,
            chatArea: false,
            customerInfo: false,
            responsiveHandler: false,
            pollingManager: false,
            panelResizer: false
        },

        /**
         * 初始化所有元件
         */
        init: function () {

            // 檢查依賴項
            if (!this.checkDependencies()) {
                return false;
            }

            // 按順序載入元件
            this.loadComponents();

            return true;
        },

        /**
         * 檢查依賴項
         */
        checkDependencies: function () {
            const required = [
                'jQuery',
                'FriendListComponent',
                'ChatAreaComponent',
                'CustomerInfoComponent',
                'ResponsiveHandler',
                'PollingManager',
                'PanelResizer'
            ];

            // 檢查 ChatArea 模組依賴
            const chatAreaModules = [
                'ChatAreaCore',
                'ChatAreaMessages',
                'ChatAreaInput',
                'ChatAreaUI',
                'ChatAreaUtils'
            ];

            // 檢查 ChatArea 可選模組 (不是必須的)
            const chatAreaOptionalModules = [
                'ChatAreaProduct',
                'ChatAreaSticker'
            ];

            // 檢查 CustomerInfo 模組依賴
            const customerInfoModules = [
                'CustomerInfoCore',
                'UIHelpers',
                'OrderManager',
                'TagsNotesManager',
                'MemberBindingManager'
            ];

            // 檢查基本依賴
            for (let i = 0; i < required.length; i++) {
                if (typeof window[required[i]] === 'undefined') {
                    return false;
                }
            }

            // 檢查 ChatArea 模組依賴
            for (let i = 0; i < chatAreaModules.length; i++) {
                if (typeof window[chatAreaModules[i]] === 'undefined') {
                    return false;
                }
            }

            // 檢查 CustomerInfo 模組依賴
            for (let i = 0; i < customerInfoModules.length; i++) {
                if (typeof window[customerInfoModules[i]] === 'undefined') {
                    return false;
                }
            }

            return true;
        },

        /**
         * 載入所有元件
         */
        loadComponents: function () {
            try {
                // 確認元件類別已載入
                this.verifyComponentClasses();

                // 觸發元件載入完成事件
                $(document).trigger('chat:components:loaded');

            } catch (error) {
                $(document).trigger('chat:components:error', [error]);
            }
        },

        /**
         * 驗證元件類別
         */
        verifyComponentClasses: function () {
            const components = [
                'FriendListComponent',
                'ChatAreaComponent',
                'CustomerInfoComponent',
                'ResponsiveHandler',
                'PollingManager',
                'PanelResizer'
            ];

            components.forEach(componentName => {
                if (typeof window[componentName] !== 'function') {
                    throw new Error(`元件類別 ${componentName} 未正確載入`);
                }
                this.loadState[this.getStateKey(componentName)] = true;
            });
        },

        /**
         * 取得狀態鍵名
         */
        getStateKey: function (componentName) {
            const keyMap = {
                'FriendListComponent': 'friendList',
                'ChatAreaComponent': 'chatArea',
                'CustomerInfoComponent': 'customerInfo',
                'ResponsiveHandler': 'responsiveHandler',
                'PollingManager': 'pollingManager',
                'PanelResizer': 'panelResizer'
            };
            return keyMap[componentName] || componentName.toLowerCase();
        },

        /**
         * 取得載入狀態
         */
        getLoadState: function () {
            return this.loadState;
        },

        /**
         * 檢查是否所有元件都已載入
         */
        isAllLoaded: function () {
            return Object.values(this.loadState).every(loaded => loaded === true);
        }
    };

    // 在 DOM 準備完成後自動初始化
    $(document).ready(function () {
        // 稍微延遲以確保所有腳本都載入完成
        setTimeout(function () {
            ChatComponentsLoader.init();
        }, 100);
    });

})(jQuery);