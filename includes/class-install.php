<?php
/**
 * Installation and upgrade routines.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
final class Install {
	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( ADAM_COMUNIDADE_BASENAME );
			wp_die(
				esc_html__( 'ADAM Comunidade requires PHP 8.1 or newer.', 'adam-comunidade' ),
				esc_html__( 'Plugin activation error', 'adam-comunidade' ),
				array( 'back_link' => true )
			);
		}

		update_option( 'adam_comunidade_version', ADAM_COMUNIDADE_VERSION, false );
		Teams\Schema::install();
		Fields\Schema::install();
		Directory\Schema::install();
		Experience\Schema::install();
		( new Experience\News() )->register_content();
		Teams\Router::add_rewrite_rules();
		Fields\Router::add_rewrite_rules();
		Directory\Router::add_rewrite_rules();
		Directory\Rest_API::add_rewrite_rules();
		Experience\Router::add_rewrite_rules();
		Experience\Api_V2::add_rewrite_rules();
		Experience\Portal::add_rewrite_rules();
		Experience\Calendar::add_rewrite_rules();

		flush_rewrite_rules();
	}
}
