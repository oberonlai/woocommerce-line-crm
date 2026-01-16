<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.4
 *
 * 重構標籤系統：標籤表改為每個標籤一筆記錄，用 JSON 儲存使用者 ID 列表.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.1.4
 */
class Upgrade_1_1_4 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.4';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '重構標籤系統：標籤表改為每個標籤一筆記錄';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$user_tags_table = $this->wpdb->prefix . 'otz_user_tags';
			$users_table     = $this->wpdb->prefix . 'otz_users';

			// ========== 階段一：備份現有資料 ==========

			if ( ! $this->table_exists( $user_tags_table ) ) {
				Logger::error( "Table does not exist: {$user_tags_table}" );
				return false;
			}

			// 備份現有標籤資料.
			$backup_data = $this->wpdb->get_results(
				"SELECT line_user_id, tag_name, created_at FROM `{$user_tags_table}` ORDER BY created_at ASC"
			);

			if ( empty( $backup_data ) ) {
				$this->log_message( '沒有需要遷移的標籤資料' );
			} else {
				$this->log_message( '備份了 ' . count( $backup_data ) . ' 筆標籤記錄' );
			}

			// ========== 階段二：調整 otz_user_tags 表結構 ==========

			// 1. 移除 UNIQUE KEY (如果存在).
			if ( $this->index_exists( $user_tags_table, 'uk_user_tag' ) ) {
				$result = $this->wpdb->query( "ALTER TABLE `{$user_tags_table}` DROP INDEX `uk_user_tag`" );
				if ( false === $result ) {
					throw new \Exception( '移除 uk_user_tag 索引失敗: ' . $this->wpdb->last_error );
				}
			}

			// 2. 移除舊的複合索引 (如果存在).
			if ( $this->index_exists( $user_tags_table, 'idx_tag_name' ) ) {
				$result = $this->wpdb->query( "ALTER TABLE `{$user_tags_table}` DROP INDEX `idx_tag_name`" );
				if ( false === $result ) {
					throw new \Exception( '移除 idx_tag_name 索引失敗: ' . $this->wpdb->last_error );
				}
			}

			// 3. 清空表（準備重建）.
			$this->wpdb->query( "TRUNCATE TABLE `{$user_tags_table}`" );

			// 4. 移除 line_user_id 欄位.
			if ( $this->column_exists( $user_tags_table, 'line_user_id' ) ) {
				$result = $this->wpdb->query( "ALTER TABLE `{$user_tags_table}` DROP COLUMN `line_user_id`" );
				if ( false === $result ) {
					throw new \Exception( '移除 line_user_id 欄位失敗: ' . $this->wpdb->last_error );
				}
			}

			// 5. 修改 tag_name 為 UNIQUE.
			$result = $this->wpdb->query( "ALTER TABLE `{$user_tags_table}` MODIFY `tag_name` VARCHAR(50) NOT NULL UNIQUE COMMENT '標籤名稱（唯一）'" );
			if ( false === $result ) {
				throw new \Exception( '修改 tag_name 為 UNIQUE 失敗: ' . $this->wpdb->last_error );
			}

			// 6. 新增 line_user_ids JSON 欄位.
			if ( ! $this->column_exists( $user_tags_table, 'line_user_ids' ) ) {
				$result = $this->wpdb->query( "ALTER TABLE `{$user_tags_table}` ADD COLUMN `line_user_ids` JSON NULL COMMENT '使用此標籤的 LINE User ID 陣列' AFTER `tag_name`" );
				if ( false === $result ) {
					throw new \Exception( '新增 line_user_ids 欄位失敗: ' . $this->wpdb->last_error );
				}
			}

			// 7. 重建單一索引.
			if ( ! $this->index_exists( $user_tags_table, 'idx_tag_name' ) ) {
				$result = $this->wpdb->query( "ALTER TABLE `{$user_tags_table}` ADD INDEX `idx_tag_name` (`tag_name`)" );
				if ( false === $result ) {
					throw new \Exception( '新增 idx_tag_name 索引失敗: ' . $this->wpdb->last_error );
				}
			}

			$this->log_message( "成功調整 {$user_tags_table} 表結構" );

			// ========== 階段三：修改 otz_users 表結構 ==========

			if ( ! $this->table_exists( $users_table ) ) {
				Logger::error( "Table does not exist: {$users_table}" );
				return false;
			}

			if ( ! $this->column_exists( $users_table, 'tags' ) ) {
				$result = $this->wpdb->query( "ALTER TABLE `{$users_table}` ADD COLUMN `tags` JSON NULL COMMENT '標籤歷史記錄'" );
				if ( false === $result ) {
					throw new \Exception( '新增 tags 欄位失敗: ' . $this->wpdb->last_error );
				}
				$this->log_message( "成功在 {$users_table} 表新增 tags JSON 欄位" );
			}

			// ========== 階段四：資料遷移 ==========

			if ( ! empty( $backup_data ) ) {
				$this->migrate_to_new_structure( $backup_data, $user_tags_table, $users_table );
			}

			$this->log_message( '升級到 1.1.4 完成 - 標籤系統已重構為新架構' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.4 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * 遷移資料到新結構
	 *
	 * @param array  $backup_data      備份的舊資料.
	 * @param string $user_tags_table User tags table name.
	 * @param string $users_table     Users table name.
	 * @return void
	 */
	private function migrate_to_new_structure( array $backup_data, string $user_tags_table, string $users_table ): void {
		// ========== 步驟一：建立 wp_otz_user_tags 新資料 ==========
		// 格式：tag_name => [line_user_id1, line_user_id2, ...]

		$tags_data = array(); // tag_name => ['user_ids' => [], 'created_at' => '...']

		foreach ( $backup_data as $row ) {
			$tag_name     = $row->tag_name;
			$line_user_id = $row->line_user_id;
			$created_at   = $row->created_at;

			if ( ! isset( $tags_data[ $tag_name ] ) ) {
				$tags_data[ $tag_name ] = array(
					'user_ids'   => array(),
					'created_at' => $created_at, // 使用第一次出現的時間.
				);
			}

			// 收集所有使用者 ID（可重複）.
			$tags_data[ $tag_name ]['user_ids'][] = $line_user_id;
		}

		// 插入到 wp_otz_user_tags.
		$tag_insert_count = 0;
		foreach ( $tags_data as $tag_name => $data ) {
			$user_ids_json = wp_json_encode( $data['user_ids'], JSON_UNESCAPED_UNICODE );

			$result = $this->wpdb->insert(
				$user_tags_table,
				array(
					'tag_name'      => $tag_name,
					'line_user_ids' => $user_ids_json,
					'created_at'    => $data['created_at'],
				),
				array( '%s', '%s', '%s' )
			);

			if ( $result ) {
				$tag_insert_count++;
			} else {
				Logger::error( "插入標籤 {$tag_name} 失敗: " . $this->wpdb->last_error );
			}
		}

		$this->log_message( "成功遷移 {$tag_insert_count} 個標籤到新結構" );

		// ========== 步驟二：建立 wp_otz_users.tags JSON 資料 ==========
		// 格式：line_user_id => [{"tag_name": "VIP", "tagged_at": "..."}]

		$users_tags_data = array(); // line_user_id => [tag_records]

		foreach ( $backup_data as $row ) {
			$line_user_id = $row->line_user_id;
			$tag_name     = $row->tag_name;
			$created_at   = $row->created_at;

			if ( ! isset( $users_tags_data[ $line_user_id ] ) ) {
				$users_tags_data[ $line_user_id ] = array();
			}

			$users_tags_data[ $line_user_id ][] = array(
				'tag_name'  => $tag_name,
				'tagged_at' => $created_at,
			);
		}

		// 更新每個使用者的 tags JSON 欄位.
		$user_update_count = 0;
		$user_failed_count = 0;

		foreach ( $users_tags_data as $line_user_id => $tags_array ) {
			$tags_json = wp_json_encode( $tags_array, JSON_UNESCAPED_UNICODE );

			$result = $this->wpdb->update(
				$users_table,
				array( 'tags' => $tags_json ),
				array( 'line_user_id' => $line_user_id ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				$user_failed_count++;
				Logger::error( "更新使用者 {$line_user_id} 的 tags 欄位失敗: " . $this->wpdb->last_error );
			} else {
				$user_update_count++;
			}
		}

		$this->log_message( "使用者標籤遷移完成：成功 {$user_update_count} 位，失敗 {$user_failed_count} 位" );
	}
}
