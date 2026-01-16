<?php
/**
 * 輪詢服務
 *
 * 處理所有輪詢更新相關的業務邏輯
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

use OrderChatz\Ajax\AbstractAjaxHandler;

class PollingService extends AbstractAjaxHandler {

	/**
	 * 訊息查詢服務
	 *
	 * @var MessageQueryService
	 */
	private $message_query_service;

	/**
	 * 表存在性快取
	 *
	 * @var array
	 */
	private array $table_exists_cache = array();

	/**
	 * 建構函式
	 *
	 * @param MessageQueryService $message_query_service 訊息查詢服務.
	 */
	public function __construct( MessageQueryService $message_query_service ) {
		$this->message_query_service = $message_query_service;
	}

	/**
	 * 取得新加入的好友
	 *
	 * @param int $last_friend_id 最後一個好友 ID.
	 * @return array 新好友陣列.
	 */
	public function getNewFriends( $last_friend_id ) {
		global $wpdb;

		$table_users = $wpdb->prefix . 'otz_users';

		$sql = "SELECT 
                    id,
                    line_user_id,
                    display_name,
                    avatar_url,
                    followed_at
                FROM {$table_users}
                WHERE id > %d
                AND line_user_id IS NOT NULL 
                AND line_user_id != ''
                ORDER BY id ASC
                LIMIT 50";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $last_friend_id ) );

		$friends = array();
		foreach ( $results as $row ) {
			$friends[] = array(
				'id'                => $row->id,
				'line_user_id'      => $row->line_user_id,
				'name'              => $row->display_name,
				'avatar'            => $row->avatar_url ?: $this->getDefaultAvatar(),
				'last_message'      => '尚無對話',
				'last_message_time' => $this->message_query_service->formatMessageTime( $row->followed_at ),
				'unread_count'      => 0,
			);
		}

		return $friends;
	}

	/**
	 * 取得好友更新
	 *
	 * @param string $last_updated_at 最後更新時間.
	 * @return array 好友更新陣列.
	 */
	public function getFriendUpdates( $last_updated_at ) {
		global $wpdb;

		$updates      = array();
		$current_time = current_time( 'mysql' );

		if ( $last_updated_at ) {
			$check_time = date( 'Y-m-d H:i:s', strtotime( $last_updated_at . ' -30 seconds' ) );
		} else {
			$check_time = date( 'Y-m-d H:i:s', strtotime( '-10 minutes' ) );
		}

		$updated_friends = array();

		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $wpdb->prefix . 'otz_messages_' . $search_month;

			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table_messages
				)
			);

			if ( ! $table_exists ) {
				continue;
			}

			// 同時查詢個人和群組的更新.
			$sql = "SELECT DISTINCT
			            CASE
			                WHEN group_id IS NOT NULL AND group_id != '' THEN group_id
			                ELSE line_user_id
			            END as identifier,
			            CASE
			                WHEN group_id IS NOT NULL AND group_id != '' THEN 'group'
			                ELSE 'user'
			            END as type
			        FROM {$table_messages}
			        WHERE CONCAT(sent_date, ' ', sent_time) > %s";

			$new_message_items = $wpdb->get_results( $wpdb->prepare( $sql, $check_time ) );
			$updated_friends   = array_merge( $updated_friends, $new_message_items );
		}

		// 去重並分類個人和群組.
		$unique_items = array();
		$seen         = array();

		foreach ( $updated_friends as $item ) {
			$key = $item->type . '_' . $item->identifier;
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ]   = true;
				$unique_items[] = $item;
			}
		}

		// 分離個人和群組 ID.
		$user_ids  = array();
		$group_ids = array();

		foreach ( $unique_items as $item ) {
			if ( $item->type === 'group' ) {
				$group_ids[] = $item->identifier;
			} else {
				$user_ids[] = $item->identifier;
			}
		}

		// 分別計算個人和群組的統計.
		if ( ! empty( $user_ids ) ) {
			$user_stats = $this->calculate_batch_friend_stats( $user_ids );
			foreach ( $user_stats as $stat ) {
				$updates[] = $stat;
			}
		}

		if ( ! empty( $group_ids ) ) {
			foreach ( $group_ids as $group_id ) {
				$group_stat = $this->calculate_group_stats( $group_id );
				if ( $group_stat ) {
					$updates[] = $group_stat;
				}
			}
		}

		return $updates;
	}

	/**
	 * 計算特定好友的統計資訊（未讀數、最後訊息等）
	 *
	 * @deprecated 1.1.4 請使用 calculate_batch_friend_stats() 進行批量查詢以提升效能.
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @return array|null 好友統計資訊或 null.
	 */
	public function calculateFriendStats( $line_user_id ) {
		global $wpdb;

		$unread_count      = 0;
		$last_message      = '';
		$last_message_time = '';

		$user_read_time = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT read_time FROM {$wpdb->prefix}otz_users WHERE line_user_id = %s",
				$line_user_id
			)
		);

		$read_threshold = $user_read_time ?: date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) );

		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $wpdb->prefix . 'otz_messages_' . $search_month;

			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table_messages
				)
			);

			if ( ! $table_exists ) {
				continue;
			}

			$unread_sql    = "SELECT COUNT(*) FROM {$table_messages} 
                           WHERE line_user_id = %s 
                           AND sender_type = 'USER'
                           AND CONCAT(sent_date, ' ', sent_time) > %s";
			$month_unread  = $wpdb->get_var( $wpdb->prepare( $unread_sql, $line_user_id, $read_threshold ) );
			$unread_count += intval( $month_unread );

			$latest_sql = "SELECT message_content, message_type, CONCAT(sent_date, ' ', sent_time) as full_time
                         FROM {$table_messages}
                         WHERE line_user_id = %s
                         ORDER BY sent_date DESC, sent_time DESC
                         LIMIT 1";

			$latest_message = $wpdb->get_row( $wpdb->prepare( $latest_sql, $line_user_id ) );

			if ( $latest_message ) {
				if ( empty( $last_message_time ) || strtotime( $latest_message->full_time ) > strtotime( $last_message_time ) ) {
					$last_message      = $this->formatLastMessage( $latest_message->message_content, $latest_message->message_type );
					$last_message_time = $latest_message->full_time;
				}
			}
		}

		if ( empty( $last_message ) ) {
			return null;
		}

		$result = array(
			'line_user_id'           => $line_user_id,
			'unread_count'           => min( $unread_count, 99 ),
			'last_message'           => $last_message,
			'last_message_time'      => $this->message_query_service->formatMessageTime( $last_message_time ),
			'last_message_timestamp' => strtotime( $last_message_time ),
		);

		return $result;
	}

	/**
	 * 輔助方法：取得最大的好友 ID
	 *
	 * @param array $friends 好友陣列.
	 * @param int   $current_max 目前最大值.
	 * @return int 最大好友 ID.
	 */
	public function getMaxFriendId( $friends, $current_max ) {
		if ( empty( $friends ) ) {
			return $current_max;
		}

		$max_id = $current_max;
		foreach ( $friends as $friend ) {
			if ( $friend['id'] > $max_id ) {
				$max_id = $friend['id'];
			}
		}

		return $max_id;
	}

	/**
	 * 輔助方法：取得最大的好友更新時間
	 *
	 * @param array  $updates 更新陣列.
	 * @param string $current_max 目前最大值.
	 * @return string 最大好友更新時間.
	 */
	public function getMaxFriendUpdatedAt( $updates, $current_max ) {
		if ( empty( $updates ) ) {
			return $current_max ?: current_time( 'mysql' );
		}

		return current_time( 'mysql' );
	}

	/**
	 * 輔助方法：取得最大的訊息 ID
	 *
	 * @param array $messages 訊息陣列.
	 * @param int   $current_max 目前最大值.
	 * @return int 最大訊息 ID.
	 */
	public function getMaxMessageId( $messages, $current_max ) {
		if ( empty( $messages ) ) {
			return $current_max;
		}

		$max_id = $current_max;
		foreach ( $messages as $message ) {
			if ( $message['id'] > $max_id ) {
				$max_id = $message['id'];
			}
		}

		return $max_id;
	}

	/**
	 * 輔助方法：檢查是否有任何更新
	 *
	 * @param array $updates 更新陣列.
	 * @return bool 是否有更新.
	 */
	public function hasAnyUpdates( $updates ) {
		return ! empty( $updates['new_friends'] ) || ! empty( $updates['friend_updates'] ) ||
			   ! empty( $updates['new_messages'] );
	}

	/**
	 * 檢查資料表是否存在（帶快取）
	 *
	 * @param string $table_name 資料表名稱.
	 * @return bool 是否存在.
	 */
	private function table_exists( $table_name ) {
		global $wpdb;

		// 檢查快取.
		if ( isset( $this->table_exists_cache[ $table_name ] ) ) {
			return $this->table_exists_cache[ $table_name ];
		}

		// 執行查詢.
		$query  = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
		$exists = $wpdb->get_var( $query ) === $table_name;

		// 存入快取.
		$this->table_exists_cache[ $table_name ] = $exists;

		return $exists;
	}

	/**
	 * 批量計算好友統計資訊
	 *
	 * @param array $line_user_ids LINE 使用者 ID 陣列.
	 * @return array 以 line_user_id 為 key 的統計陣列.
	 */
	private function calculate_batch_friend_stats( $line_user_ids ) {
		global $wpdb;

		if ( empty( $line_user_ids ) ) {
			return array();
		}

		$stats = array();

		// 先取得所有用戶的 read_time 和 bot_status.
		$placeholders = implode( ',', array_fill( 0, count( $line_user_ids ), '%s' ) );
		$users_sql    = "SELECT line_user_id, read_time, bot_status FROM {$wpdb->prefix}otz_users WHERE line_user_id IN ({$placeholders})";
		$users_data   = $wpdb->get_results( $wpdb->prepare( $users_sql, $line_user_ids ) );
		$read_times   = array();

		foreach ( $users_data as $user ) {
			$read_times[ $user->line_user_id ] = $user->read_time ?: date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) );
			$stats[ $user->line_user_id ]      = array(
				'line_user_id'           => $user->line_user_id,
				'bot_status'             => $user->bot_status,
				'unread_count'           => 0,
				'last_message'           => '',
				'last_message_time'      => '',
				'last_message_timestamp' => 0,
			);
		}

		// 查詢最近 2 個月的訊息表.
		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $table_messages ) ) {
				continue;
			}

			// 批量查詢未讀數.
			$case_when_parts = array();
			$params          = array();
			foreach ( $line_user_ids as $line_user_id ) {
				$case_when_parts[] = 'WHEN %s THEN %s';
				$params[]          = $line_user_id;
				$params[]          = $read_times[ $line_user_id ];
			}
			$case_when = implode( ' ', $case_when_parts );

			$unread_sql = "SELECT
				line_user_id,
				COUNT(*) as unread_count
			FROM {$table_messages}
			WHERE line_user_id IN ({$placeholders})
			AND (group_id IS NULL OR group_id = '')
			AND sender_type = 'USER'
			AND CONCAT(sent_date, ' ', sent_time) > CASE line_user_id {$case_when} END
			GROUP BY line_user_id";

			$params         = array_merge( $line_user_ids, $params );
			$unread_results = $wpdb->get_results( $wpdb->prepare( $unread_sql, $params ) );

			foreach ( $unread_results as $result ) {
				if ( isset( $stats[ $result->line_user_id ] ) ) {
					$stats[ $result->line_user_id ]['unread_count'] += intval( $result->unread_count );
				}
			}

			// 批量查詢最後訊息.
			$subqueries = array();
			foreach ( $line_user_ids as $line_user_id ) {
				$subqueries[] = $wpdb->prepare(
					"(SELECT line_user_id, message_content, message_type, CONCAT(sent_date, ' ', sent_time) as full_time
					FROM {$table_messages}
					WHERE line_user_id = %s
					AND (group_id IS NULL OR group_id = '')
					ORDER BY sent_date DESC, sent_time DESC
					LIMIT 1)",
					$line_user_id
				);
			}

			if ( ! empty( $subqueries ) ) {
				$latest_sql     = implode( ' UNION ALL ', $subqueries );
				$latest_results = $wpdb->get_results( $latest_sql );

				foreach ( $latest_results as $latest ) {
					$line_user_id = $latest->line_user_id;
					if ( ! isset( $stats[ $line_user_id ] ) ) {
						continue;
					}

					$current_time = strtotime( $latest->full_time );
					$stored_time  = $stats[ $line_user_id ]['last_message_timestamp'];

					// 只保留最新的訊息.
					if ( $current_time > $stored_time ) {
						$stats[ $line_user_id ]['last_message']           = $this->formatLastMessage( $latest->message_content, $latest->message_type );
						$stats[ $line_user_id ]['last_message_time']      = $this->message_query_service->formatMessageTime( $latest->full_time );
						$stats[ $line_user_id ]['last_message_timestamp'] = $current_time;
					}
				}
			}
		}

		// 限制未讀數最大值為 99，並過濾掉沒有訊息的好友.
		$filtered_stats = array();
		foreach ( $stats as $line_user_id => $stat ) {
			if ( ! empty( $stat['last_message'] ) ) {
				$stat['unread_count']            = min( $stat['unread_count'], 99 );
				$filtered_stats[ $line_user_id ] = $stat;
			}
		}

		return $filtered_stats;
	}

	/**
	 * 計算單個群組的統計資訊
	 *
	 * @param string $group_id 群組 ID.
	 * @return array|null 群組統計資訊或 null.
	 */
	private function calculate_group_stats( $group_id ) {
		global $wpdb;

		if ( empty( $group_id ) ) {
			return null;
		}

		// 從 wp_otz_groups 取得群組基本資訊.
		$group = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT group_id, group_name, created_at, read_time
				FROM {$wpdb->prefix}otz_groups
				WHERE group_id = %s",
				$group_id
			)
		);

		if ( ! $group ) {
			return null;
		}

		// 使用群組的 read_time（如果沒有則使用 3 小時前作為預設值）.
		$read_time = $group->read_time ?: date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) );

		$stat = array(
			'line_user_id'           => $group_id,
			'group_id'               => $group_id,
			'unread_count'           => 0,
			'last_message'           => '尚無對話',
			'last_message_time'      => $this->message_query_service->formatMessageTime( $group->created_at ),
			'last_message_timestamp' => strtotime( $group->created_at ),
		);

		// 查詢最近 2 個月的訊息表.
		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $table_messages ) ) {
				continue;
			}

			// 查詢未讀數.
			$unread_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$table_messages}
					WHERE group_id = %s
					AND sender_type = 'USER'
					AND CONCAT(sent_date, ' ', sent_time) > %s",
					$group_id,
					$read_time
				)
			);

			$stat['unread_count'] += (int) $unread_count;

			// 查詢最後一則訊息.
			$latest_message = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT message_content, message_type, CONCAT(sent_date, ' ', sent_time) as full_time
					FROM {$table_messages}
					WHERE group_id = %s
					ORDER BY sent_date DESC, sent_time DESC
					LIMIT 1",
					$group_id
				)
			);

			if ( $latest_message ) {
				$current_time = strtotime( $latest_message->full_time );
				if ( $current_time > $stat['last_message_timestamp'] ) {
					$stat['last_message']           = $this->formatLastMessage( $latest_message->message_content, $latest_message->message_type );
					$stat['last_message_time']      = $this->message_query_service->formatMessageTime( $latest_message->full_time );
					$stat['last_message_timestamp'] = $current_time;
				}
			}
		}

		// 只有有訊息的群組才返回.
		if ( $stat['last_message'] !== '尚無對話' ) {
			return $stat;
		}

		return null;
	}
}
