<?php

/**
 * WebhookRegistrar - LINE Webhook Registration Manager
 *
 * This class manages LINE webhook endpoint registration with manual trigger support.
 * It handles access token verification, registration status management, and provides
 * retry functionality for failed registrations.
 *
 * @package    OrderChatz
 * @subpackage API
 * @since      1.0.4
 */

namespace OrderChatz\API;

use OrderChatz\Util\Logger;
use OrderChatz\Database\ErrorHandler;
use OrderChatz\Database\SecurityValidator;

/**
 * WebhookRegistrar class
 * 
 * Manages LINE webhook registration process triggered by admin actions.
 * Provides comprehensive status tracking and error handling for webhook registration.
 */
class WebhookRegistrar {
    
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
     * LINE API client instance
     * 
     * @var LineAPIClient
     */
    private LineAPIClient $line_api_client;
    
    /**
     * Registration status cache key
     */
    private const REGISTRATION_STATUS_CACHE_KEY = 'otz_webhook_registration_status';
    
    /**
     * Cache expiration time (5 minutes)
     */
    private const CACHE_EXPIRATION = 300;
    
    /**
     * Constructor
     * 
     * @param \wpdb             $wpdb               WordPress database instance
     * @param \WC_Logger|null   $logger             Logger instance
     * @param ErrorHandler      $error_handler      Error handler instance
     * @param SecurityValidator $security_validator Security validator instance
     * @param LineAPIClient     $line_api_client    LINE API client instance
     */
    public function __construct(
        \wpdb $wpdb,
        ?\WC_Logger $logger,
        ErrorHandler $error_handler,
        SecurityValidator $security_validator,
        LineAPIClient $line_api_client
    ) {
        $this->wpdb               = $wpdb;
        $this->logger             = $logger;
        $this->error_handler      = $error_handler;
        $this->security_validator = $security_validator;
        $this->line_api_client    = $line_api_client;
    }
    
    /**
     * Register webhook with LINE platform
     * 
     * This method handles the complete webhook registration process including:
     * - Access token verification
     * - Webhook URL registration
     * - Status management and caching
     * - Error handling and admin notifications
     * 
     * @param string $access_token  LINE Channel Access Token
     * @param string $webhook_url   The webhook endpoint URL to register
     * 
     * @return array {
     *     Registration result array
     *     
     *     @type bool   $success      Whether registration was successful
     *     @type string $status       Registration status: 'registered', 'pending', 'failed', 'invalid_token'
     *     @type string $message      Human-readable status message
     *     @type array  $error        Error details if registration failed
     * }
     */
    public function register_webhook( string $access_token, string $webhook_url ): array {
        try {
            // Input validation
            if ( empty( $access_token ) || empty( $webhook_url ) ) {
                return $this->create_error_response(
                    'invalid_input',
                    '必須提供 access token 和 webhook URL',
                    'pending'
                );
            }
            
            // Validate webhook URL format
            if ( ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
                return $this->create_error_response(
                    'invalid_url',
                    'Webhook URL 格式無效',
                    'pending'
                );
            }
            
            // Set access token for LINE API client
            $this->line_api_client->set_access_token( $access_token );
            
            // Step 1: Verify access token
            
            if ( ! $this->line_api_client->verify_access_token() ) {
                Logger::error( 'Access token verification failed' );
                return $this->create_error_response(
                    'invalid_token',
                    'Access Token 無效或已過期，請檢查設定',
                    'invalid_token'
                );
            }
            
            
            // Step 2: Register webhook endpoint
            $registration_result = $this->line_api_client->register_webhook( $webhook_url );
            
            if ( ! $registration_result['success'] ) {
                $error_message = $registration_result['error']['message'] ?? 'Webhook 註冊失敗';
                $error_code = $registration_result['error']['code'] ?? 'registration_failed';
                
                Logger::error( "Webhook registration failed: {$error_message}", [
                    'error_code' => $error_code,
                    'webhook_url' => $webhook_url
                ]);
                
                return $this->create_error_response(
                    $error_code,
                    "Webhook 註冊失敗: {$error_message}",
                    'failed'
                );
            }
            
            // Step 3: Update registration status
            $this->update_registration_status( 'registered', $webhook_url );
            $this->clear_registration_cache();
            
            
            return [
                'success' => true,
                'status'  => 'registered',
                'message' => 'Webhook 註冊成功',
                'data'    => [
                    'webhook_url'   => $webhook_url,
                    'registered_at' => wp_date( 'Y-m-d H:i:s' )
                ]
            ];
            
        } catch ( \Exception $e ) {
            $this->handle_registration_exception( $e, $webhook_url );
            
            return $this->create_error_response(
                'system_error',
                '系統錯誤，請稍後重試',
                'failed'
            );
        }
    }
    
