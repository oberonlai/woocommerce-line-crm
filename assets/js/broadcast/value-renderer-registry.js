/**
 * OrderChatz Value Renderer 註冊器
 *
 * 管理所有 Value Renderer（值選擇器）的註冊與查找
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function(window, $) {
	'use strict';

	/**
	 * Value Renderer 註冊器
	 */
	const ValueRendererRegistry = {

		/**
		 * 已註冊的 Renderer 儲存.
		 */
		renderers: {},

		/**
		 * 註冊 Value Renderer.
		 *
		 * @param {string} name - Renderer 名稱 (對應 value_component).
		 * @param {Object} renderer - Renderer 物件.
		 * @return {boolean} 是否註冊成功.
		 */
		register: function(name, renderer) {
			// 驗證必要方法.
			if (typeof renderer.render !== 'function') {
				console.error(`Renderer ${name} must have a render() method`);
				return false;
			}

			if (typeof renderer.getValue !== 'function') {
				console.error(`Renderer ${name} must have a getValue() method`);
				return false;
			}

			this.renderers[name] = renderer;

			// 觸發註冊完成事件.
			$(document).trigger('otz:renderer:registered', [name, renderer]);

			return true;
		},

		/**
		 * 取得 Renderer.
		 *
		 * @param {string} name - Renderer 名稱.
		 * @return {Object|null} Renderer 物件或 null.
		 */
		get: function(name) {
			return this.renderers[name] || null;
		},

		/**
		 * 檢查是否已註冊.
		 *
		 * @param {string} name - Renderer 名稱.
		 * @return {boolean} 是否已註冊.
		 */
		has: function(name) {
			return !!this.renderers[name];
		},

		/**
		 * 取得所有已註冊的 Renderer.
		 *
		 * @return {Object} 所有 Renderer.
		 */
		getAll: function() {
			return {...this.renderers};
		}
	};

	// 暴露到全域（供內建和第三方使用）.
	window.OTZ = window.OTZ || {};
	window.OTZ.ValueRendererRegistry = ValueRendererRegistry;

	// 觸發 Registry 準備完成事件.
	$(document).ready(function() {
		$(document).trigger('otz:registry:ready');
	});

})(window, jQuery);
