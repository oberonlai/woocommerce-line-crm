<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Util\Logger;

/**
 * Group Manager Class
 *
 * Handles LINE group management including group creation, member management,
 * information synchronization, and integration with LINE API for group data retrieval.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.1.9
 */
class GroupManager extends BaseApiHandler {

	/**
	 * LINE API client instance
	 *
	 * @var LineAPIClient
	 */
	private LineAPIClient $line_api_client;

	/**
	 * User Manager instance
	 *
	 * @var UserManager
	 */
	private UserManager $user_manager;

	/**
	 * Groups table name
	 *
	 * @var string
	 */
	private string $groups_table;

	/**
	 * Group members table name
	 *
	 * @var string
	 */
	private string $group_members_table;

	/**
	 * Constructor
	 *
	 * @param \wpdb                  $wpdb WordPress database object
	 * @param \WC_Logger|null        $logger Logger instance
	 * @param ErrorHandler|null      $error_handler Error handler instance
	 * @param SecurityValidator|null $security_validator Security validator instance
	 * @param LineAPIClient|null     $line_api_client LINE API client instance
	 * @param UserManager|null       $user_manager User manager instance
	 */
	public function __construct(
		\wpdb $wpdb,
		?\WC_Logger $logger = null,
		?ErrorHandler $error_handler = null,
		?SecurityValidator $security_validator = null,
		?LineAPIClient $line_api_client = null,
		?UserManager $user_manager = null
	) {
		parent::__construct( $wpdb, $logger, $error_handler, $security_validator );

		// Initialize LINE API client.
		$this->line_api_client = $line_api_client ?? new LineAPIClient(
			$wpdb,
			$logger,
			$error_handler,
			$security_validator
		);

		// Initialize User Manager.
		$this->user_manager = $user_manager ?? new UserManager(
			$wpdb,
			$logger,
			$error_handler,
			$security_validator,
			$this->line_api_client
		);

		// Set table names.
		$this->groups_table        = $this->wpdb->prefix . 'otz_groups';
		$this->group_members_table = $this->wpdb->prefix . 'otz_group_members';
	}

	/**
	 * Process request (required by BaseApiHandler)
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	protected function process_request( array $request ): array {
		// This is handled by specific group methods.
		return array();
	}

	/**
	 * Validate request data
	 *
	 * @param mixed $request Request data to validate
	 * @return bool True if valid
	 */
	public function validate( $request ): bool {
		return is_array( $request );
	}

	/**
	 * Get required capabilities
	 *
	 * @return array Array of required capabilities
	 */
	public function get_required_capabilities(): array {
		return array( 'manage_options' );
	}

	/**
	 * Get supported HTTP methods
	 *
	 * @return array Array of supported methods
	 */
	public function get_supported_methods(): array {
		return array( 'GET', 'POST', 'PUT', 'DELETE' );
	}

	/**
	 * Create or update group in database
	 *
	 * @param string $group_id LINE group or room ID
	 * @param array  $group_data Group data array
	 * @return array Result array with success status
	 */
	public function create_or_update_group( string $group_id, array $group_data ): array {
		try {
			// Validate group ID.
			if ( empty( $group_id ) ) {
				return $this->format_error_response( 'Group ID is required', 400 );
			}

			// Check if group exists.
			$existing_group = $this->get_group( $group_id );

			$data = array(
				'group_id'          => sanitize_text_field( $group_id ),
				'group_name'        => isset( $group_data['group_name'] ) ? sanitize_text_field( $group_data['group_name'] ) : null,
				'group_avatar'      => isset( $group_data['group_avatar'] ) ? esc_url_raw( $group_data['group_avatar'] ) : null,
				'source_type'       => isset( $group_data['source_type'] ) ? sanitize_text_field( $group_data['source_type'] ) : 'group',
				'member_count'      => isset( $group_data['member_count'] ) ? absint( $group_data['member_count'] ) : 0,
				'last_message_time' => isset( $group_data['last_message_time'] ) ? sanitize_text_field( $group_data['last_message_time'] ) : null,
			);

			if ( $existing_group ) {
				// Update existing group.
				$data['updated_at'] = current_time( 'mysql' );

				$result = $this->wpdb->update(
					$this->groups_table,
					$data,
					array( 'group_id' => $group_id ),
					array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
					array( '%s' )
				);

				if ( false === $result ) {
					Logger::error( 'Failed to update group: ' . $this->wpdb->last_error, array( 'group_id' => $group_id ) );
					return $this->format_error_response( 'Failed to update group', 500 );
				}

				return $this->format_success_response( array( 'group_id' => $group_id, 'action' => 'updated' ) );

			} else {
				// Insert new group.
				$data['created_at'] = current_time( 'mysql' );

				$result = $this->wpdb->insert(
					$this->groups_table,
					$data,
					array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
				);

				if ( false === $result ) {
					Logger::error( 'Failed to create group: ' . $this->wpdb->last_error, array( 'group_id' => $group_id ) );
					return $this->format_error_response( 'Failed to create group', 500 );
				}

				return $this->format_success_response( array( 'group_id' => $group_id, 'action' => 'created' ) );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'Error in create_or_update_group: ' . $e->getMessage(), array( 'group_id' => $group_id ) );
			return $this->format_error_response( 'Failed to save group', 500 );
		}
	}

