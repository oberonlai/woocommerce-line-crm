<?php

declare(strict_types=1);

namespace OrderChatz\Database\Message;

use DateTime;
use Exception;

/**
 * Cron Message Table Manager Class
 *
 * 專門處理排程訊息資料表操作的類別，負責所有與 otz_cron_messages 相關的資料庫操作
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.1.0
 */
class TableMessageCron {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * 新增排程訊息記錄
	 *
	 * @param array $data 排程資料.
	 * @return int|false 新增的記錄 ID 或失敗時返回 false
	 */
	public function insert_cron_message( array $data ): bool|int {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		// 驗證必要欄位.
		$required_fields = array( 'line_user_id', 'source_type', 'message_content', 'schedule', 'created_by' );
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return false;
			}
		}

		// 準備插入資料.
		$insert_data = array(
			'action_id'       => absint( $data['action_id'] ),
			'line_user_id'    => sanitize_text_field( $data['line_user_id'] ),
			'source_type'     => sanitize_text_field( $data['source_type'] ),
			'group_id'        => isset( $data['group_id'] ) ? sanitize_text_field( $data['group_id'] ) : null,
			'message_type'    => isset( $data['message_type'] ) ? sanitize_text_field( $data['message_type'] ) : 'text',
			'message_content' => wp_kses_post( $data['message_content'] ),
			'schedule'        => wp_json_encode( $data['schedule'] ),
			'created_by'      => absint( $data['created_by'] ),
			'created_at'      => current_time( 'mysql' ),
			'status'          => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'pending',
		);

		$result = $this->wpdb->insert(
			$table_name,
			$insert_data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $result !== false ? $this->wpdb->insert_id : false;
	}

	/**
	 * 取得單一排程記錄
	 *
	 * @param int $id 記錄 ID.
	 * @return object|null 排程記錄或 null
	 */
	public function get_cron_message( int $id ): ?object {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$sql    = "SELECT * FROM {$table_name} WHERE id = %d";
		$result = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $id ) );

		return $result ?: null;
	}

	/**
	 * 更新排程記錄
	 *
	 * @param int   $id   記錄 ID.
	 * @param array $data 要更新的資料.
	 * @return bool 更新是否成功
	 */
	public function update_cron_message( int $id, array $data ): bool {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		// 準備可更新的欄位.
		$allowed_fields = array( 'action_id', 'message_content', 'schedule', 'message_type', 'status' );
		$update_data    = array();
		$format         = array();

		foreach ( $data as $field => $value ) {
			if ( in_array( $field, $allowed_fields, true ) ) {
				if ( $field === 'schedule' && is_array( $value ) ) {
					$update_data[ $field ] = wp_json_encode( $value );
				} else {
					$update_data[ $field ] = sanitize_text_field( $value );
				}
				$format[] = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * 刪除排程記錄
	 *
	 * @param int $id 記錄 ID.
	 * @return bool 刪除是否成功
	 */
	public function delete_cron_message( int $id ): bool {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$result = $this->wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		return $result !== false && $result > 0;
	}

	/**
	 * 依 action_id 查詢排程記錄
	 *
	 * @param int $action_id Action Scheduler ID.
	 * @return array 排程記錄陣列
	 */
	public function get_cron_messages_by_action_id( int $action_id ): array {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$sql = "SELECT * FROM {$table_name}
                WHERE action_id = %d
                ORDER BY created_at DESC";

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $action_id ) );

		return $results ?: array();
	}

	/**
	 * 依 line_user_id 查詢用戶排程
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param int    $limit        查詢數量限制.
	 * @param int    $offset       偏移量.
	 * @return array 排程記錄陣列
	 */
	public function get_cron_messages_by_user( string $line_user_id, int $limit = 20, int $offset = 0 ): array {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$sql = "SELECT * FROM {$table_name}
                WHERE line_user_id = %s
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d";

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $line_user_id, $limit, $offset ) );

		return $results ?: array();
	}

	/**
	 * 依日期範圍查詢排程記錄
	 *
	 * @param string $start_date 開始日期 (Y-m-d).
	 * @param string $end_date   結束日期 (Y-m-d).
	 * @param int    $limit      查詢數量限制.
	 * @return array 排程記錄陣列
	 */
	public function get_cron_messages_by_date_range( string $start_date, string $end_date, int $limit = 100 ): array {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$sql = "SELECT * FROM {$table_name}
                WHERE DATE(created_at) BETWEEN %s AND %s
                ORDER BY created_at DESC
                LIMIT %d";

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $start_date, $end_date, $limit ) );

		return $results ?: array();
	}

	/**
	 * 依本地 status 欄位查詢
	 *
	 * @param string      $status       狀態值.
	 * @param int         $limit        查詢數量限制.
	 * @param string|null $line_user_id LINE 使用者 ID（可選）.
	 * @return array 排程記錄陣列
	 */
	public function get_cron_messages_by_status( string $status, int $limit = 50, ?string $line_user_id = null ): array {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$where_conditions = array( 'status = %s' );
		$where_values     = array( $status );

		if ( ! empty( $line_user_id ) ) {
			$where_conditions[] = 'line_user_id = %s';
			$where_values[]     = $line_user_id;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

		$sql = "SELECT * FROM {$table_name}
                {$where_clause}
                ORDER BY created_at DESC
                LIMIT %d";

		$where_values[] = $limit;

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $where_values ) );

		return $results ?: array();
	}

	/**
	 * 更新本地狀態
	 *
	 * @param int    $id     記錄 ID.
	 * @param string $status 新狀態.
	 * @return bool 更新是否成功
	 */
	public function update_status( int $id, string $status ): bool {
		return $this->update_cron_message( $id, array( 'status' => $status ) );
	}

	/**
	 * 取得排程訊息歷史記錄（非 pending 狀態）
	 *
	 * @param int         $limit        查詢數量限制
	 * @param string|null $line_user_id LINE 使用者 ID（可選）.
	 * @return array 排程記錄陣列
	 */
	public function get_cron_message_history( int $limit = 50, ?string $line_user_id = null ): array {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';

		$where_conditions = array( "status != 'pending'" );
		$where_values     = array();

		if ( ! empty( $line_user_id ) ) {
			$where_conditions[] = 'line_user_id = %s';
			$where_values[]     = $line_user_id;
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

		$sql = "SELECT * FROM {$table_name}
                {$where_clause}
                ORDER BY created_at DESC
                LIMIT %d";

		$where_values[] = $limit;

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $where_values ) );

		return $results ?: array();
	}

	/**
	 * 根據 action_id 取得 Action Scheduler 狀態
	 *
	 * @param int $action_id Action Scheduler ID
	 * @return object|null Action 狀態資訊或 null
	 */
	public function get_action_status( int $action_id ): ?object {
		$scheduler_table = $this->wpdb->prefix . 'actionscheduler_actions';

		$sql = "SELECT
					action_id,
					status,
					scheduled_date_gmt,
					hook,
					priority,
					args
				FROM {$scheduler_table}
				WHERE action_id = %d";

		$result = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $action_id ) );

		return $result ?: null;
	}

	/**
	 * 解析排程 JSON 格式
	 *
	 * @param string $schedule_json JSON 字串.
	 * @return array|null 解析後的排程資料或 null
	 */
	public function parse_schedule_json( string $schedule_json ): ?array {
		try {
			$schedule = json_decode( $schedule_json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return null;
			}

			// 驗證必要欄位.
			if ( ! isset( $schedule['type'] ) ) {
				return null;
			}

			return $schedule;

		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * 驗證訊息資料格式
	 *
	 * @param array $data 要驗證的資料.
	 * @return bool 驗證是否通過
	 */
	public function validate_message_data( array $data ): bool {
		// 檢查必要欄位.
		$required_fields = array( 'action_id', 'line_user_id', 'source_type', 'message_content', 'schedule', 'created_by' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
				return false;
			}
		}

		// 檢查 source_type 值.
		$valid_source_types = array( 'user', 'group', 'room' );
		if ( ! in_array( $data['source_type'], $valid_source_types, true ) ) {
			return false;
		}

		// 檢查 action_id 是否為正整數.
		if ( ! is_numeric( $data['action_id'] ) || intval( $data['action_id'] ) <= 0 ) {
			return false;
		}

		// 檢查 schedule 是否為有效的 JSON 或陣列.
		if ( is_string( $data['schedule'] ) ) {
			$schedule = $this->parse_schedule_json( $data['schedule'] );
			if ( $schedule === null ) {
				return false;
			}
		} elseif ( ! is_array( $data['schedule'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 檢查資料表是否存在
	 *
	 * @return bool 表是否存在
	 */
	public function table_exists(): bool {
		$table_name = $this->wpdb->prefix . 'otz_cron_messages';
		return $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}
}
