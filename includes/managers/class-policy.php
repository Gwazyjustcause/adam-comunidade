<?php
/**
 * Community Manager entity and revision policy.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes the entity types and fields exposed to moderated editing.
 */
final class Policy {
	/**
	 * Returns entity types supported by assignments and revisions.
	 *
	 * @return string[]
	 */
	public static function entity_types(): array {
		$types = apply_filters( 'adam_comunidade_manager_revision_entity_types', array( 'team', 'field', 'partner', 'institution' ) );
		return array_values( array_unique( array_map( 'sanitize_key', is_array( $types ) ? $types : array() ) ) );
	}

	/**
	 * Returns fields a manager may propose for one entity type.
	 *
	 * @return string[]
	 */
	public static function editable_fields( string $type ): array {
		$common = array( 'name', 'cover_id', 'short_description', 'full_description', 'district', 'municipality', 'address', 'latitude', 'longitude', 'maps_url', 'website', 'facebook', 'instagram', 'whatsapp', 'email', 'phone', 'playing_styles' );
		if ( 'team' === $type ) {
			$fields = array_merge( $common, array( 'short_name', 'logo_id', 'gallery', 'team_colour', 'discord', 'youtube', 'tiktok', 'founded', 'members', 'recruitment_status', 'recruitment_min_age', 'recruitment_experience', 'recruitment_equipment', 'recruitment_training', 'equipment_tags' ) );
		} elseif ( 'field' === $type ) {
			$fields = array_merge( $common, array( 'availability', 'rules', 'opening_hours', 'max_players', 'min_players', 'recommended_players' ) );
		} elseif ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			$fields = array( 'name', 'logo_id', 'cover_id', 'short_description', 'full_description', 'website', 'facebook', 'instagram', 'email', 'phone', 'address', 'district', 'latitude', 'longitude', 'category', 'benefits', 'member_benefits', 'country', 'popular_products' );
		} else {
			$fields = array();
		}
		$fields = apply_filters( 'adam_comunidade_manager_revision_fields', $fields, sanitize_key( $type ) );
		return array_values( array_unique( array_map( 'sanitize_key', is_array( $fields ) ? $fields : array() ) ) );
	}

	/**
	 * Decodes list fields persisted as JSON by entity repositories.
	 *
	 * @param array<string,mixed> $input Entity input.
	 * @return array<string,mixed>
	 */
	public static function decode_lists( string $type, array $input ): array {
		$keys = 'team' === $type
			? array( 'gallery', 'playing_styles', 'equipment_tags' )
			: array( 'playing_styles' );
		foreach ( $keys as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) {
				$decoded = json_decode( $input[ $key ], true );
				$input[ $key ] = is_array( $decoded ) ? $decoded : array();
			}
		}
		return $input;
	}

	private function __construct() {}
}
