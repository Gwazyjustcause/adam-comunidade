<?php
/**
 * Optional ADAM ecosystem integrations.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Managed_Pages;

/**
 * Advertises stable integration points without hard dependencies.
 */
final class Integrations {
	public function register(): void {
		add_action( 'init', array( $this, 'discover' ), 5 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
	}

	public function discover(): void {
		$available = array(
			'members' => defined( 'ADAM_MEMBERS_VERSION' ) || class_exists( 'ADAM_Members' ),
			'bot'     => defined( 'ADAM_BOT_VERSION' ) || class_exists( 'ADAM_Bot' ),
			'events'  => defined( 'ADAM_EVENTS_VERSION' ) || class_exists( 'ADAM_Events' ),
		);
		foreach ( $available as $id => $active ) {
			Registry::add( 'integrations', $id, array( 'active' => $active ) );
		}
		do_action( 'adam_comunidade_integrations_ready', $available );
	}

	public function body_classes( array $classes ): array {
		if ( Managed_Pages::is_current( 'community' ) || get_query_var( 'adam_submission' ) || get_query_var( 'adam_owner_dashboard' ) || get_query_var( 'adam_calendar' ) ) {
			$classes[] = 'adam-comunidade-view';
			$classes[] = 'adam-comunidade-theme-' . sanitize_html_class( wp_get_theme()->get_stylesheet() );
		}
		return $classes;
	}
}
