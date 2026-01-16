<?php
/**
 * OrderChatz 聊天頁面渲染器
 *
 * 處理聊天頁面的內容渲染，實作三欄式聊天介面
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Services\UnreadCountService;

/**
 * 聊天頁面渲染器類別
 *
 * 渲染 LINE 聊天介面，包含好友列表、對話區域、客戶資訊三欄式布局
 */
class Chat extends PageRenderer {
	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( '聊天', 'otz' ),
			'chat',
			false // 聊天頁面沒有頁籤導航
		);
	}

	/**
	 * 渲染聊天頁面內容
	 * 覆寫父類別方法，載入聊天介面資源並渲染聊天介面
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		// 清除未讀計數 badge（用戶進入聊天頁面時）
		$this->clearUnreadBadge();

		// 載入聊天介面專用資源
		$this->enqueueAssets();

		// 渲染聊天介面
		$this->renderChatInterface();
	}

	/**
	 * 載入聊天介面相關的 CSS 和 JavaScript 資源
	 *
	 * @return void
	 */
	protected function enqueueAssets(): void {
		static $assets_enqueued = false;
		if ( $assets_enqueued ) {
			return;
		}
		$assets_enqueued = true;

		wp_enqueue_style(
			'orderchatz-chat-base',
			OTZ_PLUGIN_URL . 'assets/css/chat/base.css',
			array( 'orderchatz-admin-menu' ),
			'1.0.22'
		);

		wp_enqueue_style(
			'orderchatz-chat-messages',
			OTZ_PLUGIN_URL . 'assets/css/chat/messages.css',
			array( 'orderchatz-chat-base' ),
			'1.0.20'
		);

		wp_enqueue_style(
			'orderchatz-customer-order-modal',
			OTZ_PLUGIN_URL . 'assets/css/chat/customer-order-modal.css',
			array( 'orderchatz-chat-base' ),
			'1.0.03'
		);

		wp_enqueue_style(
			'orderchatz-chat-friend-list',
			OTZ_PLUGIN_URL . 'assets/css/chat/friend-list.css',
			array( 'orderchatz-chat-base' ),
			'1.0.17'
		);

		wp_enqueue_style(
			'orderchatz-chat-area',
			OTZ_PLUGIN_URL . 'assets/css/chat/chat-area.css',
			array( 'orderchatz-chat-base', 'orderchatz-chat-messages' ),
			'1.0.21'
		);

		wp_enqueue_style(
			'orderchatz-chat-customer-info',
			OTZ_PLUGIN_URL . 'assets/css/chat/customer-info.css',
			array( 'orderchatz-chat-base' ),
			'1.0.19'
		);

		wp_enqueue_style(
			'orderchatz-chat-components',
			OTZ_PLUGIN_URL . 'assets/css/chat/components.css',
			array( 'orderchatz-chat-base' ),
			'1.0.17'
		);

		wp_enqueue_style(
			'orderchatz-image-lightbox',
			OTZ_PLUGIN_URL . 'assets/css/chat/image-lightbox.css',
			array( 'orderchatz-chat-base' ),
			'1.0.0'
		);

		wp_enqueue_style(
			'orderchatz-sticker-picker',
			OTZ_PLUGIN_URL . 'assets/css/chat/sticker-picker.css',
			array( 'orderchatz-chat-base' ),
			'1.0.0'
		);

		wp_enqueue_style(
			'orderchatz-message-cron',
			OTZ_PLUGIN_URL . 'assets/css/chat/cron.css',
			array( 'orderchatz-chat-base' ),
			'1.0.3'
		);

		// 載入聊天介面響應式樣式
		wp_enqueue_style(
			'orderchatz-chat-responsive',
			OTZ_PLUGIN_URL . 'assets/css/chat/chat-responsive.css',
			null,
			'1.0.02'
		);

		// 載入聊天區域模組 (按依賴順序載入)
		wp_enqueue_script(
			'orderchatz-chat-area-utils',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-utils.js',
			array( 'jquery' ),
			'1.0.06',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-ui',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-ui.js',
			array( 'jquery', 'orderchatz-chat-area-utils' ),
			'1.0.03',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-messages',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-messages.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui' ),
			'1.0.13',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-menu',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-menu.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui' ),
			'1.0.01',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-input',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-input.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui', 'orderchatz-chat-area-messages', 'orderchatz-chat-area-menu' ),
			'1.0.15',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-product',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-product.js',
			array( 'jquery', 'select2', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui', 'orderchatz-chat-area-input' ),
			'1.0.14',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-template',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-template.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui', 'orderchatz-chat-area-input' ),
			'1.0.05',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-sticker',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-sticker.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui' ),
			'1.0.0',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-message-cron',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-message-cron.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui' ),
			'1.1.0',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area-core',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area/chat-area-core.js',
			array( 'jquery', 'orderchatz-chat-area-utils', 'orderchatz-chat-area-ui', 'orderchatz-chat-area-messages', 'orderchatz-chat-area-menu', 'orderchatz-chat-area-input', 'orderchatz-chat-area-product', 'orderchatz-chat-area-template', 'orderchatz-chat-area-sticker', 'orderchatz-chat-area-message-cron' ),
			'1.0.03',
			true
		);

		// 載入共用模組
		wp_enqueue_script(
			'orderchatz-ui-helpers',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/shared/ui-helpers.js',
			array( 'jquery' ),
			'1.0.03',
			true
		);

		wp_enqueue_script(
			'orderchatz-member-binding-manager',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info/member-binding-manager.js',
			array( 'jquery', 'orderchatz-ui-helpers' ),
			'1.0.07',
			true
		);

		// 載入獨立的標籤和備註管理器
		wp_enqueue_script(
			'orderchatz-customer-tags-manager',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info/customer-tags-manager.js',
			array( 'jquery', 'orderchatz-ui-helpers' ),
			'1.0.24',
			true
		);

		wp_enqueue_script(
			'orderchatz-customer-notes-manager',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info/customer-notes-manager.js',
			array( 'jquery', 'orderchatz-ui-helpers', 'select2' ),
			'1.0.34',
			true
		);

		// 載入向後相容性協調器
		wp_enqueue_script(
			'orderchatz-tags-notes-manager',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info/tags-notes-manager.js',
			array( 'jquery', 'orderchatz-ui-helpers', 'orderchatz-customer-tags-manager', 'orderchatz-customer-notes-manager' ),
			'1.0.22',
			true
		);

		wp_enqueue_script(
			'orderchatz-order-manager',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info/order-manager.js',
			array( 'jquery', 'orderchatz-ui-helpers' ),
			'1.0.04',
			true
		);

		wp_enqueue_script(
			'orderchatz-customer-info-core',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info/customer-info-core.js',
			array( 'jquery', 'orderchatz-ui-helpers', 'orderchatz-member-binding-manager', 'orderchatz-tags-notes-manager', 'orderchatz-order-manager' ),
			'1.0.26',
			true
		);

		// 載入其他元件檔案
		wp_enqueue_script(
			'orderchatz-friend-list',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/friend-list.js',
			array( 'jquery' ),
			'1.0.27',
			true
		);

		wp_enqueue_script(
			'orderchatz-chat-area',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/chat-area.js',
			array( 'jquery', 'orderchatz-chat-area-core' ),
			'1.0.26',
			true
		);

		wp_enqueue_script(
			'orderchatz-customer-info',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/customer-info.js',
			array( 'jquery', 'orderchatz-customer-info-core' ),
			'1.0.21',
			true
		);

		wp_enqueue_script(
			'orderchatz-responsive-handler',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/responsive-handler.js',
			array( 'jquery' ),
			'1.0.01',
			true
		);

		// 載入面板拖曳調整功能
		wp_enqueue_script(
			'orderchatz-panel-resizer',
			OTZ_PLUGIN_URL . 'assets/js/chat/components/panel-resizer.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// 載入輪詢管理器
		wp_enqueue_script(
			'orderchatz-polling-manager',
			OTZ_PLUGIN_URL . 'assets/js/chat/polling-manager.js',
			array( 'jquery' ),
			'1.0.05',
			true
		);

		// 載入元件載入器
		wp_enqueue_script(
			'orderchatz-components-loader',
			OTZ_PLUGIN_URL . 'assets/js/chat/chat-components-loader.js',
			array( 'jquery', 'orderchatz-friend-list', 'orderchatz-chat-area', 'orderchatz-customer-info', 'orderchatz-responsive-handler', 'orderchatz-polling-manager', 'orderchatz-panel-resizer', 'orderchatz-chat-area-core', 'orderchatz-customer-info-core', 'orderchatz-chat-area-sticker' ),
			'1.0.10',
			true
		);

		// 載入聊天介面主控制器
		wp_enqueue_script(
			'orderchatz-chat-interface',
			OTZ_PLUGIN_URL . 'assets/js/chat/chat-interface.js',
			array( 'jquery', 'orderchatz-components-loader' ),
			'1.0.26',
			true
		);

		// 本地化字串，提供多語言支援
		wp_localize_script(
			'orderchatz-chat-interface',
			'otzChatL10n',
			array(
				'search_placeholder'      => __( '搜尋好友...', 'otz' ),
				'send_message'            => __( '發送', 'otz' ),
				'type_message'            => __( '輸入訊息...', 'otz' ),
				'no_messages'             => __( '尚無對話記錄', 'otz' ),
				'select_friend'           => __( '請選擇一位好友開始聊天', 'otz' ),
				'loading'                 => __( '載入中...', 'otz' ),
				'error_loading'           => __( '載入失敗，請重新整理頁面', 'otz' ),
				'message_too_long'        => __( '訊息內容過長，請縮短後再試', 'otz' ),
				'network_error'           => __( '網路連線發生問題，請稍後再試', 'otz' ),
				'bot_mode_active'         => __( '目前為 AI 自動回應模式', 'otz' ),
				'switch_to_manual'        => __( '切換為手動回覆', 'otz' ),
			)
		);

		// 聊天配置，包含 AJAX URL、nonce 和使用者資訊
		wp_localize_script(
			'orderchatz-chat-interface',
			'otzChatConfig',
			array(
				'nonce'              => wp_create_nonce( 'orderchatz_chat_action' ),
				'binding_nonce'      => wp_create_nonce( 'otz_update_user_binding' ),
				'search_nonce'       => wp_create_nonce( 'otz_search_users' ),
				'notes_nonce'        => wp_create_nonce( 'orderchatz_chat_action' ),
				'tags_nonce'         => wp_create_nonce( 'orderchatz_chat_action' ),
				'message_cron_nonce' => wp_create_nonce( 'otz_message_cron_action' ),
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'max_message_length' => 500,
				'auto_scroll_delay'  => 300,
				'current_user'       => array(
					'id'           => get_current_user_id(),
					'name'         => wp_get_current_user()->display_name,
					'display_name' => wp_get_current_user()->display_name,
					'avatar'       => get_avatar_url(
						get_current_user_id(),
						array(
							'size'    => 32,
							'default' => 'mystery',
						)
					),
				),
				'user_avatars'       => $this->get_user_avatars_data(),
				'note_categories'    => get_option( 'otz_note_categories', array() ),
			)
		);

		// 輪詢管理器配置 (與 otzChatConfig 相同，保持相容性)
		wp_localize_script(
			'orderchatz-polling-manager',
			'otzChat',
			array(
				'nonce'              => wp_create_nonce( 'orderchatz_chat_action' ),
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'max_message_length' => 500,
				'auto_scroll_delay'  => 300,
				'current_user'       => array(
					'id'           => get_current_user_id(),
					'name'         => wp_get_current_user()->display_name,
					'display_name' => wp_get_current_user()->display_name,
					'avatar'       => get_avatar_url(
						get_current_user_id(),
						array(
							'size'    => 32,
							'default' => 'mystery',
						)
					),
				),
				'user_avatars'       => $this->get_user_avatars_data(),
			)
		);
	}

	/**
	 * 渲染聊天介面
	 * 載入聊天介面模板檔案
	 *
	 * @return void
	 */
	private function renderChatInterface(): void {
		// 防止重複渲染
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		$template_path = OTZ_PLUGIN_DIR . 'views/admin/chat-interface.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// 如果模板檔案不存在，顯示錯誤訊息
			echo '<div class="notice notice-error">';
			echo '<p>' . __( '聊天介面模板檔案不存在，請檢查外掛安裝是否完整。', 'otz' ) . '</p>';
			echo '</div>';

			// 提供降級的簡單介面
			$this->renderFallbackInterface();
		}
	}

	/**
	 * 渲染降級聊天介面
	 * 當主模板檔案不存在時使用的備用介面
	 *
	 * @return void
	 */
	private function renderFallbackInterface(): void {
		echo '<div class="orderchatz-chat-fallback">';
		echo '<div class="notice notice-info inline">';
		echo '<p>' . __( '聊天介面正在建置中，模板檔案將會在下個步驟中建立。', 'otz' ) . '</p>';
		echo '</div>';

		// 顯示基本的三欄式佔位符
		echo '<div class="chat-container-placeholder" style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 1rem; height: 600px; margin-top: 20px;">';

		echo '<div style="border: 2px dashed #ccd0d4; border-radius: 4px; padding: 20px; text-align: center; display: flex; align-items: center; justify-content: center;">';
		echo '<p style="color: #646970; margin: 0;">' . __( '好友列表', 'otz' ) . '</p>';
		echo '</div>';

		echo '<div style="border: 2px dashed #ccd0d4; border-radius: 4px; padding: 20px; text-align: center; display: flex; align-items: center; justify-content: center;">';
		echo '<p style="color: #646970; margin: 0;">' . __( '聊天區域', 'otz' ) . '</p>';
		echo '</div>';

		echo '<div style="border: 2px dashed #ccd0d4; border-radius: 4px; padding: 20px; text-align: center; display: flex; align-items: center; justify-content: center;">';
		echo '<p style="color: #646970; margin: 0;">' . __( '客戶資訊', 'otz' ) . '</p>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Get user avatars data for JavaScript
	 *
	 * @return array
	 */
	private function get_user_avatars_data(): array {
		$avatars = array();

		// 取得所有管理員使用者的頭像資訊
		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'editor', 'author' ),
				'fields'   => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		foreach ( $users as $user ) {
			$avatars[ $user->display_name ] = get_avatar_url(
				$user->ID,
				array(
					'size'    => 32,
					'default' => 'identicon',
				)
			);
		}

		return $avatars;
	}

	/**
	 * 清除未讀計數 badge
	 * 當用戶進入聊天頁面時清除未讀計數快取，讓選單 badge 消失
	 *
	 * @return void
	 */
	private function clearUnreadBadge(): void {
		try {
			$unreadCountService = new UnreadCountService();
			$unreadCountService->clearCache();

			// 記錄清除動作（用於除錯）
			error_log( '[OrderChatz] Badge cleared when entering chat page' );

		} catch ( \Exception $e ) {
			// 如果清除失敗，記錄錯誤但不影響頁面載入
			error_log( '[OrderChatz] Failed to clear badge on chat page entry: ' . $e->getMessage() );
		}
	}
}
