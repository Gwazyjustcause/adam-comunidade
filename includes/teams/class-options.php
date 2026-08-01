<?php
/**
 * Extensible Teams option collections.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies labels and selectable values used across admin and public views.
 */
final class Options {
	/**
	 * Team statuses.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			'draft'     => __( 'Rascunho', 'adam-comunidade' ),
			'published' => __( 'Publicado', 'adam-comunidade' ),
			'hidden'    => __( 'Oculto', 'adam-comunidade' ),
		);
	}

	/**
	 * Recruitment statuses.
	 *
	 * @return array<string,string>
	 */
	public static function recruitment_statuses(): array {
		$defaults = array(
			'recruiting'     => __( 'A recrutar', 'adam-comunidade' ),
			'limited'        => __( 'Recrutamento limitado', 'adam-comunidade' ),
			'not_recruiting' => __( 'Sem recrutamento', 'adam-comunidade' ),
		);
		$filtered = (array) apply_filters(
			'adam_comunidade_recruitment_statuses',
			$defaults
		);

		return array(
			'recruiting'     => (string) ( $filtered['recruiting'] ?? $defaults['recruiting'] ),
			'limited'        => (string) ( $filtered['limited'] ?? $defaults['limited'] ),
			'not_recruiting' => (string) ( $filtered['not_recruiting'] ?? $defaults['not_recruiting'] ),
		);
	}

	/**
	 * Maps stored legacy values to the canonical recruitment statuses.
	 */
	public static function normalize_recruitment_status( mixed $value ): string {
		$status = sanitize_key( (string) $value );
		$status = array(
			'open'        => 'recruiting',
			'invite_only' => 'limited',
			'closed'      => 'not_recruiting',
		)[ $status ] ?? $status;

		return isset( self::recruitment_statuses()[ $status ] ) ? $status : '';
	}

	/**
	 * Available playing styles.
	 *
	 * @return array<string,string>
	 */
	public static function playing_styles(): array {
		$options = array(
			'woodland'   => __( 'Floresta', 'adam-comunidade' ),
			'cqb'        => __( 'CQB', 'adam-comunidade' ),
			'milsim'     => __( 'MilSim', 'adam-comunidade' ),
			'speedsoft'  => __( 'Speedsoft', 'adam-comunidade' ),
			'casual'     => __( 'Casual', 'adam-comunidade' ),
			'historical' => __( 'Histórico', 'adam-comunidade' ),
		);

		return (array) apply_filters( 'adam_comunidade_team_playing_styles', $options );
	}

	/**
	 * Available equipment tags.
	 *
	 * @return array<string,string>
	 */
	public static function equipment_tags(): array {
		$options = array(
			'rentals'           => __( 'Aluguer disponível', 'adam-comunidade' ),
			'night_vision'      => __( 'Compatível com visão noturna', 'adam-comunidade' ),
			'hpa'               => __( 'Compatível com HPA', 'adam-comunidade' ),
			'beginner_friendly' => __( 'Adequado para iniciantes', 'adam-comunidade' ),
		);

		return (array) apply_filters( 'adam_comunidade_team_equipment_tags', $options );
	}

	/**
	 * Decodes a stored string array.
	 *
	 * @param mixed $value JSON string or array.
	 * @return string[]
	 */
	public static function decode_list( mixed $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
		}

		$decoded = json_decode( (string) $value, true );

		return is_array( $decoded )
			? array_values( array_filter( array_map( 'sanitize_key', $decoded ) ) )
			: array();
	}

	/**
	 * Decodes an ordered list of Media Library attachment IDs.
	 *
	 * @param mixed $value JSON string or array.
	 * @return int[]
	 */
	public static function decode_ids( mixed $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
	}
}
