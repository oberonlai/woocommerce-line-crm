<?php
/**
 * 買過商品篩選條件
 *
 * 根據訂單項目篩選用戶，支援 contains 和 contains_all 操作符.
 * 同時支援簡單商品（_product_id）和可變商品規格（_variation_id）的查詢.
 *
 * 效能優化重點：
 * 1. 訂單狀態過濾：只查詢有效訂單（completed/processing/on-hold），可透過 filter 自訂.
 * 2. 條件優化：使用 > 0 代替 IS NOT NULL，減少 NULL 值處理開銷.
 * 3. 相容性保留：同時支援 HPOS 模式（wc_orders）和傳統模式（posts/postmeta）.
 *
 * @package OrderChatz\Services\Broadcast\Conditions
 * @since 1.1.4
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast\Conditions;

use OrderChatz\Services\Broadcast\FilterConditionInterface;

/**
 * 買過商品篩選條件類別
 */
class OrderProduct implements FilterConditionInterface {

	/**
	 * 取得條件類型
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'order_product_id';
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
		$product_ids = is_array( $value ) ? $value : array( $value );

		if ( 'contains' === $operator ) {
			return $this->build_contains_sql( $product_ids, $wpdb );
		}

		if ( 'contains_all' === $operator ) {
			return $this->build_contains_all_sql( $product_ids, $wpdb );
		}

		if ( 'not_contain' === $operator ) {
			return $this->build_not_contain_sql( $product_ids, $wpdb );
		}

		if ( 'not_contain_all' === $operator ) {
			return $this->build_not_contain_all_sql( $product_ids, $wpdb );
		}

		return null;
	}

	/**
	 * 建構 contains SQL（買過任一商品，可跨訂單）
	 *
	 * 支援簡單商品和可變商品規格的查詢.
	 *
	 * 效能優化：
	 * - 只查詢有效訂單狀態（completed/processing/on-hold）.
	 * - 使用 > 0 代替 IS NOT NULL 減少 NULL 值處理.
	 * - 保留 HPOS 和傳統模式相容性.
	 *
	 * @param array  $product_ids 商品 ID 陣列（支援簡單商品 ID 或規格 ID）.
	 * @param object $wpdb        WordPress 資料庫物件.
	 * @return string
	 */
	private function build_contains_sql( array $product_ids, object $wpdb ): string {
		$product_placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%s' ) );

		// 取得有效的訂單狀態.
		$valid_statuses      = $this->get_valid_order_statuses();
		$status_placeholders = implode( ', ', array_fill( 0, count( $valid_statuses ), '%s' ) );

		$subquery = "
			SELECT DISTINCT
				COALESCE(wco.customer_id, pm_customer.meta_value) as customer_id
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
				ON oi.order_item_id = oim.order_item_id
			LEFT JOIN {$wpdb->prefix}wc_orders wco
				ON oi.order_id = wco.id AND wco.type = 'shop_order'
			LEFT JOIN {$wpdb->postmeta} pm_customer
				ON oi.order_id = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
			LEFT JOIN {$wpdb->posts} p_order
				ON oi.order_id = p_order.ID AND p_order.post_type = 'shop_order'
			WHERE oi.order_item_type = 'line_item'
			  AND oim.meta_key IN ('_product_id', '_variation_id')
			  AND oim.meta_value IN ($product_placeholders)
			  AND (wco.customer_id > 0 OR pm_customer.meta_value > 0)
			  AND (wco.status IN ($status_placeholders) OR p_order.post_status IN ($status_placeholders))
		";

		return $wpdb->prepare(
			"u.wp_user_id IN ($subquery)",
			array_merge( $product_ids, $valid_statuses, $valid_statuses )
		);
	}

