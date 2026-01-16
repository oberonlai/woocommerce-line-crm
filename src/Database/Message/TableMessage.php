<?php

declare(strict_types=1);

namespace OrderChatz\Database\Message;

use DateTime;
use Exception;

/**
 * Message Table Manager Class
 *
 * 專門處理訊息資料表操作的類別，負責所有與 otz_messages 相關的資料庫操作
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.18
 */
class TableMessage {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * 取得訊息查詢的 SQL 欄位清單
	 *
	 * @return string SQL SELECT 欄位字串
	 */
	private function get_message_select_fields(): string {
		return "SELECT
			m.id,
			m.event_id,
			m.line_user_id,
			m.group_id,
			m.source_type,
			m.sender_type,
			m.sender_name,
			m.message_type,
			m.message_content,
			m.sent_date,
			m.sent_time,
			m.quoted_message_id,
			m.quote_token,
			m.line_message_id,
			CONCAT(m.sent_date, ' ', m.sent_time) as created_at,
			gm.display_name as sender_display_name,
			gm.avatar_url as sender_avatar_url";
	}

	/**
	 * 檢查資料表是否存在
	 *
	 * @param string $table_name 表名
	 * @return bool 表是否存在
	 */
	private function table_exists( string $table_name ): bool {
		return $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * 在指定月份表中查詢訊息
	 *
	 * @param string $month_table 月份表名
	 * @param string $where_clause WHERE 條件
	 * @param array  $where_values 條件值
	 * @param string $order_clause 排序條件
	 * @return array 查詢結果
	 */
	public function query_messages_from_table( string $month_table, string $where_clause, array $where_values, string $order_clause ): array {
		$group_members_table = $this->wpdb->prefix . 'otz_group_members';

		$sql = $this->get_message_select_fields() . "
			FROM {$month_table} m
			LEFT JOIN {$group_members_table} gm
				ON m.group_id COLLATE utf8mb4_unicode_520_ci = gm.group_id
				AND m.line_user_id COLLATE utf8mb4_unicode_520_ci = gm.line_user_id
			{$where_clause}
			{$order_clause}";

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $where_values ) );
		return $results ?: array();
	}

	/**
	 * 查詢訊息資料
	 *
	 * @param string $line_user_id LINE 使用者 ID
	 * @param string $before_date 查詢日期前限制
	 * @param int    $limit 查詢數量限制
	 * @return array 訊息陣列
	 */
	public function query_messages( string $line_user_id, string $before_date = '', int $limit = 10 ): array {
		$messages        = array();
		$searched_months = array();
		$search_date     = $before_date ?: current_time( 'Y-m-d H:i:s' );

		// 計算十年前的日期
		$ten_years_ago = strtotime( '-10 years', current_time( 'timestamp' ) );

		for ( $i = 0; $i < 120; $i++ ) {
			$search_timestamp = strtotime( "-{$i} month", strtotime( $search_date ) );

			// 如果搜尋日期早於十年前，停止搜尋
			if ( $search_timestamp < $ten_years_ago ) {
				break;
			}

			$search_month     = date( 'Y_m', $search_timestamp );
			$month_table      = $this->wpdb->prefix . 'otz_messages_' . $search_month;

			if ( in_array( $search_month, $searched_months, true ) ) {
				continue;
			}
			$searched_months[] = $search_month;

			if ( ! $this->table_exists( $month_table ) ) {
				continue;
			}

			$condition        = $this->build_where_condition_for_user_or_group( $line_user_id );
			$where_conditions = $condition['conditions'];
			$where_values     = $condition['values'];

			if ( ! empty( $before_date ) ) {
				$where_conditions[] = "CONCAT(m.sent_date, ' ', m.sent_time) < %s";
				$where_values[]     = $before_date;
			}

			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
			$order_clause = 'ORDER BY sent_date DESC, sent_time DESC';

			$month_results = $this->query_messages_from_table( $month_table, $where_clause, $where_values, $order_clause );

			if ( $month_results ) {
				$messages = array_merge( $messages, $month_results );
			}

			// 如果已經收集到足夠的訊息，跳出迴圈以提高效能
			if ( count( $messages ) >= $limit * 2 ) {
				break;
			}
		}

		// 按時間降序排序（最新的在前）
		usort(
			$messages,
			function( $a, $b ) {
				return strtotime( $b->created_at ) - strtotime( $a->created_at );
			}
		);

		// 取前 N 筆作為最終結果
		$messages = array_slice( $messages, 0, $limit );

		// 再按時間升序排序（最舊的在前，符合聊天室顯示順序）
		usort(
			$messages,
			function( $a, $b ) {
				return strtotime( $a->created_at ) - strtotime( $b->created_at );
			}
		);

		return $messages;
	}

	/**
	 * 取得新訊息 (針對當前聊天的好友)
	 *
	 * @param string $line_user_id LINE 使用者 ID
	 * @param string $last_message_time 最後一則訊息時間戳記
	 * @return array 新訊息陣列
	 */
	public function get_new_messages( string $line_user_id, string $last_message_time = '' ): array {
		$messages = array();

		// 如果沒有提供最後訊息時間，使用當前時間往前5分鐘
		if ( empty( $last_message_time ) ) {
			$last_message_time = date( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );
		}

		// 動態計算需要查詢的月份範圍
		$last_month    = date( 'Y_m', strtotime( $last_message_time ) );
		$current_month = date( 'Y_m' );

		// 決定要查詢的月份列表
		$months_to_search = array( $current_month );
		if ( $last_month !== $current_month ) {
			$months_to_search[] = $last_month;
		}

		// 查詢每個需要的月份
		foreach ( $months_to_search as $search_month ) {
			$table_messages = $this->wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $table_messages ) ) {
				continue;
			}

			$condition        = $this->build_where_condition_for_user_or_group( $line_user_id );
			$where_conditions = $condition['conditions'];
			$where_conditions[] = "CONCAT(m.sent_date, ' ', m.sent_time) > %s";
			$where_clause     = 'WHERE ' . implode( ' AND ', $where_conditions );
			$where_values     = array_merge( $condition['values'], array( $last_message_time ) );
			$order_clause = 'ORDER BY sent_date ASC, sent_time ASC LIMIT 50';

			$month_results = $this->query_messages_from_table( $table_messages, $where_clause, $where_values, $order_clause );

			foreach ( $month_results as $row ) {
				$messages[] = $row;
			}
		}

		// 按時間升序排序（舊到新）
		usort(
			$messages,
			function( $a, $b ) {
				return strtotime( $a->created_at ) - strtotime( $b->created_at );
			}
		);

		return $messages;
	}

	/**
	 * 根據 line_message_id 查詢被引用的訊息
	 *
	 * @param string $quoted_message_id 被引用的訊息 ID
	 * @return object|null 被引用的訊息資料或 null
	 */
	public function fetch_quoted_message( string $quoted_message_id ): ?object {
		// 需要在多個月份表中查詢，因為引用訊息可能在不同月份
		for ( $i = 0; $i < 12; $i++ ) {
			$search_month = date( 'Y_m', strtotime( "-{$i} month" ) );
			$month_table  = $this->wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $month_table ) ) {
				continue;
			}

			// 根據 line_message_id 查詢
			$where_clause = 'WHERE m.line_message_id = %s';
			$where_values = array( $quoted_message_id );
			$order_clause = 'LIMIT 1';

			$results = $this->query_messages_from_table( $month_table, $where_clause, $where_values, $order_clause );

			if ( ! empty( $results ) ) {
				return $results[0];
			}
		}

		return null;
	}

	/**
	 * 根據時間戳記取得前後訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID
	 * @param string $target_timestamp 目標時間戳記 (Y-m-d H:i:s)
	 * @param int    $before_count 之前的訊息數量
	 * @param int    $after_count 之後的訊息數量
	 * @return array 包含目標訊息前後的訊息陣列
	 */
	public function get_messages_around_timestamp( string $line_user_id, string $target_timestamp, int $before_count = 20, int $after_count = 20 ): array {
		try {
			// 解析時間戳記取得日期和時間
			$datetime    = new DateTime( $target_timestamp );
			$target_date = $datetime->format( 'Y-m-d' );
			$target_time = $datetime->format( 'H:i:s' );

			// 確定要查詢的月份表
			$search_month = $datetime->format( 'Y_m' );
			$month_table  = $this->wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $month_table ) ) {
				return array();
			}

			$condition = $this->build_where_condition_for_user_or_group( $line_user_id );

			// 先找到目標時間點的訊息（如有多筆，取最新的）.
			$target_conditions   = $condition['conditions'];
			$target_conditions[] = 'sent_date = %s';
			$target_conditions[] = 'sent_time = %s';
			$target_where        = 'WHERE ' . implode( ' AND ', $target_conditions );
			$target_values       = array_merge( $condition['values'], array( $target_date, $target_time ) );
			$target_order  = 'ORDER BY id DESC LIMIT 1';

			$target_results = $this->query_messages_from_table( $month_table, $target_where, $target_values, $target_order );

			if ( empty( $target_results ) ) {
				return array();
			}

			$target_message = $target_results[0];

			// 取得目標訊息之前的訊息.
			$before_conditions   = $condition['conditions'];
			$before_conditions[] = "CONCAT(m.sent_date, ' ', m.sent_time) < %s";
			$before_where        = 'WHERE ' . implode( ' AND ', $before_conditions );
			$before_values       = array_merge( $condition['values'], array( $target_timestamp ) );
			$before_order  = 'ORDER BY sent_date DESC, sent_time DESC LIMIT ' . intval( $before_count );

			$before_results = $this->query_messages_from_table( $month_table, $before_where, $before_values, $before_order );

			// 取得目標訊息之後的訊息.
			$after_conditions   = $condition['conditions'];
			$after_conditions[] = "CONCAT(m.sent_date, ' ', m.sent_time) > %s";
			$after_where        = 'WHERE ' . implode( ' AND ', $after_conditions );
			$after_values       = array_merge( $condition['values'], array( $target_timestamp ) );
			$after_order  = 'ORDER BY sent_date ASC, sent_time ASC LIMIT ' . intval( $after_count );

			$after_results = $this->query_messages_from_table( $month_table, $after_where, $after_values, $after_order );

			// 合併結果：之前的訊息 + 目標訊息 + 之後的訊息
			$all_messages = array();

			// 加入之前的訊息（需要反轉順序）
			if ( ! empty( $before_results ) ) {
				$all_messages = array_merge( $all_messages, array_reverse( $before_results ) );
			}

			// 加入目標訊息並標記
			$target_message->is_target = true;
			$all_messages[]            = $target_message;
			$target_index              = count( $all_messages ) - 1;

			// 加入之後的訊息
			if ( ! empty( $after_results ) ) {
				$all_messages = array_merge( $all_messages, $after_results );
			}

			return array(
				'messages'        => $all_messages,
				'target_index'    => $target_index,
				'total_count'     => count( $all_messages ),
				'has_more_before' => count( $before_results ) === $before_count,
				'has_more_after'  => count( $after_results ) === $after_count,
			);

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * 取得最新的 reply token
	 *
	 * @param string $line_user_id LINE 使用者 ID
	 * @param string $group_id 群組 ID（選填，用於區分個人和群組對話）
	 * @return string|null Reply token 或 null
	 */
	public function get_latest_reply_token( string $line_user_id, string $group_id = '' ): ?string {
		try {
			for ( $i = 0; $i < 3; $i++ ) {
				$search_month = date( 'Y_m', strtotime( "-{$i} month" ) );
				$table_name   = $this->wpdb->prefix . 'otz_messages_' . $search_month;

				if ( ! $this->table_exists( $table_name ) ) {
					continue;
				}

				// 根據是否為群組對話，決定查詢條件.
				if ( ! empty( $group_id ) ) {
					// 群組對話：只查詢該群組的 reply_token.
					$sql = "SELECT reply_token
							FROM {$table_name}
							WHERE line_user_id = %s
							AND group_id = %s
							AND reply_token IS NOT NULL
							AND reply_token != ''
							AND reply_token NOT LIKE 'used_%'
							ORDER BY sent_date DESC, sent_time DESC
							LIMIT 1";

					$reply_token = $this->wpdb->get_var( $this->wpdb->prepare( $sql, $line_user_id, $group_id ) );
				} else {
					// 個人對話：只查詢個人訊息的 reply_token（排除群組訊息）.
					$sql = "SELECT reply_token
							FROM {$table_name}
							WHERE line_user_id = %s
							AND (group_id IS NULL OR group_id = '')
							AND reply_token IS NOT NULL
							AND reply_token != ''
							AND reply_token NOT LIKE 'used_%'
							ORDER BY sent_date DESC, sent_time DESC
							LIMIT 1";

					$reply_token = $this->wpdb->get_var( $this->wpdb->prepare( $sql, $line_user_id ) );
				}

				if ( ! empty( $reply_token ) ) {
					return $reply_token;
				}
			}

			return null;

		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * 標記 reply token 為已使用
	 *
	 * @param string $reply_token Reply token
	 * @return void
	 */
	public function mark_reply_token_as_used( string $reply_token ): void {
		try {
			for ( $i = 0; $i < 3; $i++ ) {
				$search_month = date( 'Y_m', strtotime( "-{$i} month" ) );
				$table_name   = $this->wpdb->prefix . 'otz_messages_' . $search_month;

				if ( ! $this->table_exists( $table_name ) ) {
					continue;
				}

				$updated = $this->wpdb->update(
					$table_name,
					array( 'reply_token' => 'used_' . $reply_token ),
					array( 'reply_token' => $reply_token ),
					array( '%s' ),
					array( '%s' )
				);

				if ( $updated > 0 ) {
					return;
				}
			}
		} catch ( Exception $e ) {
			// 靜默處理錯誤
		}
	}

	/**
	 * 根據時間戳和方向取得訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID
	 * @param string $reference_timestamp 參考時間戳 (Y-m-d H:i:s)
	 * @param string $direction 查詢方向 ('before' 或 'after')
	 * @param int    $limit 查詢數量限制
	 * @return array 訊息陣列
	 */
	public function get_messages_by_timestamp_direction( string $line_user_id, string $reference_timestamp, string $direction = 'before', int $limit = 10 ): array {
		try {
			// 根據時間戳確定月份表
			$search_month = date( 'Y_m', strtotime( $reference_timestamp ) );
			$month_table  = $this->wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $month_table ) ) {
				return array();
			}

			// 根據 ID 類型建立查詢條件.
			$condition        = $this->build_where_condition_for_user_or_group( $line_user_id );
			$where_conditions = $condition['conditions'];

			// 根據方向設定查詢條件.
			if ( $direction === 'before' ) {
				$where_conditions[] = "CONCAT(sent_date, ' ', sent_time) < %s";
				$order_clause = 'ORDER BY sent_date DESC, sent_time DESC LIMIT ' . intval( $limit );
			} else { // after.
				$where_conditions[] = "CONCAT(sent_date, ' ', sent_time) > %s";
				$order_clause = 'ORDER BY sent_date ASC, sent_time ASC LIMIT ' . intval( $limit );
			}

			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
			$where_values = array_merge( $condition['values'], array( $reference_timestamp ) );

			return $this->query_messages_from_table( $month_table, $where_clause, $where_values, $order_clause );

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * 根據 ID 類型建立查詢條件
	 *
	 * LINE ID 格式規則：
	 * - User ID: U 開頭
	 * - Group ID: C 開頭
	 * - Room ID: R 開頭
	 *
	 * @param string $line_user_id LINE User ID 或 Group ID.
	 * @return array ['field' => 欄位名稱, 'values' => 值陣列, 'conditions' => WHERE 條件陣列].
	 */
	private function build_where_condition_for_user_or_group( $line_user_id ) {
		if ( preg_match( '/^[CR]/', $line_user_id ) ) {
			// 群組/聊天室訊息：同時檢查 group_id 或 line_user_id（向後相容舊資料）.
			return array(
				'field'      => 'm.group_id',
				'values'     => array( $line_user_id, $line_user_id ),
				'conditions' => array( '(m.group_id = %s OR m.line_user_id = %s)' ),
			);
		} else {
			// 個人訊息：用 line_user_id 查詢，並排除群組訊息.
			return array(
				'field'      => 'm.line_user_id',
				'values'     => array( $line_user_id ),
				'conditions' => array(
					'm.line_user_id = %s',
					"(m.group_id IS NULL OR m.group_id = '' OR m.group_id = 'NULL')",
				),
			);
		}
	}
}
