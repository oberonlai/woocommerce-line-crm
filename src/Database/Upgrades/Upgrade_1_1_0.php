<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\TableCreator;
use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.0
 *
 * 新增預約排程訊息資料表.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_1_0 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.0';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增預約排程訊息資料表';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_cron_messages';

			if ( $this->table_exists( $table_name ) ) {
				$this->log_message( "資料表 {$table_name} 已存在，跳過建立" );
				return true;
			}

			$table_creator = new TableCreator( $this->wpdb, $this->logger, $this->error_handler );
			$result        = $table_creator->create_cron_messages_table( $table_name );

			if ( $result ) {
				return true;
			} else {
				throw new \Exception( "Failed to create cron messages table: {$table_name}" );
			}
		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.0 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}