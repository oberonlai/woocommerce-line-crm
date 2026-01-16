<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Util\Logger;

/**
 * Database Version Manager Class
 *
 * Handles database schema versioning, upgrades, and migration history.
 * Implements Task 5.1: 資料庫版本管理與升級機制.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class VersionManager {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Security validator instance
	 *
	 * @var SecurityValidator|null
	 */
	private ?SecurityValidator $security_validator;

	/**
	 * Database version constants
	 */
	private const DB_VERSION                = '1.2.2';
	private const DB_VERSION_OPTION         = 'otz_db_version';
	private const DB_UPGRADE_HISTORY_OPTION = 'otz_db_upgrade_history';

	/**
	 * Version upgrade paths mapping
	 *
	 * @var array
	 */
	private const UPGRADE_PATHS = array(
		'1.0.0' => array(
			'target'      => '1.0.1',
			'class'       => 'Upgrade_1_0_1',
			'description' => '新增使用者標籤索引優化',
		),
		'1.0.1' => array(
			'target'      => '1.0.2',
			'class'       => 'Upgrade_1_0_2',
			'description' => '新增訊息分表支援',
		),
		'1.0.2' => array(
			'target'      => '1.0.3',
			'class'       => 'Upgrade_1_0_3',
			'description' => '新增查詢優化和安全性增強',
		),
		'1.0.3' => array(
			'target'      => '1.0.4',
			'class'       => 'Upgrade_1_0_4',
			'description' => '新增 LINE webhook 整合支援',
		),
		'1.0.4' => array(
			'target'      => '1.0.5',
			'class'       => 'Upgrade_1_0_5',
			'description' => '移除 otz_orders 資料表，改用 wp_user_id 查詢',
		),
		'1.0.5' => array(
			'target'      => '1.0.6',
			'class'       => 'Upgrade_1_0_6',
			'description' => '新增 LINE 訊息回覆功能支援欄位',
		),
		'1.0.6' => array(
			'target'      => '1.0.7',
			'class'       => 'Upgrade_1_0_7',
			'description' => '新增 LINE 訊息 ID 欄位以支援訊息關聯',
		),
		'1.0.7' => array(
			'target'      => '1.0.8',
			'class'       => 'Upgrade_1_0_8',
			'description' => '新增客服訊息範本資料表',
		),
		'1.0.8' => array(
			'target'      => '1.0.9',
			'class'       => 'Upgrade_1_0_9',
			'description' => '新增備註關聯訊息 JSON 欄位',
		),
		'1.0.9' => array(
			'target'      => '1.1.0',
			'class'       => 'Upgrade_1_1_0',
			'description' => '新增預約排程訊息資料表',
		),
		'1.1.0' => array(
			'target'      => '1.1.1',
			'class'       => 'Upgrade_1_1_1',
			'description' => '新增備註關聯產品 ID 欄位',
		),
		'1.1.1' => array(
			'target'      => '1.1.2',
			'class'       => 'Upgrade_1_1_2',
			'description' => '修正九月訊息錯誤存入十月分表的問題',
		),
		'1.1.2' => array(
			'target'      => '1.1.3',
			'class'       => 'Upgrade_1_1_3',
			'description' => '推播系統重構：新增 Campaign 管理表',
		),
		'1.1.3' => array(
			'target'      => '1.1.4',
			'class'       => 'Upgrade_1_1_4',
			'description' => '優化標籤系統以支援重複貼標記錄與歷史追蹤',
		),
		'1.1.4' => array(
			'target'      => '1.1.6',
			'class'       => 'Upgrade_1_1_6',
			'description' => '新增 AI 機器人系統與訊息控制欄位',
		),
		'1.1.6' => array(
			'target'      => '1.1.7',
			'class'       => 'Upgrade_1_1_7',
			'description' => '新增訊息分表 sender_type ENUM 支援 bot 類型',
		),
		'1.1.7' => array(
			'target'      => '1.1.8',
			'class'       => 'Upgrade_1_1_8',
			'description' => '新增 Bot handoff_message 欄位，移除未使用的 temperature 與 max_tokens 欄位',
		),
		'1.1.8' => array(
			'target'      => '1.1.9',
			'class'       => 'Upgrade_1_1_9',
			'description' => '建立多群組架構：新增 groups 和 group_members 表',
		),
		'1.1.9' => array(
			'target'      => '1.2.0',
			'class'       => 'Upgrade_1_2_0',
			'description' => '為群組新增已讀追蹤功能：在 groups 表新增 read_time 欄位',
		),
		'1.2.0' => array(
			'target'      => '1.2.1',
			'class'       => 'Upgrade_1_2_1',
			'description' => '為備註功能新增群組支援：在 user_notes 表新增 source_type 和 group_id 欄位',
		),
		'1.2.1' => array(
			'target'      => '1.2.2',
			'class'       => 'Upgrade_1_2_2',
			'description' => '修正 groups 資料表 collation 為 utf8mb4_unicode_ci',
		),
	);

	/**
	 * Constructor
	 *
	 * @param \wpdb                  $wpdb WordPress database object
	 * @param \WC_Logger|null        $logger Logger instance
	 * @param ErrorHandler|null      $error_handler Error handler instance
	 * @param SecurityValidator|null $security_validator Security validator instance
	 */
	public function __construct( \wpdb $wpdb, ?\WC_Logger $logger = null, ?ErrorHandler $error_handler = null, ?SecurityValidator $security_validator = null ) {
		$this->wpdb               = $wpdb;
		$this->logger             = $logger;
		$this->error_handler      = $error_handler;
		$this->security_validator = $security_validator;
	}

	/**
	 * Get current database version
	 *
	 * @return string Current database version
	 */
	public function get_current_version(): string {
		return get_option( self::DB_VERSION_OPTION, '0.0.0' );
	}

	/**
	 * Get target database version
	 *
	 * @return string Target database version
	 */
	public function get_target_version(): string {
		return self::DB_VERSION;
	}

	/**
	 * Check if database upgrade is needed
	 *
	 * @return bool True if upgrade is needed, false otherwise
	 */
	public function needs_upgrade(): bool {
		$current_version = $this->get_current_version();
		return version_compare( $current_version, self::DB_VERSION, '<' );
	}

	/**
	 * Get upgrade path from current version to target version
	 *
	 * @param string|null $from_version Starting version (default: current)
	 * @param string|null $to_version Target version (default: latest)
	 * @return array Array of upgrade steps
	 */
	public function get_upgrade_path( ?string $from_version = null, ?string $to_version = null ): array {
		if ( null === $from_version ) {
			$from_version = $this->get_current_version();
		}
		if ( null === $to_version ) {
			$to_version = self::DB_VERSION;
		}

		$path    = array();
		$current = $from_version;

		// Build incremental upgrade path
		while ( version_compare( $current, $to_version, '<' ) ) {
			if ( ! isset( self::UPGRADE_PATHS[ $current ] ) ) {
				Logger::error( "No upgrade path found from version: {$current}" );
				break;
			}

			$upgrade = self::UPGRADE_PATHS[ $current ];
			$path[]  = array(
				'from'        => $current,
				'to'          => $upgrade['target'],
				'class'       => $upgrade['class'],
				'description' => $upgrade['description'],
			);

			$current = $upgrade['target'];
		}

		return $path;
	}

	/**
	 * Execute database upgrade with incremental steps
	 *
	 * @return bool True on successful upgrade, false on failure
	 */
	public function execute_upgrade(): bool {
		$current_version = $this->get_current_version();
		$target_version  = self::DB_VERSION;

		// Get upgrade path
		$upgrade_path = $this->get_upgrade_path();

		if ( empty( $upgrade_path ) ) {
			return true;
		}

		// Create upgrade instances
		$upgrade_instances = array();
		foreach ( $upgrade_path as $step ) {
			$class_name = 'OrderChatz\\Database\\Upgrades\\' . $step['class'];

			if ( ! class_exists( $class_name ) ) {
				Logger::error( "Upgrade class does not exist: {$class_name}" );
				return false;
			}

			$upgrade_instances[] = new $class_name(
				$this->wpdb,
				$this->logger,
				$this->error_handler,
				$this->security_validator
			);
		}

		// Execute upgrades using UpgradeExecutor
		$executor = new UpgradeExecutor( $this->wpdb, $this->logger, $this->error_handler );
		return $executor->execute_upgrades( $upgrade_instances, $target_version, $current_version );
	}

	/**
	 * Get upgrade history
	 *
	 * @return array Array of upgrade history records
	 */
	public function get_upgrade_history(): array {
		return get_option( self::DB_UPGRADE_HISTORY_OPTION, array() );
	}
}
