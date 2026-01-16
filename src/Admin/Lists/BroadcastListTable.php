<?php
/**
 * OrderChatz Broadcast Campaign List Table
 *
 * 處理推播活動管理頁面的 WP_List_Table 實作
 *
 * @package OrderChatz\Admin\Lists
 * @since 1.1.3
 */

namespace OrderChatz\Admin\Lists;

use OrderChatz\Database\Broadcast\Campaign;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * 推播活動列表表格類別
 *
 * 繼承 WordPress 內建的 WP_List_Table 來實作推播活動管理介面
 */
class BroadcastListTable extends \WP_List_Table {

	/**
	 * Campaign 資料庫操作類別
	 *
	 * @var Campaign
	 */
	private $campaign;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->campaign = new Campaign( $wpdb );

		parent::__construct(
			array(
				'singular' => __( 'Broadcast Campaign', 'otz' ),
				'plural'   => 'broadcasts',
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
			'cb'              => '<input type="checkbox" />',
			'campaign_name'   => __( 'Campaign Name', 'otz' ),
			'audience_type'   => __( 'Audience Type', 'otz' ),
			'message_type'    => __( 'Message Type', 'otz' ),
			'message_content' => __( 'Message Content', 'otz' ),
			'scheduled_at'    => __( 'Scheduled Time', 'otz' ),
			'updated_at'      => __( 'Updated Time', 'otz' ),
		);
	}

