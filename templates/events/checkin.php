<?php
/**
 * Delegated member check-in route.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$token = sanitize_text_field( (string) get_query_var( 'adam_event_checkin' ) );
$handled = (bool) apply_filters( 'adam_comunidade_events_render_checkin', false, $token );
if ( ! $handled ) {
	get_header();
	echo '<main class="adam-events"><div class="adam-events__empty">'
		. esc_html__( 'O check-in de sócios não está disponível neste momento.', 'adam-comunidade' )
		. '<p><a href="' . esc_url( home_url( '/eventos/' ) ) . '">&larr; ' . esc_html__( 'Voltar aos eventos', 'adam-comunidade' ) . '</a></p>'
		. '</div></main>';
	get_footer();
}
