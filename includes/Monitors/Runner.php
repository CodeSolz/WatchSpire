<?php
/**
 * Executes due monitors on schedule and records results.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors;

use WatchSpire\Alerts\AlertManager;
use WatchSpire\Alerts\Failure;
use WatchSpire\Database\Repositories\ChecksRepository;
use WatchSpire\Scheduler\Scheduler;
use WatchSpire\Support\Settings as SettingsSupport;

defined( 'ABSPATH' ) || exit;

final class Runner {

	private MonitorRegistry $registry;
	private AlertManager $alerts;
	private ChecksRepository $checks;

	public function __construct( MonitorRegistry $registry, AlertManager $alerts ) {
		$this->registry = $registry;
		$this->alerts   = $alerts;
		$this->checks   = new ChecksRepository();
	}

	public function boot(): void {
		add_action( Scheduler::HOOK_RUN_MONITORS, array( $this, 'run_due_monitors' ) );
	}

	public function run_due_monitors(): void {
		if ( defined( 'WATCHSPIRE_DISABLE_SCANS' ) && WATCHSPIRE_DISABLE_SCANS ) {
			return;
		}

		foreach ( $this->registry->get_monitors() as $monitor ) {
			if ( ! $this->is_enabled( $monitor ) ) {
				continue;
			}

			if ( ! $monitor->is_available() ) {
				continue;
			}

			if ( ! $this->is_due( $monitor ) ) {
				continue;
			}

			$this->run_monitor( $monitor );
		}
	}

	/**
	 * Run a single monitor immediately regardless of schedule (used by
	 * "check now" UI actions and by other monitors' composition).
	 */
	public function run_monitor( AbstractMonitor $monitor ): Result {
		$start = microtime( true );

		try {
			$result = $monitor->run();
		} catch ( \Throwable $e ) {
			$result = Result::fail(
				/* translators: %s: error message */
				sprintf( __( 'Monitor crashed: %s', 'watchspire' ), $e->getMessage() )
			);
		}

		if ( 0 === $result->duration_ms ) {
			$result->duration_ms = (int) round( ( microtime( true ) - $start ) * 1000 );
		}

		$this->checks->insert( $monitor->get_id(), $result->status, $result->message, $result->detail, $result->duration_ms );

		do_action( 'watchspire_check_completed', $monitor->get_id(), $result );

		if ( $result->is_failing() ) {
			$failure = new Failure( $monitor->get_id(), $monitor->get_label(), $result->message, $result->detail );
			do_action( 'watchspire_failure_detected', $failure );
			$this->alerts->handle_failure( $failure );
		} else {
			$this->maybe_handle_recovery( $monitor );
		}

		return $result;
	}

	/**
	 * Fires the recovery alert once a monitor's most recent checks form an
	 * unbroken passing streak at least as long as the configured
	 * "auto-resolve after" threshold, and the check immediately before that
	 * streak was a failure — so the alert fires exactly once per recovery,
	 * not on every subsequent passing check. With auto-resolve disabled,
	 * a single passing check after a failure is enough (threshold of 1),
	 * matching the previous behavior.
	 */
	private function maybe_handle_recovery( AbstractMonitor $monitor ): void {
		$required = SettingsSupport::get( 'auto_resolve_enabled', true )
			? max( 1, absint( SettingsSupport::get( 'auto_resolve_after', 2 ) ) )
			: 1;

		$recent = $this->checks->recent( $monitor->get_id(), $required + 1 );

		if ( count( $recent ) < $required ) {
			return;
		}

		for ( $i = 0; $i < $required; $i++ ) {
			if ( Result::FAIL === $recent[ $i ]['status'] ) {
				return;
			}
		}

		$preceding = $recent[ $required ] ?? null;
		if ( ! $preceding || Result::FAIL !== $preceding['status'] ) {
			return;
		}

		$this->alerts->handle_recovery( $monitor->get_id(), $monitor->get_label() );
	}

	private function is_enabled( AbstractMonitor $monitor ): bool {
		$enabled = SettingsSupport::get( 'monitors_enabled', array() );

		return ! isset( $enabled[ $monitor->get_id() ] ) || (bool) $enabled[ $monitor->get_id() ];
	}

	private function is_due( AbstractMonitor $monitor ): bool {
		$latest = $this->checks->latest( $monitor->get_id() );

		if ( ! $latest ) {
			return true;
		}

		$interval  = $this->get_interval( $monitor );
		$last_time = strtotime( $latest['created_at'] . ' UTC' );

		return false === $last_time || ( time() - $last_time ) >= $interval;
	}

	private function get_interval( AbstractMonitor $monitor ): int {
		$intervals = SettingsSupport::get( 'monitor_intervals', array() );

		if ( isset( $intervals[ $monitor->get_id() ] ) && (int) $intervals[ $monitor->get_id() ] > 0 ) {
			return (int) $intervals[ $monitor->get_id() ];
		}

		return $monitor->get_default_schedule();
	}
}
