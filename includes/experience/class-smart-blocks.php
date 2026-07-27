<?php
/**
 * Smart discovery shortcodes and blocks.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\View as Team_View;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\View as Field_View;

/**
 * Adds automatic homepage recommendations without duplicated content.
 */
final class Smart_Blocks {
	public function __construct( private Discovery $discovery ) {}

	public function register(): void {
		add_action( 'adam_comunidade_register_shortcodes', array( $this, 'shortcodes' ) );
		add_action( 'init', array( $this, 'blocks' ), 25 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 35 );
	}

	public function assets(): void {
		global $post;
		$content = $post instanceof \WP_Post ? $post->post_content : '';
		if ( ! str_contains( $content, '[adam_' ) && ! has_block( 'adam-comunidade/community-home', $content ) && ! has_block( 'adam-comunidade/community-map', $content ) && ! has_block( 'adam-comunidade/live-statistics', $content ) && ! has_block( 'adam-comunidade/latest-news', $content ) ) {
			return;
		}
		wp_enqueue_style( 'adam-experience', Helpers::url( 'assets/css/experience.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_script( 'adam-experience', Helpers::url( 'assets/js/experience.js' ), array(), ADAM_COMUNIDADE_VERSION, true );
		if ( ! wp_script_is( 'adam-experience', 'enqueued' ) ) {
			return;
		}
		wp_localize_script(
			'adam-experience',
			'adamExperience',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'adam_experience' ),
				'labels'      => array( 'view' => __( 'Ver', 'adam-comunidade' ), 'empty' => __( 'Nenhum conteúdo da comunidade corresponde à pesquisa.', 'adam-comunidade' ), 'error' => __( 'Não foi possível carregar os resultados da comunidade.', 'adam-comunidade' ) ),
				'groupLabels' => array( 'teams' => __( 'Equipas', 'adam-comunidade' ), 'fields' => __( 'Campos', 'adam-comunidade' ), 'partners' => __( 'Parceiros', 'adam-comunidade' ), 'institutions' => __( 'Instituições', 'adam-comunidade' ), 'brands' => __( 'Marcas', 'adam-comunidade' ), 'news' => __( 'Notícias', 'adam-comunidade' ) ),
			)
		);
	}

	public function shortcodes(): void {
		$sections = array(
			'adam_newest_teams' => array( 'teams', 'newest', false ),
			'adam_random_team' => array( 'teams', 'random', false ),
			'adam_featured_field' => array( 'fields', 'newest', true ),
			'adam_random_brand' => array( 'brands', 'random', false ),
		);
		foreach ( $sections as $shortcode => $settings ) {
			add_shortcode( $shortcode, static fn( array $attributes = array() ): string => do_shortcode( sprintf( '[adam_community_section type="%s" number="%d" order="%s" featured="%d"]', $settings[0], absint( $attributes['number'] ?? 1 ), $settings[1], $settings[2] ? 1 : 0 ) ) );
		}
		add_shortcode( 'adam_latest_news', static fn( array $attributes = array() ): string => Builder::news_cards( absint( $attributes['number'] ?? 6 ) ) );
		add_shortcode( 'adam_community_statistics', fn(): string => $this->statistics() );
		add_shortcode( 'adam_popular_team', array( $this, 'popular_team' ) );
		add_shortcode( 'adam_nearby_fields', array( $this, 'nearby_fields' ) );
	}

	public function blocks(): void {
		if ( ! wp_script_is( 'adam-comunidade-blocks', 'registered' ) ) {
			wp_register_script(
				'adam-comunidade-blocks',
				Helpers::url( 'assets/js/community-blocks.js' ),
				array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
				ADAM_COMUNIDADE_VERSION,
				true
			);
		}
		register_block_type(
			'adam-comunidade/live-statistics',
			array(
				'api_version'     => 3,
				'editor_script'   => 'adam-comunidade-blocks',
				'render_callback' => fn(): string => $this->statistics(),
			)
		);
		register_block_type(
			'adam-comunidade/latest-news',
			array(
				'api_version'   => 3,
				'editor_script' => 'adam-comunidade-blocks',
				'attributes'    => array( 'number' => array( 'type' => 'number', 'default' => 6 ) ),
				'render_callback' => static fn( array $attributes ): string => Builder::news_cards( absint( $attributes['number'] ?? 6 ) ),
			)
		);
		register_block_type(
			'adam-comunidade/community-home',
			array(
				'api_version'     => 3,
				'editor_script'   => 'adam-comunidade-blocks',
				'render_callback' => static fn(): string => ( new Builder() )->render(),
			)
		);
	}

	public function statistics( string $district = '' ): string {
		$stats = $this->discovery->statistics( $district );
		$values = array(
			__( 'Equipas', 'adam-comunidade' ) => $stats['teams'], __( 'Campos', 'adam-comunidade' ) => $stats['fields'], __( 'Parceiros', 'adam-comunidade' ) => $stats['partners'], __( 'Instituições', 'adam-comunidade' ) => $stats['institutions'], __( 'Marcas', 'adam-comunidade' ) => $stats['brands'],
		);
		if ( $stats['members'] ) {
			$values[ __( 'Jogadores registados', 'adam-comunidade' ) ] = $stats['members'];
		}
		$output = '<section class="adam-live-stats" aria-label="' . esc_attr__( 'Estatísticas da comunidade', 'adam-comunidade' ) . '">';
		foreach ( $values as $label => $value ) {
			$output .= '<div><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
		}
		$output .= '</section><section class="adam-stat-highlights">';
		$highlights = array(
			__( 'Equipa mais recente', 'adam-comunidade' ) => $stats['newest_team']->name ?? '',
			__( 'Campo mais recente', 'adam-comunidade' ) => $stats['newest_field']->name ?? '',
			__( 'Distrito mais ativo', 'adam-comunidade' ) => $stats['active_district'],
			__( 'Maior equipa', 'adam-comunidade' ) => $stats['largest_team']->name ?? '',
			__( 'Parceiro mais recente', 'adam-comunidade' ) => $stats['newest_partner']->name ?? '',
		);
		foreach ( $highlights as $label => $value ) {
			if ( $value ) {
				$output .= '<div><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
			}
		}
		return $output . '</section>';
	}

	public function popular_team(): string {
		return do_shortcode( '[adam_newest_teams number="1"]' );
	}

	public function nearby_fields( array $attributes = array() ): string {
		$attributes = shortcode_atts( array( 'district' => '', 'municipality' => '', 'number' => 4 ), $attributes, 'adam_nearby_fields' );
		$repository = new Field_Repository();
		$items = $repository->query(
			array(
				'status' => 'published',
				'district' => sanitize_text_field( $attributes['district'] ),
				'municipality' => sanitize_text_field( $attributes['municipality'] ),
				'per_page' => max( 1, min( 12, absint( $attributes['number'] ) ) ),
				'orderby' => 'updated_at',
				'order' => 'DESC',
			)
		)['items'];
		if ( ! $items ) {
			return '';
		}
		return '<section class="adam-community-widget" data-adam-widget="nearby-fields"><h2>' . esc_html__( 'Campos próximos', 'adam-comunidade' ) . '</h2><div class="adam-community-grid">' . implode( '', array_map( static fn( object $item ): string => Field_View::card( $item, $repository ), $items ) ) . '</div></section>';
	}
}
