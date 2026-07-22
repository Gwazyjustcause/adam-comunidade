<?php
/**
 * Public asset registry.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Registers public assets and only enqueues them when requested.
 */
final class Assets {
	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers shared public assets for modules to enqueue as required.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style(
			'adam-comunidade',
			Helpers::url( 'assets/css/public.css' ),
			array(),
			ADAM_COMUNIDADE_VERSION
		);
		wp_register_script(
			'adam-comunidade',
			Helpers::url( 'assets/js/public.js' ),
			array(),
			ADAM_COMUNIDADE_VERSION,
			true
		);

		if ( apply_filters( 'adam_comunidade_enqueue_public_assets', false ) ) {
			wp_enqueue_style( 'adam-comunidade' );
			wp_enqueue_script( 'adam-comunidade' );
		}
	}
}
