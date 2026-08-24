<?php
/**
 * Aggregates 404 and 5xx responses, alerting on thresholds.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\Checks;

use WatchSpire\Database\Repositories\ErrorsRepository;
use WatchSpire\Monitors\AbstractMonitor;
use WatchSpire\Monitors\Result;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class HttpErrorMonitor extends AbstractMonitor {

	private const DEFAULT_404_THRESHOLD = 20;

	private ErrorsRepository $repo;

	public function __construct() {
		$this->repo = new ErrorsRepository();
	}

	public function get_id(): string {
		return 'http_errors';
	}

	public function get_label(): string {
		return __( '404 & server errors', 'watchspire' );
	}

	public function get_description(): string {
		return __( 'Watches for missing pages and 5xx server errors, aggregated so it never bloats the database.', 'watchspire' );
	}

	public function get_default_schedule(): int {
		return HOUR_IN_SECONDS;
	}

	public function boot(): void {
		add_action( 'template_redirect', array( $this, 'capture_404' ) );
		add_action( 'shutdown', array( $this, 'capture_shutdown_errors' ), 0 );
	}

	public function capture_404(): void {
		if ( ! is_404() || is_admin() ) {
			return;
		}

		$this->maybe_record( 404 );
	}

	public function capture_shutdown_errors(): void {
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$status = function_exists( 'http_response_code' ) ? http_response_code() : false;

		if ( $status && $status >= 500 ) {
			$this->maybe_record( (int) $status );
			return;
		}

		$error = error_get_last();

		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$this->maybe_record( 500 );
		}
	}

	private function maybe_record( int $status ): void {
		$url = $this->current_url();

		if ( $this->is_ignored( $url ) || $this->is_ignored_user_agent() ) {
			return;
		}

		$referrer   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$this->repo->record_hit( $url, $status, $referrer, $user_agent );
	}

	private function current_url(): string {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return $scheme . $host . $uri;
	}

	private function is_ignored( string $url ): bool {
		$defaults = array(
			'wp-login.php',
			'xmlrpc.php',
			'.env',
			'.git',
			'wp-config.php',
			'/wp-content/uploads/',
			'favicon.ico',
			'apple-touch-icon',
		);

		$ignore = apply_filters( 'watchspire_http_error_ignore_paths', $defaults );

		foreach ( $ignore as $needle ) {
			if ( $needle && false !== stripos( $url, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_ignored_user_agent(): bool {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( '' === $ua ) {
			return false;
		}

		$defaults = array( 'MJ12bot', 'SemrushBot', 'AhrefsBot', 'DotBot' );
		$ignore   = apply_filters( 'watchspire_http_error_ignore_user_agents', $defaults );

		foreach ( $ignore as $needle ) {
			if ( $needle && false !== stripos( $ua, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	public function run(): Result {
		$threshold = (int) Settings::get( '404_threshold', self::DEFAULT_404_THRESHOLD );
		$over      = $this->repo->over_threshold_last_24h( $threshold );
		$server    = $this->repo->recent_5xx( 5 );

		if ( ! empty( $server ) ) {
			$first = $server[0];

			return Result::fail(
				sprintf(
					/* translators: 1: url, 2: status code */
					__( 'Server error (%2$d) detected on %1$s.', 'watchspire' ),
					$first['url'],
					$first['status']
				),
				array(
					'server_errors'      => $server,
					'threshold_404'      => $threshold,
					'over_threshold_404' => $over,
				)
			);
		}

		if ( ! empty( $over ) ) {
			$first = $over[0];

			return Result::warn(
				sprintf(
					/* translators: 1: hit count, 2: url */
					__( '%1$d 404 hits on %2$s in the last 24 hours.', 'watchspire' ),
					$first['count'],
					$first['url']
				),
				array(
					'threshold_404'      => $threshold,
					'over_threshold_404' => $over,
				)
			);
		}

		return Result::pass( __( 'No unusual 404 or server error activity.', 'watchspire' ) );
	}
}
