<?php
/**
 * Checks a single URL: HEAD first, GET fallback.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\LinkScan;

defined( 'ABSPATH' ) || exit;

final class LinkChecker {

	/**
	 * @return array{status:string,http_code:?int,error_reason:?string}
	 */
	public function check( string $url ): array {
		$absolute = $this->to_absolute( $url );

		if ( ! $absolute ) {
			return array(
				'status'       => 'broken',
				'http_code'    => null,
				'error_reason' => 'unreachable',
			);
		}

		$args = array(
			'timeout'     => 10,
			'redirection' => 5,
			'sslverify'   => apply_filters( 'watchspire_link_scan_sslverify', true ),
			'headers'     => array( 'User-Agent' => 'WatchSpire-LinkScanner/1.0 (+' . home_url( '/' ) . ')' ),
		);

		$response = wp_remote_head( $absolute, $args );
		$code     = is_wp_error( $response ) ? null : (int) wp_remote_retrieve_response_code( $response );

		// Some servers don't implement HEAD correctly; fall back to GET.
		if ( is_wp_error( $response ) || ! $code || $code >= 400 || 405 === $code ) {
			$response = wp_remote_get( $absolute, $args );
			$code     = is_wp_error( $response ) ? null : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( is_wp_error( $response ) || ! $code ) {
			return array(
				'status'       => 'broken',
				'http_code'    => $code,
				'error_reason' => is_wp_error( $response ) ? $this->classify_error( $response ) : 'unreachable',
			);
		}

		if ( $code >= 400 ) {
			return array(
				'status'       => 'broken',
				'http_code'    => $code,
				'error_reason' => null,
			);
		}

		return array(
			'status'       => 'ok',
			'http_code'    => $code,
			'error_reason' => null,
		);
	}

	/**
	 * Buckets a WP_Http transport failure into a coarse reason for display
	 * (the underlying transport only exposes this as free-text message).
	 */
	private function classify_error( \WP_Error $error ): string {
		$message = strtolower( $error->get_error_message() );

		if ( false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'timeout' ) ) {
			return 'timeout';
		}

		if ( false !== strpos( $message, 'ssl' ) || false !== strpos( $message, 'certificate' ) ) {
			return 'ssl_error';
		}

		if ( false !== strpos( $message, 'could not resolve host' ) || false !== strpos( $message, 'name or service not known' ) || false !== strpos( $message, 'dns' ) ) {
			return 'dns_error';
		}

		return 'unreachable';
	}

	private function to_absolute( string $url ): ?string {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		} elseif ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return null;
		}

		return $url;
	}
}
