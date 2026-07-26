<?php
/**
 * Generic cross-module relationships.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Stores source/relation/target edges without coupling modules.
 */
final class Relationship_Repository {
	/**
	 * Replaces one set of outgoing targets.
	 *
	 * @param int[] $target_ids Target IDs.
	 */
	public function sync( string $source_type, int $source_id, string $relation, string $target_type, array $target_ids ): void {
		global $wpdb;
		$table = Schema::relationships_table();
		$wpdb->delete(
			$table,
			array(
				'source_type' => $source_type,
				'source_id'   => $source_id,
				'relation'    => $relation,
				'target_type' => $target_type,
			)
		);
		foreach ( array_unique( array_filter( array_map( 'absint', $target_ids ) ) ) as $target_id ) {
			$wpdb->insert(
				$table,
				array(
					'source_type' => $source_type,
					'source_id'   => $source_id,
					'relation'    => $relation,
					'target_type' => $target_type,
					'target_id'   => $target_id,
					'created_at'  => current_time( 'mysql', true ),
				)
			);
		}
	}

	/**
	 * Returns target IDs for an outgoing edge.
	 *
	 * @return int[]
	 */
	public function target_ids( string $source_type, int $source_id, string $relation, string $target_type ): array {
		global $wpdb;
		$sql = $wpdb->prepare(
			'SELECT target_id FROM ' . Schema::relationships_table() . ' WHERE source_type=%s AND source_id=%d AND relation=%s AND target_type=%s',
			$source_type,
			$source_id,
			$relation,
			$target_type
		);
		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns relationships in either direction for public reciprocal displays.
	 *
	 * @return object[]
	 */
	public function connected( string $type, int $id ): array {
		global $wpdb;
		$sql = $wpdb->prepare(
			'SELECT * FROM ' . Schema::relationships_table() . ' WHERE (source_type=%s AND source_id=%d) OR (target_type=%s AND target_id=%d) ORDER BY relation',
			$type,
			$id,
			$type,
			$id
		);
		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
