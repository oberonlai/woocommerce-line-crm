<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.8
 *
 * 真人客服轉接功能資料庫升級：
 * - 新增 otz_bot.handoff_message 欄位（真人客服轉接訊息）
 * - 移除 otz_bot.temperature 欄位（未使用）
 * - 移除 otz_bot.max_tokens 欄位（未使用）
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.1.8
 */
class Upgrade_1_1_8 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.8';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增 Bot handoff_message 欄位，移除未使用的 temperature 與 max_tokens 欄位';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_bot';

			// 檢查 Bot 資料表是否存在.
			if ( ! $this->table_exists( $table_name ) ) {
				Logger::error( "資料表 {$table_name} 不存在，跳過升級" );
				return false;
			}

			$success_count = 0;
			$skip_count    = 0;
			$fail_count    = 0;

			// 階段一：新增 handoff_message 欄位.
			if ( $this->column_exists( $table_name, 'handoff_message' ) ) {
				$this->log_message( 'handoff_message 欄位已存在，跳過新增' );
				++$skip_count;
			} else {
				$result = $this->add_column(
					$table_name,
					'handoff_message',
					"TEXT NULL COMMENT '真人客服轉接訊息'",
					'system_prompt'
				);

				if ( $result ) {
					++$success_count;
					$this->log_message( '成功新增 handoff_message 欄位' );
				} else {
					++$fail_count;
					Logger::error( '新增 handoff_message 欄位失敗' );
				}
			}

			// 階段二：移除 temperature 欄位.
			if ( ! $this->column_exists( $table_name, 'temperature' ) ) {
				$this->log_message( 'temperature 欄位不存在，跳過移除' );
				++$skip_count;
			} else {
				$result = $this->drop_column( $table_name, 'temperature' );

				if ( $result ) {
					++$success_count;
					$this->log_message( '成功移除 temperature 欄位' );
				} else {
					++$fail_count;
					Logger::error( '移除 temperature 欄位失敗' );
				}
			}

			// 階段三：移除 max_tokens 欄位.
			if ( ! $this->column_exists( $table_name, 'max_tokens' ) ) {
				$this->log_message( 'max_tokens 欄位不存在，跳過移除' );
				++$skip_count;
			} else {
				$result = $this->drop_column( $table_name, 'max_tokens' );

				if ( $result ) {
					++$success_count;
					$this->log_message( '成功移除 max_tokens 欄位' );
				} else {
					++$fail_count;
					Logger::error( '移除 max_tokens 欄位失敗' );
				}
			}

			$this->log_message(
				"升級到 1.1.8 完成 - 成功 {$success_count} 個操作，跳過 {$skip_count} 個，失敗 {$fail_count} 個"
			);

			// 只要沒有失敗的就算成功.
			return 0 === $fail_count;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.8 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Drop a column from a table.
	 *
	 * @param string $table_name  Table name.
	 * @param string $column_name Column name.
	 * @return bool True on success, false on failure.
	 */
	private function drop_column( string $table_name, string $column_name ): bool {
		$sql = "ALTER TABLE `{$table_name}` DROP COLUMN `{$column_name}`";

		$result = $this->wpdb->query( $sql );

		if ( false === $result ) {
			Logger::error( "移除 {$table_name}.{$column_name} 失敗: " . $this->wpdb->last_error );
			return false;
		}

		return true;
	}
}
