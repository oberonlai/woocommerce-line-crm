<?php

declare(strict_types=1);

namespace OrderChatz\Services;

use OrderChatz\Util\Logger;

defined('ABSPATH') || exit;

/**
 * Push Subscription Manager
 *
 * Manages push notification subscriptions with database operations and caching.
 * Based on the reference implementation in WebPush/Table.php
 *
 * @package    OrderChatz
 * @subpackage Services
 * @since      1.0.0
 */
class PushSubscriptionManager {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Subscribers table name
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Cache group name
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'otz_push_subscriptions';

	/**
	 * Cache expiration time (5 minutes)
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 300;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'otz_subscribers';
	}

	/**
	 * Save subscription to database
	 *
	 * @param array $subscription_data Subscription data
	 * @return int|false Subscription ID on success, false on failure
	 */
	public function save_subscription(array $subscription_data) {
		// Validate subscription data
		if (!$this->validate_subscription($subscription_data)) {
			return false;
		}

		// Check for duplicate subscriptions
		if ($this->has_duplicate_subscription($subscription_data)) {
			// Update existing subscription instead of creating duplicate
			return $this->update_existing_subscription($subscription_data);
		}

		// Prepare data for insertion
		$data = $this->prepare_subscription_data($subscription_data);
		$data['subscribed_at'] = current_time('mysql');
		$data['last_used_at'] = current_time('mysql');

		// Insert into database
		$result = $this->wpdb->insert(
			$this->table_name,
			$data,
			array(
				'%d', // wp_user_id
				'%s', // line_user_id
				'%s', // endpoint
				'%s', // p256dh_key
				'%s', // auth_key
				'%s', // user_agent
				'%s', // device_type
				'%s', // subscribed_at
				'%s', // last_used_at
				'%s'  // status
			)
		);

		if ($result === false) {
			Logger::error('Failed to save push subscription', $subscription_data);
			return false;
		}

		$subscription_id = $this->wpdb->insert_id;

		// Clear cache
		$this->clear_user_cache((int) $subscription_data['wp_user_id']);

		return $subscription_id;
	}

	/**
	 * Get user subscriptions
	 *
	 * @param int $wp_user_id WordPress user ID
	 * @return array Array of subscription data
	 */
	public function get_user_subscriptions(int $wp_user_id): array {
		$cache_key = "user_subscriptions_{$wp_user_id}";
		$cached_subscriptions = wp_cache_get($cache_key, self::CACHE_GROUP);

		if ($cached_subscriptions !== false) {
			return $cached_subscriptions;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$subscriptions = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE wp_user_id = %d AND status = 'active' ORDER BY subscribed_at DESC",
				$wp_user_id
			),
			ARRAY_A
		);

		if ($subscriptions === null) {
			$subscriptions = array();
		}

		// Cache the results
		wp_cache_set($cache_key, $subscriptions, self::CACHE_GROUP, self::CACHE_EXPIRATION);

