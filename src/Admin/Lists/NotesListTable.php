<?php
/**
 * OrderChatz Notes List Table
 *
 * 處理備註管理頁面的 WP_List_Table 實作
 *
 * @package OrderChatz\Admin\Lists
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Lists;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * 備註列表表格類別
 *
 * 繼承 WordPress 內建的 WP_List_Table 來實作備註管理介面
 */
class NotesListTable extends \WP_List_Table {

	/**
	 * WordPress 資料庫物件
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * 使用者資料表名稱
	 *
	 * @var string
	 */
	private $users_table_name;

	/**
	 * 使用者備註資料表名稱
	 *
	 * @var string
	 */
	private $user_notes_table_name;

	/**
	 * 群組資料表名稱
	 *
	 * @var string
	 */
	private $groups_table_name;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb                  = $wpdb;
		$this->users_table_name      = $wpdb->prefix . 'otz_users';
		$this->user_notes_table_name = $wpdb->prefix . 'otz_user_notes';
		$this->groups_table_name     = $wpdb->prefix . 'otz_groups';

		parent::__construct(
			array(
				'singular' => __( '備註記錄', 'otz' ),
				'plural'   => __( '備註記錄', 'otz' ),
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
			'note_content'    => __( '備註內容', 'otz' ),
			'original_post'   => __( '原始貼文', 'otz' ),
			'category'        => __( '分類', 'otz' ),
			'related_product' => __( '關聯商品', 'otz' ),
			'display_name'    => __( '好友/群組', 'otz' ),
			'created_by'      => __( '建立者', 'otz' ),
			'created_at'      => __( '建立時間', 'otz' ),
			'line_user_id'    => __( 'LINE 使用者 ID', 'otz' ),
		);
	}

