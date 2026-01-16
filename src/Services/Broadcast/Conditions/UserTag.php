<?php
/**
 * 用戶標籤篩選條件
 *
 * 根據 otz_user_tags 表的標籤名稱篩選用戶.
 *
 * @package OrderChatz\Services\Broadcast\Conditions
 * @since 1.1.3
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast\Conditions;

use OrderChatz\Services\Broadcast\FilterConditionInterface;

/**
 * 用戶標籤篩選條件類別
 */
class UserTag implements FilterConditionInterface {

	/**
	 * 取得條件類型
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'user_tag';
	}

	/**
	 * 建構 SQL 條件
	 *
	 * @param array  $condition 條件資料.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @param array  $joins     JOIN 條件陣列.
	 *
	 * @return string|null
	 */
	public function build_sql( array $condition, object $wpdb, array &$joins ): ?string {
		$operator = $condition['operator'];
		$value    = $condition['value'];

		// 統一轉為陣列處理.
		$tag_names = is_array( $value ) ? $value : array( $value );

		if ( 'contains' === $operator ) {
			return $this->build_contains_sql( $tag_names, $wpdb );
		}

		if ( 'contains_all' === $operator ) {
			return $this->build_contains_all_sql( $tag_names, $wpdb );
		}

		if ( 'not_contain' === $operator ) {
			return $this->build_not_contain_sql( $tag_names, $wpdb );
		}

		if ( 'not_contain_all' === $operator ) {
			return $this->build_not_contain_all_sql( $tag_names, $wpdb );
		}

		return null;
	}

