<?php
/**
 * OrderChatz 推播儲存並發送處理器
 *
 * 處理「儲存並推播」功能，整合受眾篩選、訊息建構、推播發送和記錄寫入.
 *
 * @package OrderChatz\Services\Broadcast
 * @since 1.1.3
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast;

use Exception;
use OrderChatz\Database\Broadcast\Campaign;
use OrderChatz\Database\User;
use OrderChatz\Util\Logger;

/**
 * 推播儲存並發送處理類別
 */
class SavePushHandler {

	/**
	 * Campaign 資料庫操作類別
	 *
	 * @var Campaign
	 */
	private Campaign $campaign;

	/**
	 * User 資料庫操作類別
	 *
	 * @var User
	 */
	private User $user;

	/**
	 * 受眾篩選器
	 *
	 * @var AudienceFilter
	 */
	private AudienceFilter $audience_filter;

	/**
	 * 推播發送器
	 *
	 * @var BroadcastSender
	 */
	private BroadcastSender $broadcast_sender;

	/**
	 * WordPress 資料庫抽象層
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb             = $wpdb;
		$this->campaign         = new Campaign( $wpdb );
		$this->user             = new User( $wpdb );
		$this->audience_filter  = new AudienceFilter();
		$this->broadcast_sender = new BroadcastSender();
	}

	/**
	 * 初始化排程 hooks
	 *
	 * @return void
	 */
	public function init_scheduler_hooks(): void {
		add_action( 'otz_process_broadcast_push', array( $this, 'process_scheduled_push' ), 10, 1 );
	}

	/**
	 * 處理排程推播任務（由 Action Scheduler 背景執行）
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	public function process_scheduled_push( int $campaign_id ): void {
		try {
			if ( ! $campaign_id ) {
				throw new \Exception( __( 'Campaign ID 不存在', 'otz' ) );
			}

			// 執行推播（指定為 scheduled 類型）.
			$result = $this->handle_push( $campaign_id, 'scheduled' );

			// 更新 campaign 狀態.
			$this->update_campaign_status( $campaign_id, $result );

		} catch ( \Exception $e ) {
			$this->handle_push_error( $campaign_id, $e );
		}
	}

	/**
	 * 處理推播發送
	 *
	 * @param int    $campaign_id    Campaign ID.
	 * @param string $execution_type 執行類型 ('manual'=手動, 'scheduled'=排程背景執行).
	 * @return array 發送結果 ['success' => bool, 'sent_count' => int, 'log_id' => int].
	 * @throws Exception 當活動不存在、受眾為空或發送失敗時.
	 */
	public function handle_push( int $campaign_id, string $execution_type = 'manual' ): array {
		// 1. 載入 Campaign 資料.
		$campaign = $this->campaign->get_campaign( $campaign_id );

		if ( ! $campaign ) {
			throw new Exception( __( '找不到推播活動', 'otz' ) );
		}

		// 2. 準備受眾列表.
		$audience = $this->prepare_audience( $campaign );

		// 3. 計算目標數量.
		$target_count = 'all_followers' === $campaign['audience_type'] ? 0 : count( $audience );

		// 4. 準備 Log 資料.
		$log_data = $this->prepare_log_data( $campaign, $target_count, $execution_type );

		// 5. 發送推播.
		$result = $this->send_to_audience( $campaign, $audience, $log_data );

		return $result;
	}

	/**
	 * 準備受眾列表
	 *
	 * @param array $campaign Campaign 資料.
	 * @return array 受眾列表（all_followers 時返回空陣列）.
	 * @throws Exception 當受眾為空或類型不支援時.
	 */
	private function prepare_audience( array $campaign ): array {
		$audience_type = $campaign['audience_type'];

		switch ( $audience_type ) {
			case 'all_followers':
				// Broadcast API 不需要受眾列表.
				return array();

			case 'imported_users':
				$friends = $this->get_imported_users();
				if ( empty( $friends ) ) {
					throw new Exception( __( '沒有找到已匯入的好友', 'otz' ) );
				}
				return $friends;

			case 'filtered':
				$conditions = $campaign['filter_conditions'] ?? array();
				$friends    = $this->audience_filter->get_dynamic_filtered_friends( $conditions );
				if ( empty( $friends ) ) {
					throw new Exception( __( '沒有找到符合條件的好友', 'otz' ) );
				}
				return $friends;

			default:
				throw new Exception( sprintf( __( '不支援的受眾類型：%s', 'otz' ), $audience_type ) );
		}
	}

	/**
	 * 取得已匯入的使用者列表
	 *
	 * 從 otz_users 表中取得所有 status='active' 的使用者.
	 *
	 * @return array 使用者列表 [['line_user_id' => '', 'name' => '', 'picture_url' => ''], ...].
	 */
	private function get_imported_users(): array {
		return $this->user->get_active_users();
	}

	/**
	 * 準備 Log 資料
	 *
	 * @param array  $campaign       Campaign 資料.
	 * @param int    $target_count   目標數量.
	 * @param string $execution_type 執行類型.
	 * @return array Log 資料.
	 */
	private function prepare_log_data( array $campaign, int $target_count, string $execution_type ): array {
		return array(
			'campaign_id'            => $campaign['id'],
			'executed_at'            => current_time( 'mysql' ),
			'executed_by'            => get_current_user_id(),
			'execution_type'         => $execution_type,
			'campaign_name_snapshot' => $campaign['campaign_name'],
			'audience_type_snapshot' => $campaign['audience_type'],
			'filter_snapshot'        => $campaign['filter_conditions'] ?? null,
			'message_snapshot'       => array(
				'type'    => $campaign['message_type'],
				'content' => json_decode( $campaign['message_content'], true ),
			),
			'target_count'           => $target_count,
		);
	}

