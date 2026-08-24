<?php
/**
 * Daily retention cleanup across all WatchSpire tables.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Support;

use WatchSpire\Database\Repositories\AlertsRepository;
use WatchSpire\Database\Repositories\ChangelogRepository;
use WatchSpire\Database\Repositories\ChecksRepository;
use WatchSpire\Database\Repositories\CrawlersRepository;
use WatchSpire\Database\Repositories\ErrorsRepository;
use WatchSpire\Database\Repositories\LinksRepository;
use WatchSpire\Database\Repositories\SubmissionsRepository;
use WatchSpire\Scheduler\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Cleanup {

	private const DEFAULT_MONITOR_RETENTION_DAYS = 30;
	private const DEFAULT_ALERT_RETENTION_DAYS   = 90;

	public function boot(): void {
		add_action( Scheduler::HOOK_DAILY_CLEANUP, array( $this, 'run' ) );
	}

	public function run(): void {
		$monitor_days = max( 7, (int) Settings::get( 'retention_monitor_days', self::DEFAULT_MONITOR_RETENTION_DAYS ) );
		$alert_days   = max( 7, (int) Settings::get( 'retention_alert_days', self::DEFAULT_ALERT_RETENTION_DAYS ) );

		/**
		 * Changelog retention, filterable independently of the alert
		 * retention setting so a site can keep a longer change history
		 * than it keeps alerts.
		 */
		$changelog_days = max( $alert_days, (int) apply_filters( 'watchspire_changelog_retention_days', $alert_days ) );

		( new ChecksRepository() )->purge_older_than( $monitor_days );
		( new SubmissionsRepository() )->purge_older_than( $monitor_days );
		( new ErrorsRepository() )->purge_older_than( $monitor_days, 'last_seen' );
		( new CrawlersRepository() )->purge_older_than( $monitor_days, 'date' );
		( new LinksRepository() )->purge_older_than( $monitor_days, 'created_at' );

		( new ChangelogRepository() )->purge_older_than( $changelog_days );
		( new AlertsRepository() )->purge_older_than( $alert_days );
	}
}
