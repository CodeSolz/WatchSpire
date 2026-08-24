<?php
/**
 * Free plugin's only alert channel: plain wp_mail().
 *
 * @package WatchSpire
 */

namespace WatchSpire\Alerts\Channels;

use WatchSpire\Alerts\Alert;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class WpMailChannel implements ChannelInterface {

	public function get_id(): string {
		return 'wp_mail';
	}

	public function get_label(): string {
		return __( 'Email', 'watchspire' );
	}

	public function is_configured(): bool {
		return (bool) $this->recipient();
	}

	public function send( Alert $alert ): bool {
		$to = $this->recipient();

		if ( ! $to ) {
			return false;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		/* translators: %s: site name */
		$prefix = sprintf( __( '[WatchSpire] %s', 'watchspire' ), get_bloginfo( 'name' ) );

		return (bool) wp_mail( $to, $prefix . ' — ' . $alert->subject, $alert->body, $headers );
	}

	private function recipient(): string {
		$email = Settings::get( 'alert_email', get_option( 'admin_email' ) );

		return is_email( $email ) ? $email : '';
	}
}
