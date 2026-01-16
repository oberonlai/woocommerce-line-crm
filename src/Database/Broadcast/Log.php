<?php

declare(strict_types=1);

namespace OrderChatz\Database\Broadcast;

use OrderChatz\Util\Logger;
use wpdb;

/**
 * Otz_broadcast_logs 資料表類別
 *
 * 處理 otz_broadcast_logs 資料表的 CRD 操作.
 * 管理推播活動的執行記錄.
 *
 * @package    OrderChatz
 * @subpackage Database\Broadcast
 * @since      1.1.3
 */
class Log {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Log 資料表名稱
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * 建構子
	 *
	 * @param wpdb $wpdb WordPress 資料庫物件.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'otz_broadcast_logs';
	}

	/**
	 * 建立執行記錄
	 *
	 * @param array $data Log 資料.
	 * @return int|false 成功時返回 Log ID，失敗時返回 false.
	 */
	public function create_log( array $data ): int|false {
		try {
			// 清理和驗證資料.
			$sanitized_data = $this->sanitize_log_data( $data );
			if ( false === $sanitized_data ) {
				return false;
			}

			// 準備格式陣列.
			$format = $this->get_data_format( $sanitized_data );

			$result = $this->wpdb->insert(
				$this->table_name,
				$sanitized_data,
				$format
			);

			if ( false === $result ) {
				Logger::error( 'Failed to create log: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return $this->wpdb->insert_id;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in create_log: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 根據 ID 取得單一記錄
	 *
	 * @param int $id Log ID.
	 * @return array|null Log 資料或找不到時返回 null.
	 */
	public function get_log( int $id ): ?array {
		try {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			);

			$result = $this->wpdb->get_row( $sql, ARRAY_A );

			if ( ! $result ) {
				return null;
			}

			// 解碼 JSON 欄位.
			return $this->decode_json_fields( $result );

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_log: ' . $e->getMessage(), array(), 'otz' );
			return null;
		}
	}

	/**
	 * 根據 Campaign ID 取得執行記錄
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $args 查詢參數（order_by, order, limit, offset）.
	 * @return array Log 陣列.
	 */
	public function get_logs_by_campaign( int $campaign_id, array $args = array() ): array {
		try {
			// 設定預設值.
			$defaults = array(
				'order_by' => 'executed_at',
				'order'    => 'DESC',
				'limit'    => 50,
				'offset'   => 0,
			);

			$args = wp_parse_args( $args, $defaults );

			// 驗證 order_by.
			$allowed_columns = array( 'id', 'executed_at', 'status', 'target_count' );
			if ( ! in_array( $args['order_by'], $allowed_columns, true ) ) {
				$args['order_by'] = 'executed_at';
			}

			// 驗證 order.
			$args['order'] = strtoupper( $args['order'] );
			if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ) {
				$args['order'] = 'DESC';
			}

			// 建立查詢.
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE campaign_id = %d
				ORDER BY {$args['order_by']} {$args['order']}
				LIMIT %d OFFSET %d",
				$campaign_id,
				$args['limit'],
				$args['offset']
			);

			$results = $this->wpdb->get_results( $sql, ARRAY_A );

			if ( ! $results ) {
				return array();
			}

			// 解碼每個記錄的 JSON 欄位.
			return array_map( array( $this, 'decode_json_fields' ), $results );

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_logs_by_campaign: ' . $e->getMessage(), array(), 'otz' );
			return array();
		}
	}

	/**
	 * 刪除執行記錄
	 *
	 * @param int $id Log ID.
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function delete_log( int $id ): bool {
		try {
			if ( ! $this->log_exists( $id ) ) {
				Logger::error( "Log ID {$id} does not exist", array(), 'broadcast' );
				return false;
			}

			$result = $this->wpdb->delete(
				$this->table_name,
				array( 'id' => $id ),
				array( '%d' )
			);

			if ( false === $result ) {
				Logger::error( 'Failed to delete log: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in delete_log: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 檢查記錄是否存在
	 *
	 * @param int $id Log ID.
	 * @return bool 存在時返回 true，否則返回 false.
	 */
	private function log_exists( int $id ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
			$id
		);

		return (int) $this->wpdb->get_var( $sql ) > 0;
	}

	/**
	 * 清理和驗證記錄資料
	 *
	 * @param array $data 原始 Log 資料.
	 * @return array|false 清理後的資料或驗證失敗時返回 false.
	 */
	private function sanitize_log_data( array $data ): array|false {
		$sanitized = array();

		// 必填欄位驗證.
		$required_fields = array(
			'campaign_id',
			'executed_at',
			'executed_by',
			'campaign_name_snapshot',
			'audience_type_snapshot',
			'message_snapshot',
			'target_count',
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				Logger::error( "Required field '{$field}' is missing", array(), 'otz' );
				return false;
			}
		}

		// campaign_id.
		$sanitized['campaign_id'] = (int) $data['campaign_id'];

		// executed_at.
		$sanitized['executed_at'] = sanitize_text_field( $data['executed_at'] );

		// executed_by.
		$sanitized['executed_by'] = (int) $data['executed_by'];

		// execution_type (ENUM 驗證).
		if ( isset( $data['execution_type'] ) ) {
			$allowed_execution_types = array( 'manual', 'scheduled' );
			if ( ! in_array( $data['execution_type'], $allowed_execution_types, true ) ) {
				Logger::error( "Invalid execution_type: {$data['execution_type']}", array(), 'otz' );
				return false;
			}
			$sanitized['execution_type'] = $data['execution_type'];
		}

		// campaign_name_snapshot.
		$sanitized['campaign_name_snapshot'] = sanitize_text_field( $data['campaign_name_snapshot'] );

		// audience_type_snapshot.
		$sanitized['audience_type_snapshot'] = sanitize_text_field( $data['audience_type_snapshot'] );

		// filter_snapshot (JSON).
		if ( isset( $data['filter_snapshot'] ) && ! empty( $data['filter_snapshot'] ) ) {
			if ( is_array( $data['filter_snapshot'] ) ) {
				$sanitized['filter_snapshot'] = wp_json_encode( $data['filter_snapshot'] );
			} elseif ( is_string( $data['filter_snapshot'] ) ) {
				$sanitized['filter_snapshot'] = $data['filter_snapshot'];
			}
		}

		// message_snapshot (JSON).
		if ( is_array( $data['message_snapshot'] ) ) {
			$sanitized['message_snapshot'] = wp_json_encode( $data['message_snapshot'] );
		} elseif ( is_string( $data['message_snapshot'] ) ) {
			$sanitized['message_snapshot'] = $data['message_snapshot'];
		}

		// target_count.
		$sanitized['target_count'] = (int) $data['target_count'];

		// success_count.
		if ( isset( $data['success_count'] ) ) {
			$sanitized['success_count'] = (int) $data['success_count'];
		}

		// failed_count.
		if ( isset( $data['failed_count'] ) ) {
			$sanitized['failed_count'] = (int) $data['failed_count'];
		}

		// status (ENUM 驗證).
		if ( isset( $data['status'] ) ) {
			$allowed_statuses = array( 'pending', 'success', 'partial', 'failed' );
			if ( ! in_array( $data['status'], $allowed_statuses, true ) ) {
				Logger::error( "Invalid status: {$data['status']}", array(), 'otz' );
				return false;
			}
			$sanitized['status'] = $data['status'];
		}

		// error_message.
		if ( isset( $data['error_message'] ) ) {
			$sanitized['error_message'] = sanitize_textarea_field( $data['error_message'] );
		}

		return $sanitized;
	}

	/**
	 * 取得 wpdb 操作的資料格式陣列
	 *
	 * @param array $data Log 資料.
	 * @return array 格式陣列.
	 */
	private function get_data_format( array $data ): array {
		$format = array();

		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'campaign_id':
				case 'executed_by':
				case 'target_count':
				case 'success_count':
				case 'failed_count':
					$format[] = '%d';
					break;
				default:
					$format[] = '%s';
					break;
			}
		}

		return $format;
	}

	/**
	 * 解碼記錄資料中的 JSON 欄位
	 *
	 * @param array $log Log 資料.
	 * @return array 解碼 JSON 欄位後的 Log 資料.
	 */
	private function decode_json_fields( array $log ): array {
		$json_fields = array( 'filter_snapshot', 'message_snapshot' );

		foreach ( $json_fields as $field ) {
			if ( isset( $log[ $field ] ) && ! empty( $log[ $field ] ) ) {
				$decoded = json_decode( $log[ $field ], true );
				if ( null !== $decoded ) {
					$log[ $field ] = $decoded;
				}
			}
		}

		return $log;
	}
}
