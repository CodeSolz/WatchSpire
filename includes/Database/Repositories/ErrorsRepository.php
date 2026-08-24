<?php
/**
 * Repository for the errors (404/5xx) aggregate table.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class ErrorsRepository extends AbstractRepository {

	protected string $table = 'errors';

	/**
	 * Record a hit, aggregating by url+status. Never one row per hit.
	 */
	public function record_hit( string $url, int $status, string $referrer = '', string $user_agent = '' ): void {
		$wpdb     = $this->wpdb();
		$table    = $this->table_name();
		$url      = mb_substr( $url, 0, 500 );
		$url_hash = md5( $url );
		$now      = $this->now();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (url, url_hash, status, referrer, user_agent, count, first_seen, last_seen)
				VALUES (%s, %s, %d, %s, %s, 1, %s, %s)
				ON DUPLICATE KEY UPDATE count = count + 1, last_seen = VALUES(last_seen), referrer = VALUES(referrer), user_agent = VALUES(user_agent)",
				$url,
				$url_hash,
				$status,
				mb_substr( $referrer, 0, 500 ),
				mb_substr( $user_agent, 0, 255 ),
				$now,
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function over_threshold_last_24h( int $threshold ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 404 AND count >= %d AND last_seen >= %s ORDER BY count DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$threshold,
				$since
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	public function recent_5xx( int $limit = 20 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status >= 500 ORDER BY last_seen DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	public function top( int $limit = 20 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY last_seen DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	/**
	 * Top 404s seen within the last N days ending at $end (or now, if
	 * omitted), ordered by hit count.
	 *
	 * @param string|null $end MySQL datetime the window ends at. Defaults to now — the dashboard's custom date range is the only caller that passes an explicit value.
	 */
	public function top_404s_since( int $days, int $limit = 10, ?string $end = null ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', ( $end ? strtotime( $end ) : time() ) - ( $days * DAY_IN_SECONDS ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 404 AND last_seen >= %s ORDER BY count DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since,
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}
}
