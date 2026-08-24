<?php
/**
 * Loopback self-check for the home page and one configurable key page.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\Checks;

use WatchSpire\Monitors\AbstractMonitor;
use WatchSpire\Monitors\Result;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class UptimeMonitor extends AbstractMonitor {

	private const SOFT_404_MARKERS = array(
		'page not found',
		'404 not found',
		'this page doesn&#8217;t exist',
		'nothing found',
		'oops! that page',
	);

	public function get_id(): string {
		return 'uptime';
	}

	public function get_label(): string {
		return __( 'Uptime self-check', 'watchspire' );
	}

	public function get_description(): string {
		return __( 'Loopback checks your homepage and one key page for errors, soft-404s, and maintenance lockouts. Because the request comes from your own server, it cannot detect a site that is fully offline.', 'watchspire' );
	}

	public function get_default_schedule(): int {
		return 15 * MINUTE_IN_SECONDS;
	}

	public function run(): Result {
		$urls = array( 'home' => home_url( '/' ) );

		$key_page = trim( (string) Settings::get( 'uptime_key_page', '' ) );
		if ( $key_page ) {
			$urls['key_page'] = esc_url_raw( $key_page );
		}

		$results       = array();
		$worst         = Result::PASS;
		$worst_message = '';

		foreach ( $urls as $label => $url ) {
			$check             = $this->check_url( $url );
			$results[ $label ] = $check;

			if ( $this->rank( $check['status'] ) > $this->rank( $worst ) ) {
				$worst         = $check['status'];
				$worst_message = "{$label}: {$check['message']}";
			}
		}

		$detail = array( 'checks' => $results );

		if ( Result::PASS === $worst ) {
			return Result::pass( __( 'Homepage and key page respond normally.', 'watchspire' ), $detail );
		}

		return new Result( $worst, $worst_message, $detail );
	}

	/**
	 * @return array{status:string,message:string,http_code:?int,response_time_ms:?int}
	 */
	private function check_url( string $url ): array {
		$start = microtime( true );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => apply_filters( 'watchspire_uptime_sslverify', true ),
				'headers'   => array( 'X-WatchSpire-Check' => '1' ),
			)
		);

		$elapsed_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return array(
				'status'           => Result::WARN,
				'message'          => sprintf(
					/* translators: %s: error message */
					__( 'Cannot self-check — loopback request failed: %s', 'watchspire' ),
					$response->get_error_message()
				),
				'http_code'        => null,
				'response_time_ms' => $elapsed_ms,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 500 ) {
			return array(
				'status'           => Result::FAIL,
				'message'          => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Returned a server error (%d).', 'watchspire' ),
					$code
				),
				'http_code'        => $code,
				'response_time_ms' => $elapsed_ms,
			);
		}

		if ( $code >= 400 ) {
			return array(
				'status'           => Result::FAIL,
				'message'          => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Returned an error status (%d).', 'watchspire' ),
					$code
				),
				'http_code'        => $code,
				'response_time_ms' => $elapsed_ms,
			);
		}

		if ( $this->looks_like_maintenance( $body ) ) {
			return array(
				'status'           => Result::WARN,
				'message'          => __( 'Site appears to be in maintenance mode.', 'watchspire' ),
				'http_code'        => $code,
				'response_time_ms' => $elapsed_ms,
			);
		}

		if ( $this->looks_like_soft_404( $body ) ) {
			return array(
				'status'           => Result::WARN,
				'message'          => __( 'Returned HTTP 200 but the page body looks like a "not found" message (soft 404).', 'watchspire' ),
				'http_code'        => $code,
				'response_time_ms' => $elapsed_ms,
			);
		}

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return array(
				'status'           => Result::WARN,
				'message'          => __( 'Returned an empty page — possible white-screen fatal error.', 'watchspire' ),
				'http_code'        => $code,
				'response_time_ms' => $elapsed_ms,
			);
		}

		return array(
			'status'           => Result::PASS,
			'message'          => __( 'OK', 'watchspire' ),
			'http_code'        => $code,
			'response_time_ms' => $elapsed_ms,
		);
	}

	private function looks_like_maintenance( string $body ): bool {
		return (bool) preg_match( '/briefly unavailable for scheduled maintenance/i', $body );
	}

	private function looks_like_soft_404( string $body ): bool {
		$haystack = strtolower( $body );

		foreach ( self::SOFT_404_MARKERS as $marker ) {
			if ( false !== strpos( $haystack, strtolower( $marker ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function rank( string $status ): int {
		return array(
			Result::PASS => 0,
			Result::WARN => 1,
			Result::FAIL => 2,
		)[ $status ] ?? 0;
	}
}
