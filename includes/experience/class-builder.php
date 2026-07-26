<?php
/**
 * Configurable community homepage builder.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Helpers;

/**
 * Stores enabled/orderable sections and renders them without page maintenance.
 */
final class Builder {
	private const OPTION = 'adam_comunidade_home_sections';

	public function register(): void {
		Admin_Router::register_page( 'builder', array( 'title' => __( 'Homepage Builder', 'adam-comunidade' ), 'controller' => $this, 'method' => 'page' ) );
		add_action( 'admin_post_adam_home_builder_save', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'adam_comunidade_register_shortcodes', array( $this, 'shortcode' ) );
	}

	public function shortcode(): void {
		add_shortcode( 'adam_community_home', fn(): string => $this->render() );
	}

	public function page(): void {
		$sections = self::sections();
		require Helpers::path( 'admin/views/experience/builder.php' );
	}

	public function save(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_home_builder_save' );
		$input = isset( $_POST['sections'] ) && is_array( $_POST['sections'] ) ? wp_unslash( $_POST['sections'] ) : array();
		$allowed = array_keys( self::definitions() );
		$sections = array();
		foreach ( $input as $row ) {
			$type = sanitize_key( $row['type'] ?? '' );
			if ( in_array( $type, $allowed, true ) && ! isset( $sections[ $type ] ) ) {
				$sections[ $type ] = array(
					'type' => $type, 'enabled' => empty( $row['enabled'] ) ? 0 : 1, 'number' => max( 1, min( 24, absint( $row['number'] ?? 6 ) ) ), 'order' => sanitize_key( $row['order'] ?? 'newest' ), 'category' => sanitize_key( $row['category'] ?? '' ), 'featured' => empty( $row['featured'] ) ? 0 : 1,
				);
			}
		}
		update_option( self::OPTION, array_values( $sections ), false );
		do_action( 'adam_comunidade_home_builder_saved', $sections );
		( new Cache() )->flush();
		wp_safe_redirect( Admin_Router::page_url( 'builder', array( 'updated' => 1 ) ) );
		exit;
	}

	public function admin_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'adam-comunidade-builder' ) ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'adam-builder-admin', Helpers::url( 'assets/js/builder-admin.js' ), array( 'jquery', 'jquery-ui-sortable' ), ADAM_COMUNIDADE_VERSION, true );
		wp_enqueue_style( 'adam-directory-admin', Helpers::url( 'assets/css/directory-admin.css' ), array( 'adam-comunidade-admin' ), ADAM_COMUNIDADE_VERSION );
	}

	public function render(): string {
		$output = '<div class="adam-community-home">';
		foreach ( self::sections() as $section ) {
			if ( empty( $section['enabled'] ) ) {
				continue;
			}
			$type = $section['type'];
			if ( in_array( $type, array( 'teams', 'fields', 'partners', 'institutions', 'brands' ), true ) ) {
				$output .= do_shortcode( sprintf( '[adam_community_section type="%s" number="%d" order="%s" category="%s" featured="%d"]', esc_attr( $type ), absint( $section['number'] ), esc_attr( $section['order'] ), esc_attr( $section['category'] ), absint( $section['featured'] ) ) );
			} elseif ( 'map' === $type ) {
				$output .= do_shortcode( '[adam_community_map]' );
			} elseif ( 'news' === $type ) {
				$output .= self::news_cards( absint( $section['number'] ) );
			} elseif ( 'search' === $type ) {
				$output .= self::search_form();
			} elseif ( 'regions' === $type ) {
				$output .= '<section class="adam-community-widget" data-adam-widget="regions"><h2>' . esc_html__( 'Centro de Portugal', 'adam-comunidade' ) . '</h2><div class="adam-region-links">';
				foreach ( Router::regions() as $slug => $label ) {
					$output .= '<a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . esc_html( $label ) . '</a>';
				}
				$output .= '</div></section>';
			} elseif ( 'statistics' === $type ) {
				$output .= do_shortcode( '[adam_community_statistics]' );
			} elseif ( 'events' === $type ) {
				$output .= '<section class="adam-community-widget"><h2>' . esc_html__( 'Upcoming Events', 'adam-comunidade' ) . '</h2><div class="adam-comunidade__empty">' . esc_html__( 'Event integration is ready for a future module.', 'adam-comunidade' ) . '</div></section>';
			}
		}
		return $output . '</div>';
	}

	public static function search_form(): string {
		return '<section class="adam-universal-search"><label for="adam-universal-query">' . esc_html__( 'Search the community', 'adam-comunidade' ) . '</label><div><input id="adam-universal-query" type="search" data-adam-universal-query placeholder="' . esc_attr__( 'Teams, fields, partners, news…', 'adam-comunidade' ) . '"><button class="adam-community-button" type="button" data-adam-universal-submit>' . esc_html__( 'Search', 'adam-comunidade' ) . '</button></div><div data-adam-universal-results aria-live="polite"></div></section>';
	}

	public static function news_cards( int $number = 6 ): string {
		$posts = News::latest( $number );
		$output = '<section class="adam-community-widget"><h2>' . esc_html__( 'Latest News', 'adam-comunidade' ) . '</h2><div class="adam-news-grid">';
		foreach ( $posts as $post ) {
			$output .= '<article class="adam-news-card">' . get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy' ) ) . '<div><time datetime="' . esc_attr( get_post_time( DATE_ATOM, true, $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time><h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3><p>' . esc_html( get_the_excerpt( $post ) ) . '</p></div></article>';
		}
		return $output . '</div></section>';
	}

	public static function sections(): array {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) && $saved ? $saved : array_values( array_map( static fn( string $type ): array => array( 'type' => $type, 'enabled' => in_array( $type, array( 'search', 'statistics', 'teams', 'fields', 'partners', 'news', 'map' ), true ) ? 1 : 0, 'number' => 6, 'order' => 'newest', 'category' => '', 'featured' => 0 ), array_keys( self::definitions() ) ) );
	}

	public static function definitions(): array {
		return array( 'search' => __( 'Universal Search', 'adam-comunidade' ), 'regions' => __( 'Centro de Portugal / Regions', 'adam-comunidade' ), 'statistics' => __( 'Community Statistics', 'adam-comunidade' ), 'teams' => __( 'Teams', 'adam-comunidade' ), 'fields' => __( 'Fields', 'adam-comunidade' ), 'partners' => __( 'Partner Spotlight', 'adam-comunidade' ), 'institutions' => __( 'Institutions', 'adam-comunidade' ), 'brands' => __( 'Featured Brand', 'adam-comunidade' ), 'news' => __( 'Latest News', 'adam-comunidade' ), 'events' => __( 'Upcoming Events', 'adam-comunidade' ), 'map' => __( 'Community Map', 'adam-comunidade' ) );
	}

}
