<?php
/**
 * OrderChatz 匯出頁面渲染器
 *
 * 處理資料匯出頁面的內容渲染，包含訊息資料匯出、存儲分析、檔案管理等功能
 *
 * @package OrderChatz\Admin\Menu
 * @since 1.0.0
 */

namespace OrderChatz\Admin\Menu\Pages;

use OrderChatz\Admin\Menu\PageRenderer;
use OrderChatz\Util\Logger;

/**
 * 匯出頁面渲染器類別
 *
 * 渲染資料匯出相關功能的管理介面
 */
class Export extends PageRenderer {

	/**
	 * WordPress 資料庫實例
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * 建構函式
	 */
	public function __construct() {
		parent::__construct(
			__( '匯出', 'otz' ),
			'otz-export',
			true // 匯出頁面有頁籤導航
		);

		global $wpdb;
		$this->wpdb = $wpdb;

		// 註冊 AJAX 處理器
		$this->registerAjaxHandlers();
	}

	/**
	 * 註冊 AJAX 處理器
	 *
	 * @return void
	 */
	private function registerAjaxHandlers(): void {
		add_action( 'wp_ajax_otz_export_messages', array( $this, 'handleExportMessages' ) );
		add_action( 'wp_ajax_otz_get_storage_info', array( $this, 'handleGetStorageInfo' ) );
		add_action( 'wp_ajax_otz_download_uploads', array( $this, 'handleDownloadUploads' ) );
		add_action( 'wp_ajax_otz_clear_uploads', array( $this, 'handleClearUploads' ) );
	}

