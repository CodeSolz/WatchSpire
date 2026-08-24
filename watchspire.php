<?php
/**
 * Plugin Name:       WatchSpire – Form, Email & Uptime Monitor with Failure Alerts
 * Plugin URI:        https://codesolz.net/our-products/wordpress-plugin/watchspire
 * Description:       Monitors contact form delivery, SSL certificate expiry, 404/500 errors, uptime, and broken links — alerts you by email when something silently breaks.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WatchSpire
 * Author URI:        https://codesolz.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       watchspire
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WATCHSPIRE_VERSION', '1.0.0' );
define( 'WATCHSPIRE_DB_VERSION', '1' );
define( 'WATCHSPIRE_FILE', __FILE__ );
define( 'WATCHSPIRE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WATCHSPIRE_URL', plugin_dir_url( __FILE__ ) );
define( 'WATCHSPIRE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PHP version guard. Runs before autoload so it works even if composer
 * output (e.g. a syntax error class) would otherwise fatal on include.
 */
function watchspire_php_version_ok() {
	return version_compare( PHP_VERSION, '7.4', '>=' );
}

if ( ! watchspire_php_version_ok() ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'WatchSpire requires PHP %1$s or higher. Your site is running PHP %2$s. The plugin has been deactivated.', 'watchspire' ),
						'7.4',
						PHP_VERSION
					)
				)
			);
		}
	);

	add_action(
		'admin_init',
		function () {
			deactivate_plugins( WATCHSPIRE_BASENAME );
			if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				unset( $_GET['activate'] );
			}
		}
	);

	return;
}

$watchspire_autoload = WATCHSPIRE_DIR . 'vendor/autoload.php';
if ( file_exists( $watchspire_autoload ) ) {
	require_once $watchspire_autoload;
} else {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'WatchSpire is missing its dependencies. Please run "composer install" or reinstall the plugin from WordPress.org.', 'watchspire' )
			);
		}
	);
	return;
}

register_activation_hook( WATCHSPIRE_FILE, array( \WatchSpire\Activator::class, 'activate' ) );
register_deactivation_hook( WATCHSPIRE_FILE, array( \WatchSpire\Deactivator::class, 'deactivate' ) );

/**
 * Access the plugin container.
 *
 * @return \WatchSpire\Plugin
 */
function watchspire() {
	return \WatchSpire\Plugin::instance();
}

add_action( 'plugins_loaded', array( watchspire(), 'init' ) );