	/**
	 * 發送推播給受眾
	 *
	 * @param array $campaign  Campaign 資料.
	 * @param array $audience  受眾列表（all_followers 時為空陣列）.
	 * @param array $log_data  Log 資料.
	 * @return array 發送結果.
	 * @throws Exception 當訊息建構或發送失敗時.
	 */
	private function send_to_audience( array $campaign, array $audience, array $log_data ): array {
		$audience_type = $campaign['audience_type'];

		// 建構 LINE 訊息格式.
		$messages = $this->broadcast_sender->build_line_messages(
			$campaign['message_type'],
			json_decode( $campaign['message_content'], true )
		);

		// 是否靜音推播.
		$silent_push = (bool) ( $campaign['notification_disabled'] ?? false );

		if ( 'all_followers' === $audience_type ) {
			// 使用 Broadcast API.
			return $this->broadcast_sender->send_broadcast_to_all(
				$messages,
				$silent_push,
				$log_data
			);
		} else {
			// imported_users 或 filtered 都使用 Multicast API.
			return $this->broadcast_sender->send_multicast_message(
				$audience,
				$messages,
				$silent_push,
				$log_data
			);
		}
	}

	/**
	 * 更新 Campaign 執行狀態
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $result      發送結果.
	 * @return void
	 */
	private function update_campaign_status( int $campaign_id, array $result ): void {
		$status = 'success';
		if ( isset( $result['failed_count'] ) && $result['failed_count'] > 0 ) {
			$status = ( $result['sent_count'] > 0 ) ? 'partial' : 'failed';
		}

		$this->campaign->save_campaign(
			array(
				'id'                    => $campaign_id,
				'last_execution_status' => $status,
				'last_executed_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * 處理推播錯誤
	 *
	 * @param int        $campaign_id Campaign ID.
	 * @param \Exception $e           例外物件.
	 * @return void
	 */
	private function handle_push_error( int $campaign_id, \Exception $e ): void {
		// 記錄錯誤.
		Logger::error(
			sprintf( '推播任務執行失敗：%s', $e->getMessage() ),
			array(
				'campaign_id' => $campaign_id,
				'exception'   => $e->getMessage(),
				'trace'       => $e->getTraceAsString(),
			),
			'otz'
		);

		// 更新 campaign 狀態為 failed.
		if ( $campaign_id > 0 ) {
			$this->campaign->save_campaign(
				array(
					'id'                    => $campaign_id,
					'last_execution_status' => 'failed',
					'last_executed_at'      => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * 註冊排程推播任務
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $scheduled_at 排程時間 (MySQL datetime format).
	 * @return int|false Action Scheduler task ID or false on failure.
	 */
	public function schedule_broadcast( int $campaign_id, string $scheduled_at ) {
		try {
			// 將本地時間 (WordPress 時區) 轉換為 UTC timestamp.
			$utc_timestamp = \OrderChatz\Util\Time::convert_local_to_utc( $scheduled_at );

			if ( ! $utc_timestamp || false === $utc_timestamp ) {
				Logger::error(
					'無效的排程時間',
					array( 'scheduled_at' => $scheduled_at ),
					'otz'
				);
				return false;
			}

			// 檢查時間是否在未來.
			if ( $utc_timestamp <= time() ) {
				Logger::error(
					'排程時間必須在未來',
					array(
						'scheduled_at' => $scheduled_at,
						'timestamp'    => $utc_timestamp,
						'current_time' => time(),
					),
					'otz'
				);
				return false;
			}

			// 使用 as_schedule_single_action 註冊單次排程.
			$task_id = as_schedule_single_action(
				$utc_timestamp,
				'otz_process_broadcast_push',
				array( 'campaign_id' => $campaign_id ),
				'otz_broadcast'
			);

			return $task_id ? $task_id : false;

		} catch ( \Exception $e ) {
			Logger::error(
				sprintf( '排程註冊失敗：%s', $e->getMessage() ),
				array(
					'campaign_id' => $campaign_id,
					'exception'   => $e->getMessage(),
				),
				'otz'
			);
			return false;
		}
	}

	/**
	 * 取消排程推播任務
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool 成功返回 true.
	 */
	public function unschedule_broadcast( int $campaign_id ): bool {
		try {
			// 查詢所有 pending 狀態的 action IDs.
			$action_ids = as_get_scheduled_actions(
				array(
					'hook'   => 'otz_process_broadcast_push',
					'args'   => array( 'campaign_id' => $campaign_id ),
					'group'  => 'otz_broadcast',
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				),
				'ids'
			);

			// 逐一刪除每個 action.
			foreach ( $action_ids as $action_id ) {
				\ActionScheduler::store()->delete_action( $action_id );
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				sprintf( '取消排程失敗：%s', $e->getMessage() ),
				array(
					'campaign_id' => $campaign_id,
					'exception'   => $e->getMessage(),
				),
				'otz'
			);
			return false;
		}
	}
}
