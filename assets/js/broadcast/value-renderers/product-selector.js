/**
 * OrderChatz 商品選擇器 Value Renderer
 *
 * 使用 Select2 + AJAX 實現商品搜尋與選擇
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function($) {
	'use strict';

	const ProductSelectorRenderer = {

		/**
		 * 渲染商品選擇器.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @param {Object} config - 配置參數.
		 * @param {*} currentValue - 當前值.
		 * @return {jQuery} Select 元素.
		 */
		render: function($container, config, currentValue) {
			const selectId = 'product-select-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
			let isComposing = false;

			$container.html(`
				<select id="${selectId}"
				        class="condition-value product-selector"
				        name="condition_value"
				        ${config.multiple ? 'multiple' : ''}
				        style="width: 100%;">
				</select>
			`);

			const $select = $container.find('select');

			// 初始化 Select2.
			$select.select2({
				ajax: {
					url: window.otzFilterConfig.ajaxUrl,
					dataType: 'json',
					delay: 250,
                    type: 'POST',
					data: function(params) {
						const query = params.term || '';

						// 偵測庫存條件運算式（支援 <=, >=, <, >, =）.
						const stockPattern = /^(<=|>=|<|>|=)\s*(\d+)$/;
						const match = query.match(stockPattern);

                        if (match) {
							// 是庫存條件運算式.
							return {
								action: config.ajax_action,
								query: '',
								stock_operator: match[1],
								stock_value: match[2],
								nonce: window.otzFilterConfig.nonce
							};
						}

						// 一般關鍵字搜尋.
						return {
							action: config.ajax_action,
							query: query,
							nonce: window.otzFilterConfig.nonce
						};
					},
					processResults: function(response) {
						// 如果正在使用注音輸入法組字，不處理結果.
						if (isComposing) {
							return {results: []};
						}

						if (!response.success || !response.data || !response.data.products) {
							return {results: []};
						}

						// 取得當前已選中的值.
						const selectedValues = $select.val() || [];
						const selectedIds = Array.isArray(selectedValues) ? selectedValues : [selectedValues];

						return {
							results: response.data.products
								.filter(function(product) {
									// 過濾掉已選中的商品.
									return !selectedIds.includes(String(product.id));
								})
								.map(function(product) {
									return {
										id: product.id,
										text: product.name
									};
								})
						};
					},
					cache: true
				},
				placeholder: config.placeholder || '搜尋商品...',
				minimumInputLength: config.min_input || 0,
				allowClear: true,
				width: '100%',
			});

			// 監聽 Select2 開啟事件，綁定注音輸入法處理.
			$select.on('select2:open', function() {
				const $search = $('.select2-search__field');
				$search.on('compositionstart', () => isComposing = true);
				$search.on('compositionend', () => isComposing = false);
			});

			// 監聽選擇事件，觸發受眾計數更新.
			$select.on('select2:select select2:unselect select2:clear', function() {
				$(document).trigger('otz:condition:value-changed');
			});

			// 設定當前值（如果有）.
			if (currentValue) {
				this.loadCurrentValue($select, currentValue);
			}

			return $select;
		},

		/**
		 * 載入當前選中的商品.
		 *
		 * @param {jQuery} $select - Select 元素.
		 * @param {Array|string} value - 商品 ID 或 ID 陣列.
		 */
		loadCurrentValue: function($select, value) {
			// 確保 value 是陣列.
			const ids = Array.isArray(value) ? value : [value];

			if (ids.length === 0) {
				return;
			}

			// 如果有預載資料，使用預載資料.
			if (window.otzFilterPreloadData && window.otzFilterPreloadData.products) {
				const preloadedProducts = window.otzFilterPreloadData.products;
				ids.forEach(id => {
					const product = preloadedProducts.find(p => p.id == id);
					if (product) {
						const option = new Option(product.text, product.id, true, true);
						$select.append(option);
					}
				});
				$select.trigger('change');
			} else {
				// 使用 AJAX 載入（fallback）.
				// 注意：需要後端支援 get_products_by_ids 端點.
				// 暫時先直接設定 ID，不顯示名稱.
				ids.forEach(id => {
					const option = new Option('商品 #' + id, id, true, true);
					$select.append(option);
				});
				$select.trigger('change');
			}
		},

		/**
		 * 取得當前值.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @return {Array|string|null} 商品 ID 陣列或單一 ID.
		 */
		getValue: function($container) {
			const $select = $container.find('select');
			const value = $select.val();

			// 如果是多選，返回陣列；單選返回字串.
			if (Array.isArray(value)) {
				return value.length > 0 ? value : null;
			}

			return value || null;
		},

		/**
		 * 驗證值.
		 *
		 * @param {*} value - 要驗證的值.
		 * @return {boolean} 是否有效.
		 */
		validate: function(value) {
			if (Array.isArray(value)) {
				return value.length > 0;
			}
			return value !== null && value !== '' && value !== undefined;
		},

		/**
		 * 銷毀選擇器.
		 *
		 * @param {jQuery} $container - 容器元素.
		 */
		destroy: function($container) {
			const $select = $container.find('select');
			if ($select.data('select2')) {
				$select.select2('destroy');
			}
		}
	};

	// 等待 Registry 準備好後註冊.
	$(document).on('otz:registry:ready', function() {
		if (window.OTZ && window.OTZ.ValueRendererRegistry) {
			window.OTZ.ValueRendererRegistry.register('ProductSelector', ProductSelectorRenderer);
		}
	});

})(jQuery);
