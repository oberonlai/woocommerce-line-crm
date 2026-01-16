<?php

declare(strict_types=1);

namespace OrderChatz\API;

use OrderChatz\Util\Logger;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Database\DynamicTableManager;

/**
 * Message Manager Class
 *
 * Handles LINE webhook message storage with dynamic monthly partitioning,
 * idempotency checking, and comprehensive message validation.
 * Integrates with DynamicTableManager for automatic table creation.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */
class MessageManager extends BaseApiHandler {

	/**
	 * Dynamic table manager instance
	 *
	 * @var DynamicTableManager
	 */
	private DynamicTableManager $dynamic_table_manager;

	/**
	 * Supported LINE message types
	 *
	 * @var array
	 */
	private const SUPPORTED_MESSAGE_TYPES = array(
		'text',
		'image',
		'video',
		'audio',
		'file',
		'location',
		'sticker',
		'template',
		'flex',
		'imagemap',
	);

	/**
	 * Maximum message content length for text messages
	 *
	 * @var int
	 */
	private const MAX_TEXT_LENGTH = 5000;

	/**
	 * Constructor
	 *
	 * @param \wpdb                    $wpdb WordPress database object
	 * @param \WC_Logger|null          $logger Logger instance
	 * @param ErrorHandler|null        $error_handler Error handler instance
	 * @param SecurityValidator|null   $security_validator Security validator instance
	 * @param DynamicTableManager|null $dynamic_table_manager Dynamic table manager instance
	 */
	public function __construct(
		\wpdb $wpdb,
		?\WC_Logger $logger = null,
		?ErrorHandler $error_handler = null,
		?SecurityValidator $security_validator = null,
		?DynamicTableManager $dynamic_table_manager = null
	) {
		parent::__construct( $wpdb, $logger, $error_handler, $security_validator );

		// Initialize dynamic table manager
		$this->dynamic_table_manager = $dynamic_table_manager ?? new DynamicTableManager(
			$wpdb,
			$logger,
			$error_handler,
			$security_validator
		);
	}

	/**
	 * Process request (required by BaseApiHandler)
	 *
	 * @param array $request Request data
	 * @return array Response data
	 */
	protected function process_request( array $request ): array {
		// This is handled by specific message methods
		return array();
	}

	/**
	 * Validate request data
	 *
	 * @param array $request Request data to validate
	 * @return bool True if valid
	 */
	public function validate( array $request ): bool {
		// Basic validation for message manager requests
		return is_array( $request ) && ! empty( $request );
	}

	/**
	 * Get required capabilities
	 *
	 * @return array Array of required WordPress capabilities
	 */
	public function get_required_capabilities(): array {
		return array(); // No special capabilities required for message handling
	}

	/**
	 * Get supported HTTP methods
	 *
	 * @return array Array of supported methods
	 */
	public function get_supported_methods(): array {
		return array( 'POST' );
	}

