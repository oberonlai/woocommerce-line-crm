<?php
/**
 * 標籤處理 AJAX 處理器
 *
 * 處理客戶標籤相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Customer
 * @since 1.1.4
 */

namespace OrderChatz\Ajax\Customer;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;
use OrderChatz\Database\Tag as TagDatabase;
use OrderChatz\Util\Logger;

class Tag extends AbstractAjaxHandler {

	/**
	 * Tag database instance
	 *
	 * @var TagDatabase
	 */
	private TagDatabase $tag_db;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->tag_db = new TagDatabase();

		add_action( 'wp_ajax_otz_add_customer_tag', array( $this, 'add_customer_tag' ) );
		add_action( 'wp_ajax_otz_remove_customer_tag', array( $this, 'remove_customer_tag' ) );
		add_action( 'wp_ajax_otz_remove_customer_tag_by_time', array( $this, 'remove_customer_tag_by_time' ) );
		add_action( 'wp_ajax_otz_search_customer_tags', array( $this, 'search_customer_tags' ) );
		add_action( 'wp_ajax_otz_search_friends_by_tag', array( $this, 'search_friends_by_tag' ) );
		add_action( 'wp_ajax_otz_get_tag_users', array( $this, 'get_tag_users' ) );
		add_filter( 'wc_notify_action_module', array( $this, 'get_tag_options' ), 10, 2 );

