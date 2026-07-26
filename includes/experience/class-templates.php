<?php
/**
 * Theme-overridable template locator.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;

/**
 * Locates templates in the theme before falling back to the plugin.
 */
final class Templates {
	public static function locate( string $relative ): string {
		$relative = ltrim( str_replace( array( '..', '\\' ), array( '', '/' ), $relative ), '/' );
		$theme = locate_template( array( 'adam-comunidade/' . $relative ) );
		$path  = $theme ?: Helpers::path( 'templates/' . $relative );
		return (string) apply_filters( 'adam_comunidade_template_path', $path, $relative );
	}
}
