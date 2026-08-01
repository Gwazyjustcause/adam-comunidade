<?php
/**
 * Teams module bootstrap.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Module_Interface;
use ADAM\Comunidade\Teams\Admin\Controller as Admin_Controller;

/**
 * Registers the complete Teams feature module.
 */
final class Module implements Module_Interface {
	/**
	 * Module identifier.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'teams';
	}

	/**
	 * Registers module services.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ADAM_COMUNIDADE_VERSION !== get_option( 'adam_comunidade_version' ) ) {
			update_option( 'adam_comunidade_version', ADAM_COMUNIDADE_VERSION, false );
		}

		if ( Schema::VERSION !== get_option( 'adam_comunidade_teams_db_version' ) ) {
			Schema::install();
			\ADAM\Comunidade\Install::schedule_rewrite_flush();
		}

		$repository = new Repository();
		$router     = new Router( $repository );

		$router->register();

		if ( is_admin() ) {
			( new Admin_Controller( $repository ) )->register();
		}

		add_action( 'init', array( $this, 'register_image_sizes' ) );
	}

	/**
	 * Registers responsive media sizes used by team views.
	 *
	 * @return void
	 */
	public function register_image_sizes(): void {
		add_image_size( 'adam-team-logo', 600, 600, true );
		// A distinct uncropped rendition keeps every uploaded logo fully visible.
		add_image_size( 'adam-team-logo-contain', 600, 600, false );
		add_image_size( 'adam-team-cover', 1920, 600, true );
		add_image_size( 'adam-team-card', 720, 360, true );
	}
}
