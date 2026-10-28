<?php
/**
 * Public map of teams and fields in the six central districts.
 *
 * @package ADAM_Comunidade
 */
namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Config;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;

final class Community_Map {

	public const DISTRICTS = array( 'Aveiro', 'Viseu', 'Guarda', 'Coimbra', 'Castelo Branco', 'Leiria' );
	private const CENTRES = array(
		'Aveiro' => array( 40.6405, -8.6538 ), 'Viseu' => array( 40.6566, -7.9125 ),
		'Guarda' => array( 40.5373, -7.2676 ), 'Coimbra' => array( 40.2033, -8.4103 ),
		'Castelo Branco' => array( 39.8222, -7.4909 ), 'Leiria' => array( 39.7436, -8.8071 ),
	);

	/** Read every page, so records after the repository page limit are included. */
	public static function markers(): array {
		$markers = array();
		foreach ( array( 'team' => new Team_Repository(), 'field' => new Field_Repository() ) as $type => $repository ) {
			foreach ( self::DISTRICTS as $district ) {
				$page = 1;
				do {
					$result = $repository->query( array( 'status' => 'published', 'district' => $district, 'page' => $page, 'per_page' => Config::MAX_PAGE_SIZE, 'orderby' => 'name', 'order' => 'ASC' ) );
					foreach ( $result['items'] as $item ) {
						$location = self::location( $item );
						if ( null === $location ) {
							continue;
						}
						$markers[] = array(
							'id' => (int) $item->id, 'type' => $type, 'name' => $item->name,
							'latitude' => $location['latitude'], 'longitude' => $location['longitude'],
							'approximate' => $location['approximate'],
							'location_scope' => $location['scope'], 'location_note' => $location['note'],
							'district' => $district, 'municipality' => $item->municipality ?? '',
							'url' => 'team' === $type ? Team_Router::team_url( $item ) : Field_Router::field_url( $item ),
						);
					}
					++$page;
				} while ( $page <= $result['pages'] && $result['items'] );
			}
		}
		return $markers;
	}

	/** Prefer precise coordinates, then the concelho seat, then the district capital. */
	public static function location( object $item ): ?array {
		$centre = self::CENTRES[ $item->district ?? '' ] ?? null;
		if ( null === $centre ) {
			return null;
		}
		$precise = self::has_location( $item );
		$scope = $precise ? 'coordinates' : 'district';
		$note = $precise ? '' : __( 'Localização aproximada · centro do distrito', 'adam-comunidade' );
		if ( ! $precise && '' !== trim( (string) ( $item->municipality ?? '' ) ) ) {
			static $municipalities = null;
			if ( null === $municipalities ) {
				$municipalities = json_decode( (string) file_get_contents( ADAM_COMUNIDADE_PATH . 'includes/data/municipality-centres.json' ), true ) ?: array();
			}
			$key = strtolower( remove_accents( trim( (string) $item->municipality ) ) );
			$key = preg_replace( '/^(?:concelho|municipio|cidade)\s+(?:de\s+)?/', '', $key );
			$key = preg_replace( '/[^a-z0-9]/', '', $key );
			$municipality = $municipalities[ $item->district ][ $key ] ?? null;
			if ( $municipality ) {
				$centre = array( $municipality['latitude'], $municipality['longitude'] );
				$scope = 'municipality';
				$note = sprintf( __( 'Localização aproximada · centro do concelho de %s', 'adam-comunidade' ), $municipality['name'] );
			}
		}
		return array(
			'latitude' => $precise ? (float) $item->latitude : $centre[0],
			'longitude' => $precise ? (float) $item->longitude : $centre[1],
			'approximate' => ! $precise,
			'scope' => $scope, 'note' => $note,
		);
	}

	/** Reject missing, invalid, and clearly misplaced coordinates. */
	public static function has_location( object $item ): bool {
		return in_array( $item->district ?? '', self::DISTRICTS, true )
			&& is_numeric( $item->latitude ?? null ) && is_numeric( $item->longitude ?? null )
			&& (float) $item->latitude >= 39.15 && (float) $item->latitude <= 41.3
			&& (float) $item->longitude >= -9.6 && (float) $item->longitude <= -6.7;
	}

	public static function render(): string {
		$markers = self::markers();
		$id = wp_unique_id( 'adam-community-map-' );
		ob_start();
		include ADAM_COMUNIDADE_PATH . 'templates/directory/community-map.php';
		return (string) ob_get_clean();
	}
}
