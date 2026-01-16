<?php
/**
 * 訊息搜尋 AJAX 處理器
 *
 * 處理 quoted-message 點擊跳轉和時間戳基礎的訊息載入功能
 *
 * @package OrderChatz\Ajax\Message
 * @since 1.0.0
 */

namespace OrderChatz\Ajax\Message;

use Exception;
use OrderChatz\Ajax\AbstractAjaxHandler;

class MessageSearch extends AbstractAjaxHandler {

	/**
	 * 訊息查詢服務
	 *
	 * @var MessageQueryService
	 */
	private $message_query_service;

	/**
	 * 建構函式
	 */
	public function __construct() {
		$this->message_query_service = new MessageQueryService();
		$this->register_ajax_actions();
	}

	/**
	 * 註冊 AJAX 動作
	 *
	 * @return void
	 */
	private function register_ajax_actions() {
		add_action( 'wp_ajax_otz_jump_to_quoted_message', array( $this, 'jump_to_quoted_message' ) );
		add_action( 'wp_ajax_otz_load_messages_from_timestamp', array( $this, 'load_messages_from_timestamp' ) );
	}

	/**
	 * 跳轉到引用訊息
	 *
	 * @return void
	 */
	public function jump_to_quoted_message(): void {
		$nonce = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'orderchatz_chat_action' ) ) {
			wp_send_json_error( array( 'message' => '安全驗證失敗' ) );
		}

		$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

		$target_timestamp = ( isset( $_POST['target_timestamp'] ) ) ? sanitize_text_field( wp_unslash( $_POST['target_timestamp'] ) ) : '';

		$context_size = ( isset( $_POST['context_size'] ) ) ? intval( wp_unslash( $_POST['context_size'] ) ) : 5;

		if ( empty( $line_user_id ) || empty( $target_timestamp ) ) {
			wp_send_json_error( array( 'message' => '缺少必要參數' ) );
		}

		if ( ! $this->validate_jump_to_quoted_params(
			array(
				'line_user_id'     => $line_user_id,
				'target_timestamp' => $target_timestamp,
				'context_size'     => $context_size,
			)
		) ) {
			wp_send_json_error( array( 'message' => '參數格式錯誤' ) );
		}

		try {
			$result = $this->message_query_service->get_messages_around_timestamp(
				$line_user_id,
				$target_timestamp,
				$context_size,
				$context_size
			);

			if ( empty( $result ) ) {
				wp_send_json_error( array( 'message' => '找不到相關訊息' ) );
			}

			$response = $this->format_jump_to_quoted_response( $result, $target_timestamp );
			wp_send_json_success( $response );

		} catch ( Exception $e ) {
			$this->logError( 'Error in jump_to_quoted_message: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => '查詢時發生錯誤' ) );
		}
	}

	/**
	 * 根據時間戳載入訊息
	 *
	 * @return void
	 */
	public function load_messages_from_timestamp(): void {

		$nonce = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'orderchatz_chat_action' ) ) {
			wp_send_json_error( array( 'message' => '安全驗證失敗' ) );
		}

		$line_user_id = ( isset( $_POST['line_user_id'] ) ) ? sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ) : '';

		$reference_timestamp = ( isset( $_POST['reference_timestamp'] ) ) ? sanitize_text_field( wp_unslash( $_POST['reference_timestamp'] ) ) : '';

		$direction = ( isset( $_POST['direction'] ) ) ? sanitize_text_field( wp_unslash( $_POST['direction'] ) ) : 'before';

		$limit = ( isset( $_POST['limit'] ) ) ? intval( wp_unslash( $_POST['limit'] ) ) : 10;

		if ( empty( $line_user_id ) || empty( $reference_timestamp ) ) {
			wp_send_json_error( array( 'message' => '缺少必要參數' ) );
		}

		if ( ! $this->validate_load_messages_params(
			array(
				'line_user_id'        => $line_user_id,
				'reference_timestamp' => $reference_timestamp,
				'direction'           => $direction,
				'limit'               => $limit,
			)
		) ) {
			wp_send_json_error( array( 'message' => '參數格式錯誤' ) );
		}

		try {
			$messages = $this->message_query_service->get_messages_by_direction(
				$line_user_id,
				$reference_timestamp,
				$direction,
				$limit
			);

			$has_more = count( $messages ) === $limit;

			$response = $this->format_load_messages_response( $messages, $direction, $has_more );
			wp_send_json_success( $response );

		} catch ( Exception $e ) {
			$this->logError( 'Error in load_messages_from_timestamp: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => '查詢時發生錯誤' ) );
		}
	}

	/**
	 * 驗證跳轉到引用訊息的參數
	 *
	 * @param array $params 參數陣列.
	 *
	 * @return bool 驗證結果.
	 */
	private function validate_jump_to_quoted_params( array $params ): bool {

		if ( empty( $params['line_user_id'] ) ) {
			return false;
		}

		if ( empty( $params['target_timestamp'] ) || ! strtotime( $params['target_timestamp'] ) ) {
			return false;
		}

		if ( $params['context_size'] < 0 || $params['context_size'] > 50 ) {
			return false;
		}

		return true;
	}

	/**
	 * 驗證載入訊息的參數
	 *
	 * @param array $params 參數陣列.
	 *
	 * @return bool 驗證結果.
	 */
	private function validate_load_messages_params( array $params ): bool {
		// 驗證 line_user_id 不為空
		if ( empty( $params['line_user_id'] ) ) {
			return false;
		}

		// 驗證 reference_timestamp 格式
		if ( empty( $params['reference_timestamp'] ) || ! strtotime( $params['reference_timestamp'] ) ) {
			return false;
		}

		// 驗證 direction 參數
		if ( ! in_array( $params['direction'], array( 'before', 'after' ) ) ) {
			return false;
		}

		// 驗證 limit 範圍
		if ( $params['limit'] < 1 || $params['limit'] > 100 ) {
			return false;
		}

		return true;
	}

	/**
	 * 格式化跳轉到引用訊息的回應
	 *
	 * @param array  $result 查詢結果
	 * @param string $target_timestamp 目標時間戳
	 * @return array 格式化的回應
	 */
	private function format_jump_to_quoted_response( $result, $target_timestamp ) {
		return array(
			'messages'            => $result['messages'],
			'target_index'        => $result['target_index'],
			'total_count'         => $result['total_count'],
			'has_more_before'     => $result['has_more_before'],
			'has_more_after'      => $result['has_more_after'],
			'reference_timestamp' => $target_timestamp,
		);
	}

	/**
	 * 格式化載入訊息的回應
	 *
	 * @param array  $messages 訊息陣列
	 * @param string $direction 載入方向
	 * @param bool   $has_more 是否還有更多訊息
	 * @return array 格式化的回應
	 */
	private function format_load_messages_response( $messages, $direction, $has_more ) {
		// 取得時間範圍
		$oldest_timestamp = null;
		$newest_timestamp = null;

		if ( ! empty( $messages ) ) {
			$timestamps       = array_column( $messages, 'timestamp' );
			$oldest_timestamp = min( $timestamps );
			$newest_timestamp = max( $timestamps );
		}

		return array(
			'messages'         => $messages,
			'has_more'         => $has_more,
			'direction'        => $direction,
			'oldest_timestamp' => $oldest_timestamp,
			'newest_timestamp' => $newest_timestamp,
		);
	}
}
