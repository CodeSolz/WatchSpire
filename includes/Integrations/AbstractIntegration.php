<?php
/**
 * Shared helpers for form-builder adapters.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations;

use WatchSpire\Alerts\Failure;
use WatchSpire\Database\Repositories\SubmissionsRepository;

defined( 'ABSPATH' ) || exit;

abstract class AbstractIntegration implements IntegrationInterface {

	protected SubmissionsRepository $submissions;

	public function __construct() {
		$this->submissions = new SubmissionsRepository();
	}

	/**
	 * Record an outcome. Never pass field contents — outcome metadata only.
	 * Failures alert immediately rather than waiting for the next scheduled check.
	 *
	 * Whether this specific submission is synthetic (e.g. an automated testing
	 * engine, not a real visitor) is read from a filter rather than a
	 * parameter, because the same builder hooks (wpcf7_mail_sent, etc.)
	 * fire identically for both — there is no other signal available at
	 * this layer to tell them apart. An add-on flips this filter for the
	 * duration of a synthetic submission so it never gets counted toward
	 * real submission volume/baselines.
	 */
	protected function record( string $form_id, ?string $form_name, bool $delivered, ?string $error = null ): void {
		$error        = $error ? mb_substr( $error, 0, 500 ) : null;
		$is_synthetic = (bool) apply_filters( 'watchspire_is_synthetic_context', false );

		$this->submissions->insert( $this->get_id(), (string) $form_id, $form_name, $delivered, $error, $is_synthetic );

		if ( $delivered || $is_synthetic ) {
			return;
		}

		$label = $form_name ? sprintf( '%s (%s)', $form_name, $this->get_label() ) : $this->get_label();

		$failure = new Failure(
			'form:' . $this->get_id() . ':' . $form_id,
			$label,
			$error ? $error : __( 'Form submission failed to deliver.', 'watchspire' )
		);

		/**
		 * Fired for every detected failure, monitor checks and form
		 * submissions alike, before the alert is dispatched — so a
		 * listener can enrich $failure->message with extra context that
		 * then shows up in the alert itself.
		 */
		do_action( 'watchspire_failure_detected', $failure );

		$alerts = function_exists( 'watchspire' ) ? watchspire()->get( 'alerts' ) : null;

		if ( $alerts ) {
			$alerts->handle_failure( $failure );
		}
	}
}
