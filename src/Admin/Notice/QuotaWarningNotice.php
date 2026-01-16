<?php
/**
 * OrderChatz 配額警告通知類別
 *
 * 處理 LINE API 配額使用率警告通知
 *
 * @package OrderChatz\Admin\Notice
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Notice;

use OrderChatz\Services\StatisticsService;

/**
 * 配額警告通知類別
 *
 * 當 LINE API 使用率超過 80% 時顯示警告通知
 */
class QuotaWarningNotice {

	/**
	 * 統計服務實例
	 *
	 * @var StatisticsService
	 */
	private $statistics_service;

	/**
	 * 警告閾值（百分比）
	 *
	 * @var float
	 */
	private $warning_threshold = 90.0;

	/**
	 * 建構函式
	 */
	public function __construct() {
		$this->statistics_service = new StatisticsService();
		$this->init();
	}

	/**
	 * 初始化
	 */
	private function init(): void {
		// 只在 OrderChatz 相關頁面顯示通知
		add_action( 'admin_notices', array( $this, 'displayQuotaWarning' ) );

		// 載入樣式
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueStyles' ) );
	}

	/**
	 * 載入樣式
	 */
	public function enqueueStyles(): void {
		// 只在 OrderChatz 相關頁面載入樣式
		if ( ! $this->isOrderChatzPage() ) {
			return;
		}

		wp_enqueue_style(
			'orderchatz-quota-warning',
			OTZ_PLUGIN_URL . 'assets/css/admin/quota-warning.css',
			array(),
			'1.0.01'
		);
	}

	/**
	 * 顯示配額警告通知
	 */
	public function displayQuotaWarning(): void {
		// 檢查是否在 OrderChatz 相關頁面
		if ( ! $this->isOrderChatzPage() ) {
			return;
		}

		// 檢查是否需要強制重新整理
		$force_refresh = isset( $_GET['refresh_quota'] ) && $_GET['refresh_quota'] === '1';

		// 每小時檢查一次配額狀況（使用 transient 快取）
		$usage_data = false;
		if ( ! $force_refresh ) {
			$usage_data = get_transient( 'otz_quota_usage_check' );
		}

		if ( false === $usage_data ) {
			$usage_data = $this->getQuotaUsageData();
			// 快取 1 小時
			set_transient( 'otz_quota_usage_check', $usage_data, HOUR_IN_SECONDS );
		}

		if ( ! $usage_data || ! isset( $usage_data['usage_percentage'] ) ) {
			return;
		}

		$usage_percentage = $usage_data['usage_percentage'];
		$remaining_quota  = $usage_data['remaining_quota'];
		$total_quota      = $usage_data['total_quota'];

		// 如果使用率超過警告閾值，顯示通知
		if ( $usage_percentage >= $this->warning_threshold ) {
			$this->renderWarningNotice( $usage_percentage, $remaining_quota, $total_quota );
		}
	}

	/**
	 * 檢查是否在 OrderChatz 相關頁面
	 *
	 * @return bool
	 */
	private function isOrderChatzPage(): bool {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// 檢查是否在 OrderChatz 主選單頁面
		// 檢查 screen id 包含 orderchatz 或 otz
		if ( strpos( $screen->id, 'orderchatz' ) !== false || strpos( $screen->id, 'otz' ) !== false ) {
			return true;
		}

		// 檢查 screen base 包含 orderchatz 或 otz
		if ( strpos( $screen->base, 'orderchatz' ) !== false || strpos( $screen->base, 'otz' ) !== false ) {
			return true;
		}

		// 檢查 GET 參數的 page 值
		if ( isset( $_GET['page'] ) ) {
			$page = $_GET['page'];
			// 檢查是否為 OrderChatz 相關頁面
			return strpos( $page, 'orderchatz' ) !== false || strpos( $page, 'otz' ) !== false;
		}

		return false;
	}

	/**
	 * 獲取配額使用數據
	 *
	 * @return array|null
	 */
	private function getQuotaUsageData(): ?array {
		try {
			// 獲取配額資料
			$quota_result = $this->statistics_service->getMessageQuota();
			if ( ! $quota_result['success'] ) {
				return null;
			}

			// 獲取使用量資料
			$consumption_result = $this->statistics_service->getMessageConsumption();
			if ( ! $consumption_result['success'] ) {
				return null;
			}

			$total_quota      = $quota_result['data']['value'] ?? 0;
			$used_quota       = $consumption_result['data']['totalUsage'] ?? 0;
			$remaining_quota  = max( 0, $total_quota - $used_quota );
			$usage_percentage = $total_quota > 0 ? ( $used_quota / $total_quota ) * 100 : 0;

			return array(
				'total_quota'      => $total_quota,
				'used_quota'       => $used_quota,
				'remaining_quota'  => $remaining_quota,
				'usage_percentage' => $usage_percentage,
			);

		} catch ( \Exception $e ) {
			error_log( 'OrderChatz Quota Check Error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * 渲染警告通知
	 *
	 * @param float $usage_percentage 使用率百分比
	 * @param int   $remaining_quota  剩餘配額
	 * @param int   $total_quota      總配額
	 */
	private function renderWarningNotice( float $usage_percentage, int $remaining_quota, int $total_quota ): void {
		$notice_class   = 'notice otz-quota-warning-notice is-dismissible';
		$progress_class = 'medium';

		// 根據使用率決定樣式
		if ( $usage_percentage >= 95 ) {
			$notice_class  .= ' critical';
			$progress_class = 'critical';
		} elseif ( $usage_percentage >= 90 ) {
			$progress_class = 'high';
		} elseif ( $usage_percentage >= 75 ) {
			$progress_class = 'high';
		}

		?>
		<div class="<?php echo esc_attr( $notice_class ); ?>">
			<div class="notice-content">
				<div class="notice-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="notice-text">
					<h3 class="notice-title">
						<?php esc_html_e( 'LINE API 配額警告', 'otz' ); ?>
					</h3>
					<p class="notice-description">
						<?php
						printf(
							/* translators: 1: usage percentage, 2: remaining quota, 3: total quota */
							esc_html__( '您的 LINE Messaging API 使用率已達 %1$.1f%%，剩餘配額：%2$s / %3$s', 'otz' ),
							$usage_percentage,
							number_format( $remaining_quota ),
							number_format( $total_quota )
						);
						?>
					</p>
					<div class="otz-quota-progress">
						<div class="otz-quota-progress-bar <?php echo esc_attr( $progress_class ); ?>" 
							 style="width: <?php echo min( 100, $usage_percentage ); ?>%;"></div>
					</div>
					<?php if ( $usage_percentage >= 95 ) : ?>
						<p class="notice-critical">
							<?php esc_html_e( '⚠️ 配額即將用完，請注意訊息發送量！', 'otz' ); ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="notice-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=orderchatz&subpage=statistics' ) ); ?>" 
					   class="button button-secondary">
						<?php esc_html_e( '查看統計', 'otz' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 設定警告閾值
	 *
	 * @param float $threshold 閾值百分比 (0-100)
	 */
	public function setWarningThreshold( float $threshold ): void {
		$this->warning_threshold = max( 0, min( 100, $threshold ) );
	}

	/**
	 * 清除配額檢查快取
	 */
	public function clearQuotaCache(): void {
		delete_transient( 'otz_quota_usage_check' );
	}
}
