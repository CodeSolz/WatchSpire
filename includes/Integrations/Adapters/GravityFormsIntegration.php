<?php
/**
 * Gravity Forms adapter.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations\Adapters;

use WatchSpire\Integrations\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

final class GravityFormsIntegration extends AbstractIntegration {

	public function get_id(): string {
		return 'gravity_forms';
	}

	public function get_label(): string {
		return __( 'Gravity Forms', 'watchspire' );
	}

	public function is_active(): bool {
		return class_exists( 'GFForms' ) || class_exists( 'GFCommon' );
	}

	public function boot(): void {
		add_action( 'gform_after_submission', array( $this, 'on_submitted' ), 10, 2 );
		add_action( 'gform_send_email_failed', array( $this, 'on_send_failed' ), 10, 2 );
	}

	public function on_submitted( $entry, $form ): void {
		$id   = is_array( $form ) ? ( $form['id'] ?? 0 ) : 0;
		$name = is_array( $form ) ? ( $form['title'] ?? null ) : null;

		$this->record( (string) $id, $name, true );
	}

	public function on_send_failed( $error, $message_data ): void {
		$form = is_array( $message_data ) ? ( $message_data['form'] ?? null ) : null;
		$id   = is_array( $form ) ? ( $form['id'] ?? 0 ) : 0;
		$name = is_array( $form ) ? ( $form['title'] ?? null ) : null;

		$error_message = is_wp_error( $error )
			? $error->get_error_message()
			: ( is_string( $error ) ? $error : __( 'Gravity Forms email send failed.', 'watchspire' ) );

		$this->record( (string) $id, $name, false, $error_message );
	}
}
