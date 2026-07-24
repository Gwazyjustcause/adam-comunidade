<?php
/**
 * Reusable amenity vocabulary repository.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Manages amenity definitions independently from fields.
 */
final class Amenity_Repository {
	/**
	 * Returns amenities for a reusable context.
	 *
	 * @param string $context     Vocabulary context.
	 * @param bool   $active_only Limit to visible amenities.
	 * @return object[]
	 */
	public function all( string $context = 'field', bool $active_only = false ): array {
		global $wpdb;

		$sql  = 'SELECT * FROM ' . Schema::amenities_table() . ' WHERE context = %s';
		$args = array( sanitize_key( $context ) );

		if ( $active_only ) {
			$sql   .= ' AND status = %s';
			$args[] = 'active';
		}

		$sql .= ' ORDER BY sort_order ASC, label ASC';

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) ?: array();
	}

	/**
	 * Creates an amenity definition.
	 *
	 * @param array<string,mixed> $data Sanitized definition.
	 * @return int|false
	 */
	public function create( array $data ): int|false {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$result = $wpdb->insert(
			Schema::amenities_table(),
			array(
				'context'     => sanitize_key( is_scalar( $data['context'] ?? null ) ? (string) $data['context'] : 'field' ),
				'amenity_key' => sanitize_key( is_scalar( $data['amenity_key'] ?? null ) ? (string) $data['amenity_key'] : '' ),
				'label'       => sanitize_text_field( (string) ( $data['label'] ?? '' ) ),
				'icon'        => $this->icon( $data['icon'] ?? 'check' ),
				'status'      => 'hidden' === ( $data['status'] ?? '' ) ? 'hidden' : 'active',
				'sort_order'  => absint( $data['sort_order'] ?? 0 ),
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Updates an amenity definition.
	 *
	 * @param int                 $id   Amenity ID.
	 * @param array<string,mixed> $data Sanitized definition.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			Schema::amenities_table(),
			array(
				'label'      => sanitize_text_field( (string) ( $data['label'] ?? '' ) ),
				'icon'       => $this->icon( $data['icon'] ?? 'check' ),
				'status'     => 'active' === ( $data['status'] ?? '' ) ? 'active' : 'hidden',
				'sort_order' => absint( $data['sort_order'] ?? 0 ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'      => absint( $id ),
				'context' => 'field',
			)
		);
	}

	/**
	 * Restricts icons to the public renderer's allowlist.
	 *
	 * @param mixed $icon Submitted icon.
	 * @return string
	 */
	private function icon( mixed $icon ): string {
		$icon = is_scalar( $icon ) ? sanitize_key( (string) $icon ) : 'check';

		return isset( Options::amenity_icons()[ $icon ] ) ? $icon : 'check';
	}
}
