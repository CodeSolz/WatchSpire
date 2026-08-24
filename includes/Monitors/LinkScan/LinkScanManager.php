<?php
/**
 * Orchestrates the broken link & image scan: extraction and checking,
 * both batched and resumable via Action Scheduler.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\LinkScan;

use WatchSpire\Database\Repositories\LinksRepository;
use WatchSpire\Scheduler\Scheduler;
use WatchSpire\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class LinkScanManager {

	private const STATE_OPTION      = 'watchspire_link_scan_state';
	private const POSTS_PER_EXTRACT = 50;
	private const DEFAULT_BATCH     = 20;
	private const SHARED_BATCH      = 10;
	private const BATCH_DELAY       = 3;

	private LinksRepository $repo;
	private LinkExtractor $extractor;
	private LinkChecker $checker;

	public function __construct() {
		$this->repo      = new LinksRepository();
		$this->extractor = new LinkExtractor();
		$this->checker   = new LinkChecker();
	}

	public function boot(): void {
		add_action( Scheduler::HOOK_SCAN_EXTRACT, array( $this, 'run_extract_batch' ) );
		add_action( Scheduler::HOOK_SCAN_BATCH, array( $this, 'run_check_batch' ) );
	}

	public function get_state(): array {
		$default = array(
			'status'       => 'idle',
			'scan_id'      => '',
			'extract_page' => 0,
			'started_at'   => 0,
			'finished_at'  => 0,
			'total'        => 0,
			'checked'      => 0,
			'broken'       => 0,
			'prev_counts'  => null,
		);

		$state = get_option( self::STATE_OPTION, array() );

		return array_merge( $default, is_array( $state ) ? $state : array() );
	}

	private function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	public function start_scan(): bool {
		if ( $this->is_disabled() ) {
			return false;
		}

		$state = $this->get_state();
		if ( in_array( $state['status'], array( 'extracting', 'checking' ), true ) ) {
			return false; // Already running.
		}

		$scan_id = substr( md5( uniqid( 'watchspire', true ) ), 0, 20 );

		$this->save_state(
			array(
				'status'       => 'extracting',
				'scan_id'      => $scan_id,
				'extract_page' => 1,
				'started_at'   => time(),
				'finished_at'  => 0,
				'total'        => 0,
				'checked'      => 0,
				'broken'       => 0,
				'prev_counts'  => $this->repo->counts(),
			)
		);

		Scheduler::schedule_single( Scheduler::HOOK_SCAN_EXTRACT, 0 );

		return true;
	}

	public function pause_scan(): void {
		$state = $this->get_state();
		if ( in_array( $state['status'], array( 'extracting', 'checking' ), true ) ) {
			$state['status'] = 'paused';
			$this->save_state( $state );
		}
	}

	public function resume_scan(): void {
		$state = $this->get_state();
		if ( 'paused' !== $state['status'] ) {
			return;
		}

		$state['status'] = $state['extract_page'] > 0 && $this->still_extracting( $state ) ? 'extracting' : 'checking';
		$this->save_state( $state );

		if ( 'extracting' === $state['status'] ) {
			Scheduler::schedule_single( Scheduler::HOOK_SCAN_EXTRACT, 0 );
		} else {
			Scheduler::schedule_single( Scheduler::HOOK_SCAN_BATCH, 0 );
		}
	}

	public function cancel_scan(): void {
		$state                = $this->get_state();
		$state['status']      = 'idle';
		$state['finished_at'] = time();
		$this->save_state( $state );
	}

	private function still_extracting( array $state ): bool {
		$query = new \WP_Query(
			array(
				'post_type'      => $this->scannable_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'paged'          => $state['extract_page'],
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);

		return $query->max_num_pages >= $state['extract_page'];
	}

	public function run_extract_batch(): void {
		if ( $this->is_disabled() ) {
			return;
		}

		$state = $this->get_state();
		if ( 'extracting' !== $state['status'] ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $this->scannable_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => self::POSTS_PER_EXTRACT,
				'paged'          => $state['extract_page'],
				'no_found_rows'  => false,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		foreach ( $query->posts as $post ) {
			foreach ( $this->extractor->extract_from_post( $post ) as $link ) {
				$this->repo->queue( $link['url'], $link['type'], $post->ID, get_the_title( $post ), $post->post_type, $link['anchor_text'], $state['scan_id'] );
			}
		}

		if ( 1 === $state['extract_page'] ) {
			foreach ( $this->extractor->extract_from_menus() as $link ) {
				$this->repo->queue( $link['url'], $link['type'], $link['source_post_id'], $link['source_title'], 'nav_menu', $link['anchor_text'], $state['scan_id'] );
			}
		}

		$has_more = $state['extract_page'] < (int) $query->max_num_pages;

		if ( $has_more ) {
			++$state['extract_page'];
			$this->save_state( $state );
			Scheduler::schedule_single( Scheduler::HOOK_SCAN_EXTRACT, self::BATCH_DELAY );
			return;
		}

		$state['status'] = 'checking';
		$state['total']  = $this->repo->total_for_scan( $state['scan_id'] );
		$this->save_state( $state );

		Scheduler::schedule_single( Scheduler::HOOK_SCAN_BATCH, 0 );
	}

	/**
	 * @param ?int $limit Overrides the configured batch size. Used by the
	 *                    poll endpoint to process only a couple of URLs per
	 *                    request — the background cron/Action Scheduler
	 *                    path always calls this with no argument and gets
	 *                    the full configured batch.
	 */
	public function run_check_batch( ?int $limit = null ): void {
		if ( $this->is_disabled() ) {
			return;
		}

		$state = $this->get_state();
		if ( 'checking' !== $state['status'] ) {
			return;
		}

		$batch_size = $limit ?? $this->batch_size();
		$batch      = $this->repo->next_batch( $state['scan_id'], $batch_size );

		if ( empty( $batch ) ) {
			$counts               = $this->repo->counts_for_scan( $state['scan_id'] );
			$state['status']      = 'completed';
			$state['finished_at'] = time();
			$state['broken']      = $counts['broken'];
			$this->save_state( $state );
			do_action( 'watchspire_link_scan_completed', $state );
			return;
		}

		foreach ( $batch as $row ) {
			$result = $this->checker->check( $row['url'] );
			$this->repo->mark_result( (int) $row['id'], $result['status'], $result['http_code'], $result['error_reason'] ?? null );
			++$state['checked'];
			if ( 'broken' === $result['status'] ) {
				++$state['broken'];
			}
		}

		$this->save_state( $state );

		if ( $this->repo->pending_count( $state['scan_id'] ) > 0 ) {
			Scheduler::schedule_single( Scheduler::HOOK_SCAN_BATCH, self::BATCH_DELAY );
		} else {
			$state['status']      = 'completed';
			$state['finished_at'] = time();
			$this->save_state( $state );
			do_action( 'watchspire_link_scan_completed', $state );
		}
	}

	private function batch_size(): int {
		$configured = (int) Settings::get( 'link_scan_batch_size', self::DEFAULT_BATCH );

		if ( $this->is_shared_hosting() ) {
			return min( $configured, self::SHARED_BATCH );
		}

		return max( 1, $configured );
	}

	private function is_shared_hosting(): bool {
		$memory_limit  = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$max_execution = (int) ini_get( 'max_execution_time' );

		if ( $memory_limit > 0 && $memory_limit < 128 * MB_IN_BYTES ) {
			return true;
		}

		if ( $max_execution > 0 && $max_execution < 30 ) {
			return true;
		}

		return false;
	}

	private function is_disabled(): bool {
		return defined( 'WATCHSPIRE_DISABLE_SCANS' ) && WATCHSPIRE_DISABLE_SCANS;
	}

	/**
	 * @return string[]
	 */
	private function scannable_post_types(): array {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );

		return apply_filters( 'watchspire_link_scan_post_types', array_values( $types ) );
	}
}
