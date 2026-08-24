<?php
/**
 * Repository for the links table (broken link & image scanner).
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database\Repositories;

defined( 'ABSPATH' ) || exit;

final class LinksRepository extends AbstractRepository {

	protected string $table = 'links';

	/**
	 * Queue a discovered link/image for checking. Preserves the
	 * "ignored" flag across scans for the same url+type+source.
	 */
	public function queue( string $url, string $type, ?int $source_post_id, ?string $source_title, ?string $source_type, ?string $anchor_text, string $scan_id ): void {
		$wpdb     = $this->wpdb();
		$table    = $this->table_name();
		$url      = mb_substr( $url, 0, 500 );
		$url_hash = md5( $url );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (url, url_hash, type, status, source_post_id, source_title, source_type, anchor_text, scan_id, created_at)
				VALUES (%s, %s, %s, 'pending', %d, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
					status = IF(is_ignored = 1, status, 'pending'),
					source_title = VALUES(source_title),
					source_type = VALUES(source_type),
					anchor_text = VALUES(anchor_text),
					scan_id = VALUES(scan_id)",
				$url,
				$url_hash,
				$type,
				$source_post_id,
				$source_title,
				$source_type,
				$anchor_text,
				$scan_id,
				$this->now()
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function next_batch( string $scan_id, int $limit ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE scan_id = %s AND status = 'pending' AND is_ignored = 0 ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id,
				$limit
			),
			ARRAY_A
		);

		return $rows ?? array();
	}

	public function pending_count( string $scan_id ): int {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE scan_id = %s AND status = 'pending' AND is_ignored = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id
			)
		);
	}

	public function total_for_scan( string $scan_id ): int {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE scan_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id
			)
		);
	}

	public function mark_result( int $id, string $status, ?int $http_code, ?string $error_reason = null ): void {
		$wpdb = $this->wpdb();

		$wpdb->update(
			$this->table_name(),
			array(
				'status'       => $status,
				'http_code'    => $http_code,
				'error_reason' => $error_reason,
				'checked_at'   => $this->now(),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function set_ignored( int $id, bool $ignored ): void {
		$this->wpdb()->update(
			$this->table_name(),
			array( 'is_ignored' => $ignored ? 1 : 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	public function recheck( int $id ): void {
		$this->wpdb()->update(
			$this->table_name(),
			array( 'status' => 'pending' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public function broken_since( int $days ): int {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'broken' AND is_ignored = 0 AND checked_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);
	}

	/**
	 * Stat-card counts for the Broken Links & Images screen.
	 *
	 * @return array{total_scanned:int,broken_links:int,broken_images:int,ignored:int}
	 */
	public function counts(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		return array(
			'total_scanned' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status != 'pending'" ),
			'broken_links'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'broken' AND type = 'link' AND is_ignored = 0" ),
			'broken_images' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'broken' AND type = 'image' AND is_ignored = 0" ),
			'ignored'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_ignored = 1" ),
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Builds the shared WHERE clause + params for query()/count_matching(),
	 * driven by the Broken Links & Images screen's tab + filter bar.
	 *
	 * @param array<string,mixed> $args {
	 *     @type string $view      'all'|'links'|'images'|'ignored'. Default 'all'.
	 *     @type string $type      '', 'link', or 'image' — narrows further within the view.
	 *     @type string $source    '', a post type, or 'nav_menu'.
	 *     @type int    $post_id   Restrict to a single source post.
	 *     @type string $status    '', an HTTP code as string, or 'unreachable' (null code).
	 *     @type string $search    URL search term.
	 * }
	 * @return array{where:string,params:array<int,mixed>}
	 */
	private function build_filter( array $args ): array {
		$where  = array();
		$params = array();

		switch ( $args['view'] ?? 'all' ) {
			case 'links':
				$where[] = "status = 'broken' AND type = 'link' AND is_ignored = 0";
				break;
			case 'images':
				$where[] = "status = 'broken' AND type = 'image' AND is_ignored = 0";
				break;
			case 'ignored':
				$where[] = 'is_ignored = 1';
				break;
			default:
				$where[] = "status = 'broken' AND is_ignored = 0";
		}

		if ( ! empty( $args['type'] ) && in_array( $args['type'], array( 'link', 'image' ), true ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}

		if ( ! empty( $args['source'] ) ) {
			if ( 'nav_menu' === $args['source'] ) {
				$where[] = "source_type = 'nav_menu'";
			} else {
				$where[]  = 'source_type = %s';
				$params[] = $args['source'];
			}
		}

		if ( ! empty( $args['post_id'] ) ) {
			$where[]  = 'source_post_id = %d';
			$params[] = (int) $args['post_id'];
		}

		if ( isset( $args['status'] ) && '' !== $args['status'] ) {
			if ( in_array( $args['status'], array( 'timeout', 'ssl_error', 'dns_error', 'unreachable' ), true ) ) {
				$where[]  = 'error_reason = %s';
				$params[] = $args['status'];
			} else {
				$where[]  = 'http_code = %d';
				$params[] = (int) $args['status'];
			}
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'url LIKE %s';
			$params[] = '%' . $this->wpdb()->esc_like( $args['search'] ) . '%';
		}

		return array(
			'where'  => implode( ' AND ', $where ),
			'params' => $params,
		);
	}

	/**
	 * @param array<string,mixed> $args See build_filter().
	 * @return array<int,array<string,mixed>>
	 */
	public function query( array $args, int $limit, int $offset ): array {
		$wpdb   = $this->wpdb();
		$table  = $this->table_name();
		$filter = $this->build_filter( $args );

		$sql    = "SELECT * FROM {$table} WHERE {$filter['where']} ORDER BY checked_at DESC, id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params = array_merge( $filter['params'], array( $limit, $offset ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $rows ?? array();
	}

	/**
	 * @param array<string,mixed> $args See build_filter().
	 */
	public function count_matching( array $args ): int {
		$wpdb   = $this->wpdb();
		$table  = $this->table_name();
		$filter = $this->build_filter( $args );

		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$filter['where']}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $filter['params'] ) ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $filter['params'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Distinct posts/pages that currently have at least one tracked link or
	 * image, for the "All Posts & Pages" filter dropdown.
	 *
	 * @return array<int,array{id:int,title:string}>
	 */
	public function distinct_source_posts(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
			"SELECT DISTINCT source_post_id AS id, source_title AS title FROM {$table} WHERE source_post_id IS NOT NULL ORDER BY source_title ASC LIMIT 300",
			ARRAY_A
		);

		return array_map(
			static function ( array $row ): array {
				return array(
					'id'    => (int) $row['id'],
					'title' => (string) $row['title'],
				);
			},
			$rows ?? array()
		);
	}

	/**
	 * Distinct HTTP codes and no-response reasons among currently broken
	 * rows, for the Status filter dropdown.
	 *
	 * @return array{codes:int[],reasons:string[]}
	 */
	public function distinct_broken_statuses(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$codes = $wpdb->get_col( "SELECT DISTINCT http_code FROM {$table} WHERE status = 'broken' AND is_ignored = 0 AND http_code IS NOT NULL ORDER BY http_code ASC" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$reasons = $wpdb->get_col( "SELECT DISTINCT error_reason FROM {$table} WHERE status = 'broken' AND is_ignored = 0 AND http_code IS NULL AND error_reason IS NOT NULL ORDER BY error_reason ASC" );

		return array(
			'codes'   => array_map( 'intval', $codes ?? array() ),
			'reasons' => array_values( array_filter( $reasons ?? array() ) ),
		);
	}

	/**
	 * Distinct source types (post types + 'nav_menu') currently represented,
	 * for the Source filter dropdown.
	 *
	 * @return string[]
	 */
	public function distinct_source_types(): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal, never user input.
		$types = $wpdb->get_col( "SELECT DISTINCT source_type FROM {$table} WHERE source_type IS NOT NULL AND source_type != '' ORDER BY source_type ASC" );

		return array_values( array_filter( $types ?? array() ) );
	}

	public function counts_for_scan( string $scan_id ): array {
		$wpdb  = $this->wpdb();
		$table = $this->table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) AS c FROM {$table} WHERE scan_id = %s GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scan_id
			),
			ARRAY_A
		);

		$out = array(
			'pending' => 0,
			'ok'      => 0,
			'broken'  => 0,
		);

		foreach ( $rows ?? array() as $row ) {
			$out[ $row['status'] ] = (int) $row['c'];
		}

		return $out;
	}
}
