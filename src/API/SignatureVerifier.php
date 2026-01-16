<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Database\SecurityValidator;

/**
 * LINE Webhook Signature Verifier
 *
 * Implements secure signature verification for LINE webhook requests using
 * HMAC-SHA256 algorithm. Provides development mode support and comprehensive
 * audit logging through SecurityValidator integration.
 *
 * Based on LINE Messaging API official signature validation specification:
 * - Uses X-Line-Signature header
 * - HMAC-SHA256 with channel secret as key
 * - Base64 encoded signature comparison
 * - Raw request body as input (not JSON parsed)
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
class SignatureVerifier {

	/**
	 * Security validator for audit logging
	 *
	 * @var SecurityValidator|null
	 */
	private ?SecurityValidator $security_validator;

	/**
	 * WordPress option key for storing channel secret
	 */
	private const CHANNEL_SECRET_OPTION = 'orderchatz_line_channel_secret';

	/**
	 * Development mode constant name
	 */
	private const DEV_MODE_CONSTANT = 'OTZ_DEV_MODE';

	/**
	 * Expected signature header name
	 */
	private const SIGNATURE_HEADER = 'X-Line-Signature';

	/**
	 * HMAC algorithm used for signature generation
	 */
	private const HMAC_ALGORITHM = 'sha256';

	/**
	 * Constructor
	 *
	 * @param SecurityValidator|null $security_validator Security validator for audit logging
	 */
	public function __construct( ?SecurityValidator $security_validator = null ) {
		$this->security_validator = $security_validator;
	}

	/**
	 * Verify signature for a complete WordPress REST request
	 *
	 * Validates the LINE webhook signature against the request body and headers.
	 * Supports development mode bypass for testing environments.
	 *
	 * @param \WP_REST_Request $request WordPress REST API request object
	 * @return bool True if signature is valid or in development mode, false otherwise
	 */
	public function is_valid_request( \WP_REST_Request $request ): bool {
		$start_time = microtime( true );

		// Development mode bypass
		if ( $this->is_development_mode() ) {
			$this->audit_log( 'signature_verification_bypass', [
				'reason' => 'development_mode',
				'request_method' => $request->get_method(),
				'request_route' => $request->get_route()
			] );

			return true;
		}

		// Get signature from header
		$signature = $this->extract_signature_from_request( $request );
		if ( null === $signature ) {
			$this->audit_log( 'signature_verification_failed', [
				'reason' => 'missing_signature_header',
				'headers' => $this->sanitize_headers_for_logging( $request->get_headers() )
			] );

			return false;
		}

		// Get raw request body
		$body = $this->get_raw_request_body();
		if ( null === $body ) {
			$this->audit_log( 'signature_verification_failed', [
				'reason' => 'unable_to_read_request_body',
				'signature_provided' => !empty( $signature )
			] );

			return false;
		}

		// Get channel secret
		$channel_secret = $this->get_channel_secret();
		if ( empty( $channel_secret ) ) {
			$this->audit_log( 'signature_verification_failed', [
				'reason' => 'missing_channel_secret',
				'body_length' => strlen( $body )
			] );

			return false;
		}

		// Verify signature
		$is_valid = $this->verify_signature( $body, $signature, $channel_secret );
		
		// Calculate processing time
		$processing_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		// Audit log the verification result
		$this->audit_log( 'signature_verification_completed', [
			'result' => $is_valid ? 'valid' : 'invalid',
			'body_length' => strlen( $body ),
			'signature_length' => strlen( $signature ),
			'processing_time_ms' => $processing_time,
			'request_method' => $request->get_method(),
			'request_route' => $request->get_route()
		] );

		if ( !$is_valid ) {
			$this->log_security_violation(
				'LINE webhook signature verification failed',
				[
					'body_length' => strlen( $body ),
					'provided_signature' => substr( $signature, 0, 16 ) . '...', // Only log first 16 chars
					'processing_time_ms' => $processing_time
				],
				'invalid_signature'
			);
		}

		return $is_valid;
	}

	/**
	 * Verify HMAC-SHA256 signature
	 *
	 * Implements the official LINE signature verification algorithm:
	 * 1. Generate HMAC-SHA256 hash using channel secret and request body
	 * 2. Base64 encode the hash
	 * 3. Compare with provided signature using constant-time comparison
	 *
	 * @param string $body Raw request body
	 * @param string $signature Provided signature from header
	 * @param string $channel_secret LINE channel secret
	 * @return bool True if signature is valid, false otherwise
	 */
	public function verify_signature( string $body, string $signature, string $channel_secret ): bool {
		// Input validation
		if ( empty( $body ) || empty( $signature ) || empty( $channel_secret ) ) {
			$this->audit_log( 'signature_verification_input_error', [
				'body_empty' => empty( $body ),
				'signature_empty' => empty( $signature ),
				'channel_secret_empty' => empty( $channel_secret )
			] );

			return false;
		}

		// Generate expected signature
		$expected_signature = $this->generate_signature( $body, $channel_secret );

		if ( null === $expected_signature ) {
			return false;
		}

		// Constant-time comparison to prevent timing attacks
		return $this->constant_time_compare( $signature, $expected_signature );
	}

	/**
	 * Generate HMAC-SHA256 signature for request body
	 *
	 * Follows the official LINE Java implementation pattern:
	 * - Uses HMAC-SHA256 algorithm
	 * - Channel secret as the key
	 * - Raw request body as input
	 * - Base64 encoding of the result
	 *
	 * @param string $body Raw request body
	 * @param string $channel_secret LINE channel secret
	 * @return string|null Base64 encoded signature, null on error
	 */
	public function generate_signature( string $body, string $channel_secret ): ?string {
		try {
			// Generate HMAC-SHA256 hash
			$hash = hash_hmac( self::HMAC_ALGORITHM, $body, $channel_secret, true );

			if ( false === $hash ) {
				$this->audit_log( 'signature_generation_failed', [
					'error' => 'hash_hmac_failed',
					'body_length' => strlen( $body ),
					'channel_secret_length' => strlen( $channel_secret )
				] );

				return null;
			}

			// Base64 encode the result
			$signature = base64_encode( $hash );

			if ( false === $signature ) {
				$this->audit_log( 'signature_generation_failed', [
					'error' => 'base64_encode_failed',
					'hash_length' => strlen( $hash )
				] );

				return null;
			}

			return $signature;

		} catch ( \Exception $e ) {
			$this->audit_log( 'signature_generation_exception', [
				'exception_message' => $e->getMessage(),
				'exception_code' => $e->getCode(),
				'body_length' => strlen( $body )
			] );

			return null;
		}
	}

	/**
	 * Get LINE channel secret from WordPress options
	 *
	 * Retrieves the configured channel secret with proper error handling
	 * and audit logging for security compliance.
	 *
	 * @return string Channel secret, empty string if not configured
	 */
	public function get_channel_secret(): string {
		$channel_secret = get_option( self::CHANNEL_SECRET_OPTION, '' );

		// Audit log access to sensitive configuration
		$this->audit_log( 'channel_secret_access', [
			'option_exists' => !empty( $channel_secret ),
			'secret_length' => strlen( $channel_secret )
		] );

		if ( empty( $channel_secret ) ) {
			$this->log_security_violation(
				'LINE channel secret not configured',
				[ 'option_key' => self::CHANNEL_SECRET_OPTION ],
				'missing_configuration'
			);
		}

		return sanitize_text_field( $channel_secret );
	}

	/**
	 * Set LINE channel secret (for testing and configuration)
	 *
	 * Updates the channel secret with proper validation and audit logging.
	 *
	 * @param string $channel_secret New channel secret
	 * @return bool True if successfully saved, false otherwise
	 */
	public function set_channel_secret( string $channel_secret ): bool {
		// Validate channel secret format
		$sanitized_secret = sanitize_text_field( $channel_secret );
		
		if ( empty( $sanitized_secret ) || strlen( $sanitized_secret ) < 32 ) {
			$this->audit_log( 'channel_secret_update_failed', [
				'reason' => 'invalid_format',
				'original_length' => strlen( $channel_secret ),
				'sanitized_length' => strlen( $sanitized_secret )
			] );

			return false;
		}

		$result = update_option( self::CHANNEL_SECRET_OPTION, $sanitized_secret );

		$this->audit_log( 'channel_secret_updated', [
			'success' => $result,
			'secret_length' => strlen( $sanitized_secret ),
			'user_id' => get_current_user_id()
		] );

		return $result;
	}

	/**
	 * Check if running in development mode
	 *
	 * Checks for the OTZ_DEV_MODE constant to allow signature bypass
	 * in development environments.
	 *
	 * @return bool True if in development mode, false otherwise
	 */
	public function is_development_mode(): bool {
		return defined( self::DEV_MODE_CONSTANT ) && constant( self::DEV_MODE_CONSTANT ) === true;
	}

	/**
	 * Extract signature from WordPress REST request
	 *
	 * @param \WP_REST_Request $request WordPress REST request
	 * @return string|null Signature value or null if not found
	 */
	private function extract_signature_from_request( \WP_REST_Request $request ): ?string {
		$headers = $request->get_headers();
		
		// Check various header name formats
		$possible_header_names = [
			'x_line_signature',
			'x-line-signature',
			'X_LINE_SIGNATURE',
			'X-Line-Signature'
		];

		foreach ( $possible_header_names as $header_name ) {
			if ( isset( $headers[ $header_name ] ) ) {
				$signature = $headers[ $header_name ];
				
				// Handle array format (some servers return arrays)
				if ( is_array( $signature ) ) {
					$signature = $signature[0] ?? '';
				}

				return sanitize_text_field( $signature );
			}
		}

		return null;
	}

	/**
	 * Get raw request body from global input stream
	 *
	 * Reads the raw request body as required for signature verification.
	 * Must use raw body, not JSON parsed data.
	 *
	 * @return string|null Raw request body or null on error
	 */
	private function get_raw_request_body(): ?string {
		$body = file_get_contents( 'php://input' );
		
		if ( false === $body ) {
			$this->audit_log( 'request_body_read_failed', [
				'error' => 'file_get_contents_failed',
				'input_stream' => 'php://input'
			] );

			return null;
		}

		return $body;
	}

	/**
	 * Perform constant-time string comparison
	 *
	 * Prevents timing attacks by ensuring comparison time is constant
	 * regardless of where strings differ.
	 *
	 * @param string $known_string Known good signature
	 * @param string $user_string User provided signature
	 * @return bool True if strings match, false otherwise
	 */
	private function constant_time_compare( string $known_string, string $user_string ): bool {
		// Use PHP's built-in constant time comparison if available
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $known_string, $user_string );
		}

		// Fallback implementation for older PHP versions
		if ( strlen( $known_string ) !== strlen( $user_string ) ) {
			return false;
		}

		$result = 0;
		$length = strlen( $known_string );

		for ( $i = 0; $i < $length; $i++ ) {
			$result |= ord( $known_string[ $i ] ) ^ ord( $user_string[ $i ] );
		}

		return $result === 0;
	}

	/**
	 * Sanitize headers for safe logging
	 *
	 * Removes sensitive information from headers before logging.
	 *
	 * @param array $headers Request headers
	 * @return array Sanitized headers safe for logging
	 */
	private function sanitize_headers_for_logging( array $headers ): array {
		$safe_headers = [];
		$sensitive_headers = [ 'authorization', 'x-line-signature' ];

		foreach ( $headers as $name => $value ) {
			$lower_name = strtolower( $name );
			
			if ( in_array( $lower_name, $sensitive_headers, true ) ) {
				$safe_headers[ $name ] = '[REDACTED]';
			} else {
				$safe_headers[ $name ] = is_array( $value ) ? $value[0] : $value;
			}
		}

		return $safe_headers;
	}

	/**
	 * Add entry to security audit log
	 *
	 * @param string $action Action being audited
	 * @param array $context Context data
	 * @return void
	 */
	private function audit_log( string $action, array $context = [] ): void {
		if ( $this->security_validator ) {
			// Use existing SecurityValidator audit logging
			$enhanced_context = array_merge( $context, [
				'component' => 'SignatureVerifier',
				'ip_address' => $this->get_client_ip(),
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
				'timestamp' => wp_date( 'c' )
			] );

			// Use reflection to access private audit_log method
			try {
				$reflection = new \ReflectionClass( $this->security_validator );
				$audit_method = $reflection->getMethod( 'audit_log' );
				$audit_method->setAccessible( true );
				$audit_method->invoke( $this->security_validator, $action, $enhanced_context );
			} catch ( \ReflectionException $e ) {
				// Fallback to error_log if reflection fails
				error_log( "[OrderChatz SignatureVerifier] [{$action}] " . wp_json_encode( $enhanced_context ) );
			}
		} else {
			// Fallback audit logging
			$log_entry = [
				'action' => $action,
				'component' => 'SignatureVerifier',
				'timestamp' => wp_date( 'c' ),
				'context' => $context
			];

			error_log( "[OrderChatz SignatureVerifier Audit] " . wp_json_encode( $log_entry ) );
		}
	}

	/**
	 * Log security violations
	 *
	 * @param string $message Violation message
	 * @param array $context Additional context
	 * @param string $violation_type Type of violation
	 * @return void
	 */
	private function log_security_violation( string $message, array $context = [], string $violation_type = 'unknown' ): void {
		$enhanced_context = array_merge( $context, [
			'component' => 'SignatureVerifier',
			'violation_type' => $violation_type,
			'timestamp' => wp_date( 'c' ),
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'http_method' => $_SERVER['REQUEST_METHOD'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			'ip_address' => $this->get_client_ip()
		] );

		if ( $this->security_validator ) {
			// Use SecurityValidator's private method via reflection
			try {
				$reflection = new \ReflectionClass( $this->security_validator );
				$violation_method = $reflection->getMethod( 'log_security_violation' );
				$violation_method->setAccessible( true );
				$violation_method->invoke( $this->security_validator, $message, $enhanced_context, $violation_type );
			} catch ( \ReflectionException $e ) {
				// Fallback logging
				error_log( "[OrderChatz Security Violation] [{$violation_type}] {$message}: " . wp_json_encode( $enhanced_context ) );
			}
		} else {
			// Fallback security violation logging
			error_log( "[OrderChatz Security Violation] [{$violation_type}] {$message}: " . wp_json_encode( $enhanced_context ) );
		}
	}

	/**
	 * Get client IP address (with proxy support)
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		];

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) && !empty( $_SERVER[ $key ] ) ) {
				$ip_list = explode( ',', $_SERVER[ $key ] );
				$ip = trim( $ip_list[0] );
				
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}
}