	/**
	 * Sync group information from LINE API
	 *
	 * @param string $group_id LINE group or room ID
	 * @param string $source_type Source type ('group' or 'room')
	 * @return array Result array with success status
	 */
	public function sync_group_info( string $group_id, string $source_type = 'group' ): array {
		try {
			// Fetch from LINE API based on source type.
			if ( $source_type === 'room' ) {
				$api_response = $this->line_api_client->get_room_summary( $group_id );
			} else {
				$api_response = $this->line_api_client->get_group_summary( $group_id );
			}

			if ( ! $api_response['success'] ) {
				return $api_response;
			}

			$line_data = $api_response['data'];

			// Prepare group data.
			$group_data = array(
				'source_type' => $source_type,
			);

			if ( isset( $line_data['groupName'] ) ) {
				$group_data['group_name'] = $line_data['groupName'];
			}

			if ( isset( $line_data['pictureUrl'] ) ) {
				$group_data['group_avatar'] = $line_data['pictureUrl'];
			}

			// Save to database.
			$result = $this->create_or_update_group( $group_id, $group_data );

			if ( $result['success'] ) {
				// Sync group members.
				$members_result = $this->sync_group_members( $group_id );

				if ( ! $members_result['success'] ) {
					Logger::error( 'Failed to sync group members after group info sync', array( 'group_id' => $group_id ) );
				}
			}

			return $result;

		} catch ( \Exception $e ) {
			Logger::error( 'Error in sync_group_info: ' . $e->getMessage(), array( 'group_id' => $group_id ) );
			return $this->format_error_response( 'Failed to sync group info', 500 );
		}
	}

	/**
	 * Sync group members from LINE API
	 *
	 * @param string $group_id LINE group ID
	 * @return array Result array with success status and member count
	 */
	public function sync_group_members( string $group_id ): array {
		try {
			$all_member_ids = array();
			$start          = null;

			// Fetch all members with pagination.
			do {
				$response = $this->line_api_client->get_group_members( $group_id, $start );

				if ( ! $response['success'] ) {
					return $response;
				}

				$data = $response['data'];

				if ( isset( $data['memberIds'] ) && is_array( $data['memberIds'] ) ) {
					$all_member_ids = array_merge( $all_member_ids, $data['memberIds'] );
				}

				$start = $data['next'] ?? null;

			} while ( $start );

			// Process each member.
			$synced_count = 0;
			foreach ( $all_member_ids as $line_user_id ) {
				$member_result = $this->add_member( $group_id, $line_user_id );

				if ( $member_result['success'] ) {
					++$synced_count;
				}
			}

			// Update group member count.
			$this->wpdb->update(
				$this->groups_table,
				array(
					'member_count' => count( $all_member_ids ),
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'group_id' => $group_id ),
				array( '%d', '%s' ),
				array( '%s' )
			);

			return $this->format_success_response(
				array(
					'group_id'      => $group_id,
					'synced_count'  => $synced_count,
					'total_members' => count( $all_member_ids ),
				)
			);

		} catch ( \Exception $e ) {
			Logger::error( 'Error in sync_group_members: ' . $e->getMessage(), array( 'group_id' => $group_id ) );
			return $this->format_error_response( 'Failed to sync group members', 500 );
		}
	}