	/**
	 * 建構 contains SQL（擁有任一標籤）
	 *
	 * 新資料結構: line_user_ids 是 JSON 陣列,儲存擁有該標籤的所有使用者 ID.
	 * 使用 LIKE 查詢檢查使用者 ID 是否存在於任一標籤的 JSON 陣列中.
	 *
	 * @param array  $tag_names 標籤名稱陣列.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @return string
	 */
	private function build_contains_sql( array $tag_names, object $wpdb ): string {
		$tag_placeholders = implode( ', ', array_fill( 0, count( $tag_names ), '%s' ) );

		// 子查詢:取得符合標籤的 line_user_ids JSON 陣列.
		// 然後在主查詢中用 LIKE 檢查使用者 ID 是否在 JSON 陣列中.
		$subquery = "
			SELECT line_user_ids
			FROM {$wpdb->prefix}otz_user_tags
			WHERE tag_name IN ($tag_placeholders)
		";

		// 使用 EXISTS 檢查使用者 ID 是否在任一標籤的 JSON 陣列中.
		return $wpdb->prepare(
			"EXISTS (
				SELECT 1
				FROM {$wpdb->prefix}otz_user_tags
				WHERE tag_name IN ($tag_placeholders)
				AND line_user_ids LIKE CONCAT('%%\"', u.line_user_id, '\"%%')
			)",
			$tag_names
		);
	}

	/**
	 * 建構 contains_all SQL（同時擁有所有標籤）
	 *
	 * 新資料結構: 需要檢查使用者 ID 是否同時存在於所有指定標籤的 JSON 陣列中.
	 * 使用子查詢計算使用者 ID 在多少個標籤中出現,必須等於標籤總數.
	 *
	 * @param array  $tag_names 標籤名稱陣列.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @return string
	 */
	private function build_contains_all_sql( array $tag_names, object $wpdb ): string {
		$tag_placeholders = implode( ', ', array_fill( 0, count( $tag_names ), '%s' ) );
		$required_count   = count( $tag_names );

		// 計算使用者 ID 在多少個指定標籤中出現.
		// 如果等於標籤總數,表示同時擁有所有標籤.
		$subquery = "
			SELECT
				COUNT(*) as tag_count
			FROM {$wpdb->prefix}otz_user_tags
			WHERE tag_name IN ($tag_placeholders)
			AND line_user_ids LIKE CONCAT('%%\"', u.line_user_id, '\"%%')
		";

		return $wpdb->prepare(
			"($subquery) = %d",
			array_merge( $tag_names, array( $required_count ) )
		);
	}

	/**
	 * 建構 not_contain SQL（完全沒有任一標籤）
	 *
	 * 新資料結構: 檢查使用者 ID 不在任一指定標籤的 JSON 陣列中.
	 * 使用 NOT EXISTS 確保使用者沒有任何指定的標籤.
	 *
	 * @param array  $tag_names 標籤名稱陣列.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @return string
	 */
	private function build_not_contain_sql( array $tag_names, object $wpdb ): string {
		$tag_placeholders = implode( ', ', array_fill( 0, count( $tag_names ), '%s' ) );

		// 使用 NOT EXISTS 檢查使用者 ID 不在任一標籤的 JSON 陣列中.
		return $wpdb->prepare(
			"NOT EXISTS (
				SELECT 1
				FROM {$wpdb->prefix}otz_user_tags
				WHERE tag_name IN ($tag_placeholders)
				AND line_user_ids LIKE CONCAT('%%\"', u.line_user_id, '\"%%')
			)",
			$tag_names
		);
	}

	/**
	 * 建構 not_contain_all SQL（沒有同時擁有所有標籤）
	 *
	 * 新資料結構: 檢查使用者沒有同時擁有所有指定的標籤.
	 * 計算使用者在多少個標籤中出現,如果小於標籤總數,表示沒有全部擁有.
	 *
	 * @param array  $tag_names 標籤名稱陣列.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @return string
	 */
	private function build_not_contain_all_sql( array $tag_names, object $wpdb ): string {
		$tag_placeholders = implode( ', ', array_fill( 0, count( $tag_names ), '%s' ) );
		$required_count   = count( $tag_names );

		// 計算使用者 ID 在多少個指定標籤中出現.
		// 如果小於標籤總數,表示沒有同時擁有所有標籤.
		$subquery = "
			SELECT
				COUNT(*) as tag_count
			FROM {$wpdb->prefix}otz_user_tags
			WHERE tag_name IN ($tag_placeholders)
			AND line_user_ids LIKE CONCAT('%%\"', u.line_user_id, '\"%%')
		";

		return $wpdb->prepare(
			"($subquery) < %d",
			array_merge( $tag_names, array( $required_count ) )
		);
	}

	/**
	 * 驗證條件資料
	 *
	 * @param array $condition 條件資料.
	 * @return bool
	 */
	public function validate( array $condition ): bool {
		if ( ! isset( $condition['operator'], $condition['value'] ) ) {
			return false;
		}

		$operator = $condition['operator'];
		$value    = $condition['value'];

		// contains 和 not_contain: 支援字串或陣列.
		if ( 'contains' === $operator || 'not_contain' === $operator ) {
			if ( is_string( $value ) ) {
				return ! empty( $value );
			}
			if ( is_array( $value ) ) {
				return ! empty( $value );
			}
			return false;
		}

		// contains_all 和 not_contain_all: 必須是陣列且至少 2 個元素.
		if ( 'contains_all' === $operator || 'not_contain_all' === $operator ) {
			return is_array( $value ) && count( $value ) >= 1;
		}

		return false;
	}

	/**
	 * 取得支援的操作符
	 *
	 * @return array
	 */
	public function get_supported_operators(): array {
		return array( 'contains', 'contains_all', 'not_contain', 'not_contain_all' );
	}

	/**
	 * 取得條件所屬群組
	 *
	 * @return string
	 */
	public function get_group(): string {
		return 'user';
	}

	/**
	 * 取得前端 UI 配置
	 *
	 * @return array UI 配置陣列.
	 */
	public function get_ui_config(): array {
		return array(
			'type'            => 'user_tag',
			'label'           => __( '好友標籤', 'otz' ),
			'icon'            => 'dashicons-tag',
			'operators'       => array(
				'contains'        => array(
					'label'       => __( '擁有任一標籤', 'otz' ),
					'description' => __( '客戶擁有任一指定標籤', 'otz' ),
				),
				'contains_all'    => array(
					'label'       => __( '擁有所有標籤', 'otz' ),
					'description' => __( '客戶同時擁有所有指定標籤', 'otz' ),
				),
				'not_contain'     => array(
					'label'       => __( '沒有任一標籤', 'otz' ),
					'description' => __( '客戶完全沒有任一指定標籤', 'otz' ),
				),
				'not_contain_all' => array(
					'label'       => __( '沒有所有標籤', 'otz' ),
					'description' => __( '客戶沒有同時擁有所有指定標籤（可能有部分或完全沒有）', 'otz' ),
				),
			),
			'value_component' => 'TagSelector',
			'value_config'    => array(
				'type'        => 'ajax_select',
				'multiple'    => true,
				'ajax_action' => 'otz_search_customer_tags',
				'placeholder' => __( '搜尋或新增標籤...', 'otz' ),
				'min_input'   => 0,
			),
		);
	}

}
