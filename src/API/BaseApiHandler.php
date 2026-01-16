<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Util\Logger;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;

/**
 * Base API Handler Abstract Class
 *
 * Provides common functionality for all API handlers including logging,
 * error handling, security validation, and response formatting.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
abstract class BaseApiHandler implements ApiInterface {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	protected \wpdb $wpdb;

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	protected ?\WC_Logger $logger;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	protected ?ErrorHandler $error_handler;

	/**
	 * Security validator instance
	 *
	 * @var SecurityValidator|null
	 */
	protected ?SecurityValidator $security_validator;

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
		$this->wpdb = $wpdb;
		$this->logger = $logger;
		$this->error_handler = $error_handler;
		$this->security_validator = $security_validator;
	}

	/**
	 * Handle incoming API request with error handling
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	final public function handle( array $request ): array {
		try {
			// Pre-flight security validation
			if ( ! $this->validate_security_context( $request ) ) {
				return $this->format_error_response( 'Security validation failed', 403 );
			}

			// Validate request data
			if ( ! $this->validate( $request ) ) {
				return $this->format_error_response( 'Invalid request data', 400 );
			}

			// Process the request
			$response = $this->process_request( $request );


			return $this->format_success_response( $response );

		} catch ( \Exception $e ) {
			Logger::error( 
				'API request processing failed: ' . $e->getMessage(),
				[
					'handler' => static::class,
					'exception' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				]
			);

			return $this->format_error_response( 'Internal server error', 500 );
		}
	}

	/**
	 * Process the validated request
	 * To be implemented by concrete API handlers
	 *
	 * @param array $request Validated request data
	 * @return array Response data
	 */
	abstract protected function process_request( array $request ): array;

	/**
	 * Validate security context for the request
	 *
	 * @param array $request Request data
	 * @return bool True if security validation passes
	 */
	protected function validate_security_context( array $request ): bool {
		// Check required capabilities
		$required_caps = $this->get_required_capabilities();
		if ( ! empty( $required_caps ) ) {
			foreach ( $required_caps as $capability ) {
				if ( ! current_user_can( $capability ) ) {
					Logger::error( 
						"User lacks required capability: {$capability}",
						[ 'user_id' => get_current_user_id() ]
					);
					return false;
				}
			}
		}

		// Use SecurityValidator if available
		if ( $this->security_validator ) {
			return $this->security_validator->validate_api_request( $request );
		}

		return true;
	}

	/**
	 * Format successful API response
	 *
	 * @param array $data Response data
	 * @return array Formatted response
	 */
	protected function format_success_response( array $data ): array {
		return [
			'success' => true,
			'data' => $data,
			'timestamp' => wp_date( 'c' ),
			'version' => '1.0.4'
		];
	}

	/**
	 * Format error API response
	 *
	 * @param string $message Error message
	 * @param int $code HTTP status code
	 * @return array Formatted error response
	 */
	protected function format_error_response( string $message, int $code = 400 ): array {
		return [
			'success' => false,
			'error' => [
				'message' => $message,
				'code' => $code
			],
			'timestamp' => wp_date( 'c' ),
			'version' => '1.0.4'
		];
	}


	/**
	 * Log error messages
	 *
	 * @param string $message The error message to log
	 * @param array  $context Additional context data
	 * @return void
	 */

	/**
	 * Default implementation returns no required capabilities
	 * Override in concrete classes as needed
	 *
	 * @return array Array of required WordPress capabilities
	 */
	public function get_required_capabilities(): array {
		return [];
	}

	/**
	 * Default implementation supports POST method
	 * Override in concrete classes as needed
	 *
	 * @return array Array of supported HTTP methods
	 */
	public function get_supported_methods(): array {
		return [ 'POST' ];
	}
}