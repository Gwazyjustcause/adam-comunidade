<?php
/**
 * Shared public hero component contract.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies consistent, reusable class names for public hero markup.
 */
final class Public_Hero {
	/**
	 * Returns classes for a hero root.
	 */
	public static function root( string $specific = '', string $variant = 'image' ): string {
		return self::join(
			array(
				'adam-public-hero',
				'adam-public-hero--' . sanitize_html_class( $variant ),
				$specific,
			)
		);
	}

	/**
	 * Returns classes for a hero element.
	 */
	public static function element( string $element, string $specific = '' ): string {
		return self::join(
			array(
				'adam-public-hero__' . sanitize_html_class( $element ),
				$specific,
			)
		);
	}

	/**
	 * @param string[] $classes Class names.
	 */
	private static function join( array $classes ): string {
		$tokens = array();
		foreach ( $classes as $class_list ) {
			foreach ( preg_split( '/\s+/', trim( $class_list ) ) ?: array() as $class_name ) {
				if ( '' !== $class_name ) {
					$tokens[] = sanitize_html_class( $class_name );
				}
			}
		}
		return implode( ' ', array_filter( $tokens ) );
	}
}
