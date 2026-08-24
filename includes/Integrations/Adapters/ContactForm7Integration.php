<?php
/**
 * Contact Form 7 adapter.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations\Adapters;

use WatchSpire\Integrations\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

final class ContactForm7Integration extends AbstractIntegration {

	public function get_id(): string {
		return 'cf7';
	}

	public function get_label(): string {
		return __( 'Contact Form 7', 'watchspire' );
	}

	public function is_active(): bool {
		return class_exists( 'WPCF7_ContactForm' );
	}

	public function boot(): void {
		add_action( 'wpcf7_mail_sent', array( $this, 'on_sent' ) );
		add_action( 'wpcf7_mail_failed', array( $this, 'on_failed' ) );
	}

	public function on_sent( $contact_form ): void {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return;
		}

		$this->record( (string) $contact_form->id(), $this->title( $contact_form ), true );
	}

	public function on_failed( $contact_form ): void {
		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return;
		}

		$error = null;

		if ( method_exists( $contact_form, 'submission' ) ) {
			$submission = $contact_form->submission();
			if ( $submission && method_exists( $submission, 'get_response' ) ) {
				$error = (string) $submission->get_response();
			}
		}

		$this->record( (string) $contact_form->id(), $this->title( $contact_form ), false, $error );
	}

	private function title( $contact_form ): ?string {
		return method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : null;
	}
}
