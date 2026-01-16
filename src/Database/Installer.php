<?php

declare(strict_types=1);

namespace OrderChatz\Database;

use OrderChatz\Database\Upgrades\VersionManager;
use OrderChatz\Util\Logger;

/**
 * Database Installer Class
 *
 * Main installer class that orchestrates database schema creation and management.
 * Refactored for better maintainability with separated responsibilities.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
final class Installer {

	/**
	 * Singleton instance
	 *
	 * @var Installer|null
	 */
	private static ?Installer $instance = null;

	/**
	 * Fixed table names (without prefix)
	 *
	 * Note: Database version is managed by VersionManager class.
	 *
	 * @var array
	 */
	private const TABLES = array(
		'users',
		'user_tags',
		'user_notes',
		'subscribers',
		'broadcast',
		'broadcast_logs',
	);

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Logger instance for error handling
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger = null;

	/**
	 * Table creator instance
	 *
	 * @var TableCreator
	 */
	private TableCreator $table_creator;

	/**
	 * Dynamic table manager instance
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $dynamic_manager;

	/**
	 * Query optimizer instance
	 *
	 * @var QueryOptimizer
	 */
	private QueryOptimizer $query_optimizer;

	/**
	 * Table manager instance
	 *
	 * @var TableManager
	 */
	private TableManager $table_manager;

	/**
	 * Version manager instance
	 *
	 * @var VersionManager
	 */
	private VersionManager $version_manager;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler
	 */
	private ErrorHandler $error_handler;

	/**
	 * Security validator instance
	 *
	 * @var SecurityValidator
	 */
	private SecurityValidator $security_validator;

	/**
	 * Private constructor to prevent direct instantiation (Singleton pattern)
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		// Initialize WooCommerce logger if available
		if ( function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}

		// Initialize error handler and security validator first
		$this->error_handler      = new ErrorHandler( $this->wpdb, $this->logger );
		$this->security_validator = new SecurityValidator( $this->wpdb, $this->error_handler );

		// Initialize component classes with error handler and security validator
		$this->table_creator   = new TableCreator( $this->wpdb, $this->logger, $this->error_handler );
		$this->dynamic_manager = new DynamicTableManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator );
		$this->query_optimizer = new QueryOptimizer( $this->wpdb, $this->dynamic_manager );
		$this->table_manager   = new TableManager( $this->wpdb, $this->dynamic_manager, $this->logger, $this->error_handler );
		$this->version_manager = new VersionManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator );
	}

	/**
	 * Prevent cloning of the instance (Singleton pattern)
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization of the instance (Singleton pattern)
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Get singleton instance
	 *
	 * @return Installer
	 */
	public static function get_instance(): Installer {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Main initialization method called during plugin activation
	 * This is the entry point for database installation and setup
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function init(): void {
		$installer = self::get_instance();

		// Check if we need to install or upgrade
		if ( $installer->needs_upgrade() ) {
			$installer->install_or_upgrade();
		}
	}

	/**
	 * Check if database upgrade is needed
	 *
	 * @return bool True if upgrade is needed, false otherwise
	 * @since 1.0.0
	 */
	private function needs_upgrade(): bool {
		return $this->version_manager->needs_upgrade();
	}

	/**
	 * Install or upgrade database tables
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function install_or_upgrade(): void {
		try {
			$current_version = $this->version_manager->get_current_version();

			if ( '0.0.0' === $current_version ) {
				$this->create_fixed_tables();
				// Use target version from VersionManager for fresh installations.
				update_option( 'otz_db_version', $this->version_manager->get_target_version() );
			} else {
				$this->version_manager->execute_upgrade();
			}
		} catch ( \Exception $e ) {
			$this->handle_installation_error( $e );
		}
	}

	/**
	 * Create all fixed database tables
	 *
	 * @return bool True on success, false on failure
	 * @since 1.0.0
	 */
	public function create_fixed_tables(): bool {
		$success = true;

		foreach ( self::TABLES as $table ) {
			$table_name = $this->get_table_name( $table );
			$method     = "create_{$table}_table";

			if ( method_exists( $this->table_creator, $method ) ) {
				if ( ! $this->table_creator->$method( $table_name ) ) {
					$success = false;
					Logger::error( "Failed to create table: {$table}" );
				}
			}
		}

		return $success;
	}

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @param string $table Table name without prefix
	 * @return string Full table name with prefix
	 * @since 1.0.0
	 */
	public function get_table_name( string $table ): string {
		return $this->wpdb->prefix . 'otz_' . $table;
	}

	/**
	 * Get current database version
	 *
	 * @return string Current database version
	 * @since 1.0.0
	 */
	public function get_current_version(): string {
		return $this->version_manager->get_current_version();
	}

	/**
	 * Get target database version
	 *
	 * @return string Target database version
	 * @since 1.0.0
	 */
	public function get_target_version(): string {
		return $this->version_manager->get_target_version();
	}

	// ========== LOGGING AND ERROR HANDLING ==========

	/**
	 * Log informational messages
	 *
	 * @param string $message The message to log
	 * @param array  $context Additional context data
	 * @return void
	 * @since 1.0.0
	 */
	private function log_message( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->info( $message, array_merge( array( 'source' => 'orderchatz-installer' ), $context ) );
		} else {
			// Fallback to error_log if WooCommerce logger is not available
			error_log( "[OrderChatz Installer] {$message}" );
		}
	}

	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log
	 * @param array  $context Additional context data
	 * @return void
	 * @since 1.0.0
	 */

	/**
	 * Handle installation errors and show admin notices
	 *
	 * @param \Exception $e The exception that occurred
	 * @return void
	 * @since 1.0.0
	 */
	private function handle_installation_error( \Exception $e ): void {
		$current_version = $this->version_manager->get_current_version();
		$target_version  = $this->version_manager->get_target_version();

		$error_message = sprintf(
			'OrderChatz database installation/upgrade failed (v%s → v%s): %s',
			$current_version,
			$target_version,
			$e->getMessage()
		);

		// Log the error with enhanced context using ErrorHandler
		$this->error_handler->log_database_error(
			$error_message,
			array(
				'trace'                 => $e->getTraceAsString(),
				'current_version'       => $current_version,
				'target_version'        => $target_version,
				'upgrade_history_count' => count( $this->version_manager->get_upgrade_history() ),
				'operation'             => 'installation_upgrade',
			),
			'version'
		);

		// ErrorHandler will automatically create admin notices
		// No need for manual admin notice creation here
	}

}
