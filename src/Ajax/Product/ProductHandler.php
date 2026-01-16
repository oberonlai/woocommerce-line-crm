<?php
/**
 * 商品處理 AJAX 處理器
 *
 * 處理商品相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Product
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Product;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;
use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;

class ProductHandler extends AbstractAjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_otz_search_products', array( $this, 'searchProducts' ) );
		add_action( 'wp_ajax_otz_send_product_message', array( $this, 'sendProductMessage' ) );
		add_action( 'wp_ajax_otz_get_product_by_id', array( $this, 'getProductById' ) );
	}

	/**
	 * 搜尋商品
	 */
	public function searchProducts() {
		try {
			$this->verifyNonce();

			$search = ( isset( $_REQUEST['search'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';

			$products = $this->queryProducts( $search );

			$this->sendSuccess(
				array(
					'products' => $products,
					'total'    => count( $products ),
				)
			);

		} catch ( Exception $e ) {
			$logger = wc_get_logger();
			$logger->error(
				'OrderChatz: 商品搜尋錯誤: ' . $e->getMessage(),
				array(
					'source' => 'orderchatz',
				)
			);
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 發送商品訊息
	 */
	public function sendProductMessage() {
		try {
			$this->verifyNonce();

			$line_user_id      = sanitize_text_field( $_POST['line_user_id'] ?? '' );
			$product_id        = intval( $_POST['product_id'] ?? 0 );
			$product_title     = sanitize_text_field( $_POST['product_title'] ?? '' );
			$product_price     = sanitize_text_field( $_POST['product_price'] ?? '' );
			$product_price_raw = sanitize_text_field( $_POST['product_price_raw'] ?? '' );
			$product_url       = esc_url_raw( $_POST['product_url'] ?? '' );
			$product_image     = $_POST['product_image'] ? esc_url_raw( $_POST['product_image'] ) : '';

			if ( empty( $line_user_id ) ) {
				throw new Exception( 'LINE User ID 不能為空' );
			}

			if ( empty( $product_id ) ) {
				throw new Exception( '商品 ID 不能為空' );
			}

			// 構建 Button Template 訊息（使用原始價格避免 HTML 標籤）
			$price_for_template = ! empty( $product_price_raw ) ? 'NT$' . $product_price_raw : strip_tags( $product_price );
			$button_template    = $this->buildButtonTemplate( $product_id, $product_title, $price_for_template, $product_url, $product_image );

			$line_result = $this->sendLineButtonTemplate( $line_user_id, $button_template );

			if ( ! $line_result ) {
				$logger = wc_get_logger();
				$logger->error(
					'OrderChatz: LINE 訊息發送失敗',
					array(
						'source'        => 'orderchatz',
						'line_user_id'  => $line_user_id,
						'product_id'    => $product_id,
						'product_title' => $product_title,
						'template_data' => $button_template,
					)
				);

			}

			// 儲存簡化的商品訊息到資料庫（HTML 連結格式）
			$simple_message = "[商品推薦] <a href=\"{$product_url}\" target=\"_blank\">{$product_title}</a>";
			$db_result      = $this->saveSimpleProductMessage( $line_user_id, $simple_message );

			if ( ! $db_result ) {
				$logger->error(
					'OrderChatz: 資料庫儲存商品訊息失敗',
					array(
						'source'         => 'orderchatz',
						'line_user_id'   => $line_user_id,
						'product_id'     => $product_id,
						'product_title'  => $product_title,
						'simple_message' => $simple_message,
					)
				);
				// LINE 發送成功但資料庫儲存失敗.
				$this->sendSuccess(
					array(
						'message'  => 'LINE 訊息發送成功，但歷史記錄儲存失敗',
						'db_saved' => false,
						'product'  => array(
							'id'    => $product_id,
							'title' => $product_title,
							'url'   => $product_url,
						),
					)
				);
				return;
			}

			$this->sendSuccess(
				array(
					'message'  => '商品訊息發送成功',
					'db_saved' => true,
					'product'  => array(
						'id'    => $product_id,
						'title' => $product_title,
						'url'   => $product_url,
					),
				)
			);

		} catch ( Exception $e ) {
			$logger = wc_get_logger();
			$logger->error(
				'OrderChatz: 商品訊息發送錯誤: ' . $e->getMessage(),
				array(
					'source'        => 'orderchatz',
					'line_user_id'  => $line_user_id ?? '',
					'product_id'    => $product_id ?? 0,
					'product_title' => $product_title ?? '',
					'stack_trace'   => $e->getTraceAsString(),
				)
			);
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 根據 ID 取得單一商品資訊
	 */
	public function getProductById() {
		try {
			$this->verifyNonce();

			$product_id = ( isset( $_POST['product_id'] ) ) ? intval( wp_unslash( $_POST['product_id'] ) ) : 0;

			if ( empty( $product_id ) ) {
				throw new Exception( '商品 ID 不能為空' );
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				throw new Exception( '找不到指定的商品' );
			}

			$product_data = array(
				'id'        => $product->get_id(),
				'title'     => $product->get_name(),
				'price'     => $product->get_price_html(),
				'price_raw' => $product->get_price(),
				'image'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'permalink' => get_permalink( $product->get_id() ),
			);

			$this->sendSuccess(
				array(
					'product' => $product_data,
				)
			);

		} catch ( Exception $e ) {
			$logger = wc_get_logger();
			$logger->error(
				'OrderChatz: 取得商品資訊錯誤: ' . $e->getMessage(),
				array(
					'source'     => 'orderchatz',
					'product_id' => $product_id ?? 0,
				)
			);
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 查詢商品
	 *
	 * @param string $search 搜尋關鍵字
	 * @return array 商品列表
	 */
	private function queryProducts( string $search ): array {
		// 使用 wc_get_products 查詢商品
		$query_args = array(
			'status'     => 'publish',
			'visibility' => array( 'visible', 'catalog' ),
			'limit'      => 20,
			's'          => $search,
			'meta_key'   => 'total_sales',
			'orderby'    => 'meta_value_num',
			'order'      => 'DESC',
		);

		$products_data = wc_get_products( $query_args );
		$products      = array();

		foreach ( $products_data as $product ) {
			if ( $product ) {
				$products[] = array(
					'id'        => $product->get_id(),
					'title'     => $product->get_name(),
					'price'     => $product->get_price_html(),
					'price_raw' => $product->get_price(),
					'image'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
					'permalink' => get_permalink( $product->get_id() ),
				);
			}
		}

		return $products;
	}

	/**
	 * 建構 Button Template 訊息
	 *
	 * @param int    $product_id 商品 ID
	 * @param string $product_title 商品標題
	 * @param string $product_price 商品價格
	 * @param string $product_url 商品連結
	 * @param string $product_image 商品圖片
	 * @return array Button Template 訊息格式
	 */
	private function buildButtonTemplate( int $product_id, string $product_title, string $product_price, string $product_url, string $product_image ): array {

		$thumbnail_url = ! empty( $product_image ) ? $product_image : '';

		$template = array(
			'type'     => 'template',
			'altText'  => "📦 商品推薦: {$product_title}",
			'template' => array(
				'type'    => 'buttons',
				'title'   => substr( $product_title, 0, 40 ),
				'text'    => $product_price,
				'actions' => array(
					array(
						'type'  => 'uri',
						'label' => '👉 查看商品',
						'uri'   => $product_url,
					),
				),
			),
		);

		if ( $thumbnail_url ) {
			$template['template']['thumbnailImageUrl'] = $thumbnail_url;
			$template['template']['imageAspectRatio']  = 'rectangle';
			$template['template']['imageSize']         = 'cover';
		}

		return $template;
	}

	/**
	 * 發送 LINE Button Template 訊息
	 *
	 * @param string $line_user_id LINE 用戶 ID.
	 * @param array  $template Button Template 訊息.
	 * @return bool 發送結果.
	 */
	private function sendLineButtonTemplate( string $line_user_id, array $template ): bool {
		// 使用 BroadcastSender 發送訊息.
		try {
			$sender_service = new \OrderChatz\Services\Broadcast\BroadcastSender();
			$messages       = array( $template );
			$result         = $sender_service->send_push_message( $line_user_id, $messages );
			return $result['success'] ?? false;
		} catch ( Exception $e ) {
			$logger = wc_get_logger();
			$logger->error(
				'OrderChatz: BroadcastSender 發送失敗: ' . $e->getMessage(),
				array(
					'source'        => 'orderchatz',
					'line_user_id'  => $line_user_id,
					'template_data' => $template,
					'stack_trace'   => $e->getTraceAsString(),
				)
			);
			return false;
		}
	}

	/**
	 * 發送 LINE 訊息
	 *
	 * @param string $line_user_id LINE 用戶 ID
	 * @param string $message 訊息內容
	 * @return bool 發送結果
	 */
	private function sendLineMessage( string $line_user_id, string $message ): bool {
		// 使用現有的 LINE API 客戶端
		try {
			$line_api_client = new \OrderChatz\API\LineAPIClient(
				$GLOBALS['wpdb'],
				wc_get_logger(),
				new \OrderChatz\Core\ErrorHandler(),
				new \OrderChatz\Core\SecurityValidator()
			);

			$result = $line_api_client->send_text_message( $line_user_id, $message );
			return $result['success'] ?? false;

		} catch ( Exception $e ) {
			$logger = wc_get_logger();
			$logger->error( 'OrderChatz Product Message Error: ' . $e->getMessage(), array( 'source' => 'orderchatz' ) );
			return false;
		}
	}

	/**
	 * 儲存商品訊息到資料庫
	 *
	 * @param string $line_user_id LINE 用戶 ID
	 * @param int    $product_id 商品 ID
	 * @param string $message_text 訊息內容
	 * @param string $product_image 商品圖片 URL
	 */
	private function saveProductMessage( string $line_user_id, int $product_id, string $message_text, string $product_image = '' ): void {
		global $wpdb;
		$logger = wc_get_logger();

		$table_name = $wpdb->prefix . 'otz_messages';

		// 檢查表是否存在
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
		if ( ! $table_exists ) {
			$logger->error(
				'OrderChatz: 資料庫表不存在',
				array(
					'source'     => 'orderchatz',
					'table_name' => $table_name,
				)
			);
			return;
		}

		// 檢查表結構
		$columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
		$logger->info(
			'OrderChatz: 資料庫表結構',
			array(
				'source'  => 'orderchatz',
				'columns' => $columns,
			)
		);

		$data = array(
			'line_user_id' => $line_user_id,
			'message_type' => 'product',
			'content'      => $message_text,
			'direction'    => 'outgoing',
			'is_from_line' => 0,
			'created_at'   => current_time( 'mysql' ),
			'metadata'     => wp_json_encode(
				array(
					'product_id'    => $product_id,
					'product_image' => $product_image,
				)
			),
		);

		// 記錄插入前的資料
		$logger->info(
			'OrderChatz: 準備插入資料',
			array(
				'source' => 'orderchatz',
				'data'   => $data,
			)
		);

		$result = $wpdb->insert( $table_name, $data );

		// 詳細的結果記錄
		$logger->info(
			'OrderChatz: 資料庫插入結果',
			array(
				'source'     => 'orderchatz',
				'result'     => $result,
				'insert_id'  => $wpdb->insert_id,
				'last_error' => $wpdb->last_error,
				'last_query' => $wpdb->last_query,
			)
		);

		if ( $result === false ) {
			$logger->error(
				'OrderChatz: 資料庫插入失敗',
				array(
					'source' => 'orderchatz',
					'error'  => $wpdb->last_error,
					'query'  => $wpdb->last_query,
					'data'   => $data,
				)
			);
		}
	}

	/**
	 * 儲存商品訊息到資料庫（返回結果）
	 *
	 * @param string $line_user_id LINE 用戶 ID.
	 * @param int    $product_id 商品 ID.
	 * @param string $message_text 訊息內容.
	 * @param string $product_image 商品圖片 URL.
	 * @return bool 儲存是否成功.
	 */
	private function saveProductMessageWithResult( string $line_user_id, int $product_id, string $message_text, string $product_image = '' ): bool {
		global $wpdb;
		$logger = wc_get_logger();

		$table_name = $wpdb->prefix . 'otz_messages';

		// 檢查表是否存在
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
		if ( ! $table_exists ) {
			$logger->error(
				'OrderChatz: 資料庫表不存在',
				array(
					'source'     => 'orderchatz',
					'table_name' => $table_name,
				)
			);
			return false;
		}

		$data = array(
			'line_user_id' => $line_user_id,
			'message_type' => 'product',
			'content'      => $message_text,
			'direction'    => 'outgoing',
			'is_from_line' => 0,
			'created_at'   => current_time( 'mysql' ),
			'metadata'     => wp_json_encode(
				array(
					'product_id'    => $product_id,
					'product_image' => $product_image,
				)
			),
		);

		$result = $wpdb->insert( $table_name, $data );

		$logger->info(
			'OrderChatz: 資料庫插入結果（返回版）',
			array(
				'source'     => 'orderchatz',
				'result'     => $result,
				'insert_id'  => $wpdb->insert_id,
				'last_error' => $wpdb->last_error,
			)
		);

		return $result !== false && $wpdb->insert_id > 0;
	}

	/**
	 * 儲存簡化的商品訊息到資料庫（使用月份分表）
	 *
	 * @param string $line_user_id LINE 用戶 ID.
	 * @param string $message_text 簡化的訊息內容.
	 * @return bool 儲存是否成功.
	 */
	private function saveSimpleProductMessage( string $line_user_id, string $message_text ): bool {
		global $wpdb;
		$logger = wc_get_logger();

		// 使用月份分表邏輯
		$year_month = wp_date( 'Y_m' );

		// 創建 DynamicTableManager 實例
		$error_handler      = new ErrorHandler( $wpdb, $logger );
		$security_validator = new SecurityValidator( $wpdb, $error_handler );

		$table_manager = new DynamicTableManager(
			$wpdb,
			$logger,
			$error_handler,
			$security_validator
		);

		// 確保當前月份表存在
		if ( ! $table_manager->create_monthly_message_table( $year_month ) ) {
			return false;
		}

		// 取得正確的月份分表名稱
		$table_name = $table_manager->get_monthly_message_table_name( $year_month );

		if ( ! $table_name ) {
			return false;
		}

		// 符合月份分表結構的資料，確保顯示為 outgoing 訊息
		$current_datetime = current_time( 'mysql' );
		$current_user     = wp_get_current_user();
		$sender_name      = $current_user->display_name ?: 'OrderChatz Bot';
		$data             = array(
			'event_id'        => 'manual_product_' . uniqid() . '_' . time(), // 以 'manual_' 開頭確保 is_outbound = true
			'line_user_id'    => $line_user_id,
			'source_type'     => 'user', // 商品推薦給個人用戶
			'sender_type'     => 'ACCOUNT', // 官方帳號發送 (大寫確保 is_outbound = true)
			'sender_name'     => $sender_name, // 使用當前登入使用者名稱
			'sent_date'       => wp_date( 'Y-m-d' ), // 當前日期
			'sent_time'       => wp_date( 'H:i:s' ), // 當前時間
			'message_type'    => 'text', // 文字訊息類型
			'message_content' => $message_text, // 正確的欄位名稱
			'created_by'      => get_current_user_id(), // 當前操作的管理員
			'created_at'      => $current_datetime,
		);

		$result = $wpdb->insert( $table_name, $data );
		return $result !== false && $wpdb->insert_id > 0;
	}
}
