<?php
/**
 * 查詢自訂文章類型 Function
 *
 * @package OrderChatz\Services\Bot\Functions
 * @since 1.1.6
 */

declare(strict_types=1);

namespace OrderChatz\Services\Bot\Functions;

use OrderChatz\Util\Logger;

/**
 * 查詢自訂文章類型類別
 */
class CustomPostType implements FunctionInterface {

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
		return 'custom_post_type';
	}

	/**
	 * 取得函式描述
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Search and retrieve WordPress posts from configured post types. ' .
			'ALWAYS use this tool when user asks ANY question or mentions ANY topic. ' .
			'TRIGGER CONDITIONS (使用此工具的時機): ' .
			'1. User asks a question (e.g., "退款怎麼辦?", "運費多少?") - MUST search first ' .
			'2. User mentions a single keyword (e.g., "退款", "價格", "shipping") - MUST search first ' .
			'3. User asks about policies, terms, or information - MUST search first ' .
			'4. ALWAYS search BEFORE using your own knowledge - prioritize site content ' .
			'SEARCH RULES: ' .
			'1. Extract ONE specific keyword from user message (e.g., "退款", "訂閱", "refund") ' .
			'2. Do NOT use OR/AND operators or combine keywords ' .
			'3. For multiple topics, call this function MULTIPLE times separately ' .
			'4. If no keyword, return latest posts ' .
			'IMPORTANT: 優先使用網站內容回答，不要直接使用 AI 知識庫';
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
					'description' => 'Optional search keyword to filter posts by title, content, excerpt, categories, and tags',
				),
			),
			'required'   => array(),
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
			$keyword            = $arguments['keyword'] ?? '';
			$allowed_post_types = $arguments['_allowed_post_types'] ?? array();

			// 驗證是否有設定 post types.
			if ( empty( $allowed_post_types ) ) {
				return array(
					'success' => false,
					'error'   => '管理員尚未設定可查詢的文章類型。',
				);
			}

			// 檢查快取.
			$cached_result = $this->get_cached_result( $allowed_post_types, $keyword );
			if ( false !== $cached_result ) {
				return $cached_result;
			}

			// 查詢文章列表（含關鍵字搜尋）.
			$result = $this->query_post_list( $allowed_post_types, $keyword );

			// 如果查詢成功，儲存快取.
			if ( isset( $result['success'] ) && true === $result['success'] ) {
				$this->set_cached_result( $allowed_post_types, $keyword, $result );
			}

			return $result;

		} catch ( \Exception $e ) {
			Logger::error(
				'CustomPostType execution error: ' . $e->getMessage(),
				array(
					'arguments' => $arguments,
					'exception' => $e->getMessage(),
				),
				'otz'
			);

			return array(
				'success' => false,
				'error'   => '查詢文章時發生錯誤',
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
		// 驗證 _allowed_post_types 是否存在且為非空陣列.
		if ( ! isset( $arguments['_allowed_post_types'] ) || ! is_array( $arguments['_allowed_post_types'] ) || empty( $arguments['_allowed_post_types'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 查詢文章列表
	 *
	 * @param array  $post_types 文章類型陣列.
	 * @param string $keyword 搜尋關鍵字（選填）.
	 * @return array
	 */
	private function query_post_list( array $post_types, string $keyword = '' ): array {

		// 為每個 post type 建立子查詢.
		$union_queries = array();

		foreach ( $post_types as $post_type ) {
			if ( ! empty( $keyword ) ) {
				// 有關鍵字：搜尋 title 或 content.
				$like_keyword    = '%' . $this->wpdb->esc_like( $keyword ) . '%';
				$union_queries[] = $this->wpdb->prepare(
					"SELECT ID, post_title, post_content
					FROM {$this->wpdb->posts}
					WHERE post_type = %s
					AND post_status = 'publish'
					AND (post_title LIKE %s OR post_content LIKE %s)
					ORDER BY post_date DESC
					LIMIT 2",
					$post_type,
					$like_keyword,
					$like_keyword
				);
			} else {
				// 沒有關鍵字：按日期排序.
				$union_queries[] = $this->wpdb->prepare(
					"SELECT ID, post_title, post_content
					FROM {$this->wpdb->posts}
					WHERE post_type = %s
					AND post_status = 'publish'
					ORDER BY post_date DESC
					LIMIT 2",
					$post_type
				);
			}
		}

		// 使用 UNION ALL 組合所有子查詢.
		$sql = '(' . implode( ') UNION ALL (', $union_queries ) . ')';

		// 執行查詢.
		$results = $this->wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 檢查是否有結果.
		if ( empty( $results ) ) {
			// 如果有提供關鍵字但沒找到結果.
			if ( ! empty( $keyword ) ) {
				return array(
					'success' => false,
					'error'   => "找不到符合關鍵字「{$keyword}」的文章，請嘗試其他關鍵字。",
				);
			}

			// 如果沒有提供關鍵字且沒有文章.
			return array(
				'success' => false,
				'error'   => '找不到相關文章。',
			);
		}

		// 格式化文章資料.
		$posts = array();
		foreach ( $results as $row ) {
			// 將資料庫結果轉換為 WP_Post 物件.
			$post    = new \WP_Post( (object) $row );
			$posts[] = $this->format_post_data( $post, $keyword );
		}

		return array(
			'success' => true,
			'data'    => array(
				'posts' => $posts,
				'total' => count( $results ),
			),
		);
	}

	/**
	 * 格式化文章資料
	 *
	 * @param \WP_Post $post 文章物件.
	 * @param string   $keyword 搜尋關鍵字.
	 * @return array
	 */
	private function format_post_data( \WP_Post $post, string $keyword = '' ): array {
		return array(
			'post_title'   => $post->post_title,
			'post_content' => $this->extract_content_snippet( $post->post_content, $keyword ),
			'permalink'    => home_url() . '/?p=' . $post->ID,
		);
	}

	/**
	 * 擷取內容片段（關鍵字前後各 1000 字）
	 *
	 * @param string $content 完整文章內容.
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

	/**
	 * 生成快取 key
	 *
	 * @param array  $post_types Post types 陣列.
	 * @param string $keyword 搜尋關鍵字.
	 * @return string
	 */
	private function generate_cache_key( array $post_types, string $keyword ): string {
		// 排序 post types 確保順序一致.
		sort( $post_types );

		// 組合 post types 為字串.
		$post_types_str = implode( '-', $post_types );

		// 如果有關鍵字，加入 hash.
		$keyword_hash = empty( $keyword ) ? 'none' : md5( $keyword );

		return "otz_cpt_{$post_types_str}_{$keyword_hash}";
	}

	/**
	 * 取得快取結果
	 *
	 * @param array  $post_types Post types 陣列.
	 * @param string $keyword 搜尋關鍵字.
	 * @return mixed 快取結果或 false.
	 */
	private function get_cached_result( array $post_types, string $keyword ) {
		$cache_key = $this->generate_cache_key( $post_types, $keyword );
		return wp_cache_get( $cache_key, 'otz_custom_post_type' );
	}

	/**
	 * 儲存快取結果
	 *
	 * @param array  $post_types Post types 陣列.
	 * @param string $keyword 搜尋關鍵字.
	 * @param array  $result 查詢結果.
	 * @return bool
	 */
	private function set_cached_result( array $post_types, string $keyword, array $result ): bool {
		$cache_key = $this->generate_cache_key( $post_types, $keyword );
		return wp_cache_set( $cache_key, $result, 'otz_custom_post_type', 3600 ); // 1 小時.
	}

}
