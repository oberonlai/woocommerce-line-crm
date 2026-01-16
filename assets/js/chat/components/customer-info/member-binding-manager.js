/**
 * OrderChatz 會員綁定管理模組
 *
 * 處理 LINE 好友與 WordPress 會員的綁定功能
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 會員綁定管理器
     */
    window.MemberBindingManager = function (container) {
        this.container = container;
    };

    MemberBindingManager.prototype = {
        /**
         * 渲染會員綁定區塊
         * @returns {string} HTML 字串
         */
        renderMemberBinding: function () {
            return `
                <div class="customer-binding info-section">
                    <div class="info-header collapsible-header" data-section="member-binding">
                        <span class="dashicons dashicons-menu"></span>
                        <span class="dashicons dashicons-admin-users"></span>
                        <h4>會員綁定</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-up-alt2"></span>
                    </div>
                    <div class="binding-content collapsible-content" data-section="member-binding">
                        <p class="binding-description">此好友尚未綁定網站會員，請使用電子郵件或姓名搜尋要綁定的會員：</p>
                        <div class="member-select-container">
                            <select class="member-select" id="customer-member-select" style="width: 100%;">
                                <option value="">選擇會員...</option>
                            </select>
                            <div class="binding-actions" style="margin-top: 10px;">
                                <button type="button" class="button button-primary save-member-binding" disabled>儲存綁定</button>
                                <button type="button" class="button cancel-member-binding" style="margin-left: 5px;">取消</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * 初始化會員綁定功能
         */
        init: function () {
            const memberSelect = this.container.find('#customer-member-select');
            const saveButton = this.container.find('.save-member-binding');
            const cancelButton = this.container.find('.cancel-member-binding');

            // 初始化 Select2
            this.initSelect2(memberSelect);

            // 監聽選擇變化
            memberSelect.on('change', () => {
                saveButton.prop('disabled', !memberSelect.val());
            });

            // 儲存綁定
            saveButton.on('click', () => {
                this.saveMemberBinding(memberSelect.val());
            });

            // 取消綁定
            cancelButton.on('click', () => {
                memberSelect.val('').trigger('change');
            });
        },

        /**
         * 初始化 Select2 下拉選單
         * @param {jQuery} memberSelect - 選單元素
         */
        initSelect2: function (memberSelect) {
            const ajaxConfig = {
                url: otzChatConfig.ajax_url,
                dataType: 'json',
                delay: 250,
                data: (params) => ({
                    action: 'otz_search_users',
                    search: params.term,
                    nonce: otzChatConfig.search_nonce
                }),
                processResults: (data) => ({
                    results: data.success ? data.data : []
                }),
                cache: true
            };

            const selectConfig = {
                ajax: ajaxConfig,
                minimumInputLength: 1,
                placeholder: '搜尋會員...',
                allowClear: true
            };

            // 優先使用 selectWoo，fallback 到 select2
            if (typeof $.fn.selectWoo !== 'undefined') {
                memberSelect.selectWoo(selectConfig);
            } else if (typeof $.fn.select2 !== 'undefined') {
                memberSelect.select2(selectConfig);
            }
        },

        /**
         * 儲存會員綁定
         * @param {string} wpUserId - WordPress 使用者 ID
         */
        saveMemberBinding: function (wpUserId) {

            if (!wpUserId || !this.currentFriend) {
                console.log('MemberBindingManager: 缺少必要參數', {
                    wpUserId: wpUserId,
                    currentFriend: this.currentFriend
                });
                return;
            }

            // 顯示載入狀態
            const saveButton = this.container.find('.save-member-binding');
            const originalText = saveButton.text();
            saveButton.prop('disabled', true).text('儲存中...');

            const friendId = this.currentFriend.id;

            const data = {
                action: 'otz_update_user_binding',
                nonce: otzChatConfig.binding_nonce,
                friend_id: friendId,
                wp_user_id: wpUserId
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {

                    if (response.success) {
                        // 觸發綁定更新事件
                        $(document).trigger('customer:binding-updated', [this.currentFriend.id, wpUserId]);

                        // 觸發重新載入事件
                        $(document).trigger('customer:reload-required');

                        // 顯示成功訊息
                        alert('會員綁定成功！');
                    } else {
                        console.error('MemberBindingManager: 綁定失敗', response);
                        alert('綁定失敗：' + (response.data?.message || '未知錯誤'));

                        // 恢復按鈕狀態
                        saveButton.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('MemberBindingManager: AJAX 請求失敗', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    alert('綁定時發生網路錯誤：' + error);

                    // 恢復按鈕狀態
                    saveButton.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * 設定當前好友
         * @param {object} friend - 好友資料
         */
        setCurrentFriend: function (friend) {
            this.currentFriend = friend;
        }
    };

})(jQuery);