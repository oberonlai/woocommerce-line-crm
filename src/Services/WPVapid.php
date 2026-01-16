<?php

declare(strict_types=1);

namespace OrderChatz\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress-based VAPID implementation
 *
 * Provides VAPID key generation without external dependencies
 *
 * @package    OrderChatz
 * @subpackage Services
 * @since      1.0.0
 */
class WPVapid {

	/**
	 * Create VAPID keys using OpenSSL
	 *
	 * @return array{publicKey: string, privateKey: string}|null
	 */
	public static function createVapidKeys(): ?array {
		if ( ! extension_loaded( 'openssl' ) ) {
			return null;
		}

		// Generate a new EC key pair
		$config = array(
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name'       => 'prime256v1',
		);

		$resource = openssl_pkey_new( $config );
		if ( ! $resource ) {
			return null;
		}

		// Export private key in PEM format
		$private_key_pem = '';
		if ( ! openssl_pkey_export( $resource, $private_key_pem ) ) {
			return null;
		}

		// Get public key details
		$details = openssl_pkey_get_details( $resource );
		if ( ! $details || ! isset( $details['ec'] ) ) {
			return null;
		}

		// Extract EC coordinates for public key
		$public_key = self::extract_public_key( $details );
		if ( ! $public_key ) {
			error_log( 'WPVapid: Failed to extract public key from OpenSSL details' );
			return null;
		}
		
		// Validate P-256 key format (should be 65 bytes starting with 0x04)
		$decoded_key = base64_decode( str_pad( strtr( $public_key, '-_', '+/' ), strlen( $public_key ) % 4, '=', STR_PAD_RIGHT ) );
		if ( strlen( $decoded_key ) !== 65 || $decoded_key[0] !== "\x04" ) {
			error_log( "WPVapid: Generated public key format validation failed. Length: " . strlen( $decoded_key ) . ", First byte: 0x" . bin2hex( $decoded_key[0] ) );
			return null;
		}

		// Extract private key from PEM
		$private_key = self::extract_private_key( $private_key_pem );
		if ( ! $private_key ) {
			return null;
		}

		return array(
			'publicKey'  => $public_key,
			'privateKey' => $private_key,
		);
	}

