/**
 * OrderChatz 推播訊息編輯器
 *
 * 處理訊息類型切換、媒體上傳、字元計數等功能
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function ($) {
	'use strict';

	/**
	 * 訊息編輯器管理器
	 */
	const MessageEditorManager = {

		/**
		 * 初始化
		 */
		init: function () {
			this.initMessageTypeSelector();
			this.initCharCounter();
			this.initMediaUploader();
			this.initTestMessageSender();
			this.initMediaPreviewState();
		},

		/**
		 * 初始化訊息類型選擇器
		 */
		initMessageTypeSelector: function () {
			const self = this;

			$('input[name="message_type"]').on('change', function () {
				const type = $(this).val();

				// 更新視覺狀態.
				$('.message-type-selector label').removeClass('active');
				$(this).closest('label').addClass('active');

				// 切換編輯器顯示.
				$('.message-editor-section').removeClass('active');
				$('.message-editor-section.' + type + '-message-editor').addClass('active');

				// 更新 required 屬性.
				self.updateRequiredAttributes(type);
			});
		},

		/**
		 * 更新必填屬性
		 *
		 * @param {string} type 訊息類型.
		 */
		updateRequiredAttributes: function (type) {
			// 移除所有 required.
			$('.message-editor-section textarea, .message-editor-section input[type="hidden"]')
				.removeAttr('required');

			// 只對當前類型的編輯器設定 required.
			$('.message-editor-section.' + type + '-message-editor textarea, ' +
				'.message-editor-section.' + type + '-message-editor input[type="hidden"]')
				.attr('required', 'required');
		},

		/**
		 * 初始化字元計數器
		 */
		initCharCounter: function () {
			$('#message_text').on('input', function () {
				const length = $(this).val().length;
				$('#char-count').text(length);

				// 更新警告狀態.
				const $charCount = $('.char-count');
				$charCount.removeClass('warning error');

				if (length > 500) {
					$charCount.addClass('error');
				} else if (length > 450) {
					$charCount.addClass('warning');
				}
			}).trigger('input'); // 初始化時觸發一次.
		},

		/**
		 * 初始化媒體上傳器
		 */
		initMediaUploader: function () {
			this.initImageUploader();
			this.initVideoUploader();
			this.initVideoPreviewImageUploader();
			this.initMediaRemover();
		},

		/**
		 * 初始化圖片上傳器
		 */
		initImageUploader: function () {
			$('#upload-image-button').on('click', function (e) {
				e.preventDefault();

				const mediaUploader = wp.media({
					title: otzBroadcast.i18n?.select_image || '選擇圖片',
					button: {
						text: otzBroadcast.i18n?.use_image || '使用此圖片'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});

				mediaUploader.on('select', function () {
					const attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#message_image_url').val(attachment.url);
					$('#image-upload-area').hide();
					$('#image-preview')
						.html('<img src="' + attachment.url + '" alt=""><button type="button" class="remove-image">×</button>')
						.show();
				});

				mediaUploader.open();
			});
		},

		/**
		 * 初始化影片上傳器
		 */
		initVideoUploader: function () {
			$('#upload-video-button').on('click', function (e) {
				e.preventDefault();

				const mediaUploader = wp.media({
					title: otzBroadcast.i18n?.select_video || '選擇影片',
					button: {
						text: otzBroadcast.i18n?.use_video || '使用此影片'
					},
					multiple: false,
					library: {
						type: 'video'
					}
				});

				mediaUploader.on('select', function () {
					const attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#message_video_url').val(attachment.url);
					$('#video-upload-area').hide();
					$('#video-preview')
						.html('<video src="' + attachment.url + '" controls></video><button type="button" class="remove-media" data-target="video">×</button>')
						.show();
				});

				mediaUploader.open();
			});
		},

		/**
		 * 初始化影片封面圖上傳器
		 */
		initVideoPreviewImageUploader: function () {
			$('#upload-video-preview-image-button').on('click', function (e) {
				e.preventDefault();

				const mediaUploader = wp.media({
					title: otzBroadcast.i18n?.select_video_preview_image || '選擇影片封面圖',
					button: {
						text: otzBroadcast.i18n?.use_image || '使用此圖片'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});

				mediaUploader.on('select', function () {
					const attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#message_video_preview_url').val(attachment.url);
					$('#video-preview-image-upload-area').hide();
					$('#video-preview-image-preview')
						.html('<img src="' + attachment.url + '" alt=""><button type="button" class="remove-media" data-target="video-preview-image">×</button>')
						.show();
				});

				mediaUploader.open();
			});
		},

		/**
		 * 初始化媒體移除功能
		 */
		initMediaRemover: function () {
			// 處理圖片移除.
			$(document).on('click', '.remove-image', function () {
				const $preview = $(this).closest('.image-preview');
				const $input = $preview.siblings('input[type="hidden"]');

				$input.val('');
				$preview.hide().html('');
				$('#image-upload-area').show();
			});

			// 處理影片和影片封面圖移除.
			$(document).on('click', '.remove-media', function () {
				const target = $(this).data('target');

				if (target === 'video') {
					$('#message_video_url').val('');
					$('#video-preview').hide().html('');
					$('#video-upload-area').show();
				} else if (target === 'video-preview-image') {
					$('#message_video_preview_url').val('');
					$('#video-preview-image-preview').hide().html('');
					$('#video-preview-image-upload-area').show();
				}
			});
		},

		/**
		 * 初始化媒體預覽狀態
		 *
		 * 頁面載入時檢查是否有已上傳的媒體，若有則隱藏上傳區塊.
		 */
		initMediaPreviewState: function () {
			// 檢查圖片.
			if ($('#image-preview').is(':visible') && $('#image-preview img').length > 0) {
				$('#image-upload-area').hide();
			}

			// 檢查影片.
			if ($('#video-preview').is(':visible') && $('#video-preview video').length > 0) {
				$('#video-upload-area').hide();
			}

			// 檢查影片封面圖.
			if ($('#video-preview-image-preview').is(':visible') && $('#video-preview-image-preview img').length > 0) {
				$('#video-preview-image-upload-area').hide();
			}
		},

		/**
		 * 初始化測試訊息發送功能
		 */
		initTestMessageSender: function () {
			const self = this;

			$('#send-test-message').on('click', function () {
				const $button = $(this);
				const $status = $('.test-message-status');

				// 驗證測試 User ID.
				const testUserId = $('#test_line_user_id').val().trim();
				if (!testUserId) {
					self.showTestStatus($status, 'error', otzBroadcast.i18n?.test_user_id_required || '請輸入測試用 LINE User ID');
					return;
				}

				// 取得訊息類型.
				const messageType = $('input[name="message_type"]:checked').val();

				// 取得訊息內容.
				let messageContent = '';
				let videoPreviewUrl = '';
				switch (messageType) {
					case 'text':
						messageContent = $('#message_text').val().trim();
						break;
					case 'image':
						messageContent = $('#message_image_url').val().trim();
						break;
					case 'video':
						messageContent = $('#message_video_url').val().trim();
						videoPreviewUrl = $('#message_video_preview_url').val().trim();
						break;
					case 'flex':
						messageContent = $('#message_flex').val().trim();
						break;
				}

				// 驗證訊息內容.
				if (!messageContent) {
					self.showTestStatus($status, 'error', otzBroadcast.i18n?.message_content_required || '請先填寫訊息內容');
					return;
				}

				// 驗證影片封面圖.
				if (messageType === 'video' && !videoPreviewUrl) {
					self.showTestStatus($status, 'error', '請上傳影片封面圖');
					return;
				}

				// 準備發送資料.
				const data = {
					action: 'otz_test_message',
					nonce: otzBroadcast.nonce,
					test_line_user_id: testUserId,
					message_type: messageType
				};

				// 根據訊息類型添加內容.
				data['message_content_' + messageType] = messageContent;

				// 影片類型需要額外傳送封面圖.
				if (messageType === 'video') {
					data['message_content_video_preview'] = videoPreviewUrl;
				}

				// 發送測試訊息.
				$button.prop('disabled', true).text(otzBroadcast.i18n?.sending || '發送中...');
				$status.removeClass('success error').text('');

				$.ajax({
					url: otzBroadcast.ajaxUrl,
					type: 'POST',
					data: data,
					success: function (response) {
						if (response.success) {
							self.showTestStatus($status, 'success', response.data.message || otzBroadcast.i18n?.test_message_sent || '測試訊息已發送');
						} else {
							self.showTestStatus($status, 'error', response.data.message || otzBroadcast.i18n?.test_message_failed || '測試訊息發送失敗');
						}
					},
					error: function (xhr, status, error) {
						self.showTestStatus($status, 'error', otzBroadcast.i18n?.ajax_error || 'AJAX 請求失敗: ' + error);
					},
					complete: function () {
						$button.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> ' + (otzBroadcast.i18n?.send_test_message || '發送測試訊息'));
					}
				});
			});
		},

		/**
		 * 顯示測試狀態訊息
		 *
		 * @param {jQuery} $element 狀態元素.
		 * @param {string} type     訊息類型（success/error）.
		 * @param {string} message  訊息內容.
		 */
		showTestStatus: function ($element, type, message) {
			$element.removeClass('success error')
				.addClass(type)
				.text(message);

			// 3 秒後自動清除訊息.
			setTimeout(function () {
				$element.fadeOut(function () {
					$(this).text('').removeClass('success error').show();
				});
			}, 3000);
		}
	};

	// 文件準備就緒時初始化.
	$(document).ready(function () {
		MessageEditorManager.init();
	});

})(jQuery);
