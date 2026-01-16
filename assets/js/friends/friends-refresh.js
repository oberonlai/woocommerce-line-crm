jQuery(document).ready(function($) {
	// 處理重新取得好友資料按鈕點擊
	$(document).on('click', '.refresh-friend-data', function(e) {
		e.preventDefault();

		const button = $(this);
		const friendId = button.data('friend-id');
		const nonce = button.data('nonce');

		if (!confirm(otz_friends_refresh.messages.confirm)) {
			return;
		}

		// 禁用按鈕並顯示載入狀態
		const originalText = button.text();
		button.prop('disabled', true).text(otz_friends_refresh.messages.processing);

		// 發送 AJAX 請求
		$.ajax({
			url: otz_friends_refresh.ajax_url,
			type: 'POST',
			data: {
				action: 'otz_refresh_friend_data',
				friend_id: friendId,
				nonce: nonce
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					// 重新載入頁面以顯示更新後的資料
					window.location.reload();
				} else {
					alert(response.data.message || otz_friends_refresh.messages.error);
				}
			},
			error: function() {
				alert(otz_friends_refresh.messages.error);
			},
			complete: function() {
				// 恢復按鈕狀態
				button.prop('disabled', false).text(originalText);
			}
		});
	});
});