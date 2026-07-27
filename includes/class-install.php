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
	 * Option used to defer rewrite regeneration until a complete init request.
	 */
	public const REWRITE_FLUSH_OPTION = 'adam_comunidade_flush_rewrite_rules';

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( ADAM_COMUNIDADE_BASENAME );
			wp_die(
				'ADAM Comunidade requires PHP 8.1 or newer.',
				'Plugin activation error',
				array( 'back_link' => true )
			);
		}

		update_option( 'adam_comunidade_version', ADAM_COMUNIDADE_VERSION, false );
		Teams\Schema::install();
		Fields\Schema::install();
		Directory\Schema::install();
		Events\Migration::run();
		Experience\Schema::install();
		self::schedule_rewrite_flush();
	}

	/**
	 * Schedules one rewrite flush after all plugin routes have been registered.
	 *
	 * @return void
	 */
	public static function schedule_rewrite_flush(): void {
		update_option( self::REWRITE_FLUSH_OPTION, 1, false );
	}
}
