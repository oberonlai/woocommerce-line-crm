<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Util\Logger;

/**
 * Upgrade to version 1.1.6
 *
 * 新增 AI 機器人資料表與使用者 bot_status 欄位.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.1.6
 */
class Upgrade_1_1_6 extends AbstractUpgrade {

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number.
	 */
	public function get_version(): string {
		return '1.1.6';
	}

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string {
		return '新增 AI 機器人系統與訊息控制欄位';
	}

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool {
		try {
			$bot_table   = $this->wpdb->prefix . 'otz_bot';
			$users_table = $this->wpdb->prefix . 'otz_users';

			if ( ! $this->table_exists( $users_table ) ) {
				Logger::error( "Table does not exist: {$users_table}" );
				return false;
			}

			// ========== 階段一：新增 receive_message 欄位與索引 ==========

			// 新增 receive_message 欄位.
			if ( ! $this->column_exists( $users_table, 'receive_message' ) ) {
				$result = $this->add_column(
					$users_table,
					'receive_message',
					"ENUM('enabled','disabled') NOT NULL DEFAULT 'enabled' COMMENT '訊息接收狀態'",
					'line_user_id'
				);

				if ( ! $result ) {
					throw new \Exception( '新增 receive_message 欄位失敗' );
				}
			} else {
				$this->log_message( "欄位 receive_message 已存在於 {$users_table}" );
			}

			// 為 receive_message 欄位建立索引.
			if ( ! $this->index_exists( $users_table, 'idx_receive_message' ) ) {
				$result = $this->add_index(
					$users_table,
					'idx_receive_message',
					'receive_message'
				);

				if ( ! $result ) {
					throw new \Exception( '新增 idx_receive_message 索引失敗' );
				}
			} else {
				$this->log_message( "索引 idx_receive_message 已存在於 {$users_table}" );
			}

			// ========== 階段二：建立 otz_bot 資料表 ==========

			if ( ! $this->table_exists( $bot_table ) ) {
				$table_creator = new \OrderChatz\Database\TableCreator(
					$this->wpdb,
					$this->logger,
					$this->error_handler
				);

				$result = $table_creator->create_bot_table( $bot_table );

				if ( ! $result ) {
					throw new \Exception( "建立資料表 {$bot_table} 失敗" );
				}
			} else {
				$this->log_message( "資料表 {$bot_table} 已存在" );
			}

			// ========== 階段三：新增 bot_status 欄位與索引 ==========

			// 新增 bot_status 欄位.
			if ( ! $this->column_exists( $users_table, 'bot_status' ) ) {
				$result = $this->add_column(
					$users_table,
					'bot_status',
					"ENUM('enable','disable') NOT NULL DEFAULT 'disable' COMMENT 'AI 機器人狀態'",
					'receive_message'
				);

				if ( ! $result ) {
					throw new \Exception( '新增 bot_status 欄位失敗' );
				}
			} else {
				$this->log_message( "欄位 bot_status 已存在於 {$users_table}" );
			}

			// 為 bot_status 欄位建立索引.
			if ( ! $this->index_exists( $users_table, 'idx_bot_status' ) ) {
				$result = $this->add_index(
					$users_table,
					'idx_bot_status',
					'bot_status'
				);

				if ( ! $result ) {
					throw new \Exception( '新增 idx_bot_status 索引失敗' );
				}
			} else {
				$this->log_message( "索引 idx_bot_status 已存在於 {$users_table}" );
			}

			$this->log_message( '升級到 1.1.6 完成 - AI 機器人系統與訊息控制欄位已建立' );
			return true;

		} catch ( \Exception $e ) {
			Logger::error( '升級到 1.1.6 失敗: ' . $e->getMessage() );
			return false;
		}
	}
}
