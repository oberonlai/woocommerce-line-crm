<?php

declare(strict_types=1);

namespace OrderChatz\Database;

use OrderChatz\Util\Logger;

/**
 * Dynamic Table Manager Class
 *
 * Handles monthly message table creation and management.
 * Implements Phase 4: Dynamic partitioning functionality.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class DynamicTableManager {

	/**
	 * WordPress database abstraction layer
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
	 * Table creator instance
	 *
	 * @var TableCreator
	 */
	private TableCreator $table_creator;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Security validator instance
	 *
	 * @var SecurityValidator|null
	 */
	private ?SecurityValidator $security_validator;

	/**
	 * Constructor
	 *
	 * @param \wpdb                  $wpdb WordPress database object
	 * @param \WC_Logger|null        $logger Logger instance
	 * @param ErrorHandler|null      $error_handler Error handler instance
	 * @param SecurityValidator|null $security_validator Security validator instance
	 */
	public function __construct( \wpdb $wpdb, ?\WC_Logger $logger = null, ?ErrorHandler $error_handler = null, ?SecurityValidator $security_validator = null ) {
		$this->wpdb               = $wpdb;
		$this->logger             = $logger;
		$this->error_handler      = $error_handler;
		$this->table_creator      = new TableCreator( $wpdb, $logger, $error_handler );
		$this->security_validator = $security_validator;
	}

	/**
	 * Create monthly message table for dynamic partitioning
	 *
	 * @param string|null $year_month Year-month in YYYY_MM format (default: current month)
	 * @return bool True on successful creation, false on failure
	 */
	public function create_monthly_message_table( ?string $year_month = null ): bool {
		try {
			// Use current month if not specified
			if ( null === $year_month ) {
				$year_month = wp_date( 'Y_m' );
			}

			// Validate year_month format and security
			if ( ! $this->validate_year_month_format( $year_month ) ) {
				Logger::error( "Invalid year_month format provided: {$year_month}" );
				return false;
			}

			// Generate table name with security validation
			$table_name = $this->generate_monthly_table_name( $year_month );

			// Check if table already exists to avoid duplicate creation
			if ( $this->table_creator->table_exists( $table_name ) ) {
				return true;
			}

			// Build SQL DDL with comprehensive indexing strategy
			$sql = $this->table_creator->build_monthly_message_table_sql( $table_name );

			// Execute table creation with error handling
			$success = $this->table_creator->execute_table_creation( $table_name, $sql );

			if ( $success ) {

				// Invalidate related caches after successful table creation
				$this->invalidate_table_caches( $table_name );
			} else {
				Logger::error( "Failed to create monthly message table: {$table_name}" );
			}

			return $success;

		} catch ( \Exception $e ) {
			Logger::error(
				"Exception creating monthly message table for {$year_month}: " . $e->getMessage(),
				array( 'trace' => $e->getTraceAsString() )
			);
			return false;
		}
	}

	/**
	 * Validate year-month format and security constraints
	 *
	 * @param string $year_month Year-month string to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validate_year_month_format( string $year_month ): bool {
		// Use SecurityValidator if available for enhanced validation
		if ( $this->security_validator ) {
			$sanitized = $this->security_validator->sanitize_table_suffix( $year_month, 'year_month_validation' );
			if ( null === $sanitized ) {
				return false;
			}
			$year_month = $sanitized;
		}

		// Primary regex validation for YYYY_MM format
		if ( ! preg_match( '/^\d{4}_\d{2}$/', $year_month ) ) {
			Logger::error( "Invalid year_month format: {$year_month}" );
			return false;
		}

		// Split and validate year/month components
		$parts = explode( '_', $year_month );
		if ( 2 !== count( $parts ) ) {
			Logger::error( "Invalid year_month parts count: {$year_month}" );
			return false;
		}

		$year  = (int) $parts[0];
		$month = (int) $parts[1];

		// Validate reasonable year range (2020-2099)
		if ( $year < 2020 || $year > 2099 ) {
			Logger::error( "Year out of valid range (2020-2099): {$year}" );
			return false;
		}

		// Validate month range (01-12)
		if ( $month < 1 || $month > 12 ) {
			Logger::error( "Month out of valid range (01-12): {$month}" );
			return false;
		}

		// Additional security: ensure no SQL injection attempts (fallback if no SecurityValidator)
		if ( ! $this->security_validator && $year_month !== sanitize_text_field( $year_month ) ) {
			Logger::error( "Security validation failed for year_month: {$year_month}" );
			return false;
		}

		return true;
	}

	/**
	 * Generate monthly message table name with security validation
	 *
	 * @param string $year_month Validated year-month string
	 * @return string Full table name with WordPress prefix
	 */
	public function generate_monthly_table_name( string $year_month ): string {
		// Double validation for security (defense in depth)
		if ( ! $this->validate_year_month_format( $year_month ) ) {
			throw new \InvalidArgumentException( "Invalid year_month format for table name generation: {$year_month}" );
		}

		return $this->wpdb->prefix . 'otz_messages_' . $year_month;
	}

	/**
	 * Check if monthly message table exists for given year-month
	 *
	 * @param string $year_month Year-month in YYYY_MM format
	 * @return bool True if table exists, false otherwise
	 */
	public function monthly_message_table_exists( string $year_month ): bool {
		if ( ! $this->validate_year_month_format( $year_month ) ) {
			return false;
		}

		$table_name = $this->generate_monthly_table_name( $year_month );
		return $this->table_creator->table_exists( $table_name );
	}

	/**
	 * Get monthly message table name for given year-month
	 *
	 * @param string $year_month Year-month in YYYY_MM format
	 * @return string|false Full table name on success, false on invalid format
	 */
	public function get_monthly_message_table_name( string $year_month ) {
		if ( ! $this->validate_year_month_format( $year_month ) ) {
			return false;
		}

		return $this->generate_monthly_table_name( $year_month );
	}

	/**
	 * Create current month's message table automatically
	 *
	 * @return bool True on success, false on failure
	 */
	public function ensure_current_month_table(): bool {
		return $this->create_monthly_message_table();
	}

	/**
	 * Get list of existing monthly message tables
	 *
	 * @return array Array of year_month strings (e.g., ['2025_08', '2025_09'])
	 */
	public function get_existing_monthly_tables(): array {
		$pattern = $this->wpdb->prefix . 'otz_messages_%';
		$query   = $this->wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$pattern
		);

		$tables = $this->wpdb->get_col( $query );
		$months = array();

		foreach ( $tables as $table ) {
			// Extract year_month from table name
			$prefix = $this->wpdb->prefix . 'otz_messages_';
			if ( 0 === strpos( $table, $prefix ) ) {
				$year_month = substr( $table, strlen( $prefix ) );
				if ( $this->validate_year_month_format( $year_month ) ) {
					$months[] = $year_month;
				}
			}
		}

		// Sort chronologically (newest first)
		rsort( $months );
		return $months;
	}

	/**
	 * Log informational messages
	 *
	 * @param string $message The message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
	private function log_message( string $message, array $context = array() ): void {
		if ( $this->error_handler ) {
			$this->error_handler->log_info( $message, $context, 'orderchatz-dynamic-tables' );
		} elseif ( $this->logger ) {
			$this->logger->info( $message, array_merge( array( 'source' => 'orderchatz-dynamic-tables' ), $context ) );
		} else {
			error_log( "[OrderChatz DynamicTableManager] {$message}" );
		}
	}

	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log
	 * @param array  $context Additional context data
	 * @return void
	 */

	/**
	 * Invalidate table-related caches after table creation or modification
	 * This method is used by TableManager for cache synchronization
	 *
	 * @param string $table_name The table name that was modified
	 * @return void
	 */
	private function invalidate_table_caches( string $table_name ): void {
		// Clear monthly tables list cache
		delete_transient( 'otz_table_list_monthly_tables' );

		// Clear specific table caches
		$table_hash = md5( $table_name );
		delete_transient( 'otz_table_size_' . $table_hash );
		delete_transient( 'otz_table_performance_' . $table_hash );
	}
}
