<?php

namespace OrderChatz\Ajax;

use OrderChatz\API\LineAPIClient;
use OrderChatz\Util\Logger;

/**
 * LINE Content Proxy Handler
 *
 * Provides a proxy endpoint to serve LINE message content (images, videos, audio)
 * through WordPress, bypassing CORS restrictions and providing proper authentication.
 *
 * @package OrderChatz\Ajax
 * @since 1.0.0
 */
class LineContentProxyHandler {

	/**
	 * WordPress database instance
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * LINE API client instance
	 *
	 * @var LineAPIClient
	 */
	private LineAPIClient $line_api_client;

	/**
	 * Cache duration for content (in seconds)
	 */
	private const CACHE_DURATION = 3600; // 1 hour

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		// Initialize LINE API client
		$this->line_api_client = new LineAPIClient( $wpdb );
	}

	/**
	 * Initialize handler and register WordPress hooks
	 */
	public function init(): void {
		add_action( 'wp_ajax_otz_get_line_content', array( $this, 'handle_get_content' ) );
		add_action( 'wp_ajax_nopriv_otz_get_line_content', array( $this, 'handle_get_content' ) );
	}

	/**
	 * Handle LINE content request
	 */
	public function handle_get_content(): void {
		try {
			if ( ! check_ajax_referer( 'orderchatz_chat_action', 'nonce', false ) ) {
				wp_send_json_error(
					array(
						'message' => 'Invalid security token',
					),
					403
				);
				return;
			}

			// Get message ID from request (support both GET and POST)
			$message_id = sanitize_text_field( $_REQUEST['message_id'] ?? '' );

			if ( empty( $message_id ) ) {
				wp_send_json_error(
					array(
						'message' => 'Message ID is required',
					),
					400
				);
				return;
			}

			// Check cache first
			$cached_content = $this->get_cached_content( $message_id );
			if ( $cached_content ) {
				$this->serve_content(
					$cached_content['content'],
					$cached_content['content_type']
				);
				return;
			}

			// Get content from LINE API
			$result = $this->line_api_client->get_message_content( $message_id );

			if ( ! $result['success'] ) {
				Logger::error(
					'Failed to get LINE content',
					array(
						'message_id' => $message_id,
						'error'      => $result['error'] ?? 'Unknown error',
					)
				);

				wp_send_json_error(
					array(
						'message' => 'Failed to retrieve content from LINE',
					),
					500
				);
				return;
			}

			$content_data = $result['data'];

			// Cache the content
			$this->cache_content(
				$message_id,
				$content_data['content'],
				$content_data['content_type']
			);

			// Serve the content
			$this->serve_content(
				$content_data['content'],
				$content_data['content_type']
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'LINE content proxy exception',
				array(
					'message'    => $e->getMessage(),
					'message_id' => $message_id ?? 'unknown',
				)
			);

			wp_send_json_error(
				array(
					'message' => 'Internal server error',
				),
				500
			);
		}
	}

	/**
	 * Serve content with proper headers
	 *
	 * @param string $content Binary content
	 * @param string $content_type MIME type
	 */
	private function serve_content( string $content, string $content_type ): void {
		// Set appropriate headers
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: public, max-age=' . self::CACHE_DURATION );

		// Output content and exit
		echo $content;
		exit;
	}

	/**
	 * Get cached content for message ID
	 *
	 * @param string $message_id LINE message ID
	 * @return array|null Cached content or null if not found
	 */
	private function get_cached_content( string $message_id ): ?array {
		$cache_key = "otz_line_content_{$message_id}";
		$cached    = get_transient( $cache_key );

		if ( $cached && is_array( $cached ) ) {
			return $cached;
		}

		return null;
	}

	/**
	 * Cache content for message ID
	 *
	 * @param string $message_id LINE message ID
	 * @param string $content Binary content
	 * @param string $content_type MIME type
	 */
	private function cache_content( string $message_id, string $content, string $content_type ): void {
		$cache_key  = "otz_line_content_{$message_id}";
		$cache_data = array(
			'content'      => $content,
			'content_type' => $content_type,
			'cached_at'    => time(),
		);

		set_transient( $cache_key, $cache_data, self::CACHE_DURATION );
	}

	/**
	 * Get proxy URL for LINE message content
	 *
	 * @param string $message_id LINE message ID
	 * @return string Proxy URL
	 */
	public static function get_proxy_url( string $message_id ): string {
		return admin_url( 'admin-ajax.php' ) . '?' . http_build_query(
			array(
				'action'     => 'otz_get_line_content',
				'message_id' => $message_id,
				'nonce'      => wp_create_nonce( 'orderchatz_chat_action' ),
			)
		);
	}
}
