<?php
/**
 * Admin page controller.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Teams\Repository as Teams_Repository;
use ADAM\Comunidade\Fields\Repository as Fields_Repository;

/**
 * Renders plugin admin screens and notices.
 */
final class Admin {
	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( Helpers::class, 'render_admin_notices' ) );
	}

	/**
	 * Renders the dashboard view.
	 *
	 * @return void
	 */
	public function dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'adam-comunidade' ) );
		}

		$team_repository = new Teams_Repository();
		$team_counts     = $team_repository->status_counts();
		$recent_teams    = $team_repository->query(
			array( 'orderby' => 'created_at', 'order' => 'DESC', 'per_page' => 5 )
		)['items'];
		$updated_teams   = $team_repository->query(
			array( 'orderby' => 'updated_at', 'order' => 'DESC', 'per_page' => 5 )
		)['items'];
		$field_repository = new Fields_Repository();
		$field_counts     = $field_repository->statistics();
		$updated_fields   = $field_repository->query(
			array( 'orderby' => 'updated_at', 'order' => 'DESC', 'per_page' => 5 )
		)['items'];
		$view            = Helpers::path( 'admin/views/dashboard.php' );

		if ( is_readable( $view ) ) {
			require $view;
		}
	}

	/**
	 * Renders the settings view.
	 *
	 * @return void
	 */
	public function settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'adam-comunidade' ) );
		}

		$view = Helpers::path( 'admin/views/settings.php' );

		if ( is_readable( $view ) ) {
			require $view;
		}
	}
}
