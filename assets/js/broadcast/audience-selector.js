/**
 * OrderChatz 推播受眾選擇器
 *
 * 處理受眾類型切換與相關互動
 *
 * @package OrderChatz
 * @since 1.1.3
 */

(function ($) {
	'use strict';

	/**
	 * 受眾選擇器管理器
	 */
	const AudienceSelectorManager = {

		/**
		 * FilterBuilder 實例
		 */
		filterBuilder: null,

		/**
		 * 是否已初始化 FilterBuilder
		 */
		filterBuilderInitialized: false,

		/**
		 * 初始化
		 */
		init: function () {
			this.initAudienceTypeSelector();
			this.checkInitialState();
		},

		/**
		 * 檢查初始狀態
		 */
		checkInitialState: function () {
			const $checkedInput = $('input[name="audience_type"]:checked');
			if ($checkedInput.val() === 'filtered') {
				this.initializeFilterBuilder();
			}
		},

		/**
		 * 初始化受眾類型選擇器
		 */
		initAudienceTypeSelector: function () {
			const self = this;

			$('input[name="audience_type"]').on('change', function () {
				// 更新視覺狀態.
				$('.audience-type-option').removeClass('active');
				$(this).closest('.audience-type-option').addClass('active');

				// 切換動態篩選區顯示.
				if ($(this).val() === 'filtered') {
					$('.dynamic-filter-section').addClass('active');
					self.initializeFilterBuilder();
				} else {
					$('.dynamic-filter-section').removeClass('active');
				}
			});
		},

		/**
		 * 初始化 FilterBuilder
		 */
		initializeFilterBuilder: function () {
			// 避免重複初始化.
			if (this.filterBuilderInitialized) {
				return;
			}

			// 等待 FilterBuilder 準備好.
			if (typeof window.OTZ === 'undefined' || typeof window.OTZ.FilterBuilder === 'undefined') {
				console.warn('FilterBuilder not loaded yet, retrying...');
				setTimeout(() => this.initializeFilterBuilder(), 100);
				return;
			}

			// 取得初始資料（如果有）.
			let initialData = null;
			const $filterDataInput = $('input[name="filter_conditions_data"]');
			if ($filterDataInput.length && $filterDataInput.val()) {
				try {
					initialData = JSON.parse($filterDataInput.val());
				} catch (e) {
					console.error('Failed to parse filter conditions:', e);
				}
			}

			// 初始化 FilterBuilder.
			this.filterBuilder = window.OTZ.FilterBuilder;
			this.filterBuilder.init(initialData);
			this.filterBuilderInitialized = true;
		}
	};

	// 文件準備就緒時初始化.
	$(document).ready(function () {
		AudienceSelectorManager.init();
	});

})(jQuery);
