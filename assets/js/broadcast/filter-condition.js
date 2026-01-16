/**
 * OrderChatz 篩選條件類別
 *
 * 管理單一條件的渲染、資料收集與驗證
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function($) {
	'use strict';

	/**
	 * 篩選條件類別
	 *
	 * @param {number} groupId - 所屬群組 ID.
	 * @param {number} conditionId - 條件 ID.
	 * @param {Object} data - 條件初始資料 {field, operator, value}.
	 */
	window.OTZ = window.OTZ || {};
	window.OTZ.FilterCondition = function(groupId, conditionId, data) {
		this.groupId = groupId;
		this.conditionId = conditionId;
		this.data = data || {};
		this.$element = null;
		this.renderer = null;
		this.config = null;
	};

	window.OTZ.FilterCondition.prototype = {

		/**
		 * 渲染條件 HTML.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @return {jQuery} 條件元素.
		 */
		render: function($container) {
			const conditionHtml = this.createConditionHTML();
			$container.append(conditionHtml);
			this.$element = $container.find(`.condition-row[data-condition-id="${this.conditionId}"]`);

			// 立即填充條件類型選項.
			const $typeSelect = this.$element.find('.condition-type-select');
			this.populateConditionTypes($typeSelect);

			this.bindEvents();

			// 如果有初始資料，設定值.
			if (this.data.field) {
				this.setConditionType(this.data.field);
				if (this.data.operator) {
					this.setOperator(this.data.operator);
				}
				if (this.data.value) {
					this.setValue(this.data.value);
				}
			}

			return this.$element;
		},

		/**
		 * 建立條件 HTML 結構.
		 *
		 * @return {string} HTML 字串.
		 */
		createConditionHTML: function() {
			return `
				<div class="condition-row" data-condition-id="${this.conditionId}">
					<div class="condition-type">
						<select class="condition-type-select">
							<option value="">-- 選擇條件類型 --</option>
						</select>
					</div>
					<div class="condition-operator" style="pointer-events: none;">
						<select class="condition-operator-select">
							<option value="">-- 選擇篩選邏輯 --</option>
						</select>
					</div>
					<div class="condition-value-wrapper" style="display:none;">
						<!-- Value Renderer 會在此渲染 -->
					</div>
					<div class="condition-actions">
						<button type="button" class="button delete-condition" title="刪除條件">×</button>
					</div>
				</div>
			`;
		},

		/**
		 * 綁定事件.
		 */
		bindEvents: function() {
			const self = this;

			// 條件類型變更.
			this.$element.find('.condition-type-select').on('change', function() {
				const conditionType = $(this).val();
				if (conditionType) {
					self.setConditionType(conditionType);
				}
			});

			// 操作符變更.
			this.$element.find('.condition-operator-select').on('change', function() {
				const operator = $(this).val();
				self.setOperator(operator);
			});

			// 刪除條件.
			this.$element.find('.delete-condition').on('click', function() {
				self.remove();
			});
		},

		/**
		 * 設定條件類型.
		 *
		 * @param {string} conditionType - 條件類型.
		 */
		setConditionType: function(conditionType) {
			if (!window.otzFilterConfig || !window.otzFilterConfig.conditions) {
				console.error('Filter config not loaded');
				return;
			}

			// 找到對應的配置.
			const newConfig = window.otzFilterConfig.conditions.find(c => c.type === conditionType);
			if (!newConfig) {
				console.error(`Config not found for type: ${conditionType}`);
				return;
			}

			// 檢查條件類型是否真的改變了.
			const typeChanged = !this.config || this.config.type !== newConfig.type;

			// 如果條件類型改變，清除舊的 renderer 和 value input.
			if (typeChanged && this.config !== null) {
				// 銷毀舊的 renderer（如果有）.
				if (this.renderer && typeof this.renderer.destroy === 'function') {
					const $valueWrapper = this.$element.find('.condition-value-wrapper');
					this.renderer.destroy($valueWrapper);
				}

				// 清空 value wrapper.
				this.$element.find('.condition-value-wrapper').empty().hide();

				// 重置操作符選擇器.
				this.$element.find('.condition-operator-select').val('');

				// 重置 renderer.
				this.renderer = null;

				// 重置資料.
				this.data.value = null;
			}

			// 更新配置.
			this.config = newConfig;

			// 設定條件類型選擇器的值.
			const $typeSelect = this.$element.find('.condition-type-select');
			$typeSelect.val(conditionType);

			// 填充操作符選項.
			this.populateOperators();

			// 顯示操作符選擇器.
			this.$element.find('.condition-operator').css('pointer-events', 'auto');
		},

		/**
		 * 填充條件類型選項.
		 *
		 * @param {jQuery} $select - 選擇元素.
		 */
		populateConditionTypes: function($select) {
			if (!window.otzFilterConfig || !window.otzFilterConfig.conditions) {
				return;
			}

			// 按群組分類條件.
			const groups = {
				order: [],
				user: []
			};

			window.otzFilterConfig.conditions.forEach(config => {
				const group = config.group || 'user';
				if (groups[group]) {
					groups[group].push(config);
				}
			});

			// 渲染訂單條件群組.
			if (groups.order.length > 0) {
				const $orderGroup = $('<optgroup label="訂單條件"></optgroup>');
				groups.order.forEach(config => {
					$orderGroup.append(`<option value="${config.type}">${config.label}</option>`);
				});
				$select.append($orderGroup);
			}

			// 渲染使用者條件群組.
			if (groups.user.length > 0) {
				const $userGroup = $('<optgroup label="使用者條件"></optgroup>');
				groups.user.forEach(config => {
					$userGroup.append(`<option value="${config.type}">${config.label}</option>`);
				});
				$select.append($userGroup);
			}
		},

		/**
		 * 填充操作符選項.
		 */
		populateOperators: function() {
			if (!this.config || !this.config.operators) {
				return;
			}

			const $operatorSelect = this.$element.find('.condition-operator-select');
			$operatorSelect.empty().append('<option value="">-- 選擇篩選邏輯 --</option>');

			Object.keys(this.config.operators).forEach(operatorKey => {
				const operator = this.config.operators[operatorKey];
				$operatorSelect.append(`<option value="${operatorKey}">${operator.label}</option>`);
			});
		},

		/**
		 * 設定操作符.
		 *
		 * @param {string} operator - 操作符.
		 */
		setOperator: function(operator) {
			if (!operator) {
				return;
			}

			this.$element.find('.condition-operator-select').val(operator);

			// 渲染值輸入區.
			this.renderValueInput();

			// 顯示值輸入區.
			this.$element.find('.condition-value-wrapper').show();
		},

		/**
		 * 渲染值輸入區.
		 */
		renderValueInput: function() {
			if (!this.config || !this.config.value_component) {
				return;
			}

			const $valueWrapper = this.$element.find('.condition-value-wrapper');
			$valueWrapper.empty();

			// 從 Registry 取得對應的 Renderer.
			const registry = window.OTZ.ValueRendererRegistry;
			const rendererName = this.config.value_component;
			this.renderer = registry.get(rendererName);

			if (!this.renderer) {
				console.warn(`Renderer not found: ${rendererName}`);
				this.renderDefaultInput($valueWrapper);
				return;
			}

			// 呼叫 Renderer 渲染.
			try {
				this.renderer.render($valueWrapper, this.config.value_config, this.data.value || null);
			} catch (error) {
				console.error(`Error rendering ${rendererName}:`, error);
				this.renderDefaultInput($valueWrapper);
			}
		},

		/**
		 * Fallback: 渲染預設輸入框.
		 *
		 * @param {jQuery} $container - 容器元素.
		 */
		renderDefaultInput: function($container) {
			const placeholder = this.config.value_config?.placeholder || '輸入值...';
			$container.html(`
				<input type="text"
				       class="condition-value"
				       value="${this.data.value || ''}"
				       placeholder="${placeholder}">
			`);
		},

		/**
		 * 設定值.
		 *
		 * @param {*} value - 值.
		 */
		setValue: function(value) {
			// 值會在 renderValueInput() 時傳遞給 Renderer.
			this.data.value = value;
		},

		/**
		 * 取得條件資料.
		 *
		 * @return {Object|null} 條件資料 {field, operator, value}.
		 */
		getData: function() {
			const field = this.$element.find('.condition-type-select').val();
			const operator = this.$element.find('.condition-operator-select').val();

			if (!field || !operator) {
				return null;
			}

			let value = null;

			// 使用 Renderer 的 getValue 方法取得值.
			if (this.renderer && typeof this.renderer.getValue === 'function') {
				const $valueWrapper = this.$element.find('.condition-value-wrapper');
				value = this.renderer.getValue($valueWrapper);
			} else {
				// Fallback: 從預設輸入框取得值.
				value = this.$element.find('.condition-value').val();
			}

			return {
				field: field,
				operator: operator,
				value: value
			};
		},

		/**
		 * 驗證條件.
		 *
		 * @return {boolean} 是否有效.
		 */
		validate: function() {
			const data = this.getData();
			if (!data) {
				return false;
			}

			// 使用 Renderer 的驗證方法.
			if (this.renderer && typeof this.renderer.validate === 'function') {
				return this.renderer.validate(data.value);
			}

			// Fallback: 簡單檢查值是否存在.
			return data.value !== null && data.value !== '' && data.value !== undefined;
		},

		/**
		 * 移除條件.
		 */
		remove: function() {
			// 銷毀 Renderer（如果有）.
			if (this.renderer && typeof this.renderer.destroy === 'function') {
				const $valueWrapper = this.$element.find('.condition-value-wrapper');
				this.renderer.destroy($valueWrapper);
			}

			// 移除 DOM.
			this.$element.remove();

			// 觸發事件.
			$(document).trigger('otz:condition:removed', [this.groupId, this.conditionId]);
		}
	};

})(jQuery);
