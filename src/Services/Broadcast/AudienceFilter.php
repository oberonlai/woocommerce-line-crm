<?php
/**
 * OrderChatz 受眾篩選服務
 *
 * 處理推播受眾的篩選邏輯，包含傳統篩選和動態條件篩選.
 *
 * @package OrderChatz\Services\Broadcast
 * @since 1.0.0
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast;

use Exception;

/**
 * 受眾篩選類別
 */
class AudienceFilter {

	/**
	 * 計算動態條件篩選受眾數量
	 *
	 * @param array $dynamic_conditions 動態篩選條件.
	 *
	 * @return int 數量.
	 */
	public function calculate_dynamic_audience_count( array $dynamic_conditions ): int {
		global $wpdb;

		if ( empty( $dynamic_conditions['conditions'] ) ) {
			// 沒有條件則返回所有用戶.
			$query = "SELECT COUNT(*) FROM {$wpdb->prefix}otz_users WHERE status = 'active' AND line_user_id IS NOT NULL AND line_user_id != ''";
			$count = intval( $wpdb->get_var( $query ) );

			return $count;
		}

		$query_builder = new FilterQueryBuilder();
		$query         = $query_builder->build_query( $dynamic_conditions, true );
		return intval( $wpdb->get_var( $query ) );
	}

	/**
	 * 取得動態篩選後的好友列表
	 *
	 * @param array    $dynamic_conditions 動態篩選條件.
	 * @param int|null $limit              限制數量.
	 * @param int      $offset             偏移量.
	 *
	 * @return array 好友列表.
	 */
	public function get_dynamic_filtered_friends( array $dynamic_conditions, int $limit = null, int $offset = 0 ): array {
		global $wpdb;

		if ( empty( $dynamic_conditions['conditions'] ) ) {
			$query = "SELECT line_user_id, display_name as name, avatar_url as picture_url FROM {$wpdb->prefix}otz_users WHERE status = 'active' AND line_user_id IS NOT NULL AND line_user_id != ''";
			if ( $limit ) {
				$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
			}

			return $wpdb->get_results( $query, ARRAY_A );
		}

		$query_builder = new FilterQueryBuilder();
		$query         = $query_builder->build_query( $dynamic_conditions, false, $limit, $offset );

		return $wpdb->get_results( $query, ARRAY_A );
	}

}
