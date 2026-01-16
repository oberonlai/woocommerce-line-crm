/**
 * 網站會員同步功能 JavaScript
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    class MemberSync {
        constructor() {
            this.modal = null;
            this.currentStep = 'info';
            this.syncInProgress = false;
            this.availableUsers = [];
            this.selectedUsers = [];
            this.init();
        }

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // 同步按鈕點擊事件
            $(document).on('click', '#sync-members-btn', () => {
                this.openModal();
            });

            // 關閉燈箱事件
            $(document).on('click', '.otz-modal-close, .otz-modal-backdrop', (e) => {
                const modal = $(e.target).closest('.otz-modal');
                if (modal.attr('id') === 'sync-members-modal') {
                    this.closeModal();
                }
            });

            // 下一步到選擇會員
            $(document).on('click', '#next-to-selection-btn', () => {
                this.showSelectionStep();
            });

            // 返回上一步
            $(document).on('click', '#back-to-info-btn', () => {
                this.showStep('info');
            });

            // 開始同步按鈕
            $(document).on('click', '#start-sync-btn', () => {
                this.startSync();
            });

            // 全選/取消全選
            $(document).on('change', '#select-all-users', (e) => {
                this.toggleSelectAll(e.target.checked);
            });

            // 個別用戶選擇
            $(document).on('change', '.user-checkbox', () => {
                this.updateSelectedUsers();
            });

            // 取消同步按鈕
            $(document).on('click', '#cancel-sync-btn', () => {
                this.cancelSync();
            });

            // 刷新頁面按鈕
            $(document).on('click', '#refresh-page-btn', () => {
                window.location.reload();
            });

            // ESC 鍵關閉燈箱
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.modal && this.modal.is(':visible')) {
                        this.closeModal();
                    }
                }
            });
        }

        openModal() {
            this.modal = $('#sync-members-modal');
            if (this.modal.length) {
                this.modal.fadeIn(300);
                this.showStep('info');
                $('body').addClass('modal-open');
                
                // 防止背景滾動
                $('body').css('overflow', 'hidden');
            }
        }

        closeModal() {
            if (this.syncInProgress) {
                if (!confirm(otz_friends_sync.messages.cancel_confirm)) {
                    return;
                }
                this.cancelSync();
            }

            if (this.modal) {
                this.modal.fadeOut(300);
                $('body').removeClass('modal-open');
                $('body').css('overflow', '');
                
                // 重置狀態
                setTimeout(() => {
                    this.resetModal();
                }, 300);
            }
        }

        showStep(step) {
            this.currentStep = step;
            
            // 隱藏所有步驟
            $('.sync-step').hide();
            $('.modal-actions').hide();
            
            // 顯示對應步驟
            switch (step) {
                case 'info':
                    $('#sync-info-step').show();
                    $('#sync-info-actions').show();
                    break;
                case 'selection':
                    $('#sync-selection-step').show();
                    $('#sync-selection-actions').show();
                    break;
                case 'progress':
                    $('#sync-progress-step').show();
                    $('#sync-progress-actions').show();
                    break;
                case 'complete':
                    $('#sync-complete-step').show();
                    $('#sync-complete-actions').show();
                    break;
            }
        }

        showSelectionStep() {
            this.showStep('selection');
            this.loadAvailableUsers();
        }

        loadAvailableUsers() {
            $('#users-loading').show();
            $('#users-list').hide();
            $('#no-users-message').hide();

            $.ajax({
                url: otz_friends_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_sync_users',
                    nonce: otz_friends_sync.nonce
                },
                success: (response) => {
                    $('#users-loading').hide();
                    
                    if (response.success) {
                        if (response.data && response.data.users) {
                            this.availableUsers = response.data.users;
                            
                            if (this.availableUsers.length > 0) {
                                this.renderUsersList();
                                $('#users-list').show();
                            } else {
                                this.showNoUsersMessage();
                            }
                        } else {
                            this.availableUsers = [];
                            $('#no-users-message').show();
                        }
                    } else {
                        this.availableUsers = [];
                        $('#no-users-message').show();
                        $('#no-users-message p').text(response.data?.message || '載入會員資料失敗');
                    }
                },
                error: (xhr, status, error) => {
                    $('#users-loading').hide();
                    this.availableUsers = [];
                    $('#no-users-message').show();
                    $('#no-users-message p').text(`載入會員資料時發生網路錯誤：${error}`);
                }
            });
        }

        renderUsersList() {
            let html = '';
            
            this.availableUsers.forEach(user => {
                const statusText = user.is_imported ? '已匯入' : '未匯入';
                const statusClass = user.is_imported ? 'status-imported' : 'status-new';
                
                html += `
                    <div class="user-item" data-user-id="${user.id}">
                        <div class="user-checkbox-container">
                            <input type="checkbox" class="user-checkbox" value="${user.id}" 
                                   ${user.is_imported ? 'disabled' : ''}>
                        </div>
                        <div class="user-avatar">
                            <img src="${user.avatar_url}" alt="${user.name}" style="width: 40px; height: 40px; border-radius: 50%;">
                        </div>
                        <div class="user-info">
                            <div class="user-name">${user.name}</div>
                            <div class="user-email">${user.email}</div>
                            <div class="user-line-id">LINE ID: ${user.line_user_id}</div>
                        </div>
                        <div class="user-status ${statusClass}">
                            ${statusText}
                        </div>
                    </div>
                `;
            });
            
            $('#users-list').html(html);
            this.updateSelectedUsers();
        }

        toggleSelectAll(checked) {
            $('.user-checkbox:not(:disabled)').prop('checked', checked);
            this.updateSelectedUsers();
        }


        updateSelectedUsers() {
            this.selectedUsers = [];
            $('.user-checkbox:checked').each((index, element) => {
                this.selectedUsers.push(parseInt($(element).val()));
            });
            
            const count = this.selectedUsers.length;
            $('#selected-count').text(`已選擇：${count} 位會員`);
            
            // 更新開始匯入按鈕狀態
            $('#start-sync-btn').prop('disabled', count === 0);
            
            // 更新全選checkbox狀態
            const totalCheckboxes = $('.user-checkbox:not(:disabled)').length;
            const checkedCount = $('.user-checkbox:checked').length;
            $('#select-all-users').prop('indeterminate', checkedCount > 0 && checkedCount < totalCheckboxes);
            $('#select-all-users').prop('checked', checkedCount === totalCheckboxes && totalCheckboxes > 0);
        }

        startSync() {
            if (this.selectedUsers.length === 0) {
                alert('請選擇要匯入的會員');
                return;
            }
            
            this.syncInProgress = true;
            this.showStep('progress');
            
            // 重置進度條
            this.updateProgress(10, otz_friends_sync.messages.preparing);
            
            // 發送真實的 AJAX 請求
            this.performSync();
        }

        cancelSync() {
            this.syncInProgress = false;
            // 這裡會加入取消同步的邏輯
            this.closeModal();
        }

        performSync() {
            // 更新進度狀態
            this.updateProgress(25, otz_friends_sync.messages.scanning);
            
            // 發送 AJAX 請求
            $.ajax({
                url: otz_friends_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_sync_members',
                    nonce: otz_friends_sync.nonce,
                    selected_users: this.selectedUsers
                },
                beforeSend: () => {
                    this.updateProgress(50, otz_friends_sync.messages.importing);
                },
                success: (response) => {
                    if (response.success) {
                        this.updateProgress(90, otz_friends_sync.messages.updating);
                        
                        setTimeout(() => {
                            this.updateProgress(100, otz_friends_sync.messages.completed);
                            
                            setTimeout(() => {
                                this.completedSync(response.data);
                            }, 500);
                        }, 1000);
                    } else {
                        this.showError(response.data.message || otz_friends_sync.messages.error);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(otz_friends_sync.messages.error + ': ' + error);
                }
            });
        }

        showError(message) {
            this.syncInProgress = false;
            this.updateProgress(0, '同步失敗', message);
            
            // 顯示錯誤訊息並提供關閉選項
            $('#sync-progress-actions').html(`
                <button type="button" class="button button-secondary otz-modal-close">關閉</button>
            `);
        }

        updateProgress(percentage, message, details = '') {
            $('#sync-progress-bar').css('width', percentage + '%');
            $('#sync-status-text').text(message);
            $('#sync-details').text(details);
        }

        completedSync(results) {
            this.syncInProgress = false;
            this.showStep('complete');
            
            // 顯示結果統計
            const resultsHtml = `
                <div class="sync-results-stats">
                    <div class="stat-item">
                        <strong>${results.total}</strong>
                        <span>${otz_friends_sync.messages.total_users}</span>
                    </div>
                    <div class="stat-item">
                        <strong>${results.imported}</strong>
                        <span>${otz_friends_sync.messages.imported}</span>
                    </div>
                    <div class="stat-item">
                        <strong>${results.updated}</strong>
                        <span>${otz_friends_sync.messages.updated}</span>
                    </div>
                </div>
                ${results.details && results.details.length > 0 ? 
                    '<div class="sync-details-list" style="text-align: left; margin-top: 20px; max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px;">' +
                    '<strong>處理詳情：</strong><ul style="margin: 10px 0 0 0; padding-left: 20px;">' +
                    results.details.map(detail => `<li>${detail}</li>`).join('') +
                    '</ul></div>' : ''
                }
                <style>
                .sync-results-stats {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 15px;
                    margin: 20px 0;
                }
                .stat-item {
                    text-align: center;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 1px solid #e9ecef;
                }
                .stat-item strong {
                    display: block;
                    font-size: 24px;
                    color: #0073aa;
                    margin-bottom: 5px;
                }
                .stat-item span {
                    font-size: 14px;
                    color: #666;
                }
                .sync-details-list {
                    font-size: 14px;
                }
                .sync-details-list li {
                    margin-bottom: 5px;
                }
                </style>
            `;
            
            $('#sync-results').html(resultsHtml);
        }

        showNoUsersMessage() {
            $('#no-users-message').show();
        }

        resetModal() {
            this.currentStep = 'info';
            this.syncInProgress = false;
            this.availableUsers = [];
            this.selectedUsers = [];
            this.updateProgress(0, '');
            $('#sync-results').empty();
            $('#users-list').empty();
            $('#select-all-users').prop('checked', false);
            $('#selected-count').text('已選擇：0 位會員');
            $('#start-sync-btn').prop('disabled', true);
        }
    }

    // 當 DOM 準備好時初始化
    $(document).ready(function () {
        new MemberSync();
    });

})(jQuery);