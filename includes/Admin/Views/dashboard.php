<?php
/**
 * Dashboard screen — dark "mission control" overview.
 *
 * @package WatchSpire
 *
 * @var \WatchSpire\Monitors\AbstractMonitor[] $monitors
 * @var array<string,array<string,mixed>>     $latest
 * @var int                                   $range_days
 * @var string                                $mstatus_filter
 * @var array<string,int|float>               $current_totals
 * @var array<string,int|float>               $previous_totals
 * @var array<string,array<string,int|float>> $daily_series
 * @var array<string,int|float>               $uptime_current
 * @var array<string,int|float>               $uptime_previous
 * @var array<string,array<string,int|float>> $uptime_daily
 * @var array<int,array<string,mixed>>        $recent_failures
 * @var array<int,array<string,mixed>>        $changelog
 * @var array<int,array<string,mixed>>        $submission_forms
 * @var array<string,int>                     $submission_totals
 * @var array<string,int>                     $submission_daily
 * @var array<int,array<string,mixed>>        $top_404s
 * @var array<int,array<string,mixed>>        $crawler_today
 * @var int[]                                 $allowed_ranges Preset look-back windows, in days.
 * @var string                                $period_start  UTC 'Y-m-d H:i:s' the active window starts at.
 * @var string|null                           $range_label   Custom-range display label (e.g. "Aug 1 – Aug 10"), null for the presets.
 * @var array|null                            $custom_period Resolved custom period, null when a preset range is active.
 */

defined( 'ABSPATH' ) || exit;

use WatchSpire\Admin\AdminMenu;
use WatchSpire\Admin\Charts;
use WatchSpire\Support\Settings;

/**
 * Percentage change helper: positive/negative + whether that direction
 * is "good" for this particular metric (fewer failures is good, more
 * passes is good).
 *
 * @return array{pct:?int,good:bool,up:bool}
 */
$pct_change = static function ( $current, $previous, bool $down_is_good = false ): array {
	if ( ! $previous ) {
		return array(
			'pct'  => null,
			'good' => true,
			'up'   => true,
		);
	}

	$pct = (int) round( ( ( $current - $previous ) / $previous ) * 100 );
	$up  = $pct >= 0;

	return array(
		'pct'  => abs( $pct ),
		'good' => $down_is_good ? ! $up : $up,
		'up'   => $up,
	);
};

$response_tier = static function ( float $ms ): string {
	if ( $ms <= 0 ) {
		return __( '—', 'watchspire' );
	}
	if ( $ms < 300 ) {
		return __( 'Excellent', 'watchspire' );
	}
	if ( $ms < 800 ) {
		return __( 'Good', 'watchspire' );
	}
	if ( $ms < 2000 ) {
		return __( 'Fair', 'watchspire' );
	}
	return __( 'Slow', 'watchspire' );
};

$uptime_tier = static function ( ?float $pct ): string {
	if ( null === $pct ) {
		return __( '—', 'watchspire' );
	}
	if ( $pct >= 99.9 ) {
		return __( 'Excellent', 'watchspire' );
	}
	if ( $pct >= 99 ) {
		return __( 'Good', 'watchspire' );
	}
	if ( $pct >= 95 ) {
		return __( 'Fair', 'watchspire' );
	}
	return __( 'Poor', 'watchspire' );
};

$monitor_icons = array(
	'ssl'            => 'lock',
	'http_errors'    => 'warning',
	'mail_health'    => 'email-alt',
	'uptime'         => 'chart-line',
	'link_scan'      => 'admin-links',
	'submission_gap' => 'feedback',
);

$status_icons = array(
	'pass'    => 'yes-alt',
	'warn'    => 'warning',
	'fail'    => 'dismiss',
	'unknown' => 'editor-help',
);

// ---- Overall status + health score (current snapshot across monitors) ----

$pass_count = 0;
$warn_count = 0;
$fail_count = 0;

foreach ( $latest as $row ) {
	if ( 'pass' === $row['status'] ) {
		++$pass_count;
	} elseif ( 'warn' === $row['status'] ) {
		++$warn_count;
	} elseif ( 'fail' === $row['status'] ) {
		++$fail_count;
	}
}

$enabled_setting = Settings::get( 'monitors_enabled', array() );
$paused_count    = 0;

foreach ( $monitors as $monitor ) {
	$monitor_id_check = $monitor->get_id();
	$is_off           = isset( $enabled_setting[ $monitor_id_check ] ) && ! $enabled_setting[ $monitor_id_check ];
	$is_hidden        = ! $monitor->is_available();

	if ( $is_off || $is_hidden ) {
		++$paused_count;
	}
}

