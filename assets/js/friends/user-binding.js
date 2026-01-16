/**
 * 好友會員綁定內嵌編輯功能 JavaScript
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    class UserBinding {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // 表格行 hover 事件顯示/隱藏編輯按鈕
            $(document).on('mouseenter', 'tr', (e) => {
                $(e.currentTarget).find('.edit-user-binding').css('opacity', '1');
            });
            
            $(document).on('mouseleave', 'tr', (e) => {
                $(e.currentTarget).find('.edit-user-binding').css('opacity', '0');
            });

            // 編輯按鈕點擊事件
            $(document).on('click', '.edit-user-binding', (e) => {
                e.preventDefault();
                const button = $(e.target).closest('.edit-user-binding');
                const friendId = button.data('friend-id');
                this.showEditMode(friendId);
            });

            // 取消按鈕點擊事件
            $(document).on('click', '.cancel-user-binding', (e) => {
                e.preventDefault();
                const container = $(e.target).closest('.wp-user-binding');
                this.hideEditMode(container);
            });

            // 儲存按鈕點擊事件
            $(document).on('click', '.save-user-binding', (e) => {
                e.preventDefault();
                const friendId = $(e.target).data('friend-id');
                this.saveUserBinding(friendId);
            });

            // 初始化所有 select2 下拉選單
            this.initializeSelect2();
        }

        showEditMode(friendId) {
            const container = $(`.wp-user-binding[data-friend-id="${friendId}"]`);
            const displayDiv = container.find('.wp-user-display');
            const editDiv = container.find('.wp-user-edit');
            const select = container.find('.user-select');

            // 隱藏顯示區域，顯示編輯區域
            displayDiv.hide();
            editDiv.show();

            // 初始化 select2（如果尚未初始化）
            if (!select.hasClass('select2-hidden-accessible') && !select.hasClass('selectWoo-hidden-accessible')) {
                this.initializeUserSelect(select);
            }

            // 聚焦到選擇框
            const selectMethod = $.fn.selectWoo || $.fn.select2;
            if (selectMethod) {
                selectMethod.call(select, 'open');
            }
        }

        hideEditMode(container) {
            const displayDiv = container.find('.wp-user-display');
            const editDiv = container.find('.wp-user-edit');

            // 顯示顯示區域，隱藏編輯區域
            editDiv.hide();
            displayDiv.show();
        }

        saveUserBinding(friendId) {
            const container = $(`.wp-user-binding[data-friend-id="${friendId}"]`);
            const select = container.find('.user-select');
            const saveBtn = container.find('.save-user-binding');
            const wpUserId = select.val();

            // 顯示載入狀態
            saveBtn.prop('disabled', true).text('儲存中...');

            // 發送 AJAX 請求
            $.ajax({
                url: otz_user_binding.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_update_user_binding',
                    nonce: otz_user_binding.nonce,
                    friend_id: friendId,
                    wp_user_id: wpUserId
                },
                success: (response) => {
                    if (response.success) {
                        // 更新顯示區域
                        this.updateDisplayArea(container, response.data.display_data);
                        
                        // 隱藏編輯模式
                        this.hideEditMode(container);
                        
                        // 顯示成功訊息
                        this.showMessage(response.data.message, 'success');
                    } else {
                        this.showMessage(response.data.message || '儲存失敗', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('儲存時發生錯誤: ' + error, 'error');
                },
                complete: () => {
                    // 恢復按鈕狀態
                    saveBtn.prop('disabled', false).text('儲存');
                }
            });
        }

        updateDisplayArea(container, displayData) {
            const displayDiv = container.find('.wp-user-display');
            
            let displayHtml = '';
            if (displayData && displayData.display_name) {
                displayHtml = `<a href="${displayData.edit_link}" target="_blank" class="user-link">${displayData.display_name}</a>`;
            } else {
                displayHtml = '<span class="user-text" style="color: #999;">尚未綁定</span>';
            }
            
            const friendId = container.data('friend-id');
            displayHtml += ` <button type="button" class="button button-small edit-user-binding" data-friend-id="${friendId}" style="margin-left: 5px; opacity: 0; transition: opacity 0.3s ease;" title="${otz_user_binding.messages.edit_tooltip}"><span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span></button>`;
            
            displayDiv.html(displayHtml);
        }

        initializeSelect2() {
            // 等待 DOM 完全載入後初始化所有 select
            $(document).ready(() => {
                $('.user-select').each((index, element) => {
                    if (!$(element).hasClass('select2-hidden-accessible') && !$(element).hasClass('selectWoo-hidden-accessible')) {
                        this.initializeUserSelect($(element));
                    }
                });
            });
        }

        initializeUserSelect(selectElement) {
            // 檢查是否有 selectWoo（WooCommerce）或 select2
            const selectMethod = $.fn.selectWoo || $.fn.select2;
            
            if (!selectMethod) {
                console.error('Select2 or SelectWoo not available');
                return;
            }
            
            selectMethod.call(selectElement, {
                placeholder: otz_user_binding.placeholder,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: otz_user_binding.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: (params) => {
                        return {
                            action: 'otz_search_users',
                            search: params.term,
                            nonce: otz_user_binding.search_nonce
                        };
                    },
                    processResults: (data) => {
                        if (data.success) {
                            return {
                                results: data.data
                            };
                        } else {
                            return {
                                results: []
                            };
                        }
                    },
                    cache: true
                },
                minimumInputLength: 1,
                language: {
                    inputTooShort: () => {
                        return otz_user_binding.messages.input_too_short;
                    },
                    searching: () => {
                        return otz_user_binding.messages.searching;
                    },
                    noResults: () => {
                        return otz_user_binding.messages.no_results;
                    },
                    errorLoading: () => {
                        return otz_user_binding.messages.error_loading;
                    }
                }
            });
        }

        showMessage(message, type = 'info') {
            // 移除現有的訊息
            $('.otz-inline-message').remove();
            
            // 創建新的訊息元素
            const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
            const messageHtml = `
                <div class="notice ${messageClass} is-dismissible otz-inline-message" style="margin: 10px 0;">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">關閉此通知</span>
                    </button>
                </div>
            `;
            
            // 將訊息插入到頁面頂部
            $('.wrap h1').after(messageHtml);
            
            // 3秒後自動消失
            setTimeout(() => {
                $('.otz-inline-message').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
            
            // 綁定關閉按鈕事件
            $('.otz-inline-message .notice-dismiss').on('click', function() {
                $(this).closest('.otz-inline-message').fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    }

    // 當 DOM 準備好時初始化
    $(document).ready(function () {
        new UserBinding();
    });

})(jQuery);