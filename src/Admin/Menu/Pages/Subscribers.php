<?php

declare(strict_types=1);

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Util\Logger;
use OrderChatz\Services\PushSubscriptionManager;
use OrderChatz\Services\WebPushService;
use OrderChatz\Services\VapidKeyManager;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribers Admin Page
 *
 * Handles the admin interface for managing push notification subscribers.
 * Based on the reference implementation in WebPush/ListTableSubscribers.php
 *
 * @package    OrderChatz
 * @subpackage Admin\Menu\Pages
 * @since      1.0.0
 */
class Subscribers extends PageRenderer {

	/**
	 * Page slug
	 */
	private const PAGE_SLUG = 'otz-subscribers';

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
	 * VAPID key manager
	 *
	 * @var VapidKeyManager
	 */
	private VapidKeyManager $vapid_manager;

	/**
	 * List table instance
	 *
	 * @var PushNotificationsListTable|null
	 */
	private ?PushNotificationsListTable $list_table = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			__( '訂閱管理', 'otz' ),
			self::PAGE_SLUG,
			true // has tabs
		);

		$this->subscription_manager = new PushSubscriptionManager();
		$this->push_service         = new WebPushService();
		$this->vapid_manager        = new VapidKeyManager();
	}


	/**
	 * Render the page content
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		$this->handle_actions();
		$this->render_subscribers_list();
	}

	/**
	 * Handle form actions
	 *
	 * @return void
	 */
	private function handle_actions(): void {
		$bulk_action = $this->get_current_bulk_action();

		if ( $bulk_action ) {
			$this->handle_bulk_action( $bulk_action );
			return;
		}

		// Handle individual actions
		$action = $_REQUEST['action'] ?? '';
		$nonce  = $_REQUEST['_wpnonce'] ?? '';

		switch ( $action ) {
			case 'delete':
				if ( wp_verify_nonce( $nonce, 'delete_subscriber' ) ) {
					$this->handle_delete_subscriber();
				}
				break;
		}
	}

	/**
	 * Get current bulk action
	 *
	 * @return string|false
	 */
	private function get_current_bulk_action() {
		$action = false;
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['bulk'] ) ) {
			if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] != -1 ) {
				$action = $_REQUEST['action'];
			} elseif ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] != -1 ) {
				$action = $_REQUEST['action2'];
			}
		}

		return $action;
	}

	/**
	 * Handle bulk actions
	 *
	 * @param string $action Bulk action name
	 * @return void
	 */
	private function handle_bulk_action( string $action ): void {
		$nonce = $_REQUEST['_wpnonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-subscribers' ) ) {
			wp_die( __( '安全檢查失敗', 'otz' ) );
		}

		switch ( $action ) {
			case 'bulk-delete':
				$this->handle_bulk_delete();
				break;
		}
	}

	/**
	 * Handle deleting a single subscriber
	 *
	 * @return void
	 */
	private function handle_delete_subscriber(): void {
		$subscriber_id = absint( $_REQUEST['subscriber'] ?? 0 );

		if ( $subscriber_id > 0 ) {
			$success = $this->subscription_manager->delete_subscription( $subscriber_id );

			if ( $success ) {
				$this->add_admin_notice( '訂閱者已成功刪除', 'success' );
			} else {
				$this->add_admin_notice( '刪除訂閱者失敗', 'error' );
			}
		}
	}

	/**
	 * Handle bulk delete action
	 *
	 * @return void
	 */
	private function handle_bulk_delete(): void {
		$subscriber_ids = $_REQUEST['bulk'] ?? array();

		if ( ! is_array( $subscriber_ids ) || empty( $subscriber_ids ) ) {
			$this->add_admin_notice( '請選擇要刪除的訂閱者', 'warning' );
			return;
		}

		$deleted_count = 0;
		foreach ( $subscriber_ids as $subscriber_id ) {
			$subscriber_id = absint( $subscriber_id );
			if ( $subscriber_id > 0 && $this->subscription_manager->delete_subscription( $subscriber_id ) ) {
				$deleted_count++;
			}
		}

		if ( $deleted_count > 0 ) {
			/* translators: %d: number of deleted subscribers */
			$this->add_admin_notice( sprintf( '已成功刪除 %d 個訂閱者', $deleted_count ), 'success' );
		} else {
			$this->add_admin_notice( '沒有訂閱者被刪除', 'warning' );
		}
	}

	/**
	 * Render subscribers list table
	 *
	 * @return void
	 */
	private function render_subscribers_list(): void {
		if ( ! $this->list_table ) {
			$this->list_table = new PushNotificationsListTable( $this->subscription_manager );
			$this->list_table->prepare_items();
		}

		echo '<div class="otz-subscribers-list">';
		echo '<h2>訂閱者列表</h2>';

		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="' . esc_attr( $_REQUEST['page'] ) . '">';
		$this->list_table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Add admin notice
	 *
	 * @param string $message Notice message
	 * @param string $type    Notice type (success, error, warning, info)
	 * @return void
	 */
	private function add_admin_notice( string $message, string $type = 'info' ): void {
		add_action(
			'admin_notices',
			function() use ( $message, $type ) {
				echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible">';
				echo '<p>' . esc_html( $message ) . '</p>';
				echo '</div>';
			}
		);
	}
}