$active_monitor_count = count( $monitors ) - $paused_count;
$checked_count        = count( $latest );
$score                = $checked_count > 0 ? (int) round( ( ( $pass_count * 100 ) + ( $warn_count * 50 ) ) / $checked_count ) : null;

$prev_score_basis = $previous_totals['total'] > 0
	? ( ( $previous_totals['pass'] * 100 ) + ( $previous_totals['warn'] * 50 ) ) / $previous_totals['total']
	: null;
$curr_score_basis = $current_totals['total'] > 0
	? ( ( $current_totals['pass'] * 100 ) + ( $current_totals['warn'] * 50 ) ) / $current_totals['total']
	: null;
$score_delta      = ( null !== $prev_score_basis && null !== $curr_score_basis )
	? (int) round( $curr_score_basis - $prev_score_basis )
	: null;

$overall_status = AdminMenu::overall_status();
$status_labels  = array(
	'ok'   => __( 'All systems operational', 'watchspire' ),
	'warn' => __( 'Needs attention', 'watchspire' ),
	'fail' => __( 'Issue detected', 'watchspire' ),
);

// ---- Range option labels ----

$range_labels = array(
	7  => __( 'Last 7 days', 'watchspire' ),
	30 => __( 'Last 30 days', 'watchspire' ),
	90 => __( 'Last 90 days', 'watchspire' ),
);

$range_label_text = $range_label ?? ( $range_labels[ $range_days ] ?? sprintf(
	/* translators: %d: number of days */
	__( 'Last %d days', 'watchspire' ),
	$range_days
) );

// Prefill for the custom-range date inputs: the active custom period when
// one is set, otherwise the window the current preset covers.
$range_max_date    = current_time( 'Y-m-d' );
$range_start_value = $custom_period ? substr( $custom_period['start'], 0, 10 ) : substr( $period_start, 0, 10 );
$range_end_value   = $custom_period ? substr( $custom_period['end'], 0, 10 ) : $range_max_date;

$mstatus_labels = array(
	'all'  => __( 'All statuses', 'watchspire' ),
	'pass' => __( 'Passing only', 'watchspire' ),
	'warn' => __( 'Warning only', 'watchspire' ),
	'fail' => __( 'Failing only', 'watchspire' ),
);

// ---- Sparkline series ----

$totals_series   = array_column( $daily_series, 'total' );
$fail_series     = array_column( $daily_series, 'fail' );
$duration_series = array_column( $daily_series, 'avg_duration' );

$uptime_rate_series = array();
foreach ( $uptime_daily as $row ) {
	$uptime_rate_series[] = $row['total'] > 0 ? round( ( $row['pass'] / $row['total'] ) * 100, 1 ) : 0;
}

// ---- Stat card deltas ----

$total_delta    = $pct_change( $current_totals['total'], $previous_totals['total'] );
$fail_delta     = $pct_change( $current_totals['fail'], $previous_totals['fail'], true );
$duration_delta = $pct_change( $current_totals['avg_duration'], $previous_totals['avg_duration'], true );

$uptime_rate_current  = $uptime_current['total'] > 0 ? round( ( $uptime_current['pass'] / $uptime_current['total'] ) * 100, 1 ) : null;
$uptime_rate_previous = $uptime_previous['total'] > 0 ? round( ( $uptime_previous['pass'] / $uptime_previous['total'] ) * 100, 1 ) : null;
$uptime_delta         = ( null !== $uptime_rate_current && null !== $uptime_rate_previous )
	? $pct_change( $uptime_rate_current, $uptime_rate_previous )
	: array(
		'pct'  => null,
		'good' => true,
		'up'   => true,
	);

$nonce = wp_create_nonce( 'watchspire_admin' );

/**
 * Footer line for a stat card. Every card ends on the same divider +
 * line: the period-over-period delta when a previous period exists,
 * otherwise a muted placeholder. Without the fallback a card with no
 * prior data (a fresh install's uptime series, say) renders nothing
 * here, and the flex-filling trend chart above it expands into the
 * whole remaining card with nothing closing it off.
 *
 * @param array{pct:?int,good:bool,up:bool} $delta
 */
