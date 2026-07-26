<?php
/**
 * Image performance and accessibility defaults.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Helpers;
/**
 * Uses WordPress responsive media APIs and modern output formats.
 */
final class Media {
	public function register(): void {
		add_action( 'init', array( $this, 'sizes' ) );
		add_filter( 'image_editor_output_format', array( $this, 'modern_formats' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attributes' ), 10, 3 );
		add_filter( 'wp_preload_resources', array( $this, 'preloads' ) );
		add_shortcode( 'adam_rich_media', array( $this, 'shortcode' ) );
		Admin_Router::register_page( 'media', array( 'title' => __( 'Rich Media', 'adam-comunidade' ), 'controller' => $this, 'method' => 'admin_page' ) );
		add_action( 'admin_post_adam_rich_media_save', array( $this, 'save' ) );
		add_action( 'adam_comunidade_team_after_content', array( $this, 'for_team' ), 15 );
		add_action( 'adam_comunidade_field_after_content', array( $this, 'for_field' ), 15 );
		add_action( 'adam_comunidade_directory_entry_content', array( $this, 'for_directory' ), 15 );
	}

	public function admin_page(): void {
		global $wpdb;
		$items = $wpdb->get_results( 'SELECT * FROM ' . Schema::media_table() . ' ORDER BY object_type, object_id, sort_order LIMIT 200' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$types = self::types();
		require Helpers::path( 'admin/views/experience/media.php' );
	}

	public function save(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_rich_media_save' );
		$type = sanitize_key( wp_unslash( $_POST['media_type'] ?? '' ) );
		$url  = esc_url_raw( wp_unslash( $_POST['media_url'] ?? '' ), array( 'http', 'https' ) );
		if ( ! isset( self::types()[ $type ] ) || ! wp_http_validate_url( $url ) ) {
			wp_die( esc_html__( 'Choose a supported media type and valid URL.', 'adam-comunidade' ) );
		}
		global $wpdb;
		$wpdb->insert( Schema::media_table(), array( 'object_type' => sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) ), 'object_id' => absint( $_POST['object_id'] ?? 0 ), 'media_type' => $type, 'media_url' => $url, 'caption' => sanitize_text_field( wp_unslash( $_POST['caption'] ?? '' ) ), 'sort_order' => absint( $_POST['sort_order'] ?? 0 ) ) );
		wp_safe_redirect( Admin_Router::page_url( 'media' ) );
		exit;
	}

	public function shortcode( array $attributes ): string {
		$attributes = shortcode_atts( array( 'type' => '', 'id' => 0 ), $attributes, 'adam_rich_media' );
		$object_type = sanitize_key( $attributes['type'] );
		$object_id   = absint( $attributes['id'] );
		if ( ! $object_type || ! $object_id ) {
			return '';
		}
		global $wpdb;
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::media_table() . ' WHERE object_type = %s AND object_id = %d ORDER BY sort_order ASC', $object_type, $object_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $items ) {
			return '';
		}
		$html = '<section class="adam-rich-media"><h2>' . esc_html__( 'Media', 'adam-comunidade' ) . '</h2><div class="adam-community-grid">';
		foreach ( $items as $item ) {
			$label = $item->caption ?: ( self::types()[ $item->media_type ] ?? __( 'Open media', 'adam-comunidade' ) );
			if ( 'youtube' === $item->media_type ) {
				$embed = wp_oembed_get( $item->media_url, array( 'width' => 720 ) );
				$html .= '<figure>' . ( $embed ?: '<a href="' . esc_url( $item->media_url ) . '">' . esc_html( $label ) . '</a>' ) . '<figcaption>' . esc_html( $item->caption ) . '</figcaption></figure>';
			} elseif ( 'image_360' === $item->media_type ) {
				$html .= '<figure class="adam-media-360"><img src="' . esc_url( $item->media_url ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" decoding="async"><figcaption>' . esc_html( $label ) . '</figcaption></figure>';
			} else {
				$html .= '<a class="adam-community-button adam-community-button--ghost" href="' . esc_url( $item->media_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
			}
		}
		return $html . '</div></section>';
	}

	public function for_team( object $item ): void {
		echo do_shortcode( '[adam_rich_media type="team" id="' . absint( $item->id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function for_field( object $item ): void {
		echo do_shortcode( '[adam_rich_media type="field" id="' . absint( $item->id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function for_directory( object $item ): void {
		echo do_shortcode( '[adam_rich_media type="' . esc_attr( $item->entity_type ) . '" id="' . absint( $item->id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @return array<string,string>
	 */
	public static function types(): array {
		return (array) apply_filters( 'adam_comunidade_rich_media_types', array( 'image_360' => __( '360 image', 'adam-comunidade' ), 'youtube' => __( 'YouTube video', 'adam-comunidade' ), 'instagram' => __( 'Instagram post', 'adam-comunidade' ), 'virtual_tour' => __( 'Virtual tour', 'adam-comunidade' ), 'download' => __( 'Download', 'adam-comunidade' ) ) );
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
