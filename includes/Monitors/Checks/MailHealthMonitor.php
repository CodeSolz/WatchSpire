<?php
/**
 * Diagnoses mail deliverability risk and runs a scheduled self-test.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\Checks;

use WatchSpire\Monitors\AbstractMonitor;
use WatchSpire\Monitors\Result;
use WatchSpire\Support\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class MailHealthMonitor extends AbstractMonitor {

	private const KNOWN_SMTP_PLUGINS = array(
		'wp-mail-smtp/wp_mail_smtp.php',
		'post-smtp/postman-smtp.php',
		'easy-wp-smtp/easy-wp-smtp.php',
		'fluent-smtp/fluent-smtp.php',
		'wp-ses/wp-ses.php',
		'sendgrid-email-delivery-simplified/wpsendgrid.php',
		'wp-sendgrid-mailer/wp-sendgrid-mailer.php',
	);

	private ?array $captured = null;

	public function get_id(): string {
		return 'mail_health';
	}

	public function get_label(): string {
		return __( 'Mail deliverability', 'watchspire' );
	}

	public function get_description(): string {
		return __( 'Diagnoses your mail setup and periodically sends a real test email to confirm delivery.', 'watchspire' );
	}

	public function get_default_schedule(): int {
		return DAY_IN_SECONDS;
	}

	public function boot(): void {
		add_action( 'wp_mail_failed', array( $this, 'capture_failure' ) );
	}

	public function capture_failure( WP_Error $error ): void {
		$log   = get_option( 'watchspire_mail_failure_log', array() );
		$log[] = array(
			'time'    => time(),
			'message' => $error->get_error_message(),
		);
		$log   = array_slice( $log, -20 );
		update_option( 'watchspire_mail_failure_log', $log, false );
	}

	public function run(): Result {
		$recent_failures = $this->failures_since( time() - $this->get_default_schedule() );

		$phpmailer_callback = function ( $phpmailer ) {
			// PHPMailer's own property names -- not ours to rename.
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->captured = array(
				'from'   => $phpmailer->From,
				'mailer' => $phpmailer->Mailer,
			);
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};

		add_action( 'phpmailer_init', $phpmailer_callback );
		$test = $this->send_self_test();
		remove_action( 'phpmailer_init', $phpmailer_callback );

		$risk = $this->assess_risk();

		$detail = array(
			'is_smtp'              => $risk['is_smtp'],
			'smtp_plugin_active'   => $risk['smtp_plugin_active'],
			'from_domain_mismatch' => $risk['from_domain_mismatch'],
			'from_address'         => $risk['from_address'],
			'risk_score'           => $risk['score'],
			'self_test'            => $test,
			'recent_failures'      => $recent_failures,
		);

		if ( ! $test['attempted'] ) {
			// No test address configured — report risk diagnosis only.
			if ( $risk['score'] >= 60 ) {
				return Result::warn( $risk['diagnosis'], $detail );
			}
			return Result::pass( __( 'Mail configuration looks reasonable. Set a test address in Settings to enable live delivery testing.', 'watchspire' ), $detail );
		}

		if ( ! $test['success'] ) {
			return Result::fail(
				sprintf(
					/* translators: %s: error message */
					__( 'Test email failed to send: %s', 'watchspire' ),
					$test['error'] ? $test['error'] : __( 'unknown error', 'watchspire' )
				),
				$detail
			);
		}

		if ( ! empty( $recent_failures ) ) {
			return Result::warn(
				sprintf(
					/* translators: %d: number of failures */
					__( 'The self-test succeeded, but %d other mail send(s) failed recently.', 'watchspire' ),
					count( $recent_failures )
				),
				$detail
			);
		}

		if ( $risk['score'] >= 60 ) {
			return Result::warn( $risk['diagnosis'], $detail );
		}

		return Result::pass( __( 'Test email sent successfully.', 'watchspire' ), $detail );
	}

	/**
	 * @return array<int, array{time:int,message:string}>
	 */
	private function failures_since( int $since ): array {
		$log = get_option( 'watchspire_mail_failure_log', array() );

		return array_values(
			array_filter(
				$log,
				static function ( $entry ) use ( $since ) {
					return isset( $entry['time'] ) && $entry['time'] >= $since;
				}
			)
		);
	}

	private function assess_risk(): array {
		$active_plugins     = (array) get_option( 'active_plugins', array() );
		$smtp_plugin_active = (bool) array_intersect( self::KNOWN_SMTP_PLUGINS, $active_plugins );
		$is_smtp            = $smtp_plugin_active || ( isset( $this->captured['mailer'] ) && 'smtp' === $this->captured['mailer'] );

		$from_address = $this->captured['from'] ?? '';
		$mismatch     = false;
		$site_host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $from_address && false !== strpos( $from_address, '@' ) ) {
			$from_domain = strtolower( substr( $from_address, strpos( $from_address, '@' ) + 1 ) );
			$site_domain = strtolower( preg_replace( '/^www\./', '', (string) $site_host ) );

			if ( $from_domain && $site_domain && false === strpos( $site_domain, $from_domain ) && false === strpos( $from_domain, $site_domain ) ) {
				$mismatch = true;
			}
		}

		$score     = 0;
		$diagnoses = array();

		if ( ! $is_smtp ) {
			$score      += 50;
			$diagnoses[] = __( 'No SMTP plugin detected — mail is sent via PHP mail(), which many hosts throttle, block, or silently drop.', 'watchspire' );
		}

		if ( $mismatch ) {
			$score      += 30;
			$diagnoses[] = __( "The From address domain doesn't match your site's domain, which commonly fails SPF/DKIM checks and lands in spam or gets rejected.", 'watchspire' );
		}

		if ( empty( $diagnoses ) ) {
			$diagnoses[] = __( 'Mail configuration looks reasonable.', 'watchspire' );
		} else {
			$diagnoses[] = __( 'Fix: install an SMTP plugin (e.g. WP Mail SMTP) and authenticate with your email provider; ensure SPF and DKIM records match your sending domain.', 'watchspire' );
		}

		return array(
			'is_smtp'              => $is_smtp,
			'smtp_plugin_active'   => $smtp_plugin_active,
			'from_domain_mismatch' => $mismatch,
			'from_address'         => $from_address,
			'score'                => min( 100, $score ),
			'diagnosis'            => implode( ' ', $diagnoses ),
		);
	}

	/**
	 * @return array{attempted:bool,success:bool,error:?string}
	 */
	private function send_self_test(): array {
		$to = Settings::get( 'mail_test_address', Settings::get( 'alert_email', '' ) );

		if ( ! is_email( $to ) ) {
			return array(
				'attempted' => false,
				'success'   => false,
				'error'     => null,
			);
		}

		$error_message = null;
		$capture       = function ( WP_Error $error ) use ( &$error_message ) {
			$error_message = $error->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture );

		$sent = wp_mail(
			$to,
			sprintf(
				/* translators: %s: site name */
				__( '[WatchSpire] Test email from %s', 'watchspire' ),
				get_bloginfo( 'name' )
			),
			__( 'This is a scheduled test email from WatchSpire to confirm your site can send mail. No action is needed.', 'watchspire' )
		);

		remove_action( 'wp_mail_failed', $capture );

		return array(
			'attempted' => true,
			'success'   => (bool) $sent,
			'error'     => $error_message,
		);
	}
}
