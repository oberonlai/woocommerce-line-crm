<?php

/**
 * Frontend Chat Asset Manager
 *
 * Manages asset loading for the frontend mobile chat interface.
 * Reuses the complete asset loading strategy from the existing Chat.php
 * with whitelisting to prevent theme interference.
 *
 * @package    OrderChatz
 * @subpackage Admin
 * @since      1.0.0
 */

namespace OrderChatz\Admin;

/**
 * FrontendChatAssetManager class
 *
 * Handles asset enqueueing and removal for frontend chat interface.
 * Implements complete reuse of existing Chat.php asset loading patterns.
 */
class FrontendChatAssetManager {

	/**
	 * Whitelist of allowed assets for frontend chat
	 * Based on assets loaded by Chat.php
	 *
	 * @var array
	 */
	private const ALLOWED_STYLES = array(
		// Core WordPress/Admin styles
		'admin-menu',
		'common',
		'forms',
		'buttons',
		'dashboard',
		'wp-admin',
		'colors',
		'dashicons', // Dashicons font for frontend display

		// OrderChatz chat styles
		'orderchatz-admin-menu',
		'orderchatz-chat-base',
		'orderchatz-chat-messages',
		'orderchatz-customer-order-modal',
		'orderchatz-chat-friend-list',
		'orderchatz-chat-area',
		'orderchatz-chat-customer-info',
		'orderchatz-chat-components',
		'orderchatz-image-lightbox',
		'orderchatz-sticker-picker',
		'orderchatz-chat-responsive',
		'orderchatz-chat-interface',
		'orderchatz-cron',

		// Third-party dependencies
		'select2',

		// PWA specific styles
		'orderchatz-pwa-styles',
		'orderchatz-push-subscription-ui',

		// Frontend mobile specific styles (will be added in phase 3)
		'orderchatz-mobile-chat-shell',
		'orderchatz-mobile-content-adaptation',
		'orderchatz-mobile-responsive',
		'orderchatz-mobile-backend-override',
		'orderchatz-mobile-settings',
	);

	/**
	 * Whitelist of allowed scripts for frontend chat
	 * Based on scripts loaded by Chat.php
	 *
	 * @var array
	 */
	private const ALLOWED_SCRIPTS = array(
		// Core WordPress/jQuery
		'jquery',
		'jquery-core',
		'jquery-migrate',

		// Chat area components
		'orderchatz-chat-area-utils',
		'orderchatz-chat-area-ui',
		'orderchatz-chat-area-messages',
		'orderchatz-chat-area-menu',
		'orderchatz-chat-area-input',
		'orderchatz-chat-area-product',
		'orderchatz-chat-area-template',
		'orderchatz-chat-area-sticker',
		'orderchatz-chat-area-message-cron',
		'orderchatz-chat-area-core',

		// Third-party dependencies
		'select2',

		// Shared components
		'orderchatz-ui-helpers',

		// Customer info components
		'orderchatz-member-binding-manager',
		'orderchatz-customer-tags-manager',
		'orderchatz-customer-notes-manager',
		'orderchatz-tags-notes-manager',
		'orderchatz-order-manager',
		'orderchatz-customer-info-core',

		// Main components
		'orderchatz-friend-list',
		'orderchatz-chat-area',
		'orderchatz-customer-info',
		'orderchatz-responsive-handler',
		'orderchatz-panel-resizer',

		// Core systems
		'orderchatz-polling-manager',
		'orderchatz-components-loader',
		'orderchatz-chat-interface',

		// PWA specific scripts
		'orderchatz-pwa-manager',
		'orderchatz-pwa-install-manager',
		'orderchatz-push-manager',
		'orderchatz-push-subscription-ui',
		'orderchatz-version-manager',
		'orderchatz-pwa-integration',

		// Frontend mobile specific scripts (will be added in phase 4)
		'orderchatz-viewport-manager',
		'orderchatz-mobile-tab-navigation',
		'orderchatz-mobile-component-integrator',
		'orderchatz-mobile-settings',
	);

	/**
	 * Initialize asset manager
	 *
	 * @return void
	 */
	public function init(): void {
		// Add hooks to check for frontend chat request later in the WordPress lifecycle
		add_action( 'template_redirect', array( $this, 'maybe_initialize_frontend_assets' ) );
	}