    /**
     * Get current webhook registration status
     * 
     * @return array {
     *     Registration status information
     *     
     *     @type bool   $success         Whether status retrieval was successful
     *     @type string $status          Current status: 'registered', 'pending', 'failed', 'not_configured'
     *     @type string $webhook_url     Registered webhook URL (if any)
     *     @type string $last_check      Last status check timestamp
     *     @type array  $error           Error details if status check failed
     * }
     */
    public function get_registration_status(): array {
        try {
            // Check cache first
            $cached_status = get_transient( self::REGISTRATION_STATUS_CACHE_KEY );
            if ( $cached_status !== false ) {
                return $cached_status;
            }
            
            // Get stored registration status
            $status = get_option( 'otz_webhook_status', 'not_configured' );
            $webhook_url = get_option( 'otz_webhook_url', '' );
            $last_check = get_option( 'otz_webhook_last_check', '' );
            
            $result = [
                'success'     => true,
                'status'      => $status,
                'webhook_url' => $webhook_url,
                'last_check'  => $last_check ?: wp_date( 'Y-m-d H:i:s' )
            ];
            
            // Cache the result
            set_transient( self::REGISTRATION_STATUS_CACHE_KEY, $result, self::CACHE_EXPIRATION );
            
            return $result;
            
        } catch ( \Exception $e ) {
            $this->error_handler->handle_error(
                'webhook_status_check_failed',
                'Failed to retrieve webhook registration status',
                [ 'exception' => $e->getMessage() ],
                'WebhookRegistrar::get_registration_status'
            );
            
            return [
                'success' => false,
                'status'  => 'unknown',
                'error'   => [
                    'code'    => 'status_check_failed',
                    'message' => '無法取得註冊狀態'
                ]
            ];
        }
    }
    
    /**
     * Verify current webhook registration with LINE platform
     * 
     * This method performs a live verification of the webhook registration
     * by checking with LINE's API directly.
     * 
     * @return array {
     *     Verification result
     *     
     *     @type bool   $success      Whether verification was successful
     *     @type bool   $is_valid     Whether the webhook registration is valid
     *     @type string $message      Verification message
     *     @type array  $details      Additional verification details
     * }
     */
    public function verify_registration(): array {
        try {
            $access_token = get_option( 'otz_access_token', '' );
            
            if ( empty( $access_token ) ) {
                return [
                    'success'  => false,
                    'is_valid' => false,
                    'message'  => 'Access Token 未設定',
                    'error'    => [
                        'code' => 'no_access_token',
                        'message' => '請先設定 Access Token'
                    ]
                ];
            }
            
            // Set access token and verify
            $this->line_api_client->set_access_token( $access_token );
            
            if ( ! $this->line_api_client->verify_access_token() ) {
                $this->update_registration_status( 'invalid_token' );
                
                return [
                    'success'  => false,
                    'is_valid' => false,
                    'message'  => 'Access Token 無效',
                    'error'    => [
                        'code' => 'invalid_token',
                        'message' => 'Access Token 無效或已過期'
                    ]
                ];
            }
            
            // Get current webhook info (if supported by LINE API)
            $webhook_info = $this->line_api_client->get_webhook_info();
            
            if ( $webhook_info['success'] ) {
                $is_valid = ! empty( $webhook_info['data']['endpoint'] );
                $status = $is_valid ? 'registered' : 'not_registered';
                
                $this->update_registration_status( $status, $webhook_info['data']['endpoint'] ?? '' );
                
                return [
                    'success'  => true,
                    'is_valid' => $is_valid,
                    'message'  => $is_valid ? 'Webhook 已正確註冊' : 'Webhook 未註冊',
                    'details'  => $webhook_info['data']
                ];
            }
            
            // Fallback: assume valid if we have stored status
            $stored_status = get_option( 'otz_webhook_status', 'not_configured' );
            $is_valid = ( $stored_status === 'registered' );
            
            return [
                'success'  => true,
                'is_valid' => $is_valid,
                'message'  => $is_valid ? '根據本地記錄，Webhook 已註冊' : 'Webhook 狀態未知',
                'details'  => [
                    'stored_status' => $stored_status,
                    'note' => 'LINE API 不支援 webhook 狀態查詢，基於本地記錄判斷'
                ]
            ];
            
        } catch ( \Exception $e ) {
            $this->error_handler->handle_error(
                'webhook_verification_failed',
                'Webhook verification process failed',
                [ 'exception' => $e->getMessage() ],
                'WebhookRegistrar::verify_registration'
            );
            
            return [
                'success'  => false,
                'is_valid' => false,
                'message'  => '驗證過程發生錯誤',
                'error'    => [
                    'code' => 'verification_failed',
                    'message' => '無法驗證 webhook 註冊狀態'
                ]
            ];
        }
    }
    
