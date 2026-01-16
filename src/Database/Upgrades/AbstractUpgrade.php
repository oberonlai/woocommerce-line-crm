<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Util\Logger;

/**
 * Abstract Upgrade Base Class
 *
 * Provides common functionality and utilities for all upgrade classes.
 * Implements shared database operations and logging.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
abstract class AbstractUpgrade implements UpgradeInterface {

	/**
	 * WordPress database abstraction layer.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $wpdb;

	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger|null
	 */
	protected ?\WC_Logger $logger;

	/**
	 * Error handler instance.
	 *
	 * @var ErrorHandler|null
	 */
	protected ?ErrorHandler $error_handler;

	/**
	 * Security validator instance.
	 *
	 * @var SecurityValidator|null
	 */
	protected ?SecurityValidator $security_validator;

	/**
	 * Constructor.
	 *
	 * @param \wpdb                  $wpdb WordPress database object.
	 * @param \WC_Logger|null        $logger Logger instance.
	 * @param ErrorHandler|null      $error_handler Error handler instance.
	 * @param SecurityValidator|null $security_validator Security validator instance.
	 */
	public function __construct(
		\wpdb $wpdb,
		?\WC_Logger $logger = null,
		?ErrorHandler $error_handler = null,
		?SecurityValidator $security_validator = null
	) {
		$this->wpdb               = $wpdb;
		$this->logger             = $logger;
		$this->error_handler      = $error_handler;
		$this->security_validator = $security_validator;
	}

	/**
	 * Check if a table exists in the database.
	 *
	 * @param string $table_name Full table name.
	 * @return bool True if table exists, false otherwise.
	 */
	protected function table_exists( string $table_name ): bool {
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		return $this->wpdb->get_var( $query ) === $table_name;
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool True if column exists, false otherwise.
	 */
	protected function column_exists( string $table_name, string $column_name ): bool {
		$column_exists = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SHOW COLUMNS FROM `' . $table_name . '` LIKE %s',
				$column_name
			)
		);
		return ! empty( $column_exists );
	}

	/**
	 * Check if an index exists in a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $index_name Index name.
	 * @return bool True if index exists, false otherwise.
	 */
	protected function index_exists( string $table_name, string $index_name ): bool {
		$existing_indexes = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SHOW INDEX FROM `{$table_name}` WHERE Key_name = %s",
				$index_name
			)
		);
		return ! empty( $existing_indexes );
	}

	/**
	 * Add a column to a table if it doesn't exist.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @param string $column_definition Column definition (e.g., "VARCHAR(255) NULL").
	 * @param string $after_column Optional. Add column after this column.
	 * @return bool True on success or if column already exists, false on failure.
	 */
	protected function add_column(
		string $table_name,
		string $column_name,
		string $column_definition,
		string $after_column = ''
	): bool {
		if ( $this->column_exists( $table_name, $column_name ) ) {
			$this->log_message( "欄位 {$column_name} 已存在於 {$table_name}" );
			return true;
		}

		$after_clause = $after_column ? " AFTER `{$after_column}`" : '';
		$sql          = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}{$after_clause}";
		$result       = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error( "Failed to add column {$column_name} to {$table_name}: " . $this->wpdb->last_error );
			return false;
		}

		$this->log_message( "成功新增欄位 {$column_name} 到 {$table_name}" );
		return true;
	}

	/**
	 * Add an index to a table if it doesn't exist.
	 *
	 * @param string $table_name Table name.
	 * @param string $index_name Index name.
	 * @param string $columns Columns for the index (e.g., "column1, column2").
	 * @param string $index_type Optional. Index type (e.g., "UNIQUE").
	 * @return bool True on success or if index already exists, false on failure.
	 */
	protected function add_index(
		string $table_name,
		string $index_name,
		string $columns,
		string $index_type = ''
	): bool {
		if ( $this->index_exists( $table_name, $index_name ) ) {
			$this->log_message( "索引 {$index_name} 已存在於 {$table_name}" );
			return true;
		}

		$type_clause = $index_type ? "{$index_type} " : '';
		$sql         = "ALTER TABLE `{$table_name}` ADD {$type_clause}INDEX {$index_name} ({$columns})";
		$result      = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error( "Failed to add index {$index_name} to {$table_name}: " . $this->wpdb->last_error );
			return false;
		}

		$this->log_message( "成功新增索引 {$index_name} 到 {$table_name}" );
		return true;
	}

	/**
	 * Log informational messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	protected function log_message( string $message, array $context = array() ): void {
		if ( $this->error_handler ) {
			$this->error_handler->log_info( $message, $context, 'otz-db' );
		} elseif ( $this->logger ) {
			$this->logger->info( $message, array_merge( array( 'source' => 'otz-db' ), $context ) );
		}
	}
}