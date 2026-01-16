/**
 * Bot 編輯器 JavaScript
 *
 * 處理 Bot 編輯頁面的所有互動邏輯
 *
 * @package OrderChatz
 */

(function ($) {
	'use strict';

	/**
	 * Bot 編輯器類別
	 */
	const BotEditor = {
		/**
		 * 初始化
		 */
		init: function () {
			this.keywordInput();
			this.actionTypeToggle();
			this.quickReplies();
			this.postTypeSelector();
			this.apiKeyMasking();
			this.formValidation();
		},

		/**
		 * 關鍵字標籤輸入功能
		 */
		keywordInput: function () {
			const $input = $('#keyword-input');
			const $addBtn = $('.add-keyword-button');
			const $list = $('.keywords-list');

			// 新增關鍵字
			const addKeyword = function () {
				const keyword = $input.val().trim();

				if (keyword === '') {
					return;
				}

				// 檢查是否已存在
				const exists = $list
					.find('input[type="hidden"]')
					.filter(function () {
						return $(this).val() === keyword;
					}).length > 0;

				if (exists) {
					alert(otzBotEdit.keywordExists || '此關鍵字已存在');
					$input.val('').focus();
					return;
				}

				// 建立標籤元素
				const $tag = $('<span>', {
					class: 'keyword-item',
					html:
						keyword +
						'<button type="button" class="remove-keyword">×</button>' +
						'<input type="hidden" name="bot_keywords[]" value="' +
						keyword +
						'">',
				});

				$list.append($tag);
				$input.val('').focus();
			};

			// 按鈕點擊
			$addBtn.on('click', addKeyword);

			// Enter 鍵
			$input.on('keypress', function (e) {
				if (e.which === 13) {
					e.preventDefault();
					addKeyword();
				}
			});

			// 刪除關鍵字
			$list.on('click', '.remove-keyword', function () {
				$(this).closest('.keyword-item').remove();
			});
		},

		/**
		 * Action Type 切換
		 */
		actionTypeToggle: function () {
			const $radios = $('.action-type-radio');
			const $aiSettings = $('#ai-settings-div');
			const $functionTools = $('#function-tools-div');
			const $handoffMessage = $('#handoff-message-div');

			$radios.on('change', function () {
				const value = $(this).val();

				if (value === 'ai') {
					$aiSettings.addClass('active').slideDown(300);
					$functionTools.addClass('active').slideDown(300);
					$handoffMessage.removeClass('active').slideUp(300);
				} else {
					$aiSettings.removeClass('active').slideUp(300);
					$functionTools.removeClass('active').slideUp(300);
					$handoffMessage.addClass('active').slideDown(300);
				}
			});
		},

		/**
		 * Quick Replies Repeater
		 */
		quickReplies: function () {
			const $list = $('#quick-replies-list');
			const $addBtn = $('#add-reply');
			const maxLength = 15;

			// 更新字元計數器
			const updateCharCounter = function ($input) {
				const length = $input.val().length;
				const $counter = $input.siblings('.char-counter');
				$counter.text(length + '/' + maxLength);

				// 接近或達到上限時改變顏色
				if (length >= maxLength) {
					$counter.css('color', '#d63638');
				} else if (length >= maxLength - 3) {
					$counter.css('color', '#dba617');
				} else {
					$counter.css('color', '#646970');
				}
			};

			// 新增問題
			$addBtn.on('click', function () {
				const $newItem = $('<div>', {
					class: 'quick-reply-item',
					html:
						'<input type="text" name="quick_replies[]" class="quick-reply-input" value="" placeholder="' +
						(otzBotEdit.enterQuestion || '輸入問題...') +
						'" maxlength="' +
						maxLength +
						'" data-max-length="' +
						maxLength +
						'">' +
						'<span class="char-counter">0/' +
						maxLength +
						'</span>' +
						'<button type="button" class="button remove-reply">−</button>',
				});

				$list.append($newItem);
				$newItem.find('input').focus();
			});

			// 刪除問題
			$list.on('click', '.remove-reply', function () {
				const $items = $list.find('.quick-reply-item');

				// 至少保留一個輸入框
				if ($items.length > 1) {
					$(this).closest('.quick-reply-item').remove();
				} else {
					const $input = $(this).siblings('input');
					$input.val('');
					updateCharCounter($input);
				}
			});

			// 即時更新字元計數器
			$list.on('input', '.quick-reply-input', function () {
				updateCharCounter($(this));
			});

			// 初始化已存在的字元計數器
			$list.find('.quick-reply-input').each(function () {
				updateCharCounter($(this));
			});
		},

		/**
		 * Post Type 選擇器
		 */
		postTypeSelector: function () {
			const $toggle = $('.custom-post-type-toggle');
			const $wrapper = $('.post-type-selector-wrapper');
			const $select = $('#custom-post-types');

			// 初始化 Select2
			if ($select.length > 0 && typeof $select.select2 === 'function') {
				$select.select2({
					placeholder: otzBotEdit.selectPostTypes || '選擇文章類型...',
					allowClear: true,
					width: '100%',
				});
			}

			// Toggle 切換事件
			$toggle.on('change', function () {
				if ($(this).is(':checked')) {
					$wrapper.slideDown(300);
				} else {
					$wrapper.slideUp(300);
				}
			});
		},

		/**
		 * API 金鑰遮蔽處理
		 */
		apiKeyMasking: function () {
			const $apiKeyInput = $('#api_key');

			if ($apiKeyInput.length === 0) {
				return;
			}

			const isMasked = $apiKeyInput.data('is-masked') === 'true';
			const originalMasked = $apiKeyInput.data('original-masked');

			// 當欄位獲得焦點時.
			$apiKeyInput.on('focus', function () {
				const currentValue = $(this).val();

				// 如果當前值是遮蔽的,清空欄位供用戶輸入.
				if (currentValue === originalMasked && isMasked) {
					$(this).val('');
				}
			});

			// 當欄位失去焦點時.
			$apiKeyInput.on('blur', function () {
				const currentValue = $(this).val().trim();

				// 如果用戶未輸入任何內容,恢復遮蔽顯示.
				if (currentValue === '' && isMasked && originalMasked) {
					$(this).val(originalMasked);
				}
			});
		},

		/**
		 * 表單驗證
		 */
		formValidation: function () {
			const $form = $('#bot-form');

			$form.on('submit', function (e) {
				let errors = [];

				// 驗證 Bot 名稱
				const botName = $('#title').val().trim();
				if (botName === '') {
					errors.push(otzBotEdit.requiredName || 'Bot 名稱為必填');
				}

				// 驗證關鍵字
				const keywordCount = $('.keywords-list input[type="hidden"]').length;
				if (keywordCount === 0) {
					errors.push(otzBotEdit.requiredKeywords || '至少需要一個關鍵字');
				}

				// 驗證根據 action_type
				const actionType = $('input[name="action_type"]:checked').val();
				if (actionType === 'ai') {
					// 驗證 Custom Post Type 設定
					const customPostTypeEnabled = $('.custom-post-type-toggle').is(':checked');
					if (customPostTypeEnabled) {
						const selectedPostTypes = $('#custom-post-types').val();
						if (!selectedPostTypes || selectedPostTypes.length === 0) {
							errors.push(
								otzBotEdit.requiredPostTypes ||
									'請至少選擇一個文章類型'
							);
						}
					}
				} else if (actionType === 'human') {
					// 驗證 handoff_message (human 模式必填)
					const handoffMessage = $('#handoff_message').val().trim();
					if (handoffMessage === '') {
						errors.push(otzBotEdit.requiredHandoffMessage || '轉接訊息為必填欄位');
					}
				}

				// 顯示錯誤訊息
				if (errors.length > 0) {
					e.preventDefault();
					alert(errors.join('\n'));
					return false;
				}

				return true;
			});
		},
	};

	/**
	 * 文件就緒時初始化
	 */
	$(document).ready(function () {
		if ($('#bot-form').length > 0) {
			BotEditor.init();
		}
	});
})(jQuery);
