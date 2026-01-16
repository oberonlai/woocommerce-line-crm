<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.4
 *
 * 新增 LINE webhook 整合支援.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_4 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.4';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增 LINE webhook 整合支援';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_users';

			// Check if table exists first.
			if ( ! $this->table_exists( $table_name ) ) {
				Logger::error( "Table does not exist for upgrade: {$table_name}" );
				return false;
			}

			// Add new columns for LINE webhook integration.
			$columns_to_add = array(
				array(
					'name'       => 'source_type',
					'definition' => "ENUM('user','group','room') NOT NULL DEFAULT 'user' COMMENT 'Source type: user, group, or room'",
					'after'      => 'avatar_url',
				),
				array(
					'name'       => 'group_id',
					'definition' => "VARCHAR(64) NULL COMMENT 'Group/Room ID if applicable'",
					'after'      => 'source_type',
				),
				array(
					'name'       => 'followed_at',
					'definition' => "DATETIME NULL COMMENT 'Timestamp when user followed the bot'",
					'after'      => 'group_id',
				),
				array(
					'name'       => 'unfollowed_at',
					'definition' => "DATETIME NULL COMMENT 'Timestamp when user unfollowed the bot'",
					'after'      => 'followed_at',
				),
				array(
					'name'       => 'status',
					'definition' => "ENUM('active','blocked','unfollowed') NOT NULL DEFAULT 'active' COMMENT 'User status'",
					'after'      => 'unfollowed_at',
				),
			);

			$added_columns = 0;
			foreach ( $columns_to_add as $column ) {
				$result = $this->add_column(
					$table_name,
					$column['name'],
					$column['definition'],
					$column['after']
				);

				if ( $result && ! $this->column_exists( $table_name, $column['name'] ) ) {
					$added_columns++;
				}
			}

			// Add index for group_id if columns were added.
			if ( $added_columns > 0 ) {
				$this->add_index( $table_name, 'idx_group_id', 'group_id' );
				$this->add_index( $table_name, 'idx_status_type', 'status, source_type' );
			}

			$this->log_message( "升級到 1.0.4 完成 - 新增了 {$added_columns} 個欄位以支援 LINE webhook 整合" );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.4 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}