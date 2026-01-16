<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.2.0
 *
 * 為群組訊息新增已讀追蹤功能：
 * - 在 wp_otz_groups 表新增 read_time 欄位
 * - 用於追蹤群組訊息的已讀狀態，計算未讀訊息數
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.2.0
 */
class Upgrade_1_2_0 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.2.0';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '為群組新增已讀追蹤功能：在 groups 表新增 read_time 欄位';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// 新增 read_time 欄位到 wp_otz_groups 表.
			if ( $this->add_read_time_column() ) {
				$this->log_message( '成功新增 read_time 欄位到 wp_otz_groups 表' );
				return true;
			} else {
				Logger::error( '新增 read_time 欄位失敗' );
				return false;
			}
		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.2.0 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Add read_time column to wp_otz_groups table
	 *
	 * @return bool True on success, false on failure.
	 */
	private function add_read_time_column(): bool {
		$table_name = $this->wpdb->prefix . 'otz_groups';

		// 檢查表是否存在.
		if ( ! $this->table_exists( $table_name ) ) {
			Logger::error( 'wp_otz_groups 表不存在，無法新增欄位' );
			return false;
		}

		// 檢查欄位是否已存在.
		$column_exists = $this->wpdb->get_results(
			"SHOW COLUMNS FROM `{$table_name}` LIKE 'read_time'"
		);

		if ( ! empty( $column_exists ) ) {
			$this->log_message( 'read_time 欄位已存在，跳過新增' );
			return true;
		}

		// 新增 read_time 欄位.
		$sql = "ALTER TABLE `{$table_name}`
			ADD COLUMN `read_time` DATETIME NULL COMMENT 'Last read timestamp for unread count calculation'
			AFTER `last_message_time`,
			ADD INDEX `idx_read_time` (`read_time`)";

		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error(
				'新增 read_time 欄位失敗',
				array(
					'error' => $this->wpdb->last_error,
				)
			);
			return false;
		}

		// 初始化現有群組的 read_time 為當前時間（避免一開始全部都是未讀）.
		$init_sql = "UPDATE `{$table_name}` SET `read_time` = NOW() WHERE `read_time` IS NULL";
		$this->wpdb->query( $init_sql );

		return true;
	}
}
