<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.9
 *
 * 建立多群組架構支援：
 * - 新增 wp_otz_groups 表（群組資訊表）
 * - 新增 wp_otz_group_members 表（群組成員關聯表）
 * - 從現有訊息表回填群組基礎資料
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.1.9
 */
class Upgrade_1_1_9 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.9';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '建立多群組架構：新增 groups 和 group_members 表，並回填現有群組資料';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$success_count = 0;

			// 階段一：創建 wp_otz_groups 表.
			if ( $this->create_groups_table() ) {
				++$success_count;
				$this->log_message( '成功創建 wp_otz_groups 表' );
			} else {
				Logger::error( '創建 wp_otz_groups 表失敗' );
				return false;
			}

			// 階段二：創建 wp_otz_group_members 表.
			if ( $this->create_group_members_table() ) {
				++$success_count;
				$this->log_message( '成功創建 wp_otz_group_members 表' );
			} else {
				Logger::error( '創建 wp_otz_group_members 表失敗' );
				return false;
			}

			// 階段三：從訊息表回填群組資料.
			$backfill_result = $this->backfill_group_data();
			if ( $backfill_result['success'] ) {
				$this->log_message(
					sprintf(
						'成功回填群組資料 - 群組: %d 筆, 成員: %d 筆',
						$backfill_result['groups_count'],
						$backfill_result['members_count']
					)
				);
			} else {
				Logger::error( '回填群組資料失敗: ' . $backfill_result['error'] );
				// 不中斷升級，因為表已經創建成功.
			}

			$this->log_message(
				sprintf(
					'升級到 1.1.9 完成 - 成功創建 %d 個表',
					$success_count
				)
			);

			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.9 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Create wp_otz_groups table
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_groups_table(): bool {
		$table_name = $this->wpdb->prefix . 'otz_groups';

		// 檢查表是否已存在.
		if ( $this->table_exists( $table_name ) ) {
			$this->log_message( 'wp_otz_groups 表已存在，跳過創建' );
			return true;
		}

		// Prepare CREATE TABLE SQL.
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			group_id VARCHAR(64) NOT NULL UNIQUE COMMENT 'LINE Group/Room ID',
			group_name VARCHAR(255) NULL COMMENT 'Group name',
			group_avatar VARCHAR(500) NULL COMMENT 'Group avatar URL',
			source_type ENUM('group','room') NOT NULL DEFAULT 'group' COMMENT 'Source type',
			member_count INT UNSIGNED DEFAULT 0 COMMENT 'Total member count',
			last_message_time DATETIME NULL COMMENT 'Last message timestamp',
			created_at DATETIME NOT NULL COMMENT 'Record creation time',
			updated_at DATETIME NULL COMMENT 'Last update time',
			KEY idx_group_id (group_id),
			KEY idx_last_message_time (last_message_time),
			KEY idx_source_type (source_type)
		) {$charset_collate} ENGINE=InnoDB COMMENT='LINE Groups and Rooms Information';";

		// Execute using dbDelta.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );

		// Verify table creation.
		return $this->table_exists( $table_name );
	}

	/**
	 * Create wp_otz_group_members table
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_group_members_table(): bool {
		$table_name = $this->wpdb->prefix . 'otz_group_members';

		// 檢查表是否已存在.
		if ( $this->table_exists( $table_name ) ) {
			$this->log_message( 'wp_otz_group_members 表已存在，跳過創建' );
			return true;
		}

		// Prepare CREATE TABLE SQL.
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
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
		) {$charset_collate} ENGINE=InnoDB COMMENT='Group Members Association Table';";

		// Execute using dbDelta.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $sql );

		// Verify table creation.
		return $this->table_exists( $table_name );
	}

	/**
	 * Backfill group data from existing message tables
	 *
	 * @return array Result array with success status and counts.
	 */
	private function backfill_group_data(): array {
		try {
			$groups_table        = $this->wpdb->prefix . 'otz_groups';
			$group_members_table = $this->wpdb->prefix . 'otz_group_members';
			$users_table         = $this->wpdb->prefix . 'otz_users';

			// 查詢所有訊息表（最近 6 個月）.
			$message_tables = array();
			for ( $i = 0; $i < 6; $i++ ) {
				$month        = date( 'Y_m', strtotime( "-{$i} month" ) );
				$table_name   = $this->wpdb->prefix . 'otz_messages_' . $month;
				if ( $this->table_exists( $table_name ) ) {
					$message_tables[] = $table_name;
				}
			}

			if ( empty( $message_tables ) ) {
				$this->log_message( '沒有找到訊息表，跳過資料回填' );
				return array(
					'success'        => true,
					'groups_count'   => 0,
					'members_count'  => 0,
					'error'          => null,
				);
			}

			// 階段一：從訊息表提取群組資訊.
			$groups_inserted = 0;
			foreach ( $message_tables as $table ) {
				$sql = "INSERT IGNORE INTO `{$groups_table}`
					(group_id, source_type, last_message_time, created_at)
					SELECT DISTINCT
						group_id,
						source_type,
						MAX(CONCAT(sent_date, ' ', sent_time)) as last_message_time,
						NOW() as created_at
					FROM `{$table}`
					WHERE group_id IS NOT NULL
					AND group_id != ''
					AND source_type IN ('group', 'room')
					GROUP BY group_id, source_type";

				$result = $this->wpdb->query( $sql );
				if ( false !== $result ) {
					$groups_inserted += $result;
				}
			}

			// 階段二：從訊息表提取成員關係.
			$members_inserted = 0;
			foreach ( $message_tables as $table ) {
				$sql = "INSERT IGNORE INTO `{$group_members_table}`
					(group_id, line_user_id, display_name, avatar_url, joined_at, created_at)
					SELECT DISTINCT
						m.group_id,
						m.line_user_id,
						u.display_name,
						u.avatar_url,
						MIN(CONCAT(m.sent_date, ' ', m.sent_time)) as joined_at,
						NOW() as created_at
					FROM `{$table}` m
					LEFT JOIN `{$users_table}` u ON m.line_user_id = u.line_user_id
					WHERE m.group_id IS NOT NULL
					AND m.group_id != ''
					AND m.line_user_id IS NOT NULL
					AND m.line_user_id != ''
					GROUP BY m.group_id, m.line_user_id";

				$result = $this->wpdb->query( $sql );
				if ( false !== $result ) {
					$members_inserted += $result;
				}
			}

			// 階段三：更新群組的成員數量.
			$update_sql = "UPDATE `{$groups_table}` g
				SET member_count = (
					SELECT COUNT(*)
					FROM `{$group_members_table}` gm
					WHERE gm.group_id = g.group_id
					AND gm.left_at IS NULL
				)";
			$this->wpdb->query( $update_sql );

			return array(
				'success'        => true,
				'groups_count'   => $groups_inserted,
				'members_count'  => $members_inserted,
				'error'          => null,
			);

		} catch ( \Exception $e ) {
			return array(
				'success'        => false,
				'groups_count'   => 0,
				'members_count'  => 0,
				'error'          => $e->getMessage(),
			);
		}
	}
}
