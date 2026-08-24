<?php
/**
 * Value object describing a detected failure, passed to alert channels.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Alerts;

defined( 'ABSPATH' ) || exit;

final class Failure {

	public string $source_id;
	public string $source_label;
	public string $message;
	public array $detail;
	public int $timestamp;

	public function __construct( string $source_id, string $source_label, string $message, array $detail = array() ) {
		$this->source_id    = $source_id;
		$this->source_label = $source_label;
		$this->message      = $message;
		$this->detail       = $detail;
		$this->timestamp    = time();
	}

	/**
	 * Stable fingerprint used for deduplication. Same monitor + same
	 * message text collapses to the same fingerprint within the window.
	 */
	public function fingerprint(): string {
		return md5( $this->source_id . '|' . $this->message );
	}
}
