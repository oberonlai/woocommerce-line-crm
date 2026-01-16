<?php

/**
 * Frontend Chat Router
 *
 * Handles routing for the frontend mobile chat interface.
 * Intercepts the /order-chatz route and provides authentication,
 * authorization, and template loading for the mobile chat interface.
 *
 * @package    OrderChatz
 * @subpackage Core
 * @since      1.0.0
 */

namespace OrderChatz\Core;

use OrderChatz\Util\Logger;

/**
 * FrontendChatRouter class
 *
 * Manages frontend route handling and authentication for mobile chat interface.
 * Uses WordPress template_include filter to intercept custom routes.
 */
class FrontendChatRouter {

	/**
	 * Custom route path
	 *
	 * @var string
	 */
	private const ROUTE_PATH = 'order-chatz';

	/**
	 * Required capability for access
	 *
	 * @var string
	 */
	private const REQUIRED_CAPABILITY = 'manage_woocommerce';

	/**
	 * Initialize the router
	 *
	 * @return void
	 */
	public function init(): void {
		$this->add_rewrite_rules();

		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_route_request' ) );
		add_filter( 'template_include', array( $this, 'load_chat_template' ) );
	}

	/**
	 * Add rewrite rules for frontend chat
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^' . self::ROUTE_PATH . '/?$', 'index.php?is_order_chatz=1', 'top' );

		// Temporarily force flush rewrite rules for testing (remove after testing)
		if ( ! get_option( 'otz_rewrite_rules_flushed' ) ) {
			flush_rewrite_rules();
			add_option( 'otz_rewrite_rules_flushed', true );
		}
	}

	/**
	 * Mark rewrite rules for flushing
	 * Call this on plugin activation
	 *
	 * @return void
	 */
	public static function mark_flush_rewrite_rules(): void {
		add_option( 'otz_frontend_chat_flush_rewrite_rules', true );
	}

	/**
	 * Add custom query vars
	 *
	 * @param array $vars Existing query vars
	 * @return array Modified query vars
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'is_order_chatz';
		$vars[] = 'chat';
		$vars[] = 'friend';
		return $vars;
	}

	/**
	 * Handle route request and authentication
	 *
	 * Checks if the current request is for our custom route,
	 * validates user authentication and capabilities,
	 * and handles redirects if needed.
	 *
	 * @return void
	 */
	public function handle_route_request(): void {

		// Check if this is our custom route
		if ( ! $this->is_chat_route_request() ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			$this->redirect_to_login();
			return;
		}

		// Check user capabilities
		if ( ! $this->user_has_required_capability() ) {
			$this->handle_insufficient_permissions();
			return;
		}

		// Set query var to indicate this is a chat page
		set_query_var( 'is_order_chatz', true );
	}

	/**
	 * Load chat template
	 *
	 * Replaces the default template with our chat template
	 * when the chat route is accessed.
	 *
	 * @param string $template Template path
	 *
	 * @return string Template path
	 */
	public function load_chat_template( string $template ): string {
		// Only load our template for authenticated chat requests
		if ( ! get_query_var( 'is_order_chatz' ) ) {
			return $template;
		}

		$chat_template = $this->get_chat_template_path();

		if ( file_exists( $chat_template ) ) {
			return $chat_template;
		}

		Logger::error(
			'Chat template not found',
			array(
				'template_path' => $chat_template,
				'source'        => 'FrontendChatRouter::load_chat_template',
			)
		);

		// Fallback to default template
		return $template;
	}

	/**
	 * Check if current request is for chat route
	 *
	 * @return bool
	 */
	private function is_chat_route_request(): bool {
		return (bool) get_query_var( 'is_order_chatz' );
	}

	/**
	 * Check if user has required capability
	 *
	 * @return bool
	 */
	private function user_has_required_capability(): bool {
		return current_user_can( self::REQUIRED_CAPABILITY );
	}

	/**
	 * Redirect to login page
	 *
	 * @return void
	 */
	private function redirect_to_login(): void {
		$login_url = wp_login_url( home_url( self::ROUTE_PATH ) );

		wp_redirect( $login_url );
		exit;
	}

	/**
	 * Handle insufficient permissions
	 *
	 * @return void
	 */
	private function handle_insufficient_permissions(): void {

		wp_die(
			__( '您沒有足夠的權限訪問此頁面。', 'otz' ),
			__( '權限不足', 'otz' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Get chat template path
	 *
	 * @return string
	 */
	private function get_chat_template_path(): string {
		return OTZ_PLUGIN_DIR . 'views/frontend/chat.php';
	}

	/**
	 * Get route URL
	 *
	 * @return string
	 */
	public static function get_chat_url(): string {
		return home_url( self::ROUTE_PATH );
	}

	/**
	 * Check if current page is the frontend chat
	 *
	 * @return bool
	 */
	public static function is_frontend_chat(): bool {
		return (bool) get_query_var( 'is_order_chatz' );
	}
}
