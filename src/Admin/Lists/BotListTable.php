<?php
/**
 * OrderChatz Bot Skill List Table
 *
 * 處理 Bot Skill 管理頁面的 WP_List_Table 實作
 *
 * @package OrderChatz\Admin\Lists
 * @since 1.1.6
 */

namespace OrderChatz\Admin\Lists;

use OrderChatz\Database\Bot\Bot;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Bot 列表表格類別
 *
 * 繼承 WordPress 內建的 WP_List_Table 來實作 Bot 管理介面
 */
class BotListTable extends \WP_List_Table {

	/**
	 * Bot 資料庫操作類別
	 *
	 * @var Bot
	 */
	private $bot;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->bot = new Bot( $wpdb );

		parent::__construct(
			array(
				'singular' => __( 'Bot', 'otz' ),
				'plural'   => 'bots',
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
			'cb'             => '<input type="checkbox" />',
			'name'           => __( 'Name', 'otz' ),
			'keywords'       => __( 'Keywords', 'otz' ),
			'action_type'    => __( 'Action Type', 'otz' ),
			'function_tools' => __( 'Function Tools', 'otz' ),
			'quick_replies'  => __( 'Quick Replies', 'otz' ),
			'status'         => __( 'Status', 'otz' ),
			'priority'       => __( 'Priority', 'otz' ),
			'trigger_count'  => __( 'Trigger Count', 'otz' ),
		);
	}

	/**
	 * 取得可排序的欄位
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name'          => array( 'name', true ),
			'priority'      => array( 'priority', false ),
			'trigger_count' => array( 'trigger_count', false ),
		);
	}

	/**
	 * 取得批量操作選項
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'otz' ),
		);
	}

	/**
	 * 處理批量操作
	 */
	public function process_bulk_action() {
		if ( 'delete' === $this->current_action() ) {
			$bot_ids = isset( $_POST['bots'] ) ? $_POST['bots'] : array();

			if ( ! empty( $bot_ids ) ) {
				check_admin_referer( 'bulk-' . $this->_args['plural'] );
				$this->delete_bots( $bot_ids );
			}
		}
	}

