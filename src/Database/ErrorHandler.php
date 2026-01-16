<?php

declare(strict_types=1);

namespace OrderChatz\Database;

/**
 * Database Error Handler Class
 *
 * Centralized error handling, logging, and user notification system.
 * Implements Task 5.2: 全面錯誤處理與日誌記錄.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class ErrorHandler {

	/**
	 * WordPress database abstraction layer
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
	 * Error categories for classification
	 */
	private const ERROR_CATEGORIES = [
		'connection' => '資料庫連線錯誤',
		'permission' => '資料庫權限錯誤',
		'syntax' => 'SQL 語法錯誤',
		'constraint' => '資料約束錯誤',
		'table' => '資料表錯誤',
		'version' => '版本管理錯誤',
		'validation' => '資料驗證錯誤',
		'security' => '安全性錯誤',
		'system' => '系統錯誤',
		'unknown' => '未知錯誤'
	];

	/**
	 * WordPress error codes mapping
	 */
	private const WP_ERROR_CODES = [
		// MySQL connection errors
		2002 => 'connection',
		2003 => 'connection',
		2005 => 'connection',
		2006 => 'connection',
		2013 => 'connection',
		
		// Permission errors  
		1044 => 'permission',
		1045 => 'permission',
		1142 => 'permission',
		1143 => 'permission',
		
		// Syntax errors
		1064 => 'syntax',
		1149 => 'syntax',
		
		// Constraint errors
		1062 => 'constraint',
		1451 => 'constraint',
		1452 => 'constraint',
		
		// Table errors
		1146 => 'table',
		1050 => 'table',
		1051 => 'table'
	];

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 * @param \WC_Logger|null $logger Logger instance
	 */
	public function __construct( \wpdb $wpdb, ?\WC_Logger $logger = null ) {
		$this->wpdb = $wpdb;
		$this->logger = $logger;
	}

	/**
	 * Log and categorize database error
	 *
	 * @param string $message Error message
	 * @param array  $context Additional context data
	 * @param string|null $category Error category (auto-detected if null)
	 * @return void
	 */
	public function log_database_error( string $message, array $context = [], ?string $category = null ): void {
		// Enhance context with database information
		$enhanced_context = $this->enhance_error_context( $context );
		
		// Auto-detect category if not provided
		if ( null === $category ) {
			$category = $this->detect_error_category( $message, $enhanced_context );
		}

		// Add category to context
		$enhanced_context['error_category'] = $category;
		$enhanced_context['category_description'] = self::ERROR_CATEGORIES[ $category ] ?? '未知錯誤類型';

		// Log structured error
		$structured_message = $this->format_error_message( $message, $category );
		
		if ( $this->logger ) {
			$this->logger->error( 
				$structured_message, 
				array_merge( [ 'source' => 'orderchatz-database-error' ], $enhanced_context )
			);
		} else {
			error_log( "[OrderChatz DB Error] [{$category}] {$structured_message}: " . wp_json_encode( $enhanced_context ) );
		}

		// Record error for admin notification
		$this->record_error_for_notification( $structured_message, $category, $enhanced_context );
	}

	/**
	 * Log informational messages
	 *
	 * @param string $message The message to log
	 * @param array  $context Additional context data
	 * @param string $source Log source identifier
	 * @return void
	 */
	public function log_info( string $message, array $context = [], string $source = 'orderchatz-database' ): void {
		$enhanced_context = array_merge( 
			[ 'source' => $source, 'timestamp' => wp_date( 'c' ) ], 
			$context 
		);

		if ( $this->logger ) {
			$this->logger->info( $message, $enhanced_context );
		} else {
			error_log( "[OrderChatz DB Info] {$message}" );
		}
	}

	/**
	 * Log warning messages
	 *
	 * @param string $message The message to log
	 * @param array  $context Additional context data  
	 * @param string $source Log source identifier
	 * @return void
	 */
	public function log_warning( string $message, array $context = [], string $source = 'orderchatz-database' ): void {
		$enhanced_context = array_merge( 
			[ 'source' => $source, 'timestamp' => wp_date( 'c' ) ], 
			$context 
		);

		if ( $this->logger ) {
			$this->logger->warning( $message, $enhanced_context );
		} else {
			error_log( "[OrderChatz DB Warning] {$message}" );
		}
	}

	/**
	 * Generic error handler for all types of errors
	 *
	 * This method provides a unified interface for handling errors from different
	 * components of the system, not just database-related errors.
	 *
	 * @param string $error_code   Unique error code identifier
	 * @param string $message      Error message description
	 * @param array  $context      Additional context data
	 * @param string $source       Error source identifier (class::method)
	 * @param string $severity     Error severity: 'error', 'warning', 'info'
	 * @return void
	 */
	public function handle_error( 
		string $error_code, 
		string $message, 
		array $context = [], 
		string $source = 'orderchatz', 
		string $severity = 'error' 
	): void {
		// Enhance context with common information
		$enhanced_context = array_merge( $context, [
			'error_code' => $error_code,
			'source' => $source,
			'severity' => $severity,
			'timestamp' => wp_date( 'c' ),
			'user_id' => get_current_user_id() ?: 0,
			'is_admin' => is_admin(),
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
			'php_version' => PHP_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
			'plugin_version' => OTZ_VERSION ?? '1.0.4'
		]);

		// Format the error message
		$formatted_message = "[{$error_code}] {$message}";

		// Log based on severity
		switch ( $severity ) {
			case 'warning':
				$this->log_warning( $formatted_message, $enhanced_context, $source );
				break;
				
			case 'info':
				$this->log_info( $formatted_message, $enhanced_context, $source );
				break;
				
			case 'error':
			default:
				// For non-database errors, use the enhanced error logging
				if ( $this->logger ) {
					$this->logger->error( $formatted_message, $enhanced_context );
				} else {
					error_log( "[OrderChatz Error] {$formatted_message}: " . wp_json_encode( $enhanced_context ) );
				}
				break;
		}

		// Record critical errors for admin notification
		if ( $severity === 'error' ) {
			$this->record_system_error_for_notification( $error_code, $message, $enhanced_context );
		}
	}

	/**
	 * Record system error for admin notification display
	 *
	 * @param string $error_code Error code identifier
	 * @param string $message    Error message
	 * @param array  $context    Error context
	 * @return void
	 */
	private function record_system_error_for_notification( string $error_code, string $message, array $context ): void {
		$error_record = [
			'error_code' => $error_code,
			'message' => $message,
			'source' => $context['source'] ?? 'orderchatz',
			'timestamp' => $context['timestamp'] ?? wp_date( 'c' ),
			'user_id' => $context['user_id'] ?? 0,
			'severity' => $context['severity'] ?? 'error'
		];

		// Store in transient for admin notice display (expires in 2 hours)
		$existing_errors = get_transient( 'orderchatz_system_errors' ) ?: [];
		$existing_errors[] = $error_record;
		
		// Limit to last 15 errors to prevent memory issues
		$existing_errors = array_slice( $existing_errors, -15 );
		
		set_transient( 'orderchatz_system_errors', $existing_errors, 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Get recent system errors for admin display
	 *
	 * @return array Array of recent error records
	 */
	public function get_recent_system_errors(): array {
		return get_transient( 'orderchatz_system_errors' ) ?: [];
	}

	/**
	 * Clear recent system errors
	 *
	 * @return void
	 */
	public function clear_recent_system_errors(): void {
		delete_transient( 'orderchatz_system_errors' );
	}

	/**
	 * Enhance error context with database and system information
	 *
	 * @param array $context Original context
	 * @return array Enhanced context
	 */
	private function enhance_error_context( array $context ): array {
		// Add WordPress database error information
		if ( ! empty( $this->wpdb->last_error ) ) {
			$context['wpdb_error'] = $this->wpdb->last_error;
			$context['mysql_error_code'] = $this->extract_mysql_error_code( $this->wpdb->last_error );
		}
		
		if ( ! empty( $this->wpdb->last_query ) ) {
			$context['wpdb_query'] = $this->wpdb->last_query;
		}

		// Add system information
		$context['php_version'] = PHP_VERSION;
		$context['wp_version'] = get_bloginfo( 'version' );
		$context['mysql_version'] = $this->wpdb->db_version();
		$context['timestamp'] = wp_date( 'c' );
		$context['user_id'] = get_current_user_id() ?: 0;
		$context['is_admin'] = is_admin();
		$context['is_ajax'] = wp_doing_ajax();
		$context['memory_usage'] = memory_get_usage( true );
		$context['memory_peak'] = memory_get_peak_usage( true );

		return $context;
	}

	/**
	 * Extract MySQL error code from error message
	 *
	 * @param string $error_message MySQL error message
	 * @return int|null MySQL error code or null
	 */
	private function extract_mysql_error_code( string $error_message ): ?int {
		// Pattern to match MySQL error codes like [1062] or (1062)
		if ( preg_match( '/[\[\(](\d+)[\]\)]/', $error_message, $matches ) ) {
			return (int) $matches[1];
		}
		return null;
	}

	/**
	 * Detect error category based on message and context
	 *
	 * @param string $message Error message
	 * @param array  $context Error context
	 * @return string Error category
	 */
	private function detect_error_category( string $message, array $context ): string {
		// Check MySQL error code first
		if ( isset( $context['mysql_error_code'] ) && isset( self::WP_ERROR_CODES[ $context['mysql_error_code'] ] ) ) {
			return self::WP_ERROR_CODES[ $context['mysql_error_code'] ];
		}

		// Pattern matching on error message
		$message_lower = strtolower( $message );
		
		if ( strpos( $message_lower, 'connection' ) !== false || strpos( $message_lower, 'connect' ) !== false ) {
			return 'connection';
		}
		
		if ( strpos( $message_lower, 'permission' ) !== false || strpos( $message_lower, 'access denied' ) !== false ) {
			return 'permission';
		}
		
		if ( strpos( $message_lower, 'syntax' ) !== false || strpos( $message_lower, 'sql' ) !== false ) {
			return 'syntax';
		}
		
		if ( strpos( $message_lower, 'duplicate' ) !== false || strpos( $message_lower, 'constraint' ) !== false ) {
			return 'constraint';
		}
		
		if ( strpos( $message_lower, 'table' ) !== false || strpos( $message_lower, "doesn't exist" ) !== false ) {
			return 'table';
		}
		
		if ( strpos( $message_lower, 'version' ) !== false || strpos( $message_lower, 'upgrade' ) !== false ) {
			return 'version';
		}
		
		if ( strpos( $message_lower, 'validation' ) !== false || strpos( $message_lower, 'invalid' ) !== false ) {
			return 'validation';
		}
		
		if ( strpos( $message_lower, 'security' ) !== false || strpos( $message_lower, 'injection' ) !== false ) {
			return 'security';
		}

		return 'unknown';
	}

	/**
	 * Format error message with category prefix
	 *
	 * @param string $message Original error message
	 * @param string $category Error category
	 * @return string Formatted error message
	 */
	private function format_error_message( string $message, string $category ): string {
		$category_label = self::ERROR_CATEGORIES[ $category ] ?? '未知錯誤';
		return "[{$category_label}] {$message}";
	}

	/**
	 * Record error for admin notification display
	 *
	 * @param string $message Error message
	 * @param string $category Error category  
	 * @param array  $context Error context
	 * @return void
	 */
	private function record_error_for_notification( string $message, string $category, array $context ): void {
		$error_record = [
			'message' => $message,
			'category' => $category,
			'category_description' => self::ERROR_CATEGORIES[ $category ] ?? '未知錯誤類型',
			'timestamp' => wp_date( 'c' ),
			'mysql_error_code' => $context['mysql_error_code'] ?? null,
			'user_id' => $context['user_id'] ?? 0
		];

		// Store in transient for admin notice display (expires in 1 hour)
		$existing_errors = get_transient( 'orderchatz_db_errors' ) ?: [];
		$existing_errors[] = $error_record;
		
		// Limit to last 10 errors to prevent memory issues
		$existing_errors = array_slice( $existing_errors, -10 );
		
		set_transient( 'orderchatz_db_errors', $existing_errors, HOUR_IN_SECONDS );
	}

	/**
	 * Get recent database errors for admin display
	 *
	 * @return array Array of recent error records
	 */
	public function get_recent_errors(): array {
		return get_transient( 'orderchatz_db_errors' ) ?: [];
	}

	/**
	 * Clear recent database errors
	 *
	 * @return void
	 */
	public function clear_recent_errors(): void {
		delete_transient( 'orderchatz_db_errors' );
	}

	/**
	 * Generate user-friendly error message based on category
	 *
	 * @param string $category Error category
	 * @param array  $context Error context
	 * @return array User-friendly message and suggested actions
	 */
	public function get_user_friendly_error_info( string $category, array $context = [] ): array {
		$mysql_code = $context['mysql_error_code'] ?? null;

		switch ( $category ) {
			case 'connection':
				return [
					'title' => '資料庫連線問題',
					'message' => '無法連接到資料庫伺服器。這可能是暫時性的網路問題。',
					'suggestions' => [
						'等待幾分鐘後重試',
						'檢查網站主機狀態',
						'聯繫主機商確認資料庫服務狀態',
						'檢查 WordPress 資料庫設定'
					]
				];

			case 'permission':
				return [
					'title' => '資料庫權限不足',
					'message' => '資料庫用戶沒有執行此操作的權限。',
					'suggestions' => [
						'確認資料庫用戶具有 CREATE、ALTER、INSERT、UPDATE 權限',
						'聯繫主機商或資料庫管理員',
						'檢查 wp-config.php 中的資料庫設定',
						'確認資料庫用戶名和密碼正確'
					]
				];

			case 'table':
				return [
					'title' => '資料表問題',
					'message' => '資料表不存在或結構異常。',
					'suggestions' => [
						'重新執行資料庫安裝程序',
						'檢查資料表是否被意外刪除',
						'執行資料庫修復工具',
						'從備份還原資料庫'
					]
				];

			case 'constraint':
				if ( 1062 === $mysql_code ) {
					return [
						'title' => '資料重複錯誤',
						'message' => '嘗試插入重複的資料記錄。',
						'suggestions' => [
							'檢查是否已存在相同的記錄',
							'更新現有記錄而非插入新記錄',
							'檢查唯一鍵約束設定'
						]
					];
				}
				return [
					'title' => '資料約束錯誤',
					'message' => '資料不符合資料庫約束條件。',
					'suggestions' => [
						'檢查資料格式是否正確',
						'確認所有必填欄位都有值',
						'檢查外鍵關係是否正確'
					]
				];

			case 'version':
				return [
					'title' => '版本管理錯誤',
					'message' => '資料庫結構版本不匹配或升級失敗。',
					'suggestions' => [
						'重新執行資料庫升級程序',
						'檢查升級權限是否足夠',
						'查看詳細的升級日誌',
						'聯繫技術支援取得協助'
					]
				];

			case 'security':
				return [
					'title' => '安全性錯誤',
					'message' => '偵測到潛在的安全威脅或不當操作。',
					'suggestions' => [
						'檢查輸入資料是否包含異常內容',
						'確認操作權限是否正確',
						'查看安全性日誌',
						'立即聯繫系統管理員'
					]
				];

			default:
				return [
					'title' => '未知錯誤',
					'message' => '發生了未預期的錯誤。',
					'suggestions' => [
						'重試該操作',
						'檢查系統日誌',
						'聯繫技術支援',
						'提供完整的錯誤訊息給開發團隊'
					]
				];
		}
	}

	/**
	 * Display admin notice for database errors
	 *
	 * @return void
	 */
	public function display_admin_notices(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$recent_errors = $this->get_recent_errors();
		if ( empty( $recent_errors ) ) {
			return;
		}

		// Group errors by category
		$grouped_errors = [];
		foreach ( $recent_errors as $error ) {
			$category = $error['category'];
			if ( ! isset( $grouped_errors[ $category ] ) ) {
				$grouped_errors[ $category ] = [];
			}
			$grouped_errors[ $category ][] = $error;
		}

		foreach ( $grouped_errors as $category => $errors ) {
			$error_info = $this->get_user_friendly_error_info( $category, $errors[0] );
			$error_count = count( $errors );
			
			echo '<div class="notice notice-error is-dismissible orderchatz-db-error">';
			echo '<h3><strong>OrderChatz 資料庫錯誤</strong> - ' . esc_html( $error_info['title'] ) . '</h3>';
			
			if ( $error_count > 1 ) {
				echo '<p>在過去一小時內發生了 ' . esc_html( $error_count ) . ' 次同類型的錯誤。</p>';
			}
			
			echo '<p>' . esc_html( $error_info['message'] ) . '</p>';
			echo '<p><strong>建議解決方案：</strong></p>';
			echo '<ul>';
			foreach ( $error_info['suggestions'] as $suggestion ) {
				echo '<li>' . esc_html( $suggestion ) . '</li>';
			}
			echo '</ul>';
			
			// Show latest error details for debugging
			$latest_error = end( $errors );
			echo '<details style="margin-top: 10px;">';
			echo '<summary>顯示技術細節（供開發者參考）</summary>';
			echo '<p><strong>錯誤時間：</strong> ' . esc_html( $latest_error['timestamp'] ) . '</p>';
			echo '<p><strong>錯誤訊息：</strong> ' . esc_html( $latest_error['message'] ) . '</p>';
			if ( $latest_error['mysql_error_code'] ) {
				echo '<p><strong>MySQL 錯誤碼：</strong> ' . esc_html( $latest_error['mysql_error_code'] ) . '</p>';
			}
			echo '</details>';
			
			echo '<p><a href="' . esc_url( admin_url( 'admin-post.php?action=orderchatz_clear_db_errors' ) ) . '" class="button button-secondary">清除錯誤通知</a></p>';
			echo '</div>';
		}
	}
}