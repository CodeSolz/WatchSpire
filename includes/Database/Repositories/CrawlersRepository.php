<?php
/**
 * Repository for the crawlers (AI bot) daily aggregate table.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class CrawlersRepository extends AbstractRepository {

	protected string $table = 'crawlers';

	/**
	 * Increment today's aggregate row for a bot, creating it if needed.
	 */
	public function record_hit( string $bot, int $status ): void {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$date  = gmdate( 'Y-m-d' );

		$bucket = 'status_2xx';
		if ( $status >= 500 ) {
			$bucket = 'status_5xx';
		} elseif ( $status >= 400 ) {
			$bucket = 'status_4xx';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table/$bucket are internal, never user input.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (bot, date, hits, status_2xx, status_4xx, status_5xx)
				VALUES (%s, %s, 1, %d, %d, %d)
				ON DUPLICATE KEY UPDATE hits = hits + 1, {$bucket} = {$bucket} + 1",
				$bot,
				$date,
				'status_2xx' === $bucket ? 1 : 0,
				'status_4xx' === $bucket ? 1 : 0,
				'status_5xx' === $bucket ? 1 : 0
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * First calendar day (UTC) of an inclusive N-day window ending today.
	 *
	 * `date` is a DATE column, so a naive `today - N days` bound spans
	 * N+1 calendar days once today's own row exists - which made
	 * totals_by_bot(1), the dashboard's "Today" card, quietly report
	 * today *and* yesterday. Subtracting N-1 makes "last N days" mean
	 * exactly N days, and makes 1 mean today only.
	 *
	 * @param int $days Length of the window in calendar days.
	 */
	private function window_start( int $days ): string {
		return gmdate( 'Y-m-d', time() - ( max( 0, $days - 1 ) * DAY_IN_SECONDS ) );
	}

	public function trend( int $days = 30 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = $this->window_start( $days );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, date, hits, status_2xx, status_4xx, status_5xx FROM {$table} WHERE date >= %s ORDER BY date ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	public function totals_by_bot( int $days = 30 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = $this->window_start( $days );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, SUM(hits) AS hits, SUM(status_2xx) AS s2, SUM(status_4xx) AS s4, SUM(status_5xx) AS s5
				FROM {$table} WHERE date >= %s GROUP BY bot ORDER BY hits DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		return $rows ?? array();
	}
}
