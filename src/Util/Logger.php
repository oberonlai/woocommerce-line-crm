<?php

declare(strict_types=1);

namespace OrderChatz\Util;

/**
 * Logger Utility Class
 *
 * Provides centralized logging functionality for the OrderChatz plugin.
 * Supports multiple log levels and integrates with WooCommerce logger when available.
 * Uses 'otz' as the source identifier for all log entries.
 *
 * @package    OrderChatz
 * @subpackage Util
 * @since      1.0.4
 */
class Logger {

	/**
	 * Log levels
	 */
	public const EMERGENCY = 'emergency';
	public const ALERT     = 'alert';
	public const CRITICAL  = 'critical';
	public const ERROR     = 'error';
	public const WARNING   = 'warning';
	public const NOTICE    = 'notice';
	public const INFO      = 'info';
	public const DEBUG     = 'debug';

	/**
	 * WooCommerce logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private static ?\WC_Logger $wc_logger = null;

	/**
	 * Log source identifier
	 *
	 * @var string
	 */
	private static string $source = 'otz';

	/**
	 * Whether debug mode is enabled
	 *
	 * @var bool|null
	 */
	private static ?bool $debug_enabled = null;

	/**
	 * Initialize the logger
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( null === self::$wc_logger && class_exists( 'WC_Logger' ) ) {
			self::$wc_logger = new \WC_Logger();
		}

		if ( null === self::$debug_enabled ) {
			self::$debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		}
	}

	/**
	 * Log an emergency message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function emergency( string $message, array $context = [], string $component = '' ): void {
		self::log( self::EMERGENCY, $message, $context, $component );
	}

	/**
	 * Log an alert message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function alert( string $message, array $context = [], string $component = '' ): void {
		self::log( self::ALERT, $message, $context, $component );
	}

	/**
	 * Log a critical message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function critical( string $message, array $context = [], string $component = '' ): void {
		self::log( self::CRITICAL, $message, $context, $component );
	}

	/**
	 * Log an error message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function error( string $message, array $context = [], string $component = '' ): void {
		self::log( self::ERROR, $message, $context, $component );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function warning( string $message, array $context = [], string $component = '' ): void {
		self::log( self::WARNING, $message, $context, $component );
	}

	/**
	 * Log a notice message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function notice( string $message, array $context = [], string $component = '' ): void {
		self::log( self::NOTICE, $message, $context, $component );
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function info( string $message, array $context = [], string $component = '' ): void {
		self::log( self::INFO, $message, $context, $component );
	}

	/**
	 * Log a debug message (only when debug mode is enabled)
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function debug( string $message, array $context = [], string $component = '' ): void {
		if ( self::is_debug_enabled() ) {
			self::log( self::DEBUG, $message, $context, $component );
		}
	}

	/**
	 * Log a message with specified level
	 *
	 * @param string $level Log level
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 * @param string $component Component identifier (optional)
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = [], string $component = '' ): void {
		self::init();

		$formatted_message = self::format_message( $message, $component );
		$log_context = self::prepare_context( $context );

		// Try WooCommerce logger first
		if ( self::$wc_logger ) {
			try {
				self::$wc_logger->log( $level, $formatted_message, $log_context );
				return;
			} catch ( \Exception $e ) {
				// Fall back to error_log if WC logger fails
			}
		}

		// Fallback to WordPress error_log
		self::fallback_log( $level, $formatted_message, $log_context );
	}

	/**
	 * Format log message with component and source
	 *
	 * @param string $message Original message
	 * @param string $component Component identifier
	 * @return string Formatted message
	 */
	private static function format_message( string $message, string $component ): string {
		$parts = [ self::$source ];

		if ( ! empty( $component ) ) {
			$parts[] = $component;
		}

		$prefix = '[' . implode( '::', $parts ) . ']';
		return "{$prefix} {$message}";
	}

	/**
	 * Prepare context array for logging
	 *
	 * @param array $context Raw context data
	 * @return array Prepared context
	 */
	private static function prepare_context( array $context ): array {
		$prepared = [ 'source' => self::$source ];

		// Add timestamp
		$prepared['timestamp'] = wp_date( 'c' );

		// Add request info if available
		if ( isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$prepared['request_method'] = sanitize_text_field( $_SERVER['REQUEST_METHOD'] );
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$prepared['request_uri'] = sanitize_text_field( $_SERVER['REQUEST_URI'] );
		}

		// Merge with provided context
		return array_merge( $prepared, $context );
	}

	/**
	 * Fallback logging to WordPress error_log
	 *
	 * @param string $level Log level
	 * @param string $message Formatted message
	 * @param array  $context Log context
	 * @return void
	 */
	private static function fallback_log( string $level, string $message, array $context ): void {
		$level_upper = strtoupper( $level );
		$context_string = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		
		error_log( "[{$level_upper}] {$message}{$context_string}" );
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool
	 */
	private static function is_debug_enabled(): bool {
		if ( null === self::$debug_enabled ) {
			self::$debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		}

		return self::$debug_enabled;
	}

	/**
	 * Set debug mode (for testing purposes)
	 *
	 * @param bool $enabled Whether debug mode is enabled
	 * @return void
	 */
	public static function set_debug_enabled( bool $enabled ): void {
		self::$debug_enabled = $enabled;
	}

	/**
	 * Get current log source
	 *
	 * @return string
	 */
	public static function get_source(): string {
		return self::$source;
	}

	/**
	 * Set log source (for testing purposes)
	 *
	 * @param string $source Log source identifier
	 * @return void
	 */
	public static function set_source( string $source ): void {
		self::$source = $source;
	}

	/**
	 * Clear logger state (for testing purposes)
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$wc_logger = null;
		self::$debug_enabled = null;
		self::$source = 'otz';
	}

	/**
	 * Get logger configuration status
	 *
	 * @return array Configuration status
	 */
	public static function get_status(): array {
		self::init();

		return [
			'wc_logger_available' => null !== self::$wc_logger,
			'debug_enabled' => self::is_debug_enabled(),
			'source' => self::$source,
			'wp_debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
		];
	}
}