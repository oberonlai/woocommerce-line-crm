<?php

/**
 * REST API Manager for LINE Webhook Integration
 *
 * This class handles WordPress REST API endpoint registration and manages
 * the webhook endpoint that receives LINE platform events. It provides
 * proper authentication, validation, and request routing.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */

namespace OrderChatz\API;

use OrderChatz\Util\Logger;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Database\DynamicTableManager;
use OrderChatz\Services\WebPushService;
use OrderChatz\API\GroupManager;

/**
 * RestAPIManager class
 *
 * Manages WordPress REST API integration for LINE webhook processing.
 * Registers custom endpoints and handles request routing to appropriate handlers.
 */
class RestAPIManager {

	/**
	 * WordPress database instance
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private ?\WC_Logger $logger;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler
	 */
	private ErrorHandler $error_handler;

	/**
	 * Security validator instance
	 *
	 * @var SecurityValidator
	 */
	private SecurityValidator $security_validator;

	/**
	 * Webhook handler instance
	 *
	 * @var WebhookHandler
	 */
	private WebhookHandler $webhook_handler;

	/**
	 * API namespace for OrderChatz endpoints
	 */
	private const API_NAMESPACE = 'otz/v1';

	/**
	 * Webhook endpoint path
	 */
	private const WEBHOOK_ENDPOINT = 'webhook';

	/**
	 * Constructor
	 *
	 * @param \wpdb             $wpdb               WordPress database instance
	 * @param \WC_Logger|null   $logger             Logger instance
	 * @param ErrorHandler      $error_handler      Error handler instance
	 * @param SecurityValidator $security_validator Security validator instance
	 */
	public function __construct(
		\wpdb $wpdb,
		?\WC_Logger $logger,
		ErrorHandler $error_handler,
		SecurityValidator $security_validator
	) {
		$this->wpdb               = $wpdb;
		$this->logger             = $logger;
		$this->error_handler      = $error_handler;
		$this->security_validator = $security_validator;

		$this->initialize_webhook_handler();
	}

