<?php
/**
 * Public developer registries.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Lets integrations register components without modifying core classes.
 */
final class Registry {
	/** @var array<string,array<string,mixed>> */
	private static array $items = array();

	public function register(): void {
		do_action( 'adam_comunidade_register_platform', self::class );
	}

	public static function add( string $registry, string $id, array $definition ): bool {
		$registry = sanitize_key( $registry );
		$id       = sanitize_key( $id );
		if ( ! $registry || ! $id || isset( self::$items[ $registry ][ $id ] ) ) {
			return false;
		}
		self::$items[ $registry ][ $id ] = $definition;
		do_action( 'adam_comunidade_registry_added', $registry, $id, $definition );
		return true;
	}

	public static function all( string $registry ): array {
		return apply_filters( 'adam_comunidade_registry_' . sanitize_key( $registry ), self::$items[ sanitize_key( $registry ) ] ?? array() );
	}
}
