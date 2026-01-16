<?php
/**
 * OrderChatz 會員同步 AJAX 處理器
 *
 * 處理網站會員匯入 otz_users 資料表的 AJAX 請求
 *
 * @package OrderChatz\Ajax
 * @since 1.0.0
 */

namespace OrderChatz\Ajax;

use Exception;

class MemberSyncHandler {

	public function __construct() {
		add_action( 'wp_ajax_otz_sync_members', array( $this, 'sync_users' ) );
		add_action( 'wp_ajax_otz_get_sync_users', array( $this, 'get_sync_users' ) );
	}

	/**
	 * 取得可同步的會員列表
	 */
	public function get_sync_users() {
		try {
			check_ajax_referer( 'otz_sync_members', 'nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				throw new Exception( __( '權限不足', 'otz' ) );
			}

			$users = $this->getSyncableUsers();

			wp_send_json_success(
				array(
					'users' => $users,
					'total' => count( $users ),
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
	 * 同步網站會員到 otz_users 資料表
	 */
	public function sync_users() {
		try {
			check_ajax_referer( 'otz_sync_members', 'nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				throw new Exception( __( '權限不足', 'otz' ) );
			}

			$selected_users = isset( $_POST['selected_users'] ) ? array_map( 'intval', $_POST['selected_users'] ) : array();

			if ( empty( $selected_users ) ) {
				throw new Exception( __( '請選擇要匯入的會員', 'otz' ) );
			}

			$result = $this->importMembers( $selected_users );

			wp_send_json_success(
				array(
					'message'  => __( '會員同步完成', 'otz' ),
					'imported' => $result['imported'],
					'updated'  => $result['updated'],
					'skipped'  => $result['skipped'] ?? 0,
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
	 * 取得可同步的會員列表（具有 wc_notify_line_user_id 的會員）
	 *
	 * @return array 會員列表
	 */
	private function getSyncableUsers() {
		global $wpdb;

		// 查詢具有 wc_notify_line_user_id 的會員
		$sql = "
            SELECT u.ID, u.display_name, u.user_email, um.meta_value as line_user_id
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'wc_notify_line_user_id'
            AND um.meta_value != ''
            AND um.meta_value IS NOT NULL
            ORDER BY u.display_name ASC
        ";

		$users_with_line_id = $wpdb->get_results( $sql );

		$otz_table      = $wpdb->prefix . 'otz_users';
		$syncable_users = array();

		foreach ( $users_with_line_id as $user ) {
			// 檢查是否已存在於 otz_users 中
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$otz_table} WHERE wp_user_id = %d",
					$user->ID
				)
			);

			$is_imported = ! empty( $existing );

			$syncable_users[] = array(
				'id'           => $user->ID,
				'name'         => $user->display_name,
				'email'        => $user->user_email,
				'line_user_id' => $user->line_user_id,
				'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 40 ) ),
				'is_imported'  => $is_imported,
			);
		}

		return $syncable_users;
	}

	/**
	 * 匯入會員資料到 otz_users 資料表
	 *
	 * @param array $selected_user_ids 選定的用戶 ID 陣列
	 * @return array 匯入結果統計
	 */
	private function importMembers( $selected_user_ids = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'otz_users';
		$imported   = 0;
		$updated    = 0;
		$skipped    = 0;
		$details    = array();

		// 根據選定的用戶 ID 查詢會員
		$users = get_users(
			array(
				'include' => $selected_user_ids,
				'fields'  => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		foreach ( $users as $user ) {
			$line_user_id = get_user_meta( $user->ID, 'wc_notify_line_user_id', true );

			// 如果 LINE User ID 為空或不存在，保留為空值
			if ( empty( $line_user_id ) ) {
				$line_user_id = null; // 允許為空值，前端顯示時會顯示「尚未加入好友」
			}

			// 檢查此會員是否已經存在於 otz_users 資料表中
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, wp_user_id, line_user_id FROM $table_name WHERE wp_user_id = %d",
					$user->ID
				)
			);

			if ( $existing ) {
				// 如果會員已存在，檢查是否需要更新 line_user_id
				if ( $existing->line_user_id !== $line_user_id ) {
					$result = $wpdb->update(
						$table_name,
						array( 'line_user_id' => $line_user_id ),
						array( 'wp_user_id' => $user->ID ),
						array( '%s' ),
						array( '%d' )
					);

					if ( $result !== false ) {
						$updated++;
						$details[] = sprintf(
							__( '更新會員：%1$s (ID: %2$d) - LINE ID: %3$s', 'otz' ),
							$user->display_name,
							$user->ID,
							$line_user_id
						);
					}
				} else {
					$skipped++;
					$details[] = sprintf(
						__( '跳過會員：%1$s (ID: %2$d) - 已存在相同資料', 'otz' ),
						$user->display_name,
						$user->ID
					);
				}
			} else {
				// 檢查 line_user_id 是否已被其他人使用（僅檢查非空值）
				$line_id_exists = false;
				if ( ! empty( $line_user_id ) ) {
					$line_id_check  = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM $table_name WHERE line_user_id = %s",
							$line_user_id
						)
					);
					$line_id_exists = ( $line_id_check > 0 );
				}

				if ( $line_id_exists ) {
					// LINE ID 已存在，設為空值
					$line_user_id = null;
					$details[]    = sprintf(
						__( 'LINE ID 衝突，已設為空值：%1$s (會員ID: %2$d)', 'otz' ),
						$user->display_name,
						$user->ID
					);
				}

				// 新增記錄
				$result = $wpdb->insert(
					$table_name,
					array(
						'line_user_id' => $line_user_id,
						'wp_user_id'   => $user->ID,
						'display_name' => $user->display_name,
						'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 60 ) ),
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
						__( '匯入會員：%1$s (ID: %2$d) - LINE ID: %3$s', 'otz' ),
						$user->display_name,
						$user->ID,
						$line_user_id
					);
				} else {
					$details[] = sprintf(
						__( '匯入失敗：%1$s (ID: %2$d) - 錯誤：%3$s', 'otz' ),
						$user->display_name,
						$user->ID,
						$wpdb->last_error
					);
				}
			}
		}

		return array(
			'total'    => count( $users ),
			'imported' => $imported,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'details'  => $details,
		);
	}

}
