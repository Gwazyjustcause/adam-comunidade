<?php
/**
 * Fields selectable options.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies extensible field option collections.
 */
final class Options {
	/**
	 * Field statuses.
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
	 * Public availability states, independent of publishing status.
	 *
	 * @return array<string,string>
	 */
	public static function availability_statuses(): array {
		return (array) apply_filters(
			'adam_comunidade_field_availability_statuses',
			array(
				'open'              => __( 'Aberto', 'adam-comunidade' ),
				'seasonal'          => __( 'Sazonal', 'adam-comunidade' ),
				'temporary_closure' => __( 'Temporariamente encerrado', 'adam-comunidade' ),
				'private_events'    => __( 'Apenas eventos privados', 'adam-comunidade' ),
				'maintenance'       => __( 'Em manutenção', 'adam-comunidade' ),
			)
		);
	}

	/**
	 * Field playing styles.
	 *
	 * @return array<string,string>
	 */
	public static function playing_styles(): array {
		$options = array(
			'woodland'  => __( 'Floresta', 'adam-comunidade' ),
			'cqb'       => __( 'CQB', 'adam-comunidade' ),
			'hybrid'    => __( 'Híbrido', 'adam-comunidade' ),
			'milsim'    => __( 'MilSim', 'adam-comunidade' ),
			'speedsoft' => __( 'Speedsoft', 'adam-comunidade' ),
		);

		return (array) apply_filters( 'adam_comunidade_field_playing_styles', $options );
	}

	/**
	 * Icons that amenity administrators can select.
	 *
	 * @return array<string,string>
	 */
	public static function amenity_icons(): array {
		return array(
			'check'     => __( 'Verificação', 'adam-comunidade' ),
			'parking'   => __( 'Estacionamento', 'adam-comunidade' ),
			'shield'    => __( 'Proteção', 'adam-comunidade' ),
			'gauge'     => __( 'Cronógrafo', 'adam-comunidade' ),
			'bolt'      => __( 'Eletricidade', 'adam-comunidade' ),
			'water'     => __( 'Água', 'adam-comunidade' ),
			'camping'   => __( 'Campismo', 'adam-comunidade' ),
			'fire'      => __( 'Fogueira / churrasco', 'adam-comunidade' ),
			'toilets'   => __( 'Casas de banho', 'adam-comunidade' ),
			'shop'      => __( 'Loja', 'adam-comunidade' ),
			'equipment' => __( 'Equipamento', 'adam-comunidade' ),
			'battery'   => __( 'Carregamento de baterias', 'adam-comunidade' ),
			'food'      => __( 'Alimentação', 'adam-comunidade' ),
			'indoor'    => __( 'Interior', 'adam-comunidade' ),
			'moon'      => __( 'Noturno', 'adam-comunidade' ),
			'changing'  => __( 'Balneário', 'adam-comunidade' ),
			'first-aid' => __( 'Primeiros socorros', 'adam-comunidade' ),
		);
	}

	/**
	 * Decodes a stored JSON list.
	 *
	 * @param mixed $value Stored value.
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
