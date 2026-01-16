<?php
/**
 * OrderChatz 備註頁面渲染器
 *
 * 處理客戶備註頁面的內容渲染
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Admin\Lists\NotesListTable;

/**
 * 備註頁面渲染器類別
 *
 * 渲染客戶備註管理相關功能的管理介面
 */
class Notes extends PageRenderer {

	/**
	 * 備註列表表格實例
	 *
	 * @var NotesListTable
	 */
	private $notes_table;

	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( '備註', 'otz' ),
			'otz-notes',
			true // 備註頁面有頁籤導航
		);

		$this->notes_table = new NotesListTable();
	}

	/**
	 * 渲染備註頁面內容
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		$this->handleActions();

		echo '<div class="orderchatz-notes-page">';

		// 處理查看備註的操作
		$action       = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$line_user_id = isset( $_GET['line_user_id'] ) ? sanitize_text_field( $_GET['line_user_id'] ) : '';

		switch ( $action ) {
			case 'view':
				$this->renderNotesView( $line_user_id );
				break;
			case 'edit':
				$note_id = isset( $_GET['note_id'] ) ? intval( $_GET['note_id'] ) : 0;
				$this->renderNoteEdit( $note_id );
				break;
			default:
				$this->renderNotesList();
				break;
		}

		echo '</div>';
	}

	/**
	 * 處理各種操作
	 */
	private function handleActions() {
		if ( isset( $_POST['action'] ) ) {
			$action = sanitize_text_field( $_POST['action'] );

			switch ( $action ) {
				case 'add_note':
					$this->handleAddNote();
					break;
				case 'edit_note':
					$this->handleEditNote();
					break;
				case 'update_note':
					$this->handleUpdateNote();
					break;
				case 'delete_note':
					$this->handleDeleteNote();
					break;
			}
		}

		if ( isset( $_GET['action'] ) ) {
			$action = sanitize_text_field( $_GET['action'] );

			if ( $action === 'delete_note' && isset( $_GET['note_id'] ) && isset( $_GET['_wpnonce'] ) ) {
				$this->handleDeleteNote();
			}
		}
	}

	/**
	 * 處理新增備註
	 */
	private function handleAddNote() {
		check_admin_referer( 'orderchatz_admin_action' );

		$line_user_id = sanitize_text_field( $_POST['line_user_id'] );

		$note = sanitize_textarea_field( $_POST['notes'] );

		if ( empty( $line_user_id ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( 'LINE User ID 不能為空', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		if ( empty( $note ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '備註內容不能為空', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		global $wpdb;
		$user_notes_table = $wpdb->prefix . 'otz_user_notes';

		$result = $wpdb->insert(
			$user_notes_table,
			array(
				'line_user_id' => $line_user_id,
				'note'         => $note,
				'created_by'   => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s' )
		);

		if ( $result !== false ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes&action=view&line_user_id=' . urlencode( $line_user_id ) . '&message=note_added' ) );
			exit;
		} else {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '新增備註失敗', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * 處理編輯備註
	 */
	private function handleEditNote() {
		check_admin_referer( 'orderchatz_admin_action' );

		$note_id      = intval( $_POST['note_id'] );
		$line_user_id = sanitize_text_field( $_POST['line_user_id'] );
		$note_content = sanitize_textarea_field( $_POST['note_content'] );

		if ( empty( $note_id ) || empty( $line_user_id ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '參數錯誤', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		if ( empty( $note_content ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '備註內容不能為空', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		global $wpdb;
		$user_notes_table = $wpdb->prefix . 'otz_user_notes';

		$result = $wpdb->update(
			$user_notes_table,
			array( 'note' => $note_content ),
			array(
				'id'           => $note_id,
				'line_user_id' => $line_user_id,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		if ( $result !== false ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes&action=view&line_user_id=' . urlencode( $line_user_id ) . '&message=note_updated' ) );
			exit;
		} else {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '編輯備註失敗', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * 處理更新備註
	 */
	private function handleUpdateNote() {
		$note_id = intval( $_POST['note_id'] );
		check_admin_referer( 'orderchatz_admin_action' );

		$note_content = sanitize_textarea_field( $_POST['note_content'] );

		if ( empty( $note_id ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '參數錯誤', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		if ( empty( $note_content ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '備註內容不能為空', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		global $wpdb;
		$user_notes_table = $wpdb->prefix . 'otz_user_notes';

		$result = $wpdb->update(
			$user_notes_table,
			array( 'note' => $note_content ),
			array( 'id' => $note_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes&message=note_updated' ) );
			exit;
		} else {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '更新備註失敗', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * 處理刪除備註
	 */
	private function handleDeleteNote() {
		$note_id      = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : intval( $_GET['note_id'] );
		$line_user_id = isset( $_POST['line_user_id'] ) ? sanitize_text_field( $_POST['line_user_id'] ) : sanitize_text_field( $_GET['line_user_id'] );
		$nonce        = isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : $_GET['_wpnonce'];

		if ( ! wp_verify_nonce( $nonce, 'orderchatz_admin_action' ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		if ( empty( $note_id ) || empty( $line_user_id ) ) {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '參數錯誤', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
			return;
		}

		global $wpdb;
		$user_notes_table = $wpdb->prefix . 'otz_user_notes';

		$result = $wpdb->delete(
			$user_notes_table,
			array(
				'id'           => $note_id,
				'line_user_id' => $line_user_id,
			),
			array( '%d', '%s' )
		);

		if ( $result !== false ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes&message=note_deleted' ) );
			exit;
		} else {
			add_action(
				'admin_notices',
				function() {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . __( '刪除備註失敗', 'otz' ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * 渲染備註列表
	 */
	private function renderNotesList() {
		include OTZ_PLUGIN_DIR . 'views/admin/notes/list.php';
	}

	/**
	 * 渲染備註查看/編輯頁面
	 *
	 * @param string $line_user_id
	 */
	private function renderNotesView( $line_user_id ) {
		if ( empty( $line_user_id ) ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes' ) );
			exit;
		}

		// 顯示操作訊息
		$this->showMessages();

		global $wpdb;
		$users_table      = $wpdb->prefix . 'otz_users';
		$user_notes_table = $wpdb->prefix . 'otz_user_notes';

		$user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$users_table} WHERE line_user_id = %s",
				$line_user_id
			)
		);

		if ( ! $user ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes&message=user_not_found' ) );
			exit;
		}

		// 取得該使用者的所有備註
		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT n.*, u.display_name as created_by_name 
             FROM {$user_notes_table} n
             LEFT JOIN " . $wpdb->users . ' u ON n.created_by = u.ID
             WHERE n.line_user_id = %s
             ORDER BY n.created_at DESC',
				$line_user_id
			)
		);

		include OTZ_PLUGIN_DIR . 'views/admin/notes/view.php';
	}

	/**
	 * 渲染備註編輯頁面
	 *
	 * @param int $note_id
	 */
	private function renderNoteEdit( $note_id ) {
		if ( empty( $note_id ) ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes' ) );
			exit;
		}

		// 顯示操作訊息
		$this->showMessages();

		global $wpdb;
		$user_notes_table = $wpdb->prefix . 'otz_user_notes';
		$users_table      = $wpdb->prefix . 'otz_users';

		$note = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT n.*, u.display_name 
				 FROM {$user_notes_table} n
				 LEFT JOIN {$users_table} u ON n.line_user_id = u.line_user_id
				 WHERE n.id = %d",
				$note_id
			)
		);

		if ( ! $note ) {
			wp_redirect( admin_url( 'admin.php?page=otz-notes&message=note_not_found' ) );
			exit;
		}

		include OTZ_PLUGIN_DIR . 'views/admin/notes/edit.php';
	}

	/**
	 * 顯示操作訊息
	 */
	private function showMessages() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message = sanitize_text_field( $_GET['message'] );
		$class   = 'notice notice-success is-dismissible';
		$text    = '';

		switch ( $message ) {
			case 'note_added':
				$text = __( '備註新增成功', 'otz' );
				break;
			case 'note_updated':
				$text = __( '備註更新成功', 'otz' );
				break;
			case 'note_deleted':
				$text = __( '備註刪除成功', 'otz' );
				break;
			case 'user_not_found':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '找不到指定的使用者', 'otz' );
				break;
			case 'note_not_found':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '找不到指定的備註', 'otz' );
				break;
			case 'error':
				$class = 'notice notice-error is-dismissible';
				$text  = __( '操作失敗', 'otz' );
				break;
		}

		if ( $text ) {
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
		}
	}


	/**
	 * 取得所有分類
	 *
	 * @return array
	 */
	public function get_all_categories() {
		$categories = get_option( 'otz_note_categories', array() );
		
		// 確保返回的是陣列並且已排序
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		
		// 轉換為分類名稱陣列（向後兼容）
		$category_names = array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) ) {
				$category_names[] = $category['name'];
			} else {
				$category_names[] = $category;
			}
		}
		
		sort( $category_names );
		
		return $category_names;
	}

	/**
	 * 取得所有分類（包含顏色資訊）
	 *
	 * @return array
	 */
	public function get_all_categories_with_colors() {
		$categories = get_option( 'otz_note_categories', array() );
		
		// 確保返回的是陣列
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}
		
		// 如果沒有分類，建立一些預設分類
		if ( empty( $categories ) ) {
			$this->initializeDefaultCategories();
			$categories = get_option( 'otz_note_categories', array() );
		}
		
		$result = array();
		foreach ( $categories as $category ) {
			if ( is_array( $category ) ) {
				$result[] = $category;
			} else {
				// 向後兼容：為舊的字串格式分類分配預設顏色
				$result[] = array(
					'name' => $category,
					'color' => $this->getDefaultColor( $category )
				);
			}
		}
		
		// 依分類名稱排序
		usort( $result, function( $a, $b ) {
			return strcmp( $a['name'], $b['name'] );
		} );
		
		return $result;
	}

	/**
	 * 初始化預設分類
	 */
	private function initializeDefaultCategories() {
		$default_categories = array(
			array( 'name' => '重要', 'color' => '#e74c3c' ),
			array( 'name' => '待處理', 'color' => '#f39c12' ),
			array( 'name' => '已完成', 'color' => '#2ecc71' ),
			array( 'name' => '備忘', 'color' => '#3498db' ),
		);
		update_option( 'otz_note_categories', $default_categories );
	}

	/**
	 * 根據分類名稱取得預設顏色
	 * 
	 * @param string $category_name
	 * @return string
	 */
	private function getDefaultColor( $category_name ) {
		$default_colors = array(
			'#3498db', // 藍色
			'#e74c3c', // 紅色
			'#2ecc71', // 綠色
			'#f39c12', // 橙色
			'#9b59b6', // 紫色
			'#1abc9c', // 青綠色
			'#f1c40f', // 黃色
			'#e67e22', // 深橙色
			'#8e44ad', // 深紫色
			'#27ae60', // 深綠色
			'#2980b9', // 深藍色
			'#c0392b', // 深紅色
		);

		// 使用分類名稱的 hash 來決定顏色，確保同名分類總是得到相同顏色
		$hash = crc32( $category_name );
		$index = abs( $hash ) % count( $default_colors );
		
		return $default_colors[$index];
	}
}
