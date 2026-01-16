<?php
/**
 * 重構後的訊息處理 AJAX 處理器
 *
 * 使用服務導向架構重構的訊息處理器
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;
use OrderChatz\Services\UnreadCountService;

class MessageHandlerRefactored extends AbstractAjaxHandler {

	/**
	 * 訊息查詢服務
	 *
	 * @var MessageQueryService
	 */
	private $message_query_service;

	/**
	 * LINE API 服務
	 *
	 * @var LineApiService
	 */
	private $line_api_service;

	/**
	 * 檔案上傳服務
	 *
	 * @var FileUploadService
	 */
	private $file_upload_service;

	/**
	 * 訊息儲存服務
	 *
	 * @var MessageStorageService
	 */
	private $message_storage_service;

	/**
	 * 輪詢服務
	 *
	 * @var PollingService
	 */
	private $polling_service;

	/**
	 * 未讀計數服務
	 *
	 * @var UnreadCountService
	 */
	private $unread_count_service;

	/**
	 * 建構函式
	 */
	public function __construct() {
		$this->message_query_service   = new MessageQueryService();
		$this->line_api_service        = new LineApiService();
		$this->file_upload_service     = new FileUploadService();
		$this->message_storage_service = new MessageStorageService();
		$this->polling_service         = new PollingService( $this->message_query_service );
		$this->unread_count_service    = new UnreadCountService();

		$this->registerAjaxActions();
	}

	/**
	 * 註冊 AJAX 動作
	 *
	 * @return void
	 */
	private function registerAjaxActions() {
		add_action( 'wp_ajax_otz_get_messages', array( $this, 'getMessages' ) );
		add_action( 'wp_ajax_otz_load_more_messages', array( $this, 'getMessages' ) );
		add_action( 'wp_ajax_otz_send_message', array( $this, 'sendMessage' ) );
		add_action( 'wp_ajax_otz_send_image_message', array( $this, 'sendImageMessage' ) );
		add_action( 'wp_ajax_otz_upload_image', array( $this, 'uploadImage' ) );
		add_action( 'wp_ajax_otz_upload_compressed_file', array( $this, 'uploadCompressedFile' ) );
		add_action( 'wp_ajax_otz_upload_video_file', array( $this, 'uploadVideoFile' ) );
		add_action( 'wp_ajax_otz_send_file_message', array( $this, 'sendFileMessage' ) );
		add_action( 'wp_ajax_otz_send_video_message', array( $this, 'sendVideoMessage' ) );
		add_action( 'wp_ajax_otz_send_sticker_message', array( $this, 'sendStickerMessage' ) );
		add_action( 'wp_ajax_otz_mark_messages_read', array( $this, 'markMessagesAsRead' ) );
		add_action( 'wp_ajax_otz_polling_updates', array( $this, 'getPollingUpdates' ) );
		add_action( 'wp_ajax_otz_find_message_by_time', array( $this, 'findMessageByTime' ) );
	}

	/**
	 * 取得訊息列表（初始載入）
	 *
	 * @return void
	 */
	public function getMessages() {
		try {
			$this->verifyNonce();

			$line_user_id = sanitize_text_field( $_POST['line_user_id'] ?? '' );
			$limit        = intval( $_POST['limit'] ?? 10 );
			$before_date  = sanitize_text_field( $_POST['before_date'] ?? '' );

			if ( empty( $line_user_id ) ) {
				throw new Exception( '無效的使用者 ID' );
			}

			$messages = $this->message_query_service->queryMessages( $line_user_id, $before_date, $limit );

			$this->sendSuccess(
				array(
					'messages'         => $messages,
					'has_more'         => count( $messages ) === $limit,
					'oldest_date'      => $this->message_query_service->getOldestMessageDate( $messages ),
					'oldest_timestamp' => ! empty( $messages ) ? end( $messages )['timestamp'] : null,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in getMessages: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 發送文字訊息
	 *
	 * @return void
	 */
	public function sendMessage(): void {
		try {
			$this->verifyNonce();

			$line_user_id = sanitize_text_field( $_REQUEST['line_user_id'] ?? '' );

			$message = wp_unslash( $_REQUEST['message'] ?? '' );

			$quote_token       = sanitize_text_field( $_REQUEST['quote_token'] ?? '' );
			$quoted_message_id = sanitize_text_field( $_REQUEST['quoted_message_id'] ?? '' );
			$source_type       = sanitize_text_field( $_REQUEST['source_type'] ?? '' );
			$group_id          = sanitize_text_field( $_REQUEST['group_id'] ?? '' );

			if ( empty( $line_user_id ) || empty( $message ) ) {
				throw new Exception( '請填寫所有必填欄位' );
			}

			// 取得當前使用者的 display name，並在訊息前面加上發送者名稱。
			$current_user  = wp_get_current_user();
			$sender_name   = $current_user->display_name ?: 'OrderChatz Bot';
			$message_to_line = $sender_name . ': ' . $message;

			$reply_token = $this->message_query_service->getLatestReplyToken( $line_user_id, $group_id );

			if ( ! empty( $reply_token ) ) {
				$reply_result = $this->line_api_service->sendReplyMessage( $reply_token, $message_to_line, $quote_token );
				if ( $reply_result['success'] ) {
					$api_used = 'reply';
					$result   = $reply_result;
					$this->message_query_service->markReplyTokenAsUsed( $reply_token );
				} else {
					$result   = $this->line_api_service->sendPushMessage( $line_user_id, $message_to_line, $quote_token, null, $source_type, $group_id );
					$api_used = 'push';
				}
			} else {
				// 沒有 reply_token，使用 Push API.
				$result   = $this->line_api_service->sendPushMessage( $line_user_id, $message_to_line, $quote_token, null, $source_type, $group_id );
				$api_used = 'push';
			}

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] );
			}

			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveOutboundMessage( $line_user_id, $message, $api_used, $line_message_id, $api_response_quote_token, $quoted_message_id, $source_type, $group_id );

			$this->sendSuccess(
				array(
					'message' => '訊息發送成功',
					'result'  => $result,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in sendMessage: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 上傳圖片檔案
	 *
	 * @return void
	 */
	public function uploadImage() {
		try {
			$this->verifyNonce();

			if ( ! isset( $_FILES['image'] ) || $_FILES['image']['error'] !== UPLOAD_ERR_OK ) {
				throw new Exception( '圖片上傳失敗，請重試' );
			}

			$result = $this->file_upload_service->uploadImage( $_FILES['image'] );
			$this->sendSuccess( $result );

		} catch ( Exception $e ) {
			$this->logError( 'Error in uploadImage: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 發送圖片訊息
	 *
	 * @return void
	 */
	public function sendImageMessage() {
		try {
			$this->verifyNonce();

			$line_user_id      = sanitize_text_field( $_POST['line_user_id'] ?? '' );
			$image_url         = esc_url_raw( $_POST['image_url'] ?? '' );
			$quoted_message_id = sanitize_text_field( $_REQUEST['quoted_message_id'] ?? '' );
			$source_type       = sanitize_text_field( $_REQUEST['source_type'] ?? '' );
			$group_id          = sanitize_text_field( $_REQUEST['group_id'] ?? '' );

			$reply_token = $this->message_query_service->getLatestReplyToken( $line_user_id, $group_id );

			if ( empty( $line_user_id ) || empty( $image_url ) ) {
				throw new Exception( '請填寫所有必填欄位' );
			}

			$image_message = array(
				'type'               => 'image',
				'originalContentUrl' => $image_url,
				'previewImageUrl'    => $image_url,
			);

			if ( ! empty( $reply_token ) ) {
				$reply_result = $this->line_api_service->sendReplyImageMessage( $reply_token, $image_message );
				if ( $reply_result['success'] ) {
					$api_used = 'reply';
					$result   = $reply_result;
					$this->message_query_service->markReplyTokenAsUsed( $reply_token );
				} else {
					// reply 失敗時，fallback 到 push.
					$result   = $this->line_api_service->sendPushImageMessage( $line_user_id, $image_message, $source_type, $group_id );
					$api_used = 'push';
				}
			} else {
				// 沒有 reply_token，直接使用 Push API.
				$result   = $this->line_api_service->sendPushImageMessage( $line_user_id, $image_message, $source_type, $group_id );
				$api_used = 'push';
			}

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] );
			}

			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveImageMessage( $line_user_id, $image_url, $api_used, $line_message_id, $api_response_quote_token, $quoted_message_id, $source_type, $group_id );

			$this->sendSuccess(
				array(
					'message' => '圖片訊息發送成功',
					'result'  => $result,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in sendImageMessage: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 上傳壓縮檔案
	 *
	 * @return void
	 */
	public function uploadCompressedFile() {
		try {
			$this->verifyNonce();

			if ( ! isset( $_FILES['compressed_file'] ) || $_FILES['compressed_file']['error'] !== UPLOAD_ERR_OK ) {
				throw new Exception( '壓縮檔上傳失敗，請重試' );
			}

			$result = $this->file_upload_service->uploadCompressedFile( $_FILES['compressed_file'] );
			$this->sendSuccess( $result );

		} catch ( Exception $e ) {
			$this->logError( 'Error in uploadCompressedFile: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 上傳影片檔案
	 *
	 * @return void
	 */
	public function uploadVideoFile() {
		try {
			$this->verifyNonce();

			if ( ! isset( $_FILES['video_file'] ) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK ) {
				throw new Exception( '影片檔上傳失敗，請重試' );
			}

			$result = $this->file_upload_service->uploadVideoFile( $_FILES['video_file'] );
			$this->sendSuccess( $result );

		} catch ( Exception $e ) {
			$this->logError( 'Error in uploadVideoFile: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 發送文件訊息
	 *
	 * @return void
	 */
	public function sendFileMessage() {
		try {
			$this->verifyNonce();

			$line_user_id      = sanitize_text_field( $_POST['line_user_id'] ?? '' );
			$file_url          = esc_url_raw( $_POST['file_url'] ?? '' );
			$file_name         = sanitize_text_field( $_POST['file_name'] ?? '' );
			$quoted_message_id = sanitize_text_field( $_REQUEST['quoted_message_id'] ?? '' );
			$source_type       = sanitize_text_field( $_REQUEST['source_type'] ?? '' );
			$group_id          = sanitize_text_field( $_REQUEST['group_id'] ?? '' );

			$reply_token = $this->message_query_service->getLatestReplyToken( $line_user_id, $group_id );

			if ( empty( $line_user_id ) || empty( $file_url ) || empty( $file_name ) ) {
				throw new Exception( '請填寫所有必填欄位' );
			}

			$formatted_file_name = $this->line_api_service->formatFileNameForLine( $file_name );
			$file_size           = $this->file_upload_service->getFileSize( $file_url );

			$file_content = sprintf(
				"📁 %s\n📊 檔案大小：%s\n\n🔗 下載連結：%s",
				$formatted_file_name,
				$this->formatFileSize( $file_size ),
				$file_url,
			);

			// 優先使用 Reply API（如果有 reply_token）
			if ( ! empty( $reply_token ) ) {
				$reply_result = $this->line_api_service->sendReplyMessage( $reply_token, $file_content );
				if ( $reply_result['success'] ) {
					$api_used = 'reply';
					$result   = $reply_result;
					$this->message_query_service->markReplyTokenAsUsed( $reply_token );
				} else {
					// Reply API 失敗，改用 Push API 作為備案
					$result   = $this->line_api_service->sendPushMessage( $line_user_id, $file_content, '', null, $source_type, $group_id );
					$api_used = 'push';
				}
			} else {
				// 沒有 reply_token，使用 Push API
				$result   = $this->line_api_service->sendPushMessage( $line_user_id, $file_content, '', null, $source_type, $group_id );
				$api_used = 'push';
			}

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] );
			}

			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveFileMessage( $line_user_id, $file_url, $file_name, $api_used, $line_message_id, $api_response_quote_token, $quoted_message_id, $source_type, $group_id );

			$this->sendSuccess(
				array(
					'message' => '文件訊息發送成功',
					'result'  => $result,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in sendFileMessage: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 發送影片訊息
	 *
	 * @return void
	 */
	public function sendVideoMessage() {
		try {
			$this->verifyNonce();

			$line_user_id = sanitize_text_field( $_POST['line_user_id'] ?? '' );
			$video_url    = esc_url_raw( $_POST['video_url'] ?? '' );
			$video_name   = sanitize_text_field( $_POST['video_name'] ?? '' );
			$source_type  = sanitize_text_field( $_REQUEST['source_type'] ?? '' );
			$group_id     = sanitize_text_field( $_REQUEST['group_id'] ?? '' );

			$reply_token = $this->message_query_service->getLatestReplyToken( $line_user_id, $group_id );

			if ( empty( $line_user_id ) || empty( $video_url ) || empty( $video_name ) ) {
				throw new Exception( '請填寫所有必填欄位' );
			}

			$formatted_video_name = $this->line_api_service->formatFileNameForLine( $video_name );
			$video_size           = $this->file_upload_service->getFileSize( $video_url );
			$preview_image_url    = $this->file_upload_service->generateVideoPreviewUrl( $video_url );

			$video_content = sprintf(
				"🎥 %s\n\n🔗 觀看連結：%s\n\n📊 檔案大小：%s",
				$formatted_video_name,
				$video_url,
				$this->formatFileSize( $video_size )
			);

			// 優先使用 Reply API（如果有 reply_token）
			if ( ! empty( $reply_token ) ) {
				$reply_result = $this->line_api_service->sendReplyMessage( $reply_token, $video_content );
				if ( $reply_result['success'] ) {
					$api_used = 'reply';
					$result   = $reply_result;
					$this->message_query_service->markReplyTokenAsUsed( $reply_token );
				} else {
					// Reply API 失敗，改用 Push API 作為備案
					$result   = $this->line_api_service->sendPushMessage( $line_user_id, $video_content, '', null, $source_type, $group_id );
					$api_used = 'push';
				}
			} else {
				// 沒有 reply_token，使用 Push API
				$result   = $this->line_api_service->sendPushMessage( $line_user_id, $video_content, '', null, $source_type, $group_id );
				$api_used = 'push';
			}

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] );
			}

			$line_message_id          = $result['line_message_id'] ?? null;
			$api_response_quote_token = $result['quote_token'] ?? null;

			$this->message_storage_service->saveVideoMessage( $line_user_id, $video_url, $video_name, $api_used, $line_message_id, $api_response_quote_token, '', $source_type, $group_id );

			$this->sendSuccess(
				array(
					'message' => '影片訊息發送成功',
					'result'  => $result,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in sendVideoMessage: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 發送貼圖訊息
	 *
	 * @return void
	 */
	public function sendStickerMessage() {
		try {
			$this->verifyNonce();

			$line_user_id = sanitize_text_field( $_POST['line_user_id'] ?? '' );
			$package_id   = sanitize_text_field( $_POST['package_id'] ?? '' );
			$sticker_id   = sanitize_text_field( $_POST['sticker_id'] ?? '' );
			$quote_token  = sanitize_text_field( $_POST['quote_token'] ?? '' );
			$source_type  = sanitize_text_field( $_REQUEST['source_type'] ?? '' );
			$group_id     = sanitize_text_field( $_REQUEST['group_id'] ?? '' );

			if ( empty( $line_user_id ) || empty( $package_id ) || empty( $sticker_id ) ) {
				throw new Exception( '請填寫所有必填欄位' );
			}

			$reply_token = $this->message_query_service->getLatestReplyToken( $line_user_id, $group_id );

			// 優先使用 Reply API（如果有 reply_token）
			if ( ! empty( $reply_token ) ) {
				$reply_result = $this->line_api_service->sendReplyStickerMessage( $reply_token, $package_id, $sticker_id, $quote_token );
				if ( $reply_result['success'] ) {
					$api_used = 'reply';
					$result   = $reply_result;
					$this->message_query_service->markReplyTokenAsUsed( $reply_token );
				} else {
					// Reply API 失敗，改用 Push API 作為備案.
					$result   = $this->line_api_service->sendPushStickerMessage( $line_user_id, $package_id, $sticker_id, $quote_token, $source_type, $group_id );
					$api_used = 'push';
				}
			} else {
				// 沒有 reply_token，使用 Push API.
				$result   = $this->line_api_service->sendPushStickerMessage( $line_user_id, $package_id, $sticker_id, $quote_token, $source_type, $group_id );
				$api_used = 'push';
			}

			if ( ! $result['success'] ) {
				throw new Exception( $result['error'] );
			}

			$line_message_id = $result['line_message_id'] ?? null;
			$this->message_storage_service->saveStickerMessage( $line_user_id, $package_id, $sticker_id, $api_used, $line_message_id, $quote_token, $source_type, $group_id );

			$this->sendSuccess(
				array(
					'message' => '貼圖訊息發送成功',
					'result'  => $result,
				)
			);

		} catch ( Exception $e ) {
			$this->logError( 'Error in sendStickerMessage: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 標記訊息為已讀
	 *
	 * @return void
	 */
	public function markMessagesAsRead() {
		try {
			$this->verifyNonce();

			$line_user_id = sanitize_text_field( $_POST['line_user_id'] ?? '' );

			if ( empty( $line_user_id ) ) {
				throw new Exception( '無效的使用者 ID' );
			}

			global $wpdb;

			$current_time = current_time( 'mysql' );

			$wpdb->update(
				$wpdb->prefix . 'otz_users',
				array( 'read_time' => $current_time ),
				array( 'line_user_id' => $line_user_id ),
				array( '%s' ),
				array( '%s' )
			);

			if ( $wpdb->last_error ) {
				throw new Exception( '更新已讀狀態失敗: ' . $wpdb->last_error );
			}

			$this->sendSuccess( array( 'message' => '已標記為已讀' ) );

		} catch ( Exception $e ) {
			$this->logError( 'Error in markMessagesAsRead: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得輪詢更新
	 *
	 * @return void
	 */
	public function getPollingUpdates() {
		try {
			$this->verifyNonce();

			$last_friend_id         = intval( $_POST['last_friend_id'] ?? 0 );
			$last_friend_updated_at = sanitize_text_field( $_POST['last_friend_updated_at'] ?? '' );
			$current_line_user_id   = sanitize_text_field( $_POST['current_friend_id'] ?? '' );
			$last_message_time      = sanitize_text_field( $_POST['last_message_time'] ?? '' );

			$new_friends    = $this->polling_service->getNewFriends( $last_friend_id );
			$friend_updates = $this->polling_service->getFriendUpdates( $last_friend_updated_at );
			$new_messages   = array();

			// 處理訊息更新
			if ( ! empty( $current_line_user_id ) ) {
				$new_messages = $this->message_query_service->getNewMessages( $current_line_user_id, $last_message_time );
			}

			// 計算新的游標值
			$new_last_friend_id         = $this->polling_service->getMaxFriendId( $new_friends, $last_friend_id );
			$new_last_friend_updated_at = $this->polling_service->getMaxFriendUpdatedAt( $friend_updates, $last_friend_updated_at );
			$new_last_message_time      = $this->getMaxMessageTime( $new_messages, $last_message_time );

			$has_updates = $this->polling_service->hasAnyUpdates(
				array(
					'new_friends'    => $new_friends,
					'friend_updates' => $friend_updates,
					'new_messages'   => $new_messages,
				)
			);

			// 回應結構
			$updates = array(
				'new_friends'    => $new_friends,
				'friend_updates' => $friend_updates,
				'new_messages'   => $new_messages,
			);

			$new_cursors = array(
				'last_friend_id'         => $new_last_friend_id,
				'last_friend_updated_at' => $new_last_friend_updated_at,
				'last_message_time'      => $new_last_message_time,
			);

			$response = array(
				'updates'     => $updates,
				'cursors'     => $new_cursors,
				'has_updates' => $has_updates,
			);

			$this->sendSuccess( $response );

		} catch ( Exception $e ) {
			$this->logError( 'Error in getPollingUpdates: ' . $e->getMessage() );
			$this->sendError( $e->getMessage() );
		}
	}

	/**
	 * 取得訊息陣列中最新的時間戳記
	 *
	 * @param array  $messages 訊息陣列.
	 * @param string $fallback_time 預設時間戳記.
	 * @return string 最新的時間戳記.
	 */
	private function getMaxMessageTime( $messages, $fallback_time = '' ) {
		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return $fallback_time;
		}

		$max_time = $fallback_time;

		foreach ( $messages as $message ) {
			if ( isset( $message['timestamp'] ) ) {
				$message_time = $message['timestamp'];
			} elseif ( isset( $message->timestamp ) ) {
				$message_time = $message->timestamp;
			} else {
				continue;
			}

			if ( empty( $max_time ) || strtotime( $message_time ) > strtotime( $max_time ) ) {
				$max_time = $message_time;
			}
		}

		return $max_time;
	}

	/**
	 * 格式化檔案大小
	 *
	 * @param int $size 檔案大小（位元組）.
	 * @return string 格式化的檔案大小.
	 */
	private function formatFileSize( $size ) {
		if ( $size >= 1024 * 1024 ) {
			return round( $size / 1024 / 1024, 2 ) . ' MB';
		} elseif ( $size >= 1024 ) {
			return round( $size / 1024, 2 ) . ' KB';
		} else {
			return $size . ' bytes';
		}
	}

	/**
	 * 根據時間戳記查找訊息
	 */
	public function findMessageByTime() {
		// 驗證 nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'otz_nonce' ) ) {
			wp_send_json_error( array( 'message' => '安全驗證失敗' ) );
		}

		// 取得參數
		$line_user_id     = sanitize_text_field( $_POST['line_user_id'] ?? '' );
		$target_timestamp = sanitize_text_field( $_POST['target_timestamp'] ?? '' );
		$before_count     = intval( $_POST['before_count'] ?? 15 );
		$after_count      = intval( $_POST['after_count'] ?? 15 );

		// 驗證必要參數
		if ( empty( $line_user_id ) || empty( $target_timestamp ) ) {
			wp_send_json_error( array( 'message' => '缺少必要參數' ) );
		}

		try {
			// 使用訊息查詢服務取得前後訊息
			$result = $this->message_query_service->get_messages_around_timestamp( $line_user_id, $target_timestamp, $before_count, $after_count );

			if ( empty( $result ) ) {
				wp_send_json_error( array( 'message' => '找不到相關訊息' ) );
			}

			wp_send_json_success( $result );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => '查詢時發生錯誤' ) );
		}
	}
}
