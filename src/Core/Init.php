<?php

/**
 * OrderChatz Plugin Initialization Manager
 *
 * This class handles the centralized initialization of all plugin components,
 * managing dependencies, service registration, and WordPress hook integration.
 * It provides a clean separation between the main plugin file and implementation details.
 *
 * @package    OrderChatz
 * @subpackage Core
 * @since      1.0.4
 */

namespace OrderChatz\Core;

use OrderChatz\Database\Installer;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Util\Logger;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\API\RestAPIManager;
use OrderChatz\API\PwaManifestHandler;
use OrderChatz\Admin\SettingsPage;
use OrderChatz\Admin\Menu\AdminMenuManager;
use OrderChatz\Admin\IframeHandler;
use OrderChatz\Ajax\ChatAjaxHandler;
use OrderChatz\Ajax\BroadcastAjaxHandler;
use OrderChatz\Ajax\MemberSyncHandler;
use OrderChatz\Ajax\LineFriendsSyncHandler;
use OrderChatz\Ajax\LineContentProxyHandler;
use OrderChatz\Admin\Notice\QuotaWarningNotice;
use OrderChatz\Ajax\Statistics\StatisticsHandler;
use OrderChatz\Ajax\Product\ProductHandler;
use OrderChatz\Ajax\PushNotificationHandler;
use OrderChatz\Ajax\Message\Template;
use OrderChatz\Ajax\Message\MessageCron;
use OrderChatz\Ajax\Message\MessageCronHandler;
use OrderChatz\Core\FrontendChatRouter;
use OrderChatz\Admin\FrontendChatAssetManager;

/**
 * Init class
 *
 * Manages plugin initialization, component bootstrapping, and service container.
 * Implements singleton pattern to ensure single initialization across the plugin lifecycle.
 */
class Init {

	/**
	 * Singleton instance
	 *
	 * @var Init|null
	 */
	private static ?Init $instance = null;

	/**
	 * Component instances container
	 *
	 * @var array<string, object>
	 */
	private array $components = array();

