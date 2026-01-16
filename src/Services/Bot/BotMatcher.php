<?php

declare(strict_types=1);

namespace OrderChatz\Services\Bot;

use OrderChatz\Database\Bot\Bot;
use OrderChatz\Database\User;
use OrderChatz\Util\Logger;
use wpdb;

/**
 * Bot 關鍵字比對服務
 *
 * 處理訊息與 Bot 關鍵字的比對邏輯.
 * 管理 Bot 觸發與使用者 bot_status 更新.
 *
 * @package    OrderChatz
 * @subpackage Services\Bot
 * @since      1.1.6
 */
class BotMatcher {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Bot 資料庫操作物件
	 *
	 * @var Bot
	 */
	private Bot $bot;

	/**
	 * User 資料庫操作物件
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * 快取鍵名稱
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'otz_active_bots';

	/**
	 * 快取時間（秒）
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 1800; // 30 分鐘.

	/**
	 * 建構子
	 *
	 * @param wpdb $wpdb WordPress 資料庫物件.
	 * @param Bot  $bot Bot 資料庫操作物件.
	 * @param User $user User 資料庫操作物件.
	 */
	public function __construct( wpdb $wpdb, Bot $bot, User $user ) {
		$this->wpdb = $wpdb;
		$this->bot  = $bot;
		$this->user = $user;
	}

	/**
	 * 比對訊息與關鍵字，返回匹配的 Bot
	 *
	 * 流程:
	 * 1. 取得所有啟用的 Bot (按 priority ASC 排序，使用快取)
	 * 2. 逐一比對關鍵字
	 * 3. 返回第一個匹配的 Bot (優先順序最高)
	 *
	 * @param string $message 使用者訊息.
	 * @return array|null 匹配的 Bot 資料或 null.
	 */
	public function match_keyword( string $message ): ?array {
		try {
			// 取得所有啟用的 Bot，按優先順序排序 (使用快取).
			$active_bots = $this->get_active_bots();

			if ( empty( $active_bots ) ) {
				return null;
			}

			// 移除訊息首尾空白並轉小寫以進行不區分大小寫的比對.
			$normalized_message = mb_strtolower( trim( $message ), 'UTF-8' );

			// 逐一比對每個 Bot 的關鍵字.
			foreach ( $active_bots as $bot ) {
				if ( empty( $bot['keywords'] ) || ! is_array( $bot['keywords'] ) ) {
					continue;
				}

				// 檢查是否有任一關鍵字匹配.
				foreach ( $bot['keywords'] as $keyword ) {
					$normalized_keyword = mb_strtolower( trim( $keyword ), 'UTF-8' );

					// 完全匹配或包含匹配.
					if ( $normalized_message === $normalized_keyword || str_contains( $normalized_message, $normalized_keyword ) ) {
						return $bot;
					}
				}
			}

			return null;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in match_keyword: ' . $e->getMessage(), array(), 'otz' );
			return null;
		}
	}

	/**
	 * 處理 Bot 觸發後的動作
	 *
	 * 流程:
	 * 1. 根據 action_type 更新使用者的 bot_status
	 * 2. 更新 Bot 的觸發次數
	 *
	 * @param int    $bot_id Bot ID.
	 * @param int    $user_id 使用者 ID (otz_users.id).
	 * @param string $action_type Bot 的 action_type ('ai' 或 'human').
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function handle_bot_trigger( int $bot_id, int $user_id, string $action_type ): bool {
		try {
			// 驗證 action_type.
			$allowed_action_types = array( 'ai', 'human' );
			if ( ! in_array( $action_type, $allowed_action_types, true ) ) {
				Logger::error( "Invalid action_type: {$action_type}", array(), 'otz' );
				return false;
			}

			// 根據 action_type 決定 bot_status.
			$new_bot_status = ( 'ai' === $action_type ) ? 'enable' : 'disable';

			// 更新使用者的 bot_status (委派給 User 類別處理).
			$update_user_result = $this->user->update_bot_status( $user_id, $new_bot_status );

			if ( ! $update_user_result ) {
				Logger::error(
					'Failed to update user bot_status',
					array(
						'user_id'    => $user_id,
						'bot_status' => $new_bot_status,
					),
					'otz'
				);
				return false;
			}

			// 更新 Bot 的觸發次數.
			$update_trigger_result = $this->increment_bot_trigger_count( $bot_id );

			if ( ! $update_trigger_result ) {
				Logger::error( "Failed to update bot trigger count for bot_id: {$bot_id}", array(), 'otz' );
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in handle_bot_trigger: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 增加 Bot 的觸發次數
	 *
	 * @param int $bot_id Bot ID.
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	private function increment_bot_trigger_count( int $bot_id ): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_bot';

			// 使用 SQL 直接增加計數，避免併發問題.
			$result = $this->wpdb->query(
				$this->wpdb->prepare(
					"UPDATE {$table_name} SET trigger_count = trigger_count + 1 WHERE id = %d",
					$bot_id
				)
			);

			if ( false === $result ) {
				Logger::error( 'Failed to increment bot trigger count: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in increment_bot_trigger_count: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 檢查使用者是否啟用 AI Bot
	 *
	 * @param int $user_id 使用者 ID (otz_users.id).
	 * @return bool AI 已啟用時返回 true，否則返回 false.
	 */
	public function is_bot_enabled_for_user( int $user_id ): bool {
		// 委派給 User 類別處理.
		return $this->user->is_bot_enabled( $user_id );
	}

	/**
	 * 取得所有啟用的 Bot（使用快取）
	 *
	 * 流程:
	 * 1. 檢查快取是否存在
	 * 2. 快取存在則直接返回
	 * 3. 快取不存在則查詢資料庫並寫入快取
	 *
	 * @return array 啟用的 Bot 陣列.
	 */
	private function get_active_bots(): array {
		// 嘗試從快取取得.
		$cached_bots = get_transient( self::CACHE_KEY );

		if ( false !== $cached_bots && is_array( $cached_bots ) ) {
			return $cached_bots;
		}

		// 快取不存在，從資料庫查詢.
		$active_bots = $this->bot->get_bots_by_status(
			'active',
			array(
				'order_by' => 'priority',
				'order'    => 'ASC',
			)
		);

		// 寫入快取.
		if ( ! empty( $active_bots ) ) {
			set_transient( self::CACHE_KEY, $active_bots, self::CACHE_EXPIRATION );
		}

		return $active_bots;
	}

	/**
	 * 清除 Bot 快取
	 *
	 * 在以下情況需要清除快取:
	 * - Bot 新增/更新/刪除時
	 * - Bot 狀態改變時
	 * - Bot 優先順序改變時
	 *
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function clear_cache(): bool {
		return delete_transient( self::CACHE_KEY );
	}

	/**
	 * 刷新 Bot 快取
	 *
	 * 清除現有快取並重新載入資料.
	 *
	 * @return array 刷新後的 Bot 陣列.
	 */
	public function refresh_cache(): array {
		// 清除快取.
		$this->clear_cache();

		// 重新載入並快取.
		return $this->get_active_bots();
	}
}
