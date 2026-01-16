<?php
/**
 * LINE 官方帳號好友同步 AJAX 處理器
 *
 * 處理 LINE 官方帳號好友匯入的 AJAX 請求
 *
 * @package OrderChatz\Ajax
 * @since 1.0.0
 */

namespace OrderChatz\Ajax;

use Exception;

class LineFriendsSyncHandler {

	public function __construct() {
		add_action( 'wp_ajax_otz_get_line_friends', array( $this, 'get_line_friends' ) );
		add_action( 'wp_ajax_otz_get_line_friends_batch', array( $this, 'get_line_friends_batch' ) );
		add_action( 'wp_ajax_otz_sync_line_friends', array( $this, 'sync_line_friends' ) );
	}

	/**
	 * 取得 LINE 官方帳號好友 ID 清單（第一步：只取得 ID，不取得個人資料）
	 */
	public function get_line_friends() {
		try {
			check_ajax_referer( 'otz_get_line_friends' );

			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( __( '權限不足', 'otz' ) );
			}

			$friends_data = $this->fetchAllLineFriendsIds();

			wp_send_json_success(
				array(
					'message'        => __( '成功取得好友 ID 清單', 'otz' ),
					'friend_ids'     => $friends_data['friend_ids'],
					'existing_count' => $friends_data['existing_count'],
					'total'          => count( $friends_data['friend_ids'] ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 分批取得好友詳細資料（第二步：分批取得個人資料）
	 */
	public function get_line_friends_batch() {
		try {
			check_ajax_referer( 'otz_get_line_friends_batch' );

			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( __( '權限不足', 'otz' ) );
			}

			// 取得當前批次的好友 ID 列表(由前端切片後傳送,避免超出 max_input_vars).
			$batch_ids = ( isset( $_POST['batch_ids'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['batch_ids'] ) ) : array();
			$offset    = ( isset( $_POST['offset'] ) ) ? intval( wp_unslash( $_POST['offset'] ) ) : 0;
			$total     = ( isset( $_POST['total'] ) ) ? intval( wp_unslash( $_POST['total'] ) ) : 0;

			if ( empty( $batch_ids ) || ! is_array( $batch_ids ) ) {
				throw new Exception( __( '好友 ID 清單為空', 'otz' ) );
			}

			$batch_friends = $this->fetchFriendsBatch( $batch_ids );

			wp_send_json_success(
				array(
					'message'   => __( '成功取得好友資料', 'otz' ),
					'friends'   => $batch_friends['friends'],
					'processed' => $batch_friends['processed'],
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 同步 LINE 官方帳號好友到 otz_users 資料表
	 */
	public function sync_line_friends() {
		try {
			check_ajax_referer( 'otz_sync_line_friends' );

			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( __( '權限不足', 'otz' ) );
			}

			// 取得選擇的好友 ID 清單
			$selected_friends = $_POST['selected_friends'] ?? array();
			if ( empty( $selected_friends ) || ! is_array( $selected_friends ) ) {
				throw new Exception( __( '請選擇要匯入的好友', 'otz' ) );
			}

			$result = $this->importSelectedLineFriends( $selected_friends );

			wp_send_json_success(
				array(
					'message'  => __( 'LINE 好友同步完成', 'otz' ),
					'imported' => $result['imported'],
					'skipped'  => $result['skipped'],
					'total'    => $result['total'],
					'details'  => $result['details'],
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 取得所有 LINE 好友 ID（快速取得，不含詳細資訊）
	 *
	 * @return array 好友 ID 清單和統計
	 */
	private function fetchAllLineFriendsIds() {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'otz_users';
		$all_user_ids = array();

		// 取得 ACCESS TOKEN
		$access_token = get_option( 'otz_access_token' );
		if ( empty( $access_token ) ) {
			throw new Exception( __( 'LINE ACCESS TOKEN 未設定，請先到設定頁面配置', 'otz' ) );
		}

		// 分頁取得所有好友 ID
		$start = null;
		do {
			$friends_data = $this->fetchLineFriends( $access_token, $start, 1000 );

			if ( ! empty( $friends_data['userIds'] ) ) {
				$all_user_ids = array_merge( $all_user_ids, $friends_data['userIds'] );
			}

			$start = $friends_data['next'] ?? null;
		} while ( ! empty( $start ) );

		if ( empty( $all_user_ids ) ) {
			return array(
				'friend_ids'     => array(),
				'existing_count' => 0,
			);
		}

		// 檢查哪些好友已存在於資料庫（不取得詳細資料）
		$existing_line_ids = array();
		$placeholders      = implode( ',', array_fill( 0, count( $all_user_ids ), '%s' ) );
		$existing_query    = $wpdb->prepare(
			"SELECT line_user_id FROM {$table_name} WHERE line_user_id IN ($placeholders) AND line_user_id IS NOT NULL",
			$all_user_ids
		);
		$existing_results  = $wpdb->get_results( $existing_query );

		foreach ( $existing_results as $row ) {
			$existing_line_ids[] = $row->line_user_id;
		}

		return array(
			'friend_ids'     => $all_user_ids,
			'existing_count' => count( $existing_line_ids ),
			'existing_ids'   => $existing_line_ids,
		);
	}

	/**
	 * 分批取得好友詳細資料（避免大量 API 請求）
	 *
	 * @param array $batch_ids 當前批次的好友 ID 清單(已由前端切片).
	 * @return array 批次好友資料
	 */
	private function fetchFriendsBatch( $batch_ids ) {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'otz_users';
		$access_token = get_option( 'otz_access_token' );

		if ( empty( $access_token ) ) {
			throw new Exception( __( 'LINE ACCESS TOKEN 未設定，請先到設定頁面配置', 'otz' ) );
		}

		$friends   = array();
		$processed = 0;

		// 檢查哪些好友已存在於資料庫.
		if ( ! empty( $batch_ids ) ) {
			$placeholders     = implode( ',', array_fill( 0, count( $batch_ids ), '%s' ) );
			$existing_query   = $wpdb->prepare(
				"SELECT line_user_id FROM {$table_name} WHERE line_user_id IN ($placeholders) AND line_user_id IS NOT NULL",
				$batch_ids
			);
			$existing_results = $wpdb->get_results( $existing_query );

			$existing_line_ids = array();
			foreach ( $existing_results as $row ) {
				$existing_line_ids[] = $row->line_user_id;
			}

			// 為這一批好友取得詳細資料（遵守 API 頻率限制）.
			foreach ( $batch_ids as $line_user_id ) {
				$profile = $this->getLineUserProfile( $access_token, $line_user_id );

				$friends[] = array(
					'line_user_id' => $line_user_id,
					'display_name' => $profile['displayName'] ?? 'LINE User',
					'avatar_url'   => $profile['pictureUrl'] ?? '',
					'exists_in_db' => in_array( $line_user_id, $existing_line_ids ),
				);

				$processed++;

				// 添加延遲以避免觸發 API 頻率限制（LINE 建議）.
				if ( $processed % 5 === 0 ) {
					usleep( 100000 ); // 100ms 延遲.
				}
			}
		}

		return array(
			'friends'   => $friends,
			'processed' => $processed,
		);
	}

	/**
	 * 取得所有 LINE 好友資料（包含詳細資訊，供選擇用）- 已棄用
	 *
	 * @return array 好友資料和統計
	 * @deprecated 由於 API 頻率限制問題，改用分批載入方式
	 */
	private function fetchAllLineFriends() {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'otz_users';
		$all_user_ids = array();

		// 取得 ACCESS TOKEN
		$access_token = get_option( 'otz_access_token' );
		if ( empty( $access_token ) ) {
			throw new Exception( __( 'LINE ACCESS TOKEN 未設定，請先到設定頁面配置', 'otz' ) );
		}

		// 分頁取得所有好友 ID
		$start = null;
		do {
			$friends_data = $this->fetchLineFriends( $access_token, $start, 1000 );

			if ( ! empty( $friends_data['userIds'] ) ) {
				$all_user_ids = array_merge( $all_user_ids, $friends_data['userIds'] );
			}

			$start = $friends_data['next'] ?? null;
		} while ( ! empty( $start ) );

		if ( empty( $all_user_ids ) ) {
			return array(
				'friends'        => array(),
				'existing_count' => 0,
			);
		}

		// 檢查哪些好友已存在於資料庫
		$existing_line_ids = array();
		$placeholders      = implode( ',', array_fill( 0, count( $all_user_ids ), '%s' ) );
		$existing_query    = $wpdb->prepare(
			"SELECT line_user_id FROM {$table_name} WHERE line_user_id IN ($placeholders) AND line_user_id IS NOT NULL",
			$all_user_ids
		);
		$existing_results  = $wpdb->get_results( $existing_query );

		foreach ( $existing_results as $row ) {
			$existing_line_ids[] = $row->line_user_id;
		}

		// 取得所有好友的詳細資料
		$friends = array();
		foreach ( $all_user_ids as $line_user_id ) {
			$profile = $this->getLineUserProfile( $access_token, $line_user_id );

			$friends[] = array(
				'line_user_id' => $line_user_id,
				'display_name' => $profile['displayName'] ?? 'LINE User',
				'avatar_url'   => $profile['pictureUrl'] ?? '',
				'exists_in_db' => in_array( $line_user_id, $existing_line_ids ),
			);
		}

		return array(
			'friends'        => $friends,
			'existing_count' => count( $existing_line_ids ),
		);
	}

	/**
	 * 匯入選擇的 LINE 好友到 otz_users 資料表
	 *
	 * @param array $selected_friends 選擇的好友資料陣列
	 * @return array 匯入結果統計
	 */
	private function importSelectedLineFriends( $selected_friends ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'otz_users';
		$imported   = 0;
		$skipped    = 0;
		$details    = array();

		foreach ( $selected_friends as $friend_data ) {
			$line_user_id = $friend_data['line_user_id'] ?? '';
			$display_name = $friend_data['display_name'] ?? 'LINE User';
			$avatar_url   = $friend_data['avatar_url'] ?? '';

			if ( empty( $line_user_id ) ) {
				continue;
			}

			// 檢查是否已存在
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE line_user_id = %s",
					$line_user_id
				)
			);

			if ( $existing ) {
				$skipped++;
			/* translators: %s: 好友顯示名稱. */
				$details[] = sprintf(
					__( '跳過好友：%s (已存在)', 'otz' ),
					$display_name
				);
				continue;
			}

			// 寫入資料庫
			$result = $wpdb->insert(
				$table_name,
				array(
					'line_user_id' => $line_user_id,
					'wp_user_id'   => null,
					'display_name' => $display_name,
					'avatar_url'   => $avatar_url,
					'source_type'  => 'user',
					'followed_at'  => current_time( 'mysql' ),
					'status'       => 'active',
					'last_active'  => current_time( 'mysql' ),
				),
				array(
					'%s', // line_user_id
					'%d', // wp_user_id
					'%s', // display_name
					'%s', // avatar_url
					'%s', // source_type
					'%s', // followed_at
					'%s', // status
					'%s',  // last_active
				)
			);

			if ( $result !== false ) {
				$imported++;
			/* translators: %s: 好友顯示名稱. */
				$details[] = sprintf(
					__( '匯入好友：%s', 'otz' ),
					$display_name
				);
			} else {
			/* translators: 1: 好友顯示名稱, 2: 錯誤訊息. */
				$details[] = sprintf(
					__( '匯入失敗：%1$s - 錯誤：%2$s', 'otz' ),
					$display_name,
					$wpdb->last_error
				);
			}
		}

		return array(
			'total'    => count( $selected_friends ),
			'imported' => $imported,
			'skipped'  => $skipped,
			'details'  => $details,
		);
	}

	/**
	 * 匯入 LINE 好友資料到 otz_users 資料表（舊方法，保留以防需要）
	 *
	 * @return array 匯入結果統計
	 */
	private function importLineFriends() {
		global $wpdb;

		$table_name   = $wpdb->prefix . 'otz_users';
		$imported     = 0;
		$skipped      = 0;
		$details      = array();
		$all_user_ids = array();

		// 取得 ACCESS TOKEN
		$access_token = get_option( 'otz_access_token' );
		if ( empty( $access_token ) ) {
			throw new Exception( __( 'LINE ACCESS TOKEN 未設定，請先到設定頁面配置', 'otz' ) );
		}

		// 分頁取得所有好友 ID
		$start = null;
		do {
			$friends_data = $this->fetchLineFriends( $access_token, $start, 1000 );

			if ( ! empty( $friends_data['userIds'] ) ) {
				$all_user_ids = array_merge( $all_user_ids, $friends_data['userIds'] );
			}

			$start = $friends_data['next'] ?? null;
		} while ( ! empty( $start ) );

		if ( empty( $all_user_ids ) ) {
			return array(
				'total'    => 0,
				'imported' => 0,
				'skipped'  => 0,
				'details'  => array( __( '未找到任何 LINE 好友', 'otz' ) ),
			);
		}
		/* translators: %d: 從 LINE API 取得的好友數量. */

		$details[] = sprintf( __( '從 LINE API 取得 %d 個好友 ID', 'otz' ), count( $all_user_ids ) );

		// 檢查哪些 LINE User ID 已存在於資料庫
		$existing_line_ids = array();
		if ( ! empty( $all_user_ids ) ) {
			$placeholders     = implode( ',', array_fill( 0, count( $all_user_ids ), '%s' ) );
			$existing_query   = $wpdb->prepare(
				"SELECT line_user_id FROM {$table_name} WHERE line_user_id IN ($placeholders) AND line_user_id IS NOT NULL",
				$all_user_ids
			);
			$existing_results = $wpdb->get_results( $existing_query );

			foreach ( $existing_results as $row ) {
				$existing_line_ids[] = $row->line_user_id;
			}
		}

		/* translators: %d: 資料庫中已存在的好友數量. */
		$details[] = sprintf( __( '資料庫中已存在 %d 個好友', 'otz' ), count( $existing_line_ids ) );

		// 處理每個新的好友
		foreach ( $all_user_ids as $line_user_id ) {
			if ( in_array( $line_user_id, $existing_line_ids ) ) {
				$skipped++;
				continue;
			}

			// 取得好友詳細資料
			$profile = $this->getLineUserProfile( $access_token, $line_user_id );

			if ( $profile ) {
				// 寫入資料庫
				$result = $wpdb->insert(
					$table_name,
					array(
						'line_user_id' => $line_user_id,
						'wp_user_id'   => null,
						'display_name' => $profile['displayName'] ?? 'LINE User',
						'avatar_url'   => $profile['pictureUrl'] ?? '',
						'source_type'  => 'user',
						'followed_at'  => current_time( 'mysql' ),
						'status'       => 'active',
						'last_active'  => current_time( 'mysql' ),
					),
					array(
						'%s', // line_user_id
						'%d', // wp_user_id
						'%s', // display_name
						'%s', // avatar_url
						'%s', // source_type
						'%s', // followed_at
						'%s', // status
						'%s',  // last_active
					)
				);

				if ( $result !== false ) {
					$imported++;
					$details[] = sprintf(
				/* translators: 1: 好友顯示名稱, 2: LINE ID. */
						__( '匯入好友：%1$s (LINE ID: %2$s)', 'otz' ),
						$profile['displayName'] ?? 'LINE User',
						$line_user_id
					);
				} else {
				/* translators: 1: LINE User ID, 2: 錯誤訊息. */
					$details[] = sprintf(
						__( '匯入失敗：%1$s - 錯誤：%2$s', 'otz' ),
						$line_user_id,
						$wpdb->last_error
					);
				}
			} else {


			}
		}

		return array(
			'total'    => count( $all_user_ids ),
			'imported' => $imported,
			'skipped'  => $skipped,
			'details'  => $details,
		);
	}

	/**
	 * 從 LINE API 取得好友 ID 列表
	 *
	 * @param string      $access_token ACCESS TOKEN
	 * @param string|null $start 分頁開始位置
	 * @param int         $limit 每頁限制數量
	 * @return array API 回應資料
	 */
	private function fetchLineFriends( $access_token, $start = null, $limit = 1000 ) {
		$url = 'https://api.line.me/v2/bot/followers/ids';

		$query_params = array( 'limit' => $limit );
		if ( ! empty( $start ) ) {
			$query_params['start'] = $start;
		}

		$url .= '?' . http_build_query( $query_params );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'LINE API 請求失敗：', 'otz' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code === 403 ) {
			throw new Exception( __( 'LINE 官方帳號未驗證，無法存取好友列表。請確認您的帳號已通過驗證。', 'otz' ) );
		}

		if ( $status_code === 400 ) {
			throw new Exception( __( 'API 請求參數錯誤：', 'otz' ) . ( $data['message'] ?? 'Unknown error' ) );
		}

		if ( $status_code !== 200 ) {
			/* translators: 1: HTTP 狀態碼, 2: 錯誤訊息. */
			throw new Exception(
				sprintf(
					__( 'LINE API 回應錯誤 (狀態碼: %1$d)：%2$s', 'otz' ),
					$status_code,
					$data['message'] ?? 'Unknown error'
				)
			);
		}

		return $data;
	}

	/**
	 * 取得 LINE 使用者詳細資料
	 *
	 * @param string $access_token ACCESS TOKEN
	 * @param string $line_user_id LINE User ID
	 * @return array|null 使用者資料
	 */
	public function getLineUserProfile( $access_token, $line_user_id ) {
		$url = 'https://api.line.me/v2/bot/profile/' . $line_user_id;

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}
}