	/**
	 * 批次刪除 Bot
	 *
	 * @param array $bot_ids Bot ID 陣列.
	 */
	private function delete_bots( $bot_ids ) {
		if ( empty( $bot_ids ) ) {
			return;
		}

		$bot_ids = array_map( 'intval', $bot_ids );
		$deleted = 0;

		foreach ( $bot_ids as $id ) {
			if ( $this->bot->delete_bot( $id ) ) {
				$deleted++;
			}
		}

		if ( $deleted > 0 ) {
			add_action(
				'admin_notices',
				function() use ( $deleted ) {
					echo '<div class="notice notice-success is-dismissible">';
					/* translators: %d: number of deleted bot skills */
					echo '<p>' . sprintf( __( 'Successfully deleted %d bot(s)', 'otz' ), $deleted ) . '</p>';
					echo '</div>';
				}
			);
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

		$orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'priority';
		$order   = ( ! empty( $_GET['order'] ) ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'asc';

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// 使用 Bot 類別取得資料.
		$args = array(
			'order_by' => $orderby,
			'order'    => strtoupper( $order ),
			'limit'    => 1000, // 先取得所有資料進行搜尋和計數.
			'offset'   => 0,
		);

		$all_items = $this->bot->get_all_bots( $args );

		// 如果有搜尋條件，進行過濾.
		if ( ! empty( $search ) ) {
			$all_items = array_filter(
				$all_items,
				function( $item ) use ( $search ) {
					return stripos( $item['name'], $search ) !== false;
				}
			);
		}

		$total_items = count( $all_items );

		// 分頁處理.
		$this->items = array_slice( $all_items, $offset, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * 預設的欄位顯示方法
	 *
	 * @param array  $item 資料項目.
	 * @param string $column_name 欄位名稱.
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'keywords':
				return $this->get_keywords_display( $item );
			case 'action_type':
				return $this->get_action_type_label( $item[ $column_name ] );
			case 'function_tools':
				return $this->get_function_tools_display( $item );
			case 'quick_replies':
				return $this->get_quick_replies_display( $item );
			case 'status':
				return $this->get_status_badge( $item[ $column_name ] );
			case 'priority':
				return esc_html( $item[ $column_name ] );
			case 'trigger_count':
				return esc_html( $item[ $column_name ] );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	/**
	 * 複選框欄位
	 *
	 * @param array $item 資料項目.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bots[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * 名稱欄位（包含操作連結）
	 *
	 * @param array $item 資料項目.
	 * @return string
	 */
	public function column_name( $item ) {
		$edit_url = admin_url( 'admin.php?page=otz-bot&action=edit&id=' . $item['id'] );

		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=otz-bot&action=delete&id=' . $item['id'] ),
			'delete_bot_' . $item['id']
		);

		$copy_url = wp_nonce_url(
			admin_url( 'admin.php?page=otz-bot&action=copy&id=' . $item['id'] ),
			'copy_bot_' . $item['id']
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Edit', 'otz' ) ),
			'copy'   => sprintf( '<a href="%s">%s</a>', esc_url( $copy_url ), __( 'Copy', 'otz' ) ),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this bot?', 'otz' ) ),
				__( 'Delete', 'otz' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item['name'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * 取得關鍵字顯示
	 *
	 * @param array $item 資料項目.
	 * @return string
	 */
	private function get_keywords_display( $item ) {
		if ( empty( $item['keywords'] ) ) {
			return '-';
		}

		$keywords = is_array( $item['keywords'] ) ? $item['keywords'] : array();

		if ( empty( $keywords ) ) {
			return '-';
		}
		$output = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
		// 顯示所有關鍵字.
		$keyword_tags = array_map(
			function( $keyword ) {
				return sprintf( '<span class="otz-keyword-tag" style="font-size: 12px; padding: 5px 7px; border: 1px solid #2271B1;border-radius: 5px; color:#2271B1">%s</span>', esc_html( $keyword ) );
			},
			$keywords
		);

		$output .= implode( '', $keyword_tags );
		$output .= '</div>';

		return $output;
	}

	/**
	 * 取得動作類型標籤
	 *
	 * @param string $type 動作類型.
	 * @return string
	 */
	private function get_action_type_label( $type ) {
		$labels = array(
			'ai'    => __( 'AI response', 'otz' ),
			'human' => __( 'Fixed content response', 'otz' ),
		);

		return isset( $labels[ $type ] ) ? esc_html( $labels[ $type ] ) : esc_html( $type );
	}

	/**
	 * 取得狀態標籤
	 *
	 * @param string $status 狀態.
	 * @return string
	 */
	private function get_status_badge( $status ) {
		$labels = array(
			'active'   => __( 'Active', 'otz' ),
			'inactive' => __( 'Inactive', 'otz' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$class = 'active' === $status ? 'otz-status-active' : 'otz-status-inactive';

		return sprintf(
			'<span class="otz-status-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * 取得快速回覆顯示
	 *
	 * @param array $item 機器人資料.
	 * @return string
	 */
	private function get_quick_replies_display( $item ) {
		$quick_replies = $item['quick_replies'] ?? array();

		// 如果沒有快速回覆，顯示「—」.
		if ( empty( $quick_replies ) || ! is_array( $quick_replies ) ) {
			return '<span style="color: #999;">—</span>';
		}

		$output = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';

		// 顯示所有快速回覆標籤.
		$reply_tags = array_map(
			function( $reply ) {
				return sprintf(
					'<span class="otz-keyword-tag" style="font-size: 12px; padding: 5px 7px; border: 1px solid #2271B1; border-radius: 5px; color:#2271B1">%s</span>',
					esc_html( $reply )
				);
			},
			$quick_replies
		);

		$output .= implode( '', $reply_tags );
		$output .= '</div>';

		return $output;
	}

	/**
	 * 取得 Function Tools 顯示
	 *
	 * @param array $item 機器人資料.
	 * @return string
	 */
	private function get_function_tools_display( $item ) {
		$function_tools = $item['function_tools'] ?? array();

		// 如果沒有 function tools，顯示「—」.
		if ( empty( $function_tools ) || ! is_array( $function_tools ) ) {
			return '<span style="color: #999;">—</span>';
		}

		// 定義工具名稱映射（使用多語系字串）.
		$tool_labels = array(
			'customer_orders'  => __( 'Customer Orders', 'otz' ),
			'customer_info'    => __( 'Customer Info', 'otz' ),
			'product_info'     => __( 'Product Info', 'otz' ),
			'custom_post_type' => __( 'Custom Post Type', 'otz' ),
		);

		// 過濾出啟用的工具.
		$enabled_tools = array();
		foreach ( $function_tools as $tool_key => $tool_value ) {
			if ( 'custom_post_type' === $tool_key ) {
				// custom_post_type 使用物件格式.
				$is_enabled = is_array( $tool_value ) && ! empty( $tool_value['enabled'] );
			} else {
				// 其他工具使用布林值.
				$is_enabled = (bool) $tool_value;
			}

			if ( $is_enabled && isset( $tool_labels[ $tool_key ] ) ) {
				$enabled_tools[] = $tool_labels[ $tool_key ];
			}
		}

		// 如果沒有啟用的工具，顯示「—」.
		if ( empty( $enabled_tools ) ) {
			return '<span style="color: #999;">—</span>';
		}

		$output = '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';

		// 顯示所有啟用的工具標籤.
		$tool_tags = array_map(
			function( $tool_label ) {
				return sprintf(
					'<span class="otz-keyword-tag" style="font-size: 12px; padding: 5px 7px; border: 1px solid #2271B1; border-radius: 5px; color:#2271B1">%s</span>',
					esc_html( $tool_label )
				);
			},
			$enabled_tools
		);

		$output .= implode( '', $tool_tags );
		$output .= '</div>';

		return $output;
	}

	/**
	 * 顯示沒有資料時的訊息
	 */
	public function no_items() {
		_e( 'No bot found.', 'otz' );
	}

	/**
	 * 顯示搜尋框
	 *
	 * @param string $text 按鈕文字.
	 * @param string $input_id 輸入框 ID.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		echo '<p class="search-box">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $input_id ) . '">' . esc_html( $text ) . ':</label>';
		echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . esc_attr( _admin_search_query() ) . '" placeholder="' . esc_attr__( 'Search bot name...', 'otz' ) . '" />';
		submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) );
		echo '</p>';
	}
}
