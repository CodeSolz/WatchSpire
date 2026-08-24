<?php
/**
 * Broken link & image scanner screen.
 *
 * @package WatchSpire
 *
 * @var array<string,mixed>            $state
 * @var bool                           $opted_in
 * @var array<int,array<string,mixed>> $rows             Current page of filtered rows.
 * @var array<string,int>              $counts           total_scanned/broken_links/broken_images/ignored.
 * @var array<int,array{id:int,title:string}> $source_posts
 * @var string[]                       $source_types
 * @var array{codes:int[],has_unreachable:bool} $broken_statuses
 * @var string                         $view
 * @var string                         $type
 * @var string                         $source
 * @var int                            $post_id
 * @var string                         $status
 * @var string                         $search
 * @var int                            $total_matching
 * @var int                            $total_pages
 * @var int                            $paged
 * @var int                            $per_page
 */

defined( 'ABSPATH' ) || exit;

use WatchSpire\Admin\AdminMenu;

$state_labels = array(
	'idle'       => __( 'Idle', 'watchspire' ),
	'extracting' => __( 'Scanning content…', 'watchspire' ),
	'checking'   => __( 'Checking links…', 'watchspire' ),
	'paused'     => __( 'Paused', 'watchspire' ),
	'completed'  => __( 'Completed', 'watchspire' ),
);

$total     = (int) max( $state['total'], $state['checked'] );
$checked   = (int) $state['checked'];
$percent   = $total > 0 ? (int) min( 100, round( ( $checked / $total ) * 100 ) ) : 0;
$is_busy   = in_array( $state['status'], array( 'extracting', 'checking' ), true );
$all_total = $counts['broken_links'] + $counts['broken_images'];

$scan_titles = array(
	'idle'       => __( 'Ready to scan', 'watchspire' ),
	'extracting' => __( 'Scanning in progress…', 'watchspire' ),
	'checking'   => __( 'Scanning in progress…', 'watchspire' ),
	'paused'     => __( 'Scan paused', 'watchspire' ),
	'completed'  => __( 'Scan completed', 'watchspire' ),
);
$scan_title  = $scan_titles[ $state['status'] ] ?? ucfirst( $state['status'] );

if ( $is_busy ) {
	$scan_subtitle = $state_labels[ $state['status'] ] ?? '';
} elseif ( 'paused' === $state['status'] ) {
	$scan_subtitle = __( 'Resume anytime — progress is saved.', 'watchspire' );
} elseif ( 'completed' === $state['status'] ) {
	$scan_subtitle = sprintf(
		/* translators: %d: number of broken links/images found */
		_n( '%d broken link or image found.', '%d broken links or images found.', (int) $state['broken'], 'watchspire' ),
		(int) $state['broken']
	);
} else {
	$scan_subtitle = __( 'Run a scan to check your links and images.', 'watchspire' );
}

$elapsed_seconds = $state['started_at'] ? max( 0, ( $state['finished_at'] ? $state['finished_at'] : time() ) - $state['started_at'] ) : 0;
$elapsed_display = sprintf( '%02d:%02d:%02d', floor( $elapsed_seconds / 3600 ), floor( ( $elapsed_seconds % 3600 ) / 60 ), $elapsed_seconds % 60 );

$actions_html = sprintf(
	'<a href="%1$s" class="button button-primary"><span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>%2$s</a>
	<a href="%3$s" class="button"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>%4$s</a>',
	esc_url( admin_url( 'admin.php?page=watchspire-settings&tab=scanning' ) ),
	esc_html__( 'Schedule scans', 'watchspire' ),
	esc_url( admin_url( 'admin.php?page=watchspire-settings' ) ),
	esc_html__( 'Settings', 'watchspire' )
);

AdminMenu::render_page_header( __( 'Broken Links & Images', 'watchspire' ), '', $actions_html );
?>

