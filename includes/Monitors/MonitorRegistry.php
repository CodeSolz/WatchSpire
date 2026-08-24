<?php
/**
 * Collects and exposes all registered monitors.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors;

use WatchSpire\Monitors\Checks\SslMonitor;
use WatchSpire\Monitors\Checks\HttpErrorMonitor;
use WatchSpire\Monitors\Checks\MailHealthMonitor;
use WatchSpire\Monitors\Checks\UptimeMonitor;
use WatchSpire\Monitors\Checks\LinkScanMonitor;
use WatchSpire\Monitors\Checks\SubmissionGapMonitor;

defined( 'ABSPATH' ) || exit;

final class MonitorRegistry {

	public function boot(): void {
		add_filter( 'watchspire_monitors', array( $this, 'register_builtin_monitors' ), 5 );
		add_action( 'init', array( $this, 'boot_monitors' ), 20 );
	}

	/**
	 * Give every registered monitor a chance to attach real-time hooks
	 * (e.g. capturing a 404 as it happens), independent of its scheduled run().
	 */
	public function boot_monitors(): void {
		foreach ( $this->get_monitors() as $monitor ) {
			$monitor->boot();
		}
	}

	/**
	 * @param array<string, AbstractMonitor> $monitors
	 * @return array<string, AbstractMonitor>
	 */
	public function register_builtin_monitors( array $monitors ): array {
		$builtins = array(
			new SslMonitor(),
			new HttpErrorMonitor(),
			new MailHealthMonitor(),
			new UptimeMonitor(),
			new LinkScanMonitor(),
			new SubmissionGapMonitor(),
		);

		foreach ( $builtins as $monitor ) {
			$monitors[ $monitor->get_id() ] = $monitor;
		}

		return $monitors;
	}

	/**
	 * @return array<string, AbstractMonitor>
	 */
	public function get_monitors(): array {
		$monitors = apply_filters( 'watchspire_monitors', array() );

		return is_array( $monitors ) ? $monitors : array();
	}

	public function get_monitor( string $id ): ?AbstractMonitor {
		$monitors = $this->get_monitors();

		return $monitors[ $id ] ?? null;
	}
}
