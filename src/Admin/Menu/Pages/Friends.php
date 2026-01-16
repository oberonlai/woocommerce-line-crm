<?php
/**
 * OrderChatz 好友頁面渲染器
 *
 * 處理好友管理頁面的內容渲染
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use Exception;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * 好友清單表格類別
 */
class Friends_List_Table extends \WP_List_Table {
	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'friend',
				'plural'   => 'friends',
				'ajax'     => false,
			)
		);
	}

	/**
	 * 取得欄位
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'display_name' => __( 'LINE 顯示名稱', 'otz' ),
			'line_user_id' => __( 'LINE ID', 'otz' ),
			'wp_user'      => __( '網站會員', 'otz' ),
			'followed_at'  => __( '加入時間', 'otz' ),
			'actions'      => __( '操作', 'otz' ),
		);
	}

	/**
	 * 取得可排序的欄位
	 */
	public function get_sortable_columns() {
		return array(
			'display_name' => array( 'display_name', false ),
			'followed_at'  => array( 'followed_at', false ),
		);
	}

	/**
	 * 取得大量操作選項
	 */
	public function get_bulk_actions() {
		return array(
			'refresh_data' => __( '重新取得資料', 'otz' ),
			'delete'       => __( '刪除', 'otz' ),
		);
	}

	/**
	 * 準備項目
	 */
	public function prepare_items() {
		global $wpdb;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'followed_at';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'desc';

		$table_name = $wpdb->prefix . 'otz_users';

		// 取得搜尋關鍵字
		$search = isset( $_GET['s'] ) ? trim( sanitize_text_field( $_GET['s'] ) ) : '';

		$where_clause = '';
		if ( ! empty( $search ) ) {
			$like_search = '%' . $wpdb->esc_like( $search ) . '%';

			// 基本搜尋條件（LINE 顯示名稱和 LINE ID）
			$conditions = array(
				$wpdb->prepare( 'display_name LIKE %s', $like_search ),
				$wpdb->prepare( 'line_user_id LIKE %s', $like_search ),
			);

			// 如果搜尋詞看起來像 email，嘗試搜尋 WordPress 會員
			if ( filter_var( $search, FILTER_VALIDATE_EMAIL ) || strpos( $search, '@' ) !== false ) {
				$user = get_user_by( 'email', $search );
				if ( $user ) {
					$conditions[] = $wpdb->prepare( 'wp_user_id = %d', $user->ID );
				}
			}

			$where_clause = ' WHERE (' . implode( ' OR ', $conditions ) . ')';
		}

		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name $where_clause" );

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * 渲染核取方塊欄位
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="friends[]" value="%s" />',
			$item->id
		);
	}


	/**
	 * 渲染顯示名稱欄位
	 */
	public function column_display_name( $item ) {
		$edit_url = add_query_arg(
			array(
				'action'    => 'edit',
				'friend_id' => $item->id,
			)
		);

		$delete_url = add_query_arg(
			array(
				'action'    => 'delete',
				'friend_id' => $item->id,
				'_wpnonce'  => wp_create_nonce( 'delete_friend_' . $item->id ),
			)
		);

		$chat_url = add_query_arg(
			array(
				'page'   => 'order-chatz',
				'friend' => $item->id,
			)
		);

		$actions = array(
			// 'chat'            => sprintf( '<a href="%s">%s</a>', $chat_url, __( '聊天', 'otz' ) ),
							'edit' => sprintf( '<a href="%s">%s</a>', $edit_url, __( '編輯', 'otz' ) ),
			'import_messages'      => sprintf(
				'<a href="#" class="import-messages-btn" data-friend-id="%s" data-line-user-id="%s" data-display-name="%s">%s</a>',
				esc_attr( $item->id ),
				esc_attr( $item->line_user_id ),
				esc_attr( $item->display_name ),
				__( '匯入訊息', 'otz' )
			),
			'delete'               => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				$delete_url,
				__( '確定要刪除這位好友嗎？', 'otz' ),
				__( '刪除', 'otz' )
			),
		);

		$avatar_html = sprintf(
			'<img src="%s" alt="%s" style="width: 32px; height: 32px; border-radius: 50%%; margin-right: 10px; vertical-align: middle;" />',
			esc_url( $item->avatar_url ),
			esc_attr( $item->display_name )
		);

		return sprintf(
			'<div style="display: flex;">%s<div>%s%s</div></div>',
			$avatar_html,
			esc_html( $item->display_name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * 渲染 LINE ID 欄位
	 */
	public function column_line_user_id( $item ) {
		if ( empty( $item->line_user_id ) ) {
			return '<span style="color: #999;">' . __( '尚未加入好友', 'otz' ) . '</span>';
		}
		return esc_html( $item->line_user_id );
	}

	/**
	 * 渲染網站會員欄位
	 */
	public function column_wp_user( $item ) {
		$friend_id       = $item->id;
		$current_user_id = $item->wp_user_id;

		// 取得目前綁定的會員資訊
		$current_user_display = '';
		$current_user_link    = '';

		if ( ! empty( $current_user_id ) ) {
			$user = get_user_by( 'ID', $current_user_id );
			if ( $user ) {
				$current_user_display = $user->display_name;
				$current_user_link    = get_edit_user_link( $user->ID );
			} else {
				$current_user_display = __( '會員不存在', 'otz' );
			}
		} else {
			$current_user_display = __( '尚未綁定', 'otz' );
		}

		$html = '<div class="wp-user-binding" data-friend-id="' . esc_attr( $friend_id ) . '">';

		// 顯示區域
		$html .= '<div class="wp-user-display">';
		if ( ! empty( $current_user_id ) && $user ) {
			$html .= '<a href="' . esc_url( $current_user_link ) . '" target="_blank" class="user-link">' . esc_html( $current_user_display ) . '</a>';
		} else {
			$html .= '<span class="user-text" style="color: ' . ( empty( $current_user_id ) ? '#999' : '#dc3232' ) . ';">' . esc_html( $current_user_display ) . '</span>';
		}
		$html .= ' <button type="button" class="button button-small edit-user-binding" data-friend-id="' . esc_attr( $friend_id ) . '" style="margin-top: 10px; opacity: 0; transition: opacity 0.3s ease;" title="' . __( '編輯會員綁定', 'otz' ) . '"><span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span></button>';
		$html .= '</div>';

		// 編輯區域（預設隱藏）
		$html .= '<div class="wp-user-edit" style="display: none;">';
		$html .= '<select class="user-select" data-friend-id="' . esc_attr( $friend_id ) . '" style="width: 200px; margin-right: 5px;">';
		$html .= '<option value="">' . __( '選擇會員...', 'otz' ) . '</option>';
		if ( ! empty( $current_user_id ) && $user ) {
			$html .= '<option value="' . esc_attr( $current_user_id ) . '" selected>' . esc_html( $current_user_display . ' (' . $user->user_email . ')' ) . '</option>';
		}
		$html .= '</select>';
		$html .= '<div style="margin-top:5px"><button type="button" class="button button-small save-user-binding" data-friend-id="' . esc_attr( $friend_id ) . '">' . __( '儲存', 'otz' ) . '</button>';
		$html .= ' <button type="button" class="button button-small cancel-user-binding">' . __( '取消', 'otz' ) . '</button></div>';
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * 渲染加入時間欄位
	 */
	public function column_followed_at( $item ) {
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->followed_at ) );
	}

	/**
	 * 渲染操作欄位
	 */
	public function column_actions( $item ) {
		$edit_url = add_query_arg(
			array(
				'action'    => 'edit',
				'friend_id' => $item->id,
			)
		);

		$delete_url = add_query_arg(
			array(
				'action'    => 'delete',
				'friend_id' => $item->id,
				'_wpnonce'  => wp_create_nonce( 'delete_friend_' . $item->id ),
			)
		);

		return sprintf(
			'<a href="%s" class="button button-small">%s</a> <a href="#" class="button button-small refresh-friend-data" data-friend-id="%s" data-nonce="%s">%s</a> <a href="%s" class="button button-small" onclick="return confirm(\'%s\')">%s</a>',
			$edit_url,
			__( '編輯', 'otz' ),
			$item->id,
			wp_create_nonce( 'otz_refresh_friend_data' ),
			__( '重新取得資料', 'otz' ),
			$delete_url,
			__( '確定要刪除這位好友嗎？', 'otz' ),
			__( '刪除', 'otz' )
		);
	}

	/**
	 * 預設欄位渲染
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}
}

/**
 * 好友頁面渲染器類別
 *
 * 渲染 LINE 好友管理相關功能的管理介面
 */
class Friends extends PageRenderer {

	/**
	 * 靜態方法用於註冊 AJAX handlers
	 */
	public static function init_ajax_handlers() {
		add_action( 'wp_ajax_otz_search_users', array( __CLASS__, 'ajax_search_users_static' ) );
		add_action( 'wp_ajax_otz_update_user_binding', array( __CLASS__, 'ajax_update_user_binding_static' ) );
		add_action( 'wp_ajax_otz_refresh_friend_data', array( __CLASS__, 'ajax_refresh_friend_data_static' ) );
	}

	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( '好友', 'otz' ),
			'otz-friends',
			true
		);

		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * 處理操作
	 */
	public function handle_actions() {
		// 只在好友頁面處理操作
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'otz-friends' ) {
			return;
		}

		$action = $_GET['action'] ?? '';

		if ( $action === 'delete' && isset( $_GET['friend_id'] ) ) {
			$this->handle_delete();
		}

		// 處理編輯操作（GET 請求顯示表單，POST 請求處理表單）
		if ( ( $action === 'edit' && isset( $_GET['friend_id'] ) ) ||
			( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['friend_id'] ) ) ) {
			$this->handle_edit();
		}

		// 處理批次重新取得資料操作 (支援上方和下方的批次操作選單)
		if ( ( isset( $_POST['action'] ) && $_POST['action'] === 'refresh_data' ) ||
			( isset( $_POST['action2'] ) && $_POST['action2'] === 'refresh_data' ) ) {

			$this->handle_bulk_refresh_data();
		}

		// 處理批次刪除操作 (支援上方和下方的批次操作選單)
		if ( ( isset( $_POST['action'] ) && $_POST['action'] === 'delete' ) ||
			( isset( $_POST['action2'] ) && $_POST['action2'] === 'delete' ) ) {

			$this->handle_bulk_delete();
		}
	}

	/**
	 * 處理刪除操作
	 */
	private function handle_delete() {
		$friend_id = intval( $_GET['friend_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_friend_' . $friend_id ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'otz_users';

		$result = $wpdb->delete( $table_name, array( 'id' => $friend_id ), array( '%d' ) );

		if ( $result !== false ) {
			wp_redirect( add_query_arg( 'message', 'deleted', remove_query_arg( array( 'action', 'friend_id', '_wpnonce' ) ) ) );
			exit;
		} else {
			wp_redirect( add_query_arg( 'error', 'delete_failed', remove_query_arg( array( 'action', 'friend_id', '_wpnonce' ) ) ) );
			exit;
		}
	}

	/**
	 * 處理批次重新取得好友資料操作
	 */
	private function handle_bulk_refresh_data() {
		try {
			if ( ! isset( $_POST['friends'] ) || ! is_array( $_POST['friends'] ) ) {
				wp_redirect( add_query_arg( 'error', 'no_items_selected' ) );
				exit;
			}

			check_admin_referer( 'bulk-friends' );

			$friend_ids = array_map( 'intval', $_POST['friends'] );
			$friend_ids = array_filter( $friend_ids );

			if ( empty( $friend_ids ) ) {
				wp_redirect( add_query_arg( 'error', 'no_valid_items' ) );
				exit;
			}

			// 取得 ACCESS TOKEN.
			$access_token = get_option( 'otz_access_token' );
			if ( empty( $access_token ) ) {
				wp_redirect( add_query_arg( 'error', 'no_access_token' ) );
				exit;
			}

			global $wpdb;
			$table_name   = $wpdb->prefix . 'otz_users';
			$line_handler = new \OrderChatz\Ajax\LineFriendsSyncHandler();

			$updated_count = 0;
			$failed_count  = 0;
			$total_count   = count( $friend_ids );

			foreach ( $friend_ids as $friend_id ) {
				// 取得好友資料（包含 line_user_id）.
				$friend = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $friend_id ) );

				if ( ! $friend || empty( $friend->line_user_id ) ) {
					$failed_count++;
					continue;
				}

				// 取得 LINE 資料.
				$profile = $line_handler->getLineUserProfile( $access_token, $friend->line_user_id );

				if ( ! $profile ) {
					$failed_count++;
					continue;
				}

				// 更新資料庫.
				$result = $wpdb->update(
					$table_name,
					array(
						'display_name' => $profile['displayName'] ?? $friend->display_name,
						'avatar_url'   => $profile['pictureUrl'] ?? $friend->avatar_url,
					),
					array( 'id' => $friend_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				if ( $result !== false ) {
					$updated_count++;
				} else {
					$failed_count++;
				}
			}

			// 根據結果重導向.
			if ( $updated_count === $total_count ) {
				wp_redirect( add_query_arg( 'message', 'bulk_refresh_completed' ) );
			} elseif ( $updated_count > 0 ) {
				wp_redirect(
					add_query_arg(
						array(
							'message' => 'bulk_refresh_partial',
							'updated' => $updated_count,
							'failed'  => $failed_count,
						)
					)
				);
			} else {
				wp_redirect( add_query_arg( 'error', 'bulk_refresh_failed' ) );
			}
			exit;

		} catch ( Exception $e ) {
			wp_redirect( add_query_arg( 'error', 'bulk_refresh_error' ) );
			exit;
		}
	}

	/**
	 * 處理大量刪除操作
	 */
	private function handle_bulk_delete() {
		try {

			if ( ! isset( $_POST['friends'] ) || ! is_array( $_POST['friends'] ) ) {

				wp_redirect( add_query_arg( 'error', 'no_items_selected' ) );
				exit;
			}

			check_admin_referer( 'bulk-friends' );

			global $wpdb;
			$table_name = $wpdb->prefix . 'otz_users';

			$ids = array_map( 'intval', $_POST['friends'] );
			$ids = array_filter( $ids ); // 移除空值

			if ( empty( $ids ) ) {
				wp_redirect( add_query_arg( 'error', 'no_valid_items' ) );
				exit;
			}

			// 使用更簡單的方法來避免參數問題
			$deleted_count = 0;
			foreach ( $ids as $id ) {
				$result = $wpdb->delete(
					$table_name,
					array( 'id' => $id ),
					array( '%d' )
				);
				if ( $result !== false ) {
					$deleted_count++;
				}
			}

			$result = $deleted_count > 0 ? $deleted_count : false;

			if ( $result !== false ) {
				wp_redirect( add_query_arg( 'message', 'bulk_deleted' ) );
				exit;
			} else {
				wp_redirect( add_query_arg( 'error', 'bulk_delete_failed' ) );
				exit;
			}
		} catch ( Exception $e ) {

			wp_redirect( add_query_arg( 'error', 'bulk_delete_error' ) );
			exit;
		}
	}

	/**
	 * 處理編輯操作
	 */
	private function handle_edit() {
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			check_admin_referer( 'edit_friend' );

			$friend_id    = intval( $_POST['friend_id'] );
			$display_name = sanitize_text_field( $_POST['display_name'] );

			// 優先使用 wp_user_select 的值，如果為空則使用 wp_user_id
			$wp_user_id = null;
			if ( ! empty( $_POST['wp_user_select'] ) ) {
				$wp_user_id = intval( $_POST['wp_user_select'] );
			} elseif ( ! empty( $_POST['wp_user_id'] ) ) {
				$wp_user_id = intval( $_POST['wp_user_id'] );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'otz_users';

			$update_data = array(
				'display_name' => $display_name,
				'wp_user_id'   => $wp_user_id,
			);

			$result = $wpdb->update(
				$table_name,
				$update_data,
				array( 'id' => $friend_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			if ( $result !== false ) {
				wp_redirect( add_query_arg( 'message', 'updated', remove_query_arg( array( 'action', 'friend_id' ) ) ) );
				exit;
			}
		}
	}

	/**
	 * 靜態 AJAX 更新會員綁定處理器
	 */
	public static function ajax_update_user_binding_static() {
		// 檢查 nonce
		$nonce = $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'otz_update_user_binding' ) ) {
			wp_send_json_error( array( 'message' => __( '安全驗證失敗', 'otz' ) ) );
			return;
		}

		// 檢查權限ㄡ
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
			return;
		}

		$friend_id  = intval( $_POST['friend_id'] ?? 0 );
		$wp_user_id = intval( $_POST['wp_user_id'] ?? 0 );

		if ( empty( $friend_id ) ) {
			wp_send_json_error( array( 'message' => __( '好友 ID 不能為空', 'otz' ) ) );
			return;
		}

		try {
			global $wpdb;
			$table_name = $wpdb->prefix . 'otz_users';

			$result = $wpdb->update(
				$table_name,
				array( 'wp_user_id' => $wp_user_id ?: null ),
				array( 'id' => $friend_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( $result !== false ) {

				$display_data = array();
				if ( ! empty( $wp_user_id ) ) {
					$user = get_user_by( 'ID', $wp_user_id );
					if ( $user ) {
						$display_data = array(
							'display_name' => $user->display_name,
							'user_email'   => $user->user_email,
							'edit_link'    => get_edit_user_link( $user->ID ),
						);
					}
				}

				wp_send_json_success(
					array(
						'message'      => __( '會員綁定已更新', 'otz' ),
						'display_data' => $display_data,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( '更新失敗', 'otz' ) ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( '更新時發生錯誤', 'otz' ) ) );
		}
	}

	/**
	 * 靜態 AJAX 重新取得好友資料處理器
	 */
	public static function ajax_refresh_friend_data_static() {
		// 檢查 nonce.
		$nonce = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'otz_refresh_friend_data' ) ) {
			wp_send_json_error( array( 'message' => __( '安全驗證失敗', 'otz' ) ) );
			return;
		}

		// 檢查權限.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
			return;
		}

		$friend_id = intval( $_POST['friend_id'] ?? 0 );

		try {
			global $wpdb;
			$table_name = $wpdb->prefix . 'otz_users';

			// 取得好友資料.
			$friend = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $friend_id ) );

			// 取得 ACCESS TOKEN.
			$access_token = get_option( 'otz_access_token' );
			if ( empty( $access_token ) ) {
				wp_send_json_error( array( 'message' => __( 'LINE ACCESS TOKEN 未設定', 'otz' ) ) );
				return;
			}

			// 使用現有的 LineFriendsSyncHandler 來取得 LINE 資料.
			$line_handler = new \OrderChatz\Ajax\LineFriendsSyncHandler();
			$profile      = $line_handler->getLineUserProfile( $access_token, $friend->line_user_id );

			if ( ! $profile ) {
				wp_send_json_error( array( 'message' => __( '無法從 LINE API 取得用戶資料', 'otz' ) ) );
				return;
			}

			// 更新資料庫.
			$result = $wpdb->update(
				$table_name,
				array(
					'display_name' => $profile['displayName'] ?? $friend->display_name,
					'avatar_url'   => $profile['pictureUrl'] ?? $friend->avatar_url,
				),
				array( 'id' => $friend_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( $result !== false ) {
				wp_send_json_success(
					array(
						'message'      => __( '好友資料已更新', 'otz' ),
						'display_name' => $profile['displayName'] ?? $friend->display_name,
						'avatar_url'   => $profile['pictureUrl'] ?? $friend->avatar_url,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( '更新失敗', 'otz' ) ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( '更新時發生錯誤：', 'otz' ) . $e->getMessage() ) );
		}
	}

	/**
	 * 靜態 AJAX 搜尋用戶處理器
	 */
	public static function ajax_search_users_static() {
		// 檢查 nonce
		$nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'otz_search_users' ) ) {
			wp_send_json_error( array( 'message' => __( '安全驗證失敗', 'otz' ) ) );
			return;
		}

		// 檢查權限
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '權限不足', 'otz' ) ) );
			return;
		}

		// 取得搜尋關鍵字
		$search_term = sanitize_text_field( $_POST['search'] ?? $_GET['search'] ?? '' );

		if ( strlen( $search_term ) < 1 ) {
			wp_send_json_error( array( 'message' => __( '請輸入搜尋關鍵字', 'otz' ) ) );
			return;
		}

		try {
			// 分別搜尋基本欄位和 meta 欄位，然後合併結果
			$basic_users = get_users(
				array(
					'search'         => '*' . $search_term . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
					'number'         => 20,
					'fields'         => array( 'ID', 'display_name', 'user_email' ),
				)
			);

			$meta_users = get_users(
				array(
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key'     => 'first_name',
							'value'   => $search_term,
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'last_name',
							'value'   => $search_term,
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'billing_first_name',
							'value'   => $search_term,
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'billing_last_name',
							'value'   => $search_term,
							'compare' => 'LIKE',
						),
					),
					'number'     => 20,
					'fields'     => array( 'ID', 'display_name', 'user_email' ),
				)
			);

			// 合併結果並去重
			$user_ids = array();
			$users    = array();

			foreach ( array_merge( $basic_users, $meta_users ) as $user ) {
				if ( ! in_array( $user->ID, $user_ids ) ) {
					$user_ids[] = $user->ID;
					$users[]    = $user;
				}
			}

			$results = array();
			foreach ( $users as $user ) {
				$results[] = array(
					'id'   => $user->ID,
					'text' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
				);
			}

			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => __( '搜尋時發生錯誤', 'otz' ) ) );
		}
	}

	/**
	 * 渲染好友頁面內容
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		$this->show_messages();

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['friend_id'] ) ) {
			$this->render_edit_form();
			return;
		}

		// 準備列表表格
		$list_table = new Friends_List_Table();
		$list_table->prepare_items();

		// 載入列表頁面模板
		$template_path = OTZ_PLUGIN_DIR . 'views/admin/friends/list.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . __( '好友列表模板檔案不存在', 'otz' ) . '</p></div>';
		}

		// 載入同步燈箱
		$this->render_sync_modal();

		// 載入 LINE 好友同步燈箱
		$this->render_line_sync_modal();

		// 載入訊息匯入燈箱
		$this->render_message_import_modal();

		// 載入同步功能的 JavaScript
		$this->enqueue_sync_scripts();
	}

	/**
	 * 顯示訊息
	 */
	private function show_messages() {
		if ( isset( $_GET['message'] ) ) {
			$message = '';
			switch ( $_GET['message'] ) {
				case 'deleted':
					$message = __( '好友已刪除', 'otz' );
					break;
				case 'bulk_deleted':
					$message = __( '已刪除選中的好友', 'otz' );
					break;
				case 'updated':
					$message = __( '好友資料已更新', 'otz' );
					break;
				case 'bulk_refresh_completed':
					$message = __( '已成功重新取得所有選中好友的資料', 'otz' );
					break;
				case 'bulk_refresh_partial':
					$updated = intval( $_GET['updated'] ?? 0 );
					$failed  = intval( $_GET['failed'] ?? 0 );
					/* translators: 1: 成功更新的好友數量, 2: 更新失敗的好友數量. */
					$message = sprintf( __( '批次更新完成：成功 %1$d 個，失敗 %2$d 個', 'otz' ), $updated, $failed );
					break;
			}
			if ( $message ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
			}
		}

		if ( isset( $_GET['error'] ) ) {
			$error = '';
			switch ( $_GET['error'] ) {
				case 'delete_failed':
					$error = __( '刪除失敗', 'otz' );
					break;
				case 'no_items_selected':
					$error = __( '請選擇要操作的好友', 'otz' );
					break;
				case 'no_valid_items':
					$error = __( '沒有有效的項目可操作', 'otz' );
					break;
				case 'no_access_token':
					$error = __( 'LINE ACCESS TOKEN 未設定，無法取得好友資料', 'otz' );
					break;
				case 'bulk_delete_failed':
					$error = __( '批次刪除失敗', 'otz' );
					break;
				case 'bulk_delete_error':
					$error = __( '批次刪除時發生錯誤', 'otz' );
					break;
				case 'bulk_refresh_failed':
					$error = __( '批次重新取得資料失敗', 'otz' );
					break;
				case 'bulk_refresh_error':
					$error = __( '批次重新取得資料時發生錯誤', 'otz' );
					break;
			}
			if ( $error ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
			}
		}
	}

	/**
	 * 渲染編輯表單
	 */
	private function render_edit_form() {
		$friend_id = intval( $_GET['friend_id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'otz_users';
		$friend     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $friend_id ) );

		if ( ! $friend ) {
			echo '<div class="notice notice-error"><p>' . __( '找不到好友資料', 'otz' ) . '</p></div>';
			return;
		}

		// 載入編輯表單模板
		$template_path = OTZ_PLUGIN_DIR . 'views/admin/friends/edit-form.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . __( '編輯表單模板檔案不存在', 'otz' ) . '</p></div>';
		}

		// 載入編輯頁面腳本
		$this->enqueue_edit_scripts();
	}

	/**
	 * 載入編輯頁面所需的腳本和樣式
	 */
	private function enqueue_edit_scripts() {
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		$script_deps = array( 'jquery', 'selectWoo' );

		// 載入自定義 JavaScript
		wp_enqueue_script(
			'otz-friends-edit',
			OTZ_PLUGIN_URL . 'assets/js/friends/friends-edit.js',
			$script_deps,
			'1.0.05',
			true
		);

		// 傳遞本地化字串和 AJAX nonce
		wp_localize_script(
			'otz-friends-edit',
			'otz_friends_edit',
			array(
				'placeholder' => __( '搜尋會員...', 'otz' ),
				'nonce'       => wp_create_nonce( 'otz_search_users' ),
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'messages'    => array(
					'no_results'       => __( '找不到符合的用戶', 'otz' ),
					'error'            => __( '搜尋發生錯誤', 'otz' ),
					'searching'        => __( '搜尋中...', 'otz' ),
					'selectwoo_loaded' => class_exists( 'WooCommerce' ) ? 'yes' : 'no',
				),
			)
		);
	}

	/**
	 * 渲染同步燈箱
	 */
	private function render_sync_modal() {
		// 載入同步燈箱模板
		$modal_template = OTZ_PLUGIN_DIR . 'views/admin/friends/sync-modal.php';
		if ( file_exists( $modal_template ) ) {
			include $modal_template;
		}
	}
	/**
	 * 渲染 LINE 好友同步燈箱
	 */
	private function render_line_sync_modal() {
		// 載入 LINE 好友同步燈箱模板.
		$modal_template = OTZ_PLUGIN_DIR . 'views/admin/friends/line-sync-modal.php';
		if ( file_exists( $modal_template ) ) {
			include $modal_template;
		}
	}

	/**
	 * 渲染訊息匯入燈箱
	 */
	private function render_message_import_modal() {
		// 載入訊息匯入燈箱模板.
		$modal_template = OTZ_PLUGIN_DIR . 'views/admin/friends/message-import-modal.php';
		if ( file_exists( $modal_template ) ) {
			include $modal_template;
		}
	}

	/**
	 * 載入同步功能的腳本和樣式
	 */
	private function enqueue_sync_scripts() {
		// 載入 Select2（用於會員搜尋）
		// 優先使用 WooCommerce 的 Select2，如果不存在則載入自定義版本
		if ( class_exists( 'WooCommerce' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		} else {
			// 註冊並載入 Select2 CDN 版本
			wp_enqueue_script(
				'select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
				array( 'jquery' ),
				'4.1.0',
				true
			);
			wp_enqueue_style(
				'select2',
				'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
				array(),
				'4.1.0'
			);
		}

		// 載入同步燈箱樣式
		wp_enqueue_style(
			'otz-friends-sync-modal',
			OTZ_PLUGIN_URL . 'assets/css/friends/sync-modal.css',
			array(),
			'1.0.01'
		);

		// 載入會員綁定編輯樣式
		wp_enqueue_style(
			'otz-friends-user-binding',
			OTZ_PLUGIN_URL . 'assets/css/friends/user-binding.css',
			array(),
			'1.0.01'
		);

		// 載入訊息匯入燈箱樣式
		wp_enqueue_style(
			'otz-message-import-modal',
			OTZ_PLUGIN_URL . 'assets/css/friends/message-import-modal.css',
			array(),
			'1.0.03'
		);

		// 載入網站會員同步 JavaScript
		wp_enqueue_script(
			'otz-friends-member-sync',
			OTZ_PLUGIN_URL . 'assets/js/friends/member-sync.js',
			array( 'jquery' ),
			'1.0.01',
			true
		);

		// 載入 LINE 好友同步 JavaScript
		wp_enqueue_script(
			'otz-friends-line-sync',
			OTZ_PLUGIN_URL . 'assets/js/friends/line-sync.js',
			array( 'jquery' ),
			'1.0.02',
			true
		);

		// 載入訊息匯入 JavaScript
		wp_enqueue_script(
			'otz-message-import',
			OTZ_PLUGIN_URL . 'assets/js/friends/message-import.js',
			array( 'jquery' ),
			'1.0.01',
			true
		);

		// 載入好友資料重新整理 JavaScript
		wp_enqueue_script(
			'otz-friends-refresh',
			OTZ_PLUGIN_URL . 'assets/js/friends/friends-refresh.js',
			array( 'jquery' ),
			'1.0.01',
			true
		);

		// 為兩個腳本都傳遞本地化字串和 AJAX 配置
		$localized_data = array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'otz_sync_members' ),
			'line_get_nonce'   => wp_create_nonce( 'otz_get_line_friends' ),
			'line_batch_nonce' => wp_create_nonce( 'otz_get_line_friends_batch' ),
			'line_sync_nonce'  => wp_create_nonce( 'otz_sync_line_friends' ),
			'messages'         => array(
				'cancel_confirm'  => __( '同步正在進行中，確定要取消嗎？', 'otz' ),
				'preparing'       => __( '準備中...', 'otz' ),
				'scanning'        => __( '掃描網站會員...', 'otz' ),
				'importing'       => __( '匯入會員資料...', 'otz' ),
				'matching'        => __( '配對 LINE 帳號...', 'otz' ),
				'updating'        => __( '更新好友資料...', 'otz' ),
				'completed'       => __( '同步完成！', 'otz' ),
				'total_users'     => __( '總會員數', 'otz' ),
				'imported'        => __( '已匯入', 'otz' ),
				'matched'         => __( '已配對', 'otz' ),
				'updated'         => __( '已更新', 'otz' ),
				'error'           => __( '同步時發生錯誤', 'otz' ),
				'line_connecting' => __( '連線到 LINE API...', 'otz' ),
				'line_fetching'   => __( '取得好友清單...', 'otz' ),
				'line_processing' => __( '處理好友資料...', 'otz' ),
				'line_completed'  => __( 'LINE 好友匯入完成！', 'otz' ),
				'line_error'      => __( 'LINE 好友匯入失敗', 'otz' ),
				'skipped'         => __( '重複匯入', 'otz' ),
			),
		);

		// 載入會員綁定編輯 JavaScript
		$select_deps = array( 'jquery' );
		if ( class_exists( 'WooCommerce' ) ) {
			$select_deps[] = 'selectWoo';
		} else {
			$select_deps[] = 'select2';
		}

		wp_enqueue_script(
			'otz-friends-user-binding',
			OTZ_PLUGIN_URL . 'assets/js/friends/user-binding.js',
			$select_deps,
			'1.0.03',
			true
		);

		// 傳遞到兩個腳本
		wp_localize_script( 'otz-friends-member-sync', 'otz_friends_sync', $localized_data );
		wp_localize_script( 'otz-friends-line-sync', 'otz_friends_sync', $localized_data );

		// 為訊息匯入腳本傳遞本地化資料
		wp_localize_script(
			'otz-message-import',
			'otz_message_import',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'otz_import_messages' ),
				'messages' => array(
					'invalid_file_type'    => __( '請選擇 CSV 檔案', 'otz' ),
					'file_too_large'       => __( '檔案大小不能超過 10MB', 'otz' ),
					'preview_failed'       => __( '預覽失敗', 'otz' ),
					'import_failed'        => __( '匯入失敗', 'otz' ),
					'no_messages_selected' => __( '請至少選擇一則訊息', 'otz' ),
					'cancel_confirm'       => __( '匯入正在進行中，確定要取消嗎？', 'otz' ),
					'preparing'            => __( '準備中...', 'otz' ),
					'importing'            => __( '正在匯入訊息...', 'otz' ),
					'completed'            => __( '匯入完成！', 'otz' ),
					'total_messages'       => __( '總訊息數', 'otz' ),
					'user_messages'        => __( '好友訊息', 'otz' ),
					'account_messages'     => __( '官方帳號訊息', 'otz' ),
					'duplicate'            => __( '重複', 'otz' ),
					'new_message'          => __( '新訊息', 'otz' ),
					'total_processed'      => __( '總處理數', 'otz' ),
					'imported'             => __( '已匯入', 'otz' ),
					'skipped'              => __( '重複匯入', 'otz' ),
					'errors'               => __( '錯誤', 'otz' ),
				),
			)
		);

		// 為會員綁定腳本傳遞本地化資料
		wp_localize_script(
			'otz-friends-user-binding',
			'otz_user_binding',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'otz_update_user_binding' ),
				'search_nonce' => wp_create_nonce( 'otz_search_users' ),
				'placeholder'  => __( '搜尋會員...', 'otz' ),
				'messages'     => array(
					'input_too_short' => __( '請輸入至少 1 個字元', 'otz' ),
					'searching'       => __( '搜尋中...', 'otz' ),
					'no_results'      => __( '找不到符合的會員', 'otz' ),
					'error_loading'   => __( '載入時發生錯誤', 'otz' ),
					'edit_tooltip'    => __( '編輯會員綁定', 'otz' ),
				),
			)
		);

		// 為好友重新整理腳本傳遞本地化資料
		wp_localize_script(
			'otz-friends-refresh',
			'otz_friends_refresh',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'messages' => array(
					'confirm'    => __( '確定要重新取得好友資料嗎？', 'otz' ),
					'processing' => __( '正在更新資料...', 'otz' ),
					'success'    => __( '好友資料已更新', 'otz' ),
					'error'      => __( '更新失敗', 'otz' ),
				),
			)
		);
	}
}
