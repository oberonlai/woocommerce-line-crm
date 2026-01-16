<?php
/**
 * 查詢顧客歷史訂單 Function
 *
 * @package OrderChatz\Services\Bot\Functions
 * @since 1.1.6
 */

declare(strict_types=1);

namespace OrderChatz\Services\Bot\Functions;

use OrderChatz\Database\User;
use OrderChatz\Util\Logger;

/**
 * 查詢顧客歷史訂單類別
 */
class CustomerOrders implements FunctionInterface {

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
		return 'customer_orders';
	}

	/**
	 * 取得函式描述
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '查詢顧客的歷史訂單。當顧客詢問關於「訂單」、「購買記錄」、「購物紀錄」、「我買過什麼」、「歷史訂單」時使用此工具。可根據訂單編號、訂單狀態、日期範圍進行搜尋。注意：customer_id 會由系統自動提供，無需在參數中指定。';
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
				'limit'        => array(
					'type'        => 'integer',
					'description' => '返回的訂單數量限制，預設為 5',
				),
				'status'       => array(
					'type'        => 'string',
					'description' => '訂單狀態篩選，例如：pending（待付款）、processing（處理中）、completed（已完成）、cancelled（已取消）',
				),
				'order_number' => array(
					'type'        => 'string',
					'description' => '訂單編號搜尋',
				),
				'date_from'    => array(
					'type'        => 'string',
					'description' => '開始日期，格式：YYYY-MM-DD',
				),
				'date_to'      => array(
					'type'        => 'string',
					'description' => '結束日期，格式：YYYY-MM-DD',
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
					'error'   => '請先綁定會員後即可查詢訂單記錄。',
				);
			}

			// 3. 檢查 WooCommerce 是否啟用.
			if ( ! class_exists( 'WooCommerce' ) ) {
				return array(
					'success' => false,
					'error'   => '訂單查詢功能目前無法使用。',
				);
			}

			// 4. 組裝查詢篩選條件.
			$filters = array(
				'limit'        => isset( $arguments['limit'] ) ? (int) $arguments['limit'] : 5,
				'status'       => $arguments['status'] ?? null,
				'order_number' => $arguments['order_number'] ?? null,
				'date_from'    => $arguments['date_from'] ?? null,
				'date_to'      => $arguments['date_to'] ?? null,
			);

			// 5. 查詢訂單.
			$orders = $this->query_orders( (int) $wp_user_id, $filters );

			// 6. 檢查查詢結果.
			if ( empty( $orders ) ) {
				return array(
					'success' => true,
					'data'    => array(
						'orders' => array(),
						'total'  => 0,
					),
				);
			}

			// 7. 格式化訂單資料.
			$formatted_orders = array();
			foreach ( $orders as $order ) {
				$formatted_orders[] = $this->format_order_data( $order );
			}

			return array(
				'success' => true,
				'data'    => array(
					'orders' => $formatted_orders,
					'total'  => count( $formatted_orders ),
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'CustomerOrders execution error: ' . $e->getMessage(),
				array(
					'arguments' => $arguments,
					'exception' => $e->getMessage(),
				),
				'otz'
			);

			return array(
				'success' => false,
				'error'   => '查詢訂單時發生錯誤。',
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

		// 驗證 limit 必須為數值.
		if ( isset( $arguments['limit'] ) && ! is_numeric( $arguments['limit'] ) ) {
			return false;
		}

		// 驗證日期格式.
		if ( isset( $arguments['date_from'] ) && ! $this->is_valid_date( $arguments['date_from'] ) ) {
			return false;
		}

		if ( isset( $arguments['date_to'] ) && ! $this->is_valid_date( $arguments['date_to'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 驗證日期格式是否為 Y-m-d
	 *
	 * @param string $date 日期字串.
	 * @return bool
	 */
	private function is_valid_date( string $date ): bool {
		$parsed_date = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $parsed_date && $parsed_date->format( 'Y-m-d' ) === $date;
	}

	/**
	 * 查詢顧客訂單
	 *
	 * @param int   $wp_user_id WordPress User ID.
	 * @param array $filters 篩選條件.
	 * @return array WC_Order 物件陣列.
	 */
	private function query_orders( int $wp_user_id, array $filters ): array {
		// 組裝 wc_get_orders 參數.
		$args = array(
			'customer_id' => $wp_user_id,
			'limit'       => $filters['limit'],
			'orderby'     => 'date',
			'order'       => 'DESC',
		);

		// 加入狀態篩選.
		if ( ! empty( $filters['status'] ) ) {
			$args['status'] = $filters['status'];
		}

		// 加入日期範圍篩選.
		if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) {
			$args['date_created'] = $this->build_date_query( $filters['date_from'], $filters['date_to'] );
		}

		// 查詢訂單.
		$orders = wc_get_orders( $args );

		// 如果有訂單編號篩選，需要額外處理.
		if ( ! empty( $filters['order_number'] ) ) {
			$orders = $this->filter_by_order_number( $orders, $filters['order_number'] );
		}

		return $orders;
	}

	/**
	 * 建立日期查詢條件
	 *
	 * @param string|null $date_from 開始日期.
	 * @param string|null $date_to 結束日期.
	 * @return string 日期查詢字串.
	 */
	private function build_date_query( $date_from, $date_to ): string {
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			return $date_from . '...' . $date_to;
		} elseif ( ! empty( $date_from ) ) {
			return '>' . $date_from;
		} elseif ( ! empty( $date_to ) ) {
			return '<' . $date_to;
		}

		return '';
	}

	/**
	 * 根據訂單編號篩選訂單
	 *
	 * @param array  $orders 訂單陣列.
	 * @param string $order_number 訂單編號.
	 * @return array 篩選後的訂單陣列.
	 */
	private function filter_by_order_number( array $orders, string $order_number ): array {
		return array_filter(
			$orders,
			function ( $order ) use ( $order_number ) {
				return strpos( (string) $order->get_order_number(), $order_number ) !== false;
			}
		);
	}

	/**
	 * 格式化訂單資料
	 *
	 * @param \WC_Order $order WooCommerce 訂單物件.
	 * @return array 格式化的訂單資料.
	 */
	private function format_order_data( \WC_Order $order ): array {
		// 基本資訊.
		$order_data = array(
			'order_number' => $order->get_order_number(),
			'status'       => $order->get_status(),
			'status_name'  => wc_get_order_status_name( $order->get_status() ),
			'total'        => $order->get_total(),
			'currency'     => $order->get_currency(),
			'date_created' => $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ),
		);

		// 商品資訊.
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'subtotal' => $item->get_subtotal(),
				'total'    => $item->get_total(),
			);
		}
		$order_data['items'] = $items;

		// 付款資訊.
		$order_data['payment'] = array(
			'method'       => $order->get_payment_method(),
			'method_title' => $order->get_payment_method_title(),
		);

		// 運送資訊.
		$order_data['shipping'] = array(
			'method'  => $order->get_shipping_method(),
			'total'   => $order->get_shipping_total(),
			'address' => $this->format_shipping_address( $order ),
		);

		// 訂單連結.
		$order_data['permalink'] = $order->get_view_order_url();

		return $order_data;
	}

	/**
	 * 格式化運送地址
	 *
	 * @param \WC_Order $order WooCommerce 訂單物件.
	 * @return string 格式化的地址字串.
	 */
	private function format_shipping_address( \WC_Order $order ): string {
		$address_parts = array(
			$order->get_shipping_country(),
			$order->get_shipping_state(),
			$order->get_shipping_city(),
			$order->get_shipping_address_1(),
			$order->get_shipping_address_2(),
		);

		// 過濾空值並組合.
		$address_parts = array_filter( $address_parts );
		return implode( ' ', $address_parts );
	}
}
