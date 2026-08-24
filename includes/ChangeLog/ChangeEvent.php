<?php
/**
 * Value object for a recorded change-log entry.
 *
 * @package WatchSpire
 */

namespace WatchSpire\ChangeLog;

defined( 'ABSPATH' ) || exit;

final class ChangeEvent {

	public string $type;
	public string $object_slug;
	public ?string $object_name;
	public ?string $from_version;
	public ?string $to_version;
	public bool $is_auto_update;
	public ?int $user_id;
	public int $timestamp;

	public function __construct(
		string $type,
		string $object_slug,
		?string $object_name = null,
		?string $from_version = null,
		?string $to_version = null,
		bool $is_auto_update = false,
		?int $user_id = null
	) {
		$this->type           = $type;
		$this->object_slug    = $object_slug;
		$this->object_name    = $object_name;
		$this->from_version   = $from_version;
		$this->to_version     = $to_version;
		$this->is_auto_update = $is_auto_update;
		$this->user_id        = $user_id;
		$this->timestamp      = time();
	}
}
