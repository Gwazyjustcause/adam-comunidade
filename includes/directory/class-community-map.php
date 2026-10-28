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

	/** Read every page, so records after the repository page limit are included. */
	public static function markers(): array {
		$markers = array();
		foreach ( array( 'team' => new Team_Repository(), 'field' => new Field_Repository() ) as $type => $repository ) {
			foreach ( self::DISTRICTS as $district ) {
				$page = 1;
				do {
					$result = $repository->query( array( 'status' => 'published', 'district' => $district, 'page' => $page, 'per_page' => Config::MAX_PAGE_SIZE, 'orderby' => 'name', 'order' => 'ASC' ) );
					foreach ( $result['items'] as $item ) {
						if ( ! self::has_location( $item ) ) {
							continue;
						}
						$markers[] = array(
							'id' => (int) $item->id, 'type' => $type, 'name' => $item->name,
							'latitude' => (float) $item->latitude, 'longitude' => (float) $item->longitude,
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
