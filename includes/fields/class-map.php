<?php
/**
 * Reusable map support.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Produces individual-map URLs and an extensible marker contract.
 */
final class Map {
	/**
	 * Builds an OpenStreetMap embed URL.
	 *
	 * @param float $latitude  Latitude.
	 * @param float $longitude Longitude.
	 * @param float $radius    Bounding-box radius.
	 * @return string
	 */
	public static function embed_url( float $latitude, float $longitude, float $radius = 0.02 ): string {
		return 'https://www.openstreetmap.org/export/embed.html?bbox='
			. ( $longitude - $radius ) . '%2C'
			. ( $latitude - $radius ) . '%2C'
			. ( $longitude + $radius ) . '%2C'
			. ( $latitude + $radius )
			. '&layer=mapnik&marker=' . $latitude . '%2C' . $longitude;
	}

	/**
	 * Returns marker data through a future-compatible filter.
	 *
	 * Events, clustering, ratings, and archive maps can extend this contract.
	 *
	 * @param object[] $fields Field records.
	 * @return array<int,array<string,mixed>>
	 */
	public static function markers( array $fields ): array {
		$markers = array();

		foreach ( $fields as $field ) {
			if ( null === $field->latitude || null === $field->longitude ) {
				continue;
			}

			$markers[] = array(
				'id'        => (int) $field->id,
				'latitude'  => (float) $field->latitude,
				'longitude' => (float) $field->longitude,
				'title'     => $field->name,
				'url'       => Router::field_url( $field ),
			);
		}

		return (array) apply_filters( 'adam_comunidade_field_map_markers', $markers, $fields );
	}

	/**
	 * Returns a stable archive-map configuration for future map consumers.
	 *
	 * @param object[] $fields Field records.
	 * @return array<string,mixed>
	 */
	public static function configuration( array $fields ): array {
		$config = array(
			'markers'            => self::markers( $fields ),
			'clustering_enabled' => true,
			'filter_keys'        => array( 'district', 'municipality', 'playing_style', 'amenity_id', 'team_id' ),
		);

		return (array) apply_filters( 'adam_comunidade_field_map_config', $config, $fields );
	}
}
