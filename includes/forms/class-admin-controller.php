<?php
/**
 * Public forms administration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Forms;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router;
use ADAM\Comunidade\Helpers;

/**
 * Connects the shared form manager to the central admin router.
 */
final class Admin_Controller {
	public function __construct( private Manager $manager ) {}

	public function register(): void {
		Router::register_page(
			'forms',
			array(
				'title'      => __( 'Gestor de Formulários', 'adam-comunidade' ),
				'menu_title' => __( 'Formulários', 'adam-comunidade' ),
				'controller' => $this,
				'method'     => 'page',
			)
		);
		add_action( 'admin_post_adam_comunidade_save_forms', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function page(): void {
		$forms       = array();
		$types       = $this->manager->types();
		$field_types = $this->manager->field_types();
		foreach ( array_keys( $types ) as $type ) {
			$forms[ $type ] = $this->manager->get( $type );
		}
		require Helpers::path( 'admin/views/forms/manager.php' );
	}

	public function save(): void {
		Router::authorize();
		check_admin_referer( 'adam_comunidade_save_forms' );
		$input = isset( $_POST['forms'] ) && is_array( $_POST['forms'] ) ? $_POST['forms'] : array();
		$this->manager->save( $input );
		wp_safe_redirect( add_query_arg( 'updated', '1', Router::page_url( 'forms' ) ) );
		exit;
	}

	public function assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'adam-comunidade-forms' ) ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'adam-comunidade-forms', Helpers::url( 'assets/js/forms-admin.js' ), array( 'jquery', 'jquery-ui-sortable' ), ADAM_COMUNIDADE_VERSION, true );
		wp_enqueue_style( 'adam-comunidade-forms', Helpers::url( 'assets/css/forms-admin.css' ), array( 'adam-comunidade-admin' ), ADAM_COMUNIDADE_VERSION );
	}
}