	/**
	 * Plugin initialization status
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * WordPress database instance
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->logger = $this->create_logger();
	}

	/**
	 * Get singleton instance
	 *
	 * @return Init
	 */
	public static function get_instance(): Init {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 *
	 * This method should be called during plugin activation and on plugins_loaded.
	 * It handles database installation, component initialization, and hook registration.
	 *
	 * @param bool $force_init Whether to force re-initialization
	 *
	 * @return void
	 */
	public function initialize( bool $force_init = false ): void {
		if ( $this->initialized && ! $force_init ) {
			return;
		}

		try {
		// Initialize core components
		$this->init_core_components();

			// Handle database installation and upgrades
			$this->handle_database_operations();

			// Initialize API components
			$this->init_api_components();

			// Initialize frontend components
			$this->init_frontend_components();

			// Initialize admin components (only in admin context)
			if ( is_admin() ) {
				$this->init_admin_components();
			}

			// Register WordPress hooks
			$this->register_wordpress_hooks();

			$this->initialized = true;

		} catch ( \Exception $e ) {
			$this->handle_initialization_error( $e );
		}
	}

	/**
	 * Get a component instance by name
	 *
	 * @param string $component_name Component identifier
	 *
	 * @return object|null Component instance or null if not found
	 */
	public function get_component( string $component_name ): ?object {
		return $this->components[ $component_name ] ?? null;
	}

	/**
	 * Get all registered components
	 *
	 * @return array<string, object>
	 */
	public function get_all_components(): array {
		return $this->components;
	}

	/**
	 * Check if plugin is initialized
	 *
	 * @return bool
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}

	/**
	 * Get main webhook URL for LINE registration
	 *
	 * @return string Webhook URL
	 */
	public function get_webhook_url(): string {
		$rest_api_manager = $this->get_component( 'rest_api_manager' );

		if ( $rest_api_manager instanceof RestAPIManager ) {
			return $rest_api_manager->get_webhook_url();
		}

		// Fallback URL construction
		return rest_url( 'otz/v1/webhook' );
	}

	/**
	 * Get plugin configuration status
	 *
	 * @return array Configuration status information
	 */
	public function get_plugin_status(): array {
		$rest_api_manager = $this->get_component( 'rest_api_manager' );
		$error_handler    = $this->get_component( 'error_handler' );

		$status = array(
			'initialized'       => $this->initialized,
			'components_loaded' => count( $this->components ),
			'webhook_url'       => $this->get_webhook_url(),
			'database_version'  => $this->get_database_version(),
			'wp_version'        => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
		);

		// Add REST API status if available
		if ( $rest_api_manager instanceof RestAPIManager ) {
			$status['rest_api'] = $rest_api_manager->get_configuration_status();
		}

		// Add recent errors if available
		if ( $error_handler instanceof ErrorHandler ) {
			$status['recent_errors_count'] = count( $error_handler->get_recent_system_errors() );
		}

		return $status;
	}



	/**
	 * Initialize core components
	 *
	 * @return void
	 */
	private function init_core_components(): void {
		// Error Handler (always first)
		$this->components['error_handler'] = new ErrorHandler( $this->wpdb, $this->logger );

		// Security Validator
		$this->components['security_validator'] = new SecurityValidator(
			$this->wpdb,
			$this->components['error_handler']
		);

		// Broadcast Push Handler（排程推播處理器）- 需要在非 admin 環境也能執行.
		$this->components['broadcast_push_handler'] = new \OrderChatz\Services\Broadcast\SavePushHandler();
		$this->components['broadcast_push_handler']->init_scheduler_hooks();

	}

	/**
	 * Handle database installation and upgrades
	 *
	 * @return void
	 */
	private function handle_database_operations(): void {
		try {
			// Check if database upgrade is needed (only in admin context for performance)
			if ( is_admin() ) {
				$installer       = Installer::get_instance();
				$current_version = $installer->get_current_version();
				$target_version  = $installer->get_target_version();

				if ( version_compare( $current_version, $target_version, '<' ) ) {

					Installer::init();

				}
			}
		} catch ( \Exception $e ) {
			Logger::error(
				'Database initialization failed during plugin init',
				array(
					'exception' => $e->getMessage(),
					'source'    => 'Init::handle_database_operations',
				)
			);

			// 保持與 ErrorHandler 的相容性
			if ( isset( $this->components['error_handler'] ) ) {
				$this->components['error_handler']->handle_error(
					'database_initialization_failed',
					'Database initialization failed during plugin init',
					array( 'exception' => $e->getMessage() ),
					'Init::handle_database_operations'
				);
			}
		}
	}

	/**
	 * Initialize API components
	 *
	 * @return void
	 */
	private function init_api_components(): void {
		try {
			// REST API Manager
			$this->components['rest_api_manager'] = new RestAPIManager(
				$this->wpdb,
				$this->logger,
				$this->components['error_handler'],
				$this->components['security_validator']
			);

			// PWA Manifest Handler
			PwaManifestHandler::init();

		} catch ( \Exception $e ) {
			Logger::error(
				'API components initialization failed',
				array(
					'exception' => $e->getMessage(),
					'source'    => 'Init::init_api_components',
				)
			);

			// 保持與 ErrorHandler 的相容性
			if ( isset( $this->components['error_handler'] ) ) {
				$this->components['error_handler']->handle_error(
					'api_components_initialization_failed',
					'API components initialization failed',
					array( 'exception' => $e->getMessage() ),
					'Init::init_api_components'
				);
			}
		}
	}

	/**
	 * Initialize frontend components
	 *
	 * @return void
	 */
	private function init_frontend_components(): void {
		try {
			// Frontend Chat Router
			$this->components['frontend_chat_router'] = new FrontendChatRouter();
			$this->components['frontend_chat_router']->init();

			// Frontend Chat Asset Manager
			$this->components['frontend_chat_asset_manager'] = new FrontendChatAssetManager();
			$this->components['frontend_chat_asset_manager']->init();

		} catch ( \Exception $e ) {
			Logger::error(
				'Frontend components initialization failed',
				array(
					'exception' => $e->getMessage(),
					'source'    => 'Init::init_frontend_components',
				)
			);

			if ( isset( $this->components['error_handler'] ) ) {
				$this->components['error_handler']->handle_error(
					'frontend_components_initialization_failed',
					'Frontend components initialization failed',
					array( 'exception' => $e->getMessage() ),
					'Init::init_frontend_components'
				);
			}
		}
	}

	/**
	 * Initialize admin components
	 *
	 * @return void
	 */
	private function init_admin_components(): void {
		try {

			// Admin Menu Manager
			$this->components['admin_menu_manager'] = new AdminMenuManager();
			$this->components['admin_menu_manager']->init();

			// 設定全域變數供 MenuRegistrar 使用
			global $orderChatzAdminMenuManager;
			$orderChatzAdminMenuManager = $this->components['admin_menu_manager'];

			// Iframe Handler - 處理 iframe 中的 WordPress 管理頁面顯示
			$this->components['iframe_handler'] = new IframeHandler();

			// Chat AJAX Handler
			$this->components['chat_ajax_handler'] = new ChatAjaxHandler();

			// Broadcast AJAX Handler
			$this->components['broadcast_ajax_handler'] = new BroadcastAjaxHandler();

			// Member Sync AJAX Handler
			$this->components['member_sync_handler']       = new MemberSyncHandler();
			$this->components['line_friends_sync_handler'] = new LineFriendsSyncHandler();

			// LINE Content Proxy Handler
			$this->components['line_content_proxy_handler'] = new LineContentProxyHandler();
			$this->components['line_content_proxy_handler']->init();

			// 配額警告通知
			$this->components['quota_warning_notice'] = new QuotaWarningNotice();

			// 統計 AJAX 處理器
			$this->components['statistics_handler'] = new StatisticsHandler();

			// 商品 AJAX 處理器
			$this->components['product_handler'] = new ProductHandler();

			// PWA 推播通知 AJAX 處理器
			$this->components['push_notification_handler'] = new PushNotificationHandler();
			$this->components['push_notification_handler']->init();

			// Template AJAX Handler
			$this->components['template_handler'] = new Template();

			// Message Cron AJAX Handler
			$this->components['message_cron_handler'] = new MessageCron();

			// Message Cron Scheduler Handler (處理實際排程發送)
			$this->components['message_cron_scheduler_handler'] = new MessageCronHandler();

			$this->components['customer_note_handler'] = new \OrderChatz\Ajax\Customer\Note();

			// LINE 訊息匯入 AJAX 處理器
			$this->components['line_message_import_handler'] = new \OrderChatz\Ajax\LineMessageImport();

		} catch ( \Exception $e ) {
			Logger::error(
				'Admin components initialization failed',
				array(
					'exception' => $e->getMessage(),
					'source'    => 'Init::init_admin_components',
				)
			);

			// 保持與 ErrorHandler 的相容性
			if ( isset( $this->components['error_handler'] ) ) {
				$this->components['error_handler']->handle_error(
					'admin_components_initialization_failed',
					'Admin components initialization failed',
					array( 'exception' => $e->getMessage() ),
					'Init::init_admin_components'
				);
			}
		}
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_wordpress_hooks(): void {
		// REST API initialization
		add_action( 'rest_api_init', array( $this, 'init_rest_api' ) );

		// Register Action Scheduler hooks
		add_action( 'otz_update_user_profile', array( 'OrderChatz\API\UserManager', 'handle_scheduled_profile_update' ) );

		// Admin initialization hooks (only if we have admin components)
		if ( isset( $this->components['settings_page'] ) ) {
			// Settings page hooks are handled by the SettingsPage class itself
		}

	}

	/**
	 * Initialize REST API endpoints
	 *
	 * This method is called by WordPress on the 'rest_api_init' hook
	 *
	 * @return void
	 */
	public function init_rest_api(): void {
		$rest_api_manager = $this->get_component( 'rest_api_manager' );

		if ( $rest_api_manager instanceof RestAPIManager ) {
			$rest_api_manager->register_routes();
		} else {
			Logger::error( 'REST API manager not available during route registration' );
		}
	}

	/**
	 * Create logger instance
	 *
	 * @return \WC_Logger|null
	 */
	private function create_logger(): ?\WC_Logger {
		if ( class_exists( 'WC_Logger' ) ) {
			return new \WC_Logger();
		}

		return null;
	}

	/**
	 * Get current database version
	 *
	 * @return string
	 */
	private function get_database_version(): string {
		try {
			$installer = Installer::get_instance();
			return $installer->get_current_version();
		} catch ( \Exception $e ) {
			return 'unknown';
		}
	}

	/**
	 * Handle initialization errors
	 *
	 * @param \Exception $e Exception instance
	 *
	 * @return void
	 */
	private function handle_initialization_error( \Exception $e ): void {
		$error_message = 'OrderChatz plugin initialization failed: ' . $e->getMessage();

		// 優先使用 Logger
		Logger::critical(
			'Plugin initialization process failed with exception',
			array(
				'exception'              => $e->getMessage(),
				'trace'                  => $e->getTraceAsString(),
				'initialized_components' => array_keys( $this->components ),
				'source'                 => 'Init::initialize',
			)
		);

		// 保持與 ErrorHandler 的相容性
		if ( isset( $this->components['error_handler'] ) ) {
			$this->components['error_handler']->handle_error(
				'plugin_initialization_failed',
				'Plugin initialization process failed with exception',
				array(
					'exception'              => $e->getMessage(),
					'trace'                  => $e->getTraceAsString(),
					'initialized_components' => array_keys( $this->components ),
				),
				'Init::initialize'
			);
		}

		// Set a transient to show admin notice
		if ( is_admin() ) {
			set_transient( 'orderchatz_init_error', $error_message, 300 );
		}
	}


	/**
	 * Plugin activation handler
	 *
	 * This method should be called during plugin activation
	 *
	 * @return void
	 */
	public static function activate_plugin(): void {
		$init = self::get_instance();

		try {
			// Force database installation during activation
			Installer::init();

			// Initialize VAPID keys for web push (lazy loading for existing installations)
			$vapid_manager = new \OrderChatz\Services\VapidKeyManager();
			$vapid_manager->get_keys(); // This will generate keys if they don't exist

			// Initialize components
			$init->initialize( true );

		} catch ( \Exception $e ) {
			$init->handle_initialization_error( $e );

			// Show activation error
			wp_die(
				'OrderChatz plugin activation failed: ' . esc_html( $e->getMessage() ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}
	}


	/**
	 * Plugin deactivation handler
	 *
	 * @return void
	 */
	public static function deactivate_plugin(): void {
		// Clear any transients
		delete_transient( 'orderchatz_init_error' );

		// Note: We don't clear database data during deactivation
		// Data should only be removed during uninstallation
	}

	/**
	 * Display admin notice for initialization errors
	 *
	 * @return void
	 */
	public static function display_init_error_notice(): void {
		$error_message = get_transient( 'orderchatz_init_error' );

		if ( $error_message && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error">';
			echo '<h3>OrderChatz Plugin Error</h3>';
			echo '<p>' . esc_html( $error_message ) . '</p>';
			echo '<p>Please check the error logs and contact support if the issue persists.</p>';
			echo '</div>';

			delete_transient( 'orderchatz_init_error' );
		}
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
