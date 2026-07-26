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
		add_action( 'wp_enqueue_scripts', array( $this, 'appearance' ), 100 );
	}

	/**
	 * Applies administrator-selected design tokens after a module requests CSS.
	 */
	public function appearance(): void {
		if ( ! wp_style_is( 'adam-comunidade', 'enqueued' ) ) {
			return;
		}
		$settings  = wp_parse_args( get_option( Settings::OPTION_NAME, array() ), Settings::defaults() );
		$primary   = sanitize_hex_color( $settings['primary_colour'] ?? '' ) ?: '#1d4ed8';
		$secondary = sanitize_hex_color( $settings['secondary_colour'] ?? '' ) ?: '#0f172a';
		$accent    = sanitize_hex_color( $settings['accent_colour'] ?? '' ) ?: '#f59e0b';
		wp_add_inline_style( 'adam-comunidade', ':root{--adam-primary:' . $primary . ';--adam-secondary:' . $secondary . ';--adam-accent:' . $accent . ';}' );
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
