<?php
/**
 * Change log screen: filterable, paginated timeline of site changes.
 *
 * @package WatchSpire
 *
 * @var array<int,array<string,mixed>> $events
 * @var string                         $type
 * @var string                         $search
 * @var int                            $user_id
 * @var string                         $from
 * @var string                         $to
 * @var array<string,int>              $counts        total/plugin/theme/core/user, for the stat cards.
 * @var \WP_User[]                     $users         Everyone with at least one event, for the "By" filter.
 * @var int                            $total_matching
 * @var int                            $total_pages
 * @var int                            $paged
 * @var int                            $per_page
 */

defined( 'ABSPATH' ) || exit;

use WatchSpire\Admin\AdminMenu;

$type_meta = array(
	'plugin_update'     => array(
		'label' => __( 'Plugin Updated', 'watchspire' ),
		'color' => 'blue',
		'icon'  => 'update',
	),
	'plugin_install'    => array(
		'label' => __( 'Plugin Installed', 'watchspire' ),
		'color' => 'blue',
		'icon'  => 'download',
	),
	'plugin_activate'   => array(
		'label' => __( 'Plugin Activated', 'watchspire' ),
		'color' => 'green',
		'icon'  => 'yes-alt',
	),
	'plugin_deactivate' => array(
		'label' => __( 'Plugin Deactivated', 'watchspire' ),
		'color' => 'red',
		'icon'  => 'marker',
	),
	'plugin_delete'     => array(
		'label' => __( 'Plugin Deleted', 'watchspire' ),
		'color' => 'red',
		'icon'  => 'trash',
	),
	'theme_update'      => array(
		'label' => __( 'Theme Updated', 'watchspire' ),
		'color' => 'blue',
		'icon'  => 'admin-appearance',
	),
	'theme_install'     => array(
		'label' => __( 'Theme Installed', 'watchspire' ),
		'color' => 'blue',
		'icon'  => 'download',
	),
	'theme_switch'      => array(
		'label' => __( 'Theme Switched', 'watchspire' ),
		'color' => 'purple',
		'icon'  => 'admin-customizer',
	),
	'core_update'       => array(
		'label' => __( 'Core Updated', 'watchspire' ),
		'color' => 'amber',
		'icon'  => 'wordpress-alt',
	),
	'option_change'     => array(
		'label' => __( 'Settings Changed', 'watchspire' ),
		'color' => 'purple',
		'icon'  => 'admin-settings',
	),
	'user_login'        => array(
		'label' => __( 'User Login', 'watchspire' ),
		'color' => 'cyan',
		'icon'  => 'admin-users',
	),
	'user_logout'       => array(
		'label' => __( 'User Logout', 'watchspire' ),
		'color' => 'slate',
		'icon'  => 'migrate',
	),
);

$types = array( '' => __( 'All Types', 'watchspire' ) );
foreach ( $type_meta as $value => $meta ) {
	$types[ $value ] = $meta['label'];
}

$today = current_time( 'Y-m-d' );

$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'  => 'watchspire_export_changelog',
			'type'    => $type,
			'from'    => $from,
			'to'      => $to,
			'user_id' => $user_id,
			's'       => $search,
		),
		admin_url( 'admin-post.php' )
	),
	'watchspire_export_changelog'
);

$actions_html = sprintf(
	'<a href="%1$s" class="button"><span class="dashicons dashicons-download" aria-hidden="true"></span>%2$s</a>',
	esc_url( $export_url ),
	esc_html__( 'Export Log', 'watchspire' )
);

AdminMenu::render_page_header( __( 'Change Log', 'watchspire' ), __( 'Everything WatchSpire has seen change on this site — the foundation for spotting what broke something.', 'watchspire' ), $actions_html );
?>

