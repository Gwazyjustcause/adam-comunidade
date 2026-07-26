<?php
/**
 * Fields module bootstrap.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Module_Interface;
use ADAM\Comunidade\Fields\Admin\Controller as Admin_Controller;

/**
 * Registers the Campos feature.
 */
final class Module implements Module_Interface {
	/**
	 * Module ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'fields';
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

		if ( Schema::VERSION !== get_option( 'adam_comunidade_fields_db_version' ) ) {
			Schema::install();
			\ADAM\Comunidade\Install::schedule_rewrite_flush();
		}

		$repository = new Repository();
		( new Router( $repository ) )->register();

		if ( is_admin() ) {
			( new Admin_Controller( $repository, new Amenity_Repository() ) )->register();
		}

		add_action( 'init', array( $this, 'register_image_sizes' ) );
	}

	/**
	 * Adds responsive image sizes.
	 *
	 * @return void
	 */
	public function register_image_sizes(): void {
		add_image_size( 'adam-field-cover', 1920, 700, true );
		add_image_size( 'adam-field-card', 720, 400, true );
		add_image_size( 'adam-field-gallery', 1000, 700, false );
	}
}
