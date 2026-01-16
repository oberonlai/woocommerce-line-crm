<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.7
 *
 * 新增 LINE 訊息 ID 欄位以支援訊息關聯.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_7 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.7';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增 LINE 訊息 ID 欄位以支援訊息關聯';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// 欄位定義.
			$column_definition = "VARCHAR(255) NULL COMMENT 'LINE message ID for correlation with replies'";

			// 取得現有的月份表.
			$dynamic_manager = new DynamicTableManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator );
			$monthly_tables  = $dynamic_manager->get_existing_monthly_tables();

			$updated_tables_count = 0;

			// 更新每個現有的月份表.
			foreach ( $monthly_tables as $year_month ) {
				$table_name = $dynamic_manager->get_monthly_message_table_name( $year_month );

				if ( ! $table_name || ! $this->table_exists( $table_name ) ) {
					$this->log_message( "跳過不存在的表格: {$year_month}" );
					continue;
				}

				// 添加 line_message_id 欄位.
				$result = $this->add_column(
					$table_name,
					'line_message_id',
					$column_definition
				);

				if ( $result ) {
					$updated_tables_count++;
				}
			}

			$this->log_message( "升級到 1.0.7 完成 - 共更新了 {$updated_tables_count} 個月份訊息表，新增 LINE 訊息 ID 欄位" );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.7 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}