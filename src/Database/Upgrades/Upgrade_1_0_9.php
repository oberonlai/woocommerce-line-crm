<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.9
 *
 * 新增備註關聯訊息 JSON 欄位.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_9 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.9';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增備註關聯訊息 JSON 欄位';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_user_notes';

			// Check if table exists first.
			if ( ! $this->table_exists( $table_name ) ) {
				Logger::error( "Table does not exist for upgrade: {$table_name}" );
				return false;
			}

			// Add related_message column.
			$result = $this->add_column(
				$table_name,
				'related_message',
				"JSON NULL COMMENT '關聯訊息資料JSON格式'",
				'category'
			);

			if ( ! $result ) {
				throw new \Exception( 'Failed to add related_message column' );
			}

			$this->log_message( '升級到 1.0.9 完成 - 新增備註關聯訊息 JSON 欄位' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.9 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}