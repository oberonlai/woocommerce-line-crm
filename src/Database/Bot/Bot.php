<?php

declare(strict_types=1);

namespace OrderChatz\Database\Bot;

use OrderChatz\Util\Logger;
use wpdb;

/**
 * Otz_bot 資料表類別
 *
 * 處理 otz_bot 資料表的 CRUD 操作.
 * 管理 AI 機器人的設定與配置.
 *
 * @package    OrderChatz
 * @subpackage Database\Bot
 * @since      1.1.6
 */
class Bot {

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Bot 資料表名稱
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
		$this->table_name = $wpdb->prefix . 'otz_bot';
	}

	/**
	 * 儲存機器人（新增或更新）
	 *
	 * @param array $data Bot 資料.
	 * @return int|false 成功時返回 Bot ID，失敗時返回 false.
	 */
	public function save_bot( array $data ): int|false {
		try {
			// 清理和驗證資料.
			$sanitized_data = $this->sanitize_bot_data( $data );
			if ( false === $sanitized_data ) {
				return false;
			}

			// 判斷是更新還是新增.
			$is_update = ! empty( $data['id'] );

			if ( $is_update ) {
				// 更新模式.
				$bot_id = (int) $data['id'];

				if ( ! $this->bot_exists( $bot_id ) ) {
					Logger::error( "Bot ID {$bot_id} does not exist", array(), 'otz' );
					return false;
				}

				// 新增 updated_at.
				$sanitized_data['updated_at'] = current_time( 'mysql' );

				// 從資料中移除 id 以進行更新.
				unset( $sanitized_data['id'] );

				// 準備格式陣列.
				$format = $this->get_data_format( $sanitized_data );

				$result = $this->wpdb->update(
					$this->table_name,
					$sanitized_data,
					array( 'id' => $bot_id ),
					$format,
					array( '%d' )
				);

				if ( false === $result ) {
					Logger::error( 'Failed to update bot: ' . $this->wpdb->last_error, array(), 'otz' );
					return false;
				}

				return $bot_id;

			} else {
				// 新增模式.
				$sanitized_data['created_at'] = current_time( 'mysql' );

				// 準備格式陣列.
				$format = $this->get_data_format( $sanitized_data );

				$result = $this->wpdb->insert(
					$this->table_name,
					$sanitized_data,
					$format
				);

				if ( false === $result ) {
					Logger::error( 'Failed to create bot: ' . $this->wpdb->last_error, array(), 'otz' );
					return false;
				}

				return $this->wpdb->insert_id;
			}
		} catch ( \Exception $e ) {
			Logger::error( 'Exception in save_bot: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 根據 ID 取得單一機器人
	 *
	 * @param int $id Bot ID.
	 * @return array|null Bot 資料或找不到時返回 null.
	 */
	public function get_bot( int $id ): ?array {
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
			Logger::error( 'Exception in get_bot: ' . $e->getMessage(), array(), 'otz' );
			return null;
		}
	}

	/**
	 * 取得所有機器人
	 *
	 * @param array $args 查詢參數（order_by, order, limit, offset）.
	 * @return array Bot 陣列.
	 */
	public function get_all_bots( array $args = array() ): array {
		try {
			// 設定預設值.
			$defaults = array(
				'order_by' => 'priority',
				'order'    => 'ASC',
				'limit'    => 100,
				'offset'   => 0,
			);

			$args = wp_parse_args( $args, $defaults );

			// 驗證 order_by.
			$allowed_columns = array( 'id', 'name', 'priority', 'status', 'created_at', 'trigger_count' );
			if ( ! in_array( $args['order_by'], $allowed_columns, true ) ) {
				$args['order_by'] = 'priority';
			}

			// 驗證 order.
			$args['order'] = strtoupper( $args['order'] );
			if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ) {
				$args['order'] = 'ASC';
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

			// 解碼每個機器人的 JSON 欄位.
			return array_map( array( $this, 'decode_json_fields' ), $results );

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_all_bots: ' . $e->getMessage(), array(), 'otz' );
			return array();
		}
	}

	/**
	 * 根據狀態取得機器人
	 *
	 * @param string $status Bot 狀態（active 或 inactive）.
	 * @param array  $args 查詢參數（order_by, order, limit, offset）.
	 * @return array Bot 陣列.
	 */
	public function get_bots_by_status( string $status, array $args = array() ): array {
		try {
			// 驗證狀態.
			$allowed_statuses = array( 'active', 'inactive' );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				Logger::error( "Invalid status: {$status}", array(), 'otz' );
				return array();
			}

			// 設定預設值.
			$defaults = array(
				'order_by' => 'priority',
				'order'    => 'ASC',
				'limit'    => 100,
				'offset'   => 0,
			);

			$args = wp_parse_args( $args, $defaults );

			// 驗證 order_by.
			$allowed_columns = array( 'id', 'name', 'priority', 'created_at', 'trigger_count' );
			if ( ! in_array( $args['order_by'], $allowed_columns, true ) ) {
				$args['order_by'] = 'priority';
			}

			// 驗證 order.
			$args['order'] = strtoupper( $args['order'] );
			if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ), true ) ) {
				$args['order'] = 'ASC';
			}

			// 建立查詢.
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE status = %s
				ORDER BY {$args['order_by']} {$args['order']}
				LIMIT %d OFFSET %d",
				$status,
				$args['limit'],
				$args['offset']
			);

			$results = $this->wpdb->get_results( $sql, ARRAY_A );

			if ( ! $results ) {
				return array();
			}

			// 解碼每個機器人的 JSON 欄位.
			return array_map( array( $this, 'decode_json_fields' ), $results );

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_bots_by_status: ' . $e->getMessage(), array(), 'otz' );
			return array();
		}
	}

	/**
	 * 刪除機器人
	 *
	 * @param int $id Bot ID.
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function delete_bot( int $id ): bool {
		try {
			if ( ! $this->bot_exists( $id ) ) {
				Logger::error( "Bot ID {$id} does not exist", array(), 'otz' );
				return false;
			}

			$result = $this->wpdb->delete(
				$this->table_name,
				array( 'id' => $id ),
				array( '%d' )
			);

			if ( false === $result ) {
				Logger::error( 'Failed to delete bot: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in delete_bot: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * 檢查機器人是否存在
	 *
	 * @param int $id Bot ID.
	 * @return bool 存在時返回 true，否則返回 false.
	 */
	private function bot_exists( int $id ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
			$id
		);

		return (int) $this->wpdb->get_var( $sql ) > 0;
	}

	/**
	 * 清理和驗證機器人資料
	 *
	 * @param array $data 原始 Bot 資料.
	 * @return array|false 清理後的資料或驗證失敗時返回 false.
	 */
	private function sanitize_bot_data( array $data ): array|false {
		$sanitized = array();

		// 判斷是否為更新模式.
		$is_update = ! empty( $data['id'] );

		// 必填欄位驗證.
		$required_fields = array( 'name', 'keywords', 'action_type' );

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

		// name.
		if ( isset( $data['name'] ) ) {
			$sanitized['name'] = sanitize_text_field( $data['name'] );
		}

		// description.
		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] );
		}

		// keywords (JSON).
		if ( isset( $data['keywords'] ) ) {
			if ( is_array( $data['keywords'] ) ) {
				$sanitized['keywords'] = wp_json_encode( $data['keywords'] );
			} elseif ( is_string( $data['keywords'] ) ) {
				$sanitized['keywords'] = $data['keywords'];
			}
		}

		// action_type (ENUM 驗證).
		if ( isset( $data['action_type'] ) ) {
			$allowed_action_types = array( 'ai', 'human' );
			if ( ! in_array( $data['action_type'], $allowed_action_types, true ) ) {
				Logger::error( "Invalid action_type: {$data['action_type']}", array(), 'otz' );
				return false;
			}
			$sanitized['action_type'] = $data['action_type'];
		}

		// api_key.
		if ( isset( $data['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $data['api_key'] );
		}

		// model.
		if ( isset( $data['model'] ) ) {
			$sanitized['model'] = sanitize_text_field( $data['model'] );
		}

		// system_prompt.
		if ( isset( $data['system_prompt'] ) ) {
			$sanitized['system_prompt'] = sanitize_textarea_field( $data['system_prompt'] );
		}

		// handoff_message.
		if ( isset( $data['handoff_message'] ) ) {
			$sanitized['handoff_message'] = sanitize_textarea_field( $data['handoff_message'] );
		}

		// function_tools (JSON).
		if ( isset( $data['function_tools'] ) ) {
			if ( is_array( $data['function_tools'] ) ) {
				$sanitized['function_tools'] = wp_json_encode( $data['function_tools'] );
			} elseif ( is_string( $data['function_tools'] ) ) {
				$sanitized['function_tools'] = $data['function_tools'];
			}
		}

		// quick_replies (JSON) - 驗證並過濾超過 15 字元的項目.
		if ( isset( $data['quick_replies'] ) ) {
			if ( is_array( $data['quick_replies'] ) ) {
				$validated_quick_replies   = $this->sanitize_quick_replies( $data['quick_replies'] );
				$sanitized['quick_replies'] = wp_json_encode( $validated_quick_replies );
			} elseif ( is_string( $data['quick_replies'] ) ) {
				// 如果是字串，先解碼再驗證.
				$decoded = json_decode( $data['quick_replies'], true );
				if ( is_array( $decoded ) ) {
					$validated_quick_replies   = $this->sanitize_quick_replies( $decoded );
					$sanitized['quick_replies'] = wp_json_encode( $validated_quick_replies );
				} else {
					$sanitized['quick_replies'] = $data['quick_replies'];
				}
			}
		}

		// trigger_count.
		if ( isset( $data['trigger_count'] ) ) {
			$sanitized['trigger_count'] = (int) $data['trigger_count'];
		}

		// total_tokens.
		if ( isset( $data['total_tokens'] ) ) {
			$sanitized['total_tokens'] = (int) $data['total_tokens'];
		}

		// avg_response_time.
		if ( isset( $data['avg_response_time'] ) ) {
			$sanitized['avg_response_time'] = (float) $data['avg_response_time'];
		}

		// priority.
		if ( isset( $data['priority'] ) ) {
			$sanitized['priority'] = (int) $data['priority'];
		}

		// status (ENUM 驗證).
		if ( isset( $data['status'] ) ) {
			$allowed_statuses = array( 'active', 'inactive' );
			if ( ! in_array( $data['status'], $allowed_statuses, true ) ) {
				Logger::error( "Invalid status: {$data['status']}", array(), 'otz' );
				return false;
			}
			$sanitized['status'] = $data['status'];
		}

		return $sanitized;
	}

	/**
	 * 取得 wpdb 操作的資料格式陣列
	 *
	 * @param array $data Bot 資料.
	 * @return array 格式陣列.
	 */
	private function get_data_format( array $data ): array {
		$format = array();

		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'id':
				case 'trigger_count':
				case 'total_tokens':
				case 'priority':
					$format[] = '%d';
					break;
				case 'avg_response_time':
					$format[] = '%f';
					break;
				default:
					$format[] = '%s';
					break;
			}
		}

		return $format;
	}

	/**
	 * 驗證並清理 Quick Replies 資料
	 *
	 * @param array $quick_replies Quick reply 項目陣列.
	 * @return array 驗證後的 Quick reply 陣列（過濾空值和超長項目）.
	 */
	private function sanitize_quick_replies( array $quick_replies ): array {
		$sanitized  = array();
		$max_length = 15;
		$max_items  = 13; // LINE API 限制.

		foreach ( $quick_replies as $item ) {
			// 過濾空值.
			if ( empty( $item ) || ! is_string( $item ) ) {
				continue;
			}

			// 清理字串.
			$item = sanitize_text_field( $item );

			// 檢查長度限制.
			$item_length = mb_strlen( $item, 'UTF-8' );
			if ( $item_length > $max_length ) {
				Logger::warning(
					sprintf(
						'Quick reply item "%s" exceeds %d characters (length: %d), skipping.',
						$item,
						$max_length,
						$item_length
					),
					array( 'source' => 'orderchatz-bot-database' ),
					'otz'
				);
				continue;
			}

			$sanitized[] = $item;

			// 達到最大數量限制.
			if ( count( $sanitized ) >= $max_items ) {
				Logger::info(
					sprintf( 'Reached maximum of %d quick reply items, ignoring remaining items.', $max_items ),
					array( 'source' => 'orderchatz-bot-database' ),
					'otz'
				);
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * 解碼機器人資料中的 JSON 欄位
	 *
	 * @param array $bot Bot 資料.
	 * @return array 解碼 JSON 欄位後的 Bot 資料.
	 */
	private function decode_json_fields( array $bot ): array {
		$json_fields = array( 'keywords', 'function_tools', 'quick_replies' );

		foreach ( $json_fields as $field ) {
			if ( isset( $bot[ $field ] ) && ! empty( $bot[ $field ] ) ) {
				$decoded = json_decode( $bot[ $field ], true );
				if ( null !== $decoded ) {
					$bot[ $field ] = $decoded;
				}
			}
		}

		return $bot;
	}
}
