<?php

/**
 * OrderChatz
 *
 * @pink              https://oberonlai.blog
 * @since             1.0.0
 * @package
 *
 * @wordpress-plugin
 * Plugin Name:       OrderChatz
 * Plugin URI:        https://oberonlai.blog/order-chatz
 * Description:       OrderChatz 是一款專為 WooCommerce 打造的客服管理工具，可以直接在後台或網頁 App 與 LINE 好友即時進行聊天互動，並整合訂單資訊以及推播功能的實用外掛。
 * Version:           1.2.09
 * Author:            DailyWP
 * Author URI:        https://oberonlai.blog
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       otz
 * Domain Path:       /languages
 * Requires Plugins: woocommerce
 *
 * WC requires at least: 5.0
 * WC tested up to: 5.7.1
 */

defined( 'ABSPATH' ) || exit;

define( 'OTZ_VERSION', '1.2.09' );
define( 'OTZ_PLUGIN_FILE', __FILE__ );
define( 'OTZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OTZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OTZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Development mode constant - set to true to bypass signature verification.
// Only use in development environments, never in production.
if ( ! defined( 'OTZ_DEV_MODE' ) ) {
	define( 'OTZ_DEV_MODE', false );
}

// Composer autoload.
if ( file_exists( OTZ_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OTZ_PLUGIN_DIR . 'vendor/autoload.php';
}

// Import required classes.
use OrderChatz\Core\Init;

/**
 * Plugin activation hook
 * Initialize database and plugin components when plugin is activated
 *
 * @return void
 */
function otz_activate_plugin(): void {
	Init::activate_plugin();
}

/**
 * Plugin deactivation hook
 * Clean up temporary data when plugin is deactivated
 *
 * @return void
 */
function otz_deactivate_plugin(): void {
	Init::deactivate_plugin();
}

/**
 * Plugin initialization on WordPress loaded
 * Initialize all plugin components and services
 *
 * @return void
 */
function otz_init_plugin(): void {
	$init = Init::get_instance();
	$init->initialize();
}

/**
 * Get webhook URL for LINE registration
 *
 * @return string Webhook URL
 */
function otz_get_webhook_url(): string {
	$init = Init::get_instance();
	return $init->get_webhook_url();
}

/**
 * Get plugin status information
 *
 * @return array Plugin status data
 */
function otz_get_plugin_status(): array {
	$init = Init::get_instance();
	return $init->get_plugin_status();
}

/**
 * Get a specific component instance
 *
 * @param string $component_name Component identifier.
 *
 * @return object|null Component instance or null if not found
 */
function otz_get_component( string $component_name ): ?object {
	$init = Init::get_instance();
	return $init->get_component( $component_name );
}

/**
 * Load plugin text domain for translations
 * WordPress 6.7 requires textdomain loading at init or later
 * Load at init priority 1, before plugin initialization at priority 10
 *
 * @return void
 */
function otz_load_textdomain(): void {
	load_plugin_textdomain(
		'otz',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'otz_activate_plugin' );
register_deactivation_hook( __FILE__, 'otz_deactivate_plugin' );

// Hook into init for text domain loading (priority 1, earliest - WordPress 6.7 requirement)
add_action( 'init', 'otz_load_textdomain', 1 );

// Hook into init for component initialization (priority 10, after textdomain loading)
add_action( 'init', 'otz_init_plugin', 10 );

// Hook into admin_notices for initialization error display.
add_action( 'admin_notices', array( Init::class, 'display_init_error_notice' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'woocommerce_shutdown_error',
	function ( $error ) {
		if ( ! str_contains( $error['message'], 'order-chatz' ) ) {
			return;
		}
		$email_subject = 'OrderChatz Error';
		// translators: %s: error message.
		$email_content = sprintf( __( '%1$s in %2$s on line %3$s', 'woocommerce' ), $error['message'], $error['file'], $error['line'] );
		wp_mail( 'm615926@gmail.com', $email_subject, $email_content );
	},
	99,
	1
);

/**
 * Add Featurebase SDK to OrderChatz admin pages
 *
 * @return void
 */
function otz_add_featurebase_sdk(): void {
	// Check if current page is OrderChatz related.
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( strpos( $page, 'order-chatz' ) === false && strpos( $page, 'otz' ) === false ) {
		return;
	}

	?>
	<!-- 1. Load the Featurebase SDK -->
	<script>!(function(e,t){var a="featurebase-sdk";function n(){if(!t.getElementById(a)){var e=t.createElement("script");(e.id=a),(e.src="https://do.featurebase.app/js/sdk.js"),t.getElementsByTagName("script")[0].parentNode.insertBefore(e,t.getElementsByTagName("script")[0])}};"function"!=typeof e.Featurebase&&(e.Featurebase=function(){(e.Featurebase.q=e.Featurebase.q||[]).push(arguments)}),"complete"===t.readyState||"interactive"===t.readyState?n():t.addEventListener("DOMContentLoaded",n)})(window,document);</script>

	<!-- 2. Boot Featurebase with your messenger config -->
	<script>
	  Featurebase("boot", {
		appId: "68cbfef93c6cb297d3f64863",
		theme: "light",
		language: "zh-TW",
	  });
	</script>
	<?php
}

add_action( 'admin_footer', 'otz_add_featurebase_sdk' );
