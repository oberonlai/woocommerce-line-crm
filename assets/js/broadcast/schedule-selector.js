/**
 * OrderChatz 推播排程選擇器
 *
 * 處理排程類型切換與相關互動
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function ($) {
	'use strict';

	/**
	 * 排程選擇器管理器
	 */
	const ScheduleSelectorManager = {

		/**
		 * 初始化
		 */
		init: function () {
			this.initScheduleTypeSelector();
		},

		/**
		 * 初始化排程類型選擇器
		 */
		initScheduleTypeSelector: function () {
			$('input[name="schedule_type"]').on('change', function () {
				if ($(this).val() === 'scheduled') {
					// 排程發送：顯示時間選擇器，隱藏推播按鈕.
					$('.scheduled-datetime').addClass('active');
					$('#save-and-broadcast, #quick-save-and-broadcast').hide();
				} else {
					// 立即發送：隱藏時間選擇器，顯示推播按鈕.
					$('.scheduled-datetime').removeClass('active');
					$('#save-and-broadcast, #quick-save-and-broadcast').show();
				}
			});
		}
	};

	// 文件準備就緒時初始化.
	$(document).ready(function () {
		ScheduleSelectorManager.init();
	});

})(jQuery);