	/**
	 * 取得可排序的欄位
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'display_name'    => array( 'display_name', true ),
			'category'        => array( 'category', true ),
			'note_content'    => array( 'note', false ),
			'created_at'      => array( 'created_at', false ),
			'related_product' => array( 'related_product', false ),
		);
	}

	/**
	 * 準備要顯示的項目
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array( 'line_user_id' ); // 隱藏 LINE User ID 欄位
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'created_at';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'desc';

		$search          = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$category_filter = isset( $_GET['category_filter'] ) ? sanitize_text_field( $_GET['category_filter'] ) : '';
		$product_filter  = isset( $_GET['product_filter'] ) ? sanitize_text_field( $_GET['product_filter'] ) : '';

		$total_items = $this->get_notes_count( $search, $category_filter, $product_filter );
		$this->items = $this->get_notes( $offset, $per_page, $orderby, $order, $search, $category_filter, $product_filter );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * 取得備註資料
	 *
	 * @param int    $offset
	 * @param int    $per_page
	 * @param string $orderby
	 * @param string $order
	 * @param string $search
	 * @param string $category_filter
	 * @param string $product_filter
	 * @return array
	 */
	private function get_notes( $offset, $per_page, $orderby, $order, $search = '', $category_filter = '', $product_filter = '' ) {
		$where_conditions = array();
		$params           = array();

		if ( ! empty( $search ) ) {
			$where_conditions[] = '(u.display_name LIKE %s OR g.group_name LIKE %s OR n.note LIKE %s OR u.line_user_id LIKE %s OR n.group_id LIKE %s)';
			$search_term        = '%' . $this->wpdb->esc_like( $search ) . '%';
			$params             = array( $search_term, $search_term, $search_term, $search_term, $search_term );
		}

		// 分類篩選
		if ( ! empty( $category_filter ) ) {
			if ( $category_filter === '_no_category' ) {
				$where_conditions[] = '(n.category IS NULL OR n.category = "")';
			} else {
				$where_conditions[] = 'n.category = %s';
				$params[]           = $category_filter;
			}
		}

		// 商品篩選
		if ( ! empty( $product_filter ) ) {
			if ( $product_filter === '_no_product' ) {
				$where_conditions[] = '(n.related_product_id IS NULL OR n.related_product_id = 0)';
			} else {
				$where_conditions[] = 'n.related_product_id = %d';
				$params[]           = intval( $product_filter );
			}
		}

		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

		$valid_orderby = array( 'display_name', 'created_at', 'note' );
		$orderby       = in_array( $orderby, $valid_orderby ) ? $orderby : 'created_at';
		$order         = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = "
            SELECT
                n.id,
                n.line_user_id,
                n.source_type,
                n.group_id,
                n.note,
                n.category,
                n.related_product_id,
                n.related_message,
                n.created_by,
                n.created_at,
                u.display_name,
                u.wp_user_id,
                g.group_name,
                g.group_avatar
            FROM {$this->user_notes_table_name} n
            LEFT JOIN {$this->users_table_name} u ON n.line_user_id = u.line_user_id AND n.source_type = 'user'
            LEFT JOIN {$this->groups_table_name} g ON n.group_id = g.group_id AND n.source_type IN ('group', 'room')
            {$where_clause}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ";

		$params[] = $per_page;
		$params[] = $offset;

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * 取得備註總數
	 *
	 * @param string $search
	 * @param string $category_filter
	 * @param string $product_filter
	 * @return int
	 */
	private function get_notes_count( $search = '', $category_filter = '', $product_filter = '' ) {
		$where_conditions = array();
		$params           = array();

		if ( ! empty( $search ) ) {
			$where_conditions[] = '(u.display_name LIKE %s OR g.group_name LIKE %s OR n.note LIKE %s OR u.line_user_id LIKE %s OR n.group_id LIKE %s)';
			$search_term        = '%' . $this->wpdb->esc_like( $search ) . '%';
			$params             = array( $search_term, $search_term, $search_term, $search_term, $search_term );
		}

		// 分類篩選
		if ( ! empty( $category_filter ) ) {
			if ( $category_filter === '_no_category' ) {
				$where_conditions[] = '(n.category IS NULL OR n.category = "")';
			} else {
				$where_conditions[] = 'n.category = %s';
				$params[]           = $category_filter;
			}
		}

		// 商品篩選
		if ( ! empty( $product_filter ) ) {
			if ( $product_filter === '_no_product' ) {
				$where_conditions[] = '(n.related_product_id IS NULL OR n.related_product_id = 0)';
			} else {
				$where_conditions[] = 'n.related_product_id = %d';
				$params[]           = intval( $product_filter );
			}
		}

		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

		$sql = "SELECT COUNT(n.id)
                FROM {$this->user_notes_table_name} n
                LEFT JOIN {$this->users_table_name} u ON n.line_user_id = u.line_user_id AND n.source_type = 'user'
                LEFT JOIN {$this->groups_table_name} g ON n.group_id = g.group_id AND n.source_type IN ('group', 'room')
                {$where_clause}";

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return (int) $this->wpdb->get_var( $sql );
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
			case 'line_user_id':
				return esc_html( $item[ $column_name ] );
			case 'display_name':
				return esc_html( $item[ $column_name ] ?: __( '無名稱', 'otz' ) );
			case 'note_content':
				return $this->get_notes_preview( $item['note'] );
			case 'original_post':
				return $this->get_original_post_content( $item['related_message'] );
			case 'category':
				return $this->get_category_select( $item );
			case 'created_by':
				return $this->get_created_by_info( $item['created_by'] );
			case 'created_at':
				return $item['created_at'] ?
					mysql2date( 'Y-m-d H:i', $item['created_at'] ) :
					__( '無記錄', 'otz' );
			case 'actions':
				return $this->get_note_actions( $item );
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * 取得建立者資訊
	 *
	 * @param int $created_by
	 * @return string
	 */
	private function get_created_by_info( $created_by ) {
		if ( empty( $created_by ) ) {
			return '<span class="description">' . __( '系統', 'otz' ) . '</span>';
		}

		$user = get_user_by( 'id', $created_by );
		if ( ! $user ) {
			return '<span class="description">' . __( '未知用戶', 'otz' ) . '</span>';
		}

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			admin_url( 'user-edit.php?user_id=' . $user->ID ),
			esc_html( $user->display_name )
		);
	}

	/**
	 * 取得備註預覽
	 *
	 * @param string $note
	 * @return string
	 */
	private function get_notes_preview( $note ) {
		if ( empty( $note ) ) {
			return '<span class="description">' . __( '無備註', 'otz' ) . '</span>';
		}

		$preview = strip_tags( $note );
		return sprintf(
			'<span class="notes-preview" title="%s">%s</span>',
			esc_attr( wp_strip_all_tags( $note ) ),
			esc_html( $preview )
		);
	}

	/**
	 * 取得原始貼文內容
	 *
	 * @param string $related_message JSON 格式的關聯訊息
	 * @return string
	 */
	private function get_original_post_content( $related_message ) {
		if ( empty( $related_message ) ) {
			return '<span class="description">' . __( '無原始貼文', 'otz' ) . '</span>';
		}

		$message_data = json_decode( $related_message, true );
		if ( ! $message_data || ! isset( $message_data['type'] ) || ! isset( $message_data['content'] ) ) {
			return '<span class="description">' . __( '無效資料', 'otz' ) . '</span>';
		}

		$type    = $message_data['type'];
		$content = $message_data['content'];

		switch ( $type ) {
			case 'text':
				// 限制150個字符
				$preview = mb_strlen( $content ) > 150 ? mb_substr( $content, 0, 150 ) . '...' : $content;
				return sprintf(
					'<div class="original-post-text" title="%s">
						<span>%s</span>
					</div>',
					esc_attr( $content ),
					esc_html( $preview )
				);

			case 'image':
				return sprintf(
					'<div class="original-post-image">
						<img src="%s" alt="%s" style="max-width: 60px; max-height: 40px; border-radius: 3px; vertical-align: middle;"
							 onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'inline\';">
						<span style="display: none; color: #666;">[圖片]</span>
					</div>',
					esc_url( $content ),
					esc_attr__( '原始貼文圖片', 'otz' )
				);

			case 'file':
				$filename = basename( $content );
				return sprintf(
					'<div class="original-post-file" title="%s">
						<span>%s</span>
					</div>',
					esc_attr( $content ),
					esc_html( $filename )
				);

			default:
				return sprintf(
					'<div class="original-post-unknown">
						<span>%s</span>
					</div>',
					esc_html__( '未知類型', 'otz' )
				);
		}
	}

	/**
	 * 備註內容欄位（包含操作連結）
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_note_content( $item ) {
		$edit_url = admin_url( 'admin.php?page=otz-notes&action=edit&note_id=' . $item['id'] );

		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=otz-notes&action=delete_note&note_id=' . $item['id'] . '&line_user_id=' . urlencode( $item['line_user_id'] ) ),
			'orderchatz_admin_action'
		);

		$view_user_url = admin_url( 'admin.php?page=otz-notes&action=view&line_user_id=' . urlencode( $item['line_user_id'] ) );

		$actions = array(
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( '編輯', 'otz' ) ),
			'view_user' => sprintf( '<a href="%s">%s</a>', esc_url( $view_user_url ), __( '查看用戶', 'otz' ) ),
			'delete'    => sprintf(
				'<a href="%s"  onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( '確定要刪除這個備註嗎？', 'otz' ) ),
				__( '刪除', 'otz' )
			),
		);

		$note_preview = $this->get_notes_preview( $item['note'] );
		$edit_url     = admin_url( 'admin.php?page=otz-notes&action=edit&note_id=' . $item['id'] );

		return sprintf(
			'<strong><a href="%s" style="text-decoration: none; color: inherit;">%s</a></strong>%s',
			esc_url( $edit_url ),
			$note_preview,
			$this->row_actions( $actions )
		);
	}

	/**
	 * 顯示名稱欄位
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_display_name( $item ) {
		// 根據來源類型決定顯示名稱和連結.
		if ( $item['source_type'] === 'group' || $item['source_type'] === 'room' ) {
			// 群組備註.
			$display_name = $item['group_name'] ?: __( '未命名群組', 'otz' );
			$view_url     = admin_url( 'admin.php?page=otz-notes&action=view&group_id=' . urlencode( $item['group_id'] ) );
		} else {
			// 個人備註.
			$display_name = $item['display_name'] ?: __( '無名稱', 'otz' );
			$view_url     = admin_url( 'admin.php?page=otz-notes&action=view&line_user_id=' . urlencode( $item['line_user_id'] ) );
		}

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $view_url ),
			esc_html( $display_name )
		);
	}

	/**
	 * 顯示關聯商品欄位
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_related_product( $item ) {
		if ( empty( $item['related_product_id'] ) ) {
			return '<span class="description">-</span>';
		}

		$product_id = intval( $item['related_product_id'] );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return '<span class="description" style="color: #d63638;">' . __( '商品不存在', 'otz' ) . '</span>';
		}

		$edit_url     = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
		$product_name = $product->get_name();

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $edit_url ),
			esc_html( $product_name )
		);
	}

	/**
	 * 顯示沒有資料時的訊息
	 */
	public function no_items() {
		_e( '目前沒有任何備註記錄', 'otz' );
	}

	/**
	 * 取得備註操作按鈕
	 *
	 * @param array $item
	 * @return string
	 */
	private function get_note_actions( $item ) {
		$edit_url   = admin_url( 'admin.php?page=otz-notes&action=edit&note_id=' . $item['id'] );
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=otz-notes&action=delete_note&note_id=' . $item['id'] . '&line_user_id=' . urlencode( $item['line_user_id'] ) ),
			'orderchatz_admin_action'
		);

		$actions = array();

		$actions[] = sprintf(
			'<a href="%s" class="button button-small" title="%s"><span class="dashicons dashicons-edit"></span></a>',
			esc_url( $edit_url ),
			esc_attr__( '編輯', 'otz' )
		);

		$actions[] = sprintf(
			'<a href="%s" class="button button-small" style="color: #d63638; border-color: #d63638;" title="%s" onclick="return confirm(\'%s\')"><span class="dashicons dashicons-trash"></span></a>',
			esc_url( $delete_url ),
			esc_attr__( '刪除', 'otz' ),
			esc_js( __( '確定要刪除這個備註嗎？', 'otz' ) )
		);

		return '<div style="display: flex; gap: 4px;">' . implode( '', $actions ) . '</div>';
	}

