<?php
/**
 * 訂單處理 AJAX 處理器
 *
 * 處理訂單相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Order
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Order;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;

class OrderHandler extends AbstractAjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_otz_get_order_detail', array( $this, 'getOrderDetail' ) );
		add_action( 'wp_ajax_otz_get_order_settings', array( $this, 'getOrderSettings' ) );
		add_action( 'wp_ajax_otz_save_order_settings', array( $this, 'saveOrderSettings' ) );
		add_action( 'wp_ajax_otz_search_customer_orders', array( $this, 'searchCustomerOrders' ) );
	}

	/**
	 * 取得訂單詳細資訊
	 */
	public function getOrderDetail() {
		try {
			$this->verifyNonce();

			$order_id = intval( $_POST['order_id'] ?? 0 );

			if ( ! $order_id ) {
				$this->sendError( '無效的訂單 ID' );
				return;
			}

			if ( ! class_exists( 'WooCommerce' ) ) {
				$this->sendError( 'WooCommerce 未安裝或未啟用' );
				return;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->sendError( '找不到指定的訂單' );
				return;
			}

			$order_data = $this->formatOrderDetail( $order );

			$this->sendSuccess( $order_data );

		} catch ( Exception $e ) {
			$this->logError( 'OrderChatz getOrderDetail 錯誤: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 格式化訂單詳細資料
	 */
	private function formatOrderDetail( $order ) {
		$order_data = array(
			'id'            => $order->get_id(),
			'order_number'  => $order->get_order_number(),
			'status'        => $order->get_status(),
			'status_name'   => wc_get_order_status_name( $order->get_status() ),
			'date_created'  => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
			'total'         => $order->get_total(),
			'currency'      => $order->get_currency(),
		);

		$order_data['customer_name']  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$order_data['customer_email'] = $order->get_billing_email();
		$order_data['customer_phone'] = $order->get_billing_phone();

		$billing_address = array();
		if ( $order->get_billing_address_1() ) {
			$billing_address[] = $order->get_billing_address_1();
		}
		if ( $order->get_billing_address_2() ) {
			$billing_address[] = $order->get_billing_address_2();
		}
		if ( $order->get_billing_city() ) {
			$billing_address[] = $order->get_billing_city();
		}
		if ( $order->get_billing_state() ) {
			$billing_address[] = $order->get_billing_state();
		}
		if ( $order->get_billing_postcode() ) {
			$billing_address[] = $order->get_billing_postcode();
		}
		if ( $order->get_billing_country() ) {
			$billing_address[] = WC()->countries->countries[ $order->get_billing_country() ] ?? $order->get_billing_country();
		}
		$order_data['billing_address'] = implode( ', ', $billing_address );

		$shipping_address = array();
		if ( $order->get_shipping_address_1() ) {
			$shipping_address[] = $order->get_shipping_address_1();
		}
		if ( $order->get_shipping_address_2() ) {
			$shipping_address[] = $order->get_shipping_address_2();
		}
		if ( $order->get_shipping_city() ) {
			$shipping_address[] = $order->get_shipping_city();
		}
		if ( $order->get_shipping_state() ) {
			$shipping_address[] = $order->get_shipping_state();
		}
		if ( $order->get_shipping_postcode() ) {
			$shipping_address[] = $order->get_shipping_postcode();
		}
		if ( $order->get_shipping_country() ) {
			$shipping_address[] = WC()->countries->countries[ $order->get_shipping_country() ] ?? $order->get_shipping_country();
		}
		$order_data['shipping_address'] = empty( $shipping_address ) ? $order_data['billing_address'] : implode( ', ', $shipping_address );

		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'price'    => $order->get_formatted_line_subtotal( $item ),
				'total'    => $item->get_total(),
			);
		}
		$order_data['items'] = $items;

		$order_data['order_notes'] = $order->get_customer_note();

		$order_data['payment_method'] = $order->get_payment_method();
		$order_data['payment_method_title'] = $order->get_payment_method_title();
		$order_data['date_paid'] = $order->get_date_paid() ? $order->get_date_paid()->format( 'Y-m-d H:i:s' ) : '';

		$shipping_methods = array();
		foreach ( $order->get_shipping_methods() as $shipping_method ) {
			$shipping_methods[] = $shipping_method->get_name();
		}
		$order_data['shipping_method'] = implode( ', ', $shipping_methods );

		$order_data['custom_fields'] = $this->getCustomOrderFields( $order );

		return $order_data;
	}

	/**
	 * 取得自訂訂單欄位
	 */
	private function getCustomOrderFields( $order ) {
		$custom_fields = array();
		
		$custom_field_settings = get_option( 'otz_order_custom_fields', array() );
		
		if ( ! empty( $custom_field_settings ) ) {
			foreach ( $custom_field_settings as $field_setting ) {
				$field_key = $field_setting['key'];
				$field_name = $field_setting['name'];
				
				$field_value = $order->get_meta( $field_key );
				
				if ( ! empty( $field_value ) ) {
					if ( is_array( $field_value ) ) {
						$custom_fields[] = array(
							'name'  => $field_name,
							'value' => $field_value,
							'key'   => $field_key
						);
					} else {
						$custom_fields[] = array(
							'name'  => $field_name,
							'value' => $field_value,
							'key'   => $field_key
						);
					}
				}
			}
		}

		return $custom_fields;
	}

	/**
	 * 取得自訂欄位設定
	 */
	public function getOrderSettings() {
		try {
			$this->verifyNonce();

			$settings = get_option( 'otz_order_custom_fields', array() );

			$this->sendSuccess( $settings );

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 儲存自訂欄位設定
	 */
	public function saveOrderSettings() {
		try {
			$this->verifyNonce();

			$settings_json = stripslashes( $_POST['settings'] ?? '' );
			
			$settings = json_decode( $settings_json, true );

			if ( ! is_array( $settings ) ) {
				$this->sendError( '無效的設定資料格式' );
				return;
			}

			$validated_settings = array();
			foreach ( $settings as $setting ) {
				if ( isset( $setting['name'] ) && isset( $setting['key'] ) ) {
					$name = sanitize_text_field( $setting['name'] );
					$key = sanitize_key( $setting['key'] );
					
					if ( ! empty( $name ) && ! empty( $key ) ) {
						if ( preg_match( '/^[a-zA-Z0-9_-]+$/', $key ) ) {
							$validated_settings[] = array(
								'name' => $name,
								'key'  => $key
							);
						}
					}
				}
			}

			update_option( 'otz_order_custom_fields', $validated_settings );

			$this->sendSuccess(
				array(
					'message' => '設定已成功儲存',
					'settings' => $validated_settings
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 搜尋客戶訂單
	 */
	public function searchCustomerOrders() {
		try {
			$this->verifyNonce();

			$wp_user_id = intval( $_POST['wp_user_id'] ?? 0 );
			$search_term = sanitize_text_field( $_POST['search_term'] ?? '' );

			if ( ! $wp_user_id ) {
				$this->sendError( '無效的用戶 ID' );
				return;
			}

			if ( empty( $search_term ) ) {
				$this->sendError( '搜尋關鍵字不能為空' );
				return;
			}

			if ( ! class_exists( 'WooCommerce' ) ) {
				$this->sendError( 'WooCommerce 未安裝或未啟用' );
				return;
			}

			$all_orders = wc_get_orders( array(
				'customer_id' => $wp_user_id,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'limit'       => -1,
			) );

			$order_data = array();
			foreach ( $all_orders as $order ) {
				$order_number = $order->get_order_number();
				$order_id = $order->get_id();
				
				if ( stripos( $order_number, $search_term ) !== false || 
					 stripos( (string) $order_id, $search_term ) !== false ) {
					$order_data[] = array(
						'id'           => $order_id,
						'order_number' => $order_number,
						'status'       => $order->get_status(),
						'status_name'  => wc_get_order_status_name( $order->get_status() ),
						'total'        => $order->get_total(),
						'date_created' => $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ),
					);
				}
			}

			$this->sendSuccess( array(
				'orders' => $order_data,
				'found_count' => count( $order_data ),
				'search_term' => $search_term
			) );

		} catch ( Exception $e ) {
			$this->logError( 'OrderChatz searchCustomerOrders 錯誤: ' . $e->getMessage() );
			$this->sendError( '搜尋訂單時發生錯誤：' . $e->getMessage() );
		}
	}
}