$delta_footer = static function ( array $delta ): void {
	if ( null === $delta['pct'] ) {
		?>
		<p class="wpdash-stat-sub"><?php esc_html_e( 'No prior period data', 'watchspire' ); ?></p>
		<?php
		return;
	}
	?>
	<p class="wpdash-stat-sub <?php echo $delta['good'] ? 'is-good' : 'is-bad'; ?>">
		<span class="dashicons dashicons-arrow-<?php echo $delta['up'] ? 'up' : 'down'; ?>-alt" aria-hidden="true"></span>
		<?php echo esc_html( (string) $delta['pct'] ); ?>% <?php esc_html_e( 'from last period', 'watchspire' ); ?>
	</p>
	<?php
};
?>
<div class="wpdash">
	<div class="wpdash-header">
		<div class="wpdash-header-title">
			<span class="watchspire-brand__mark" aria-hidden="true"><?php echo AdminMenu::logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h2><?php esc_html_e( 'WatchSpire', 'watchspire' ); ?></h2>
			<span class="wpdash-status-pill is-<?php echo esc_attr( 'ok' === $overall_status ? 'ok' : $overall_status ); ?>">
				<span class="dot" aria-hidden="true"></span><?php echo esc_html( $status_labels[ $overall_status ] ); ?>
			</span>
		</div>
		<div class="wpdash-header-actions">
			<details class="wpdash-toolbtn wpdash-filter"<?php echo $custom_period ? ' open' : ''; ?>>
				<summary>
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<span class="wpdash-toolbtn-text"><?php echo esc_html( $range_label_text ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2 wpdash-toolbtn-caret" aria-hidden="true"></span>
				</summary>
				<div class="wpdash-filter-panel wpdash-filter-panel--range">
					<?php foreach ( $allowed_ranges as $value ) : ?>
						<?php
						$preset_label     = $range_labels[ $value ] ?? sprintf(
							/* translators: %d: number of days */
							_n( 'Last %d day', 'Last %d days', $value, 'watchspire' ),
							$value
						);
						$is_active_preset = ! $custom_period && (int) $range_days === (int) $value;
						?>
						<a href="<?php echo esc_url( add_query_arg( array( 'range' => $value ), remove_query_arg( array( 'range', 'range_start', 'range_end' ) ) ) ); ?>" class="<?php echo $is_active_preset ? 'is-active' : ''; ?>">
							<?php echo esc_html( $preset_label ); ?>
							<?php if ( $is_active_preset ) : ?>
								<span class="dashicons dashicons-yes" aria-hidden="true"></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>

					<?php if ( has_action( 'watchspire_dashboard_custom_range_ui' ) ) : ?>
						<?php
						/**
						 * Replaces the start/end date control below with one of
						 * the listener's own, so an add-on offering a richer
						 * picker doesn't stack a second one underneath ours.
						 * With no listener, WatchSpire renders its own — the
						 * custom range is never unavailable.
						 *
						 * @param int        $range_days     Days spanned by the active range.
						 * @param string     $mstatus_filter Active monitor-status filter, so the control can preserve it.
						 * @param array|null $custom_period  The resolved custom period, if one is active.
						 */
						do_action( 'watchspire_dashboard_custom_range_ui', $range_days, $mstatus_filter, $custom_period );
						?>
					<?php else : ?>
						<div class="wpdash-filter-daterange">
							<span class="wpdash-filter-daterange-label"><?php esc_html_e( 'Custom range', 'watchspire' ); ?></span>
							<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
								<input type="hidden" name="page" value="watchspire" />
								<input type="hidden" name="range" value="custom" />
								<?php if ( 'all' !== $mstatus_filter ) : ?>
									<input type="hidden" name="mstatus" value="<?php echo esc_attr( $mstatus_filter ); ?>" />
								<?php endif; ?>
								<label>
									<?php esc_html_e( 'From', 'watchspire' ); ?>
									<input type="date" name="range_start" max="<?php echo esc_attr( $range_max_date ); ?>" value="<?php echo esc_attr( $range_start_value ); ?>" required />
								</label>
								<label>
									<?php esc_html_e( 'To', 'watchspire' ); ?>
									<input type="date" name="range_end" max="<?php echo esc_attr( $range_max_date ); ?>" value="<?php echo esc_attr( $range_end_value ); ?>" required />
								</label>
								<button type="submit" class="button"><?php esc_html_e( 'Apply range', 'watchspire' ); ?></button>
							</form>
						</div>
					<?php endif; ?>
				</div>
			</details>

			<details class="wpdash-toolbtn wpdash-filter">
				<summary>
					<span class="dashicons dashicons-filter" aria-hidden="true"></span>
					<span class="wpdash-toolbtn-text"><?php esc_html_e( 'Filters', 'watchspire' ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2 wpdash-toolbtn-caret" aria-hidden="true"></span>
				</summary>
				<div class="wpdash-filter-panel">
					<?php foreach ( $mstatus_labels as $value => $label ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'mstatus' => $value ), remove_query_arg( 'mstatus' ) ) ); ?>" class="<?php echo $mstatus_filter === $value ? 'is-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
							<?php
							if ( $mstatus_filter === $value ) :
								?>
								<span class="dashicons dashicons-yes" aria-hidden="true"></span><?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			</details>

			<button type="button" class="wpdash-toolbtn wpdash-toolbtn--primary" id="wpdash-run-all" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Run all checks now', 'watchspire' ); ?>
			</button>
		</div>
	</div>

	<?php AdminMenu::render_own_notices(); ?>

	<?php
	// Active-filter summary. Every number on this screen is scoped by the
	// range and status pickers in the header, so when either is off its
	// default we say so explicitly — otherwise a filtered dashboard is
	// indistinguishable from a quiet one. Each chip clears just its own
	// parameter, preserving the other.
	$active_filters = array();

	if ( $custom_period || 7 !== (int) $range_days ) {
		$active_filters[] = array(
			'label' => __( 'Date range', 'watchspire' ),
			'value' => $range_label_text,
			'icon'  => 'calendar-alt',
			'reset' => remove_query_arg( array( 'range', 'range_start', 'range_end' ) ),
		);
	}

	if ( 'all' !== $mstatus_filter ) {
		$active_filters[] = array(
			'label' => __( 'Status', 'watchspire' ),
			'value' => $mstatus_labels[ $mstatus_filter ] ?? $mstatus_filter,
			'icon'  => 'filter',
			'reset' => remove_query_arg( 'mstatus' ),
		);
	}
	?>

	<?php if ( ! empty( $active_filters ) ) : ?>
		<div class="wpdash-active-filters">
			<span class="wpdash-active-filters-label">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %d: number of active filters */
					esc_html( _n( '%d filter applied', '%d filters applied', count( $active_filters ), 'watchspire' ) ),
					(int) count( $active_filters )
				);
				?>
			</span>

			<?php foreach ( $active_filters as $chip ) : ?>
				<span class="wpdash-chip">
					<span class="dashicons dashicons-<?php echo esc_attr( $chip['icon'] ); ?>" aria-hidden="true"></span>
					<span class="wpdash-chip-key"><?php echo esc_html( $chip['label'] ); ?></span>
					<strong><?php echo esc_html( $chip['value'] ); ?></strong>
					<a
						class="wpdash-chip-x"
						href="<?php echo esc_url( $chip['reset'] ); ?>"
						aria-label="
						<?php
						printf(
							/* translators: %s: filter name, e.g. "Date range" */
							esc_attr__( 'Clear %s filter', 'watchspire' ),
							esc_attr( $chip['label'] )
						);
						?>
						"
					><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></a>
				</span>
			<?php endforeach; ?>

			<?php if ( count( $active_filters ) > 1 ) : ?>
				<a class="wpdash-active-filters-clear" href="<?php echo esc_url( remove_query_arg( array( 'range', 'range_start', 'range_end', 'mstatus' ) ) ); ?>">
					<?php esc_html_e( 'Clear all', 'watchspire' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php do_action( 'watchspire_admin_dashboard_after_cards' ); ?>

	<div class="wpdash-stats">
		<div class="wpdash-stat wpdash-stat--score">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-indigo" aria-hidden="true"><span class="dashicons dashicons-shield"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Health Score', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-gauge-wrap">
				<?php echo Charts::gauge( $score, 'lg', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php if ( null !== $score_delta ) : ?>
				<p class="wpdash-stat-sub <?php echo $score_delta >= 0 ? 'is-good' : 'is-bad'; ?>">
					<span class="dashicons dashicons-arrow-up-alt<?php echo $score_delta >= 0 ? '' : '-2'; ?>" aria-hidden="true"></span>
					<?php echo esc_html( (string) abs( $score_delta ) ); ?> <?php esc_html_e( 'from last period', 'watchspire' ); ?>
				</p>
			<?php else : ?>
				<p class="wpdash-stat-sub"><?php esc_html_e( 'No prior period data', 'watchspire' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon wpdash-stat-icon--solid is-green" aria-hidden="true"><span class="dashicons dashicons-visibility"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Active Monitors', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( (string) $active_monitor_count ); ?><small><?php echo esc_html( sprintf( /* translators: %d: total monitor count */ __( 'of %d', 'watchspire' ), count( $monitors ) ) ); ?></small></span>
			</div>
			<div class="wpdash-stat-spark-wrap">
				<?php echo Charts::sparkline( $totals_series, 200, 50, '#7c3aed' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<p class="wpdash-stat-sub">
				<span class="dot" style="background:#10b981;" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: 1: passing count, 2: issue count */
					esc_html__( '%1$d passing · %2$d issue(s)', 'watchspire' ),
					(int) $pass_count,
					(int) ( $warn_count + $fail_count )
				);
				?>
			</p>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-violet" aria-hidden="true"><span class="dashicons dashicons-yes-alt"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Total Checks', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $current_totals['total'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php echo esc_html( $range_label_text ); ?></p>
			<div class="wpdash-stat-spark-wrap">
				<?php echo Charts::sparkline( $totals_series, 200, 50, '#7c3aed' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php $delta_footer( $total_delta ); ?>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon wpdash-stat-icon--solid is-red" aria-hidden="true"><span class="dashicons dashicons-warning"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Failures', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $current_totals['fail'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php echo esc_html( $range_label_text ); ?></p>
			<div class="wpdash-stat-spark-wrap">
				<?php echo Charts::sparkline( $fail_series, 200, 50, '#ef4444' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php $delta_footer( $fail_delta ); ?>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-cyan" aria-hidden="true"><span class="dashicons dashicons-clock"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Avg Response Time', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( (string) round( $current_totals['avg_duration'] ) ); ?><small>ms</small></span>
			</div>
			<p class="wpdash-stat-caption"><?php echo esc_html( $response_tier( (float) $current_totals['avg_duration'] ) ); ?></p>
			<div class="wpdash-stat-spark-wrap">
				<?php echo Charts::sparkline( $duration_series, 200, 50, '#7c3aed' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php $delta_footer( $duration_delta ); ?>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon wpdash-stat-icon--solid is-green" aria-hidden="true"><span class="dashicons dashicons-heart"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Uptime (Self-check)', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo null !== $uptime_rate_current ? esc_html( (string) $uptime_rate_current ) . '<small>%</small>' : '<small>—</small>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php echo esc_html( $uptime_tier( $uptime_rate_current ) ); ?></p>
			<div class="wpdash-stat-spark-wrap">
				<?php echo Charts::sparkline( $uptime_rate_series, 200, 50, '#10b981' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php $delta_footer( $uptime_delta ); ?>
		</div>
	</div>

	<div class="wpdash-row2">
		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><?php esc_html_e( 'Monitoring Overview', 'watchspire' ); ?></p>
			</div>
			<?php
			// One source of truth for both the donut segments and the legend
			// beside it — the two used to carry separate color lists that had
			// already drifted apart (Paused was #e2e8f0 in the ring but
			// #94a3b8 in the legend). 'icon' is ignored by Charts::donut().
			$donut_rows  = array(
				array(
					'label' => __( 'Passing', 'watchspire' ),
					'value' => $pass_count,
					'color' => '#10b981',
					'icon'  => 'yes',
				),
				array(
					'label' => __( 'Warnings', 'watchspire' ),
					'value' => $warn_count,
					'color' => '#f59e0b',
					'icon'  => 'warning',
				),
				array(
					'label' => __( 'Failing', 'watchspire' ),
					'value' => $fail_count,
					'color' => '#ef4444',
					'icon'  => 'no-alt',
				),
				array(
					'label' => __( 'Paused', 'watchspire' ),
					'value' => $paused_count,
					'color' => '#94a3b8',
					'icon'  => 'controls-pause',
				),
			);
			$donut_total = max( 1, array_sum( array_column( $donut_rows, 'value' ) ) );
			?>
			<div class="wpdash-donut-wrap">
				<?php echo Charts::donut( $donut_rows, 200, __( 'Monitors', 'watchspire' ), 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Charts::donut() escapes internally. ?>
				<div class="wpdash-donut-legend wpdash-donut-legend--icons">
					<?php foreach ( $donut_rows as $donut_row ) : ?>
						<div class="wpdash-donut-legend-row">
							<span class="wpdash-donut-legend-name">
								<span class="wpdash-donut-legend-badge" style="background:<?php echo esc_attr( $donut_row['color'] ); ?>;" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr( $donut_row['icon'] ); ?>"></span></span>
								<?php echo esc_html( $donut_row['label'] ); ?>
							</span>
							<span class="wpdash-donut-legend-val"><?php echo esc_html( sprintf( '%1$d (%2$d%%)', $donut_row['value'], round( ( $donut_row['value'] / $donut_total ) * 100 ) ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<a class="wpdash-card-link" href="#wpdash-monitors" style="display:block;margin-top:14px;"><?php esc_html_e( 'View all monitors →', 'watchspire' ); ?></a>
		</div>

		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><?php esc_html_e( 'Checks Over Time', 'watchspire' ); ?></p>
				<div class="wpdash-area-legend">
					<span><span class="dot" style="background:#10b981;"></span><?php esc_html_e( 'Passed', 'watchspire' ); ?></span>
					<span><span class="dot" style="background:#f59e0b;"></span><?php esc_html_e( 'Warnings', 'watchspire' ); ?></span>
					<span><span class="dot" style="background:#ef4444;"></span><?php esc_html_e( 'Failures', 'watchspire' ); ?></span>
				</div>
				<span class="wpdash-range-tag"><?php echo esc_html( $range_label_text ); ?></span>
			</div>
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Charts::area_multi() escapes internally; __() calls here only build its $lines argument.
			echo Charts::area_multi(
				$daily_series,
				array(
					'pass' => array(
						'color' => '#10b981',
						'label' => __( 'Passed', 'watchspire' ),
					),
					'warn' => array(
						'color' => '#f59e0b',
						'label' => __( 'Warnings', 'watchspire' ),
					),
					'fail' => array(
						'color' => '#ef4444',
						'label' => __( 'Failures', 'watchspire' ),
					),
				),
				520,
				190
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>

		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><span class="wpdash-card-icon is-red" aria-hidden="true"><span class="dashicons dashicons-warning"></span></span><?php esc_html_e( 'Recent Failures', 'watchspire' ); ?></p>
			</div>
			<div class="wpdash-list">
				<?php if ( empty( $recent_failures ) ) : ?>
					<div class="wpdash-list-empty">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'No failures recorded.', 'watchspire' ); ?>
					</div>
				<?php else : ?>
					<?php foreach ( $recent_failures as $failure ) : ?>
						<div class="wpdash-list-item">
							<span class="wpdash-list-dot" style="background:#ef4444;" aria-hidden="true"></span>
							<div class="wpdash-list-body">
								<p class="wpdash-list-title"><?php echo esc_html( $failure['monitor_id'] ); ?></p>
								<p class="wpdash-list-desc"><?php echo esc_html( $failure['message'] ); ?></p>
							</div>
							<span class="wpdash-list-meta"><?php echo esc_html( human_time_diff( strtotime( $failure['created_at'] . ' UTC' ) ) ); ?> <?php esc_html_e( 'ago', 'watchspire' ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="wpdash-row3">
		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><span class="wpdash-card-icon is-indigo" aria-hidden="true"><span class="dashicons dashicons-feedback"></span></span><?php esc_html_e( 'Submissions', 'watchspire' ); ?> <span class="wpdash-card-hdr-meta">(<?php echo esc_html( $range_label_text ); ?>)</span></p>
			</div>
			<div class="wpdash-kpi-row">
				<span class="wpdash-kpi-num"><?php echo esc_html( number_format_i18n( $submission_totals['total'] ) ); ?></span>
				<span class="wpdash-kpi-caption"><?php esc_html_e( 'total submissions', 'watchspire' ); ?></span>
			</div>
			<?php if ( $submission_totals['total'] > 0 ) : ?>
				<div class="wpdash-kpi-breakdown">
					<span class="is-green"><?php echo esc_html( (string) $submission_totals['delivered'] ); ?> <?php esc_html_e( 'Delivered', 'watchspire' ); ?> (<?php echo esc_html( (string) round( ( $submission_totals['delivered'] / $submission_totals['total'] ) * 100 ) ); ?>%)</span>
					<span class="is-red"><?php echo esc_html( (string) $submission_totals['failed'] ); ?> <?php esc_html_e( 'Failed', 'watchspire' ); ?> (<?php echo esc_html( (string) round( ( $submission_totals['failed'] / $submission_totals['total'] ) * 100 ) ); ?>%)</span>
				</div>
			<?php endif; ?>
			<?php if ( empty( $submission_forms ) ) : ?>
				<div class="wpdash-list-empty">
					<span class="dashicons dashicons-email" aria-hidden="true"></span>
					<?php esc_html_e( 'No form submissions recorded yet.', 'watchspire' ); ?>
				</div>
			<?php else : ?>
				<?php $max_daily = max( 1, max( $submission_daily ) ); ?>
				<div class="wpdash-mini-bars">
					<?php foreach ( $submission_daily as $date => $count ) : ?>
						<div class="bar" style="height: <?php echo esc_attr( max( 4, round( ( $count / $max_daily ) * 88 ) ) ); ?>px;" title="<?php echo esc_attr( gmdate( 'D', strtotime( $date ) ) . ': ' . $count ); ?>"></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><span class="wpdash-card-icon is-amber" aria-hidden="true"><span class="dashicons dashicons-editor-removeformatting"></span></span><?php esc_html_e( 'Top 404s', 'watchspire' ); ?> <span class="wpdash-card-hdr-meta">(<?php echo esc_html( $range_label_text ); ?>)</span></p>
			</div>
			<div class="wpdash-list">
				<?php if ( empty( $top_404s ) ) : ?>
					<div class="wpdash-list-empty">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'No 404s recorded.', 'watchspire' ); ?>
					</div>
				<?php else : ?>
					<?php foreach ( $top_404s as $error_row ) : ?>
						<div class="wpdash-list-item">
							<div class="wpdash-list-body">
								<p class="wpdash-list-title"><?php echo esc_html( $error_row['url'] ); ?></p>
							</div>
							<span class="wpdash-list-meta"><?php echo esc_html( (string) $error_row['count'] ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><span class="wpdash-card-icon is-blue" aria-hidden="true"><span class="dashicons dashicons-backup"></span></span><?php esc_html_e( 'Change Log', 'watchspire' ); ?> <span class="wpdash-card-hdr-meta">(<?php esc_html_e( 'Recent', 'watchspire' ); ?>)</span></p>
			</div>
			<div class="wpdash-list">
				<?php if ( empty( $changelog ) ) : ?>
					<div class="wpdash-list-empty">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						<?php esc_html_e( 'No changes recorded yet.', 'watchspire' ); ?>
					</div>
				<?php else : ?>
					<?php foreach ( $changelog as $event ) : ?>
						<div class="wpdash-list-item">
							<span class="wpdash-list-dot" style="background:#3b82f6;" aria-hidden="true"></span>
							<div class="wpdash-list-body">
								<p class="wpdash-list-title"><?php echo esc_html( $event['object_name'] ? $event['object_name'] : $event['object_slug'] ); ?></p>
								<p class="wpdash-list-desc"><?php echo esc_html( ucwords( str_replace( '_', ' ', $event['type'] ) ) ); ?></p>
							</div>
							<span class="wpdash-list-meta"><?php echo esc_html( human_time_diff( strtotime( $event['created_at'] . ' UTC' ) ) ); ?> <?php esc_html_e( 'ago', 'watchspire' ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<a class="wpdash-card-link" href="<?php echo esc_url( admin_url( 'admin.php?page=watchspire-changelog' ) ); ?>" style="display:block;margin-top:12px;"><?php esc_html_e( 'View full change log →', 'watchspire' ); ?></a>
		</div>

		<div class="wpdash-card">
			<div class="wpdash-card-hdr">
				<p class="wpdash-card-title"><span class="wpdash-card-icon is-purple" aria-hidden="true"><span class="dashicons dashicons-networking"></span></span><?php esc_html_e( 'AI Crawler Activity', 'watchspire' ); ?> <span class="wpdash-card-hdr-meta">(<?php esc_html_e( 'Today', 'watchspire' ); ?>)</span></p>
			</div>
			<?php
			$crawler_total_today = array_sum( array_column( $crawler_today, 'hits' ) );
			?>
			<?php if ( empty( $crawler_today ) || 0 === $crawler_total_today ) : ?>
				<div class="wpdash-list-empty">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<?php esc_html_e( 'No AI crawler hits recorded yet.', 'watchspire' ); ?>
				</div>
			<?php else : ?>
				<div class="wpdash-kpi-row">
					<span class="wpdash-kpi-num"><?php echo esc_html( number_format_i18n( $crawler_total_today ) ); ?></span>
					<span class="wpdash-kpi-caption"><?php esc_html_e( 'total hits', 'watchspire' ); ?></span>
				</div>

				<?php if ( has_action( 'watchspire_dashboard_crawler_breakdown' ) ) : ?>
					<?php
					/**
					 * Replaces the per-bot list below with the listener's own,
					 * so an add-on with a richer breakdown doesn't render a
					 * second list under ours. With no listener, WatchSpire
					 * renders its own — the breakdown is never unavailable.
					 *
					 * @param array<int,array<string,mixed>> $crawler_today       Bot rows, highest hits first.
					 * @param int                            $crawler_total_today Sum of hits across all bots.
					 */
					do_action( 'watchspire_dashboard_crawler_breakdown', $crawler_today, $crawler_total_today );
					?>
				<?php else : ?>
				<div class="wpdash-list">
					<?php foreach ( $crawler_today as $crawler_row ) : ?>
						<?php
						$bot_hits  = (int) $crawler_row['hits'];
						$bot_share = $crawler_total_today > 0 ? (int) round( ( $bot_hits / $crawler_total_today ) * 100 ) : 0;
						?>
						<div class="wpdash-list-item">
							<div class="wpdash-list-body">
								<p class="wpdash-list-title"><?php echo esc_html( $crawler_row['bot'] ); ?></p>
								<p class="wpdash-list-desc">
									<?php
									printf(
										/* translators: 1: 2xx response count, 2: 4xx response count, 3: 5xx response count */
										esc_html__( '%1$s OK · %2$s client errors · %3$s server errors', 'watchspire' ),
										esc_html( number_format_i18n( (int) $crawler_row['s2'] ) ),
										esc_html( number_format_i18n( (int) $crawler_row['s4'] ) ),
										esc_html( number_format_i18n( (int) $crawler_row['s5'] ) )
									);
									?>
								</p>
							</div>
							<span class="wpdash-list-meta">
								<?php echo esc_html( number_format_i18n( $bot_hits ) ); ?>
								<span class="wpdash-list-meta-share">(<?php echo esc_html( (string) $bot_share ); ?>%)</span>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="wpdash-monitors-section" id="wpdash-monitors">
		<h2 class="wpdash-monitors-title"><?php esc_html_e( 'Monitors Status', 'watchspire' ); ?></h2>
		<div class="wpdash-monitors-strip">
			<?php
			foreach ( $monitors as $monitor ) :
				$monitor_id     = $monitor->get_id();
				$row            = $latest[ $monitor_id ] ?? null;
				$monitor_status = $row['status'] ?? 'unknown';

				if ( 'all' !== $mstatus_filter && $mstatus_filter !== $monitor_status ) {
					continue;
				}
				?>
				<div class="wpdash-mon-card is-<?php echo esc_attr( $monitor_status ); ?>">
					<div class="wpdash-mon-head">
						<span class="wpdash-mon-icon" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr( $monitor_icons[ $monitor_id ] ?? 'shield' ); ?>"></span></span>
						<p class="wpdash-mon-name"><?php echo esc_html( $monitor->get_label() ); ?></p>
					</div>
					<p class="wpdash-mon-status is-<?php echo esc_attr( $monitor_status ); ?>">
						<span class="dashicons dashicons-<?php echo esc_attr( $status_icons[ $monitor_status ] ?? $status_icons['unknown'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( ucfirst( $monitor_status ) ); ?>
					</p>
					<?php if ( $row && $row['message'] ) : ?>
						<p class="wpdash-mon-detail"><?php echo esc_html( $row['message'] ); ?></p>
					<?php endif; ?>
					<p class="wpdash-mon-meta">
						<?php
						if ( $row ) {
							printf(
								/* translators: %s: human time diff */
								esc_html__( 'Checked %s ago', 'watchspire' ),
								esc_html( human_time_diff( strtotime( $row['created_at'] . ' UTC' ) ) )
							);
						} else {
							esc_html_e( 'Not checked yet', 'watchspire' );
						}
						?>
					</p>
				</div>
			<?php endforeach; ?>

			<?php if ( in_array( $mstatus_filter, array( 'all', 'pass' ), true ) && $crawler_total_today > 0 ) : ?>
				<div class="wpdash-mon-card is-pass">
					<div class="wpdash-mon-head">
						<span class="wpdash-mon-icon" aria-hidden="true"><span class="dashicons dashicons-networking"></span></span>
						<p class="wpdash-mon-name"><?php esc_html_e( 'AI Crawler Activity', 'watchspire' ); ?></p>
					</div>
					<p class="wpdash-mon-status is-pass"><span class="dashicons dashicons-info" aria-hidden="true"></span><?php esc_html_e( 'Info', 'watchspire' ); ?></p>
					<p class="wpdash-mon-detail">
						<?php
						printf(
							/* translators: %s: hit count */
							esc_html__( '%s hits today', 'watchspire' ),
							esc_html( number_format_i18n( $crawler_total_today ) )
						);
						?>
					</p>
					<p class="wpdash-mon-meta"><?php esc_html_e( 'Normal activity', 'watchspire' ); ?></p>
				</div>
			<?php endif; ?>

			<a class="wpdash-mon-card wpdash-mon-card--add" href="<?php echo esc_url( admin_url( 'admin.php?page=watchspire-settings&tab=monitors' ) ); ?>">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<span class="label"><?php esc_html_e( 'Manage monitors', 'watchspire' ); ?></span>
				<span class="sub"><?php esc_html_e( 'Enable, disable, tune', 'watchspire' ); ?></span>
			</a>
		</div>
	</div>
</div>
