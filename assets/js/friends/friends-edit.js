/**
 * 好友編輯頁面 JavaScript
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    class FriendsEdit {
        constructor() {
            this.init();
        }

        init() {
            this.initSelectWoo();
            this.bindEvents();
        }

        initSelectWoo() {
            const currentUserId = $('#wp_user_id').val();
            const currentUserDisplay = $('#current-user-display span').text();

            // 檢查 SelectWoo 是否可用
            if (typeof $.fn.selectWoo === 'undefined') {
                return;
            }

            // 初始化 SelectWoo
            $('#wp_user_select').selectWoo({
                placeholder: otz_friends_edit.placeholder,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            action: 'otz_search_users',
                            search: params.term || '',
                            nonce: otz_friends_edit.nonce
                        };
                    },
                    processResults: function (data) {
                        if (data.success) {
                            return {
                                results: data.data
                            };
                        }
                        return {
                            results: []
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1,
                escapeMarkup: function (markup) {
                    return markup;
                }
            });

            // 如果有現有用戶，設定選中狀態
            if (currentUserId && currentUserDisplay) {
                const option = new Option(currentUserDisplay, currentUserId, true, true);
                $('#wp_user_select').append(option).trigger('change');
            }
        }

        bindEvents() {
            // 監聽 SelectWoo 變更事件
            $('#wp_user_select').on('change', (e) => {
                const selectedValue = $(e.target).val();
                $('#wp_user_id').val(selectedValue || '');
            });

            // 清除選擇
            $('#wp_user_select').on('selectWoo:clear', () => {
                $('#wp_user_id').val('');
            });
        }
    }

    // 當 DOM 準備好時初始化
    $(document).ready(function () {
        new FriendsEdit();
    });

})(jQuery);