<?php

declare(strict_types=1);

namespace OrderChatz\Database\Message;

use OrderChatz\Database\ErrorHandler;
use OrderChatz\Util\Logger;

/**
 * Template Table Class
 *
 * Handles CRUD operations for the otz_templates table.
 * Manages customer service message templates with code shortcuts.
 *
 * @package    OrderChatz
 * @subpackage Database
 * @since      1.0.8
 */
class TableTemplate {

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
	 * Error handler instance
	 *
	 * @var ErrorHandler|null
	 */
	private ?ErrorHandler $error_handler;

	/**
	 * Templates table name
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor
	 *
	 * @param \wpdb             $wpdb WordPress database object.
	 * @param \WC_Logger|null   $logger Logger instance.
	 * @param ErrorHandler|null $error_handler Error handler instance.
	 */
	public function __construct( \wpdb $wpdb, ?\WC_Logger $logger = null, ?ErrorHandler $error_handler = null ) {
		$this->wpdb          = $wpdb;
		$this->logger        = $logger;
		$this->error_handler = $error_handler;
		$this->table_name    = $wpdb->prefix . 'otz_templates';
	}

	/**
	 * Create a new template
	 *
	 * @param string $content Template content.
	 * @param string $code Template shortcode.
	 * @return int|false Template ID on success, false on failure
	 */
	public function create_template( string $content, string $code ): int|false {
		try {
			if ( empty( $content ) || empty( $code ) ) {
				return false;
			}

			if ( $this->code_exists( $code ) ) {
				return false;
			}

			$result = $this->wpdb->insert(
				$this->table_name,
				array(
					'content'    => $content,
					'code'       => $code,
					'created_at' => current_time( 'mysql' ),
				),
				array(
					'%s', // content.
					'%s', // code.
					'%s', // created_at.
				)
			);

			if ( false === $result ) {
				Logger::error( 'Failed to create template: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return $this->wpdb->insert_id;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in create_template: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * Get a single template by ID
	 *
	 * @param int $id Template ID.
	 * @return array|null Template data or null if not found
	 */
	public function get_template( int $id ): ?array {
		try {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			);

			$result = $this->wpdb->get_row( $sql, ARRAY_A );

			return $result ?: null;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_template: ' . $e->getMessage(), array(), 'otz' );
			return null;
		}
	}

	/**
	 * Get template by code
	 *
	 * @param string $code Template code.
	 * @return array|null Template data or null if not found
	 */
	public function get_template_by_code( string $code ): ?array {
		try {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE code = %s",
				$code
			);

			$result = $this->wpdb->get_row( $sql, ARRAY_A );

			return $result ?: null;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_template_by_code: ' . $e->getMessage(), array(), 'otz' );
			return null;
		}
	}

	/**
	 * Get all templates
	 *
	 * @param string $order_by Column to order by (default: created_at).
	 * @param string $order Direction to order (ASC|DESC, default: DESC).
	 * @return array Array of templates
	 */
	public function get_all_templates( string $order_by = 'created_at', string $order = 'DESC' ): array {
		try {
			$allowed_columns = array( 'id', 'code', 'created_at' );
			if ( ! in_array( $order_by, $allowed_columns, true ) ) {
				$order_by = 'created_at';
			}

			$order = strtoupper( $order );
			if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
				$order = 'DESC';
			}

			$sql = "SELECT * FROM {$this->table_name} ORDER BY {$order_by} {$order}";

			$results = $this->wpdb->get_results( $sql, ARRAY_A );

			return $results ?: array();

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_all_templates: ' . $e->getMessage(), array(), 'otz' );
			return array();
		}
	}

	/**
	 * Update a template
	 *
	 * @param int    $id Template ID.
	 * @param string $content New content.
	 * @param string $code New code.
	 * @return bool True on success, false on failure
	 */
	public function update_template( int $id, string $content, string $code ): bool {
		try {
			if ( empty( $content ) || empty( $code ) ) {
				return false;
			}

			if ( ! $this->template_exists( $id ) ) {
				return false;
			}

			$existing_template = $this->get_template_by_code( $code );
			if ( $existing_template && (int) $existing_template['id'] !== $id ) {
				return false;
			}

			$result = $this->wpdb->update(
				$this->table_name,
				array(
					'content' => $content,
					'code'    => $code,
				),
				array( 'id' => $id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				Logger::error( 'Failed to update template: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in update_template: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * Delete a template
	 *
	 * @param int $id Template ID.
	 * @return bool True on success, false on failure
	 */
	public function delete_template( int $id ): bool {
		try {
			if ( ! $this->template_exists( $id ) ) {
				return false;
			}

			$result = $this->wpdb->delete(
				$this->table_name,
				array( 'id' => $id ),
				array( '%d' )
			);

			if ( false === $result ) {
				Logger::error( 'Failed to delete template: ' . $this->wpdb->last_error, array(), 'otz' );
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in delete_template: ' . $e->getMessage(), array(), 'otz' );
			return false;
		}
	}

	/**
	 * Search templates by content or code
	 *
	 * @param string $search_term Search term.
	 * @param int    $limit Maximum number of results (default: 10).
	 * @return array Array of matching templates
	 */
	public function search_templates( string $search_term, int $limit = 10 ): array {
		try {
			if ( empty( $search_term ) ) {
				return array();
			}

			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE content LIKE %s OR code LIKE %s
				ORDER BY created_at DESC
				LIMIT %d",
				'%' . $this->wpdb->esc_like( $search_term ) . '%',
				'%' . $this->wpdb->esc_like( $search_term ) . '%',
				$limit
			);

			$results = $this->wpdb->get_results( $sql, ARRAY_A );

			return $results ?: array();

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in search_templates: ' . $e->getMessage(), array(), 'otz' );
			return array();
		}
	}

	/**
	 * Get templates count
	 *
	 * @return int Total number of templates
	 */
	public function get_templates_count(): int {
		try {
			$sql = "SELECT COUNT(*) FROM {$this->table_name}";

			$count = $this->wpdb->get_var( $sql );

			return (int) $count;

		} catch ( \Exception $e ) {
			Logger::error( 'Exception in get_templates_count: ' . $e->getMessage(), array(), 'otz' );
			return 0;
		}
	}

	/**
	 * Check if a template exists
	 *
	 * @param int $id Template ID
	 * @return bool True if exists, false otherwise
	 */
	private function template_exists( int $id ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
			$id
		);

		return (int) $this->wpdb->get_var( $sql ) > 0;
	}

	/**
	 * Check if a code already exists
	 *
	 * @param string $code Template code
	 * @return bool True if exists, false otherwise
	 */
	private function code_exists( string $code ): bool {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE code = %s",
			$code
		);

		return (int) $this->wpdb->get_var( $sql ) > 0;
	}

}
