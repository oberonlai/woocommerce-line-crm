<?php
/**
 * 備註處理 AJAX 處理器
 *
 * 處理客戶備註和備註分類相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Customer
 * @since 1.0.9
 */

namespace OrderChatz\Ajax\Customer;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;
use OrderChatz\Database\User;
use OrderChatz\Util\Logger;

class Note extends AbstractAjaxHandler {

	/**
	 * User 資料庫操作實例.
	 *
	 * @var User
	 */
	private $user_db;

	public function __construct() {
		global $wpdb;
		$this->user_db = new User( $wpdb );
		// 備註相關 AJAX actions
		add_action( 'wp_ajax_otz_save_customer_notes', array( $this, 'saveCustomerNotes' ) );
		add_action( 'wp_ajax_otz_edit_customer_note', array( $this, 'editCustomerNote' ) );
		add_action( 'wp_ajax_otz_delete_customer_note', array( $this, 'deleteCustomerNote' ) );

		// 分類相關 AJAX actions
		add_action( 'wp_ajax_otz_search_note_categories', array( $this, 'searchNoteCategories' ) );
		add_action( 'wp_ajax_update_note_category', array( $this, 'updateNoteCategory' ) );
		add_action( 'wp_ajax_get_note_categories_with_stats', array( $this, 'getCategoriesWithStats' ) );
		add_action( 'wp_ajax_add_note_category', array( $this, 'addNoteCategory' ) );
		add_action( 'wp_ajax_update_category_name', array( $this, 'updateCategoryName' ) );
		add_action( 'wp_ajax_delete_note_category', array( $this, 'deleteNoteCategory' ) );
	}

