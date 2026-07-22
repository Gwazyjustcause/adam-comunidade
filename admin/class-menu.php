<?php
/**
 * Admin menu registration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin administration pages.
 */
final class Menu {
	/**
	 * Admin page controller.
	 *
	 * @var Admin
	 */
	private Admin $admin;

	/**
	 * Constructor.
	 *
	 * @param Admin $admin Admin page controller.
	 */
	public function __construct( Admin $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Registers menu hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Creates the top-level menu and initial submenus.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$capability = (string) apply_filters( 'adam_comunidade_admin_capability', 'manage_options' );

		add_menu_page(
			__( 'ADAM Comunidade', 'adam-comunidade' ),
			__( 'ADAM Comunidade', 'adam-comunidade' ),
			$capability,
			'adam-comunidade',
			array( $this->admin, 'dashboard' ),
			'dashicons-groups',
			26
		);

		add_submenu_page(
			'adam-comunidade',
			__( 'Dashboard', 'adam-comunidade' ),
			__( 'Dashboard', 'adam-comunidade' ),
			$capability,
			'adam-comunidade',
			array( $this->admin, 'dashboard' )
		);

		/**
		 * Allows modules to add their own menu items between Dashboard and Settings.
		 *
		 * @param string $parent_slug Parent menu slug.
		 * @param string $capability  Required capability.
		 */
		do_action( 'adam_comunidade_admin_menu', 'adam-comunidade', $capability );

		add_submenu_page(
			'adam-comunidade',
			__( 'Settings', 'adam-comunidade' ),
			__( 'Settings', 'adam-comunidade' ),
			$capability,
			'adam-comunidade-settings',
			array( $this->admin, 'settings' )
		);
	}
}