	/**
	 * 取得分類顯示和編輯功能
	 *
	 * @param array $item
	 * @return string
	 */
	private function get_category_select( $item ) {
		$current_category = $item['category'] ?? '';
		$note_id          = $item['id'];

		$html = '<div class="category-display-wrapper" data-note-id="' . esc_attr( $note_id ) . '">';

		// 顯示模式 - 顯示帶顏色的分類標籤
		if ( ! empty( $current_category ) ) {
			$category_color = $this->getCategoryColor( $current_category );
			$html          .= '<span class="category-display-tag" style="'
				. 'display: inline-block; '
				. 'padding: 2px 8px; '
				. 'border-radius: 3px; '
				. 'font-size: 12px; '
				. 'color: #fff; '
				. 'background-color: ' . esc_attr( $category_color ) . ';'
				. '">' . esc_html( $current_category ) . '</span>';
		} else {
			$html .= '<span class="category-display" style="color: #999;">' . __( '無分類', 'otz' ) . '</span>';
		}

		// 編輯按鈕
		$html .= ' <a href="#" class="category-edit-btn" data-note-id="' . esc_attr( $note_id ) . '" title="' . esc_attr__( '編輯分類', 'otz' ) . '" ><span class="dashicons dashicons-edit" style="font-size: 14px; line-height: 1.2;"></span></a>';

		// 編輯模式（隱藏）
		$html .= '<div class="category-edit-form" style="display: none;">';
		$html .= '<select class="note-category-select" data-note-id="' . esc_attr( $note_id ) . '" style="min-width: 150px;">';
		$html .= '<option value=""' . selected( '', $current_category, false ) . '>' . __( '無分類', 'otz' ) . '</option>';

		// 如果有當前分類，添加到選項中
		if ( ! empty( $current_category ) ) {
			$html .= '<option value="' . esc_attr( $current_category ) . '" selected>' . esc_html( $current_category ) . '</option>';
		}

		$html .= '</select>';
		$html .= ' <div style="margin-top:5px"><a href="#" class="category-save-btn button button-small" style="">' . __( '儲存', 'otz' ) . '</a>';
		$html .= ' <a href="#" class="category-cancel-btn button button-small" style="margin-left: 2px;">' . __( '取消', 'otz' ) . '</a></div>';
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * 取得可用的分類選項
	 *
	 * @return array
	 */
	private function get_available_categories() {
		return array();
	}

	/**
	 * 取得所有有被備註關聯的商品
	 *
	 * @return array 商品 ID => 商品名稱的陣列
	 */
	public function get_all_products() {
		// 查詢所有有關聯商品的備註
		$sql = "SELECT DISTINCT related_product_id
                FROM {$this->user_notes_table_name}
                WHERE related_product_id IS NOT NULL
                AND related_product_id > 0
                ORDER BY related_product_id";

		$product_ids = $this->wpdb->get_col( $sql );

		if ( empty( $product_ids ) ) {
			return array();
		}

		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[ $product_id ] = $product->get_name();
			}
		}

		return $products;
	}

	/**
	 * 根據分類名稱取得預設顏色
	 *
	 * @param string $category_name
	 * @return string
	 */
	private function getCategoryColor( $category_name ) {
		if ( empty( $category_name ) ) {
			return '#f5f5f5';
		}

		// 首先嘗試從分類設定中取得顏色
		$categories = get_option( 'otz_note_categories', array() );
		foreach ( $categories as $category ) {
			if ( is_array( $category ) && $category['name'] === $category_name ) {
				return $category['color'];
			}
		}

		// 如果找不到，使用預設顏色演算法
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
		$hash  = crc32( $category_name );
		$index = abs( $hash ) % count( $default_colors );

		return $default_colors[ $index ];
	}

	/**
	 * 取得分類標籤
	 *
	 * @param string $category
	 * @return string
	 */
	private function get_category_label( $category ) {
		if ( empty( $category ) ) {
			return __( '無分類', 'otz' );
		}
		return $category;
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
		echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . _admin_search_query() . '" placeholder="' . esc_attr__( '請輸入關鍵字', 'otz' ) . '" />';
		submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) );
		echo '</p>';
	}
}
