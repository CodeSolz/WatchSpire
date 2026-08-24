<?php
/**
 * Weekly summary email: uptime, failures, submissions, broken links,
 * SSL expiry, changes, and crawler activity. Skips sending when there's
 * genuinely nothing to report.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Support;

use WatchSpire\Database\Repositories\ChangelogRepository;
use WatchSpire\Database\Repositories\ChecksRepository;
use WatchSpire\Database\Repositories\CrawlersRepository;
use WatchSpire\Database\Repositories\LinksRepository;
use WatchSpire\Database\Repositories\SubmissionsRepository;
use WatchSpire\Scheduler\Scheduler;

defined( 'ABSPATH' ) || exit;

final class WeeklyDigest {

	private const WINDOW_DAYS = 7;

	public function boot(): void {
		add_action( Scheduler::HOOK_WEEKLY_DIGEST, array( $this, 'maybe_send' ) );
	}

	public function maybe_send(): void {
		if ( ! Settings::get( 'digest_enabled', true ) ) {
			return;
		}

		$day = Settings::get( 'digest_day', 'monday' );

		if ( strtolower( gmdate( 'l' ) ) !== $day ) {
			return;
		}

		$week_key = gmdate( 'oW' );

		if ( get_option( 'watchspire_last_digest_week' ) === $week_key ) {
			return;
		}

		update_option( 'watchspire_last_digest_week', $week_key, false );

		$data = $this->gather_data();

		if ( $this->is_empty( $data ) ) {
			return;
		}

		$this->send( $data );
	}

	private function gather_data(): array {
		$checks_repo      = new ChecksRepository();
		$submissions_repo = new SubmissionsRepository();

		$uptime_pass_rate = $checks_repo->pass_rate_since( 'uptime', self::WINDOW_DAYS );
		$failures         = $checks_repo->failures_since( self::WINDOW_DAYS );

		$forms = array();
		foreach ( $submissions_repo->distinct_forms() as $form ) {
			$counts  = $submissions_repo->daily_counts( $form['integration'], $form['form_id'], self::WINDOW_DAYS );
			$forms[] = array(
				'name'  => $form['form_name'] ? $form['form_name'] : $form['form_id'],
				'total' => array_sum( $counts ),
			);
		}

		$ssl_latest = $checks_repo->latest( 'ssl' );
		$ssl_detail = ( $ssl_latest && ! empty( $ssl_latest['detail'] ) ) ? json_decode( $ssl_latest['detail'], true ) : null;

		return array(
			'uptime_pass_rate' => $uptime_pass_rate,
			'failures'         => $failures,
			'forms'            => $forms,
			'new_broken_links' => ( new LinksRepository() )->broken_since( self::WINDOW_DAYS ),
			'ssl_days'         => is_array( $ssl_detail ) ? ( $ssl_detail['days_remaining'] ?? null ) : null,
			'changes'          => ( new ChangelogRepository() )->recent( 20, array( 'since' => gmdate( 'Y-m-d H:i:s', time() - self::WINDOW_DAYS * DAY_IN_SECONDS ) ) ),
			'crawler_totals'   => ( new CrawlersRepository() )->totals_by_bot( self::WINDOW_DAYS ),
		);
	}

	private function is_empty( array $data ): bool {
		$has_activity = ! empty( $data['failures'] )
			|| ! empty( $data['new_broken_links'] )
			|| ! empty( $data['changes'] )
			|| array_sum( array_column( $data['forms'], 'total' ) ) > 0
			|| ! empty( $data['crawler_totals'] );

		return ! $has_activity;
	}

	private function send( array $data ): void {
		$to = Settings::get( 'alert_email', get_option( 'admin_email' ) );

		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[WatchSpire] Weekly summary for %s', 'watchspire' ),
			get_bloginfo( 'name' )
		);

		$text_body = $this->render_body( $data );
		$html_body = $this->render_html_body( $data, $text_body );

		$set_alt_body = function ( $phpmailer ) use ( $text_body ) {
			$phpmailer->AltBody = $text_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own property name.
		};

		add_action( 'phpmailer_init', $set_alt_body );
		wp_mail( $to, $subject, $html_body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		remove_action( 'phpmailer_init', $set_alt_body );
	}

	private function render_html_body( array $data, string $text_body ): string {
		$escaped = nl2br( esc_html( $text_body ) );

		return '<!doctype html><html><body style="font-family:sans-serif;font-size:14px;line-height:1.6;color:#1d2327;max-width:640px;margin:0 auto;">'
			. '<div style="white-space:normal;">' . $escaped . '</div>'
			. '</body></html>';
	}

	private function render_body( array $data ): string {
		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: site name */
			__( 'Weekly WatchSpire summary for %s', 'watchspire' ),
			get_bloginfo( 'name' )
		);
		$lines[] = str_repeat( '-', 40 );
		$lines[] = '';

		if ( null !== $data['uptime_pass_rate'] ) {
			$lines[] = sprintf(
				/* translators: %s: pass rate percentage */
				__( 'Uptime self-check pass rate: %s%%', 'watchspire' ),
				$data['uptime_pass_rate']
			);
		}

		if ( null !== $data['ssl_days'] ) {
			$lines[] = sprintf(
				/* translators: %d: days remaining */
				__( 'SSL certificate: %d day(s) remaining', 'watchspire' ),
				$data['ssl_days']
			);
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %d: number of failures */
			__( 'Failures this week: %d', 'watchspire' ),
			count( $data['failures'] )
		);

		foreach ( array_slice( $data['failures'], 0, 10 ) as $failure ) {
			$lines[] = sprintf( '  - [%1$s] %2$s: %3$s', $failure['created_at'], $failure['monitor_id'], $failure['message'] );
		}

		if ( ! empty( $data['forms'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Form submissions:', 'watchspire' );
			foreach ( $data['forms'] as $form ) {
				$lines[] = sprintf( '  - %1$s: %2$d', $form['name'], $form['total'] );
			}
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %d: number of broken links */
			__( 'New broken links/images found: %d', 'watchspire' ),
			$data['new_broken_links']
		);

		if ( ! empty( $data['changes'] ) ) {
			$lines[] = '';
			$lines[] = __( 'Changes made this week:', 'watchspire' );
			foreach ( array_slice( $data['changes'], 0, 15 ) as $change ) {
				$change_label = $change['object_name'] ? $change['object_name'] : $change['object_slug'];
				$lines[]      = sprintf( '  - [%1$s] %2$s (%3$s)', $change['created_at'], $change_label, $change['type'] );
			}
		}

		if ( ! empty( $data['crawler_totals'] ) ) {
			$lines[] = '';
			$lines[] = __( 'AI crawler activity:', 'watchspire' );
			foreach ( $data['crawler_totals'] as $bot ) {
				$lines[] = sprintf( '  - %1$s: %2$d hits', $bot['bot'], $bot['hits'] );
			}
		}

		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=watchspire' );
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: settings URL */
			__( 'To stop these emails, disable the weekly summary here: %s', 'watchspire' ),
			admin_url( 'admin.php?page=watchspire-settings&tab=general' )
		);

		return implode( "\n", $lines );
	}
}
