<?php
/**
 * Fluent Forms adapter.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations\Adapters;

use WatchSpire\Integrations\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

final class FluentFormsIntegration extends AbstractIntegration {

	public function get_id(): string {
		return 'fluent_forms';
	}

	public function get_label(): string {
		return __( 'Fluent Forms', 'watchspire' );
	}

	public function is_active(): bool {
		return defined( 'FLUENTFORM' ) || class_exists( '\FluentForm\Framework\Foundation\Application' );
	}

	public function boot(): void {
		add_action( 'fluentform/submission_inserted', array( $this, 'on_submitted' ), 10, 3 );
		add_action( 'fluentform/notification_failed', array( $this, 'on_notification_failed' ), 10, 3 );
	}

	public function on_submitted( $insert_id, $form_data, $form ): void {
		[ $id, $name ] = $this->form_identity( $form );

		$this->record( $id, $name, true );
	}

	public function on_notification_failed( $notification, $feed, $entry ): void {
		$form          = is_array( $feed ) ? ( $feed['form'] ?? null ) : null;
		[ $id, $name ] = $this->form_identity( $form );

		$this->record( $id, $name, false, __( 'Fluent Forms notification failed to send.', 'watchspire' ) );
	}

	/**
	 * @return array{0:string,1:?string}
	 */
	private function form_identity( $form ): array {
		if ( is_object( $form ) ) {
			$id   = $form->id ?? 'unknown';
			$name = $form->title ?? null;

			return array( (string) $id, $name );
		}

		if ( is_array( $form ) ) {
			return array( (string) ( $form['id'] ?? 'unknown' ), $form['title'] ?? null );
		}

		return array( 'unknown', null );
	}
}
