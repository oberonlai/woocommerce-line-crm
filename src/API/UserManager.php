<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Util\Logger;

/**
 * User Manager Class
 *
 * Handles LINE user management including user creation, profile synchronization,
 * status updates, and lifecycle events. Integrates with LineAPIClient for
 * profile data retrieval and provides fallback mechanisms for API failures.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
class UserManager extends BaseApiHandler {

	/**
	 * LINE API client instance
	 *
	 * @var LineAPIClient
	 */
	private LineAPIClient $line_api_client;


	/**
	 * Maximum retry attempts for profile fetching
	 *
	 * @var int
	 */
	private const MAX_PROFILE_RETRIES = 2;

	/**
	 * Profile cache duration in seconds (1 hour)
	 *
	 * @var int
	 */
	private const PROFILE_CACHE_DURATION = 3600;

	/**
	 * Users table name
	 *
	 * @var string
	 */
	private string $users_table;

	/**
	 * Constructor
	 *
	 * @param \wpdb                  $wpdb WordPress database object
	 * @param \WC_Logger|null        $logger Logger instance
	 * @param ErrorHandler|null      $error_handler Error handler instance
	 * @param SecurityValidator|null $security_validator Security validator instance
	 * @param LineAPIClient|null     $line_api_client LINE API client instance
	 */
	public function __construct(
		\wpdb $wpdb,
		?\WC_Logger $logger = null,
		?ErrorHandler $error_handler = null,
		?SecurityValidator $security_validator = null,
		?LineAPIClient $line_api_client = null
	) {
		parent::__construct( $wpdb, $logger, $error_handler, $security_validator );

		// Initialize LINE API client
		$this->line_api_client = $line_api_client ?? new LineAPIClient(
			$wpdb,
			$logger,
			$error_handler,
			$security_validator
		);

		// Set table name
		$this->users_table = $this->wpdb->prefix . 'otz_users';
	}

	/**
	 * Process request (required by BaseApiHandler)
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	protected function process_request( array $request ): array {
		// This is handled by specific user methods
		return array();
	}

	/**
	 * Validate request data
	 *
	 * @param array $request Request data to validate
	 * @return bool True if valid
	 */
	public function validate( array $request ): bool {
		// Basic validation for user manager requests
		return is_array( $request ) && ! empty( $request );
	}

	/**
	 * Get required capabilities
	 *
	 * @return array Array of required WordPress capabilities
	 */
	public function get_required_capabilities(): array {
		return array(); // No special capabilities required for user management
	}

	/**
	 * Get supported HTTP methods
	 *
	 * @return array Array of supported methods
	 */
	public function get_supported_methods(): array {
		return array( 'POST', 'GET' );
	}

	/**
	 * Ensure user exists in database, create or update if needed
	 *
	 * @param string $user_id LINE user ID
	 * @param array  $source_info Source information from LINE event
	 * @return array User data array with status information
	 */
	public function ensure_user_exists( string $user_id, array $source_info ): array {
		try {
			// Validate parameters
			if ( empty( $user_id ) || ! is_array( $source_info ) ) {
				return $this->format_error_response( 'Invalid parameters provided', 400 );
			}

			// Check if user already exists
			$existing_user = $this->get_user_data( $user_id );

			if ( $existing_user['success'] ) {
				// 只有個人訊息才更新 last_active，群組訊息不更新.
				$source_type = $source_info['type'] ?? 'user';
				if ( $source_type === 'user' ) {
					$this->update_last_active( $user_id );
				}

				return $existing_user;
			}

			// User doesn't exist, create new user
			return $this->create_user( $user_id, $source_info );

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception ensuring user exists: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return $this->format_error_response( 'Failed to ensure user exists', 500 );
		}
	}

	/**
	 * Get user data from database
	 *
	 * @param string $user_id LINE user ID
	 * @return array User data or error response
	 */
	public function get_user_data( string $user_id ): array {
		try {
			if ( empty( $user_id ) ) {
				return $this->format_error_response( 'User ID is required', 400 );
			}

			// Query user from database
			$query = $this->wpdb->prepare(
				"SELECT * FROM {$this->users_table} WHERE line_user_id = %s",
				$user_id
			);

			$user_data = $this->wpdb->get_row( $query, ARRAY_A );

			if ( $this->wpdb->last_error ) {
				Logger::error(
					'Database error getting user data',
					array(
						'error'   => $this->wpdb->last_error,
						'user_id' => $user_id,
					),
					'UserManager'
				);
				return $this->format_error_response( 'Database error', 500 );
			}

			if ( ! $user_data ) {
				return $this->format_error_response( 'User not found', 404 );
			}

			// Remove sensitive internal fields
			unset( $user_data['id'] );

			return $this->format_success_response( $user_data );

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception getting user data: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return $this->format_error_response( 'Failed to get user data', 500 );
		}
	}

	/**
	 * Update user profile data from LINE API
	 *
	 * @param string     $user_id LINE user ID
	 * @param array|null $profile_data Optional profile data (if not provided, fetched from API)
	 * @return bool True on success, false on failure
	 */
	public function update_user_profile( string $user_id, ?array $profile_data = null ): bool {
		try {
			// Fetch profile data from LINE API if not provided
			if ( null === $profile_data ) {
				$profile_response = $this->line_api_client->get_user_profile( $user_id );

				if ( ! $profile_response['success'] ) {
					Logger::error(
						'Failed to fetch user profile from LINE API',
						array(
							'user_id'      => $user_id,
							'api_response' => $profile_response,
						),
						'UserManager'
					);
					return false;
				}

				$profile_data = $profile_response['data'];
			}

			// Prepare update data - updated to match database schema
			$update_data = array(
				'display_name' => sanitize_text_field( $profile_data['displayName'] ?? '' ),
				'avatar_url'   => esc_url_raw( $profile_data['pictureUrl'] ?? $this->get_default_avatar_url() ),
			);

			// Remove empty fields
			$update_data = array_filter(
				$update_data,
				function( $value ) {
					return '' !== $value;
				}
			);

			// Update user in database
			$result = $this->wpdb->update(
				$this->users_table,
				$update_data,
				array( 'line_user_id' => $user_id ),
				array_fill( 0, count( $update_data ), '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				Logger::error(
					'Database update failed for user profile',
					array(
						'error'   => $this->wpdb->last_error,
						'user_id' => $user_id,
					),
					'UserManager'
				);
				return false;
			}

			// Cache profile data
			$cache_key = 'otz_user_profile_' . md5( $user_id );
			set_transient( $cache_key, $profile_data, self::PROFILE_CACHE_DURATION );

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception updating user profile: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return false;
		}
	}

	/**
	 * Update user's last active timestamp
	 *
	 * @param string $user_id LINE user ID
	 * @return bool True on success, false on failure
	 */
	public function update_last_active( string $user_id ): bool {
		try {
			if ( empty( $user_id ) ) {
				return false;
			}

			$result = $this->wpdb->update(
				$this->users_table,
				array( 'last_active' => wp_date( 'Y-m-d H:i:s' ) ),
				array( 'line_user_id' => $user_id ),
				array( '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				Logger::error(
					'Failed to update last active timestamp',
					array(
						'error'   => $this->wpdb->last_error,
						'user_id' => $user_id,
					),
					'UserManager'
				);
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception updating last active: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return false;
		}
	}

	/**
	 * Handle user follow event
	 *
	 * @param string $user_id LINE user ID
	 * @param array  $event_data Complete follow event data
	 * @return bool True on success, false on failure
	 */
	public function handle_follow( string $user_id, array $event_data ): bool {
		try {
			// Validate event data
			if ( empty( $user_id ) || ! is_array( $event_data ) ) {
				return false;
			}

			// Extract source information
			$source_info = $event_data['source'] ?? array();

			// Ensure user exists
			$user_result = $this->ensure_user_exists( $user_id, $source_info );
			if ( ! $user_result['success'] ) {
				Logger::error(
					'Failed to ensure user exists for follow event',
					array( 'user_id' => $user_id ),
					'UserManager'
				);
				return false;
			}

			// Update follow status
			$update_data = array(
				'status'        => 'active',
				'followed_at'   => wp_date( 'Y-m-d H:i:s' ),
				'unfollowed_at' => null,
				'last_active'   => wp_date( 'Y-m-d H:i:s' ),
			);

			$result = $this->wpdb->update(
				$this->users_table,
				$update_data,
				array( 'line_user_id' => $user_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				Logger::error(
					'Failed to update user follow status',
					array(
						'error'   => $this->wpdb->last_error,
						'user_id' => $user_id,
					),
					'UserManager'
				);
				return false;
			}

			// Try to update user profile from LINE API
			$this->update_user_profile( $user_id );

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception handling follow event: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return false;
		}
	}

	/**
	 * Handle user unfollow event
	 *
	 * @param string $user_id LINE user ID
	 * @return bool True on success, false on failure
	 */
	public function handle_unfollow( string $user_id ): bool {
		try {
			if ( empty( $user_id ) ) {
				return false;
			}

			// Update unfollow status
			$update_data = array(
				'status'        => 'unfollowed',
				'unfollowed_at' => wp_date( 'Y-m-d H:i:s' ),
				'last_active'   => wp_date( 'Y-m-d H:i:s' ),
			);

			$result = $this->wpdb->update(
				$this->users_table,
				$update_data,
				array( 'line_user_id' => $user_id ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				Logger::error(
					'Failed to update user unfollow status',
					array(
						'error'   => $this->wpdb->last_error,
						'user_id' => $user_id,
					),
					'UserManager'
				);
				return false;
			}

			// Clear user profile cache
			$cache_key = 'otz_user_profile_' . md5( $user_id );
			delete_transient( $cache_key );

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception handling unfollow event: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return false;
		}
	}

	/**
	 * Get default avatar URL when LINE profile is unavailable
	 *
	 * @return string Default avatar URL
	 */
	public function get_default_avatar_url(): string {
		// Check if custom default avatar is configured
		$custom_avatar = get_option( 'otz_default_avatar_url', '' );

		if ( ! empty( $custom_avatar ) && filter_var( $custom_avatar, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $custom_avatar );
		}

		// Use WordPress built-in default avatar
		return get_avatar_url( 0, array( 'default' => 'mystery' ) );
	}

	/**
	 * Create new user in database
	 *
	 * @param string $user_id LINE user ID
	 * @param array  $source_info Source information from LINE event
	 * @return array User creation result
	 */
	private function create_user( string $user_id, array $source_info ): array {
		try {
			// Extract source details
			$source_type = sanitize_text_field( $source_info['type'] ?? 'user' );
			$group_id    = null;

			if ( isset( $source_info['groupId'] ) ) {
				$group_id = sanitize_text_field( $source_info['groupId'] );
			} elseif ( isset( $source_info['roomId'] ) ) {
				$group_id = sanitize_text_field( $source_info['roomId'] );
			}

			// Prepare user data - updated to match database schema
			$user_data = array(
				'line_user_id' => sanitize_text_field( $user_id ),
				'source_type'  => $source_type,
				'group_id'     => $group_id,
				'display_name' => '',
				'avatar_url'   => $this->get_default_avatar_url(),
				'status'       => 'active',
				'followed_at'  => wp_date( 'Y-m-d H:i:s' ),
				'last_active'  => wp_date( 'Y-m-d H:i:s' ),
				'linked_at'    => wp_date( 'Y-m-d H:i:s' ),
			);

			// Insert user into database
			$result = $this->wpdb->insert(
				$this->users_table,
				$user_data,
				array(
					'%s', // line_user_id
					'%s', // source_type
					'%s', // group_id
					'%s', // display_name
					'%s', // avatar_url
					'%s', // status
					'%s', // followed_at
					'%s', // last_active
					'%s',  // linked_at
				)
			);

			if ( false === $result ) {
				Logger::error(
					'Failed to insert new user',
					array(
						'error'   => $this->wpdb->last_error,
						'user_id' => $user_id,
					),
					'UserManager'
				);
				return $this->format_error_response( 'Failed to create user', 500 );
			}

			// Try to update profile from LINE API (don't block on failure)
			$this->update_user_profile( $user_id );

			// Remove sensitive internal fields before returning
			unset( $user_data['id'] );

			return $this->format_success_response( $user_data );

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception creating user: ' . $e->getMessage(),
				array( 'user_id' => $user_id ),
				'UserManager'
			);
			return $this->format_error_response( 'Failed to create user', 500 );
		}
	}


	/**
	 * Get user statistics
	 *
	 * @return array User statistics
	 */
	public function get_user_statistics(): array {
		try {
			// Total users
			$total_query = "SELECT COUNT(*) FROM {$this->users_table}";
			$total_users = (int) $this->wpdb->get_var( $total_query );

			// Active users (followed and not unfollowed)
			$active_query = "SELECT COUNT(*) FROM {$this->users_table} WHERE status = 'active'";
			$active_users = (int) $this->wpdb->get_var( $active_query );

			// Users by source type
			$source_query   = "SELECT source_type, COUNT(*) as count FROM {$this->users_table} GROUP BY source_type";
			$source_results = $this->wpdb->get_results( $source_query, ARRAY_A );

			$by_source = array();
			foreach ( $source_results as $row ) {
				$by_source[ $row['source_type'] ] = (int) $row['count'];
			}

			// Recent activity (last 24 hours)
			$recent_query  = "SELECT COUNT(*) FROM {$this->users_table} WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
			$recent_active = (int) $this->wpdb->get_var( $recent_query );

			return $this->format_success_response(
				array(
					'total_users'       => $total_users,
					'active_users'      => $active_users,
					'recent_active_24h' => $recent_active,
					'by_source_type'    => $by_source,
					'generated_at'      => wp_date( 'c' ),
				)
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception getting user statistics: ' . $e->getMessage(),
				array(),
				'UserManager'
			);
			return $this->format_error_response( 'Failed to get user statistics', 500 );
		}
	}

	/**
	 * Search users by display name or user ID
	 *
	 * @param string $search_term Search term
	 * @param int    $limit Results limit
	 * @return array Search results
	 */
	public function search_users( string $search_term, int $limit = 20 ): array {
		try {
			if ( empty( trim( $search_term ) ) ) {
				return $this->format_error_response( 'Search term is required', 400 );
			}

			$limit       = max( 1, min( $limit, 100 ) ); // Ensure reasonable limits
			$search_term = '%' . $this->wpdb->esc_like( sanitize_text_field( $search_term ) ) . '%';

			$query = $this->wpdb->prepare(
				"SELECT line_user_id, display_name, avatar_url, source_type, status, last_active 
				 FROM {$this->users_table} 
				 WHERE display_name LIKE %s OR line_user_id LIKE %s 
				 ORDER BY last_active DESC 
				 LIMIT %d",
				$search_term,
				$search_term,
				$limit
			);

			$results = $this->wpdb->get_results( $query, ARRAY_A );

			if ( $this->wpdb->last_error ) {
				Logger::error(
					'Database error during user search',
					array( 'error' => $this->wpdb->last_error ),
					'UserManager'
				);
				return $this->format_error_response( 'Search failed', 500 );
			}

			return $this->format_success_response(
				array(
					'users'       => $results ?? array(),
					'total_found' => count( $results ?? array() ),
					'search_term' => str_replace( '%', '', $search_term ),
				)
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception during user search: ' . $e->getMessage(),
				array( 'search_term' => $search_term ?? '' ),
				'UserManager'
			);
			return $this->format_error_response( 'Search failed', 500 );
		}
	}
}
