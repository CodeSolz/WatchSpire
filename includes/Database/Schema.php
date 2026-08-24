<?php
/**
 * Database schema definitions and migration runner.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/**
	 * Run (or re-run) dbDelta against every WatchSpire table.
	 * dbDelta itself is idempotent — safe to call on every version bump.
	 */
	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'watchspire_';

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}checks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			monitor_id VARCHAR(64) NOT NULL,
			status VARCHAR(16) NOT NULL,
			message TEXT NULL,
			detail LONGTEXT NULL,
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY monitor_id (monitor_id),
			KEY created_at (created_at),
			KEY monitor_status (monitor_id, status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}changelog (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(32) NOT NULL,
			object_slug VARCHAR(191) NOT NULL,
			object_name VARCHAR(191) NULL,
			from_version VARCHAR(32) NULL,
			to_version VARCHAR(32) NULL,
			is_auto_update TINYINT(1) NOT NULL DEFAULT 0,
			user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY object_slug (object_slug),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}submissions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			integration VARCHAR(32) NOT NULL,
			form_id VARCHAR(64) NOT NULL,
			form_name VARCHAR(191) NULL,
			delivered TINYINT(1) NOT NULL DEFAULT 0,
			error TEXT NULL,
			is_synthetic TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY integration_form (integration, form_id),
			KEY created_at (created_at),
			KEY is_synthetic (is_synthetic)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}crawlers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bot VARCHAR(64) NOT NULL,
			date DATE NOT NULL,
			hits INT UNSIGNED NOT NULL DEFAULT 0,
			status_2xx INT UNSIGNED NOT NULL DEFAULT 0,
			status_4xx INT UNSIGNED NOT NULL DEFAULT 0,
			status_5xx INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY bot_date (bot, date),
			KEY date (date)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}alerts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			channel VARCHAR(32) NOT NULL,
			subject VARCHAR(191) NOT NULL,
			fingerprint VARCHAR(64) NULL,
			status VARCHAR(16) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY channel (channel),
			KEY fingerprint (fingerprint),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}errors (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url VARCHAR(500) NOT NULL,
			url_hash CHAR(32) NOT NULL,
			status SMALLINT UNSIGNED NOT NULL,
			referrer VARCHAR(500) NULL,
			user_agent VARCHAR(255) NULL,
			count INT UNSIGNED NOT NULL DEFAULT 1,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_status (url_hash, status),
			KEY status (status),
			KEY last_seen (last_seen)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}links (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url VARCHAR(500) NOT NULL,
			url_hash CHAR(32) NOT NULL,
			type VARCHAR(16) NOT NULL DEFAULT 'link',
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			http_code SMALLINT UNSIGNED NULL,
			error_reason VARCHAR(32) NULL,
			source_post_id BIGINT UNSIGNED NULL,
			source_title VARCHAR(191) NULL,
			source_type VARCHAR(32) NULL,
			anchor_text VARCHAR(255) NULL,
			is_ignored TINYINT(1) NOT NULL DEFAULT 0,
			scan_id VARCHAR(32) NOT NULL DEFAULT '',
			checked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url_source (url_hash, type, source_post_id),
			KEY status (status),
			KEY scan_id (scan_id),
			KEY is_ignored (is_ignored),
			KEY source_type (source_type)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	public static function drop_all(): void {
		global $wpdb;

		$prefix = $wpdb->prefix . 'watchspire_';
		$tables = array( 'checks', 'changelog', 'submissions', 'crawlers', 'alerts', 'errors', 'links' );

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" );
		}
	}
}
