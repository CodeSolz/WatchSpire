<?php
/**
 * Records site changes (updates, activations, option changes) so the
 * change log can show what happened on the site around the time a
 * check started failing.
 *
 * @package WatchSpire
 */

namespace WatchSpire\ChangeLog;

use WatchSpire\Database\Repositories\ChangelogRepository;

defined( 'ABSPATH' ) || exit;

final class ChangeLogRecorder {

	/**
	 * Selective option allowlist. Never a blanket option watcher.
	 * "active_plugins" is intentionally excluded — activated_plugin /
	 * deactivated_plugin already cover it and watching the option too
	 * would double-log every activation.
	 */
	private const WATCHED_OPTIONS = array(
		'permalink_structure',
		'home',
		'siteurl',
		'template',
		'stylesheet',
		'mailserver_url',
		'mailserver_login',
		'timezone_string',
		'gmt_offset',
	);

	private ChangelogRepository $repo;

	/**
	 * Versions captured just before an upgrade, keyed by plugin/theme file.
	 *
	 * @var array<string,string>
	 */
	private array $pending_versions = array();

	/**
	 * Plugin names captured just before deletion, keyed by plugin file.
	 *
	 * @var array<string,string>
	 */
	private array $pending_names = array();

	public function __construct() {
		$this->repo = new ChangelogRepository();
	}

