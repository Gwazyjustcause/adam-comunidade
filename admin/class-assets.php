<?php
/**
 * Admin asset loader.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;

/**
 * Loads admin assets only on plugin screens.
 */
final class Assets {
	/**
	 * Registers asset hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues assets for ADAM Comunidade screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'adam-comunidade' ) ) {
			return;
		}

		wp_enqueue_style( 'adam-comunidade-admin', Helpers::url( 'assets/css/admin.css' ), array(), ADAM_COMUNIDADE_VERSION );

		if ( str_contains( $hook_suffix, 'adam-comunidade-settings' ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script(
				'adam-comunidade-admin',
				Helpers::url( 'assets/js/admin.js' ),
				array( 'jquery', 'wp-color-picker' ),
				ADAM_COMUNIDADE_VERSION,
				true
			);
		}
	}
}
