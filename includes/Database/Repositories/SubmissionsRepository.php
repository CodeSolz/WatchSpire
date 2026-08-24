<?php
/**
 * Repository for the submissions table.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class SubmissionsRepository extends AbstractRepository {

	protected string $table = 'submissions';

	public function insert(
		string $integration,
		string $form_id,
		?string $form_name,
		bool $delivered,
		?string $error = null,
		bool $is_synthetic = false
	): int {
		$wpdb = $this->wpdb();

		$wpdb->insert(
			$this->table_name(),
			array(
				'integration'  => $integration,
				'form_id'      => $form_id,
				'form_name'    => $form_name,
				'delivered'    => $delivered ? 1 : 0,
				'error'        => $error,
				'is_synthetic' => $is_synthetic ? 1 : 0,
				'created_at'   => $this->now(),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Daily submission counts for a form over the last N days (real submissions only).
	 *
	 * @return array<string,int> date => count
	 */
	public function daily_counts( string $integration, string $form_id, int $days = 30 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d 00:00:00', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table}
				WHERE integration = %s AND form_id = %s AND is_synthetic = 0 AND created_at >= %s
				GROUP BY DATE(created_at)",
				$integration,
				$form_id,
				$since
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		foreach ( $rows ?? array() as $row ) {
			$out[ $row['d'] ] = (int) $row['c'];
		}

		return $out;
	}

	public function distinct_forms(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			"SELECT DISTINCT integration, form_id, form_name FROM {$table} WHERE is_synthetic = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
			ARRAY_A
		);

		return $rows ?? array();
	}

	public function last_submission_at( string $integration, string $form_id ): ?string {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(created_at) FROM {$table} WHERE integration = %s AND form_id = %s AND is_synthetic = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$integration,
				$form_id
			)
		);

		return $value ?? null;
	}

	/**
	 * Total/delivered/failed real-submission counts in the last N days
	 * ending at $end (or now, if omitted), across every form.
	 *
	 * @param string|null $end MySQL datetime the window ends at. Defaults to now — the dashboard's custom date range is the only caller that passes an explicit value.
	 * @return array{total:int,delivered:int,failed:int}
	 */
	public function totals_since( int $days, ?string $end = null ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', ( $end ? strtotime( $end ) : time() ) - ( $days * DAY_IN_SECONDS ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(delivered = 1) AS delivered, SUM(delivered = 0) AS failed FROM {$table} WHERE is_synthetic = 0 AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		return array(
			'total'     => (int) ( $row['total'] ?? 0 ),
			'delivered' => (int) ( $row['delivered'] ?? 0 ),
			'failed'    => (int) ( $row['failed'] ?? 0 ),
		);
	}

	/**
	 * Day-by-day total real-submission counts for the last N days
	 * (today inclusive), across every form. Missing days are zero-filled.
	 *
	 * @return array<string,int> keyed by Y-m-d
	 */
	public function daily_totals( int $days ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d 00:00:00', time() - ( ( $days - 1 ) * DAY_IN_SECONDS ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS c FROM {$table} WHERE is_synthetic = 0 AND created_at >= %s GROUP BY DATE(created_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		$by_date = array();
		foreach ( $rows ?? array() as $row ) {
			$by_date[ $row['d'] ] = (int) $row['c'];
		}

		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date            = gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );
			$series[ $date ] = $by_date[ $date ] ?? 0;
		}

		return $series;
	}

	public function recent_failures( int $limit = 20 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE delivered = 0 AND is_synthetic = 0 ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}
}
