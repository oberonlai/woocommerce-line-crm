/**
 * OrderChatz 篩選群組類別
 *
 * 管理群組內的多個條件（AND 邏輯）
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function($) {
	'use strict';

	/**
	 * 篩選群組類別
	 *
	 * @param {number} groupId - 群組 ID.
	 * @param {Array} conditions - 初始條件陣列.
	 */
	window.OTZ = window.OTZ || {};
	window.OTZ.FilterGroup = function(groupId, conditions) {
		this.groupId = groupId;
		this.conditions = [];
		this.conditionIdCounter = 0;
		this.$element = null;
		this.initialConditions = conditions || [];
	};

	window.OTZ.FilterGroup.prototype = {

		/**
		 * 渲染群組 HTML.
		 *
		 * @param {jQuery} $container - 容器元素.
		 * @return {jQuery} 群組元素.
		 */
		render: function($container) {
			const groupHtml = this.createGroupHTML();
			$container.append(groupHtml);
			this.$element = $container.find(`.filter-group[data-group-id="${this.groupId}"]`);

			this.bindEvents();

			// 渲染初始條件或新增第一個空條件.
			if (this.initialConditions.length > 0) {
				this.initialConditions.forEach(conditionData => {
					this.addCondition(conditionData);
				});
			} else {
				this.addCondition();
			}

			return this.$element;
		},

		/**
		 * 建立群組 HTML 結構.
		 *
		 * @return {string} HTML 字串.
		 */
		createGroupHTML: function() {
			return `
				<div class="filter-group" data-group-id="${this.groupId}">
					<div class="group-header">
						<span class="group-label">群組 <span class="group-number">${this.groupId + 1}</span></span>
						<button type="button" class="delete-group" title="刪除群組">×</button>
					</div>
					<div class="filter-conditions">
						<!-- 條件會在此渲染 -->
					</div>
					<div class="group-actions">
						<button type="button" class="button button-small add-condition">
							新增規則
						</button>
					</div>
				</div>
			`;
		},

		/**
		 * 綁定事件.
		 */
		bindEvents: function() {
			const self = this;

			// 新增條件.
			this.$element.find('.add-condition').on('click', function() {
				self.addCondition();
			});

			// 刪除群組.
			this.$element.find('.delete-group').on('click', function() {
				self.remove();
			});

			// 監聽條件移除事件.
			$(document).on('otz:condition:removed', function(e, groupId, conditionId) {
				if (groupId === self.groupId) {
					self.removeConditionById(conditionId);
				}
			});
		},

		/**
		 * 新增條件.
		 *
		 * @param {Object} data - 條件初始資料.
		 * @return {Object} 條件實例.
		 */
		addCondition: function(data) {
			const conditionId = this.conditionIdCounter++;
			const condition = new window.OTZ.FilterCondition(this.groupId, conditionId, data);

			const $conditionsContainer = this.$element.find('.filter-conditions');
			condition.render($conditionsContainer);

			this.conditions.push(condition);

			// 觸發事件.
			$(document).trigger('otz:condition:added', [this.groupId, conditionId]);

			return condition;
		},

		/**
		 * 根據 ID 移除條件.
		 *
		 * @param {number} conditionId - 條件 ID.
		 */
		removeConditionById: function(conditionId) {
			this.conditions = this.conditions.filter(c => c.conditionId !== conditionId);

			// 如果群組內沒有條件了，移除整個群組.
			if (this.conditions.length === 0) {
				this.remove();
			}
		},

		/**
		 * 更新群組編號顯示.
		 *
		 * @param {number} newNumber - 新編號.
		 */
		updateGroupNumber: function(newNumber) {
			this.$element.find('.group-number').text(newNumber);
		},

		/**
		 * 取得群組內所有條件的資料.
		 *
		 * @return {Array} 條件資料陣列.
		 */
		getData: function() {
			const data = [];

			this.conditions.forEach(condition => {
				const conditionData = condition.getData();
				if (conditionData) {
					data.push(conditionData);
				}
			});

			return data;
		},

		/**
		 * 驗證群組.
		 *
		 * @return {boolean} 是否至少有一個有效條件.
		 */
		validate: function() {
			if (this.conditions.length === 0) {
				return false;
			}

			// 至少要有一個有效條件.
			return this.conditions.some(condition => condition.validate());
		},

		/**
		 * 移除群組.
		 */
		remove: function() {
			// 銷毀所有條件.
			this.conditions.forEach(condition => {
				if (typeof condition.remove === 'function') {
					condition.remove();
				}
			});

			// 移除 DOM.
			this.$element.remove();

			// 觸發事件.
			$(document).trigger('otz:group:removed', [this.groupId]);
		}
	};

})(jQuery);
