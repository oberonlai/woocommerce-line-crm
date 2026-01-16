<?php
/**
 * OrderChatz 統計頁面渲染器
 *
 * 處理 LINE Messaging API 使用量統計頁面的內容渲染
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;

/**
 * 統計頁面渲染器類別
 *
 * 渲染 LINE Messaging API 使用統計介面，包含訊息配額、發送統計、好友數量等資訊
 */
class Statistics extends PageRenderer {
	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( '統計', 'otz' ),
			'otz-statistics',
			true // 統計頁面有頁籤導航
		);
	}

	/**
	 * 渲染統計頁面內容
	 * 覆寫父類別方法，載入統計介面資源並渲染統計介面
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		// 載入統計介面專用資源
		$this->enqueueAssets();

		// 渲染統計介面
		$this->renderStatisticsInterface();
	}

	/**
	 * 載入統計介面所需的 CSS 和 JavaScript 資源
	 *
	 * @return void
	 */
	private function enqueueAssets(): void {
		// 載入統計頁面樣式
		wp_enqueue_style(
			'orderchatz-statistics',
			OTZ_PLUGIN_URL . 'assets/css/admin/statistics.css',
			array(),
			'1.0.02'
		);

		// 載入 Chart.js
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
			array(),
			'3.9.1',
			true
		);

		// 載入統計頁面 JavaScript
		wp_enqueue_script(
			'orderchatz-statistics',
			OTZ_PLUGIN_URL . 'assets/js/admin/statistics.js',
			array( 'jquery', 'chart-js' ),
			'1.0.05',
			true
		);

		// 傳遞 AJAX 配置到前端
		wp_localize_script(
			'orderchatz-statistics',
			'otzStatisticsConfig',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'orderchatz_chat_action' ),
				'strings'  => array(
					'loading'         => __( '載入中...', 'otz' ),
					'error'           => __( '載入失敗', 'otz' ),
					'no_data'         => __( '暫無數據', 'otz' ),
					'retry'           => __( '重試', 'otz' ),
					'refresh'         => __( '重新整理', 'otz' ),
					'quota_exceeded'  => __( '配額已超過', 'otz' ),
					'quota_remaining' => __( '剩餘配額', 'otz' ),
					'messages_sent'   => __( '已發送訊息', 'otz' ),
					'daily_usage'     => __( '每日使用量', 'otz' ),
					'monthly_usage'   => __( '每月使用量', 'otz' ),
				),
			)
		);
	}

	/**
	 * 渲染統計介面
	 *
	 * @return void
	 */
	private function renderStatisticsInterface(): void {
		// 載入統計介面模板
		$template_path = OTZ_PLUGIN_DIR . 'views/admin/statistics-interface.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>' . __( '統計介面模板檔案不存在', 'otz' ) . '</p></div>';
		}
	}

	/**
	 * 獲取頁面標題
	 *
	 * @return string
	 */
	public function getPageTitle(): string {
		return __( 'LINE Messaging API 統計', 'otz' );
	}

	/**
	 * 獲取頁面描述
	 *
	 * @return string
	 */
	public function getPageDescription(): string {
		return __( '查看 LINE Messaging API 的使用統計，包含訊息配額、發送數量、好友統計等資訊', 'otz' );
	}
}
