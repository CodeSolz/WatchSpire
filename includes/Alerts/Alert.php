<?php
/**
 * Value object passed to alert channels for delivery.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Alerts;

defined( 'ABSPATH' ) || exit;

final class Alert {

	public string $subject;
	public string $body;
	public string $severity;
	public ?string $fingerprint;
	public array $context;

	public function __construct( string $subject, string $body, string $severity = 'failure', ?string $fingerprint = null, array $context = array() ) {
		$this->subject     = $subject;
		$this->body        = $body;
		$this->severity    = $severity;
		$this->fingerprint = $fingerprint;
		$this->context     = $context;
	}
}
