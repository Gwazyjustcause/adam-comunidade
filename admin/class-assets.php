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
		$allowed = array(
			'toplevel_page_adam-comunidade',
			'adam-comunidade_page_adam-comunidade-settings',
		);
		$is_module_screen = str_contains( $hook_suffix, 'adam-comunidade-team' )
			|| str_contains( $hook_suffix, 'adam-comunidade-field' )
			|| str_contains( $hook_suffix, 'adam-comunidade-directory' )
			|| str_contains( $hook_suffix, 'adam-comunidade-partner' )
			|| str_contains( $hook_suffix, 'adam-comunidade-institution' )
			|| str_contains( $hook_suffix, 'adam-comunidade-brand' )
			|| str_contains( $hook_suffix, 'adam-comunidade-builder' )
			|| str_contains( $hook_suffix, 'adam-comunidade-analytics' )
			|| str_contains( $hook_suffix, 'adam-comunidade-tools' )
			|| str_contains( $hook_suffix, 'adam-comunidade-moderation' )
			|| str_contains( $hook_suffix, 'adam-comunidade-calendar' )
			|| str_contains( $hook_suffix, 'adam-comunidade-media' )
			|| str_contains( $hook_suffix, 'adam-comunidade-health' )
			|| str_contains( $hook_suffix, 'adam-comunidade-import-wizard' );

		if ( ! in_array( $hook_suffix, $allowed, true ) && ! $is_module_screen ) {
			return;
		}

		wp_enqueue_style( 'adam-comunidade-admin', Helpers::url( 'assets/css/admin.css' ), array(), ADAM_COMUNIDADE_VERSION );

		if ( 'adam-comunidade_page_adam-comunidade-settings' === $hook_suffix ) {
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
