<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.1
 *
 * 新增使用者標籤索引優化.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_1 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.1';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增使用者標籤索引優化';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_user_tags';

			// Check if table exists first.
			if ( ! $this->table_exists( $table_name ) ) {
				Logger::error( "Table does not exist for upgrade: {$table_name}" );
				return false;
			}

			// Add performance index (if not exists).
			return $this->add_index(
				$table_name,
				'idx_tag_created',
				'tag_name, created_at'
			);

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.1 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}