		return $subscriptions;
	}

	/**
	 * Delete subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return bool True on success, false on failure
	 */
	public function delete_subscription(int $subscription_id): bool {
		// Get subscription data for cache clearing
		$subscription = $this->get_subscription_by_id($subscription_id);
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->delete(
			$this->table_name,
			array('id' => $subscription_id),
			array('%d')
		);

		if ($result !== false && $subscription) {
			$this->clear_user_cache((int) $subscription['wp_user_id']);
			return true;
		}

		return false;
	}

	/**
	 * Delete subscription by endpoint
	 *
	 * @param string $endpoint Push subscription endpoint
	 * @return bool True on success, false on failure
	 */
	public function delete_subscription_by_endpoint(string $endpoint): bool {
		// Get subscription data for cache clearing
		$subscription = $this->get_subscription_by_endpoint($endpoint);
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->delete(
			$this->table_name,
			array('endpoint' => $endpoint),
			array('%s')
		);

		if ($result !== false && $subscription) {
			$this->clear_user_cache((int) $subscription['wp_user_id']);
			return true;
		}

		return false;
	}

	/**
	 * Get all active subscriptions
	 *
	 * @return array Array of subscription data
	 */
	public function get_all_active_subscriptions(): array {
		$cache_key = 'all_active_subscriptions';
		$cached_subscriptions = wp_cache_get($cache_key, self::CACHE_GROUP);

		if ($cached_subscriptions !== false) {
			return $cached_subscriptions;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$subscriptions = $this->wpdb->get_results(
			"SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY subscribed_at DESC",
			ARRAY_A
		);

		if ($subscriptions === null) {
			$subscriptions = array();
		}

		// Cache the results for a shorter time
		wp_cache_set($cache_key, $subscriptions, self::CACHE_GROUP, 60);

		return $subscriptions;
	}

	/**
	 * Update subscription last used time
	 *
	 * @param int $subscription_id Subscription ID
	 * @return bool True on success, false on failure
	 */
	public function update_last_used(int $subscription_id): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->update(
			$this->table_name,
			array('last_used_at' => current_time('mysql')),
			array('id' => $subscription_id),
			array('%s'),
			array('%d')
		);

		return $result !== false;
	}

	/**
	 * Validate subscription data
	 *
	 * @param array $subscription_data Subscription data to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validate_subscription(array $subscription_data): bool {
		// Required fields
		$required_fields = array('wp_user_id', 'endpoint', 'p256dh_key', 'auth_key');

		foreach ($required_fields as $field) {
			if (!isset($subscription_data[$field]) || empty($subscription_data[$field])) {
				return false;
			}
		}

		// Validate WordPress user ID
		if (!is_numeric($subscription_data['wp_user_id']) || $subscription_data['wp_user_id'] <= 0) {
			return false;
		}

		// Validate endpoint URL format
		if (!filter_var($subscription_data['endpoint'], FILTER_VALIDATE_URL)) {
			return false;
		}

		// Validate key lengths (basic check)
		if (strlen($subscription_data['p256dh_key']) < 20 || strlen($subscription_data['auth_key']) < 10) {
			return false;
		}

		return true;
	}

	/**
	 * Check for duplicate subscriptions
	 *
	 * @param array $subscription_data Subscription data
	 * @return bool True if duplicate exists, false otherwise
	 */
	private function has_duplicate_subscription(array $subscription_data): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE wp_user_id = %d AND endpoint = %s",
				$subscription_data['wp_user_id'],
				$subscription_data['endpoint']
			)
		);

		return $existing > 0;
	}

	/**
	 * Update existing subscription
	 *
	 * @param array $subscription_data Subscription data
	 * @return int|false Subscription ID on success, false on failure
	 */
	private function update_existing_subscription(array $subscription_data) {
		$data = $this->prepare_subscription_data($subscription_data);
		$data['last_used_at'] = current_time('mysql');
		$data['status'] = 'active'; // Reactivate if was inactive

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->update(
			$this->table_name,
			$data,
			array(
				'wp_user_id' => $subscription_data['wp_user_id'],
				'endpoint' => $subscription_data['endpoint']
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s'), // data format
			array('%d', '%s') // where format
		);

		if ($result !== false) {
			// Get the subscription ID
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$subscription_id = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->table_name} WHERE wp_user_id = %d AND endpoint = %s",
					$subscription_data['wp_user_id'],
					$subscription_data['endpoint']
				)
			);

			$this->clear_user_cache((int) $subscription_data['wp_user_id']);
			return (int) $subscription_id;
		}

		return false;
	}

	/**
	 * Prepare subscription data for database insertion
	 *
	 * @param array $subscription_data Raw subscription data
	 * @return array Prepared data
	 */
	private function prepare_subscription_data(array $subscription_data): array {
		return array(
			'wp_user_id' => (int) $subscription_data['wp_user_id'],
			'line_user_id' => $subscription_data['line_user_id'] ?? null,
			'endpoint' => sanitize_url($subscription_data['endpoint']),
			'p256dh_key' => sanitize_text_field($subscription_data['p256dh_key']),
			'auth_key' => sanitize_text_field($subscription_data['auth_key']),
			'user_agent' => sanitize_text_field($subscription_data['user_agent'] ?? ''),
			'device_type' => sanitize_text_field($subscription_data['device_type'] ?? $this->detect_device_type()),
			'status' => 'active'
		);
	}

	/**
	 * Get subscription by ID
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array|null Subscription data or null
	 */
	private function get_subscription_by_id(int $subscription_id): ?array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$subscription = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$subscription_id
			),
			ARRAY_A
		);

		return $subscription ?: null;
	}

	/**
	 * Get subscription by endpoint
	 *
	 * @param string $endpoint Push subscription endpoint
	 * @return array|null Subscription data or null
	 */
	private function get_subscription_by_endpoint(string $endpoint): ?array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$subscription = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE endpoint = %s",
				$endpoint
			),
			ARRAY_A
		);

		return $subscription ?: null;
	}

	/**
	 * Clear user-specific cache
	 *
	 * @param int $wp_user_id WordPress user ID
	 * @return void
	 */
	private function clear_user_cache(int $wp_user_id): void {
		wp_cache_delete("user_subscriptions_{$wp_user_id}", self::CACHE_GROUP);
		wp_cache_delete('all_active_subscriptions', self::CACHE_GROUP);
	}

	/**
	 * Detect device type from user agent
	 *
	 * @return string Device type (mobile/desktop/tablet)
	 */
	private function detect_device_type(): string {
		if (!isset($_SERVER['HTTP_USER_AGENT'])) {
			return 'unknown';
		}

		$user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));

		if (wp_is_mobile()) {
			// Further detect between mobile and tablet
			if (preg_match('/tablet|ipad/i', $user_agent)) {
				return 'tablet';
			}
			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * Get subscription statistics
	 *
	 * @return array Statistics data
	 */
	public function get_subscription_stats(): array {
		$cache_key = 'subscription_stats';
		$cached_stats = wp_cache_get($cache_key, self::CACHE_GROUP);

		if ($cached_stats !== false) {
			return $cached_stats;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$stats = $this->wpdb->get_results(
			"SELECT 
				status,
				device_type,
				COUNT(*) as count
			FROM {$this->table_name}
			GROUP BY status, device_type",
			ARRAY_A
		);

		$formatted_stats = array(
			'total' => 0,
			'active' => 0,
			'inactive' => 0,
			'expired' => 0,
			'by_device' => array(
				'mobile' => 0,
				'desktop' => 0,
				'tablet' => 0,
				'unknown' => 0
			)
		);

		foreach ($stats as $stat) {
			$formatted_stats['total'] += $stat['count'];
			$formatted_stats[$stat['status']] += $stat['count'];
			
			$device = $stat['device_type'] ?: 'unknown';
			if (isset($formatted_stats['by_device'][$device])) {
				$formatted_stats['by_device'][$device] += $stat['count'];
			}
		}

		wp_cache_set($cache_key, $formatted_stats, self::CACHE_GROUP, self::CACHE_EXPIRATION);

		return $formatted_stats;
	}
}