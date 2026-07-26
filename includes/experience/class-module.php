<?php
/**
 * Phase 5 community experience module.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
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
			$news->register_content();
			Router::add_rewrite_rules();
			Api_V2::add_rewrite_rules();
			flush_rewrite_rules( false );
		}
		$teams     = new Team_Repository();
		$fields    = new Field_Repository();
		$directory = new Directory_Repository();
		$discovery = new Discovery( $teams, $fields, $directory );

		( new Cache() )->register();
		$news->register();
		( new Analytics() )->register();
		( new Router( $discovery ) )->register();
		( new Api_V2( $discovery, $teams, $fields, $directory ) )->register();
		( new Rest_Cache() )->register();
		( new Builder() )->register();
		( new Smart_Blocks( $discovery ) )->register();
		( new Related_Content( $teams, $fields, $directory ) )->register();
		( new Media() )->register();
		( new Portal() )->register();
		( new Calendar() )->register();
		( new Registry() )->register();
		( new Integrations() )->register();
		( new Notifications() )->register();

		if ( is_admin() ) {
			( new Admin_Tools( $directory ) )->register();
			( new Health() )->register();
			( new Import_Wizard() )->register();
		}

		do_action( 'adam_comunidade_experience_registered', $this, $discovery );
	}
}
