<?php
/**
 * Contract every alert channel must implement.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Alerts\Channels;

use WatchSpire\Alerts\Alert;

defined( 'ABSPATH' ) || exit;

interface ChannelInterface {

	/**
	 * Stable identifier, e.g. "wp_mail", "slack".
	 */
	public function get_id(): string;

	/**
	 * Human-readable label for the admin UI.
	 */
	public function get_label(): string;

	/**
	 * Whether this channel is configured and ready to send.
	 */
	public function is_configured(): bool;

	/**
	 * Send the alert. Returns true on success.
	 */
	public function send( Alert $alert ): bool;
}
