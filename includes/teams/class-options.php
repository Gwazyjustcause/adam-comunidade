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
		return (array) apply_filters(
			'adam_comunidade_recruitment_statuses',
			array(
				'recruiting'     => __( 'A recrutar', 'adam-comunidade' ),
				'limited'        => __( 'Recrutamento limitado', 'adam-comunidade' ),
				'not_recruiting' => __( 'Sem recrutamento', 'adam-comunidade' ),
				// Legacy values remain readable during upgrades.
				'open'           => __( 'A recrutar', 'adam-comunidade' ),
				'invite_only'    => __( 'Recrutamento limitado', 'adam-comunidade' ),
				'closed'         => __( 'Sem recrutamento', 'adam-comunidade' ),
			)
		);
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
}
