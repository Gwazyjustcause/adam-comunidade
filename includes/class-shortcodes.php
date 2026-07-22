<?php
/**
 * Shortcode registration service.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a future shortcode registration point.
 */
final class Shortcodes {
	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Fires the extension hook for shortcode modules.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		do_action( 'adam_comunidade_register_shortcodes' );
	}
}
