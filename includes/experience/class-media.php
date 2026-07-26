<?php
/**
 * Image performance and accessibility defaults.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Applies WordPress image performance and accessibility defaults.
 */
final class Media {
	public function register(): void {
		add_action( 'init', array( $this, 'sizes' ) );
		add_filter( 'image_editor_output_format', array( $this, 'modern_formats' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attributes' ), 10, 3 );
		add_filter( 'wp_preload_resources', array( $this, 'preloads' ) );
	}

	public function sizes(): void {
		add_image_size( 'adam-community-thumb', 480, 300, true );
		add_image_size( 'adam-community-wide', 1280, 720, true );
	}

	public function modern_formats( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return apply_filters( 'adam_comunidade_image_output_formats', $formats );
	}

	public function attributes( array $attributes, \WP_Post $attachment, string|array $size ): array {
		unset( $attachment, $size );
		$attributes['decoding'] = 'async';
		if ( empty( $attributes['fetchpriority'] ) ) {
			$attributes['loading'] = $attributes['loading'] ?? 'lazy';
		}
		return $attributes;
	}

	public function preloads( array $preloads ): array {
		if ( is_singular( 'adam_news' ) && has_post_thumbnail() ) {
			$url = get_the_post_thumbnail_url( get_queried_object_id(), 'adam-community-wide' );
			if ( $url ) {
				$preloads[] = array( 'href' => $url, 'as' => 'image', 'fetchpriority' => 'high' );
			}
		}
		return $preloads;
	}
}
