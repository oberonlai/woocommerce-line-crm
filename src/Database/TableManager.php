<?php

declare(strict_types=1);

namespace OrderChatz\Database;

use OrderChatz\Util\Logger;

/**
 * Table Manager Class
 *
 * Advanced table management functionality for Phase 6: Management and Performance Optimization.
 * Provides comprehensive table statistics, size information, and performance monitoring.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class TableManager {

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
	 * Dynamic table manager instance
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $dynamic_manager;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Cache expiration constants (in seconds)
	 */
	private const CACHE_EXPIRY_TABLE_SIZE = 1800; // 30 minutes
	private const CACHE_EXPIRY_TABLE_LIST = 3600; // 1 hour
	private const CACHE_EXPIRY_PERFORMANCE = 900;  // 15 minutes

	/**
	 * Cache key prefixes
	 */
	private const CACHE_PREFIX_SIZE = 'otz_table_size_';
	private const CACHE_PREFIX_LIST = 'otz_table_list_';
	private const CACHE_PREFIX_PERFORMANCE = 'otz_table_performance_';

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 * @param DynamicTableManager $dynamic_manager Dynamic table manager instance
	 * @param \WC_Logger|null $logger Logger instance
	 * @param ErrorHandler|null $error_handler Error handler instance
	 */
	public function __construct( \wpdb $wpdb, DynamicTableManager $dynamic_manager, ?\WC_Logger $logger = null, ?ErrorHandler $error_handler = null ) {
		$this->wpdb = $wpdb;
		$this->dynamic_manager = $dynamic_manager;
		$this->logger = $logger;
		$this->error_handler = $error_handler;
	}

	/**
	 * Get comprehensive list of monthly message tables with detailed information
	 * Enhanced version of DynamicTableManager::get_existing_monthly_tables() with caching and statistics
	 *
	 * @param bool $force_refresh Force cache refresh
	 * @return array Array of table information including size, row count, and metadata
	 */
	public function get_monthly_tables( bool $force_refresh = false ): array {
		$cache_key = self::CACHE_PREFIX_LIST . 'monthly_tables';
		
		// Try to get from cache first (unless forced refresh)
		if ( ! $force_refresh ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data && is_array( $cached_data ) ) {
				$this->log_message( 'Monthly tables list retrieved from cache' );
				return $cached_data;
			}
		}

		try {
			// Get basic table list from dynamic manager
			$table_months = $this->dynamic_manager->get_existing_monthly_tables();
			$detailed_tables = array();

			foreach ( $table_months as $year_month ) {
				$table_name = $this->dynamic_manager->get_monthly_message_table_name( $year_month );
				
				if ( false === $table_name ) {
					continue;
				}

				// Get detailed table information
				$table_info = $this->get_table_detailed_info( $table_name, $year_month );
				
				if ( null !== $table_info ) {
					$detailed_tables[] = $table_info;
				}
			}

			// Sort by year_month descending (newest first)
			usort( $detailed_tables, function( $a, $b ) {
				return strcmp( $b['year_month'], $a['year_month'] );
			});

			// Cache the results
			set_transient( $cache_key, $detailed_tables, self::CACHE_EXPIRY_TABLE_LIST );
			
			$this->log_message( sprintf( 'Retrieved detailed information for %d monthly tables', count( $detailed_tables ) ) );
			
			return $detailed_tables;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Failed to get monthly tables list: ' . $e->getMessage(),
				array( 'trace' => $e->getTraceAsString() )
			);
			return array();
		}
	}

	/**
	 * Get table size and statistics using SHOW TABLE STATUS
	 *
	 * @param string $table_name Full table name
	 * @param bool $force_refresh Force cache refresh
	 * @return array|null Table size information or null on failure
	 */
	public function get_table_size( string $table_name, bool $force_refresh = false ): ?array {
		$cache_key = self::CACHE_PREFIX_SIZE . md5( $table_name );
		
		// Try to get from cache first (unless forced refresh)
		if ( ! $force_refresh ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data && is_array( $cached_data ) ) {
				return $cached_data;
			}
		}

		try {
			// Use SHOW TABLE STATUS for comprehensive table information
			$query = $this->wpdb->prepare( "SHOW TABLE STATUS LIKE %s", $table_name );
			$table_status = $this->wpdb->get_row( $query, ARRAY_A );

			if ( null === $table_status ) {
				Logger::error( "Table not found or query failed: {$table_name}" );
				return null;
			}

			// Extract and format table size information
			$size_info = array(
				'table_name' => $table_name,
				'engine' => $table_status['Engine'] ?? 'Unknown',
				'rows' => (int) ( $table_status['Rows'] ?? 0 ),
				'avg_row_length' => (int) ( $table_status['Avg_row_length'] ?? 0 ),
				'data_length' => (int) ( $table_status['Data_length'] ?? 0 ),
				'index_length' => (int) ( $table_status['Index_length'] ?? 0 ),
				'data_free' => (int) ( $table_status['Data_free'] ?? 0 ),
				'auto_increment' => (int) ( $table_status['Auto_increment'] ?? 0 ),
				'create_time' => $table_status['Create_time'] ?? null,
				'update_time' => $table_status['Update_time'] ?? null,
				'check_time' => $table_status['Check_time'] ?? null,
				'collation' => $table_status['Collation'] ?? 'Unknown',
				// Calculated fields
				'total_size' => (int) ( $table_status['Data_length'] ?? 0 ) + (int) ( $table_status['Index_length'] ?? 0 ),
				'data_size_mb' => round( ( (int) ( $table_status['Data_length'] ?? 0 ) ) / 1024 / 1024, 2 ),
				'index_size_mb' => round( ( (int) ( $table_status['Index_length'] ?? 0 ) ) / 1024 / 1024, 2 ),
				'total_size_mb' => round( ( (int) ( $table_status['Data_length'] ?? 0 ) + (int) ( $table_status['Index_length'] ?? 0 ) ) / 1024 / 1024, 2 ),
				'fragmentation_mb' => round( ( (int) ( $table_status['Data_free'] ?? 0 ) ) / 1024 / 1024, 2 ),
			);

			// Cache the results
			set_transient( $cache_key, $size_info, self::CACHE_EXPIRY_TABLE_SIZE );
			
			return $size_info;

		} catch ( \Exception $e ) {
			Logger::error( 
				"Failed to get table size for {$table_name}: " . $e->getMessage(),
				array( 'trace' => $e->getTraceAsString() )
			);
			return null;
		}
	}

	/**
	 * Get table performance statistics and health information
	 *
	 * @param string $table_name Full table name
	 * @param bool $force_refresh Force cache refresh
	 * @return array|null Performance statistics or null on failure
	 */
	public function get_table_performance_stats( string $table_name, bool $force_refresh = false ): ?array {
		$cache_key = self::CACHE_PREFIX_PERFORMANCE . md5( $table_name );
		
		// Try to get from cache first (unless forced refresh)
		if ( ! $force_refresh ) {
			$cached_data = get_transient( $cache_key );
			if ( false !== $cached_data && is_array( $cached_data ) ) {
				return $cached_data;
			}
		}

		try {
			// Get basic table size information first
			$size_info = $this->get_table_size( $table_name, $force_refresh );
			if ( null === $size_info ) {
				return null;
			}

			// Get index information
			$indexes = $this->get_table_indexes( $table_name );
			
			// Calculate performance metrics
			$performance_stats = array(
				'table_name' => $table_name,
				'timestamp' => current_time( 'mysql' ),
				// Size-based metrics
				'row_count' => $size_info['rows'],
				'total_size_mb' => $size_info['total_size_mb'],
				'data_size_mb' => $size_info['data_size_mb'],
				'index_size_mb' => $size_info['index_size_mb'],
				'fragmentation_mb' => $size_info['fragmentation_mb'],
				// Performance indicators
				'avg_row_length' => $size_info['avg_row_length'],
				'index_count' => count( $indexes ),
				'indexes' => $indexes,
				// Health indicators
				'fragmentation_ratio' => $size_info['total_size_mb'] > 0 ? round( ( $size_info['fragmentation_mb'] / $size_info['total_size_mb'] ) * 100, 2 ) : 0,
				'index_ratio' => $size_info['data_size_mb'] > 0 ? round( ( $size_info['index_size_mb'] / $size_info['data_size_mb'] ) * 100, 2 ) : 0,
				// Recommendations
				'health_status' => $this->calculate_table_health_status( $size_info, $indexes ),
				'recommendations' => $this->generate_table_recommendations( $size_info, $indexes ),
				// Timestamps
				'last_update' => $size_info['update_time'],
				'created_at' => $size_info['create_time'],
			);

			// Cache the results
			set_transient( $cache_key, $performance_stats, self::CACHE_EXPIRY_PERFORMANCE );
			
			return $performance_stats;

		} catch ( \Exception $e ) {
			Logger::error( 
				"Failed to get performance stats for {$table_name}: " . $e->getMessage(),
				array( 'trace' => $e->getTraceAsString() )
			);
			return null;
		}
	}

	/**
	 * Get detailed table information combining size and performance data
	 *
	 * @param string $table_name Full table name
	 * @param string $year_month Year-month identifier
	 * @return array|null Detailed table information
	 */
	private function get_table_detailed_info( string $table_name, string $year_month ): ?array {
		$size_info = $this->get_table_size( $table_name );
		
		if ( null === $size_info ) {
			return null;
		}

		return array(
			'year_month' => $year_month,
			'table_name' => $table_name,
			'short_name' => str_replace( $this->wpdb->prefix, '', $table_name ),
			'row_count' => $size_info['rows'],
			'total_size_mb' => $size_info['total_size_mb'],
			'data_size_mb' => $size_info['data_size_mb'],
			'index_size_mb' => $size_info['index_size_mb'],
			'fragmentation_mb' => $size_info['fragmentation_mb'],
			'engine' => $size_info['engine'],
			'created_at' => $size_info['create_time'],
			'updated_at' => $size_info['update_time'],
			'auto_increment' => $size_info['auto_increment'],
		);
	}

	/**
	 * Get table index information
	 *
	 * @param string $table_name Full table name
	 * @return array Array of index information
	 */
	private function get_table_indexes( string $table_name ): array {
		try {
			$query = $this->wpdb->prepare( "SHOW INDEX FROM `%s`", $table_name );
			// Note: WPDB prepare doesn't support table names in backticks properly,
			// so we need to use a different approach for security
			$escaped_table = esc_sql( $table_name );
			$query = "SHOW INDEX FROM `{$escaped_table}`";
			
			$results = $this->wpdb->get_results( $query, ARRAY_A );
			
			if ( null === $results ) {
				return array();
			}

			$indexes = array();
			foreach ( $results as $row ) {
				$key_name = $row['Key_name'];
				if ( ! isset( $indexes[ $key_name ] ) ) {
					$indexes[ $key_name ] = array(
						'name' => $key_name,
						'unique' => '0' === $row['Non_unique'] ? true : false,
						'type' => $row['Index_type'] ?? 'BTREE',
						'columns' => array(),
					);
				}
				$indexes[ $key_name ]['columns'][] = $row['Column_name'];
			}

			return array_values( $indexes );

		} catch ( \Exception $e ) {
			Logger::error( 
				"Failed to get indexes for {$table_name}: " . $e->getMessage()
			);
			return array();
		}
	}

	/**
	 * Calculate table health status based on metrics
	 *
	 * @param array $size_info Table size information
	 * @param array $indexes Index information
	 * @return string Health status (excellent, good, fair, poor)
	 */
	private function calculate_table_health_status( array $size_info, array $indexes ): string {
		$score = 0;

		// Fragmentation score (0-3 points)
		$fragmentation_ratio = $size_info['total_size_mb'] > 0 ? ( $size_info['fragmentation_mb'] / $size_info['total_size_mb'] ) * 100 : 0;
		if ( $fragmentation_ratio < 5 ) {
			$score += 3;
		} elseif ( $fragmentation_ratio < 15 ) {
			$score += 2;
		} elseif ( $fragmentation_ratio < 30 ) {
			$score += 1;
		}

		// Index ratio score (0-2 points)
		$index_ratio = $size_info['data_size_mb'] > 0 ? ( $size_info['index_size_mb'] / $size_info['data_size_mb'] ) * 100 : 0;
		if ( $index_ratio > 10 && $index_ratio < 50 ) {
			$score += 2;
		} elseif ( $index_ratio <= 10 || $index_ratio < 80 ) {
			$score += 1;
		}

		// Index count score (0-1 point)
		$index_count = count( $indexes );
		if ( $index_count >= 3 && $index_count <= 8 ) {
			$score += 1;
		}

		// Determine status based on score
		if ( $score >= 5 ) {
			return 'excellent';
		} elseif ( $score >= 3 ) {
			return 'good';
		} elseif ( $score >= 1 ) {
			return 'fair';
		} else {
			return 'poor';
		}
	}

	/**
	 * Generate table maintenance recommendations
	 *
	 * @param array $size_info Table size information
	 * @param array $indexes Index information
	 * @return array Array of recommendations
	 */
	private function generate_table_recommendations( array $size_info, array $indexes ): array {
		$recommendations = array();

		// Check fragmentation
		$fragmentation_ratio = $size_info['total_size_mb'] > 0 ? ( $size_info['fragmentation_mb'] / $size_info['total_size_mb'] ) * 100 : 0;
		if ( $fragmentation_ratio > 20 ) {
			$recommendations[] = array(
				'type' => 'optimization',
				'priority' => 'high',
				'message' => sprintf( '資料表碎片化嚴重 (%.1f%%)，建議執行 OPTIMIZE TABLE', $fragmentation_ratio ),
				'action' => 'optimize_table'
			);
		} elseif ( $fragmentation_ratio > 10 ) {
			$recommendations[] = array(
				'type' => 'maintenance',
				'priority' => 'medium',
				'message' => sprintf( '資料表有中等程度碎片化 (%.1f%%)，可考慮定期優化', $fragmentation_ratio ),
				'action' => 'schedule_optimization'
			);
		}

		// Check table size
		if ( $size_info['total_size_mb'] > 1000 ) {
			$recommendations[] = array(
				'type' => 'archival',
				'priority' => 'medium',
				'message' => sprintf( '資料表大小較大 (%.1f MB)，建議考慮資料歸檔', $size_info['total_size_mb'] ),
				'action' => 'archive_old_data'
			);
		}

		// Check row count
		if ( $size_info['rows'] > 1000000 ) {
			$recommendations[] = array(
				'type' => 'performance',
				'priority' => 'medium',
				'message' => sprintf( '資料表記錄數較多 (%s 筆)，確保查詢效能', number_format( $size_info['rows'] ) ),
				'action' => 'review_queries'
			);
		}

		// Check index efficiency
		$index_count = count( $indexes );
		if ( $index_count < 2 ) {
			$recommendations[] = array(
				'type' => 'performance',
				'priority' => 'high',
				'message' => '索引數量不足，可能影響查詢效能',
				'action' => 'add_indexes'
			);
		} elseif ( $index_count > 10 ) {
			$recommendations[] = array(
				'type' => 'performance',
				'priority' => 'medium',
				'message' => '索引數量較多，可能影響寫入效能',
				'action' => 'review_indexes'
			);
		}

		return $recommendations;
	}

	/**
	 * Invalidate all table-related caches
	 *
	 * @param string|null $table_name Specific table name to clear cache for (null for all)
	 * @return int Number of cache entries cleared
	 */
	public function invalidate_table_cache( ?string $table_name = null ): int {
		$cleared = 0;

		if ( null === $table_name ) {
			// Clear all table caches
			$cache_keys = array(
				self::CACHE_PREFIX_LIST . 'monthly_tables',
			);

			foreach ( $cache_keys as $key ) {
				if ( delete_transient( $key ) ) {
					$cleared++;
				}
			}

			// For size and performance caches, we need to clear by pattern (WordPress doesn't support this natively)
			// So we'll use a workaround with cache version incrementing
			$cache_version = get_option( 'otz_table_cache_version', 1 );
			update_option( 'otz_table_cache_version', $cache_version + 1 );
			$cleared += 2; // Assume we cleared size and performance caches

		} else {
			// Clear specific table caches
			$cache_keys = array(
				self::CACHE_PREFIX_SIZE . md5( $table_name ),
				self::CACHE_PREFIX_PERFORMANCE . md5( $table_name ),
			);

			foreach ( $cache_keys as $key ) {
				if ( delete_transient( $key ) ) {
					$cleared++;
				}
			}

			// Also clear the monthly tables list as it includes this table
			if ( delete_transient( self::CACHE_PREFIX_LIST . 'monthly_tables' ) ) {
				$cleared++;
			}
		}

		$this->log_message( "Cleared {$cleared} table cache entries" . ( $table_name ? " for {$table_name}" : '' ) );
		
		return $cleared;
	}

	/**
	 * Get table management summary for admin interface
	 *
	 * @return array Summary information for dashboard display
	 */
	public function get_management_summary(): array {
		try {
			$monthly_tables = $this->get_monthly_tables();
			$total_tables = count( $monthly_tables );
			
			if ( 0 === $total_tables ) {
				return array(
					'total_tables' => 0,
					'total_size_mb' => 0,
					'total_rows' => 0,
					'oldest_table' => null,
					'newest_table' => null,
					'health_summary' => array(),
				);
			}

			$total_size_mb = 0;
			$total_rows = 0;
			$health_counts = array( 'excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0 );
			
			foreach ( $monthly_tables as $table_info ) {
				$total_size_mb += $table_info['total_size_mb'];
				$total_rows += $table_info['row_count'];
				
				// Get performance stats for health status
				$performance_stats = $this->get_table_performance_stats( $table_info['table_name'] );
				if ( $performance_stats ) {
					$health_status = $performance_stats['health_status'];
					if ( isset( $health_counts[ $health_status ] ) ) {
						$health_counts[ $health_status ]++;
					}
				}
			}

			return array(
				'total_tables' => $total_tables,
				'total_size_mb' => round( $total_size_mb, 2 ),
				'total_rows' => $total_rows,
				'oldest_table' => end( $monthly_tables )['year_month'] ?? null,
				'newest_table' => reset( $monthly_tables )['year_month'] ?? null,
				'health_summary' => $health_counts,
				'average_size_mb' => round( $total_size_mb / $total_tables, 2 ),
				'average_rows' => round( $total_rows / $total_tables ),
			);

		} catch ( \Exception $e ) {
			Logger::error( 
				'Failed to generate management summary: ' . $e->getMessage()
			);
			return array();
		}
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
			$this->error_handler->log_info( $message, $context, 'orderchatz-table-manager' );
		} elseif ( $this->logger ) {
			$this->logger->info( $message, array_merge( array( 'source' => 'orderchatz-table-manager' ), $context ) );
		} else {
			error_log( "[OrderChatz TableManager] {$message}" );
		}
	}

	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
}