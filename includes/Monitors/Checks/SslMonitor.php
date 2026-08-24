<?php
/**
 * SSL certificate expiry monitor.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\Checks;

use WatchSpire\Monitors\AbstractMonitor;
use WatchSpire\Monitors\Result;

defined( 'ABSPATH' ) || exit;

final class SslMonitor extends AbstractMonitor {

	private const WARN_THRESHOLDS = array( 30, 14, 7, 1 );

	public function get_id(): string {
		return 'ssl';
	}

	public function get_label(): string {
		return __( 'SSL certificate expiry', 'watchspire' );
	}

	public function get_description(): string {
		return __( 'Reads your site\'s own SSL certificate directly and warns you before it expires or stops covering your hostname.', 'watchspire' );
	}

	public function get_default_schedule(): int {
		return DAY_IN_SECONDS;
	}

	public function run(): Result {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			return Result::warn( __( 'Could not determine site host to check.', 'watchspire' ) );
		}

		$is_https = 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );

		$detail = array( 'host' => $host );

		if ( $is_https ) {
			$cert_result = $this->check_certificate( $host );
			$detail      = array_merge( $detail, $cert_result['detail'] );
		} else {
			$cert_result = array(
				'status'  => Result::WARN,
				'message' => __( 'Site is not served over HTTPS.', 'watchspire' ),
			);
		}

		return new Result( $cert_result['status'], $cert_result['message'], $detail );
	}

	/**
	 * @return array{status:string,message:string,detail:array}
	 */
	private function check_certificate( string $host ): array {
		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'SNI_enabled'       => true,
					'peer_name'         => $host,
				),
			)
		);

		$errno  = 0;
		$errstr = '';

		$client = @stream_socket_client( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			"ssl://{$host}:443",
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $client ) {
			return array(
				'status'  => Result::WARN,
				'message' => sprintf(
					/* translators: %s: connection error */
					__( 'Could not connect to check the SSL certificate: %s', 'watchspire' ),
					$errstr ? $errstr : __( 'unknown error', 'watchspire' )
				),
				'detail'  => array(),
			);
		}

		$params = stream_context_get_params( $client );
		fclose( $client ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			return array(
				'status'  => Result::WARN,
				'message' => __( 'Could not read the SSL certificate.', 'watchspire' ),
				'detail'  => array(),
			);
		}

		$cert = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );

		if ( ! $cert || empty( $cert['validTo_time_t'] ) ) {
			return array(
				'status'  => Result::WARN,
				'message' => __( 'Could not parse the SSL certificate.', 'watchspire' ),
				'detail'  => array(),
			);
		}

		$days_remaining = (int) floor( ( $cert['validTo_time_t'] - time() ) / DAY_IN_SECONDS );
		$common_name    = $cert['subject']['CN'] ?? '';
		$san            = $cert['extensions']['subjectAltName'] ?? '';
		$covers_host    = $this->san_covers_host( $host, $common_name, $san );

		$detail = array(
			'cert_expires'   => gmdate( 'Y-m-d', $cert['validTo_time_t'] ),
			'days_remaining' => $days_remaining,
			'issuer'         => $cert['issuer']['O'] ?? ( $cert['issuer']['CN'] ?? '' ),
			'common_name'    => $common_name,
			'covers_host'    => $covers_host,
		);

		if ( ! $covers_host ) {
			return array(
				'status'  => Result::FAIL,
				'message' => sprintf(
					/* translators: %s: hostname */
					__( 'The SSL certificate does not cover %s (hostname mismatch).', 'watchspire' ),
					$host
				),
				'detail'  => $detail,
			);
		}

		if ( $days_remaining < 0 ) {
			return array(
				'status'  => Result::FAIL,
				'message' => __( 'The SSL certificate has expired.', 'watchspire' ),
				'detail'  => $detail,
			);
		}

		$status = $this->status_for_days_remaining( $days_remaining );

		if ( Result::PASS === $status ) {
			return array(
				'status'  => Result::PASS,
				'message' => sprintf(
					/* translators: %d: days remaining */
					__( 'SSL certificate is valid for %d more day(s).', 'watchspire' ),
					$days_remaining
				),
				'detail'  => $detail,
			);
		}

		return array(
			'status'  => $status,
			'message' => sprintf(
				/* translators: %d: days remaining */
				__( 'SSL certificate expires in %d day(s).', 'watchspire' ),
				$days_remaining
			),
			'detail'  => $detail,
		);
	}

	private function san_covers_host( string $host, string $common_name, string $san ): bool {
		$names = array();

		if ( $common_name ) {
			$names[] = $common_name;
		}

		if ( $san ) {
			foreach ( explode( ',', $san ) as $entry ) {
				$entry = trim( $entry );
				if ( 0 === stripos( $entry, 'DNS:' ) ) {
					$names[] = substr( $entry, 4 );
				}
			}
		}

		foreach ( $names as $name ) {
			if ( $this->wildcard_match( $name, $host ) ) {
				return true;
			}
		}

		return empty( $names ) ? true : false; // Unknown shape: don't false-flag.
	}

	private function wildcard_match( string $pattern, string $host ): bool {
		if ( strtolower( $pattern ) === strtolower( $host ) ) {
			return true;
		}

		if ( 0 === strpos( $pattern, '*.' ) ) {
			$suffix = substr( $pattern, 1 ); // ".example.com"
			return (bool) preg_match( '/' . preg_quote( $suffix, '/' ) . '$/i', $host ) && substr_count( $host, '.' ) >= substr_count( $pattern, '.' );
		}

		return false;
	}

	private function status_for_days_remaining( int $days ): string {
		if ( $days < 0 ) {
			return Result::FAIL;
		}

		foreach ( self::WARN_THRESHOLDS as $threshold ) {
			if ( $days <= $threshold ) {
				return Result::WARN;
			}
		}

		return Result::PASS;
	}
}