<?php if ( ! $opted_in ) : ?>
	<div class="notice notice-info inline">
		<p>
			<?php esc_html_e( 'Link scanning is off by default because it can be resource-intensive on large sites.', 'watchspire' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=watchspire-settings&tab=scanning' ) ); ?>"><?php esc_html_e( 'Enable it in Settings →', 'watchspire' ); ?></a>
		</p>
	</div>
<?php else : ?>
	<?php
	$prev_counts = is_array( $state['prev_counts'] ?? null ) ? $state['prev_counts'] : null;

	/**
	 * Renders the "+N since last scan" / "No change" caption under a stat
	 * card, or nothing when there's no prior scan to diff against yet.
	 */
	$stat_delta_caption = static function ( ?array $prev_counts, string $key ) use ( $counts ) {
		if ( null === $prev_counts || ! isset( $prev_counts[ $key ] ) ) {
			return '';
		}

		$delta = (int) $counts[ $key ] - (int) $prev_counts[ $key ];

		if ( 0 === $delta ) {
			$text = __( 'No change', 'watchspire' );
		} else {
			$text = sprintf( '%+d %s', $delta, __( 'since last scan', 'watchspire' ) );
		}

		return '<p class="watchspire-stat-caption">' . esc_html( $text ) . '</p>';
	};
	?>
	<div class="wpdash wpdash-links">
		<div class="watchspire-stats-row">
			<div class="watchspire-stat-card">
				<div class="watchspire-stat-row">
					<span class="watchspire-stat-icon is-indigo" aria-hidden="true"><span class="dashicons dashicons-admin-links"></span></span>
					<div class="watchspire-stat-main">
						<span class="watchspire-stat-value"><?php echo esc_html( number_format_i18n( $counts['total_scanned'] ) ); ?></span>
						<span class="watchspire-stat-label"><?php esc_html_e( 'Total Scanned', 'watchspire' ); ?></span>
					</div>
				</div>
				<?php echo $stat_delta_caption( $prev_counts, 'total_scanned' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above. ?>
			</div>

			<div class="watchspire-stat-card">
				<div class="watchspire-stat-row">
					<span class="watchspire-stat-icon is-red" aria-hidden="true"><span class="dashicons dashicons-editor-unlink"></span></span>
					<div class="watchspire-stat-main">
						<span class="watchspire-stat-value"><?php echo esc_html( number_format_i18n( $counts['broken_links'] ) ); ?></span>
						<span class="watchspire-stat-label"><?php esc_html_e( 'Broken Links', 'watchspire' ); ?></span>
					</div>
				</div>
				<?php echo $stat_delta_caption( $prev_counts, 'broken_links' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above. ?>
			</div>

			<div class="watchspire-stat-card">
				<div class="watchspire-stat-row">
					<span class="watchspire-stat-icon is-amber" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
					<div class="watchspire-stat-main">
						<span class="watchspire-stat-value"><?php echo esc_html( number_format_i18n( $counts['broken_images'] ) ); ?></span>
						<span class="watchspire-stat-label"><?php esc_html_e( 'Broken Images', 'watchspire' ); ?></span>
					</div>
				</div>
				<?php echo $stat_delta_caption( $prev_counts, 'broken_images' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above. ?>
			</div>

			<div class="watchspire-stat-card">
				<div class="watchspire-stat-row">
					<span class="watchspire-stat-icon is-purple" aria-hidden="true"><span class="dashicons dashicons-controls-repeat"></span></span>
					<div class="watchspire-stat-main">
						<span class="watchspire-stat-value"><?php echo esc_html( number_format_i18n( $counts['ignored'] ) ); ?></span>
						<span class="watchspire-stat-label"><?php esc_html_e( 'Ignored', 'watchspire' ); ?></span>
					</div>
				</div>
				<?php echo $stat_delta_caption( $prev_counts, 'ignored' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above. ?>
			</div>

			<div class="watchspire-stat-card">
				<div class="watchspire-stat-row">
					<span class="watchspire-stat-icon is-green" aria-hidden="true"><span class="dashicons dashicons-clock"></span></span>
					<div class="watchspire-stat-main">
						<span class="watchspire-stat-value watchspire-stat-value--sm">
							<?php echo $state['finished_at'] ? esc_html( date_i18n( 'M j, Y', $state['finished_at'] ) ) : esc_html__( 'Never', 'watchspire' ); ?>
						</span>
						<span class="watchspire-stat-label"><?php esc_html_e( 'Last Scan', 'watchspire' ); ?></span>
					</div>
				</div>
				<?php if ( $state['finished_at'] ) : ?>
					<p class="watchspire-stat-caption">
						<?php echo esc_html( date_i18n( 'g:i A', $state['finished_at'] ) ); ?> ·
						<?php
						/* translators: %s: human-readable time difference, e.g. "25 minutes" */
						printf( esc_html__( '%s ago', 'watchspire' ), esc_html( human_time_diff( $state['finished_at'] ) ) );
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="watchspire-scan-controls" id="watchspire-scan-controls" data-state="<?php echo esc_attr( $state['status'] ); ?>" data-total="<?php echo esc_attr( (string) $total ); ?>" data-checked="<?php echo esc_attr( (string) $checked ); ?>" data-broken="<?php echo esc_attr( (string) (int) $state['broken'] ); ?>" data-started-at="<?php echo esc_attr( (string) (int) $state['started_at'] ); ?>" data-finished-at="<?php echo esc_attr( (string) (int) $state['finished_at'] ); ?>">
			<div class="watchspire-scan-controls-row">
				<div class="watchspire-scan-gauge-block">
					<span class="watchspire-gauge watchspire-gauge--md">
						<svg class="watchspire-gauge-svg" viewBox="0 0 100 100" role="img" aria-hidden="true">
							<circle class="watchspire-gauge-track" cx="50" cy="50" r="42" />
							<circle class="watchspire-gauge-fill" cx="50" cy="50" r="42" stroke="url(#watchspire-scan-gauge-grad)" pathLength="100" stroke-dasharray="<?php echo esc_attr( (string) $percent ); ?> 100" />
							<defs>
								<linearGradient id="watchspire-scan-gauge-grad" x1="0" y1="0" x2="1" y2="1">
									<stop offset="0%" stop-color="#4f46e5" />
									<stop offset="100%" stop-color="#7c3aed" />
								</linearGradient>
							</defs>
						</svg>
						<span class="watchspire-gauge-center">
							<span class="watchspire-gauge-num" id="watchspire-scan-percent" style="color:var(--c-primary);"><?php echo esc_html( (string) $percent ); ?>%</span>
						</span>
					</span>
				</div>

				<div class="watchspire-scan-status-block">
					<p class="watchspire-scan-title" id="watchspire-scan-status"><?php echo esc_html( $scan_title ); ?></p>
					<p class="watchspire-scan-subtitle" id="watchspire-scan-progress"><?php echo esc_html( $scan_subtitle ); ?></p>
				</div>

				<span class="watchspire-scan-spinner<?php echo $is_busy ? ' is-visible' : ''; ?>" id="watchspire-scan-spinner" aria-hidden="true"></span>

				<div class="watchspire-scan-actions watchspire-scan-actions--grid">
					<button type="button" class="button button-primary" id="watchspire-scan-start" <?php disabled( $is_busy ); ?>>
						<span class="dashicons dashicons-controls-play" aria-hidden="true" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom;"></span>
						<?php esc_html_e( 'Scan now', 'watchspire' ); ?>
					</button>
					<button type="button" class="button" id="watchspire-scan-pause" <?php disabled( ! $is_busy ); ?>>
						<?php esc_html_e( 'Pause', 'watchspire' ); ?>
					</button>
					<button type="button" class="button" id="watchspire-scan-resume" <?php disabled( 'paused' !== $state['status'] ); ?>>
						<?php esc_html_e( 'Resume', 'watchspire' ); ?>
					</button>
					<button type="button" class="button" id="watchspire-scan-cancel" <?php disabled( in_array( $state['status'], array( 'idle', 'completed' ), true ) ); ?>>
						<?php esc_html_e( 'Cancel', 'watchspire' ); ?>
					</button>
				</div>
			</div>

			<div class="watchspire-progress-track">
				<div class="watchspire-progress-fill" id="watchspire-scan-progress-fill" style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></div>
			</div>

			<div class="watchspire-scan-meta-row">
				<span id="watchspire-scan-meta-scanned">
					<?php
					printf(
						/* translators: 1: number of URLs already checked, 2: total number of URLs to check */
						esc_html__( 'Scanned %1$s of %2$s URLs', 'watchspire' ),
						esc_html( number_format_i18n( $checked ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</span>
				<span id="watchspire-scan-elapsed">
					<?php
					printf(
						/* translators: %s: elapsed time, e.g. "1m 20s" */
						esc_html__( 'Elapsed: %s', 'watchspire' ),
						esc_html( $elapsed_display )
					);
					?>
				</span>
			</div>

			<p class="watchspire-scan-warning<?php echo $is_busy ? ' is-visible' : ''; ?>" id="watchspire-scan-warning">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php esc_html_e( "Please don't refresh or close this tab while the scan is running.", 'watchspire' ); ?>
			</p>
		</div>

		<nav class="watchspire-tabs">
			<?php
			// Named link_tabs / link_tab rather than tabs / tab: this file is
			// include()d from a method, and $tab is a WordPress global on some
			// admin screens, so the shorter names read as a global override.
			$link_tabs = array(
				'all'     => array( __( 'All', 'watchspire' ), $all_total ),
				'links'   => array( __( 'Broken Links', 'watchspire' ), $counts['broken_links'] ),
				'images'  => array( __( 'Broken Images', 'watchspire' ), $counts['broken_images'] ),
				'ignored' => array( __( 'Ignored', 'watchspire' ), $counts['ignored'] ),
			);
			foreach ( $link_tabs as $tab_slug => $link_tab ) :
				$tab_url = add_query_arg(
					array(
						'page'  => 'watchspire-links',
						'view'  => $tab_slug,
						'paged' => false,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="<?php echo $tab_slug === $view ? 'is-active' : ''; ?>">
					<?php echo esc_html( $link_tab[0] ); ?> <span class="count">(<?php echo esc_html( number_format_i18n( $link_tab[1] ) ); ?>)</span>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="watchspire-quickfilters">
			<form method="get" id="watchspire-links-filters">
				<input type="hidden" name="page" value="watchspire-links" />
				<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />

				<select name="type" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All Types', 'watchspire' ); ?></option>
					<option value="link" <?php selected( $type, 'link' ); ?>><?php esc_html_e( 'Links', 'watchspire' ); ?></option>
					<option value="image" <?php selected( $type, 'image' ); ?>><?php esc_html_e( 'Images', 'watchspire' ); ?></option>
				</select>

				<select name="source" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All Sources', 'watchspire' ); ?></option>
					<?php foreach ( $source_types as $source_type ) : ?>
						<?php
						if ( 'nav_menu' === $source_type ) {
							$source_label = __( 'Navigation menu', 'watchspire' );
						} else {
							$post_type_obj = get_post_type_object( $source_type );
							$source_label  = $post_type_obj ? $post_type_obj->labels->singular_name : ucfirst( $source_type );
						}
						?>
						<option value="<?php echo esc_attr( $source_type ); ?>" <?php selected( $source, $source_type ); ?>><?php echo esc_html( $source_label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="status" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'All Statuses', 'watchspire' ); ?></option>
					<?php foreach ( $broken_statuses['codes'] as $code ) : ?>
						<option value="<?php echo esc_attr( (string) $code ); ?>" <?php selected( $status, (string) $code ); ?>>
							<?php echo esc_html( $code . ' ' . get_status_header_desc( $code ) ); ?>
						</option>
					<?php endforeach; ?>
					<?php
					$reason_labels = array(
						'timeout'     => __( 'Timeout', 'watchspire' ),
						'ssl_error'   => __( 'SSL Error', 'watchspire' ),
						'dns_error'   => __( 'DNS Error', 'watchspire' ),
						'unreachable' => __( 'Unreachable', 'watchspire' ),
					);
					foreach ( $broken_statuses['reasons'] as $reason ) :
						?>
						<option value="<?php echo esc_attr( $reason ); ?>" <?php selected( $status, $reason ); ?>><?php echo esc_html( $reason_labels[ $reason ] ?? ucfirst( $reason ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="post_id" onchange="this.form.submit()">
					<option value="0"><?php esc_html_e( 'All Posts & Pages', 'watchspire' ); ?></option>
					<?php foreach ( $source_posts as $source_post ) : ?>
						<option value="<?php echo esc_attr( (string) $source_post['id'] ); ?>" <?php selected( $post_id, $source_post['id'] ); ?>><?php echo esc_html( $source_post['title'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<a href="<?php echo esc_url( add_query_arg( array(
'page' => 'watchspire-links',
'view' => $view
), admin_url( 'admin.php' ) ) ); ?>" class="button">
					<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span><?php esc_html_e( 'Clear filters', 'watchspire' ); ?>
				</a>

				<span class="watchspire-search">
					<label for="watchspire-links-search" class="screen-reader-text"><?php esc_html_e( 'Search URLs', 'watchspire' ); ?></label>
					<input type="search" name="s" id="watchspire-links-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search URLs…', 'watchspire' ); ?>" />
					<button type="submit" class="watchspire-search-submit" aria-label="<?php esc_attr_e( 'Search', 'watchspire' ); ?>">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
					</button>
				</span>
			</form>
		</div>

		<div class="watchspire-table-wrap">
			<?php if ( empty( $rows ) ) : ?>
				<div class="watchspire-empty">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Nothing here — either nothing matches these filters, or a scan hasn\'t completed yet.', 'watchspire' ); ?>
				</div>
			<?php else : ?>
				<table class="watchspire-table" id="watchspire-links-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'URL', 'watchspire' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'watchspire' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'watchspire' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Found On', 'watchspire' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last Checked', 'watchspire' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'watchspire' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$reason_labels = array(
							'timeout'     => __( 'Timeout', 'watchspire' ),
							'ssl_error'   => __( 'SSL Error', 'watchspire' ),
							'dns_error'   => __( 'DNS Error', 'watchspire' ),
							'unreachable' => __( 'Unreachable', 'watchspire' ),
						);
						?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$code   = $row['http_code'] ? (int) $row['http_code'] : null;
							$reason = $row['error_reason'] ?? null;

							if ( 'ok' === $row['status'] ) {
								$badge_class = 'is-pass';
								$badge_label = $code ? $code . ' ' . get_status_header_desc( $code ) : __( 'OK', 'watchspire' );
							} elseif ( $code && $code >= 300 && $code < 400 ) {
								$badge_class = 'is-pass';
								$badge_label = __( 'Redirect', 'watchspire' ) . ' ' . $code;
							} elseif ( in_array( $code, array( 401, 403, 429 ), true ) ) {
								$badge_class = 'is-warn';
								$badge_label = $code . ' ' . get_status_header_desc( $code );
							} elseif ( $code ) {
								$badge_class = 'is-fail';
								$badge_label = $code . ' ' . get_status_header_desc( $code );
							} elseif ( 'timeout' === $reason ) {
								$badge_class = 'is-warn';
								$badge_label = $reason_labels['timeout'];
							} elseif ( 'ssl_error' === $reason ) {
								$badge_class = 'is-purple';
								$badge_label = $reason_labels['ssl_error'];
							} else {
								$badge_class = 'is-fail';
								$badge_label = $reason_labels[ $reason ] ?? __( 'Unreachable', 'watchspire' );
							}
							?>
							<tr data-id="<?php echo esc_attr( $row['id'] ); ?>">
								<td class="watchspire-truncate">
									<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['url'] ); ?></a>
								</td>
								<td>
									<span class="dashicons dashicons-<?php echo 'image' === $row['type'] ? 'format-image' : 'admin-links'; ?>" style="color:#9ca3af;font-size:15px;width:15px;height:15px;vertical-align:text-bottom;" aria-hidden="true"></span>
									<?php echo esc_html( ucfirst( $row['type'] ) ); ?>
								</td>
								<td><span class="watchspire-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span></td>
								<td>
									<?php if ( $row['source_post_id'] ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $row['source_post_id'] ) ); ?>"><?php echo esc_html( $row['source_title'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $row['source_title'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo $row['checked_at'] ? esc_html( human_time_diff( strtotime( $row['checked_at'] . ' UTC' ) ) . ' ' . __( 'ago', 'watchspire' ) ) : '—'; ?></td>
								<td>
									<div class="watchspire-row-actions">
										<button type="button" class="watchspire-icon-btn watchspire-link-action" data-action="recheck" data-id="<?php echo esc_attr( $row['id'] ); ?>" title="<?php esc_attr_e( 'Recheck', 'watchspire' ); ?>">
											<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
										</button>
										<?php if ( $row['is_ignored'] ) : ?>
											<button type="button" class="watchspire-icon-btn watchspire-link-action" data-action="unignore" data-id="<?php echo esc_attr( $row['id'] ); ?>" title="<?php esc_attr_e( 'Unignore', 'watchspire' ); ?>">
												<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
											</button>
										<?php else : ?>
											<button type="button" class="watchspire-icon-btn watchspire-link-action" data-action="ignore" data-id="<?php echo esc_attr( $row['id'] ); ?>" title="<?php esc_attr_e( 'Ignore', 'watchspire' ); ?>">
												<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
											</button>
										<?php endif; ?>
										<?php if ( $row['source_post_id'] ) : ?>
											<a href="<?php echo esc_url( (string) get_edit_post_link( $row['source_post_id'] ) ); ?>" class="watchspire-icon-btn" title="<?php esc_attr_e( 'Edit source', 'watchspire' ); ?>">
												<span class="dashicons dashicons-edit" aria-hidden="true"></span>
											</a>
										<?php endif; ?>
										<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="watchspire-icon-btn" title="<?php esc_attr_e( 'Open URL', 'watchspire' ); ?>">
											<span class="dashicons dashicons-external" aria-hidden="true"></span>
										</a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<?php if ( $total_matching > 0 ) : ?>
			<div class="wpdash-pagination">
				<span>
					<?php
					$range_start = ( $paged - 1 ) * $per_page + 1;
					$range_end   = min( $total_matching, $paged * $per_page );
					printf(
						/* translators: 1: first row number on this page, 2: last row number on this page, 3: total matching rows */
						esc_html__( 'Showing %1$s to %2$s of %3$s results', 'watchspire' ),
						esc_html( number_format_i18n( $range_start ) ),
						esc_html( number_format_i18n( $range_end ) ),
						esc_html( number_format_i18n( $total_matching ) )
					);
					?>
				</span>
				<?php if ( $total_pages > 1 ) : ?>
					<span class="wpdash-pagination-links">
						<?php
						$page_links = paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => (int) $paged,
								'total'     => (int) $total_pages,
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'type'      => 'array',
								'mid_size'  => 1,
								'end_size'  => 1,
							)
						);

						// Escaped on output rather than suppressed: paginate_links()
						// only ever returns anchors/spans, all of which survive
						// wp_kses_post() intact.
						foreach ( (array) $page_links as $page_link ) {
							echo wp_kses_post( $page_link );
						}
						?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
