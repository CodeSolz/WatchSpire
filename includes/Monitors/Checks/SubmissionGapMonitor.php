<?php
/**
 * Flags forms that have gone statistically-unusually quiet, using a
 * rolling per-form baseline. Never alerts without an established baseline.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors\Checks;

use WatchSpire\Database\Repositories\SubmissionsRepository;
use WatchSpire\Monitors\AbstractMonitor;
use WatchSpire\Monitors\Result;

defined( 'ABSPATH' ) || exit;

final class SubmissionGapMonitor extends AbstractMonitor {

	private const MIN_TOTAL_SUBMISSIONS = 10;
	private const MIN_DAYS_WITH_DATA    = 14;
	private const BASELINE_WINDOW_DAYS  = 30;

	/**
	 * Flag silence once the expected submission count for the elapsed
	 * silence period would be ~6 (Poisson P(zero) ≈ 0.25%), i.e. a very
	 * unlikely silence given the form's own historical rate.
	 */
	private const EXPECTED_COUNT_THRESHOLD = 6.0;

	private SubmissionsRepository $repo;

	public function __construct() {
		$this->repo = new SubmissionsRepository();
	}

	public function get_id(): string {
		return 'submission_gap';
	}

	public function get_label(): string {
		return __( 'Submission gaps', 'watchspire' );
	}

	public function get_description(): string {
		return __( 'Learns each form\'s normal submission rate and alerts only when silence is statistically unusual for that specific form.', 'watchspire' );
	}

	public function get_default_schedule(): int {
		return DAY_IN_SECONDS;
	}

	public function run(): Result {
		$flagged = array();

		foreach ( $this->repo->distinct_forms() as $form ) {
			$label     = $form['form_name'] ? $form['form_name'] : $form['form_id'];
			$candidate = $this->evaluate_form( $form['integration'], $form['form_id'], $label );
			if ( $candidate ) {
				$flagged[] = $candidate;
			}
		}

		if ( empty( $flagged ) ) {
			return Result::pass( __( 'All forms with an established baseline are submitting normally.', 'watchspire' ) );
		}

		$first = $flagged[0];

		return Result::fail(
			sprintf(
				/* translators: 1: form name, 2: days of silence, 3: expected days between submissions */
				__( '%1$s has received no submissions for %2$s days (normally about one every %3$s day(s)).', 'watchspire' ),
				$first['name'],
				$first['silence_days'],
				$first['expected_interval_days']
			),
			array( 'flagged' => $flagged )
		);
	}

	private function evaluate_form( string $integration, string $form_id, string $name ): ?array {
		$daily = $this->repo->daily_counts( $integration, $form_id, self::BASELINE_WINDOW_DAYS );
		$total = array_sum( $daily );

		if ( $total < self::MIN_TOTAL_SUBMISSIONS || count( $daily ) < self::MIN_DAYS_WITH_DATA ) {
			return null; // No real baseline yet — never alert without one.
		}

		$values = array();
		for ( $i = self::BASELINE_WINDOW_DAYS - 1; $i >= 0; $i-- ) {
			$date     = gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );
			$values[] = $daily[ $date ] ?? 0;
		}

		$mean = array_sum( $values ) / count( $values );

		if ( $mean <= 0 ) {
			return null;
		}

		$variance = array_sum(
			array_map(
				static function ( $v ) use ( $mean ) {
					return ( $v - $mean ) ** 2;
				},
				$values
			)
		) / count( $values );

		$stddev = sqrt( $variance );

		// Highly erratic/seasonal forms: don't trust the mean enough to alert.
		if ( $stddev > 2 * $mean ) {
			return null;
		}

		$last = $this->repo->last_submission_at( $integration, $form_id );

		if ( ! $last ) {
			return null;
		}

		$silence_days = ( time() - strtotime( $last . ' UTC' ) ) / DAY_IN_SECONDS;
		$threshold    = self::EXPECTED_COUNT_THRESHOLD / $mean;

		if ( $silence_days < $threshold ) {
			return null;
		}

		return array(
			'integration'            => $integration,
			'form_id'                => $form_id,
			'name'                   => $name,
			'silence_days'           => round( $silence_days, 1 ),
			'daily_mean'             => round( $mean, 2 ),
			'expected_interval_days' => round( 1 / $mean, 1 ),
		);
	}
}
