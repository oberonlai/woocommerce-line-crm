<?php

declare(strict_types=1);

namespace OrderChatz\Database\Upgrades;

/**
 * Upgrade Interface
 *
 * Defines the contract for all database upgrade classes.
 * Each upgrade version must implement this interface.
 *
 * @package    OrderChatz
 * @subpackage Database\Upgrades
 * @since      1.0.3
 */
interface UpgradeInterface {

	/**
	 * Execute the upgrade.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function execute(): bool;

	/**
	 * Get the target version for this upgrade.
	 *
	 * @return string Version number (e.g., '1.0.1').
	 */
	public function get_version(): string;

	/**
	 * Get the description of this upgrade.
	 *
	 * @return string Human-readable description.
	 */
	public function get_description(): string;
}