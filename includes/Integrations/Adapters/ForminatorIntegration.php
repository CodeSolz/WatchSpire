<?php
/**
 * Forminator adapter.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations\Adapters;

use WatchSpire\Integrations\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

final class ForminatorIntegration extends AbstractIntegration {

	public function get_id(): string {
		return 'forminator';
	}

	public function get_label(): string {
		return __( 'Forminator', 'watchspire' );
	}

	public function is_active(): bool {
		return class_exists( '\Forminator_API' ) || defined( 'FORMINATOR_VERSION' );
	}

	public function boot(): void {
		add_action( 'forminator_form_after_save_entry', array( $this, 'on_submitted' ), 10, 2 );
		add_action( 'forminator_custom_form_mail_failed', array( $this, 'on_mail_failed' ), 10, 2 );
	}

	public function on_submitted( $form_id, $response = null ): void {
		$this->record( (string) $form_id, $this->form_name( $form_id ), true );
	}

	public function on_mail_failed( $form_id, $error = null ): void {
		$message = is_wp_error( $error ) ? $error->get_error_message() : ( is_string( $error ) ? $error : __( 'Forminator email failed to send.', 'watchspire' ) );

		$this->record( (string) $form_id, $this->form_name( $form_id ), false, $message );
	}

	private function form_name( $form_id ): ?string {
		if ( ! class_exists( '\Forminator_API' ) || ! method_exists( '\Forminator_API', 'get_form' ) ) {
			return null;
		}

		try {
			$form = \Forminator_API::get_form( $form_id );
			return is_object( $form ) && isset( $form->settings['formName'] ) ? $form->settings['formName'] : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
