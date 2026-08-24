<?php
/**
 * WPForms adapter.
 *
 * WPForms doesn't fire a dedicated "mail failed" action, so this adapter
 * watches wp_mail_failed while a WPForms submission is being processed
 * and attributes any failure in that window to the form.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations\Adapters;

use WatchSpire\Integrations\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

final class WPFormsIntegration extends AbstractIntegration {

	private bool $processing    = false;
	private ?string $mail_error = null;

	public function get_id(): string {
		return 'wpforms';
	}

	public function get_label(): string {
		return __( 'WPForms', 'watchspire' );
	}

	public function is_active(): bool {
		return function_exists( 'wpforms' );
	}

	public function boot(): void {
		add_action( 'wpforms_process', array( $this, 'on_process_start' ), 5, 1 );
		add_action( 'wpforms_process_complete', array( $this, 'on_complete' ), 20, 4 );
		add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ) );
	}

	public function on_process_start( $fields = null ): void {
		$this->processing = true;
		$this->mail_error = null;
	}

	public function on_mail_failed( $error ): void {
		if ( $this->processing && is_wp_error( $error ) ) {
			$this->mail_error = $error->get_error_message();
		}
	}

	public function on_complete( $fields, $entry, $form_data, $entry_id ): void {
		$form_id   = is_array( $form_data ) ? ( $form_data['id'] ?? 0 ) : 0;
		$form_name = is_array( $form_data ) ? ( $form_data['settings']['form_title'] ?? null ) : null;

		$this->record( (string) $form_id, $form_name, null === $this->mail_error, $this->mail_error );

		$this->processing = false;
		$this->mail_error = null;
	}
}