	/**
	 * Initialize webhook handler with all dependencies
	 *
	 * @return void
	 */
	private function initialize_webhook_handler(): void {
		try {
			// Initialize all required components.
			$signature_verifier    = new SignatureVerifier( $this->security_validator );
			$line_api_client       = new LineAPIClient( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator );
			$dynamic_table_manager = new DynamicTableManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator );
			$message_manager       = new MessageManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator, $dynamic_table_manager );
			$user_manager          = new UserManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator, $line_api_client );
			$group_manager         = new GroupManager( $this->wpdb, $this->logger, $this->error_handler, $this->security_validator, $line_api_client, $user_manager );
			$web_push_service      = new WebPushService();

			// Create webhook handler.
			$this->webhook_handler = new WebhookHandler(
				$this->wpdb,
				$this->logger,
				$this->error_handler,
				$this->security_validator,
				$signature_verifier,
				$message_manager,
				$user_manager,
				$group_manager,
				$web_push_service
			);

		} catch ( \Exception $e ) {
			$this->error_handler->handle_error(
				'webhook_handler_initialization_failed',
				'Failed to initialize webhook handler components',
				array( 'exception' => $e->getMessage() ),
				'RestAPIManager::initialize_webhook_handler'
			);
		}
	}

	/**
	 * Register REST API routes
	 *
	 * This method is hooked to 'rest_api_init' and registers all
	 * OrderChatz REST API endpoints.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		try {
			// Register LINE webhook endpoint
			$webhook_registered = register_rest_route(
				self::API_NAMESPACE,
				'/' . self::WEBHOOK_ENDPOINT,
				array(
					'methods'             => \WP_REST_Server::CREATABLE, // POST
					'callback'            => array( $this, 'handle_webhook_request' ),
					'permission_callback' => array( $this, 'webhook_permission_callback' ),
					'args'                => $this->get_webhook_endpoint_args(),
				)
			);

			if ( ! $webhook_registered ) {
				$this->error_handler->handle_error(
					'webhook_route_registration_failed',
					'Failed to register LINE webhook REST API route',
					array(
						'namespace' => self::API_NAMESPACE,
						'endpoint'  => self::WEBHOOK_ENDPOINT,
					),
					'RestAPIManager::register_routes'
				);
				return;
			}

			// Register health check endpoint (useful for monitoring)
			register_rest_route(
				self::API_NAMESPACE,
				'/health',
				array(
					'methods'             => \WP_REST_Server::READABLE, // GET
					'callback'            => array( $this, 'handle_health_check' ),
					'permission_callback' => '__return_true', // Public endpoint
					'args'                => array(),
				)
			);

		} catch ( \Exception $e ) {
			$this->error_handler->handle_error(
				'rest_api_registration_exception',
				'Exception during REST API route registration',
				array( 'exception' => $e->getMessage() ),
				'RestAPIManager::register_routes'
			);
		}
	}

	/**
	 * Handle LINE webhook requests
	 *
	 * @param \WP_REST_Request $request WordPress REST request object
	 *
	 * @return \WP_REST_Response WordPress REST response
	 */
	public function handle_webhook_request( \WP_REST_Request $request ): \WP_REST_Response {
		try {

			// Check if webhook handler is available
			if ( ! $this->webhook_handler ) {
				Logger::error( 'Webhook handler not initialized' );

				return new \WP_REST_Response(
					array(
						'success' => false,
						'error'   => array(
							'code'    => 'handler_unavailable',
							'message' => 'Webhook handler not available',
						),
					),
					503
				);
			}

			// Delegate to webhook handler
			$response = $this->webhook_handler->handle_webhook( $request );

			return $response;

		} catch ( \Exception $e ) {
			$this->error_handler->handle_error(
				'webhook_request_exception',
				'Exception during webhook request handling',
				array(
					'exception'           => $e->getMessage(),
					'request_method'      => $request->get_method(),
					'request_body_length' => strlen( $request->get_body() ),
				),
				'RestAPIManager::handle_webhook_request'
			);

			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => array(
						'code'    => 'internal_error',
						'message' => 'Internal server error',
					),
				),
				500
			);
		}
	}

	/**
	 * Permission callback for webhook endpoint
	 *
	 * LINE webhooks don't use WordPress authentication - they use
	 * signature verification instead. This method always returns true
	 * to allow the request to proceed to signature verification.
	 *
	 * @param \WP_REST_Request $request WordPress REST request object
	 *
	 * @return bool Always returns true for webhook endpoints
	 */
	public function webhook_permission_callback( \WP_REST_Request $request ): bool {
		// LINE webhooks use signature verification, not WordPress permissions
		// The actual security check happens in WebhookHandler::validate_webhook_security()
		return true;
	}

	/**
	 * Handle health check requests
	 *
	 * @param \WP_REST_Request $request WordPress REST request object
	 *
	 * @return \WP_REST_Response WordPress REST response
	 */
	public function handle_health_check( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$health_data = array(
				'status'           => 'healthy',
				'timestamp'        => wp_date( 'c' ),
				'version'          => OTZ_VERSION ?? '1.0.4',
				'php_version'      => PHP_VERSION,
				'wp_version'       => get_bloginfo( 'version' ),
				'webhook_endpoint' => rest_url( self::API_NAMESPACE . '/' . self::WEBHOOK_ENDPOINT ),
				'components'       => array(
					'database'        => $this->check_database_health(),
					'webhook_handler' => $this->webhook_handler !== null,
					'line_api_config' => $this->check_line_api_configuration(),
				),
			);

			// Determine overall health status
			$all_healthy = array_reduce(
				$health_data['components'],
				function( $carry, $component ) {
					return $carry && $component;
				},
				true
			);

			if ( ! $all_healthy ) {
				$health_data['status'] = 'degraded';
			}

			$status_code = $all_healthy ? 200 : 503;

			return new \WP_REST_Response( $health_data, $status_code );

		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				array(
					'status'    => 'error',
					'message'   => 'Health check failed',
					'timestamp' => wp_date( 'c' ),
				),
				500
			);
		}
	}

	/**
	 * Get webhook endpoint arguments schema
	 *
	 * @return array Endpoint arguments configuration
	 */
	private function get_webhook_endpoint_args(): array {
		return array(
			'events' => array(
				'description' => 'Array of LINE webhook events',
				'type'        => 'array',
				'required'    => false, // Will be validated in webhook handler
				'items'       => array(
					'type' => 'object',
				),
			),
		);
	}

	/**
	 * Check database connectivity and health
	 *
	 * @return bool Whether database is healthy
	 */
	private function check_database_health(): bool {
		try {
			// Simple query to test database connectivity
			$result = $this->wpdb->get_var( 'SELECT 1' );
			return $result === '1';

		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check LINE API configuration status
	 *
	 * @return bool Whether LINE API is properly configured
	 */
	private function check_line_api_configuration(): bool {
		$access_token   = get_option( 'otz_access_token', '' );
		$channel_secret = get_option( 'otz_channel_secret', '' );

		return ! empty( $access_token ) && ! empty( $channel_secret );
	}

	/**
	 * Get client IP address from request
	 *
	 * @param \WP_REST_Request $request WordPress REST request object
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip( \WP_REST_Request $request ): string {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',            // Proxy
			'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
			'HTTP_X_FORWARDED',          // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
			'HTTP_FORWARDED_FOR',        // Proxy
			'HTTP_FORWARDED',            // Proxy
			'REMOTE_ADDR',                // Standard
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// Handle comma-separated list (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}

	/**
	 * Get registered webhook URL
	 *
	 * @return string Complete webhook URL
	 */
	public function get_webhook_url(): string {
		return rest_url( self::API_NAMESPACE . '/' . self::WEBHOOK_ENDPOINT );
	}

	/**
	 * Get API namespace
	 *
	 * @return string API namespace
	 */
	public function get_api_namespace(): string {
		return self::API_NAMESPACE;
	}

	/**
	 * Check if REST API is properly configured
	 *
	 * @return array Configuration status information
	 */
	public function get_configuration_status(): array {
		return array(
			'webhook_url'               => $this->get_webhook_url(),
			'api_namespace'             => self::API_NAMESPACE,
			'webhook_handler_available' => $this->webhook_handler !== null,
			'database_healthy'          => $this->check_database_health(),
			'line_api_configured'       => $this->check_line_api_configuration(),
			'rest_api_enabled'          => function_exists( 'rest_url' ),
			'permalinks_enabled'        => get_option( 'permalink_structure' ) !== '',
		);
	}


	/**
	 * Log error messages
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 *
	 * @return void
	 */
}
