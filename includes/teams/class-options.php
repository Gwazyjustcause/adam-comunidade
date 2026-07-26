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
			'draft'     => __( 'Draft', 'adam-comunidade' ),
			'published' => __( 'Published', 'adam-comunidade' ),
			'hidden'    => __( 'Hidden', 'adam-comunidade' ),
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
				'recruiting'     => __( 'Recruiting', 'adam-comunidade' ),
				'limited'        => __( 'Limited recruitment', 'adam-comunidade' ),
				'not_recruiting' => __( 'Not recruiting', 'adam-comunidade' ),
				// Legacy values remain readable during upgrades.
				'open'           => __( 'Recruiting', 'adam-comunidade' ),
				'invite_only'    => __( 'Limited recruitment', 'adam-comunidade' ),
				'closed'         => __( 'Not recruiting', 'adam-comunidade' ),
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
			'woodland'   => __( 'Woodland', 'adam-comunidade' ),
			'cqb'        => __( 'CQB', 'adam-comunidade' ),
			'milsim'     => __( 'MilSim', 'adam-comunidade' ),
			'speedsoft'  => __( 'Speedsoft', 'adam-comunidade' ),
			'casual'     => __( 'Casual', 'adam-comunidade' ),
			'historical' => __( 'Historical', 'adam-comunidade' ),
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
			'rentals'         => __( 'Rentals Available', 'adam-comunidade' ),
			'night_vision'    => __( 'Night Vision Friendly', 'adam-comunidade' ),
			'hpa'             => __( 'HPA Friendly', 'adam-comunidade' ),
			'beginner_friendly' => __( 'Beginner Friendly', 'adam-comunidade' ),
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
