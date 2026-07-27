<?php
/**
 * Community Manager module bootstrap.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Email_Service;
use ADAM\Comunidade\Module_Interface;

/**
 * Boots the isolated manager portal and revision workflow.
 */
final class Module implements Module_Interface {
	public function id(): string {
		return 'managers';
	}

	public function register(): void {
		if ( Schema::VERSION !== get_option( 'adam_comunidade_managers_db_version' ) ) {
			Schema::install();
			update_option( 'adam_comunidade_manager_routes_version', '', false );
		}
		$emails  = new Email_Service();
		$auth    = new Auth();
		$service = new Service( $emails );
		( new Portal( $auth, $service ) )->register();
		( new Admin( $service ) )->register();
	}
}
