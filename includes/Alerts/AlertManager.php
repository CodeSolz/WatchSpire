<?php
/**
 * Routes failures/recoveries to channels with dedupe and rate limiting.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Alerts;

use WatchSpire\Alerts\Channels\ChannelInterface;
use WatchSpire\Alerts\Channels\WpMailChannel;
use WatchSpire\Database\Repositories\AlertsRepository;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class AlertManager {

	private const RATE_LIMIT_PER_HOUR = 10;

	private AlertsRepository $repo;

	public function __construct() {
		$this->repo = new AlertsRepository();
	}

	public function boot(): void {
		add_filter( 'watchspire_alert_channels', array( $this, 'register_builtin_channels' ), 5 );
	}

	/**
	 * @param array<string, ChannelInterface> $channels
	 * @return array<string, ChannelInterface>
	 */
	public function register_builtin_channels( array $channels ): array {
		$wp_mail                        = new WpMailChannel();
		$channels[ $wp_mail->get_id() ] = $wp_mail;

		return $channels;
	}

	/**
	 * @return array<string, ChannelInterface>
	 */
	public function get_channels(): array {
		$channels = apply_filters( 'watchspire_alert_channels', array() );

		return is_array( $channels ) ? $channels : array();
	}

	public function handle_failure( Failure $failure ): void {
		$window = (int) Settings::get( 'alert_dedupe_window', 6 * HOUR_IN_SECONDS );

		if ( $window > 0 && $this->repo->recently_sent( $failure->fingerprint(), $window ) ) {
			return;
		}

		if ( $this->is_rate_limited() ) {
			$this->send_rate_limit_digest();
			return;
		}

		$alert = new Alert(
			/* translators: %s: monitor label */
			sprintf( __( '%s check failed', 'watchspire' ), $failure->source_label ),
			$this->format_failure_body( $failure ),
			'failure',
			$failure->fingerprint(),
			array(
				'source_id' => $failure->source_id,
			)
		);

		$this->dispatch( $alert );
	}

	public function handle_recovery( string $monitor_id, string $monitor_label ): void {
		$alert = new Alert(
			/* translators: %s: monitor label */
			sprintf( __( '%s has recovered', 'watchspire' ), $monitor_label ),
			sprintf(
				/* translators: 1: monitor label, 2: site name */
				__( 'Good news — %1$s is passing again on %2$s.', 'watchspire' ),
				$monitor_label,
				get_bloginfo( 'name' )
			),
			'recovery',
			'recovery:' . md5( $monitor_id . gmdate( 'YmdHi' ) ),
			array( 'source_id' => $monitor_id )
		);

		$this->dispatch( $alert );
	}

	private function dispatch( Alert $alert ): void {
		$sent_any = false;

		foreach ( $this->get_channels() as $channel ) {
			if ( ! $channel->is_configured() ) {
				continue;
			}

			$ok = false;

			try {
				$ok = $channel->send( $alert );
			} catch ( \Throwable $e ) {
				$ok = false;
			}

			$this->repo->insert( $channel->get_id(), $alert->subject, $alert->fingerprint, $ok ? 'sent' : 'failed' );

			$sent_any = $sent_any || $ok;
		}

		if ( $sent_any ) {
			do_action( 'watchspire_alert_sent', $alert );
		}
	}

	private function is_rate_limited(): bool {
		return $this->repo->sent_count_since( HOUR_IN_SECONDS ) >= self::RATE_LIMIT_PER_HOUR;
	}

	private function send_rate_limit_digest(): void {
		$flag = 'watchspire_rate_limit_digest_sent';

		if ( get_transient( $flag ) ) {
			return;
		}

		set_transient( $flag, 1, HOUR_IN_SECONDS );

		$alert = new Alert(
			__( 'Multiple checks are failing', 'watchspire' ),
			sprintf(
				/* translators: %d: alert count */
				__( "More than %d alerts fired in the last hour, so WatchSpire has switched to a single digest to avoid flooding your inbox. Open the WatchSpire dashboard to see everything that's failing.", 'watchspire' ),
				self::RATE_LIMIT_PER_HOUR
			),
			'digest',
			null,
			array()
		);

		$this->dispatch( $alert );
	}

	private function format_failure_body( Failure $failure ): string {
		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: monitor label, 2: site name */
			__( '%1$s failed a check on %2$s.', 'watchspire' ),
			$failure->source_label,
			get_bloginfo( 'name' )
		);
		$lines[] = '';
		$lines[] = $failure->message;

		if ( ! empty( $failure->detail ) ) {
			$lines[] = '';
			$lines[] = __( 'Detail:', 'watchspire' );
			foreach ( $failure->detail as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$lines[] = "- {$key}: {$value}";
				}
			}
		}

		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=watchspire' );

		return implode( "\n", $lines );
	}
}
