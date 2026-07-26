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
use ADAM\Comunidade\Directory\Repository as Directory_Repository;

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
		Router::register_page(
			'dashboard',
			array(
				'title'      => __( 'Painel', 'adam-comunidade' ),
				'menu_title' => __( 'Painel', 'adam-comunidade' ),
				'controller' => $this,
				'method'     => 'dashboard',
			)
		);
		Router::register_page(
			'settings',
			array(
				'title'      => __( 'Definições', 'adam-comunidade' ),
				'menu_title' => __( 'Definições', 'adam-comunidade' ),
				'controller' => $this,
				'method'     => 'settings',
			)
		);
	}

	/**
	 * Renders the dashboard view.
	 *
	 * @return void
	 */
	public function dashboard(): void {
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
		$directory_repository = new Directory_Repository();
		$directory_counts = array(
			'partner'     => $directory_repository->statistics( 'partner' ),
			'institution' => $directory_repository->statistics( 'institution' ),
			'brand'       => $directory_repository->statistics( 'brand' ),
		);
		$recent_directory = array_merge(
			$directory_repository->query( 'partner', array( 'orderby' => 'created_at', 'order' => 'DESC', 'per_page' => 5 ) )['items'],
			$directory_repository->query( 'institution', array( 'orderby' => 'created_at', 'order' => 'DESC', 'per_page' => 5 ) )['items'],
			$directory_repository->query( 'brand', array( 'orderby' => 'created_at', 'order' => 'DESC', 'per_page' => 5 ) )['items']
		);
		usort( $recent_directory, static fn( object $a, object $b ): int => strcmp( $b->created_at, $a->created_at ) );
		$recent_directory = array_slice( $recent_directory, 0, 6 );
		$featured_directory = array_merge(
			$directory_repository->query( 'partner', array( 'status' => 'published', 'featured' => 1, 'orderby' => 'priority', 'order' => 'DESC', 'per_page' => 4 ) )['items'],
			$directory_repository->query( 'institution', array( 'status' => 'published', 'featured' => 1, 'orderby' => 'priority', 'order' => 'DESC', 'per_page' => 4 ) )['items'],
			$directory_repository->query( 'brand', array( 'status' => 'published', 'featured' => 1, 'orderby' => 'priority', 'order' => 'DESC', 'per_page' => 4 ) )['items']
		);
		global $wpdb;
		$community_insights = array(
			'pending'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . \ADAM\Comunidade\Experience\Schema::submissions_table() . " WHERE status = 'pending'" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table and constant status.
			'claims'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . \ADAM\Comunidade\Experience\Schema::submissions_table() . " WHERE submission_type = 'claim' AND status = 'pending'" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'owners'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM " . \ADAM\Comunidade\Experience\Schema::owners_table() . " WHERE status = 'verified'" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'calendar' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . \ADAM\Comunidade\Experience\Schema::calendar_table() . ' WHERE status = %s AND start_at >= %s', 'published', current_time( 'mysql', true ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
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
		$view = Helpers::path( 'admin/views/settings.php' );

		if ( is_readable( $view ) ) {
			require $view;
		}
	}
}
