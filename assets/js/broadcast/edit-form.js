/**
 * OrderChatz 推播編輯表單基礎交互
 *
 * 處理表單標題、狀態編輯、表單提交等基礎功能
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function ($) {
	'use strict';

	/**
	 * 表單交互管理器
	 */
	const EditFormManager = {

		/**
		 * 初始化
		 */
		init: function () {
			this.initTitlePrompt();
			this.initStatusEditor();
			this.initBroadcastConfirmation();
		},

		/**
		 * 初始化標題提示文字
		 */
		initTitlePrompt: function () {
			$('#title').on('input', function () {
				if ($(this).val()) {
					$('#title-prompt-text').hide();
				} else {
					$('#title-prompt-text').show();
				}
			});
		},

		/**
		 * 初始化狀態編輯器
		 */
		initStatusEditor: function () {
			// 編輯按鈕.
			$('.edit-post-status').on('click', function (e) {
				e.preventDefault();
				$('#post-status-select').slideDown();
				$(this).hide();
			});

			// 儲存按鈕.
			$('.save-post-status').on('click', function (e) {
				e.preventDefault();
				const status = $('#post_status').val();
				const statusText = $('#post_status option:selected').text();
				$('#post-status-display').text(statusText);
				$('#post-status-select').slideUp();
				$('.edit-post-status').show();
			});

			// 取消按鈕.
			$('.cancel-post-status').on('click', function (e) {
				e.preventDefault();
				$('#post-status-select').slideUp();
				$('.edit-post-status').show();
			});
		},

		/**
		 * 初始化推播確認對話框
		 */
		initBroadcastConfirmation: function () {
			const confirmMessage = otzBroadcast?.i18n?.broadcast_confirm || '確定要立即發送推播嗎？此操作無法復原。';

			// 監聽右側邊欄的「儲存並推播」按鈕.
			$('#save-and-broadcast').on('click', function (e) {
				if (!confirm(confirmMessage)) {
					e.preventDefault();
					return false;
				}
			});

			// 監聽快速儲存區的「儲存並推播」按鈕.
			$('#quick-save-and-broadcast').on('click', function (e) {
				if (!confirm(confirmMessage)) {
					e.preventDefault();
					return false;
				}
			});
		},
	};

	// 文件準備就緒時初始化.
	$(document).ready(function () {
		EditFormManager.init();
	});

})(jQuery);
