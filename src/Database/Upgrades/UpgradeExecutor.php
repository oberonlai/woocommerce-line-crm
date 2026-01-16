<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

use OrderChatz\Database\ErrorHandler;
use OrderChatz\Util\Logger;

/**
 * Upgrade Executor Class
 *
 * Handles the execution of database upgrades with transaction management,
 * error handling, and upgrade history tracking.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class UpgradeExecutor {

	/**
	 * WordPress database abstraction layer.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger;

	/**
	 * Error handler instance.
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Database version option name.
	 */
	private const DB_VERSION_OPTION = 'otz_db_version';

	/**
	 * Database upgrade history option name.
	 */
	private const DB_UPGRADE_HISTORY_OPTION = 'otz_db_upgrade_history';

	/**
	 * Constructor.
	 *
	 * @param \wpdb             $wpdb WordPress database object.
	 * @param \WC_Logger|null   $logger Logger instance.
	 * @param ErrorHandler|null $error_handler Error handler instance.
	 */
	public function __construct( \wpdb $wpdb, ?\WC_Logger $logger = null, ?ErrorHandler $error_handler = null ) {
		$this->wpdb          = $wpdb;
		$this->logger        = $logger;
		$this->error_handler = $error_handler;
	}

	/**
	 * Execute database upgrade with upgrade instances.
	 *
	 * @param array  $upgrade_instances Array of UpgradeInterface instances to execute.
	 * @param string $target_version Target version to upgrade to.
	 * @param string $current_version Current version before upgrade.
	 * @return bool True on successful upgrade, false on failure.
	 */
	public function execute_upgrades(
		array $upgrade_instances,
		string $target_version,
		string $current_version
	): bool {
		if ( empty( $upgrade_instances ) ) {
			return true;
		}

		// Start database transaction for atomic upgrade.
		$this->wpdb->query( 'START TRANSACTION' );

		try {
			// Execute each upgrade step.
			foreach ( $upgrade_instances as $upgrade ) {
				if ( ! $upgrade instanceof UpgradeInterface ) {
					throw new \Exception( '無效的升級實例' );
				}

				$result = $upgrade->execute();
				if ( ! $result ) {
					throw new \Exception( "升級步驟失敗: {$upgrade->get_version()}" );
				}

				// Record successful step.
				$this->record_upgrade_step(
					$current_version,
					$upgrade->get_version(),
					$upgrade->get_description()
				);

				// Update current version for next iteration.
				$current_version = $upgrade->get_version();
			}

			// Update database version.
			update_option( self::DB_VERSION_OPTION, $target_version );

			// Commit transaction.
			$this->wpdb->query( 'COMMIT' );

			return true;

		} catch ( \Exception $e ) {
			// Rollback transaction on failure.
			$this->wpdb->query( 'ROLLBACK' );

			Logger::error(
				'資料庫升級失敗: ' . $e->getMessage(),
				array(
					'current_version' => $current_version,
					'target_version'  => $target_version,
					'trace'           => $e->getTraceAsString(),
				)
			);

			return false;
		}
	}

	/**
	 * Record upgrade step in history.
	 *
	 * @param string $from_version Starting version.
	 * @param string $to_version Target version.
	 * @param string $description Upgrade description.
	 * @return void
	 */
	private function record_upgrade_step(
		string $from_version,
		string $to_version,
		string $description
	): void {
		$history = get_option( self::DB_UPGRADE_HISTORY_OPTION, array() );

		$history[] = array(
			'from_version' => $from_version,
			'to_version'   => $to_version,
			'description'  => $description,
			'executed_at'  => wp_date( 'Y-m-d H:i:s' ),
			'wp_user'      => get_current_user_id() ? get_current_user_id() : 0,
		);

		update_option( self::DB_UPGRADE_HISTORY_OPTION, $history );
	}

	/**
	 * Get upgrade history.
	 *
	 * @return array Array of upgrade history records.
	 */
	public function get_upgrade_history(): array {
		return get_option( self::DB_UPGRADE_HISTORY_OPTION, array() );
	}
}
