<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.2.3
 *
 * 補建缺失的 groups 和 group_members 表。
 * 針對從早期版本升級但 Upgrade_1_1_9 未正確執行的環境。
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.2.3
 */
class Upgrade_1_2_3 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.2.3';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '補建缺失的 groups 和 group_members 表';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// 補建 groups 表.
			if ( ! $this->ensure_groups_table() ) {
				return false;
			}

			// 補建 group_members 表.
			if ( ! $this->ensure_group_members_table() ) {
				return false;
			}

			$this->log_message( '升級到 1.2.3 完成 - 確認 groups 和 group_members 表存在' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.2.3 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * 確保 groups 表存在
	 *
	 * @return bool True on success, false on failure.
	 */
	private function ensure_groups_table(): bool {
		$table_name = $this->wpdb->prefix . 'otz_groups';

		if ( $this->table_exists( $table_name ) ) {
			$this->log_message( 'otz_groups 表已存在，跳過建立' );
			return true;
		}

		$this->log_message( '偵測到 otz_groups 表缺失，開始補建' );

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			group_id VARCHAR(64) NOT NULL UNIQUE COMMENT 'LINE Group/Room ID',
			group_name VARCHAR(255) NULL COMMENT 'Group name',
			group_avatar VARCHAR(500) NULL COMMENT 'Group avatar URL',
			source_type ENUM('group','room') NOT NULL DEFAULT 'group' COMMENT 'Source type',
			member_count INT UNSIGNED DEFAULT 0 COMMENT 'Total member count',
			last_message_time DATETIME NULL COMMENT 'Last message timestamp',
			read_time DATETIME NULL COMMENT '最後已讀時間',
			created_at DATETIME NOT NULL COMMENT 'Record creation time',
			updated_at DATETIME NULL COMMENT 'Last update time',
			KEY idx_group_id (group_id),
			KEY idx_last_message_time (last_message_time),
			KEY idx_source_type (source_type)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='LINE Groups and Rooms Information';";

		dbDelta( $sql );

		if ( ! $this->table_exists( $table_name ) ) {
			Logger::error( '補建 otz_groups 表失敗' );
			return false;
		}

		$this->log_message( '成功補建 otz_groups 表' );
		return true;
	}

	/**
	 * 確保 group_members 表存在
	 *
	 * @return bool True on success, false on failure.
	 */
	private function ensure_group_members_table(): bool {
		$table_name = $this->wpdb->prefix . 'otz_group_members';

		if ( $this->table_exists( $table_name ) ) {
			$this->log_message( 'otz_group_members 表已存在，跳過建立' );
			return true;
		}

		$this->log_message( '偵測到 otz_group_members 表缺失，開始補建' );

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			group_id VARCHAR(64) NOT NULL COMMENT 'Reference to wp_otz_groups.group_id',
			line_user_id VARCHAR(64) NOT NULL COMMENT 'LINE User ID',
			display_name VARCHAR(255) NULL COMMENT 'Member display name',
			avatar_url VARCHAR(500) NULL COMMENT 'Member avatar URL',
			joined_at DATETIME NOT NULL COMMENT 'Join timestamp',
			left_at DATETIME NULL COMMENT 'Leave timestamp (NULL = still in group)',
			role VARCHAR(50) DEFAULT 'member' COMMENT 'Member role (reserved)',
			created_at DATETIME NOT NULL COMMENT 'Record creation time',
			updated_at DATETIME NULL COMMENT 'Last update time',
			UNIQUE KEY uk_group_user (group_id, line_user_id),
			KEY idx_group_id (group_id),
			KEY idx_line_user_id (line_user_id),
			KEY idx_left_at (left_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		COMMENT='Group Members Association Table';";

		dbDelta( $sql );

		if ( ! $this->table_exists( $table_name ) ) {
			Logger::error( '補建 otz_group_members 表失敗' );
			return false;
		}

		$this->log_message( '成功補建 otz_group_members 表' );
		return true;
	}
}
