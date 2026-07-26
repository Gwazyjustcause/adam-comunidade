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
			'draft'     => __( 'Draft', 'adam-comunidade' ),
			'published' => __( 'Published', 'adam-comunidade' ),
			'hidden'    => __( 'Hidden', 'adam-comunidade' ),
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
				'open'              => __( 'Open', 'adam-comunidade' ),
				'seasonal'          => __( 'Seasonal', 'adam-comunidade' ),
				'temporary_closure' => __( 'Temporarily closed', 'adam-comunidade' ),
				'private_events'    => __( 'Private events only', 'adam-comunidade' ),
				'maintenance'       => __( 'Under maintenance', 'adam-comunidade' ),
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
			'woodland'  => __( 'Woodland', 'adam-comunidade' ),
			'cqb'       => __( 'CQB', 'adam-comunidade' ),
			'hybrid'    => __( 'Hybrid', 'adam-comunidade' ),
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
			'check'     => __( 'Check', 'adam-comunidade' ),
			'parking'   => __( 'Parking', 'adam-comunidade' ),
			'shield'    => __( 'Shield', 'adam-comunidade' ),
			'gauge'     => __( 'Gauge', 'adam-comunidade' ),
			'bolt'      => __( 'Electricity', 'adam-comunidade' ),
			'water'     => __( 'Water', 'adam-comunidade' ),
			'camping'   => __( 'Camping', 'adam-comunidade' ),
			'fire'      => __( 'Fire / BBQ', 'adam-comunidade' ),
			'toilets'   => __( 'Toilets', 'adam-comunidade' ),
			'shop'      => __( 'Shop', 'adam-comunidade' ),
			'equipment' => __( 'Equipment', 'adam-comunidade' ),
			'battery'   => __( 'Battery', 'adam-comunidade' ),
			'food'      => __( 'Food', 'adam-comunidade' ),
			'indoor'    => __( 'Indoor', 'adam-comunidade' ),
			'moon'      => __( 'Night', 'adam-comunidade' ),
			'changing'  => __( 'Changing Room', 'adam-comunidade' ),
			'first-aid' => __( 'First Aid', 'adam-comunidade' ),
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
