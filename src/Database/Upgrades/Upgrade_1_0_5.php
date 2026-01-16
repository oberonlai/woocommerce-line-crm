<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.5
 *
 * 移除 otz_orders 資料表，改用 wp_user_id 查詢.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_5 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.5';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '移除 otz_orders 資料表,改用 wp_user_id 查詢';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$orders_table = $this->wpdb->prefix . 'otz_orders';

			// 檢查 otz_orders 資料表是否存在.
			if ( $this->table_exists( $orders_table ) ) {
				// 刪除 otz_orders 資料表.
				$sql    = "DROP TABLE IF EXISTS {$orders_table}";
				$result = $this->wpdb->query( $sql );

				if ( false === $result ) {
					throw new \Exception( "Failed to drop table {$orders_table}: " . $this->wpdb->last_error );
				}
			}

			$this->log_message( '升級到 1.0.5 完成 - 已移除 otz_orders 資料表，現在透過 wp_user_id 直接查詢訂單' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.5 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}