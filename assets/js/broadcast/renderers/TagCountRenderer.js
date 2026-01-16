/**
 * 標籤次數 Value Renderer
 *
 * 在單一容器內渲染兩個輸入元件:
 * 1. 標籤選擇器 (Select2)
 * 2. 次數輸入框 (Number)
 *
 * @package OrderChatz
 * @since 1.1.5
 */

(function($) {
	'use strict';

	/**
	 * 標籤次數 Renderer
	 */
	const TagCountRenderer = {

		/**
		 * 渲染 UI
		 *
		 * @param {jQuery} $container 容器元素.
		 * @param {Object} config 配置參數.
		 * @param {Object|null} initialValue 初始值 {tag_name, count}.
		 */
		render: function($container, config, initialValue) {
			const placeholderTag = config.placeholder?.tag || '選擇標籤...';
			const placeholderCount = config.placeholder?.count || '次數';

			// 渲染 HTML.
			const html = `
				<div class="tag-count-inputs" style="display: flex; gap: 8px; align-items: center;">
					<select class="tag-select" style="flex: 2; min-width: 200px;">
						<option value="">${placeholderTag}</option>
					</select>
					<input type="number"
					       class="count-input"
					       min="1"
					       placeholder="${placeholderCount}"
					       style="flex: 1; width: 80px;">
				</div>
			`;
			$container.html(html);

			// 初始化 Select2.
			const $tagSelect = $container.find('.tag-select');
			$tagSelect.select2({
				ajax: {
					url: window.otzFilterConfig.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							action: config.ajax_action || 'otz_search_customer_tags',
							nonce: window.otzFilterConfig.nonce,
							query: params.term || '',
							page: params.page || 1
						};
					},
					processResults: function(response) {
						if (response.success && response.data) {
							return response.data;
						}
						return {results: []};
					},
					cache: true
				},
				placeholder: placeholderTag,
				minimumInputLength: 0,
				allowClear: true
			});

			// 設定初始值.
			if (initialValue && initialValue.tag_name) {
				const option = new Option(
					initialValue.tag_name,
					initialValue.tag_name,
					true,
					true
				);
				$tagSelect.append(option).trigger('change');
				$container.find('.count-input').val(initialValue.count || '');
			}

			// 綁定變更事件.
			$tagSelect.on('change', function() {
				$(document).trigger('otz:condition:value-changed');
			});

			$container.find('.count-input').on('input', function() {
				$(document).trigger('otz:condition:value-changed');
			});
		},

		/**
		 * 取得值
		 *
		 * @param {jQuery} $container 容器元素.
		 * @return {Object} 值物件 {tag_name, count}.
		 */
		getValue: function($container) {
			const tagName = $container.find('.tag-select').val();
			const count = parseInt($container.find('.count-input').val()) || 0;

			return {
				tag_name: tagName || '',
				count: count
			};
		},

		/**
		 * 驗證值
		 *
		 * @param {Object} value 值物件.
		 * @return {boolean} 是否有效.
		 */
		validate: function(value) {
			if (!value || typeof value !== 'object') {
				return false;
			}

			return !!(value.tag_name && value.count && value.count > 0);
		},

		/**
		 * 銷毀 (清理 Select2)
		 *
		 * @param {jQuery} $container 容器元素.
		 */
		destroy: function($container) {
			const $select = $container.find('.tag-select');
			if ($select.data('select2')) {
				$select.select2('destroy');
			}
		}
	};

	// 註冊到 Registry.
	if (window.OTZ && window.OTZ.ValueRendererRegistry) {
		window.OTZ.ValueRendererRegistry.register('TagCountRenderer', TagCountRenderer);
	}

})(jQuery);
