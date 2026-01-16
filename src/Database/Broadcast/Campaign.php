<?php

declare(strict_types=1);

namespace OrderChatz\Database\Broadcast;

use OrderChatz\Util\Logger;
use wpdb;

/**
 * Otz_broadcast 資料表類別
 *
 * 處理 otz_broadcast 資料表的 CRUD 操作.
 * 管理推播活動的設定與配置.
 *
 * @package    OrderChatz
 * @subpackage Database\Broadcast
 * @since      1.1.3
 */
class Campaign {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Campaign 資料表名稱
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
		$this->table_name = $wpdb->prefix . 'otz_broadcast';
	}

	/**
	 * 儲存活動（新增或更新）
	 *
	 * @param array $data Campaign 資料.
	 * @return int|false 成功時返回 Campaign ID，失敗時返回 false.
	 */
	public function save_campaign( array $data ): int|false {
		try {
			// 清理和驗證資料.
			$sanitized_data = $this->sanitize_campaign_data( $data );
			if ( false === $sanitized_data ) {
				return false;
			}

			// 判斷是更新還是新增.
			$is_update = ! empty( $data['id'] );

			if ( $is_update ) {
				// 更新模式.
				$campaign_id = (int) $data['id'];

				if ( ! $this->campaign_exists( $campaign_id ) ) {
					Logger::error( "Campaign ID {$campaign_id} does not exist", array(), 'otz' );
					return false;
				}

				// 新增 updated_by 和 updated_at.
				$sanitized_data['updated_by'] = get_current_user_id();
				$sanitized_data['updated_at'] = current_time( 'mysql' );

				// 從資料中移除 id 以進行更新.
				unset( $sanitized_data['id'] );

				// 準備格式陣列.
				$format = $this->get_data_format( $sanitized_data );

				$result = $this->wpdb->update(
					$this->table_name,
					$sanitized_data,
					array( 'id' => $campaign_id ),
					$format,
					array( '%d' )
				);

				if ( false === $result ) {
					Logger::error( 'Failed to update campaign: ' . $this->wpdb->last_error, array(), 'otz' );
					return false;
				}

				return $campaign_id;

			} else {
				// 新增模式.
				$sanitized_data['created_by'] = get_current_user_id();
				$sanitized_data['created_at'] = current_time( 'mysql' );

				// 準備格式陣列.
				$format = $this->get_data_format( $sanitized_data );

				$result = $this->wpdb->insert(
					$this->table_name,
					$sanitized_data,
					$format
				);

				if ( false === $result ) {
					Logger::error( 'Failed to create campaign: ' . $this->wpdb->last_error, array(), 'otz' );
					return false;
				}

				return $this->wpdb->insert_id;
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Exception in save_campaign: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 根據 ID 取得單一活動
	 *
	 * @param int $id Campaign ID.
	 * @return array|null Campaign 資料或找不到時返回 null.
	 */
	public function get_campaign( int $id ): ?array {
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
			Logger::error( 'Exception in get_campaign: ' . $e->getMessage(), array(), 'otz' );
			return null;
		}
	}

	/**
	 * 取得所有活動
	 *
	 * @param array $args 查詢參數（order_by, order, limit, offset）.
	 * @return array Campaign 陣列.
	 */
	public function get_all_campaigns( array $args = array() ): array {
		try {
			// 設定預設值.
			$defaults = array(
				'order_by' => 'created_at',
				'order'    => 'DESC',
				'limit'    => 100,
				'offset'   => 0,
			);

			$args = wp_parse_args( $args, $defaults );

			// 驗證 order_by.
			$allowed_columns = array( 'id', 'campaign_name', 'created_at', 'updated_at', 'sort_order', 'status' );
			if ( ! in_array( $args['order_by'], $allowed_columns, true ) ) {
				$args['order_by'] = 'created_at';
			}

			// 驗證 order.
			$args['order'] = strtoupper( $args['order'] );
			if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ) {
				$args['order'] = 'DESC';
			}

			// 建立查詢.
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				ORDER BY {$args['order_by']} {$args['order']}
				LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			);

			$results = $this->wpdb->get_results( $sql, ARRAY_A );

			if ( ! $results ) {
				return array();
			}

			// 解碼每個活動的 JSON 欄位.
			return array_map( array( $this, 'decode_json_fields' ), $results );

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_all_campaigns: ' . $e->getMessage(), array(), 'otz' );
			return array();
		}
	}

	/**
	 * 刪除活動
	 *
	 * @param int $id Campaign ID.
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function delete_campaign( int $id ): bool {
		try {
			if ( ! $this->campaign_exists( $id ) ) {
				Logger::error( "Campaign ID {$id} does not exist", array(), 'otz' );
				return false;
			}

			$result = $this->wpdb->delete(
				$this->table_name,
				array( 'id' => $id ),
				array( '%d' )
			);

			if ( false === $result ) {
				Logger::error( 'Failed to delete campaign: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in delete_campaign: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 檢查活動是否存在
	 *
	 * @param int $id Campaign ID.
	 * @return bool 存在時返回 true，否則返回 false.
	 */
	private function campaign_exists( int $id ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
			$id
		);

		return (int) $this->wpdb->get_var( $sql ) > 0;
	}

	/**
	 * 清理和驗證活動資料
	 *
	 * @param array $data 原始 Campaign 資料.
	 * @return array|false 清理後的資料或驗證失敗時返回 false.
	 */
	private function sanitize_campaign_data( array $data ): array|false {
		$sanitized = array();

		// 判斷是否為更新模式.
		$is_update = ! empty( $data['id'] );

		// 必填欄位驗證.
		$required_fields = array( 'campaign_name', 'audience_type', 'message_type', 'message_content' );

		if ( ! $is_update ) {
			// 新增模式：必須提供所有必填欄位.
			foreach ( $required_fields as $field ) {
				if ( empty( $data[ $field ] ) ) {
					Logger::error( "Required field '{$field}' is missing", array(), 'otz' );
					return false;
				}
			}
		} else {
			// 更新模式：如果提供了必填欄位，則不可為空.
			foreach ( $required_fields as $field ) {
				if ( isset( $data[ $field ] ) && empty( $data[ $field ] ) ) {
					Logger::error( "Required field '{$field}' cannot be empty when provided", array(), 'otz' );
					return false;
				}
			}
		}

		// campaign_name.
		if ( isset( $data['campaign_name'] ) ) {
			$sanitized['campaign_name'] = sanitize_text_field( $data['campaign_name'] );
		}

		// description.
		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] );
		}

		// audience_type (ENUM 驗證).
		if ( isset( $data['audience_type'] ) ) {
			$allowed_audience_types = array( 'all_followers', 'imported_users', 'filtered' );
			if ( ! in_array( $data['audience_type'], $allowed_audience_types, true ) ) {
				Logger::error( "Invalid audience_type: {$data['audience_type']}", array(), 'otz' );
				return false;
			}
			$sanitized['audience_type'] = $data['audience_type'];
		}

		// filter_conditions (JSON).
		if ( isset( $data['filter_conditions'] ) ) {
			if ( is_array( $data['filter_conditions'] ) ) {
				$sanitized['filter_conditions'] = wp_json_encode( $data['filter_conditions'] );
			} elseif ( is_string( $data['filter_conditions'] ) ) {
				$sanitized['filter_conditions'] = $data['filter_conditions'];
			}
		}

		// message_type (ENUM 驗證).
		if ( isset( $data['message_type'] ) ) {
			$allowed_message_types = array( 'text', 'image', 'video', 'flex' );
			if ( ! in_array( $data['message_type'], $allowed_message_types, true ) ) {
				Logger::error( "Invalid message_type: {$data['message_type']}", array(), 'otz' );
				return false;
			}
			$sanitized['message_type'] = $data['message_type'];
		}

		// message_content (JSON).
		if ( isset( $data['message_content'] ) ) {
			if ( is_array( $data['message_content'] ) ) {
				$sanitized['message_content'] = wp_json_encode( $data['message_content'] );
			} elseif ( is_string( $data['message_content'] ) ) {
				$sanitized['message_content'] = $data['message_content'];
			}
		}

		// notification_disabled.
		if ( isset( $data['notification_disabled'] ) ) {
			$sanitized['notification_disabled'] = (bool) $data['notification_disabled'];
		}

		// schedule_type (ENUM 驗證).
		if ( isset( $data['schedule_type'] ) ) {
			$allowed_schedule_types = array( 'immediate', 'scheduled' );
			if ( ! in_array( $data['schedule_type'], $allowed_schedule_types, true ) ) {
				Logger::error( "Invalid schedule_type: {$data['schedule_type']}", array(), 'otz' );
				return false;
			}
			$sanitized['schedule_type'] = $data['schedule_type'];
		}

		// scheduled_at.
		if ( isset( $data['scheduled_at'] ) ) {
			$sanitized['scheduled_at'] = sanitize_text_field( $data['scheduled_at'] );
		}

		// action_id.
		if ( isset( $data['action_id'] ) ) {
			$sanitized['action_id'] = (int) $data['action_id'];
		}

		// status (ENUM 驗證).
		if ( isset( $data['status'] ) ) {
			$allowed_statuses = array( 'draft', 'published' );
			if ( ! in_array( $data['status'], $allowed_statuses, true ) ) {
				Logger::error( "Invalid status: {$data['status']}", array(), 'otz' );
				return false;
			}
			$sanitized['status'] = $data['status'];
		}

		// last_execution_status (ENUM 驗證).
		if ( isset( $data['last_execution_status'] ) ) {
			$allowed_execution_statuses = array( 'pending', 'success', 'partial', 'failed' );
			if ( ! in_array( $data['last_execution_status'], $allowed_execution_statuses, true ) ) {
				Logger::error( "Invalid last_execution_status: {$data['last_execution_status']}", array(), 'otz' );
				return false;
			}
			$sanitized['last_execution_status'] = $data['last_execution_status'];
		}

		// category.
		if ( isset( $data['category'] ) ) {
			$sanitized['category'] = sanitize_text_field( $data['category'] );
		}

		// tags (JSON).
		if ( isset( $data['tags'] ) ) {
			if ( is_array( $data['tags'] ) ) {
				$sanitized['tags'] = wp_json_encode( $data['tags'] );
			} elseif ( is_string( $data['tags'] ) ) {
				$sanitized['tags'] = $data['tags'];
			}
		}

		// sort_order.
		if ( isset( $data['sort_order'] ) ) {
			$sanitized['sort_order'] = (int) $data['sort_order'];
		}

		// copied_from.
		if ( isset( $data['copied_from'] ) ) {
			$sanitized['copied_from'] = (int) $data['copied_from'];
		}

		return $sanitized;
	}

	/**
	 * 取得 wpdb 操作的資料格式陣列
	 *
	 * @param array $data Campaign 資料.
	 * @return array 格式陣列.
	 */
	private function get_data_format( array $data ): array {
		$format = array();

		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'id':
				case 'action_id':
				case 'sort_order':
				case 'copied_from':
				case 'created_by':
				case 'notification_disabled':
				case 'updated_by':
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
	 * 解碼活動資料中的 JSON 欄位
	 *
	 * @param array $campaign Campaign 資料.
	 * @return array 解碼 JSON 欄位後的 Campaign 資料.
	 */
	private function decode_json_fields( array $campaign ): array {
		$json_fields = array( 'filter_conditions', 'tags' );

		foreach ( $json_fields as $field ) {
			if ( isset( $campaign[ $field ] ) && ! empty( $campaign[ $field ] ) ) {
				$decoded = json_decode( $campaign[ $field ], true );
				if ( null !== $decoded ) {
					$campaign[ $field ] = $decoded;
				}
			}
		}

		// 注意：message_content 刻意保持 JSON 字串格式.
		// 如有需要可由呼叫端自行解碼.

		return $campaign;
	}
}