	/**
	 * 檢查權限
	 */
	private function check_permissions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			throw new Exception( '權限不足' );
		}
	}

	/**
	 * 驗證 Nonce
	 */
	private function validate_nonce( $action = 'orderchatz_chat_action' ) {
		if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
			throw new Exception( '安全驗證失敗，請重新整理頁面後再試' );
		}
	}

	/**
	 * 驗證必填欄位
	 */
	private function validate_required_fields( $fields ) {
		foreach ( $fields as $field_name => $field_value ) {
			if ( empty( $field_value ) ) {
				throw new Exception( $field_name . ' 不能為空' );
			}
		}
	}

	/**
	 * 取得客戶備註
	 */
	public function getCustomerNotes( $line_user_id, $source_type = 'user', $group_id = '' ) {
		global $wpdb;

		$user_notes_table = $wpdb->prefix . 'otz_user_notes';

		// 根據來源類型決定查詢條件.
		if ( $source_type === 'group' || $source_type === 'room' ) {
			// 查詢群組或聊天室備註.
			$notes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, note, category, related_product_id, related_message, created_by, created_at FROM {$user_notes_table}
					WHERE group_id = %s AND source_type = %s ORDER BY created_at DESC",
					$group_id,
					$source_type
				)
			);
		} else {
			// 查詢個人備註.
			$notes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, note, category, related_product_id, related_message, created_by, created_at FROM {$user_notes_table}
					WHERE line_user_id = %s AND source_type = 'user' ORDER BY created_at DESC",
					$line_user_id
				)
			);
		}

		return array_map(
			function( $note ) {
				$created_by_name = '';
				if ( $note->created_by ) {
					$user            = get_user_by( 'id', $note->created_by );
					$created_by_name = $user ? $user->display_name : '未知用戶';
				}

				$result = array(
					'id'                 => $note->id,
					'note'               => $note->note,
					'category'           => $note->category ?? '',
					'related_product_id' => $note->related_product_id ? intval( $note->related_product_id ) : 0,
					'created_by'         => $note->created_by,
					'created_by_name'    => $created_by_name,
					'created_at'         => $note->created_at,
				);

				// 新增 related_message 欄位支援.
				if ( ! empty( $note->related_message ) ) {
					$result['related_message'] = json_decode( $note->related_message, true );
				}

				// 新增商品資訊.
				if ( $note->related_product_id && intval( $note->related_product_id ) > 0 ) {
					$product = wc_get_product( intval( $note->related_product_id ) );
					if ( $product ) {
						$result['related_product_name'] = $product->get_name();
						$result['related_product_url']  = get_permalink( $product->get_id() );
					}
				}

				return $result;
			},
			$notes
		);
	}

	/**
	 * 儲存客戶備註
	 */
	public function saveCustomerNotes() {
		try {
			$this->check_permissions();
			$this->validate_nonce();

			$line_user_id       = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$source_type        = ( isset( $_POST['source_type'] ) ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : 'user';
			$group_id           = ( isset( $_POST['group_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '';
			$note               = ( isset( $_POST['notes'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
			$category           = ( isset( $_POST['category'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
			$related_product_id = ( isset( $_POST['related_product_id'] ) ) ? intval( wp_unslash( $_POST['related_product_id'] ) ) : 0;

			// 驗證產品是否存在（如果有提供產品 ID）.
			if ( $related_product_id > 0 ) {
				$product = wc_get_product( $related_product_id );
				if ( ! $product ) {
					throw new Exception( '指定的產品不存在' );
				}
			}

			// 新增：支援關聯訊息資料
			$related_message_data = ( isset( $_POST['related_message'] ) ) ? wp_unslash( $_POST['related_message'] ) : '';
			$related_message_json = null;

			if ( ! empty( $related_message_data ) ) {
				// 驗證並清理 related_message 資料
				if ( is_array( $related_message_data ) ) {
					$datetime = ( isset( $related_message_data['datetime'] ) ) ? sanitize_text_field( $related_message_data['datetime'] ) : '';
					$content  = ( isset( $related_message_data['content'] ) ) ? sanitize_textarea_field( $related_message_data['content'] ) : '';
					$type     = ( isset( $related_message_data['type'] ) ) ? sanitize_text_field( $related_message_data['type'] ) : '';

					if ( ! empty( $datetime ) && ! empty( $content ) && ! empty( $type ) ) {
						$related_message_json = wp_json_encode(
							array(
								'datetime' => $datetime,
								'content'  => $content,
								'type'     => $type,
							)
						);
					}
				}
			}

			// 根據來源類型驗證必填欄位.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				// 群組或聊天室：只需要 group_id 和備註內容.
				$this->validate_required_fields(
					array(
						'Group ID' => $group_id,
						'備註內容'      => $note,
					)
				);
			} else {
				// 個人：需要 line_user_id 和備註內容.
				$this->validate_required_fields(
					array(
						'LINE User ID' => $line_user_id,
						'備註內容'         => $note,
					)
				);
			}

			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			if ( ! empty( $category ) ) {
				$this->ensureCategoryExists( $category );
			}

			// 準備插入資料.
			$insert_data = array(
				'line_user_id' => $line_user_id,
				'source_type'  => $source_type,
				'group_id'     => $group_id,
				'note'         => $note,
				'category'     => $category,
				'created_by'   => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			);

			$format = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

			// 如果有關聯訊息資料，加入到插入資料中
			if ( ! empty( $related_message_json ) ) {
				$insert_data['related_message'] = $related_message_json;
				$format[]                       = '%s';
			}

			// 如果有關聯產品 ID，加入到插入資料中.
			if ( $related_product_id > 0 ) {
				$insert_data['related_product_id'] = $related_product_id;
				$format[]                          = '%d';
			}

			// 新增備註記錄.
			$result = $wpdb->insert( $user_notes_table, $insert_data, $format );

			if ( ! $result ) {
				throw new Exception( '儲存備註失敗' );
			}

			// 取得更新後的所有備註
			$updated_notes = $this->getCustomerNotes( $line_user_id, $source_type, $group_id );

			// 取得 LINE 用戶對應的 WP User ID.
			$user_data  = $this->user_db->get_user_with_wp_user_id( $line_user_id );
			$wp_user_id = $user_data['wp_user_id'] ?? 0;

			// 觸發備註儲存後的 hook.
			do_action(
				'otz_saved_customer_note',
				array(
					'line_user_id'       => $line_user_id,
					'wp_user_id'         => $wp_user_id,
					'note'               => $note,
					'category'           => $category,
					'related_product_id' => $related_product_id,
					'related_message'    => $related_message_json ? json_decode( $related_message_json, true ) : null,
				)
			);

			$this->sendSuccess(
				array(
					'message' => '備註已儲存',
					'notes'   => $updated_notes,
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '儲存備註失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 編輯客戶備註
	 */
	public function editCustomerNote() {
		try {
			$this->check_permissions();
			$this->validate_nonce();

			$line_user_id       = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$source_type        = ( isset( $_POST['source_type'] ) ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : 'user';
			$group_id           = ( isset( $_POST['group_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '';
			$note_id            = ( isset( $_POST['note_id'] ) ) ? intval( wp_unslash( $_POST['note_id'] ) ) : 0;
			$note_content       = ( isset( $_POST['note_content'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['note_content'] ) ) : '';
			$category           = ( isset( $_POST['category'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
			$related_product_id = ( isset( $_POST['related_product_id'] ) ) ? intval( wp_unslash( $_POST['related_product_id'] ) ) : 0;

			// 驗證產品是否存在（如果有提供產品 ID）.
			if ( $related_product_id > 0 ) {
				$product = wc_get_product( $related_product_id );
				if ( ! $product ) {
					throw new Exception( '指定的產品不存在' );
				}
			}

			// 根據來源類型驗證必填欄位.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				$this->validate_required_fields(
					array(
						'Group ID' => $group_id,
						'備註 ID'    => $note_id,
						'備註內容'     => $note_content,
					)
				);
			} else {
				$this->validate_required_fields(
					array(
						'LINE User ID' => $line_user_id,
						'備註 ID'        => $note_id,
						'備註內容'         => $note_content,
					)
				);
			}

			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			// 根據來源類型驗證備註是否存在.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				$existing_note = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$user_notes_table} WHERE id = %d AND group_id = %s AND source_type = %s",
						$note_id,
						$group_id,
						$source_type
					)
				);
			} else {
				$existing_note = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$user_notes_table} WHERE id = %d AND line_user_id = %s AND source_type = 'user'",
						$note_id,
						$line_user_id
					)
				);
			}

			if ( ! $existing_note ) {
				throw new Exception( '找不到指定的備註' );
			}

			// 如果提供了新的分類名稱，先將其添加到分類列表中.
			if ( ! empty( $category ) ) {
				$this->ensureCategoryExists( $category );
			}

			// 準備更新資料.
			$update_data = array(
				'note'     => $note_content,
				'category' => $category,
			);

			$update_format = array( '%s', '%s' );

			// 更新產品關聯（0 表示無關聯）.
			$update_data['related_product_id'] = $related_product_id;
			$update_format[]                   = '%d';

			// 根據來源類型準備更新條件.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				$where_conditions = array(
					'id'          => $note_id,
					'group_id'    => $group_id,
					'source_type' => $source_type,
				);
				$where_format     = array( '%d', '%s', '%s' );
			} else {
				$where_conditions = array(
					'id'           => $note_id,
					'line_user_id' => $line_user_id,
					'source_type'  => 'user',
				);
				$where_format     = array( '%d', '%s', '%s' );
			}

			// 更新備註.
			$result = $wpdb->update(
				$user_notes_table,
				$update_data,
				$where_conditions,
				$update_format,
				$where_format
			);

			if ( $result === false ) {
				throw new Exception( '更新備註失敗' );
			}

			// 取得更新後的所有備註.
			$updated_notes = $this->getCustomerNotes( $line_user_id, $source_type, $group_id );

			// 取得 LINE 用戶對應的 WP User ID.
			$user_data  = $this->user_db->get_user_with_wp_user_id( $line_user_id );
			$wp_user_id = $user_data['wp_user_id'] ?? 0;

			// 觸發備註更新後的 hook.
			do_action(
				'otz_updated_customer_note',
				array(
					'line_user_id'       => $line_user_id,
					'wp_user_id'         => $wp_user_id,
					'note'               => $note_content,
					'category'           => $category,
					'related_product_id' => $related_product_id,
					'old_data'           => $existing_note,
				)
			);

			$this->sendSuccess(
				array(
					'notes'   => $updated_notes,
					'message' => '備註更新成功',
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '編輯備註失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 刪除客戶備註
	 */
	public function deleteCustomerNote() {
		try {
			$this->check_permissions();
			$this->validate_nonce();

			$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$source_type  = ( isset( $_POST['source_type'] ) ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : 'user';
			$group_id     = ( isset( $_POST['group_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '';
			$note_id      = ( isset( $_POST['note_id'] ) ) ? intval( wp_unslash( $_POST['note_id'] ) ) : 0;

			// 根據來源類型驗證必填欄位.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				$this->validate_required_fields(
					array(
						'Group ID' => $group_id,
						'備註 ID'    => $note_id,
					)
				);
			} else {
				$this->validate_required_fields(
					array(
						'LINE User ID' => $line_user_id,
						'備註 ID'        => $note_id,
					)
				);
			}

			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			// 根據來源類型驗證備註是否存在.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				$existing_note = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$user_notes_table} WHERE id = %d AND group_id = %s AND source_type = %s",
						$note_id,
						$group_id,
						$source_type
					)
				);
			} else {
				$existing_note = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$user_notes_table} WHERE id = %d AND line_user_id = %s AND source_type = 'user'",
						$note_id,
						$line_user_id
					)
				);
			}

			if ( ! $existing_note ) {
				throw new Exception( '找不到指定的備註' );
			}

			// 根據來源類型準備刪除條件.
			if ( $source_type === 'group' || $source_type === 'room' ) {
				$delete_conditions = array(
					'id'          => $note_id,
					'group_id'    => $group_id,
					'source_type' => $source_type,
				);
				$delete_format     = array( '%d', '%s', '%s' );
			} else {
				$delete_conditions = array(
					'id'           => $note_id,
					'line_user_id' => $line_user_id,
					'source_type'  => 'user',
				);
				$delete_format     = array( '%d', '%s', '%s' );
			}

			// 刪除備註.
			$result = $wpdb->delete(
				$user_notes_table,
				$delete_conditions,
				$delete_format
			);

			if ( $result === false ) {
				throw new Exception( '刪除備註失敗' );
			}

			// 取得剩餘的備註.
			$remaining_notes = $this->getCustomerNotes( $line_user_id, $source_type, $group_id );

			// 取得 LINE 用戶對應的 WP User ID.
			$user_data  = $this->user_db->get_user_with_wp_user_id( $line_user_id );
			$wp_user_id = $user_data['wp_user_id'] ?? 0;

			// 觸發備註刪除後的 hook.
			do_action(
				'otz_deleted_customer_note',
				array(
					'line_user_id' => $line_user_id,
					'wp_user_id'   => $wp_user_id,
					'deleted_data' => $existing_note,
				)
			);

			$this->sendSuccess(
				array(
					'notes'   => $remaining_notes,
					'message' => '備註刪除成功',
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '刪除備註失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 搜尋備註分類
	 */
	public function searchNoteCategories() {
		try {
			$this->check_permissions();
			$this->validate_nonce();

			$search_term = ( isset( $_REQUEST['search'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
			$page        = ( isset( $_REQUEST['page'] ) ) ? intval( wp_unslash( $_REQUEST['page'] ) ) : 1;
			$per_page    = 20;

			$all_categories = get_option( 'otz_note_categories', array() );

			if ( empty( $all_categories ) ) {
				$this->initializeDefaultCategories();
				$all_categories = get_option( 'otz_note_categories', array() );
			}

			$filtered_categories = array();
			if ( ! empty( $search_term ) && strlen( $search_term ) >= 2 ) {
				foreach ( $all_categories as $category ) {
					$category_name = is_array( $category ) ? $category['name'] : $category;
					if ( stripos( $category_name, $search_term ) !== false ) {
						$filtered_categories[] = $category;
					}
				}
			} else {
				$filtered_categories = $all_categories;
			}

			usort(
				$filtered_categories,
				function( $a, $b ) {
					$name_a = is_array( $a ) ? $a['name'] : $a;
					$name_b = is_array( $b ) ? $b['name'] : $b;
					return strcmp( $name_a, $name_b );
				}
			);

			$total_count     = count( $filtered_categories );
			$offset          = ( $page - 1 ) * $per_page;
			$page_categories = array_slice( $filtered_categories, $offset, $per_page );

			$results = array();
			foreach ( $page_categories as $category ) {
				$category_name = is_array( $category ) ? $category['name'] : $category;
				$results[]     = array(
					'id'   => $category_name,
					'text' => $category_name,
				);
			}

			$has_more = ( $page * $per_page ) < $total_count;

			$this->sendSuccess(
				array(
					'results'    => $results,
					'pagination' => array( 'more' => $has_more ),
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '搜尋備註分類失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 更新備註分類
	 */
	public function updateNoteCategory() {
		try {
			$this->check_permissions();
			$this->validate_nonce( 'orderchatz_admin_action' );

			$note_id  = ( isset( $_POST['note_id'] ) ) ? intval( wp_unslash( $_POST['note_id'] ) ) : 0;
			$category = ( isset( $_POST['category'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

			$this->validate_required_fields(
				array(
					'備註 ID' => $note_id,
				)
			);

			if ( strlen( $category ) > 50 ) {
				throw new Exception( '分類名稱過長' );
			}

			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			$result = $wpdb->update(
				$user_notes_table,
				array( 'category' => $category ),
				array( 'id' => $note_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( $result !== false ) {
				$this->sendSuccess(
					array(
						'message'        => '分類更新成功',
						'category_label' => empty( $category ) ? '無分類' : $category,
					)
				);
			} else {
				throw new Exception( '分類更新失敗' );
			}
		} catch ( Exception $e ) {
			Logger::error( '更新備註分類失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得分類統計資料
	 */
	public function getCategoriesWithStats() {
		try {
			$this->check_permissions();
			$this->validate_nonce( 'orderchatz_admin_action' );

			// 從 option 中取得所有分類
			$all_categories = get_option( 'otz_note_categories', array() );

			// 如果沒有分類，建立一些預設分類
			if ( empty( $all_categories ) ) {
				$this->initializeDefaultCategories();
				$all_categories = get_option( 'otz_note_categories', array() );
			}

			// 計算每個分類的使用數量
			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			$result = array();
			foreach ( $all_categories as $category ) {
				// 支援新結構和舊結構
				$category_name  = is_array( $category ) ? $category['name'] : $category;
				$category_color = is_array( $category ) ? $category['color'] : $this->getDefaultColor( $category_name );

				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$user_notes_table} WHERE category = %s",
						$category_name
					)
				);

				$result[] = array(
					'name'  => $category_name,
					'color' => $category_color,
					'count' => intval( $count ),
				);
			}

			// 依分類名稱排序
			usort(
				$result,
				function( $a, $b ) {
					return strcmp( $a['name'], $b['name'] );
				}
			);

			$this->sendSuccess(
				array(
					'categories' => $result,
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '取得分類統計失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 新增分類
	 */
	public function addNoteCategory() {
		try {
			$this->check_permissions();
			$this->validate_nonce( 'orderchatz_admin_action' );

			$category_name  = ( isset( $_POST['category_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';
			$category_color = ( isset( $_POST['category_color'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category_color'] ) ) : '';

			$this->validate_required_fields(
				array(
					'分類名稱' => $category_name,
				)
			);

			if ( strlen( $category_name ) > 50 ) {
				throw new Exception( '分類名稱不能超過50個字符' );
			}

			// 如果沒有提供顏色，使用預設顏色
			if ( empty( $category_color ) ) {
				$category_color = $this->getDefaultColor( $category_name );
			}

			// 驗證顏色格式
			if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $category_color ) ) {
				throw new Exception( '顏色格式不正確' );
			}

			// 從 option 取得現有分類
			$existing_categories = get_option( 'otz_note_categories', array() );

			// 如果沒有分類，建立一些預設分類
			if ( empty( $existing_categories ) ) {
				$this->initializeDefaultCategories();
				$existing_categories = get_option( 'otz_note_categories', array() );
			}

			// 檢查分類是否已存在
			foreach ( $existing_categories as $category ) {
				$existing_name = is_array( $category ) ? $category['name'] : $category;
				if ( $existing_name === $category_name ) {
					throw new Exception( '分類「' . $category_name . '」已存在' );
				}
			}

			// 新增分類到 option
			$existing_categories[] = array(
				'name'  => $category_name,
				'color' => $category_color,
			);
			update_option( 'otz_note_categories', $existing_categories );

			$this->sendSuccess(
				array(
					'message'  => '分類「' . $category_name . '」建立成功',
					'category' => array(
						'name'  => $category_name,
						'color' => $category_color,
					),
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '新增分類失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 更新分類
	 */
	public function updateCategoryName() {
		try {
			$this->check_permissions();
			$this->validate_nonce( 'orderchatz_admin_action' );

			$old_name  = ( isset( $_POST['old_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['old_name'] ) ) : '';
			$new_name  = ( isset( $_POST['new_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['new_name'] ) ) : '';
			$new_color = ( isset( $_POST['new_color'] ) ) ? sanitize_text_field( wp_unslash( $_POST['new_color'] ) ) : '';

			$this->validate_required_fields(
				array(
					'舊分類名稱' => $old_name,
					'新分類名稱' => $new_name,
				)
			);

			if ( strlen( $new_name ) > 50 ) {
				throw new Exception( '分類名稱不能超過50個字符' );
			}

			// 驗證顏色格式如果有提供的話
			if ( ! empty( $new_color ) && ! preg_match( '/^#[0-9A-Fa-f]{6}$/', $new_color ) ) {
				throw new Exception( '顏色格式不正確' );
			}

			// 從 option 取得現有分類
			$existing_categories = get_option( 'otz_note_categories', array() );

			// 如果沒有分類，建立一些預設分類
			if ( empty( $existing_categories ) ) {
				$this->initializeDefaultCategories();
				$existing_categories = get_option( 'otz_note_categories', array() );
			}

			// 尋找舊分類
			$old_index    = -1;
			$old_category = null;
			foreach ( $existing_categories as $index => $category ) {
				$category_name = is_array( $category ) ? $category['name'] : $category;
				if ( $category_name === $old_name ) {
					$old_index    = $index;
					$old_category = $category;
					break;
				}
			}

			if ( $old_index === -1 ) {
				throw new Exception( '找不到舊分類「' . $old_name . '」' );
			}

			// 檢查新分類名稱是否已存在（排除自己）
			if ( $old_name !== $new_name ) {
				foreach ( $existing_categories as $category ) {
					$category_name = is_array( $category ) ? $category['name'] : $category;
					if ( $category_name === $new_name ) {
						throw new Exception( '分類「' . $new_name . '」已存在' );
					}
				}
			}

			// 獲得舊顏色和新顏色
			$old_color = is_array( $old_category ) ? $old_category['color'] : $this->getDefaultColor( $old_name );
			if ( empty( $new_color ) ) {
				$new_color = $old_color;
			}

			// 更新 option 中的分類
			$existing_categories[ $old_index ] = array(
				'name'  => $new_name,
				'color' => $new_color,
			);
			update_option( 'otz_note_categories', $existing_categories );

			// 更新所有使用舊分類名稱的備註
			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			$result = $wpdb->update(
				$user_notes_table,
				array( 'category' => $new_name ),
				array( 'category' => $old_name ),
				array( '%s' ),
				array( '%s' )
			);

			if ( $result === false ) {
				throw new Exception( '更新資料庫中的分類失敗' );
			}

			$this->sendSuccess(
				array(
					'message'  => '分類「' . $old_name . '」已更新為「' . $new_name . '」',
					'category' => array(
						'name'  => $new_name,
						'color' => $new_color,
					),
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '更新分類失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 刪除分類
	 */
	public function deleteNoteCategory() {
		try {
			$this->check_permissions();
			$this->validate_nonce( 'orderchatz_admin_action' );

			$category_name = ( isset( $_POST['category_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['category_name'] ) ) : '';

			$this->validate_required_fields(
				array(
					'分類名稱' => $category_name,
				)
			);

			// 從 option 中移除分類
			$existing_categories = get_option( 'otz_note_categories', array() );
			$category_index      = -1;

			foreach ( $existing_categories as $index => $category ) {
				$existing_name = is_array( $category ) ? $category['name'] : $category;
				if ( $existing_name === $category_name ) {
					$category_index = $index;
					break;
				}
			}

			if ( $category_index === -1 ) {
				throw new Exception( '找不到分類「' . $category_name . '」' );
			}

			// 從陣列中移除分類
			unset( $existing_categories[ $category_index ] );
			// 重新索引陣列
			$existing_categories = array_values( $existing_categories );
			update_option( 'otz_note_categories', $existing_categories );

			// 將使用此分類的備註設為無分類
			global $wpdb;
			$user_notes_table = $wpdb->prefix . 'otz_user_notes';

			$result = $wpdb->update(
				$user_notes_table,
				array( 'category' => null ),
				array( 'category' => $category_name ),
				array( '%s' ),
				array( '%s' )
			);

			if ( $result === false ) {
				throw new Exception( '更新資料庫中的備註分類失敗' );
			}

			$affected_count = $wpdb->rows_affected;

			$this->sendSuccess(
				array(
					'message' => '分類「' . $category_name . '」已刪除，' . $affected_count . ' 個備註已設為無分類',
				)
			);

		} catch ( Exception $e ) {
			Logger::error( '刪除分類失敗: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), 'otz' );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 初始化預設分類
	 */
	private function initializeDefaultCategories() {
		$default_categories = array(
			array(
				'name'  => '重要',
				'color' => '#e74c3c',
			),
			array(
				'name'  => '待處理',
				'color' => '#f39c12',
			),
			array(
				'name'  => '已完成',
				'color' => '#2ecc71',
			),
			array(
				'name'  => '備忘',
				'color' => '#3498db',
			),
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
		$hash  = crc32( $category_name );
		$index = abs( $hash ) % count( $default_colors );

		return $default_colors[ $index ];
	}

	/**
	 * 確保分類存在，如果不存在則自動建立
	 *
	 * @param string $category_name 分類名稱
	 */
	private function ensureCategoryExists( $category_name ) {
		if ( empty( $category_name ) ) {
			return;
		}

		// 從 option 中取得現有分類
		$existing_categories = get_option( 'otz_note_categories', array() );

		// 如果沒有分類，建立一些預設分類
		if ( empty( $existing_categories ) ) {
			$this->initializeDefaultCategories();
			$existing_categories = get_option( 'otz_note_categories', array() );
		}

		// 檢查分類是否已存在
		foreach ( $existing_categories as $category ) {
			$existing_name = is_array( $category ) ? $category['name'] : $category;
			if ( $existing_name === $category_name ) {
				return; // 分類已存在，直接返回
			}
		}

		// 分類不存在，自動新增
		$existing_categories[] = array(
			'name'  => $category_name,
			'color' => $this->getDefaultColor( $category_name ),
		);

		update_option( 'otz_note_categories', $existing_categories );
	}
}