	/**
	 * 取得可排序的欄位
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'campaign_name' => array( 'campaign_name', true ),
			'scheduled_at'  => array( 'scheduled_at', false ),
			'updated_at'    => array( 'updated_at', false ),
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
			$campaign_ids = isset( $_POST['campaigns'] ) ? $_POST['campaigns'] : array();

			if ( ! empty( $campaign_ids ) ) {
				check_admin_referer( 'bulk-' . $this->_args['plural'] );
				$this->delete_campaigns( $campaign_ids );
			}
		}
	}

	/**
	 * 批次刪除推播活動
	 *
	 * @param array $campaign_ids
	 */
	private function delete_campaigns( $campaign_ids ) {
		if ( empty( $campaign_ids ) ) {
			return;
		}

		$campaign_ids = array_map( 'intval', $campaign_ids );
		$deleted      = 0;

		foreach ( $campaign_ids as $id ) {
			if ( $this->campaign->delete_campaign( $id ) ) {
				$deleted++;
			}
		}

		if ( $deleted > 0 ) {
			add_action(
				'admin_notices',
				function() use ( $deleted ) {
					echo '<div class="notice notice-success is-dismissible">';
					// translators: %d: number of deleted campaigns.
					echo '<p>' . sprintf( __( 'Successfully deleted %d broadcast campaigns', 'otz' ), $deleted ) . '</p>';
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

		$orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order   = ( ! empty( $_GET['order'] ) ) ? sanitize_text_field( $_GET['order'] ) : 'desc';

		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

		// 使用 Campaign 類別取得資料.
		$args = array(
			'order_by' => $orderby,
			'order'    => strtoupper( $order ),
			'limit'    => 1000, // 先取得所有資料進行搜尋和計數.
			'offset'   => 0,
		);

		$all_items = $this->campaign->get_all_campaigns( $args );

		// 如果有搜尋條件，進行過濾.
		if ( ! empty( $search ) ) {
			$all_items = array_filter(
				$all_items,
				function( $item ) use ( $search ) {
					return stripos( $item['campaign_name'], $search ) !== false;
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
	 * @param array  $item
	 * @param string $column_name
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'audience_type':
				return $this->get_audience_type_label( $item[ $column_name ] );
			case 'message_type':
				return $this->get_message_type_label( $item[ $column_name ] );
			case 'message_content':
				return $this->get_message_content_preview( $item );
			case 'scheduled_at':
				return $this->get_scheduled_time( $item );
			case 'updated_at':
				return $item[ $column_name ] ? mysql2date( 'Y-m-d H:i', $item[ $column_name ] ) : '-';
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	/**
	 * 複選框欄位
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="campaigns[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * 活動名稱欄位（包含操作連結）
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_campaign_name( $item ) {
		$edit_url = admin_url( 'admin.php?page=otz-broadcast&action=edit&id=' . $item['id'] );

		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=otz-broadcast&action=delete&id=' . $item['id'] ),
			'delete_campaign_' . $item['id']
		);

		$copy_url = wp_nonce_url(
			admin_url( 'admin.php?page=otz-broadcast&action=copy&id=' . $item['id'] ),
			'copy_campaign_' . $item['id']
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Edit', 'otz' ) ),
			'copy'   => sprintf( '<a href="%s">%s</a>', esc_url( $copy_url ), __( 'Copy', 'otz' ) ),
			'delete' => sprintf(
				'<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this broadcast campaign?', 'otz' ) ),
				__( 'Delete', 'otz' )
			),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item['campaign_name'] ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * 取得受眾類型標籤
	 *
	 * @param string $type
	 * @return string
	 */
	private function get_audience_type_label( $type ) {
		$labels = array(
			'all_followers'  => __( 'All Followers', 'otz' ),
			'imported_users' => __( 'Imported Followers', 'otz' ),
			'filtered'       => __( 'Filtered Followers', 'otz' ),
		);

		return isset( $labels[ $type ] ) ? esc_html( $labels[ $type ] ) : esc_html( $type );
	}

	/**
	 * 取得訊息類型標籤
	 *
	 * @param string $type
	 * @return string
	 */
	private function get_message_type_label( $type ) {
		$labels = array(
			'text'  => __( 'Text', 'otz' ),
			'image' => __( 'Image', 'otz' ),
			'video' => __( 'Video', 'otz' ),
			'flex'  => __( 'Flex Message', 'otz' ),
		);

		return isset( $labels[ $type ] ) ? esc_html( $labels[ $type ] ) : esc_html( $type );
	}

	/**
	 * 取得訊息內文預覽
	 *
	 * @param array $item 推播活動資料.
	 * @return string
	 */
	private function get_message_content_preview( $item ) {
		$message_type    = isset( $item['message_type'] ) ? $item['message_type'] : '';
		$message_content = isset( $item['message_content'] ) ? $item['message_content'] : '';

		// 解析 JSON 格式的訊息內容.
		if ( is_string( $message_content ) ) {
			$content_array = json_decode( $message_content, true );
		} elseif ( is_array( $message_content ) ) {
			$content_array = $message_content;
		} else {
			return '-';
		}

		// 根據訊息類型返回預覽.
		switch ( $message_type ) {
			case 'text':
				$text = isset( $content_array['text'] ) ? $content_array['text'] : '';
				if ( mb_strlen( $text ) > 50 ) {
					$text = mb_substr( $text, 0, 50 ) . '...';
				}
				return esc_html( $text );

			case 'image':
				$url = isset( $content_array['url'] ) ? $content_array['url'] : '';
				if ( $url ) {
					return sprintf(
						'<img src="%s" style="max-width: 100px; max-height: 60px; object-fit: cover; border-radius: 4px;" alt="">',
						esc_url( $url )
					);
				}
				return '-';

			case 'video':
				$preview_url = isset( $content_array['previewImageUrl'] ) ? $content_array['previewImageUrl'] : '';
				if ( $preview_url ) {
					return sprintf(
						'<img src="%s" style="max-width: 100px; max-height: 60px; object-fit: cover; border-radius: 4px;" alt="">',
						esc_url( $preview_url )
					);
				}
				return '-';

			case 'flex':
				return 'flex JSON';

			default:
				return '-';
		}
	}

	/**
	 * 取得預計發送時間
	 *
	 * @param array $item 推播活動資料.
	 * @return string
	 */
	private function get_scheduled_time( $item ) {
		$schedule_type = isset( $item['schedule_type'] ) ? $item['schedule_type'] : '';
		$scheduled_at  = isset( $item['scheduled_at'] ) ? $item['scheduled_at'] : '';

		// 必須同時符合兩個條件：schedule_type 是 scheduled 且 scheduled_at 不為空.
		if ( 'scheduled' === $schedule_type && ! empty( $scheduled_at ) && '0000-00-00 00:00:00' !== $scheduled_at ) {
			return mysql2date( 'Y-m-d H:i', $scheduled_at );
		}

		return __( 'Manual Broadcast', 'otz' );
	}

	/**
	 * 顯示沒有資料時的訊息
	 */
	public function no_items() {
		_e( 'No broadcast campaigns found', 'otz' );
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
		echo '<input type="search" id="' . esc_attr( $input_id ) . '" name="s" value="' . _admin_search_query() . '" placeholder="' . esc_attr__( 'Search campaign name...', 'otz' ) . '" />';
		submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) );
		echo '</p>';
	}
}
