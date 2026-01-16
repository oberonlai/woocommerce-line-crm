/**
 * 好友同步功能 JavaScript
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    class FriendsSync {
        constructor() {
            this.modal = null;
            this.currentStep = 'info';
            this.syncInProgress = false;
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

            // LINE 好友同步按鈕點擊事件
            $(document).on('click', '#sync-line-friends-btn', () => {
                this.openLineModal();
            });

            // 關閉燈箱事件（支援兩個燈箱）
            $(document).on('click', '.otz-modal-close, .otz-modal-backdrop', (e) => {
                const modal = $(e.target).closest('.otz-modal');
                if (modal.attr('id') === 'sync-line-friends-modal') {
                    this.closeLineModal();
                } else {
                    this.closeModal();
                }
            });

            // 開始同步按鈕
            $(document).on('click', '#start-sync-btn', () => {
                this.startSync();
            });

            // 開始 LINE 好友取得按鈕
            $(document).on('click', '#start-line-sync-btn', () => {
                this.fetchLineFriends();
            });

            // 確認匯入選擇的好友
            $(document).on('click', '#confirm-import-btn', () => {
                this.importSelectedFriends();
            });

            // 取消同步按鈕
            $(document).on('click', '#cancel-sync-btn', () => {
                this.cancelSync();
            });

            // 取消 LINE 好友同步按鈕
            $(document).on('click', '#cancel-line-sync-btn', () => {
                this.cancelLineSync();
            });

            // 取消 LINE 好友載入
            $(document).on('click', '#cancel-line-loading-btn', () => {
                this.cancelLineLoading();
            });

            // 全選好友
            $(document).on('change', '#select-all-friends', (e) => {
                this.toggleAllFriends(e.target.checked);
            });

            // 個別好友選擇
            $(document).on('change', '.friend-checkbox', () => {
                this.updateSelectionCount();
            });

            // 捲動到底部自動載入更多好友
            let scrollTimer = null;
            $(document).on('scroll', '.modal-body', (e) => {
                // 防抖處理，避免頻繁觸發
                if (scrollTimer) {
                    clearTimeout(scrollTimer);
                }
                
                scrollTimer = setTimeout(() => {
                    const container = $(e.currentTarget);
                    if (container.length) {
                        const scrollHeight = container[0].scrollHeight;
                        const scrollTop = container.scrollTop();
                        const clientHeight = container.height();
                        
                        // 當捲動到底部 100px 內時觸發
                        if (scrollHeight - scrollTop - clientHeight < 100) {
                            this.loadNextFriendsBatch();
                        }
                    }
                }, 200);
            });

            // 重新取得好友按鈕
            $(document).on('click', '#reset-line-friends-btn', () => {
                if (confirm('確定要重新取得好友清單嗎？這會清除目前已載入的資料。')) {
                    this.resetLineFriendsData();
                }
            });

            // 刷新頁面按鈕
            $(document).on('click', '#refresh-page-btn', () => {
                window.location.reload();
            });

            // ESC 鍵關閉燈箱
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.lineModal && this.lineModal.is(':visible')) {
                        this.closeLineModal();
                    } else if (this.modal && this.modal.is(':visible')) {
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

        startSync() {
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
                    nonce: otz_friends_sync.nonce
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

        resetModal() {
            this.currentStep = 'info';
            this.syncInProgress = false;
            this.updateProgress(0, '');
            $('#sync-results').empty();
        }

        // LINE 好友同步相關方法
        openLineModal() {
            this.lineModal = $('#sync-line-friends-modal');
            if (this.lineModal.length) {
                this.lineModal.fadeIn(300);
                $('body').addClass('modal-open');
                $('body').css('overflow', 'hidden');
                
                // 檢查是否已有好友數據，如果有就直接跳到選擇步驟
                if (this.allFriendIds && this.allFriendIds.length > 0 && this.allFriends && this.allFriends.length > 0) {
                    this.restoreFriendsSelection();
                } else {
                    this.showLineStep('info');
                }
            }
        }

        closeLineModal() {
            if (this.lineSyncInProgress || this.lineLoadingInProgress) {
                if (!confirm(otz_friends_sync.messages.cancel_confirm)) {
                    return;
                }
                this.cancelLineSync();
                this.cancelLineLoading();
            }

            if (this.lineModal) {
                this.lineModal.fadeOut(300);
                $('body').removeClass('modal-open');
                $('body').css('overflow', '');
                
                // 不自動重置數據，保留已載入的好友清單
                // 只重置正在進行的狀態
                this.lineSyncInProgress = false;
                this.lineLoadingInProgress = false;
                this.loadingBatch = false;
            }
        }

        // LINE 好友同步流程方法
        showLineStep(step) {
            this.currentLineStep = step;
            
            // 隱藏所有步驟
            $('.line-sync-step').hide();
            $('.modal-actions').hide();
            
            // 顯示對應步驟
            switch (step) {
                case 'info':
                    $('#line-sync-info-step').show();
                    $('#line-sync-info-actions').show();
                    break;
                case 'loading':
                    $('#line-sync-loading-step').show();
                    $('#line-sync-loading-actions').show();
                    break;
                case 'select':
                    $('#line-sync-select-step').show();
                    $('#line-sync-select-actions').show();
                    break;
                case 'progress':
                    $('#line-sync-progress-step').show();
                    $('#line-sync-progress-actions').show();
                    break;
                case 'complete':
                    $('#line-sync-complete-step').show();
                    $('#line-sync-complete-actions').show();
                    break;
            }
        }

        // 第一步：取得 LINE 好友 ID 清單
        fetchLineFriends() {
            this.lineLoadingInProgress = true;
            this.showLineStep('loading');
            
            // 更新載入進度
            this.updateLoadingProgress(10, otz_friends_sync.messages.line_connecting);
            
            // 發送 AJAX 請求取得好友 ID 清單（不含詳細資料）
            $.ajax({
                url: otz_friends_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_line_friends',
                    _ajax_nonce: otz_friends_sync.line_get_nonce
                },
                beforeSend: () => {
                    this.updateLoadingProgress(30, otz_friends_sync.messages.line_fetching);
                },
                success: (response) => {
                    if (response.success) {
                        this.updateLoadingProgress(100, '取得好友清單完成');
                        
                        setTimeout(() => {
                            // 儲存好友 ID 清單並初始化分批載入
                            this.allFriendIds = response.data.friend_ids || [];
                            this.existingCount = response.data.existing_count || 0;
                            this.allFriends = [];
                            this.currentOffset = 0;
                            
                            this.initializeFriendsSelection();
                        }, 500);
                    } else {
                        this.showLoadingError(response.data.message || '取得好友清單失敗');
                    }
                },
                error: (xhr, status, error) => {
                    this.showLoadingError('取得好友清單失敗: ' + error);
                }
            });
        }

        // 恢復已載入的好友選擇介面
        restoreFriendsSelection() {
            this.showLineStep('select');
            
            // 恢復統計信息
            const totalFriends = this.allFriendIds.length;
            $('#friends-statistics').html(`
                總好友數：${totalFriends} | 已載入：${this.allFriends.length} | 已存在：${this.existingCount} | 可匯入：${totalFriends - this.existingCount}
            `);
            
            // 恢復好友列表容器
            $('#friends-list-container').html(`
                <div id="friends-list"></div>
            `);
            
            // 重新渲染已載入的好友
            if (this.allFriends.length > 0) {
                this.appendFriendsToList(this.allFriends);
            }
            
            // 更新選擇狀態
            this.updateSelectionCount();
        }

        // 初始化好友選擇介面並開始分批載入
        initializeFriendsSelection() {
            this.lineLoadingInProgress = false;
            this.showLineStep('select');
            
            // 顯示總體統計訊息
            const totalFriends = this.allFriendIds.length;
            const newFriends = totalFriends - this.existingCount;
            
            $('#friends-statistics').html(`
                總好友數：${totalFriends} | 已存在：${this.existingCount} | 可匯入：${newFriends}
            `);
            
            // 初始化空的好友列表容器
            $('#friends-list-container').html(`
                <div id="friends-loading-indicator" style="padding: 20px; text-align: center; color: #666;">
                    正在載入好友資料...
                </div>
                <div id="friends-list"></div>
            `);
            
            // 載入第一批好友
            this.loadNextFriendsBatch();
        }

        // 分批載入好友詳細資料
        loadNextFriendsBatch() {
            if (this.loadingBatch || this.currentOffset >= this.allFriendIds.length) {
                return;
            }
            
            this.loadingBatch = true;
            
            // 顯示載入提示（如果還沒有的話）
            if ($('#loading-indicator-bottom').length === 0) {
                $('#friends-list').append(`
                    <div id="loading-indicator-bottom" style="text-align: center; padding: 20px;">
                        <div class="spinner is-active"></div>
                        <p>載入更多好友中...</p>
                    </div>
                `);
            } else {
                $('#loading-indicator-bottom').show();
            }
            
            $.ajax({
                url: otz_friends_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_get_line_friends_batch',
                    _ajax_nonce: otz_friends_sync.line_batch_nonce,
                    friend_ids: this.allFriendIds,
                    offset: this.currentOffset,
                    limit: 10
                },
                success: (response) => {
                    if (response.success) {
                        // 將新載入的好友添加到現有列表
                        const newFriends = response.data.friends || [];
                        this.allFriends = this.allFriends.concat(newFriends);
                        
                        // 更新顯示
                        this.appendFriendsToList(newFriends);
                        
                        // 更新偏移量
                        this.currentOffset = response.data.next_offset || (this.currentOffset + 10);
                        
                        // 隱藏載入指示器（如果這是第一批）
                        $('#friends-loading-indicator').hide();
                        
                        // 檢查是否還有更多
                        if (response.data.has_more) {
                            // 隱藏底部載入提示，準備下一次載入
                            $('#loading-indicator-bottom').hide();
                        } else {
                            // 沒有更多資料，顯示提示
                            $('#loading-indicator-bottom').html(`
                                <p style="color: #666; padding: 10px;">已載入全部好友</p>
                            `);
                            // 移除載入更多按鈕容器（如果存在）
                            $('#load-more-container').remove();
                        }
                        
                        this.updateSelectionCount();
                        
                    } else {
                        $('#loading-indicator-bottom').html(`
                            <p style="color: red;">載入失敗: ${response.data.message || '未知錯誤'}</p>
                        `);
                        setTimeout(() => {
                            $('#loading-indicator-bottom').hide();
                        }, 3000);
                    }
                    
                    this.loadingBatch = false;
                },
                error: (xhr, status, error) => {
                    $('#loading-indicator-bottom').html(`
                        <p style="color: red;">載入失敗: ${error}</p>
                    `);
                    setTimeout(() => {
                        $('#loading-indicator-bottom').hide();
                    }, 3000);
                    this.loadingBatch = false;
                }
            });
        }

        // 追加新的好友到列表（用於分批載入）
        appendFriendsToList(friends) {
            if (friends.length === 0) {
                return;
            }
            
            let html = '';
            friends.forEach(friend => {
                const isExisting = friend.exists_in_db;
                const disabled = isExisting ? 'disabled' : '';
                const opacity = isExisting ? 'opacity: 0.5;' : '';
                const statusText = isExisting ? '（已存在）' : '';
                
                html += `
                    <div class="friend-item" style="display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #eee; ${opacity}">
                        <label style="display: flex; align-items: center; width: 100%; cursor: ${disabled ? 'not-allowed' : 'pointer'};">
                            <input type="checkbox" class="friend-checkbox" ${disabled} 
                                   data-line-user-id="${friend.line_user_id}"
                                   data-display-name="${friend.display_name}"
                                   data-avatar-url="${friend.avatar_url}"
                                   style="margin-right: 10px;" />
                            <img src="${friend.avatar_url || this.getDefaultAvatar()}" 
                                 style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;" 
                                 onerror="this.src='${this.getDefaultAvatar()}'" />
                            <div>
                                <div style="font-weight: 500;">${friend.display_name} ${statusText}</div>
                                <div style="font-size: 12px; color: #666;">${friend.line_user_id}</div>
                            </div>
                        </label>
                    </div>
                `;
            });
            
            $('#friends-list').append(html);
        }

        // 全選/取消全選
        toggleAllFriends(checked) {
            $('.friend-checkbox:not(:disabled)').prop('checked', checked);
            this.updateSelectionCount();
        }

        // 更新選擇計數和載入狀態
        updateSelectionCount() {
            const selected = $('.friend-checkbox:checked').length;
            const loadedFriends = this.allFriends.length;
            const totalFriends = this.allFriendIds.length;
            
            // 更新載入進度統計
            $('#friends-statistics').html(`
                總好友數：${totalFriends} | 已載入：${loadedFriends} | 已存在：${this.existingCount} | 可匯入：${totalFriends - this.existingCount}
            `);
            
            // 更新選擇統計
            $('#selection-summary').text(`已選擇 ${selected} 位好友`);
            
            // 啟用/停用匯入按鈕
            $('#confirm-import-btn').prop('disabled', selected === 0);
        }

        // 第二步：匯入選擇的好友
        importSelectedFriends() {
            const selectedFriends = this.getSelectedFriends();
            
            if (selectedFriends.length === 0) {
                alert('請選擇要匯入的好友');
                return;
            }
            
            this.lineSyncInProgress = true;
            this.showLineStep('progress');
            
            // 重置進度條
            this.updateLineProgress(10, '開始匯入好友...');
            
            // 發送匯入請求
            $.ajax({
                url: otz_friends_sync.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_sync_line_friends',
                    _ajax_nonce: otz_friends_sync.line_sync_nonce,
                    selected_friends: selectedFriends
                },
                beforeSend: () => {
                    this.updateLineProgress(30, `正在匯入 ${selectedFriends.length} 位好友...`);
                },
                success: (response) => {
                    if (response.success) {
                        this.updateLineProgress(90, otz_friends_sync.messages.updating);
                        
                        setTimeout(() => {
                            this.updateLineProgress(100, otz_friends_sync.messages.line_completed);
                            
                            setTimeout(() => {
                                this.completedLineSync(response.data);
                            }, 500);
                        }, 1000);
                    } else {
                        this.showLineError(response.data.message || otz_friends_sync.messages.line_error);
                    }
                },
                error: (xhr, status, error) => {
                    this.showLineError(otz_friends_sync.messages.line_error + ': ' + error);
                }
            });
        }

        // 取得選擇的好友資料
        getSelectedFriends() {
            const selected = [];
            $('.friend-checkbox:checked').each(function() {
                const checkbox = $(this);
                selected.push({
                    line_user_id: checkbox.data('line-user-id'),
                    display_name: checkbox.data('display-name'),
                    avatar_url: checkbox.data('avatar-url')
                });
            });
            return selected;
        }

        // 取得預設頭像
        getDefaultAvatar() {
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNFNUU3RUIiLz4KPHA+dGggZD0iTTIwIDEyQzE2LjY5IDEyIDE0IDEzLjc5IDE0IDE2QzE0IDE4LjIxIDE2LjY5IDIwIDIwIDIwQzIzLjMxIDIwIDI2IDE4LjIxIDI2IDE2QzI2IDEzLjc5IDIzLjMxIDEyIDIwIDEyWiIgZmlsbD0iIzlDQTNBRiIvPgo8cGF0aCBkPSJNMjAgMjJDMTUuMDMgMjIgMTEgMjQuNjkgMTEgMjhWMzBIMjlWMjhDMjkgMjQuNjkgMjQuOTcgMjIgMjAgMjJaIiBmaWxsPSIjOUNBM0FGIi8+Cjwvc3ZnPgo=';
        }

        cancelLineLoading() {
            this.lineLoadingInProgress = false;
            this.closeLineModal();
        }

        cancelLineSync() {
            this.lineSyncInProgress = false;
            this.closeLineModal();
        }

        // 載入進度更新方法
        updateLoadingProgress(percentage, message, details = '') {
            $('#line-sync-loading-bar').css('width', percentage + '%');
            $('#line-sync-loading-text').text(message);
        }

        showLoadingError(message) {
            this.lineLoadingInProgress = false;
            this.updateLoadingProgress(0, 'LINE 好友載入失敗', message);
            
            // 顯示錯誤訊息並提供關閉選項
            $('#line-sync-loading-actions').html(`
                <button type="button" class="button button-secondary otz-modal-close">關閉</button>
            `);
        }

        showLineError(message) {
            this.lineSyncInProgress = false;
            this.updateLineProgress(0, 'LINE 好友匯入失敗', message);
            
            // 顯示錯誤訊息並提供關閉選項
            $('#line-sync-progress-actions').html(`
                <button type="button" class="button button-secondary otz-modal-close">關閉</button>
            `);
        }

        updateLineProgress(percentage, message, details = '') {
            $('#line-sync-progress-bar').css('width', percentage + '%');
            $('#line-sync-status-text').text(message);
            $('#line-sync-details').text(details);
        }

        completedLineSync(results) {
            this.lineSyncInProgress = false;
            this.showLineStep('complete');
            
            // 顯示結果統計
            const resultsHtml = `
                <div class="line-sync-results-stats">
                    <div class="stat-item">
                        <strong>${results.total}</strong>
                        <span>總好友數</span>
                    </div>
                    <div class="stat-item">
                        <strong>${results.imported}</strong>
                        <span>${otz_friends_sync.messages.imported}</span>
                    </div>
                    <div class="stat-item">
                        <strong>${results.skipped}</strong>
                        <span>${otz_friends_sync.messages.skipped}</span>
                    </div>
                </div>
                ${results.details && results.details.length > 0 ? 
                    '<div class="sync-details-list" style="text-align: left; margin-top: 20px; max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 8px;">' +
                    '<strong>處理詳情：</strong><ul style="margin: 10px 0 0 0; padding-left: 20px;">' +
                    results.details.map(detail => `<li>${detail}</li>`).join('') +
                    '</ul></div>' : ''
                }
                <style>
                .line-sync-results-stats {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 15px;
                    margin: 20px 0;
                }
                .line-sync-results-stats .stat-item {
                    text-align: center;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 1px solid #e9ecef;
                }
                .line-sync-results-stats .stat-item strong {
                    display: block;
                    font-size: 24px;
                    color: #00C300;
                    margin-bottom: 5px;
                }
                .line-sync-results-stats .stat-item span {
                    font-size: 14px;
                    color: #666;
                }
                </style>
            `;
            
            $('#line-sync-results').html(resultsHtml);
        }

        // 手動重置 LINE 好友數據（當用戶想重新開始時）
        resetLineFriendsData() {
            this.allFriendIds = [];
            this.allFriends = [];
            this.existingCount = 0;
            this.currentOffset = 0;
            this.showLineStep('info');
            $('#friends-list-container').empty();
            $('#friends-statistics').empty();
            $('#selection-summary').text('已選擇 0 位好友');
            $('#select-all-friends').prop('checked', false);
        }

        // 重置燈箱狀態（私有方法，僅用於真正需要完全重置時）
        resetLineModal() {
            this.currentLineStep = 'info';
            this.lineLoadingInProgress = false;
            this.lineSyncInProgress = false;
            this.loadingBatch = false;
            this.allFriendIds = [];
            this.allFriends = [];
            this.existingCount = 0;
            this.currentOffset = 0;
            this.updateLoadingProgress(0, '');
            this.updateLineProgress(0, '');
            $('#line-sync-results').empty();
            $('#friends-list-container').empty();
            $('#friends-statistics').empty();
            $('#selection-summary').text('已選擇 0 位好友');
            $('#select-all-friends').prop('checked', false);
        }
    }

    // 當 DOM 準備好時初始化
    $(document).ready(function () {
        new FriendsSync();
    });

})(jQuery);