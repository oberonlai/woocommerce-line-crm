<?php

declare(strict_types=1);

namespace OrderChatz\Database;

/**
 * Query Optimizer Class
 *
 * Provides cross-month query logic documentation and optimization strategies.
 * Contains the documentation from Task 4.2: 分表索引與查詢優化.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class QueryOptimizer {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Dynamic table manager
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $dynamic_manager;

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 * @param DynamicTableManager $dynamic_manager Dynamic table manager instance
	 */
	public function __construct( \wpdb $wpdb, DynamicTableManager $dynamic_manager ) {
		$this->wpdb = $wpdb;
		$this->dynamic_manager = $dynamic_manager;
	}

	/**
	 * Get cross-month query optimization documentation
	 *
	 * @return array Documentation for cross-month query patterns
	 */
	public function get_query_optimization_guide(): array {
		return array(
			'composite_index_strategy' => array(
				'primary_index' => 'KEY idx_user_date_time (line_user_id, sent_date, sent_time)',
				'description' => 'This composite index is optimized for the common query pattern: "Get latest 20 messages for a specific LINE user, ordered by time"',
			),
			'query_pattern_example' => array(
				'step_1' => array(
					'description' => 'Start with current month table',
					'sql' => "SELECT * FROM wp_otz_messages_2025_08 WHERE line_user_id = 'U1234567890abcdef' ORDER BY sent_date DESC, sent_time DESC LIMIT 20;",
				),
				'step_2' => array(
					'description' => 'If less than 20 records found, query previous months',
					'sql' => "SELECT * FROM wp_otz_messages_2025_07 WHERE line_user_id = 'U1234567890abcdef' ORDER BY sent_date DESC, sent_time DESC LIMIT (20 - found_records);",
				),
				'step_3' => array(
					'description' => 'Continue until 20 records collected or no more tables',
					'logic' => 'Iterative process across monthly partitions',
				),
			),
			'performance_considerations' => array(
				'index_efficiency' => 'Index (line_user_id, sent_date, sent_time) allows efficient sorted access',
				'volume_management' => 'Each monthly table keeps data volume manageable (<1M rows typical)',
				'partition_access' => 'Cross-month queries only access necessary partition tables',
				'sort_optimization' => 'ORDER BY uses index columns in same order for optimal performance',
			),
			'implementation_helper_methods' => array(
				'get_user_messages_paginated' => 'get_user_messages_paginated($line_user_id, $limit, $offset)',
				'get_recent_user_messages' => 'get_recent_user_messages($line_user_id, $count = 20)',
				'search_user_messages_across_months' => 'search_user_messages_across_months($line_user_id, $search_term, $date_range)',
			),
			'index_usage_patterns' => array(
				'idx_user_date_time' => 'User-specific time-ordered queries (primary use case)',
				'idx_sender_type' => 'Filter messages by User vs Account sender',
				'idx_sent_date' => 'Date-range queries across all users',
				'idx_created_at' => 'Administrative queries for record tracking',
			),
			'timezone_considerations' => array(
				'storage_timezone' => 'sent_date/sent_time should be stored in consistent timezone (UTC recommended)',
				'partition_boundaries' => 'Partition boundaries are based on sent_date, not created_at',
				'cross_timezone_queries' => 'Cross-timezone queries may need to check adjacent month tables',
			),
		);
	}

	/**
	 * Get recent messages for a user across multiple months
	 * Example implementation of cross-month query logic
	 *
	 * @param string $line_user_id LINE User ID
	 * @param int    $limit        Number of messages to retrieve
	 * @return array Array of message records
	 */
	public function get_recent_user_messages( string $line_user_id, int $limit = 20 ): array {
		$messages = array();
		$remaining = $limit;
		$existing_tables = $this->dynamic_manager->get_existing_monthly_tables();

		foreach ( $existing_tables as $year_month ) {
			if ( $remaining <= 0 ) {
				break;
			}

			$table_name = $this->dynamic_manager->get_monthly_message_table_name( $year_month );
			if ( ! $table_name ) {
				continue;
			}

			$query = $this->wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE line_user_id = %s ORDER BY sent_date DESC, sent_time DESC LIMIT %d",
				$line_user_id,
				$remaining
			);

			$monthly_messages = $this->wpdb->get_results( $query, ARRAY_A );
			
			if ( $monthly_messages ) {
				$messages = array_merge( $messages, $monthly_messages );
				$remaining -= count( $monthly_messages );
			}
		}

		return $messages;
	}

	/**
	 * Get query performance recommendations
	 *
	 * @return array Performance recommendations
	 */
	public function get_performance_recommendations(): array {
		return array(
			'query_patterns' => array(
				'recommended' => array(
					'Use composite index order in WHERE and ORDER BY clauses',
					'Query specific monthly tables when date range is known',
					'Limit cross-month queries to necessary partitions only',
					'Use LIMIT to prevent excessive data retrieval',
				),
				'avoid' => array(
					'Full table scans across multiple months',
					'ORDER BY columns not in index',
					'Complex JOINs across monthly partitions',
					'Queries without line_user_id when possible',
				),
			),
			'index_maintenance' => array(
				'Monitor index usage with EXPLAIN plans',
				'Consider additional indexes based on query patterns',
				'Regular table statistics updates',
				'Partition pruning optimization',
			),
			'data_archival' => array(
				'Plan for old table archival or deletion',
				'Consider data retention policies',
				'Monitor table sizes and growth',
				'Implement backup strategies for historical data',
			),
		);
	}
}