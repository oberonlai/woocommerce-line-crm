/**
 * OrderChatz 好友列表元件
 *
 * 管理左側好友列表的顯示和互動功能
 *
 * @package OrderChatz
 * @since 1.0.4
 */

(function ($) {
    'use strict';

    /**
     * 好友列表元件
     * 管理左側好友列表的顯示和互動功能
     */
    window.FriendListComponent = function (containerSelector) {
        this.container = $(containerSelector);
        this.friendList = this.container.find('.friend-list');
        this.searchInput = this.container.find('#friend-search');
        this.searchClear = this.container.find('#friend-search-clear');
        this.selectedFriendId = null;
        this.selectedGroupId = null;
        this.friends = [];
        this.currentPage = 1;
        this.perPage = 20;
        this.hasMore = true;
        this.isLoading = false;
        this.searchQuery = '';
        this.searchType = null; // 'name' 或 'tag'
        this.searchDropdown = null;
        this.selectedDropdownIndex = 0; // 下拉選單選中的索引 (0 或 1)
        this.isComposing = false; // 是否正在使用輸入法組字
        this.justFinishedComposing = false; // 是否剛完成組字

        this.init();
    };

    FriendListComponent.prototype = {
        /**
         * 初始化元件
         */
        init: function () {
            this.createSearchDropdown();
            this.bindEvents();
            this.loadFriends();
        },

        /**
         * 建立搜尋下拉選單
         */
        createSearchDropdown: function () {
            const dropdownHtml = `
                <div class="friend-search-dropdown" id="friend-search-dropdown" style="display: none;">
                    <div class="search-dropdown-option" data-search-type="name">
                        <span class="option-icon dashicons dashicons-admin-users"></span>
                        <span class="option-text">搜尋好友<span class="search-keyword"></span></span>
                    </div>
                    <div class="search-dropdown-option" data-search-type="tag">
                        <span class="option-icon dashicons dashicons-tag"></span>
                        <span class="option-text">搜尋標籤<span class="search-keyword"></span>的好友</span>
                    </div>
                </div>
            `;
            this.container.find('.friend-search-container').append(dropdownHtml);
            this.searchDropdown = $('#friend-search-dropdown');
        },

        /**
         * 綁定事件監聽器
         */
        bindEvents: function () {
            // 搜尋功能
            this.searchInput.on('input', this.handleSearch.bind(this));
            this.searchInput.on('keyup', this.handleSearchKeyup.bind(this));
            this.searchClear.on('click', this.clearSearch.bind(this));

            // 輸入法組字事件
            this.searchInput.on('compositionstart', () => {
                this.isComposing = true;
            });
            this.searchInput.on('compositionend', () => {
                this.isComposing = false;
                // 標記剛完成組字
                this.justFinishedComposing = true;
                // 在下一個事件循環後清除標記
                setTimeout(() => {
                    this.justFinishedComposing = false;
                }, 100);
            });

            // 搜尋下拉選單
            this.container.on('click', '.search-dropdown-option', this.handleSearchOptionClick.bind(this));

            // 點擊外部關閉下拉選單
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.friend-search-container').length) {
                    this.hideSearchDropdown();
                }
            });

            // 好友選擇事件委派
            this.friendList.on('click', '.friend-item', this.handleFriendSelect.bind(this));
            this.friendList.on('keydown', '.friend-item', this.handleFriendKeydown.bind(this));

            // 滾動載入更多
            this.friendList.on('scroll', this.handleScroll.bind(this));

            // 監聽外部事件
            $(document).on('message:sent', this.handleMessageSent.bind(this));
            $(document).on('friend:updated', this.handleFriendUpdated.bind(this));
            $(document).on('messages:marked-as-read', this.handleMessagesMarkedAsRead.bind(this));

            // 監聽輪詢事件
            $(document).on('polling:new-friends', this.handlePollingNewFriends.bind(this));
            $(document).on('polling:friend-updates', this.handlePollingFriendUpdates.bind(this));
        },

        /**
         * 載入好友資料
         */
        loadFriends: function (reset = true) {

            if (this.isLoading) {
                return;
            }

            if (reset) {
                this.currentPage = 1;
                this.hasMore = true;
                this.friends = [];
                this.friendList.empty();
            }

            this.isLoading = true;
            this.showLoadingState();

            const data = {
                action: 'otz_get_friends',
                nonce: otzChatConfig.nonce,
                page: this.currentPage,
                per_page: this.perPage,
                search: this.searchQuery
            };


            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    this.isLoading = false;
                    this.hideLoadingState();


                    if (response.success && response.data) {
                        const newFriends = response.data.friends || [];
                        this.hasMore = response.data.has_more || false;


                        // 確保 newFriends 是陣列
                        if (!Array.isArray(newFriends)) {
                            this.renderError('伺服器回應格式錯誤');
                            return;
                        }

                        if (reset) {
                            this.friends = newFriends;
                        } else {
                            // 確保 this.friends 是陣列
                            if (!Array.isArray(this.friends)) {
                                this.friends = [];
                            }
                            // 去重合併新好友，避免重複.
                            newFriends.forEach(newFriend => {
                                let existingFriend;

                                // 群組使用 group_id 比對.
                                if (newFriend.group_id) {
                                    existingFriend = this.friends.find(f =>
                                        f.group_id && f.group_id === newFriend.group_id
                                    );
                                } else {
                                    // 個人好友使用 line_user_id 比對（排除空字串）.
                                    existingFriend = this.friends.find(f =>
                                        f.line_user_id &&
                                        newFriend.line_user_id &&
                                        f.line_user_id === newFriend.line_user_id &&
                                        !f.group_id  // 確保不是群組.
                                    );
                                }

                                if (!existingFriend) {
                                    this.friends.push(newFriend);
                                } else {
                                    // 選擇性更新現有好友的資料，避免覆蓋現有完整資訊
                                    if (newFriend.unread_count !== undefined && newFriend.unread_count !== null) {
                                        existingFriend.unread_count = newFriend.unread_count;
                                    }
                                    if (newFriend.last_message && newFriend.last_message !== '尚無對話' && newFriend.last_message.trim() !== '') {
                                        existingFriend.last_message = newFriend.last_message;
                                    }
                                    // 更新其他重要欄位
                                    if (newFriend.followed_at) {
                                        existingFriend.followed_at = newFriend.followed_at;
                                    }
                                    if (newFriend.last_active) {
                                        existingFriend.last_active = newFriend.last_active;
                                    }
                                    if (newFriend.read_time) {
                                        existingFriend.read_time = newFriend.read_time;
                                    }
                                }
                            });
                        }

                        // 套用排序邏輯
                        this.sortFriendList();
                        this.renderFriendList();

                        // 觸發好友列表初始化完成事件，傳遞好友資料給輪詢管理器.
                        if (reset && this.friends.length > 0) {
                            $(document).trigger('friendlist:initialized', [this.friends]);
                        }

                        const urlParams = new URLSearchParams(window.location.search);
                        const hasFriendParam = urlParams.get('friend');

                        // 如果是第一次載入且沒有選中好友，自動選中第一位好友
                        if (reset && !this.selectedFriendId && this.friends.length > 0 && !hasFriendParam) {
                            setTimeout(() => {
                                this.autoSelectFirstFriend();
                            }, 100);
                        }
                    } else {
                        this.renderError(response.data?.message || '載入好友列表時發生錯誤');
                    }
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    this.hideLoadingState();
                    this.renderError('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 渲染好友列表
         * @param {array} friendsToRender - 要渲染的好友陣列（可選）
         */
        renderFriendList: function (friendsToRender) {
            const friends = friendsToRender || this.friends;
            const scrollTopBefore = this.friendList.scrollTop();

            // 確保 friends 是陣列
            if (!Array.isArray(friends)) {
                this.renderError('好友資料格式錯誤');
                return;
            }

            if (friends.length === 0) {
                this.renderEmptyState();
                return;
            }

            let html = '';
            friends.forEach(friend => {
                html += this.renderFriendItem(friend);
            });

            this.friendList.html(html);
            this.updateUnreadBadges();

            // 恢復選中狀態（傳入 groupId）.
            if (this.selectedFriendId) {
                this.highlightSelectedFriend(this.selectedFriendId, this.selectedGroupId);
            }

            // 恢復滾動位置.
            if (scrollTopBefore > 0) {
                this.friendList.scrollTop(scrollTopBefore);
            }
        },

        /**
         * 渲染單個好友項目
         * @param {object} friend - 好友資料
         * @returns {string} HTML 字串
         */
        renderFriendItem: function (friend) {
            const template = $('#friend-item-template').html();
            if (!template) {
                console.error('FriendListComponent: 好友項目模板未找到');
                return '';
            }

            // 判斷是否為群組對話（支援 group 和 room 兩種類型）.
            const isGroup = friend.source_type === 'group' || friend.source_type === 'room';
            const groupIconClass = isGroup ? 'show' : 'hide';
            const groupIconTitle = isGroup ? (friend.source_type === 'room' ? '聊天室' : '群組對話') : '';

            return template
                .replace(/\{friendId\}/g, friend.id)
                .replace(/\{avatar\}/g, friend.avatar || this.getDefaultAvatar())
                .replace(/\{name\}/g, this.escapeHtml(friend.name))
                .replace(/\{lastMessage\}/g, this.escapeHtml(friend.last_message || ''))
                .replace(/\{lastMessageTime\}/g, this.escapeHtml(friend.last_message_time || ''))
                .replace(/\{onlineClass\}/g, friend.is_online ? 'online' : 'offline')
                .replace(/\{unreadClass\}/g, friend.unread_count > 0 ? 'show' : 'hide')
                .replace(/\{unreadCount\}/g, friend.unread_count || '')
                .replace(/\{groupIconClass\}/g, groupIconClass)
                .replace(/\{groupId\}/g, friend.group_id || '')
                .replace(/\{groupIconTitle\}/g, groupIconTitle);
        },

        /**
         * 處理好友選擇
         * @param {Event} event - 點擊事件
         */
        handleFriendSelect: function (event) {
            event.preventDefault();

            const friendItem = $(event.currentTarget);
            const friendId = friendItem.data('friend-id');
            const groupId = friendItem.data('group-id') || null;

            if (!friendId) {
                console.error('FriendListComponent: 好友 ID 未找到');
                return;
            }

            // 避免重複選擇（需同時比對 friendId 和 groupId）.
            if (this.selectedFriendId === friendId && this.selectedGroupId === groupId) {
                return;
            }

            // 檢查是否正在載入訊息，避免競爭條件.
            if (window.chatAreaInstance &&
                window.chatAreaInstance.isLoadingMessages) {
                return;
            }

            // 更新選中狀態（同時記錄 friendId 和 groupId）.
            this.selectedFriendId = friendId;
            this.selectedGroupId = groupId;
            // highlight 由 syncState 統一處理.

            // 標記訊息為已讀.
            this.markMessageAsRead(friendId, groupId);
            this.updateUnreadBadges();

            // 找到對應的好友物件.
            const friendData = this.friends.find(f => {
                if (groupId) {
                    return f.id == friendId && f.group_id === groupId;
                } else {
                    return f.id == friendId && !f.group_id;
                }
            });
            if (!friendData) {
                console.error('FriendListComponent: 找不到好友資料', friendId, groupId);
                return;
            }

            // 觸發好友選擇事件，傳遞完整的好友物件.
            $(document).trigger('friend:selected', [friendData]);
        },

        /**
         * 處理鍵盤導航
         * @param {Event} event - 鍵盤事件
         */
        handleFriendKeydown: function (event) {
            const currentItem = $(event.currentTarget);
            let targetItem = null;

            switch (event.keyCode) {
                case 13: // Enter
                case 32: // Space
                    event.preventDefault();
                    this.handleFriendSelect(event);
                    break;

                case 38: // Arrow Up
                    event.preventDefault();
                    targetItem = currentItem.prev('.friend-item');
                    break;

                case 40: // Arrow Down
                    event.preventDefault();
                    targetItem = currentItem.next('.friend-item');
                    break;
            }

            if (targetItem && targetItem.length) {
                targetItem.focus();
            }
        },

        /**
         * 高亮選中的好友
         *
         *
         */
        highlightSelectedFriend: function (friendId, groupId) {
            
            // 移除之前的選中狀態.
            this.friendList.find('.friend-item').removeClass('selected');

            let selectedItem;
            if (groupId) {
                selectedItem = this.friendList.find(
                    `[data-friend-id="${friendId}"][data-group-id="${groupId}"]`
                );
            } else {
                selectedItem = this.friendList.find(
                    `[data-friend-id="${friendId}"]`
                ).filter(function() {
                    return !$(this).data('group-id');
                });
            }

            selectedItem.addClass('selected');
            this.scrollToFriend(selectedItem);
        },

        /**
         * 滾動到指定好友項目
         * @param {jQuery} friendItem - 好友項目元素
         */
        scrollToFriend: function (friendItem) {
            if (friendItem.length === 0) return;

            const container = this.friendList;
            const itemTop = friendItem.position().top;
            const containerHeight = container.height();
            const itemHeight = friendItem.outerHeight();

            // if (itemTop < 0 || itemTop + itemHeight > containerHeight) {
            //     container.animate({
            //         scrollTop: container.scrollTop() + itemTop - containerHeight / 2
            //     }, 300);
            // }
        },

        /**
         * 更新未讀訊息徽章
         */
        updateUnreadBadges: function () {
            this.friendList.find('.friend-item').each((index, item) => {
                const $item = $(item);
                const friendId = $item.data('friend-id');
                const groupId = $item.data('group-id') || null;
                const friend = this.getFriend(friendId, groupId);

                if (friend) {
                    const badge = $item.find('.unread-badge');
                    if (friend.unread_count > 0) {
                        badge.addClass('show').removeClass('hide').text(friend.unread_count);
                    } else {
                        badge.addClass('hide').removeClass('show');
                    }
                }
            });
        },

        /**
         * 處理搜尋輸入
         * @param {Event} event - 輸入事件
         */
        handleSearch: function (event) {
            const query = $(event.target).val();

            // 顯示/隱藏清除按鈕和下拉選單
            if (query.length > 0) {
                this.searchClear.show();
                // 無論是否在組字,都顯示下拉選單
                this.showSearchDropdown(query);
            } else {
                this.searchClear.hide();
                this.hideSearchDropdown();
                // 如果搜尋框清空,恢復顯示所有好友
                this.searchQuery = '';
                this.searchType = null;
                this.loadFriends(true);
            }
        },

        /**
         * 處理搜尋按鍵
         * @param {Event} event - 鍵盤事件
         */
        handleSearchKeyup: function (event) {
            // 如果正在組字中或剛完成組字,不處理按鍵事件
            if (this.isComposing || this.justFinishedComposing) {
                return;
            }

            const dropdownVisible = this.searchDropdown && this.searchDropdown.is(':visible');

            if (event.keyCode === 27) { // Escape
                this.clearSearch();
            } else if (event.keyCode === 38 && dropdownVisible) { // Arrow Up
                event.preventDefault();
                this.selectedDropdownIndex = Math.max(0, this.selectedDropdownIndex - 1);
                this.updateDropdownSelection();
            } else if (event.keyCode === 40 && dropdownVisible) { // Arrow Down
                event.preventDefault();
                this.selectedDropdownIndex = Math.min(1, this.selectedDropdownIndex + 1);
                this.updateDropdownSelection();
            } else if (event.keyCode === 13) { // Enter
                event.preventDefault();
                const keyword = $(event.target).val().trim();

                if (!keyword) {
                    return;
                }

                if (dropdownVisible) {
                    // 如果下拉選單可見,根據選中的項目執行搜尋
                    const options = this.searchDropdown.find('.search-dropdown-option');
                    const selectedOption = options.eq(this.selectedDropdownIndex);
                    const searchType = selectedOption.data('search-type');

                    this.searchType = searchType;
                    this.searchQuery = keyword;
                    this.hideSearchDropdown();

                    if (searchType === 'tag') {
                        this.searchByTag(keyword);
                    } else {
                        this.loadFriends(true);
                    }
                } else {
                    // 如果下拉選單不可見,預設執行好友名稱搜尋
                    this.searchType = 'name';
                    this.searchQuery = keyword;
                    this.loadFriends(true);
                }
            }
        },

        /**
         * 清除搜尋
         */
        clearSearch: function () {
            this.searchInput.val('');
            this.searchClear.hide();
            this.searchQuery = '';
            this.searchType = null;
            this.hideSearchDropdown();
            this.loadFriends(true);
            this.searchInput.focus();
        },

        /**
         * 顯示搜尋下拉選單
         * @param {string} keyword - 搜尋關鍵字
         */
        showSearchDropdown: function (keyword) {
            if (!keyword || keyword.trim() === '') {
                this.hideSearchDropdown();
                return;
            }

            // 更新下拉選單中的關鍵字
            this.searchDropdown.find('.search-keyword').text('「' + this.escapeHtml(keyword.trim()) + '」');

            // 重置選中索引為第一項
            this.selectedDropdownIndex = 0;
            this.updateDropdownSelection();

            this.searchDropdown.show();
        },

        /**
         * 隱藏搜尋下拉選單
         */
        hideSearchDropdown: function () {
            if (this.searchDropdown) {
                this.searchDropdown.hide();
                // 重置選中索引
                this.selectedDropdownIndex = 0;
            }
        },

        /**
         * 更新下拉選單選中狀態
         */
        updateDropdownSelection: function () {
            if (!this.searchDropdown) {
                return;
            }

            const options = this.searchDropdown.find('.search-dropdown-option');

            // 移除所有選中狀態
            options.removeClass('selected');

            // 為當前選中項目添加選中狀態
            options.eq(this.selectedDropdownIndex).addClass('selected');
        },

        /**
         * 處理搜尋選項點擊
         * @param {Event} event - 點擊事件
         */
        handleSearchOptionClick: function (event) {
            event.preventDefault();
            const option = $(event.currentTarget);
            const searchType = option.data('search-type');
            const keyword = this.searchInput.val().trim();

            if (!keyword) {
                return;
            }

            this.searchType = searchType;
            this.searchQuery = keyword;
            this.hideSearchDropdown();

            // 根據搜尋類型執行對應的搜尋
            if (searchType === 'tag') {
                this.searchByTag(keyword);
            } else {
                this.loadFriends(true);
            }
        },

        /**
         * 根據標籤搜尋好友
         * @param {string} tagName - 標籤名稱
         */
        searchByTag: function (tagName) {
            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            this.showLoadingState();

            const data = {
                action: 'otz_search_friends_by_tag',
                nonce: otzChatConfig.nonce,
                tag_name: tagName,
                page: 1,
                per_page: this.perPage
            };

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    this.isLoading = false;
                    this.hideLoadingState();

                    if (response.success && response.data) {
                        this.friends = response.data.friends || [];
                        this.hasMore = response.data.has_more || false;
                        this.currentPage = 1;

                        this.renderFriendList();

                        // 如果有結果且沒有選中好友，自動選中第一位
                        if (this.friends.length > 0 && !this.selectedFriendId) {
                            setTimeout(() => {
                                this.autoSelectFirstFriend();
                            }, 100);
                        }
                    } else {
                        this.renderError(response.data?.message || '搜尋標籤時發生錯誤');
                    }
                },
                error: () => {
                    this.isLoading = false;
                    this.hideLoadingState();
                    this.renderError('網路連線發生問題，請稍後再試');
                }
            });
        },

        /**
         * 處理滾動載入更多
         */
        handleScroll: function (event) {
            if (this.isLoading || !this.hasMore) return;

            const container = $(event.target);
            const scrollTop = container.scrollTop();
            const scrollHeight = container[0].scrollHeight;
            const containerHeight = container.height();

            // 當滾動到接近底部時載入更多
            if (scrollTop + containerHeight >= scrollHeight - 50) {
                this.currentPage++;
                this.loadFriends(false);
            }
        },

        /**
         * 處理訊息發送事件
         * @param {Event} event - 自定義事件
         * @param {object} messageData - 訊息資料
         */
        handleMessageSent: function (event, messageData) {
            if (messageData && messageData.friendId) {
                // 根據是否有 groupId 精確查找.
                let friendItem;
                if (messageData.groupId) {
                    friendItem = this.friendList.find(
                        `[data-friend-id="${messageData.friendId}"][data-group-id="${messageData.groupId}"]`
                    );
                } else {
                    friendItem = this.friendList.find(`[data-friend-id="${messageData.friendId}"]`)
                        .filter(function() {
                            return !$(this).data('group-id');
                        });
                }

                if (friendItem.length) {
                    friendItem.find('.last-message').text(messageData.content);
                    friendItem.find('.last-message-time').text('現在');
                }
            }
        },

        /**
         * 處理好友資料更新事件
         * @param {Event} event - 自定義事件
         * @param {string} friendId - 好友 ID
         */
        handleFriendUpdated: function (event, friendId) {
            this.loadFriends(true);
        },

        /**
         * 渲染空狀態
         */
        renderEmptyState: function () {
            const html = `
                <div class="friend-list-empty">
                    <div class="empty-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <p>尚無好友資料</p>
                </div>
            `;
            this.friendList.html(html);
        },

        /**
         * 渲染錯誤狀態
         * @param {string} message - 錯誤訊息
         */
        renderError: function (message) {
            const html = `
                <div class="friend-list-error">
                    <div class="error-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <p>${this.escapeHtml(message)}</p>
                    <button type="button" class="button button-small" onclick="location.reload()">
                        重新載入
                    </button>
                </div>
            `;
            this.friendList.html(html);
        },

        /**
         * HTML 跳脫
         * @param {string} text - 要跳脫的文字
         * @returns {string} 跳脫後的文字
         */
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * 顯示載入狀態
         */
        showLoadingState: function () {
            if (this.friends.length === 0) {
                this.friendList.html('<div class="friend-list-loading">載入中...</div>');
            }
        },

        /**
         * 隱藏載入狀態
         */
        hideLoadingState: function () {
            this.friendList.find('.friend-list-loading').remove();
        },

        /**
         * 取得好友資料
         * @param {string} friendId - 好友 ID
         * @param {string|null} groupId - 群組 ID（可選）
         * @returns {object|null} 好友資料
         */
        getFriend: function (friendId, groupId = null) {
            return this.friends.find(friend => {
                if (groupId) {
                    return friend.id == friendId && friend.group_id === groupId;
                } else {
                    return friend.id == friendId && !friend.group_id;
                }
            }) || null;
        },

        /**
         * 標記訊息為已讀
         * @param {string} friendId - 好友 ID
         * @param {string|null} groupId - 群組 ID（可選）
         */
        markMessageAsRead: function (friendId, groupId = null) {
            const friend = this.getFriend(friendId, groupId);
            if (friend) {
                // 立即更新前端顯示.
                friend.unread_count = 0;

                // 同步已讀狀態到伺服器.
                if (groupId) {
                    // 群組聊天：更新群組的 read_time.
                    this.syncGroupReadStatusToServer(groupId);
                } else if (friend.line_user_id) {
                    // 個人聊天：更新個人的 read_time.
                    this.syncReadStatusToServer(friend.line_user_id);
                }
            }
        },

        /**
         * 同步已讀狀態到伺服器（僅適用於個人聊天）
         * @param {string} lineUserId - LINE 使用者 ID
         */
        syncReadStatusToServer: function (lineUserId) {
            if (!lineUserId) {
                return;
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_mark_messages_read',
                    line_user_id: lineUserId,
                    nonce: otzChatConfig.nonce
                },
                success: (response) => {
                    if (response.success) {

                    } else {
                        console.warn('FriendListComponent: 同步已讀狀態失敗', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.warn('FriendListComponent: 同步已讀狀態網路錯誤', error);
                }
            });
        },

        /**
         * 同步群組已讀狀態到伺服器
         * @param {string} groupId - 群組 ID
         */
        syncGroupReadStatusToServer: function (groupId) {
            if (!groupId) {
                return;
            }

            $.ajax({
                url: otzChatConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'otz_mark_group_messages_read',
                    group_id: groupId,
                    nonce: otzChatConfig.nonce
                },
                success: (response) => {
                    if (response.success) {
                        //console.log('FriendListComponent: 群組已讀狀態已同步', groupId);
                    } else {
                        console.warn('FriendListComponent: 同步群組已讀狀態失敗', response.data.message);
                    }
                },
                error: (xhr, status, error) => {
                    console.warn('FriendListComponent: 同步群組已讀狀態網路錯誤', error);
                }
            });
        },

        /**
         * 處理訊息已讀事件
         * @param {Event} event - 自定義事件
         * @param {string} friendId - 好友 ID
         */
        handleMessagesMarkedAsRead: function (event, friendId) {
            if (friendId) {
                this.updateFriendUnreadCount(friendId, 0);
            }
        },

        /**
         * 取得預設頭像
         * @returns {string} 頭像 URL
         */
        getDefaultAvatar: function () {
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNEREQiLz4KPHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeD0iMTIiIHk9IjEyIj4KPHBhdGggZD0iTTggMEMzLjU4IDAgMCA0IDAgOFMzLjU4IDE2IDggMTZTMTYgMTIgMTYgOFMxMi40MiAwIDggMFpNOCAzQzEwLjIxIDMgMTIgNC43OSAxMiA3UzEwLjIxIDExIDggMTFTNCA5LjIxIDQgN1M1Ljc5IDMgOCAzWk04IDEzLjVDNS41IDEzLjUgMy4zNSAxMi4zNSAyLjUgMTAuNTVDMi41MSA4LjkgNS4xIDguMjUgOCA4LjI1UzEzLjQ5IDguOSAxMy41IDEwLjU1QzEyLjY1IDEyLjM1IDEwLjUgMTMuNSA4IDEzLjVaIiBmaWxsPSIjNjY2Ii8+Cjwvc3ZnPgo8L3N2Zz4=';
        },

        /**
         * 取得當前選中的好友 ID
         * @returns {string|null} 好友 ID
         */
        getSelectedFriendId: function () {
            return this.selectedFriendId;
        },

        /**
         * 處理輪詢新好友事件
         * @param {Event} event - 自定義事件
         * @param {array} newFriends - 新好友列表
         */
        handlePollingNewFriends: function (event, newFriends) {
            if (!Array.isArray(newFriends) || newFriends.length === 0) {
                return;
            }

            // 將新好友添加到列表，確保去重
            newFriends.reverse().forEach(friend => {
                // 檢查是否已存在（同時檢查 ID 和 line_user_id）
                const existingFriend = this.friends.find(f => f.id === friend.id || f.line_user_id === friend.line_user_id);
                if (!existingFriend) {
                    this.friends.unshift(friend);
                } else {
                    // 選擇性更新現有好友的資料，避免覆蓋現有完整資訊
                    if (friend.unread_count !== undefined && friend.unread_count !== null) {
                        existingFriend.unread_count = friend.unread_count;
                    }
                    if (friend.last_message && friend.last_message !== '尚無對話' && friend.last_message.trim() !== '') {
                        existingFriend.last_message = friend.last_message;
                    }
                }
            });

            // 套用排序邏輯後重新渲染好友列表
            this.sortFriendList();
            this.renderFriendList();

            // 如果當前沒有選中好友，自動選中第一個新好友
            if (!this.selectedFriendId && newFriends.length > 0) {
                const firstNewFriend = newFriends[0];
                setTimeout(() => {
                    this.handleFriendSelect({
                        currentTarget: this.friendList.find(`[data-friend-id="${firstNewFriend.id}"]`)[0],
                        preventDefault: () => {
                        }
                    });
                }, 100);
            }
        },

        /**
         * 處理輪詢好友更新事件
         * @param {Event} event - 自定義事件
         * @param {array} friendUpdates - 好友更新列表
         */
        handlePollingFriendUpdates: function (event, friendUpdates) {

            if (!Array.isArray(friendUpdates) || friendUpdates.length === 0) {
                return;
            }

            let hasUpdates = false;
            let needsReorder = false;
            const updatedFriends = [];

            // 更新好友資料
            friendUpdates.forEach(update => {
                // 支援個人好友和群組的匹配邏輯.
                const friend = this.friends.find(f => {
                    if (update.group_id) {
                        // 群組匹配：使用 group_id.
                        return f.group_id === update.group_id;
                    } else {
                        // 個人好友匹配：使用 line_user_id.
                        return f.line_user_id === update.line_user_id;
                    }
                });
                if (friend) {
                    // 更新未讀數
                    const oldUnreadCount = friend.unread_count || 0;
                    const oldLastMessage = friend.last_message || '';

                    friend.unread_count = update.unread_count || 0;

                    // 更新最後訊息
                    if (update.last_message) {
                        friend.last_message = update.last_message;
                    }
                    if (update.last_message_time) {
                        friend.last_message_time = update.last_message_time;
                    }
                    if (update.last_message_timestamp) {
                        // 確保時間戳為數字格式
                        friend.last_message_timestamp = typeof update.last_message_timestamp === 'number'
                            ? update.last_message_timestamp
                            : new Date(update.last_message_timestamp).getTime() / 1000;
                    }

                    // 檢查是否有需要重新排序的變化（新訊息或未讀數變化）
                    if (oldUnreadCount !== friend.unread_count ||
                        (update.last_message && oldLastMessage !== update.last_message)) {
                        hasUpdates = true;
                        updatedFriends.push(friend);

                        if (friend.id !== this.selectedFriendId) {
                            needsReorder = true;
                        }

                    }
                }
            });

            if (hasUpdates) {
                if (needsReorder) {
                    // 需要重新排序時才重新渲染整個列表
                    this.sortFriendList();
                    this.renderFriendList()
                }
                updatedFriends.forEach(friend => {
                    this.updateFriendItem(friend);
                });
            }
        },

        /**
         * 更新單個好友項目（避免重新渲染整個列表）
         * @param {object} friend - 好友物件
         */
        updateFriendItem: function (friend) {
            let friendItem;

            // 根據是否有 group_id 來精確查找.
            if (friend.group_id) {
                friendItem = this.friendList.find(
                    `[data-friend-id="${friend.id}"][data-group-id="${friend.group_id}"]`
                );
            } else {
                friendItem = this.friendList.find(`[data-friend-id="${friend.id}"]`)
                    .filter(function() {
                        return !$(this).data('group-id');
                    });
            }

            if (friendItem.length === 0) {
                return;
            }

            // 更新最後訊息.
            const lastMessageElement = friendItem.find('.last-message');
            if (lastMessageElement.length && friend.last_message) {
                lastMessageElement.text(this.escapeHtml(friend.last_message));
            }

            // 更新最後訊息時間.
            const lastMessageTimeElement = friendItem.find('.last-message-time');
            if (lastMessageTimeElement.length && friend.last_message_time) {
                lastMessageTimeElement.text(this.escapeHtml(friend.last_message_time));
            }

            // 更新未讀徽章.
            const badge = friendItem.find('.unread-badge');
            if (badge.length) {
                if (friend.unread_count > 0) {
                    badge.addClass('show').removeClass('hide').text(friend.unread_count);
                } else {
                    badge.addClass('hide').removeClass('show');
                }
            }

            // 更新在線狀態.
            const onlineStatus = friendItem.find('.online-status');
            if (onlineStatus.length) {
                onlineStatus.removeClass('online offline');
                onlineStatus.addClass(friend.is_online ? 'online' : 'offline');
            }
        },

        /**
         * 去除好友列表中的重複項目
         */
        deduplicateFriends: function () {
            const uniqueFriends = [];
            const seenLineUserIds = new Set();
            const seenGroupIds = new Set();

            this.friends.forEach(friend => {
                let isDuplicate = false;

                // 群組使用 group_id 判斷重複.
                if (friend.group_id) {
                    isDuplicate = seenGroupIds.has(friend.group_id);
                    if (!isDuplicate) {
                        seenGroupIds.add(friend.group_id);
                    }
                } else {
                    // 個人好友使用 line_user_id 判斷重複（排除空字串）.
                    if (friend.line_user_id && friend.line_user_id !== '') {
                        isDuplicate = seenLineUserIds.has(friend.line_user_id);
                        if (!isDuplicate) {
                            seenLineUserIds.add(friend.line_user_id);
                        }
                    }
                }

                if (!isDuplicate) {
                    uniqueFriends.push(friend);
                }
            });

            const removedCount = this.friends.length - uniqueFriends.length;
            if (removedCount > 0) {
                this.friends = uniqueFriends;
            }
        },

        /**
         * 排序好友列表：有未讀訊息 -> 最近傳送訊息 -> 加入時間
         */
        sortFriendList: function () {
            // 確保 this.friends 是陣列
            if (!Array.isArray(this.friends)) {
                console.error('FriendListComponent: sortFriendList - friends 不是陣列', this.friends);
                this.friends = [];
                return;
            }

            // 在排序前先去重
            this.deduplicateFriends();

            this.friends.sort((a, b) => {
                // 1. 有未讀訊息的優先
                const aUnread = parseInt(a.unread_count) || 0;
                const bUnread = parseInt(b.unread_count) || 0;

                if (aUnread > 0 && bUnread === 0) {
                    return -1;
                }
                if (aUnread === 0 && bUnread > 0) {
                    return 1;
                }

                // 2. 最近傳送訊息時間（都有未讀或都沒未讀時）
                // 統一使用 last_message_timestamp，確保時間戳為秒級別
                let aTimestamp = 0;
                let bTimestamp = 0;

                if (a.last_message_timestamp) {
                    aTimestamp = typeof a.last_message_timestamp === 'number'
                        ? a.last_message_timestamp
                        : new Date(a.last_message_timestamp).getTime() / 1000;
                } else if (a.followed_at) {
                    aTimestamp = new Date(a.followed_at).getTime() / 1000;
                }

                if (b.last_message_timestamp) {
                    bTimestamp = typeof b.last_message_timestamp === 'number'
                        ? b.last_message_timestamp
                        : new Date(b.last_message_timestamp).getTime() / 1000;
                } else if (b.followed_at) {
                    bTimestamp = new Date(b.followed_at).getTime() / 1000;
                }

                if (Math.abs(aTimestamp - bTimestamp) > 1) { // 差異超過1秒才進行排序
                    return bTimestamp - aTimestamp; // 最近的時間優先
                }

                // 3. 加入時間（最近加入的優先）
                const aFollowed = new Date(a.followed_at || 0).getTime() / 1000;
                const bFollowed = new Date(b.followed_at || 0).getTime() / 1000;
                return bFollowed - aFollowed;
            });

        },

        /**
         * 檢查用戶是否正在互動
         * @returns {boolean}
         */
        isUserInteracting: function () {
            // 檢查用戶是否在最近 5 秒內有互動
            const lastInteraction = this.lastUserInteraction || 0;
            return (Date.now() - lastInteraction) < 5000;
        },

        /**
         * 增量添加好友到列表
         * @param {array} newFriends - 新好友列表
         */
        addFriendsIncremental: function (newFriends) {
            if (!Array.isArray(newFriends) || newFriends.length === 0) {
                return;
            }

            let addedCount = 0;
            newFriends.forEach(friend => {
                // 檢查是否已存在（同時檢查 ID 和 line_user_id）
                const existingFriend = this.friends.find(f => f.id === friend.id || f.line_user_id === friend.line_user_id);
                if (!existingFriend) {
                    this.friends.push(friend);
                    addedCount++;
                } else {
                    // 選擇性更新現有好友的資料，避免覆蓋現有完整資訊
                    if (friend.unread_count !== undefined && friend.unread_count !== null) {
                        existingFriend.unread_count = friend.unread_count;
                    }
                    if (friend.last_message && friend.last_message !== '尚無對話' && friend.last_message.trim() !== '') {
                        existingFriend.last_message = friend.last_message;
                    }
                    if (friend.last_message_time && friend.last_message_time.trim() !== '') {
                        existingFriend.last_message_time = friend.last_message_time;
                    }
                    if (friend.last_message_timestamp) {
                        existingFriend.last_message_timestamp = friend.last_message_timestamp;
                    }
                    // 更新其他重要欄位
                    if (friend.followed_at) {
                        existingFriend.followed_at = friend.followed_at;
                    }
                    if (friend.last_active) {
                        existingFriend.last_active = friend.last_active;
                    }
                    if (friend.read_time) {
                        existingFriend.read_time = friend.read_time;
                    }
                }
            });

            if (addedCount > 0) {
                // 添加新好友後需要重新排序
                this.sortFriendList();
                this.renderFriendList();
            }
        },

        /**
         * 更新好友的未讀數
         * @param {string} friendId - 好友 ID 或 LINE User ID
         * @param {number} unreadCount - 未讀數
         * @param {string|null} groupId - 群組 ID（可選）
         */
        updateFriendUnreadCount: function (friendId, unreadCount, groupId = null) {
            // 使用 getFriend 方法查找，支援 groupId.
            let friend = this.getFriend(friendId, groupId);

            // 如果找不到且 groupId 為 null，嘗試用 line_user_id 查找.
            if (!friend && !groupId) {
                friend = this.friends.find(f => f.line_user_id === friendId && !f.group_id);
            }

            if (friend && friend.unread_count !== unreadCount) {
                friend.unread_count = unreadCount;

                // 只更新徽章，不重新渲染整個列表.
                let friendItem;
                if (friend.group_id) {
                    friendItem = this.friendList.find(
                        `[data-friend-id="${friend.id}"][data-group-id="${friend.group_id}"]`
                    );
                } else {
                    friendItem = this.friendList.find(`[data-friend-id="${friend.id}"]`)
                        .filter(function() {
                            return !$(this).data('group-id');
                        });
                }

                if (friendItem.length) {
                    const badge = friendItem.find('.unread-badge');
                    if (unreadCount > 0) {
                        badge.addClass('show').removeClass('hide').text(unreadCount);
                    } else {
                        badge.addClass('hide').removeClass('show');
                    }
                }
            }
        },

        /**
         * 自動選擇第一位好友
         */
        autoSelectFirstFriend: function () {
            if (this.friends.length > 0 && !this.selectedFriendId) {
                const firstFriend = this.friends[0];

                // 模擬點擊第一位好友（精確查找）.
                let friendElement;
                if (firstFriend.group_id) {
                    friendElement = this.friendList.find(
                        `[data-friend-id="${firstFriend.id}"][data-group-id="${firstFriend.group_id}"]`
                    ).first();
                } else {
                    friendElement = this.friendList.find(`[data-friend-id="${firstFriend.id}"]`)
                        .filter(function() {
                            return !$(this).data('group-id');
                        })
                        .first();
                }

                if (friendElement.length) {
                    this.handleFriendSelect({
                        currentTarget: friendElement[0],
                        preventDefault: () => {
                        }
                    });
                }
            }
        },

        /**
         * 同步狀態 (供外部元件調用)
         * @param {object} state - 狀態物件
         */
        syncState: function (state) {
            const friendChanged = state.currentFriend && state.currentFriend !== this.selectedFriendId;
            const groupChanged = state.currentGroupId !== this.selectedGroupId;

            if (friendChanged || groupChanged) {
                this.selectedFriendId = state.currentFriend;
                this.selectedGroupId = state.currentGroupId || null;
                this.highlightSelectedFriend(state.currentFriend, state.currentGroupId);
            }
        },

        /**
         * 銷毀元件
         */
        destroy: function () {
            this.searchInput.off();
            this.searchClear.off();
            this.friendList.off();
            $(document).off('message:sent', this.handleMessageSent);
            $(document).off('friend:updated', this.handleFriendUpdated);
            $(document).off('messages:marked-as-read', this.handleMessagesMarkedAsRead);
            $(document).off('polling:new-friends', this.handlePollingNewFriends);
            $(document).off('polling:friend-updates', this.handlePollingFriendUpdates);
        }
    };

})(jQuery);