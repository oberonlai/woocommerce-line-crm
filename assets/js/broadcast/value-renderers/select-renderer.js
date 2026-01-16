/**
 * OrderChatz 下拉選單 Value Renderer
 *
 * 渲染簡單的下拉選單,支援固定選項列表.
 *
 * @package OrderChatz
 * @since 1.1.4
 */

(function($) {
	'use strict';

	const SelectRenderer = {

		/**
		 * 渲染下拉選單.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @param {Object} config - 配置參數.
		 * @param {Array} config.options - 選項陣列，格式：[{value: '...', label: '...'}].
		 * @param {*} currentValue - 當前值.
		 * @return {jQuery} Select 元素.
		 */
		render: function($container, config, currentValue) {
			const selectId = 'select-input-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
			const options = config.options || [];
			const value = currentValue || '';

			// 建立 select 元素.
			let optionsHtml = '<option value="">-- 請選擇 --</option>';
			options.forEach(function(option) {
				const selected = option.value === value ? ' selected' : '';
				optionsHtml += `<option value="${option.value}"${selected}>${option.label}</option>`;
			});

			$container.html(`
				<select id="${selectId}"
				        class="condition-value select-input"
				        style="width: 100%;">
					${optionsHtml}
				</select>
			`);

			const $select = $container.find('select');

			// 監聽變更事件,觸發受眾計數更新.
			$select.on('change', function() {
				$(document).trigger('otz:condition:value-changed');
			});

			return $select;
		},

		/**
		 * 取得當前值.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @return {string|null} 選中的值.
		 */
		getValue: function($container) {
			const $select = $container.find('select');
			const value = $select.val();

			// 返回選中的值,如果為空則返回 null.
			return value && value !== '' ? value : null;
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
		 * 銷毀下拉選單.
		 *
		 * @param {jQuery} $container - 容器元素.
		 */
		destroy: function($container) {
			const $select = $container.find('select');
			$select.off('change');
		}
	};

	// 等待 Registry 準備好後註冊.
	$(document).on('otz:registry:ready', function() {
		if (window.OTZ && window.OTZ.ValueRendererRegistry) {
			window.OTZ.ValueRendererRegistry.register('SelectRenderer', SelectRenderer);
		}
	});

})(jQuery);
