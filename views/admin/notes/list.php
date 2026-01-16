<?php
/**
 * 備註列表頁面
 *
 * @package OrderChatz\Views\Admin\Notes
 */

// 防止直接訪問
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php
// 引入 Select2 資源
wp_enqueue_script( 'select2' );
wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
?>

<div class="wrap">
	<?php $this->notes_table->prepare_items(); ?>
	
	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">
		
		<!-- 分類篩選 -->
		<div class="tablenav top" style="margin-bottom: 10px;">
			<div class="alignleft actions">
				<label for="filter-category" class="screen-reader-text"><?php _e( '依分類篩選', 'otz' ); ?></label>
				<select id="filter-category" name="category_filter" style="width: 200px;">
					<option value=""><?php _e( '所有分類', 'otz' ); ?></option>
					<option value="_no_category" <?php selected( isset( $_GET['category_filter'] ) ? $_GET['category_filter'] : '', '_no_category' ); ?>><?php _e( '無分類', 'otz' ); ?></option>
					<?php
					$all_categories = $this->get_all_categories();
					$current_filter = isset( $_GET['category_filter'] ) ? $_GET['category_filter'] : '';

					// 確保 debug 分類始終存在
					if ( ! in_array( 'debug', $all_categories ) ) {
						array_unshift( $all_categories, 'debug' );
					}

					foreach ( $all_categories as $category ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $category ),
							selected( $current_filter, $category, false ),
							esc_html( $category )
						);
					}
					?>
				</select>

				<label for="filter-product" class="screen-reader-text"><?php _e( '依商品篩選', 'otz' ); ?></label>
				<select id="filter-product" name="product_filter" style="width: 200px; margin-left: 5px;">
				</select>

				<input type="submit" id="category-filter-submit" class="button" value="<?php _e( '篩選', 'otz' ); ?>">
				<button type="button" id="category-manager-btn" class="button" style="margin-left: 10px;"><?php _e( '分類管理', 'otz' ); ?></button>
			</div>
			<?php
			$this->notes_table->search_box( __( '搜尋', 'otz' ), 'notes' );
			$this->notes_table->display();
			?>
		</div>
	</form>
</div>

<!-- 分類管理燈箱 -->
<div id="category-manager-modal" style="display: none;">
	<div class="modal-overlay">
		<div class="modal-content">
			<div class="modal-header">
				<h2><?php _e( '分類管理', 'otz' ); ?></h2>
				<button type="button" class="modal-close">&times;</button>
			</div>
			<div class="modal-body">
				
				<!-- 新增分類 -->
				<div class="category-add-section">
					<h3>新增分類</h3>
					<div class="category-add-form">
						<input type="text" id="new-category-name" placeholder="輸入分類名稱" maxlength="50">
						<input type="color" id="new-category-color" value="#3498db" title="選擇顏色">
						<button type="button" id="add-category-btn" class="button button-primary">新增</button>
					</div>
				</div>
				
				<!-- 現有分類列表 -->
				<div class="category-list-section">
					<h3>現有分類</h3>
					<div id="category-list">
						<!-- 這裡會動態載入分類列表 -->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
/* 備註內容點擊樣式 */
.wp-list-table .column-note_content strong a {
	display: block;
	padding: 4px 0;
	border-radius: 3px;
	transition: background-color 0.2s ease;
}

.wp-list-table .column-note_content strong a:hover {
	background-color: #f8f9fa;
	text-decoration: none !important;
	cursor: pointer;
}

.wp-list-table .column-note_content strong a:focus {
	box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, 0.8);
	outline: none;
}

/* 改善備註內容的可讀性 */
.wp-list-table .column-note_content {
	max-width: 400px;
}

.wp-list-table .column-note_content strong {
	font-weight: 400;
	line-height: 1.4;
}

/* 原始貼文欄位樣式 */
.wp-list-table .column-original_post {
	max-width: 300px;
	min-width: 150px;
}

.original-post-text {
	line-height: 1.4;
	word-wrap: break-word;
	max-width: 100%;
}

.original-post-image {
	display: flex;
	align-items: center;
}

.original-post-file {
	max-width: 100%;
}

.original-post-file span {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-size: 12px;
	display: block;
}

.original-post-unknown {
	color: #999;
	font-style: italic;
}

/* 分類下拉選單樣式 */
.note-category-select {
	width: 100% !important;
}