	/**
	 * 建構 contains_all SQL（同一訂單內包含所有商品）
	 *
	 * 支援簡單商品和可變商品規格的查詢.
	 *
	 * 效能優化：
	 * - 只查詢有效訂單狀態（completed/processing/on-hold）.
	 * - 使用 > 0 代替 IS NOT NULL 減少 NULL 值處理.
	 * - 保留 HPOS 和傳統模式相容性.
	 *
	 * @param array  $product_ids 商品 ID 陣列（支援簡單商品 ID 或規格 ID）.
	 * @param object $wpdb        WordPress 資料庫物件.
	 * @return string
	 */
	private function build_contains_all_sql( array $product_ids, object $wpdb ): string {
		$product_placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%s' ) );
		$required_count       = count( $product_ids );

		// 取得有效的訂單狀態.
		$valid_statuses      = $this->get_valid_order_statuses();
		$status_placeholders = implode( ', ', array_fill( 0, count( $valid_statuses ), '%s' ) );

		$subquery = "
			SELECT customer_id
			FROM (
				SELECT
					oi.order_id,
					COALESCE(wco.customer_id, pm_customer.meta_value) as customer_id,
					COUNT(DISTINCT oim.meta_value) as matched_count
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
					ON oi.order_item_id = oim.order_item_id
				LEFT JOIN {$wpdb->prefix}wc_orders wco
					ON oi.order_id = wco.id AND wco.type = 'shop_order'
				LEFT JOIN {$wpdb->postmeta} pm_customer
					ON oi.order_id = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
				LEFT JOIN {$wpdb->posts} p_order
					ON oi.order_id = p_order.ID AND p_order.post_type = 'shop_order'
				WHERE oi.order_item_type = 'line_item'
				  AND oim.meta_key IN ('_product_id', '_variation_id')
				  AND oim.meta_value IN ($product_placeholders)
				  AND (wco.customer_id > 0 OR pm_customer.meta_value > 0)
				  AND (wco.status IN ($status_placeholders) OR p_order.post_status IN ($status_placeholders))
				GROUP BY oi.order_id, customer_id
				HAVING matched_count = %d
			) AS matched_orders
		";

		return $wpdb->prepare(
			"u.wp_user_id IN ($subquery)",
			array_merge( $product_ids, $valid_statuses, $valid_statuses, array( $required_count ) )
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

		// contains_all 和 not_contain_all: 必須是陣列且至少 1 個元素.
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
		return 'order';
	}

	/**
	 * 建構 not_contain SQL（完全沒買過任一商品）
	 *
	 * 支援簡單商品和可變商品規格的查詢.
	 *
	 * 效能優化：
	 * - 只查詢有效訂單狀態（completed/processing/on-hold）.
	 * - 使用 > 0 代替 IS NOT NULL 減少 NULL 值處理.
	 * - 保留 HPOS 和傳統模式相容性.
	 *
	 * @param array  $product_ids 商品 ID 陣列（支援簡單商品 ID 或規格 ID）.
	 * @param object $wpdb        WordPress 資料庫物件.
	 * @return string
	 */
	private function build_not_contain_sql( array $product_ids, object $wpdb ): string {
		$product_placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%s' ) );

		// 取得有效的訂單狀態.
		$valid_statuses      = $this->get_valid_order_statuses();
		$status_placeholders = implode( ', ', array_fill( 0, count( $valid_statuses ), '%s' ) );

		$subquery = "
			SELECT DISTINCT
				COALESCE(wco.customer_id, pm_customer.meta_value) as customer_id
			FROM {$wpdb->prefix}woocommerce_order_items oi
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
				ON oi.order_item_id = oim.order_item_id
			LEFT JOIN {$wpdb->prefix}wc_orders wco
				ON oi.order_id = wco.id AND wco.type = 'shop_order'
			LEFT JOIN {$wpdb->postmeta} pm_customer
				ON oi.order_id = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
			LEFT JOIN {$wpdb->posts} p_order
				ON oi.order_id = p_order.ID AND p_order.post_type = 'shop_order'
			WHERE oi.order_item_type = 'line_item'
			  AND oim.meta_key IN ('_product_id', '_variation_id')
			  AND oim.meta_value IN ($product_placeholders)
			  AND (wco.customer_id > 0 OR pm_customer.meta_value > 0)
			  AND (wco.status IN ($status_placeholders) OR p_order.post_status IN ($status_placeholders))
		";

		return $wpdb->prepare(
			"u.wp_user_id NOT IN ($subquery)",
			array_merge( $product_ids, $valid_statuses, $valid_statuses )
		);
	}

	/**
	 * 建構 not_contain_all SQL（沒有在同訂單買齊所有商品）
	 *
	 * 支援簡單商品和可變商品規格的查詢.
	 *
	 * 效能優化：
	 * - 只查詢有效訂單狀態（completed/processing/on-hold）.
	 * - 使用 > 0 代替 IS NOT NULL 減少 NULL 值處理.
	 * - 保留 HPOS 和傳統模式相容性.
	 *
	 * @param array  $product_ids 商品 ID 陣列（支援簡單商品 ID 或規格 ID）.
	 * @param object $wpdb        WordPress 資料庫物件.
	 * @return string
	 */
	private function build_not_contain_all_sql( array $product_ids, object $wpdb ): string {
		$product_placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%s' ) );
		$required_count       = count( $product_ids );

		// 取得有效的訂單狀態.
		$valid_statuses      = $this->get_valid_order_statuses();
		$status_placeholders = implode( ', ', array_fill( 0, count( $valid_statuses ), '%s' ) );

		$subquery = "
			SELECT customer_id
			FROM (
				SELECT
					oi.order_id,
					COALESCE(wco.customer_id, pm_customer.meta_value) as customer_id,
					COUNT(DISTINCT oim.meta_value) as matched_count
				FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
					ON oi.order_item_id = oim.order_item_id
				LEFT JOIN {$wpdb->prefix}wc_orders wco
					ON oi.order_id = wco.id AND wco.type = 'shop_order'
				LEFT JOIN {$wpdb->postmeta} pm_customer
					ON oi.order_id = pm_customer.post_id AND pm_customer.meta_key = '_customer_user'
				LEFT JOIN {$wpdb->posts} p_order
					ON oi.order_id = p_order.ID AND p_order.post_type = 'shop_order'
				WHERE oi.order_item_type = 'line_item'
				  AND oim.meta_key IN ('_product_id', '_variation_id')
				  AND oim.meta_value IN ($product_placeholders)
				  AND (wco.customer_id > 0 OR pm_customer.meta_value > 0)
				  AND (wco.status IN ($status_placeholders) OR p_order.post_status IN ($status_placeholders))
				GROUP BY oi.order_id, customer_id
				HAVING matched_count = %d
			) AS matched_orders
		";

		return $wpdb->prepare(
			"u.wp_user_id NOT IN ($subquery)",
			array_merge( $product_ids, $valid_statuses, $valid_statuses, array( $required_count ) )
		);
	}

	/**
	 * 取得有效的訂單狀態清單
	 *
	 * 預設包含已完成、處理中、保留中的訂單.
	 * 可透過 filter 自訂有效狀態.
	 *
	 * @return array
	 */
	private function get_valid_order_statuses(): array {
		$default_statuses = array(
			'wc-completed',
			'wc-processing',
			'wc-on-hold',
		);

		/**
		 * 過濾有效的訂單狀態
		 *
		 * @param array $statuses 訂單狀態陣列.
		 */
		return apply_filters( 'otz_broadcast_valid_order_statuses', $default_statuses );
	}

	/**
	 * 取得前端 UI 配置
	 *
	 * @return array UI 配置陣列.
	 */
	public function get_ui_config(): array {
		return array(
			'type'            => 'order_product_id',
			'label'           => __( '購買商品條件', 'otz' ),
			'icon'            => 'dashicons-products',
			'operators'       => array(
				'contains'        => array(
					'label'       => __( '買過任一商品', 'otz' ),
					'description' => __( '客戶買過任一指定商品（可跨訂單）', 'otz' ),
				),
				'contains_all'    => array(
					'label'       => __( '買齊所有商品', 'otz' ),
					'description' => __( '客戶在同一訂單內買過所有指定商品', 'otz' ),
				),
				'not_contain'     => array(
					'label'       => __( '未買過任一商品', 'otz' ),
					'description' => __( '客戶從未買過任一指定商品', 'otz' ),
				),
				'not_contain_all' => array(
					'label'       => __( '未買齊所有商品', 'otz' ),
					'description' => __( '客戶沒有在同一訂單內買齊所有指定商品（可能買過部分或完全沒買）', 'otz' ),
				),
			),
			'value_component' => 'ProductSelector',
			'value_config'    => array(
				'type'        => 'ajax_select',
				'multiple'    => true,
				'ajax_action' => 'otz_search_products_for_filter',
				'placeholder' => __( '搜尋商品...', 'otz' ),
				'min_input'   => 0,
			),
		);
	}

}
