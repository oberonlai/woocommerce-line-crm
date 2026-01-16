<?php
/**
 * 標籤貼上次數篩選條件
 *
 * 根據特定標籤被貼上的次數來篩選用戶.
 * 使用字串函數計算 JSON 陣列中標籤出現次數,相容所有 MySQL/MariaDB 版本.
 *
 * @package OrderChatz\Services\Broadcast\Conditions
 * @since 1.1.5
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast\Conditions;

use OrderChatz\Services\Broadcast\FilterConditionInterface;

/**
 * 標籤貼上次數篩選條件類別
 */
class UserTagCount implements FilterConditionInterface {

	/**
	 * 取得條件類型
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'user_tag_count';
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
	 * 取得支援的操作符
	 *
	 * @return array
	 */
	public function get_supported_operators(): array {
		return array( '>=', '<=', '=', '>', '<' );
	}

	/**
	 * 建構 SQL 條件
	 *
	 * 使用字串函數計算標籤在 JSON 陣列中出現的次數.
	 * 原理: (原長度 - 移除後長度) / 搜尋字串長度 = 出現次數.
	 *
	 * @param array  $condition 條件資料.
	 * @param object $wpdb      WordPress 資料庫物件.
	 * @param array  $joins     JOIN 條件陣列.
	 *
	 * @return string|null
	 */
	public function build_sql( array $condition, object $wpdb, array &$joins ): ?string {
		// 從 value 物件中取得 tag_name 和 count.
		$value = $condition['value'] ?? array();

		if ( ! is_array( $value ) ) {
			return null;
		}

		$tag_name = $value['tag_name'] ?? '';
		$count    = isset( $value['count'] ) ? (int) $value['count'] : 0;
		$operator = $condition['operator'] ?? '>=';

		if ( empty( $tag_name ) || $count <= 0 ) {
			return null;
		}

		// 驗證運算符.
		if ( ! in_array( $operator, $this->get_supported_operators(), true ) ) {
			return null;
		}

		// 建構搜尋字串 (JSON 中的格式: "tag_name":"VIP").
		$search_pattern = '"tag_name":"' . $wpdb->esc_like( $tag_name ) . '"';
		$pattern_length = strlen( $search_pattern );

		// 計算 tag_name 在 tags JSON 中出現的次數.
		// COALESCE 確保 tags 為 NULL 時使用空陣列 '[]'.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"(CHAR_LENGTH(COALESCE(u.tags, '[]')) - CHAR_LENGTH(REPLACE(COALESCE(u.tags, '[]'), %s, ''))) / %d {$operator} %d",
			$search_pattern,
			$pattern_length,
			$count
		);

		return $sql;
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

		// 驗證運算符.
		if ( ! in_array( $condition['operator'], $this->get_supported_operators(), true ) ) {
			return false;
		}

		// 驗證 value 格式.
		$value = $condition['value'];
		if ( ! is_array( $value ) ) {
			return false;
		}

		// 驗證必要欄位.
		if ( empty( $value['tag_name'] ) ) {
			return false;
		}

		if ( ! isset( $value['count'] ) || ! is_numeric( $value['count'] ) ) {
			return false;
		}

		if ( (int) $value['count'] <= 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * 取得前端 UI 配置
	 *
	 * @return array UI 配置陣列.
	 */
	public function get_ui_config(): array {
		return array(
			'type'            => 'user_tag_count',
			'label'           => __( '標籤貼上次數', 'otz' ),
			'group'           => 'user',
			'icon'            => 'dashicons-marker',
			'operators'       => array(
				'>=' => array(
					'label'       => __( '大於等於', 'otz' ),
					'description' => __( '標籤被貼上的次數大於等於指定值', 'otz' ),
				),
				'<=' => array(
					'label'       => __( '小於等於', 'otz' ),
					'description' => __( '標籤被貼上的次數小於等於指定值', 'otz' ),
				),
				'='  => array(
					'label'       => __( '等於', 'otz' ),
					'description' => __( '標籤被貼上的次數等於指定值', 'otz' ),
				),
				'>'  => array(
					'label'       => __( '大於', 'otz' ),
					'description' => __( '標籤被貼上的次數大於指定值', 'otz' ),
				),
				'<'  => array(
					'label'       => __( '小於', 'otz' ),
					'description' => __( '標籤被貼上的次數小於指定值', 'otz' ),
				),
			),
			'value_component' => 'TagCountRenderer',
			'value_config'    => array(
				'ajax_action' => 'otz_search_customer_tags',
				'placeholder' => array(
					'tag'   => __( '選擇標籤...', 'otz' ),
					'count' => __( '次數', 'otz' ),
				),
			),
		);
	}
}