.select2-container {
	min-width: 150px !important;
	font-size: 13px;
}

.select2-container--default .select2-selection--single {
	height: 30px !important;
	line-height: 28px !important;
	border-color: #ddd !important;
	border-radius: 3px !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
	padding-left: 8px !important;
	padding-right: 20px !important;
	color: #333 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
	height: 28px !important;
	right: 5px !important;
}

.select2-dropdown {
	border-color: #ddd !important;
	border-radius: 3px !important;
}

.select2-container--default .select2-results__option {
	padding: 6px 12px !important;
	cursor: pointer !important;
}

.select2-container--default .select2-results__option--highlighted {
	background-color: #0073aa !important;
	color: #fff !important;
}

.select2-container--default .select2-results__option--highlighted .product-price {
	color: #fff !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
	border-color: #ddd !important;
	border-radius: 3px !important;
}

/* 分類編輯按鈕樣式 */
.category-edit-btn {
	opacity: 0;
}

#the-list tr:hover .category-edit-btn {
	opacity: 1;
}

.category-display-wrapper:hover .category-edit-btn {
	color: #0073aa !important;
}
p.search-box {
	margin: 0;
}

/* 分類管理燈箱樣式 */
.modal-overlay {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.5);
	z-index: 100000;
	display: flex;
	align-items: center;
	justify-content: center;
}

