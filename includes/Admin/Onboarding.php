<?php
/**
 * Three-step onboarding wizard shown once after activation.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Admin;

use WatchSpire\Monitors\MonitorRegistry;
use WatchSpire\Support\Settings as SettingsSupport;

defined( 'ABSPATH' ) || exit;

final class Onboarding {

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_post_watchspire_onboarding_step', array( $this, 'handle_step' ) );
		add_action( 'admin_post_watchspire_onboarding_skip', array( $this, 'handle_skip' ) );
	}

	public function register_page(): void {
		add_submenu_page( null, __( 'WatchSpire Setup', 'watchspire' ), __( 'WatchSpire Setup', 'watchspire' ), AdminMenu::CAPABILITY, 'watchspire-onboarding', array( $this, 'render' ) ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
	}

	public function maybe_redirect(): void {
		if ( ! get_option( 'watchspire_do_activation_redirect' ) ) {
			return;
		}

		delete_option( 'watchspire_do_activation_redirect' );

		if ( isset( $_GET['activate-multi'] ) || wp_doing_ajax() || is_network_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			return;
		}

		if ( get_option( 'watchspire_onboarding_complete' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=watchspire-onboarding' ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			return;
		}

		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$step = max( 1, min( 3, $step ) );

		$skip_url = wp_nonce_url( admin_url( 'admin-post.php?action=watchspire_onboarding_skip' ), 'watchspire_onboarding_skip' );

		$steps = array(
			1 => __( 'Alert email', 'watchspire' ),
			2 => __( 'Monitors', 'watchspire' ),
			3 => __( 'Link scanning', 'watchspire' ),
		);
		?>
		<div class="wrap watchspire-app">
			<?php // Anchor for WordPress's admin-notice relocation — see AdminMenu::open_shell(). ?>
			<h1 class="screen-reader-text"><?php esc_html_e( 'WatchSpire Setup', 'watchspire' ); ?></h1>
			<div class="watchspire-onboarding-shell">
				<div class="watchspire-onboarding">
					<div class="watchspire-onboarding__brand">
						<span class="watchspire-brand__mark" aria-hidden="true"><?php echo AdminMenu::logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="watchspire-brand__name"><?php esc_html_e( 'WatchSpire', 'watchspire' ); ?></span>
					</div>

					<h2><?php esc_html_e( 'Welcome to WatchSpire', 'watchspire' ); ?></h2>
					<p class="watchspire-onboarding__intro"><?php esc_html_e( 'Three quick steps and you will be watching for the failures that do not show up as "the site is down."', 'watchspire' ); ?></p>

					<div class="watchspire-onboarding__progress" aria-hidden="true">
						<?php foreach ( $steps as $num => $label ) : ?>
							<span class="<?php echo $num <= $step ? 'is-done' : ''; ?> <?php echo $num === $step ? 'is-active' : ''; ?>"></span>
						<?php endforeach; ?>
					</div>

					<ul class="watchspire-onboarding__steps">
						<?php foreach ( $steps as $num => $label ) : ?>
							<li class="<?php echo $num === $step ? 'is-active' : ( $num < $step ? 'is-done' : '' ); ?>">
								<span class="watchspire-onboarding__step-num"><?php echo $num < $step ? '&#10003;' : esc_html( (string) $num ); ?></span>
								<?php echo esc_html( $label ); ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="watchspire_onboarding_step" />
						<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
						<?php wp_nonce_field( 'watchspire_onboarding_step' ); ?>

						<fieldset>
							<?php if ( 1 === $step ) : ?>
								<p class="watchspire-onboarding__question"><?php esc_html_e( 'Where should WatchSpire send you alerts when something breaks?', 'watchspire' ); ?></p>
								<label for="watchspire-onboard-email" class="screen-reader-text"><?php esc_html_e( 'Alert email', 'watchspire' ); ?></label>
								<input type="email" id="watchspire-onboard-email" name="alert_email" required value="<?php echo esc_attr( SettingsSupport::get( 'alert_email', get_option( 'admin_email' ) ) ); ?>" />
							<?php elseif ( 2 === $step ) : ?>
								<p class="watchspire-onboarding__question"><?php esc_html_e( 'Choose which checks to run. You can change these anytime in Settings.', 'watchspire' ); ?></p>
								<?php
								$enabled = SettingsSupport::get( 'monitors_enabled', array() );
								foreach ( ( new MonitorRegistry() )->get_monitors() as $monitor ) :
									if ( 'link_scan' === $monitor->get_id() ) {
										continue; // Handled explicitly in step 3.
									}
									?>
									<div class="watchspire-onboarding__monitor-row">
										<label class="watchspire-toggle">
											<input type="checkbox" name="monitors_enabled[<?php echo esc_attr( $monitor->get_id() ); ?>]" value="1" <?php checked( ! isset( $enabled[ $monitor->get_id() ] ) || $enabled[ $monitor->get_id() ] ); ?> />
											<span class="watchspire-toggle__track" aria-hidden="true"></span>
										</label>
										<span><?php echo esc_html( $monitor->get_label() ); ?></span>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<p class="watchspire-onboarding__question"><?php esc_html_e( 'Broken link & image scanning is optional and off by default — it scans your content in the background, in small batches, and never blocks your site.', 'watchspire' ); ?></p>
								<div class="watchspire-onboarding__monitor-row">
									<label class="watchspire-toggle">
										<input type="checkbox" name="link_scan_opt_in" value="1" />
										<span class="watchspire-toggle__track" aria-hidden="true"></span>
									</label>
									<span><?php esc_html_e( 'Enable link scanning now', 'watchspire' ); ?></span>
								</div>
							<?php endif; ?>
						</fieldset>

						<div class="watchspire-onboarding__actions">
							<a class="watchspire-onboarding__skip" href="<?php echo esc_url( $skip_url ); ?>"><?php esc_html_e( 'Skip setup', 'watchspire' ); ?></a>
							<button type="submit" class="button button-primary">
								<?php echo 3 === $step ? esc_html__( 'Finish setup', 'watchspire' ) : esc_html__( 'Next →', 'watchspire' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_step(): void {
		check_admin_referer( 'watchspire_onboarding_step' );

		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'watchspire' ) );
		}

		$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;

		if ( 1 === $step && isset( $_POST['alert_email'] ) && is_email( wp_unslash( $_POST['alert_email'] ) ) ) {
			SettingsSupport::set( 'alert_email', sanitize_email( wp_unslash( $_POST['alert_email'] ) ) );
		}

		if ( 2 === $step ) {
			// Checkbox array: only the keys carry meaning (a present key means
			// that monitor was ticked). map_deep() sanitizes every submitted
			// value, then the ids themselves are run through sanitize_key()
			// before being trusted as array keys.
			$submitted_raw = isset( $_POST['monitors_enabled'] ) && is_array( $_POST['monitors_enabled'] )
				? map_deep( wp_unslash( $_POST['monitors_enabled'] ), 'sanitize_text_field' )
				: array();

			$submitted = array();

			foreach ( array_keys( (array) $submitted_raw ) as $submitted_id ) {
				$submitted[ sanitize_key( $submitted_id ) ] = true;
			}

			$enabled = SettingsSupport::get( 'monitors_enabled', array() );
			foreach ( ( new MonitorRegistry() )->get_monitors() as $monitor ) {
				if ( 'link_scan' === $monitor->get_id() ) {
					continue;
				}
				$enabled[ $monitor->get_id() ] = ! empty( $submitted[ $monitor->get_id() ] );
			}
			SettingsSupport::set( 'monitors_enabled', $enabled );
		}

		if ( 3 === $step ) {
			SettingsSupport::set( 'link_scan_opt_in', ! empty( $_POST['link_scan_opt_in'] ) );
			update_option( 'watchspire_onboarding_complete', true );
			wp_safe_redirect( admin_url( 'admin.php?page=watchspire&onboarded=1' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=watchspire-onboarding&step=' . ( $step + 1 ) ) );
		exit;
	}

	public function handle_skip(): void {
		check_admin_referer( 'watchspire_onboarding_skip' );

		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'watchspire' ) );
		}

		update_option( 'watchspire_onboarding_complete', true );

		wp_safe_redirect( admin_url( 'admin.php?page=watchspire' ) );
		exit;
	}
}
