<?php
/**
 * 訊息儲存服務
 *
 * 處理所有訊息儲存相關的業務邏輯
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;

class MessageStorageService extends AbstractAjaxHandler {

	/**
	 * 儲存外發訊息到資料庫
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $message 訊息內容.
	 * @param string      $api_used 使用的 API.
	 * @param string|null $line_message_id LINE 訊息 ID.
	 * @param string|null $quote_token 這則訊息的 quote token（從 LINE API 取得，供別人引用用）.
	 * @param string|null $quoted_message_id 被引用訊息的 ID（如果這則訊息是回覆某則訊息）.
	 * @return void
	 */
	public function saveOutboundMessage( $line_user_id, $message, $api_used, $line_message_id, $quote_token = null, $quoted_message_id = null, $source_type = '', $group_id = '' ) {
		try {
			$current_month = date( 'Y_m' );
			$table_name    = $this->prepare_message_table( $current_month );
			$sender_name   = $this->get_sender_info();

			$message_data = $this->prepare_base_message_data(
				$line_user_id,
				'text',
				$message,
				$api_used,
				$line_message_id,
				$quote_token,
				$quoted_message_id,
				$sender_name,
				$source_type,
				$group_id
			);

			$message_data['raw_payload'] = wp_json_encode(
				array(
					'api_used'        => $api_used,
					'sent_by_admin'   => true,
					'admin_user_id'   => get_current_user_id(),
					'line_message_id' => $line_message_id,
					'quote_token'     => $quote_token,
				)
			);

			$format_array = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			);

			$this->insert_message_record( $table_name, $message_data, $format_array );
		} catch ( Exception $e ) {
			$this->logError( '儲存外發訊息時發生錯誤: ' . $e->getMessage() );
		}
	}

	/**
	 * 儲存圖片訊息到資料庫
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $image_url 圖片 URL.
	 * @param string      $api_used 使用的 API.
	 * @param string|null $line_message_id LINE message ID 為訊息關聯.
	 * @return void
	 */
	public function saveImageMessage( $line_user_id, $image_url, $api_used, $line_message_id = null, $quote_token = null, $quoted_message_id = null, $source_type = '', $group_id = '' ) {
		try {
			$current_month = date( 'Y_m' );
			$table_name    = $this->prepare_message_table( $current_month );
			$sender_name   = $this->get_sender_info();

			$image_content = wp_json_encode(
				array(
					'originalContentUrl' => $image_url,
					'previewImageUrl'    => $image_url,
				)
			);

			$message_data = $this->prepare_base_message_data(
				$line_user_id,
				'image',
				$image_content,
				$api_used,
				$line_message_id,
				$quote_token,
				$quoted_message_id,
				$sender_name,
				$source_type,
				$group_id
			);

			$message_data['raw_payload'] = wp_json_encode(
				array(
					'api_used'        => $api_used,
					'sent_by_admin'   => true,
					'admin_user_id'   => get_current_user_id(),
					'image_url'       => $image_url,
					'line_message_id' => $line_message_id,
				)
			);

			$format_array = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			);

			$this->insert_message_record( $table_name, $message_data, $format_array );
		} catch ( Exception $e ) {
			$this->logError( '儲存圖片訊息時發生錯誤: ' . $e->getMessage() );
		}
	}

	/**
	 * 儲存文件訊息到資料庫
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $file_url 文件 URL.
	 * @param string      $file_name 文件名稱.
	 * @param string      $api_used 使用的 API.
	 * @param string|null $line_message_id LINE message ID 為訊息關聯.
	 * @return void
	 */
	public function saveFileMessage( $line_user_id, $file_url, $file_name, $api_used, $line_message_id = null, $quote_token = null, $quoted_message_id = null, $source_type = '', $group_id = '' ) {
		try {
			$current_month = date( 'Y_m' );
			$table_name    = $this->prepare_message_table( $current_month );
			$sender_name   = $this->get_sender_info();

			$file_content = wp_json_encode(
				array(
					'file_url'  => $file_url,
					'file_name' => $file_name,
				)
			);

			$message_data = $this->prepare_base_message_data(
				$line_user_id,
				'file',
				$file_content,
				$api_used,
				$line_message_id,
				$quote_token,
				$quoted_message_id,
				$sender_name,
				$source_type,
				$group_id
			);

			$message_data['raw_payload'] = wp_json_encode(
				array(
					'api_used'        => $api_used,
					'sent_by_admin'   => true,
					'admin_user_id'   => get_current_user_id(),
					'file_url'        => $file_url,
					'file_name'       => $file_name,
					'line_message_id' => $line_message_id,
				)
			);

			$format_array = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			);

			$this->insert_message_record( $table_name, $message_data, $format_array );
		} catch ( Exception $e ) {
			$this->logError( '儲存文件訊息時發生錯誤: ' . $e->getMessage() );
		}
	}

	/**
	 * 儲存影片訊息到資料庫
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $video_url 影片 URL.
	 * @param string      $video_name 影片名稱.
	 * @param string      $api_used 使用的 API.
	 * @param string|null $line_message_id LINE message ID 為訊息關聯.
	 * @return void
	 */
	public function saveVideoMessage( $line_user_id, $video_url, $video_name, $api_used, $line_message_id = null, $quote_token = null, $quoted_message_id = null, $source_type = '', $group_id = '' ) {
		try {
			$current_month = date( 'Y_m' );
			$table_name    = $this->prepare_message_table( $current_month );
			$sender_name   = $this->get_sender_info();

			$video_content = wp_json_encode(
				array(
					'video_url'  => $video_url,
					'video_name' => $video_name,
				)
			);

			$message_data = $this->prepare_base_message_data(
				$line_user_id,
				'video',
				$video_content,
				$api_used,
				$line_message_id,
				$quote_token,
				$quoted_message_id,
				$sender_name,
				$source_type,
				$group_id
			);

			$message_data['raw_payload'] = wp_json_encode(
				array(
					'api_used'        => $api_used,
					'sent_by_admin'   => true,
					'admin_user_id'   => get_current_user_id(),
					'video_url'       => $video_url,
					'video_name'      => $video_name,
					'line_message_id' => $line_message_id,
				)
			);

			$format_array = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			);

			$this->insert_message_record( $table_name, $message_data, $format_array );
		} catch ( Exception $e ) {
			$this->logError( '儲存影片訊息時發生錯誤: ' . $e->getMessage() );
		}
	}

	/**
	 * 儲存貼圖訊息到資料庫
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $package_id 貼圖包 ID.
	 * @param string      $sticker_id 貼圖 ID.
	 * @param string      $api_used 使用的 API.
	 * @param string|null $line_message_id LINE message ID.
	 * @param string|null $quote_token 引用訊息的 quote token.
	 * @return void
	 */
	public function saveStickerMessage( $line_user_id, $package_id, $sticker_id, $api_used, $line_message_id = null, $quote_token = null, $source_type = '', $group_id = '' ) {
		try {
			$current_month = date( 'Y_m' );
			$table_name    = $this->prepare_message_table( $current_month );
			$sender_name   = $this->get_sender_info();

			$sticker_content = wp_json_encode(
				array(
					'packageId' => $package_id,
					'stickerId' => $sticker_id,
				)
			);

			$message_data = $this->prepare_base_message_data(
				$line_user_id,
				'sticker',
				$sticker_content,
				$api_used,
				$line_message_id,
				$quote_token,
				null,
				$sender_name,
				$source_type,
				$group_id
			);

			$message_data['raw_payload'] = wp_json_encode(
				array(
					'api_used'        => $api_used,
					'sent_by_admin'   => true,
					'admin_user_id'   => get_current_user_id(),
					'package_id'      => $package_id,
					'sticker_id'      => $sticker_id,
					'line_message_id' => $line_message_id,
					'quote_token'     => $quote_token,
				)
			);

			$format_array = array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			);

			$this->insert_message_record( $table_name, $message_data, $format_array );
		} catch ( Exception $e ) {
			$this->logError( '儲存貼圖訊息時發生錯誤: ' . $e->getMessage() );
		}
	}

	/**
	 * 準備訊息資料表，檢查是否存在並建立
	 *
	 * @param string $current_month 當前月份.
	 * @return string 資料表名稱.
	 */
	private function prepare_message_table( $current_month ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'otz_messages_' . $current_month;

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			$this->create_monthly_message_table( $current_month );
		}

		return $table_name;
	}

	/**
	 * 取得發送者資訊
	 *
	 * @return string 發送者名稱.
	 */
	private function get_sender_info() {
		$current_user = wp_get_current_user();
		return $current_user->display_name ?: 'OrderChatz Bot';
	}

	/**
	 * 準備基本訊息資料
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $message_type 訊息類型.
	 * @param string      $message_content 訊息內容.
	 * @param string      $api_used 使用的 API.
	 * @param string|null $line_message_id LINE 訊息 ID.
	 * @param string|null $quote_token 引用 token.
	 * @param string|null $quoted_message_id 被引用訊息 ID.
	 * @param string      $sender_name 發送者名稱.
	 * @return array 基本訊息資料.
	 */
	private function prepare_base_message_data( $line_user_id, $message_type, $message_content, $api_used, $line_message_id, $quote_token, $quoted_message_id, $sender_name, $source_type = '', $group_id = '' ) {
		return array(
			'event_id'          => 'manual_' . $message_type . '_' . uniqid(),
			'line_user_id'      => $line_user_id,
			'source_type'       => ! empty( $source_type ) ? $source_type : 'user',
			'sender_type'       => 'ACCOUNT',
			'sender_name'       => $sender_name,
			'group_id'          => ! empty( $group_id ) ? $group_id : null,
			'sent_date'         => current_time( 'Y-m-d' ),
			'sent_time'         => current_time( 'H:i:s' ),
			'message_type'      => $message_type,
			'message_content'   => $message_content,
			'reply_token'       => 'NULL',
			'quoted_message_id' => ! empty( $quoted_message_id ) ? $quoted_message_id : null,
			'quote_token'       => $quote_token,
			'line_message_id'   => $line_message_id,
			'created_by'        => get_current_user_id(),
			'created_at'        => current_time( 'mysql' ),
		);
	}

	/**
	 * 插入訊息記錄到資料庫
	 *
	 * @param string $table_name 資料表名稱.
	 * @param array  $message_data 訊息資料.
	 * @param array  $format_array 格式陣列.
	 * @return void
	 */
	private function insert_message_record( $table_name, $message_data, $format_array ) {
		global $wpdb;

		$wpdb->insert(
			$table_name,
			$message_data,
			$format_array
		);

		if ( $wpdb->last_error ) {
			$this->logError( '儲存訊息失敗: ' . $wpdb->last_error );
		}
	}

	/**
	 * 建立月份訊息資料表
	 *
	 * @param string $month_suffix 月份後綴.
	 * @return void
	 */
	private function create_monthly_message_table( $month_suffix ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'otz_messages_' . $month_suffix;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id varchar(100) NOT NULL,
            line_user_id varchar(100) NOT NULL,
            source_type enum('USER','GROUP','ROOM') NOT NULL,
            sender_type enum('USER','ACCOUNT') NOT NULL,
            sender_name varchar(255) DEFAULT 'NULL',
            group_id varchar(64) DEFAULT 'NULL',
            sent_date date NOT NULL,
            sent_time time NOT NULL,
            message_type varchar(50) NOT NULL DEFAULT 'text',
            message_content longtext DEFAULT 'NULL',
            reply_token varchar(255) DEFAULT 'NULL',
            quote_token varchar(255) NULL COMMENT 'LINE quote token for replying to this message',
            quoted_message_id varchar(255) NULL COMMENT 'ID of the message being replied to',
            line_message_id varchar(255) NULL COMMENT 'LINE message ID for correlation with replies',
            raw_payload longtext DEFAULT 'NULL',
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id),
            KEY idx_event_id (event_id),
            KEY idx_user_date_time (line_user_id, sent_date, sent_time),
            KEY idx_source_type (source_type),
            KEY idx_group_id (group_id),
            KEY idx_sent_date (sent_date),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
