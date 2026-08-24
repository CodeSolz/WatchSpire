<?php
/**
 * Activation routine.
 *
 * @package WatchSpire
 */

namespace WatchSpire;

use WatchSpire\Database\Schema;
use WatchSpire\Scheduler\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		if ( ! version_compare( PHP_VERSION, '7.4', '>=' ) ) {
			deactivate_plugins( WATCHSPIRE_BASENAME );
			wp_die(
				esc_html__( 'WatchSpire requires PHP 7.4 or higher.', 'watchspire' ),
				esc_html__( 'Plugin activation error', 'watchspire' ),
				array( 'back_link' => true )
			);
		}

		Schema::migrate();
		update_option( 'watchspire_db_version', WATCHSPIRE_DB_VERSION );

		if ( false === get_option( 'watchspire_settings' ) ) {
			add_option( 'watchspire_settings', self::default_settings() );
		}

		add_option( 'watchspire_onboarding_complete', false );
		add_option( 'watchspire_activated_at', time() );
		add_option( 'watchspire_do_activation_redirect', true );

		Scheduler::schedule_defaults();

		flush_rewrite_rules();
	}

	public static function default_settings(): array {
		return array(
			'alert_email'                => get_option( 'admin_email' ),
			'monitors_enabled'           => array(
				'ssl'         => true,
				'http_errors' => true,
				'mail_health' => true,
				'uptime'      => true,
				'link_scan'   => false,
			),
			'link_scan_opt_in'           => false,
			'digest_enabled'             => true,
			'digest_day'                 => 'monday',
			'digest_time'                => '08:00',
			'ignored_error_paths'        => array(),
			'check_interval_seconds'     => 15 * MINUTE_IN_SECONDS,
			'auto_resolve_enabled'       => true,
			'auto_resolve_after'         => 2,
			'retention_monitor_days'     => 30,
			'retention_alert_days'       => 90,
			'link_scan_broken_threshold' => 5,
		);
	}
}
