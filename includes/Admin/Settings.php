<?php
/**
 * Settings screen: General, Monitors, Alerts, Scanning, Data & Privacy.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Admin;

use WatchSpire\Activator;
use WatchSpire\Database\Repositories\AlertsRepository;
use WatchSpire\Database\Repositories\ChangelogRepository;
use WatchSpire\Database\Repositories\ChecksRepository;
use WatchSpire\Database\Repositories\CrawlersRepository;
use WatchSpire\Database\Repositories\ErrorsRepository;
use WatchSpire\Database\Repositories\LinksRepository;
use WatchSpire\Database\Repositories\SubmissionsRepository;
use WatchSpire\Monitors\MonitorRegistry;
use WatchSpire\Scheduler\Scheduler;
use WatchSpire\Support\Settings as SettingsSupport;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION_GROUP = 'watchspire_settings_group';
	public const OPTION_NAME  = 'watchspire_settings';
	public const FORM_ID      = 'watchspire-settings-form';

	public function boot(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_post_watchspire_export_data', array( $this, 'handle_export' ) );
		add_action( 'admin_post_watchspire_purge_data', array( $this, 'handle_purge' ) );
	}

	public function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Activator::default_settings(),
			)
		);
	}

	/**
	 * Sanitizes and merges submitted fields with the existing option, so a
	 * single-tab form submission never wipes out other tabs' settings.
	 *
	 * @param mixed $input
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$existing = SettingsSupport::all();
		$out      = $existing;

		if ( isset( $input['alert_email'] ) && '' !== trim( $input['alert_email'] ) ) {
			if ( is_email( $input['alert_email'] ) ) {
				$out['alert_email'] = sanitize_email( $input['alert_email'] );
			} else {
				add_settings_error( self::OPTION_NAME, 'invalid_alert_email', __( 'Alert email was not saved: not a valid email address.', 'watchspire' ) );
			}
		}

		if ( isset( $input['mail_test_address'] ) ) {
			if ( '' === trim( $input['mail_test_address'] ) ) {
				$out['mail_test_address'] = '';
			} elseif ( is_email( $input['mail_test_address'] ) ) {
				$out['mail_test_address'] = sanitize_email( $input['mail_test_address'] );
			} else {
				add_settings_error( self::OPTION_NAME, 'invalid_test_address', __( 'Mail test address was not saved: not a valid email address.', 'watchspire' ) );
			}
		}

		if ( isset( $input['uptime_key_page'] ) ) {
			$out['uptime_key_page'] = esc_url_raw( trim( $input['uptime_key_page'] ) );
		}

		if ( isset( $input['digest_enabled'] ) ) {
			$out['digest_enabled'] = (bool) $input['digest_enabled'];
		} elseif ( isset( $input['__section'] ) && 'general' === $input['__section'] ) {
			$out['digest_enabled'] = false;
		}

		if ( isset( $input['digest_day'] ) ) {
			$valid             = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
			$out['digest_day'] = in_array( $input['digest_day'], $valid, true ) ? $input['digest_day'] : 'monday';
		}

		if ( isset( $input['digest_time'] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $input['digest_time'] ) ) {
			$out['digest_time'] = $input['digest_time'];
		}

		if ( isset( $input['monitors_enabled'] ) && is_array( $input['monitors_enabled'] ) ) {
			$known = array( 'ssl', 'http_errors', 'mail_health', 'uptime', 'link_scan', 'submission_gap' );
			foreach ( $known as $key ) {
				$out['monitors_enabled'][ $key ] = ! empty( $input['monitors_enabled'][ $key ] );
			}
		} elseif ( isset( $input['__section'] ) && 'monitors' === $input['__section'] ) {
			// All checkboxes unchecked submits no monitors_enabled array at all.
			$known                   = array( 'ssl', 'http_errors', 'mail_health', 'uptime', 'link_scan', 'submission_gap' );
			$out['monitors_enabled'] = array_fill_keys( $known, false );
		}

		if ( isset( $input['404_threshold'] ) ) {
			$out['404_threshold'] = max( 1, absint( $input['404_threshold'] ) );
		}

		if ( isset( $input['alert_dedupe_window'] ) ) {
			$out['alert_dedupe_window'] = max( 0, absint( $input['alert_dedupe_window'] ) ) * HOUR_IN_SECONDS;
		}

		if ( isset( $input['__section'] ) && in_array( $input['__section'], array( 'general', 'scanning' ), true ) ) {
			$out['link_scan_opt_in'] = ! empty( $input['link_scan_opt_in'] );
		}

		if ( isset( $input['link_scan_batch_size'] ) ) {
			$out['link_scan_batch_size'] = min( 20, max( 1, absint( $input['link_scan_batch_size'] ) ) );
		}

		if ( isset( $input['check_interval_seconds'] ) ) {
			$allowed_intervals = array( 5 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS );
			$interval          = absint( $input['check_interval_seconds'] );

			$out['check_interval_seconds'] = in_array( $interval, $allowed_intervals, true ) ? $interval : 15 * MINUTE_IN_SECONDS;
		}

		if ( isset( $input['auto_resolve_enabled'] ) ) {
			$out['auto_resolve_enabled'] = (bool) $input['auto_resolve_enabled'];
		} elseif ( isset( $input['__section'] ) && 'general' === $input['__section'] ) {
			$out['auto_resolve_enabled'] = false;
		}

		if ( isset( $input['auto_resolve_after'] ) ) {
			$out['auto_resolve_after'] = min( 10, max( 1, absint( $input['auto_resolve_after'] ) ) );
		}

		if ( isset( $input['retention_monitor_days'] ) ) {
			$allowed_monitor_days = array( 7, 14, 30 );
			$monitor_days         = absint( $input['retention_monitor_days'] );

			$out['retention_monitor_days'] = in_array( $monitor_days, $allowed_monitor_days, true ) ? $monitor_days : 30;
		}

		if ( isset( $input['retention_alert_days'] ) ) {
			$allowed_alert_days = array( 7, 14, 30, 60, 90 );
			$alert_days         = absint( $input['retention_alert_days'] );

			$out['retention_alert_days'] = in_array( $alert_days, $allowed_alert_days, true ) ? $alert_days : 90;
		}

		if ( isset( $input['link_scan_broken_threshold'] ) ) {
			$out['link_scan_broken_threshold'] = max( 1, absint( $input['link_scan_broken_threshold'] ) );
		}

		$previous_interval = $existing['check_interval_seconds'] ?? null;
		if ( isset( $out['check_interval_seconds'] ) && $previous_interval !== $out['check_interval_seconds'] ) {
			Scheduler::unschedule( Scheduler::HOOK_RUN_MONITORS );
			Scheduler::schedule_recurring( Scheduler::HOOK_RUN_MONITORS, $out['check_interval_seconds'] );
		}

		SettingsSupport::flush_cache();

		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			return;
		}

		$tabs = apply_filters(
			'watchspire_settings_tabs',
			array(
				'general'  => __( 'General', 'watchspire' ),
				'monitors' => __( 'Monitors', 'watchspire' ),
				'alerts'   => __( 'Alerts', 'watchspire' ),
				'scanning' => __( 'Scanning', 'watchspire' ),
				'privacy'  => __( 'Data & Privacy', 'watchspire' ),
			)
		);

		$icons = apply_filters(
			'watchspire_settings_tab_icons',
			array(
				'general'  => 'admin-home',
				'monitors' => 'visibility',
				'alerts'   => 'bell',
				'scanning' => 'admin-links',
				'privacy'  => 'privacy',
			)
		);

		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}

		// Only the tabs that still render a plain settings form get the
		// header's Save Changes button — it submits the tab's <form> from
		// outside it via the HTML5 form="" attribute. Privacy's destructive
		// purge action and any tab added by a third party keep their own in-context
		// controls instead.
		$has_header_save = in_array( $current, array( 'general', 'monitors', 'alerts', 'scanning' ), true );
		$header_actions  = $has_header_save
			? '<button type="submit" form="' . esc_attr( self::FORM_ID ) . '" class="button button-primary"><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html__( 'Save Changes', 'watchspire' ) . '</button>'
			: '';
		?>
		<?php AdminMenu::render_page_header( __( 'Settings', 'watchspire' ), __( 'Configure what WatchSpire watches and how it reaches you.', 'watchspire' ), $header_actions ); ?>
		<?php settings_errors( self::OPTION_NAME ); ?>

		<nav class="watchspire-settings-tabbar">
			<?php
			foreach ( $tabs as $slug => $label ) :
				$tab_url = add_query_arg(
					array(
						'page' => 'watchspire-settings',
						'tab'  => $slug,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="<?php echo $slug === $current ? 'is-active' : ''; ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $icons[ $slug ] ?? 'admin-generic' ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'general' === $current ) : ?>
			<?php $this->render_general_tab(); ?>
		<?php else : ?>
			<div class="watchspire-settings-panel">
				<?php
				switch ( $current ) {
					case 'monitors':
						$this->render_monitors_tab();
						break;
					case 'alerts':
						$this->render_alerts_tab();
						break;
					case 'scanning':
						$this->render_scanning_tab();
						break;
					case 'privacy':
						$this->render_privacy_tab();
						break;
					default:
						do_action( 'watchspire_settings_render_tab_' . $current );
				}
				?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * A labelled toggle switch bound to a real checkbox, for accessibility.
	 */
	private function toggle_row( string $name, bool $checked, string $label, string $desc = '', string $tooltip = '' ): void {
		?>
		<div class="watchspire-toggle-row">
			<div>
				<p class="watchspire-toggle-row__label">
					<?php echo esc_html( $label ); ?>
					<?php if ( $tooltip ) : ?>
						<span class="dashicons dashicons-info-outline" aria-hidden="true" title="<?php echo esc_attr( $tooltip ); ?>"></span>
					<?php endif; ?>
				</p>
				<?php
				if ( $desc ) :
					?>
					<p class="watchspire-toggle-row__desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</div>
			<label class="watchspire-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
				<span class="watchspire-toggle__track" aria-hidden="true"></span>
			</label>
		</div>
		<?php
	}

	private function form_open(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" id="' . esc_attr( self::FORM_ID ) . '">';
		settings_fields( self::OPTION_GROUP );
	}

	private function form_close(): void {
		echo '</form>';
	}

	/**
	 * A field label with a small hover tooltip (native title attribute —
	 * no JS tooltip library in this admin UI).
	 */
	private function field_label( string $input_id, string $text, string $tooltip = '' ): void {
		?>
		<label class="watchspire-field__label" for="<?php echo esc_attr( $input_id ); ?>">
			<?php echo esc_html( $text ); ?>
			<?php if ( $tooltip ) : ?>
				<span class="dashicons dashicons-info-outline" aria-hidden="true" title="<?php echo esc_attr( $tooltip ); ?>"></span>
			<?php endif; ?>
		</label>
		<?php
	}

	private function settings_card_open( string $icon, string $title ): void {
		?>
		<div class="watchspire-settings-card">
			<div class="watchspire-settings-card__head">
				<span class="watchspire-settings-card__icon" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span></span>
				<h3><?php echo esc_html( $title ); ?></h3>
			</div>
		<?php
	}

	private function settings_card_close(): void {
		echo '</div>';
	}

	private function render_general_tab(): void {
		$email               = SettingsSupport::get( 'alert_email', get_option( 'admin_email' ) );
		$key_page            = SettingsSupport::get( 'uptime_key_page', '' );
		$digest_on           = SettingsSupport::get( 'digest_enabled', true );
		$day                 = SettingsSupport::get( 'digest_day', 'monday' );
		$time                = SettingsSupport::get( 'digest_time', '08:00' );
		$check_interval      = (int) SettingsSupport::get( 'check_interval_seconds', 15 * MINUTE_IN_SECONDS );
		$auto_resolve_on     = SettingsSupport::get( 'auto_resolve_enabled', true );
		$auto_resolve_after  = (int) SettingsSupport::get( 'auto_resolve_after', 2 );
		$retain_monitor      = (int) SettingsSupport::get( 'retention_monitor_days', 30 );
		$retain_alert        = (int) SettingsSupport::get( 'retention_alert_days', 90 );
		$link_scan_on        = SettingsSupport::get( 'link_scan_opt_in', false );
		$link_scan_threshold = (int) SettingsSupport::get( 'link_scan_broken_threshold', 5 );
		$field_name          = self::OPTION_NAME;
		$privacy_tab_url     = add_query_arg(
			array(
				'page' => 'watchspire-settings',
				'tab'  => 'privacy',
			),
			admin_url( 'admin.php' )
		);

		$interval_options = array(
			5 * MINUTE_IN_SECONDS  => __( '5 minutes', 'watchspire' ),
			15 * MINUTE_IN_SECONDS => __( '15 minutes', 'watchspire' ),
			30 * MINUTE_IN_SECONDS => __( '30 minutes', 'watchspire' ),
			HOUR_IN_SECONDS        => __( '1 hour', 'watchspire' ),
		);
		?>
		<?php $this->form_open(); ?>
		<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[__section]" value="general" />

		<div class="watchspire-settings-grid">

			<?php $this->settings_card_open( 'admin-generic', __( 'General Settings', 'watchspire' ) ); ?>

				<div class="watchspire-field">
					<?php $this->field_label( 'watchspire-alert-email', __( 'Alert Email', 'watchspire' ), __( 'Email address to receive immediate alerts.', 'watchspire' ) ); ?>
					<input type="email" id="watchspire-alert-email" name="<?php echo esc_attr( $field_name ); ?>[alert_email]" value="<?php echo esc_attr( $email ); ?>" required />
					<p class="watchspire-field__desc"><?php esc_html_e( 'Email address to receive immediate alerts.', 'watchspire' ); ?></p>
				</div>

				<div class="watchspire-field">
					<?php $this->field_label( 'watchspire-key-page', __( 'Key Page to Monitor', 'watchspire' ), __( 'The primary page WatchSpire will monitor for uptime and performance.', 'watchspire' ) ); ?>
					<input type="url" id="watchspire-key-page" name="<?php echo esc_attr( $field_name ); ?>[uptime_key_page]" value="<?php echo esc_attr( $key_page ); ?>" placeholder="https://example.com/" />
					<p class="watchspire-field__desc"><?php esc_html_e( 'The primary page WatchSpire will monitor for uptime and performance.', 'watchspire' ); ?></p>
				</div>

				<div class="watchspire-field">
					<?php $this->toggle_row( $field_name . '[digest_enabled]', (bool) $digest_on, __( 'Weekly Summary Email', 'watchspire' ), '', __( 'Enable weekly summary reports.', 'watchspire' ) ); ?>
					<div class="watchspire-field-inline">
						<select name="<?php echo esc_attr( $field_name ); ?>[digest_day]">
							<?php foreach ( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ) as $d ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $day, $d ); ?>><?php echo esc_html( ucfirst( $d ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="time" name="<?php echo esc_attr( $field_name ); ?>[digest_time]" value="<?php echo esc_attr( $time ); ?>" />
					</div>
					<p class="watchspire-field__desc"><?php esc_html_e( 'Enable weekly summary reports.', 'watchspire' ); ?></p>
				</div>

				<div class="watchspire-field">
					<?php $this->field_label( 'watchspire-check-interval', __( 'Monitor Intervals', 'watchspire' ), __( 'How often WatchSpire checks your site.', 'watchspire' ) ); ?>
					<select id="watchspire-check-interval" name="<?php echo esc_attr( $field_name ); ?>[check_interval_seconds]">
						<?php foreach ( $interval_options as $seconds => $label ) : ?>
							<option value="<?php echo esc_attr( $seconds ); ?>" <?php selected( $check_interval, $seconds ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="watchspire-field__desc"><?php esc_html_e( 'How often WatchSpire checks your site.', 'watchspire' ); ?></p>
				</div>

				<div class="watchspire-field">
					<?php $this->toggle_row( $field_name . '[auto_resolve_enabled]', (bool) $auto_resolve_on, __( 'Auto-resolve Recovery', 'watchspire' ), '', __( 'Prevents alert flapping when issues are intermittent.', 'watchspire' ) ); ?>
					<div class="watchspire-field-inline">
						<?php esc_html_e( 'Automatically resolve an alert after', 'watchspire' ); ?>
						<input type="number" min="1" max="10" class="small-text" name="<?php echo esc_attr( $field_name ); ?>[auto_resolve_after]" value="<?php echo esc_attr( $auto_resolve_after ); ?>" />
						<select name="<?php echo esc_attr( $field_name ); ?>[auto_resolve_unit]">
							<option value="consecutive"><?php esc_html_e( 'consecutive', 'watchspire' ); ?></option>
						</select>
						<?php esc_html_e( 'successful checks', 'watchspire' ); ?>
					</div>
					<p class="watchspire-field__desc"><?php esc_html_e( 'Prevents alert flapping when issues are intermittent.', 'watchspire' ); ?></p>
				</div>

			<?php $this->settings_card_close(); ?>

			<div class="watchspire-settings-stack">

				<?php $this->settings_card_open( 'database', __( 'Data Retention', 'watchspire' ) ); ?>
					<div class="watchspire-field">
						<?php $this->field_label( 'watchspire-retain-monitor', __( 'Retain Monitor Data', 'watchspire' ), __( 'Performance, uptime and alert data.', 'watchspire' ) ); ?>
						<select id="watchspire-retain-monitor" name="<?php echo esc_attr( $field_name ); ?>[retention_monitor_days]">
							<?php foreach ( array( 7, 14, 30 ) as $d ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $retain_monitor, $d ); ?>>
									<?php
									/* translators: %d: number of days */
									echo esc_html( sprintf( _n( '%d day', '%d days', $d, 'watchspire' ), $d ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="watchspire-field__desc"><?php esc_html_e( 'Performance, uptime and alert data.', 'watchspire' ); ?></p>
					</div>
					<div class="watchspire-field">
						<?php $this->field_label( 'watchspire-retain-alert', __( 'Retain Alert Logs', 'watchspire' ), __( 'Detailed alert history and notifications.', 'watchspire' ) ); ?>
						<select id="watchspire-retain-alert" name="<?php echo esc_attr( $field_name ); ?>[retention_alert_days]">
							<?php foreach ( array( 7, 14, 30, 60, 90 ) as $d ) : ?>
								<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $retain_alert, $d ); ?>>
									<?php
									/* translators: %d: number of days */
									echo esc_html( sprintf( _n( '%d day', '%d days', $d, 'watchspire' ), $d ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="watchspire-field__desc"><?php esc_html_e( 'Detailed alert history and notifications.', 'watchspire' ); ?></p>
					</div>
				<?php $this->settings_card_close(); ?>

				<?php $this->settings_card_open( 'download', __( 'Export Data', 'watchspire' ) ); ?>
					<p class="watchspire-field__desc" style="margin:0 0 14px;"><?php esc_html_e( 'Download your WatchSpire data for backup or analysis. Exports include monitors, alerts, uptime logs, and settings.', 'watchspire' ); ?></p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=watchspire_export_data' ), 'watchspire_export_data' ) ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Export Data', 'watchspire' ); ?>
					</a>
				<?php $this->settings_card_close(); ?>

				<?php $this->settings_card_open( 'admin-links', __( 'Link Scanning', 'watchspire' ) ); ?>
					<?php $this->toggle_row( $field_name . '[link_scan_opt_in]', (bool) $link_scan_on, __( 'Enable link scanning', 'watchspire' ) ); ?>
					<div class="watchspire-field" style="margin-top:14px;">
						<?php $this->field_label( 'watchspire-link-threshold', __( 'Broken Link Alert Threshold', 'watchspire' ) ); ?>
						<input type="number" min="1" id="watchspire-link-threshold" class="small-text" name="<?php echo esc_attr( $field_name ); ?>[link_scan_broken_threshold]" value="<?php echo esc_attr( $link_scan_threshold ); ?>" />
						<p class="watchspire-field__desc"><?php esc_html_e( 'Alert me when broken links exceed this number.', 'watchspire' ); ?></p>
					</div>
				<?php $this->settings_card_close(); ?>

			</div>
		</div>
		<?php $this->form_close(); ?>

		<div class="watchspire-settings-card watchspire-settings-card--full">
			<div class="watchspire-settings-card__head">
				<span class="watchspire-settings-card__icon" aria-hidden="true"><span class="dashicons dashicons-shield"></span></span>
				<h3><?php esc_html_e( 'Privacy & Data', 'watchspire' ); ?></h3>
			</div>
			<div class="watchspire-columns">
				<div>
					<p class="watchspire-privacy-col-title"><?php esc_html_e( 'What WatchSpire Stores on Your Site', 'watchspire' ); ?></p>
					<ul class="watchspire-check-list is-check">
						<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Monitor settings, schedules, and preferences', 'watchspire' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Uptime checks, response times, and performance data', 'watchspire' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Alert logs, email history, and notification status', 'watchspire' ); ?></li>
						<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'All data is stored securely in your WordPress database.', 'watchspire' ); ?></li>
					</ul>
				</div>
				<div>
					<p class="watchspire-privacy-col-title"><?php esc_html_e( 'What Leaves Your Site', 'watchspire' ); ?></p>
					<ul class="watchspire-check-list is-info">
						<li><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Alert notifications (delivered via email, when enabled)', 'watchspire' ); ?></li>
						<li><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Requests to your own site (uptime self-checks and robots.txt)', 'watchspire' ); ?></li>
						<li><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'No content, user data, or passwords are transmitted', 'watchspire' ); ?></li>
					</ul>
					<p style="margin:14px 0 0;">
						<a href="<?php echo esc_url( $privacy_tab_url ); ?>">
							<?php esc_html_e( 'Learn more about our privacy practices', 'watchspire' ); ?> &rarr;
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_monitors_tab(): void {
		$registry  = new MonitorRegistry();
		$enabled   = SettingsSupport::get( 'monitors_enabled', array() );
		$threshold = SettingsSupport::get( '404_threshold', 20 );

		$this->form_open();
		?>
		<h2 style="margin-top:0;padding-top:0;border-top:none;"><?php esc_html_e( 'Monitors', 'watchspire' ); ?></h2>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[__section]" value="monitors" />

		<?php foreach ( $registry->get_monitors() as $monitor ) : ?>
			<?php
			$this->toggle_row(
				self::OPTION_NAME . '[monitors_enabled][' . $monitor->get_id() . ']',
				! isset( $enabled[ $monitor->get_id() ] ) || $enabled[ $monitor->get_id() ],
				$monitor->get_label(),
				$monitor->get_description()
			);
			?>
		<?php endforeach; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="watchspire-404-threshold"><?php esc_html_e( '404 alert threshold', 'watchspire' ); ?></label></th>
				<td>
					<input type="number" min="1" id="watchspire-404-threshold" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[404_threshold]" value="<?php echo esc_attr( $threshold ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'Alert when a single URL gets this many 404 hits within 24 hours.', 'watchspire' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		$this->form_close();
	}

	private function render_alerts_tab(): void {
		$window_hours = (int) SettingsSupport::get( 'alert_dedupe_window', 6 * HOUR_IN_SECONDS ) / HOUR_IN_SECONDS;

		$this->form_open();
		?>
		<h2 style="margin-top:0;padding-top:0;border-top:none;"><?php esc_html_e( 'Alerts', 'watchspire' ); ?></h2>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[__section]" value="alerts" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="watchspire-dedupe"><?php esc_html_e( 'Repeat-alert window', 'watchspire' ); ?></label></th>
				<td>
					<input type="number" min="0" id="watchspire-dedupe" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[alert_dedupe_window]" value="<?php echo esc_attr( $window_hours ); ?>" class="small-text" />
					<?php esc_html_e( 'hours', 'watchspire' ); ?>
					<p class="description"><?php esc_html_e( 'The same failure won\'t alert again within this window. Set to 0 to alert every time.', 'watchspire' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		$this->form_close();

		do_action( 'watchspire_settings_alerts_tab_after' );
	}

	private function render_scanning_tab(): void {
		$opted_in   = SettingsSupport::get( 'link_scan_opt_in', false );
		$batch_size = SettingsSupport::get( 'link_scan_batch_size', 20 );

		$this->form_open();
		?>
		<h2 style="margin-top:0;padding-top:0;border-top:none;"><?php esc_html_e( 'Scanning', 'watchspire' ); ?></h2>
		<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[__section]" value="scanning" />

		<?php $this->toggle_row( self::OPTION_NAME . '[link_scan_opt_in]', $opted_in, __( 'Broken link & image scanning', 'watchspire' ), __( 'Off by default. Scans run in small batches in the background and never block your site — recommended to leave off on very large sites with limited hosting resources.', 'watchspire' ) ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="watchspire-batch-size"><?php esc_html_e( 'Batch size', 'watchspire' ); ?></label></th>
				<td>
					<input type="number" min="1" max="20" id="watchspire-batch-size" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[link_scan_batch_size]" value="<?php echo esc_attr( $batch_size ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'URLs checked per batch (max 20). Automatically halved on hosts with low memory or execution-time limits.', 'watchspire' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		$this->form_close();
	}

	private function render_privacy_tab(): void {
		$general_url = add_query_arg(
			array(
				'page' => 'watchspire-settings',
				'tab'  => 'general',
			),
			admin_url( 'admin.php' )
		);
		?>
		<h2 style="margin-top:0;padding-top:0;border-top:none;"><?php esc_html_e( 'Data & Privacy', 'watchspire' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to the General settings tab */
				esc_html__( 'Data retention is set from the %s tab.', 'watchspire' ),
				'<a href="' . esc_url( $general_url ) . '">' . esc_html__( 'General', 'watchspire' ) . '</a>'
			);
			?>
		</p>

		<h2><?php esc_html_e( 'What leaves this site', 'watchspire' ); ?></h2>
		<div class="watchspire-panel" style="background:var(--wp-success-bg);border-color:var(--wp-success-border);box-shadow:none;">
			<p style="margin:0 0 8px;color:var(--wp-ink-soft);font-size:13px;">
				<?php esc_html_e( 'WatchSpire stores everything on your own server and does not contact any third-party service. Its checks request your own site (uptime self-checks and robots.txt), and — only if you turn on broken link scanning — the URLs your own content already links to. No usage tracking, no analytics, no phoning home.', 'watchspire' ); ?>
			</p>
			<p style="margin:0;color:var(--wp-ink-soft);font-size:13px;"><?php esc_html_e( 'WatchSpire never stores form field contents or customer personal data — only outcomes (delivered / failed) and metadata.', 'watchspire' ); ?></p>
		</div>

		<h2><?php esc_html_e( 'Export & purge', 'watchspire' ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=watchspire_export_data' ), 'watchspire_export_data' ) ); ?>">
				<span class="dashicons dashicons-download" aria-hidden="true" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom;"></span>
				<?php esc_html_e( 'Export all WatchSpire data (JSON)', 'watchspire' ); ?>
			</a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete all WatchSpire data on this site? This cannot be undone.', 'watchspire' ) ); ?>');">
			<input type="hidden" name="action" value="watchspire_purge_data" />
			<?php wp_nonce_field( 'watchspire_purge_data' ); ?>
			<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Purge all WatchSpire data', 'watchspire' ); ?></button>
		</form>
		<?php
	}

	public function handle_export(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'watchspire' ) );
		}

		check_admin_referer( 'watchspire_export_data' );

		$data = array(
			'settings'    => SettingsSupport::all(),
			'checks'      => ( new ChecksRepository() )->recent_failures( 200 ),
			'changelog'   => ( new ChangelogRepository() )->recent( 500 ),
			'crawlers'    => ( new CrawlersRepository() )->trend( 90 ),
			'errors'      => ( new ErrorsRepository() )->top( 200 ),
			'alerts'      => ( new AlertsRepository() )->recent( 200 ),
			'submissions' => ( new SubmissionsRepository() )->distinct_forms(),
			'exported_at' => gmdate( 'c' ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="watchspire-export-' . gmdate( 'Y-m-d' ) . '.json"' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function handle_purge(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'watchspire' ) );
		}

		check_admin_referer( 'watchspire_purge_data' );

		( new ChecksRepository() )->truncate();
		( new ChangelogRepository() )->truncate();
		( new SubmissionsRepository() )->truncate();
		( new CrawlersRepository() )->truncate();
		( new AlertsRepository() )->truncate();
		( new ErrorsRepository() )->truncate();
		( new LinksRepository() )->truncate();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'watchspire-settings',
					'tab'    => 'privacy',
					'purged' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
