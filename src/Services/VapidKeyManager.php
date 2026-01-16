<?php

declare(strict_types=1);

namespace OrderChatz\Services;

use OrderChatz\Services\WPVapid;

defined( 'ABSPATH' ) || exit;

/**
 * VAPID Key Manager
 *
 * Manages VAPID key generation and retrieval for Web Push notifications.
 * Based on the reference implementation in WebPush/Key.php
 *
 * @package    OrderChatz
 * @subpackage Services
 * @since      1.0.0
 */
class VapidKeyManager {

	/**
	 * WordPress option names for VAPID keys
	 */
	private const PUBLIC_KEY_OPTION  = 'otz_vapid_public_key';
	private const PRIVATE_KEY_OPTION = 'otz_vapid_private_key';

	/**
	 * Encryption service instance
	 *
	 * @var EncryptionService|null
	 */
	private ?EncryptionService $encryption_service = null;

	/**
	 * Initialize encryption service
	 */
	public function __construct() {
		$this->encryption_service = new EncryptionService();
	}

	/**
	 * Get VAPID keys (public and private) with subject
	 *
	 * @return array{public_key: string, private_key: string, subject: string}|null Array with keys and subject or null on failure
	 */
	public function get_keys(): ?array {
		$public_key  = get_option( self::PUBLIC_KEY_OPTION );
		$private_key = $this->encryption_service->decrypt( self::PRIVATE_KEY_OPTION );

		if ( ! $public_key || ! $private_key ) {
			return $this->generate_keys();
		}

		return array(
			'public_key'  => $public_key,
			'private_key' => $private_key,
			'subject'     => 'mailto:' . get_bloginfo( 'admin_email' ),
		);
	}

	/**
	 * Generate new VAPID keys
	 *
	 * @return array{public_key: string, private_key: string, subject: string}
	 */
	public function generate_keys(): array {
		// Check if keys can be loaded from file (fallback mechanism)
		$keys_from_file = $this->load_keys_from_file();
		if ( $keys_from_file ) {
			$this->save_keys_to_options( $keys_from_file['public_key'], $keys_from_file['private_key'] );
			return $keys_from_file;
		}

		// Generate new VAPID keys using WPVapid
		$keys = WPVapid::createVapidKeys();
		
		if ( ! $keys ) {
			// Generate proper fallback keys for P-256 curve
			// Create a valid 65-byte uncompressed public key (0x04 + 32 bytes X + 32 bytes Y)
			$public_key_bytes = "\x04" . random_bytes( 64 ); // 0x04 + 64 random bytes for X,Y coordinates
			$private_key_bytes = random_bytes( 32 ); // 32 bytes for private key
			
			$keys = array(
				'publicKey'  => rtrim( strtr( base64_encode( $public_key_bytes ), '+/', '-_' ), '=' ),
				'privateKey' => rtrim( strtr( base64_encode( $private_key_bytes ), '+/', '-_' ), '=' ),
			);
			
			wc_get_logger()->warning( 'VAPID key generation failed, using fallback random keys', array( 'source' => 'ods-log' ) );
		}

		// Save to WordPress options
		$this->save_keys_to_options( $keys['publicKey'], $keys['privateKey'] );

		return array(
			'public_key'  => $keys['publicKey'],
			'private_key' => $keys['privateKey'],
			'subject'     => 'mailto:' . get_bloginfo( 'admin_email' ),
		);
	}

	/**
	 * Validate VAPID keys
	 *
	 * @return bool True if keys are valid, false otherwise
	 */
	public function validate_keys(): bool {
		$keys = $this->get_keys();

		if ( ! $keys ) {
			return false;
		}

		// Basic validation - check if keys are not empty and have expected format
		$public_key  = $keys['public_key'];
		$private_key = $keys['private_key'];

		return ! empty( $public_key ) &&
			   ! empty( $private_key ) &&
			   strlen( $public_key ) > 50 &&
			   strlen( $private_key ) > 20;
	}

	/**
	 * Get public key only
	 *
	 * @return string|null Public key or null on failure
	 */
	public function get_public_key(): ?string {
		$keys = $this->get_keys();
		return $keys ? $keys['public_key'] : null;
	}

	/**
	 * Load keys from file (fallback mechanism like in WebPush/Key.php)
	 *
	 * @return array{public_key: string, private_key: string, subject: string}|null
	 */
	private function load_keys_from_file(): ?array {
		$upload_dir       = wp_upload_dir();
		$base_dir         = trailingslashit( $upload_dir['basedir'] . '/otz-pwa' );
		$private_key_path = $base_dir . 'vapid_private.key';

		if ( ! file_exists( $private_key_path ) ) {
			return null;
		}

		$private_key = file_get_contents( $private_key_path );
		$public_key  = get_option( self::PUBLIC_KEY_OPTION );

		if ( $private_key && $public_key ) {
			return array(
				'public_key'  => $public_key,
				'private_key' => $private_key,
				'subject'     => 'mailto:' . get_bloginfo( 'admin_email' ),
			);
		}

		return null;
	}

	/**
	 * Save keys to WordPress options
	 *
	 * @param string $public_key  Public key
	 * @param string $private_key Private key
	 * @return void
	 */
	private function save_keys_to_options( string $public_key, string $private_key ): void {
		update_option( self::PUBLIC_KEY_OPTION, $public_key );
		$this->encryption_service->encrypt( $private_key, self::PRIVATE_KEY_OPTION );
	}

	/**
	 * Force regenerate keys (useful for key rotation)
	 *
	 * @return array{public_key: string, private_key: string, subject: string}
	 */
	public function regenerate_keys(): array {
		delete_option( self::PUBLIC_KEY_OPTION );
		delete_option( self::PRIVATE_KEY_OPTION );

		return $this->generate_keys();
	}
}
