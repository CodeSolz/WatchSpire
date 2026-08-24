<?php
/**
 * Logs AI crawler hits as daily aggregates and reads robots.txt for
 * AI-bot directives.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Support;

use WatchSpire\Database\Repositories\CrawlersRepository;

defined( 'ABSPATH' ) || exit;

final class CrawlerLogger {

	private const DEFAULT_SIGNATURES = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'ClaudeBot',
		'Claude-User',
		'Claude-SearchBot',
		'anthropic-ai',
		'PerplexityBot',
		'Perplexity-User',
		'CCBot',
		'Google-Extended',
		'Bytespider',
		'Amazonbot',
		'meta-externalagent',
		'Applebot-Extended',
		'cohere-ai',
		'Diffbot',
		'ImagesiftBot',
		'YouBot',
	);

	private CrawlersRepository $repo;

	public function __construct() {
		$this->repo = new CrawlersRepository();
	}

	public function boot(): void {
		add_action( 'shutdown', array( $this, 'maybe_log' ), 0 );
	}

	public function maybe_log(): void {
		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( '' === $ua ) {
			return;
		}

		$bot = $this->match_bot( $ua );

		if ( ! $bot ) {
			return;
		}

		$status = function_exists( 'http_response_code' ) ? (int) http_response_code() : 200;

		$this->repo->record_hit( $bot, $status ? $status : 200 );
	}

	private function match_bot( string $user_agent ): ?string {
		foreach ( $this->signatures() as $signature ) {
			if ( false !== stripos( $user_agent, $signature ) ) {
				return $signature;
			}
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	public function signatures(): array {
		return apply_filters( 'watchspire_ai_crawler_signatures', self::DEFAULT_SIGNATURES );
	}

	/**
	 * Fetch and parse robots.txt, reporting which AI bots are allowed or
	 * disallowed. Cached for an hour.
	 *
	 * @return array<string,string> bot => "allowed"|"disallowed"|"unspecified"
	 */
	public function robots_txt_status(): array {
		$cache_key = 'watchspire_robots_txt_status';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$body  = $this->fetch_robots_txt();
		$rules = $this->parse_robots_txt( $body );

		$status = array();
		foreach ( $this->signatures() as $bot ) {
			$status[ $bot ] = $rules[ strtolower( $bot ) ] ?? 'unspecified';
		}

		set_transient( $cache_key, $status, HOUR_IN_SECONDS );

		return $status;
	}

	private function fetch_robots_txt(): string {
		$response = wp_remote_get(
			home_url( '/robots.txt' ),
			array( 'timeout' => 8 )
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * @return array<string,string> lowercased user-agent => "allowed"|"disallowed"
	 */
	private function parse_robots_txt( string $body ): array {
		if ( '' === trim( $body ) ) {
			return array();
		}

		$lines          = preg_split( '/\r\n|\r|\n/', $body );
		$current_agents = array();
		$rules          = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			if ( preg_match( '/^user-agent:\s*(.+)$/i', $line, $m ) ) {
				$agent            = strtolower( trim( $m[1] ) );
				$current_agents[] = $agent;
				continue;
			}

			if ( preg_match( '/^disallow:\s*(.*)$/i', $line, $m ) ) {
				$path = trim( $m[1] );
				foreach ( $current_agents as $agent ) {
					if ( '' !== $path ) {
						$rules[ $agent ] = 'disallowed';
					} elseif ( ! isset( $rules[ $agent ] ) ) {
						$rules[ $agent ] = 'allowed';
					}
				}
				continue;
			}

			if ( preg_match( '/^allow:\s*(.*)$/i', $line, $m ) ) {
				foreach ( $current_agents as $agent ) {
					$rules[ $agent ] = 'allowed';
				}
				continue;
			}

			// Any other directive line ends the current agent block grouping.
			if ( preg_match( '/^[a-z-]+:/i', $line ) ) {
				$current_agents = array();
			}
		}

		return $rules;
	}
}
