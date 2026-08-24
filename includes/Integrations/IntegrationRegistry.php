<?php
/**
 * Collects and boots active form-builder adapters.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Integrations;

use WatchSpire\Integrations\Adapters\ContactForm7Integration;
use WatchSpire\Integrations\Adapters\WPFormsIntegration;
use WatchSpire\Integrations\Adapters\GravityFormsIntegration;
use WatchSpire\Integrations\Adapters\ElementorProIntegration;
use WatchSpire\Integrations\Adapters\FluentFormsIntegration;
use WatchSpire\Integrations\Adapters\ForminatorIntegration;

defined( 'ABSPATH' ) || exit;

final class IntegrationRegistry {

	public function boot(): void {
		add_filter( 'watchspire_form_integrations', array( $this, 'register_builtin_integrations' ), 5 );
		add_action( 'init', array( $this, 'boot_active_integrations' ), 20 );
	}

	/**
	 * @param array<string, IntegrationInterface> $integrations
	 * @return array<string, IntegrationInterface>
	 */
	public function register_builtin_integrations( array $integrations ): array {
		$builtins = array(
			new ContactForm7Integration(),
			new WPFormsIntegration(),
			new GravityFormsIntegration(),
			new ElementorProIntegration(),
			new FluentFormsIntegration(),
			new ForminatorIntegration(),
		);

		foreach ( $builtins as $integration ) {
			$integrations[ $integration->get_id() ] = $integration;
		}

		return $integrations;
	}

	/**
	 * @return array<string, IntegrationInterface>
	 */
	public function get_integrations(): array {
		$integrations = apply_filters( 'watchspire_form_integrations', array() );

		return is_array( $integrations ) ? $integrations : array();
	}

	/**
	 * @return array<string, IntegrationInterface>
	 */
	public function get_active_integrations(): array {
		return array_filter(
			$this->get_integrations(),
			static function ( IntegrationInterface $integration ) {
				try {
					return $integration->is_active();
				} catch ( \Throwable $e ) {
					return false;
				}
			}
		);
	}

	public function boot_active_integrations(): void {
		foreach ( $this->get_active_integrations() as $integration ) {
			try {
				$integration->boot();
			} catch ( \Throwable $e ) {
				continue;
			}
		}
	}
}
