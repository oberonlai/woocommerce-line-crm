<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.2
 *
 * 修正九月訊息錯誤存入十月分表的問題.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_1_2 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.2';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '修正九月訊息錯誤存入十月分表的問題';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$october_table   = $this->wpdb->prefix . 'otz_messages_2025_10';
			$september_table = $this->wpdb->prefix . 'otz_messages_2025_09';

			// Check if October table exists.
			if ( ! $this->table_exists( $october_table ) ) {
				return true;
			}

			// Count September messages in October table.
			$count_query = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM `{$october_table}` WHERE sent_date LIKE %s",
				'2025-09-%'
			);
			$count       = (int) $this->wpdb->get_var( $count_query );

			if ( $count === 0 ) {
				return true;
			}

			$this->log_message( "發現 {$count} 筆九月訊息錯誤存入十月分表，開始遷移..." );

			// Ensure September table exists.
			if ( ! $this->table_exists( $september_table ) ) {
				$dynamic_manager = new DynamicTableManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator );
				if ( ! $dynamic_manager->create_monthly_message_table( '2025_09' ) ) {
					throw new \Exception( '無法建立九月分表' );
				}
			}

			// Copy September messages to September table using INSERT IGNORE to avoid duplicates.
			// Exclude id column to avoid primary key conflicts, let AUTO_INCREMENT generate new ids.
			$insert_query = "INSERT IGNORE INTO `{$september_table}`
				(event_id, line_user_id, source_type, sender_type, sender_name, group_id,
				 sent_date, sent_time, message_type, message_content, reply_token, quote_token,
				 quoted_message_id, line_message_id, raw_payload, created_by, created_at)
				SELECT event_id, line_user_id, source_type, sender_type, sender_name, group_id,
				       sent_date, sent_time, message_type, message_content, reply_token, quote_token,
				       quoted_message_id, line_message_id, raw_payload, created_by, created_at
				FROM `{$october_table}`
				WHERE sent_date LIKE '2025-09-%'";

			$result = $this->wpdb->query( $insert_query );

			if ( false === $result ) {
				throw new \Exception( '資料遷移失敗: ' . $this->wpdb->last_error );
			}

			// Delete migrated records from October table.
			$delete_query  = "DELETE FROM `{$october_table}` WHERE sent_date LIKE '2025-09-%'";
			$delete_result = $this->wpdb->query( $delete_query );

			if ( false === $delete_result ) {
				Logger::error( '從十月分表刪除已遷移記錄失敗: ' . $this->wpdb->last_error );
			} else {
				$this->log_message( "成功從十月分表刪除 {$delete_result} 筆已遷移記錄" );
			}

			// Verify migration.
			$remaining_count = (int) $this->wpdb->get_var( $count_query );
			if ( $remaining_count > 0 ) {
				Logger::error( "遷移後仍有 {$remaining_count} 筆九月訊息留在十月分表" );
			}

			$this->log_message( '升級到 1.1.2 完成 - 九月訊息已遷移至正確分表' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.2 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}