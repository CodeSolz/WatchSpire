<?php
/**
 * Base class every monitor extends.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors;

defined( 'ABSPATH' ) || exit;

abstract class AbstractMonitor {

	/**
	 * Stable, unique identifier, e.g. "ssl", "http_errors".
	 */
	abstract public function get_id(): string;

	/**
	 * Human-readable label for the admin UI.
	 */
	abstract public function get_label(): string;

	/**
	 * Execute the check and return a Result.
	 */
	abstract public function run(): Result;

	/**
	 * Default interval between runs, in seconds.
	 */
	public function get_default_schedule(): int {
		return DAY_IN_SECONDS;
	}

	/**
	 * Whether this monitor can run at all right now (e.g. required
	 * conditions/plugins present). Does not mean "enabled" — that is a
	 * user setting checked separately by the Runner.
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Short description shown in Settings under this monitor's toggle.
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Called once per request (on `init`, after the registry resolves the
	 * monitor list) so monitors that need real-time hooks — capturing a
	 * 404 as it happens, rather than only on the scheduled run() — can
	 * register them. No-op by default.
	 */
	public function boot(): void {}
}
