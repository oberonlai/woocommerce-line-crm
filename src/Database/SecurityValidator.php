<?php

declare(strict_types=1);

namespace OrderChatz\Database;

/**
 * Database Security Validator Class
 *
 * Comprehensive security validation and protection mechanisms.
 * Implements Task 5.3: 安全性防護與驗證機制.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.3
 */
class SecurityValidator {

	/**
	 * WordPress database abstraction layer
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Security audit log option name
	 */
	private const AUDIT_LOG_OPTION = 'orderchatz_security_audit_log';
	
	/**
	 * Maximum audit log entries to keep
	 */
	private const MAX_AUDIT_ENTRIES = 1000;

	/**
	 * Allowed table name patterns
	 */
	private const ALLOWED_TABLE_PATTERNS = [
		'/^[a-z0-9_]+$/',  // Basic alphanumeric and underscores
		'/^\w+_\d{4}_\d{2}$/'  // Monthly table pattern: prefix_YYYY_MM
	];

	/**
	 * Sensitive operations that require extra validation
	 */
	private const SENSITIVE_OPERATIONS = [
		'CREATE',
		'DROP', 
		'ALTER',
		'TRUNCATE',
		'DELETE',
		'INSERT',
		'UPDATE'
	];

	/**
	 * Constructor
	 *
	 * @param \wpdb $wpdb WordPress database object
	 * @param ErrorHandler|null $error_handler Error handler instance
	 */
	public function __construct( \wpdb $wpdb, ?ErrorHandler $error_handler = null ) {
		$this->wpdb = $wpdb;
		$this->error_handler = $error_handler;
	}

