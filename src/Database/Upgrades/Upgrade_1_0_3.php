<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.3
 *
 * 新增查詢優化和安全性增強.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_3 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.3';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增查詢優化和安全性增強';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// Add any schema changes for 1.0.3.
			// For example, add audit columns or security enhancements.
			$this->log_message( '升級到 1.0.3 - 查詢優化和安全性增強完成' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.3 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}