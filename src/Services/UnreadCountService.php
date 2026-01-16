<?php
/**
 * 未讀訊息計數服務
 *
 * 負責計算所有用戶的未讀訊息總數，用於選單 badge 顯示
 *
 * @package OrderChatz\Services
 * @since 1.0.0
 */

namespace OrderChatz\Services;

use OrderChatz\Util\Logger;

/**
 * 未讀訊息計數服務類別
 *
 * 提供全域未讀訊息計算和快取功能
 */
class UnreadCountService {

	/**
	 * 快取鍵名
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'orderchatz_total_unread_count';

	/**
	 * 快取過期時間（秒）
	 *
	 * @var int
	 */
	private const CACHE_EXPIRY = 60;

	/**
	 * WordPress 資料庫實例
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * 表存在性快取
	 *
	 * @var array
	 */
	private array $table_exists_cache = array();

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * 取得總未讀訊息數
	 *
	 * 優先從快取取得，如果快取不存在則重新計算並快取
	 *
	 * @return int 總未讀訊息數
	 */
	public function getTotalUnreadCount(): int {
		// 嘗試從快取取得
		$cached_count = get_transient( self::CACHE_KEY );

		if ( $cached_count !== false ) {
			return (int) $cached_count;
		}

		// 快取不存在，重新計算
		$total_count = $this->calculateTotalUnreadCount();

		// 儲存到快取
		set_transient( self::CACHE_KEY, $total_count, self::CACHE_EXPIRY );

		return $total_count;
	}

	/**
	 * 計算總未讀訊息數
	 *
	 * 基於現有的 calculateFriendStats 邏輯進行全域計算
	 *
	 * @return int 總未讀訊息數
	 */
	private function calculateTotalUnreadCount(): int {
		try {
			$total_unread = 0;

			// 取得所有有效的 LINE 用戶
			$users = $this->getActiveLineUsers();

			if ( empty( $users ) ) {
				return 0;
			}

			// 計算每個用戶的未讀數
			foreach ( $users as $user ) {
				$user_unread   = $this->calculateUserUnreadCount( $user->line_user_id, $user->read_time );
				$total_unread += $user_unread;
			}

			// 限制最大顯示數量（避免 badge 太大）
			return min( $total_unread, 999 );

		} catch ( \Exception $e ) {
			Logger::error(
				'計算未讀訊息總數失敗',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
			return 0;
		}
	}

	/**
	 * 取得所有活躍的 LINE 用戶
	 *
	 * @return array 用戶列表
	 */
	private function getActiveLineUsers(): array {
		$sql = "SELECT line_user_id, read_time 
                FROM {$this->wpdb->prefix}otz_users 
                WHERE line_user_id IS NOT NULL 
                AND line_user_id != '' 
                AND status = 'active'";

		return $this->wpdb->get_results( $sql );
	}

	/**
	 * 計算單一用戶的未讀訊息數
	 *
	 * @param string      $line_user_id LINE 用戶 ID
	 * @param string|null $read_time 用戶最後已讀時間
	 * @return int 該用戶的未讀訊息數
	 */
	private function calculateUserUnreadCount( string $line_user_id, ?string $read_time ): int {
		$unread_count = 0;

		// 如果沒有已讀時間，使用 3 小時前作為基準
		$read_threshold = $read_time ?: date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) );

		// 查詢最近 2 個月的訊息表
		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $this->wpdb->prefix . 'otz_messages_' . $search_month;

			// 檢查表是否存在
			if ( ! $this->tableExists( $table_messages ) ) {
				continue;
			}

			// 計算該月的未讀訊息數（只計算用戶發送的訊息）
			$unread_sql = "SELECT COUNT(*) FROM {$table_messages}
                          WHERE line_user_id = %s
                          AND (group_id IS NULL OR group_id = '')
                          AND sender_type = 'USER'
                          AND CONCAT(sent_date, ' ', sent_time) > %s";

			$month_unread = $this->wpdb->get_var(
				$this->wpdb->prepare( $unread_sql, $line_user_id, $read_threshold )
			);

			$unread_count += intval( $month_unread );
		}

		return $unread_count;
	}

	/**
	 * 檢查資料表是否存在
	 *
	 * @param string $table_name 資料表名稱
	 * @return bool 是否存在
	 */
	private function tableExists( string $table_name ): bool {
		// 檢查快取.
		if ( isset( $this->table_exists_cache[ $table_name ] ) ) {
			return $this->table_exists_cache[ $table_name ];
		}

		// 執行查詢.
		$query  = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		$exists = $this->wpdb->get_var( $query ) === $table_name;

		// 存入快取.
		$this->table_exists_cache[ $table_name ] = $exists;

		return $exists;
	}

	/**
	 * 清除未讀計數快取
	 *
	 * 當有新訊息接收或已讀狀態更新時呼叫
	 *
	 * @return bool 清除是否成功
	 */
	public function clearCache(): bool {
		$result = delete_transient( self::CACHE_KEY );

		return $result;
	}

	/**
	 * 強制重新計算並更新快取
	 *
	 * @return int 重新計算的未讀訊息總數
	 */
	public function refreshCount(): int {
		$this->clearCache();
		return $this->getTotalUnreadCount();
	}

	/**
	 * 取得快取狀態資訊
	 *
	 * @return array 快取狀態資訊
	 */
	public function getCacheInfo(): array {
		$cached_count  = get_transient( self::CACHE_KEY );
		$cache_timeout = get_option( '_transient_timeout_' . self::CACHE_KEY );

		return array(
			'has_cache'            => $cached_count !== false,
			'cached_count'         => $cached_count,
			'cache_expires_at'     => $cache_timeout ? date( 'Y-m-d H:i:s', $cache_timeout ) : null,
			'cache_key'            => self::CACHE_KEY,
			'cache_expiry_seconds' => self::CACHE_EXPIRY,
		);
	}
}