	/**
	 * Check if we should initialize frontend assets and do so if needed
	 *
	 * @return void
	 */
	public function maybe_initialize_frontend_assets(): void {
		// Only initialize on frontend chat page
		if ( ! $this->is_frontend_chat_request() ) {
			return;
		}

		add_action( 'otz_frontend_chat_enqueue_assets', array( $this, 'enqueue_chat_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'remove_non_whitelisted_assets' ), 999 );
		add_action( 'wp_print_styles', array( $this, 'remove_non_whitelisted_styles' ), 999 );
		add_action( 'wp_print_scripts', array( $this, 'remove_non_whitelisted_scripts' ), 999 );
	}

	/**
	 * Enqueue all chat assets using exact same pattern as Chat.php
	 * Complete reuse of existing enqueueAssets() method
	 *
	 * @return void
	 */
	public function enqueue_chat_assets(): void {
		static $assets_enqueued = false;
		if ( $assets_enqueued ) {
			return;
		}
		$assets_enqueued = true;

		wp_enqueue_style( 'select2' );

		// Enqueue Dashicons for frontend (WordPress doesn't load it by default on frontend)
		wp_enqueue_style( 'dashicons' );

		// Enqueue admin menu styles first (dependency for chat styles)
		wp_enqueue_style(
			'orderchatz-admin-menu',
			OTZ_PLUGIN_URL . 'assets/css/admin-menu.css',
			array(),
			'1.0.0'
		);

		// === CSS Assets - Exact copy from Chat.php ===
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
			'1.0.21'
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
			'1.0.20'
		);

		wp_enqueue_style(
			'orderchatz-chat-customer-info',
			OTZ_PLUGIN_URL . 'assets/css/chat/customer-info.css',
			array( 'orderchatz-chat-base' ),
			'1.0.18'
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

		// 載入聊天介面主樣式
		wp_enqueue_style(
			'orderchatz-chat-interface',
			OTZ_PLUGIN_URL . 'assets/css/chat/chat-interface.css',
			array( 'orderchatz-chat-base' ),
			'1.0.02'
		);

		wp_enqueue_style(
			'orderchatz-sticker-picker',
			OTZ_PLUGIN_URL . 'assets/css/chat/sticker-picker.css',
			array( 'orderchatz-chat-base' ),
			'1.0.0'
		);

		// 載入排程訊息樣式
		wp_enqueue_style(
			'orderchatz-cron',
			OTZ_PLUGIN_URL . 'assets/css/chat/cron.css',
			array( 'orderchatz-chat-base' ),
			'1.0.0'
		);

		// 載入聊天介面響應式樣式
		wp_enqueue_style(
			'orderchatz-chat-responsive',
			OTZ_PLUGIN_URL . 'assets/css/chat/chat-responsive.css',
			array( 'orderchatz-chat-interface' ),
			'1.0.02'
		);

		// === Frontend Mobile Specific Styles ===

		// Mobile chat shell (core mobile app layout)
		wp_enqueue_style(
			'orderchatz-mobile-chat-shell',
			OTZ_PLUGIN_URL . 'assets/css/frontend/mobile-chat-shell.css',
			array( 'orderchatz-chat-base' ),
			'1.0.3'
		);

		// Mobile content adaptations
		wp_enqueue_style(
			'orderchatz-mobile-content-adaptation',
			OTZ_PLUGIN_URL . 'assets/css/frontend/mobile-content-adaptation.css',
			array( 'orderchatz-mobile-chat-shell' ),
			'1.0.21'
		);

		// Mobile responsive optimizations
		wp_enqueue_style(
			'orderchatz-mobile-responsive',
			OTZ_PLUGIN_URL . 'assets/css/frontend/mobile-responsive.css',
			array( 'orderchatz-mobile-content-adaptation' ),
			'1.0.2'
		);

		// Mobile backend override styles (frontend-only)
		wp_enqueue_style(
			'orderchatz-mobile-backend-override',
			OTZ_PLUGIN_URL . 'assets/css/frontend/mobile-backend-override.css',
			array( 'orderchatz-chat-interface', 'orderchatz-chat-responsive', 'orderchatz-mobile-responsive' ),
			'1.0.8'
		);

		// Mobile settings styles
		wp_enqueue_style(
			'orderchatz-mobile-settings',
			OTZ_PLUGIN_URL . 'assets/css/frontend/mobile-settings.css',
			array( 'orderchatz-mobile-backend-override' ),
			'1.0.2'
		);

		// === JavaScript Assets - Exact copy from Chat.php ===

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
			'1.0.13',
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
			'1.0.03',
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
			'1.1.1',
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
			'1.0.22',
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
			array( 'jquery', 'orderchatz-friend-list', 'orderchatz-chat-area', 'orderchatz-customer-info', 'orderchatz-responsive-handler', 'orderchatz-polling-manager', 'orderchatz-panel-resizer', 'orderchatz-chat-area-core', 'orderchatz-customer-info-core' ),
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

		// === Frontend Mobile Specific Scripts ===

		// Viewport manager (load early for iOS Safari fix)
		wp_enqueue_script(
			'orderchatz-viewport-manager',
			OTZ_PLUGIN_URL . 'assets/js/frontend/viewport-manager.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Mobile tab navigation controller
		wp_enqueue_script(
			'orderchatz-mobile-tab-navigation',
			OTZ_PLUGIN_URL . 'assets/js/frontend/mobile-tab-navigation.js',
			array( 'jquery', 'orderchatz-chat-interface', 'orderchatz-viewport-manager' ),
			'1.0.9',
			true
		);

		// Mobile component integrator
		wp_enqueue_script(
			'orderchatz-mobile-component-integrator',
			OTZ_PLUGIN_URL . 'assets/js/frontend/mobile-component-integrator.js',
			array( 'jquery', 'orderchatz-mobile-tab-navigation', 'orderchatz-components-loader' ),
			'1.0.2',
			true
		);

		// Mobile settings controller
		wp_enqueue_script(
			'orderchatz-mobile-settings',
			OTZ_PLUGIN_URL . 'assets/js/frontend/mobile-settings.js',
			array( 'jquery', 'orderchatz-mobile-tab-navigation' ),
			'1.0.1',
			true
		);

		// === PWA 功能腳本 ===

		// PWA 主管理器 (Service Worker 註冊等核心功能)
		wp_enqueue_script(
			'orderchatz-pwa-manager',
			OTZ_PLUGIN_URL . 'assets/js/pwa/pwa-manager.js',
			array( 'jquery' ),
			'1.0.02',
			true
		);

		// PWA 推播管理器
		wp_enqueue_script(
			'orderchatz-push-manager',
			OTZ_PLUGIN_URL . 'assets/js/pwa/push-manager.js',
			array( 'jquery', 'orderchatz-pwa-manager' ),
			'1.0.04',
			true
		);

		// PWA 推播訂閱 UI 管理器
		wp_enqueue_script(
			'orderchatz-push-subscription-ui',
			OTZ_PLUGIN_URL . 'assets/js/pwa/push-subscription-ui.js',
			array( 'jquery', 'orderchatz-push-manager' ),
			'1.0.6',
			true
		);

		// PWA 安裝提示管理器
		wp_enqueue_script(
			'orderchatz-pwa-install-manager',
			OTZ_PLUGIN_URL . 'assets/js/pwa/pwa-install-manager.js',
			array( 'jquery', 'orderchatz-pwa-manager' ),
			'1.0.2',
			true
		);

		// === PWA 功能樣式 ===

		// PWA 專用樣式
		wp_enqueue_style(
			'orderchatz-pwa-styles',
			OTZ_PLUGIN_URL . 'assets/css/pwa/pwa-styles.css',
			array( 'orderchatz-chat-base' ),
			'1.0.0'
		);

		// PWA 推播訂閱 UI 樣式
		wp_enqueue_style(
			'orderchatz-push-subscription-ui',
			OTZ_PLUGIN_URL . 'assets/css/pwa/push-subscription-ui.css',
			array( 'orderchatz-pwa-styles' ),
			'1.0.1'
		);

		// === Localization - Exact copy from Chat.php ===

		// 本地化字串，提供多語言支援
		wp_localize_script(
			'orderchatz-chat-interface',
			'otzChatL10n',
			array(
				'search_placeholder' => __( '搜尋好友...', 'otz' ),
				'send_message'       => __( '發送', 'otz' ),
				'type_message'       => __( '輸入訊息...', 'otz' ),
				'no_messages'        => __( '尚無對話記錄', 'otz' ),
				'select_friend'      => __( '請選擇一位好友開始聊天', 'otz' ),
				'loading'            => __( '載入中...', 'otz' ),
				'error_loading'      => __( '載入失敗，請重新整理頁面', 'otz' ),
				'message_too_long'   => __( '訊息內容過長，請縮短後再試', 'otz' ),
				'network_error'      => __( '網路連線發生問題，請稍後再試', 'otz' ),
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
							'size' => 32,
						)
					),
				),
				'user_avatars'       => $this->get_user_avatars_data(),
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

		// PWA 相關配置本地化 (提供給 PWA Manager)
		wp_localize_script(
			'orderchatz-pwa-manager',
			'otzPWAConfig',
			array(
				'vapid_public_key'     => get_option( 'otz_vapid_public_key', '' ),
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'push_nonce'           => wp_create_nonce( 'otz_push_subscription' ),
				'service_worker_url'   => OTZ_PLUGIN_URL . 'assets/js/pwa/sw.js?ver=1.1.04',
				'manifest_url'         => rest_url( 'orderchatz/v1/manifest' ),
				'current_user_id'      => get_current_user_id(),
				'current_line_user_id' => get_user_meta( get_current_user_id(), '_otz_line_user_id', true ) ?: null,
				'plugin_url'           => OTZ_PLUGIN_URL,
				'icon_url'             => OTZ_PLUGIN_URL . 'assets/img/otz-icon-192.png',
			)
		);

		// 手機設定面板配置本地化 (提供給 Mobile Settings)
		wp_localize_script(
			'orderchatz-mobile-settings',
			'otzPushConfig',
			array(
				'vapidPublicKey' => get_option( 'otz_vapid_public_key', '' ),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'otz_push_subscription' ),
			)
		);
	}

	/**
	 * Remove non-whitelisted assets to prevent theme interference
	 *
	 * @return void
	 */
	public function remove_non_whitelisted_assets(): void {
		global $wp_styles, $wp_scripts;

		if ( ! $wp_styles instanceof \WP_Styles || ! $wp_scripts instanceof \WP_Scripts ) {
			return;
		}

		// Remove non-whitelisted styles
		foreach ( $wp_styles->registered as $handle => $style ) {
			if ( ! in_array( $handle, self::ALLOWED_STYLES, true ) ) {
				wp_dequeue_style( $handle );
			}
		}

		// Remove non-whitelisted scripts
		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( ! in_array( $handle, self::ALLOWED_SCRIPTS, true ) ) {
				wp_dequeue_script( $handle );
			}
		}
	}

