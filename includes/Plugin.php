<?php
/**
 * Plugin bootstrap / service container.
 *
 * @package WatchSpire
 */

namespace WatchSpire;

use WatchSpire\Database\Schema;
use WatchSpire\Scheduler\Scheduler;
use WatchSpire\Monitors\MonitorRegistry;
use WatchSpire\Monitors\Runner;
use WatchSpire\Alerts\AlertManager;
use WatchSpire\ChangeLog\ChangeLogRecorder;
use WatchSpire\Integrations\IntegrationRegistry;
use WatchSpire\Monitors\LinkScan\LinkScanManager;
use WatchSpire\Admin\AdminMenu;
use WatchSpire\Admin\Settings;
use WatchSpire\Admin\Onboarding;
use WatchSpire\Admin\Notices;
use WatchSpire\Admin\PluginLinks;
use WatchSpire\Support\CrawlerLogger;
use WatchSpire\Support\WeeklyDigest;
use WatchSpire\Support\Cleanup;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<string, object>
	 */
	private $services = array();

	private bool $booted = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Boot the plugin. Hooked to plugins_loaded.
	 */
	public function init(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// Translations are loaded automatically by WordPress for plugins
		// hosted on WordPress.org since 4.6, so no load_plugin_textdomain()
		// call is needed here.
		$this->maybe_migrate();
		$this->maybe_handle_upgrade();

		$this->services['scheduler']    = new Scheduler();
		$this->services['monitors']     = new MonitorRegistry();
		$this->services['alerts']       = new AlertManager();
		$this->services['runner']       = new Runner( $this->services['monitors'], $this->services['alerts'] );
		$this->services['changelog']    = new ChangeLogRecorder();
		$this->services['integrations'] = new IntegrationRegistry();
		$this->services['crawler']      = new CrawlerLogger();
		$this->services['digest']       = new WeeklyDigest();
		$this->services['cleanup']      = new Cleanup();
		$this->services['link_scan']    = new LinkScanManager();

		$this->services['scheduler']->boot();
		$this->services['monitors']->boot();
		$this->services['alerts']->boot();
		$this->services['runner']->boot();
		$this->services['changelog']->boot();
		$this->services['integrations']->boot();
		$this->services['crawler']->boot();
		$this->services['digest']->boot();
		$this->services['cleanup']->boot();
		$this->services['link_scan']->boot();

		if ( is_admin() ) {
			$this->services['admin_menu'] = new AdminMenu();
			$this->services['settings']   = new Settings();
			$this->services['onboarding'] = new Onboarding();
			$this->services['notices']    = new Notices();
			$this->services['links']      = new PluginLinks();

			$this->services['admin_menu']->boot();
			$this->services['settings']->boot();
			$this->services['onboarding']->boot();
			$this->services['notices']->boot();
			$this->services['links']->boot();
		}

		do_action( 'watchspire_loaded', $this );
	}

	public function get( string $id ) {
		return $this->services[ $id ] ?? null;
	}

	/**
	 * On a version change, clear the non-permanent notice dismissals so a
	 * review request someone put off with "I'll do it later" gets one
	 * more airing. A permanent dismissal is never undone.
	 */
	private function maybe_handle_upgrade(): void {
		$stored = get_option( 'watchspire_version' );

		if ( WATCHSPIRE_VERSION === $stored ) {
			return;
		}

		if ( false !== $stored ) {
			Notices::reset_transient_dismissals();
		}

		update_option( 'watchspire_version', WATCHSPIRE_VERSION );
	}

	private function maybe_migrate(): void {
		$installed = (string) get_option( 'watchspire_db_version', '0' );

		// Any difference migrates, not just a higher number. The schema
		// version was renumbered to 1 for the 1.0.0 release, and a "<"
		// test would strand sites already carrying the old higher number
		// on it — skipping this migration and every future one. Schema
		// is dbDelta-based, so re-running it is harmless.
		if ( $installed !== (string) WATCHSPIRE_DB_VERSION ) {
			Schema::migrate();
			update_option( 'watchspire_db_version', WATCHSPIRE_DB_VERSION );
		}
	}
}
