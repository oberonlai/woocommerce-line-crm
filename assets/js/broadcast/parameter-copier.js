/**
 * OrderChatz 推播參數複製器
 *
 * 處理可帶入參數的複製功能
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function ($) {
	'use strict';

	/**
	 * 參數複製器管理器
	 */
	const ParameterCopierManager = {

		/**
		 * 通知顯示時間（毫秒）
		 */
		notificationDuration: 2000,

		/**
		 * 初始化
		 */
		init: function () {
			this.initParameterCopier();
		},

		/**
		 * 初始化參數複製功能
		 */
		initParameterCopier: function () {
			const self = this;

			$('.parameter-item').on('click', function () {
				const param = $(this).data('param');
				self.copyToClipboard(param);
			});
		},

		/**
		 * 複製文字到剪貼簿
		 *
		 * @param {string} text 要複製的文字.
		 */
		copyToClipboard: function (text) {
			// 使用現代 Clipboard API（如果支援）.
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text)
					.then(() => this.showNotification())
					.catch(() => this.fallbackCopy(text));
			} else {
				this.fallbackCopy(text);
			}
		},

		/**
		 * 後備複製方法（使用 execCommand）
		 *
		 * @param {string} text 要複製的文字.
		 */
		fallbackCopy: function (text) {
			const $tempInput = $('<input>');
			$('body').append($tempInput);
			$tempInput.val(text).select();

			try {
				document.execCommand('copy');
				this.showNotification();
			} catch (err) {
				console.error('複製失敗:', err);
			} finally {
				$tempInput.remove();
			}
		},

		/**
		 * 顯示複製通知
		 */
		showNotification: function () {
			const $notification = $('#copy-notification');

			$notification.addClass('show');

			setTimeout(function () {
				$notification.removeClass('show');
			}, this.notificationDuration);
		}
	};

	// 文件準備就緒時初始化.
	$(document).ready(function () {
		ParameterCopierManager.init();
	});

})(jQuery);
