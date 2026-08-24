<?php
/**
 * The plugin's own row on wp-admin/plugins.php: action links on the left,
 * support/rating links in the meta row underneath.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Admin;

use WatchSpire\Support\Pro;

defined( 'ABSPATH' ) || exit;

final class PluginLinks {

	public function boot(): void {
		add_filter( 'plugin_action_links_' . WATCHSPIRE_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * @param  mixed $links
	 * @return array<string,string>
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();

		$custom = array();

		if ( ! Pro::is_active() ) {
			$custom['watchspire-go-pro'] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer" style="color:#c60051;font-weight:600;" aria-label="%2$s">%3$s</a>',
				esc_url( Pro::upgrade_url( 'pl-action-links' ) ),
				esc_attr__( 'Upgrade to WatchSpire Pro', 'watchspire' ),
				esc_html__( 'Upgrade to Pro', 'watchspire' )
			);
		}

		$custom['watchspire-dashboard'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=watchspire' ) ),
			esc_html__( 'Dashboard', 'watchspire' )
		);

		$custom['watchspire-settings'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=watchspire-settings' ) ),
			esc_html__( 'Settings', 'watchspire' )
		);

		return array_merge( $custom, $links );
	}

	/**
	 * @param  mixed  $links
	 * @param  string $file
	 * @return array<int|string,string>
	 */
	public function row_meta( $links, $file ): array {
		$links = is_array( $links ) ? $links : array();

		if ( WATCHSPIRE_BASENAME !== $file ) {
			return $links;
		}

		$links['watchspire-support'] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( 'https://wordpress.org/support/plugin/watchspire/' ),
			esc_html__( 'Support', 'watchspire' )
		);

		$links['watchspire-review'] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( Notices::REVIEW_URL ),
			esc_html__( 'Rate WatchSpire', 'watchspire' )
		);

		return $links;
	}
}
