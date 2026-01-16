<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.0.6
 *
 * 新增 LINE 訊息回覆功能支援欄位.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
class Upgrade_1_0_6 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.0.6';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增 LINE 訊息回覆功能支援欄位';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// 欄位定義.
			$columns_to_add = array(
				array(
					'name'       => 'quote_token',
					'definition' => "VARCHAR(255) NULL COMMENT 'LINE quote token for replying to this message'",
				),
				array(
					'name'       => 'quoted_message_id',
					'definition' => "VARCHAR(255) NULL COMMENT 'ID of the message being replied to'",
				),
			);

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

				$added_columns = 0;

				foreach ( $columns_to_add as $column ) {
					$result = $this->add_column(
						$table_name,
						$column['name'],
						$column['definition']
					);

					if ( $result ) {
						$added_columns++;
					}
				}

				if ( $added_columns > 0 ) {
					$updated_tables_count++;
				}
			}

			$this->log_message( "升級到 1.0.6 完成 - 共更新了 {$updated_tables_count} 個月份訊息表，新增 LINE 回覆功能支援欄位" );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.0.6 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}