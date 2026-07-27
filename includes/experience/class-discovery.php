<?php
/**
 * Cross-module community discovery.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\Router as Directory_Router;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;

/**
 * Aggregates searchable records and live statistics without copying content.
 */
final class Discovery {
	public function __construct(
		private Team_Repository $teams,
		private Field_Repository $fields,
		private Directory_Repository $directory
	) {}

	/**
	 * Universal search across all current and future-ready content types.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function search( string $term, array $filters = array(), int $limit = 8 ): array {
		$term    = sanitize_text_field( $term );
		$filters = array_map( 'sanitize_text_field', $filters );
		$key     = 'search|' . ( Related_Content::is_member() ? 'member' : 'public' ) . '|' . strtolower( $term ) . '|' . wp_json_encode( $filters ) . '|' . $limit;
		$results = Cache::remember(
			$key,
			function () use ( $term, $filters, $limit ): array {
				$common = array( 'status' => 'published', 'search' => $term, 'district' => $filters['district'] ?? '', 'per_page' => $limit, 'orderby' => 'name', 'order' => 'ASC' );
				$groups = array(
					'teams'        => array_map( fn( object $item ): array => $this->record( $item, 'team', Team_Router::team_url( $item ), 'groups' ), $this->teams->query( $common + array( 'municipality' => $filters['municipality'] ?? '', 'recruitment' => $filters['recruitment'] ?? '', 'playing_style' => $filters['playing_style'] ?? '' ) )['items'] ),
					'fields'       => array_map( fn( object $item ): array => $this->record( $item, 'field', Field_Router::field_url( $item ), 'location-alt' ), $this->fields->query( $common + array( 'municipality' => $filters['municipality'] ?? '', 'playing_style' => $filters['playing_style'] ?? '', 'amenity_id' => $this->facility_id( $filters['facility'] ?? '' ) ) )['items'] ),
					'partners'     => $this->directory_group( 'partner', $term, $filters, $limit ),
					'institutions' => $this->directory_group( 'institution', $term, $filters, $limit ),
					'news'         => array_map(
						static fn( \WP_Post $post ): array => array(
							'id' => $post->ID, 'type' => 'news', 'name' => get_the_title( $post ), 'description' => get_the_excerpt( $post ), 'url' => get_permalink( $post ), 'icon' => 'megaphone', 'district' => '',
						),
						News::latest( $limit, $term )
					),
				);
				return apply_filters( 'adam_comunidade_search_results', $groups, $term, $filters );
			},
			180
		);
		return $results;
	}

	/**
	 * Returns live totals and automatically derived highlights.
	 *
	 * @return array<string,mixed>
	 */
	public function statistics( string $district = '' ): array {
		return Cache::remember(
			'stats|' . strtolower( $district ),
			function () use ( $district ): array {
				$args = array( 'status' => 'published', 'district' => $district, 'per_page' => 1 );
				$teams  = $this->teams->query( $args );
				$fields = $this->fields->query( $args );
				$partners = $this->directory->query( 'partner', $args );
				$institutions = $this->directory->query( 'institution', $args );
				$newest_team = $this->teams->query( $args + array( 'orderby' => 'created_at', 'order' => 'DESC' ) )['items'][0] ?? null;
				$newest_field = $this->fields->query( $args + array( 'orderby' => 'created_at', 'order' => 'DESC' ) )['items'][0] ?? null;
				$largest_team = $this->teams->query( $args + array( 'orderby' => 'members', 'order' => 'DESC' ) )['items'][0] ?? null;
				$newest_partner = $this->directory->query( 'partner', $args + array( 'orderby' => 'created_at', 'order' => 'DESC' ) )['items'][0] ?? null;
				return array(
					'teams' => $teams['total'], 'fields' => $fields['total'], 'partners' => $partners['total'], 'institutions' => $institutions['total'],
					'members' => (int) apply_filters( 'adam_comunidade_members_count', 0 ),
					'newest_team' => $newest_team, 'newest_field' => $newest_field, 'largest_team' => $largest_team, 'newest_partner' => $newest_partner,
					'active_district' => $district ?: $this->most_active_district(),
				);
			},
			300
		);
	}

	/**
	 * Returns all map records with filter metadata.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function map_records( array $filters = array() ): array {
		$groups  = $this->search( '', $filters, 100 );
		$records = array();
		foreach ( array( 'teams', 'fields', 'partners', 'institutions' ) as $group ) {
			foreach ( $groups[ $group ] as $record ) {
				if ( null !== $record['latitude'] && null !== $record['longitude'] ) {
					$records[] = $record;
				}
			}
		}
		return $records;
	}

	private function directory_group( string $type, string $term, array $filters, int $limit ): array {
		$args = array(
			'status' => 'published', 'search' => $term, 'district' => $filters['district'] ?? '', 'category' => $filters[ $type . '_category' ] ?? '', 'featured' => $filters['featured'] ?? '', 'per_page' => $limit, 'orderby' => 'name', 'order' => 'ASC',
		);
		return array_map( fn( object $item ): array => $this->record( $item, $type, Directory_Router::entry_url( $item ), 'partner' === $type ? 'store' : ( 'institution' === $type ? 'bank' : 'tag' ) ), $this->directory->query( $type, $args )['items'] );
	}

	private function record( object $item, string $type, string $url, string $icon ): array {
		return array(
			'id' => (int) $item->id, 'type' => $type, 'name' => $item->name, 'description' => $item->short_description ?? '', 'url' => $url, 'icon' => $icon,
			'district' => $item->district ?? '', 'municipality' => $item->municipality ?? '', 'latitude' => null !== ( $item->latitude ?? null ) ? (float) $item->latitude : null, 'longitude' => null !== ( $item->longitude ?? null ) ? (float) $item->longitude : null,
			'playing_styles' => json_decode( $item->playing_styles ?? '[]', true ) ?: array(), 'category' => $item->category ?? '', 'featured' => (bool) ( $item->featured ?? false ),
		);
	}

	private function most_active_district(): string {
		global $wpdb;
		$teams = $wpdb->prefix . 'adam_teams';
		$fields = $wpdb->prefix . 'adam_fields';
		$entries = $wpdb->prefix . 'adam_directory_entries';
		$sql = "SELECT district,SUM(total) total FROM (
			SELECT district,COUNT(*) total FROM {$teams} WHERE status='published' AND district<>'' GROUP BY district
			UNION ALL SELECT district,COUNT(*) FROM {$fields} WHERE status='published' AND district<>'' GROUP BY district
			UNION ALL SELECT district,COUNT(*) FROM {$entries} WHERE status='published' AND district<>'' GROUP BY district
		) community GROUP BY district ORDER BY total DESC LIMIT 1";
		return (string) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function facility_id( mixed $facility ): int {
		$id = absint( $facility );
		if ( $id || ! $facility ) {
			return $id;
		}
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'adam_amenities WHERE context=%s AND amenity_key=%s AND status=%s LIMIT 1', 'field', sanitize_key( $facility ), 'active' );
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
