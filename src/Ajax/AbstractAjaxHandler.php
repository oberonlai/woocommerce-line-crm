<?php
/**
 * AJAX 處理器基礎類別
 *
 * 提供共用的 AJAX 處理邏輯和安全驗證
 *
 * @package OrderChatz\Ajax
 * @since 1.0.0
 */

namespace OrderChatz\Ajax;

use Exception;

abstract class AbstractAjaxHandler {

	/**
	 * 格式化訊息時間
	 */
	protected function formatMessageTime( $datetime ) {
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
	 * 取得預設頭像
	 */
	protected function getDefaultAvatar() {
		return get_avatar_url(
			'',
			array(
				'size'    => 40,
				'default' => 'mystery',
			)
		);
	}

	/**
	 * 格式化好友列表中的最後訊息
	 */
	protected function formatLastMessage( $content, $message_type ) {
		if ( empty( $content ) ) {
			return '';
		}

		switch ( $message_type ) {
			case 'image':
				return '📷 圖片';

			case 'sticker':
				return '😊 貼圖';

			case 'video':
				return '🎬 影片';

			case 'audio':
				return '🎵 語音';

			case 'location':
				return '📍 位置';

			case 'file':
				return '📎 檔案';

			case 'text':
			default:
				// 特別處理包含 LINE emoji 的內容
				if ( strpos( $content, '<img' ) !== false && strpos( $content, 'line-emoji' ) !== false ) {
					// 檢查是否只包含表情符號（沒有文字）
					$text_only = strip_tags( $content );
					if ( empty( trim( $text_only ) ) ) {
						// 純表情符號訊息
						return '😊 表情符號';
					} else {
						// 混合內容：保留文字部分，但將表情符號替換為 [emoji]
						$content = preg_replace( '/<img[^>]*class="line-emoji"[^>]*>/i', '[表情符號]', $content );
						$content = strip_tags( $content );
					}
				} else {
					$content = strip_tags( $content );
				}

				if ( mb_strlen( $content ) > 30 ) {
					$content = mb_substr( $content, 0, 30 ) . '...';
				}
				return $content ?: '😊 表情符號'; // 防止空內容
		}
	}

	/**
	 * 安全驗證
	 */
	protected function verifyNonce( $action = 'orderchatz_chat_action' ) {
		check_ajax_referer( $action, 'nonce' );
	}

	/**
	 * 返回成功響應
	 */
	protected function sendSuccess( $data = array() ) {
		wp_send_json_success( $data );
	}

	/**
	 * 返回錯誤響應
	 */
	protected function sendError( $message, $data = array() ) {
		wp_send_json_error( array_merge( array( 'message' => $message ), $data ) );
	}

	/**
	 * 記錄錯誤日誌
	 */
	protected function logError( $message, $context = array() ) {
		wc_get_logger()->error( $message, array_merge( array( 'source' => 'otz' ), $context ) );
	}

	/**
	 * 取得 POST 參數
	 */
	protected function getPostParam( $key, $default = null ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}
}
