<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Database\SecurityValidator;
use OrderChatz\Util\Logger;

/**
 * API Router Class
 *
 * Handles routing of API requests to appropriate handlers.
 * Manages endpoint registration, middleware, and request dispatching.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
class ApiRouter {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Security validator instance
	 *
	 * @var SecurityValidator|null
	 */
	private ?SecurityValidator $security_validator;

	/**
	 * Registered API endpoints
	 *
	 * @var array<string, ApiInterface>
	 */
	private array $endpoints = [];

	/**
	 * API namespace for WordPress REST API integration
	 */
	private const API_NAMESPACE = 'orderchatz/v1';

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 * @param SecurityValidator|null $security_validator Security validator instance
	 */
	public function __construct( 
		\wpdb $wpdb, 
		?SecurityValidator $security_validator = null 
	) {
		$this->wpdb = $wpdb;
		$this->security_validator = $security_validator;
	}

	/**
	 * Initialize API router and register WordPress hooks
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'init', [ $this, 'register_legacy_endpoints' ] );
	}

	/**
	 * Register an API endpoint
	 *
	 * @param string $endpoint Endpoint path (e.g., 'line/webhook')
	 * @param ApiInterface $handler Handler instance
	 * @return void
	 */
	public function register_endpoint( string $endpoint, ApiInterface $handler ): void {
		$endpoint = trim( $endpoint, '/' );
		$this->endpoints[ $endpoint ] = $handler;

	}

	/**
	 * Register REST API routes for WordPress REST API
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		foreach ( $this->endpoints as $endpoint => $handler ) {
			register_rest_route(
				self::API_NAMESPACE,
				'/' . $endpoint,
				[
					'methods' => $handler->get_supported_methods(),
					'callback' => [ $this, 'handle_rest_request' ],
					'permission_callback' => [ $this, 'check_rest_permissions' ],
					'args' => [
						'endpoint' => [
							'required' => false,
							'default' => $endpoint,
							'sanitize_callback' => 'sanitize_text_field'
						]
					]
				]
			);
		}
	}

	/**
	 * Register legacy endpoints for direct POST handling
	 * For external webhooks that don't use WordPress REST API
	 *
	 * @return void
	 */
	public function register_legacy_endpoints(): void {
		// Add query var for legacy API endpoints
		add_filter( 'query_vars', [ $this, 'add_api_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_legacy_request' ] );
	}

	/**
	 * Add API query variables
	 *
	 * @param array $vars Current query variables
	 * @return array Modified query variables
	 */
	public function add_api_query_vars( array $vars ): array {
		$vars[] = 'orderchatz_api';
		$vars[] = 'orderchatz_endpoint';
		return $vars;
	}

	/**
	 * Handle legacy API requests (non-REST)
	 *
	 * @return void
	 */
	public function handle_legacy_request(): void {
		$api_call = get_query_var( 'orderchatz_api' );
		$endpoint = get_query_var( 'orderchatz_endpoint' );

		if ( 'true' === $api_call && ! empty( $endpoint ) ) {
			$this->dispatch_request( $endpoint );
			exit;
		}
	}

	/**
	 * Handle WordPress REST API requests
	 *
	 * @param \WP_REST_Request $request WordPress REST request object
	 * @return \WP_REST_Response|\WP_Error Response object
	 */
	public function handle_rest_request( \WP_REST_Request $request ) {
		$endpoint = $request->get_param( 'endpoint' );
		$route = $request->get_route();
		
		// Extract endpoint from route if not provided as parameter
		if ( empty( $endpoint ) && ! empty( $route ) ) {
			$parts = explode( '/', trim( $route, '/' ) );
			if ( count( $parts ) >= 3 ) {
				$endpoint = implode( '/', array_slice( $parts, 2 ) );
			}
		}

		if ( empty( $endpoint ) ) {
			return new \WP_Error( 'missing_endpoint', 'API endpoint not specified', [ 'status' => 400 ] );
		}

		try {
			$response_data = $this->dispatch_request( $endpoint, $request->get_params() );
			return new \WP_REST_Response( $response_data, 200 );

		} catch ( \Exception $e ) {
			return new \WP_Error( 
				'api_error', 
				'API request failed: ' . $e->getMessage(), 
				[ 'status' => 500 ] 
			);
		}
	}

	/**
	 * Check REST API permissions
	 *
	 * @param \WP_REST_Request $request WordPress REST request object
	 * @return bool|\WP_Error True if permitted, WP_Error otherwise
	 */
	public function check_rest_permissions( \WP_REST_Request $request ) {
		$endpoint = $request->get_param( 'endpoint' );
		
		if ( empty( $endpoint ) || ! isset( $this->endpoints[ $endpoint ] ) ) {
			return new \WP_Error( 'unknown_endpoint', 'Unknown API endpoint', [ 'status' => 404 ] );
		}

		$handler = $this->endpoints[ $endpoint ];
		$required_caps = $handler->get_required_capabilities();

		// If no capabilities required, allow access (e.g., for webhooks)
		if ( empty( $required_caps ) ) {
			return true;
		}

		// Check each required capability
		foreach ( $required_caps as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return new \WP_Error( 
					'insufficient_permissions', 
					'You do not have permission to access this endpoint', 
					[ 'status' => 403 ] 
				);
			}
		}

		return true;
	}

	/**
	 * Dispatch request to appropriate handler
	 *
	 * @param string $endpoint Endpoint path
	 * @param array $data Request data
	 * @return array Response data
	 */
	private function dispatch_request( string $endpoint, array $data = [] ): array {
		$endpoint = trim( $endpoint, '/' );

		if ( ! isset( $this->endpoints[ $endpoint ] ) ) {
			Logger::error( "Unknown API endpoint requested: {$endpoint}", [ 'endpoint' => $endpoint ], 'ApiRouter' );
			return [
				'success' => false,
				'error' => [
					'message' => 'Unknown API endpoint',
					'code' => 404
				]
			];
		}

		// Get request data if not provided
		if ( empty( $data ) ) {
			$data = $this->get_request_data();
		}

		$handler = $this->endpoints[ $endpoint ];
		

		return $handler->handle( $data );
	}

	/**
	 * Get request data from various sources
	 *
	 * @return array Request data
	 */
	private function get_request_data(): array {
		$data = [];

		// Try to get JSON body first
		$raw_body = file_get_contents( 'php://input' );
		if ( ! empty( $raw_body ) ) {
			$json_data = json_decode( $raw_body, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$data = $json_data;
			}
		}

		// Merge with POST data
		$data = array_merge( $data, $_POST );

		// Merge with GET data for convenience
		$data = array_merge( $data, $_GET );

		return $data;
	}

	/**
	 * Get registered endpoints information
	 *
	 * @return array Endpoints information
	 */
	public function get_endpoints_info(): array {
		$info = [];

		foreach ( $this->endpoints as $endpoint => $handler ) {
			$info[ $endpoint ] = [
				'handler' => get_class( $handler ),
				'methods' => $handler->get_supported_methods(),
				'capabilities' => $handler->get_required_capabilities(),
				'rest_url' => rest_url( self::API_NAMESPACE . '/' . $endpoint ),
				'legacy_url' => home_url( '?orderchatz_api=true&orderchatz_endpoint=' . $endpoint )
			];
		}

		return $info;
	}


}