	/**
	 * 渲染匯出頁面內容
	 *
	 * @return void
	 */
	protected function renderPageContent(): void {
		// 載入頁面樣式和腳本
		$this->enqueueAssets();

		// 載入模板檔案
		$template_path = OTZ_PLUGIN_DIR . 'views/admin/export.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			// 後備方案：如果模板不存在，使用原始方法
			$this->renderFallbackContent();
		}
	}

	/**
	 * 載入頁面資源
	 *
	 * @return void
	 */
	private function enqueueAssets(): void {
		wp_enqueue_style(
			'orderchatz-export',
			OTZ_PLUGIN_URL . 'assets/css/admin-export.css',
			array(),
			'1.0.05'
		);

		// 載入腳本
		wp_enqueue_script(
			'orderchatz-export',
			OTZ_PLUGIN_URL . 'assets/js/admin-export.js',
			array( 'jquery' ),
			'1.0.05',
			true
		);

		// 本地化腳本
		wp_localize_script(
			'orderchatz-export',
			'otzExport',
			array(
				'nonce'    => wp_create_nonce( 'orderchatz_export' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'strings'  => array(
					'exporting'     => __( '匯出中...', 'otz' ),
					'loading'       => __( '載入中...', 'otz' ),
					'error'         => __( '操作失敗', 'otz' ),
					'success'       => __( '操作成功', 'otz' ),
					'confirm_clear' => __( '確定要清除所有上傳檔案嗎？此操作無法復原！', 'otz' ),
				),
			)
		);
	}

	/**
	 * 後備渲染方案
	 *
	 * @return void
	 */
	private function renderFallbackContent(): void {
		echo '<div class="orderchatz-export-page">';

		// 渲染訊息匯出區塊
		$this->renderMessageExportSection();

		// 渲染存儲分析區塊
		$this->renderStorageAnalysisSection();

		// 渲染檔案管理區塊
		$this->renderFileManagementSection();

		echo '</div>';
	}

	/**
	 * 渲染訊息匯出區塊
	 *
	 * @return void
	 */
	private function renderMessageExportSection(): void {
		echo '<div class="export-section">';
		echo '<h2>' . esc_html__( '訊息資料匯出', 'otz' ) . '</h2>';
		echo '<div class="export-card">';

		echo '<div class="form-group">';
		echo '<label for="export-date-range">' . esc_html__( '選擇日期範圍', 'otz' ) . '</label>';
		echo '<div class="date-range-inputs">';
		echo '<input type="text" id="export-start-date" name="start_date" placeholder="' . esc_attr__( '開始日期', 'otz' ) . '" readonly>';
		echo '<span class="date-separator">至</span>';
		echo '<input type="text" id="export-end-date" name="end_date" placeholder="' . esc_attr__( '結束日期', 'otz' ) . '" readonly>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( '點擊日期欄位選擇要匯出的訊息日期範圍', 'otz' ) . '</p>';
		echo '</div>';

		echo '<div class="form-group">';
		echo '<label for="export-format">' . esc_html__( '匯出格式', 'otz' ) . '</label>';
		echo '<select id="export-format" name="export_format">';
		echo '<option value="csv">CSV</option>';
		echo '<option value="json">JSON</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="form-actions">';
		echo '<button type="button" id="btn-export-messages" class="button button-primary">';
		echo esc_html__( '匯出訊息', 'otz' );
		echo '</button>';
		echo '<span class="export-status"></span>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * 渲染存儲分析區塊
	 *
	 * @return void
	 */
	private function renderStorageAnalysisSection(): void {
		echo '<div class="export-section">';
		echo '<h2>' . __( '存儲分析', 'otz' ) . '</h2>';
		echo '<div class="export-card">';

		echo '<div class="storage-info" id="storage-info">';
		echo '<div class="loading">' . __( '載入存儲資訊中...', 'otz' ) . '</div>';
		echo '</div>';

		echo '<div class="form-actions">';
		echo '<button type="button" id="btn-refresh-storage" class="button">';
		echo __( '重新整理', 'otz' );
		echo '</button>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * 渲染檔案管理區塊
	 *
	 * @return void
	 */
	private function renderFileManagementSection(): void {
		echo '<div class="export-section">';
		echo '<h2>' . __( '檔案管理', 'otz' ) . '</h2>';
		echo '<div class="export-card">';

		echo '<div class="file-info" id="file-info">';
		echo '<div class="loading">' . __( '載入檔案資訊中...', 'otz' ) . '</div>';
		echo '</div>';

		echo '<div class="form-actions">';
		echo '<button type="button" id="btn-download-uploads" class="button button-secondary">';
		echo __( '打包下載', 'otz' );
		echo '</button>';
		echo '<button type="button" id="btn-clear-uploads" class="button button-delete">';
		echo __( '清除檔案', 'otz' );
		echo '</button>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * 取得可用的訊息月份資料
	 *
	 * @return array 可用月份列表
	 */
	private function getAvailableMessageMonths(): array {
		$months = array();

		try {
			// 查詢所有 otz_messages_ 開頭的資料表
			$tables = $this->wpdb->get_results(
				"SHOW TABLES LIKE '{$this->wpdb->prefix}otz_messages_%'"
			);

			foreach ( $tables as $table ) {
				$table_name = current( $table );
				$suffix     = str_replace( $this->wpdb->prefix . 'otz_messages_', '', $table_name );

				// 驗證是否為有效的年月格式 (YYYY_MM)
				if ( preg_match( '/^(\d{4})_(\d{2})$/', $suffix, $matches ) ) {
					$year  = $matches[1];
					$month = $matches[2];

					// 取得該表的記錄數
					$count = $this->wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" );

					$months[] = array(
						'table_suffix' => $suffix,
						'year'         => $year,
						'month'        => $month,
						'count'        => $count,
					);
				}
			}

			// 按年月排序（最新的在前）
			usort(
				$months,
				function( $a, $b ) {
					return strcmp( $b['table_suffix'], $a['table_suffix'] );
				}
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'取得可用月份失敗',
				array(
					'error' => $e->getMessage(),
				)
			);
		}

		return $months;
	}

	/**
	 * 處理訊息匯出 AJAX 請求
	 *
	 * @return void
	 */
	public function handleExportMessages(): void {
		// 驗證 nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'orderchatz_export' ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		// 檢查權限
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( '權限不足', 'otz' ) );
		}

		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );
		$format     = sanitize_text_field( $_POST['format'] ?? 'csv' );

		if ( empty( $start_date ) || empty( $end_date ) ) {
			wp_send_json_error( __( '請選擇日期範圍', 'otz' ) );
		}

		// 驗證日期格式
		if ( ! $this->isValidDate( $start_date ) || ! $this->isValidDate( $end_date ) ) {
			wp_send_json_error( __( '日期格式無效', 'otz' ) );
		}

		// 驗證日期範圍不超過 31 天
		if ( $this->getDateDifference( $start_date, $end_date ) > 31 ) {
			wp_send_json_error( __( '日期範圍不能超過 31 天', 'otz' ) );
		}

		try {
			$file_url = $this->exportMessagesByDateRange( $start_date, $end_date, $format );
			wp_send_json_success(
				array(
					'file_url' => $file_url,
					'message'  => __( '匯出成功', 'otz' ),
				)
			);
		} catch ( \Exception $e ) {
			Logger::error(
				'訊息匯出失敗',
				array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
					'format'     => $format,
					'error'      => $e->getMessage(),
				)
			);
			wp_send_json_error( __( '匯出失敗：', 'otz' ) . $e->getMessage() );
		}
	}

	/**
	 * 處理存儲資訊 AJAX 請求
	 *
	 * @return void
	 */
	public function handleGetStorageInfo(): void {
		// 驗證 nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'orderchatz_export' ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		try {
			$storage_info = $this->getStorageInfo();
			wp_send_json_success( $storage_info );
		} catch ( \Exception $e ) {
			Logger::error(
				'取得存儲資訊失敗',
				array(
					'error' => $e->getMessage(),
				)
			);
			wp_send_json_error( __( '取得存儲資訊失敗', 'otz' ) );
		}
	}

	/**
	 * 處理上傳檔案打包下載 AJAX 請求
	 *
	 * @return void
	 */
	public function handleDownloadUploads(): void {
		// 驗證 nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'orderchatz_export' ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		// 檢查權限
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( '權限不足', 'otz' ) );
		}

		try {
			$zip_url = $this->createUploadsZip();
			wp_send_json_success(
				array(
					'zip_url' => $zip_url,
					'message' => __( '打包完成', 'otz' ),
				)
			);
		} catch ( \Exception $e ) {
			Logger::error(
				'打包上傳檔案失敗',
				array(
					'error' => $e->getMessage(),
				)
			);
			wp_send_json_error( __( '打包失敗：', 'otz' ) . $e->getMessage() );
		}
	}

	/**
	 * 處理清除上傳檔案 AJAX 請求
	 *
	 * @return void
	 */
	public function handleClearUploads(): void {
		// 驗證 nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'orderchatz_export' ) ) {
			wp_die( __( '安全驗證失敗', 'otz' ) );
		}

		// 檢查權限
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( '權限不足', 'otz' ) );
		}

		try {
			$result = $this->clearUploads();
			/* translators: %d: 已刪除的檔案數量. */
			wp_send_json_success(
				array(
					'message'     => sprintf( __( '已清除 %d 個檔案', 'otz' ), $result['deleted_files'] ),
					'freed_space' => $this->formatFileSize( $result['freed_space'] ),
				)
			);
		} catch ( \Exception $e ) {
			Logger::error(
				'清除上傳檔案失敗',
				array(
					'error' => $e->getMessage(),
				)
			);
			wp_send_json_error( __( '清除失敗：', 'otz' ) . $e->getMessage() );
		}
	}

	/**
	 * 匯出訊息資料
	 *
	 * @param string $month_suffix 月份後綴 (YYYY_MM)
	 * @param string $format 匯出格式
	 * @return string 匯出檔案的 URL
	 */
	private function exportMessages( string $month_suffix, string $format ): string {
		$table_name = $this->wpdb->prefix . 'otz_messages_' . $month_suffix;

		// 檢查資料表是否存在
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( ! $table_exists ) {
			throw new \Exception( __( '指定月份的資料表不存在', 'otz' ) );
		}

		// 取得資料
		$messages = $this->wpdb->get_results( "SELECT * FROM `{$table_name}` ORDER BY sent_date, sent_time", ARRAY_A );

		if ( empty( $messages ) ) {
			throw new \Exception( __( '該月份沒有訊息資料', 'otz' ) );
		}

		// 建立匯出目錄
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/order-chatz/exports/';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		// 產生檔案名稱
		$filename  = sprintf( 'messages_%s_%s.%s', $month_suffix, date( 'YmdHis' ), $format );
		$file_path = $export_dir . $filename;

		// 根據格式匯出
		if ( $format === 'csv' ) {
			$this->exportToCsv( $messages, $file_path );
		} else {
			$this->exportToJson( $messages, $file_path );
		}

		return $upload_dir['baseurl'] . '/order-chatz/exports/' . $filename;
	}

	/**
	 * 匯出為 CSV 格式
	 *
	 * @param array  $data 資料陣列
	 * @param string $file_path 檔案路徑
	 * @return void
	 */
	private function exportToCsv( array $data, string $file_path ): void {
		$fp = fopen( $file_path, 'w' );

		if ( ! $fp ) {
			throw new \Exception( __( '無法建立匯出檔案', 'otz' ) );
		}

		// 設定 UTF-8 BOM 以確保中文正確顯示
		fwrite( $fp, "\xEF\xBB\xBF" );

		// 寫入標題行
		if ( ! empty( $data ) ) {
			fputcsv( $fp, array_keys( $data[0] ) );

			// 寫入資料行
			foreach ( $data as $row ) {
				fputcsv( $fp, $row );
			}
		}

		fclose( $fp );
	}

	/**
	 * 匯出為 JSON 格式
	 *
	 * @param array  $data 資料陣列
	 * @param string $file_path 檔案路徑
	 * @return void
	 */
	private function exportToJson( array $data, string $file_path ): void {
		$json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( file_put_contents( $file_path, $json ) === false ) {
			throw new \Exception( __( '無法建立匯出檔案', 'otz' ) );
		}
	}

	/**
	 * 取得存儲資訊
	 *
	 * @return array 存儲資訊
	 */
	private function getStorageInfo(): array {
		$info = array();

		// 取得所有 OTZ 相關資料表大小
		$tables = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT 
					table_name,
					ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
					table_rows
				FROM information_schema.TABLES 
				WHERE table_schema = DATABASE() 
				AND table_name LIKE %s",
				$this->wpdb->prefix . 'otz_%'
			)
		);

		$info['tables'] = array();
		$total_size     = 0;

		foreach ( $tables as $table ) {
			$info['tables'][] = array(
				'name'           => $table->table_name,
				'size_mb'        => $table->size_mb,
				'rows'           => $table->table_rows,
				'size_formatted' => $this->formatFileSize( $table->size_mb * 1024 * 1024 ),
			);
			$total_size      += $table->size_mb;
		}

		$info['total_db_size'] = $this->formatFileSize( $total_size * 1024 * 1024 );

		// 取得上傳目錄大小
		$upload_dir  = wp_upload_dir()['basedir'] . '/order-chatz/';
		$upload_size = 0;
		$file_count  = 0;

		if ( is_dir( $upload_dir ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $upload_dir, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$upload_size += $file->getSize();
					$file_count++;
				}
			}
		}

		$info['upload_size'] = $this->formatFileSize( $upload_size );
		$info['file_count']  = $file_count;

		return $info;
	}

	/**
	 * 建立上傳檔案的 ZIP 壓縮包
	 *
	 * @return string ZIP 檔案的 URL
	 */
	private function createUploadsZip(): string {
		$upload_dir = wp_upload_dir();
		$source_dir = $upload_dir['basedir'] . '/order-chatz/';

		if ( ! is_dir( $source_dir ) ) {
			throw new \Exception( __( '上傳目錄不存在', 'otz' ) );
		}

		// 建立 ZIP 檔案
		$zip_filename = 'orderchatz_uploads_' . date( 'YmdHis' ) . '.zip';
		$zip_path     = $upload_dir['basedir'] . '/' . $zip_filename;

		$zip    = new \ZipArchive();
		$result = $zip->open( $zip_path, \ZipArchive::CREATE );

		if ( $result !== true ) {
			throw new \Exception( __( '無法建立 ZIP 檔案', 'otz' ) );
		}

		// 遞迴加入檔案
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$relative_path = substr( $file, strlen( $source_dir ) );
				$zip->addFile( $file, $relative_path );
			}
		}

		$zip->close();

		return $upload_dir['baseurl'] . '/' . $zip_filename;
	}

	/**
	 * 清除上傳檔案
	 *
	 * @return array 清除結果
	 */
	private function clearUploads(): array {
		$upload_dir = wp_upload_dir()['basedir'] . '/order-chatz/';

		if ( ! is_dir( $upload_dir ) ) {
			return array(
				'deleted_files' => 0,
				'freed_space'   => 0,
			);
		}

		$deleted_files = 0;
		$freed_space   = 0;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $upload_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$freed_space += $file->getSize();
				unlink( $file );
				$deleted_files++;
			} elseif ( $file->isDir() ) {
				rmdir( $file );
			}
		}

		// 移除主目錄
		if ( is_dir( $upload_dir ) ) {
			rmdir( $upload_dir );
		}

		return array(
			'deleted_files' => $deleted_files,
			'freed_space'   => $freed_space,
		);
	}

	/**
	 * 格式化檔案大小
	 *
	 * @param int $size 檔案大小（位元組）
	 * @return string 格式化後的大小
	 */
	private function formatFileSize( int $size ): string {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$unit  = 0;

		while ( $size >= 1024 && $unit < count( $units ) - 1 ) {
			$size /= 1024;
			$unit++;
		}

		return round( $size, 2 ) . ' ' . $units[ $unit ];
	}

	/**
	 * 驗證日期格式
	 *
	 * @param string $date 日期字符串 (YYYY-MM-DD)
	 * @return bool 是否有效
	 */
	private function isValidDate( string $date ): bool {
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * 計算日期差異
	 *
	 * @param string $start_date 開始日期
	 * @param string $end_date   結束日期
	 * @return int 相差天數
	 */
	private function getDateDifference( string $start_date, string $end_date ): int {
		$start = new \DateTime( $start_date );
		$end   = new \DateTime( $end_date );
		return $start->diff( $end )->days;
	}

	/**
	 * 根據日期範圍匯出訊息資料
	 *
	 * @param string $start_date 開始日期 (YYYY-MM-DD)
	 * @param string $end_date   結束日期 (YYYY-MM-DD)
	 * @param string $format     匯出格式
	 * @return string 匯出檔案的 URL
	 */
	private function exportMessagesByDateRange( string $start_date, string $end_date, string $format ): string {
		// 取得日期範圍內涵蓋的所有月份表格
		$tables = $this->getTablesInDateRange( $start_date, $end_date );

		if ( empty( $tables ) ) {
			throw new \Exception( __( '指定日期範圍內沒有找到對應的資料表', 'otz' ) );
		}

		$all_messages = array();

		// 從每個月份表格查詢資料
		foreach ( $tables as $table_suffix ) {
			$table_name = $this->wpdb->prefix . 'otz_messages_' . $table_suffix;

			// 檢查資料表是否存在
			$table_exists = $this->wpdb->get_var(
				$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
			);

			if ( ! $table_exists ) {
				continue;
			}

			// 查詢指定日期範圍的資料
			$messages = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM `{$table_name}` 
					 WHERE sent_date BETWEEN %s AND %s 
					 ORDER BY sent_date, sent_time",
					$start_date,
					$end_date
				),
				ARRAY_A
			);

			if ( ! empty( $messages ) ) {
				$all_messages = array_merge( $all_messages, $messages );
			}
		}

		if ( empty( $all_messages ) ) {
			throw new \Exception( __( '指定日期範圍內沒有訊息資料', 'otz' ) );
		}

		// 按日期時間排序
		usort(
			$all_messages,
			function( $a, $b ) {
				$date_compare = strcmp( $a['sent_date'], $b['sent_date'] );
				if ( $date_compare === 0 ) {
					return strcmp( $a['sent_time'], $b['sent_time'] );
				}
				return $date_compare;
			}
		);

		// 建立匯出目錄
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/order-chatz/exports/';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		// 產生檔案名稱
		$filename  = sprintf(
			'messages_%s_to_%s_%s.%s',
			str_replace( '-', '', $start_date ),
			str_replace( '-', '', $end_date ),
			date( 'YmdHis' ),
			$format
		);
		$file_path = $export_dir . $filename;

		// 根據格式匯出
		if ( $format === 'csv' ) {
			$this->exportToCsv( $all_messages, $file_path );
		} else {
			$this->exportToJson( $all_messages, $file_path );
		}

		return $upload_dir['baseurl'] . '/order-chatz/exports/' . $filename;
	}

	/**
	 * 取得日期範圍內涵蓋的所有月份表格
	 *
	 * @param string $start_date 開始日期
	 * @param string $end_date   結束日期
	 * @return array 月份後綴陣列
	 */
	private function getTablesInDateRange( string $start_date, string $end_date ): array {
		$tables = array();
		$start  = new \DateTime( $start_date );
		$end    = new \DateTime( $end_date );

		// 取得開始日期的月份
		$current = clone $start;
		$current->modify( 'first day of this month' );

		while ( $current <= $end ) {
			$suffix = $current->format( 'Y_m' );

			// 檢查該月份表格是否存在
			$table_name = $this->wpdb->prefix . 'otz_messages_' . $suffix;
			$exists     = $this->wpdb->get_var(
				$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
			);

			if ( $exists ) {
				$tables[] = $suffix;
			}

			// 移動到下個月
			$current->modify( '+1 month' );
		}

		return $tables;
	}
}
