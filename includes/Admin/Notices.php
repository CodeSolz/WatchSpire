<?php
/**
 * Dismissible admin notices shown on OTHER admin screens — a welcome
 * pointer right after activation, and a review request once the site has
 * actually lived with the plugin for a while.
 *
 * WatchSpire's own screens are deliberately excluded: they already carry
 * the plugin's own UI, and a notice about WatchSpire on a WatchSpire page
 * is noise.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Admin;

defined( 'ABSPATH' ) || exit;

final class Notices {

	/**
	 * How long the site has to have had WatchSpire installed before the
	 * review request is allowed to appear.
	 */
	private const REVIEW_AFTER_DAYS = 14;

	private const OPTION_PREFIX = 'watchspire_notice_dismissed_';

	private const NONCE_ACTION = 'watchspire_notices';

	public const REVIEW_URL = 'https://wordpress.org/support/plugin/watchspire/reviews/#new-post';

	public function boot(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'wp_ajax_watchspire_dismiss_notice', array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Whether notices may render on the screen currently being loaded.
	 * Used by both the asset enqueue and the render pass so the two can
	 * never disagree.
	 */
	private function should_render(): bool {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Not on WatchSpire's own screens — free or add-on. Every one of
		// them is registered under the 'watchspire' menu, so their screen
		// ids all carry the slug.
		if ( ! $screen || false !== strpos( (string) $screen->id, 'watchspire' ) ) {
			return false;
		}

		return true;
	}

	public function enqueue( string $hook ): void {
		unset( $hook );

		if ( ! $this->should_render() || ! $this->get_notices() ) {
			return;
		}

		$css_path = WATCHSPIRE_DIR . 'assets/css/notices.css';
		$js_path  = WATCHSPIRE_DIR . 'assets/js/notices.js';

		wp_enqueue_style(
			'watchspire-notices',
			WATCHSPIRE_URL . 'assets/css/notices.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : WATCHSPIRE_VERSION
		);

		wp_enqueue_script(
			'watchspire-notices',
			WATCHSPIRE_URL . 'assets/js/notices.js',
			array(),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : WATCHSPIRE_VERSION,
			true
		);

		wp_localize_script(
			'watchspire-notices',
			'watchspireNotices',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'reviewUrl' => self::REVIEW_URL,
			)
		);
	}

	/**
	 * Every notice that currently wants to show, keyed by its dismissal id.
	 *
	 * @return array<string,array{type:string,message:string,actions?:string}>
	 */
	private function get_notices(): array {
		$notices = array();

		if ( ! self::is_dismissed( 'welcome' ) ) {
			$notices['welcome'] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: 1: opening link tag to the WatchSpire dashboard, 2: closing link tag */
					esc_html__( 'Thank you for choosing us. WatchSpire is watching your site now — %1$stake a look at what it found%2$s.', 'watchspire' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=watchspire' ) ) . '"><strong>',
					'</strong></a>'
				),
			);
		}

		if ( ! self::is_dismissed( 'review' ) && $this->review_is_due() ) {
			$notices['review'] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: 1: opening bold tag, 2: closing bold tag, 3: opening link tag to the review form, 4: closing link tag, 5: five star icons */
					esc_html__( 'You are using WatchSpire quite a while! If you are enjoying it, %3$splease consider giving us a 5-star (%5$s) rating.%4$s %1$sYour valuable review%2$s will %1$sinspire us%2$s to make it even better.', 'watchspire' ),
					'<b>',
					'</b>',
					'<a href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener noreferrer"><strong>',
					'</strong></a>',
					str_repeat( '<span class="dashicons dashicons-star-filled"></span>', 5 )
				),
				'actions' => $this->review_buttons(),
			);
		}

		/**
		 * Notices to show on non-WatchSpire admin screens, keyed by the id
		 * used to remember their dismissal. Each entry is
		 * array{type:string,message:string,actions?:string}, where 'type'
		 * is one of info|success|warning|error, 'message' is already-escaped
		 * HTML, and 'actions' is optional already-escaped button markup.
		 *
		 * @param array<string,array<string,string>> $notices
		 */
		$notices = (array) apply_filters( 'watchspire_admin_notices', $notices );

		// A listener can add entries but must not resurrect a dismissed
		// one, and anything malformed is dropped rather than rendered.
		foreach ( $notices as $id => $notice ) {
			if ( ! is_string( $id ) || ! is_array( $notice ) || empty( $notice['message'] ) || self::is_dismissed( $id ) ) {
				unset( $notices[ $id ] );
			}
		}

		return $notices;
	}

	private function review_buttons(): string {
		ob_start();
		?>
		<div class="watchspire-notice-actions">
			<button type="button" class="button button-primary watchspire-review-now"><?php esc_html_e( 'Let\'s do it now!', 'watchspire' ); ?></button>
			<button type="button" class="button watchspire-review-never"><?php esc_html_e( 'I\'ve already done it!', 'watchspire' ); ?></button>
			<button type="button" class="button watchspire-review-later"><?php esc_html_e( 'I\'ll do it later!', 'watchspire' ); ?></button>
			<button type="button" class="button watchspire-review-never"><?php esc_html_e( 'Please don\'t bother me again :(', 'watchspire' ); ?></button>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * True once the plugin has been installed long enough that asking for
	 * a review is a fair thing to do.
	 */
	private function review_is_due(): bool {
		$installed_on = (int) get_option( 'watchspire_activated_at', 0 );

		if ( ! $installed_on ) {
			// Installed before this option existed, or the activation hook
			// never ran. Start the clock now rather than asking straight away.
			update_option( 'watchspire_activated_at', time() );

			return false;
		}

		return ( time() - $installed_on ) >= ( self::REVIEW_AFTER_DAYS * DAY_IN_SECONDS );
	}

	public function render(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		foreach ( $this->get_notices() as $id => $notice ) {
			$type = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'info';
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible watchspire-admin-notice" data-watchspire-notice="<?php echo esc_attr( $id ); ?>">
				<p><strong><?php esc_html_e( 'WatchSpire', 'watchspire' ); ?></strong></p>
				<p><?php echo wp_kses_post( $notice['message'] ); ?></p>
				<?php if ( ! empty( $notice['actions'] ) ) : ?>
					<?php echo wp_kses_post( $notice['actions'] ); ?>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Remembers a dismissal. A permanent dismissal also survives the
	 * plugin being updated; a plain one is cleared again on upgrade so a
	 * "later" really does mean later.
	 */
	public function ajax_dismiss(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$id = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';

		if ( '' === $id ) {
			wp_send_json_error( null, 400 );
		}

		$permanent = ! empty( $_POST['permanent'] );

		update_option( self::OPTION_PREFIX . $id . ( $permanent ? '_permanent' : '' ), true );

		wp_send_json_success();
	}

	private static function is_dismissed( string $id ): bool {
		return (bool) get_option( self::OPTION_PREFIX . $id )
			|| (bool) get_option( self::OPTION_PREFIX . $id . '_permanent' );
	}

	/**
	 * Clears the non-permanent dismissals so the welcome pointer comes
	 * back on a fresh activation, and the review request gets one more
	 * chance after an update. Anything dismissed permanently stays gone.
	 */
	public static function reset_transient_dismissals(): void {
		foreach ( array( 'welcome', 'review' ) as $id ) {
			delete_option( self::OPTION_PREFIX . $id );
		}
	}
}
