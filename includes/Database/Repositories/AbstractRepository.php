<?php
/**
 * Shared helpers for table repositories.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

abstract class AbstractRepository {

	/**
	 * Table name without prefix, e.g. "checks".
	 */
	protected string $table = '';

	protected function wpdb() {
		global $wpdb;
		return $wpdb;
	}

	protected function table_name(): string {
		return $this->wpdb()->prefix . 'watchspire_' . $this->table;
	}

	protected function now(): string {
		return current_time( 'mysql', true );
	}

	/**
	 * Delete rows older than N days based on the given column.
	 */
	public function purge_older_than( int $days, string $column = 'created_at' ): int {
		$wpdb   = $this->wpdb();
		$table  = $this->table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE {$column} < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table/$column are internal, never user input.
				$cutoff
			)
		);

		return false === $deleted ? 0 : (int) $deleted;
	}

	public function count(): int {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function truncate(): void {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
