<?php
/**
 * Contract every form-builder adapter must implement.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations;

defined( 'ABSPATH' ) || exit;

interface IntegrationInterface {

	/**
	 * Stable identifier, e.g. "cf7", "wpforms".
	 */
	public function get_id(): string;

	/**
	 * Human-readable label for the admin UI.
	 */
	public function get_label(): string;

	/**
	 * Whether the target form builder is active on this site.
	 */
	public function is_active(): bool;

	/**
	 * Register the builder's success/failure hooks. Only called when
	 * is_active() is true.
	 */
	public function boot(): void;
}
