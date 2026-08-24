<?php
/**
 * Value object returned by every monitor run.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Monitors;

defined( 'ABSPATH' ) || exit;

final class Result {

	public const PASS = 'pass';
	public const WARN = 'warn';
	public const FAIL = 'fail';

	public string $status;
	public string $message;
	public array $detail;
	public int $duration_ms;

	public function __construct( string $status, string $message = '', array $detail = array(), int $duration_ms = 0 ) {
		$this->status      = $status;
		$this->message     = $message;
		$this->detail      = $detail;
		$this->duration_ms = $duration_ms;
	}

	public static function pass( string $message = '', array $detail = array(), int $duration_ms = 0 ): self {
		return new self( self::PASS, $message, $detail, $duration_ms );
	}

	public static function warn( string $message = '', array $detail = array(), int $duration_ms = 0 ): self {
		return new self( self::WARN, $message, $detail, $duration_ms );
	}

	public static function fail( string $message = '', array $detail = array(), int $duration_ms = 0 ): self {
		return new self( self::FAIL, $message, $detail, $duration_ms );
	}

	public function is_failing(): bool {
		return self::FAIL === $this->status;
	}

	public function to_array(): array {
		return array(
			'status'      => $this->status,
			'message'     => $this->message,
			'detail'      => $this->detail,
			'duration_ms' => $this->duration_ms,
		);
	}
}