	/**
	 * Add member to group
	 *
	 * @param string $group_id LINE group ID
	 * @param string $line_user_id LINE user ID
	 * @return array Result array with success status
	 */
	public function add_member( string $group_id, string $line_user_id ): array {
		try {
			// Check if member already exists.
			$existing_member = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->group_members_table} WHERE group_id = %s AND line_user_id = %s",
					$group_id,
					$line_user_id
				)
			);

			// Get user profile info.
			$user_data = $this->user_manager->ensure_user_exists( $line_user_id, array( 'type' => 'user' ) );

			$member_data = array(
				'group_id'     => sanitize_text_field( $group_id ),
				'line_user_id' => sanitize_text_field( $line_user_id ),
				'display_name' => isset( $user_data['data']['display_name'] ) ? sanitize_text_field( $user_data['data']['display_name'] ) : null,
				'avatar_url'   => isset( $user_data['data']['avatar_url'] ) ? esc_url_raw( $user_data['data']['avatar_url'] ) : null,
			);

			if ( $existing_member ) {
				// Update existing member (mark as active again if left).
				$member_data['left_at']    = null;
				$member_data['updated_at'] = current_time( 'mysql' );

				$result = $this->wpdb->update(
					$this->group_members_table,
					$member_data,
					array(
						'group_id'     => $group_id,
						'line_user_id' => $line_user_id,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s' ),
					array( '%s', '%s' )
				);

				return $this->format_success_response( array( 'action' => 'updated' ) );

			} else {
				// Insert new member.
				$member_data['joined_at']  = current_time( 'mysql' );
				$member_data['created_at'] = current_time( 'mysql' );

				$result = $this->wpdb->insert(
					$this->group_members_table,
					$member_data,
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				if ( false === $result ) {
					Logger::error( 'Failed to add member: ' . $this->wpdb->last_error, array( 'group_id' => $group_id, 'line_user_id' => $line_user_id ) );
					return $this->format_error_response( 'Failed to add member', 500 );
				}

				return $this->format_success_response( array( 'action' => 'created' ) );
			}

		} catch ( \Exception $e ) {
			Logger::error( 'Error in add_member: ' . $e->getMessage(), array( 'group_id' => $group_id, 'line_user_id' => $line_user_id ) );
			return $this->format_error_response( 'Failed to add member', 500 );
		}
	}

	/**
	 * Remove member from group (mark as left)
	 *
	 * @param string $group_id LINE group ID
	 * @param string $line_user_id LINE user ID
	 * @return array Result array with success status
	 */
	public function remove_member( string $group_id, string $line_user_id ): array {
		try {
			$result = $this->wpdb->update(
				$this->group_members_table,
				array(
					'left_at'    => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'group_id'     => $group_id,
					'line_user_id' => $line_user_id,
				),
				array( '%s', '%s' ),
				array( '%s', '%s' )
			);

			if ( false === $result ) {
				Logger::error( 'Failed to remove member: ' . $this->wpdb->last_error, array( 'group_id' => $group_id, 'line_user_id' => $line_user_id ) );
				return $this->format_error_response( 'Failed to remove member', 500 );
			}

			// Update group member count.
			$active_count = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->group_members_table} WHERE group_id = %s AND left_at IS NULL",
					$group_id
				)
			);

			$this->wpdb->update(
				$this->groups_table,
				array(
					'member_count' => intval( $active_count ),
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'group_id' => $group_id ),
				array( '%d', '%s' ),
				array( '%s' )
			);

			return $this->format_success_response( array( 'action' => 'removed' ) );

		} catch ( \Exception $e ) {
			Logger::error( 'Error in remove_member: ' . $e->getMessage(), array( 'group_id' => $group_id, 'line_user_id' => $line_user_id ) );
			return $this->format_error_response( 'Failed to remove member', 500 );
		}
	}

	/**
	 * Get group members
	 *
	 * @param string $group_id LINE group ID
	 * @param bool   $active_only Only get active members (not left)
	 * @return array Array of group members
	 */
	public function get_group_members( string $group_id, bool $active_only = true ): array {
		try {
			$where_clause = 'WHERE group_id = %s';

			if ( $active_only ) {
				$where_clause .= ' AND left_at IS NULL';
			}

			$query = "SELECT * FROM {$this->group_members_table} {$where_clause} ORDER BY joined_at DESC";

			$members = $this->wpdb->get_results(
				$this->wpdb->prepare( $query, $group_id ),
				ARRAY_A
			);

			return $members ? $members : array();

		} catch ( \Exception $e ) {
			Logger::error( 'Error in get_group_members: ' . $e->getMessage(), array( 'group_id' => $group_id ) );
			return array();
		}
	}

	/**
	 * Get groups for a user
	 *
	 * @param string $line_user_id LINE user ID
	 * @param bool   $active_only Only get active groups (user not left)
	 * @return array Array of groups
	 */
	public function get_user_groups( string $line_user_id, bool $active_only = true ): array {
		try {
			$where_clause = 'WHERE gm.line_user_id = %s';

			if ( $active_only ) {
				$where_clause .= ' AND gm.left_at IS NULL';
			}

			$query = "SELECT g.*, gm.joined_at, gm.left_at
					  FROM {$this->groups_table} g
					  INNER JOIN {$this->group_members_table} gm ON g.group_id = gm.group_id
					  {$where_clause}
					  ORDER BY g.last_message_time DESC";

			$groups = $this->wpdb->get_results(
				$this->wpdb->prepare( $query, $line_user_id ),
				ARRAY_A
			);

			return $groups ? $groups : array();

		} catch ( \Exception $e ) {
			Logger::error( 'Error in get_user_groups: ' . $e->getMessage(), array( 'line_user_id' => $line_user_id ) );
			return array();
		}
	}

	/**
	 * Get group by group ID
	 *
	 * @param string $group_id LINE group ID
	 * @return array|null Group data or null if not found
	 */
	public function get_group( string $group_id ): ?array {
		try {
			$group = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->groups_table} WHERE group_id = %s",
					$group_id
				),
				ARRAY_A
			);

			return $group ? $group : null;

		} catch ( \Exception $e ) {
			Logger::error( 'Error in get_group: ' . $e->getMessage(), array( 'group_id' => $group_id ) );
			return null;
		}
	}
}
