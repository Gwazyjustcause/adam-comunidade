<?php
/**
 * Configurable community homepage builder.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the automatic community homepage and its reusable blocks.
 */
final class Builder {
	private const OPTION = 'adam_comunidade_home_sections';

	public function register(): void {
		add_action( 'adam_comunidade_register_shortcodes', array( $this, 'shortcode' ) );
	}

	public function shortcode(): void {
		add_shortcode( 'adam_community_home', fn(): string => $this->render() );
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
		return '<section class="adam-universal-search"><label for="adam-universal-query">' . esc_html__( 'Search the community', 'adam-comunidade' ) . '</label><div><input id="adam-universal-query" type="search" data-adam-universal-query placeholder="' . esc_attr__( 'Teams, fields, partners, news…', 'adam-comunidade' ) . '"><button class="adam-community-button" type="button" data-adam-universal-submit>' . esc_html__( 'Pesquisar', 'adam-comunidade' ) . '</button></div><div data-adam-universal-results aria-live="polite"></div></section>';
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
		return array( 'search' => __( 'Universal Search', 'adam-comunidade' ), 'regions' => __( 'Centro de Portugal / Regions', 'adam-comunidade' ), 'statistics' => __( 'Community Statistics', 'adam-comunidade' ), 'teams' => __( 'Equipas', 'adam-comunidade' ), 'fields' => __( 'Campos', 'adam-comunidade' ), 'partners' => __( 'Partner Spotlight', 'adam-comunidade' ), 'institutions' => __( 'Instituições', 'adam-comunidade' ), 'brands' => __( 'Featured Brand', 'adam-comunidade' ), 'news' => __( 'Latest News', 'adam-comunidade' ), 'events' => __( 'Upcoming Events', 'adam-comunidade' ), 'map' => __( 'Community Map', 'adam-comunidade' ) );
	}

}