<div class="wpdash">
	<div class="wpdash-stats" style="grid-template-columns:repeat(5,1fr);">
		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-indigo" aria-hidden="true"><span class="dashicons dashicons-list-view"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Total Events', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $counts['total'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php esc_html_e( 'All recorded events', 'watchspire' ); ?></p>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-blue" aria-hidden="true"><span class="dashicons dashicons-admin-plugins"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Plugin Events', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $counts['plugin'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php esc_html_e( 'Plugin related changes', 'watchspire' ); ?></p>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-green" aria-hidden="true"><span class="dashicons dashicons-admin-appearance"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Theme Events', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $counts['theme'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php esc_html_e( 'Theme related changes', 'watchspire' ); ?></p>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-amber" aria-hidden="true"><span class="dashicons dashicons-wordpress-alt"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'Core Events', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $counts['core'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php esc_html_e( 'WordPress core changes', 'watchspire' ); ?></p>
		</div>

		<div class="wpdash-stat">
			<div class="wpdash-stat-top">
				<span class="wpdash-stat-icon is-cyan" aria-hidden="true"><span class="dashicons dashicons-admin-users"></span></span>
				<span class="wpdash-stat-label"><?php esc_html_e( 'User Events', 'watchspire' ); ?></span>
			</div>
			<div class="wpdash-stat-body">
				<span class="wpdash-stat-value"><?php echo esc_html( number_format_i18n( $counts['user'] ) ); ?></span>
			</div>
			<p class="wpdash-stat-caption"><?php esc_html_e( 'User related activities', 'watchspire' ); ?></p>
		</div>
	</div>

	<div class="wpdash-filterbar">
		<form method="get">
			<input type="hidden" name="page" value="watchspire-changelog" />
			<div class="wpdash-filterbar-row">
				<div class="wpdash-filterbar-field">
					<label for="wp-cl-type"><?php esc_html_e( 'Type', 'watchspire' ); ?></label>
					<select name="type" id="wp-cl-type">
						<?php foreach ( $types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpdash-filterbar-field">
					<label for="wp-cl-from"><?php esc_html_e( 'Date Range', 'watchspire' ); ?></label>
					<div class="wpdash-filterbar-daterange">
						<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
						<label for="wp-cl-from" class="screen-reader-text"><?php esc_html_e( 'From', 'watchspire' ); ?></label>
						<input type="date" name="from" id="wp-cl-from" value="<?php echo esc_attr( $from ); ?>" max="<?php echo esc_attr( $today ); ?>" />
						<span class="sep" aria-hidden="true">–</span>
						<label for="wp-cl-to" class="screen-reader-text"><?php esc_html_e( 'To', 'watchspire' ); ?></label>
						<input type="date" name="to" id="wp-cl-to" value="<?php echo esc_attr( $to ); ?>" max="<?php echo esc_attr( $today ); ?>" />
					</div>
				</div>

				<div class="wpdash-filterbar-field">
					<label for="wp-cl-user"><?php esc_html_e( 'User', 'watchspire' ); ?></label>
					<select name="user_id" id="wp-cl-user">
						<option value="0"><?php esc_html_e( 'All Users', 'watchspire' ); ?></option>
						<?php foreach ( $users as $log_user ) : ?>
							<option value="<?php echo esc_attr( (string) $log_user->ID ); ?>" <?php selected( $user_id, $log_user->ID ); ?>><?php echo esc_html( $log_user->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpdash-filterbar-field wpdash-filterbar-search">
					<label for="wp-cl-search" class="screen-reader-text"><?php esc_html_e( 'Search changes', 'watchspire' ); ?></label>
					<input type="search" name="s" id="wp-cl-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search changes…', 'watchspire' ); ?>" />
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
				</div>
			</div>

			<div class="wpdash-filterbar-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=watchspire-changelog' ) ); ?>" class="wpdash-toolbtn">
					<span class="dashicons dashicons-image-rotate" aria-hidden="true"></span><?php esc_html_e( 'Reset Filters', 'watchspire' ); ?>
				</a>
				<button type="submit" class="wpdash-toolbtn wpdash-toolbtn--primary">
					<span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e( 'Apply Filters', 'watchspire' ); ?>
				</button>
			</div>
		</form>
	</div>

	<div class="watchspire-table-wrap">
		<?php if ( empty( $events ) ) : ?>
			<div class="watchspire-empty">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<?php esc_html_e( 'No changes recorded for this filter.', 'watchspire' ); ?>
			</div>
		<?php else : ?>
			<table class="watchspire-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'watchspire' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'watchspire' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Item', 'watchspire' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Change', 'watchspire' ); ?></th>
						<th scope="col"><?php esc_html_e( 'By', 'watchspire' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'watchspire' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
						<?php
						$log_user = $event['user_id'] ? get_userdata( $event['user_id'] ) : null;
						$meta     = $type_meta[ $event['type'] ] ?? array(
							'label' => ucwords( str_replace( '_', ' ', $event['type'] ) ),
							'color' => 'slate',
							'icon'  => 'marker',
						);

						$is_login_type = 0 === strpos( $event['type'], 'user_' );
						if ( $is_login_type ) {
							$source_label = __( 'Login', 'watchspire' );
							$source_icon  = 'admin-users';
						} elseif ( $event['is_auto_update'] ) {
							$source_label = __( 'WP-Cron', 'watchspire' );
							$source_icon  = 'clock';
						} else {
							$source_label = __( 'Dashboard', 'watchspire' );
							// Dashicon handle (dashicons-wordpress), not prose —
							// it must stay lowercase or the icon won't resolve.
							$source_icon = 'wordpress'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
						}
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'M j, Y g:i A', $event['created_at'] ) ); ?></td>
							<td>
								<span class="wpdash-type-badge is-<?php echo esc_attr( $meta['color'] ); ?>">
									<span class="dashicons dashicons-<?php echo esc_attr( $meta['icon'] ); ?>" aria-hidden="true"></span>
									<?php echo esc_html( $meta['label'] ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $event['object_name'] ? $event['object_name'] : $event['object_slug'] ); ?></td>
							<td>
								<?php if ( $event['from_version'] || $event['to_version'] ) : ?>
									<code><?php echo esc_html( $event['from_version'] . ' → ' . $event['to_version'] ); ?></code>
								<?php endif; ?>
								<?php if ( $event['is_auto_update'] ) : ?>
									<span class="watchspire-tag"><?php esc_html_e( 'auto', 'watchspire' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $log_user ) : ?>
									<span class="wpdash-by-user">
										<?php echo get_avatar( $log_user->ID, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php echo esc_html( $log_user->display_name ); ?>
									</span>
								<?php else : ?>
									<span class="wpdash-by-user--system">—</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="wpdash-source">
									<span class="dashicons dashicons-<?php echo esc_attr( $source_icon ); ?>" aria-hidden="true"></span>
									<?php echo esc_html( $source_label ); ?>
								</span>
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
					esc_html__( 'Showing %1$s to %2$s of %3$s changes', 'watchspire' ),
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
