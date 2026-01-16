<?php
/**
 * OrderChatz Bot 頁面渲染器
 *
 * 處理 Bot 頁面的內容渲染，包含列表和 CRUD 功能
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.1.6
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Admin\Lists\BotListTable;
use OrderChatz\Database\Bot\Bot as BotDatabase;
use OrderChatz\Database\User;
use OrderChatz\Services\Bot\OpenAIClient;
use OrderChatz\Services\Bot\BotMatcher;

/**
 * Bot 頁面渲染器類別
 *
 * 渲染 Bot 管理介面，支援列表顯示和 CRUD 操作
 */
class Bot extends PageRenderer {

	/**
	 * Bot 資料庫操作類別
	 *
	 * @var BotDatabase
	 */
	private $bot;

	/**
	 * BotMatcher 服務類別
	 *
	 * @var BotMatcher
	 */
	private $bot_matcher;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->bot = new BotDatabase( $wpdb );

		// 初始化 BotMatcher (用於清除快取).
		$user              = new User( $wpdb );
		$this->bot_matcher = new BotMatcher( $wpdb, $this->bot, $user );

		parent::__construct(
			__( 'Bot', 'otz' ),
			'otz-bot',
			true
		);
	}

	/**
	 * 處理各種操作
	 *
	 * @return void
	 */
	public function handle_actions() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// 處理刪除.
		if ( 'delete' === $action && isset( $_GET['id'] ) ) {
			$this->handle_delete();
		}

		// 處理複製.
		if ( 'copy' === $action && isset( $_GET['id'] ) ) {
			$this->handle_copy();
		}

		// 處理表單儲存.
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['save_bot'] ) ) {
			$this->handle_save();
		}
	}

	/**
	 * 渲染 Bot
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		$this->handle_actions();
		$this->show_messages();

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

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
		$list_table = new BotListTable();
		$list_table->prepare_items();

		$template_path = OTZ_PLUGIN_DIR . 'views/admin/bot/list.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Template file not found.', 'otz' ) . '</p></div>';
		}
	}

	/**
	 * 渲染編輯表單
	 *
	 * @return void
	 */
	private function render_edit_form() {
		$bot_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$bot    = null;

		if ( $bot_id > 0 ) {
			$bot = $this->bot->get_bot( $bot_id );
			if ( ! $bot ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Bot not found.', 'otz' ) . '</p></div>';
				return;
			}
		}

		// 準備 Post Type 資料（排除 WooCommerce 相關）.
		$available_post_types = $this->get_available_post_types();

		// 載入編輯介面資源.
		$this->enqueue_assets();

		$template_path = OTZ_PLUGIN_DIR . 'views/admin/bot/edit.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Template file not found.', 'otz' ) . '</p></div>';
		}
	}

	/**
	 * 處理刪除操作
	 *
	 * @return void
	 */
	private function handle_delete() {
		$bot_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'delete_bot_' . $bot_id ) ) {
			$this->add_message( 'error', __( 'Security check failed.', 'otz' ) );
			return;
		}

		if ( $this->bot->delete_bot( $bot_id ) ) {
			$this->add_message( 'success', __( 'Bot deleted successfully.', 'otz' ) );

			// 清除 Bot 快取.
			$this->bot_matcher->clear_cache();

			wp_safe_redirect( admin_url( 'admin.php?page=otz-bot' ) );
			exit;
		} else {
			$this->add_message( 'error', __( 'Failed to delete bot.', 'otz' ) );
		}
	}

	/**
	 * 處理複製操作
	 *
	 * @return void
	 */
	private function handle_copy() {
		$bot_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'copy_bot_' . $bot_id ) ) {
			$this->add_message( 'error', __( 'Security check failed.', 'otz' ) );
			return;
		}

		$bot = $this->bot->get_bot( $bot_id );

		if ( ! $bot ) {
			$this->add_message( 'error', __( 'Bot not found.', 'otz' ) );
			return;
		}

		// 移除 ID 以建立新記錄.
		unset( $bot['id'] );
		unset( $bot['created_at'] );
		unset( $bot['updated_at'] );

		// 在名稱後加上 (Copy).
		/* translators: %s: original bot name */
		$bot['name'] = sprintf( __( '%s (Copy)', 'otz' ), $bot['name'] );

		$new_id = $this->bot->save_bot( $bot );

		if ( $new_id ) {
			$this->add_message( 'success', __( 'Bot copied successfully.', 'otz' ) );

			// 清除 Bot 快取.
			$this->bot_matcher->clear_cache();

			wp_safe_redirect( admin_url( 'admin.php?page=otz-bot&action=edit&id=' . $new_id ) );
			exit;
		} else {
			$this->add_message( 'error', __( 'Failed to copy bot.', 'otz' ) );
		}
	}

	/**
	 * 處理儲存操作
	 *
	 * @return void
	 */
	private function handle_save(): void {
		// Nonce 驗證.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_bot' ) ) {
			$this->add_message( 'error', __( 'Security check failed.', 'otz' ) );
			return;
		}

		// 準備資料.
		$bot_data = array();

		// Bot ID (編輯模式).
		if ( isset( $_POST['bot_id'] ) && ! empty( $_POST['bot_id'] ) ) {
			$bot_data['id'] = (int) $_POST['bot_id'];
		}

		// 名稱 (必填).
		if ( isset( $_POST['bot_name'] ) ) {
			$bot_data['name'] = sanitize_text_field( wp_unslash( $_POST['bot_name'] ) );
		}

		// 描述.
		if ( isset( $_POST['bot_description'] ) ) {
			$bot_data['description'] = sanitize_textarea_field( wp_unslash( $_POST['bot_description'] ) );
		}

		// 關鍵字 (必填).
		if ( isset( $_POST['bot_keywords'] ) && is_array( $_POST['bot_keywords'] ) ) {
			$bot_data['keywords'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['bot_keywords'] ) );
		}

		// Action Type (必填).
		if ( isset( $_POST['action_type'] ) ) {
			$bot_data['action_type'] = sanitize_text_field( wp_unslash( $_POST['action_type'] ) );
		}

		// AI 相關設定.
		if ( isset( $_POST['api_key'] ) ) {
			$submitted_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );

			// 檢查是否為遮蔽格式 (包含星號).
			if ( strpos( $submitted_key, '*' ) !== false && isset( $bot_data['id'] ) ) {
				// 如果提交的是遮蔽金鑰,從資料庫取得原始金鑰.
				$existing_bot = $this->bot->get_bot( $bot_data['id'] );
				if ( $existing_bot && ! empty( $existing_bot['api_key'] ) ) {
					$bot_data['api_key'] = $existing_bot['api_key'];
				}
			} else {
				// 提交的是完整金鑰,正常更新.
				$bot_data['api_key'] = $submitted_key;
			}
		}

		if ( isset( $_POST['model'] ) ) {
			$bot_data['model'] = sanitize_text_field( wp_unslash( $_POST['model'] ) );
		}

		if ( isset( $_POST['system_prompt'] ) ) {
			$bot_data['system_prompt'] = sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ) );
		}

		// Handoff Message.
		if ( isset( $_POST['handoff_message'] ) ) {
			$bot_data['handoff_message'] = sanitize_textarea_field( wp_unslash( $_POST['handoff_message'] ) );
		}

		// Function Tools.
		if ( isset( $_POST['function_tools'] ) && is_array( $_POST['function_tools'] ) ) {
			$function_tools = array();

			foreach ( $_POST['function_tools'] as $tool_key => $tool_value ) {
				// 處理 custom_post_type 的特殊格式.
				if ( 'custom_post_type' === $tool_key ) {
					// 檢查是否有選擇 Post Type.
					if ( isset( $_POST['custom_post_types'] ) && is_array( $_POST['custom_post_types'] ) ) {
						$selected_post_types                = array_map( 'sanitize_text_field', wp_unslash( $_POST['custom_post_types'] ) );
						$function_tools['custom_post_type'] = array(
							'enabled'    => true,
							'post_types' => $selected_post_types,
						);
					}
				} else {
					// 其他工具維持原有格式.
					$function_tools[ $tool_key ] = (bool) $tool_value;
				}
			}

			$bot_data['function_tools'] = $function_tools;
		}

		// Quick Replies.
		if ( isset( $_POST['quick_replies'] ) && is_array( $_POST['quick_replies'] ) ) {
			// 過濾空值.
			$quick_replies             = array_map( 'sanitize_text_field', wp_unslash( $_POST['quick_replies'] ) );
			$quick_replies             = array_filter( $quick_replies );
			$bot_data['quick_replies'] = array_values( $quick_replies );
		}

		// 優先序.
		if ( isset( $_POST['bot_priority'] ) ) {
			$bot_data['priority'] = (int) $_POST['bot_priority'];
		}

		// 狀態.
		if ( isset( $_POST['bot_status'] ) ) {
			$bot_data['status'] = sanitize_text_field( wp_unslash( $_POST['bot_status'] ) );
		}

		// 儲存到資料庫.
		$result = $this->bot->save_bot( $bot_data );

		if ( $result ) {
			$is_edit = isset( $bot_data['id'] );
			$message = $is_edit ? __( 'Bot updated successfully.', 'otz' ) : __( 'Bot created successfully.', 'otz' );
			$this->add_message( 'success', $message );

			// 清除 Bot 快取.
			$this->bot_matcher->clear_cache();

			// 重導向到編輯頁面.
			wp_safe_redirect( admin_url( 'admin.php?page=otz-bot&action=edit&id=' . $result ) );
			exit;
		} else {
			$this->add_message( 'error', __( 'Failed to save bot.', 'otz' ) );
		}
	}

	/**
	 * 顯示訊息
	 *
	 * @return void
	 */
	private function show_messages() {
		if ( empty( $this->messages ) ) {
			return;
		}

		foreach ( $this->messages as $message ) {
			$class = 'notice notice-' . esc_attr( $message['type'] );
			if ( 'success' === $message['type'] ) {
				$class .= ' is-dismissible';
			}
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $message['message'] ) . '</p></div>';
		}
	}

	/**
	 * 新增訊息
	 *
	 * @param string $type 訊息類型 (success, error, warning, info).
	 * @param string $message 訊息內容.
	 * @return void
	 */
	private function add_message( $type, $message ) {
		if ( ! isset( $this->messages ) ) {
			$this->messages = array();
		}

		$this->messages[] = array(
			'type'    => $type,
			'message' => $message,
		);
	}

	/**
	 * 載入樣式和腳本
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		// 載入列表樣式.
		wp_enqueue_style(
			'orderchatz-bot-list',
			OTZ_PLUGIN_URL . 'assets/css/bot/list.css',
			array(),
			OTZ_VERSION
		);

		// 編輯頁面額外載入樣式和腳本.
		if ( 'create' === $action || 'edit' === $action ) {
			// 載入 Select2（WordPress 內建）.
			wp_enqueue_style(
				'select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
				array(),
				'4.1.0'
			);
			wp_enqueue_script( 'select2' );

			// 載入編輯頁面樣式.
			wp_enqueue_style(
				'orderchatz-bot-edit',
				OTZ_PLUGIN_URL . 'assets/css/bot/edit.css',
				array(),
				'1.0.0'
			);

			// 載入編輯頁面腳本.
			wp_enqueue_script(
				'orderchatz-bot-edit',
				OTZ_PLUGIN_URL . 'assets/js/bot/edit.js',
				array( 'jquery', 'select2' ),
				'1.0.1',
				true
			);

			// 傳遞翻譯文字到 JavaScript.
			wp_localize_script(
				'orderchatz-bot-edit',
				'otzBotEdit',
				array(
					'requiredName'           => __( 'Bot name is required', 'otz' ),
					'requiredKeywords'       => __( 'At least one keyword is required', 'otz' ),
					'keywordExists'          => __( 'This keyword already exists', 'otz' ),
					'requiredHandoffMessage' => __( 'Handoff message is required', 'otz' ),
					'enterQuestion'          => __( 'Enter question...', 'otz' ),
					'confirmDelete'          => __( 'Are you sure you want to delete this?', 'otz' ),
					'selectPostTypes'        => __( 'Select post types...', 'otz' ),
					'requiredPostTypes'      => __( 'Please select at least one post type', 'otz' ),
					'nonce'                  => wp_create_nonce( 'otz_bot_edit' ),
				)
			);
		}
	}

	/**
	 * 取得可用的 Post Type 列表（排除 WooCommerce 相關）
	 *
	 * @return array Post Type 陣列，格式：[ ['value' => 'post', 'label' => '文章'], ... ]
	 */
	private function get_available_post_types(): array {
		// 取得所有公開的 Post Type.
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		// WooCommerce 相關的 Post Type（需排除）.
		$excluded_post_types = array(
			'product',
			'product_variation',
			'shop_order',
			'shop_order_refund',
			'shop_coupon',
			'shop_webhook',
			'attachment',
		);

		$available_post_types = array();

		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, $excluded_post_types, true ) ) {
				continue;
			}

			$available_post_types[] = array(
				'value' => $post_type->name,
				'label' => $post_type->label,
			);
		}

		return $available_post_types;
	}
}
