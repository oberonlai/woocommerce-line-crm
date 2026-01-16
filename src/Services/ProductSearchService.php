<?php
/**
 * OrderChatz 商品搜尋服務
 *
 * 處理 WooCommerce 商品搜尋相關功能
 *
 * @package OrderChatz\Services
 * @since 1.0.0
 */

namespace OrderChatz\Services;

use Exception;

class ProductSearchService {

	/**
	 * 搜尋 WooCommerce 商品（用於推播頁面的商品選擇）
	 *
	 * @param string $query 搜尋關鍵字
	 * @return array 商品列表
	 */
	public function searchProducts( $query ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 20,
				's'      => $query,
			)
		);

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'price' => $product->get_price_html(),
				'image' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
			);
		}

		return $results;
	}

	/**
	 * 搜尋 WooCommerce 商品用於篩選條件（包含可變商品規格）
	 *
	 * @param string      $query          搜尋關鍵字.
	 * @param string|null $stock_operator 庫存運算符（<=, >=, <, >, =）.
	 * @param int|null    $stock_value    庫存數值.
	 * @return array 商品列表.
	 */
	public function searchProductsForFilter( $query, $stock_operator = null, $stock_value = null ): array {

		$results = array();

		// 如果提供了庫存條件，使用庫存篩選.
		if ( $stock_operator !== null && $stock_value !== null ) {
			return $this->search_products_by_stock( $stock_operator, $stock_value );
		}

		// 一般關鍵字搜尋.
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 50,
				's'      => $query,
			)
		);

		foreach ( $products as $product ) {
			if ( $product->get_type() === 'variable' ) {
				$variations = $product->get_children();
				foreach ( $variations as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation && $variation->is_purchasable() ) {
						$variation_name = $product->get_name() . ' - ' . implode( ', ', $variation->get_variation_attributes() );
						$results[]      = array(
							'id'        => $variation_id,
							'name'      => $variation_name,
							'sku'       => $variation->get_sku(),
							'type'      => 'variation',
							'parent_id' => $product->get_id(),
						);
					}
				}
			} else {
				$results[] = array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'sku'  => $product->get_sku(),
					'type' => 'simple',
				);
			}
		}

		return $results;
	}

	/**
	 * 依據庫存條件搜尋商品
	 *
	 * @param string $operator 運算符（<=, >=, <, >, =）.
	 * @param int    $value    庫存數值.
	 * @return array 符合條件的商品列表.
	 */
	private function search_products_by_stock( string $operator, int $value ): array {
		$results  = array();
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 50,
			)
		);

		foreach ( $products as $product ) {
			if ( $product->get_type() === 'variable' ) {
				// 可變商品：檢查每個規格的庫存.
				$variations = $product->get_children();
				foreach ( $variations as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation || ! $variation->is_purchasable() ) {
						continue;
					}

					// 只處理有啟用庫存管理的規格.
					if ( ! $variation->managing_stock() ) {
						continue;
					}

					$stock_quantity = $variation->get_stock_quantity();
					if ( $this->compare_stock( $stock_quantity, $operator, $value ) ) {
						$variation_name = $product->get_name() . ' - ' . implode( ', ', $variation->get_variation_attributes() );
						$results[]      = array(
							'id'        => $variation_id,
							'name'      => $variation_name . ' (庫存: ' . $stock_quantity . ')',
							'sku'       => $variation->get_sku(),
							'type'      => 'variation',
							'parent_id' => $product->get_id(),
							'stock'     => $stock_quantity,
						);
					}
				}
			} else {
				// 簡單商品：檢查商品庫存.
				// 只處理有啟用庫存管理的商品.
				if ( ! $product->managing_stock() ) {
					continue;
				}

				$stock_quantity = $product->get_stock_quantity();
				if ( $this->compare_stock( $stock_quantity, $operator, $value ) ) {
					$results[] = array(
						'id'    => $product->get_id(),
						'name'  => $product->get_name() . ' (庫存: ' . $stock_quantity . ')',
						'sku'   => $product->get_sku(),
						'type'  => 'simple',
						'stock' => $stock_quantity,
					);
				}
			}
		}

		return $results;
	}

	/**
	 * 比較庫存數量
	 *
	 * @param int|null $stock_quantity 庫存數量.
	 * @param string   $operator       運算符.
	 * @param int      $value          比較值.
	 * @return bool 是否符合條件.
	 */
	private function compare_stock( $stock_quantity, string $operator, int $value ): bool {
		// 如果庫存為 null，視為不符合條件.
		if ( $stock_quantity === null ) {
			return false;
		}

		switch ( $operator ) {
			case '<=':
				return $stock_quantity <= $value;
			case '>=':
				return $stock_quantity >= $value;
			case '<':
				return $stock_quantity < $value;
			case '>':
				return $stock_quantity > $value;
			case '=':
				return $stock_quantity === $value;
			default:
				return false;
		}
	}

	/**
	 * 搜尋 WooCommerce 商品類別
	 *
	 * @param string $query 搜尋關鍵字.
	 * @return array 類別列表.
	 */
	public function searchProductCategories( string $query ): array {
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 20,
		);

		// 只在有搜尋關鍵字時才加入 search 參數.
		if ( ! empty( $query ) ) {
			$args['search'] = $query;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$results = array();
		foreach ( $terms as $term ) {
			$results[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $results;
	}

	/**
	 * 搜尋 WooCommerce 商品標籤
	 *
	 * @param string $query 搜尋關鍵字.
	 * @return array 標籤列表.
	 */
	public function searchProductTags( string $query ): array {
		$args = array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'number'     => 20,
		);

		// 只在有搜尋關鍵字時才加入 search 參數.
		if ( ! empty( $query ) ) {
			$args['search'] = $query;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$results = array();
		foreach ( $terms as $term ) {
			$results[] = array(
				'id'    => $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}

		return $results;
	}

	/**
	 * 根據商品 ID 獲取商品資訊
	 *
	 * @param int $product_id 商品 ID
	 * @return array|null 商品資訊
	 */
	public function getProductById( $product_id ): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		return array(
			'id'    => $product->get_id(),
			'name'  => $product->get_name(),
			'price' => $product->get_price_html(),
			'image' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
			'sku'   => $product->get_sku(),
			'type'  => $product->get_type(),
		);
	}

	/**
	 * 根據類別 ID 獲取類別資訊
	 *
	 * @param int $category_id 類別 ID
	 * @return array|null 類別資訊
	 */
	public function getCategoryById( $category_id ): ?array {
		$term = get_term( $category_id, 'product_cat' );

		if ( is_wp_error( $term ) || ! $term ) {
			return null;
		}

		return array(
			'id'   => $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
		);
	}

	/**
	 * 檢查商品是否存在且已發布
	 *
	 * @param int $product_id 商品 ID
	 * @return bool 是否存在
	 */
	public function isProductAvailable( $product_id ): bool {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		return $product && $product->get_status() === 'publish';
	}

	/**
	 * 獲取商品的所有變體
	 *
	 * @param int $product_id 商品 ID
	 * @return array 變體列表
	 */
	public function getProductVariations( $product_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || $product->get_type() !== 'variable' ) {
			return array();
		}

		$variations = array();
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->is_purchasable() ) {
				$variations[] = array(
					'id'         => $variation_id,
					'name'       => $product->get_name() . ' - ' . implode( ', ', $variation->get_variation_attributes() ),
					'sku'        => $variation->get_sku(),
					'price'      => $variation->get_price_html(),
					'attributes' => $variation->get_variation_attributes(),
				);
			}
		}

		return $variations;
	}

	/**
	 * 驗證搜尋查詢字串
	 *
	 * @param string $query 查詢字串
	 * @return string|false 清理後的查詢字串或 false
	 */
	public function validateSearchQuery( $query ): string|false {
		$query = sanitize_text_field( trim( $query ) );

		if ( empty( $query ) || strlen( $query ) < 1 ) {
			return false;
		}

		return $query;
	}

	/**
	 * 獲取熱門商品（基於銷售數量）
	 *
	 * @param int $limit 限制數量
	 * @return array 商品列表
	 */
	public function getPopularProducts( $limit = 10 ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => $limit,
				'orderby' => 'popularity',
				'order'   => 'DESC',
			)
		);

		$results = array();
		foreach ( $products as $product ) {
			$results[] = array(
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'price'       => $product->get_price_html(),
				'image'       => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'total_sales' => $product->get_total_sales(),
			);
		}

		return $results;
	}
}
