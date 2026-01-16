<?php
/**
 * OrderChatz 推播頁面渲染器
 *
 * 處理推播訊息頁面的內容渲染，包含推播活動列表和 CRUD 功能
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Admin\Lists\BroadcastListTable;
use OrderChatz\Database\Broadcast\Campaign;


/**
 * 推播頁面渲染器類別
 *
 * 渲染 LINE 推播活動管理介面，支援列表顯示和 CRUD 操作
 */
class Broadcast extends PageRenderer {

	/**
	 * Campaign 資料庫操作類別
	 *
	 * @var Campaign
	 */
	private $campaign;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->campaign = new Campaign( $wpdb );

		parent::__construct(
			__( '推播', 'otz' ),
			'otz-broadcast',
			true
		);
	}

	/**
	 * 處理各種操作
	 *
	 * @return void
	 */
	public function handle_actions() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

		// 處理刪除.
		if ( 'delete' === $action && isset( $_GET['id'] ) ) {
			$this->handle_delete();
		}

		// 處理複製.
		if ( 'copy' === $action && isset( $_GET['id'] ) ) {
			$this->handle_copy();
		}

		// 處理表單儲存.
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_campaign'] ) ) {
			$this->handle_save();
		}
	}

	/**
	 * 渲染推播頁面內容
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		$this->handle_actions();
		$this->show_messages();

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

		switch ( $action ) {
			case 'create':
				$this->render_edit_form();
				break;
			case 'edit':
				$this->render_edit_form();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * 渲染列表頁面
	 *
	 * @return void
	 */
	private function render_list() {
		$list_table = new BroadcastListTable();
		$list_table->prepare_items();

		$template_path = OTZ_PLUGIN_DIR . 'views/admin/broadcast/list.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . __( '列表模板檔案不存在', 'otz' ) . '</p></div>';
		}
	}

	/**
	 * 載入推播編輯介面相關的 CSS 和 JavaScript 資源
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		static $assets_enqueued = false;
		if ( $assets_enqueued ) {
			return;
		}
		$assets_enqueued = true;

		// 載入 CSS 檔案（依照依賴順序）.
		// 載入 Select2 CSS (使用 CDN).
		wp_enqueue_style(
			'select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
			array(),
			'4.1.0'
		);

		wp_enqueue_style(
			'orderchatz-broadcast-base',
			OTZ_PLUGIN_URL . 'assets/css/broadcast/base.css',
			array(),
			'1.1.3'
		);

		wp_enqueue_style(
			'orderchatz-broadcast-form-layout',
			OTZ_PLUGIN_URL . 'assets/css/broadcast/form-layout.css',
			array( 'orderchatz-broadcast-base' ),
			'1.1.3'
		);

		wp_enqueue_style(
			'orderchatz-broadcast-metabox',
			OTZ_PLUGIN_URL . 'assets/css/broadcast/metabox.css',
			array( 'orderchatz-broadcast-base' ),
			'1.1.3'
		);

		wp_enqueue_style(
			'orderchatz-broadcast-audience-filter',
			OTZ_PLUGIN_URL . 'assets/css/broadcast/audience-filter.css',
			array( 'orderchatz-broadcast-base' ),
			'1.1.5'
		);

		wp_enqueue_style(
			'orderchatz-broadcast-message-editor',
			OTZ_PLUGIN_URL . 'assets/css/broadcast/message-editor.css',
			array( 'orderchatz-broadcast-base' ),
			'1.1.6'
		);

		wp_enqueue_style(
			'orderchatz-broadcast-sidebar',
			OTZ_PLUGIN_URL . 'assets/css/broadcast/sidebar.css',
			array( 'orderchatz-broadcast-base' ),
			'1.1.5'
		);

		// 載入必要的 JS 依賴.
		wp_enqueue_media(); // WordPress 媒體上傳器.
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'select2' ); // 商品搜尋下拉選單.

		// 1. 載入 Value Renderer 註冊器（最先載入）.
		wp_enqueue_script(
			'orderchatz-value-renderer-registry',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderer-registry.js',
			array( 'jquery' ),
			'1.1.3',
			true
		);

		// 2. 載入基礎類別（依賴 Registry）.
		wp_enqueue_script(
			'orderchatz-filter-condition',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/filter-condition.js',
			array( 'jquery', 'orderchatz-value-renderer-registry' ),
			'1.1.3',
			true
		);

		wp_enqueue_script(
			'orderchatz-filter-group',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/filter-group.js',
			array( 'jquery', 'orderchatz-filter-condition' ),
			'1.1.3',
			true
		);

		// 3. 載入 Value Renderers（依賴 Registry 和 Select2）.
		wp_enqueue_script(
			'orderchatz-product-selector',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderers/product-selector.js',
			array( 'jquery', 'select2', 'orderchatz-value-renderer-registry' ),
			'1.1.3',
			true
		);

		wp_enqueue_script(
			'orderchatz-tag-selector',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderers/tag-selector.js',
			array( 'jquery', 'select2', 'orderchatz-value-renderer-registry' ),
			'1.1.3',
			true
		);

		wp_enqueue_script(
			'orderchatz-product-tag-selector',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderers/product-tag-selector.js',
			array( 'jquery', 'select2', 'orderchatz-value-renderer-registry' ),
			'1.1.5',
			true
		);

		wp_enqueue_script(
			'orderchatz-category-selector',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderers/category-selector.js',
			array( 'jquery', 'select2', 'orderchatz-value-renderer-registry' ),
			'1.1.5',
			true
		);

		wp_enqueue_script(
			'orderchatz-tag-count-renderer',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/renderers/TagCountRenderer.js',
			array( 'jquery', 'select2', 'orderchatz-value-renderer-registry' ),
			'1.1.5',
			true
		);

		wp_enqueue_script(
			'orderchatz-text-renderer',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderers/text-renderer.js',
			array( 'jquery', 'orderchatz-value-renderer-registry' ),
			'1.1.4',
			true
		);

		wp_enqueue_script(
			'orderchatz-select-renderer',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/value-renderers/select-renderer.js',
			array( 'jquery', 'orderchatz-value-renderer-registry' ),
			'1.1.4',
			true
		);

		// 4. 載入 FilterBuilder（依賴所有基礎類別）.
		wp_enqueue_script(
			'orderchatz-filter-builder',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/filter-builder.js',
			array( 'jquery', 'orderchatz-filter-group', 'orderchatz-product-selector', 'orderchatz-tag-selector', 'orderchatz-product-tag-selector', 'orderchatz-category-selector', 'orderchatz-text-renderer', 'orderchatz-select-renderer' ),
			'1.1.5',
			true
		);

		// 5. 載入其他推播編輯器 JS 模組.
		wp_enqueue_script(
			'orderchatz-broadcast-edit-form',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/edit-form.js',
			array( 'jquery' ),
			'1.1.7',
			true
		);

		wp_enqueue_script(
			'orderchatz-broadcast-audience-selector',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/audience-selector.js',
			array( 'jquery', 'orderchatz-filter-builder' ),
			'1.1.3',
			true
		);

		wp_enqueue_script(
			'orderchatz-broadcast-message-editor',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/message-editor.js',
			array( 'jquery' ),
			'1.1.7',
			true
		);

		wp_enqueue_script(
			'orderchatz-broadcast-schedule-selector',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/schedule-selector.js',
			array( 'jquery' ),
			'1.1.5',
			true
		);

		wp_enqueue_script(
			'orderchatz-broadcast-tag-manager',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/tag-manager.js',
			array( 'jquery' ),
			'1.1.3',
			true
		);

		wp_enqueue_script(
			'orderchatz-broadcast-parameter-copier',
			OTZ_PLUGIN_URL . 'assets/js/broadcast/parameter-copier.js',
			array( 'jquery' ),
			'1.1.3',
			true
		);

		// 初始化並註冊預設條件.
		\OrderChatz\Services\Broadcast\FilterConditionRegistry::init_default_conditions();

		// 收集所有條件的 UI 配置.
		$conditions_config = array();
		foreach ( \OrderChatz\Services\Broadcast\FilterConditionRegistry::get_all() as $condition ) {
			$ui_config           = $condition->get_ui_config();
			$ui_config['group']  = $condition->get_group();
			$conditions_config[] = $ui_config;
		}

		// 傳遞篩選條件配置給前端.
		wp_localize_script(
			'orderchatz-filter-builder',
			'otzFilterConfig',
			array(
				'conditions' => $conditions_config,
				'nonce'      => wp_create_nonce( 'otz_broadcast_action' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'i18n'       => array(
					'addCondition'    => __( '新增規則', 'otz' ),
					'deleteCondition' => __( '刪除', 'otz' ),
					'selectCondition' => __( '選擇條件類型', 'otz' ),
					'addGroup'        => __( '新增群組', 'otz' ),
					'deleteGroup'     => __( '刪除群組', 'otz' ),
				),
			)
		);

		// 傳遞 AJAX 配置給編輯器.
		wp_localize_script(
			'orderchatz-broadcast-edit-form',
			'otzBroadcast',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'otz_broadcast_action' ),
				'i18n'     => array(
					'select_image'      => __( '選擇圖片', 'otz' ),
					'use_image'         => __( '使用此圖片', 'otz' ),
					'select_video'      => __( '選擇影片', 'otz' ),
					'use_video'         => __( '使用此影片', 'otz' ),
					'broadcast_confirm' => __( '確定要立即發送推播嗎？此操作無法復原。', 'otz' ),
				),
			)
		);

		// 傳遞 AJAX 配置給訊息編輯器.
		wp_localize_script(
			'orderchatz-broadcast-message-editor',
			'otzBroadcast',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'otz_broadcast_action' ),
				'i18n'    => array(
					'test_user_id_required'    => __( '請輸入測試用 LINE User ID', 'otz' ),
					'message_content_required' => __( '請先填寫訊息內容', 'otz' ),
					'sending'                  => __( '發送中...', 'otz' ),
					'test_message_sent'        => __( '測試訊息已發送', 'otz' ),
					'test_message_failed'      => __( '測試訊息發送失敗', 'otz' ),
					'ajax_error'               => __( 'AJAX 請求失敗', 'otz' ),
					'send_test_message'        => __( '發送測試訊息', 'otz' ),
					'select_image'             => __( '選擇圖片', 'otz' ),
					'use_image'                => __( '使用此圖片', 'otz' ),
					'select_video'             => __( '選擇影片', 'otz' ),
					'use_video'                => __( '使用此影片', 'otz' ),
				),
			)
		);
	}

	/**
	 * 渲染編輯表單
	 *
	 * @return void
	 */
	private function render_edit_form() {
		$campaign       = null;
		$broadcast_logs = array();

		if ( isset( $_GET['id'] ) ) {
			$campaign_id = intval( $_GET['id'] );
			$campaign    = $this->campaign->get_campaign( $campaign_id );

			if ( ! $campaign ) {
				echo '<div class="notice notice-error"><p>' . __( '找不到推播活動', 'otz' ) . '</p></div>';
				return;
			}

			// 查詢推播紀錄（編輯模式）.
			global $wpdb;
			$log_handler    = new \OrderChatz\Database\Broadcast\Log( $wpdb );
			$broadcast_logs = $log_handler->get_logs_by_campaign(
				$campaign_id,
				array(
					'order_by' => 'executed_at',
					'order'    => 'DESC',
					'limit'    => 9999,
					'offset'   => 0,
				)
			);
		}

		// 準備預載資料（在 enqueue_assets 之前）.
		$preload_data = $this->prepare_preload_data( $campaign );

		// 載入編輯介面資源.
		$this->enqueue_assets();

		// 將預載資料傳遞給前端.
		if ( ! empty( $preload_data['products'] ) || ! empty( $preload_data['tags'] ) || ! empty( $preload_data['product_tags'] ) || ! empty( $preload_data['categories'] ) ) {
			wp_localize_script(
				'orderchatz-product-selector',
				'otzFilterPreloadData',
				$preload_data
			);
		}

		// 準備參數列表 HTML.
		$message_replacer = new \OrderChatz\Services\MessageReplace();
		$parameters_html  = $message_replacer->render_parameters_list();

		$template_path = OTZ_PLUGIN_DIR . 'views/admin/broadcast/edit.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . __( '編輯表單模板檔案不存在', 'otz' ) . '</p></div>';
		}
	}

	/**
	 * 處理刪除操作
	 *
	 * @return void
	 */
	private function handle_delete() {
		$campaign_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'delete_campaign_' . $campaign_id ) ) {
			wp_die( esc_html__( '安全驗證失敗', 'otz' ) );
		}

		$result = $this->campaign->delete_campaign( $campaign_id );

		if ( $result ) {
			wp_safe_redirect( add_query_arg( 'message', 'deleted', admin_url( 'admin.php?page=otz-broadcast' ) ) );
			exit;
		} else {
			wp_safe_redirect( add_query_arg( 'error', 'delete_failed', admin_url( 'admin.php?page=otz-broadcast' ) ) );
			exit;
		}
	}

	/**
	 * 處理複製操作
	 *
	 * @return void
	 */
	private function handle_copy() {
		$campaign_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'copy_campaign_' . $campaign_id ) ) {
			wp_die( esc_html__( '安全驗證失敗', 'otz' ) );
		}

		$original = $this->campaign->get_campaign( $campaign_id );

		if ( ! $original ) {
			wp_safe_redirect( add_query_arg( 'error', 'campaign_not_found', admin_url( 'admin.php?page=otz-broadcast' ) ) );
			exit;
		}

		// 準備複製資料.
		$copy_data                  = $original;
		$copy_data['campaign_name'] = $original['campaign_name'] . ' - ' . __( '副本', 'otz' );
		$copy_data['status']        = 'published';
		$copy_data['copied_from']   = $campaign_id;
		unset( $copy_data['id'] );
		unset( $copy_data['created_at'] );
		unset( $copy_data['updated_at'] );
		unset( $copy_data['created_by'] );
		unset( $copy_data['updated_by'] );

		$new_id = $this->campaign->save_campaign( $copy_data );

		if ( $new_id ) {
			wp_safe_redirect( add_query_arg( 'message', 'copied', admin_url( 'admin.php?page=otz-broadcast' ) ) );
			exit;
		} else {
			wp_safe_redirect( add_query_arg( 'error', 'copy_failed', admin_url( 'admin.php?page=otz-broadcast' ) ) );
			exit;
		}
	}

	/**
	 * 處理儲存操作
	 *
	 * @return void
	 */
	private function handle_save() {
		// Nonce 驗證.
		check_admin_referer( 'save_broadcast_campaign' );

		// 收集並過濾表單資料.
		$campaign_data = array();

		// ID（編輯模式）.
		if ( isset( $_POST['campaign_id'] ) && ! empty( $_POST['campaign_id'] ) ) {
			$campaign_data['id'] = intval( $_POST['campaign_id'] );
		}

		// 必填欄位：活動名稱.
		if ( isset( $_POST['campaign_name'] ) ) {
			$campaign_data['campaign_name'] = sanitize_text_field( wp_unslash( $_POST['campaign_name'] ) );
		}

		// 活動描述.
		if ( isset( $_POST['campaign_description'] ) ) {
			$campaign_data['description'] = sanitize_textarea_field( wp_unslash( $_POST['campaign_description'] ) );
		}

		// 必填欄位：受眾類型.
		if ( isset( $_POST['audience_type'] ) ) {
			$campaign_data['audience_type'] = sanitize_text_field( wp_unslash( $_POST['audience_type'] ) );
		}

		// 篩選條件（JSON）.
		if ( isset( $_POST['filter_conditions'] ) && ! empty( $_POST['filter_conditions'] ) ) {
			$raw_conditions = wp_unslash( $_POST['filter_conditions'] );

			// 解碼 JSON 字串.
			if ( is_string( $raw_conditions ) ) {
				$decoded_conditions = json_decode( $raw_conditions, true );
			} elseif ( is_array( $raw_conditions ) ) {
				$decoded_conditions = $raw_conditions;
			} else {
				$decoded_conditions = null;
			}

			// 驗證並清理條件資料.
			if ( is_array( $decoded_conditions ) && isset( $decoded_conditions['conditions'] ) ) {
				// 初始化條件註冊器.
				\OrderChatz\Services\Broadcast\FilterConditionRegistry::init_default_conditions();

				$sanitized_conditions = array();

				foreach ( $decoded_conditions['conditions'] as $group_id => $conditions ) {
					if ( ! is_array( $conditions ) ) {
						continue;
					}

					$sanitized_group = array();

					foreach ( $conditions as $condition ) {
						// 驗證條件結構.
						if ( ! isset( $condition['field'], $condition['operator'], $condition['value'] ) ) {
							continue;
						}

						// 使用策略驗證條件.
						$strategy = \OrderChatz\Services\Broadcast\FilterConditionRegistry::get( $condition['field'] );
						if ( ! $strategy || ! $strategy->validate( $condition ) ) {
							continue;
						}

						// 清理條件資料.
						$sanitized_condition = array(
							'field'    => sanitize_text_field( $condition['field'] ),
							'operator' => sanitize_text_field( $condition['operator'] ),
							'value'    => $this->sanitize_condition_value( $condition['value'] ),
						);

						$sanitized_group[] = $sanitized_condition;
					}

					// 只保留有效的群組.
					if ( ! empty( $sanitized_group ) ) {
						$sanitized_conditions[ $group_id ] = $sanitized_group;
					}
				}

				// 傳遞清理後的條件陣列.
				if ( ! empty( $sanitized_conditions ) ) {
					$campaign_data['filter_conditions'] = array(
						'conditions' => $sanitized_conditions,
					);
				}
			}
		}

		// 必填欄位：訊息類型.
		if ( isset( $_POST['message_type'] ) ) {
			$campaign_data['message_type'] = sanitize_text_field( wp_unslash( $_POST['message_type'] ) );
		}

		// 必填欄位：訊息內容（JSON）.
		$message_type = $campaign_data['message_type'] ?? 'text';

		if ( 'text' === $message_type && isset( $_POST['message_content_text'] ) ) {
			$content = trim( sanitize_textarea_field( wp_unslash( $_POST['message_content_text'] ) ) );
			// 驗證文字內容不為空.
			if ( ! empty( $content ) ) {
				$campaign_data['message_content'] = array( 'text' => $content );
			}
		}

		if ( 'flex' === $message_type && isset( $_POST['message_content_flex'] ) ) {
			$flex_json = trim( wp_unslash( $_POST['message_content_flex'] ) );
			// 驗證 JSON 格式且不為空.
			if ( ! empty( $flex_json ) ) {
				$decoded = json_decode( $flex_json, true );
				if ( null !== $decoded && JSON_ERROR_NONE === json_last_error() ) {
					$campaign_data['message_content'] = $decoded;
				}
			}
		}

		if ( 'image' === $message_type && isset( $_POST['message_content_image'] ) ) {
			$url = trim( esc_url_raw( wp_unslash( $_POST['message_content_image'] ) ) );
			// 驗證 URL 不為空.
			if ( ! empty( $url ) ) {
				$campaign_data['message_content'] = array( 'url' => $url );
			}
		}

		if ( 'video' === $message_type ) {
			$video_url   = isset( $_POST['message_content_video'] ) ? trim( esc_url_raw( wp_unslash( $_POST['message_content_video'] ) ) ) : '';
			$preview_url = isset( $_POST['message_content_video_preview'] ) ? trim( esc_url_raw( wp_unslash( $_POST['message_content_video_preview'] ) ) ) : '';

			// 驗證 URL 不為空.
			if ( ! empty( $video_url ) && ! empty( $preview_url ) ) {
				$campaign_data['message_content'] = array(
					'videoUrl'        => $video_url,
					'previewImageUrl' => $preview_url,
				);
			}
		}

		// 停用通知.
		$campaign_data['notification_disabled'] = isset( $_POST['notification_disabled'] ) ? 1 : 0;

		// 排程類型.
		if ( isset( $_POST['schedule_type'] ) ) {
			$campaign_data['schedule_type'] = sanitize_text_field( wp_unslash( $_POST['schedule_type'] ) );

			// 排程時間.
			if ( 'scheduled' === $campaign_data['schedule_type'] && isset( $_POST['scheduled_at'] ) ) {
				$campaign_data['scheduled_at'] = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ) );
			}
		}

		// 狀態（強制設定為 published）.
		$campaign_data['status'] = 'published';

		// 分類.
		if ( isset( $_POST['campaign_category'] ) ) {
			$campaign_data['category'] = sanitize_text_field( wp_unslash( $_POST['campaign_category'] ) );
		}

		// 標籤（JSON 陣列）.
		if ( isset( $_POST['campaign_tags'] ) && is_array( $_POST['campaign_tags'] ) ) {
			$campaign_data['tags'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['campaign_tags'] ) );
		} elseif ( isset( $_POST['campaign_tags'] ) && ! empty( $_POST['campaign_tags'] ) ) {
			// 如果是逗號分隔的字串，轉換為陣列.
			$tags_string           = sanitize_text_field( wp_unslash( $_POST['campaign_tags'] ) );
			$campaign_data['tags'] = array_map( 'trim', explode( ',', $tags_string ) );
		}

		// 驗證必填欄位.
		$required_fields = array( 'campaign_name', 'audience_type', 'message_type' );
		foreach ( $required_fields as $field ) {
			if ( empty( $campaign_data[ $field ] ) ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'error' => 'missing_required_fields',
							'field' => $field,
						),
						wp_get_referer()
					)
				);
				exit;
			}
		}

		// 驗證訊息內容是否已設定.
		if ( empty( $campaign_data['message_content'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'error'        => 'missing_required_fields',
						'field'        => 'message_content',
						'message_type' => $message_type,
					),
					wp_get_referer()
				)
			);
			exit;
		}

		// 初始化 SavePushHandler.
		$save_push_handler = new \OrderChatz\Services\Broadcast\SavePushHandler();

		// 判斷是否為編輯模式.
		$is_edit_mode = ! empty( $campaign_data['id'] );
		$campaign_id  = $is_edit_mode ? $campaign_data['id'] : 0;

		// 處理排程設定.
		// 情況 1: schedule_type 切換為 immediate，清空 scheduled_at 並取消排程.
		if ( 'immediate' === $campaign_data['schedule_type'] ) {
			// 清空 scheduled_at.
			$campaign_data['scheduled_at'] = null;

			// 如果是編輯模式且之前有排程，需要取消.
			if ( $is_edit_mode && $campaign_id > 0 ) {
				$save_push_handler->unschedule_broadcast( $campaign_id );

				// 清空 action_id.
				$campaign_data['action_id'] = null;
			}
		}

		// 情況 2: schedule_type 為 scheduled 且有 scheduled_at，註冊排程.
		if ( 'scheduled' === $campaign_data['schedule_type'] && ! empty( $campaign_data['scheduled_at'] ) ) {
			// 如果是編輯模式且之前有排程，先取消舊排程.
			if ( $is_edit_mode && $campaign_id > 0 ) {
				$save_push_handler->unschedule_broadcast( $campaign_id );
			}

			// 先儲存 campaign 資料以取得 campaign_id.
			$temp_campaign_id = $this->campaign->save_campaign( $campaign_data );

			if ( false === $temp_campaign_id ) {
				wp_safe_redirect( add_query_arg( 'error', 'save_failed', wp_get_referer() ) );
				exit;
			}

			// 註冊新排程.
			$action_id = $save_push_handler->schedule_broadcast( $temp_campaign_id, $campaign_data['scheduled_at'] );

			if ( $action_id ) {
				// 更新 action_id.
				$this->campaign->save_campaign(
					array(
						'id'        => $temp_campaign_id,
						'action_id' => $action_id,
					)
				);

				wp_safe_redirect(
					add_query_arg(
						array(
							'message' => 'saved_and_scheduled',
							'id'      => $temp_campaign_id,
						),
						admin_url( 'admin.php?page=otz-broadcast&action=edit' )
					)
				);
				exit;
			} else {
				// 排程註冊失敗.
				\OrderChatz\Util\Logger::error(
					'排程註冊失敗',
					array( 'campaign_id' => $temp_campaign_id ),
					'broadcast'
				);

				wp_safe_redirect(
					add_query_arg(
						array(
							'message' => 'saved_but_schedule_failed',
							'id'      => $temp_campaign_id,
						),
						admin_url( 'admin.php?page=otz-broadcast&action=edit' )
					)
				);
				exit;
			}
		}

		// 儲存活動資料.
		$campaign_id = $this->campaign->save_campaign( $campaign_data );

		if ( false === $campaign_id ) {
			wp_safe_redirect( add_query_arg( 'error', 'save_failed', wp_get_referer() ) );
			exit;
		}

		// 檢測是否為「儲存並推播」按鈕.
		if ( isset( $_POST['save_and_broadcast'] ) ) {
			// 註冊 Action Scheduler 任務（立即返回，不阻塞）.
			$task_id = as_enqueue_async_action(
				'otz_process_broadcast_push',
				array( 'campaign_id' => $campaign_id ),
				'otz_broadcast'
			);

			if ( $task_id ) {
				// 更新 campaign 狀態為 pending.
				$this->campaign->save_campaign(
					array(
						'id'                    => $campaign_id,
						'last_execution_status' => 'pending',
					)
				);

				wp_safe_redirect(
					add_query_arg(
						array(
							'message' => 'saved_and_queued',
							'id'      => $campaign_id,
							'task_id' => $task_id,
						),
						admin_url( 'admin.php?page=otz-broadcast&action=edit' )
					)
				);
				exit;
			} else {
				// 排程失敗.
				\OrderChatz\Util\Logger::error(
					'推播排程失敗',
					array( 'campaign_id' => $campaign_id ),
					'broadcast'
				);

				wp_safe_redirect(
					add_query_arg(
						array(
							'message' => 'saved_but_queue_failed',
							'id'      => $campaign_id,
						),
						admin_url( 'admin.php?page=otz-broadcast&action=edit' )
					)
				);
				exit;
			}
		}

		// 一般儲存（無推播）.
		wp_safe_redirect(
			add_query_arg(
				array(
					'message' => 'saved',
					'id'      => $campaign_id,
				),
				admin_url( 'admin.php?page=otz-broadcast&action=edit' )
			)
		);
		exit;
	}

	/**
	 * 清理條件值
	 *
	 * @param mixed $value 條件值（字串或陣列）.
	 * @return mixed 清理後的值.
	 */
	private function sanitize_condition_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * 準備預載資料
	 *
	 * 收集所有篩選條件中的商品 ID 和標籤名稱，用於前端預載.
	 *
	 * @param array|null $campaign 活動資料.
	 * @return array 預載資料 ['products' => [...], 'tags' => [...]]
	 */
	private function prepare_preload_data( $campaign ) {
		$preload_data = array(
			'products'     => array(),
			'tags'         => array(),
			'product_tags' => array(),
			'categories'   => array(),
		);

		// 如果沒有活動資料或沒有篩選條件，返回空資料.
		if ( ! $campaign || ! isset( $campaign['filter_conditions']['conditions'] ) ) {
			return $preload_data;
		}

		$product_ids     = array();
		$tag_names       = array();
		$product_tag_ids = array();
		$category_ids    = array();

		// 遍歷所有群組的條件.
		foreach ( $campaign['filter_conditions']['conditions'] as $group_id => $conditions ) {
			if ( ! is_array( $conditions ) ) {
				continue;
			}

			foreach ( $conditions as $condition ) {
				// 收集商品 ID.
				if ( isset( $condition['field'] ) && 'order_product_id' === $condition['field'] ) {
					if ( isset( $condition['value'] ) ) {
						if ( is_array( $condition['value'] ) ) {
							$product_ids = array_merge( $product_ids, $condition['value'] );
						} else {
							$product_ids[] = $condition['value'];
						}
					}
				}

				// 收集標籤名稱.
				if ( isset( $condition['field'] ) && 'user_tag' === $condition['field'] ) {
					if ( isset( $condition['value'] ) ) {
						if ( is_array( $condition['value'] ) ) {
							$tag_names = array_merge( $tag_names, $condition['value'] );
						} else {
							$tag_names[] = $condition['value'];
						}
					}
				}

				// 收集商品標籤 ID.
				if ( isset( $condition['field'] ) && 'order_product_tag' === $condition['field'] ) {
					if ( isset( $condition['value'] ) ) {
						if ( is_array( $condition['value'] ) ) {
							$product_tag_ids = array_merge( $product_tag_ids, $condition['value'] );
						} else {
							$product_tag_ids[] = $condition['value'];
						}
					}
				}

				// 收集商品分類 ID.
				if ( isset( $condition['field'] ) && 'order_product_category' === $condition['field'] ) {
					if ( isset( $condition['value'] ) ) {
						if ( is_array( $condition['value'] ) ) {
							$category_ids = array_merge( $category_ids, $condition['value'] );
						} else {
							$category_ids[] = $condition['value'];
						}
					}
				}
			}
		}

		// 去除重複的 ID.
		$product_ids     = array_unique( $product_ids );
		$tag_names       = array_unique( $tag_names );
		$product_tag_ids = array_unique( $product_tag_ids );
		$category_ids    = array_unique( $category_ids );

		// 查詢商品資料.
		if ( ! empty( $product_ids ) ) {
			$preload_data['products'] = $this->get_products_data( $product_ids );
		}

		// 標籤資料（標籤名稱本身就是完整資料，直接使用）.
		if ( ! empty( $tag_names ) ) {
			foreach ( $tag_names as $tag ) {
				$preload_data['tags'][] = array(
					'id'   => $tag,
					'text' => $tag,
				);
			}
		}

		// 查詢商品標籤資料.
		if ( ! empty( $product_tag_ids ) ) {
			$preload_data['product_tags'] = $this->get_product_tags_data( $product_tag_ids );
		}

		// 查詢商品分類資料.
		if ( ! empty( $category_ids ) ) {
			$preload_data['categories'] = $this->get_product_categories_data( $category_ids );
		}

		return $preload_data;
	}

	/**
	 * 取得商品資料
	 *
	 * 透過 WooCommerce API 取得商品名稱，支援簡單商品和可變商品規格.
	 *
	 * @param array $product_ids 商品 ID 陣列.
	 * @return array 商品資料陣列 [['id' => '21', 'text' => '商品名稱'], ...]
	 */
	private function get_products_data( $product_ids ) {
		$products_data = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			// 跳過不存在的商品.
			if ( ! $product ) {
				continue;
			}

			// 處理可變商品規格.
			if ( $product instanceof \WC_Product_Variation ) {
				$product_name = $product->get_name(); // 取得完整規格名稱（包含父商品名稱和規格屬性）.
			} else {
				// 處理簡單商品.
				$product_name = $product->get_title();
			}

			$products_data[] = array(
				'id'   => (string) $product_id,
				'text' => $product_name,
			);
		}

		return $products_data;
	}

	/**
	 * 取得商品標籤資料
	 *
	 * 透過 WordPress Taxonomy API 取得商品標籤名稱.
	 *
	 * @param array $product_tag_ids 商品標籤 ID 陣列.
	 * @return array 商品標籤資料陣列 [['id' => '1', 'text' => '標籤名稱'], ...]
	 */
	private function get_product_tags_data( $product_tag_ids ) {
		$tags_data = array();

		foreach ( $product_tag_ids as $tag_id ) {
			$term = get_term( $tag_id, 'product_tag' );

			// 跳過不存在的標籤.
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$tags_data[] = array(
				'id'   => (string) $tag_id,
				'text' => $term->name,
			);
		}

		return $tags_data;
	}

	/**
	 * 取得商品分類資料
	 *
	 * 透過 WordPress Taxonomy API 取得商品分類名稱.
	 *
	 * @param array $category_ids 商品分類 ID 陣列.
	 * @return array 商品分類資料陣列 [['id' => '1', 'text' => '分類名稱'], ...]
	 */
	private function get_product_categories_data( $category_ids ) {
		$categories_data = array();

		foreach ( $category_ids as $category_id ) {
			$term = get_term( $category_id, 'product_cat' );

			// 跳過不存在的分類.
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$categories_data[] = array(
				'id'   => (string) $category_id,
				'text' => $term->name,
			);
		}

		return $categories_data;
	}

	/**
	 * 顯示操作訊息
	 *
	 * @return void
	 */
	private function show_messages() {
		// 確保只在 broadcast 頁面顯示.
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'otz-broadcast' ) {
			return;
		}

		// 成功訊息.
		if ( isset( $_GET['message'] ) ) {
			$message_type = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			$message      = '';
			switch ( $message_type ) {
				case 'deleted':
					$message = __( '推播活動已刪除', 'otz' );
					break;
				case 'copied':
					$message = __( '推播活動已複製', 'otz' );
					break;
				case 'saved':
					$message = __( '推播活動已儲存', 'otz' );
					break;
				case 'saved_and_queued':
					$message = __( '推播活動已儲存並加入推播排程，系統將在背景執行推播作業', 'otz' );
					break;
				case 'saved_but_queue_failed':
					$message = __( '推播活動已儲存，但排程失敗，請稍後再試', 'otz' );
					break;
				case 'saved_and_broadcasted':
					$sent_count = isset( $_GET['sent_count'] ) ? intval( $_GET['sent_count'] ) : 0;
					/* translators: %d: 成功發送的好友數量. */
					$message    = sprintf( __( '推播活動已儲存並成功發送給 %d 位好友', 'otz' ), $sent_count );
					break;
				case 'saved_but_broadcast_failed':
					$error_msg = isset( $_GET['error_msg'] ) ? urldecode( sanitize_text_field( wp_unslash( $_GET['error_msg'] ) ) ) : '';
					/* translators: %s: 錯誤訊息. */
					$message   = sprintf( __( '推播活動已儲存，但發送失敗：%s', 'otz' ), $error_msg );
					break;
				case 'saved_and_scheduled':
					$message = __( '推播活動已儲存並設定排程，系統將在指定時間自動發送', 'otz' );
					break;
				case 'saved_but_schedule_failed':
					$message = __( '推播活動已儲存，但排程設定失敗，請重新設定排程時間', 'otz' );
					break;
			}
			if ( $message ) {
				// 失敗或排程失敗訊息使用 warning notice.
				$warning_types = array( 'saved_but_broadcast_failed', 'saved_but_queue_failed', 'saved_but_schedule_failed' );
				$notice_class  = in_array( $message_type, $warning_types, true ) ? 'notice-warning' : 'notice-success';
				echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}

		// 錯誤訊息.
		if ( isset( $_GET['error'] ) ) {
			$error_type = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$error      = '';
			switch ( $error_type ) {
				case 'delete_failed':
					$error = __( '刪除失敗', 'otz' );
					break;
				case 'copy_failed':
					$error = __( '複製失敗', 'otz' );
					break;
				case 'campaign_not_found':
					$error = __( '找不到推播活動', 'otz' );
					break;
				case 'missing_required_fields':
					$field        = isset( $_GET['field'] ) ? sanitize_text_field( wp_unslash( $_GET['field'] ) ) : '';
					$message_type = isset( $_GET['message_type'] ) ? sanitize_text_field( wp_unslash( $_GET['message_type'] ) ) : '';

					// 如果是訊息內容欄位且有訊息類型資訊,顯示更詳細的錯誤訊息.
					if ( 'message_content' === $field && ! empty( $message_type ) ) {
						$type_labels = array(
							'text'  => __( '文字訊息', 'otz' ),
							'image' => __( '圖片訊息', 'otz' ),
							'video' => __( '影片訊息', 'otz' ),
							'flex'  => __( 'Flex 訊息', 'otz' ),
						);
						$type_label  = $type_labels[ $message_type ] ?? $message_type;
						/* translators: 1: 欄位名稱, 2: 訊息類型. */
						$error       = sprintf( __( '缺少必填欄位：%1$s（%2$s 內容不可為空）', 'otz' ), $field, $type_label );
					} else {
						/* translators: %s: 欄位名稱. */
						$error = sprintf( __( '缺少必填欄位：%s', 'otz' ), $field );
					}
					break;
				case 'save_failed':
					$error = __( '儲存失敗，請稍後再試', 'otz' );
					break;
			}
			if ( $error ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
			}
		}
	}
}
