<?php
/**
 * Listing quality and profile completeness.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates consistent, filterable completeness scores.
 */
final class Quality {
	public static function score( object|array $record ): int {
		$record = (array) $record;
		$groups = array(
			array( 'name' ),
			array( 'short_description', 'full_description' ),
			array( 'district', 'municipality', 'address' ),
			array( 'logo_id', 'cover_id' ),
			array( 'website', 'email', 'phone', 'facebook', 'instagram' ),
		);
		$complete = 0;
		foreach ( $groups as $fields ) {
			foreach ( $fields as $field ) {
				if ( ! empty( $record[ $field ] ) ) {
					++$complete;
					break;
				}
			}
		}
		return (int) apply_filters( 'adam_comunidade_profile_completeness', round( 100 * $complete / count( $groups ) ), $record );
	}
}
