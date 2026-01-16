/**
 * OrderChatz 文字輸入 Value Renderer
 *
 * 渲染簡單的文字輸入框,支援即時更新受眾計數.
 *
 * @package OrderChatz
 * @since 1.1.4
 */

(function($) {
	'use strict';

	const TextRenderer = {

		/**
		 * 渲染文字輸入框.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @param {Object} config - 配置參數.
		 * @param {*} currentValue - 當前值.
		 * @return {jQuery} Input 元素.
		 */
		render: function($container, config, currentValue) {
			const inputId = 'text-input-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
			const placeholder = config.placeholder || '輸入值...';
			const value = currentValue || '';

			$container.html(`
				<input type="text"
				       id="${inputId}"
				       class="condition-value text-input"
				       value="${value}"
				       placeholder="${placeholder}"
				       style="width: 100%;">
			`);

			const $input = $container.find('input');

			// 監聽失去焦點事件,觸發受眾計數更新.
			$input.on('blur', function() {
				$(document).trigger('otz:condition:value-changed');
			});

			return $input;
		},

		/**
		 * 取得當前值.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @return {string|null} 輸入的文字.
		 */
		getValue: function($container) {
			const $input = $container.find('input');
			const value = $input.val();

			// 返回去除前後空白的值,如果為空則返回 null.
			return value && value.trim() !== '' ? value.trim() : null;
		},

		/**
		 * 驗證值.
		 *
		 * @param {*} value - 要驗證的值.
		 * @return {boolean} 是否有效.
		 */
		validate: function(value) {
			return value !== null && value !== '' && value !== undefined;
		},

		/**
		 * 銷毀輸入框.
		 *
		 * @param {jQuery} $container - 容器元素.
		 */
		destroy: function($container) {
			const $input = $container.find('input');
			$input.off('blur');
		}
	};

	// 等待 Registry 準備好後註冊.
	$(document).on('otz:registry:ready', function() {
		if (window.OTZ && window.OTZ.ValueRendererRegistry) {
			window.OTZ.ValueRendererRegistry.register('text', TextRenderer);
		}
	});

})(jQuery);
