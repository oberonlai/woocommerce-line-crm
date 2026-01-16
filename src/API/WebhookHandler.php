<?php

/**
 * WebhookHandler - LINE Webhook Core Processing System
 *
 * This class handles incoming LINE webhook requests, coordinates all processing
 * components including signature verification, message storage, user management,
 * and provides comprehensive error handling and logging.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */

namespace OrderChatz\API;

use OrderChatz\Util\Logger;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;
use OrderChatz\Services\UnreadCountService;
use OrderChatz\Services\WebPushService;
use OrderChatz\Services\Bot\BotResponder;
use OrderChatz\Database\Bot\Bot;
use OrderChatz\Database\User;
use OrderChatz\Database\Message\TableMessage;

/**
 * WebhookHandler class
 *
 * Main entry point for processing LINE webhook events. Orchestrates the entire
 * webhook processing pipeline including security verification, event processing,
 * and response generation.
 */
class WebhookHandler {

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
	 * Signature verifier instance
	 *
	 * @var SignatureVerifier
	 */
	private SignatureVerifier $signature_verifier;

	/**
	 * Message manager instance
	 *
	 * @var MessageManager
	 */
	private MessageManager $message_manager;

	/**
	 * User manager instance
	 *
	 * @var UserManager
	 */
	private UserManager $user_manager;

	/**
	 * Group manager instance
	 *
	 * @var GroupManager
	 */
	private GroupManager $group_manager;

	/**
	 * Web push service instance
	 *
	 * @var WebPushService
	 */
	private WebPushService $web_push_service;

	/**
	 * Supported LINE event types
	 */
	private const SUPPORTED_EVENT_TYPES = array(
		'message',
		'follow',
		'unfollow',
		'join',
		'leave',
		'memberJoined',
		'memberLeft',
		'postback',
		'beacon',
	);

	/**
	 * Event processing timeout (seconds)
	 */
	private const PROCESSING_TIMEOUT = 25;

	/**
	 * Constructor
	 *
	 * @param \wpdb             $wpdb               WordPress database instance
	 * @param \WC_Logger|null   $logger             Logger instance
	 * @param ErrorHandler      $error_handler      Error handler instance
	 * @param SecurityValidator $security_validator Security validator instance
	 * @param SignatureVerifier $signature_verifier Signature verifier instance
	 * @param MessageManager    $message_manager    Message manager instance
	 * @param UserManager       $user_manager       User manager instance
	 * @param GroupManager      $group_manager      Group manager instance
	 * @param WebPushService    $web_push_service   Web push service instance
	 */
	public function __construct(
		\wpdb $wpdb,
		?\WC_Logger $logger,
		ErrorHandler $error_handler,
		SecurityValidator $security_validator,
		SignatureVerifier $signature_verifier,
		MessageManager $message_manager,
		UserManager $user_manager,
		GroupManager $group_manager,
		WebPushService $web_push_service
	) {
		$this->wpdb               = $wpdb;
		$this->logger             = $logger;
		$this->error_handler      = $error_handler;
		$this->security_validator = $security_validator;
		$this->signature_verifier = $signature_verifier;
		$this->message_manager    = $message_manager;
		$this->user_manager       = $user_manager;
		$this->group_manager      = $group_manager;
		$this->web_push_service   = $web_push_service;
	}

	/**
	 * Handle incoming LINE webhook request
	 *
	 * This is the main entry point for processing LINE webhook events.
	 * It performs comprehensive security checks, validates the request format,
	 * processes events, and returns appropriate HTTP responses.
	 *
	 * @param \WP_REST_Request $request WordPress REST API request object
	 *
	 * @return \WP_REST_Response WordPress REST API response
	 */
	public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		$start_time = microtime( true );

		// Fire hook for webhook request received.
		do_action( 'otz_webhook_request_received', $request );

