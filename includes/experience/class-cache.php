<?php
/**
 * Versioned object and transient cache.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps expensive discovery responses fast and centrally invalidatable.
 */
final class Cache {
	private const GROUP = 'adam_comunidade';
	private const VERSION_OPTION = 'adam_comunidade_cache_version';

	public function register(): void {
		foreach ( array( 'adam_comunidade_team_saved', 'adam_comunidade_field_saved', 'adam_comunidade_directory_entry_saved', 'adam_comunidade_event_created', 'adam_comunidade_event_updated', 'adam_comunidade_event_deleted', 'save_post_adam_news', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( $this, 'flush' ) );
		}
		add_action( 'adam_comunidade_reset_cache', array( $this, 'flush' ) );
	}

	public static function remember( string $key, callable $callback, int $ttl = 300 ): mixed {
		$key   = self::key( $key );
		$value = wp_cache_get( $key, self::GROUP );
		if ( false !== $value ) {
			return $value;
		}
		$value = get_transient( $key );
		if ( false === $value ) {
			$value = $callback();
			set_transient( $key, $value, $ttl );
		}
		wp_cache_set( $key, $value, self::GROUP, $ttl );
		return $value;
	}

	public function flush( mixed ...$ignored ): void {
		unset( $ignored );
		update_option( self::VERSION_OPTION, (string) microtime( true ), false );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::GROUP );
			wp_cache_flush_group( 'adam_comunidade_archives' );
		}
	}

	private static function key( string $key ): string {
		$version = (string) get_option( self::VERSION_OPTION, '1' );
		return 'adam_' . md5( $version . '|' . $key );
	}
}
