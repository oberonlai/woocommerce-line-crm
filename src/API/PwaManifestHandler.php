<?php

declare(strict_types=1);

namespace OrderChatz\API;

defined( 'ABSPATH' ) || exit;

/**
 * PWA Manifest Handler
 *
 * Handles dynamic generation of PWA manifest.json for OrderChatz chat interface.
 * Based on the reference implementation in WebPush/Pwa.php
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.0
 */
class PwaManifestHandler {

	/**
	 * Instance
	 *
	 * @var PwaManifestHandler|null
	 */
	private static ?PwaManifestHandler $instance = null;

	/**
	 * Initialize the PWA manifest handler
	 *
	 * @return void
	 */
	public static function init(): void {
		$handler = self::get_instance();
		add_action( 'rest_api_init', array( $handler, 'register_routes' ) );
		add_action( 'wp_head', array( $handler, 'add_manifest_link' ) );
	}

	/**
	 * Get singleton instance
	 *
	 * @return PwaManifestHandler
	 */
	public static function get_instance(): PwaManifestHandler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register REST API routes for PWA manifest
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'orderchatz/v1',
			'/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_manifest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'start_url' => array(
						'description'       => 'Custom start URL for the PWA',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

	}

	/**
	 * Add manifest link to wp_head
	 *
	 * @return void
	 */
	public function add_manifest_link(): void {
		// Only add manifest link on chat interface pages
		if ( ! $this->is_chat_interface_page() ) {
			return;
		}

		$manifest_url = rest_url( 'orderchatz/v1/manifest' );
		echo '<link rel="manifest" href="' . esc_url( $manifest_url ) . '">' . "\n";

		// Add theme color meta tag
		echo '<meta name="theme-color" content="#007cba">' . "\n";

		// Add apple-specific meta tags
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="OrderChatz">' . "\n";

		// Add apple touch icons
		$apple_icon_url = $this->get_apple_touch_icon_url();
		if ( $apple_icon_url ) {
			echo '<link rel="apple-touch-icon" href="' . esc_url( $apple_icon_url ) . '">' . "\n";
		}
	}

	/**
	 * Generate and return PWA manifest data
	 *
	 * @param \WP_REST_Request $request REST API request object
	 * @return array Manifest data
	 */
	public function get_manifest( \WP_REST_Request $request ): array {
		// Get parameters
		$start_url = $request->get_param( 'start_url' );

		// Always use default start_url to ensure it matches scope
		$start_url = $this->get_default_start_url();

		// If a custom start_url is provided, ensure it's within scope
		$custom_start_url = $request->get_param( 'start_url' );
		if ( ! empty( $custom_start_url ) && strpos( $custom_start_url, '/order-chatz' ) !== false ) {
			$start_url = $custom_start_url;
		}

		// Get site information
		$site_name        = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );

		// Generate manifest data
		$manifest = array(
			'name'             => sprintf( '%s - OrderChatz', $site_name ),
			'short_name'       => 'OrderChatz',
			'description'      => $site_description,
			'start_url'        => $start_url,
			'display'          => 'standalone',
			'background_color' => $this->get_background_color(),
			'theme_color'      => $this->get_theme_color(),
			'orientation'      => 'portrait-primary',
			'scope'            => $this->get_scope(),
			'icons'            => $this->get_icons(),
			'categories'       => array( 'business', 'productivity', 'social' ),
			'lang'             => $this->get_language(),
			'dir'              => 'ltr',
			'screenshots'      => $this->get_screenshots(),
		);

		// Add optional features if available
		$shortcuts = $this->get_shortcuts();
		if ( ! empty( $shortcuts ) ) {
			$manifest['shortcuts'] = $shortcuts;
		}

		return $manifest;
	}


	/**
	 * Get default start URL for the PWA
	 *
	 * @return string Start URL
	 */
	private function get_default_start_url(): string {
		// Use the order-chatz chat interface URL
		return home_url( '/order-chatz/' );
	}

	/**
	 * Get PWA scope
	 *
	 * @return string Scope URL
	 */
	private function get_scope(): string {
		return home_url( '/order-chatz' );
	}

	/**
	 * Get theme color
	 *
	 * @return string Hex color code
	 */
	private function get_theme_color(): string {
		// Allow customization via WordPress customizer or options
		$theme_color = get_option( 'otz_pwa_theme_color', '#007cba' );
		return sanitize_hex_color( $theme_color ) ?: '#007cba';
	}

	/**
	 * Get background color
	 *
	 * @return string Hex color code
	 */
	private function get_background_color(): string {
		// Allow customization via WordPress customizer or options
		$bg_color = get_option( 'otz_pwa_background_color', '#ffffff' );
		return sanitize_hex_color( $bg_color ) ?: '#ffffff';
	}

	/**
	 * Get PWA icons array
	 *
	 * @return array Icons configuration
	 */
	private function get_icons(): array {
		$icons = array();

		// Try to get site icon first
		$site_icon_url = OTZ_PLUGIN_URL . 'assets/img/otz-icon-192.png';
		if ( $site_icon_url ) {
			$icons[] = array(
				'src'     => $site_icon_url,
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any maskable',
			);
		}

		// Add plugin-specific icons
		$plugin_icons = $this->get_plugin_icons();
		$icons        = array_merge( $icons, $plugin_icons );

		// If no icons found, use default
		if ( empty( $icons ) ) {
			$icons[] = array(
				'src'     => $this->get_default_icon_url(),
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			);
		}

		return $icons;
	}

	/**
	 * Get plugin-specific icons
	 *
	 * @return array Plugin icons
	 */
	private function get_plugin_icons(): array {
		$icons      = array();
		$plugin_url = defined( 'OTZ_PLUGIN_URL' ) ? OTZ_PLUGIN_URL : plugin_dir_url( dirname( __DIR__, 2 ) );

		// Define available icon sizes
		$icon_sizes = array(
			'72x72'   => 'otz-icon-72.png',
			'96x96'   => 'otz-icon-96.png',
			'128x128' => 'otz-icon-128.png',
			'144x144' => 'otz-icon-144.png',
			'152x152' => 'otz-icon-152.png',
			'192x192' => 'otz-icon-192.png',
			'384x384' => 'otz-icon-384.png',
			'512x512' => 'otz-icon-512.png',
		);

		foreach ( $icon_sizes as $size => $filename ) {
			$icon_path      = $plugin_url . 'assets/img/' . $filename;
			$icon_file_path = str_replace( $plugin_url, plugin_dir_path( dirname( __DIR__, 2 ) ), $icon_path );

			// Only include if file exists
			if ( file_exists( $icon_file_path ) ) {
				$icons[] = array(
					'src'     => $icon_path,
					'sizes'   => $size,
					'type'    => 'image/png',
					'purpose' => $size === '192x192' ? 'any maskable' : 'any',
				);
			}
		}

		return $icons;
	}

	/**
	 * Get default icon URL
	 *
	 * @return string Default icon URL
	 */
	private function get_default_icon_url(): string {
		$plugin_url = defined( 'OTZ_PLUGIN_URL' ) ? OTZ_PLUGIN_URL : plugin_dir_url( dirname( __DIR__, 2 ) );
		return $plugin_url . 'assets/img/otz-icon-192.png';
	}

	/**
	 * Get Apple touch icon URL
	 *
	 * @return string|null Apple touch icon URL
	 */
	private function get_apple_touch_icon_url(): ?string {
		return OTZ_PLUGIN_URL . 'assets/img/otz-icon-512.png';
	}

	/**
	 * Get language for manifest
	 *
	 * @return string Language code
	 */
	private function get_language(): string {
		$locale = get_locale();

		// Convert WordPress locale to language code
		$language_map = array(
			'zh_TW' => 'zh-TW',
			'zh_CN' => 'zh-CN',
			'ja'    => 'ja',
			'ko_KR' => 'ko',
			'en_US' => 'en',
			'en_GB' => 'en-GB',
		);

		return $language_map[ $locale ] ?? 'zh-TW';
	}

	/**
	 * Get PWA shortcuts
	 *
	 * @return array Shortcuts configuration
	 */
	private function get_shortcuts(): array {
		$shortcuts = array();

		$shortcuts[] = array(
			'name'        => '好友列表',
			'short_name'  => '好友',
			'description' => '查看 LINE 好友列表',
			'url'         => home_url( '/order-chatz' ),
			'icons'       => array(
				array(
					'src'   => OTZ_PLUGIN_URL . 'assets/img/otz-icon-192.png',
					'sizes' => '192x192',
					'type'  => 'image/png',
				),
			),
		);

		return $shortcuts;
	}

	/**
	 * Get screenshots for app stores
	 *
	 * @return array Screenshots configuration
	 */
	private function get_screenshots(): array {
		$screenshots = array();
		$plugin_url  = defined( 'OTZ_PLUGIN_URL' ) ? OTZ_PLUGIN_URL : plugin_dir_url( dirname( __DIR__, 2 ) );

		// Desktop screenshot
		$desktop_screenshot = $plugin_url . 'assets/img/screenshot-desktop.png';
		$desktop_file       = str_replace( $plugin_url, plugin_dir_path( dirname( __DIR__, 2 ) ), $desktop_screenshot );

		if ( file_exists( $desktop_file ) ) {
			$screenshots[] = array(
				'src'         => $desktop_screenshot,
				'sizes'       => '1280x720',
				'type'        => 'image/png',
				'form_factor' => 'wide',
				'label'       => 'OrderChatz 桌面版聊天介面',
			);
		}

		// Mobile screenshot
		$mobile_screenshot = $plugin_url . 'assets/img/screenshot-mobile.png';
		$mobile_file       = str_replace( $plugin_url, plugin_dir_path( dirname( __DIR__, 2 ) ), $mobile_screenshot );

		if ( file_exists( $mobile_file ) ) {
			$screenshots[] = array(
				'src'         => $mobile_screenshot,
				'sizes'       => '375x812',
				'type'        => 'image/png',
				'form_factor' => 'narrow',
				'label'       => 'OrderChatz 行動版聊天介面',
			);
		}

		return $screenshots;
	}

	/**
	 * Check if current page is chat interface
	 *
	 * @return bool True if chat interface page
	 */
	private function is_chat_interface_page(): bool {
		// Check for chat interface query parameter
		if ( isset( $_GET['otz_frontend_chat'] ) ) {
			return true;
		}

		// Check for rewrite route (order-chatz)
		if ( get_query_var( 'is_order_chatz' ) ) {
			return true;
		}

		// Check for specific chat routes or pages
		global $wp_query;
		if ( $wp_query && ( isset( $wp_query->query_vars['otz_frontend_chat'] ) ||
						  isset( $wp_query->query_vars['is_order_chatz'] ) ) ) {
			return true;
		}

		return false;
	}
}
