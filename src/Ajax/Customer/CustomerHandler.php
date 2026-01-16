<?php
/**
 * 客戶處理 AJAX 處理器
 *
 * 處理客戶資訊相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Customer
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Customer;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;
use OrderChatz\Ajax\Customer\Note;
use OrderChatz\Database\User;

class CustomerHandler extends AbstractAjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_otz_get_customer_info', array( $this, 'getCustomerInfo' ) );
		add_action( 'wp_ajax_otz_switch_to_manual_reply', array( $this, 'switchToManualReply' ) );
	}

	/**
	 * 取得客戶資訊
	 */
	public function getCustomerInfo() {
		try {
			// 檢查用戶權限
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			$this->verifyNonce();

			$line_user_id = sanitize_text_field( $_POST['line_user_id'] ?? '' );

			if ( empty( $line_user_id ) ) {
				throw new Exception( 'LINE User ID 不能為空' );
			}

			$customer_info = $this->queryCustomerInfo( $line_user_id );

			$this->sendSuccess( $customer_info );

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 查詢客戶資訊
	 */
	private function queryCustomerInfo( $line_user_id ) {
		global $wpdb;

		$user_table = $wpdb->prefix . 'otz_users';

		$line_user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$user_table} WHERE line_user_id = %s",
				$line_user_id
			)
		);

		if ( ! $line_user ) {
			throw new Exception( '找不到用戶資料' );
		}

		// 建立 Note 實例來取得客戶備註
		$note_handler = new Note();

		$customer_info = array(
			'line_user_id' => $line_user_id,
			'wp_user_id'   => $line_user->wp_user_id,
			'bot_status'   => $line_user->bot_status,
			'user_data'    => null,
			'orders'       => array(),
			'tags'         => array(),
			'notes'        => $note_handler->getCustomerNotes( $line_user_id ),
		);

		if ( ! empty( $line_user->wp_user_id ) ) {
			$wp_user = get_user_by( 'ID', $line_user->wp_user_id );
			if ( $wp_user ) {
				$customer_info['user_data'] = array(
					'display_name'    => $wp_user->display_name,
					'user_email'      => $wp_user->user_email,
					'user_registered' => date_i18n( 'Y-m-d', strtotime( $wp_user->user_registered ) ),
					'billing_phone'   => get_user_meta( $wp_user->ID, 'billing_phone', true ),
					'phone'           => get_user_meta( $wp_user->ID, 'phone', true ),
				);

				if ( class_exists( 'WooCommerce' ) ) {
					$customer_info['orders'] = $this->getCustomerOrders( $wp_user->ID );
				}
			}
		}

		// 取得客戶標籤.
		$tag_handler = new Tag();
		$customer_info['tags'] = $tag_handler->get_user_tags_with_count( $line_user_id );

		return $customer_info;
	}

	/**
	 * 取得客戶訂單資訊
	 */
	private function getCustomerOrders( $user_id, $limit = 10 ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$order_data = array();
		foreach ( $orders as $order ) {
			$order_data[] = array(
				'id'           => $order->get_id(),
				'order_number' => $order->get_order_number(),
				'status'       => $order->get_status(),
				'status_name'  => wc_get_order_status_name( $order->get_status() ),
				'total'        => $order->get_total(),
				'date_created' => $order->get_date_created()->date_i18n( 'Y-m-d H:i:s' ),
			);
		}

		return $order_data;
	}

	/**
	 * 切換為手動回覆模式
	 *
	 * 將使用者的 bot_status 從 'enable' 切換為 'disable'.
	 */
	public function switchToManualReply() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			$this->verifyNonce();

			$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

			if ( empty( $line_user_id ) ) {
				throw new Exception( 'LINE User ID 不能為空' );
			}

			// 取得使用者 ID.
			global $wpdb;
			$user_table = $wpdb->prefix . 'otz_users';

			$user = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, bot_status FROM {$user_table} WHERE line_user_id = %s",
					$line_user_id
				)
			);

			if ( ! $user ) {
				throw new Exception( '找不到用戶資料' );
			}

			// 檢查當前狀態是否為 enable.
			if ( 'enable' !== $user->bot_status ) {
				throw new Exception( '當前已是手動回覆模式' );
			}

			// 更新 bot_status 為 disable.
			$user_model     = new User( $wpdb );
			$update_success = $user_model->update_bot_status( (int) $user->id, 'disable' );

			if ( ! $update_success ) {
				throw new Exception( '更新狀態失敗' );
			}

			$this->sendSuccess(
				array(
					'message'    => '已切換為手動回覆模式',
					'bot_status' => 'disable',
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

}
