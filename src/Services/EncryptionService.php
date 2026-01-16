<?php

declare(strict_types=1);

namespace OrderChatz\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Encryption Service
 *
 * Provides AES-256-CBC encryption services for sensitive data storage.
 * Based on the reference implementation in WebPush/Encrypt.php
 *
 * @package    OrderChatz
 * @subpackage Services
 * @since      1.0.0
 */
class EncryptionService {

	/**
	 * Default option key for encryption
	 */
	private const DEFAULT_OPTION_KEY = 'otz_encrypted_data';

	/**
	 * Encrypt and store data in WordPress options
	 *
	 * @param string $value      Value to encrypt
	 * @param string $option_key WordPress option key
	 * @return bool True on success, false on failure
	 */
	public static function encrypt( string $value, string $option_key = self::DEFAULT_OPTION_KEY ): bool {
		$encryption_key = self::get_encryption_key();
		if ( ! $encryption_key ) {
			return false;
		}

		try {
			// Generate random initialization vector
			$iv = openssl_random_pseudo_bytes( 16 );

			// Encrypt the value using AES-256-CBC
			$encrypted = openssl_encrypt( $value, 'aes-256-cbc', $encryption_key, 0, $iv );

			if ( $encrypted === false ) {
				return false;
			}

			// Combine IV and encrypted data, then base64 encode
			$stored_value = base64_encode( $iv . $encrypted );

			update_option( $option_key, $stored_value );

			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Decrypt data from WordPress options
	 *
	 * @param string $option_key WordPress option key
	 * @return string Decrypted value or empty string on failure
	 */
	public static function decrypt( string $option_key = self::DEFAULT_OPTION_KEY ): string {
		$stored = get_option( $option_key, '' );
		if ( empty( $stored ) ) {
			return '';
		}

		$encryption_key = self::get_encryption_key();
		if ( ! $encryption_key ) {
			return '';
		}

		try {
			// Base64 decode the stored data
			$data = base64_decode( $stored );
			if ( $data === false ) {
				return '';
			}

			// Extract IV (first 16 bytes) and encrypted data
			$iv             = substr( $data, 0, 16 );
			$encrypted_data = substr( $data, 16 );

			// Decrypt using AES-256-CBC
			$decrypted = openssl_decrypt( $encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv );

			return $decrypted !== false ? $decrypted : '';
		} catch ( \Exception $e ) {
			error_log( '[OrderChatz EncryptionService] Decryption failed: ' . $e->getMessage() );
			return '';
		}
	}

	/**
	 * Generate encryption key using WordPress AUTH_SALT
	 *
	 * This method creates a consistent 32-byte key for AES-256 encryption
	 * using WordPress AUTH_SALT to avoid circular dependency with VAPID keys.
	 *
	 * @return string|null 32-byte encryption key or null on failure
	 */
	protected static function get_encryption_key(): ?string {
		// Use WordPress AUTH_SALT as encryption key to avoid circular dependency
		$fallback_key = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'otz-default-encryption-key';
		return hash( 'sha256', $fallback_key, true );
	}

	/**
	 * Check if encryption is available
	 *
	 * @return bool True if OpenSSL extension is available
	 */
	public static function is_encryption_available(): bool {
		return extension_loaded( 'openssl' ) && function_exists( 'openssl_encrypt' );
	}

	/**
	 * Validate encrypted data integrity
	 *
	 * @param string $option_key WordPress option key
	 * @return bool True if data can be decrypted successfully
	 */
	public static function validate_encrypted_data( string $option_key ): bool {
		$decrypted = self::decrypt( $option_key );
		return ! empty( $decrypted );
	}

	/**
	 * Remove encrypted data
	 *
	 * @param string $option_key WordPress option key
	 * @return bool True on success
	 */
	public static function remove_encrypted_data( string $option_key ): bool {
		return delete_option( $option_key );
	}

	/**
	 * Simple web push payload encryption
	 * This is a simplified implementation that just returns the payload
	 * Real web push encryption requires complex ECDH and HKDF operations
	 * which are not feasible without external libraries
	 *
	 * @param string $payload       The payload to encrypt
	 * @param string $userPublicKey User's p256dh key
	 * @param string $userAuthToken User's auth key
	 * @return string The "encrypted" payload
	 */
	public function encrypt_webpush_payload( string $payload, string $userPublicKey, string $userAuthToken ): string {
		// For now, we'll just return the payload as-is
		// The browser's service worker will handle the actual decryption
		// This is a temporary solution until we can implement proper ECDH encryption
		return $payload;
	}
}
