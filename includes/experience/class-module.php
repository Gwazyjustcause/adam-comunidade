<?php
/**
 * Community experience module.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Forms\Admin_Controller as Forms_Admin_Controller;
use ADAM\Comunidade\Forms\Manager as Forms_Manager;
use ADAM\Comunidade\Module_Interface;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

/**
 * Boots interactive discovery services independently.
 */
final class Module implements Module_Interface {
	public function id(): string {
		return 'experience';
	}

	public function register(): void {
		$news = new News();
		if ( Schema::VERSION !== get_option( 'adam_comunidade_experience_db_version' ) ) {
			Schema::install();
			\ADAM\Comunidade\Install::schedule_rewrite_flush();
		}
		$teams     = new Team_Repository();
		$fields    = new Field_Repository();
		$directory = new Directory_Repository();
		$discovery = new Discovery( $teams, $fields, $directory );
		$forms     = new Forms_Manager();
		$emails    = new Email_Service();

		( new Cache() )->register();
		$news->register();
		( new Router( $discovery ) )->register();
		( new Api_V2( $discovery, $teams, $fields, $directory ) )->register();
		( new Rest_Cache() )->register();
		( new Builder() )->register();
		( new Smart_Blocks( $discovery ) )->register();
		( new Related_Content( $teams, $fields, $directory ) )->register();
		( new Media() )->register();
		( new Portal( $forms, $emails ) )->register();
		( new Registry() )->register();
		( new Integrations() )->register();
		( new Notifications() )->register();

		if ( is_admin() ) {
			( new Forms_Admin_Controller( $forms, $emails ) )->register();
		}

		do_action( 'adam_comunidade_experience_registered', $this, $discovery );
	}
}
