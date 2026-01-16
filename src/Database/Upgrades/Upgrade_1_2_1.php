<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.2.1
 *
 * 為備註功能新增群組支援：
 * - 在 wp_otz_user_notes 表新增 source_type 欄位（區分個人/群組/聊天室）
 * - 在 wp_otz_user_notes 表新增 group_id 欄位（儲存群組 ID）
 * - 新增相關索引以優化查詢效能
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.2.1
 */
class Upgrade_1_2_1 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.2.1';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '為備註功能新增群組支援：在 user_notes 表新增 source_type 和 group_id 欄位';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$success = true;

			// 新增 source_type 欄位.
			if ( ! $this->add_source_type_column() ) {
				Logger::error( '新增 source_type 欄位失敗' );
				$success = false;
			}

			// 新增 group_id 欄位.
			if ( ! $this->add_group_id_column() ) {
				Logger::error( '新增 group_id 欄位失敗' );
				$success = false;
			}

			if ( $success ) {
				$this->log_message( '成功為 wp_otz_user_notes 表新增群組支援欄位' );
			}

			return $success;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.2.1 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Add source_type column to wp_otz_user_notes table
	 *
	 * @return bool True on success, false on failure.
	 */
	private function add_source_type_column(): bool {
		$table_name = $this->wpdb->prefix . 'otz_user_notes';

		// 檢查表是否存在.
		if ( ! $this->table_exists( $table_name ) ) {
			Logger::error( 'wp_otz_user_notes 表不存在，無法新增欄位' );
			return false;
		}

		// 檢查欄位是否已存在.
		$column_exists = $this->wpdb->get_results(
			"SHOW COLUMNS FROM `{$table_name}` LIKE 'source_type'"
		);

		if ( ! empty( $column_exists ) ) {
			$this->log_message( 'source_type 欄位已存在，跳過新增' );
			return true;
		}

		// 新增 source_type 欄位.
		$sql = "ALTER TABLE `{$table_name}`
			ADD COLUMN `source_type` ENUM('user','group','room') NOT NULL DEFAULT 'user'
			COMMENT '來源類型：個人、群組或聊天室'
			AFTER `line_user_id`";

		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error(
				'新增 source_type 欄位失敗',
				array(
					'error' => $this->wpdb->last_error,
				)
			);
			return false;
		}

		// 更新現有資料，將所有現有備註的 source_type 設為 'user'.
		$update_sql = "UPDATE `{$table_name}` SET `source_type` = 'user' WHERE `source_type` IS NULL OR `source_type` = ''";
		$this->wpdb->query( $update_sql );

		return true;
	}

	/**
	 * Add group_id column to wp_otz_user_notes table
	 *
	 * @return bool True on success, false on failure.
	 */
	private function add_group_id_column(): bool {
		$table_name = $this->wpdb->prefix . 'otz_user_notes';

		// 檢查欄位是否已存在.
		$column_exists = $this->wpdb->get_results(
			"SHOW COLUMNS FROM `{$table_name}` LIKE 'group_id'"
		);

		if ( ! empty( $column_exists ) ) {
			$this->log_message( 'group_id 欄位已存在，跳過新增' );
			return true;
		}

		// 新增 group_id 欄位.
		$sql = "ALTER TABLE `{$table_name}`
			ADD COLUMN `group_id` VARCHAR(64) NULL
			COMMENT '群組 ID（當 source_type 為 group/room 時使用）'
			AFTER `source_type`,
			ADD INDEX `idx_group_id` (`group_id`),
			ADD INDEX `idx_source_type_group` (`source_type`, `group_id`)";

		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error(
				'新增 group_id 欄位失敗',
				array(
					'error' => $this->wpdb->last_error,
				)
			);
			return false;
		}

		return true;
	}
}
