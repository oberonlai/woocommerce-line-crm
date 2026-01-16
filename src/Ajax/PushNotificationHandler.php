<?php

declare(strict_types=1);

namespace OrderChatz\Ajax;

use OrderChatz\Services\PushSubscriptionManager;
use OrderChatz\Services\WebPushService;

defined( 'ABSPATH' ) || exit;

/**
 * Push Notification AJAX Handler
 *
 * Handles AJAX requests for push notification subscription management.
 * Extends the base AbstractAjaxHandler for consistent security and validation.
 *
 * @package    OrderChatz
 * @subpackage Ajax
 * @since      1.0.0
 */
class PushNotificationHandler extends AbstractAjaxHandler {

	/**
	 * Push subscription manager
	 *
	 * @var PushSubscriptionManager
	 */
	private PushSubscriptionManager $subscription_manager;

	/**
	 * Web push service
	 *
	 * @var WebPushService
	 */
	private WebPushService $push_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->subscription_manager = new PushSubscriptionManager();
		$this->push_service         = new WebPushService();
	}

	/**
	 * Initialize AJAX handlers
	 *
	 * @return void
	 */
	public static function init(): void {
		$handler = new self();

		// Register AJAX handlers for both logged-in and non-logged-in users
		add_action( 'wp_ajax_otz_push_subscribe', array( $handler, 'handle_subscribe' ) );
		add_action( 'wp_ajax_otz_push_unsubscribe', array( $handler, 'handle_unsubscribe' ) );
		add_action( 'wp_ajax_otz_push_verify', array( $handler, 'handle_verify' ) );
		add_action( 'wp_ajax_otz_push_test', array( $handler, 'handle_test' ) );
		add_action( 'wp_ajax_otz_push_get_subscriptions', array( $handler, 'handle_get_subscriptions' ) );
	}

	/**
	 * Handle push notification subscription
	 */
	public function handle_subscribe(): void {
		try {
			// Verify nonce
			$this->verifyNonce( 'otz_push_subscription' );

			// Get subscription data
			$subscription_json = $this->getPostParam( 'subscription', '' );
			if ( empty( $subscription_json ) ) {
				$this->sendError( 'Missing subscription data' );
				return;
			}

			$subscription_data = json_decode( stripslashes( $subscription_json ), true );
			if ( ! $subscription_data ) {
				$this->sendError( 'Invalid subscription data format' );
				return;
			}

			// Ensure user ID is set
			$current_user_id = get_current_user_id();
			if ( $current_user_id > 0 ) {
				$subscription_data['wp_user_id'] = $current_user_id;
			} elseif ( ! isset( $subscription_data['wp_user_id'] ) || empty( $subscription_data['wp_user_id'] ) ) {
				$this->sendError( 'User authentication required' );
				return;
			}

			// Save subscription
			$subscription_id = $this->subscription_manager->save_subscription( $subscription_data );

			if ( $subscription_id === false ) {
				$this->sendError( 'Failed to save subscription' );
				return;
			}

			$this->sendSuccess(
				array(
					'message'         => '推播訂閱已成功儲存',
					'subscription_id' => $subscription_id,
				)
			);

		} catch ( \Exception $e ) {
			$this->logError( 'Push subscription failed: ' . $e->getMessage() );
			$this->sendError( '推播訂閱失敗' );
		}
	}

	/**
	 * Handle push notification unsubscription
	 */
	public function handle_unsubscribe(): void {
		try {
			// Verify nonce
			$this->verifyNonce( 'otz_push_subscription' );

			// Get endpoint
			$endpoint = $this->getPostParam( 'endpoint', '' );
			if ( empty( $endpoint ) ) {
				$this->sendError( 'Missing endpoint' );
				return;
			}

			// Remove subscription
			$success = $this->subscription_manager->delete_subscription_by_endpoint( $endpoint );

			if ( $success ) {
				$this->sendSuccess(
					array(
						'message' => '推播訂閱已取消',
					)
				);
			} else {
				$this->sendError( '取消訂閱失敗' );
			}
		} catch ( \Exception $e ) {
			$this->logError( 'Push unsubscription failed: ' . $e->getMessage() );
			$this->sendError( '取消訂閱失敗' );
		}
	}

	/**
	 * Handle subscription verification
	 */
	public function handle_verify(): void {
		try {
			// Verify nonce
			$this->verifyNonce( 'otz_push_subscription' );

			// Get endpoint
			$endpoint = $this->getPostParam( 'endpoint', '' );
			if ( empty( $endpoint ) ) {
				$this->sendError( 'Missing endpoint' );
				return;
			}

			// Check if subscription exists and is valid
			$subscriptions = $this->subscription_manager->get_all_active_subscriptions();
			$is_valid      = false;

			foreach ( $subscriptions as $subscription ) {
				if ( $subscription['endpoint'] === $endpoint && $subscription['status'] === 'active' ) {
					$is_valid = true;
					break;
				}
			}

			$this->sendSuccess(
				array(
					'valid'   => $is_valid,
					'message' => $is_valid ? '訂閱有效' : '訂閱無效或已過期',
				)
			);

		} catch ( \Exception $e ) {
			$this->logError( 'Subscription verification failed: ' . $e->getMessage() );
			$this->sendError( '訂閱驗證失敗' );
		}
	}

	/**
	 * Handle test notification sending
	 */
	public function handle_test(): void {
		try {
			// Verify nonce and user capabilities
			$this->verifyNonce( 'otz_push_subscription' );

			$current_user_id = get_current_user_id();
			if ( $current_user_id === 0 ) {
				$this->sendError( '需要登入才能發送測試通知' );
				return;
			}

			// Check if push service is ready
			if ( ! $this->push_service->is_ready() ) {
				$this->sendError( '推播服務未準備就緒' );
				return;
			}

			// Send test notification
			$success = $this->push_service->send_test_notification( $current_user_id );

			if ( $success ) {
				$this->sendSuccess(
					array(
						'message' => '測試通知已發送',
					)
				);
			} else {
				$this->sendError( '測試通知發送失敗' );
			}
		} catch ( \Exception $e ) {
			$this->logError( 'Test notification failed: ' . $e->getMessage() );
			$this->sendError( '測試通知發送失敗' );
		}
	}

	/**
	 * Handle getting user subscriptions
	 */
	public function handle_get_subscriptions(): void {
		try {
			// Verify nonce and admin capabilities
			$this->verifyNonce( 'otz_push_subscription' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				$this->sendError( '權限不足' );
				return;
			}

			// Get all subscriptions or user-specific subscriptions
			$user_id = $this->getPostParam( 'user_id', 0 );

			if ( $user_id > 0 ) {
				$subscriptions = $this->subscription_manager->get_user_subscriptions( $user_id );
			} else {
				$subscriptions = $this->subscription_manager->get_all_active_subscriptions();
			}

			// Format subscription data for display
			$formatted_subscriptions = array();
			foreach ( $subscriptions as $subscription ) {
				$formatted_subscriptions[] = array(
					'id'            => $subscription['id'],
					'user_id'       => $subscription['wp_user_id'],
					'line_user_id'  => $subscription['line_user_id'],
					'device_type'   => $subscription['device_type'],
					'subscribed_at' => $subscription['subscribed_at'],
					'last_used_at'  => $subscription['last_used_at'],
					'status'        => $subscription['status'],
				);
			}

			$this->sendSuccess(
				array(
					'subscriptions' => $formatted_subscriptions,
					'count'         => count( $formatted_subscriptions ),
				)
			);

		} catch ( \Exception $e ) {
			$this->logError( 'Get subscriptions failed: ' . $e->getMessage() );
			$this->sendError( '取得訂閱列表失敗' );
		}
	}

	/**
	 * Handle sending push notification to specific user
	 */
	public function handle_send_notification(): void {
		try {
			// Verify nonce and admin capabilities
			$this->verifyNonce( 'otz_push_subscription' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				$this->sendError( '權限不足' );
				return;
			}

			// Get notification parameters
			$user_id = $this->getPostParam( 'user_id', 0 );
			$title   = $this->getPostParam( 'title', '' );
			$message = $this->getPostParam( 'message', '' );
			$url     = $this->getPostParam( 'url', '' );

			if ( $user_id === 0 || empty( $title ) || empty( $message ) ) {
				$this->sendError( '缺少必要參數' );
				return;
			}

			// Create notification payload
			$payload = $this->push_service->create_notification_payload(
				sanitize_text_field( $title ),
				wp_kses_post( wp_unslash( $message ) ),
				null,
				esc_url_raw( $url )
			);

			// Send notification
			$success = $this->push_service->send_to_user( $user_id, $payload );

			if ( $success ) {
				$this->sendSuccess(
					array(
						'message' => '通知已發送',
					)
				);
			} else {
				$this->sendError( '通知發送失敗' );
			}
		} catch ( \Exception $e ) {
			$this->logError( 'Send notification failed: ' . $e->getMessage() );
			$this->sendError( '通知發送失敗' );
		}
	}

	/**
	 * Handle getting subscription statistics
	 */
	public function handle_get_stats(): void {
		try {
			// Verify nonce and admin capabilities
			$this->verifyNonce( 'otz_push_subscription' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				$this->sendError( '權限不足' );
				return;
			}

			// Get subscription statistics
			$stats = $this->subscription_manager->get_subscription_stats();

			$this->sendSuccess(
				array(
					'stats' => $stats,
				)
			);

		} catch ( \Exception $e ) {
			$this->logError( 'Get stats failed: ' . $e->getMessage() );
			$this->sendError( '取得統計資料失敗' );
		}
	}

	/**
	 * Validate subscription data structure
	 *
	 * @param array $data Subscription data
	 * @return bool True if valid
	 */
	private function validate_subscription_data( array $data ): bool {
		$required_fields = array( 'endpoint', 'p256dh_key', 'auth_key' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
				return false;
			}
		}

		// Validate endpoint URL
		if ( ! filter_var( $data['endpoint'], FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Validate key lengths
		if ( strlen( $data['p256dh_key'] ) < 20 || strlen( $data['auth_key'] ) < 10 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get current LINE user ID if available
	 *
	 * @return string|null LINE user ID
	 */
	private function get_current_line_user_id(): ?string {
		$current_user_id = get_current_user_id();
		if ( $current_user_id === 0 ) {
			return null;
		}

		// Try to get LINE user ID from user meta or custom logic
		$line_user_id = get_user_meta( $current_user_id, '_otz_line_user_id', true );

		return ! empty( $line_user_id ) ? $line_user_id : null;
	}
}
