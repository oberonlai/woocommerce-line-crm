<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.7
 *
 * 新增 sender_type='bot' 支援,用於儲存 AI 機器人回應訊息.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.1.7
 */
class Upgrade_1_1_7 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.7';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增訊息分表 sender_type ENUM 支援 bot 類型';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// 取得所有 otz_messages_ 開頭的資料表.
			$message_tables = $this->get_message_tables();

			if ( empty( $message_tables ) ) {
				$this->log_message( '未找到任何訊息分表，跳過升級' );
				return true;
			}

			$success_count = 0;
			$skip_count    = 0;
			$fail_count    = 0;

			foreach ( $message_tables as $table_name ) {
				// 檢查 sender_type 欄位是否已包含 'bot'.
				if ( $this->sender_type_has_bot( $table_name ) ) {
					$this->log_message( "資料表 {$table_name} 的 sender_type 已包含 'bot'，跳過" );
					++$skip_count;
					continue;
				}

				// 修改 sender_type ENUM 新增 'bot'.
				$result = $this->modify_sender_type( $table_name );

				if ( $result ) {
					++$success_count;
					$this->log_message( "成功修改資料表 {$table_name} 的 sender_type 欄位" );
				} else {
					++$fail_count;
					Logger::error( "修改資料表 {$table_name} 的 sender_type 欄位失敗" );
				}
			}

			$total = count( $message_tables );
			$this->log_message(
				"升級到 1.1.7 完成 - 總計 {$total} 個分表，成功 {$success_count} 個，跳過 {$skip_count} 個，失敗 {$fail_count} 個"
			);

			// 只要沒有失敗的就算成功.
			return 0 === $fail_count;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.7 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get all message partition tables (otz_messages_YYYY_MM).
	 *
	 * @return array Array of table names.
	 */
	private function get_message_tables(): array {
		$prefix = $this->wpdb->prefix . 'otz_messages_';
		$query  = $this->wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$prefix . '%'
		);

		$tables = $this->wpdb->get_col( $query );
		return is_array( $tables ) ? $tables : array();
	}

	/**
	 * Check if sender_type ENUM already includes 'bot'.
	 *
	 * @param string $table_name Table name.
	 * @return bool True if 'bot' is already in ENUM, false otherwise.
	 */
	private function sender_type_has_bot( string $table_name ): bool {
		$query = $this->wpdb->prepare(
			'SHOW COLUMNS FROM `' . $table_name . '` LIKE %s',
			'sender_type'
		);

		$column = $this->wpdb->get_row( $query, ARRAY_A );

		if ( ! $column || ! isset( $column['Type'] ) ) {
			return false;
		}

		// 檢查 Type 欄位是否包含 'bot'.
		return false !== strpos( $column['Type'], "'bot'" );
	}

	/**
	 * Modify sender_type ENUM to include 'bot'.
	 *
	 * @param string $table_name Table name.
	 * @return bool True on success, false on failure.
	 */
	private function modify_sender_type( string $table_name ): bool {
		$sql = "ALTER TABLE `{$table_name}`
				MODIFY COLUMN sender_type ENUM('user','account','bot') NOT NULL
				COMMENT 'Message sender: User, Official Account, or Bot'";

		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error( "修改 {$table_name} sender_type 失敗: " . $this->wpdb->last_error );
			return false;
		}

		return true;
	}
}
