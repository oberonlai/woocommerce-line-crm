<?php
/**
 * 查詢產品資訊 Function
 *
 * @package OrderChatz\Services\Bot\Functions
 * @since 1.1.6
 */

declare(strict_types=1);

namespace OrderChatz\Services\Bot\Functions;

use OrderChatz\Util\Logger;

/**
 * 查詢產品資訊類別
 */
class ProductInfo implements FunctionInterface {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * 建構子
	 *
	 * @param \wpdb $wpdb WordPress 資料庫物件.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * 取得函式名稱
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'product_info';
	}

	/**
	 * 取得函式描述
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Search and retrieve WooCommerce product information by keyword. ' .
			'Use this tool to find products when users ask about product details, prices, stock status, or specific items. ' .
			'You can search across product titles, descriptions, SKUs, categories, and tags.';
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
				'keyword' => array(
					'type'        => 'string',
					'description' => 'Search keyword to filter products by title, content, SKU, categories, and tags',
				),
			),
			'required'   => array( 'keyword' ),
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
			$keyword = $arguments['keyword'] ?? '';

			// 檢查關鍵字是否為空.
			if ( empty( $keyword ) ) {
				return array(
					'success' => false,
					'error'   => '請告訴我你想要搜尋的產品名稱或關鍵字。',
				);
			}

			// 檢查 WooCommerce 是否啟用.
			if ( ! class_exists( 'WooCommerce' ) ) {
				return array(
					'success' => false,
					'error'   => 'WooCommerce 外掛未啟用，無法查詢產品資訊。',
				);
			}

			// 查詢產品列表.
			return $this->query_product_list( $keyword );

		} catch ( \Exception $e ) {
			Logger::error(
				'ProductInfo execution error: ' . $e->getMessage(),
				array(
					'arguments' => $arguments,
					'exception' => $e->getMessage(),
				),
				'otz'
			);

			return array(
				'success' => false,
				'error'   => '查詢產品時發生錯誤，請稍後再試。',
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
		if ( ! isset( $arguments['keyword'] ) || empty( $arguments['keyword'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 查詢產品列表
	 *
	 * @param string $keyword 搜尋關鍵字.
	 * @return array
	 */
	private function query_product_list( string $keyword ): array {

		$start_time = microtime( true );

		// 準備查詢關鍵字.
		$like_keyword = '%' . $this->wpdb->esc_like( $keyword ) . '%';

		// 查詢產品：搜尋標題、內容、摘要和 SKU.
		$sql = $this->wpdb->prepare(
			"SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_excerpt
			FROM {$this->wpdb->posts} p
			LEFT JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND (
				p.post_title LIKE %s
				OR p.post_content LIKE %s
				OR p.post_excerpt LIKE %s
				OR pm.meta_value LIKE %s
			)
			ORDER BY p.post_date DESC
			LIMIT 5",
			$like_keyword,
			$like_keyword,
			$like_keyword,
			$like_keyword
		);

		// 執行查詢.
		$results = $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 檢查是否有結果.
		if ( empty( $results ) ) {
			return array(
				'success' => false,
				'error'   => "找不到符合關鍵字「{$keyword}」的產品。請嘗試使用其他關鍵字，或告訴我您想找的商品類型。",
			);
		}

		// 格式化產品資料.
		$products = array();
		foreach ( $results as $row ) {
			$product_id = (int) $row->ID;
			$product    = wc_get_product( $product_id );

			// 跳過無效的產品.
			if ( ! $product ) {
				continue;
			}

			$products[] = $this->format_product_data( $product, $keyword );
		}

		return array(
			'success' => true,
			'data'    => array(
				'products' => $products,
				'total'    => count( $products ),
			),
		);
	}

	/**
	 * 格式化產品資料
	 *
	 * @param \WC_Product $product 產品物件.
	 * @param string      $keyword 搜尋關鍵字.
	 * @return array
	 */
	private function format_product_data( \WC_Product $product, string $keyword = '' ): array {

		// 基本資訊.
		$data = array(
			'id'        => $product->get_id(),
			'name'      => $product->get_name(),
			'price'     => wp_strip_all_tags( $product->get_price_html() ),
			'sku'       => $product->get_sku(),
			'type'      => $product->get_type(),
			'permalink' => $product->get_permalink(),
		);

		// 產品圖片.
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$data['image'] = wp_get_attachment_image_url( $image_id, 'medium' );
		}

		// 詳細描述（限制長度）.
		$description = $product->get_description();
		if ( ! empty( $description ) ) {
			$data['description'] = $this->extract_content_snippet( $description, $keyword );
		}

		// 短描述.
		$short_description = $product->get_short_description();
		if ( ! empty( $short_description ) ) {
			$data['short_description'] = wp_strip_all_tags( $short_description );
		}

		// 庫存狀態.
		$data['stock_status'] = $product->get_stock_status();

		// 庫存數量（如果有管理庫存）.
		if ( $product->managing_stock() ) {
			$data['stock_quantity'] = $product->get_stock_quantity();
		}

		// 分類.
		$category_ids = $product->get_category_ids();
		if ( ! empty( $category_ids ) ) {
			$categories = array();
			foreach ( $category_ids as $cat_id ) {
				$term = get_term( $cat_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$categories[] = $term->name;
				}
			}
			$data['categories'] = $categories;
		}

		// 標籤.
		$tag_ids = $product->get_tag_ids();
		if ( ! empty( $tag_ids ) ) {
			$tags = array();
			foreach ( $tag_ids as $tag_id ) {
				$term = get_term( $tag_id, 'product_tag' );
				if ( $term && ! is_wp_error( $term ) ) {
					$tags[] = $term->name;
				}
			}
			$data['tags'] = $tags;
		}

		// 可變商品：列出變體資訊.
		if ( 'variable' === $product->get_type() ) {
			$data['variations'] = $this->format_variations( $product );
		}

		return $data;
	}

	/**
	 * 格式化可變商品的變體資訊（簡化版）
	 *
	 * @param \WC_Product_Variable $product 可變商品物件.
	 * @return array
	 */
	private function format_variations( \WC_Product_Variable $product ): array {

		$variations    = array();
		$variation_ids = $product->get_children();

		// 限制最多回傳 10 個變體.
		$variation_ids = array_slice( $variation_ids, 0, 10 );

		foreach ( $variation_ids as $variation_id ) {
			$variation_obj = wc_get_product( $variation_id );

			if ( ! $variation_obj ) {
				continue;
			}

			// 取得變體屬性組合.
			$attributes      = $variation_obj->get_variation_attributes();
			$attribute_names = array();

			foreach ( $attributes as $attr_name => $attr_value ) {
				// 移除 attribute_ 前綴.
				$clean_name        = str_replace( 'attribute_', '', $attr_name );
				$attribute_names[] = $attr_value;
			}

			$variation_name = implode( ' / ', $attribute_names );

			$variations[] = array(
				'name'         => $variation_name,
				'price'        => wp_strip_all_tags( $variation_obj->get_price_html() ),
				'stock_status' => $variation_obj->get_stock_status(),
				'sku'          => $variation_obj->get_sku(),
			);
		}

		return $variations;
	}

	/**
	 * 擷取內容片段（關鍵字前後各 1000 字）
	 *
	 * @param string $content 完整產品描述內容.
	 * @param string $keyword 搜尋關鍵字.
	 * @return string 擷取的內容片段.
	 */
	private function extract_content_snippet( string $content, string $keyword ): string {

		// 移除 WordPress block 註釋標記.
		$content = preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );

		// 移除 HTML 標籤和多餘空白.
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\s+/', ' ', trim( $content ) );

		// 如果沒有關鍵字，返回前 2000 字.
		if ( empty( $keyword ) ) {
			$content_length = mb_strlen( $content );
			if ( $content_length <= 2000 ) {
				return $content;
			}
			return mb_substr( $content, 0, 2000 ) . '...';
		}

		// 計算內容長度.
		$content_length = mb_strlen( $content );

		// 如果內容少於 2000 字，直接返回完整內容.
		if ( $content_length <= 2000 ) {
			return $content;
		}

		// 尋找首個關鍵字位置（不區分大小寫）.
		$keyword_position = mb_stripos( $content, $keyword );

		// 如果找不到關鍵字，返回前 2000 字.
		if ( false === $keyword_position ) {
			return mb_substr( $content, 0, 2000 ) . '...';
		}

		// 計算擷取範圍：關鍵字前後各 1000 字.
		$start_position = max( 0, $keyword_position - 1000 );
		$snippet_length = 2000;

		// 調整長度以避免超出內容範圍.
		if ( $start_position + $snippet_length > $content_length ) {
			$snippet_length = $content_length - $start_position;
		}

		// 擷取片段.
		$snippet = mb_substr( $content, $start_position, $snippet_length );

		// 添加省略符號.
		$prefix = ( $start_position > 0 ) ? '...' : '';
		$suffix = ( $start_position + $snippet_length < $content_length ) ? '...' : '';

		return $prefix . $snippet . $suffix;
	}
}
