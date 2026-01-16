<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\TableCreator;
use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.3
 *
 * 推播系統重構：新增 Campaign 管理表，移除舊的 broadcast_history 表.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.1.3
 */
class Upgrade_1_1_3 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.3';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '推播系統重構：新增 Campaign 管理表，移除舊的 broadcast_history 表';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			// ========== 階段一：處理 otz_broadcast 表 ==========

			$broadcast_table      = $this->wpdb->prefix . 'otz_broadcast';
			$old_history_table    = $this->wpdb->prefix . 'otz_broadcast_history';

			// 1. 建立新的 otz_broadcast 表.
			if ( ! $this->table_exists( $broadcast_table ) ) {
				$table_creator = new TableCreator( $this->wpdb, $this->logger, $this->error_handler );
				if ( ! $table_creator->create_broadcast_table( $broadcast_table ) ) {
					throw new \Exception( "無法建立 {$broadcast_table} 表" );
				}
				$this->log_message( "成功建立 {$broadcast_table} 表" );
			} else {
				$this->log_message( "{$broadcast_table} 表已存在，跳過建立" );
			}

			// 2. 刪除舊的 otz_broadcast_history 表.
			if ( $this->table_exists( $old_history_table ) ) {
				// 檢查是否有資料（可選：是否需要備份）.
				$count = $this->wpdb->get_var( "SELECT COUNT(*) FROM `{$old_history_table}`" );
				$this->log_message( "舊的 {$old_history_table} 表共有 {$count} 筆記錄，準備刪除" );

				$result = $this->wpdb->query( "DROP TABLE `{$old_history_table}`" );
				if ( false === $result ) {
					Logger::error( "刪除 {$old_history_table} 表失敗: " . $this->wpdb->last_error );
				} else {
					$this->log_message( "成功刪除舊的 {$old_history_table} 表" );
				}
			} else {
				$this->log_message( "{$old_history_table} 表不存在，跳過刪除" );
			}

			// ========== 階段二：處理 otz_broadcast_logs 表 ==========

			$broadcast_logs_table = $this->wpdb->prefix . 'otz_broadcast_logs';

			// 建立 otz_broadcast_logs 表.
			if ( ! $this->table_exists( $broadcast_logs_table ) ) {
				$table_creator = new TableCreator( $this->wpdb, $this->logger, $this->error_handler );
				if ( ! $table_creator->create_broadcast_logs_table( $broadcast_logs_table ) ) {
					throw new \Exception( "無法建立 {$broadcast_logs_table} 表" );
				}
				$this->log_message( "成功建立 {$broadcast_logs_table} 表" );
			} else {
				$this->log_message( "{$broadcast_logs_table} 表已存在，跳過建立" );
			}

			$this->log_message( '升級到 1.1.3 完成 - 推播系統已重構為 Campaign 管理模式' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.3 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}