		try {
			// Set processing timeout
			set_time_limit( self::PROCESSING_TIMEOUT );

			// Extract request data
			$request_body = $request->get_body();
			$signature    = $request->get_header( 'x-line-signature' );

			// Step 1: Security validation
			$security_result = $this->validate_webhook_security( $request_body, $signature );

			if ( ! $security_result['valid'] ) {
				return $this->create_error_response(
					$security_result['code'],
					$security_result['message'],
					$security_result['http_code']
				);
			}

			// Step 2: Parse and validate JSON payload.
			$webhook_data = $this->parse_webhook_payload( $request_body );
			if ( ! $webhook_data['valid'] ) {
				return $this->create_error_response(
					$webhook_data['code'],
					$webhook_data['message'],
					400
				);
			}

			// Step 3: Process webhook events.
			$events             = $webhook_data['data']['events'] ?? array();
			$processing_results = $this->process_webhook_events( $events );

			// Step 4: Generate response.
			$processing_time = microtime( true ) - $start_time;

			$response = new \WP_REST_Response(
				array(
					'success'            => true,
					'processed'          => $processing_results['successful'],
					'failed'             => $processing_results['failed'],
					'processing_time_ms' => round( $processing_time * 1000, 2 ),
				),
				200
			);

			// Fire hook for webhook request processed.
			do_action( 'otz_webhook_request_processed', $request, $response );

			return $response;

		} catch ( \Exception $e ) {
			$this->handle_webhook_exception( $e, $request );

			return $this->create_error_response(
				'internal_error',
				'Internal server error occurred',
				500
			);
		}
	}

	/**
	 * Validate webhook security including signature verification
	 *
	 * @param string $request_body Raw request body
	 * @param string $signature    X-Line-Signature header value
	 *
	 * @return array {
	 *     Validation result
	 *
	 *     @type bool   $valid     Whether validation passed
	 *     @type string $code      Error code if validation failed
	 *     @type string $message   Error message if validation failed
	 *     @type int    $http_code HTTP status code for response
	 * }
	 */
	private function validate_webhook_security( string $request_body, ?string $signature ): array {
		try {
			// Check for missing signature header
			if ( empty( $signature ) ) {
				Logger::error(
					'Missing X-Line-Signature header',
					array(
						'remote_ip' => $this->get_client_ip(),
					)
				);

				return array(
					'valid'     => false,
					'code'      => 'missing_signature',
					'message'   => 'Missing X-Line-Signature header',
					'http_code' => 400,
				);
			}

			// Get channel secret
			$channel_secret = get_option( 'otz_channel_secret', '' );
			if ( empty( $channel_secret ) ) {
				Logger::error( 'Channel secret not configured' );

				return array(
					'valid'     => false,
					'code'      => 'configuration_error',
					'message'   => 'Server configuration error',
					'http_code' => 500,
				);
			}

			// Verify signature
			if ( ! $this->signature_verifier->verify_signature( $request_body, $signature, $channel_secret ) ) {
				Logger::error(
					'Signature verification failed',
					array(
						'signature'   => substr( $signature, 0, 20 ) . '...',
						'body_length' => strlen( $request_body ),
						'remote_ip'   => $this->get_client_ip(),
					)
				);

				return array(
					'valid'     => false,
					'code'      => 'invalid_signature',
					'message'   => 'Invalid signature',
					'http_code' => 401,
				);
			}

			return array(
				'valid' => true,
			);

		} catch ( \Exception $e ) {
			$this->error_handler->handle_error(
				'webhook_security_validation_failed',
				'Security validation process failed',
				array( 'exception' => $e->getMessage() ),
				'WebhookHandler::validate_webhook_security'
			);

			return array(
				'valid'     => false,
				'code'      => 'security_error',
				'message'   => 'Security validation failed',
				'http_code' => 500,
			);
		}
	}

	/**
	 * Parse and validate webhook JSON payload
	 *
	 * @param string $request_body Raw request body
	 *
	 * @return array {
	 *     Parsing result
	 *
	 *     @type bool  $valid   Whether parsing was successful
	 *     @type array $data    Parsed webhook data
	 *     @type string $code   Error code if parsing failed
	 *     @type string $message Error message if parsing failed
	 * }
	 */
	private function parse_webhook_payload( string $request_body ): array {
		try {
			// Check for empty body
			if ( empty( $request_body ) ) {
				return array(
					'valid'   => false,
					'code'    => 'empty_body',
					'message' => 'Empty request body',
				);
			}

			// Parse JSON
			$webhook_data = json_decode( $request_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				Logger::error(
					'JSON parsing failed',
					array(
						'error'        => json_last_error_msg(),
						'body_preview' => substr( $request_body, 0, 200 ),
					)
				);

				return array(
					'valid'   => false,
					'code'    => 'invalid_json',
					'message' => 'Invalid JSON format',
				);
			}

			// Validate webhook structure
			if ( ! isset( $webhook_data['events'] ) || ! is_array( $webhook_data['events'] ) ) {
				Logger::error( 'Invalid webhook structure - missing events array' );

				return array(
					'valid'   => false,
					'code'    => 'invalid_structure',
					'message' => 'Invalid webhook structure',
				);
			}

			return array(
				'valid' => true,
				'data'  => $webhook_data,
			);

		} catch ( \Exception $e ) {
			$this->error_handler->handle_error(
				'webhook_payload_parsing_failed',
				'Failed to parse webhook payload',
				array( 'exception' => $e->getMessage() ),
				'WebhookHandler::parse_webhook_payload'
			);

			return array(
				'valid'   => false,
				'code'    => 'parsing_error',
				'message' => 'Failed to parse request',
			);
		}
	}

	/**
	 * Process array of webhook events
	 *
	 * @param array $events Array of LINE webhook events
	 *
	 * @return array {
	 *     Processing results
	 *
	 *     @type int $successful Number of successfully processed events
	 *     @type int $failed     Number of failed events
	 *     @type array $errors   Array of error details for failed events
	 * }
	 */
	private function process_webhook_events( array $events ): array {
		$results = array(
			'successful' => 0,
			'failed'     => 0,
			'errors'     => array(),
		);

		foreach ( $events as $index => $event ) {
			try {
				$event_result = $this->process_single_event( $event );

				if ( $event_result['success'] ) {
					$results['successful']++;
				} else {
					$results['failed']++;
					$results['errors'][] = array(
						'event_index' => $index,
						'event_type'  => $event['type'] ?? 'unknown',
						'error'       => $event_result['error'],
					);
				}
			} catch ( \Exception $e ) {
				$results['failed']++;
				$results['errors'][] = array(
					'event_index' => $index,
					'event_type'  => $event['type'] ?? 'unknown',
					'error'       => array(
						'code'    => 'processing_exception',
						'message' => 'Event processing failed: ' . $e->getMessage(),
					),
				);

				$this->error_handler->handle_error(
					'event_processing_exception',
					'Exception during event processing',
					array(
						'event_index' => $index,
						'event_data'  => $event,
						'exception'   => $e->getMessage(),
					),
					'WebhookHandler::process_webhook_events'
				);
			}
		}

		return $results;
	}

	/**
	 * Process individual LINE webhook event
	 *
	 * @param array $event LINE event data
	 *
	 * @return array {
	 *     Processing result
	 *
	 *     @type bool  $success Whether event was processed successfully
	 *     @type array $data    Event processing data
	 *     @type array $error   Error details if processing failed
	 * }
	 */
	private function process_single_event( array $event ): array {
		try {
			// Fire hook for event received.
			do_action( 'otz_webhook_event_received', $event );

			// Validate event structure.
			if ( ! isset( $event['type'] ) || ! isset( $event['source'] ) ) {
				return array(
					'success' => false,
					'error'   => array(
						'code'    => 'invalid_event_structure',
						'message' => 'Event missing required fields',
					),
				);
			}

			$event_type = $event['type'];
			$source     = $event['source'];
			$user_id    = $source['userId'] ?? null;

			// Check if event type is supported.
			if ( ! in_array( $event_type, self::SUPPORTED_EVENT_TYPES, true ) ) {

				return array(
					'success' => true,
					'data'    => array(
						'event_type' => $event_type,
						'action'     => 'logged_only',
						'message'    => 'Event type logged but not processed',
					),
				);
			}

			// Handle events that require user ID
			if ( in_array( $event_type, array( 'message', 'follow', 'unfollow' ), true ) && empty( $user_id ) ) {
				return array(
					'success' => false,
					'error'   => array(
						'code'    => 'missing_user_id',
						'message' => 'User ID required for this event type',
					),
				);
			}

			// Process event based on type
			$result = null;
			switch ( $event_type ) {
				case 'message':
					$result = $this->process_message_event( $event );
					break;

				case 'follow':
					$result = $this->process_follow_event( $event );
					break;

				case 'unfollow':
					$result = $this->process_unfollow_event( $event );
					break;

				case 'join':
				case 'leave':
					$result = $this->process_group_event( $event );
					break;

				case 'memberJoined':
				case 'memberLeft':
					$result = $this->process_member_event( $event );
					break;

				default:
					// Log other events but don't process them.
					$result = array(
						'success' => true,
						'data'    => array(
							'event_type' => $event_type,
							'action'     => 'logged_only',
						),
					);
					break;
			}

			// Fire hook for event processed.
			do_action( 'otz_webhook_event_processed', $event, $result );

			return $result;
		} catch ( \Exception $e ) {
			$error_result = array(
				'success' => false,
				'error'   => array(
					'code'    => 'event_processing_failed',
					'message' => 'Failed to process event: ' . $e->getMessage(),
				),
			);

			// Fire hook for event processed with error.
			do_action( 'otz_webhook_event_processed', $event, $error_result );

			return $error_result;
		}
	}

	/**
	 * Process LINE message event
	 *
	 * @param array $event LINE message event data
	 *
	 * @return array Processing result
	 */
	private function process_message_event( array $event ): array {
		try {
			$user_id = $event['source']['userId'];
			$source  = $event['source'];

			// Fire hook for LINE message received.
			do_action( 'otz_line_message_received', $event, $user_id );

			// Step 1: Ensure user exists.
			$user_result = $this->user_manager->ensure_user_exists( $user_id, $source );
			if ( ! $user_result['success'] ) {
				Logger::error(
					"Failed to ensure user exists: {$user_id}",
					array(
						'error' => $user_result['error'] ?? 'Unknown error',
					)
				);
			}

			// Step 1.1: Send web push notification to all subscribers.
			$this->send_new_message_notification( $event, $user_id );

			// Step 2: Store message.
			$message_stored = $this->message_manager->store_message( $event );
			if ( ! $message_stored ) {
				return array(
					'success' => false,
					'error'   => array(
						'code'    => 'message_storage_failed',
						'message' => 'Failed to store message',
					),
				);
			}

			// Step 3: Update user activity.
			$this->user_manager->update_last_active( $user_id );

			// Step 4: Clear unread count cache (new message received).
			$unreadCountService = new UnreadCountService();
			$unreadCountService->clearCache();

			// Step 5: Handle Bot auto-response (only for text messages).
			if ( isset( $event['message']['type'] ) && 'text' === $event['message']['type'] ) {
				// 檢查訊息來源類型.
				$source_type = $event['source']['type'] ?? 'user';

				// 只在個人訊息（非群組/聊天室）中處理 Bot 回應.
				if ( 'user' === $source_type ) {
					$this->handle_bot_response( $user_id, $event );
				}
			}

			return array(
				'success' => true,
				'data'    => array(
					'event_type'     => 'message',
					'user_id'        => $user_id,
					'message_stored' => true,
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Message event processing failed',
				array(
					'exception'  => $e->getMessage(),
					'event_data' => $event,
				)
			);

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'message_processing_exception',
					'message' => 'Message processing failed',
				),
			);
		}
	}

	/**
	 * Process LINE follow event
	 *
	 * @param array $event LINE follow event data
	 *
	 * @return array Processing result
	 */
	private function process_follow_event( array $event ): array {
		try {
			$user_id = $event['source']['userId'];

			// Fire hook for LINE user followed.
			do_action( 'otz_line_user_followed', $event, $user_id );

			$follow_result = $this->user_manager->handle_follow( $user_id, $event );

			if ( $follow_result ) {

				return array(
					'success' => true,
					'data'    => array(
						'event_type' => 'follow',
						'user_id'    => $user_id,
						'action'     => 'user_followed',
					),
				);
			}

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'follow_processing_failed',
					'message' => 'Failed to process follow event',
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Follow event processing failed',
				array(
					'exception' => $e->getMessage(),
					'user_id'   => $event['source']['userId'] ?? 'unknown',
				)
			);

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'follow_processing_exception',
					'message' => 'Follow processing failed',
				),
			);
		}
	}

	/**
	 * Process LINE unfollow event
	 *
	 * @param array $event LINE unfollow event data
	 *
	 * @return array Processing result
	 */
	private function process_unfollow_event( array $event ): array {
		try {
			$user_id = $event['source']['userId'];

			// Fire hook for LINE user unfollowed.
			do_action( 'otz_line_user_unfollowed', $event, $user_id );

			$unfollow_result = $this->user_manager->handle_unfollow( $user_id );

			if ( $unfollow_result ) {

				return array(
					'success' => true,
					'data'    => array(
						'event_type' => 'unfollow',
						'user_id'    => $user_id,
						'action'     => 'user_unfollowed',
					),
				);
			}

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'unfollow_processing_failed',
					'message' => 'Failed to process unfollow event',
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Unfollow event processing failed',
				array(
					'exception' => $e->getMessage(),
					'user_id'   => $event['source']['userId'] ?? 'unknown',
				)
			);

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'unfollow_processing_exception',
					'message' => 'Unfollow processing failed',
				),
			);
		}
	}

	/**
	 * Process group-related events (join/leave)
	 *
	 * @param array $event LINE group event data
	 *
	 * @return array Processing result
	 */
	private function process_group_event( array $event ): array {
		try {
			$event_type = $event['type'];
			$source     = $event['source'];
			$group_id   = $source['groupId'] ?? $source['roomId'] ?? null;
			$source_type = isset( $source['groupId'] ) ? 'group' : 'room';

			if ( empty( $group_id ) ) {
				return array(
					'success' => false,
					'error'   => array(
						'code'    => 'missing_group_id',
						'message' => 'Group ID is required',
					),
				);
			}

			if ( $event_type === 'join' ) {
				// Bot joins group - sync group info.
				$sync_result = $this->group_manager->sync_group_info( $group_id, $source_type );

				if ( $sync_result['success'] ) {
					return array(
						'success' => true,
						'data'    => array(
							'event_type' => 'join',
							'action'     => 'group_synced',
							'group_id'   => $group_id,
						),
					);
				}

				return $sync_result;

			} elseif ( $event_type === 'leave' ) {
				// Bot leaves group - just log for now.
				return array(
					'success' => true,
					'data'    => array(
						'event_type' => 'leave',
						'action'     => 'logged',
						'group_id'   => $group_id,
					),
				);
			}

			return array(
				'success' => true,
				'data'    => array(
					'event_type' => $event_type,
					'action'     => 'logged_only',
					'group_id'   => $group_id,
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Group event processing failed',
				array(
					'exception'  => $e->getMessage(),
					'event_data' => $event,
				)
			);

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'group_processing_exception',
					'message' => 'Group processing failed',
				),
			);
		}
	}

	/**
	 * Process member joined/left events
	 *
	 * @param array $event LINE member event data
	 *
	 * @return array Processing result
	 */
	private function process_member_event( array $event ): array {
		try {
			$event_type = $event['type'];
			$source     = $event['source'];
			$group_id   = $source['groupId'] ?? $source['roomId'] ?? null;
			$joined     = $event['joined'] ?? array();
			$left       = $event['left'] ?? array();

			if ( empty( $group_id ) ) {
				return array(
					'success' => false,
					'error'   => array(
						'code'    => 'missing_group_id',
						'message' => 'Group ID is required',
					),
				);
			}

			$processed_count = 0;

			if ( $event_type === 'memberJoined' ) {
				// Process joined members.
				if ( isset( $joined['members'] ) && is_array( $joined['members'] ) ) {
					foreach ( $joined['members'] as $member ) {
						$line_user_id = $member['userId'] ?? null;
						if ( $line_user_id ) {
							$result = $this->group_manager->add_member( $group_id, $line_user_id );
							if ( $result['success'] ) {
								++$processed_count;
							}
						}
					}
				}

				return array(
					'success' => true,
					'data'    => array(
						'event_type'      => 'memberJoined',
						'action'          => 'members_added',
						'group_id'        => $group_id,
						'processed_count' => $processed_count,
					),
				);

			} elseif ( $event_type === 'memberLeft' ) {
				// Process left members.
				if ( isset( $left['members'] ) && is_array( $left['members'] ) ) {
					foreach ( $left['members'] as $member ) {
						$line_user_id = $member['userId'] ?? null;
						if ( $line_user_id ) {
							$result = $this->group_manager->remove_member( $group_id, $line_user_id );
							if ( $result['success'] ) {
								++$processed_count;
							}
						}
					}
				}

				return array(
					'success' => true,
					'data'    => array(
						'event_type'      => 'memberLeft',
						'action'          => 'members_removed',
						'group_id'        => $group_id,
						'processed_count' => $processed_count,
					),
				);
			}

			return array(
				'success' => true,
				'data'    => array(
					'event_type' => $event_type,
					'action'     => 'logged_only',
					'group_id'   => $group_id,
				),
			);

		} catch ( \Exception $e ) {
			Logger::error(
				'Member event processing failed',
				array(
					'exception'  => $e->getMessage(),
					'event_data' => $event,
				)
			);

			return array(
				'success' => false,
				'error'   => array(
					'code'    => 'member_processing_exception',
					'message' => 'Member processing failed',
				),
			);
		}
	}

	/**
	 * Handle Bot auto-response
	 *
	 * 處理 AI 自動回應，包含關鍵字匹配與 AI 對話.
	 *
	 * @param string $line_user_id LINE 使用者 ID.
	 * @param array  $event Webhook event 資料.
	 * @return void
	 */
	private function handle_bot_response( string $line_user_id, array $event ): void {
		try {
			// 取得訊息內容.
			$message_text = $event['message']['text'] ?? '';
			if ( empty( $message_text ) ) {
				return;
			}

			// 取得 event timestamp.
			$event_timestamp = $event['timestamp'] ?? null;

			// Bot 回應的 idempotency 檢查 - 防止重複處理同一個 webhook event.
			$event_id = $event['replyToken'] ?? $event['webhookEventId'] ?? null;
			if ( ! $event_id ) {
				Logger::error(
					'No event ID for bot response idempotency check',
					array( 'line_user_id' => $line_user_id ),
					'otz'
				);
				return;
			}

			// 檢查是否已經處理過這個 event 的 Bot 回應.
			$bot_response_key = 'otz_bot_response_processed_' . md5( $event_id );
			if ( get_transient( $bot_response_key ) ) {
				return;
			}

			set_transient( $bot_response_key, true, 60 * 60 );

			// 取得 reply token.
			$reply_token = $event['replyToken'] ?? '';
			if ( empty( $reply_token ) ) {
				Logger::error(
					'Missing reply token in bot response',
					array( 'line_user_id' => $line_user_id ),
					'otz'
				);
				return;
			}

			// 從 line_user_id 取得 otz_users 的 id.
			$user_data = $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->wpdb->prefix}otz_users WHERE line_user_id = %s",
					$line_user_id
				),
				ARRAY_A
			);

			if ( ! $user_data || ! isset( $user_data['id'] ) ) {
				Logger::error(
					'Failed to get user id for bot response',
					array( 'line_user_id' => $line_user_id ),
					'otz'
				);
				return;
			}

			$user_id = (int) $user_data['id'];

			// 初始化 BotResponder 依賴.
			$bot_db           = new Bot( $this->wpdb );
			$user_db          = new User( $this->wpdb );
			$message_db       = new TableMessage( $this->wpdb );
			$line_api_service = new \OrderChatz\Ajax\Message\LineApiService();
			$table_manager    = new \OrderChatz\Database\DynamicTableManager( $this->wpdb );

			// 建立 BotMatcher 實例.
			$bot_matcher = new \OrderChatz\Services\Bot\BotMatcher(
				$this->wpdb,
				$bot_db,
				$user_db
			);

			// 建立 BotResponder 實例.
			$bot_responder = new BotResponder(
				$this->wpdb,
				$bot_db,
				$bot_matcher,
				$message_db,
				$line_api_service,
				$table_manager
			);

			// 處理使用者訊息.
			$result = $bot_responder->handle_user_message( $user_id, $line_user_id, $message_text, $reply_token, $event_timestamp );

			// 如果有觸發 Bot，記錄日誌（僅記錄成功，錯誤已在 BotResponder 中記錄）.
			if ( $result['triggered'] && ! isset( $result['error'] ) ) {
				// Fire hook for bot response sent.
				do_action(
					'otz_bot_response_sent',
					$user_id,
					$line_user_id,
					$result
				);
			}
		} catch ( \Exception $e ) {
			Logger::error(
				'Error in handle_bot_response: ' . $e->getMessage(),
				array(
					'line_user_id' => $line_user_id,
					'exception'    => $e->getMessage(),
				),
				'otz'
			);
		}
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip(): string {
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
	 * Create error response with consistent format
	 *
	 * @param string $error_code   Error code identifier
	 * @param string $message      Human-readable error message
	 * @param int    $http_code    HTTP status code
	 *
	 * @return \WP_REST_Response WordPress REST response
	 */
	private function create_error_response( string $error_code, string $message, int $http_code ): \WP_REST_Response {
		$response_data = array(
			'success'   => false,
			'error'     => array(
				'code'    => $error_code,
				'message' => $message,
			),
			'timestamp' => wp_date( 'c' ),
		);

		// Add debug info in development mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response_data['debug'] = array(
				'php_version'    => PHP_VERSION,
				'wp_version'     => get_bloginfo( 'version' ),
				'plugin_version' => OTZ_VERSION ?? '1.0.4',
			);
		}

		return new \WP_REST_Response( $response_data, $http_code );
	}

	/**
	 * Handle webhook processing exceptions
	 *
	 * @param \Exception       $e       Exception instance
	 * @param \WP_REST_Request $request Original request object
	 *
	 * @return void
	 */
	private function handle_webhook_exception( \Exception $e, \WP_REST_Request $request ): void {
		$this->error_handler->handle_error(
			'webhook_processing_exception',
			'Webhook processing failed with exception',
			array(
				'exception'       => $e->getMessage(),
				'trace'           => $e->getTraceAsString(),
				'request_method'  => $request->get_method(),
				'request_headers' => $request->get_headers(),
				'remote_ip'       => $this->get_client_ip(),
			),
			'WebhookHandler::handle_webhook'
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
	private function log_error( string $message, array $context = array() ): void {
		Logger::error( $message, $context );
	}

	/**
	 * Send web push notification for new message
	 *
	 * @param array  $event        LINE webhook event data
	 * @param string $line_user_id LINE user ID
	 *
	 * @return void
	 */
	private function send_new_message_notification( array $event, string $line_user_id ): void {
		try {
			// Extract event ID for duplicate check.
			$event_id = $event['replyToken'] ?? $event['webhookEventId'] ?? null;
			if ( ! $event_id ) {
				Logger::error( 'No event ID found for notification duplicate check' );
				return;
			}

			// Check if this event has already been processed to avoid duplicate notifications.
			if ( $this->message_manager->is_event_processed( $event_id ) ) {
				return;
			}

			// Get sender information and avatar
			$user_info   = $this->get_line_user_info( $line_user_id );
			$sender_name = $user_info['display_name'] ?? '未知用戶';
			$avatar_url  = $user_info['avatar_url'] ?? null;

			// Build notification content
			$title   = "新訊息來自 {$sender_name}";
			$message = $this->extract_message_preview( $event['message'] );
			$url     = $this->build_chat_url( $line_user_id );

			// Create notification payload (with LINE user avatar)
			$payload = $this->web_push_service->create_notification_payload(
				$title,
				$message,
				$avatar_url, // Use LINE user avatar
				$url
			);

			// Send to all subscribers
			$this->web_push_service->send_to_all_subscribers( $payload );

		} catch ( \Exception $e ) {
			Logger::error( 'Failed to send web push notification: ' . $e->getMessage() );
		}
	}

	/**
	 * Get LINE user information from database
	 *
	 * @param string $line_user_id LINE user ID
	 *
	 * @return array User information
	 */
	private function get_line_user_info( string $line_user_id ): array {
		$user = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, display_name, avatar_url FROM {$this->wpdb->prefix}otz_users WHERE line_user_id = %s",
				$line_user_id
			),
			ARRAY_A
		);

		return $user ?: array();
	}

	/**
	 * Extract message preview based on message type
	 *
	 * @param array $message LINE message data
	 *
	 * @return string Message preview
	 */
	private function extract_message_preview( array $message ): string {
		switch ( $message['type'] ) {
			case 'text':
				$text = $message['text'];

				// 處理包含表情符號的文字訊息
				if ( isset( $message['emojis'] ) && is_array( $message['emojis'] ) && count( $message['emojis'] ) > 0 ) {
					// 如果整個文字都是表情符號（沒有其他文字）
					$text_without_emojis = $text;
					foreach ( $message['emojis'] as $emoji ) {
						$index  = $emoji['index'] ?? 0;
						$length = $emoji['length'] ?? 0;
						if ( $length > 0 ) {
							$placeholder         = mb_substr( $text, $index, $length );
							$text_without_emojis = str_replace( $placeholder, '', $text_without_emojis );
						}
					}

					if ( empty( trim( $text_without_emojis ) ) ) {
						// 純表情符號訊息
						return '😊 傳送了表情符號';
					} else {
						// 混合文字和表情符號：將表情符號替換為標記
						$preview_text = $text;
						// 按照索引倒序處理，避免索引偏移
						$emojis_sorted = $message['emojis'];
						usort(
							$emojis_sorted,
							function( $a, $b ) {
								return $b['index'] - $a['index'];
							}
						);

						foreach ( $emojis_sorted as $emoji ) {
							$index  = $emoji['index'] ?? 0;
							$length = $emoji['length'] ?? 0;
							if ( $length > 0 ) {
								$preview_text = mb_substr( $preview_text, 0, $index ) . '[表情符號]' . mb_substr( $preview_text, $index + $length );
							}
						}
						return mb_substr( $preview_text, 0, 50 ) . ( mb_strlen( $preview_text ) > 50 ? '...' : '' );
					}
				}

				// 純文字訊息
				return mb_substr( $text, 0, 50 ) . ( mb_strlen( $text ) > 50 ? '...' : '' );

			case 'image':
				return '📷 傳送了一張圖片';
			case 'sticker':
				return '😊 傳送了貼圖';
			case 'video':
				return '🎬 傳送了影片';
			case 'audio':
				return '🎵 傳送了語音';
			case 'location':
				return '📍 分享了位置';
			default:
				return '收到新訊息';
		}
	}

	/**
	 * Build chat URL for web push notification
	 *
	 * @param string $line_user_id LINE user ID
	 *
	 * @return string Chat URL
	 */
	private function build_chat_url( string $line_user_id ): string {
		// Get corresponding id (serial number) from otz_users table
		$friend_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->wpdb->prefix}otz_users WHERE line_user_id = %s",
				$line_user_id
			)
		);

		if ( ! $friend_id ) {
			// If not found, use default chat page
			return home_url( 'order-chatz/?chat=1' );
		}

		return home_url( "order-chatz/?chat=1&friend={$friend_id}" );
	}
}
