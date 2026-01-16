<?php

declare(strict_types=1);

namespace OrderChatz\Database;

use wpdb;

/**
 * Otz_users 資料表類別
 *
 * 處理 otz_users 資料表的查詢操作.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.1.3
 */
class User {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Users 資料表名稱
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * 建構子
	 *
	 * @param wpdb $wpdb WordPress 資料庫物件.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'otz_users';
	}

	/**
	 * 取得所有活躍使用者（用於推播）
	 *
	 * @return array 使用者列表 [['line_user_id' => '', 'name' => '', 'picture_url' => ''], ...].
	 */
	public function get_active_users(): array {
		$sql = $this->wpdb->prepare(
			"SELECT line_user_id, display_name as name, avatar_url as picture_url
			FROM {$this->table_name}
			WHERE status = %s
			AND line_user_id IS NOT NULL
			AND line_user_id != ''",
			'active'
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return $results ? $results : array();
	}

	/**
	 * 根據 LINE User ID 取得使用者基本資料
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @return array|null 使用者資料 ['line_user_id' => '', 'line_display_name' => '', 'wp_user_id' => int] 或 null.
	 */
	public function get_user_with_wp_user_id( string $line_user_id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT line_user_id, display_name as line_display_name, wp_user_id
			FROM {$this->table_name}
			WHERE line_user_id = %s",
			$line_user_id
		);

		$result = $this->wpdb->get_row( $sql, ARRAY_A );

		return $result ? $result : null;
	}

	/**
	 * 檢查使用者是否啟用 AI Bot
	 *
	 * @param int $user_id 使用者 ID (otz_users.id).
	 * @return bool AI 已啟用時返回 true，否則返回 false.
	 */
	public function is_bot_enabled( int $user_id ): bool {
		$bot_status = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT bot_status FROM {$this->table_name} WHERE id = %d",
				$user_id
			)
		);

		if ( null === $bot_status ) {
			return false;
		}

		return 'enable' === $bot_status;
	}

	/**
	 * 更新使用者的 bot_status
	 *
	 * @param int    $user_id 使用者 ID (otz_users.id).
	 * @param string $bot_status Bot 狀態 ('enable' 或 'disable').
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function update_bot_status( int $user_id, string $bot_status ): bool {
		// 驗證 bot_status.
		$allowed_statuses = array( 'enable', 'disable' );
		if ( ! in_array( $bot_status, $allowed_statuses, true ) ) {
			return false;
		}

		// 檢查使用者是否存在.
		$user_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
				$user_id
			)
		);

		if ( ! $user_exists ) {
			return false;
		}

		// 更新 bot_status.
		$result = $this->wpdb->update(
			$this->table_name,
			array( 'bot_status' => $bot_status ),
			array( 'id' => $user_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}
}
