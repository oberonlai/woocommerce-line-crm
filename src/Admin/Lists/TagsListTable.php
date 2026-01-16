<?php
/**
 * OrderChatz Tags List Table
 *
 * 處理標籤管理頁面的 WP_List_Table 實作
 *
 * @package OrderChatz\Admin\Lists
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Lists;

use OrderChatz\Database\Tag;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * 標籤列表表格類別
 *
 * 繼承 WordPress 內建的 WP_List_Table 來實作標籤管理介面
 */
class TagsListTable extends \WP_List_Table {

	/**
	 * WordPress 資料庫物件
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * 使用者標籤資料表名稱
	 *
	 * @var string
	 */
	private $user_tags_table_name;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb                 = $wpdb;
		$this->user_tags_table_name = $wpdb->prefix . 'otz_user_tags';

		parent::__construct(
			array(
				'singular' => __( '標籤', 'otz' ),
				'plural'   => __( '標籤', 'otz' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * 取得資料表格的欄位
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'tag_name'    => __( '標籤名稱', 'otz' ),
			'usage_count' => __( '好友人數', 'otz' ),
			'created_at'  => __( '建立時間', 'otz' ),
		);
	}

	/**
	 * 取得可排序的欄位
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'tag_name'    => array( 'tag_name', true ),
			'usage_count' => array( 'usage_count', false ),
			'created_at'  => array( 'created_at', false ),
		);
	}

	/**
	 * 取得批量操作選項
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( '刪除', 'otz' ),
		);
	}

	/**
	 * 處理批量操作
	 */
	public function process_bulk_action() {
		if ( 'delete' === $this->current_action() ) {
			$tag_names = isset( $_POST['tag'] ) ? $_POST['tag'] : array();

			if ( ! empty( $tag_names ) ) {
				check_admin_referer( 'bulk-' . $this->_args['plural'] );
				$this->delete_tags( $tag_names );
			}
		}
	}

	/**
	 * 準備要顯示的項目
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'created_at';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'desc';

		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

		$total_items = $this->get_tags_count( $search );
		$this->items = $this->get_tags( $offset, $per_page, $orderby, $order, $search );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * 取得標籤資料
	 *
	 * @param int    $offset
	 * @param int    $per_page
	 * @param string $orderby
	 * @param string $order
	 * @param string $search
	 * @return array
	 */
	private function get_tags( $offset, $per_page, $orderby, $order, $search = '' ) {
		$tag_db = new Tag();
		$all_tags = $tag_db->get_all_tags();

		// 搜尋過濾.
		if ( ! empty( $search ) ) {
			$all_tags = array_filter(
				$all_tags,
				function( $tag ) use ( $search ) {
					return stripos( $tag['tag_name'], $search ) !== false;
				}
			);
		}

		// 排序.
		$valid_orderby = array( 'tag_name', 'usage_count', 'created_at' );
		$orderby       = in_array( $orderby, $valid_orderby ) ? $orderby : 'created_at';
		$order         = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		usort(
			$all_tags,
			function( $a, $b ) use ( $orderby, $order ) {
				$result = 0;
				if ( $orderby === 'usage_count' ) {
					$result = $a['user_count'] - $b['user_count'];
				} elseif ( $orderby === 'tag_name' ) {
					$result = strcmp( $a['tag_name'], $b['tag_name'] );
				} elseif ( $orderby === 'created_at' ) {
					$result = strcmp( $a['created_at'], $b['created_at'] );
				}
				return $order === 'ASC' ? $result : -$result;
			}
		);

		// 分頁.
		$paged_tags = array_slice( $all_tags, $offset, $per_page );

		// 格式化為 WP_List_Table 需要的格式.
		return array_map(
			function( $tag ) {
				return array(
					'tag_name'    => $tag['tag_name'],
					'usage_count' => $tag['user_count'], // 使用 user_count 而非 total_usage.
					'created_at'  => $tag['created_at'],
				);
			},
			$paged_tags
		);
	}

	/**
	 * 取得標籤總數
	 *
	 * @param string $search
	 * @return int
	 */
	private function get_tags_count( $search = '' ) {
		$tag_db   = new Tag();
		$all_tags = $tag_db->get_all_tags();

		// 搜尋過濾.
		if ( ! empty( $search ) ) {
			$all_tags = array_filter(
				$all_tags,
				function( $tag ) use ( $search ) {
					return stripos( $tag['tag_name'], $search ) !== false;
				}
			);
		}

		return count( $all_tags );
	}

	/**
	 * 刪除標籤
	 *
	 * @param array $tag_names
	 */
	private function delete_tags( $tag_names ) {
		if ( empty( $tag_names ) ) {
			return;
		}

		$tag_names = array_map( 'sanitize_text_field', $tag_names );
		$tag_db    = new Tag();

		$success_count = 0;
		$failed_count  = 0;

		foreach ( $tag_names as $tag_name ) {
			$result = $tag_db->delete_tag( $tag_name );
			if ( $result ) {
				$success_count++;
			} else {
				$failed_count++;
			}
		}

		if ( $success_count > 0 ) {
			add_action(
				'admin_notices',
				function() use ( $success_count ) {
					echo '<div class="notice notice-success is-dismissible">';
					/* translators: %d: 成功刪除的標籤數量. */
					echo '<p>' . sprintf( __( '成功刪除 %d 個標籤', 'otz' ), $success_count ) . '</p>';
					echo '</div>';
				}
			);
		}

		if ( $failed_count > 0 ) {
			add_action(
				'admin_notices',
				function() use ( $failed_count ) {
					echo '<div class="notice notice-error is-dismissible">';
					/* translators: %d: 刪除失敗的標籤數量. */
					echo '<p>' . sprintf( __( '刪除 %d 個標籤失敗', 'otz' ), $failed_count ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * 預設的欄位顯示方法
	 *
	 * @param array  $item
	 * @param string $column_name
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'created_at':
				return mysql2date( 'Y-m-d H:i', $item[ $column_name ] );
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * 複選框欄位
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="tag[]" value="%s" />', esc_attr( $item['tag_name'] ) );
	}

	/**
	 * 標籤名稱欄位（包含操作連結）
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_tag_name( $item ) {
		$delete_nonce = wp_create_nonce( 'orderchatz_admin_action' );
		$delete_url   = admin_url( 'admin.php?page=otz-tags&action=delete&tag_name=' . urlencode( $item['tag_name'] ) . '&_wpnonce=' . $delete_nonce );

		$actions = array(
			'edit'   => sprintf(
				'<a href="#" class="edit-tag-link" data-tag-name="%s">%s</a>',
				esc_attr( $item['tag_name'] ),
				__( '編輯', 'otz' )
			),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( '確定要刪除這個標籤嗎？', 'otz' ) ),
				__( '刪除', 'otz' )
			),
		);

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $item['tag_name'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * 好友人數欄位（可點擊查看好友清單）
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_usage_count( $item ) {
		$count     = (int) $item['usage_count'];
		$tag_name  = $item['tag_name'];
		$css_class = $count > 0 ? 'view-tag-users' : 'view-tag-users no-users';

		return sprintf(
			'<a href="#" class="%s" data-tag-name="%s">%d</a>',
			esc_attr( $css_class ),
			esc_attr( $tag_name ),
			$count
		);
	}

	/**
	 * 顯示沒有資料時的訊息
	 */
	public function no_items() {
		_e( '找不到標籤', 'otz' );
	}

	/**
	 * 顯示搜尋框
	 *
	 * @param string $text
	 * @param string $input_id
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . $text . ':</label>';
		echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . _admin_search_query() . '" placeholder="' . esc_attr__( '搜尋標籤...', 'otz' ) . '" />';
		submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) );
		echo '</p>';
	}
}
