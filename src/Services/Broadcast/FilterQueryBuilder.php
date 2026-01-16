<?php
/**
 * 篩選查詢建構器
 *
 * 使用策略模式建構動態篩選條件的 SQL 查詢.
 *
 * @package OrderChatz\Services\Broadcast
 * @since 1.1.3
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast;

use OrderChatz\Util\Logger;

/**
 * 篩選查詢建構器類別
 */
class FilterQueryBuilder {

	/**
	 * 建構函式
	 */
	public function __construct() {
		FilterConditionRegistry::init_default_conditions();
	}

	/**
	 * 建構動態篩選查詢 SQL
	 *
	 * @param array    $dynamic_conditions 動態篩選條件.
	 * @param bool     $count_only 是否只計算數量.
	 * @param int|null $limit 限制數量.
	 * @param int      $offset 偏移量.
	 * @return string SQL 查詢語句.
	 */
	public function build_query( array $dynamic_conditions, bool $count_only = false, ?int $limit = null, int $offset = 0 ): string {
		global $wpdb;

		$select = $count_only
			? 'COUNT(DISTINCT u.line_user_id)'
			: 'DISTINCT u.line_user_id, u.display_name as name, u.avatar_url as picture_url';

		$query = "SELECT {$select} FROM {$wpdb->prefix}otz_users u";

		$joins            = array();
		$group_conditions = array();

		// 處理每個群組（群組之間是 OR）.
		if ( isset( $dynamic_conditions['conditions'] ) && is_array( $dynamic_conditions['conditions'] ) ) {
			foreach ( $dynamic_conditions['conditions'] as $group_id => $conditions ) {
				if ( empty( $conditions ) ) {
					continue;
				}

				$condition_parts = array();

				// 處理群組內的條件（條件之間是 AND）.
				foreach ( $conditions as $condition ) {
					$condition_sql = $this->build_single_condition( $condition, $wpdb, $joins );
					if ( $condition_sql ) {
						$condition_parts[] = $condition_sql;
					}
				}

				if ( ! empty( $condition_parts ) ) {
					$group_conditions[] = '(' . implode( ' AND ', $condition_parts ) . ')';
				}
			}
		}

		// 組合查詢.
		if ( ! empty( $joins ) ) {
			$query .= ' ' . implode( ' ', array_unique( $joins ) );
		}

		$base_condition = "u.status = 'active' AND u.line_user_id IS NOT NULL AND u.line_user_id != ''";
		if ( ! empty( $group_conditions ) ) {
			$query .= " WHERE {$base_condition} AND (" . implode( ' OR ', $group_conditions ) . ')';
		} else {
			$query .= " WHERE {$base_condition}";
		}

		if ( ! $count_only && $limit ) {
			$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		return $query;
	}

	/**
	 * 使用註冊的策略建構單一條件
	 *
	 * @param array  $condition 條件資料.
	 * @param object $wpdb WordPress 資料庫物件.
	 * @param array  $joins JOIN 條件陣列（引用傳遞）.
	 * @return string|null 條件 SQL.
	 */
	private function build_single_condition( array $condition, $wpdb, array &$joins ): ?string {
		$field = $condition['field'] ?? '';

		// 從註冊器取得對應的條件策略.
		$strategy = FilterConditionRegistry::get( $field );

		if ( ! $strategy ) {
			Logger::warning( "Unknown filter condition type: {$field}", array(), 'otz' );
			return null;
		}

		// 驗證條件資料.
		if ( ! $strategy->validate( $condition ) ) {
			Logger::warning( "Invalid condition data for type: {$field}", array(), 'otz' );
			return null;
		}

		// 使用策略建構 SQL.
		return $strategy->build_sql( $condition, $wpdb, $joins );
	}
}
