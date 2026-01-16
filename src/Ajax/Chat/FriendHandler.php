<?php
/**
 * 好友處理 AJAX 處理器
 *
 * 處理好友列表相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Chat
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Chat;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;

class FriendHandler extends AbstractAjaxHandler {

	/**
	 * 表存在性快取
	 *
	 * @var array
	 */
	private array $table_exists_cache = array();

	public function __construct() {
		add_action( 'wp_ajax_otz_get_friends', array( $this, 'getFriends' ) );
		add_action( 'wp_ajax_otz_load_more_friends', array( $this, 'loadMoreFriends' ) );
		add_action( 'wp_ajax_otz_mark_group_messages_read', array( $this, 'markGroupMessagesRead' ) );
		// 註意：otz_search_users 和 otz_update_user_binding 由 Friends.php 統一處理.
	}

	/**
	 * 取得好友列表
	 */
	public function getFriends() {
		try {
			$this->verifyNonce();

			// 效能監控開始.
			$start_time = microtime( true );

			$page     = intval( $_POST['page'] ?? 1 );
			$per_page = intval( $_POST['per_page'] ?? 20 );
			$search   = sanitize_text_field( $_POST['search'] ?? '' );

			$friends = $this->queryFriends( $page, $per_page, $search );

			// 效能監控結束.
			$execution_time = microtime( true ) - $start_time;

			// 記錄慢查詢.
			if ( $execution_time > 2.0 ) {
				$this->logError(
					sprintf( '好友列表查詢過慢: %.2f 秒', $execution_time ),
					array(
						'page'     => $page,
						'per_page' => $per_page,
						'search'   => $search,
					)
				);
			}

			$this->sendSuccess(
				array(
					'friends'  => $friends,
					'has_more' => count( $friends ) === $per_page,
					'_debug'   => array(
						'execution_time' => round( $execution_time, 3 ),
						'friend_count'   => count( $friends ),
					),
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in getFriends: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 載入更多好友
	 */
	public function loadMoreFriends() {
		try {
			$this->verifyNonce();

			// 效能監控開始.
			$start_time = microtime( true );

			$page     = intval( $_POST['page'] ?? 1 );
			$per_page = intval( $_POST['per_page'] ?? 20 );
			$search   = sanitize_text_field( $_POST['search'] ?? '' );

			$friends = $this->queryFriends( $page, $per_page, $search );

			// 效能監控結束.
			$execution_time = microtime( true ) - $start_time;

			// 記錄慢查詢.
			if ( $execution_time > 2.0 ) {
				$this->logError(
					sprintf( '載入更多好友查詢過慢: %.2f 秒', $execution_time ),
					array(
						'page'     => $page,
						'per_page' => $per_page,
						'search'   => $search,
					)
				);
			}

			$this->sendSuccess(
				array(
					'friends'  => $friends,
					'has_more' => count( $friends ) === $per_page,
					'_debug'   => array(
						'execution_time' => round( $execution_time, 3 ),
						'friend_count'   => count( $friends ),
					),
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 查詢好友資料（兩階段載入：個人好友 + 群組）
	 */
	private function queryFriends( $page = 1, $per_page = 20, $search = '' ) {
		$offset = ( $page - 1 ) * $per_page;

		// 兩階段載入：先載入有訊息的好友，再載入沒有訊息的好友.
		$friends_with_messages = $this->queryFriendsWithMessages( $per_page, $offset, $search );
		$loaded_count          = count( $friends_with_messages );

		$friends = $friends_with_messages;

		// 如果第一階段沒有載滿，則載入沒有訊息的好友.
		if ( $loaded_count < $per_page ) {
			$remaining_count    = $per_page - $loaded_count;
			$used_line_user_ids = array_column( $friends_with_messages, 'line_user_id' );

			$friends_without_messages = $this->queryFriendsWithoutMessages(
				$remaining_count,
				$offset,
				$search,
				$used_line_user_ids
			);

			$friends = array_merge( $friends, $friends_without_messages );
		}

		// 查詢群組資料
		$groups = $this->queryGroups( $search );

		// 合併個人好友和群組，並按照最後活動時間排序.
		$all_items = array_merge( $friends, $groups );

		usort(
			$all_items,
			function( $a, $b ) {
				// 1. 未讀數優先.
				if ( $a['unread_count'] > 0 && $b['unread_count'] == 0 ) {
					return -1;
				}
				if ( $a['unread_count'] == 0 && $b['unread_count'] > 0 ) {
					return 1;
				}

				// 2. 最後活動時間戳比較.
				$a_timestamp = is_numeric( $a['last_message_timestamp'] )
					? intval( $a['last_message_timestamp'] )
					: strtotime( $a['last_message_timestamp'] );
				$b_timestamp = is_numeric( $b['last_message_timestamp'] )
					? intval( $b['last_message_timestamp'] )
					: strtotime( $b['last_message_timestamp'] );

				return $b_timestamp - $a_timestamp; // 最新的在前面.
			}
		);

		// 應用分頁限制（因為已經加入群組資料）.
		return array_slice( $all_items, 0, $per_page );
	}

	/**
	 * 第一階段：查詢有活動記錄的好友
	 */
	private function queryFriendsWithMessages( $limit, $offset, $search = '' ) {
		global $wpdb;

		$table_users = $wpdb->prefix . 'otz_users';

		// 搜尋條件
		$where_conditions = array(
			'line_user_id IS NOT NULL',
			"line_user_id != ''",
			'last_active IS NOT NULL',
			"last_active != '0000-00-00 00:00:00'",
		);
		$params           = array();

		if ( ! empty( $search ) ) {
			$where_conditions[] = 'display_name LIKE %s';
			$params[]           = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

		$sql = "SELECT id,
		               line_user_id,
		               display_name,
		               avatar_url,
		               followed_at,
		               last_active,
		               read_time
		        FROM {$table_users}
		        {$where_clause}
		        ORDER BY last_active DESC
		        LIMIT %d OFFSET %d";

		$params[] = $limit;
		$params[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		// 批量查詢所有好友的統計資訊（優化 N+1 查詢問題）.
		$stats_batch = $this->calculate_batch_stats( $results );

		$friends = array();
		foreach ( $results as $row ) {
			// 從批量結果中取得該好友的統計資訊.
			$stats = $stats_batch[ $row->line_user_id ] ?? null;

			$friend_data = array(
				'id'                     => $row->id,
				'line_user_id'           => $row->line_user_id,
				'name'                   => $row->display_name,
				'avatar'                 => $row->avatar_url ?: $this->getDefaultAvatar(),
				'last_message'           => $stats ? $stats['last_message'] : '尚無對話',
				'last_message_time'      => $stats ? $stats['last_message_time'] : $this->formatMessageTime( $row->last_active ),
				'unread_count'           => $stats ? $stats['unread_count'] : 0,
				'followed_at'            => $row->followed_at,
				'last_active'            => $row->last_active,
				'read_time'              => $row->read_time,
				'last_message_timestamp' => $stats && isset( $stats['last_message_timestamp'] )
					? $stats['last_message_timestamp']
					: strtotime( $row->last_active ),
			);

			$friends[] = $friend_data;
		}

		// 排序：未讀數優先，然後按最後活動時間
		usort(
			$friends,
			function( $a, $b ) {
				// 1. 未讀數優先
				if ( $a['unread_count'] > 0 && $b['unread_count'] == 0 ) {
					return -1;
				}
				if ( $a['unread_count'] == 0 && $b['unread_count'] > 0 ) {
					return 1;
				}

				// 2. 最後活動時間戳比較
				$a_timestamp = is_numeric( $a['last_message_timestamp'] )
				? intval( $a['last_message_timestamp'] )
				: strtotime( $a['last_message_timestamp'] );
				$b_timestamp = is_numeric( $b['last_message_timestamp'] )
				? intval( $b['last_message_timestamp'] )
				: strtotime( $b['last_message_timestamp'] );

				return $b_timestamp - $a_timestamp; // 最新的在前面
			}
		);

		return $friends;
	}

	/**
	 * 第二階段：查詢沒有訊息記錄的好友
	 */
	private function queryFriendsWithoutMessages( $limit, $offset, $search = '', $exclude_line_user_ids = array() ) {
		global $wpdb;

		$table_users = $wpdb->prefix . 'otz_users';

		$where_conditions = array();
		$where_values     = array();

		// 基本條件
		$where_conditions[] = "line_user_id IS NOT NULL AND line_user_id != ''";

		// 搜尋條件
		if ( ! empty( $search ) ) {
			$where_conditions[] = '(display_name LIKE %s)';
			$where_values[]     = '%' . $wpdb->esc_like( $search ) . '%';
		}

		// 排除已載入的好友
		if ( ! empty( $exclude_line_user_ids ) ) {
			$placeholders       = implode( ',', array_fill( 0, count( $exclude_line_user_ids ), '%s' ) );
			$where_conditions[] = "line_user_id NOT IN ({$placeholders})";
			$where_values       = array_merge( $where_values, $exclude_line_user_ids );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

		$sql = "SELECT id,
		               line_user_id,
		               display_name,
		               avatar_url,
		               followed_at,
		               last_active,
		               read_time
		        FROM {$table_users}
		        {$where_clause}
		        ORDER BY followed_at DESC
		        LIMIT %d OFFSET %d";

		$params  = array_merge( $where_values, array( $limit, $offset ) );
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$friends = array();
		foreach ( $results as $row ) {
			$friend_data = array(
				'id'                     => $row->id,
				'line_user_id'           => $row->line_user_id,
				'name'                   => $row->display_name,
				'avatar'                 => $row->avatar_url ?: $this->getDefaultAvatar(),
				'last_message'           => '尚無對話',
				'last_message_time'      => $this->formatMessageTime( $row->followed_at ),
				'unread_count'           => 0,
				'followed_at'            => $row->followed_at,
				'last_active'            => $row->last_active,
				'read_time'              => $row->read_time,
				'last_message_timestamp' => strtotime( $row->followed_at ),
			);

			$friends[] = $friend_data;
		}

		return $friends;
	}

	/**
	 * 計算好友統計資訊（單一查詢，已棄用）
	 *
	 * @deprecated 使用 calculate_batch_stats() 批量查詢以提升效能.
	 * @param string $line_user_id LINE 使用者 ID.
	 * @return array|null 統計資訊陣列.
	 */
	private function calculateFriendStats( $line_user_id ) {
		global $wpdb;

		// 記錄 deprecated 警告.
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				'calculateFriendStats 被呼叫（建議使用批量查詢 calculate_batch_stats）',
				array(
					'source'       => 'order-chatz',
					'line_user_id' => $line_user_id,
				)
			);
		}

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

		// 即使沒有訊息，也返回基本好友資訊
		if ( empty( $last_message ) ) {
			// 從 users 表取得好友的基本資訊
			global $wpdb;
			$user_info = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT display_name, followed_at FROM {$wpdb->prefix}otz_users WHERE line_user_id = %s",
					$line_user_id
				)
			);

			if ( $user_info ) {
				return array(
					'line_user_id'           => $line_user_id,
					'unread_count'           => 0,
					'last_message'           => '尚無對話',
					'last_message_time'      => $this->formatMessageTime( $user_info->followed_at ),
					'last_message_timestamp' => strtotime( $user_info->followed_at ),
				);
			} else {
				return null;
			}
		}

		$result = array(
			'line_user_id'           => $line_user_id,
			'unread_count'           => min( $unread_count, 99 ),
			'last_message'           => $last_message,
			'last_message_time'      => $this->formatMessageTime( $last_message_time ),
			'last_message_timestamp' => strtotime( $last_message_time ),
		);

		return $result;
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
	 * @param array $user_rows 用戶資料陣列（包含 line_user_id 和 read_time）.
	 * @return array 以 line_user_id 為 key 的統計陣列.
	 */
	private function calculate_batch_stats( $user_rows ) {
		global $wpdb;

		if ( empty( $user_rows ) ) {
			return array();
		}

		$stats = array();

		// 建立 line_user_id => read_time 對應.
		$read_times = array();
		foreach ( $user_rows as $row ) {
			$line_user_id                = $row->line_user_id;
			$stats[ $line_user_id ]      = array(
				'line_user_id'           => $line_user_id,
				'unread_count'           => 0,
				'last_message'           => '尚無對話',
				'last_message_time'      => $this->formatMessageTime( $row->followed_at ),
				'last_message_timestamp' => strtotime( $row->followed_at ),
			);
			$read_times[ $line_user_id ] = $row->read_time ?: date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) );
		}

		$line_user_ids = array_keys( $stats );

		// 查詢最近 2 個月的訊息表.
		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $table_messages ) ) {
				continue;
			}

			// 批量查詢未讀數.
			$placeholders = implode( ',', array_fill( 0, count( $line_user_ids ), '%s' ) );

			// 建立 CASE WHEN 語句來匹配每個用戶的 read_time.
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
				$stats[ $result->line_user_id ]['unread_count'] += intval( $result->unread_count );
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

			$latest_sql     = implode( ' UNION ALL ', $subqueries );
			$latest_results = $wpdb->get_results( $latest_sql );

			foreach ( $latest_results as $latest ) {
				$line_user_id = $latest->line_user_id;
				$current_time = strtotime( $latest->full_time );
				$stored_time  = $stats[ $line_user_id ]['last_message_timestamp'];

				// 只保留最新的訊息.
				if ( $current_time > $stored_time ) {
					$stats[ $line_user_id ]['last_message']           = $this->formatLastMessage( $latest->message_content, $latest->message_type );
					$stats[ $line_user_id ]['last_message_time']      = $this->formatMessageTime( $latest->full_time );
					$stats[ $line_user_id ]['last_message_timestamp'] = $current_time;
				}
			}
		}

		// 限制未讀數最大值為 99.
		foreach ( $stats as $line_user_id => $stat ) {
			$stats[ $line_user_id ]['unread_count'] = min( $stat['unread_count'], 99 );
		}

		return $stats;
	}

	/**
	 * 查詢群組資料（含訊息統計）
	 *
	 * @param string $search 搜尋關鍵字.
	 * @return array 群組資料陣列.
	 */
	private function queryGroups( $search = '' ) {
		global $wpdb;

		$table_groups = $wpdb->prefix . 'otz_groups';

		// 搜尋條件.
		$where_conditions = array(
			"group_id IS NOT NULL AND group_id != ''",
		);
		$params           = array();

		if ( ! empty( $search ) ) {
			$where_conditions[] = 'group_name LIKE %s';
			$params[]           = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

		$sql = "SELECT id,
		               group_id,
		               group_name,
		               group_avatar,
		               source_type,
		               member_count,
		               last_message_time,
		               created_at
		        FROM {$table_groups}
		        {$where_clause}
		        ORDER BY last_message_time DESC";

		if ( ! empty( $params ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

		// 批量計算群組統計資訊.
		$stats_batch = $this->calculate_group_batch_stats( $results );

		$groups = array();
		foreach ( $results as $row ) {
			$stats = $stats_batch[ $row->group_id ] ?? null;

			$group_data = array(
				'id'                     => $row->id,
				'group_id'               => $row->group_id,
				'line_user_id'           => $row->group_id,
				'source_type'            => $row->source_type,
				'name'                   => $row->group_name ?: '未命名群組',
				'avatar'                 => $row->group_avatar ?: $this->getDefaultAvatar(),
				'last_message'           => $stats ? $stats['last_message'] : '尚無對話',
				'last_message_time'      => $stats ? $stats['last_message_time'] : $this->formatMessageTime( $row->last_message_time ),
				'unread_count'           => $stats ? $stats['unread_count'] : 0,
				'member_count'           => $row->member_count,
				'created_at'             => $row->created_at,
				'last_message_timestamp' => $stats && isset( $stats['last_message_timestamp'] )
					? $stats['last_message_timestamp']
					: strtotime( $row->last_message_time ?: $row->created_at ),
			);

			$groups[] = $group_data;
		}

		return $groups;
	}

	/**
	 * 批量計算群組統計資訊
	 *
	 * @param array $group_rows 群組資料陣列.
	 * @return array 以 group_id 為 key 的統計陣列.
	 */
	private function calculate_group_batch_stats( $group_rows ) {
		global $wpdb;

		if ( empty( $group_rows ) ) {
			return array();
		}

		$stats = array();
		$read_times = array();

		// 建立 group_id => 預設統計資料對應.
		foreach ( $group_rows as $row ) {
			$group_id           = $row->group_id;
			$stats[ $group_id ] = array(
				'group_id'               => $group_id,
				'unread_count'           => 0,
				'last_message'           => '尚無對話',
				'last_message_time'      => $this->formatMessageTime( $row->last_message_time ?: $row->created_at ),
				'last_message_timestamp' => strtotime( $row->last_message_time ?: $row->created_at ),
			);

			// 建立 read_time 對應（如果沒有則使用 3 小時前作為預設值）.
			$read_times[ $group_id ] = $row->read_time ?: date( 'Y-m-d H:i:s', strtotime( '-3 hours' ) );
		}

		$group_ids = array_keys( $stats );

		// 查詢最近 2 個月的訊息表.
		for ( $i = 0; $i < 2; $i++ ) {
			$search_month   = date( 'Y_m', strtotime( "-{$i} month" ) );
			$table_messages = $wpdb->prefix . 'otz_messages_' . $search_month;

			if ( ! $this->table_exists( $table_messages ) ) {
				continue;
			}

			// 批量查詢未讀數.
			$placeholders = implode( ', ', array_fill( 0, count( $group_ids ), '%s' ) );

			$case_when_parts = array();
			$params          = array();

			// 先加入 IN 子句的參數.
			foreach ( $group_ids as $group_id ) {
				$params[] = $group_id;
			}

			// 再加入 CASE WHEN 的參數.
			foreach ( $group_ids as $group_id ) {
				$case_when_parts[] = 'WHEN %s THEN %s';
				$params[]          = $group_id;
				$params[]          = $read_times[ $group_id ];
			}
			$case_when = implode( ' ', $case_when_parts );

			$unread_sql = $wpdb->prepare(
				"SELECT
					group_id,
					COUNT(*) as unread_count
				FROM {$table_messages}
				WHERE group_id IN ({$placeholders})
				AND sender_type = 'USER'
				AND CONCAT(sent_date, ' ', sent_time) > CASE group_id {$case_when} END
				GROUP BY group_id",
				...$params
			);

			$unread_results = $wpdb->get_results( $unread_sql );

			foreach ( $unread_results as $unread ) {
				$group_id                        = $unread->group_id;
				$stats[ $group_id ]['unread_count'] += (int) $unread->unread_count;
			}

			// 批量查詢最後訊息.
			$subqueries = array();
			foreach ( $group_ids as $group_id ) {
				$subqueries[] = $wpdb->prepare(
					"(SELECT group_id, message_content, message_type, CONCAT(sent_date, ' ', sent_time) as full_time
					FROM {$table_messages}
					WHERE group_id = %s
					ORDER BY sent_date DESC, sent_time DESC
					LIMIT 1)",
					$group_id
				);
			}

			$latest_sql     = implode( ' UNION ALL ', $subqueries );
			$latest_results = $wpdb->get_results( $latest_sql );

			foreach ( $latest_results as $latest ) {
				$group_id     = $latest->group_id;
				$current_time = strtotime( $latest->full_time );
				$stored_time  = $stats[ $group_id ]['last_message_timestamp'];

				// 只保留最新的訊息.
				if ( $current_time > $stored_time ) {
					$stats[ $group_id ]['last_message']           = $this->formatLastMessage( $latest->message_content, $latest->message_type );
					$stats[ $group_id ]['last_message_time']      = $this->formatMessageTime( $latest->full_time );
					$stats[ $group_id ]['last_message_timestamp'] = $current_time;
				}
			}
		}

		return $stats;
	}

	/**
	 * AJAX handler: 標記群組訊息為已讀
	 *
	 * @return void
	 */
	public function markGroupMessagesRead() {
		$this->verifyNonce();

		$group_id = ( isset( $_POST['group_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '';

		if ( empty( $group_id ) ) {
			wp_send_json_error( array( 'message' => '群組 ID 不能為空' ) );
		}

		$result = $this->update_group_read_time( $group_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * 更新群組的已讀時間
	 *
	 * @param string $group_id 群組 ID.
	 * @return array 更新結果.
	 */
	private function update_group_read_time( $group_id ) {
		global $wpdb;

		if ( empty( $group_id ) ) {
			return array(
				'success' => false,
				'message' => '群組 ID 不能為空',
			);
		}

		$table_name = $wpdb->prefix . 'otz_groups';
		$result     = $wpdb->update(
			$table_name,
			array( 'read_time' => current_time( 'mysql' ) ),
			array( 'group_id' => $group_id ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => '更新已讀時間失敗',
			);
		}

		return array(
			'success' => true,
			'message' => '已讀時間已更新',
		);
	}
}