    /**
     * Reset webhook registration status
     * 
     * This method clears all registration status and forces a fresh registration.
     * Useful for troubleshooting or when changing webhook configuration.
     * 
     * @return bool Whether status reset was successful
     */
    public function reset_registration_status(): bool {
        try {
            // Clear all webhook-related options
            delete_option( 'otz_webhook_status' );
            delete_option( 'otz_webhook_url' );
            delete_option( 'otz_webhook_last_check' );
            
            // Clear cache
            $this->clear_registration_cache();
            
            
            return true;
            
        } catch ( \Exception $e ) {
            $this->error_handler->handle_error(
                'webhook_status_reset_failed',
                'Failed to reset webhook registration status',
                [ 'exception' => $e->getMessage() ],
                'WebhookRegistrar::reset_registration_status'
            );
            
            return false;
        }
    }
    
    /**
     * Update registration status to registered (public method for smart detection)
     * 
     * @return bool Whether update was successful
     */
    public function update_registration_status_to_registered(): bool {
        return $this->update_registration_status( 'registered', $this->get_current_webhook_url() );
    }
    
    /**
     * Get current webhook URL
     * 
     * @return string
     */
    private function get_current_webhook_url(): string {
        // This should match the RestAPIManager's webhook URL
        return home_url( '/wp-json/otz/v1/webhook' );
    }
    
    /**
     * Update registration status in database
     * 
     * @param string $status      New status value
     * @param string $webhook_url Optional webhook URL
     * 
     * @return bool Whether update was successful
     */
    private function update_registration_status( string $status, string $webhook_url = '' ): bool {
        try {
            update_option( 'otz_webhook_status', $status );
            update_option( 'otz_webhook_last_check', wp_date( 'Y-m-d H:i:s' ) );
            
            if ( ! empty( $webhook_url ) ) {
                update_option( 'otz_webhook_url', $webhook_url );
            }
            
            return true;
            
        } catch ( \Exception $e ) {
            Logger::error( 'Failed to update registration status', [
                'status' => $status,
                'webhook_url' => $webhook_url,
                'exception' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Clear registration status cache
     * 
     * @return bool Whether cache clear was successful
     */
    private function clear_registration_cache(): bool {
        return delete_transient( self::REGISTRATION_STATUS_CACHE_KEY );
    }
    
    /**
     * Create standardized error response
     * 
     * @param string $error_code Error code identifier
     * @param string $message    Human-readable error message
     * @param string $status     Registration status to set
     * 
     * @return array Error response array
     */
    private function create_error_response( string $error_code, string $message, string $status = 'failed' ): array {
        $this->update_registration_status( $status );
        
        return [
            'success' => false,
            'status'  => $status,
            'message' => $message,
            'error'   => [
                'code'    => $error_code,
                'message' => $message
            ]
        ];
    }
    
    /**
     * Handle registration exceptions
     * 
     * @param \Exception $e           Exception instance
     * @param string     $webhook_url Webhook URL being registered
     * 
     * @return void
     */
    private function handle_registration_exception( \Exception $e, string $webhook_url ): void {
        $this->error_handler->handle_error(
            'webhook_registration_exception',
            'Webhook registration process failed with exception',
            [
                'exception'   => $e->getMessage(),
                'webhook_url' => $webhook_url,
                'trace'       => $e->getTraceAsString()
            ],
            'WebhookRegistrar::register_webhook'
        );
        
        $this->update_registration_status( 'failed' );
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