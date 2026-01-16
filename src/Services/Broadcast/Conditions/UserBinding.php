<?php
/**
 * 會員綁定狀態篩選條件
 *
 * 根據好友是否已綁定 WordPress 會員帳號進行篩選.
 *
 * 篩選邏輯：
 * - 已綁定：wp_user_id > 0
 * - 未綁定：wp_user_id IS NULL 或 wp_user_id = 0
 *
 * @package OrderChatz\Services\Broadcast\Conditions
 * @since 1.1.4
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast\Conditions;

use OrderChatz\Services\Broadcast\FilterConditionInterface;

/**
 * 會員綁定狀態篩選條件類別
 */
class UserBinding implements FilterConditionInterface {

	/**
	 * 取得條件類型
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'user_binding';
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

		if ( 'equal' === $operator ) {
			return $this->build_equal_sql( $value );
		}

		return null;
	}

	/**
	 * 建構 equal SQL（會員綁定狀態等於指定值）
	 *
	 * @param string $value 綁定狀態值（'bound' 或 'unbound'）.
	 * @return string
	 */
	private function build_equal_sql( string $value ): string {
		if ( 'bound' === $value ) {
			// 已綁定：wp_user_id > 0.
			return 'u.wp_user_id > 0';
		}

		if ( 'unbound' === $value ) {
			// 未綁定：wp_user_id IS NULL 或 wp_user_id = 0.
			return '(u.wp_user_id IS NULL OR u.wp_user_id = 0)';
		}

		return '1=0'; // 無效值，返回永遠為 false 的條件.
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

		// 只支援 equal 操作符.
		if ( 'equal' !== $operator ) {
			return false;
		}

		// 值必須是 'bound' 或 'unbound'.
		return in_array( $value, array( 'bound', 'unbound' ), true );
	}

	/**
	 * 取得支援的操作符
	 *
	 * @return array
	 */
	public function get_supported_operators(): array {
		return array( 'equal' );
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
			'type'            => 'user_binding',
			'label'           => __( '會員綁定狀態', 'otz' ),
			'icon'            => 'dashicons-admin-users',
			'operators'       => array(
				'equal' => array(
					'label'       => __( '等於', 'otz' ),
					'description' => __( '好友的會員綁定狀態等於指定值', 'otz' ),
				),
			),
			'value_component' => 'SelectRenderer',
			'value_config'    => array(
				'options' => array(
					array(
						'value' => 'bound',
						'label' => __( '已綁定', 'otz' ),
					),
					array(
						'value' => 'unbound',
						'label' => __( '未綁定', 'otz' ),
					),
				),
			),
		);
	}

}
