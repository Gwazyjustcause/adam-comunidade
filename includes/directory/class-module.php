<?php
/**
 * Shared directory module.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Admin\Controller as Admin_Controller;
use ADAM\Comunidade\Module_Interface;

/**
 * Boots independent organisation types and shared integrations.
 */
final class Module implements Module_Interface {
	public function id(): string {
		return 'directory';
	}

	public function register(): void {
		if ( ADAM_COMUNIDADE_VERSION !== get_option( 'adam_comunidade_version' ) ) {
			update_option( 'adam_comunidade_version', ADAM_COMUNIDADE_VERSION, false );
		}
		if ( Schema::VERSION !== get_option( 'adam_comunidade_directory_db_version' ) ) {
			Schema::install();
			\ADAM\Comunidade\Install::schedule_rewrite_flush();
		}
		$repository    = new Repository();
		$relationships = new Relationship_Repository();
		( new Router( $repository ) )->register();
		( new Rest_API( $repository ) )->register();
		( new Components( $repository, $relationships ) )->register();
		if ( is_admin() ) {
			( new Admin_Controller( $repository, $relationships ) )->register();
		}
		add_action( 'init', array( $this, 'register_image_sizes' ) );
	}

	public function register_image_sizes(): void {
		add_image_size( 'adam-directory-logo', 600, 600, true );
		add_image_size( 'adam-directory-cover', 1920, 700, true );
		add_image_size( 'adam-directory-card', 720, 420, true );
		add_image_size( 'adam-directory-gallery', 1000, 700, false );
	}
}
