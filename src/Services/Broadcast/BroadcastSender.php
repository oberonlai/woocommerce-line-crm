<?php
/**
 * OrderChatz 推播發送服務
 *
 * 處理 LINE 推播訊息的發送功能.
 *
 * @package OrderChatz\Services\Broadcast
 * @since 1.0.0
 */

declare(strict_types=1);

namespace OrderChatz\Services\Broadcast;

use Exception;
use OrderChatz\Util\Logger;
use OrderChatz\Database\Broadcast\Log;
use OrderChatz\Services\MessageReplace;

/**
 * 推播發送類別
 */
class BroadcastSender {

	/**
	 * Log 處理器
	 *
	 * @var Log|null
	 */
	private ?Log $log_handler = null;

	/**
	 * 訊息參數替換服務
	 *
	 * @var MessageReplace
	 */
	private MessageReplace $message_replacer;

	/**
	 * 建構函式
	 */
	public function __construct() {
		global $wpdb;
		$this->log_handler      = new Log( $wpdb );
		$this->message_replacer = new MessageReplace();
	}

	/**
	 * 建構 LINE 訊息格式
	 *
	 * @param string $type    訊息類型 (text, image, video, flex).
	 * @param array  $content 訊息內容陣列.
	 * @return array LINE 訊息陣列.
	 * @throws Exception 當訊息類型不支援或內容格式錯誤時.
	 */
	public function build_line_messages( string $type, array $content ): array {
		$messages = array();

		switch ( $type ) {
			case 'text':
				if ( empty( $content['text'] ) ) {
					throw new Exception( __( '文字訊息內容不可為空', 'otz' ) );
				}
				$messages[] = array(
					'type' => 'text',
					'text' => $content['text'],
				);
				break;

			case 'image':
				if ( empty( $content['url'] ) ) {
					throw new Exception( __( '圖片 URL 不可為空', 'otz' ) );
				}
				$encoded_url = $this->encode_url_path( $content['url'] );
				$messages[]  = array(
					'type'               => 'image',
					'originalContentUrl' => $encoded_url,
					'previewImageUrl'    => $encoded_url,
				);
				break;

			case 'video':
				if ( empty( $content['videoUrl'] ) || empty( $content['previewImageUrl'] ) ) {
					throw new Exception( __( '影片 URL 和封面圖 URL 不可為空', 'otz' ) );
				}
				$encoded_video_url   = $this->encode_url_path( $content['videoUrl'] );
				$encoded_preview_url = $this->encode_url_path( $content['previewImageUrl'] );
				$messages[]          = array(
					'type'               => 'video',
					'originalContentUrl' => $encoded_video_url,
					'previewImageUrl'    => $encoded_preview_url,
				);
				break;

			case 'flex':
				$messages[] = array(
					'type'     => 'flex',
					'altText'  => 'This is a Flex Message',
					'contents' => $content,
				);
				break;

			default:
				throw new Exception( sprintf( __( '不支援的訊息類型：%s', 'otz' ), $type ) );
		}

		return $messages;
	}

	/**
	 * 發送廣播訊息給所有好友
	 *
	 * @param array      $messages    LINE 訊息陣列.
	 * @param bool       $silent_push 是否靜音推播.
	 * @param array|null $log_data    Log 資料（可選）.
	 * @return array 發送結果.
	 * @throws Exception 當 Access Token 未設定或請求失敗時.
	 */
	public function send_broadcast_to_all( array $messages, bool $silent_push = false, ?array $log_data = null ): array {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			throw new Exception( __( 'LINE Channel Access Token 未設定', 'otz' ) );
		}

		// 1. 如果提供 log_data，先建立記錄（status = 'pending'）.
		$log_id = null;
		if ( $log_data ) {
			$log_data['status'] = 'pending';
			$log_id             = $this->create_broadcast_log( $log_data );
		}

