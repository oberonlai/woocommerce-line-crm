/**
 * OrderChatz 標籤管理頁面 JavaScript
 *
 * 處理編輯模式切換、好友清單燈箱等功能
 *
 * @package OrderChatz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * 標籤管理類別
	 */
	const TagsManager = {
		/**
		 * 初始化
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * 綁定事件
		 */
		bindEvents: function() {
			// 編輯標籤 - 點擊編輯連結.
			$(document).on('click', '.edit-tag-link', this.handleEditTag.bind(this));

			// 取消編輯.
			$(document).on('click', '#cancel-edit-tag', this.handleCancelEdit.bind(this));

			// 查看好友清單 - 點擊好友人數.
			$(document).on('click', '.view-tag-users', this.handleViewUsers.bind(this));

			// 好友名稱點擊 - 開啟前台聊天介面.
			$(document).on('click', '.friend-name-link', this.handleFriendNameClick.bind(this));

			// 關閉燈箱.
			$(document).on('click', '.otz-modal-close, .otz-modal-backdrop', this.handleCloseModal.bind(this));

			// 表單提交後重置為新增模式.
			$('#tag-form').on('submit', this.handleFormSubmit.bind(this));
		},

		/**
		 * 處理編輯標籤
		 */
		handleEditTag: function(e) {
			e.preventDefault();

			const tagName = $(e.currentTarget).data('tag-name');
			if (!tagName) {
				return;
			}

			// 切換為編輯模式.
			this.switchToEditMode(tagName);
		},

		/**
		 * 切換為編輯模式
		 */
		switchToEditMode: function(tagName) {
			const $form = $('#tag-form');
			const $wrapper = $('.form-wrap');
			const $header = $('.form-wrap h2');
			const $submitBtn = $('#submit-tag-btn');
			const $cancelBtn = $('#cancel-edit-tag');

			// 更新表單.
			$form.find('input[name="action"]').val('edit_tag');
			$form.find('input[name="tag_name"]').val(tagName);
			$form.find('input[name="old_tag_name"]').remove(); // 移除舊的 hidden input.
			$form.append('<input type="hidden" name="old_tag_name" value="' + tagName + '">');

			// 更新 UI.
			$wrapper.addClass('edit-mode');
			$header.text(otzTags.i18n.editTag);
			$submitBtn.text(otzTags.i18n.updateTag);
			$cancelBtn.show();

			// 聚焦到輸入框.
			$form.find('input[name="tag_name"]').focus().select();
		},

		/**
		 * 處理取消編輯
		 */
		handleCancelEdit: function(e) {
			e.preventDefault();
			this.switchToAddMode();
		},

		/**
		 * 切換為新增模式
		 */
		switchToAddMode: function() {
			const $form = $('#tag-form');
			const $wrapper = $('.form-wrap');
			const $header = $('.form-wrap h2');
			const $submitBtn = $('#submit-tag-btn');
			const $cancelBtn = $('#cancel-edit-tag');

			// 重置表單.
			$form.find('input[name="action"]').val('add_tag');
			$form.find('input[name="tag_name"]').val('');
			$form.find('input[name="old_tag_name"]').remove();

			// 更新 UI.
			$wrapper.removeClass('edit-mode');
			$header.text(otzTags.i18n.addTag);
			$submitBtn.text(otzTags.i18n.addTag);
			$cancelBtn.hide();
		},

		/**
		 * 處理表單提交
		 */
		handleFormSubmit: function(e) {
			const action = $(e.currentTarget).find('input[name="action"]').val();

			// 如果是新增標籤，提交後重置表單.
			if (action === 'add_tag') {
				// 讓表單正常提交，頁面會重新載入.
			}
		},

		/**
		 * 處理查看好友清單
		 */
		handleViewUsers: function(e) {
			e.preventDefault();

			const tagName = $(e.currentTarget).data('tag-name');
			if (!tagName) {
				return;
			}

			this.showUsersModal(tagName);
		},

		/**
		 * 顯示好友清單燈箱 - 支援無限滾動
		 */
		showUsersModal: function(tagName) {
			const self = this;
			const $modal = $('#tag-users-modal');
			const $title = $('#tag-users-modal-title');
			const $loading = $('#tag-users-loading');
			const $content = $('#tag-users-content');
			const $empty = $('#tag-users-empty');
			const $loadingMore = $('#tag-users-loading-more');
			const $noMore = $('#tag-users-no-more');
			const $tbody = $('#tag-users-tbody');
			const $modalBody = $modal.find('.otz-modal-body');
			const $loadedCount = $('#tag-users-loaded-count');
			const $totalCount = $('#tag-users-total-count');

			// 重置狀態.
			$tbody.empty();
			$loading.show();
			$content.hide();
			$empty.hide();
			$loadingMore.hide();
			$noMore.hide();
			$loadedCount.text('0');
			$totalCount.text('--');

			// 分頁狀態.
			let currentPage = 0;
			let totalPages = 0;
			let isLoading = false;
			let loadedCount = 0;
			let totalFriends = 0;

			// 更新標題.
			$title.text(otzTags.i18n.usersWithTag + '「' + tagName + '」');

			// 顯示燈箱.
			$modal.addClass('active');

			// 載入下一頁.
			function loadNextPage() {
				if (isLoading || (totalPages > 0 && currentPage >= totalPages)) {
					return;
				}

				isLoading = true;
				currentPage++;

				// 首次載入顯示初始 loading,之後顯示載入更多.
				if (currentPage === 1) {
					$loading.show();
				} else {
					$loadingMore.show();
				}
				$noMore.hide();

				$.ajax({
					url: otzTags.ajaxUrl,
					type: 'POST',
					data: {
						action: 'otz_get_tag_users',
						nonce: otzTags.nonce,
						tag_name: tagName,
						page: currentPage,
						per_page: 20
					},
					success: function(response) {
						if (response.success && response.data.friends) {
							totalFriends = response.data.total;
							totalPages = response.data.total_pages;
							$totalCount.text(totalFriends);

							// 如果第一頁且沒有資料,顯示空狀態.
							if (currentPage === 1 && response.data.friends.length === 0) {
								$loading.hide();
								$empty.show();
								return;
							}

							// 顯示表格.
							if (currentPage === 1) {
								$loading.hide();
								$content.show();
							}

							// 渲染好友列表.
							response.data.friends.forEach(function(friend) {
								const displayName = friend.display_name || '無名稱';
								const lineUserId = friend.line_user_id || '';
								const friendId = friend.friend_id || '';
								const avatarUrl = friend.avatar_url || '';
								let wpUserHtml = '<span class="not-bound">未綁定</span>';
								let lastActiveHtml = '<span class="description">無記錄</span>';

								// WordPress 使用者資訊.
								if (friend.wp_user) {
									wpUserHtml = '<a href="' + friend.wp_user.edit_url + '" class="wp-user-link" target="_blank">' +
										friend.wp_user.name + '<br><small>' + friend.wp_user.email + '</small></a>';
								}

								// 最後活動時間.
								if (friend.last_active) {
									const lastActive = new Date(friend.last_active);
									lastActiveHtml = lastActive.getFullYear() + '-' +
										String(lastActive.getMonth() + 1).padStart(2, '0') + '-' +
										String(lastActive.getDate()).padStart(2, '0') + ' ' +
										String(lastActive.getHours()).padStart(2, '0') + ':' +
										String(lastActive.getMinutes()).padStart(2, '0');
								} else if (friend.followed_at) {
									const followedAt = new Date(friend.followed_at);
									lastActiveHtml = followedAt.getFullYear() + '-' +
										String(followedAt.getMonth() + 1).padStart(2, '0') + '-' +
										String(followedAt.getDate()).padStart(2, '0') + ' ' +
										String(followedAt.getHours()).padStart(2, '0') + ':' +
										String(followedAt.getMinutes()).padStart(2, '0') +
										'<br><small>加入時間</small>';
								}

								// 頭像 HTML.
								const avatarHtml = avatarUrl ?
									'<img src="' + avatarUrl + '" alt="' + displayName + '" class="tag-user-avatar" onerror="this.style.display=\'none\'">' :
									'';

								// 好友名稱連結 HTML.
								const friendNameHtml = friendId ?
									'<a href="#" class="friend-name-link" data-friend-id="' + friendId + '">' + displayName + '</a>' :
									'<strong>' + displayName + '</strong>';

								const rowHtml = '<tr>' +
									'<td>' +
										'<div class="tag-user-name-cell">' +
											avatarHtml +
											friendNameHtml +
										'</div>' +
									'</td>' +
									'<td>' + wpUserHtml + '</td>' +
									'<td>' + lastActiveHtml + '</td>' +
									'</tr>';

								$tbody.append(rowHtml);
								loadedCount++;
							});

							$loadedCount.text(loadedCount);

							// 檢查是否還有更多資料.
							if (!response.data.has_more) {
								$noMore.show();
							}
						} else {
							if (currentPage === 1) {
								$loading.hide();
								$empty.show();
							}
						}
					},
					error: function() {
						$loading.hide();
						$loadingMore.hide();
						alert(otzTags.i18n.loadError);
					},
					complete: function() {
						isLoading = false;
						$loadingMore.hide();
					}
				});
			}

			// 監聽滾動事件.
			$modalBody.off('scroll.tagUsers').on('scroll.tagUsers', function() {
				const scrollTop = $(this).scrollTop();
				const scrollHeight = $(this)[0].scrollHeight;
				const clientHeight = $(this).height();

				// 距離底部小於 100px 時載入下一頁.
				if (scrollHeight - scrollTop - clientHeight < 100) {
					loadNextPage();
				}
			});

			// 載入第一頁.
			loadNextPage();
		},

		/**
		 * 處理好友名稱點擊 - 開啟前台聊天介面
		 */
		handleFriendNameClick: function(e) {
			e.preventDefault();
			e.stopPropagation();

			// 使用 .attr() 而不是 .data(),避免 jQuery 快取問題.
			const friendId = $(e.currentTarget).attr('data-friend-id');
			if (!friendId) {
				return;
			}

			// 構建前台聊天 URL.
            const chatUrl = otzTags.adminChatUrl + '&chat=1&friend=' + friendId

			// 在新分頁開啟.
			window.open(chatUrl, '_blank');
		},

		/**
		 * 處理關閉燈箱
		 */
		handleCloseModal: function(e) {
			// 只有點擊關閉按鈕、背景或是按下 Escape 鍵才關閉.
			if ($(e.target).hasClass('otz-modal-close') ||
				$(e.target).hasClass('otz-modal-backdrop')) {
				const $modal = $('#tag-users-modal');
				const $modalBody = $modal.find('.otz-modal-body');

				// 移除滾動監聽.
				$modalBody.off('scroll.tagUsers');

				// 關閉燈箱.
				$modal.removeClass('active');
			}
		}
	};

	// 當 DOM 準備好時初始化.
	$(document).ready(function() {
		TagsManager.init();
	});

})(jQuery);
