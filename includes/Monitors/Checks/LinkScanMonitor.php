<?php
/**
 * Reports broken-link scan status and kicks off a new scan when due.
 * The actual scan runs asynchronously via LinkScanManager/Action Scheduler.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\Checks;

use WatchSpire\Database\Repositories\LinksRepository;
use WatchSpire\Monitors\AbstractMonitor;
use WatchSpire\Monitors\LinkScan\LinkScanManager;
use WatchSpire\Monitors\Result;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class LinkScanMonitor extends AbstractMonitor {

	public function get_id(): string {
		return 'link_scan';
	}

	public function get_label(): string {
		return __( 'Broken links & images', 'watchspire' );
	}

	public function get_description(): string {
		return __( 'Off by default. When enabled, periodically scans your content for broken links and images in resumable batches.', 'watchspire' );
	}

	public function get_default_schedule(): int {
		return WEEK_IN_SECONDS;
	}

	public function is_available(): bool {
		return (bool) Settings::get( 'link_scan_opt_in', false );
	}

	public function run(): Result {
		$manager = new LinkScanManager();
		$state   = $manager->get_state();

		if ( in_array( $state['status'], array( 'extracting', 'checking' ), true ) ) {
			return Result::pass(
				sprintf(
					/* translators: 1: checked count, 2: total count, 3: broken count */
					__( 'Scan in progress: %1$d of %2$d items checked, %3$d broken so far.', 'watchspire' ),
					$state['checked'],
					max( $state['total'], $state['checked'] ),
					$state['broken']
				),
				$state
			);
		}

		if ( 'paused' === $state['status'] ) {
			return Result::warn( __( 'Link scan is paused.', 'watchspire' ), $state );
		}

		$previous_broken = $state['broken'];
		$was_completed   = 'completed' === $state['status'];

		$manager->start_scan();

		if ( ! $was_completed ) {
			return Result::pass( __( 'Link scanning is enabled; the first scan is starting.', 'watchspire' ), $state );
		}

		$repo   = new LinksRepository();
		$broken = $repo->counts_for_scan( $state['scan_id'] );

		$threshold = max( 1, (int) Settings::get( 'link_scan_broken_threshold', 5 ) );

		if ( $previous_broken > $threshold ) {
			return Result::fail(
				sprintf(
					/* translators: 1: number of broken links/images, 2: configured alert threshold */
					__( 'Last scan found %1$d broken link(s) or image(s), above your alert threshold of %2$d.', 'watchspire' ),
					$previous_broken,
					$threshold
				),
				array_merge( $state, array( 'counts' => $broken ) )
			);
		}

		if ( $previous_broken > 0 ) {
			return Result::warn(
				sprintf(
					/* translators: %d: number of broken links/images */
					__( 'Last scan found %d broken link(s) or image(s).', 'watchspire' ),
					$previous_broken
				),
				array_merge( $state, array( 'counts' => $broken ) )
			);
		}

		return Result::pass( __( 'No broken links or images found in the last scan.', 'watchspire' ), array_merge( $state, array( 'counts' => $broken ) ) );
	}
}
