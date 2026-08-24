<?php
/**
 * Elementor Pro Forms adapter.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations\Adapters;

use WatchSpire\Integrations\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

final class ElementorProIntegration extends AbstractIntegration {

	public function get_id(): string {
		return 'elementor_pro';
	}

	public function get_label(): string {
		return __( 'Elementor Pro Forms', 'watchspire' );
	}

	public function is_active(): bool {
		return class_exists( '\ElementorPro\Modules\Forms\Module' );
	}

	public function boot(): void {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'on_submitted' ), 10, 2 );
		add_action( 'elementor_pro/forms/mail_failed', array( $this, 'on_mail_failed' ), 10, 2 );
	}

	public function on_submitted( $record, $handler ): void {
		[ $id, $name ] = $this->form_identity( $record );

		$this->record( $id, $name, true );
	}

	public function on_mail_failed( $error_message, $record = null ): void {
		[ $id, $name ] = $this->form_identity( $record );

		$error = is_wp_error( $error_message )
			? $error_message->get_error_message()
			: ( is_string( $error_message ) ? $error_message : __( 'Elementor Pro form email failed to send.', 'watchspire' ) );

		$this->record( $id, $name, false, $error );
	}

	/**
	 * @return array{0:string,1:?string}
	 */
	private function form_identity( $record ): array {
		if ( is_object( $record ) && method_exists( $record, 'get_form_settings' ) ) {
			$raw_id   = $record->get_form_settings( 'id' );
			$raw_name = $record->get_form_settings( 'form_name' );
			$id       = $raw_id ? $raw_id : '';
			$name     = $raw_name ? $raw_name : null;

			return array( (string) $id, $name );
		}

		return array( 'unknown', null );
	}
}
