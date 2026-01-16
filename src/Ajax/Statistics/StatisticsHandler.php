<?php
/**
 * 統計 AJAX 處理器
 *
 * 處理統計相關的 AJAX 請求
 *
 * @package OrderChatz\Ajax\Statistics
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Statistics;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;
use OrderChatz\Services\StatisticsService;

class StatisticsHandler extends AbstractAjaxHandler {

	/**
	 * 統計服務實例
	 *
	 * @var StatisticsService
	 */
	private StatisticsService $statistics_service;

	public function __construct() {
		$this->statistics_service = new StatisticsService();

		// 註冊 AJAX 處理器
		add_action( 'wp_ajax_otz_get_statistics_summary', array( $this, 'getStatisticsSummary' ) );
		add_action( 'wp_ajax_otz_get_message_quota', array( $this, 'getMessageQuota' ) );
		add_action( 'wp_ajax_otz_get_message_consumption', array( $this, 'getMessageConsumption' ) );
		add_action( 'wp_ajax_otz_get_friends_statistics', array( $this, 'getFriendsStatistics' ) );
		add_action( 'wp_ajax_otz_get_message_delivery_stats', array( $this, 'getMessageDeliveryStats' ) );
		add_action( 'wp_ajax_otz_get_multi_day_statistics', array( $this, 'getMultiDayStatistics' ) );

		// 註冊 admin notice 檢查
		add_action( 'admin_notices', array( $this, 'show_quota_warning_notice' ) );
	}

	/**
	 * 取得統計摘要
	 */
	public function getStatisticsSummary() {
		try {
			$this->verifyNonce();

			if ( ! $this->statistics_service->isConfigured() ) {
				throw new Exception( 'LINE API 設定不完整，請先設定 Channel Access Token' );
			}

			$summary = $this->statistics_service->getStatisticsSummary();

			$this->sendSuccess(
				array(
					'summary' => $summary,
					'message' => '統計摘要載入成功',
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得訊息配額
	 */
	public function getMessageQuota() {
		try {
			// 支援兩種 nonce action.
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'orderchatz_chat_action' ) &&
			     ! wp_verify_nonce( $nonce, 'otz_broadcast_action' ) ) {
				throw new Exception( '安全驗證失敗' );
			}

			if ( ! $this->statistics_service->isConfigured() ) {
				throw new Exception( 'LINE API 設定不完整，請先設定 Channel Access Token' );
			}

			$quota = $this->statistics_service->getMessageQuota();

			if ( ! $quota['success'] ) {
				throw new Exception( $quota['message'] );
			}

			$this->sendSuccess(
				array(
					'quota'   => $quota['data'],
					'message' => '訊息配額載入成功',
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得訊息使用量
	 */
	public function getMessageConsumption() {
		try {
			// 支援兩種 nonce action.
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'orderchatz_chat_action' ) &&
			     ! wp_verify_nonce( $nonce, 'otz_broadcast_action' ) ) {
				throw new Exception( '安全驗證失敗' );
			}

			if ( ! $this->statistics_service->isConfigured() ) {
				throw new Exception( 'LINE API 設定不完整，請先設定 Channel Access Token' );
			}

			$consumption = $this->statistics_service->getMessageConsumption();

			if ( ! $consumption['success'] ) {
				throw new Exception( $consumption['message'] );
			}

			$this->sendSuccess(
				array(
					'consumption' => $consumption['data'],
					'message'     => '訊息使用量載入成功',
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得好友統計
	 */
	public function getFriendsStatistics() {
		try {
			$this->verifyNonce();

			if ( ! $this->statistics_service->isConfigured() ) {
				throw new Exception( 'LINE API 設定不完整，請先設定 Channel Access Token' );
			}

			$date    = sanitize_text_field( $_POST['date'] ?? '' );
			$friends = $this->statistics_service->getFriendsStatistics( $date );

			if ( ! $friends['success'] ) {
				throw new Exception( $friends['message'] );
			}

			$this->sendSuccess(
				array(
					'friends' => $friends['data'],
					'date'    => $date ?: date( 'Y-m-d' ),
					'message' => '好友統計載入成功',
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得訊息發送統計
	 */
	public function getMessageDeliveryStats() {
		try {
			$this->verifyNonce();

			if ( ! $this->statistics_service->isConfigured() ) {
				throw new Exception( 'LINE API 設定不完整，請先設定 Channel Access Token' );
			}

			$date           = sanitize_text_field( $_POST['date'] ?? '' );
			$formatted_date = $date ? date( 'Ymd', strtotime( $date ) ) : '';

			// 取得各種訊息類型的發送統計
			$reply     = $this->statistics_service->getReplyMessageDelivery( $formatted_date );
			$push      = $this->statistics_service->getPushMessageDelivery( $formatted_date );
			$multicast = $this->statistics_service->getMulticastMessageDelivery( $formatted_date );
			$broadcast = $this->statistics_service->getBroadcastMessageDelivery( $formatted_date );

			$stats = array(
				'reply'     => $reply['success'] ? $reply['data'] : array(
					'success' => 0,
					'status'  => 'unknown',
				),
				'push'      => $push['success'] ? $push['data'] : array(
					'success' => 0,
					'status'  => 'unknown',
				),
				'multicast' => $multicast['success'] ? $multicast['data'] : array(
					'success' => 0,
					'status'  => 'unknown',
				),
				'broadcast' => $broadcast['success'] ? $broadcast['data'] : array(
					'success' => 0,
					'status'  => 'unknown',
				),
			);

			$this->sendSuccess(
				array(
					'stats'   => $stats,
					'date'    => $date ?: date( 'Y-m-d' ),
					'message' => '訊息發送統計載入成功',
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}

		// 清除配額警告快取，確保最新資料
		$this->clearQuotaWarningCache();
	}

	/**
	 * 取得多天統計數據
	 */
	public function getMultiDayStatistics() {
		try {
			$this->verifyNonce();

			if ( ! $this->statistics_service->isConfigured() ) {
				throw new Exception( 'LINE API 設定不完整，請先設定 Channel Access Token' );
			}

			$days = intval( $_POST['days'] ?? 30 );
			$days = max( 1, min( $days, 90 ) ); // 限制在 1-90 天之間

			$statistics = $this->statistics_service->getMultiDayStatistics( $days );

			$this->sendSuccess(
				array(
					'statistics' => $statistics,
					'days'       => $days,
					'message'    => "過去 {$days} 天統計載入成功",
				)
			);

		} catch ( Exception $e ) {
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 格式化數字顯示
	 *
	 * @param int $number 數字
	 * @return string 格式化後的字串
	 */
	private function formatNumber( int $number ): string {
		if ( $number >= 1000000 ) {
			return round( $number / 1000000, 1 ) . 'M';
		} elseif ( $number >= 1000 ) {
			return round( $number / 1000, 1 ) . 'K';
		}
		return number_format( $number );
	}

	/**
	 * 計算百分比
	 *
	 * @param int $used 已使用數量
	 * @param int $total 總數量
	 * @return float 百分比
	 */
	private function calculatePercentage( int $used, int $total ): float {
		if ( $total <= 0 ) {
			return 0;
		}
		return round( ( $used / $total ) * 100, 1 );
	}

	/**
	 * 驗證日期格式
	 *
	 * @param string $date 日期字串
	 * @return bool 是否為有效日期
	 */
	private function isValidDate( string $date ): bool {
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * 取得日期範圍內的統計
	 *
	 * @param string $start_date 開始日期
	 * @param string $end_date 結束日期
	 * @return array
	 */
	private function getDateRangeStatistics( string $start_date, string $end_date ): array {
		$statistics = array();
		$current    = new \DateTime( $start_date );
		$end        = new \DateTime( $end_date );

		while ( $current <= $end ) {
			$dateString = $current->format( 'Ymd' );

			$reply     = $this->statistics_service->getReplyMessageDelivery( $dateString );
			$push      = $this->statistics_service->getPushMessageDelivery( $dateString );
			$multicast = $this->statistics_service->getMulticastMessageDelivery( $dateString );
			$broadcast = $this->statistics_service->getBroadcastMessageDelivery( $dateString );

			$statistics[] = array(
				'date'      => $current->format( 'Y-m-d' ),
				'reply'     => $reply['success'] ? $reply['data']['success'] : 0,
				'push'      => $push['success'] ? $push['data']['success'] : 0,
				'multicast' => $multicast['success'] ? $multicast['data']['success'] : 0,
				'broadcast' => $broadcast['success'] ? $broadcast['data']['success'] : 0,
			);

			$current->add( new \DateInterval( 'P1D' ) );

			// 避免 API 限制
			usleep( 100000 );
		}

		return $statistics;
	}

	/**
	 * 檢查配額使用率
	 *
	 * @return array 包含使用率資訊的陣列
	 */
	private function check_quota_usage(): array {
		// 檢查緩存，但如果有強制重新整理的參數就跳過
		$force_refresh = isset( $_GET['refresh_quota'] ) && $_GET['refresh_quota'] === '1';
		
		if ( ! $force_refresh ) {
			$cached_result = get_transient( 'otz_quota_usage_check_stats' );
			if ( false !== $cached_result ) {
				return $cached_result;
			}
		}

		$result = array(
			'success'    => false,
			'usage_rate' => 0,
			'used'       => 0,
			'total'      => 0,
			'message'    => '',
		);

		try {
			if ( ! $this->statistics_service->isConfigured() ) {
				$result['message'] = 'LINE API 設定不完整';
				set_transient( 'otz_quota_usage_check_stats', $result, 30 * MINUTE_IN_SECONDS );
				return $result;
			}

			// 取得配額資訊
			$quota = $this->statistics_service->getMessageQuota();
			if ( ! $quota['success'] ) {
				$result['message'] = $quota['message'] ?? '無法取得配額資訊';
				set_transient( 'otz_quota_usage_check_stats', $result, 5 * MINUTE_IN_SECONDS );
				return $result;
			}

			// 取得使用量資訊
			$consumption = $this->statistics_service->getMessageConsumption();
			if ( ! $consumption['success'] ) {
				$result['message'] = $consumption['message'] ?? '無法取得使用量資訊';
				set_transient( 'otz_quota_usage_check_stats', $result, 5 * MINUTE_IN_SECONDS );
				return $result;
			}

			$total_quota = (int) ( $quota['data']['value'] ?? 0 );
			$total_used  = (int) ( $consumption['data']['totalUsage'] ?? 0 );

			// 計算使用率
			$usage_rate = $this->calculatePercentage( $total_used, $total_quota );

			$result = array(
				'success'    => true,
				'usage_rate' => $usage_rate,
				'used'       => $total_used,
				'total'      => $total_quota,
				'message'    => '配額檢查完成',
			);

			// 緩存結果 1 小時
			set_transient( 'otz_quota_usage_check_stats', $result, HOUR_IN_SECONDS );

		} catch ( Exception $e ) {
			$result['message'] = $e->getMessage();
			// 錯誤情況下緩存時間較短
			set_transient( 'otz_quota_usage_check_stats', $result, 5 * MINUTE_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * 顯示配額警告通知
	 */
	public function show_quota_warning_notice(): void {
		// 只在管理後台顯示
		if ( ! is_admin() ) {
			return;
		}

		// 只對有權限的用戶顯示
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// 檢查配額使用率
		$usage_info = $this->check_quota_usage();

		// 如果檢查失敗或使用率未達到警告閾值，不顯示通知
		if ( ! $usage_info['success'] || $usage_info['usage_rate'] < 98 ) {
			return;
		}

		$usage_rate = $usage_info['usage_rate'];
		$used       = $this->formatNumber( $usage_info['used'] );
		$total      = $this->formatNumber( $usage_info['total'] );

		// 根據使用率設定不同的警告等級
		$notice_class   = 'notice notice-warning';
		$message_prefix = '⚠️ 緊急警告';
		if ( $usage_rate >= 98 ) {
			$notice_class   = 'notice notice-error';
			$message_prefix = '🚨 緊急警告';
		}

		printf(
			'<div class="%s is-dismissible"><p><strong>%s：LINE 訊息配額使用率已達 %.1f%%</strong></p><p>已使用：%s / %s 則訊息。建議您注意訊息發送量，避免超過配額限制。</p><p><a href="%s" class="button button-primary">查看詳細統計</a></p></div>',
			esc_attr( $notice_class ),
			esc_html( $message_prefix ),
			(float) $usage_rate,
			esc_html( $used ),
			esc_html( $total ),
			esc_url( admin_url( 'admin.php?page=otz-statistics' ) )
		);
	}

	/**
	 * 清除配額警告快取
	 */
	private function clearQuotaWarningCache(): void {
		delete_transient( 'otz_quota_usage_check_stats' );
		delete_transient( 'otz_quota_usage_check' );
	}
}
