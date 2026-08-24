<?php
/**
 * Knows whether the separate WatchSpire Pro add-on is running, and where
 * to send someone who wants it.
 *
 * Nothing in WatchSpire is gated on this — every feature the plugin has
 * works the same either way. It only decides whether the "Upgrade to Pro"
 * pointers are worth showing, which they are not once Pro is already
 * installed.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Support;

defined( 'ABSPATH' ) || exit;

final class Pro {

	private const UPGRADE_URL = 'https://codesolz.net/our-products/wordpress-plugin/watchspire';

	/**
	 * Fallback plugin file, used only when Pro is present but did not get
	 * far enough to define its constant (its own PHP-version guard, say).
	 */
	private const PLUGIN_FILE = 'watchspire-pro/watchspire-pro.php';

	public static function is_active(): bool {
		// Pro defines this as it loads, so this covers the normal case and
		// stays correct if the add-on is ever renamed or installed from a
		// differently named folder.
		if ( defined( 'WATCHSPIRE_PRO_VERSION' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::PLUGIN_FILE );
	}

	/**
	 * @param string $source Where the click came from, for campaign tracking.
	 */
	public static function upgrade_url( string $source ): string {
		return add_query_arg(
			array(
				'utm_source'   => $source,
				'utm_medium'   => 'wp-dash',
				'utm_campaign' => 'gopro',
			),
			self::UPGRADE_URL
		);
	}
}
