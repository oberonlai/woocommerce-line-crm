<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.2.2
 *
 * 修正 groups 資料表的 collation 為 utf8mb4_unicode_ci：
 * - 將 wp_otz_groups 表的 collation 從 utf8mb4_unicode_520_ci 改為 utf8mb4_unicode_ci
 * - 確保與其他資料表（wp_otz_users、wp_otz_user_notes）使用一致的 collation
 * - 解決 JOIN 查詢時的 collation 衝突問題
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.2.2
 */
class Upgrade_1_2_2 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.2.2';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '修正 groups 資料表 collation 為 utf8mb4_unicode_ci';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$table_name = $this->wpdb->prefix . 'otz_groups';

			// 檢查資料表是否存在.
			if ( ! $this->table_exists( $table_name ) ) {
				$this->log_message( "資料表 {$table_name} 不存在，跳過 collation 修正" );
				return true;
			}

			// 檢查當前的 collation.
			$current_collation = $this->get_table_collation( $table_name );
			
			if ( 'utf8mb4_unicode_ci' === $current_collation ) {
				$this->log_message( "{$table_name} 已經使用 utf8mb4_unicode_ci collation，跳過轉換" );
				return true;
			}

			$this->log_message( "{$table_name} 當前 collation: {$current_collation}，準備轉換為 utf8mb4_unicode_ci" );

			// 執行 collation 轉換.
			$sql = "ALTER TABLE `{$table_name}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
			
			$result = $this->wpdb->query( $sql );

			if ( false === $result ) {
				Logger::error(
					"轉換 {$table_name} collation 失敗",
					array(
						'error' => $this->wpdb->last_error,
					)
				);
				return false;
			}

			// 驗證轉換成功.
			$new_collation = $this->get_table_collation( $table_name );
			
			if ( 'utf8mb4_unicode_ci' !== $new_collation ) {
				Logger::error(
					"Collation 轉換驗證失敗",
					array(
						'expected' => 'utf8mb4_unicode_ci',
						'actual'   => $new_collation,
					)
				);
				return false;
			}

			$this->log_message( "成功將 {$table_name} 轉換為 utf8mb4_unicode_ci collation" );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.2.2 失敗: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get the collation of a table.
	 *
	 * @param string $table_name Table name.
	 * @return string Table collation.
	 */
	private function get_table_collation( string $table_name ): string {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SHOW TABLE STATUS WHERE Name = %s',
				$table_name
			)
		);

		return $result->Collation ?? '';
	}
}