/**
 * Push Notifications List Table
 *
 * WordPress list table for displaying push notification subscribers.
 */
class PushNotificationsListTable extends \WP_List_Table {

	/**
	 * Push subscription manager
	 *
	 * @var PushSubscriptionManager
	 */
	private PushSubscriptionManager $subscription_manager;

	/**
	 * Constructor
	 *
	 * @param PushSubscriptionManager $subscription_manager Subscription manager instance
	 */
	public function __construct( PushSubscriptionManager $subscription_manager ) {
		parent::__construct(
			array(
				'singular' => '訂閱者',
				'plural'   => 'subscribers',
				'ajax'     => false,
			)
		);

		$this->subscription_manager = $subscription_manager;
	}

	/**
	 * Get table columns
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'cb'            => '<input type="checkbox" />',
			'user_info'     => '使用者',
			'user_agent'    => '裝置',
			'subscribed_at' => '訂閱時間',
			'last_used_at'  => '最後使用',
			'status'        => '狀態',
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array(
			'user_info'     => array( 'wp_user_id', false ),
			'device_type'   => array( 'device_type', false ),
			'subscribed_at' => array( 'subscribed_at', false ),
			'last_used_at'  => array( 'last_used_at', false ),
			'status'        => array( 'status', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return array(
			'bulk-delete' => '刪除',
		);
	}

	/**
	 * Column checkbox
	 *
	 * @param array $item Item data
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="bulk[]" value="%s" />', $item['id'] );
	}

	/**
	 * Default column handler
	 *
	 * @param array  $item        Item data
	 * @param string $column_name Column name
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'user_info':
				return $this->render_user_info( $item );
			case 'user_agent':
				return esc_html( $item['user_agent'] ?? '未知' );

			case 'device_type':
				return $this->get_device_type_label( $item['device_type'] ?? 'unknown' );

			case 'subscribed_at':
				return $this->format_datetime( $item['subscribed_at'] );

			case 'last_used_at':
				return $this->format_datetime( $item['last_used_at'] );

			case 'status':
				return $this->render_status( $item['status'] );

			default:
				return esc_html( $item[ $column_name ] ?? '' );
		}
	}

	/**
	 * Render user info column
	 *
	 * @param array $item Item data
	 * @return string
	 */
	private function render_user_info( array $item ): string {
		$user      = get_userdata( $item['wp_user_id'] );
		$user_name = $user ? $user->display_name : '未知使用者';

		$actions = array(
			'delete' => sprintf(
				'<a href="?page=%s&action=delete&subscriber=%s&_wpnonce=%s" onclick="return confirm(\'確定要刪除此訂閱者嗎？\')">刪除</a>',
				$_REQUEST['page'],
				$item['id'],
				wp_create_nonce( 'delete_subscriber' )
			),
		);

		return sprintf(
			'%s %s',
			esc_html( $user_name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render status column
	 *
	 * @param string $status Status value
	 * @return string
	 */
	private function render_status( string $status ): string {
		$status_labels = array(
			'active'   => '<span style="color: #46b450;">啟用</span>',
			'inactive' => '<span style="color: #ffb900;">停用</span>',
			'expired'  => '<span style="color: #dc3232;">過期</span>',
		);

		return $status_labels[ $status ] ?? esc_html( $status );
	}

	/**
	 * Get device type label
	 *
	 * @param string $device_type Device type
	 * @return string
	 */
	private function get_device_type_label( string $device_type ): string {
		$labels = array(
			'mobile'  => '📱 手機',
			'desktop' => '💻 桌機',
			'tablet'  => '📱 平板',
			'unknown' => '❓ 未知',
		);

		return $labels[ $device_type ] ?? esc_html( $device_type );
	}

	/**
	 * Format datetime string
	 *
	 * @param string|null $datetime Datetime string
	 * @return string
	 */
	private function format_datetime( ?string $datetime ): string {
		if ( empty( $datetime ) || $datetime === '0000-00-00 00:00:00' ) {
			return '—';
		}

		$timestamp = strtotime( $datetime );
		return $timestamp ? date_i18n( 'Y-m-d H:i', $timestamp ) : '—';
	}

	/**
	 * Prepare table items
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = $this->get_items_per_page( 'push_subscribers_per_page', 20 );
		$current_page = $this->get_pagenum();

		$all_subscriptions = $this->subscription_manager->get_all_active_subscriptions();
		$total_items       = count( $all_subscriptions );

		$offset      = ( $current_page - 1 ) * $per_page;
		$this->items = array_slice( $all_subscriptions, $offset, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}
}