	public function boot(): void {
		add_action( 'upgrader_pre_install', array( $this, 'capture_pre_install' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_complete' ), 10, 2 );

		add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_deactivated' ) );

		add_action( 'delete_plugin', array( $this, 'capture_pre_delete' ) );
		add_action( 'deleted_plugin', array( $this, 'on_plugin_deleted' ), 10, 2 );

		add_action( 'switch_theme', array( $this, 'on_theme_switched' ), 10, 3 );
		add_action( '_core_updated_successfully', array( $this, 'on_core_updated' ) );

		add_action( 'wp_login', array( $this, 'on_user_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'on_user_logout' ) );

		foreach ( self::WATCHED_OPTIONS as $option ) {
			add_action( "update_option_{$option}", array( $this, 'on_watched_option_changed' ), 10, 3 );
		}
	}

	public function capture_pre_install( $response, array $hook_extra ) {
		if ( ! empty( $hook_extra['plugin'] ) ) {
			$file = $hook_extra['plugin'];
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$path = WP_PLUGIN_DIR . '/' . $file;
			if ( file_exists( $path ) ) {
				$data                                        = get_plugin_data( $path, false, false );
				$this->pending_versions[ 'plugin:' . $file ] = $data['Version'] ?? '';
				$this->pending_names[ 'plugin:' . $file ]    = $data['Name'] ?? $file;
			}
		}

		if ( ! empty( $hook_extra['theme'] ) ) {
			$stylesheet = $hook_extra['theme'];
			$theme      = wp_get_theme( $stylesheet );
			if ( $theme->exists() ) {
				$this->pending_versions[ 'theme:' . $stylesheet ] = $theme->get( 'Version' );
				$this->pending_names[ 'theme:' . $stylesheet ]    = $theme->get( 'Name' );
			}
		}

		return $response;
	}

	public function on_upgrader_complete( $upgrader, array $hook_extra ): void {
		$is_auto_update = wp_doing_cron();

		if ( ! empty( $hook_extra['plugin'] ) && 'plugin' === ( $hook_extra['type'] ?? 'plugin' ) ) {
			$file = $hook_extra['plugin'];
			$this->log_plugin_change( $file, $hook_extra, $is_auto_update );
			return;
		}

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) && empty( $hook_extra['plugin'] ) ) {
			foreach ( $hook_extra['plugins'] as $file ) {
				$this->log_plugin_change( $file, $hook_extra, $is_auto_update );
			}
			return;
		}

		if ( ! empty( $hook_extra['theme'] ) && 'theme' === ( $hook_extra['type'] ?? 'theme' ) ) {
			$stylesheet = $hook_extra['theme'];
			$this->log_theme_change( $stylesheet, $hook_extra, $is_auto_update );
			return;
		}

		if ( ! empty( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) && empty( $hook_extra['theme'] ) ) {
			foreach ( $hook_extra['themes'] as $stylesheet ) {
				$this->log_theme_change( $stylesheet, $hook_extra, $is_auto_update );
			}
		}
	}

	private function log_plugin_change( string $file, array $hook_extra, bool $is_auto_update ): void {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$path = WP_PLUGIN_DIR . '/' . $file;
		$data = file_exists( $path ) ? get_plugin_data( $path, false, false ) : array();

		$name         = $data['Name'] ?? $this->pending_names[ 'plugin:' . $file ] ?? $file;
		$to_version   = $data['Version'] ?? '';
		$from_version = $this->pending_versions[ 'plugin:' . $file ] ?? null;
		$action       = $hook_extra['action'] ?? 'update';
		$type         = 'install' === $action ? 'plugin_install' : 'plugin_update';

		$this->record( $type, $file, $name, $from_version, $to_version, $is_auto_update );

		unset( $this->pending_versions[ 'plugin:' . $file ], $this->pending_names[ 'plugin:' . $file ] );
	}

	private function log_theme_change( string $stylesheet, array $hook_extra, bool $is_auto_update ): void {
		$theme        = wp_get_theme( $stylesheet );
		$name         = $theme->exists() ? $theme->get( 'Name' ) : $stylesheet;
		$to_version   = $theme->exists() ? $theme->get( 'Version' ) : '';
		$from_version = $this->pending_versions[ 'theme:' . $stylesheet ] ?? null;
		$action       = $hook_extra['action'] ?? 'update';
		$type         = 'install' === $action ? 'theme_install' : 'theme_update';

		$this->record( $type, $stylesheet, $name, $from_version, $to_version, $is_auto_update );

		unset( $this->pending_versions[ 'theme:' . $stylesheet ] );
	}

	public function on_plugin_activated( string $plugin ): void {
		$name = $this->plugin_name( $plugin );
		$this->record( 'plugin_activate', $plugin, $name, null, null, false );
	}

	public function on_plugin_deactivated( string $plugin ): void {
		$name = $this->plugin_name( $plugin );
		$this->record( 'plugin_deactivate', $plugin, $name, null, null, false );
	}

	public function capture_pre_delete( string $plugin ): void {
		$this->pending_names[ 'plugin:' . $plugin ] = $this->plugin_name( $plugin );
	}

	public function on_plugin_deleted( string $plugin, bool $deleted ): void {
		if ( ! $deleted ) {
			return;
		}

		$name = $this->pending_names[ 'plugin:' . $plugin ] ?? $plugin;
		$this->record( 'plugin_delete', $plugin, $name, null, null, false );
		unset( $this->pending_names[ 'plugin:' . $plugin ] );
	}

	public function on_theme_switched( string $new_name, \WP_Theme $new_theme, \WP_Theme $old_theme ): void {
		$this->record(
			'theme_switch',
			$new_theme->get_stylesheet(),
			$new_name,
			$old_theme->exists() ? $old_theme->get( 'Name' ) . ' ' . $old_theme->get( 'Version' ) : null,
			$new_theme->get( 'Version' ),
			false
		);
	}

	public function on_core_updated( string $wp_version ): void {
		$previous = get_option( 'watchspire_last_known_core_version', '' );
		update_option( 'watchspire_last_known_core_version', $wp_version, false );

		if ( $previous === $wp_version ) {
			return;
		}

		$this->record( 'core_update', 'wordpress-core', __( 'WordPress core', 'watchspire' ), $previous ? $previous : null, $wp_version, wp_doing_cron() );
	}

	public function on_user_login( string $user_login, \WP_User $user ): void {
		$this->record( 'user_login', $user_login, $user->display_name, null, null, false, $user->ID );
	}

	/**
	 * @param int $user_id 0 pre-WP 5.5, where wp_logout carries no argument —
	 *                      the current user is already cleared by then, so
	 *                      there's nothing to attribute the event to.
	 */
	public function on_user_logout( int $user_id = 0 ): void {
		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );

		$this->record(
			'user_logout',
			$user ? $user->user_login : (string) $user_id,
			$user ? $user->display_name : null,
			null,
			null,
			false,
			$user_id
		);
	}

	/**
	 * @param mixed $old_value
	 * @param mixed $value
	 */
	public function on_watched_option_changed( $old_value, $value, string $option ): void {
		if ( $old_value === $value ) {
			return;
		}

		$this->record(
			'option_change',
			$option,
			$option,
			is_scalar( $old_value ) ? (string) $old_value : wp_json_encode( $old_value ),
			is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
			false
		);
	}

	private function record( string $type, string $slug, ?string $name, ?string $from_version, ?string $to_version, bool $is_auto_update, ?int $user_id = null ): void {
		// wp_logout fires after the current user has already been cleared,
		// so callers there must pass the ID explicitly rather than relying
		// on the get_current_user_id() fallback every other event uses.
		$user_id = $user_id ?? get_current_user_id();

		$recorded_by = $user_id ? $user_id : null;

		$this->repo->insert( $type, $slug, $name, $from_version, $to_version, $is_auto_update, $recorded_by );

		$event = new ChangeEvent( $type, $slug, $name, $from_version, $to_version, $is_auto_update, $recorded_by );

		do_action( 'watchspire_change_recorded', $event );
	}

	private function plugin_name( string $plugin ): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$path = WP_PLUGIN_DIR . '/' . $plugin;

		if ( ! file_exists( $path ) ) {
			return $plugin;
		}

		$data = get_plugin_data( $path, false, false );

		return $data['Name'] ?? $plugin;
	}
}
