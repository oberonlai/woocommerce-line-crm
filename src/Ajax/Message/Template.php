<?php

declare(strict_types=1);

namespace OrderChatz\Ajax\Message;

use OrderChatz\Database\Message\TableTemplate;
use OrderChatz\Util\Logger;

/**
 * Template AJAX Handler Class
 *
 * Handles AJAX requests for message template operations.
 * Provides endpoints for creating, reading, updating, and deleting templates.
 *
 * @package    OrderChatz
 * @subpackage Ajax\Message
 * @since      1.0.8
 */
class Template {

	/**
	 * Template table handler
	 *
	 * @var TableTemplate
	 */
	private TableTemplate $template_table;

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;

		$this->logger         = wc_get_logger();
		$this->template_table = new TableTemplate( $wpdb, $this->logger );

		$this->register_ajax_hooks();
	}

	/**
	 * Register AJAX hooks
	 *
	 * @return void
	 */
	private function register_ajax_hooks(): void {
		add_action( 'wp_ajax_otz_get_templates', array( $this, 'get_templates' ) );
		add_action( 'wp_ajax_otz_save_template', array( $this, 'save_template' ) );
		add_action( 'wp_ajax_otz_delete_template', array( $this, 'delete_template' ) );
		add_action( 'wp_ajax_otz_search_templates', array( $this, 'search_templates' ) );
		add_action( 'wp_ajax_otz_get_template_by_code', array( $this, 'get_template_by_code' ) );
	}

	/**
	 * Verify nonce and user permissions
	 *
	 * @return bool True if valid, false otherwise (also sends error response)
	 */
	private function verify_request(): bool {
		$nonce = ( isset( $_POST['nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'orderchatz_chat_action' ) ) {
			wp_send_json_error( array( 'message' => '安全驗證失敗' ) );
			return false;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => '權限不足' ) );
			return false;
		}

		return true;
	}

	/**
	 * Get all templates
	 *
	 * @return void
	 */
	public function get_templates(): void {
		try {
			if ( ! $this->verify_request() ) {
				return;
			}

			$templates = $this->template_table->get_all_templates();

			wp_send_json_success(
				array(
					'templates' => $templates,
					'count'     => count( $templates ),
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => '取得範本時發生錯誤' ) );
		}
	}

	/**
	 * Save template (create or update)
	 *
	 * @return void
	 */
	public function save_template(): void {
		try {
			if ( ! $this->verify_request() ) {
				return;
			}

			$template_id = isset( $_POST['template_id'] ) ? (int) $_POST['template_id'] : 0;

			$content = ( isset( $_POST['content'] ) ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

			$code = ( isset( $_POST['code'] ) ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

			if ( empty( $content ) ) {
				wp_send_json_error( array( 'message' => '範本內容不能為空' ) );
				return;
			}

			if ( empty( $code ) ) {
				wp_send_json_error( array( 'message' => '快速代碼不能為空' ) );
				return;
			}

			if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $code ) ) {
				wp_send_json_error( array( 'message' => '快速代碼只能包含英數字、底線和橫線' ) );
				return;
			}

			$result  = false;
			$message = '';

			if ( $template_id > 0 ) {
				$result  = $this->template_table->update_template( $template_id, $content, $code );
				$message = $result ? '範本更新成功' : '範本更新失敗';
			} else {
				$template_id = $this->template_table->create_template( $content, $code );
				$result      = $template_id !== false;
				$message     = $result ? '範本建立成功' : '範本建立失敗';
			}

			if ( $result ) {
				$template = $this->template_table->get_template( $template_id );

				wp_send_json_success(
					array(
						'message'  => $message,
						'template' => $template,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => $message ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => '儲存範本時發生錯誤' ) );
		}
	}

	/**
	 * Delete template
	 *
	 * @return void
	 */
	public function delete_template(): void {
		try {
			if ( ! $this->verify_request() ) {
				return;
			}

			$template_id = isset( $_POST['template_id'] ) ? (int) $_POST['template_id'] : 0;

			if ( $template_id <= 0 ) {
				wp_send_json_error( array( 'message' => '無效的範本 ID' ) );
				return;
			}

			$result = $this->template_table->delete_template( $template_id );

			if ( $result ) {
				wp_send_json_success( array( 'message' => '範本刪除成功' ) );
			} else {
				wp_send_json_error( array( 'message' => '範本刪除失敗' ) );
			}
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in delete_template: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => '刪除範本時發生錯誤' ) );
		}
	}

	/**
	 * Search templates
	 *
	 * @return void
	 */
	public function search_templates(): void {
		try {
			if ( ! $this->verify_request() ) {
				return;
			}

			$search_term = ( isset( $_POST['search'] ) ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

			$limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 10;

			if ( empty( $search_term ) ) {
				$templates = $this->template_table->get_all_templates();
			} else {
				$templates = $this->template_table->search_templates( $search_term, $limit );
			}

			wp_send_json_success(
				array(
					'templates'   => $templates,
					'count'       => count( $templates ),
					'search_term' => $search_term,
				)
			);

		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in search_templates: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => '搜尋範本時發生錯誤' ) );
		}
	}

	/**
	 * Get template by code (for @shortcode functionality)
	 * This is called when user types @code in the message input
	 *
	 * @return void
	 */
	public function get_template_by_code(): void {
		try {
			if ( ! $this->verify_request() ) {
				return;
			}

			$code = ( isset( $_POST['code'] ) ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

			if ( empty( $code ) ) {
				wp_send_json_error( array( 'message' => '範本代碼不能為空' ) );
				return;
			}

			$template = $this->template_table->get_template_by_code( $code );

			if ( $template ) {
				wp_send_json_success(
					array(
						'template' => $template,
						'content'  => $template['content'],
					)
				);
			} else {
				wp_send_json_error( array( 'message' => "找不到代碼為 '{$code}' 的範本" ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => '取得範本時發生錯誤' ) );
		}
	}

	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
	private function log_error( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->error( $message, array_merge( array( 'source' => 'orderchatz-template-ajax' ), $context ) );
		} else {
			error_log( "[OrderChatz Template AJAX ERROR] {$message}" );
		}
	}

}
