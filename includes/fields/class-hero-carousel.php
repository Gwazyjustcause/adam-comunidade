<?php
/**
 * Fields directory hero carousel configuration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves curated and approved-field images for the public hero.
 */
final class Hero_Carousel {
	public const OPTION = 'adam_comunidade_fields_hero_carousel';

	/**
	 * @return array<string,mixed>
	 */
	public static function settings(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return wp_parse_args(
			$stored,
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
		if ( ! in_array( $source, array( 'manual', 'approved_fields' ), true ) ) {
			$source = 'manual';
		}

		$enabled = array_map( 'absint', array_keys( (array) ( $input['enabled'] ?? array() ) ) );
		$images  = array();
		foreach ( array_unique( array_map( 'absint', (array) ( $input['image_ids'] ?? array() ) ) ) as $image_id ) {
			if ( $image_id && wp_attachment_is_image( $image_id ) ) {
				$images[] = array(
					'id'      => $image_id,
					'enabled' => in_array( $image_id, $enabled, true ) ? 1 : 0,
				);
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
	 * @param Repository $repository Fields repository.
	 * @return array<int,array<string,mixed>>
	 */
	public static function slides( Repository $repository ): array {
		$settings = self::settings();
		$manual   = self::manual_slides( (array) $settings['images'] );
		if ( 'approved_fields' === $settings['source'] ) {
			$automatic = self::approved_field_slides( $repository );
			if ( count( $automatic ) >= absint( $settings['minimum_featured'] ) ) {
				return $automatic;
			}
		}
		return $manual;
	}

	/**
	 * @param array<int,mixed> $images Configured images.
	 * @return array<int,array<string,mixed>>
	 */
	private static function manual_slides( array $images ): array {
		$slides = array();
		foreach ( $images as $image ) {
			$image = (array) $image;
			$id    = absint( $image['id'] ?? 0 );
			if ( ! $id || empty( $image['enabled'] ) ) {
				continue;
			}
			$url = wp_get_attachment_image_url( $id, 'adam-field-cover' );
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
	 * @return array<int,array<string,mixed>>
	 */
	private static function approved_field_slides( Repository $repository ): array {
		$result = $repository->query(
			array(
				'status'   => 'published',
				'orderby'  => 'updated_at',
				'order'    => 'DESC',
				'per_page' => 100,
			)
		);
		$fields = array_values(
			array_filter(
				$result['items'],
				static fn( object $field ): bool => ! empty( $field->cover_id )
			)
		);
		usort( $fields, static fn( object $a, object $b ): int => (int) ( $b->featured ?? 0 ) <=> (int) ( $a->featured ?? 0 ) );

		$slides = array();
		$seen   = array();
		foreach ( $fields as $field ) {
			$id = absint( $field->cover_id );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$url = wp_get_attachment_image_url( $id, 'adam-field-cover' );
			if ( $url ) {
				$slides[] = array(
					'id'  => $id,
					'url' => $url,
					'alt' => sprintf( __( 'Campo de airsoft %s', 'adam-comunidade' ), $field->name ),
				);
				$seen[ $id ] = true;
			}
			if ( 12 === count( $slides ) ) {
				break;
			}
		}
		return $slides;
	}
}
