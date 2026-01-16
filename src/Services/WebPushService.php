<?php

declare(strict_types=1);

namespace OrderChatz\Services;

use OrderChatz\Util\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Web Push Service
 *
 * Handles web push notification sending using WordPress HTTP API.
 * Replacement for Minishlink WebPush to avoid Guzzle conflicts
 *
 * @package    OrderChatz
 * @subpackage Services
 * @since      1.0.0
 */
class WebPushService {

	/**
	 * Third-party API endpoint for web push
	 *
	 * @var string
	 */
	private string $api_endpoint = 'https://push-e0r.pages.dev/api/send';

	/**
	 * VAPID key manager
	 *
	 * @var VapidKeyManager
	 */
	private VapidKeyManager $vapid_manager;

	/**
	 * Push subscription manager
	 *
	 * @var PushSubscriptionManager
	 */
	private PushSubscriptionManager $subscription_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->vapid_manager        = new VapidKeyManager();
		$this->subscription_manager = new PushSubscriptionManager();
	}

	/**
	 * Send notification to a single subscription
	 *
	 * @param array  $subscription_data Subscription data
	 * @param string $payload           Notification payload (JSON string)
	 * @return bool True on success, false on failure
	 */
	public function send_notification( array $subscription_data, string $payload ): bool {

		try {
			// Validate subscription data.
			if ( ! $this->subscription_manager->validate_subscription( $subscription_data ) ) {
				Logger::error( 'Invalid subscription data', $subscription_data );
				return false;
			}

			// Prepare subscription data for third-party API
			$subscription = array(
				'endpoint' => $subscription_data['endpoint'],
				'keys'     => array(
					'p256dh' => $subscription_data['p256dh_key'],
					'auth'   => $subscription_data['auth_key'],
				),
			);

			// Send the notification via third-party API.
			$result = $this->send_to_api( $subscription, $payload );

			if ( $result && ! is_wp_error( $result ) ) {
				$response_code = wp_remote_retrieve_response_code( $result );
				if ( $response_code === 200 || $response_code === 201 ) {
					// Update last used timestamp.
					if ( isset( $subscription_data['id'] ) ) {
						$this->subscription_manager->update_last_used( (int) $subscription_data['id'] );
					}
					return true;
				} else {
					$this->handle_api_error( $result, $subscription_data );
					return false;
				}
			} else {
				$error_message = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown API error';
				Logger::error( 'API request failed: ' . $error_message, $subscription_data );
				return false;
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Exception sending push notification: ' . $e->getMessage(), $subscription_data );
			return false;
		}
	}

	/**
	 * Send notifications to all active subscribers
	 *
	 * @param string $payload Notification payload (JSON string)
	 * @return array Results array with success/failure counts
	 */
	public function send_to_all_subscribers( string $payload ): array {
		$subscriptions = $this->subscription_manager->get_all_active_subscriptions();
		$results       = array(
			'total'      => count( $subscriptions ),
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		if ( empty( $subscriptions ) ) {
			return $results;
		}

		foreach ( $subscriptions as $subscription ) {
			$success = $this->send_notification( $subscription, $payload );

			if ( $success ) {
				$results['successful']++;
			} else {
				$results['failed']++;
				$results['errors'][] = "Failed to send to user {$subscription['wp_user_id']}";
			}
		}

		return $results;
	}

	/**
	 * Send notification to specific user
	 *
	 * @param int    $wp_user_id WordPress user ID
	 * @param string $payload    Notification payload (JSON string)
	 * @return bool True on success, false on failure
	 */
	public function send_to_user( int $wp_user_id, string $payload ): bool {
		$subscriptions = $this->subscription_manager->get_user_subscriptions( $wp_user_id );

		if ( empty( $subscriptions ) ) {
			Logger::info( "No active subscriptions found for user {$wp_user_id}" );
			return false;
		}

		$success_count = 0;
		foreach ( $subscriptions as $subscription ) {
			if ( $this->send_notification( $subscription, $payload ) ) {
				$success_count++;
			}
		}

		return $success_count > 0;
	}

	/**
	 * Create notification payload
	 *
	 * @param string      $title   Notification title
	 * @param string      $body    Notification body
	 * @param string|null $icon    Notification icon URL
	 * @param string|null $url     Click action URL
	 * @param array       $data    Additional data
	 * @return string JSON encoded payload
	 */
	public function create_notification_payload(
		string $title,
		string $body,
		?string $icon = null,
		?string $url = null,
		array $data = array()
	): string {
		$payload = array(
			'title'              => $title,
			'body'               => $body,
			'icon'               => $icon ?: $this->get_default_icon(),
			'badge'              => $this->get_default_badge(),
			'data'               => array_merge(
				array(
					'url'       => $url ?: home_url(),
					'timestamp' => time(),
					'source'    => 'orderchatz',
				),
				$data
			),
			'actions'            => array(
				array(
					'action' => 'open',
					'title'  => '檢視',
					'icon'   => $this->get_default_icon(),
				),
			),
			'requireInteraction' => false,
			'silent'             => false,
		);

		return wp_json_encode( $payload );
	}

	/**
	 * Create LINE message notification payload
	 *
	 * @param string $friend_name Friend display name
	 * @param string $message     Message content
	 * @param int    $friend_id   Friend ID from database
	 * @param string $line_user_id LINE user ID
	 * @return string JSON encoded payload
	 */
	public function create_line_message_payload( string $friend_name, string $message, int $friend_id, string $line_user_id ): string {
		// Truncate long messages
		$truncated_message = mb_strlen( $message ) > 100 ? mb_substr( $message, 0, 100 ) . '...' : $message;

		// Build chat URL with friend_id parameter
		$chat_url = add_query_arg( array( 'friend' => $friend_id ), home_url( '/order-chatz/' ) );

		return $this->create_notification_payload(
			sprintf( '來自 %s 的新訊息', $friend_name ),
			$truncated_message,
			$this->get_default_icon(),
			$chat_url,
			array(
				'type'            => 'line_message',
				'friend_id'       => $friend_id,
				'line_user_id'    => $line_user_id,
				'friend_name'     => $friend_name,
				'message_preview' => $truncated_message,
			)
		);
	}

	/**
	 * Send notification to third-party API
	 *
	 * @param array  $subscription Subscription data formatted for API
	 * @param string $payload      Notification payload (JSON string)
	 * @return array|WP_Error API response or error
	 */
	private function send_to_api( array $subscription, string $payload ) {
		if ( ! $this->vapid_manager->validate_keys() ) {
			Logger::error( 'VAPID keys not valid, cannot send push notification' );
			return new \WP_Error( 'invalid_keys', 'VAPID keys not valid' );
		}

		$keys = $this->vapid_manager->get_keys();

		if ( ! $keys ) {
			Logger::error( 'Failed to get VAPID keys' );
			return new \WP_Error( 'missing_keys', 'Failed to get VAPID keys' );
		}

		$api_data = array(
			'site_key'     => 'otz_web_push',
			'vapid'        => array(
				'subject'     => $keys['subject'],
				'public_key'  => $keys['public_key'],
				'private_key' => $keys['private_key'],
			),
			'subscription' => $subscription,
			'payload'      => json_decode( $payload, true ),
		);

		return wp_remote_post(
			$this->api_endpoint,
			array(
				'body'    => wp_json_encode( $api_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);
	}

	/**
	 * Handle API push notification errors
	 *
	 * @param array $result          API response
	 * @param array $subscription_data Subscription data
	 * @return void
	 */
	private function handle_api_error( array $result, array $subscription_data ): void {
		$error_message = 'Push notification API failed';
		$status_code   = wp_remote_retrieve_response_code( $result );
		$response_body = wp_remote_retrieve_body( $result );

		if ( $status_code > 0 ) {
			$error_message .= " (Status: {$status_code})";

			// Handle specific error cases.
			if ( 410 === $status_code || 404 === $status_code ) {
				// Subscription expired or not found, remove it.
				$this->subscription_manager->delete_subscription_by_endpoint( $subscription_data['endpoint'] );
				Logger::info( "Removed expired subscription: {$subscription_data['endpoint']}" );
			} elseif ( 400 === $status_code || 413 === $status_code ) {
				// Invalid subscription or payload too large.
				Logger::warning(
					"Invalid subscription or payload: {$subscription_data['endpoint']}",
					array(
						'status_code' => $status_code,
						'endpoint'    => $subscription_data['endpoint'],
						'response'    => $response_body,
					)
				);
			}
		}

		if ( ! empty( $response_body ) ) {
			$error_message .= ': ' . $response_body;
		}

		Logger::error(
			$error_message,
			array(
				'subscription_id' => $subscription_data['id'] ?? 'unknown',
				'endpoint'        => $subscription_data['endpoint'],
			)
		);
	}

	/**
	 * Get default notification icon
	 *
	 * @return string Icon URL
	 */
	private function get_default_icon(): string {
		// Try to get site icon first
		$site_icon = get_site_icon_url( 192 );
		if ( $site_icon ) {
			return $site_icon;
		}

		// Fallback to plugin icon
		$plugin_icon = defined( 'OTZ_PLUGIN_URL' ) ? OTZ_PLUGIN_URL . 'assets/img/otz-icon-192.png' : '';
		if ( $plugin_icon && file_exists( str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $plugin_icon ) ) ) {
			return $plugin_icon;
		}

		// Final fallback
		return home_url( '/favicon.ico' );
	}

	/**
	 * Get default notification badge
	 *
	 * @return string Badge URL
	 */
	private function get_default_badge(): string {
		$badge_url = defined( 'OTZ_PLUGIN_URL' ) ? OTZ_PLUGIN_URL . 'assets/img/otz-badge-72.png' : '';
		return $badge_url ?: $this->get_default_icon();
	}

	/**
	 * Test push notification functionality
	 *
	 * @param int $wp_user_id WordPress user ID
	 * @return bool True on success, false on failure
	 */
	public function send_test_notification( int $wp_user_id ): bool {
		$payload = $this->create_notification_payload(
			'OrderChatz 測試通知',
			'這是一個測試推播通知，確認 PWA 功能正常運作。',
			null,
			home_url(),
			array( 'type' => 'test' )
		);

		return $this->send_to_user( $wp_user_id, $payload );
	}

	/**
	 * Check if WebPush service is ready
	 *
	 * @return bool True if ready, false otherwise
	 */
	public function is_ready(): bool {
		return $this->vapid_manager->validate_keys();
	}
}
