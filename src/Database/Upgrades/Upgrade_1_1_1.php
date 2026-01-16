<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.1
 *
 * 新增備註關聯產品 ID 欄位.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_1_1 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.1';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增備註關聯產品 ID 欄位';
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

			// Add related_product_id column.
			$result = $this->add_column(
				$table_name,
				'related_product_id',
				"BIGINT UNSIGNED NULL COMMENT 'WooCommerce 產品 ID'",
				'category'
			);

			if ( ! $result ) {
				throw new \Exception( 'Failed to add related_product_id column' );
			}

			// Add index for related_product_id.
			$this->add_index( $table_name, 'idx_related_product_id', 'related_product_id' );

			// Add composite index for product and user queries.
			$this->add_index( $table_name, 'idx_product_user', 'related_product_id, line_user_id' );

			$this->log_message( '升級到 1.1.1 完成 - 新增備註關聯產品 ID 欄位' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.1 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}