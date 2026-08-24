<?php
/**
 * Deactivation routine.
 *
 * @package WatchSpire
 */

namespace WatchSpire;

use WatchSpire\Admin\Notices;
use WatchSpire\Scheduler\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		Scheduler::unschedule_all();

		// So the welcome pointer shows again if the plugin is activated
		// afresh. Permanent dismissals are left alone.
		Notices::reset_transient_dismissals();
	}
}