	/**
	 * Store LINE webhook message in dynamic monthly table
	 *
	 * @param array $event_data Complete LINE event data
	 * @return bool True on successful storage, false on failure
	 */
	public function store_message( array $event_data ): bool {
		try {
			// Validate event data structure
			if ( ! $this->validate_message_data( $event_data ) ) {
				Logger::error( 'Invalid message data provided for storage' );
				return false;
			}

			// Extract event ID for idempotency
			$event_id = $event_data['replyToken'] ?? $event_data['webhookEventId'] ?? null;
			if ( ! $event_id ) {
				Logger::error( 'No event ID found in event data for idempotency check' );
				return false;
			}

			// Check idempotency.
			if ( $this->is_event_processed( $event_id ) ) {
				return true; // Return true as this is not an error.
			}

			// Extract timestamp from event data to determine correct table.
			$timestamp = $event_data['timestamp'] ?? null;
			if ( ! $timestamp ) {
				Logger::error( 'No timestamp found in event data' );
				return false;
			}

			// Calculate year_month directly from timestamp to ensure consistency with sent_date field.
			$year_month = wp_date( 'Y_m', (int) $timestamp / 1000 );
			$table_name = $this->dynamic_table_manager->get_monthly_message_table_name( $year_month );
			if ( ! $table_name ) {
				Logger::error( 'Failed to determine table name for message storage' );
				return false;
			}

			// Ensure monthly table exists.
			if ( ! $this->dynamic_table_manager->create_monthly_message_table( $year_month ) ) {
				Logger::error(
					'Failed to create monthly message table',
					array(
						'table_name' => $table_name,
						'year_month' => $year_month,
					)
				);
				return false;
			}

			// Prepare message data for insertion
			$message_data = $this->prepare_message_data( $event_data, $event_id );
			if ( ! $message_data ) {
				Logger::error( 'Failed to prepare message data for storage' );
				return false;
			}

			// Insert message into table
			$result = $this->wpdb->insert(
				$table_name,
				$message_data,
				$this->get_insert_format()
			);

			if ( false === $result ) {
				Logger::error(
					'Database insert failed for message',
					array(
						'table_name' => $table_name,
						'error'      => $this->wpdb->last_error,
						'event_id'   => $event_id,
					)
				);
				return false;
			}

			// Mark event as processed for idempotency
			$this->mark_event_processed( $event_id );

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception during message storage: ' . $e->getMessage(),
				array(
					'event_id' => $event_id ?? 'unknown',
					'trace'    => $e->getTraceAsString(),
				)
			);
			return false;
		}
	}

	/**
	 * Check if event has already been processed (idempotency check)
	 *
	 * @param string $event_id LINE event ID
	 * @return bool True if already processed, false otherwise
	 */
	public function is_event_processed( string $event_id ): bool {
		try {
			// Get current and previous month tables to check
			$current_month  = wp_date( 'Y_m' );
			$previous_month = wp_date( 'Y_m', strtotime( '-1 month' ) );

			$tables_to_check = array( $current_month );
			if ( $previous_month !== $current_month ) {
				$tables_to_check[] = $previous_month;
			}

			foreach ( $tables_to_check as $year_month ) {
				// Check if table exists first
				if ( ! $this->dynamic_table_manager->monthly_message_table_exists( $year_month ) ) {
					continue;
				}

				$table_name = $this->dynamic_table_manager->get_monthly_message_table_name( $year_month );
				if ( ! $table_name ) {
					continue;
				}

				// Check for existing event
				$query = $this->wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE event_id = %s",
					$event_id
				);

				$count = $this->wpdb->get_var( $query );

				if ( $this->wpdb->last_error ) {
					Logger::error(
						'Database error during idempotency check',
						array(
							'error'      => $this->wpdb->last_error,
							'table_name' => $table_name,
							'event_id'   => $event_id,
						)
					);
					continue;
				}

				if ( $count > 0 ) {
					return true;
				}
			}

			return false;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception during idempotency check: ' . $e->getMessage(),
				array( 'event_id' => $event_id )
			);
			return false;
		}
	}

	/**
	 * Mark event as processed for idempotency
	 *
	 * @param string $event_id LINE event ID
	 * @return bool True on success, false on failure
	 */
	public function mark_event_processed( string $event_id ): bool {
		try {
			// Store in WordPress transients for quick lookup (24 hours)
			$transient_key = 'otz_event_processed_' . md5( $event_id );
			set_transient( $transient_key, true, DAY_IN_SECONDS );

			return true;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception marking event as processed: ' . $e->getMessage(),
				array( 'event_id' => $event_id )
			);
			return false;
		}
	}

	/**
	 * Validate message data structure and content
	 *
	 * @param array $data Event data to validate
	 * @return bool True if valid, false otherwise
	 */
	public function validate_message_data( array $data ): bool {
		// Check required top-level fields
		$required_fields = array( 'type', 'timestamp' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				Logger::error( "Required field missing: {$field}" );
				return false;
			}
		}

		// Validate event type
		if ( ! in_array( $data['type'], array( 'message', 'follow', 'unfollow', 'join', 'leave', 'memberJoined', 'memberLeft', 'postback' ), true ) ) {
			Logger::error( "Unsupported event type: {$data['type']}" );
			return false;
		}

		// Validate source information
		if ( ! isset( $data['source'] ) || ! is_array( $data['source'] ) ) {
			Logger::error( 'Invalid or missing source information' );
			return false;
		}

		$source = $data['source'];
		if ( ! isset( $source['type'] ) || ! in_array( $source['type'], array( 'user', 'group', 'room' ), true ) ) {
			Logger::error( 'Invalid source type: ' . ( $source['type'] ?? 'missing' ) );
			return false;
		}

		if ( ! isset( $source['userId'] ) || empty( $source['userId'] ) ) {
			Logger::error( 'Missing userId in source' );
			return false;
		}

		// Validate message-specific data
		if ( $data['type'] === 'message' ) {
			if ( ! isset( $data['message'] ) || ! is_array( $data['message'] ) ) {
				Logger::error( 'Invalid or missing message data' );
				return false;
			}

			$message = $data['message'];
			if ( ! isset( $message['type'] ) || ! in_array( $message['type'], self::SUPPORTED_MESSAGE_TYPES, true ) ) {
				Logger::error( 'Unsupported message type: ' . ( $message['type'] ?? 'missing' ) );
				return false;
			}

			// Validate text message content length
			if ( $message['type'] === 'text' ) {
				if ( ! isset( $message['text'] ) || strlen( $message['text'] ) > self::MAX_TEXT_LENGTH ) {
					Logger::error( 'Text message content invalid or too long' );
					return false;
				}
			}
		}

		// Validate timestamp format
		$timestamp = $data['timestamp'];
		if ( ! is_numeric( $timestamp ) || $timestamp < 0 ) {
			Logger::error( "Invalid timestamp format: {$timestamp}" );
			return false;
		}

		return true;
	}

	/**
	 * Get appropriate table name for given date
	 *
	 * @param string $date Date in Y-m-d H:i:s format
	 * @return string|false Table name on success, false on failure
	 */
	public function get_table_for_date( string $date ) {
		try {
			// Convert date to year_month format
			$year_month = wp_date( 'Y_m', strtotime( $date ) );
			if ( ! $year_month ) {
				Logger::error( "Invalid date format provided: {$date}" );
				return false;
			}

			return $this->dynamic_table_manager->get_monthly_message_table_name( $year_month );

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception getting table for date: ' . $e->getMessage(),
				array( 'date' => $date )
			);
			return false;
		}
	}

	/**
	 * Prepare message data for database insertion
	 *
	 * @param array  $event_data Complete event data
	 * @param string $event_id Event ID for idempotency
	 * @return array|false Prepared data array on success, false on failure
	 */
	private function prepare_message_data( array $event_data, string $event_id ) {
		try {
			$source  = $event_data['source'];
			$message = $event_data['message'] ?? null;

			$data = array(
				'event_id'          => sanitize_text_field( $event_id ),
				'line_user_id'      => sanitize_text_field( $source['userId'] ),
				'source_type'       => sanitize_text_field( $source['type'] ),
				'sender_type'       => 'user',
				'sender_name'       => null,
				'group_id'          => isset( $source['groupId'] ) ? sanitize_text_field( $source['groupId'] ) : null,
				'sent_date'         => wp_date( 'Y-m-d', (int) $event_data['timestamp'] / 1000 ),
				'sent_time'         => wp_date( 'H:i:s', (int) $event_data['timestamp'] / 1000 ),
				'reply_token'       => isset( $event_data['replyToken'] ) ? sanitize_text_field( $event_data['replyToken'] ) : null,
				'quote_token'       => $this->extract_quote_token( $event_data ),
				'quoted_message_id' => $this->extract_quoted_message_id( $event_data ),
				'line_message_id'   => $this->extract_line_message_id( $event_data ),
				'raw_payload'       => wp_json_encode( $event_data ),
				'created_at'        => wp_date( 'Y-m-d H:i:s' ),
			);

			// Handle room ID for room sources
			if ( isset( $source['roomId'] ) ) {
				$data['group_id'] = sanitize_text_field( $source['roomId'] );
			}

			// Add message-specific data
			if ( $message ) {
				$data['message_type'] = sanitize_text_field( $message['type'] );

				// Handle different message types - use message_content field
				switch ( $message['type'] ) {
					case 'text':
						// Process emoji if present
						$text_content = $message['text'];
						$has_emojis   = isset( $message['emojis'] ) && is_array( $message['emojis'] ) && count( $message['emojis'] ) > 0;

						if ( $has_emojis ) {
							$text_content = $this->process_line_emojis( $text_content, $message['emojis'] );
							// For text with LINE emojis, use wp_kses to allow safe HTML (img tags for emojis)
							$data['message_content'] = wp_kses(
								$text_content,
								array(
									'img' => array(
										'src'     => array(),
										'alt'     => array(),
										'class'   => array(),
										'style'   => array(),
										'data-fallback-urls' => array(),
										'onerror' => array(),
									),
								)
							);
						} else {
							// For plain text, use standard sanitization
							$data['message_content'] = wp_kses_post( $text_content );
						}
						break;

					case 'video':
						// Handle video messages with unified format
						$content_provider = $message['contentProvider'] ?? array();
						$message_id       = sanitize_text_field( $message['id'] ?? '' );

						// Get URLs from contentProvider or message
						$original_url = sanitize_url( $content_provider['originalContentUrl'] ?? $message['originalContentUrl'] ?? '' );
						$preview_url  = sanitize_url( $content_provider['previewImageUrl'] ?? $message['previewImageUrl'] ?? '' );

						// If contentProvider type is 'line' and no direct URLs, download and store the content
						if ( $content_provider['type'] === 'line' && empty( $original_url ) && empty( $preview_url ) && ! empty( $message_id ) ) {
							$stored_urls = $this->download_and_store_line_content( $message_id, $message['type'] );
							if ( $stored_urls ) {
								$original_url = $stored_urls['original_url'];
								$preview_url  = $stored_urls['preview_url'];
							}
						}

						// Extract filename from URL
						$filename   = basename( parse_url( $original_url, PHP_URL_PATH ) );
						$video_name = ! empty( $filename ) ? $filename : "video_{$message_id}.mp4";

						// Use unified format for video messages
						$data['message_content'] = wp_json_encode(
							array(
								'video_url'   => $original_url,
								'preview_url' => $preview_url,
								'video_name'  => $video_name,
							)
						);
						break;

					case 'image':
					case 'audio':
					case 'file':
						// Handle LINE API structure where contentProvider contains URLs
						$content_provider = $message['contentProvider'] ?? array();
						$message_id       = sanitize_text_field( $message['id'] ?? '' );

						// Get URLs from contentProvider or message
						$original_url = sanitize_url( $content_provider['originalContentUrl'] ?? $message['originalContentUrl'] ?? '' );
						$preview_url  = sanitize_url( $content_provider['previewImageUrl'] ?? $message['previewImageUrl'] ?? '' );

						// If contentProvider type is 'line' and no direct URLs, download and store the content
						if ( $content_provider['type'] === 'line' && empty( $original_url ) && empty( $preview_url ) && ! empty( $message_id ) ) {
							$stored_urls = $this->download_and_store_line_content( $message_id, $message['type'] );
							if ( $stored_urls ) {
								$original_url = $stored_urls['original_url'];
								$preview_url  = $stored_urls['preview_url'];
							}
						}

						$data['message_content'] = wp_json_encode(
							array(
								'id'                 => $message_id,
								'contentProvider'    => array(
									'type'               => sanitize_text_field( $content_provider['type'] ?? 'line' ),
									'originalContentUrl' => $original_url,
									'previewImageUrl'    => $preview_url,
								),
								// Also store direct properties for backward compatibility
								'originalContentUrl' => $original_url,
								'previewImageUrl'    => $preview_url,
								'fileName'           => sanitize_text_field( $message['fileName'] ?? '' ),
								'fileSize'           => absint( $message['fileSize'] ?? 0 ),
								'duration'           => absint( $message['duration'] ?? 0 ),
							)
						);
						break;

					case 'location':
						$data['message_content'] = wp_json_encode(
							array(
								'title'     => sanitize_text_field( $message['title'] ?? '' ),
								'address'   => sanitize_text_field( $message['address'] ?? '' ),
								'latitude'  => $message['latitude'] ?? null,
								'longitude' => $message['longitude'] ?? null,
							)
						);
						break;

					case 'sticker':
						$data['message_content'] = wp_json_encode(
							array(
								'packageId' => sanitize_text_field( $message['packageId'] ?? '' ),
								'stickerId' => sanitize_text_field( $message['stickerId'] ?? '' ),
							)
						);
						break;

					default:
						// For complex message types (template, flex, imagemap), store as JSON
						$data['message_content'] = wp_json_encode( $message );
						break;
				}
			}

			return $data;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception preparing message data: ' . $e->getMessage(),
				array( 'event_id' => $event_id )
			);
			return false;
		}
	}

	/**
	 * Extract quote token from event data for LINE reply functionality
	 *
	 * @param array $event_data Complete event data from LINE webhook
	 * @return string|null Quote token if found, null otherwise
	 */
	private function extract_quote_token( array $event_data ): ?string {
		// Check for quote token in message data.
		if ( isset( $event_data['message']['quoteToken'] ) ) {
			return sanitize_text_field( $event_data['message']['quoteToken'] );
		}

		// Check for quote token at event level (alternative structure).
		if ( isset( $event_data['quoteToken'] ) ) {
			return sanitize_text_field( $event_data['quoteToken'] );
		}

		return null;
	}

	/**
	 * Extract quoted message ID from event data for LINE reply functionality
	 *
	 * @param array $event_data Complete event data from LINE webhook
	 * @return string|null Quoted message ID if found, null otherwise
	 */
	private function extract_quoted_message_id( array $event_data ): ?string {
		// Check for quoted message in message data.
		if ( isset( $event_data['message']['quotedMessageId'] ) ) {
			return sanitize_text_field( $event_data['message']['quotedMessageId'] );
		}

		// Alternative field name structures.
		if ( isset( $event_data['message']['quoted_message_id'] ) ) {
			return sanitize_text_field( $event_data['message']['quoted_message_id'] );
		}

		if ( isset( $event_data['quotedMessageId'] ) ) {
			return sanitize_text_field( $event_data['quotedMessageId'] );
		}

		return null;
	}

	/**
	 * Extract LINE message ID from event data for message correlation
	 *
	 * @param array $event_data Complete event data from LINE webhook
	 * @return string|null LINE message ID if found, null otherwise
	 */
	private function extract_line_message_id( array $event_data ): ?string {
		// Check for message ID in message data.
		if ( isset( $event_data['message']['id'] ) ) {
			return sanitize_text_field( $event_data['message']['id'] );
		}

		// Alternative field name structures.
		if ( isset( $event_data['messageId'] ) ) {
			return sanitize_text_field( $event_data['messageId'] );
		}

		return null;
	}

	/**
	 * Get database insert format array
	 *
	 * @return array Format specifiers for wpdb::insert
	 */
	private function get_insert_format(): array {
		return array(
			'%s', // event_id
			'%s', // line_user_id
			'%s', // source_type
			'%s', // sender_type
			'%s', // sender_name
			'%s', // group_id
			'%s', // sent_date
			'%s', // sent_time
			'%s', // reply_token
			'%s', // quote_token
			'%s', // quoted_message_id
			'%s', // line_message_id
			'%s', // raw_payload
			'%s', // created_at
			'%s', // message_type
			'%s',  // message_content
		);
	}

	/**
	 * Extract year_month from table name
	 *
	 * @param string $table_name Full table name
	 * @return string Year_month string (e.g., '2025_08')
	 */
	private function extract_year_month_from_table( string $table_name ): string {
		$prefix = $this->wpdb->prefix . 'otz_messages_';
		return str_replace( $prefix, '', $table_name );
	}

	/**
	 * Get message statistics for a given month
	 *
	 * @param string $year_month Year-month in YYYY_MM format
	 * @return array Statistics data
	 */
	public function get_monthly_statistics( string $year_month ): array {
		try {
			// Validate year_month format
			if ( ! $this->dynamic_table_manager->validate_year_month_format( $year_month ) ) {
				return $this->format_error_response( 'Invalid year_month format', 400 );
			}

			// Check if table exists
			if ( ! $this->dynamic_table_manager->monthly_message_table_exists( $year_month ) ) {
				return $this->format_success_response(
					array(
						'total_messages' => 0,
						'unique_users'   => 0,
						'message_types'  => array(),
						'period'         => $year_month,
					)
				);
			}

			$table_name = $this->dynamic_table_manager->get_monthly_message_table_name( $year_month );

			// Get total message count
			$total_query    = "SELECT COUNT(*) FROM {$table_name}";
			$total_messages = (int) $this->wpdb->get_var( $total_query );

			// Get unique user count
			$users_query  = "SELECT COUNT(DISTINCT user_id) FROM {$table_name}";
			$unique_users = (int) $this->wpdb->get_var( $users_query );

			// Get message type distribution
			$types_query   = "SELECT message_type, COUNT(*) as count FROM {$table_name} WHERE message_type IS NOT NULL GROUP BY message_type";
			$types_results = $this->wpdb->get_results( $types_query, ARRAY_A );

			$message_types = array();
			foreach ( $types_results as $row ) {
				$message_types[ $row['message_type'] ] = (int) $row['count'];
			}

			return $this->format_success_response(
				array(
					'total_messages' => $total_messages,
					'unique_users'   => $unique_users,
					'message_types'  => $message_types,
					'period'         => $year_month,
				)
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception getting monthly statistics: ' . $e->getMessage(),
				array( 'year_month' => $year_month )
			);
			return $this->format_error_response( 'Failed to retrieve statistics', 500 );
		}
	}

	/**
	 * Get recent messages for a specific user
	 *
	 * @param string $user_id LINE user ID
	 * @param int    $limit Number of messages to retrieve
	 * @return array Message data array
	 */
	public function get_user_recent_messages( string $user_id, int $limit = 10 ): array {
		try {
			// Validate parameters
			if ( empty( $user_id ) || $limit < 1 || $limit > 100 ) {
				return $this->format_error_response( 'Invalid parameters', 400 );
			}

			$limit    = min( $limit, 100 ); // Cap at 100 messages
			$messages = array();

			// Get recent monthly tables (current and previous month)
			$tables = $this->dynamic_table_manager->get_existing_monthly_tables();

			// Limit to recent tables to avoid performance issues
			$tables = array_slice( $tables, 0, 3 );

			foreach ( $tables as $year_month ) {
				$table_name = $this->dynamic_table_manager->get_monthly_message_table_name( $year_month );
				if ( ! $table_name ) {
					continue;
				}

				$query = $this->wpdb->prepare(
					"SELECT event_id, message_type, content, timestamp, created_at 
					 FROM {$table_name} 
					 WHERE user_id = %s AND message_type IS NOT NULL 
					 ORDER BY timestamp DESC 
					 LIMIT %d",
					$user_id,
					$limit - count( $messages )
				);

				$results = $this->wpdb->get_results( $query, ARRAY_A );

				if ( $results ) {
					$messages = array_merge( $messages, $results );
				}

				// Stop if we have enough messages
				if ( count( $messages ) >= $limit ) {
					break;
				}
			}

			// Sort by timestamp desc and limit
			usort(
				$messages,
				function( $a, $b ) {
					return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
				}
			);

			$messages = array_slice( $messages, 0, $limit );

			return $this->format_success_response(
				array(
					'user_id'     => $user_id,
					'messages'    => $messages,
					'total_found' => count( $messages ),
				)
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception getting user recent messages: ' . $e->getMessage(),
				array( 'user_id' => $user_id )
			);
			return $this->format_error_response( 'Failed to retrieve user messages', 500 );
		}
	}

	/**
	 * Download and store LINE content locally
	 *
	 * @param string $message_id LINE message ID
	 * @param string $content_type Content type (image, video, audio, file)
	 * @return array|null Array with original_url and preview_url, or null on failure
	 */
	private function download_and_store_line_content( string $message_id, string $content_type ): ?array {
		try {
			// Initialize LINE API client
			$line_api_client = new \OrderChatz\API\LineAPIClient( $this->wpdb );

			$stored_urls = array();

			// For images and videos, get preview first (smaller size for chat display)
			if ( in_array( $content_type, array( 'image', 'video' ) ) ) {
				$preview_result = $line_api_client->get_message_preview( $message_id );

				if ( $preview_result['success'] ) {
					$preview_data = $preview_result['data'];

					// Determine file extension from content type
					$extension = $this->get_file_extension_from_mime( $preview_data['content_type'] );
					$filename  = "line_preview_{$message_id}.{$extension}";

					// Upload to WordPress custom directory
					$upload = $this->custom_upload_bits( $filename, $preview_data['content'] );

					if ( ! $upload['error'] ) {
						$stored_urls['preview_url'] = $upload['url'];
					} else {
						Logger::error(
							'Failed to upload LINE preview',
							array(
								'message_id' => $message_id,
								'error'      => $upload['error'],
							)
						);
					}
				}
			}

			// Always get the original content for full-size lightbox display
			if ( true ) { // Changed condition to always download original
				$content_result = $line_api_client->get_message_content( $message_id );

				if ( $content_result['success'] ) {
					$content_data = $content_result['data'];

					// Determine file extension from content type
					$extension = $this->get_file_extension_from_mime( $content_data['content_type'] );
					$filename  = "line_original_{$message_id}.{$extension}";

					// Upload to WordPress custom directory
					$upload = $this->custom_upload_bits( $filename, $content_data['content'] );

					if ( ! $upload['error'] ) {
						$stored_urls['original_url'] = $upload['url'];

						// If we don't have preview URL yet, use original as fallback
						if ( empty( $stored_urls['preview_url'] ) ) {
							$stored_urls['preview_url'] = $upload['url'];
						}
					} else {
						Logger::error(
							'Failed to upload LINE original content',
							array(
								'message_id' => $message_id,
								'error'      => $upload['error'],
							)
						);
					}
				}
			}

			return ! empty( $stored_urls ) ? $stored_urls : null;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception downloading LINE content',
				array(
					'message_id' => $message_id,
					'exception'  => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * Get file extension from MIME type
	 *
	 * @param string $mime_type MIME type
	 * @return string File extension
	 */
	private function get_file_extension_from_mime( string $mime_type ): string {
		$mime_to_ext = array(
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'image/gif'       => 'gif',
			'image/webp'      => 'webp',
			'video/mp4'       => 'mp4',
			'video/mpeg'      => 'mpeg',
			'audio/mp3'       => 'mp3',
			'audio/mpeg'      => 'mp3',
			'audio/wav'       => 'wav',
			'application/pdf' => 'pdf',
			'text/plain'      => 'txt',
		);

		return $mime_to_ext[ $mime_type ] ?? 'bin';
	}

	/**
	 * Process LINE emojis in text message content
	 *
	 * @param string $text Original text content with emoji placeholders
	 * @param array  $emojis Array of emoji data from LINE webhook
	 * @return string Processed text with emoji HTML
	 */
	private function process_line_emojis( string $text, array $emojis ): string {
		try {
			Logger::info(
				'Processing LINE emojis',
				array(
					'original_text' => $text,
					'emojis_count'  => count( $emojis ),
					'emojis_data'   => $emojis,
				)
			);

			// Sort emojis by index in descending order to avoid index shifting during replacement
			usort(
				$emojis,
				function( $a, $b ) {
					return $b['index'] - $a['index'];
				}
			);

			$processed_positions = array();
			foreach ( $emojis as $emoji_data ) {
				$index      = $emoji_data['index'] ?? 0;
				$length     = $emoji_data['length'] ?? 0;
				$product_id = $emoji_data['productId'] ?? '';
				$emoji_id   = $emoji_data['emojiId'] ?? '';

				if ( $length > 0 && ! empty( $product_id ) && ! empty( $emoji_id ) ) {
					// Get the placeholder text to replace
					$placeholder = mb_substr( $text, $index, $length );

					// Create LINE emoji HTML with multiple URL fallbacks and improved error handling
					$emoji_urls = array(
						"https://stickershop.line-scdn.net/sticonshop/v1/sticon/{$product_id}/android/{$emoji_id}.png",
						"https://stickershop.line-scdn.net/sticonshop/v1/sticon/{$product_id}/iPhone/{$emoji_id}@2x.png",
						"https://dl.stickershop.line.naver.jp/sticonshop/v1/sticon/{$product_id}/android/{$emoji_id}.png",
					);

					$primary_url   = $emoji_urls[0];
					$fallback_urls = implode( ',', array_slice( $emoji_urls, 1 ) );

					$emoji_html = "<img src=\"{$primary_url}\" alt=\"{$placeholder}\" class=\"line-emoji\" data-fallback-urls=\"{$fallback_urls}\" style=\"width: 20px; height: 20px; vertical-align: middle; display: inline-block;\" onerror=\"this.style.display='none'; this.parentNode.insertBefore(document.createTextNode(this.alt), this.nextSibling);\" />";

					// Replace the placeholder with emoji HTML
					$text                  = mb_substr( $text, 0, $index ) . $emoji_html . mb_substr( $text, $index + $length );
					$processed_positions[] = array(
						'start' => $index,
						'end'   => $index + $length,
					);
				}
			}

			// Handle unprocessed emoji patterns that LINE API may not include
			$text = $this->process_fallback_emojis( $text );

			return $text;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception processing LINE emojis: ' . $e->getMessage(),
				array(
					'text'   => $text,
					'emojis' => $emojis,
				)
			);
			return $text; // Return original text on error
		}
	}

	/**
	 * Process fallback emoji patterns for emojis not provided by LINE API
	 *
	 * @param string $text Text that may contain unprocessed emoji patterns
	 * @return string Text with fallback emoji processing
	 */
	private function process_fallback_emojis( string $text ): string {
		try {
			// Common LINE emoji patterns that may not be included in API response
			$fallback_emojis = array(
				'(hello)'       => '👋',
				'(love)'        => '❤️',
				'(smile)'       => '😊',
				'(happy)'       => '😀',
				'(sad)'         => '😢',
				'(angry)'       => '😠',
				'(surprised)'   => '😲',
				'(wink)'        => '😉',
				'(cry)'         => '😭',
				'(laugh)'       => '😂',
				'(cool)'        => '😎',
				'(kiss)'        => '😘',
				'(heart)'       => '💖',
				'(thumbs_up)'   => '👍',
				'(thumbs_down)' => '👎',
				'(ok)'          => '👌',
				'(peace)'       => '✌️',
				'(clap)'        => '👏',
				'(fire)'        => '🔥',
			);

			// Replace emoji patterns with Unicode emojis as fallback
			foreach ( $fallback_emojis as $pattern => $emoji ) {
				if ( strpos( $text, $pattern ) !== false ) {
					Logger::info(
						'Applying fallback emoji',
						array(
							'pattern' => $pattern,
							'emoji'   => $emoji,
						)
					);
					$text = str_replace( $pattern, $emoji, $text );
				}
			}

			// Look for any remaining unprocessed emoji patterns
			// Strip HTML tags to avoid false positives from img alt attributes.
			$plain_text = wp_strip_all_tags( $text );
			if ( preg_match_all( '/\([a-zA-Z_]+\)/', $plain_text, $matches ) ) {
				Logger::warning(
					'Unprocessed emoji patterns found',
					array(
						'patterns' => $matches[0],
						'text'     => $plain_text,
					)
				);
			}

			return $text;

		} catch ( \Exception $e ) {
			Logger::error(
				'Exception in fallback emoji processing: ' . $e->getMessage(),
				array( 'text' => $text )
			);
			return $text;
		}
	}

	/**
	 * Custom upload function that saves files to order-chatz subdirectory
	 *
	 * @param string $filename The filename
	 * @param string $bits     The file content
	 * @return array Array with 'file', 'url', 'type', and 'error' keys
	 */
	private function custom_upload_bits( string $filename, string $bits ): array {
		$upload_dir = wp_upload_dir();
		$custom_dir = $upload_dir['basedir'] . '/order-chatz/line-content/';
		$custom_url = $upload_dir['baseurl'] . '/order-chatz/line-content/';

		// Create directory if it doesn't exist
		if ( ! file_exists( $custom_dir ) ) {
			wp_mkdir_p( $custom_dir );
		}

		// Generate unique filename if file exists
		$file_path = $custom_dir . $filename;
		$counter   = 1;
		$name      = pathinfo( $filename, PATHINFO_FILENAME );
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		while ( file_exists( $file_path ) ) {
			$filename  = $name . '_' . $counter . '.' . $extension;
			$file_path = $custom_dir . $filename;
			$counter++;
		}

		// Write file
		$result = file_put_contents( $file_path, $bits );

		if ( $result === false ) {
			return array(
				'file'  => '',
				'url'   => '',
				'type'  => '',
				'error' => 'Could not write file ' . $file_path,
			);
		}

		// Set proper file permissions
		chmod( $file_path, 0644 );

		return array(
			'file'  => $file_path,
			'url'   => $custom_url . $filename,
			'type'  => wp_check_filetype( $filename )['type'],
			'error' => false,
		);
	}
}
