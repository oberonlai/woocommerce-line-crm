<?php
/**
 * 查詢顧客基本資訊 Function
 *
 * @package OrderChatz\Services\Bot\Functions
 * @since 1.1.6
 */

declare(strict_types=1);

namespace OrderChatz\Services\Bot\Functions;

use OrderChatz\Database\User;
use OrderChatz\Util\Logger;

/**
 * 查詢顧客基本資訊類別
 */
class CustomerInfo implements FunctionInterface {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * User 資料表處理類別
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * 建構子
	 *
	 * @param \wpdb $wpdb WordPress 資料庫物件.
	 * @param User  $user User 資料表處理物件.
	 */
	public function __construct( \wpdb $wpdb, User $user ) {
		$this->wpdb = $wpdb;
		$this->user = $user;
	}

	/**
	 * 取得函式名稱
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'customer_info';
	}

	/**
	 * 取得函式描述
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Retrieve customer basic information and all user metadata (excluding sensitive data). ' .
			'Use this tool when users ask about their account details, profile information, or membership data. ' .
			'Note: customer_id is automatically provided by the system and does not need to be specified in parameters.';
	}

	/**
	 * 取得參數 Schema
	 *
	 * @return array
	 */
	public function get_parameters_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'query' => array(
					'type'        => 'string',
					'description' => 'Optional query parameter (not used, always leave empty)',
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * 執行函式
	 *
	 * @param array $arguments 函式參數.
	 * @return array
	 */
	public function execute( array $arguments ): array {
		try {
			$customer_id = $arguments['customer_id'] ?? '';

			// 1. 查詢 wp_user_id.
			$user_data  = $this->user->get_user_with_wp_user_id( $customer_id );
			$wp_user_id = $user_data['wp_user_id'] ?? null;

			// 2. 檢查是否綁定會員.
			if ( null === $wp_user_id ) {
				return array(
					'success' => false,
					'error'   => '請先綁定會員後即可查詢會員資料。',
				);
			}

			// 3. 取得 WordPress User 資料.
			$user_data = get_userdata( $wp_user_id );

			if ( ! $user_data ) {
				return array(
					'success' => false,
					'error'   => '找不到該會員資料。請確認您已完成會員綁定，或聯繫客服協助。',
				);
			}

			// 4. 取得所有 User Meta 並過濾機密資訊.
			$all_user_meta = get_user_meta( $wp_user_id );
			$filtered_meta = $this->filter_sensitive_meta( $all_user_meta );

			// 5. 取得 WooCommerce 客戶統計.
			$customer_stats = array();
			if ( class_exists( 'WooCommerce' ) ) {
				$customer       = new \WC_Customer( $wp_user_id );
				$customer_stats = array(
					'total_spent' => $customer->get_total_spent(),
					'order_count' => $customer->get_order_count(),
					'currency'    => get_woocommerce_currency(),
				);
			}

			// 6. 組裝回傳資料.
			return array(
				'success' => true,
				'data'    => array(
					'user_info' => array(
						'ID'              => $user_data->ID,
						'user_login'      => $user_data->user_login,
						'display_name'    => $user_data->display_name,
						'user_email'      => $user_data->user_email,
						'user_registered' => $user_data->user_registered,
					),
					'user_meta'      => $filtered_meta,
					'customer_stats' => $customer_stats,
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'CustomerInfo execution error: ' . $e->getMessage(),
				array(
					'arguments' => $arguments,
					'exception' => $e->getMessage(),
				),
				'otz'
			);

			return array(
				'success' => false,
				'error'   => '查詢會員資料時發生錯誤。',
			);
		}
	}

	/**
	 * 驗證參數
	 *
	 * @param array $arguments 函式參數.
	 * @return bool
	 */
	public function validate( array $arguments ): bool {
		// customer_id 已從 FunctionRegistry 自動注入，無需驗證.
		return true;
	}

	/**
	 * 過濾敏感的 User Meta 資訊
	 *
	 * @param array $all_meta 所有 user meta 資料.
	 * @return array 過濾後的 user meta 資料.
	 */
	private function filter_sensitive_meta( array $all_meta ): array {

		// 定義敏感資訊黑名單.
		$sensitive_keys = array(
			// 安全相關（絕對不可洩露）.
			'session_tokens',
			'activation_key',

			// 權限相關（內部資訊）.
			'wp_capabilities',
			'wp_user_level',

			// 系統內部狀態（無意義或隱私）.
			'dismissed_wp_pointers',
			'wp_dashboard_quick_press_last_post_id',
			'community-events-location',
			'show_welcome_panel',
		);

		// 定義需要用前綴過濾的 pattern.
		$sensitive_patterns = array(
			'wp_user-settings', // 使用者設定.
			'meta-box-order_', // Meta box 順序.
			'woocommerce_admin_', // WooCommerce 管理設定.
			'_woocommerce_',   // WooCommerce 內部資料.
		);

		$filtered = array();

		foreach ( $all_meta as $meta_key => $meta_value ) {

			// 檢查是否在黑名單中.
			if ( in_array( $meta_key, $sensitive_keys, true ) ) {
				continue;
			}

			// 檢查是否符合敏感 pattern.
			$is_sensitive = false;
			foreach ( $sensitive_patterns as $pattern ) {
				if ( strpos( $meta_key, $pattern ) === 0 ) {
					$is_sensitive = true;
					break;
				}
			}

			if ( $is_sensitive ) {
				continue;
			}

			// 保留此 meta，將陣列值取第一個元素（get_user_meta 回傳的是陣列）.
			$filtered[ $meta_key ] = is_array( $meta_value ) && count( $meta_value ) === 1
				? $meta_value[0]
				: $meta_value;
		}

		return $filtered;
	}
}
