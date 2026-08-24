<?php
/**
 * Registers the admin menu and renders the dashboard, change log, and
 * broken-links screens.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Admin;

use WatchSpire\Database\Repositories\ChangelogRepository;
use WatchSpire\Database\Repositories\ChecksRepository;
use WatchSpire\Database\Repositories\CrawlersRepository;
use WatchSpire\Database\Repositories\ErrorsRepository;
use WatchSpire\Database\Repositories\LinksRepository;
use WatchSpire\Database\Repositories\SubmissionsRepository;
use WatchSpire\Monitors\LinkScan\LinkScanManager;
use WatchSpire\Monitors\MonitorRegistry;
use WatchSpire\Support\Pro;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public const CAPABILITY = 'manage_options';

	/** Menu slug for the "Upgrade to Pro" pointer; it renders no page of its own. */
	private const UPGRADE_PAGE = 'watchspire-go-pro';

	/**
	 * Preset dashboard look-back windows, in days. Always available; the
	 * watchspire_dashboard_allowed_ranges filter can add to these but
	 * never remove one.
	 */
	private const DEFAULT_RANGES = array( 7, 30, 90 );

	/**
	 * Longest look-back the dashboard will build a daily series for, so
	 * neither a hand-edited URL nor a misbehaving add-on can ask for a
	 * chart with tens of thousands of buckets in it.
	 */
	private const MAX_RANGE_DAYS = 730;

	private string $dashboard_hook = '';

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_upgrade_link_script' ) );

		add_action( 'wp_ajax_watchspire_run_check_now', array( $this, 'ajax_run_check_now' ) );
		add_action( 'wp_ajax_watchspire_run_all_checks', array( $this, 'ajax_run_all_checks' ) );
		add_action( 'wp_ajax_watchspire_link_scan_action', array( $this, 'ajax_link_scan_action' ) );
		add_action( 'wp_ajax_watchspire_link_scan_poll', array( $this, 'ajax_link_scan_poll' ) );
		add_action( 'wp_ajax_watchspire_link_row_action', array( $this, 'ajax_link_row_action' ) );
		add_action( 'admin_post_watchspire_export_changelog', array( $this, 'export_changelog' ) );
	}

	public function register_menu(): void {
		$this->dashboard_hook = add_menu_page(
			__( 'WatchSpire', 'watchspire' ),
			__( 'WatchSpire', 'watchspire' ),
			self::CAPABILITY,
			'watchspire',
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			58
		);

		add_submenu_page( 'watchspire', __( 'Dashboard', 'watchspire' ), __( 'Dashboard', 'watchspire' ), self::CAPABILITY, 'watchspire', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'watchspire', __( 'Change Log', 'watchspire' ), __( 'Change Log', 'watchspire' ), self::CAPABILITY, 'watchspire-changelog', array( $this, 'render_changelog' ) );
		add_submenu_page( 'watchspire', __( 'Broken Links & Images', 'watchspire' ), __( 'Broken Links', 'watchspire' ), self::CAPABILITY, 'watchspire-links', array( $this, 'render_links' ) );
		add_submenu_page( 'watchspire', __( 'Settings', 'watchspire' ), __( 'Settings', 'watchspire' ), self::CAPABILITY, 'watchspire-settings', array( $this, 'render_settings_placeholder' ) );

		// A pointer to the separate Pro add-on, and nothing more — no
		// WatchSpire feature depends on it. Pointless once Pro is already
		// running, so it is not registered at all in that case. The item
		// has no page of its own: maybe_redirect_to_upgrade() sends the
		// click straight out before any output starts.
		if ( ! Pro::is_active() ) {
			add_submenu_page(
				'watchspire',
				__( 'Upgrade to Pro', 'watchspire' ),
				'<span class="watchspire-go-pro" style="color:#c60051"><span class="dashicons dashicons-star-filled" style="font-size:17px;color:#c60051"></span> ' . esc_html__( 'Upgrade to Pro', 'watchspire' ) . '</span>',
				self::CAPABILITY,
				self::UPGRADE_PAGE,
				'__return_null'
			);
		}
	}

	/**
	 * Opens the "Upgrade to Pro" menu item in a new tab, pointing straight
	 * at the product page.
	 *
	 * WordPress builds submenu anchors from the page slug and offers no
	 * way to set a target on one, so the attribute can only be added
	 * here. The href is rewritten at the same time to skip the
	 * wp-admin round trip. With JavaScript off nothing breaks: the item
	 * still links to its own slug, and maybe_redirect_to_upgrade() sends
	 * that tab to the same destination.
	 */
	public function enqueue_upgrade_link_script(): void {
		if ( Pro::is_active() || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$js = sprintf(
			'( function () {
				var apply = function () {
					var label = document.querySelector( "#adminmenu .watchspire-go-pro" );
					var link  = label && label.closest ? label.closest( "a" ) : null;
					if ( ! link ) { return; }
					link.href = %1$s;
					link.target = "_blank";
					link.rel = "noopener noreferrer";
					link.setAttribute( "aria-label", %2$s );
				};
				if ( "loading" === document.readyState ) {
					document.addEventListener( "DOMContentLoaded", apply );
				} else {
					apply();
				}
			} )();',
			wp_json_encode( Pro::upgrade_url( 'wp-menu' ) ),
			wp_json_encode( __( 'Upgrade to WatchSpire Pro (opens in a new tab)', 'watchspire' ) )
		);

		wp_add_inline_script( 'common', $js );
	}

	/**
	 * Fallback for the "Upgrade to Pro" menu item when JavaScript has not
	 * rewritten its link: sends the request out to the product page.
	 *
	 * Runs on admin_init, before any output, so this is a plain header
	 * redirect — the redirect deliberately does not go through a rendered
	 * page and a script tag.
	 */
	public function maybe_redirect_to_upgrade(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::UPGRADE_PAGE !== $page || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// Not wp_safe_redirect(): the destination is deliberately off-site,
		// and it is a fixed URL rather than anything read from the request.
		wp_redirect( Pro::upgrade_url( 'wp-menu' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'watchspire' ) ) {
			return;
		}

		$css_path = WATCHSPIRE_DIR . 'assets/css/admin.css';
		$js_path  = WATCHSPIRE_DIR . 'assets/js/admin.js';

		// Cache-bust on the file's own modified time rather than the static
		// plugin version, so a browser that already cached an older
		// admin.css always picks up the latest one — no separate manual
		// version bump needed on every style/script change.
		$css_version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WATCHSPIRE_VERSION;
		$js_version  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WATCHSPIRE_VERSION;

		wp_enqueue_style( 'watchspire-admin', WATCHSPIRE_URL . 'assets/css/admin.css', array(), $css_version );
		wp_enqueue_script( 'watchspire-admin', WATCHSPIRE_URL . 'assets/js/admin.js', array(), $js_version, true );
		wp_localize_script(
			'watchspire-admin',
			'watchspireAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'watchspire_admin' ),
				'i18n'    => array(
					'confirmCancel'      => __( 'Cancel the current scan? Progress will be lost.', 'watchspire' ),
					'running'            => __( 'Running…', 'watchspire' ),
					'scanTitleIdle'      => __( 'Ready to scan', 'watchspire' ),
					'scanTitleBusy'      => __( 'Scanning in progress…', 'watchspire' ),
					'scanTitlePaused'    => __( 'Scan paused', 'watchspire' ),
					'scanTitleCompleted' => __( 'Scan completed', 'watchspire' ),
					'subtitleExtracting' => __( 'Scanning content…', 'watchspire' ),
					'subtitleChecking'   => __( 'Checking links…', 'watchspire' ),
					'subtitlePaused'     => __( 'Resume anytime — progress is saved.', 'watchspire' ),
					'subtitleReady'      => __( 'Run a scan to check your links and images.', 'watchspire' ),
					/* translators: %d: number of broken links/images found */
					'subtitleFoundOne'   => __( '%d broken link or image found.', 'watchspire' ),
					/* translators: %d: number of broken links/images found */
					'subtitleFoundMany'  => __( '%d broken links or images found.', 'watchspire' ),
					/* translators: 1: checked count, 2: total count */
					'scannedOf'          => __( 'Scanned %1$s of %2$s URLs', 'watchspire' ),
					/* translators: %s: elapsed time as HH:MM:SS */
					'elapsed'            => __( 'Elapsed: %s', 'watchspire' ),
				),
			)
		);
	}

	/**
	 * Resolves the dashboard's custom start/end date range out of the query
	 * string into the same shape the preset ranges produce. Returns null
	 * when no usable pair of dates was supplied, in which case the caller
	 * falls back to the default preset.
	 *
	 * @return array{days:int,start:string,end:string,prev_start:string,label:string}|null
	 */
	private static function resolve_custom_period(): ?array {
		$start = isset( $_GET['range_start'] ) ? sanitize_text_field( wp_unslash( $_GET['range_start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$end   = isset( $_GET['range_end'] ) ? sanitize_text_field( wp_unslash( $_GET['range_end'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
			return null;
		}

		if ( $start > $end ) {
			list( $start, $end ) = array( $end, $start );
		}

		$start_ts = strtotime( $start . ' 00:00:00 UTC' );
		$end_ts   = strtotime( $end . ' 23:59:59 UTC' );

		if ( false === $start_ts || false === $end_ts ) {
			return null;
		}

		// Cap the window so a hand-edited URL can't ask for a series with
		// thousands of daily buckets in it. The start moves with the cap so
		// the reported days, the queried period, and the label all agree.
		if ( ( $end_ts - $start_ts ) > ( self::MAX_RANGE_DAYS * DAY_IN_SECONDS ) ) {
			$start_ts = $end_ts - ( self::MAX_RANGE_DAYS * DAY_IN_SECONDS );
		}

		$days = (int) max( 1, ceil( ( $end_ts - $start_ts ) / DAY_IN_SECONDS ) );

		return array(
			'days'       => $days,
			'start'      => gmdate( 'Y-m-d H:i:s', $start_ts ),
			'end'        => gmdate( 'Y-m-d H:i:s', $end_ts ),
			'prev_start' => gmdate( 'Y-m-d H:i:s', $start_ts - ( $days * DAY_IN_SECONDS ) ),
			'label'      => sprintf(
				/* translators: 1: range start date, 2: range end date */
				__( '%1$s – %2$s', 'watchspire' ),
				date_i18n( (string) get_option( 'date_format' ), $start_ts ),
				date_i18n( (string) get_option( 'date_format' ), $end_ts )
			),
		);
	}

	/**
	 * Guards the custom period against a listener returning something the
	 * dashboard can't render. Anything missing a key, or not an array at
	 * all, is treated as "no custom range" rather than fatalling.
	 *
	 * @param  mixed $period
	 * @return array{days:int,start:string,end:string,prev_start:string,label:string}|null
	 */
	private static function validate_custom_period( $period ): ?array {
		if ( ! is_array( $period ) ) {
			return null;
		}

		foreach ( array( 'days', 'start', 'end', 'prev_start', 'label' ) as $key ) {
			if ( ! isset( $period[ $key ] ) ) {
				return null;
			}
		}

		return array(
			'days'       => max( 1, (int) $period['days'] ),
			'start'      => (string) $period['start'],
			'end'        => (string) $period['end'],
			'prev_start' => (string) $period['prev_start'],
			'label'      => (string) $period['label'],
		);
	}

	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		/**
		 * Extra preset look-back windows, in days, on top of the 7/30/90
		 * WatchSpire always reports on itself.
		 *
		 * This only ever ADDS windows: the three built-in ones are merged
		 * back in below, so no add-on can take away a range WatchSpire
		 * ships. An unrecognised ?range= value still falls back to 7.
		 *
		 * @param int[] $allowed_ranges
		 */
		$filtered_ranges = (array) apply_filters( 'watchspire_dashboard_allowed_ranges', self::DEFAULT_RANGES );
		$filtered_ranges = array_filter(
			array_map( 'absint', $filtered_ranges ),
			static function ( $days ) {
				return $days >= 1 && $days <= self::MAX_RANGE_DAYS;
			}
		);
		$allowed_ranges  = array_values( array_unique( array_merge( self::DEFAULT_RANGES, $filtered_ranges ) ) );
		sort( $allowed_ranges );

		$range_param = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		/**
		 * The resolved custom date range. WatchSpire resolves ?range_start
		 * and ?range_end itself; a listener may return a different period
		 * of the same shape — array{days:int,start:string,end:string,
		 * prev_start:string,label:string}, datetimes as UTC 'Y-m-d H:i:s' —
		 * or null to fall back to the default preset.
		 *
		 * @param array|null $period
		 */
		$custom_period = 'custom' === $range_param
			? self::validate_custom_period( apply_filters( 'watchspire_dashboard_custom_period', self::resolve_custom_period() ) )
			: null;

		if ( $custom_period ) {
			$range_days   = max( 1, (int) $custom_period['days'] );
			$now          = $custom_period['end'];
			$period_start = $custom_period['start'];
			$prev_start   = $custom_period['prev_start'];
			$range_label  = $custom_period['label'];
		} else {
			$range_days = absint( $range_param );
			if ( ! in_array( $range_days, $allowed_ranges, true ) ) {
				$range_days = 7;
			}
			$now          = current_time( 'mysql', true );
			$period_start = gmdate( 'Y-m-d H:i:s', time() - ( $range_days * DAY_IN_SECONDS ) );
			$prev_start   = gmdate( 'Y-m-d H:i:s', time() - ( 2 * $range_days * DAY_IN_SECONDS ) );
			$range_label  = null; // dashboard.php falls back to its own preset labels.
		}

		$allowed_mstatus = array( 'all', 'pass', 'warn', 'fail' );
		$mstatus_filter  = isset( $_GET['mstatus'] ) ? sanitize_key( wp_unslash( $_GET['mstatus'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $mstatus_filter, $allowed_mstatus, true ) ) {
			$mstatus_filter = 'all';
		}

		$registry = new MonitorRegistry();
		$monitors = $registry->get_monitors();

		$checks_repo      = new ChecksRepository();
		$submissions_repo = new SubmissionsRepository();
		$errors_repo      = new ErrorsRepository();
		$crawlers_repo    = new CrawlersRepository();
		$changelog_repo   = new ChangelogRepository();

		$latest = array();
		foreach ( $checks_repo->latest_per_monitor() as $row ) {
			$latest[ $row['monitor_id'] ] = $row;
		}

		$current_totals  = $checks_repo->totals_between( $period_start, $now );
		$previous_totals = $checks_repo->totals_between( $prev_start, $period_start );
		$daily_series    = $checks_repo->daily_series( $range_days, null, $now );

		$uptime_current  = $checks_repo->totals_between( $period_start, $now, 'uptime' );
		$uptime_previous = $checks_repo->totals_between( $prev_start, $period_start, 'uptime' );
		$uptime_daily    = $checks_repo->daily_series( $range_days, 'uptime', $now );

		$recent_failures   = $checks_repo->recent_failures( 6 );
		$changelog         = $changelog_repo->recent( 6 );
		$submission_forms  = $submissions_repo->distinct_forms();
		$submission_totals = $submissions_repo->totals_since( $range_days, $now );
		$submission_daily  = $submissions_repo->daily_totals( 7 );
		$top_404s          = $errors_repo->top_404s_since( $range_days, 5, $now );
		$crawler_today     = $crawlers_repo->totals_by_bot( 1 );

		self::open_shell( __( 'WatchSpire Dashboard', 'watchspire' ) );
		include WATCHSPIRE_DIR . 'includes/Admin/Views/dashboard.php';
		self::close_shell();
	}

	public function ajax_run_all_checks(): void {
		check_ajax_referer( 'watchspire_admin', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'watchspire' ) ), 403 );
		}

		$runner = function_exists( 'watchspire' ) ? watchspire()->get( 'runner' ) : null;

		if ( ! $runner ) {
			wp_send_json_error( array( 'message' => __( 'Runner unavailable.', 'watchspire' ) ), 500 );
		}

		$registry = new MonitorRegistry();
		$enabled  = Settings::get( 'monitors_enabled', array() );
		$ran      = 0;

		foreach ( $registry->get_monitors() as $monitor ) {
			if ( ! $monitor->is_available() ) {
				continue;
			}

			if ( isset( $enabled[ $monitor->get_id() ] ) && ! $enabled[ $monitor->get_id() ] ) {
				continue;
			}

			$runner->run_monitor( $monitor );
			++$ran;
		}

		wp_send_json_success( array( 'ran' => $ran ) );
	}

	/**
	 * Parses and sanitizes the Change Log screen's filter query args,
	 * shared between the on-screen table and the CSV export so both
	 * always see exactly the same window.
	 *
	 * @return array{type:string,search:string,user_id:int,from:string,to:string,args:array<string,mixed>}
	 */
	private function parse_changelog_filters(): array {
		$type    = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$default_to   = current_time( 'Y-m-d' );
		$default_from = gmdate( 'Y-m-d', strtotime( $default_to ) - ( 30 * DAY_IN_SECONDS ) );

		$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $default_to; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$from = $default_from;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$to = $default_to;
		}
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}

		$args = array(
			'since' => $from . ' 00:00:00',
			'until' => $to . ' 23:59:59',
		);
		if ( $type ) {
			$args['type'] = $type;
		}
		if ( $user_id ) {
			$args['user_id'] = $user_id;
		}
		if ( $search ) {
			$args['search'] = $search;
		}

		return array(
			'type'    => $type,
			'search'  => $search,
			'user_id' => $user_id,
			'from'    => $from,
			'to'      => $to,
			'args'    => $args,
		);
	}

	public function render_changelog(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$filters = $this->parse_changelog_filters();
		$type    = $filters['type'];
		$search  = $filters['search'];
		$user_id = $filters['user_id'];
		$from    = $filters['from'];
		$to      = $filters['to'];
		$args    = $filters['args'];

		$per_page = 10;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$repo = new ChangelogRepository();

		$total_matching = $repo->count_matching( $args );
		$total_pages    = max( 1, (int) ceil( $total_matching / $per_page ) );
		$paged          = min( $paged, $total_pages );

		$events = apply_filters( 'watchspire_changelog_query', $repo->query( $args, $per_page, ( $paged - 1 ) * $per_page ), $args );
		$counts = $repo->counts_by_range( $args['since'], $args['until'] );
		$users  = $repo->distinct_users();

		self::open_shell( __( 'WatchSpire Change Log', 'watchspire' ) );
		include WATCHSPIRE_DIR . 'includes/Admin/Views/changelog.php';
		self::close_shell();
	}

	/**
	 * Streams the currently filtered Change Log as a CSV download.
	 */
	public function export_changelog(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'watchspire' ) );
		}

		check_admin_referer( 'watchspire_export_changelog' );

		$filters = $this->parse_changelog_filters();
		$rows    = ( new ChangelogRepository() )->query( $filters['args'], 10000, 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="watchspire-changelog-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Time (UTC)', 'Type', 'Item', 'Change', 'By', 'Source' ) );

		foreach ( $rows as $row ) {
			$user   = $row['user_id'] ? get_userdata( $row['user_id'] ) : null;
			$change = trim( trim( (string) $row['from_version'] ) . ( $row['from_version'] || $row['to_version'] ? ' -> ' : '' ) . trim( (string) $row['to_version'] ) );
			$source = 0 === strpos( $row['type'], 'user_' ) ? 'Login' : ( $row['is_auto_update'] ? 'WP-Cron' : 'Dashboard' );

			fputcsv(
				$out,
				array(
					$row['created_at'],
					ucwords( str_replace( '_', ' ', $row['type'] ) ),
					$row['object_name'] ? $row['object_name'] : $row['object_slug'],
					$change,
					$user ? $user->display_name : '—',
					$source,
				)
			);
		}

		// WP_Filesystem has no streaming equivalent — this writes the CSV
		// straight to php://output for the download, never to disk.
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public function render_links(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$manager  = new LinkScanManager();
		$state    = $manager->get_state();
		$repo     = new LinksRepository();
		$opted_in = (bool) Settings::get( 'link_scan_opt_in', false );

		$allowed_views = array( 'all', 'links', 'images', 'ignored' );
		$view          = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $view, $allowed_views, true ) ) {
			$view = 'all';
		}

		$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source  = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$filters = array(
			'view'    => $view,
			'type'    => $type,
			'source'  => $source,
			'post_id' => $post_id,
			'status'  => $status,
			'search'  => $search,
		);

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$total_matching = $repo->count_matching( $filters );
		$total_pages    = max( 1, (int) ceil( $total_matching / $per_page ) );
		$paged          = min( $paged, $total_pages );

		$rows            = $repo->query( $filters, $per_page, ( $paged - 1 ) * $per_page );
		$counts          = $repo->counts();
		$source_posts    = $repo->distinct_source_posts();
		$source_types    = $repo->distinct_source_types();
		$broken_statuses = $repo->distinct_broken_statuses();

		self::open_shell( __( 'WatchSpire Broken Links & Images', 'watchspire' ) );
		include WATCHSPIRE_DIR . 'includes/Admin/Views/links.php';
		self::close_shell();
	}

	public function render_settings_placeholder(): void {
		$settings = function_exists( 'watchspire' ) ? watchspire()->get( 'settings' ) : null;

		if ( ! $settings ) {
			return;
		}

		self::open_shell( __( 'WatchSpire Settings', 'watchspire' ) );
		$settings->render();
		self::close_shell();
	}

	/**
	 * Shared branded shell, used by every WatchSpire admin screen
	 * (including Settings, rendered by a different class). Cross-page
	 * navigation is WP's own admin submenu — we don't duplicate it in
	 * page chrome; the dashboard's hero and each page's compact header
	 * carry the visual weight instead.
	 *
	 * Core's and other plugins' admin notices are printed by WordPress in
	 * its standard position via the admin_notices hook — WatchSpire never
	 * relocates or suppresses them. WatchSpire's own notice is the one
	 * exception: it is not registered on that hook at all, and each screen
	 * prints it inline via render_own_notices() instead.
	 *
	 * @param string $page_title Title for the screen's accessible heading.
	 */
	public static function open_shell( string $page_title = '' ): void {
		$page_title = '' !== $page_title ? $page_title : __( 'WatchSpire', 'watchspire' );

		echo '<div class="wrap watchspire-app">';

		/*
		 * WordPress moves every admin notice to sit immediately after the
		 * first <h1>/<h2> inside .wrap (wp-admin/js/common.js), and
		 * wp.updates looks for one that is a direct child of .wrap. Our
		 * branded headers keep their heading nested inside a flex row, so
		 * with no anchor here notices get injected into the middle of the
		 * header and pull it apart. This screen-reader-only heading is
		 * that anchor: notices land above the page chrome where they
		 * belong, and the screen still exposes exactly one <h1>.
		 */
		printf( '<h1 class="screen-reader-text">%s</h1>', esc_html( $page_title ) );

		echo '<div class="watchspire-shell">';
	}

	/**
	 * Prints WatchSpire's own admin notices at the current point in the
	 * markup — called by every WatchSpire screen just after its page
	 * header, and available to add-on screens that lay out their own
	 * chrome and want the notice in a particular spot.
	 *
	 * WatchSpire's notice is not registered on admin_notices, because
	 * that hook fires outside .wrap and would put a WatchSpire-specific
	 * message full-bleed at the top of the page, above every other
	 * plugin's notices. Printing it here keeps it inside the screen it
	 * refers to. This affects only WatchSpire's own notice; core's and
	 * every other plugin's are left exactly where WordPress puts them.
	 */
	public static function render_own_notices(): void {
		$scheduler = function_exists( 'watchspire' ) ? watchspire()->get( 'scheduler' ) : null;

		if ( $scheduler ) {
			$scheduler->maybe_show_cron_notice();
		}
	}

	public static function close_shell(): void {
		echo '</div></div>';
	}

	/**
	 * Compact header used by every screen except the dashboard (which
	 * gets the full hero instead): title + subtitle + optional
	 * right-aligned actions.
	 */
	public static function render_page_header( string $title, string $desc = '', string $actions_html = '' ): void {
		?>
		<div class="watchspire-page-header">
			<div>
				<h2 class="watchspire-page-title watchspire-breadcrumb-title">
					<span class="watchspire-breadcrumb-icon" aria-hidden="true"><?php echo self::logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=watchspire' ) ); ?>" class="watchspire-breadcrumb-root"><?php esc_html_e( 'WatchSpire', 'watchspire' ); ?></a>
					<span class="watchspire-breadcrumb-sep" aria-hidden="true">›</span>
					<span class="watchspire-breadcrumb-current"><?php echo esc_html( $title ); ?></span>
				</h2>
				<?php
				if ( $desc ) :
					?>
					<p class="watchspire-page-desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</div>
			<?php if ( $actions_html ) : ?>
				<div class="watchspire-page-header-end"><?php echo $actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
		</div>
		<?php
		self::render_own_notices();
	}

	/**
	 * Overall status across every monitor's latest result: 'ok' unless
	 * something is failing or warning.
	 */
	public static function overall_status(): string {
		$status = 'ok';

		foreach ( ( new ChecksRepository() )->latest_per_monitor() as $row ) {
			if ( 'fail' === $row['status'] ) {
				return 'fail';
			}
			if ( 'warn' === $row['status'] ) {
				$status = 'warn';
			}
		}

		return $status;
	}

	public static function logo_svg(): string {
		return '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.5 4 5.5v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10v-6L12 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m8.5 12.2 2.4 2.4 4.6-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	public function ajax_run_check_now(): void {
		check_ajax_referer( 'watchspire_admin', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'watchspire' ) ), 403 );
		}

		$monitor_id = isset( $_POST['monitor_id'] ) ? sanitize_key( wp_unslash( $_POST['monitor_id'] ) ) : '';

		$registry = new MonitorRegistry();
		$monitor  = $registry->get_monitor( $monitor_id );

		if ( ! $monitor ) {
			wp_send_json_error( array( 'message' => __( 'Unknown monitor.', 'watchspire' ) ), 404 );
		}

		$runner = function_exists( 'watchspire' ) ? watchspire()->get( 'runner' ) : null;

		if ( ! $runner ) {
			wp_send_json_error( array( 'message' => __( 'Runner unavailable.', 'watchspire' ) ), 500 );
		}

		$result = $runner->run_monitor( $monitor );

		wp_send_json_success( $result->to_array() );
	}

	public function ajax_link_scan_action(): void {
		check_ajax_referer( 'watchspire_admin', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'watchspire' ) ), 403 );
		}

		$action  = isset( $_POST['scan_action'] ) ? sanitize_key( wp_unslash( $_POST['scan_action'] ) ) : '';
		$manager = new LinkScanManager();

		switch ( $action ) {
			case 'start':
				$manager->start_scan();
				break;
			case 'pause':
				$manager->pause_scan();
				break;
			case 'resume':
				$manager->resume_scan();
				break;
			case 'cancel':
				$manager->cancel_scan();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'watchspire' ) ), 400 );
		}

		wp_send_json_success( $manager->get_state() );
	}

	/**
	 * Polled by the browser while a scan is running. Besides reporting
	 * fresh progress, this also nudges the scan itself forward by one
	 * batch — so progress keeps moving as long as an admin has the
	 * screen open, without depending on WP-Cron or a loopback request
	 * firing in the background (unreliable on some hosts/local setups).
	 */
	public function ajax_link_scan_poll(): void {
		check_ajax_referer( 'watchspire_admin', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'watchspire' ) ), 403 );
		}

		$manager = new LinkScanManager();
		$state   = $manager->get_state();

		if ( 'extracting' === $state['status'] ) {
			$manager->run_extract_batch();
		} elseif ( 'checking' === $state['status'] ) {
			// A handful of URLs per poll, not the full configured batch —
			// each check is a real HTTP request with its own timeout, and
			// this runs synchronously inside the request/response cycle.
			$manager->run_check_batch( 3 );
		}

		wp_send_json_success( $manager->get_state() );
	}

	public function ajax_link_row_action(): void {
		check_ajax_referer( 'watchspire_admin', 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'watchspire' ) ), 403 );
		}

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$action = isset( $_POST['row_action'] ) ? sanitize_key( wp_unslash( $_POST['row_action'] ) ) : '';
		$repo   = new LinksRepository();

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Missing row id.', 'watchspire' ) ), 400 );
		}

		switch ( $action ) {
			case 'ignore':
				$repo->set_ignored( $id, true );
				break;
			case 'unignore':
				$repo->set_ignored( $id, false );
				break;
			case 'recheck':
				$repo->recheck( $id );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'watchspire' ) ), 400 );
		}

		wp_send_json_success();
	}
}