	/**
	 * Remove non-whitelisted styles at print time
	 *
	 * @return void
	 */
	public function remove_non_whitelisted_styles(): void {
		global $wp_styles;

		if ( ! $wp_styles instanceof \WP_Styles ) {
			return;
		}

		foreach ( $wp_styles->queue as $key => $handle ) {
			if ( ! in_array( $handle, self::ALLOWED_STYLES, true ) ) {
				unset( $wp_styles->queue[ $key ] );
			}
		}
	}

	/**
	 * Remove non-whitelisted scripts at print time
	 *
	 * @return void
	 */
	public function remove_non_whitelisted_scripts(): void {
		global $wp_scripts;

		if ( ! $wp_scripts instanceof \WP_Scripts ) {
			return;
		}

		foreach ( $wp_scripts->queue as $key => $handle ) {
			if ( ! in_array( $handle, self::ALLOWED_SCRIPTS, true ) ) {
				unset( $wp_scripts->queue[ $key ] );
			}
		}
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
					'size' => 32,
				)
			);
		}

		return $avatars;
	}

	/**
	 * Check if current request is for frontend chat
	 *
	 * @return bool
	 */
	private function is_frontend_chat_request(): bool {
		// Check if WordPress query vars are available
		if ( ! function_exists( 'get_query_var' ) ) {
			return false;
		}

		// Try to get the query var safely
		try {
			return (bool) get_query_var( 'is_order_chatz' );
		} catch ( \Error $e ) {
			// Fallback to manual URL checking if query vars aren't ready
			return $this->check_url_for_chat_route();
		}
	}

	/**
	 * Fallback method to check URL for chat route
	 *
	 * @return bool
	 */
	private function check_url_for_chat_route(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$site_url    = site_url();
		$parsed_url  = wp_parse_url( $site_url );
		$site_path   = $parsed_url['path'] ?? '';

		// Remove site path from request URI if it exists
		if ( $site_path && strpos( $request_uri, $site_path ) === 0 ) {
			$request_uri = substr( $request_uri, strlen( $site_path ) );
		}

		// Clean up URI (remove leading/trailing slashes, query parameters)
		$request_uri = trim( $request_uri, '/' );
		$request_uri = strtok( $request_uri, '?' ); // Remove query parameters

		return $request_uri === 'order-chatz';
	}
}