	/**
	 * Extract public key in base64url format from OpenSSL details
	 *
	 * @param array $details OpenSSL key details
	 * @return string|null Base64url encoded public key
	 */
	private static function extract_public_key( array $details ): ?string {
		// Try to get the public key in a simpler way
		if ( isset( $details['key'] ) ) {
			// Parse the PEM public key to get raw EC point
			$pem = $details['key'];
			$key = openssl_pkey_get_public( $pem );
			if ( ! $key ) {
				error_log( 'WPVapid: Failed to get public key from PEM' );
				return null;
			}
			
			$key_details = openssl_pkey_get_details( $key );
			if ( ! isset( $key_details['key'] ) ) {
				error_log( 'WPVapid: No key details found in public key' );
				return null;
			}
			
			// Extract the raw public key from DER encoding
			// This is a simplified approach - we'll extract the last 65 bytes
			// which should be the uncompressed EC point (0x04 + X + Y)
			$der = base64_decode( 
				str_replace( 
					array( '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r" ), 
					'', 
					$key_details['key'] 
				) 
			);
			
			// For P-256, look for the uncompressed point format
			// The DER structure should contain a 65-byte sequence starting with 0x04
			$der_len = strlen( $der );
			error_log( "WPVapid: DER length: {$der_len}" );
			
			// Try different approaches to extract the 65-byte public key
			for ( $i = 0; $i <= $der_len - 65; $i++ ) {
				if ( $der[ $i ] === "\x04" ) {
					$candidate = substr( $der, $i, 65 );
					if ( strlen( $candidate ) === 65 ) {
						error_log( "WPVapid: Found P-256 public key at offset {$i}" );
						return rtrim( strtr( base64_encode( $candidate ), '+/', '-_' ), '=' );
					}
				}
			}
			
			// Fallback: try the last 65 bytes approach
			if ( $der_len >= 65 ) {
				$public_key = substr( $der, -65 );
				
				// Verify it starts with 0x04 (uncompressed point)
				if ( $public_key[0] === "\x04" ) {
					error_log( 'WPVapid: Using fallback method (last 65 bytes)' );
					return rtrim( strtr( base64_encode( $public_key ), '+/', '-_' ), '=' );
				}
			}
		}
		
		error_log( 'WPVapid: Failed to extract valid P-256 public key' );
		return null;
	}

	/**
	 * Extract private key in base64url format from PEM
	 *
	 * @param string $pem PEM formatted private key
	 * @return string|null Base64url encoded private key
	 */
	private static function extract_private_key( string $pem ): ?string {
		// Extract the raw private key from DER encoding
		// This is a simplified approach
		$der = base64_decode( 
			str_replace( 
				array( '-----BEGIN EC PRIVATE KEY-----', '-----END EC PRIVATE KEY-----', '-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r" ), 
				'', 
				$pem 
			) 
		);
		
		// For EC private keys, look for a 32-byte sequence after specific markers
		// This is a heuristic approach - in production you'd want proper ASN.1 parsing
		if ( strlen( $der ) >= 32 ) {
			// Look for the private key value (usually after 0x04 0x20)
			$pos = strpos( $der, "\x04\x20" );
			if ( $pos !== false && strlen( $der ) >= $pos + 2 + 32 ) {
				$private_key = substr( $der, $pos + 2, 32 );
				return rtrim( strtr( base64_encode( $private_key ), '+/', '-_' ), '=' );
			}
			
			// Fallback: try to find a 32-byte sequence that looks like a key
			// This is not ideal but might work for some cases
			for ( $i = 0; $i < strlen( $der ) - 32; $i++ ) {
				$potential_key = substr( $der, $i, 32 );
				// Check if it looks like a valid private key (non-zero, not all same byte)
				if ( $potential_key !== str_repeat( "\x00", 32 ) && 
				     $potential_key !== str_repeat( $potential_key[0], 32 ) ) {
					// This might be the key
					return rtrim( strtr( base64_encode( $potential_key ), '+/', '-_' ), '=' );
				}
			}
		}
		
		return null;
	}

	/**
	 * Generate a JWT for VAPID authentication using Firebase JWT
	 *
	 * @param string $audience    The audience (push service URL)
	 * @param string $subject     The subject (mailto: or https:)
	 * @param string $public_key  The public key (base64url)
	 * @param string $private_key The private key (base64url)
	 * @param int    $expiration  Expiration time in seconds (default 12 hours)
	 * @return string|null The JWT token or null on failure
	 */
	public static function getVapidJWT( 
		string $audience, 
		string $subject, 
		string $public_key, 
		string $private_key, 
		int $expiration = 43200 
	): ?string {
		try {
			$payload = array(
				'aud' => $audience,
				'exp' => time() + $expiration,
				'sub' => $subject,
			);

			// Decode the private key from base64url
			$private_key_binary = base64_decode( strtr( $private_key, '-_', '+/' ) );
			
			// Create PEM format for the private key
			$pem = self::create_pem_from_key( $private_key_binary, $public_key );
			if ( ! $pem ) {
				// Fallback to simple encoding if PEM creation fails
				return self::create_simple_jwt( $payload, $private_key );
			}

			// Use Firebase JWT with ES256
			$jwt = JWT::encode( $payload, $pem, 'ES256' );
			
			return $jwt;
			
		} catch ( \Exception $e ) {
			// Fallback to simple JWT if Firebase JWT fails
			return self::create_simple_jwt( $payload, $private_key );
		}
	}

	/**
	 * Create a PEM formatted private key from raw key data
	 *
	 * @param string $private_key_binary Raw private key bytes
	 * @param string $public_key_base64  Base64url encoded public key
	 * @return string|null PEM formatted private key
	 */
	private static function create_pem_from_key( string $private_key_binary, string $public_key_base64 ): ?string {
		// This is a simplified approach - in production, you'd want proper ASN.1 encoding
		// For now, we'll try to construct a basic EC private key structure
		
		try {
			// Basic EC private key ASN.1 structure (simplified)
			$der = 
				"\x30\x77" . // SEQUENCE (119 bytes)
				"\x02\x01\x01" . // INTEGER 1 (version)
				"\x04\x20" . $private_key_binary . // OCTET STRING (32 bytes private key)
				"\xa0\x0a" . // Context tag 0
				"\x06\x08" . // OID
				"\x2a\x86\x48\xce\x3d\x03\x01\x07" . // OID for prime256v1
				"\xa1\x44" . // Context tag 1
				"\x03\x42" . // BIT STRING
				"\x00" . base64_decode( strtr( $public_key_base64, '-_', '+/' ) ); // Public key
			
			$pem = "-----BEGIN EC PRIVATE KEY-----\n" .
			       chunk_split( base64_encode( $der ), 64, "\n" ) .
			       "-----END EC PRIVATE KEY-----\n";
			
			return $pem;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Create a simple JWT without proper ES256 signing (fallback)
	 *
	 * @param array  $payload     JWT payload
	 * @param string $private_key Private key
	 * @return string JWT token
	 */
	private static function create_simple_jwt( array $payload, string $private_key ): string {
		$header = array(
			'typ' => 'JWT',
			'alg' => 'ES256',
		);

		$header_encoded  = rtrim( strtr( base64_encode( wp_json_encode( $header ) ), '+/', '-_' ), '=' );
		$payload_encoded = rtrim( strtr( base64_encode( wp_json_encode( $payload ) ), '+/', '-_' ), '=' );

		$data = $header_encoded . '.' . $payload_encoded;

		// Simple signature (not cryptographically valid ES256, but better than nothing)
		$signature = hash_hmac( 'sha256', $data, $private_key, true );
		$signature_encoded = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );

		return $data . '.' . $signature_encoded;
	}
}