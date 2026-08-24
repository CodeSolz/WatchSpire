<?php
/**
 * Typed accessor for the single watchspire_settings option.
 *
 * @package WatchSpire
 */

namespace WatchSpire\Support;

defined( 'ABSPATH' ) || exit;

final class Settings {

	private const OPTION = 'watchspire_settings';

	private static ?array $cache = null;

	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = is_array( $stored ) ? $stored : array();
		}

		return self::$cache;
	}

	/**
	 * @param mixed $default_value
	 * @return mixed
	 */
	public static function get( string $key, $default_value = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default_value;
	}

	public static function set( string $key, $value ): void {
		$all         = self::all();
		$all[ $key ] = $value;
		self::$cache = $all;
		update_option( self::OPTION, $all );
	}

	public static function update( array $values ): void {
		$all         = array_merge( self::all(), $values );
		self::$cache = $all;
		update_option( self::OPTION, $all );
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}
}
