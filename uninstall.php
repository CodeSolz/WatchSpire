<?php
/**
 * Uninstall routine — removes all WatchSpire data.
 *
 * @package WatchSpire
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	'checks',
	'changelog',
	'submissions',
	'crawlers',
	'alerts',
	'errors',
	'links',
);

foreach ( $tables as $table ) {
	$full_name = $wpdb->prefix . 'watchspire_' . $table;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$full_name}`" );
}

$options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'watchspire_' ) . '%'
	)
);

foreach ( $options as $option_name ) {
	delete_option( $option_name );
}

// Transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_watchspire\_%' OR option_name LIKE '\_transient\_timeout\_watchspire\_%'"
);

// Scheduled Action Scheduler actions belonging to WatchSpire, if the library left rows behind.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'watchspire' );
}
