<?php
/**
 * Post type registration service.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the central registration point for future content types.
 */
final class Post_Types {
	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Allows modules to register their post types at a predictable point.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		do_action( 'adam_comunidade_register_post_types' );
	}
}
