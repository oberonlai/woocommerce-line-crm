<?php
/**
 * LINE API 服務
 *
 * 處理所有 LINE API 通訊相關的業務邏輯
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

class LineApiService {

	/**
	 * 使用 reply API 發送訊息
	 *
	 * @param string      $reply_token Reply token.
	 * @param string      $message 訊息內容.
	 * @param string|null $quote_token 引用訊息的 quote token.
	 * @param array|null  $quick_reply Quick reply 選項陣列.
	 * @return array 回應結果.
	 */
	public function sendReplyMessage( $reply_token, $message, $quote_token = null, $quick_reply = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/reply';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$message_data = array(
			'type' => 'text',
			'text' => $message,
		);

		// 如果有 quote token，加入到訊息中.
		if ( ! empty( $quote_token ) ) {
			$message_data['quoteToken'] = $quote_token;
		}

		// 如果有 quick reply，加入到訊息中.
		if ( ! empty( $quick_reply ) && is_array( $quick_reply ) ) {
			$quick_reply_items = $this->build_quick_reply_items( $quick_reply );
			if ( ! empty( $quick_reply_items ) ) {
				$message_data['quickReply'] = array(
					'items' => $quick_reply_items,
				);
			}
		}

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => array( $message_data ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 push API 發送訊息
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $message 訊息內容.
	 * @param string|null $quote_token 引用訊息的 quote token.
	 * @param array|null  $quick_reply Quick reply 選項陣列.
	 * @param string|null $source_type 來源類型 ('user', 'group', 'room').
	 * @param string|null $group_id 群組 ID（當 source_type 為 'group' 時使用）.
	 * @return array 回應結果.
	 */
	public function sendPushMessage( $line_user_id, $message, $quote_token = null, $quick_reply = null, $source_type = null, $group_id = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/push';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$message_data = array(
			'type' => 'text',
			'text' => $message,
		);

		// 如果有 quote token，加入到訊息中.
		if ( ! empty( $quote_token ) ) {
			$message_data['quoteToken'] = $quote_token;
		}

		// 如果有 quick reply，加入到訊息中.
		if ( ! empty( $quick_reply ) && is_array( $quick_reply ) ) {
			$quick_reply_items = $this->build_quick_reply_items( $quick_reply );
			if ( ! empty( $quick_reply_items ) ) {
				$message_data['quickReply'] = array(
					'items' => $quick_reply_items,
				);
			}
		}

		// 根據 source_type 決定發送目標.
		$to = $line_user_id;
		if ( $source_type === 'group' && ! empty( $group_id ) ) {
			$to = $group_id;
		}

		$data = array(
			'to'       => $to,
			'messages' => array( $message_data ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 reply API 發送圖片訊息
	 *
	 * @param string $reply_token Reply token.
	 * @param array  $image_message 圖片訊息資料.
	 * @return array 回應結果.
	 */
	public function sendReplyImageMessage( $reply_token, $image_message ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/reply';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => array( $image_message ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 push API 發送圖片訊息
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param array       $image_message 圖片訊息資料.
	 * @param string|null $source_type 來源類型 ('user', 'group', 'room').
	 * @param string|null $group_id 群組 ID（當 source_type 為 'group' 時使用）.
	 * @return array 回應結果.
	 */
	public function sendPushImageMessage( $line_user_id, $image_message, $source_type = null, $group_id = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/push';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		// 根據 source_type 決定發送目標.
		$to = $line_user_id;
		if ( $source_type === 'group' && ! empty( $group_id ) ) {
			$to = $group_id;
		}

		$data = array(
			'to'       => $to,
			'messages' => array( $image_message ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 reply API 發送文件訊息
	 *
	 * @param string $reply_token Reply token.
	 * @param array  $file_message 文件訊息資料.
	 * @return array 回應結果.
	 */
	public function sendReplyFileMessage( $reply_token, $file_message ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/reply';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => array( $file_message ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 push API 發送文件訊息
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param array       $file_message 文件訊息資料.
	 * @param string|null $source_type 來源類型 ('user', 'group', 'room').
	 * @param string|null $group_id 群組 ID（當 source_type 為 'group' 時使用）.
	 * @return array 回應結果.
	 */
	public function sendPushFileMessage( $line_user_id, $file_message, $source_type = null, $group_id = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/push';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		// 根據 source_type 決定發送目標.
		$to = $line_user_id;
		if ( $source_type === 'group' && ! empty( $group_id ) ) {
			$to = $group_id;
		}

		$data = array(
			'to'       => $to,
			'messages' => array( $file_message ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 reply API 發送影片訊息
	 *
	 * @param string $reply_token Reply token.
	 * @param array  $video_message 影片訊息資料.
	 * @return array 回應結果.
	 */
	public function sendReplyVideoMessage( $reply_token, $video_message ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/reply';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => array( $video_message ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 push API 發送影片訊息
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param array       $video_message 影片訊息資料.
	 * @param string|null $source_type 來源類型 ('user', 'group', 'room').
	 * @param string|null $group_id 群組 ID（當 source_type 為 'group' 時使用）.
	 * @return array 回應結果.
	 */
	public function sendPushVideoMessage( $line_user_id, $video_message, $source_type = null, $group_id = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/push';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		// 根據 source_type 決定發送目標.
		$to = $line_user_id;
		if ( $source_type === 'group' && ! empty( $group_id ) ) {
			$to = $group_id;
		}

		$data = array(
			'to'       => $to,
			'messages' => array( $video_message ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 reply API 發送貼圖訊息
	 *
	 * @param string      $reply_token Reply token.
	 * @param string      $package_id 貼圖包 ID.
	 * @param string      $sticker_id 貼圖 ID.
	 * @param string|null $quote_token 引用訊息的 quote token.
	 * @return array 回應結果.
	 */
	public function sendReplyStickerMessage( $reply_token, $package_id, $sticker_id, $quote_token = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/reply';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$message_data = array(
			'type'      => 'sticker',
			'packageId' => $package_id,
			'stickerId' => $sticker_id,
		);

		// 如果有 quote token，加入到訊息中.
		if ( ! empty( $quote_token ) ) {
			$message_data['quoteToken'] = $quote_token;
		}

		$data = array(
			'replyToken' => $reply_token,
			'messages'   => array( $message_data ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 使用 push API 發送貼圖訊息
	 *
	 * @param string      $line_user_id LINE 使用者 ID.
	 * @param string      $package_id 貼圖包 ID.
	 * @param string      $sticker_id 貼圖 ID.
	 * @param string|null $quote_token 引用訊息的 quote token.
	 * @param string|null $source_type 來源類型 ('user', 'group', 'room').
	 * @param string|null $group_id 群組 ID（當 source_type 為 'group' 時使用）.
	 * @return array 回應結果.
	 */
	public function sendPushStickerMessage( $line_user_id, $package_id, $sticker_id, $quote_token = null, $source_type = null, $group_id = null ) {
		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定，請到 Webhook 設定頁面配置',
			);
		}

		$url     = 'https://api.line.me/v2/bot/message/push';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$message_data = array(
			'type'      => 'sticker',
			'packageId' => $package_id,
			'stickerId' => $sticker_id,
		);

		// 如果有 quote token，加入到訊息中.
		if ( ! empty( $quote_token ) ) {
			$message_data['quoteToken'] = $quote_token;
		}

		// 根據 source_type 決定發送目標.
		$to = $line_user_id;
		if ( $source_type === 'group' && ! empty( $group_id ) ) {
			$to = $group_id;
		}

		$data = array(
			'to'       => $to,
			'messages' => array( $message_data ),
		);

		return $this->sendLineApiRequest( $url, $headers, $data );
	}

	/**
	 * 發送 LINE API 請求
	 *
	 * @param string $url API URL.
	 * @param array  $headers 請求標頭.
	 * @param array  $data 請求資料.
	 * @return array 回應結果.
	 */
	private function sendLineApiRequest( $url, $headers, $data ) {
		$args = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
			'timeout' => 30,
			'method'  => 'POST',
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code === 200 ) {
			$response_data = json_decode( $response_body, true );
			$message_data  = $this->extractMessageData( $response_data );

			return array(
				'success'         => true,
				'response'        => $response_data,
				'line_message_id' => $message_data['line_message_id'],
				'quote_token'     => $message_data['quote_token'],
			);
		} else {
			$error_data    = json_decode( $response_body, true );
			$error_message = $error_data['message'] ?? "HTTP Error: $response_code";

			if ( strpos( $error_message, 'Invalid reply token' ) !== false ) {
				return array(
					'success' => false,
					'error'   => 'Reply token 已過期或無效',
				);
			}

			return array(
				'success' => false,
				'error'   => $error_message,
			);
		}
	}

	/**
	 * 從 LINE API 回應中擷取訊息資料 (ID 和 Quote Token)
	 *
	 * @param array $response_data LINE API 回應資料.
	 * @return array 包含 line_message_id 和 quote_token 的陣列，或空陣列.
	 */
	private function extractMessageData( $response_data ) {
		$result = array(
			'line_message_id' => null,
			'quote_token'     => null,
		);

		// 根據 LINE Messaging API 文件，成功發送訊息後會回傳 sentMessages 陣列
		if ( isset( $response_data['sentMessages'] ) && is_array( $response_data['sentMessages'] ) && ! empty( $response_data['sentMessages'] ) ) {
			$first_message = $response_data['sentMessages'][0];

			if ( isset( $first_message['id'] ) ) {
				$result['line_message_id'] = sanitize_text_field( $first_message['id'] );
			}

			if ( isset( $first_message['quoteToken'] ) ) {
				$result['quote_token'] = sanitize_text_field( $first_message['quoteToken'] );
			}

			return $result;
		}

		// 檢查是否有其他可能的欄位結構
		if ( isset( $response_data['messageId'] ) ) {
			$result['line_message_id'] = sanitize_text_field( $response_data['messageId'] );
		} elseif ( isset( $response_data['id'] ) ) {
			$result['line_message_id'] = sanitize_text_field( $response_data['id'] );
		}

		// 檢查 quote token 的其他可能位置
		if ( isset( $response_data['quoteToken'] ) ) {
			$result['quote_token'] = sanitize_text_field( $response_data['quoteToken'] );
		}

		return $result;
	}

	/**
	 * 顯示 LINE loading indicator（正在輸入動畫）
	 *
	 * @param string $line_user_id LINE 使用者 ID（chatId）.
	 * @param int    $seconds 顯示秒數（5-60 秒之間）.
	 * @return array 回應結果 ['success' => bool, 'error' => string|null].
	 */
	public function show_loading_indicator( $line_user_id, $seconds = 20 ) {
		// 驗證參數範圍（5-60 秒）.
		$seconds = max( 5, min( 60, (int) $seconds ) );

		$channel_access_token = get_option( 'otz_access_token' );

		if ( empty( $channel_access_token ) ) {
			return array(
				'success' => false,
				'error'   => 'LINE Channel Access Token 未設定',
			);
		}

		$url     = 'https://api.line.me/v2/bot/chat/loading/start';
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $channel_access_token,
		);

		$data = array(
			'chatId'         => $line_user_id,
			'loadingSeconds' => $seconds,
		);

		$args = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
			'timeout' => 10,
			'method'  => 'POST',
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code || 202 === $response_code ) {
			return array( 'success' => true );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$error_data    = json_decode( $response_body, true );
		$error_message = $error_data['message'] ?? "HTTP Error: $response_code";

		return array(
			'success' => false,
			'error'   => $error_message,
		);
	}

	/**
	 * 格式化文件名稱以避免 LINE 自動判定為網頁連結
	 *
	 * @param string $file_name 原始文件名稱.
	 * @return string 格式化後的文件名稱.
	 */
	public function formatFileNameForLine( $file_name ) {
		// 找到最後一個點號的位置.
		$last_dot_pos = strrpos( $file_name, '.' );

		if ( false !== $last_dot_pos ) {
			// 在點號前後加上括弧.
			$name_part      = substr( $file_name, 0, $last_dot_pos );
			$extension_part = substr( $file_name, $last_dot_pos + 1 );
			return $name_part . '(.)' . $extension_part;
		}

		// 如果沒有找到點號，直接返回原文件名.
		return $file_name;
	}

	/**
	 * 建構 LINE Quick Reply Items
	 *
	 * @param array $items Quick reply 項目陣列（字串陣列）.
	 * @return array LINE Quick Reply Items 格式，超過 15 字元的項目會被過濾.
	 */
	private function build_quick_reply_items( $items ) {
		$quick_reply_items = array();
		$max_length        = 15;
		$logger            = wc_get_logger();

		foreach ( $items as $item ) {
			// 過濾空值.
			if ( empty( $item ) || ! is_string( $item ) ) {
				continue;
			}

			// 檢查長度限制.
			$item_length = mb_strlen( $item, 'UTF-8' );
			if ( $item_length > $max_length ) {
				$logger->warning(
					sprintf(
						'Quick reply item "%s" exceeds %d characters (length: %d), skipping.',
						$item,
						$max_length,
						$item_length
					),
					array( 'source' => 'orderchatz-line-api' )
				);
				continue;
			}

			// 建構 LINE Quick Reply Item.
			$quick_reply_items[] = array(
				'type'   => 'action',
				'action' => array(
					'type'  => 'message',
					'label' => $item,
					'text'  => $item,
				),
			);

			// LINE API 限制最多 13 個 quick reply items.
			if ( count( $quick_reply_items ) >= 13 ) {
				$logger->info(
					'Reached maximum of 13 quick reply items, ignoring remaining items.',
					array( 'source' => 'orderchatz-line-api' )
				);
				break;
			}
		}

		return $quick_reply_items;
	}
}