.modal-content {
	background: white;
	border-radius: 5px;
	width: 90%;
	max-width: 600px;
	max-height: 80vh;
	overflow-y: auto;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-header {
	padding: 20px;
	border-bottom: 1px solid #ddd;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.modal-header h2 {
	margin: 0;
	font-size: 18px;
}

.modal-close {
	background: none;
	border: none;
	font-size: 24px;
	cursor: pointer;
	color: #666;
	padding: 0;
	width: 30px;
	height: 30px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.modal-close:hover {
	color: #000;
}

.modal-body {
	padding: 20px;
}

.category-add-section,
.category-list-section {
	margin-bottom: 30px;
}

.category-add-section h3,
.category-list-section h3 {
	margin-top: 0;
	margin-bottom: 15px;
	font-size: 16px;
}

.category-add-form {
	display: flex;
	gap: 10px;
	align-items: center;
}

.category-add-form input[type="text"] {
	flex: 1;
	padding: 8px 12px;
	border: 1px solid #ddd;
	border-radius: 3px;
}

.category-add-form input[type="color"] {
	width: 40px;
	height: 36px;
	border: 1px solid #ddd;
	border-radius: 3px;
	padding: 0;
	cursor: pointer;
}

.category-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 10px;
	border: 1px solid #ddd;
	border-radius: 3px;
	margin-bottom: 5px;
	background: #f9f9f9;
}

.category-item.editing {
	background: #fff;
}

.category-info {
	display: flex;
	align-items: center;
	gap: 10px;
	flex: 1;
}

.category-color-preview {
	width: 20px;
	height: 20px;
	border-radius: 3px;
	border: 1px solid #ccc;
	flex-shrink: 0;
}

.category-name {
	font-weight: 500;
}

.category-edit-controls {
	display: flex;
	align-items: center;
	gap: 8px;
}

.category-edit-input {
	padding: 4px 8px;
	border: 1px solid #ddd;
	border-radius: 3px;
	font-size: 13px;
}

.category-color-input {
	width: 30px;
	height: 30px;
	border: 1px solid #ddd;
	border-radius: 3px;
	padding: 0;
	cursor: pointer;
}

.category-actions {
	display: flex;
	gap: 5px;
}

.category-actions .button {
	padding: 5px 10px;
	font-size: 12px;
	height: auto;
	line-height: 1.2;
}

.category-item .save-category-btn,
.category-item .cancel-category-btn {
	display: none;
}

.category-item.editing .save-category-btn,
.category-item.editing .cancel-category-btn {
	display: inline-block;
}

.category-item.editing .edit-category-btn,
.category-item.editing .delete-category-btn {
	display: none;
}

.category-stats {
	font-size: 12px;
	color: #666;
	margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
	// 初始化商品篩選 Select2
	const currentProductId = '<?php echo esc_js( isset( $_GET['product_filter'] ) && $_GET['product_filter'] !== '' && $_GET['product_filter'] !== '_no_product' ? $_GET['product_filter'] : '' ); ?>';
	let isProductComposing = false;

	$('#filter-product').select2({
		placeholder: '搜尋商品...',
		allowClear: true,
		minimumInputLength: 0,
		ajax: {
			url: ajaxurl,
			dataType: 'json',
			delay: 250,
			type: 'POST',
			data: function(params) {
				return {
					action: 'otz_search_products',
					search: params.term,
					nonce: '<?php echo wp_create_nonce( 'orderchatz_chat_action' ); ?>'
				};
			},
			processResults: function(data) {
				if (isProductComposing) return {};
				if (data.success) {
					return {
						results: data.data.products.map(function(product) {
							return {
								id: product.id,
								text: product.title,
								product: product
							};
						})
					};
				}
				return {results: []};
			},
			cache: true
		},
		templateResult: function(product) {
			if (product.loading) {
				return product.text;
			}

			if (!product.product) {
				return product.text;
			}

			try {
				const productData = product.product || {};
				const imageUrl = (productData.image && typeof productData.image === 'string') ? productData.image : '';
				const title = productData.title || '未命名商品';
				const price = productData.price || '0';

				const imageHtml = imageUrl ?
					'<img src="' + imageUrl + '" alt="' + title + '" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px;" />' :
					'<div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px; margin-right: 10px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">無圖</div>';

				const $container = $(
					'<div class="product-option" style="display: flex; align-items: center;">' +
					'<div class="product-image">' +
					imageHtml +
					'</div>' +
					'<div class="product-info">' +
					'<div class="product-title" style="font-weight: 500;">' + title + '</div>' +
					'<div class="product-price" style="font-size: 12px; color: #666;">' + price + '</div>' +
					'</div>' +
					'</div>'
				);

				return $container;
			} catch (error) {
				console.error('Error in templateResult:', error);
				return product.text || '商品載入錯誤';
			}
		},
		templateSelection: function(product) {
			return product.text;
		}
	});

	// 處理中文輸入法
	$('#filter-product').on('select2:open', function() {
		const $search = $('.select2-search__field');
		$search.on('compositionstart', () => isProductComposing = true);
		$search.on('compositionend', () => isProductComposing = false);
	});

	// 如果有當前選中的商品，載入並顯示
	if (currentProductId) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'otz_get_product_by_id',
				product_id: currentProductId,
				nonce: '<?php echo wp_create_nonce( 'orderchatz_chat_action' ); ?>'
			},
			success: function(response) {
				if (response.success && response.data.product) {
					const product = response.data.product;
					const option = new Option(product.title, product.id, true, true);
					$('#filter-product').append(option).trigger('change');
				}
			}
		});
	}

	// 分類編輯按鈕點擊事件
	$(document).on('click', '.category-edit-btn', function(e) {
		e.preventDefault();
		const $wrapper = $(this).closest('.category-display-wrapper');
		const $display = $wrapper.find('.category-display,.category-display-tag');
		const $editForm = $wrapper.find('.category-edit-form');
		const $select = $editForm.find('.note-category-select');
		const noteId = $(this).data('note-id');
		
		// 隱藏顯示模式，顯示編輯模式
		$display.hide();
		$(this).hide();
		$editForm.show();

		let isComposing = false;
		
		// 初始化 Select2
		if (!$select.hasClass('select2-hidden-accessible')) {
			$select.select2({
				placeholder: '選擇分類...',
				allowClear: true,
				tags: true,
				tokenSeparators: [','],
				ajax: {
					url: ajaxurl,
					dataType: 'json',
					delay: 250,
					type: 'POST',
					data: function(params) {
						return {
							action: 'otz_search_note_categories',
							nonce: '<?php echo wp_create_nonce( 'orderchatz_chat_action' ); ?>',
							search: params.term || '',
							page: params.page || 1
						};
					},
					processResults: function(data) {
						if (isComposing) {
							return {};
						}
						if (!data.success) {
							return { results: [] };
						}
						return {
							results: data.data.results || [],
							pagination: data.data.pagination || {}
						};
					},
					cache: true
				},
				minimumInputLength: 0,
				escapeMarkup: function(markup) {
					return markup;
				}
			});
		}

		$select.on('select2:open', function () {
			const $search = $('.select2-search__field');
			$search.on('compositionstart', () => isComposing = true);
			$search.on('compositionend', () => isComposing = false);
		});
	});
	
	// 分類儲存按鈕點擊事件
	$(document).on('click', '.category-save-btn', function(e) {
		e.preventDefault();
		const $wrapper = $(this).closest('.category-display-wrapper');
		const $select = $wrapper.find('.note-category-select');
		const noteId = $wrapper.data('note-id');
		const category = $select.val();
		
		updateNoteCategory(noteId, category, $wrapper);
	});
	
	// 分類取消按鈕點擊事件
	$(document).on('click', '.category-cancel-btn', function(e) {
		e.preventDefault();
		const $wrapper = $(this).closest('.category-display-wrapper');
		cancelCategoryEdit($wrapper);
	});
	
	/**
	 * 取得分類顏色
	 */
	function getCategoryColor(categoryName, categories) {
		if (!categoryName) {
			return '#f5f5f5';
		}
		
		// 首先嘗試從分類設定中取得顏色
		for (let i = 0; i < categories.length; i++) {
			if (categories[i].name === categoryName) {
				return categories[i].color;
			}
		}
		
		// 如果找不到，使用預設顏色演算法
		const defaultColors = [
			'#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c',
			'#34495e', '#f1c40f', '#e67e22', '#95a5a6', '#8e44ad', '#16a085'
		];
		
		// 使用分類名稱的字串雜湊來選擇顏色
		let hash = 0;
		for (let i = 0; i < categoryName.length; i++) {
			const char = categoryName.charCodeAt(i);
			hash = ((hash << 5) - hash) + char;
			hash = hash & hash; // 轉換為 32bit 整數
		}
		hash = Math.abs(hash);
		
		return defaultColors[hash % defaultColors.length];
	}

	/**
	 * 更新備註分類
	 */
	function updateNoteCategory(noteId, category, $wrapper) {
		let display = $wrapper.find('.category-display,.category-display-tag');
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_note_category',
				_wpnonce: '<?php echo wp_create_nonce( 'orderchatz_admin_action' ); ?>',
				note_id: noteId,
				category: category
			},
			success: function(response) {
				if (response.success) {
					// 使用更直接的方法取得分類顏色
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'get_note_categories_with_stats',
							nonce: '<?php echo wp_create_nonce( 'orderchatz_admin_action' ); ?>'
						},
						success: function(categoriesResponse) {
							
							if (categoriesResponse.success && categoriesResponse.data && categoriesResponse.data.categories) {
								const categories = categoriesResponse.data.categories;
								
								if (category) {
									// 尋找對應的分類
									let categoryColor = null;
									for (let i = 0; i < categories.length; i++) {
										if (categories[i].name === category) {
											categoryColor = categories[i].color;
											break;
										}
									}
									
									// 如果沒找到顏色，使用預設顏色算法
									if (!categoryColor) {
										categoryColor = getCategoryColor(category, categories);
									}
									// 建立有顏色的分類標籤
									const categoryHtml = '<span class="category-display-tag" style="' +
										'display: inline-block; ' +
										'padding: 2px 8px; ' +
										'border-radius: 3px; ' +
										'font-size: 12px; ' +
										'color: #fff; ' +
										'background-color: ' + categoryColor + ';' +
										'">' + category + '</span>';
									display.replaceWith(categoryHtml);
								} else {
									// 無分類時顯示預設樣式
									display.html('<span style="color: #999; font-style: italic;"><?php echo esc_js( __( '無分類', 'otz' ) ); ?></span>');
								}
							} else {
								// 使用預設顏色
								if (category) {
									const categoryColor = getCategoryColor(category, []);
									const categoryHtml = '<span class="category-display-tag" style="' +
										'display: inline-block; ' +
										'padding: 2px 8px; ' +
										'border-radius: 3px; ' +
										'font-size: 12px; ' +
										'color: #fff; ' +
										'background-color: ' + categoryColor + ';' +
										'">' + category + '</span>';

									display.html(categoryHtml);
									
								} else {
									display.html('<span style="color: #999; font-style: italic;"><?php echo esc_js( __( '無分類', 'otz' ) ); ?></span>');
								}
							}
							
							// 切換回顯示模式
							cancelCategoryEdit($wrapper);
							
							// 顯示成功訊息
							showMessage(response.data.message, 'success');
						},
						error: function(xhr, status, error) {
							console.log('取得分類資料失敗:', xhr, status, error);
							// 如果無法取得分類資料，使用預設顏色
							if (category) {
								const categoryColor = getCategoryColor(category, []);
								const categoryHtml = '<span class="category-display-tag" style="' +
									'display: inline-block; ' +
									'padding: 2px 8px; ' +
									'border-radius: 3px; ' +
									'font-size: 12px; ' +
									'color: #fff; ' +
									'background-color: ' + categoryColor + ';' +
									'">' + category + '</span>';

								display.html(categoryHtml);
							} else {
								display.html('<span style="color: #999; font-style: italic;"><?php echo esc_js( __( '無分類', 'otz' ) ); ?></span>');
							}
							
							// 切換回顯示模式
							cancelCategoryEdit($wrapper);
							
							// 顯示成功訊息
							showMessage(response.data.message, 'success');
						}
					});
				} else {
					showMessage(response.data.message || '更新失敗', 'error');
				}
			},
			error: function(xhr, status, error) {
				console.log('AJAX Error Details:', {
					status: xhr.status,
					statusText: xhr.statusText,
					responseText: xhr.responseText,
					error: error,
					ajaxStatus: status
				});
				showMessage('網路錯誤，請稍後再試。錯誤代碼: ' + xhr.status + ' - ' + xhr.statusText, 'error');
			}
		});
	}
	
	/**
	 * 取消分類編輯
	 */
	function cancelCategoryEdit($wrapper) {
		$wrapper.find('.category-display').show();
		$wrapper.find('.category-display-tag').show();
		$wrapper.find('.category-edit-btn').show();
		$wrapper.find('.category-edit-form').hide();
	}
	
	/**
	 * 顯示訊息
	 */
	function showMessage(message, type) {
		const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
		$('.wrap h1').after($notice);
		
		// 3秒後自動隱藏
		setTimeout(function() {
			$notice.fadeOut();
		}, 3000);
	}
	
	// 分類管理功能
	$('#category-manager-btn').on('click', function() {
		$('#category-manager-modal').show();
		loadCategories();
	});
	
	// 關閉燈箱
	$('.modal-close, .modal-overlay').on('click', function(e) {
		if (e.target === this) {
			$('#category-manager-modal').hide();
		}
	});
	
	// 新增分類
	$('#add-category-btn').on('click', function() {
		const categoryName = $('#new-category-name').val().trim();
		const categoryColor = $('#new-category-color').val();
		if (!categoryName) {
			alert('請輸入分類名稱');
			return;
		}
		addCategory(categoryName, categoryColor);
	});
	
	// Enter 鍵新增分類
	$('#new-category-name').on('keypress', function(e) {
		if (e.which === 13) {
			$('#add-category-btn').click();
		}
	});
	
	/**
	 * 載入分類列表
	 */
	function loadCategories() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'get_note_categories_with_stats',
				nonce: '<?php echo wp_create_nonce( 'orderchatz_admin_action' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					displayCategories(response.data.categories);
				} else {
					showMessage(response.data.message || '載入分類失敗', 'error');
				}
			},
			error: function() {
				showMessage('網路錯誤，請稍後再試', 'error');
			}
		});
	}
	
	/**
	 * 顯示分類列表
	 */
	function displayCategories(categories) {
		const $categoryList = $('#category-list');
		$categoryList.empty();
		
		if (categories.length === 0) {
			$categoryList.html('<p>尚未建立任何分類</p>');
			return;
		}
		
		categories.forEach(function(category) {
			const $item = $('<div class="category-item" data-category="' + category.name + '" data-color="' + (category.color || '#3498db') + '">');
			$item.html(
				'<div class="category-info">' +
					'<div class="category-color-preview" style="background-color: ' + (category.color || '#3498db') + ';"></div>' +
					'<span class="category-name">' + category.name + '</span>' +
					'<div class="category-edit-controls" style="display: none;">' +
						'<input type="text" class="category-edit-input" value="' + category.name + '" maxlength="50">' +
						'<input type="color" class="category-color-input" value="' + (category.color || '#3498db') + '" title="選擇顏色">' +
					'</div>' +
					'<span class="category-stats">(' + category.count + ' 個備註)</span>' +
				'</div>' +
				'<div class="category-actions">' +
					'<button type="button" class="button edit-category-btn">編輯</button>' +
					'<button type="button" class="button save-category-btn">儲存</button>' +
					'<button type="button" class="button cancel-category-btn">取消</button>' +
					'<button type="button" class="button delete-category-btn" style="color:#d63638;">刪除</button>' +
				'</div>'
			);
			$categoryList.append($item);
		});
	}
	
	/**
	 * 新增分類
	 */
	function addCategory(categoryName, categoryColor) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'add_note_category',
				nonce: '<?php echo wp_create_nonce( 'orderchatz_admin_action' ); ?>',
				category_name: categoryName,
				category_color: categoryColor
			},
			success: function(response) {
				if (response.success) {
					$('#new-category-name').val('');
					$('#new-category-color').val('#3498db');
					loadCategories();
					showMessage(response.data.message, 'success');
				} else {
					showMessage(response.data.message || '新增分類失敗', 'error');
				}
			},
			error: function() {
				showMessage('網路錯誤，請稍後再試', 'error');
			}
		});
	}
	
	// 編輯分類
	$(document).on('click', '.edit-category-btn', function() {
		const $item = $(this).closest('.category-item');
		$item.addClass('editing');
		$item.find('.category-name').hide();
		$item.find('.category-color-preview').hide();
		$item.find('.category-edit-controls').show();
		$item.find('.category-edit-input').focus();
	});
	
	// 儲存分類編輯
	$(document).on('click', '.save-category-btn', function() {
		const $item = $(this).closest('.category-item');
		const oldName = $item.data('category');
		const oldColor = $item.data('color');
		const newName = $item.find('.category-edit-input').val().trim();
		const newColor = $item.find('.category-color-input').val();
		
		if (!newName) {
			alert('分類名稱不能為空');
			return;
		}
		
		if (oldName === newName && oldColor === newColor) {
			cancelCategoryModalEdit($item);
			return;
		}
		
		updateCategory(oldName, newName, newColor, $item);
	});
	
	// 取消編輯
	$(document).on('click', '.cancel-category-btn', function() {
		const $item = $(this).closest('.category-item');
		cancelCategoryModalEdit($item);
	});
	
	// 刪除分類
	$(document).on('click', '.delete-category-btn', function() {
		const $item = $(this).closest('.category-item');
		const categoryName = $item.data('category');
		const categoryStats = $item.find('.category-stats').text();
		
		if (!confirm('確定要刪除分類「' + categoryName + '」嗎？\n' + categoryStats + '\n\n刪除後，使用此分類的備註將變為「無分類」。')) {
			return;
		}
		
		deleteCategory(categoryName);
	});
	
	/**
	 * 取消分類管理燈箱中的分類編輯
	 */
	function cancelCategoryModalEdit($item) {
		$item.removeClass('editing');
		$item.find('.category-name').show();
		$item.find('.category-color-preview').show();
		$item.find('.category-edit-controls').hide();
		
		// 恢復原始值
		const originalName = $item.data('category');
		const originalColor = $item.data('color');
		$item.find('.category-edit-input').val(originalName);
		$item.find('.category-color-input').val(originalColor);
	}
	
	/**
	 * 更新分類
	 */
	function updateCategory(oldName, newName, newColor, $item) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'update_category_name',
				nonce: '<?php echo wp_create_nonce( 'orderchatz_admin_action' ); ?>',
				old_name: oldName,
				new_name: newName,
				new_color: newColor
			},
			success: function(response) {
				if (response.success) {
					// 重載分類列表（會自動退出編輯模式）
					loadCategories();
					showMessage(response.data.message, 'success');
				} else {
					showMessage(response.data.message || '更新分類失敗', 'error');
					cancelCategoryModalEdit($item);
				}
			},
			error: function() {
				showMessage('網路錯誤，請稍後再試', 'error');
				cancelCategoryModalEdit($item);
			}
		});
	}
	
	/**
	 * 刪除分類
	 */
	function deleteCategory(categoryName) {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'delete_note_category',
				nonce: '<?php echo wp_create_nonce( 'orderchatz_admin_action' ); ?>',
				category_name: categoryName
			},
			success: function(response) {
				if (response.success) {
					loadCategories();
					showMessage(response.data.message, 'success');
					// 重新載入頁面以更新分類篩選下拉選單
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					showMessage(response.data.message || '刪除分類失敗', 'error');
				}
			},
			error: function() {
				showMessage('網路錯誤，請稍後再試', 'error');
			}
		});
	}
});
</script>
