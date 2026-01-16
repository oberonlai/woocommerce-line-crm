<?php
/**
 * OrderChatz 推播 AJAX 處理器 (重構版)
 *
 * 使用服務類別來處理推播相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax
 * @since 1.0.0
 */

namespace OrderChatz\Ajax;

use Exception;
use OrderChatz\Services\Broadcast\AudienceFilter;
use OrderChatz\Services\ProductSearchService;
use OrderChatz\Services\Broadcast\BroadcastSender;

class BroadcastAjaxHandler {

	/**
	 * 受眾篩選服務
	 *
	 * @var AudienceFilter
	 */
	private AudienceFilter $filter_service;

	/**
	 * 商品搜尋服務
	 *
	 * @var ProductSearchService
	 */
	private ProductSearchService $product_service;

	/**
	 * 推播發送服務
	 *
	 * @var BroadcastSender
	 */
	private BroadcastSender $sender_service;

	public function __construct() {
		// 初始化服務.
		$this->filter_service  = new AudienceFilter();
		$this->product_service = new ProductSearchService();
		$this->sender_service  = new BroadcastSender();

		// 推播相關 AJAX endpoints
		add_action( 'wp_ajax_otz_get_audience_count', array( $this, 'getAudienceCount' ) );
		add_action( 'wp_ajax_otz_preview_audience', array( $this, 'previewAudience' ) );
		add_action( 'wp_ajax_otz_search_products', array( $this, 'searchProducts' ) );
		add_action( 'wp_ajax_otz_search_products_for_filter', array( $this, 'searchProductsForFilter' ) );
		add_action( 'wp_ajax_otz_search_product_categories', array( $this, 'searchProductCategories' ) );
		add_action( 'wp_ajax_otz_search_product_tags', array( $this, 'searchProductTags' ) );
		add_action( 'wp_ajax_otz_test_message', array( $this, 'testMessage' ) );

	}

	/**
	 * 取得推播對象數量
	 */
	public function getAudienceCount() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			$filter_mode = sanitize_text_field( $_POST['filter_mode'] ?? 'all' );

