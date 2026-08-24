<?php
/**
 * Repository for the checks table.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class ChecksRepository extends AbstractRepository {

	protected string $table = 'checks';

	public function insert( string $monitor_id, string $status, string $message, array $detail, int $duration_ms ): int {
		$wpdb = $this->wpdb();

		$wpdb->insert(
			$this->table_name(),
			array(
				'monitor_id'  => $monitor_id,
				'status'      => $status,
				'message'     => $message,
				'detail'      => wp_json_encode( $detail ),
				'duration_ms' => $duration_ms,
				'created_at'  => $this->now(),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Latest result for a monitor.
	 */
	public function latest( string $monitor_id ): ?array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE monitor_id = %s ORDER BY created_at DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$monitor_id
			),
			ARRAY_A
		);

		return $row ?? null;
	}

	/**
	 * Latest result per monitor, one row each.
	 */
	public function latest_per_monitor(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$rows = $wpdb->get_results(
			"SELECT c1.* FROM {$table} c1
			INNER JOIN (
				SELECT monitor_id, MAX(id) AS max_id FROM {$table} GROUP BY monitor_id
			) c2 ON c1.monitor_id = c2.monitor_id AND c1.id = c2.max_id",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ?? array();
	}

	public function recent( string $monitor_id, int $limit = 20 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE monitor_id = %s ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$monitor_id,
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	/**
	 * Fail/warn results in the last N days, most recent first.
	 */
	public function failures_since( int $days ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('fail','warn') AND created_at >= %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	/**
	 * Pass rate (0-100) for a monitor over the last N days.
	 */
	public function pass_rate_since( string $monitor_id, int $days ): ?float {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(status = 'pass') AS passes FROM {$table} WHERE monitor_id = %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$monitor_id,
				$since
			),
			ARRAY_A
		);

		if ( ! $row || 0 === (int) $row['total'] ) {
			return null;
		}

		return round( ( (int) $row['passes'] / (int) $row['total'] ) * 100, 1 );
	}

	public function recent_failures( int $limit = 20 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'fail' ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	/**
	 * Aggregate totals (count + status breakdown + avg duration) for
	 * checks created in [$from, $to). Used to compare one period against
	 * the equivalent prior period for "up/down from last week" stats.
	 *
	 * @return array{total:int,pass:int,warn:int,fail:int,avg_duration:float}
	 */
	public function totals_between( string $from, string $to, ?string $monitor_id = null ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$where  = 'created_at >= %s AND created_at < %s';
		$params = array( $from, $to );

		if ( $monitor_id ) {
			$where   .= ' AND monitor_id = %s';
			$params[] = $monitor_id;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				// $table comes from $wpdb->prefix and $where is assembled above
				// from string literals only; every user-supplied value is a %s
				// placeholder filled from $params. The placeholder sniff can't
				// see into $where, hence the second ignore.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"SELECT COUNT(*) AS total, SUM(status = 'pass') AS pass, SUM(status = 'warn') AS warn, SUM(status = 'fail') AS fail, AVG(duration_ms) AS avg_duration FROM {$table} WHERE {$where}",
				$params
			),
			ARRAY_A
		);

		return array(
			'total'        => (int) ( $row['total'] ?? 0 ),
			'pass'         => (int) ( $row['pass'] ?? 0 ),
			'warn'         => (int) ( $row['warn'] ?? 0 ),
			'fail'         => (int) ( $row['fail'] ?? 0 ),
			'avg_duration' => round( (float) ( $row['avg_duration'] ?? 0 ), 1 ),
		);
	}

	/**
	 * Day-by-day totals for the last N days ending at $end (or today, if
	 * omitted), for sparklines and the multi-series "checks over time"
	 * chart. Days with no checks are included with all-zero values.
	 *
	 * @param string|null $end MySQL datetime the window ends at (inclusive day). Defaults to now — the dashboard's custom date range is the only caller that passes an explicit value.
	 * @return array<string, array{total:int,pass:int,warn:int,fail:int,avg_duration:float}> keyed by Y-m-d
	 */
	public function daily_series( int $days, ?string $monitor_id = null, ?string $end = null ): array {
		$wpdb   = $this->wpdb();
		$table  = $this->table_name();
		$end_ts = $end ? strtotime( $end ) : time();
		$since  = gmdate( 'Y-m-d 00:00:00', $end_ts - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		$where  = 'created_at >= %s';
		$params = array( $since );

		if ( $monitor_id ) {
			$where   .= ' AND monitor_id = %s';
			$params[] = $monitor_id;
		}

		// Same shape as totals_between(): literal-only $where holding %s
		// placeholders, values supplied through $params, $table from
		// $wpdb->prefix. Disabled as a block because the interpolation falls on
		// the second line of the string, which a single-line ignore misses.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS total, SUM(status = 'pass') AS pass, SUM(status = 'warn') AS warn, SUM(status = 'fail') AS fail, AVG(duration_ms) AS avg_duration
				FROM {$table} WHERE {$where} GROUP BY DATE(created_at)",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$by_date = array();
		foreach ( $rows ?? array() as $row ) {
			$by_date[ $row['d'] ] = array(
				'total'        => (int) $row['total'],
				'pass'         => (int) $row['pass'],
				'warn'         => (int) $row['warn'],
				'fail'         => (int) $row['fail'],
				'avg_duration' => round( (float) $row['avg_duration'], 1 ),
			);
		}

		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date = gmdate( 'Y-m-d', $end_ts - ( $i * DAY_IN_SECONDS ) );

			$series[ $date ] = $by_date[ $date ] ?? array(
				'total'        => 0,
				'pass'         => 0,
				'warn'         => 0,
				'fail'         => 0,
				'avg_duration' => 0.0,
			);
		}

		return $series;
	}
}