	/**
	 * Validate and sanitize table suffix for dynamic table creation
	 *
	 * @param string $suffix Table suffix to validate
	 * @param string $context Context for audit logging
	 * @return string|false Sanitized suffix on success, false on failure
	 */
	public function sanitize_table_suffix( string $suffix, string $context = '' ): ?string {
		// Record security check
		$this->audit_log( 'table_suffix_validation', [
			'suffix' => $suffix,
			'context' => $context,
			'user_id' => get_current_user_id(),
			'ip_address' => $this->get_client_ip(),
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
		] );

		// Basic sanitization
		$sanitized = sanitize_text_field( $suffix );
		if ( $sanitized !== $suffix ) {
			$this->log_security_violation( 
				'Table suffix contains unsafe characters', 
				[
					'original_suffix' => $suffix,
					'sanitized_suffix' => $sanitized,
					'context' => $context
				], 
				'input_sanitization' 
			);
			return null;
		}

		// Pattern validation
		$is_valid = false;
		foreach ( self::ALLOWED_TABLE_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $sanitized ) ) {
				$is_valid = true;
				break;
			}
		}

		if ( ! $is_valid ) {
			$this->log_security_violation( 
				'Table suffix does not match allowed patterns', 
				[
					'suffix' => $sanitized,
					'allowed_patterns' => self::ALLOWED_TABLE_PATTERNS,
					'context' => $context
				], 
				'pattern_validation' 
			);
			return null;
		}

		// Length validation
		if ( strlen( $sanitized ) > 64 ) {
			$this->log_security_violation( 
				'Table suffix exceeds maximum length', 
				[
					'suffix' => $sanitized,
					'length' => strlen( $sanitized ),
					'max_length' => 64,
					'context' => $context
				], 
				'length_validation' 
			);
			return null;
		}

		// SQL injection prevention - check for common SQL keywords
		$sql_keywords = [
			'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 
			'ALTER', 'GRANT', 'REVOKE', 'UNION', 'ORDER', 'GROUP', 'HAVING'
		];
		
		$upper_suffix = strtoupper( $sanitized );
		foreach ( $sql_keywords as $keyword ) {
			if ( strpos( $upper_suffix, $keyword ) !== false ) {
				$this->log_security_violation( 
					'Table suffix contains SQL keywords', 
					[
						'suffix' => $sanitized,
						'detected_keyword' => $keyword,
						'context' => $context
					], 
					'sql_injection_prevention' 
				);
				return null;
			}
		}

		return $sanitized;
	}

	/**
	 * Verify WordPress nonce for database operations
	 *
	 * @param string $nonce_action Nonce action name
	 * @param string $nonce_name Nonce field name (default: '_wpnonce')
	 * @param string $context Operation context for logging
	 * @return bool True if nonce is valid, false otherwise
	 */
	public function verify_nonce( string $nonce_action, string $nonce_name = '_wpnonce', string $context = '' ): bool {
		$nonce_value = $_REQUEST[ $nonce_name ] ?? '';
		
		$this->audit_log( 'nonce_verification', [
			'action' => $nonce_action,
			'nonce_name' => $nonce_name,
			'context' => $context,
			'user_id' => get_current_user_id(),
			'ip_address' => $this->get_client_ip()
		] );

		$is_valid = wp_verify_nonce( $nonce_value, $nonce_action );

		if ( ! $is_valid ) {
			$this->log_security_violation( 
				'Invalid or missing nonce', 
				[
					'nonce_action' => $nonce_action,
					'nonce_name' => $nonce_name,
					'context' => $context,
					'provided_nonce' => substr( $nonce_value, 0, 8 ) . '...' // Log only first 8 chars for security
				], 
				'nonce_validation' 
			);
		}

		return $is_valid;
	}

	/**
	 * Check if current user has required capabilities for database operations
	 *
	 * @param string|array $required_caps Required capabilities
	 * @param string $context Operation context for logging
	 * @return bool True if user has required capabilities, false otherwise
	 */
	public function check_user_capabilities( $required_caps, string $context = '' ): bool {
		if ( is_string( $required_caps ) ) {
			$required_caps = [ $required_caps ];
		}

		$user_id = get_current_user_id();
		$has_capability = true;

		foreach ( $required_caps as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				$has_capability = false;
				break;
			}
		}

		$this->audit_log( 'capability_check', [
			'required_capabilities' => $required_caps,
			'context' => $context,
			'user_id' => $user_id,
			'has_capability' => $has_capability,
			'ip_address' => $this->get_client_ip()
		] );

		if ( ! $has_capability ) {
			$this->log_security_violation( 
				'Insufficient user capabilities', 
				[
					'required_capabilities' => $required_caps,
					'user_id' => $user_id,
					'context' => $context
				], 
				'capability_check' 
			);
		}

		return $has_capability;
	}

	/**
	 * Validate SQL query for potential injection attacks
	 *
	 * @param string $query SQL query to validate
	 * @param string $context Query context for logging
	 * @return bool True if query appears safe, false if suspicious
	 */
	public function validate_sql_query( string $query, string $context = '' ): bool {
		// Audit log the query validation
		$this->audit_log( 'sql_query_validation', [
			'query_hash' => hash( 'sha256', $query ), // Don't log full query for security
			'query_length' => strlen( $query ),
			'context' => $context,
			'user_id' => get_current_user_id(),
			'ip_address' => $this->get_client_ip()
		] );

		// Check for multiple statement attempts
		$statement_count = substr_count( $query, ';' );
		if ( $statement_count > 1 ) {
			$this->log_security_violation( 
				'Multiple SQL statements detected', 
				[
					'statement_count' => $statement_count,
					'context' => $context,
					'query_preview' => substr( $query, 0, 100 ) . '...'
				], 
				'multiple_statements' 
			);
			return false;
		}

		// Check for dangerous SQL patterns
		$dangerous_patterns = [
			'/\b(UNION\s+SELECT)\b/i',
			'/\b(DROP\s+TABLE)\b/i',
			'/\b(TRUNCATE\s+TABLE)\b/i',
			'/\b(DELETE\s+FROM.*WHERE\s+1=1)\b/i',
			'/\b(UPDATE.*SET.*WHERE\s+1=1)\b/i',
			'/\b(EXEC\s*\()\b/i',
			'/\b(SCRIPT\s*>)\b/i',
			'/<\s*script\b/i',
			'/javascript:/i'
		];

		foreach ( $dangerous_patterns as $pattern ) {
			if ( preg_match( $pattern, $query ) ) {
				$this->log_security_violation( 
					'Dangerous SQL pattern detected', 
					[
						'pattern' => $pattern,
						'context' => $context,
						'query_preview' => substr( $query, 0, 100 ) . '...'
					], 
					'dangerous_sql_pattern' 
				);
				return false;
			}
		}

		// Check for excessive query length (potential buffer overflow)
		if ( strlen( $query ) > 10000 ) {
			$this->log_security_violation( 
				'Excessively long SQL query', 
				[
					'query_length' => strlen( $query ),
					'context' => $context
				], 
				'query_length' 
			);
			return false;
		}

		return true;
	}

	/**
	 * Prepare SQL query safely using wpdb->prepare
	 *
	 * @param string $query SQL query with placeholders
	 * @param mixed ...$args Arguments to bind to placeholders
	 * @return string|null Prepared query on success, null on failure
	 */
	public function prepare_query( string $query, ...$args ): ?string {
		try {
			// Validate the base query first
			if ( ! $this->validate_sql_query( $query, 'prepare_query' ) ) {
				return null;
			}

			// Use WordPress prepare method
			$prepared_query = $this->wpdb->prepare( $query, ...$args );

			if ( false === $prepared_query ) {
				$this->log_security_violation( 
					'SQL query preparation failed', 
					[
						'query_preview' => substr( $query, 0, 100 ) . '...',
						'args_count' => count( $args ),
						'wpdb_error' => $this->wpdb->last_error ?: 'No specific error'
					], 
					'query_preparation' 
				);
				return null;
			}

			return $prepared_query;

		} catch ( \Exception $e ) {
			$this->log_security_violation( 
				'Exception during SQL query preparation', 
				[
					'exception_message' => $e->getMessage(),
					'query_preview' => substr( $query, 0, 100 ) . '...',
					'args_count' => count( $args )
				], 
				'query_preparation_exception' 
			);
			return null;
		}
	}

	/**
	 * Get client IP address (with proxy support)
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip(): string {
		// Check for IP from various sources
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',            // Proxy
			'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
			'HTTP_X_FORWARDED',          // Proxy
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
			'HTTP_FORWARDED_FOR',        // Proxy
			'HTTP_FORWARDED',            // Proxy
			'REMOTE_ADDR'                // Standard
		];

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) && ! empty( $_SERVER[ $key ] ) ) {
				$ip_list = explode( ',', $_SERVER[ $key ] );
				$ip = trim( $ip_list[0] );
				
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	}

	/**
	 * Log security violations
	 *
	 * @param string $message Security violation message
	 * @param array  $context Additional context data
	 * @param string $violation_type Type of security violation
	 * @return void
	 */
	private function log_security_violation( string $message, array $context = [], string $violation_type = 'unknown' ): void {
		$enhanced_context = array_merge( $context, [
			'violation_type' => $violation_type,
			'timestamp' => wp_date( 'c' ),
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'http_method' => $_SERVER['REQUEST_METHOD'] ?? '',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
		] );

		if ( $this->error_handler ) {
			$this->error_handler->log_database_error( $message, $enhanced_context, 'security' );
		} else {
			error_log( "[OrderChatz Security Violation] [{$violation_type}] {$message}: " . wp_json_encode( $enhanced_context ) );
		}

		// Also add to security audit log
		$this->audit_log( 'security_violation', array_merge( [
			'message' => $message,
			'violation_type' => $violation_type
		], $enhanced_context ) );
	}

	/**
	 * Add entry to security audit log
	 *
	 * @param string $action Action being audited
	 * @param array  $context Context data
	 * @return void
	 */
	private function audit_log( string $action, array $context = [] ): void {
		$audit_entry = [
			'action' => $action,
			'timestamp' => wp_date( 'c' ),
			'user_id' => get_current_user_id(),
			'session_id' => session_id() ?: 'no-session',
			'context' => $context
		];

		// Get existing audit log
		$audit_log = get_option( self::AUDIT_LOG_OPTION, [] );
		
		// Add new entry
		$audit_log[] = $audit_entry;
		
		// Keep only the most recent entries
		if ( count( $audit_log ) > self::MAX_AUDIT_ENTRIES ) {
			$audit_log = array_slice( $audit_log, -self::MAX_AUDIT_ENTRIES );
		}
		
		// Update option
		update_option( self::AUDIT_LOG_OPTION, $audit_log );
	}

	/**
	 * Get security audit log entries
	 *
	 * @param int    $limit Number of entries to return
	 * @param string $action_filter Filter by action type
	 * @param int    $hours_back How many hours back to look
	 * @return array Array of audit log entries
	 */
	public function get_audit_log( int $limit = 100, string $action_filter = '', int $hours_back = 24 ): array {
		$audit_log = get_option( self::AUDIT_LOG_OPTION, [] );
		
		// Filter by time range
		$cutoff_time = wp_date( 'c', strtotime( "-{$hours_back} hours" ) );
		$filtered_log = array_filter( $audit_log, function( $entry ) use ( $cutoff_time ) {
			return $entry['timestamp'] >= $cutoff_time;
		} );

		// Filter by action if specified
		if ( ! empty( $action_filter ) ) {
			$filtered_log = array_filter( $filtered_log, function( $entry ) use ( $action_filter ) {
				return $entry['action'] === $action_filter;
			} );
		}

		// Sort by timestamp (newest first) and limit
		usort( $filtered_log, function( $a, $b ) {
			return strcmp( $b['timestamp'], $a['timestamp'] );
		} );

		return array_slice( $filtered_log, 0, $limit );
	}

	/**
	 * Clear old audit log entries
	 *
	 * @param int $days_to_keep Number of days to keep entries
	 * @return int Number of entries removed
	 */
	public function cleanup_audit_log( int $days_to_keep = 30 ): int {
		$audit_log = get_option( self::AUDIT_LOG_OPTION, [] );
		$original_count = count( $audit_log );
		
		// Calculate cutoff timestamp
		$cutoff_time = wp_date( 'c', strtotime( "-{$days_to_keep} days" ) );
		
		// Filter to keep only recent entries
		$filtered_log = array_filter( $audit_log, function( $entry ) use ( $cutoff_time ) {
			return $entry['timestamp'] >= $cutoff_time;
		} );
		
		// Update option
		update_option( self::AUDIT_LOG_OPTION, array_values( $filtered_log ) );
		
		$removed_count = $original_count - count( $filtered_log );
		
		if ( $removed_count > 0 && $this->error_handler ) {
			$this->error_handler->log_info( 
				"Cleaned up {$removed_count} old audit log entries",
				[ 'days_kept' => $days_to_keep, 'remaining_entries' => count( $filtered_log ) ],
				'orderchatz-security-cleanup'
			);
		}
		
		return $removed_count;
	}

	/**
	 * Get security statistics
	 *
	 * @param int $hours_back Hours to analyze
	 * @return array Security statistics
	 */
	public function get_security_stats( int $hours_back = 24 ): array {
		$audit_entries = $this->get_audit_log( 1000, '', $hours_back );
		
		$stats = [
			'total_operations' => count( $audit_entries ),
			'security_violations' => 0,
			'nonce_verifications' => 0,
			'capability_checks' => 0,
			'sql_validations' => 0,
			'table_suffix_validations' => 0,
			'unique_users' => [],
			'unique_ips' => [],
			'violation_types' => []
		];

		foreach ( $audit_entries as $entry ) {
			$action = $entry['action'];
			$context = $entry['context'] ?? [];
			
			// Count by action type
			switch ( $action ) {
				case 'security_violation':
					$stats['security_violations']++;
					$violation_type = $context['violation_type'] ?? 'unknown';
					$stats['violation_types'][ $violation_type ] = ( $stats['violation_types'][ $violation_type ] ?? 0 ) + 1;
					break;
				case 'nonce_verification':
					$stats['nonce_verifications']++;
					break;
				case 'capability_check':
					$stats['capability_checks']++;
					break;
				case 'sql_query_validation':
					$stats['sql_validations']++;
					break;
				case 'table_suffix_validation':
					$stats['table_suffix_validations']++;
					break;
			}
			
			// Track unique users and IPs
			if ( ! empty( $entry['user_id'] ) ) {
				$stats['unique_users'][ $entry['user_id'] ] = true;
			}
			if ( ! empty( $context['ip_address'] ) ) {
				$stats['unique_ips'][ $context['ip_address'] ] = true;
			}
		}

		$stats['unique_users'] = array_keys( $stats['unique_users'] );
		$stats['unique_ips'] = array_keys( $stats['unique_ips'] );

		return $stats;
	}
}