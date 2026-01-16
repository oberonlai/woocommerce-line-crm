/**
 * OrderChatz 推播標籤管理器
 *
 * 處理標籤的新增與移除
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function ($) {
	'use strict';

	/**
	 * 標籤管理器
	 */
	const TagManager = {

		/**
		 * 初始化
		 */
		init: function () {
			this.initTagAdder();
			this.initTagRemover();
		},

		/**
		 * 初始化標籤新增功能
		 */
		initTagAdder: function () {
			const self = this;

			// 點擊新增按鈕.
			$('.add-tag-button').on('click', function () {
				self.addTag();
			});

			// Enter 或逗號鍵新增.
			$('#campaign_tags_input').on('keypress', function (e) {
				if (e.which === 13 || e.which === 188) { // Enter or comma.
					e.preventDefault();
					self.addTag();
				}
			});
		},

		/**
		 * 新增標籤
		 */
		addTag: function () {
			const $tagInput = $('#campaign_tags_input');
			const tag = $tagInput.val().trim();

			if (!tag) {
				return;
			}

			// 檢查是否已存在.
			const exists = $('.tags-list input[type="hidden"]').filter(function () {
				return $(this).val() === tag;
			}).length > 0;

			if (exists) {
				$tagInput.val('');
				return;
			}

			// 建立標籤 HTML.
			const tagHtml = this.createTagHTML(tag);
			$('.tags-list').append(tagHtml);
			$tagInput.val('');
		},

		/**
		 * 建立標籤 HTML
		 *
		 * @param {string} tag 標籤名稱.
		 * @return {string} HTML 字串.
		 */
		createTagHTML: function (tag) {
			const escapedTag = $('<div>').text(tag).html();
			return '<span class="tag-item">' +
				escapedTag +
				'<button type="button" class="remove-tag">×</button>' +
				'<input type="hidden" name="campaign_tags[]" value="' + escapedTag + '">' +
				'</span>';
		},

		/**
		 * 初始化標籤移除功能
		 */
		initTagRemover: function () {
			$(document).on('click', '.remove-tag', function () {
				$(this).closest('.tag-item').remove();
			});
		}
	};

	// 文件準備就緒時初始化.
	$(document).ready(function () {
		TagManager.init();
	});

})(jQuery);