			if ( $filter_mode === 'conditions' ) {
				// 動態條件篩選
				$dynamic_conditions = $_POST['dynamic_conditions'] ?? array();
				$count              = $this->filter_service->calculate_dynamic_audience_count( $dynamic_conditions );
			} elseif ( $filter_mode === 'all' ) {
				// 所有好友（資料庫中的）
				global $wpdb;
				$count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}otz_users WHERE status = 'active'" ) );
			} elseif ( $filter_mode === 'broadcast' ) {
				// 所有追蹤者（無法精確計算，回傳 -1 表示未知）
				$count = -1;
			}

			wp_send_json_success(
				array(
					'count'       => $count,
					'filter_mode' => $filter_mode,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 預覽推播對象名單
	 */
	public function previewAudience() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			$filter_mode = sanitize_text_field( $_POST['filter_mode'] ?? 'all' );
			$page        = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
			$per_page    = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 20;
			$offset      = ( $page - 1 ) * $per_page;

			if ( $filter_mode === 'conditions' ) {
				// 動態條件篩選
				$dynamic_conditions = $_POST['dynamic_conditions'] ?? array();
				$friends            = $this->filter_service->get_dynamic_filtered_friends( $dynamic_conditions, $per_page, $offset );
				$total              = $this->filter_service->calculate_dynamic_audience_count( $dynamic_conditions );
			} elseif ( $filter_mode === 'all' ) {
				// 所有好友（資料庫中的）
				global $wpdb;
				$friends = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT line_user_id, display_name as name, avatar_url as picture_url FROM {$wpdb->prefix}otz_users WHERE status = 'active' LIMIT %d OFFSET %d",
						$per_page,
						$offset
					),
					ARRAY_A
				);
				$total   = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}otz_users WHERE status = 'active'" ) );
			} elseif ( $filter_mode === 'broadcast' ) {
				// 所有追蹤者（無法預覽）
				$friends = array();
				$total   = -1;
			}

			$total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 0;
			$has_more    = $page < $total_pages;

			wp_send_json_success(
				array(
					'friends'      => $friends,
					'total'        => $total,
					'current_page' => $page,
					'per_page'     => $per_page,
					'total_pages'  => $total_pages,
					'has_more'     => $has_more,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 搜尋商品
	 */
	public function searchProducts() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			$query = ( isset( $_POST['query'] ) ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

			if ( strlen( $query ) < 2 ) {
				wp_send_json_success( array( 'products' => array() ) );
				return;
			}

			$products = $this->product_service->searchProducts( $query );

			wp_send_json_success(
				array(
					'products' => $products,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 搜尋商品用於篩選條件（包含可變商品規格）
	 */
	public function searchProductsForFilter() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			$query          = ( ! empty( $_POST['query'] ) ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
			$stock_operator = ( ! empty( $_POST['stock_operator'] ) ) ? wp_unslash( $_POST['stock_operator'] ) : null;
			$stock_value    = ( ! empty( $_POST['stock_value'] ) ) ? intval( $_POST['stock_value'] ) : null;

			// 驗證庫存運算符.
			if ( $stock_operator !== null && ! in_array( $stock_operator, array( '<=', '>=', '<', '>', '=' ), true ) ) {
				$stock_operator = null;
			}

			$products = $this->product_service->searchProductsForFilter( $query, $stock_operator, $stock_value );

			wp_send_json_success(
				array(
					'products' => $products,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 搜尋商品類別
	 */
	public function searchProductCategories() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			$query = $this->product_service->validateSearchQuery( $_POST['query'] ?? '' );

			$categories = $this->product_service->searchProductCategories( $query );

			wp_send_json_success(
				array(
					'categories' => $categories,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 搜尋商品標籤
	 */
	public function searchProductTags() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			$query = ( ! empty( $_POST['query'] ) ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

			$tags = $this->product_service->searchProductTags( $query );

			wp_send_json_success(
				array(
					'tags' => $tags,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 測試發送訊息
	 */
	public function testMessage() {
		try {
			check_ajax_referer( 'otz_broadcast_action', 'nonce' );

			// 取得並儲存測試 User ID.
			$test_user_id = ( isset( $_POST['test_line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['test_line_user_id'] ) ) : '';

			if ( empty( $test_user_id ) ) {
				throw new Exception( __( '請輸入測試用 LINE User ID', 'otz' ) );
			}

			// 儲存測試 User ID 到 wp_options.
			update_option( 'otz_test_line_user_id', $test_user_id );

			// 取得訊息類型與內容.
			$message_type = sanitize_text_field( $_POST['message_type'] ?? 'text' );

			// 根據訊息類型準備內容陣列.
			$content = array();
			switch ( $message_type ) {
				case 'text':
					$content['text'] = wp_kses_post( wp_unslash( $_POST['message_content_text'] ?? '' ) );
					break;
				case 'image':
					$content['url'] = esc_url_raw( $_POST['message_content_image'] ?? '' );
					break;
				case 'video':
					$content['videoUrl']        = esc_url_raw( $_POST['message_content_video'] ?? '' );
					$content['previewImageUrl'] = esc_url_raw( $_POST['message_content_video_preview'] ?? '' );
					break;
				case 'flex':
					$flex_json = ( isset( $_POST['message_content_flex'] ) ) ? wp_unslash( $_POST['message_content_flex'] ) : '';
					$content   = json_decode( $flex_json, true );
					break;
			}

			// 驗證訊息內容.
			if ( ! $this->sender_service->validate_message_content( $message_type, $content ) ) {
				throw new Exception( __( '訊息內容無效', 'otz' ) );
			}

			// 建構 LINE 訊息格式.
			$line_messages = $this->sender_service->build_line_messages( $message_type, $content );

			// 發送測試訊息.
			$result = $this->sender_service->send_push_message( $test_user_id, $line_messages );

			wp_send_json_success(
				array(
					'message' => __( '測試訊息已發送', 'otz' ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}
}
