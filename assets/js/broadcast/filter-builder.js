/**
 * OrderChatz 篩選建構器
 *
 * 主要篩選條件管理器，整合所有群組與條件
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function($) {
	'use strict';

	/**
	 * 篩選建構器管理器
	 */
	const FilterBuilder = {

		/**
		 * 條件配置
		 */
		conditions: [],

		/**
		 * 群組陣列
		 */
		groups: [],

		/**
		 * 群組 ID 計數器
		 */
		groupIdCounter: 0,

		/**
		 * Registry 實例
		 */
		registry: null,

		/**
		 * 容器元素
		 */
		$container: null,

		/**
		 * 初始資料
		 */
		initialData: null,

		/**
		 * 初始化
		 */
		init: function(initialData) {
			this.initialData = initialData || null;
			this.conditions = window.otzFilterConfig.conditions || [];
			this.registry = window.OTZ.ValueRendererRegistry;
			this.$container = $('#filter-groups-container');

			if (!this.$container.length) {
				console.error('Filter groups container not found');
				return;
			}

			// 清空預設內容.
			this.$container.empty();

			// 等待所有 Renderer 註冊完成.
			this.waitForRenderers().then(() => {
				this.bindEvents();
				this.loadInitialData();
			});
		},

		/**
		 * 等待 Renderer 註冊完成.
		 *
		 * @return {Promise} Promise 物件.
		 */
		waitForRenderers: function() {
			return new Promise((resolve) => {
				// 給 Renderer 100ms 註冊時間.
				setTimeout(() => {
					$(document).trigger('otz:builder:ready');
					resolve();
				}, 100);
			});
		},

		/**
		 * 綁定事件
		 */
		bindEvents: function() {
			const self = this;

			// 新增群組按鈕.
			$('#add-group').on('click', function() {
				self.addGroup();
			});

			// 預覽受眾按鈕.
			$('#preview-audience').on('click', function() {
				self.previewAudience();
			});

			// 監聽群組移除事件.
			$(document).on('otz:group:removed', function(e, groupId) {
				self.removeGroupById(groupId);
			});

			// 監聽條件值變更事件（商品、標籤選擇）.
			$(document).on('otz:condition:value-changed', function() {
				self.updateAudienceCount();
			});

			// 表單提交時序列化條件資料.
			$('#broadcast-campaign-form').on('submit', function() {
				self.serializeConditions();
			});
		},

		/**
		 * 載入初始資料
		 */
		loadInitialData: function() {
			if (this.initialData && this.initialData.conditions) {
				// 有初始條件，載入群組與條件.
				Object.keys(this.initialData.conditions).forEach(groupId => {
					const conditions = this.initialData.conditions[groupId];
					if (Array.isArray(conditions) && conditions.length > 0) {
						this.addGroup(conditions);
					}
				});
			} else {
				// 沒有初始條件，新增一個空群組.
				this.addGroup();
			}

			// 初始計算受眾人數.
			this.updateAudienceCount();

			// 載入推播額度.
			this.loadBroadcastQuota();
		},

		/**
		 * 新增群組.
		 *
		 * @param {Array} conditions - 初始條件陣列.
		 * @return {Object} 群組實例.
		 */
		addGroup: function(conditions) {
			const groupId = this.groupIdCounter++;
			const group = new window.OTZ.FilterGroup(groupId, conditions);

			group.render(this.$container);
			this.groups.push(group);

			// 更新群組編號顯示.
			this.updateGroupNumbers();

			// 觸發事件.
			$(document).trigger('otz:group:added', [groupId]);

			return group;
		},

		/**
		 * 根據 ID 移除群組.
		 *
		 * @param {number} groupId - 群組 ID.
		 */
		removeGroupById: function(groupId) {
			this.groups = this.groups.filter(g => g.groupId !== groupId);

			// 如果沒有群組了，自動新增一個空群組.
			if (this.groups.length === 0) {
				this.addGroup();
			}

			// 更新群組編號顯示.
			this.updateGroupNumbers();

			// 更新受眾計數.
			this.updateAudienceCount();
		},

		/**
		 * 更新群組編號顯示
		 */
		updateGroupNumbers: function() {
			this.groups.forEach((group, index) => {
				group.updateGroupNumber(index + 1);
			});
		},

		/**
		 * 取得所有條件資料.
		 *
		 * @return {Object} 條件資料物件 {conditions: {group_0: [...], group_1: [...]}}.
		 */
		getData: function() {
			const data = {
				conditions: {}
			};

			this.groups.forEach(group => {
				const groupData = group.getData();
				if (groupData.length > 0) {
					data.conditions['group_' + group.groupId] = groupData;
				}
			});

			return data;
		},

		/**
		 * 序列化條件資料到隱藏欄位
		 */
		serializeConditions: function() {
			const data = this.getData();

			// 移除舊的隱藏欄位.
			$('input[name="filter_conditions"]').remove();

			// 建立新的隱藏欄位.
			$('#broadcast-campaign-form').append(
				$('<input>')
					.attr('type', 'hidden')
					.attr('name', 'filter_conditions')
					.val(JSON.stringify(data))
			);
		},

		/**
		 * 更新受眾計數
		 */
		updateAudienceCount: function() {
			const data = this.getData();

			// 如果沒有條件，顯示 0.
			if (Object.keys(data.conditions).length === 0) {
				$('#audience-count-number').text('0');
				return;
			}

			// AJAX 請求受眾計數.
			$.ajax({
				url: window.otzFilterConfig.ajaxUrl,
				type: 'POST',
				data: {
					action: 'otz_get_audience_count',
					nonce: window.otzFilterConfig.nonce,
					filter_mode: 'conditions',
					dynamic_conditions: data
				},
				success: function(response) {
					if (response.success && response.data.count !== undefined) {
						$('#audience-count-number').text(response.data.count);
					} else {
						$('#audience-count-number').text('--');
					}
				},
				error: function() {
					$('#audience-count-number').text('--');
				}
			});
		},

		/**
		 * 預覽受眾名單
		 */
		previewAudience: function() {
			const data = this.getData();

			// 驗證是否有條件.
			if (Object.keys(data.conditions).length === 0) {
				alert('請先設定篩選條件');
				return;
			}

			// 建立燈箱.
			this.createPreviewModal(data);
		},

		/**
		 * 建立預覽燈箱
		 *
		 * @param {Object} filterData - 篩選條件資料.
		 */
		createPreviewModal: function(filterData) {
			const self = this;

			// 移除舊燈箱（如果存在）.
			$('.audience-preview-modal').remove();

			// 建立燈箱 HTML.
			const modalHtml = `
				<div class="audience-preview-modal">
					<div class="modal-overlay"></div>
					<div class="modal-content">
						<div class="modal-header">
							<h2 class="modal-title">預覽受眾名單</h2>
							<button class="modal-close" aria-label="關閉">&times;</button>
						</div>
						<div class="modal-body">
							<ul class="audience-list"></ul>
							<div class="loading-indicator" style="display: none;">
								<span class="dashicons dashicons-update"></span>
								<p>載入中...</p>
							</div>
							<div class="no-more-data" style="display: none;">已載入全部受眾</div>
						</div>
						<div class="modal-footer">
							<div class="audience-stats">
								已載入 <strong class="loaded-count">0</strong> / 總計 <strong class="total-count">--</strong> 人
							</div>
						</div>
					</div>
				</div>
			`;

			$('body').append(modalHtml);

			const $modal = $('.audience-preview-modal');
			const $list = $modal.find('.audience-list');
			const $loadingIndicator = $modal.find('.loading-indicator');
			const $noMoreData = $modal.find('.no-more-data');
			const $loadedCount = $modal.find('.loaded-count');
			const $totalCount = $modal.find('.total-count');
			const $modalBody = $modal.find('.modal-body');

			let currentPage = 0;
			let totalPages = 0;
			let isLoading = false;
			let loadedCount = 0;
			let totalFriends = 0;

			// 載入下一頁.
			function loadNextPage() {
				if (isLoading || (totalPages > 0 && currentPage >= totalPages)) {
					return;
				}

				isLoading = true;
				currentPage++;
				$loadingIndicator.show();
				$noMoreData.hide();

				$.ajax({
					url: window.otzFilterConfig.ajaxUrl,
					type: 'POST',
					data: {
						action: 'otz_preview_audience',
						nonce: window.otzFilterConfig.nonce,
						filter_mode: 'conditions',
						dynamic_conditions: filterData,
						page: currentPage,
						per_page: 20
					},
					success: function(response) {
						if (response.success && response.data.friends) {
							totalFriends = response.data.total;
							totalPages = response.data.total_pages;
							$totalCount.text(totalFriends);

							// 渲染好友列表.
							response.data.friends.forEach(function(friend) {
								const avatarUrl = friend.picture_url || '';
								const name = friend.name || friend.line_user_id || '未知用戶';
								const userId = friend.line_user_id || '';

								const itemHtml = `
									<li class="audience-item">
										<img src="${avatarUrl}" alt="${name}" class="audience-avatar" onerror="this.style.display='none'">
										<div class="audience-info">
											<p class="audience-name">${name}</p>
											<p class="audience-id">${userId}</p>
										</div>
									</li>
								`;
								$list.append(itemHtml);
								loadedCount++;
							});

							$loadedCount.text(loadedCount);

							// 檢查是否還有更多資料.
							if (!response.data.has_more) {
								$noMoreData.show();
							}
						} else {
							alert('無法取得受眾名單');
						}
					},
					error: function() {
						alert('載入失敗，請稍後再試');
					},
					complete: function() {
						isLoading = false;
						$loadingIndicator.hide();
					}
				});
			}

			// 監聽捲動事件.
			$modalBody.on('scroll', function() {
				const scrollTop = $(this).scrollTop();
				const scrollHeight = $(this)[0].scrollHeight;
				const clientHeight = $(this).height();

				// 距離底部小於 100px 時載入下一頁.
				if (scrollHeight - scrollTop - clientHeight < 100) {
					loadNextPage();
				}
			});

			// 關閉燈箱.
			function closeModal() {
				$modal.removeClass('active');
				$('body').css('overflow', '');
				setTimeout(function() {
					$modal.remove();
				}, 300);
			}

			$modal.find('.modal-close, .modal-overlay').on('click', closeModal);

			// ESC 鍵關閉.
			$(document).on('keydown.audienceModal', function(e) {
				if (e.key === 'Escape') {
					closeModal();
					$(document).off('keydown.audienceModal');
				}
			});

			// 防止背景捲動.
			$('body').css('overflow', 'hidden');

			// 顯示燈箱.
			$modal.addClass('active');

			// 載入第一頁.
			loadNextPage();
		},

		/**
		 * 驗證所有條件
		 *
		 * @return {boolean} 是否所有條件都有效.
		 */
		validate: function() {
			if (this.groups.length === 0) {
				return false;
			}

			// 至少要有一個有效群組.
			return this.groups.some(group => group.validate());
		},

		/**
		 * 載入推播額度資訊
		 */
		loadBroadcastQuota: function() {
			const self = this;

			// 顯示載入中.
			$('#quota-remaining-broadcast').text('...');

			// 使用與 statistics.js 相同的 AJAX 端點.
			const quotaPromise = $.ajax({
				url: window.otzFilterConfig.ajaxUrl,
				type: 'POST',
				data: {
					action: 'otz_get_message_quota',
					nonce: window.otzFilterConfig.nonce
				}
			});

			const consumptionPromise = $.ajax({
				url: window.otzFilterConfig.ajaxUrl,
				type: 'POST',
				data: {
					action: 'otz_get_message_consumption',
					nonce: window.otzFilterConfig.nonce
				}
			});

			Promise.allSettled([quotaPromise, consumptionPromise])
				.then(function(results) {
					const [quotaResult, consumptionResult] = results;

					let total = 0;
					let used = 0;

					if (quotaResult.status === 'fulfilled' && quotaResult.value.success) {
						total = quotaResult.value.data.quota.value || 0;
					}

					if (consumptionResult.status === 'fulfilled' && consumptionResult.value.success) {
						used = consumptionResult.value.data.consumption.totalUsage || 0;
					}

					const remaining = Math.max(0, total - used);
					self.updateQuotaDisplay(remaining);
				})
				.catch(function() {
					$('#quota-remaining-broadcast').text('--');
				});
		},

		/**
		 * 更新額度顯示
		 *
		 * @param {number} remaining - 剩餘額度.
		 */
		updateQuotaDisplay: function(remaining) {
			const formattedNumber = remaining.toLocaleString();
			$('#quota-remaining-broadcast').text(formattedNumber);

			// 根據剩餘量設定顏色警示.
			const $quotaNumber = $('#quota-remaining-broadcast');
			$quotaNumber.removeClass('quota-low quota-critical');

			if (remaining < 100) {
				$quotaNumber.addClass('quota-critical');
			} else if (remaining < 500) {
				$quotaNumber.addClass('quota-low');
			}
		}
	};

	// 暴露到全域.
	window.OTZ = window.OTZ || {};
	window.OTZ.FilterBuilder = FilterBuilder;

})(jQuery);
