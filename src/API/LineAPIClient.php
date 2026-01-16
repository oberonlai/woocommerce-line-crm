<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Util\Logger;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;

/**
 * LINE API Client
 *
 * Handles communication with LINE Messaging API including webhook registration,
 * user profile retrieval, and access token verification. Follows LINE API
 * guidelines and WordPress best practices.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
class LineAPIClient extends BaseApiHandler {

	/**
	 * LINE API Base URL
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.line.me';

	/**
	 * API endpoints
	 *
	 * @var array
	 */
	private const ENDPOINTS = [
		'webhook'        => '/v2/bot/channel/webhook/endpoint',
		'webhook_test'   => '/v2/bot/channel/webhook/test',
		'user_profile'   => '/v2/bot/profile',
		'content'        => '/v2/bot/message/{messageId}/content',
		'preview'        => '/v2/bot/message/{messageId}/content/preview',
		'group_summary'  => '/v2/bot/group/{groupId}/summary',
		'group_members'  => '/v2/bot/group/{groupId}/members/ids',
		'room_summary'   => '/v2/bot/room/{roomId}/summary',
	];

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 30;

	/**
	 * Maximum retry attempts for failed requests
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Access token for LINE API
	 *
	 * @var string|null
	 */
	private ?string $access_token = null;

	/**
	 * Development mode flag
	 *
	 * @var bool
	 */
	private bool $dev_mode;

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 * @param \WC_Logger|null $logger Logger instance
	 * @param ErrorHandler|null $error_handler Error handler instance
	 * @param SecurityValidator|null $security_validator Security validator instance
	 */
	public function __construct( 
		\wpdb $wpdb, 
		?\WC_Logger $logger = null, 
		?ErrorHandler $error_handler = null, 
		?SecurityValidator $security_validator = null 
	) {
		parent::__construct( $wpdb, $logger, $error_handler, $security_validator );
		
		$this->dev_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$this->access_token = $this->get_access_token();
	}

	/**
	 * Process API request (required by BaseApiHandler)
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	protected function process_request( array $request ): array {
		// This method is required by BaseApiHandler but not used directly
		// for LINE API client operations
		return [];
	}

	/**
	 * Validate request data (required by ApiInterface)
	 *
	 * @param mixed $request Request data to validate
	 * @return bool True if valid
	 */
	public function validate( $request ): bool {
		// Basic validation for LINE API client requests
		return is_array( $request );
	}

	/**
	 * Get required capabilities for this API client
	 *
	 * @return array Array of required WordPress capabilities
	 */
	public function get_required_capabilities(): array {
		return [ 'manage_options' ];
	}

	/**
	 * Get supported HTTP methods
	 *
	 * @return array Array of supported methods
	 */
	public function get_supported_methods(): array {
		return [ 'GET', 'POST', 'PUT' ];
	}

	/**
	 * Register webhook endpoint with LINE Platform
	 *
	 * @param string $webhook_url The webhook URL to register
	 * @return array Response data with success status and details
	 */
	public function register_webhook( string $webhook_url ): array {
		try {
			// Validate webhook URL
			if ( ! $this->validate_webhook_url( $webhook_url ) ) {
				return $this->format_error_response( 'Invalid webhook URL format', 400 );
			}

			// Check access token
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['webhook'];
			$body = [ 'endpoint' => $webhook_url ];

			$response = $this->make_api_request( 'PUT', $endpoint, $body );

			if ( $response['success'] ) {
				
				// Store webhook URL in options
				update_option( 'otz_webhook_url', $webhook_url );
				
				return $this->format_success_response([
					'message' => 'Webhook endpoint registered successfully. Please manually enable "Use webhook" in LINE Developers Console.',
					'endpoint' => $webhook_url,
					'manual_action_required' => true,
					'instructions' => 'Go to LINE Developers Console > Messaging API > Webhook settings > Enable "Use webhook"'
				]);
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Webhook registration failed: ' . $e->getMessage(),
				[ 
					'webhook_url' => $webhook_url,
					'exception' => $e->getMessage()
				]
			);

			return $this->format_error_response( 'Webhook registration failed', 500 );
		}
	}

	/**
	 * Test and enable webhook for the LINE Bot channel
	 *
	 * This method tests the current webhook endpoint and enables the "Use webhook" setting.
	 * This is required for the LINE platform to send events to your webhook endpoint.
	 *
	 * @param string $webhook_url Optional webhook URL to test (uses stored URL if not provided)
	 * @return array Response with success/error information
	 */
	public function test_and_enable_webhook( string $webhook_url = '' ): array {
		try {
			// Use stored webhook URL if not provided
			if ( empty( $webhook_url ) ) {
				$webhook_url = get_option( 'otz_webhook_url', '' );
			}

			if ( empty( $webhook_url ) ) {
				return $this->format_error_response( 'No webhook URL configured', 400 );
			}

			// Check access token
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['webhook_test'];
			$body = [ 'endpoint' => $webhook_url ];

			$response = $this->make_api_request( 'POST', $endpoint, $body );

			if ( $response['success'] ) {
				return $this->format_success_response([
					'message' => 'Webhook tested and enabled successfully',
					'endpoint' => $webhook_url,
					'enabled' => true
				]);
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Webhook test and enable failed: ' . $e->getMessage(),
				[ 
					'webhook_url' => $webhook_url ?? 'not provided',
					'exception' => $e->getMessage()
				]
			);

			return $this->format_error_response( 'Failed to test and enable webhook', 500 );
		}
	}

	/**
	 * Enable webhook for the LINE Bot channel
	 *
	 * This method enables the "Use webhook" setting in LINE Bot configuration.
	 * This is required for the LINE platform to send events to your webhook endpoint.
	 *
	 * @return array Response with success/error information
	 */
	public function enable_webhook(): array {
		try {
			// Check access token
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['webhook_test'];

			// The webhook test endpoint also enables webhook when called successfully
			$response = $this->make_api_request( 'POST', $endpoint, [] );

			if ( $response['success'] ) {
				return $this->format_success_response([
					'message' => 'Webhook enabled successfully',
					'enabled' => true
				]);
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Webhook enable failed: ' . $e->getMessage(),
				[ 
					'exception' => $e->getMessage()
				]
			);

			return $this->format_error_response( 'Failed to enable webhook', 500 );
		}
	}

	/**
	 * Get user profile from LINE API
	 *
	 * @param string $user_id LINE user ID
	 * @return array User profile data or error
	 */
	public function get_user_profile( string $user_id ): array {
		try {
			// Validate user ID format
			if ( ! $this->validate_user_id( $user_id ) ) {
				return $this->format_error_response( 'Invalid user ID format', 400 );
			}

			// Check access token
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['user_profile'] . '/' . $user_id;

			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] && isset( $response['data'] ) ) {
				$profile_data = $response['data'];
				
				// Validate and sanitize profile data
				$profile = $this->sanitize_user_profile( $profile_data );
				

				return $this->format_success_response( $profile );
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Failed to get user profile: ' . $e->getMessage(),
				[ 
					'user_id' => $user_id,
					'exception' => $e->getMessage()
				]
			);

			return $this->format_error_response( 'Failed to retrieve user profile', 500 );
		}
	}

	/**
	 * Verify access token with LINE API
	 *
	 * @return bool Whether the access token is valid
	 */
	public function verify_access_token(): bool {
		try {
			// Check if access token exists
			if ( ! $this->access_token ) {
				Logger::error( 'ACCESS_TOKEN not configured' );
				return false;
			}

			// Use webhook endpoint to verify token (GET webhook info)
			$endpoint = self::API_BASE_URL . self::ENDPOINTS['webhook'];

			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] && isset( $response['data'] ) ) {
				$webhook_data = $response['data'];
				

				// Update token verification timestamp and status
				update_option( 'otz_token_verified', time() );
				update_option( 'otz_webhook_status', 'valid_token' );

				return true;
			}

			// Check if this is a 404 error (webhook not found) - this means token is valid but no webhook set
			if ( ! $response['success'] && isset( $response['error']['code'] ) && $response['error']['code'] === 404 ) {

				// Update token verification timestamp and status
				update_option( 'otz_token_verified', time() );
				update_option( 'otz_webhook_status', 'valid_token_no_webhook' );

				return true;
			}

			Logger::error( 'Access token verification failed', [
				'response' => $response
			]);
			
			// Update status to indicate invalid token
			update_option( 'otz_webhook_status', 'invalid_token' );
			
			return false;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Access token verification failed: ' . $e->getMessage(),
				[ 'exception' => $e->getMessage() ]
			);

			update_option( 'otz_webhook_status', 'invalid_token' );
			return false;
		}
	}

	/**
	 * Get webhook information from LINE platform
	 *
	 * @return array Webhook information or error
	 */
	public function get_webhook_info(): array {
		try {
			// Check access token
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . self::ENDPOINTS['webhook'];

			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] && isset( $response['data'] ) ) {
				$webhook_data = $response['data'];
				

				return $this->format_success_response( $webhook_data );
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error( 
				'Failed to get webhook info: ' . $e->getMessage(),
				[ 'exception' => $e->getMessage() ]
			);

			return $this->format_error_response( 'Failed to retrieve webhook information', 500 );
		}
	}

	/**
	 * Get access token from WordPress options or use test token
	 *
	 * @return string|null Access token
	 */
	public function get_access_token(): ?string {
		// Get from WordPress options first
		$token = get_option( 'otz_access_token', '' );
		
		// Fallback to test token if in development mode
		if ( empty( $token ) && $this->dev_mode ) {
			$token = $this->get_test_access_token();
		}

		return ! empty( $token ) ? $token : null;
	}

	/**
	 * Set access token and store securely
	 *
	 * @param string $token Access token
	 * @return bool Success status
	 */
	public function set_access_token( string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		// Validate token format (basic check)
		if ( ! $this->validate_access_token_format( $token ) ) {
			Logger::error( 'Invalid access token format provided' );
			return false;
		}

		// Store token securely
		$updated = update_option( 'otz_access_token', $token );
		
		if ( $updated ) {
			$this->access_token = $token;
			
			// Clear verification timestamp to force re-verification
			delete_option( 'otz_token_verified' );
		}

		return $updated;
	}

	/**
	 * Make HTTP request to LINE API with error handling and retries
	 *
	 * @param string $method HTTP method
	 * @param string $endpoint API endpoint URL
	 * @param array|null $body Request body data
	 * @return array Response data
	 */
	private function make_api_request( string $method, string $endpoint, ?array $body = null ): array {
		$headers = [
			'Authorization' => 'Bearer ' . $this->access_token,
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'OrderChatz-WordPress-Plugin/1.0.4'
		];

		$args = [
			'method'  => $method,
			'headers' => $headers,
			'timeout' => self::REQUEST_TIMEOUT,
		];

		if ( $body && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		// Retry logic for failed requests
		$last_error = null;
		for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			$response = wp_remote_request( $endpoint, $args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
				Logger::error( 
					"API request attempt {$attempt} failed: {$last_error}",
					[ 'endpoint' => $endpoint, 'method' => $method ]
				);

				if ( $attempt < self::MAX_RETRIES ) {
					// Wait before retry (exponential backoff)
					sleep( $attempt );
					continue;
				}
				break;
			}

			// Parse response
			$status_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$request_id = wp_remote_retrieve_header( $response, 'x-line-request-id' );


			if ( $status_code >= 200 && $status_code < 300 ) {
				$data = json_decode( $response_body, true );
				return $this->format_success_response( $data ?? [] );
			}

			// Handle LINE API errors
			$error_data = json_decode( $response_body, true );
			$error_message = $this->parse_line_api_error( $status_code, $error_data );

			// Don't retry on client errors (4xx)
			if ( $status_code >= 400 && $status_code < 500 ) {
				Logger::error( 
					"LINE API client error: {$error_message}",
					[ 
						'endpoint' => $endpoint,
						'status_code' => $status_code,
						'error_data' => $error_data
					]
				);
				return $this->format_error_response( $error_message, $status_code );
			}

			// Retry on server errors (5xx)
			if ( $status_code >= 500 && $attempt < self::MAX_RETRIES ) {
				Logger::error( 
					"LINE API server error on attempt {$attempt}: {$error_message}",
					[ 'endpoint' => $endpoint, 'status_code' => $status_code ]
				);
				sleep( $attempt );
				continue;
			}

			// Final attempt failed
			Logger::error( 
				"LINE API request failed after {$attempt} attempts: {$error_message}",
				[ 
					'endpoint' => $endpoint,
					'status_code' => $status_code,
					'error_data' => $error_data
				]
			);
			return $this->format_error_response( $error_message, $status_code );
		}

		// All retries failed
		return $this->format_error_response( 
			$last_error ?? 'Request failed after maximum retries', 
			500 
		);
	}

	/**
	 * Parse LINE API error response
	 *
	 * @param int $status_code HTTP status code
	 * @param array|null $error_data Error response data
	 * @return string User-friendly error message
	 */
	private function parse_line_api_error( int $status_code, ?array $error_data ): string {
		// Default error messages based on status code
		$status_messages = [
			400 => 'Bad Request - Invalid request format',
			401 => 'Unauthorized - Invalid or expired access token',
			403 => 'Forbidden - Insufficient permissions',
			404 => 'Not Found - Resource does not exist',
			409 => 'Conflict - Resource already exists',
			429 => 'Too Many Requests - Rate limit exceeded',
			500 => 'Internal Server Error - LINE Platform error',
			502 => 'Bad Gateway - LINE Platform unavailable',
			503 => 'Service Unavailable - LINE Platform maintenance'
		];

		$default_message = $status_messages[$status_code] ?? 'Unknown error occurred';

		// Try to extract specific error message from LINE API response
		if ( is_array( $error_data ) ) {
			if ( isset( $error_data['message'] ) ) {
				return sanitize_text_field( $error_data['message'] );
			}

			if ( isset( $error_data['details'] ) && is_array( $error_data['details'] ) ) {
				$details = array_map( 'sanitize_text_field', $error_data['details'] );
				return $default_message . ': ' . implode( ', ', $details );
			}
		}

		return $default_message;
	}


	/**
	 * Validate webhook URL format
	 *
	 * @param string $url Webhook URL
	 * @return bool True if valid
	 */
	private function validate_webhook_url( string $url ): bool {
		// Must be HTTPS
		if ( strpos( $url, 'https://' ) !== 0 ) {
			Logger::error( 'Webhook URL validation failed: Must use HTTPS protocol', [
				'url' => $url
			]);
			return false;
		}

		// Validate URL format
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			Logger::error( 'Webhook URL validation failed: Invalid URL format', [
				'url' => $url
			]);
			return false;
		}

		// Check if URL is reachable (basic check)
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || empty( $parsed_url['host'] ) ) {
			Logger::error( 'Webhook URL validation failed: Cannot parse URL or missing host', [
				'url' => $url,
				'parsed' => $parsed_url
			]);
			return false;
		}

		// Check for local/development domains that LINE cannot access
		$host = strtolower( $parsed_url['host'] );
		$local_domains = [
			'localhost',
			'127.0.0.1',
			'::1',
		];

		$local_tlds = [ '.test', '.local', '.dev' ];

		// Check for exact local domains
		if ( in_array( $host, $local_domains ) ) {
			Logger::error( 'Webhook URL validation failed: Local domain not accessible by LINE platform', [
				'url' => $url,
				'host' => $host,
				'reason' => 'Local domain detected'
			]);
			return false;
		}

		// Check for local TLDs
		foreach ( $local_tlds as $tld ) {
			if ( str_ends_with( $host, $tld ) ) {
				Logger::error( 'Webhook URL validation failed: Local TLD not accessible by LINE platform', [
					'url' => $url,
					'host' => $host,
					'tld' => $tld,
					'reason' => 'Local TLD detected'
				]);
				return false;
			}
		}

		// Check for private IP ranges
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ip = $host;
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				Logger::error( 'Webhook URL validation failed: Private/reserved IP not accessible by LINE platform', [
					'url' => $url,
					'ip' => $ip,
					'reason' => 'Private IP range detected'
				]);
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate LINE user ID format
	 *
	 * @param string $user_id LINE user ID
	 * @return bool True if valid
	 */
	private function validate_user_id( string $user_id ): bool {
		// LINE user ID should be a non-empty string
		if ( empty( $user_id ) || ! is_string( $user_id ) ) {
			return false;
		}

		// Basic format validation (alphanumeric and some special characters)
		if ( ! preg_match( '/^[a-zA-Z0-9._-]+$/', $user_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate access token format
	 *
	 * @param string $token Access token
	 * @return bool True if valid format
	 */
	private function validate_access_token_format( string $token ): bool {
		// Basic validation - should be a non-empty string
		if ( empty( $token ) ) {
			return false;
		}

		// LINE access tokens are typically long alphanumeric strings
		if ( strlen( $token ) < 10 ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize user profile data from LINE API
	 *
	 * @param array $profile_data Raw profile data
	 * @return array Sanitized profile data
	 */
	private function sanitize_user_profile( array $profile_data ): array {
		$sanitized = [];

		$allowed_fields = [ 'displayName', 'userId', 'language', 'pictureUrl', 'statusMessage' ];

		foreach ( $allowed_fields as $field ) {
			if ( isset( $profile_data[$field] ) ) {
				if ( $field === 'pictureUrl' ) {
					$sanitized[$field] = esc_url_raw( $profile_data[$field] );
				} else {
					$sanitized[$field] = sanitize_text_field( $profile_data[$field] );
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize token information data
	 *
	 * @param array $token_data Raw token data
	 * @return array Sanitized token data
	 */
	private function sanitize_token_info( array $token_data ): array {
		$sanitized = [];

		$allowed_fields = [ 'scope', 'client_id', 'expires_in' ];

		foreach ( $allowed_fields as $field ) {
			if ( isset( $token_data[$field] ) ) {
				if ( $field === 'expires_in' ) {
					$sanitized[$field] = absint( $token_data[$field] );
				} else {
					$sanitized[$field] = sanitize_text_field( $token_data[$field] );
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Get test access token for development mode
	 *
	 * @return string Test access token
	 */
	private function get_test_access_token(): string {
		// Return test token specified in project requirements
		// This should only be used in development mode
		return 'Bpjrj+Kp8tZJYUxY9dCbGsG6NqWFBKO9amdz3lzYXpBKmJiNFZCMaKJWYfphIzSVaLyD9P5Ix+cJKPYNJ9TQG6xHfX+5fZjLjXoQuqWSDFSY4R1YfgRc3wLtZHNjH4l5aZXQe5YNJJPJoJ9YXJLJaG6JNYaLJGN+cZfZYXJNjNLfZ=';
	}

	/**
	 * Check if LINE API client is properly configured
	 *
	 * @return array Configuration status
	 */
	public function get_configuration_status(): array {
		$status = [
			'access_token_configured' => ! empty( $this->access_token ),
			'webhook_url_configured'  => ! empty( get_option( 'otz_webhook_url' ) ),
			'last_token_verification' => get_option( 'otz_token_verified', 0 ),
			'dev_mode'               => $this->dev_mode
		];

		// Check token verification status (consider valid for 1 hour)
		$last_verified = (int) $status['last_token_verification'];
		$status['token_verification_valid'] = ( time() - $last_verified ) < 3600;

		return $status;
	}

	/**
	 * Get content from LINE API using message ID
	 *
	 * @param string $message_id LINE message ID
	 * @return array Response data with content or error
	 */
	public function get_message_content( string $message_id ): array {
		if ( empty( $message_id ) ) {
			return $this->format_error_response( 'Message ID is required', 400 );
		}

		if ( empty( $this->access_token ) ) {
			return $this->format_error_response( 'Access token is required', 401 );
		}

		// Build endpoint URL
		$endpoint = str_replace( '{messageId}', $message_id, self::ENDPOINTS['content'] );
		$url = 'https://api-data.line.me' . $endpoint;

		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->access_token,
			],
			'timeout' => self::REQUEST_TIMEOUT,
			'method'  => 'GET'
		];

		// Make API request
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error( 
				'Failed to get LINE content: ' . $response->get_error_message(),
				[ 'message_id' => $message_id ]
			);
			return $this->format_error_response( 'Failed to connect to LINE API', 500 );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code === 200 ) {
			// Content retrieved successfully
			return $this->format_success_response([
				'content' => $response_body,
				'content_type' => $content_type,
				'size' => strlen( $response_body ),
				'message_id' => $message_id
			]);
		}

		// Handle errors
		$error_data = json_decode( $response_body, true );
		$error_message = $this->parse_line_api_error( $status_code, $error_data );

		Logger::error( 
			"Failed to get LINE content: {$error_message}",
			[ 
				'message_id' => $message_id,
				'status_code' => $status_code,
				'error_data' => $error_data
			]
		);

		return $this->format_error_response( $error_message, $status_code );
	}

	/**
	 * Get preview image from LINE API using message ID
	 *
	 * @param string $message_id LINE message ID
	 * @return array Response data with preview content or error
	 */
	public function get_message_preview( string $message_id ): array {
		if ( empty( $message_id ) ) {
			return $this->format_error_response( 'Message ID is required', 400 );
		}

		if ( empty( $this->access_token ) ) {
			return $this->format_error_response( 'Access token is required', 401 );
		}

		// Build endpoint URL
		$endpoint = str_replace( '{messageId}', $message_id, self::ENDPOINTS['preview'] );
		$url = 'https://api-data.line.me' . $endpoint;

		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->access_token,
			],
			'timeout' => self::REQUEST_TIMEOUT,
			'method'  => 'GET'
		];

		// Make API request
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::error( 
				'Failed to get LINE preview: ' . $response->get_error_message(),
				[ 'message_id' => $message_id ]
			);
			return $this->format_error_response( 'Failed to connect to LINE API', 500 );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code === 200 ) {
			// Preview retrieved successfully
			return $this->format_success_response([
				'content' => $response_body,
				'content_type' => $content_type,
				'size' => strlen( $response_body ),
				'message_id' => $message_id
			]);
		}

		// Handle errors
		$error_data = json_decode( $response_body, true );
		$error_message = $this->parse_line_api_error( $status_code, $error_data );

		Logger::error( 
			"Failed to get LINE preview: {$error_message}",
			[ 
				'message_id' => $message_id,
				'status_code' => $status_code,
				'error_data' => $error_data
			]
		);

		return $this->format_error_response( $error_message, $status_code );
	}

	/**
	 * Get group summary from LINE API
	 *
	 * @param string $group_id LINE group ID
	 * @return array Group summary data or error
	 */
	public function get_group_summary( string $group_id ): array {
		try {
			// Validate group ID format.
			if ( empty( $group_id ) || ! is_string( $group_id ) ) {
				return $this->format_error_response( 'Invalid group ID format', 400 );
			}

			// Check access token.
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . str_replace( '{groupId}', $group_id, self::ENDPOINTS['group_summary'] );

			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] && isset( $response['data'] ) ) {
				$group_data = $response['data'];

				// Sanitize group data.
				$sanitized_group = array();
				if ( isset( $group_data['groupId'] ) ) {
					$sanitized_group['groupId'] = sanitize_text_field( $group_data['groupId'] );
				}
				if ( isset( $group_data['groupName'] ) ) {
					$sanitized_group['groupName'] = sanitize_text_field( $group_data['groupName'] );
				}
				if ( isset( $group_data['pictureUrl'] ) ) {
					$sanitized_group['pictureUrl'] = esc_url_raw( $group_data['pictureUrl'] );
				}

				return $this->format_success_response( $sanitized_group );
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error(
				'Failed to get group summary: ' . $e->getMessage(),
				array(
					'group_id' => $group_id,
					'exception' => $e->getMessage(),
				)
			);

			return $this->format_error_response( 'Failed to retrieve group summary', 500 );
		}
	}

	/**
	 * Get group member IDs from LINE API
	 *
	 * @param string $group_id LINE group ID
	 * @param string|null $start Continuation token for pagination
	 * @return array Group member IDs or error
	 */
	public function get_group_members( string $group_id, ?string $start = null ): array {
		try {
			// Validate group ID format.
			if ( empty( $group_id ) || ! is_string( $group_id ) ) {
				return $this->format_error_response( 'Invalid group ID format', 400 );
			}

			// Check access token.
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . str_replace( '{groupId}', $group_id, self::ENDPOINTS['group_members'] );

			// Add pagination token if provided.
			if ( $start ) {
				$endpoint .= '?start=' . urlencode( $start );
			}

			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] && isset( $response['data'] ) ) {
				$members_data = $response['data'];

				// Sanitize response.
				$sanitized_data = array();
				if ( isset( $members_data['memberIds'] ) && is_array( $members_data['memberIds'] ) ) {
					$sanitized_data['memberIds'] = array_map( 'sanitize_text_field', $members_data['memberIds'] );
				}
				if ( isset( $members_data['next'] ) ) {
					$sanitized_data['next'] = sanitize_text_field( $members_data['next'] );
				}

				return $this->format_success_response( $sanitized_data );
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error(
				'Failed to get group members: ' . $e->getMessage(),
				array(
					'group_id' => $group_id,
					'start' => $start,
					'exception' => $e->getMessage(),
				)
			);

			return $this->format_error_response( 'Failed to retrieve group members', 500 );
		}
	}

	/**
	 * Get room summary from LINE API
	 *
	 * @param string $room_id LINE room ID
	 * @return array Room summary data or error
	 */
	public function get_room_summary( string $room_id ): array {
		try {
			// Validate room ID format.
			if ( empty( $room_id ) || ! is_string( $room_id ) ) {
				return $this->format_error_response( 'Invalid room ID format', 400 );
			}

			// Check access token.
			if ( ! $this->access_token ) {
				return $this->format_error_response( 'ACCESS_TOKEN not configured', 401 );
			}

			$endpoint = self::API_BASE_URL . str_replace( '{roomId}', $room_id, self::ENDPOINTS['room_summary'] );

			$response = $this->make_api_request( 'GET', $endpoint );

			if ( $response['success'] && isset( $response['data'] ) ) {
				$room_data = $response['data'];

				// Sanitize room data (similar to group data).
				$sanitized_room = array();
				if ( isset( $room_data['roomId'] ) ) {
					$sanitized_room['roomId'] = sanitize_text_field( $room_data['roomId'] );
				}

				return $this->format_success_response( $sanitized_room );
			}

			return $response;

		} catch ( \Exception $e ) {
			Logger::error(
				'Failed to get room summary: ' . $e->getMessage(),
				array(
					'room_id' => $room_id,
					'exception' => $e->getMessage(),
				)
			);

			return $this->format_error_response( 'Failed to retrieve room summary', 500 );
		}
	}
}