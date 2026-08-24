<?php
/**
 * Thin wrapper around Action Scheduler.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	public const GROUP = 'watchspire';

	public const HOOK_RUN_MONITORS   = 'watchspire_run_due_monitors';
	public const HOOK_DAILY_CLEANUP  = 'watchspire_daily_cleanup';
	public const HOOK_WEEKLY_DIGEST  = 'watchspire_send_weekly_digest';
	public const HOOK_SCAN_BATCH     = 'watchspire_link_scan_batch';
	public const HOOK_SCAN_EXTRACT   = 'watchspire_link_scan_extract';
	public const HOOK_CRAWLER_ROLLUP = 'watchspire_crawler_rollup';

	public function boot(): void {
		$this->maybe_bundle_action_scheduler();

		// Deliberately NOT hooked to admin_notices: that fires at the top
		// of #wpbody-content, outside .wrap, so the notice would render
		// full-bleed above every other plugin's notices instead of inside
		// the WatchSpire screen it belongs to. WatchSpire's own screens
		// print it themselves via AdminMenu::render_own_notices(), right
		// under the page header. Other plugins' and core's notices are
		// untouched and keep their normal position.
		add_action( 'wp_ajax_watchspire_dismiss_cron_notice', array( $this, 'dismiss_cron_notice' ) );
	}

	private function maybe_bundle_action_scheduler(): void {
		if ( ! class_exists( '\ActionScheduler' ) ) {
			$as_init = WATCHSPIRE_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
			if ( file_exists( $as_init ) ) {
				require_once $as_init;
			}
		}
	}

	/**
	 * Schedule everything the free plugin needs by default. Called on activation.
	 */
	public static function schedule_defaults(): void {
		self::schedule_recurring( self::HOOK_RUN_MONITORS, 15 * MINUTE_IN_SECONDS );
		self::schedule_recurring( self::HOOK_DAILY_CLEANUP, DAY_IN_SECONDS );
		self::schedule_recurring( self::HOOK_CRAWLER_ROLLUP, DAY_IN_SECONDS );
		self::schedule_recurring( self::HOOK_WEEKLY_DIGEST, DAY_IN_SECONDS );
	}

	public static function schedule_recurring( string $hook, int $interval_seconds, array $args = array() ): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( $hook, $args, self::GROUP ) ) {
			as_schedule_recurring_action( time() + $interval_seconds, $interval_seconds, $hook, $args, self::GROUP );
		}
	}

	public static function schedule_single( string $hook, int $delay_seconds = 0, array $args = array() ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		as_schedule_single_action( time() + $delay_seconds, $hook, $args, self::GROUP );
	}

	public static function unschedule( string $hook, array $args = array() ): void {
		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return;
		}

		as_unschedule_action( $hook, $args, self::GROUP );
	}

	public static function is_scheduled( string $hook, array $args = array() ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		return (bool) as_has_scheduled_action( $hook, $args, self::GROUP );
	}

	public static function unschedule_all(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( '', array(), self::GROUP );
	}

	/**
	 * Real cron depends on site traffic when DISABLE_WP_CRON is not set and
	 * no system cron has been wired to wp-cron.php. We can't detect a system
	 * crontab directly, so we surface the traffic-dependent case, which is
	 * the common one on low-traffic sites.
	 */
	public static function is_cron_traffic_dependent(): bool {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return false; // Site owner has taken control of cron explicitly.
		}

		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			return true;
		}

		return true;
	}

	public function maybe_show_cron_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( 'watchspire_cron_notice_dismissed' ) ) {
			return;
		}

		if ( ! self::is_cron_traffic_dependent() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'watchspire' ) ) {
			return;
		}

		?>
		<?php // The watchspire-app class travels with the notice so its design tokens resolve wherever a caller places it, including outside the shell. ?>
		<div class="watchspire-app watchspire-notice watchspire-notice--info" id="watchspire-cron-notice">
			<span class="watchspire-notice-icon" aria-hidden="true"><span class="dashicons dashicons-clock"></span></span>
			<div class="watchspire-notice-body">
				<p class="watchspire-notice-title"><?php esc_html_e( 'System Cron is Recommended', 'watchspire' ); ?></p>
				<p>
					<?php esc_html_e( 'For accurate monitoring, enable WordPress cron or set up a real system cron job.', 'watchspire' ); ?>
				</p>
				<details>
					<summary><?php esc_html_e( 'Show the cron command', 'watchspire' ); ?></summary>
					<code>*/15 * * * * curl -s <?php echo esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?> >/dev/null 2>&1</code>
				</details>
			</div>
			<div class="watchspire-notice-actions">
				<a class="button" href="<?php echo esc_url( 'https://developer.wordpress.org/plugins/cron/' ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'How to Set Up Cron', 'watchspire' ); ?>
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
				</a>
				<button type="button" class="watchspire-notice-dismiss" id="watchspire-dismiss-cron-notice">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'watchspire' ); ?></span>
				</button>
			</div>
		</div>
		<?php
		// The dismiss handler lives in the enqueued assets/js/admin.js —
		// this notice only renders on WatchSpire screens, where that
		// script and its localized watchspireAdmin data are already
		// enqueued via admin_enqueue_scripts.
	}

	public function dismiss_cron_notice(): void {
		check_ajax_referer( 'watchspire_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		update_option( 'watchspire_cron_notice_dismissed', true );
		wp_send_json_success();
	}
}
