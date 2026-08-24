<?php
/**
 * Repository for the alerts table (sent-alert log, dedupe + rate limiting).
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class AlertsRepository extends AbstractRepository {

	protected string $table = 'alerts';

	public function insert( string $channel, string $subject, ?string $fingerprint, string $status ): int {
		$wpdb = $this->wpdb();

		$wpdb->insert(
			$this->table_name(),
			array(
				'channel'     => $channel,
				'subject'     => $subject,
				'fingerprint' => $fingerprint,
				'status'      => $status,
				'created_at'  => $this->now(),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Was an alert with this fingerprint sent within the last N seconds?
	 */
	public function recently_sent( string $fingerprint, int $window_seconds ): bool {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - $window_seconds );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE fingerprint = %s AND status = 'sent' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$fingerprint,
				$since
			)
		);

		return (int) $count > 0;
	}

	public function sent_count_since( int $window_seconds ): int {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - $window_seconds );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);
	}

	public function recent( int $limit = 50 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}
}
