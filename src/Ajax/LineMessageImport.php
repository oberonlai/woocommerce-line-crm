<?php
/**
 * LINE 訊息匯入 AJAX 處理器
 *
 * 處理 LINE 聊天記錄匯入的相關 AJAX 請求
 *
 * @package OrderChatz\Ajax
 * @since 1.0.18
 */

namespace OrderChatz\Ajax;

use OrderChatz\Database\Message\TableMessageImport;
use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Util\Logger;
use Exception;

class LineMessageImport extends AbstractAjaxHandler {

	/**
	 * TableMessageImport instance
	 *
	 * @var TableMessageImport
	 */
	private TableMessageImport $message_importer;

	/**
	 * DynamicTableManager instance
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $table_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;

		// 從 Init 容器獲取依賴項.
		$init               = \OrderChatz\Core\Init::get_instance();
		$error_handler      = $init->get_component( 'error_handler' );
		$security_validator = $init->get_component( 'security_validator' );

		// 創建 logger 實例（如果 WooCommerce 可用）.
		$logger = null;
		if ( class_exists( 'WC_Logger' ) ) {
			$logger = new \WC_Logger();
		}

		$this->table_manager    = new DynamicTableManager( $wpdb, $logger, $error_handler, $security_validator );
		$this->message_importer = new TableMessageImport( $wpdb, $this->table_manager );

		$this->register_ajax_actions();
	}

	/**
	 * 註冊 AJAX 動作
	 *
	 * @return void
	 */
	public function register_ajax_actions(): void {
		add_action( 'wp_ajax_otz_preview_csv_messages', array( $this, 'preview_csv_messages' ) );
		add_action( 'wp_ajax_otz_import_messages', array( $this, 'import_messages' ) );
	}

	/**
	 * 預覽 CSV 訊息
	 *
	 * @return void
	 */
	public function preview_csv_messages(): void {
		try {
			// 驗證權限和 nonce
			if ( ! $this->validate_permissions() ) {
				wp_send_json_error( array( 'message' => '權限不足' ) );
				return;
			}

			// 處理檔案上傳
			$upload_result = $this->handle_file_upload();
			if ( ! $upload_result['success'] ) {
				wp_send_json_error( $upload_result );
				return;
			}

			$csv_path     = $upload_result['file_path'];
			$nonce        = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

			if ( empty( $line_user_id ) ) {
				wp_send_json_error( array( 'message' => 'LINE 使用者 ID 不能為空' ) );
				return;
			}

			// 準備篩選選項
			$options = array();
			if ( isset( $_POST['page'] ) ) {
				$options['page'] = intval( $_POST['page'] );
			}
			if ( isset( $_POST['per_page'] ) ) {
				$options['per_page'] = intval( $_POST['per_page'] );
			}
			if ( isset( $_POST['start_date'] ) && ! empty( $_POST['start_date'] ) ) {
				$options['start_date'] = sanitize_text_field( wp_unslash( $_POST['start_date'] ) );
			}
			if ( isset( $_POST['end_date'] ) && ! empty( $_POST['end_date'] ) ) {
				$options['end_date'] = sanitize_text_field( wp_unslash( $_POST['end_date'] ) );
			}
			if ( isset( $_POST['start_time'] ) && ! empty( $_POST['start_time'] ) ) {
				$options['start_time'] = sanitize_text_field( wp_unslash( $_POST['start_time'] ) );
			}
			if ( isset( $_POST['end_time'] ) && ! empty( $_POST['end_time'] ) ) {
				$options['end_time'] = sanitize_text_field( wp_unslash( $_POST['end_time'] ) );
			}
			if ( isset( $_POST['sender_types'] ) && is_array( $_POST['sender_types'] ) ) {
				$options['sender_types'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['sender_types'] ) );
			}

			// 執行預覽
			$result = $this->message_importer->preview_csv_messages( $csv_path, $line_user_id, $options );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			Logger::error( "預覽 CSV 訊息失敗: {$e->getMessage()}" );
			wp_send_json_error( array( 'message' => '預覽過程發生錯誤: ' . $e->getMessage() ) );
		}
	}

	/**
	 * 匯入訊息
	 *
	 * @return void
	 */
	public function import_messages(): void {
		try {
			// 驗證權限和 nonce
			if ( ! $this->validate_permissions() ) {
				wp_send_json_error( array( 'message' => '權限不足' ) );
				return;
			}

			$nonce = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

			$line_user_id         = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';
			$parsed_messages_json = ( isset( $_POST['parsed_messages'] ) ) ? wp_unslash( $_POST['parsed_messages'] ) : '';

			if ( empty( $line_user_id ) ) {
				wp_send_json_error( array( 'message' => 'LINE 使用者 ID 不能為空' ) );
				return;
			}

			if ( empty( $parsed_messages_json ) ) {
				wp_send_json_error( array( 'message' => '沒有要匯入的訊息資料' ) );
				return;
			}

			// 解析 JSON 資料
			$parsed_messages = json_decode( $parsed_messages_json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( array( 'message' => 'JSON 資料格式錯誤' ) );
				return;
			}

			// 準備選項
			$options = array();
			if ( isset( $_POST['selected_indices'] ) ) {
				$selected_indices = wp_unslash( $_POST['selected_indices'] );

				// 處理可能是字串的情況.
				if ( is_string( $selected_indices ) ) {
					// 如果包含逗號，分割成陣列.
					if ( strpos( $selected_indices, ',' ) !== false ) {
						$selected_indices = explode( ',', $selected_indices );
					} else {
						// 單一值，轉換為陣列.
						$selected_indices = array( $selected_indices );
					}
				}

				$options['selected_indices'] = array_map( 'intval', $selected_indices );
			}

			$result = $this->message_importer->import_parsed_messages( $parsed_messages, $line_user_id, $options );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			Logger::error( "匯入訊息失敗: {$e->getMessage()}" );
			wp_send_json_error( array( 'message' => '匯入過程發生錯誤: ' . $e->getMessage() ) );
		}
	}

	/**
	 * 驗證權限
	 *
	 * @return bool 是否有權限
	 */
	private function validate_permissions(): bool {
		// 檢查用戶是否登入
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// 檢查 nonce
		$nonce = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'otz_import_messages' ) ) {
			return false;
		}

		// 檢查用戶權限（需要管理權限）
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 處理檔案上傳
	 *
	 * @return array 上傳結果
	 */
	private function handle_file_upload(): array {
		// 檢查是否有檔案上傳
		if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			return array(
				'success' => false,
				'message' => '檔案上傳失敗',
			);
		}

		$file = $_FILES['csv_file'];

		// 驗證檔案類型
		$file_type = wp_check_filetype( $file['name'] );
		if ( $file_type['ext'] !== 'csv' ) {
			return array(
				'success' => false,
				'message' => '只允許上傳 CSV 檔案',
			);
		}

		// 檢查檔案大小（限制 10MB）
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			return array(
				'success' => false,
				'message' => '檔案大小不能超過 10MB',
			);
		}

		// 建立上傳目錄
		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/order-chatz/imports/';

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return array(
				'success' => false,
				'message' => '無法建立上傳目錄',
			);
		}

		// 生成唯一檔名
		$file_name   = uniqid( 'import_' ) . '_' . sanitize_file_name( $file['name'] );
		$target_path = $target_dir . $file_name;

		// 移動檔案
		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
			return array(
				'success' => false,
				'message' => '檔案儲存失敗',
			);
		}

		return array(
			'success'   => true,
			'file_path' => $target_path,
			'file_name' => $file_name,
		);
	}
}
