<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.2
 *
 * 新增訊息分表支援.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_2 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.2';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增訊息分表支援';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// Create current month's message table if not exists.
			$dynamic_manager = new DynamicTableManager( $this->wpdb, $this->logger );
			$result          = $dynamic_manager->ensure_current_month_table();

			if ( $result ) {
				$this->log_message( '成功初始化當月訊息分表' );
			} else {
				throw new \Exception( 'Failed to create current month message table' );
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.2 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}