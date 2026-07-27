<?php
/**
 * Teams directory hero carousel configuration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the same carousel contract as the Fields directory.
 */
final class Hero_Carousel {
	public const OPTION = 'adam_comunidade_teams_hero_carousel';

	/**
	 * @return array<string,mixed>
	 */
	public static function settings(): array {
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'source'           => 'manual',
				'autoplay'         => 1,
				'interval'         => 6000,
				'minimum_featured' => 3,
				'images'           => array(),
			)
		);
	}

	/**
	 * @param array<string,mixed> $input Submitted settings.
	 */
	public static function save( array $input ): void {
		$source = sanitize_key( (string) ( $input['source'] ?? 'manual' ) );
		if ( ! in_array( $source, array( 'manual', 'published_teams' ), true ) ) {
			$source = 'manual';
		}
		$enabled = array_map( 'absint', array_keys( (array) ( $input['enabled'] ?? array() ) ) );
		$images  = array();
		foreach ( array_unique( array_map( 'absint', (array) ( $input['image_ids'] ?? array() ) ) ) as $id ) {
			if ( $id && wp_attachment_is_image( $id ) ) {
				$images[] = array( 'id' => $id, 'enabled' => in_array( $id, $enabled, true ) ? 1 : 0 );
			}
		}
		update_option(
			self::OPTION,
			array(
				'source'           => $source,
				'autoplay'         => empty( $input['autoplay'] ) ? 0 : 1,
				'interval'         => max( 3000, min( 15000, absint( $input['interval'] ?? 6000 ) ) ),
				'minimum_featured' => max( 2, min( 12, absint( $input['minimum_featured'] ?? 3 ) ) ),
				'images'           => $images,
			),
			false
		);
	}

	/**
	 * @return array<int,array{id:int,url:string,alt:string}>
	 */
	public static function slides( Repository $repository ): array {
		$settings = self::settings();
		if ( 'published_teams' === $settings['source'] ) {
			$automatic = self::team_slides( $repository );
			if ( count( $automatic ) >= absint( $settings['minimum_featured'] ) ) {
				return $automatic;
			}
		}
		$slides = array();
		foreach ( (array) $settings['images'] as $image ) {
			$image = (array) $image;
			$id    = absint( $image['id'] ?? 0 );
			$url   = $id && ! empty( $image['enabled'] ) ? wp_get_attachment_image_url( $id, 'adam-team-cover' ) : false;
			if ( $url ) {
				$slides[] = array(
					'id'  => $id,
					'url' => $url,
					'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				);
			}
		}
		return $slides;
	}

	/**
	 * @return array<int,array{id:int,url:string,alt:string}>
	 */
	private static function team_slides( Repository $repository ): array {
		$result = $repository->query(
			array(
				'status'                 => 'published',
				'orderby'                => 'updated_at',
				'order'                  => 'DESC',
				'per_page'               => 100,
				'prioritize_associated' => true,
			)
		);
		$slides = array();
		$seen   = array();
		foreach ( $result['items'] as $team ) {
			$id  = absint( $team->cover_id ?? 0 );
			$url = $id && empty( $seen[ $id ] ) ? wp_get_attachment_image_url( $id, 'adam-team-cover' ) : false;
			if ( $url ) {
				$slides[]  = array( 'id' => $id, 'url' => $url, 'alt' => sprintf( __( 'Equipa de airsoft %s', 'adam-comunidade' ), $team->name ) );
				$seen[ $id ] = true;
			}
			if ( 12 === count( $slides ) ) {
				break;
			}
		}
		return $slides;
	}
}
