<?php
/**
 * 訊息查詢服務
 *
 * 處理所有訊息查詢相關的業務邏輯，負責訊息格式化和引用訊息處理
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

use Exception;
use OrderChatz\Database\Message\TableMessage;

class MessageQueryService {

	/**
	 * 訊息資料表管理器
	 *
	 * @var TableMessage
	 */
	private $table_message;

	/**
	 * Constructor
	 *
	 * @param TableMessage|null $table_message 訊息資料表管理器
	 */
	public function __construct( ?TableMessage $table_message = null ) {
		global $wpdb;
		$this->table_message = $table_message ?: new TableMessage( $wpdb );
	}

	/**
	 * 查詢訊息資料
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $before_date 查詢日期前限制.
	 * @param int    $limit 查詢數量限制.
	 *
	 * @return array 訊息陣列.
	 */
	public function queryMessages( $line_user_id, $before_date = '', $limit = 10 ) {
		$raw_messages = $this->table_message->query_messages( $line_user_id, $before_date, $limit );

		$messages = array();

		foreach ( $raw_messages as $row ) {
			$messages[] = $this->format_message_row( $row );
		}

		$this->load_quoted_messages_for_batch( $messages );

		return $messages;
	}

	/**
	 * 取得訊息列表中最舊的訊息日期
	 *
	 * @param array $messages 訊息陣列.
	 * @return string 最舊訊息日期.
	 */
	public function getOldestMessageDate( $messages ) {
		if ( empty( $messages ) ) {
			return '';
		}

		$oldest = $messages[0];
		foreach ( $messages as $message ) {
			if ( strtotime( $message['timestamp'] ) < strtotime( $oldest['timestamp'] ) ) {
				$oldest = $message;
			}
		}

		return $oldest['timestamp'];
	}

	/**
	 * 格式化訊息時間
	 *
	 * @param string $datetime 日期時間字串.
	 * @return string 格式化後的時間字串.
	 */
	public function formatMessageTime( $datetime ) {
		$time = strtotime( $datetime );
		$now  = current_time( 'timestamp' );
		$diff = $now - $time;

		if ( $diff < 60 ) {
			return '剛剛';
		} elseif ( $diff < 3600 ) {
			return floor( $diff / 60 ) . ' 分鐘前';
		} elseif ( $diff < 86400 ) {
			return floor( $diff / 3600 ) . ' 小時前';
		} elseif ( $diff < 604800 ) {
			return floor( $diff / 86400 ) . ' 天前';
		} else {
			return date_i18n( 'm/d H:i', $time );
		}
	}

	/**
	 * 取得新訊息 (針對當前聊天的好友)
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $last_message_time 最後一則訊息時間戳記.
	 * @return array 新訊息陣列.
	 */
	public function getNewMessages( $line_user_id, $last_message_time = '' ) {
		$raw_messages = $this->table_message->get_new_messages( $line_user_id, $last_message_time );
		$messages     = array();

		foreach ( $raw_messages as $row ) {
			$messages[] = $this->format_message_row( $row );
		}

		// 載入引用訊息
		$this->load_quoted_messages_for_batch( $messages );

		return $messages;
	}

	/**
	 * 取得最新的 reply token
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $group_id 群組 ID（選填，用於區分個人和群組對話）.
	 * @return string|null Reply token 或 null.
	 */
	public function getLatestReplyToken( $line_user_id, $group_id = '' ) {
		return $this->table_message->get_latest_reply_token( $line_user_id, $group_id );
	}

	/**
	 * 標記 reply token 為已使用
	 *
	 * @param string $reply_token Reply token.
	 * @return void
	 */
	public function markReplyTokenAsUsed( $reply_token ) {
		$this->table_message->mark_reply_token_as_used( $reply_token );
	}

	/**
	 * 根據時間戳記取得前後訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param string $target_timestamp 目標時間戳記 (Y-m-d H:i:s).
	 * @param int    $before_count 之前的訊息數量.
	 * @param int    $after_count 之後的訊息數量.
	 * @return array 包含目標訊息前後的訊息陣列.
	 */
	public function get_messages_around_timestamp( $line_user_id, $target_timestamp, $before_count = 20, $after_count = 20 ) {
		$result = $this->table_message->get_messages_around_timestamp( $line_user_id, $target_timestamp, $before_count, $after_count );

		if ( empty( $result['messages'] ) ) {
			return array();
		}

		// 格式化所有訊息
		$formatted_messages = array();
		foreach ( $result['messages'] as $row ) {
			$formatted_message = $this->format_message_row( $row );

			// 保留目標訊息標記
			if ( isset( $row->is_target ) && $row->is_target ) {
				$formatted_message['is_target'] = true;
			}

			$formatted_messages[] = $formatted_message;
		}

		// 載入引用訊息
		$this->load_quoted_messages_for_batch( $formatted_messages );

		return array(
			'messages'        => $formatted_messages,
			'target_index'    => $result['target_index'],
			'total_count'     => $result['total_count'],
			'has_more_before' => $result['has_more_before'],
			'has_more_after'  => $result['has_more_after'],
		);
	}

	/**
	 * 將資料庫結果轉換為訊息陣列格式
	 *
	 * @param object $row 資料庫查詢結果行
	 * @return array 格式化的訊息陣列
	 */
	private function format_message_row( $row ) {
		$is_outbound = ( $row->sender_type === 'ACCOUNT' ) ||
					   ( $row->sender_type === 'account' ) ||
					   ( $row->sender_type === 'bot' ) ||
					   ( strpos( $row->event_id, 'manual_' ) === 0 );

		return array(
			'id'                   => $row->id,
			'event_id'             => $row->event_id,
			'sender_type'          => $row->sender_type,
			'sender_name'          => $row->sender_name,
			'message_type'         => $row->message_type,
			'content'              => $row->message_content,
			'timestamp'            => $row->created_at,
			'formatted_time'       => $this->formatMessageTime( $row->created_at ),
			'is_outbound'          => $is_outbound,
			'quoted_message_id'    => $row->quoted_message_id ?? null,
			'line_user_id'         => $row->line_user_id,
			'group_id'             => $row->group_id ?? null,
			'line_message_id'      => $row->line_message_id ?? null,
			'quote_token'          => $row->quote_token ?? null,
			'quoted_message'       => null, // 後續填入
			'sender_avatar_url'    => $row->sender_avatar_url ?? null,
			'sender_display_name'  => $row->sender_display_name ?? null,
		);
	}

	/**
	 * 為訊息陣列批次載入引用訊息
	 *
	 * @param array &$messages 訊息陣列（引用傳遞）
	 */
	private function load_quoted_messages_for_batch( &$messages ) {
		foreach ( $messages as &$message ) {
			if ( ! empty( $message['quoted_message_id'] ) ) {
				$message['quoted_message'] = $this->fetch_quoted_message( $message['quoted_message_id'] );
			}
		}
		unset( $message ); // 清除引用
	}

	/**
	 * 根據 line_message_id 查詢被引用的訊息（含快取和類型處理）
	 *
	 * @param string $quoted_message_id 被引用的訊息 ID
	 * @return array|null 被引用的訊息資料或 null
	 */
	private function fetch_quoted_message( $quoted_message_id ) {
		// 使用快取避免重複查詢相同訊息
		static $quoted_cache = array();

		if ( isset( $quoted_cache[ $quoted_message_id ] ) ) {
			return $quoted_cache[ $quoted_message_id ];
		}

		$quoted_msg = $this->table_message->fetch_quoted_message( $quoted_message_id );

		if ( $quoted_msg ) {
			$result = $this->format_message_row( $quoted_msg );

			// 根據訊息類型處理引用顯示內容
			$result['quoted_display'] = $this->prepare_quoted_display_content( $quoted_msg );

			// 快取結果
			$quoted_cache[ $quoted_message_id ] = $result;
			return $result;
		}

		// 找不到引用訊息，快取 null 結果
		$quoted_cache[ $quoted_message_id ] = null;
		return null;
	}

	/**
	 * 準備引用訊息的顯示內容
	 *
	 * @param object $message_row 訊息資料庫行
	 * @return array 引用顯示資料
	 */
	private function prepare_quoted_display_content( $message_row ) {
		$display_data = array(
			'type'        => $message_row->message_type,
			'text'        => '',
			'preview_url' => null,
			'file_name'   => null,
			'icon'        => null,
		);

		switch ( $message_row->message_type ) {
			case 'text':
				// 文字訊息：截斷長文字
				$text                 = $message_row->message_content;
				$display_data['text'] = $this->truncate_quoted_text( $text, 50 );
				$display_data['icon'] = '💬';
				break;

			case 'image':
				// 圖片訊息：提取縮圖 URL
				$display_data = $this->prepare_image_quoted_display( $message_row->message_content );
				break;

			case 'file':
				// 檔案訊息：提取檔名
				$display_data = $this->prepare_file_quoted_display( $message_row->message_content );
				break;

			case 'video':
				// 影片訊息：提取檔名和預覽
				$display_data = $this->prepare_video_quoted_display( $message_row->message_content );
				break;

			case 'sticker':
				// 貼圖訊息
				$display_data['text'] = '貼圖';
				$display_data['icon'] = '😊';
				break;

			default:
				// 其他類型
				$display_data['text'] = '不支援的訊息類型';
				$display_data['icon'] = '❓';
				break;
		}

		return $display_data;
	}

	/**
	 * 處理圖片訊息的引用顯示
	 *
	 * @param string $content 圖片內容 JSON
	 * @return array 引用顯示資料
	 */
	private function prepare_image_quoted_display( $content ) {
		try {
			$image_data = json_decode( $content, true );

			// 提取預覽圖 URL
			$preview_url = '';
			if ( isset( $image_data['contentProvider']['previewImageUrl'] ) ) {
				$preview_url = $image_data['contentProvider']['previewImageUrl'];
			} elseif ( isset( $image_data['previewImageUrl'] ) ) {
				$preview_url = $image_data['previewImageUrl'];
			} elseif ( isset( $image_data['originalContentUrl'] ) ) {
				$preview_url = $image_data['originalContentUrl'];
			}

			return array(
				'type'        => 'image',
				'text'        => '圖片',
				'preview_url' => $preview_url,
				'file_name'   => null,
				'icon'        => '📷',
			);
		} catch ( Exception $e ) {
			return array(
				'type'        => 'image',
				'text'        => '圖片',
				'preview_url' => null,
				'file_name'   => null,
				'icon'        => '📷',
			);
		}
	}

	/**
	 * 處理檔案訊息的引用顯示
	 *
	 * @param string $content 檔案內容 JSON
	 * @return array 引用顯示資料
	 */
	private function prepare_file_quoted_display( $content ) {
		try {
			$file_data = json_decode( $content, true );
			$file_name = $file_data['file_name'] ?? '未知檔案';

			return array(
				'type'        => 'file',
				'text'        => $file_name,
				'preview_url' => null,
				'file_name'   => $file_name,
				'icon'        => '📁',
			);
		} catch ( Exception $e ) {
			return array(
				'type'        => 'file',
				'text'        => '檔案',
				'preview_url' => null,
				'file_name'   => '未知檔案',
				'icon'        => '📁',
			);
		}
	}

	/**
	 * 處理影片訊息的引用顯示
	 *
	 * @param string $content 影片內容 JSON
	 * @return array 引用顯示資料
	 */
	private function prepare_video_quoted_display( $content ) {
		try {
			$video_data = json_decode( $content, true );
			$video_name = $video_data['video_name'] ?? '未知影片';

			return array(
				'type'        => 'video',
				'text'        => $video_name,
				'preview_url' => null,
				'file_name'   => $video_name,
				'icon'        => '🎥',
			);
		} catch ( Exception $e ) {
			return array(
				'type'        => 'video',
				'text'        => '影片',
				'preview_url' => null,
				'file_name'   => '未知影片',
				'icon'        => '🎥',
			);
		}
	}

	/**
	 * 截斷引用文字
	 *
	 * @param string $text 原始文字
	 * @param int    $max_length 最大長度
	 * @return string 截斷後的文字
	 */
	private function truncate_quoted_text( $text, $max_length = 50 ) {
		if ( mb_strlen( $text ) <= $max_length ) {
			return $text;
		}

		return mb_substr( $text, 0, $max_length ) . '...';
	}

	/**
	 * 根據時間戳和方向取得訊息
	 *
	 * @param string $line_user_id LINE 使用者 ID
	 * @param string $reference_timestamp 參考時間戳 (Y-m-d H:i:s)
	 * @param string $direction 查詢方向 ('before' 或 'after')
	 * @param int    $limit 查詢數量限制
	 * @return array 格式化的訊息陣列
	 */
	public function get_messages_by_direction( $line_user_id, $reference_timestamp, $direction = 'before', $limit = 10 ) {
		$raw_messages = $this->table_message->get_messages_by_timestamp_direction(
			$line_user_id,
			$reference_timestamp,
			$direction,
			$limit
		);

		$messages = array();
		foreach ( $raw_messages as $row ) {
			$messages[] = $this->format_message_row( $row );
		}

		// 載入引用訊息
		$this->load_quoted_messages_for_batch( $messages );

		return $messages;
	}
}
