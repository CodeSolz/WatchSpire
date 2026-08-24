<?php
/**
 * Tiny dependency-free inline SVG chart helpers for the admin UI.
 * No charting library — WP core ships none, and the architecture forbids
 * pulling in external front-end frameworks.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Admin;

defined( 'ABSPATH' ) || exit;

final class Charts {

	private const PRIMARY      = '#4f46e5';
	private const PRIMARY_SOFT = '#a5b4fc';
	private const GRID         = '#eef0f6';
	private const AXIS_TEXT    = '#9ca3af';
	private const GRADIENT_ID  = 'watchspire-area-fill';

	/**
	 * @param array<int,float|int> $values
	 */
	public static function sparkline( array $values, int $width = 320, int $height = 60, string $color = self::PRIMARY ): string {
		$count = count( $values );

		if ( 0 === $count ) {
			return '';
		}

		$max  = max( 1, max( $values ) );
		$step = $count > 1 ? $width / ( $count - 1 ) : 0;

		$points = array();
		foreach ( array_values( $values ) as $i => $value ) {
			$x        = round( $i * $step, 1 );
			$y        = round( $height - ( ( $value / $max ) * ( $height - 6 ) ) - 3, 1 );
			$points[] = "{$x},{$y}";
		}

		$line_points             = implode( ' ', $points );
		$area_points             = "0,{$height} {$line_points} {$width},{$height}";
		$last                    = end( $points );
		list( $last_x, $last_y ) = array_map( 'floatval', explode( ',', $last ) );
		$gid                     = self::GRADIENT_ID . '-' . substr( md5( $line_points ), 0, 6 );

		return sprintf(
			'<svg class="watchspire-sparkline" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" preserveAspectRatio="none" role="img" aria-label="%3$s">
				<defs><linearGradient id="%9$s" x1="0" y1="0" x2="0" y2="1">
					<stop offset="0%%" stop-color="%4$s" stop-opacity="0.25" />
					<stop offset="100%%" stop-color="%4$s" stop-opacity="0" />
				</linearGradient></defs>
				<polygon points="%5$s" fill="url(#%9$s)" />
				<polyline fill="none" stroke="%4$s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="%6$s" />
				<circle cx="%7$s" cy="%8$s" r="3" fill="%4$s" />
			</svg>',
			$width,
			$height,
			esc_attr__( 'Trend chart', 'watchspire' ),
			esc_attr( $color ),
			esc_attr( $area_points ),
			esc_attr( $line_points ),
			esc_attr( (string) $last_x ),
			esc_attr( (string) $last_y ),
			esc_attr( $gid )
		);
	}

	/**
	 * @param array<string,float|int> $data label => value
	 */
	public static function bar_chart( array $data, int $width = 320, int $height = 150, string $color = self::PRIMARY ): string {
		$count = count( $data );

		if ( 0 === $count ) {
			return '<p class="description">' . esc_html__( 'No data yet.', 'watchspire' ) . '</p>';
		}

		// Round the scale up to a multiple of 4 so the four gridlines land on
		// whole numbers instead of values like 1.75 / 3.5.
		$max = max( 1, max( $data ) );
		$max = max( 4, (int) ceil( $max / 4 ) * 4 );

		$margin_left = 34; // room for the y-axis values
		$margin_top  = 10; // keeps the topmost value label inside the viewBox
		$label_h     = 18;
		$plot_w      = $width - $margin_left;
		$plot_h      = $height - $label_h - $margin_top;
		$bar_w       = $plot_w / $count;
		$bars        = '';
		$grid        = '';
		$i           = 0;
		$gid         = self::GRADIENT_ID . '-bar-' . substr( md5( wp_json_encode( $data ) ), 0, 6 );

		for ( $g = 0; $g <= 4; $g++ ) {
			$gy    = round( $plot_h - ( $plot_h / 4 ) * $g, 1 );
			$gv    = (int) round( ( $max / 4 ) * $g );
			$grid .= sprintf(
				'<line x1="0" y1="%1$s" x2="%2$s" y2="%1$s" stroke="%4$s" stroke-width="1" /><text x="-8" y="%1$s" text-anchor="end" dominant-baseline="middle" font-size="9" fill="%5$s">%3$d</text>',
				$gy,
				round( $plot_w, 1 ),
				$gv,
				esc_attr( self::GRID ),
				esc_attr( self::AXIS_TEXT )
			);
		}

		// Thin the x-axis labels to what actually fits. A date like "Aug 12"
		// needs roughly 34 viewBox units at this font size, so with more bars
		// than that allows every label would be drawn on top of its
		// neighbours — which is what a month of daily data used to look like.
		$label_w     = 34;
		$label_every = max( 1, (int) ceil( $label_w / max( 1, $bar_w ) ) );
		$label_at    = array();

		for ( $k = 0; $k < $count; $k += $label_every ) {
			$label_at[ $k ] = true;
		}

		// Always label the final bar — it's the one people look for — but drop
		// the preceding tick when the leftover gap is narrower than a label,
		// which is exactly where the two would otherwise collide.
		if ( $count > 1 ) {
			$last = $count - 1;

			if ( ! isset( $label_at[ $last ] ) ) {
				$prev = $last - ( $last % $label_every );

				if ( ( ( $last - $prev ) * $bar_w ) < $label_w ) {
					unset( $label_at[ $prev ] );
				}

				$label_at[ $last ] = true;
			}
		}

		foreach ( $data as $label => $value ) {
			$bar_h       = $value > 0 ? max( 3, ( $value / $max ) * ( $plot_h - 10 ) ) : 0;
			$x           = round( $i * $bar_w + 3, 1 );
			$y           = round( $plot_h - $bar_h, 1 );
			$w           = max( 1, round( $bar_w - 6, 1 ) );
			$lx          = round( $i * $bar_w + ( $bar_w / 2 ), 1 );
			$short_label = mb_strlen( $label ) > 10 ? mb_substr( $label, 0, 9 ) . '…' : $label;

			$tick = '';

			if ( isset( $label_at[ $i ] ) ) {
				// A centred label on the first/last bar hangs half outside the
				// viewBox and gets clipped, so anchor the edge ones inward.
				$half   = $label_w / 2;
				$anchor = 'middle';
				$tx     = $lx;

				if ( $lx - $half < 0 ) {
					$anchor = 'start';
					$tx     = 0;
				} elseif ( $lx + $half > $plot_w ) {
					$anchor = 'end';
					$tx     = round( $plot_w, 1 );
				}

				$tick = sprintf(
					'<text x="%1$s" y="%2$s" text-anchor="%3$s" font-size="9.5" fill="%4$s">%5$s</text>',
					$tx,
					$plot_h + 13,
					esc_attr( $anchor ),
					esc_attr( self::AXIS_TEXT ),
					esc_html( $short_label )
				);
			}

			// Value bubble shown on hover. Kept inside the SVG and toggled by
			// CSS so the chart needs no JavaScript; clamped to the plot so it
			// can't hang off either end or above the top edge.
			$tip_label = $label . ': ' . $value;
			$tip_w     = max( 30, ( mb_strlen( $tip_label ) * 5.6 ) + 14 );
			$tip_x     = min( max( 0, $lx - ( $tip_w / 2 ) ), max( 0, $plot_w - $tip_w ) );
			$tip_y     = max( 0, $y - 26 );

			// No <title> here on purpose: it would trigger the browser's own
			// tooltip a second after the styled one above already appeared,
			// showing the same text twice. The rendered bubble is the label.
			$bars .= sprintf(
				'<g class="watchspire-bar-group" aria-label="%1$s: %2$s">
					<rect class="watchspire-bar-hit" x="%9$s" y="0" width="%10$s" height="%11$s" fill="transparent" />
					<rect class="watchspire-bar-rect" x="%3$s" y="%4$s" width="%5$s" height="%6$s" fill="url(#%7$s)" rx="4" />
					%8$s
					<g class="watchspire-bar-tip">
						<rect x="%12$s" y="%13$s" width="%14$s" height="20" rx="5" fill="#0f172a" />
						<text x="%15$s" y="%16$s" text-anchor="middle" font-size="10" font-weight="700" fill="#ffffff">%17$s</text>
					</g>
				</g>',
				esc_attr( $label ),
				esc_attr( (string) $value ),
				$x,
				$y,
				$w,
				round( $bar_h, 1 ),
				esc_attr( $gid ),
				$tick, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr()/esc_html() values just above.
				round( $i * $bar_w, 1 ),
				round( $bar_w, 1 ),
				round( $plot_h, 1 ),
				round( $tip_x, 1 ),
				round( $tip_y, 1 ),
				round( $tip_w, 1 ),
				round( $tip_x + ( $tip_w / 2 ), 1 ),
				round( $tip_y + 13.5, 1 ),
				esc_html( $tip_label )
			);

			++$i;
		}

		return sprintf(
			'<svg class="watchspire-bar-chart" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" role="img" aria-label="%3$s">
				<defs><linearGradient id="%7$s" x1="0" y1="0" x2="0" y2="1">
					<stop offset="0%%" stop-color="%4$s" />
					<stop offset="100%%" stop-color="%8$s" />
				</linearGradient></defs>
				<g transform="translate(%9$d,%10$d)">%5$s%6$s</g>
			</svg>',
			$width,
			$height,
			esc_attr__( 'Bar chart', 'watchspire' ),
			esc_attr( $color ),
			$grid,
			$bars,
			esc_attr( $gid ),
			esc_attr( self::PRIMARY_SOFT ),
			$margin_left,
			$margin_top
		);
	}

	public static function status_badge( string $status ): string {
		$map = array(
			'pass' => array(
				'label' => __( 'Pass', 'watchspire' ),
				'class' => 'is-pass',
				'icon'  => 'yes-alt',
			),
			'warn' => array(
				'label' => __( 'Warning', 'watchspire' ),
				'class' => 'is-warn',
				'icon'  => 'warning',
			),
			'fail' => array(
				'label' => __( 'Failing', 'watchspire' ),
				'class' => 'is-fail',
				'icon'  => 'dismiss',
			),
		);

		$info = $map[ $status ] ?? array(
			'label' => __( 'Unknown', 'watchspire' ),
			'class' => 'is-unknown',
			'icon'  => 'editor-help',
		);

		return sprintf(
			'<span class="watchspire-badge %1$s"><span class="dashicons dashicons-%2$s" aria-hidden="true"></span>%3$s</span>',
			esc_attr( $info['class'] ),
			esc_attr( $info['icon'] ),
			esc_html( $info['label'] )
		);
	}

	/**
	 * Open-bottom arc gauge (0-100), speedometer style. Uses the
	 * pathLength trick so both strokes' dasharrays are expressible
	 * directly in percentage points — no circumference math needed.
	 *
	 * The arc spans SWEEP percent of the circle, leaving the remainder as
	 * a gap. A <circle> path starts at 3 o'clock and runs clockwise, so
	 * .watchspire-gauge--arc rotates the SVG to centre that gap at the
	 * bottom (see admin.css).
	 *
	 * @param int|null $score Null renders an empty/unknown arc.
	 * @param string   $size  xl|lg|md|sm — matches .watchspire-gauge--{size} in admin.css.
	 */
	public static function gauge( ?int $score, string $size = 'lg', bool $on_dark = false ): string {
		$radius      = 42;
		$sweep       = 75; // 75% of the circle = a 270° arc, 90° gap at the bottom.
		$track_color = $on_dark ? 'rgba(255,255,255,.08)' : '#f1f5f9';
		$has_score   = null !== $score;
		$value       = $has_score ? max( 0, min( 100, $score ) ) : 0;
		$filled      = round( ( $value / 100 ) * $sweep, 2 );

		// Health ramp: green at 100 down through lime and amber to red at
		// 0. Both the arc stroke and the score number take this color, so
		// the gauge reads as good/bad at a glance without parsing the
		// digits. Steps align with the tier labels below so the color and
		// the wording can never disagree.
		if ( $value >= 90 ) {
			$color = '#10b981';
			$tier  = __( 'Excellent', 'watchspire' );
		} elseif ( $value >= 70 ) {
			$color = '#84cc16';
			$tier  = __( 'Good', 'watchspire' );
		} elseif ( $value >= 40 ) {
			$color = '#f59e0b';
			$tier  = __( 'Needs attention', 'watchspire' );
		} else {
			$color = '#ef4444';
			$tier  = __( 'Critical', 'watchspire' );
		}

		$num_html = $has_score
			? sprintf( '<span class="watchspire-gauge-num" style="color:%s;">%d</span>', esc_attr( $on_dark ? '#fff' : $color ), $value )
			: '<span class="watchspire-gauge-num" style="color:#cbd5e1;">–</span>';

		$tier_html = $has_score ? sprintf( '<span class="watchspire-gauge-tier">%s</span>', esc_html( $tier ) ) : '';

		return sprintf(
			'<span class="watchspire-gauge watchspire-gauge--arc watchspire-gauge--%1$s">
				<svg class="watchspire-gauge-svg" viewBox="0 0 100 100" role="img" aria-label="%2$s">
					<circle class="watchspire-gauge-track" cx="50" cy="50" r="%3$d" stroke="%4$s" pathLength="100" stroke-dasharray="%9$s 100" />
					<circle class="watchspire-gauge-fill" cx="50" cy="50" r="%3$d" stroke="%5$s" pathLength="100" stroke-dasharray="%6$s 100" />
				</svg>
				<span class="watchspire-gauge-center">%7$s%8$s</span>
			</span>',
			esc_attr( $size ),
			esc_attr(
				sprintf(
					/* translators: %d: score out of 100 */
					$has_score ? __( 'Health score: %d out of 100', 'watchspire' ) : __( 'Health score not yet available', 'watchspire' ),
					$value
				)
			),
			$radius,
			esc_attr( $track_color ),
			esc_attr( $color ),
			esc_attr( (string) $filled ),
			$num_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$tier_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( (string) $sweep )
		);
	}

	/**
	 * Donut chart. Uses the same pathLength trick as gauge() — each
	 * segment's dasharray/dashoffset is expressed directly in
	 * percentage points, no circumference math needed.
	 *
	 * @param array<int,array{label:string,value:int|float,color:string}> $segments
	 */
	public static function donut( array $segments, int $size = 140, string $center_label = '', int $stroke = 11 ): string {
		$total = array_sum( array_column( $segments, 'value' ) );

		// Keep the ring inside the 100x100 viewBox: the stroke straddles the
		// radius, so a thick ring has to pull its radius in or the outer edge
		// clips. Caps at the historic 40 so the default stays pixel-identical.
		$radius = (int) min( 40, 50 - ( $stroke / 2 ) - 1 );

		if ( $total <= 0 ) {
			$circles = sprintf(
				'<circle cx="50" cy="50" r="%d" fill="none" stroke="%s" stroke-width="%d" />',
				$radius,
				esc_attr( self::GRID ),
				$stroke
			);
		} else {
			$circles = '';
			$offset  = 0.0;

			foreach ( $segments as $segment ) {
				$value = (float) $segment['value'];

				if ( $value <= 0 ) {
					continue;
				}

				$pct = ( $value / $total ) * 100;

				$circles .= sprintf(
					'<circle cx="50" cy="50" r="%1$d" fill="none" stroke="%2$s" stroke-width="%3$d" pathLength="100" stroke-dasharray="%4$s %5$s" stroke-dashoffset="%6$s" />',
					$radius,
					esc_attr( $segment['color'] ),
					$stroke,
					esc_attr( (string) round( $pct, 2 ) ),
					esc_attr( (string) round( 100 - $pct, 2 ) ),
					esc_attr( (string) round( -$offset, 2 ) )
				);

				$offset += $pct;
			}
		}

		$center_num   = sprintf( '<text x="50" y="47" text-anchor="middle" transform="rotate(90 50 50)" class="wpdash-donut-center-num">%d</text>', (int) $total );
		$center_label = $center_label
			? sprintf( '<text x="50" y="60" text-anchor="middle" transform="rotate(90 50 50)" class="wpdash-donut-center-label">%s</text>', esc_html( $center_label ) )
			: '';

		return sprintf(
			'<svg class="wpdash-donut-svg" width="%1$d" height="%1$d" viewBox="0 0 100 100" role="img" aria-label="%2$s">%3$s%4$s%5$s</svg>',
			$size,
			esc_attr__( 'Monitoring overview', 'watchspire' ),
			$circles, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$center_num, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$center_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Multi-series area/line chart sharing one Y-scale — used for
	 * "checks over time" (passed/warnings/failures).
	 *
	 * @param array<string,array<string,int|float>> $series_by_date Date (Y-m-d) => [line key => value].
	 * @param array<string,array{color:string,label:string}>        $lines          Line key => color/label, drawn in order.
	 */
	public static function area_multi( array $series_by_date, array $lines, int $width = 640, int $height = 200 ): string {
		$dates = array_keys( $series_by_date );
		$count = count( $dates );

		if ( 0 === $count ) {
			return '<p class="description">' . esc_html__( 'No data yet.', 'watchspire' ) . '</p>';
		}

		$margin_left  = 26;
		$margin_right = 24;
		$label_h      = 20;

		// The top gridline sits at y=0 of the plot and its value label is
		// centred on it, so without a top margin the label's upper half falls
		// outside the viewBox and renders clipped.
		$margin_top = 10;
		$plot_w     = $width - $margin_left - $margin_right;
		$plot_h     = $height - $label_h - $margin_top;

		$max = 1;
		foreach ( $series_by_date as $row ) {
			foreach ( $lines as $key => $meta ) {
				$max = max( $max, (float) ( $row[ $key ] ?? 0 ) );
			}
		}
		$max = max( 4, (int) ceil( $max / 4 ) * 4 );

		$step = $count > 1 ? $plot_w / ( $count - 1 ) : 0;

		$grid = '';
		for ( $g = 0; $g <= 4; $g++ ) {
			$gy = round( $plot_h - ( $plot_h / 4 ) * $g, 1 );
			$gv = (int) round( ( $max / 4 ) * $g );

			$grid .= sprintf(
				'<line x1="0" y1="%1$s" x2="%2$s" y2="%1$s" stroke="%4$s" stroke-width="1" /><text x="-8" y="%1$s" text-anchor="end" dominant-baseline="middle" font-size="9" fill="%5$s">%3$d</text>',
				$gy,
				$plot_w,
				$gv,
				esc_attr( self::GRID ),
				esc_attr( self::AXIS_TEXT )
			);
		}

		$label_every = max( 1, (int) ceil( $count / 7 ) );
		$x_labels    = '';

		foreach ( $dates as $i => $date ) {
			if ( 0 !== $i % $label_every && $i !== $count - 1 ) {
				continue;
			}

			$lx        = round( $i * $step, 1 );
			$x_labels .= sprintf(
				'<text x="%1$s" y="%2$d" text-anchor="middle" font-size="9" fill="%4$s">%3$s</text>',
				$lx,
				$plot_h + 14,
				esc_html( gmdate( 'M j', strtotime( $date ) ) ),
				esc_attr( self::AXIS_TEXT )
			);
		}

		$show_markers = $count <= 14;

		$paths = '';
		foreach ( $lines as $key => $meta ) {
			$points  = array();
			$markers = '';

			foreach ( array_values( $series_by_date ) as $i => $row ) {
				$value    = (float) ( $row[ $key ] ?? 0 );
				$x        = round( $i * $step, 1 );
				$y        = round( $plot_h - ( $value / $max ) * ( $plot_h - 6 ), 1 );
				$points[] = "{$x},{$y}";

				if ( $show_markers ) {
					$markers .= sprintf(
						'<circle cx="%1$s" cy="%2$s" r="2.5" fill="%3$s" />',
						$x,
						$y,
						esc_attr( $meta['color'] )
					);
				}
			}

			$line_points = implode( ' ', $points );
			$area_points = "0,{$plot_h} {$line_points} {$plot_w},{$plot_h}";
			$gid         = 'wpdash-area-' . $key . '-' . substr( md5( $line_points ), 0, 6 );

			$paths .= sprintf(
				'<defs><linearGradient id="%1$s" x1="0" y1="0" x2="0" y2="1"><stop offset="0%%" stop-color="%2$s" stop-opacity="0.28" /><stop offset="100%%" stop-color="%2$s" stop-opacity="0" /></linearGradient></defs>
				<polygon points="%3$s" fill="url(#%1$s)" />
				<polyline points="%4$s" fill="none" stroke="%2$s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
				%5$s',
				esc_attr( $gid ),
				esc_attr( $meta['color'] ),
				esc_attr( $area_points ),
				esc_attr( $line_points ),
				$markers // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_attr()'d values above.
			);
		}

		return sprintf(
			'<svg class="wpdash-area-svg" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" role="img" aria-label="%3$s">
				<g transform="translate(%6$d,%8$d)">%4$s%5$s%7$s</g>
			</svg>',
			$width,
			$height,
			esc_attr__( 'Checks over time', 'watchspire' ),
			$grid, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$paths, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$margin_left,
			$x_labels, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$margin_top
		);
	}
}
