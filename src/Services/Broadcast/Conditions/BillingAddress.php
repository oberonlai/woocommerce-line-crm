<?php
/**
 * 帳單地址篩選條件
 *
 * 根據用戶個人資料的帳單地址欄位進行模糊比對篩選,支援單一關鍵字搜尋多個地址欄位.
 *
 * 搜尋欄位包含 (wp_usermeta):
 * - billing_country (國家)
 * - billing_state (州/省/縣市)
 * - billing_city (城市)
 * - billing_address_1 (地址第一行)
 * - billing_address_2 (地址第二行)
 *
 * 效能優化重點:
 * 1. 直接查詢用戶資料:從 wp_usermeta 表查詢用戶的帳單地址.
 * 2. 模糊比對:使用 LIKE %keyword% 支援部分關鍵字搜尋.
 * 3. 多欄位搜尋:只要任一地址欄位符合即算中.
 *
 * @package OrderChatz\Services\Broadcast\Conditions
 * @since 1.1.4
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast\Conditions;

use OrderChatz\Services\Broadcast\FilterConditionInterface;

/**
 * 帳單地址篩選條件類別
 */
class BillingAddress implements FilterConditionInterface {

	/**
	 * 取得條件類型
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'billing_address';
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

		if ( 'contains' === $operator ) {
			return $this->build_contains_sql( $value, $wpdb );
		}

		if ( 'not_contain' === $operator ) {
			return $this->build_not_contain_sql( $value, $wpdb );
		}

		return null;
	}

	/**
	 * 建構 contains SQL(帳單地址包含關鍵字)
	 *
	 * 從 wp_usermeta 表查詢用戶的帳單地址,只要任一欄位符合即算中.
	 *
	 * 查詢邏輯:
	 * - 搜尋 5 個帳單地址相關欄位.
	 * - 使用 LIKE 模糊比對.
	 * - 透過 user_id 關聯到 otz_users 表的 wp_user_id.
	 *
	 * @param string $keyword 搜尋關鍵字.
	 * @param object $wpdb    WordPress 資料庫物件.
	 * @return string
	 */
	private function build_contains_sql( string $keyword, object $wpdb ): string {
		$search_pattern = '%' . $wpdb->esc_like( $keyword ) . '%';

		// 從 wp_usermeta 查詢帳單地址.
		$subquery = "
			SELECT DISTINCT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key IN ('billing_country', 'billing_state', 'billing_city', 'billing_address_1', 'billing_address_2')
			  AND meta_value LIKE %s
		";

		return $wpdb->prepare(
			"u.wp_user_id IN ($subquery)",
			$search_pattern
		);
	}

	/**
	 * 建構 not_contain SQL(帳單地址不包含關鍵字)
	 *
	 * 從 wp_usermeta 表查詢用戶的帳單地址,所有欄位都不符合才算中.
	 *
	 * 查詢邏輯:
	 * - 搜尋 5 個帳單地址相關欄位.
	 * - 使用 LIKE 模糊比對.
	 * - 透過 user_id 關聯到 otz_users 表的 wp_user_id.
	 *
	 * @param string $keyword 搜尋關鍵字.
	 * @param object $wpdb    WordPress 資料庫物件.
	 * @return string
	 */
	private function build_not_contain_sql( string $keyword, object $wpdb ): string {
		$search_pattern = '%' . $wpdb->esc_like( $keyword ) . '%';

		// 從 wp_usermeta 查詢帳單地址.
		$subquery = "
			SELECT DISTINCT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key IN ('billing_country', 'billing_state', 'billing_city', 'billing_address_1', 'billing_address_2')
			  AND meta_value LIKE %s
		";

		return $wpdb->prepare(
			"u.wp_user_id NOT IN ($subquery)",
			$search_pattern
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

		// contains 和 not_contain: 必須是非空字串.
		if ( 'contains' === $operator || 'not_contain' === $operator ) {
			return is_string( $value ) && ! empty( trim( $value ) );
		}

		return false;
	}

	/**
	 * 取得支援的操作符
	 *
	 * @return array
	 */
	public function get_supported_operators(): array {
		return array( 'contains', 'not_contain' );
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
			'type'            => 'billing_address',
			'label'           => __( '帳單地址', 'otz' ),
			'icon'            => 'dashicons-location',
			'operators'       => array(
				'contains'    => array(
					'label'       => __( '包含', 'otz' ),
					'description' => __( '帳單地址包含指定關鍵字(搜尋國家、州/省/縣市、城市、地址)', 'otz' ),
				),
				'not_contain' => array(
					'label'       => __( '不包含', 'otz' ),
					'description' => __( '帳單地址不包含指定關鍵字', 'otz' ),
				),
			),
			'value_component' => 'text',
			'value_config'    => array(
				'type'        => 'text',
				'placeholder' => __( '輸入任一地址關鍵字，譬如新北市、中山路', 'otz' ),
			),
		);
	}

}