		$url     = 'https://api.line.me/v2/bot/message/broadcast';
		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => 'Bearer ' . $channel_access_token,
			'X-Line-Retry-Key' => wp_generate_uuid4(),
		);

		$data = array(
			'messages'             => $messages,
			'notificationDisabled' => $silent_push,
		);

		try {
			$result = $this->send_line_api_request( $url, $headers, $data );

			// 2. 發送成功，更新 Log（status = 'success'）.
			if ( $log_id ) {
				$this->update_broadcast_log(
					$log_id,
					array(
						'status'        => 'success',
						'success_count' => $log_data['target_count'] ?? 0,
					)
				);
			}

			// 3. 返回結果包含 log_id.
			$result['log_id'] = $log_id;
			return $result;

		} catch ( Exception $e ) {
			// 4. 發送失敗，更新 Log（status = 'failed'）.
			if ( $log_id ) {
				$this->update_broadcast_log(
					$log_id,
					array(
						'status'        => 'failed',
						'failed_count'  => $log_data['target_count'] ?? 0,
						'error_message' => $e->getMessage(),
					)
				);
			}
			throw $e;
		}
	}

	/**
	 * 發送多播訊息給指定好友
	 *
	 * @param array      $friends     好友列表.
	 * @param array      $messages    LINE 訊息陣列.
	 * @param bool       $silent_push 是否靜音推播.
	 * @param array|null $log_data    Log 資料（可選）.
	 * @return array 發送結果.
	 * @throws Exception 當全部批次都失敗時.
	 */
	public function send_multicast_message( array $friends, array $messages, bool $silent_push = false, ?array $log_data = null ): array {
		if ( empty( $friends ) ) {
			throw new Exception( __( '沒有找到符合條件的好友', 'otz' ) );
		}

		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			throw new Exception( __( 'LINE Channel Access Token 未設定', 'otz' ) );
		}

		// 1. 如果提供 log_data，先建立記錄（status = 'pending'）.
		$log_id = null;
		if ( $log_data ) {
			$log_data['status']       = 'pending';
			$log_data['target_count'] = count( $friends );
			$log_id                   = $this->create_broadcast_log( $log_data );
		}

		// 檢查訊息是否包含需要替換的參數.
		$has_parameters = $this->messages_have_parameters( $messages );

		// 2. 如果訊息包含參數，需要個人化發送.
		if ( $has_parameters ) {
			return $this->send_personalized_messages( $friends, $messages, $silent_push, $log_id );
		}

		// 3. 如果沒有參數，使用原本的 multicast 方式群發.
		$url     = 'https://api.line.me/v2/bot/message/multicast';
		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => 'Bearer ' . $channel_access_token,
			'X-Line-Retry-Key' => wp_generate_uuid4(),
		);

		// LINE API 一次最多支援 500 個收件人.
		$chunks        = array_chunk( $friends, 500 );
		$total_sent    = 0;
		$total_failed  = 0;
		$failed_chunks = array();

		foreach ( $chunks as $index => $chunk ) {
			$to_users = array_column( $chunk, 'line_user_id' );

			// 1. 移除 null, false, empty string
			$to_users = array_filter(
				$to_users,
				function( $user_id ) {
					return ! empty( $user_id );
				}
			);

			// 2. 驗證格式並過濾
			$to_users = array_filter(
				$to_users,
				function( $user_id ) {
					return is_string( $user_id )
					   && strlen( $user_id ) === 33
					   && preg_match( '/^U[0-9a-fA-F]{32}$/', $user_id );
				}
			);

			// 3. 重新索引(移除空隙)
			$to_users = array_values( $to_users );

			$data = array(
				'to'                   => $to_users,
				'messages'             => $messages,
				'notificationDisabled' => $silent_push,
			);

			try {
				$result = $this->send_line_api_request( $url, $headers, $data );
				if ( $result['success'] ) {
					$total_sent += count( $to_users );
				}
			} catch ( Exception $e ) {
				$total_failed   += count( $to_users );
				$failed_chunks[] = array(
					'chunk_index' => $index,
					'user_count'  => count( $to_users ),
					'error'       => $e->getMessage(),
				);
				// 記錄批次失敗但繼續處理其他批次.
				Logger::warning(
					sprintf( '推播批次 %d 發送失敗', $index ),
					array(
						'user_count' => count( $to_users ),
						'error'      => $e->getMessage(),
					),
					'otz'
				);
			}
		}

		// 4. 判斷最終狀態.
		if ( $total_sent === 0 && $total_failed > 0 ) {
			$final_status = 'failed';
		} elseif ( $total_failed > 0 ) {
			$final_status = 'partial';
		} else {
			$final_status = 'success';
		}

		// 5. 更新 Log.
		if ( $log_id ) {
			$this->update_broadcast_log(
				$log_id,
				array(
					'status'        => $final_status,
					'success_count' => $total_sent,
					'failed_count'  => $total_failed,
					'error_message' => ! empty( $failed_chunks ) ? wp_json_encode( $failed_chunks ) : null,
				)
			);
		}

		// 6. 如果全部失敗則拋出例外.
		if ( $total_sent === 0 && $total_failed > 0 ) {
			throw new Exception( __( '所有推播批次都發送失敗', 'otz' ) );
		}

		return array(
			'success'       => $total_failed === 0,
			'sent_count'    => $total_sent,
			'failed_count'  => $total_failed,
			'failed_chunks' => $failed_chunks,
			'log_id'        => $log_id,
		);
	}

	/**
	 * 發送推播訊息給指定用戶（用於測試）
	 *
	 * @param string $user_id  LINE 用戶 ID.
	 * @param array  $messages LINE 訊息陣列.
	 * @return array 發送結果.
	 * @throws Exception 當 Access Token 未設定或請求失敗時.
	 */
	public function send_push_message( string $user_id, array $messages ): array {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			throw new Exception( __( 'LINE Channel Access Token 未設定', 'otz' ) );
		}

		// 替換訊息中的參數.
		$messages = $this->replace_message_parameters( $messages, $user_id );

		$url     = 'https://api.line.me/v2/bot/message/push';
		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => 'Bearer ' . $channel_access_token,
			'X-Line-Retry-Key' => wp_generate_uuid4(),
		);

		$data = array(
			'to'       => $user_id,
			'messages' => $messages,
		);

		return $this->send_line_api_request( $url, $headers, $data );
	}

	/**
	 * 發送 LINE API 請求
	 *
	 * @param string $url     API URL.
	 * @param array  $headers HTTP headers.
	 * @param array  $data    請求資料.
	 * @return array 回應結果.
	 * @throws Exception 當請求失敗時.
	 */
	private function send_line_api_request( string $url, array $headers, array $data ): array {
		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::error(
				'LINE API 請求失敗 (WP_Error): ' . $response->get_error_message(),
				array( 'url' => $url ),
				'otz'
			);
			throw new Exception( $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data    = json_decode( $body, true );
			$error_message = $error_data['message'] ?? 'LINE API 請求失敗';

			// 使用 Logger 記錄詳細錯誤資訊.
			Logger::error(
				'LINE API 請求失敗: ' . $error_message,
				array(
					'url'           => $url,
					'status_code'   => $status_code,
					'response_body' => $body,
				),
				'otz'
			);

			// 拋出例外而非返回 success = true.
			throw new Exception( $error_message );
		}

		return array(
			'success'  => true,
			'response' => json_decode( $body, true ),
		);
	}

	/**
	 * 建立推播執行記錄
	 *
	 * @param array $log_data Log 資料.
	 * @return int|false 成功時返回 Log ID，失敗時返回 false.
	 */
	public function create_broadcast_log( array $log_data ) {
		if ( ! $this->log_handler ) {
			Logger::error( 'Log handler not initialized', array(), 'otz' );
			return false;
		}

		return $this->log_handler->create_log( $log_data );
	}

	/**
	 * 更新推播執行記錄
	 *
	 * @param int   $log_id      Log ID.
	 * @param array $update_data 更新資料.
	 * @return bool 成功時返回 true，失敗時返回 false.
	 */
	public function update_broadcast_log( int $log_id, array $update_data ): bool {
		if ( ! $this->log_handler ) {
			Logger::error( 'Log handler not initialized', array(), 'otz' );
			return false;
		}

		// 使用 wpdb 直接更新（Log 類別沒有 update 方法）.
		global $wpdb;
		$table_name = $wpdb->prefix . 'otz_broadcast_logs';

		// 準備可更新的欄位.
		$allowed_fields = array( 'success_count', 'failed_count', 'status', 'error_message' );
		$sanitized_data = array();
		$format         = array();

		foreach ( $update_data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				$sanitized_data[ $key ] = $value;
				$format[]               = in_array( $key, array( 'success_count', 'failed_count' ), true ) ? '%d' : '%s';
			}
		}

		if ( empty( $sanitized_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			$sanitized_data,
			array( 'id' => $log_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * 驗證 LINE 訊息內容
	 *
	 * @param string $type    訊息類型.
	 * @param array  $content 訊息內容陣列.
	 * @return bool 是否有效.
	 */
	public function validate_message_content( string $type, array $content ): bool {

		switch ( $type ) {
			case 'text':
				return ! empty( $content['text'] ) && strlen( $content['text'] ) <= 5000;

			case 'image':
				return ! empty( $content['url'] ) && wp_http_validate_url( $content['url'] );

			case 'video':
				return ! empty( $content['videoUrl'] )
					&& wp_http_validate_url( $content['videoUrl'] )
					&& ! empty( $content['previewImageUrl'] )
					&& wp_http_validate_url( $content['previewImageUrl'] );

			case 'flex':
				return ! empty( $content );

			default:
				return false;
		}
	}

	/**
	 * 編碼 URL 路徑中的非 ASCII 字元
	 *
	 * LINE API 要求 URL 必須完全符合 RFC 3986 標準，中文等非 ASCII 字元需要編碼.
	 *
	 * @param string $url 原始 URL.
	 * @return string 編碼後的 URL.
	 */
	private function encode_url_path( string $url ): string {
		$parsed = wp_parse_url( $url );

		if ( ! $parsed ) {
			return $url;
		}

		// 編碼路徑部分.
		if ( isset( $parsed['path'] ) ) {
			$path_parts     = explode( '/', $parsed['path'] );
			$encoded_parts  = array_map(
				function ( $part ) {
					// 只編碼未編碼的部分，避免重複編碼.
					return rawurlencode( rawurldecode( $part ) );
				},
				$path_parts
			);
			$parsed['path'] = implode( '/', $encoded_parts );
		}

		// 編碼查詢字串.
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query_params );
			$parsed['query'] = http_build_query( $query_params, '', '&', PHP_QUERY_RFC3986 );
		}

		// 重建 URL.
		$encoded_url = '';
		if ( isset( $parsed['scheme'] ) ) {
			$encoded_url .= $parsed['scheme'] . '://';
		}
		if ( isset( $parsed['user'] ) ) {
			$encoded_url .= $parsed['user'];
			if ( isset( $parsed['pass'] ) ) {
				$encoded_url .= ':' . $parsed['pass'];
			}
			$encoded_url .= '@';
		}
		if ( isset( $parsed['host'] ) ) {
			$encoded_url .= $parsed['host'];
		}
		if ( isset( $parsed['port'] ) ) {
			$encoded_url .= ':' . $parsed['port'];
		}
		if ( isset( $parsed['path'] ) ) {
			$encoded_url .= $parsed['path'];
		}
		if ( isset( $parsed['query'] ) ) {
			$encoded_url .= '?' . $parsed['query'];
		}
		if ( isset( $parsed['fragment'] ) ) {
			$encoded_url .= '#' . $parsed['fragment'];
		}

		return $encoded_url;
	}

	/**
	 * 檢查 LINE Channel Access Token 是否已設定
	 *
	 * @return bool 是否已設定.
	 */
	public function has_valid_access_token(): bool {
		$token = get_option( 'otz_access_token' );
		return ! empty( $token );
	}

	/**
	 * 獲取推播配額資訊（如果 LINE API 提供）
	 *
	 * @return array|null 配額資訊.
	 */
	public function get_broadcast_quota(): ?array {
		if ( ! $this->has_valid_access_token() ) {
			return null;
		}

		$channel_access_token = get_option( 'otz_access_token' );
		$url                  = 'https://api.line.me/v2/bot/message/quota';

		$headers = array(
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$response = wp_remote_get( $url, array( 'headers' => $headers ) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * 批量發送多個訊息類型
	 *
	 * @param array $friends         好友列表.
	 * @param array $message_configs 多個訊息配置.
	 * @param bool  $silent_push     是否靜音推播.
	 * @return array 發送結果.
	 * @throws Exception 當訊息內容無效或發送失敗時.
	 */
	public function send_bulk_messages( array $friends, array $message_configs, bool $silent_push = false ): array {
		$all_messages = array();

		foreach ( $message_configs as $config ) {
			$messages     = $this->build_line_messages(
				$config['type'],
				$config['content']
			);
			$all_messages = array_merge( $all_messages, $messages );
		}

		if ( empty( $all_messages ) ) {
			throw new Exception( __( '沒有有效的訊息內容', 'otz' ) );
		}

		// LINE API 單次最多支援 5 則訊息.
		if ( count( $all_messages ) > 5 ) {
			$all_messages = array_slice( $all_messages, 0, 5 );
		}

		return $this->send_multicast_message( $friends, $all_messages, $silent_push );
	}

	/**
	 * 檢查訊息陣列是否包含需要替換的參數
	 *
	 * @param array $messages LINE 訊息陣列.
	 * @return bool 是否包含參數.
	 */
	private function messages_have_parameters( array $messages ): bool {
		foreach ( $messages as $message ) {
			// 只檢查文字訊息.
			if ( isset( $message['type'] ) && $message['type'] === 'text' && isset( $message['text'] ) ) {
				if ( strpos( $message['text'], '{{' ) !== false ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * 發送個人化訊息（每個使用者收到的內容不同）
	 *
	 * @param array    $friends     好友列表.
	 * @param array    $messages    LINE 訊息陣列.
	 * @param bool     $silent_push 是否靜音推播.
	 * @param int|null $log_id      Log ID.
	 * @return array 發送結果.
	 */
	private function send_personalized_messages( array $friends, array $messages, bool $silent_push, ?int $log_id ): array {
		$channel_access_token = get_option( 'otz_access_token' );
		$url                  = 'https://api.line.me/v2/bot/message/push';
		$headers              = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$total_sent   = 0;
		$total_failed = 0;
		$failed_users = array();

		foreach ( $friends as $friend ) {
			$line_user_id = $friend['line_user_id'] ?? '';

			if ( empty( $line_user_id ) ) {
				$total_failed++;
				continue;
			}

			// 替換訊息中的參數.
			$personalized_messages = $this->replace_message_parameters( $messages, $line_user_id );

			$data = array(
				'to'       => $line_user_id,
				'messages' => $personalized_messages,
			);

			// 加入 Retry Key.
			$headers['X-Line-Retry-Key'] = wp_generate_uuid4();

			try {
				$result = $this->send_line_api_request( $url, $headers, $data );
				if ( $result['success'] ) {
					$total_sent++;
				}
			} catch ( Exception $e ) {
				$total_failed++;
				$failed_users[] = array(
					'line_user_id' => $line_user_id,
					'error'        => $e->getMessage(),
				);
				// 記錄個別使用者發送失敗.
				Logger::warning(
					sprintf( '個人化訊息發送失敗 (User: %s)', $line_user_id ),
					array(
						'line_user_id' => $line_user_id,
						'error'        => $e->getMessage(),
					),
					'otz'
				);
			}

			// 加入短暫延遲，避免觸發 API 速率限制.
			if ( count( $friends ) > 10 ) {
				usleep( 50000 ); // 50ms.
			}
		}

		// 判斷最終狀態.
		if ( $total_sent === 0 && $total_failed > 0 ) {
			$final_status = 'failed';
		} elseif ( $total_failed > 0 ) {
			$final_status = 'partial';
		} else {
			$final_status = 'success';
		}

		// 更新 Log.
		if ( $log_id ) {
			$this->update_broadcast_log(
				$log_id,
				array(
					'status'        => $final_status,
					'success_count' => $total_sent,
					'failed_count'  => $total_failed,
					'error_message' => ! empty( $failed_users ) ? wp_json_encode( $failed_users ) : null,
				)
			);
		}

		// 如果全部失敗則拋出例外.
		if ( $total_sent === 0 && $total_failed > 0 ) {
			throw new Exception( __( '所有個人化訊息都發送失敗', 'otz' ) );
		}

		return array(
			'success'      => $total_failed === 0,
			'sent_count'   => $total_sent,
			'failed_count' => $total_failed,
			'failed_users' => $failed_users,
			'log_id'       => $log_id,
		);
	}

	/**
	 * 替換訊息陣列中的參數
	 *
	 * @param array  $messages     原始訊息陣列.
	 * @param string $line_user_id LINE 使用者 ID.
	 * @return array 替換後的訊息陣列.
	 */
	private function replace_message_parameters( array $messages, string $line_user_id ): array {
		$replaced_messages = array();

		foreach ( $messages as $message ) {
			$replaced_message = $message;

			// 只處理文字訊息的參數替換.
			if ( isset( $message['type'] ) && $message['type'] === 'text' && isset( $message['text'] ) ) {
				$replaced_message['text'] = $this->message_replacer->replace_message(
					$message['text'],
					$line_user_id
				);
			}

			$replaced_messages[] = $replaced_message;
		}

		return $replaced_messages;
	}
}
