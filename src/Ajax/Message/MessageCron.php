<?php

declare(strict_types=1);

namespace OrderChatz\Ajax\Message;

use Exception;
use OrderChatz\Database\Message\TableMessageCron;
use OrderChatz\Util\Time;
use OrderChatz\Ajax\AbstractAjaxHandler;

/**
 * Message Cron Ajax Handler
 *
 * 處理排程訊息相關的 Ajax 請求
 *
 * @package    OrderChatz
 * @subpackage Ajax
 * @since      1.1.0
 */
class MessageCron extends AbstractAjaxHandler {

	/**
	 * 排程訊息資料庫操作類別
	 *
	 * @var TableMessageCron
	 */
	private TableMessageCron $cron_table;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->cron_table = new TableMessageCron( $wpdb );

		$this->init_hooks();
	}

	/**
	 * 初始化 Ajax hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'wp_ajax_otz_create_message_cron', array( $this, 'create_message_cron' ) );
		add_action( 'wp_ajax_otz_update_message_cron', array( $this, 'update_message_cron' ) );
		add_action( 'wp_ajax_otz_delete_message_cron', array( $this, 'delete_message_cron' ) );
		add_action( 'wp_ajax_otz_get_cron_history', array( $this, 'get_cron_history' ) );
		add_action( 'wp_ajax_otz_trigger_cron_manual', array( $this, 'trigger_cron_manual' ) );
	}

	/**
	 * 統一處理排程訊息的新增和更新
	 *
	 * @return void
	 */
	public function save_message_cron(): void {
		try {
			$this->verifyNonce( 'otz_message_cron_action' );

			// 判斷是新增還是更新操作
			$cron_id   = absint( $this->get_post_param( 'cron_id' ) );
			$is_update = $cron_id > 0;

			// 取得並驗證參數.
			$line_user_id    = $this->get_post_param( 'line_user_id' );
			$source_type     = $this->get_post_param( 'source_type', 'user' );
			$group_id        = $this->get_post_param( 'group_id' );
			$message_type    = $this->get_post_param( 'message_type', 'text' );
			$message_content = $this->get_post_param( 'message_content' );
			$schedule_json   = $this->get_post_param( 'schedule' );

			// 驗證操作類型相關的必要參數
			if ( $is_update ) {
				// 更新操作：檢查排程是否存在
				$existing_cron = $this->cron_table->get_cron_message( $cron_id );
				if ( ! $existing_cron ) {
					$this->sendError( '排程不存在' );
					return;
				}
			} else {
				// 新增操作：檢查必要參數
				if ( empty( $line_user_id ) ) {
					$this->sendError( 'LINE User ID 不能為空' );
					return;
				}
			}

			// 驗證共用必要參數
			if ( empty( $message_content ) || empty( $schedule_json ) ) {
				$this->sendError( '必要參數不能為空' );
				return;
			}

			// 解析排程資料.
			$schedule_data = json_decode( $schedule_json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$this->sendError( '排程資料格式錯誤' );
				return;
			}

			// 根據排程類型進行不同的驗證和時間計算.
			$utc_timestamp = false;

			if ( $schedule_data['type'] === 'once' ) {
				// 單次排程：使用原有邏輯.
				$utc_timestamp = Time::parse_schedule_timestamp( $schedule_data );
				if ( ! $utc_timestamp ) {
					$this->sendError( '排程時間無效' );
					return;
				}

				// 檢查時間是否在有效範圍內.
				$datetime_string = $schedule_data['sent_date'] . ' ' . $schedule_data['sent_time'];
				if ( ! Time::is_valid_schedule_range( $datetime_string ) ) {
					$this->sendError( '排程時間必須在未來一年內' );
					return;
				}
			} elseif ( $schedule_data['type'] === 'recurring' ) {
				// 重複排程：使用新的驗證邏輯.
				if ( ! Time::validate_recurring_schedule( $schedule_data ) ) {
					$this->sendError( '重複排程設定無效' );
					return;
				}

				// 計算重複排程的首次執行時間.
				$utc_timestamp = Time::calculate_recurring_first_run( $schedule_data );
				if ( ! $utc_timestamp ) {
					$this->sendError( '無法計算重複排程的執行時間' );
					return;
				}
			} else {
				$this->sendError( '不支援的排程類型' );
				return;
			}

			if ( $is_update ) {
				// 更新邏輯
				$this->handle_update_operation( $cron_id, $existing_cron, $message_content, $message_type, $schedule_data, $utc_timestamp );
			} else {
				// 新增邏輯
				$this->handle_create_operation( $line_user_id, $source_type, $group_id, $message_type, $message_content, $schedule_data, $utc_timestamp );
			}
		} catch ( Exception $e ) {
			$this->logError( 'Save message cron error: ' . $e->getMessage() );
			$this->sendError( '系統錯誤，請稍後再試' );
		}
	}

	/**
	 * 處理新增操作
	 */
	private function handle_create_operation( $line_user_id, $source_type, $group_id, $message_type, $message_content, $schedule_data, $utc_timestamp ): void {
		// 準備資料庫資料（先儲存以取得 cron_message_id）.
		$cron_data = array(
			'action_id'       => 0, // 暫時設為 0，稍後更新.
			'line_user_id'    => $line_user_id,
			'source_type'     => $source_type,
			'group_id'        => $group_id,
			'message_type'    => $message_type,
			'message_content' => $message_content,
			'schedule'        => $schedule_data,
			'created_by'      => get_current_user_id(),
		);

		// 先儲存到資料庫以取得 cron_message_id.
		$cron_message_id = $this->cron_table->insert_cron_message( $cron_data );
		if ( ! $cron_message_id ) {
			$this->sendError( '排程儲存失敗' );
			return;
		}

		// 註冊 Action Scheduler.
		$action_id = $this->register_action_scheduler( $schedule_data, $utc_timestamp, $cron_message_id );
		if ( ! $action_id ) {
			// 如果排程註冊失敗，刪除已建立的資料庫記錄.
			$this->cron_table->delete_cron_message( $cron_message_id );
			$this->sendError( '排程註冊失敗' );
			return;
		}

		// 更新資料庫記錄的 action_id.
		$this->cron_table->update_cron_message( $cron_message_id, array( 'action_id' => $action_id ) );

		$this->sendSuccess(
			array(
				'message'     => '排程建立成功',
				'cron_id'     => $cron_message_id,
				'action_id'   => $action_id,
				'schedule_at' => Time::format_display_time( $utc_timestamp ),
			)
		);
	}

	/**
	 * 處理更新操作
	 */
	private function handle_update_operation( $cron_id, $existing_cron, $message_content, $message_type, $schedule_data, $utc_timestamp ): void {
		// 準備更新資料.
		$update_data = array();

		if ( ! empty( $message_content ) ) {
			$update_data['message_content'] = $message_content;
		}

		if ( ! empty( $message_type ) ) {
			$update_data['message_type'] = $message_type;
		}

		// 處理排程更新
		if ( ! empty( $schedule_data ) ) {
			// 取消舊的排程.
			as_unschedule_action( 'otz_send_scheduled_message', array( 'cron_message_id' => $cron_id ) );

			// 註冊新的排程.
			$new_action_id = $this->register_action_scheduler( $schedule_data, $utc_timestamp, $cron_id );
			if ( ! $new_action_id ) {
				$this->sendError( '新排程註冊失敗' );
				return;
			}

			$update_data['schedule'] = $schedule_data;
			// 更新 action_id 需要直接更新，因為不在 allowed_fields 中.
			$this->cron_table->update_cron_message( $cron_id, array( 'action_id' => $new_action_id ) );
		}

		if ( empty( $update_data ) ) {
			$this->sendError( '沒有要更新的資料' );
			return;
		}

		// 更新資料庫.
		$result = $this->cron_table->update_cron_message( $cron_id, $update_data );
		if ( ! $result ) {
			$this->sendError( '更新失敗' );
			return;
		}

		$this->sendSuccess(
			array(
				'message' => '排程更新成功',
				'cron_id' => $cron_id,
			)
		);
	}

	/**
	 * 新增排程訊息（向後相容性方法）
	 *
	 * @return void
	 */
	public function create_message_cron(): void {
		$this->save_message_cron();
	}

	/**
	 * 更新排程訊息（向後相容性方法）
	 *
	 * @return void
	 */
	public function update_message_cron(): void {
		$this->save_message_cron();
	}

	/**
	 * 刪除排程訊息
	 *
	 * @return void
	 */
	public function delete_message_cron(): void {
		try {
			$this->verifyNonce( 'otz_message_cron_action' );

			$cron_id = absint( $this->get_post_param( 'cron_id' ) );

			if ( $cron_id <= 0 ) {
				$this->sendError( '無效的排程 ID' );
				return;
			}

			// 檢查排程是否存在.
			$existing_cron = $this->cron_table->get_cron_message( $cron_id );
			if ( ! $existing_cron ) {
				$this->sendError( '排程不存在' );
				return;
			}

			// 取消 Action Scheduler 排程.
			as_unschedule_action( 'otz_send_scheduled_message', array( 'cron_message_id' => $cron_id ) );

			// 刪除資料庫記錄.
			$result = $this->cron_table->delete_cron_message( $cron_id );
			if ( ! $result ) {
				$this->sendError( '刪除失敗' );
				return;
			}

			$this->sendSuccess(
				array(
					'message' => '排程刪除成功',
					'cron_id' => $cron_id,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Delete message cron error: ' . $e->getMessage() );
			$this->sendError( '系統錯誤，請稍後再試' );
		}
	}

	/**
	 * 取得排程歷史記錄
	 *
	 * @return void
	 */
	public function get_cron_history(): void {
		try {
			$this->verifyNonce( 'otz_message_cron_action' );

			$page     = absint( $this->get_post_param( 'page', '1' ) );
			$per_page = absint( $this->get_post_param( 'per_page', '20' ) );
			$user_id  = $this->get_post_param( 'line_user_id' );
			$status   = $this->get_post_param( 'status' );

			$limit  = $per_page;
			$offset = ( $page - 1 ) * $per_page;

			// 根據篩選條件查詢.
			if ( ! empty( $user_id ) ) {
				$cron_messages = $this->cron_table->get_cron_messages_by_user( $user_id, $limit, $offset );
			} elseif ( ! empty( $status ) ) {
				$cron_messages = $this->cron_table->get_cron_messages_by_status( $status, $limit, $user_id );
			} else {
				// 取得所有非 pending 狀態的記錄（已執行過的排程）.
				$cron_messages = $this->cron_table->get_cron_message_history( $limit, $user_id );
			}

			// 格式化輸出資料.
			$formatted_messages = array();
			foreach ( $cron_messages as $message ) {
				$schedule_data = $this->cron_table->parse_schedule_json( $message->schedule );
				$action_status = $this->cron_table->get_action_status( intval( $message->action_id ) );

				// 查詢實際發送時間.
				$actual_sent_time = null;
				$is_recurring     = ( isset( $schedule_data['type'] ) && $schedule_data['type'] === 'recurring' );

				// completed/manual 狀態或重複排程都查詢實際發送時間.
				if ( $message->status === 'completed' || $message->status === 'manual' || $is_recurring ) {
					$actual_sent_time = $this->get_actual_sent_time( intval( $message->action_id ) );
				}

				$formatted_messages[] = array(
					'id'               => $message->id,
					'action_id'        => intval( $message->action_id ),
					'line_user_id'     => $message->line_user_id,
					'source_type'      => $message->source_type,
					'message_type'     => $message->message_type,
					'message_content'  => wp_kses_post( $message->message_content ),
					'schedule'         => $schedule_data,
					'local_status'     => $message->status,
					'scheduler_status' => $action_status ? $action_status->status : 'unknown',
					'created_by'       => $message->created_by,
					'created_at'       => $message->created_at,
					'formatted_time'   => $this->formatMessageTime( $message->created_at ),
					'actual_sent_time' => $actual_sent_time,
				);
			}

			$this->sendSuccess(
				array(
					'messages' => $formatted_messages,
					'page'     => $page,
					'per_page' => $per_page,
					'total'    => count( $formatted_messages ),
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Get cron history error: ' . $e->getMessage() );
			$this->sendError( '系統錯誤，請稍後再試' );
		}
	}

	/**
	 * 手動觸發排程
	 *
	 * @return void
	 */
	public function trigger_cron_manual(): void {
		try {
			$this->verifyNonce( 'otz_message_cron_action' );

			$cron_id = absint( $this->get_post_param( 'cron_id' ) );

			if ( $cron_id <= 0 ) {
				$this->sendError( '無效的排程 ID' );
				return;
			}

			// 檢查排程是否存在
			$existing_cron = $this->cron_table->get_cron_message( $cron_id );
			if ( ! $existing_cron ) {
				$this->sendError( '排程不存在' );
				return;
			}

			// 解析排程類型
			$schedule_data = $this->cron_table->parse_schedule_json( $existing_cron->schedule );
			if ( ! $schedule_data ) {
				$this->sendError( '排程資料格式錯誤' );
				return;
			}

			// 根據排程類型處理
			if ( $schedule_data['type'] === 'once' ) {
				// 單次排程：取消 Action Scheduler 註冊
				as_unschedule_action( 'otz_send_scheduled_message', array( 'cron_message_id' => $cron_id ) );
			} elseif ( $schedule_data['type'] === 'recurring' ) {
				// 重複排程：新增一筆記錄（模擬下次執行）
				$new_cron_data = array(
					'action_id'       => $existing_cron->action_id,
					'line_user_id'    => $existing_cron->line_user_id,
					'source_type'     => $existing_cron->source_type,
					'group_id'        => $existing_cron->group_id,
					'message_type'    => $existing_cron->message_type,
					'message_content' => $existing_cron->message_content,
					'schedule'        => json_decode( $existing_cron->schedule, true ),
					'created_by'      => $existing_cron->created_by,
					'status'          => 'pending',
				);

				$new_record_id = $this->cron_table->insert_cron_message( $new_cron_data );
				if ( ! $new_record_id ) {
					$this->sendError( '新增執行記錄失敗' );
					return;
				}
			}

			// 更新原始記錄狀態為 manual.
			$this->cron_table->update_status( $cron_id, 'manual' );

			$response_data = array(
				'message' => '排程已手動觸發',
				'cron_id' => $cron_id,
				'type'    => $schedule_data['type'],
			);

			// 如果是重複排程，回傳新建立的記錄 ID.
			if ( $schedule_data['type'] === 'recurring' && isset( $new_record_id ) ) {
				$response_data['new_record_id'] = $new_record_id;
			}

			$this->sendSuccess( $response_data );

		} catch ( Exception $e ) {
			$this->logError( 'Trigger cron manual error: ' . $e->getMessage() );
			$this->sendError( '系統錯誤，請稍後再試' );
		}
	}

	/**
	 * 註冊 Action Scheduler 排程
	 *
	 * @param array $schedule_data 排程資料.
	 * @param int   $utc_timestamp UTC 時間戳.
	 * @param int   $cron_message_id 排程訊息 ID.
	 * @return int Action ID，失敗時返回 0
	 */
	private function register_action_scheduler( array $schedule_data, int $utc_timestamp, int $cron_message_id ): int {
		try {
			$hook  = 'otz_send_scheduled_message';
			$args  = array(
				'cron_message_id' => $cron_message_id,
			);
			$group = 'otz_message_cron';

			if ( $schedule_data['type'] === 'once' ) {
				// 單次排程.
				return as_schedule_single_action( $utc_timestamp, $hook, $args, $group );
			} elseif ( $schedule_data['type'] === 'recurring' && ! empty( $schedule_data['interval'] ) ) {
				// 重複排程.
				$interval_seconds = Time::interval_to_seconds( $schedule_data['interval'] );
				if ( $interval_seconds === false ) {
					return 0;
				}

				return as_schedule_recurring_action( $utc_timestamp, $interval_seconds, $hook, $args, $group );
			}

			return 0;

		} catch ( Exception $e ) {
			$this->logError( 'Action Scheduler registration error: ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * 查詢排程的實際發送時間
	 *
	 * @param int $action_id Action Scheduler ID
	 * @return string|null 實際發送時間或 null
	 */
	private function get_actual_sent_time( int $action_id ): ?string {
		if ( $action_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'actionscheduler_logs';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT log_date_local FROM {$table_name}
				 WHERE action_id = %d AND message LIKE %s
				 ORDER BY log_date_gmt DESC LIMIT 1",
				$action_id,
				'%執行的動作已完成%'
			)
		);

		return $result ?: null;
	}

	/**
	 * 取得 POST 參數（覆寫父類方法以支援陣列）
	 *
	 * @param string $key     參數鍵
	 * @param mixed  $default 預設值
	 * @return mixed 參數值
	 */
	protected function get_post_param( $key, $default = null ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		if ( is_array( $_POST[ $key ] ) ) {
			return array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) );
		}

		// 對於 schedule 參數，使用不同的清理方式以保持 JSON 格式完整.
		if ( $key === 'schedule' ) {
			return wp_unslash( $_POST[ $key ] );
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}
}