		// 新增: 監聽 wc-notify 發送完成事件.
		add_action( 'wc_notify_after_send', array( $this, 'handle_auto_tagging' ), 10, 2 );
	}

	/**
	 * 新增客戶標籤
	 */
	public function add_customer_tag() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			// 檢查 nonce 驗證.
			if ( ! check_ajax_referer( 'orderchatz_chat_action', 'nonce', false ) ) {
				throw new Exception( '安全驗證失敗,請重新整理頁面後再試' );
			}

			$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$tag_name     = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';

			if ( empty( $line_user_id ) || empty( $tag_name ) ) {
				throw new Exception( 'LINE User ID 和標籤名稱不能為空' );
			}

			// 使用 Database\Tag 新增標籤.
			$result = $this->tag_db->add_tag_to_user( $line_user_id, $tag_name );

			if ( ! $result ) {
				throw new Exception( '新增標籤失敗' );
			}

			// 取得當前時間作為貼標時間.
			$tagged_at = current_time( 'mysql' );

			$this->sendSuccess(
				array(
					'message'   => '標籤已新增',
					'tag'       => $tag_name,
					'tagged_at' => $tagged_at,
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 移除客戶標籤
	 */
	public function remove_customer_tag() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			// 檢查 nonce 驗證.
			if ( ! check_ajax_referer( 'orderchatz_chat_action', 'nonce', false ) ) {
				throw new Exception( '安全驗證失敗,請重新整理頁面後再試' );
			}

			$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$tag_name     = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';

			if ( empty( $line_user_id ) || empty( $tag_name ) ) {
				throw new Exception( 'LINE User ID 和標籤名稱不能為空' );
			}

			// 使用 Database\Tag 移除標籤.
			$result = $this->tag_db->remove_tag_from_user( $line_user_id, $tag_name );

			if ( ! $result ) {
				throw new Exception( '移除標籤失敗' );
			}

			$this->sendSuccess(
				array(
					'message'  => '標籤已移除',
					'tag_name' => $tag_name,
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 根據時間刪除客戶標籤
	 */
	public function remove_customer_tag_by_time() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			// 檢查 nonce 驗證.
			if ( ! check_ajax_referer( 'orderchatz_chat_action', 'nonce', false ) ) {
				throw new Exception( '安全驗證失敗,請重新整理頁面後再試' );
			}

			$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$tag_name     = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';
			$tagged_at    = ( isset( $_POST['tagged_at'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tagged_at'] ) ) : '';

			if ( empty( $line_user_id ) || empty( $tag_name ) || empty( $tagged_at ) ) {
				throw new Exception( 'LINE User ID、標籤名稱和時間不能為空' );
			}

			// 使用 Database\Tag 根據時間刪除標籤.
			$result = $this->tag_db->remove_tag_by_time( $line_user_id, $tag_name, $tagged_at );

			if ( ! $result ) {
				throw new Exception( '刪除標籤失敗' );
			}

			$this->sendSuccess(
				array(
					'message'   => '標籤記錄已刪除',
					'tag_name'  => $tag_name,
					'tagged_at' => $tagged_at,
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得使用者標籤並包含次數和時間陣列
	 *
	 * @param string $line_user_id LINE User ID.
	 * @return array 標籤陣列 [{"tag_name": "VIP", "count": 3, "times": ["2025-10-21 10:00:00", ...]}].
	 */
	public function get_user_tags_with_count( string $line_user_id ): array {
		// 取得所有標籤記錄.
		$tags = $this->tag_db->get_user_tags( $line_user_id );

		if ( empty( $tags ) ) {
			return array();
		}

		// 按標籤名稱分組.
		$grouped_tags = array();

		foreach ( $tags as $tag ) {
			$tag_name  = $tag['tag_name'] ?? '';
			$tagged_at = $tag['tagged_at'] ?? '';

			if ( empty( $tag_name ) ) {
				continue;
			}

			if ( ! isset( $grouped_tags[ $tag_name ] ) ) {
				$grouped_tags[ $tag_name ] = array(
					'tag_name' => $tag_name,
					'count'    => 0,
					'times'    => array(),
				);
			}

			$grouped_tags[ $tag_name ]['count']++;
			if ( ! empty( $tagged_at ) ) {
				$grouped_tags[ $tag_name ]['times'][] = $tagged_at;
			}
		}

		// 將關聯陣列轉為索引陣列.
		return array_values( $grouped_tags );
	}

	/**
	 * 搜尋客戶標籤
	 */
	public function search_customer_tags() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			// 檢查 nonce 驗證 - 支援聊天頁面和推播頁面兩種來源.
			$nonce_valid = check_ajax_referer( 'orderchatz_chat_action', 'nonce', false ) ||
						check_ajax_referer( 'otz_broadcast_action', 'nonce', false );

			if ( ! $nonce_valid ) {
				throw new Exception( '安全驗證失敗,請重新整理頁面後再試' );
			}

			// 支援兩種參數名稱 (query 和 search) 以保持相容性.
			$search_term = '';
			if ( isset( $_POST['query'] ) ) {
				$search_term = sanitize_text_field( wp_unslash( $_POST['query'] ) );
			} elseif ( isset( $_REQUEST['search'] ) ) {
				$search_term = sanitize_text_field( wp_unslash( $_REQUEST['search'] ) );
			}

			$page = ( isset( $_REQUEST['page'] ) ) ? intval( wp_unslash( $_REQUEST['page'] ) ) : 1;

			$per_page = 20; // 每頁顯示數量.

			global $wpdb;
			$user_tags_table = $wpdb->prefix . 'otz_user_tags';

			// 構建查詢.
			$where_clause = '';
			$params       = array();

			if ( ! empty( $search_term ) ) {
				$where_clause = ' WHERE tag_name LIKE %s';
				$params[]     = '%' . $wpdb->esc_like( $search_term ) . '%';
			}

			// 計算總數(新結構:每個標籤只有一筆記錄).
			$total_count_sql = "SELECT COUNT(*) FROM {$user_tags_table}" . $where_clause;
			$total_count     = $wpdb->get_var(
				empty( $params ) ? $total_count_sql : $wpdb->prepare( $total_count_sql, ...$params )
			);

			// 分頁查詢.
			$offset    = ( $page - 1 ) * $per_page;
			$query_sql = "SELECT tag_name FROM {$user_tags_table}" . $where_clause .
						' ORDER BY tag_name ASC LIMIT %d OFFSET %d';

			$final_params = array_merge( $params, array( $per_page, $offset ) );
			$tags         = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$final_params ) );

			// 格式化結果供 Select2 使用.
			$results = array();
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					$results[] = array(
						'id'   => $tag->tag_name,
						'text' => $tag->tag_name,
					);
				}
			}

			$has_more = ( $page * $per_page ) < $total_count;

			$this->sendSuccess(
				array(
					'results'    => $results,
					'pagination' => array( 'more' => $has_more ),
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 根據標籤搜尋好友列表
	 */
	public function search_friends_by_tag() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			// 檢查 nonce 驗證.
			if ( ! check_ajax_referer( 'orderchatz_chat_action', 'nonce', false ) ) {
				throw new Exception( '安全驗證失敗,請重新整理頁面後再試' );
			}

			$tag_name = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';
			$page     = ( isset( $_POST['page'] ) ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;
			$per_page = ( isset( $_POST['per_page'] ) ) ? intval( wp_unslash( $_POST['per_page'] ) ) : 20;

			if ( empty( $tag_name ) ) {
				throw new Exception( '標籤名稱不能為空' );
			}

			// 使用 Database\Tag 取得該標籤的所有使用者 ID.
			$line_user_ids = $this->tag_db->get_unique_users_by_tag( $tag_name );

			if ( empty( $line_user_ids ) ) {
				$this->sendSuccess(
					array(
						'friends'  => array(),
						'has_more' => false,
					)
				);
				return;
			}

			// 查詢這些使用者的完整資訊.
			$friends = $this->query_friends_by_line_user_ids( $line_user_ids, $page, $per_page );

			$this->sendSuccess(
				array(
					'friends'  => $friends,
					'has_more' => count( $friends ) === $per_page,
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得帶有指定標籤的好友清單
	 *
	 * 用於標籤管理頁面的好友清單燈箱.
	 * 支援分頁載入.
	 */
	public function get_tag_users() {
		try {
			// 檢查用戶權限.
			if ( ! current_user_can( 'edit_posts' ) ) {
				throw new Exception( '權限不足' );
			}

			// 檢查 nonce 驗證.
			if ( ! check_ajax_referer( 'orderchatz_admin_action', 'nonce', false ) ) {
				throw new Exception( '安全驗證失敗,請重新整理頁面後再試' );
			}

			$tag_name = ( isset( $_POST['tag_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';
			$page     = ( isset( $_POST['page'] ) ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;
			$per_page = ( isset( $_POST['per_page'] ) ) ? intval( wp_unslash( $_POST['per_page'] ) ) : 20;

			if ( empty( $tag_name ) ) {
				throw new Exception( '標籤名稱不能為空' );
			}

			// 取得總數.
			$total = $this->get_tag_users_count( $tag_name );

			// 取得分頁資料.
			$users = $this->get_users_with_tag( $tag_name, $page, $per_page );

			// 計算分頁資訊.
			$total_pages = ceil( $total / $per_page );
			$has_more    = $page < $total_pages;

			// 格式化好友資料.
			$friends = array();
			foreach ( $users as $user ) {
				$wp_user_info = null;
				if ( $user['wp_user_id'] ) {
					$wp_user = get_user_by( 'id', $user['wp_user_id'] );
					if ( $wp_user ) {
						$wp_user_info = array(
							'id'           => $wp_user->ID,
							'name'         => $wp_user->display_name,
							'email'        => $wp_user->user_email,
							'edit_url'     => admin_url( 'user-edit.php?user_id=' . $wp_user->ID ),
						);
					}
				}

				$friends[] = array(
					'friend_id'      => $user['id'],
					'line_user_id'   => $user['line_user_id'],
					'display_name'   => $user['display_name'] ?: __( '無名稱', 'otz' ),
					'avatar_url'     => $user['avatar_url'] ?: '',
					'wp_user'        => $wp_user_info,
					'last_active'    => $user['last_active'],
					'followed_at'    => $user['followed_at'],
				);
			}

			$this->sendSuccess(
				array(
					'friends'     => $friends,
					'total'       => $total,
					'total_pages' => $total_pages,
					'has_more'    => $has_more,
					'current_page'=> $page,
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 在 OrderNotify 中加入標籤選項
	 *
	 * @param object $trigger 觸發器物件.
	 */
	public function get_tag_options( $wc_notify_action_module, $action ) {
		$wc_notify_action_module[] = $action->addMultiSelect(
			array(
				'id'    => 'wc_notify_trigger_user_tags',
				'class' => 'wc_notify_trigger_user_tags',
				'label' => __( 'User Tags', 'otz' ),
				'desc'  => __( 'Apply tags to user when the rule was triggered. ', 'otz' ),
			),
			$this->tag_db->get_all_tags_for_select2(),
			true
		);
		return $wc_notify_action_module;
	}

	/**
	 * 根據 LINE User IDs 查詢好友完整資訊
	 *
	 * 注意:此方法重用了 FriendHandler 的查詢邏輯
	 * 直接調用 otz_get_friends action 並傳入 line_user_ids 篩選
	 *
	 * @param array $line_user_ids LINE User ID 陣列.
	 * @param int   $page          頁碼.
	 * @param int   $per_page      每頁數量.
	 * @return array 好友資料陣列.
	 */
	private function query_friends_by_line_user_ids( array $line_user_ids, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		if ( empty( $line_user_ids ) ) {
			return array();
		}

		$table_users = $wpdb->prefix . 'otz_users';

		// 構建 IN 條件的 SQL.
		$placeholders = implode( ',', array_fill( 0, count( $line_user_ids ), '%s' ) );
		$offset       = ( $page - 1 ) * $per_page;

		$sql = "SELECT id,
		               line_user_id,
		               display_name,
		               avatar_url,
		               followed_at,
		               last_active,
		               read_time
		        FROM {$table_users}
		        WHERE line_user_id IN ({$placeholders})
		        ORDER BY last_active DESC
		        LIMIT %d OFFSET %d";

		$params  = array_merge( $line_user_ids, array( $per_page, $offset ) );
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		if ( empty( $results ) ) {
			return array();
		}

		// 使用 FriendHandler 的 calculate_batch_stats 方法.
		// 由於是 private 方法,這裡使用相同邏輯但簡化版本.
		require_once __DIR__ . '/../Chat/FriendHandler.php';
		$friend_handler = new \OrderChatz\Ajax\Chat\FriendHandler();

		// 使用反射調用 private 方法.
		$reflection = new \ReflectionClass( $friend_handler );
		$method     = $reflection->getMethod( 'calculate_batch_stats' );
		$method->setAccessible( true );
		$stats_batch = $method->invoke( $friend_handler, $results );

		$friends = array();
		foreach ( $results as $row ) {
			$stats = $stats_batch[ $row->line_user_id ] ?? null;

			$friend_data = array(
				'id'                     => $row->id,
				'line_user_id'           => $row->line_user_id,
				'name'                   => $row->display_name,
				'avatar'                 => $row->avatar_url ? $row->avatar_url : $this->getDefaultAvatar(),
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

		// 排序:未讀數優先,然後按最後活動時間.
		usort(
			$friends,
			function ( $a, $b ) {
				// 1. 未讀數優先.
				if ( $a['unread_count'] > 0 && 0 === $b['unread_count'] ) {
					return -1;
				}
				if ( 0 === $a['unread_count'] && $b['unread_count'] > 0 ) {
					return 1;
				}

				// 2. 最後活動時間戳比較.
				$a_timestamp = is_numeric( $a['last_message_timestamp'] )
					? intval( $a['last_message_timestamp'] )
					: strtotime( $a['last_message_timestamp'] );
				$b_timestamp = is_numeric( $b['last_message_timestamp'] )
					? intval( $b['last_message_timestamp'] )
					: strtotime( $b['last_message_timestamp'] );

				return $b_timestamp - $a_timestamp;
			}
		);

		return $friends;
	}

	/**
	 * 處理推播後的自動貼標籤
	 *
	 * @param int   $wp_user_id WP User ID.
	 * @param array $action     完整的 action 設定.
	 */
	public function handle_auto_tagging( $wp_user_id, $action ) {
		// 檢查 WP User 是否存在.
		$user = get_user_by( 'ID', $wp_user_id );
		if ( ! $user ) {
			return;
		}

		// 從 otz_users 取得 LINE User ID.
		$line_user_id = $this->get_line_user_id_by_wp_user( $wp_user_id );
		if ( ! $line_user_id ) {
			return;
		}

		// 取得要貼的標籤.
		$tags = $this->get_tags_from_action( $action );
		if ( empty( $tags ) ) {
			return;
		}

		// 貼標籤.
		$this->apply_tags_to_user( $line_user_id, $tags );
	}

	/**
	 * 從 otz_users 表取得 LINE User ID
	 *
	 * @param int $wp_user_id WordPress User ID.
	 * @return string|null
	 */
	private function get_line_user_id_by_wp_user( int $wp_user_id ): ?string {
		global $wpdb;
		$table_name = $wpdb->prefix . 'otz_users';

		$line_user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT line_user_id FROM `{$table_name}` WHERE wp_user_id = %d LIMIT 1",
				$wp_user_id
			)
		);

		return $line_user_id ?: null;
	}

	/**
	 * 從 action 取得標籤設定
	 *
	 * @param array $action Action 設定.
	 * @return array
	 */
	private function get_tags_from_action( array $action ): array {
		if ( ! isset( $action['wc_notify_trigger_user_tags'] ) ) {
			return array();
		}

		$tags = $action['wc_notify_trigger_user_tags'];

		// 如果是 JSON 字串,解析成陣列.
		if ( is_string( $tags ) ) {
			$tags = json_decode( $tags, true );
		}

		return is_array( $tags ) ? $tags : array();
	}

	/**
	 * 為 LINE User ID 貼上標籤
	 *
	 * @param string $line_user_id LINE User ID.
	 * @param array  $tags         標籤陣列.
	 */
	private function apply_tags_to_user( string $line_user_id, array $tags ): void {
		foreach ( $tags as $tag_name ) {
			if ( empty( $tag_name ) ) {
				continue;
			}

			$success = $this->tag_db->add_tag_to_user( $line_user_id, $tag_name );

			// 只在失敗時記錄 log.
			if ( ! $success ) {
				Logger::error(
					"WC Notify 自動貼標籤失敗 - 標籤: {$tag_name}, LINE User ID: {$line_user_id}",
					array(
						'tag_name'     => $tag_name,
						'line_user_id' => $line_user_id,
					),
					'wc-notify-integration'
				);
			}
		}
	}

	/**
	 * 取得帶有指定標籤的好友資料
	 *
	 * @param string $tag_name 標籤名稱.
	 * @param int    $page     頁碼 (從 1 開始).
	 * @param int    $per_page 每頁筆數.
	 * @return array
	 */
	private function get_users_with_tag( string $tag_name, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		// 使用 Database\Tag 取得所有使用此標籤的 LINE User ID.
		$user_ids = $this->tag_db->get_users_by_tag( $tag_name );

		// 如果沒有使用者,回傳空陣列.
		if ( empty( $user_ids ) ) {
			return array();
		}

		$users_table = $wpdb->prefix . 'otz_users';

		// 準備 IN 查詢的 placeholders.
		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%s' ) );

		// 計算 OFFSET.
		$offset = ( $page - 1 ) * $per_page;

		$sql = "
			SELECT id, line_user_id, display_name, avatar_url, wp_user_id, last_active, followed_at
			FROM {$users_table}
			WHERE line_user_id IN ($placeholders)
			ORDER BY display_name ASC
			LIMIT %d OFFSET %d
		";

		// 合併參數: user_ids + limit + offset.
		$params = array_merge( $user_ids, array( $per_page, $offset ) );

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * 取得帶有指定標籤的好友總數
	 *
	 * @param string $tag_name 標籤名稱.
	 * @return int
	 */
	private function get_tag_users_count( string $tag_name ): int {
		// 使用 Database\Tag 取得所有使用此標籤的 LINE User ID.
		$user_ids = $this->tag_db->get_users_by_tag( $tag_name );

		return count( $user_ids );
	}
}
