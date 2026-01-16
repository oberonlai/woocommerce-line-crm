<?php

declare(strict_types=1);

namespace OrderChatz\Ajax\Message;

use Exception;
use OrderChatz\Database\Message\TableMessageCron;
use OrderChatz\Ajax\Message\LineApiService;
use OrderChatz\Ajax\Message\MessageStorageService;

/**
 * 排程訊息發送處理器
 *
 * 負責處理預約排程訊息的實際發送作業
 *
 * @package    OrderChatz
 * @subpackage Ajax
 * @since      1.1.0
 */
class MessageCronHandler {

	/**
	 * 排程訊息資料庫操作類別
	 *
	 * @var TableMessageCron
	 */
	private TableMessageCron $cron_table;

	/**
	 * LINE API 服務
	 *
	 * @var LineApiService
	 */
	private LineApiService $line_api_service;

	/**
	 * 訊息儲存服務
	 *
	 * @var MessageStorageService
	 */
	private MessageStorageService $message_storage_service;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->cron_table              = new TableMessageCron( $wpdb );
		$this->line_api_service        = new LineApiService();
		$this->message_storage_service = new MessageStorageService();

		$this->register_hooks();
	}

	/**
	 * 註冊 Action Scheduler hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'otz_send_scheduled_message', array( $this, 'send_scheduled_message' ), 10, 1 );
	}

	/**
	 * 發送排程訊息
	 *
	 * @param int $cron_message_id 排程訊息 ID.
	 * @return void
	 */
	public function send_scheduled_message( int $cron_message_id ): void {
		try {

			// 從資料庫查詢完整的排程資料.
			$cron_message = $this->cron_table->get_cron_message( $cron_message_id );
			if ( ! $cron_message ) {
				$this->log_error( "Cron message not found: {$cron_message_id}" );
				return;
			}

			// 更新狀態為 processing.
			$this->cron_table->update_status( $cron_message_id, 'processing' );

			$line_user_id    = $cron_message->line_user_id;
			$message_type    = $cron_message->message_type;
			$message_content = $cron_message->message_content;
			$source_type     = $cron_message->source_type ?? '';
			$group_id        = $cron_message->group_id ?? '';

			// 根據訊息類型分發處理.
			switch ( $message_type ) {
				case 'text':
					$result = $this->send_text_message( $line_user_id, $message_content, $source_type, $group_id );
					break;
				case 'image':
					$result = $this->send_image_message( $line_user_id, $message_content, $source_type, $group_id );
					break;
				case 'file':
					$result = $this->send_file_message( $line_user_id, $message_content, $source_type, $group_id );
					break;
				case 'video':
					$result = $this->send_video_message( $line_user_id, $message_content, $source_type, $group_id );
					break;
				default:
					throw new Exception( "Unsupported message type: {$message_type}" );
			}

			if ( ! $result['success'] ) {
				$this->cron_table->update_status( $cron_message_id, 'failed' );
				throw new Exception( $result['error'] );
			}

			// 解析排程資料，判斷是否為重複排程.
			$schedule_data = json_decode( $cron_message->schedule, true );
			$is_recurring  = ( isset( $schedule_data['type'] ) && $schedule_data['type'] === 'recurring' );

			// 單次排程更新為 completed，重複排程保持 pending 狀態.
			if ( ! $is_recurring ) {
				$this->cron_table->update_status( $cron_message_id, 'completed' );
			}

			// 處理重複排程的下次執行記錄.
			$this->handle_recurring_schedule( $cron_message );

		} catch ( Exception $e ) {
			$this->cron_table->update_status( $cron_message_id, 'failed' );
			$this->log_error( 'Error in send_scheduled_message: ' . $e->getMessage() );
		}
	}

	/**
	 * 發送文字訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $message_content 訊息內容.
	 * @param string $source_type 來源類型.
	 * @param string $group_id 群組 ID.
	 * @return array 發送結果.
	 */
	private function send_text_message( string $line_user_id, string $message_content, string $source_type = '', string $group_id = '' ): array {
		if ( empty( $message_content ) ) {
			return array(
				'success' => false,
				'error'   => 'Empty message content',
			);
		}

		$result = $this->line_api_service->sendPushMessage( $line_user_id, $message_content, '', null, $source_type, $group_id );

		if ( $result['success'] ) {
			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveOutboundMessage(
				$line_user_id,
				$message_content,
				'push',
				$line_message_id,
				$api_response_quote_token,
				'',
				$source_type,
				$group_id
			);
		}

		return $result;
	}

	/**
	 * 發送圖片訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $message_content 訊息內容（JSON 格式）.
	 * @param string $source_type 來源類型.
	 * @param string $group_id 群組 ID.
	 * @return array 發送結果.
	 */
	private function send_image_message( string $line_user_id, string $message_content, string $source_type = '', string $group_id = '' ): array {
		// 解析 JSON 格式的圖片資訊.
		$image_data = json_decode( $message_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'error'   => 'Invalid image data format',
			);
		}

		$image_url = $image_data['file_url'] ?? '';

		if ( empty( $image_url ) ) {
			return array(
				'success' => false,
				'error'   => 'Missing image URL',
			);
		}

		$image_message = array(
			'type'               => 'image',
			'originalContentUrl' => $image_url,
			'previewImageUrl'    => $image_url,
		);

		$result = $this->line_api_service->sendPushImageMessage( $line_user_id, $image_message, $source_type, $group_id );

		if ( $result['success'] ) {
			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveImageMessage(
				$line_user_id,
				$image_url,
				'push',
				$line_message_id,
				$api_response_quote_token,
				'',
				$source_type,
				$group_id
			);
		}

		return $result;
	}

	/**
	 * 發送檔案訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $message_content 訊息內容（JSON 格式）.
	 * @param string $source_type 來源類型.
	 * @param string $group_id 群組 ID.
	 * @return array 發送結果.
	 */
	private function send_file_message( string $line_user_id, string $message_content, string $source_type = '', string $group_id = '' ): array {
		// 解析 JSON 格式的檔案資訊.
		$file_data = json_decode( $message_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'error'   => 'Invalid file data format',
			);
		}

		$file_url  = $file_data['file_url'] ?? '';
		$file_name = $file_data['file_name'] ?? '';
		$file_size = $file_data['file_size'] ?? 0;

		if ( empty( $file_url ) || empty( $file_name ) ) {
			return array(
				'success' => false,
				'error'   => 'Missing file URL or filename',
			);
		}

		$formatted_file_name = $this->line_api_service->formatFileNameForLine( $file_name );

		$file_content = sprintf(
			"📁 %s\n📊 檔案大小：%s\n\n🔗 下載連結：%s",
			$formatted_file_name,
			$this->format_file_size( $file_size ),
			$file_url
		);

		$result = $this->line_api_service->sendPushMessage( $line_user_id, $file_content, '', null, $source_type, $group_id );

		if ( $result['success'] ) {
			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveFileMessage(
				$line_user_id,
				$file_url,
				$file_name,
				'push',
				$line_message_id,
				$api_response_quote_token,
				'',
				$source_type,
				$group_id
			);
		}

		return $result;
	}

	/**
	 * 發送影片訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $message_content 訊息內容（JSON 格式）.
	 * @param string $source_type 來源類型.
	 * @param string $group_id 群組 ID.
	 * @return array 發送結果.
	 */
	private function send_video_message( string $line_user_id, string $message_content, string $source_type = '', string $group_id = '' ): array {
		// 解析 JSON 格式的影片資訊.
		$video_data = json_decode( $message_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'error'   => 'Invalid video data format',
			);
		}

		$video_url  = $video_data['file_url'] ?? '';
		$video_name = $video_data['file_name'] ?? '';
		$video_size = $video_data['file_size'] ?? 0;

		if ( empty( $video_url ) || empty( $video_name ) ) {
			return array(
				'success' => false,
				'error'   => 'Missing video URL or filename',
			);
		}

		$formatted_video_name = $this->line_api_service->formatFileNameForLine( $video_name );

		$video_content = sprintf(
			"🎥 %s\n\n🔗 觀看連結：%s\n\n📊 檔案大小：%s",
			$formatted_video_name,
			$video_url,
			$this->format_file_size( $video_size )
		);

		$result = $this->line_api_service->sendPushMessage( $line_user_id, $video_content, '', null, $source_type, $group_id );

		if ( $result['success'] ) {
			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveVideoMessage(
				$line_user_id,
				$video_url,
				$video_name,
				'push',
				$line_message_id,
				$api_response_quote_token,
				'',
				$source_type,
				$group_id
			);
		}

		return $result;
	}

	/**
	 * 格式化檔案大小
	 *
	 * @param int $size 檔案大小（位元組）.
	 * @return string 格式化的檔案大小.
	 */
	private function format_file_size( int $size ): string {
		if ( $size >= 1024 * 1024 ) {
			return round( $size / 1024 / 1024, 2 ) . ' MB';
		} elseif ( $size >= 1024 ) {
			return round( $size / 1024, 2 ) . ' KB';
		} else {
			return $size . ' bytes';
		}
	}

	/**
	 * 記錄錯誤日誌
	 *
	 * @param string $message 錯誤訊息.
	 * @return void
	 */
	private function log_error( string $message ): void {
		$logger = wc_get_logger();
		$logger->error( $message, array( 'source' => 'otz-cron' ) );
	}

	/**
	 * 處理重複排程的下次執行記錄
	 *
	 * @param object $cron_message 排程訊息記錄.
	 * @return void
	 */
	private function handle_recurring_schedule( object $cron_message ): void {
		// 重複排程不新增記錄，由 Action Scheduler 自動處理重複執行.
		return;
	}
}
