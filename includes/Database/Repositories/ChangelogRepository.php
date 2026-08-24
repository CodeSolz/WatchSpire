<?php
/**
 * Repository for the changelog table.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class ChangelogRepository extends AbstractRepository {

	protected string $table = 'changelog';

	public function insert(
		string $type,
		string $object_slug,
		?string $object_name = null,
		?string $from_version = null,
		?string $to_version = null,
		bool $is_auto_update = false,
		?int $user_id = null
	): int {
		$wpdb = $this->wpdb();

		$wpdb->insert(
			$this->table_name(),
			array(
				'type'           => $type,
				'object_slug'    => $object_slug,
				'object_name'    => $object_name,
				'from_version'   => $from_version,
				'to_version'     => $to_version,
				'is_auto_update' => $is_auto_update ? 1 : 0,
				'user_id'        => $user_id,
				'created_at'     => $this->now(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Events within [from, to] (MySQL datetime strings, UTC).
	 */
	public function between( string $from, string $to ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE created_at BETWEEN %s AND %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$to
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	public function recent( int $limit = 50, array $args = array() ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}

		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['since'];
		}

		$params[] = $limit;

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $rows ?? array();
	}

	/**
	 * Shared WHERE-builder for the Change Log admin screen: type, date
	 * window, a specific user, and a free-text search against the object
	 * name/slug/type. Used by both query() and count_matching() so the
	 * table rows and the "N of M" tally always agree.
	 *
	 * @param array{type?:string,since?:string,until?:string,user_id?:int,search?:string} $args
	 * @return array{0:string,1:array<int,mixed>} [$where_sql, $params]
	 */
	private function build_where( array $args ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}

		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['since'];
		}

		if ( ! empty( $args['until'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['until'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $this->wpdb()->esc_like( $args['search'] ) . '%';
			$where[]  = '(object_name LIKE %s OR object_slug LIKE %s OR type LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Paginated, filtered event list for the Change Log admin screen.
	 *
	 * @param array{type?:string,since?:string,until?:string,user_id?:int,search?:string} $args
	 */
	public function query( array $args, int $limit, int $offset = 0 ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		list( $where, $params ) = $this->build_where( $args );
		$params[]               = $limit;
		$params[]               = $offset;

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $rows ?? array();
	}

	/**
	 * Row count for the same filters used by query(), for pagination.
	 *
	 * @param array{type?:string,since?:string,until?:string,user_id?:int,search?:string} $args
	 */
	public function count_matching( array $args ): int {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		list( $where, $params ) = $this->build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Category breakdown for the Change Log stat cards, within a date
	 * window only — deliberately independent of the type/user/search
	 * filters so the tiles stay a stable "totals for this period"
	 * overview while the table below them narrows.
	 *
	 * @return array{total:int,plugin:int,theme:int,core:int,user:int}
	 */
	public function counts_by_range( string $since, string $until ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// The LIKE patterns are fixed prefixes, but they still go through
		// prepare() as placeholders: an inline wildcard reads as an injection
		// risk to reviewers and to Plugin Check, whichever way it was built.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(type LIKE %s) AS plugin,
					SUM(type LIKE %s) AS theme,
					SUM(type = 'core_update') AS core,
					SUM(type LIKE %s) AS user
				FROM {$table} WHERE created_at >= %s AND created_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built internally from $wpdb->prefix, never user input.
				$wpdb->esc_like( 'plugin_' ) . '%',
				$wpdb->esc_like( 'theme_' ) . '%',
				$wpdb->esc_like( 'user_' ) . '%',
				$since,
				$until
			),
			ARRAY_A
		);

		return array(
			'total'  => (int) ( $row['total'] ?? 0 ),
			'plugin' => (int) ( $row['plugin'] ?? 0 ),
			'theme'  => (int) ( $row['theme'] ?? 0 ),
			'core'   => (int) ( $row['core'] ?? 0 ),
			'user'   => (int) ( $row['user'] ?? 0 ),
		);
	}

	/**
	 * Every distinct user who has at least one changelog event, for the
	 * "By" filter dropdown. Rows attributed to nobody (e.g. WP-Cron
	 * auto-updates) have a NULL user_id and are excluded.
	 *
	 * @return \WP_User[]
	 */
	public function distinct_users(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$table} WHERE user_id IS NOT NULL ORDER BY user_id" );

		if ( empty( $ids ) ) {
			return array();
		}

		return get_users( array( 'include' => array_map( 'intval', $ids ) ) );
	}
}
