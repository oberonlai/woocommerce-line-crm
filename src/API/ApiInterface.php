<?php

declare(strict_types=1);

namespace OrderChatz\API;

/**
 * Base API Interface
 *
 * Defines the contract for all API handlers in the OrderChatz system.
 * This interface ensures consistent implementation across different API endpoints.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
interface ApiInterface {

	/**
	 * Handle incoming API request
	 *
	 * @param array $request Request data
	 * @return array Response data
	 * @throws \Exception On handling errors
	 */
	public function handle( array $request ): array;

	/**
	 * Validate incoming request data
	 *
	 * @param array $request Request data to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validate( array $request ): bool;

	/**
	 * Get supported HTTP methods for this API endpoint
	 *
	 * @return array Array of supported HTTP methods (e.g., ['POST', 'GET'])
	 */
	public function get_supported_methods(): array;

	/**
	 * Get required capabilities for accessing this API endpoint
	 *
	 * @return array Array of required WordPress capabilities
	 */
	public function get_required_capabilities(): array;
}