<?php
/**
 * Homepage components, blocks, and community map.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;

/**
 * Provides reusable, server-rendered homepage building blocks.
 */
final class Components {
	public function __construct(
		private Repository $repository,
		private Relationship_Repository $relationships
	) {}

	public function register(): void {
		add_action( 'adam_comunidade_register_shortcodes', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 25 );
		add_action( 'adam_comunidade_team_after_content', array( $this, 'team_connections' ) );
		add_action( 'adam_comunidade_field_after_content', array( $this, 'field_connections' ) );
	}

	public function register_shortcodes(): void {
		add_shortcode( 'adam_featured_partner', fn( array $attributes = array() ): string => $this->highlight( 'partner', 'featured' ) );
		add_shortcode( 'adam_newest_partner', fn( array $attributes = array() ): string => $this->highlight( 'partner', 'newest' ) );
		add_shortcode( 'adam_featured_brand', fn( array $attributes = array() ): string => $this->highlight( 'brand', 'featured' ) );
		add_shortcode( 'adam_institution_spotlight', fn( array $attributes = array() ): string => $this->highlight( 'institution', 'featured' ) );
		add_shortcode( 'adam_random_partner', fn( array $attributes = array() ): string => $this->highlight( 'partner', 'random' ) );
		add_shortcode( 'adam_community_section', array( $this, 'section_shortcode' ) );
		add_shortcode( 'adam_community_map', fn( array $attributes = array() ): string => $this->map() );
	}

	/**
	 * Loads component assets only on routes or posts that can render them.
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets(): void {
		global $post;
		$content = $post instanceof \WP_Post ? $post->post_content : '';
		$has_component = (bool) get_query_var( 'adam_team_slug' )
			|| (bool) get_query_var( 'adam_field_slug' )
			|| str_contains( $content, '[adam_' )
			|| has_block( 'adam-comunidade/community-section', $content )
			|| has_block( 'adam-comunidade/community-highlight', $content )
			|| has_block( 'adam-comunidade/community-map', $content );
		if ( $has_component ) {
			$has_map = str_contains( $content, '[adam_community_map' ) || has_block( 'adam-comunidade/community-map', $content );
			$this->assets( $has_map );
		}
	}

	public function register_blocks(): void {
		wp_register_script(
			'adam-comunidade-blocks',
			Helpers::url( 'assets/js/community-blocks.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		register_block_type(
			'adam-comunidade/community-section',
			array(
				'api_version'   => 3,
				'editor_script' => 'adam-comunidade-blocks',
				'attributes'    => array(
					'type'     => array( 'type' => 'string', 'default' => 'teams' ),
					'number'   => array( 'type' => 'number', 'default' => 6 ),
					'order'    => array( 'type' => 'string', 'default' => 'newest' ),
					'category' => array( 'type' => 'string', 'default' => '' ),
					'featured' => array( 'type' => 'boolean', 'default' => false ),
				),
				'render_callback' => fn( array $attributes ): string => $this->section( $attributes ),
			)
		);
		register_block_type(
			'adam-comunidade/community-highlight',
			array(
				'api_version'   => 3,
				'editor_script' => 'adam-comunidade-blocks',
				'attributes'    => array(
					'type' => array( 'type' => 'string', 'default' => 'featured_partner' ),
				),
				'render_callback' => function ( array $attributes ): string {
					$types = array(
						'featured_partner'      => array( 'partner', 'featured' ),
						'newest_partner'        => array( 'partner', 'newest' ),
						'random_partner'        => array( 'partner', 'random' ),
						'featured_brand'        => array( 'brand', 'featured' ),
						'institution_spotlight' => array( 'institution', 'featured' ),
					);
					$choice = $types[ $attributes['type'] ?? '' ] ?? $types['featured_partner'];
					return $this->highlight( $choice[0], $choice[1] );
				},
			)
		);
		register_block_type(
			'adam-comunidade/community-map',
			array(
				'api_version'     => 3,
				'editor_script'   => 'adam-comunidade-blocks',
				'render_callback' => fn(): string => $this->map(),
			)
		);
	}

	public function section_shortcode( array $attributes = array() ): string {
		return $this->section(
			shortcode_atts(
				array( 'type' => 'teams', 'number' => 6, 'order' => 'newest', 'category' => '', 'featured' => false ),
				$attributes,
				'adam_community_section'
			)
		);
	}

	/**
	 * Renders an identical grid for every community module.
	 *
	 * @param array<string,mixed> $attributes Component settings.
	 */
	public function section( array $attributes ): string {
		$this->assets();
		$type     = sanitize_key( $attributes['type'] ?? 'teams' );
		$number   = max( 1, min( 24, absint( $attributes['number'] ?? 6 ) ) );
		$order    = sanitize_key( $attributes['order'] ?? 'newest' );
		$featured = filter_var( $attributes['featured'] ?? false, FILTER_VALIDATE_BOOL );
		$labels   = array( 'teams' => __( 'Equipas Associadas', 'adam-comunidade' ), 'fields' => __( 'Campos', 'adam-comunidade' ), 'partners' => __( 'Parceiros', 'adam-comunidade' ), 'institutions' => __( 'Instituições', 'adam-comunidade' ), 'brands' => __( 'Marcas', 'adam-comunidade' ) );
		if ( ! isset( $labels[ $type ] ) ) {
			return '';
		}
		$items = array();
		if ( 'teams' === $type ) {
			$result = ( new Team_Repository() )->query( array( 'status' => 'published', 'featured' => $featured ? 1 : '', 'orderby' => 'newest' === $order ? 'created_at' : 'name', 'order' => 'alphabetical' === $order ? 'ASC' : 'DESC', 'per_page' => 'random' === $order ? 100 : $number ) );
			if ( 'random' === $order ) {
				shuffle( $result['items'] );
				$result['items'] = array_slice( $result['items'], 0, $number );
			}
			foreach ( $result['items'] as $item ) {
				$items[] = $this->core_card( $item, 'team', Team_Router::team_url( $item ), (int) $item->logo_id );
			}
		} elseif ( 'fields' === $type ) {
			$result = ( new Field_Repository() )->query( array( 'status' => 'published', 'featured' => $featured ? 1 : '', 'orderby' => 'newest' === $order ? 'created_at' : 'name', 'order' => 'alphabetical' === $order ? 'ASC' : 'DESC', 'per_page' => 'random' === $order ? 100 : $number ) );
			if ( 'random' === $order ) {
				shuffle( $result['items'] );
				$result['items'] = array_slice( $result['items'], 0, $number );
			}
			foreach ( $result['items'] as $item ) {
				$items[] = $this->core_card( $item, 'field', Field_Router::field_url( $item ), (int) $item->cover_id );
			}
		} else {
			$singular = array( 'partners' => 'partner', 'institutions' => 'institution', 'brands' => 'brand' )[ $type ];
			$result   = $this->repository->query( $singular, array( 'status' => 'published', 'category' => sanitize_key( $attributes['category'] ?? '' ), 'featured' => $featured ? 1 : '', 'orderby' => 'alphabetical' === $order ? 'name' : ( 'priority' === $order ? 'priority' : 'created_at' ), 'order' => 'alphabetical' === $order ? 'ASC' : 'DESC', 'per_page' => $number ) );
			if ( 'random' === $order ) {
				$result = $this->repository->query( $singular, array( 'status' => 'published', 'category' => sanitize_key( $attributes['category'] ?? '' ), 'featured' => $featured ? 1 : '', 'per_page' => 100 ) );
				shuffle( $result['items'] );
				$result['items'] = array_slice( $result['items'], 0, $number );
			}
			foreach ( $result['items'] as $item ) {
				$items[] = View::card( $item );
			}
		}
		if ( ! $items ) {
			return '<div class="adam-comunidade__empty">' . esc_html__( 'No published content is available.', 'adam-comunidade' ) . '</div>';
		}
		return '<section class="adam-community-widget" data-adam-widget="' . esc_attr( $type ) . '"><h2>' . esc_html( $labels[ $type ] ) . '</h2><div class="adam-community-grid">' . implode( '', $items ) . '</div></section>';
	}

	public function highlight( string $type, string $mode ): string {
		$this->assets();
		$args = array( 'status' => 'published', 'per_page' => 'random' === $mode ? 100 : 1 );
		if ( 'featured' === $mode ) {
			$args['homepage_featured'] = 1;
			$args['orderby']           = 'priority';
			$args['order']             = 'DESC';
		} else {
			$args['orderby'] = 'created_at';
			$args['order']   = 'DESC';
		}
		$items = $this->repository->query( $type, $args )['items'];
		if ( 'featured' === $mode && ! $items ) {
			unset( $args['homepage_featured'] );
			$args['featured'] = 1;
			$items = $this->repository->query( $type, $args )['items'];
		}
		if ( 'random' === $mode && $items ) {
			$items = array( $items[ array_rand( $items ) ] );
		}
		return $items ? '<div class="adam-community-highlight" data-adam-widget="' . esc_attr( $mode . '-' . $type ) . '">' . View::card( $items[0] ) . '</div>' : '';
	}

	public function map(): string {
		$this->assets( true );
		$markers = array();
		$sets = array(
			array( 'team', ( new Team_Repository() )->query( array( 'status' => 'published', 'per_page' => 100 ) )['items'] ),
			array( 'field', ( new Field_Repository() )->query( array( 'status' => 'published', 'per_page' => 100 ) )['items'] ),
			array( 'partner', $this->repository->query( 'partner', array( 'status' => 'published', 'per_page' => 100 ) )['items'] ),
			array( 'institution', $this->repository->query( 'institution', array( 'status' => 'published', 'per_page' => 100 ) )['items'] ),
		);
		foreach ( $sets as $set ) {
			list( $type, $items ) = $set;
			foreach ( $items as $item ) {
				if ( null === $item->latitude || null === $item->longitude ) {
					continue;
				}
				$url = 'team' === $type ? Team_Router::team_url( $item ) : ( 'field' === $type ? Field_Router::field_url( $item ) : Router::entry_url( $item ) );
				$markers[] = array( 'id' => (int) $item->id, 'type' => $type, 'name' => $item->name, 'latitude' => (float) $item->latitude, 'longitude' => (float) $item->longitude, 'district' => $item->district ?? '', 'municipality' => $item->municipality ?? '', 'url' => $url );
			}
		}
		$id = wp_unique_id( 'adam-community-map-' );
		return '<section class="adam-map-widget adam-advanced-map" id="' . esc_attr( $id ) . '" data-adam-advanced-map data-markers=\'' . esc_attr( wp_json_encode( $markers ) ) . '\'><header><div><span>' . esc_html__( 'Live', 'adam-comunidade' ) . '</span><h2>' . esc_html__( 'Community Map', 'adam-comunidade' ) . '</h2></div><div class="adam-map-legend"><i data-type="team"></i>' . esc_html__( 'Equipas', 'adam-comunidade' ) . '<i data-type="field"></i>' . esc_html__( 'Campos', 'adam-comunidade' ) . '<i data-type="partner"></i>' . esc_html__( 'Parceiros', 'adam-comunidade' ) . '<i data-type="institution"></i>' . esc_html__( 'Instituições', 'adam-comunidade' ) . '</div></header><form class="adam-map-filters" data-adam-map-filters><label>' . esc_html__( 'Distrito', 'adam-comunidade' ) . '<input name="district" type="text"></label><label>' . esc_html__( 'Concelho', 'adam-comunidade' ) . '<input name="municipality" type="text"></label><label>' . esc_html__( 'Playing Style', 'adam-comunidade' ) . '<input name="playing_style" type="text"></label></form><div class="adam-map-layout"><div class="adam-map-canvas" data-adam-map-canvas role="region" aria-label="' . esc_attr__( 'Interactive community map', 'adam-comunidade' ) . '"></div><div class="adam-map-results" data-adam-map-results aria-live="polite"></div></div></section>';
	}

	public function team_connections( object $team ): void {
		echo $this->backlinks( 'team', (int) $team->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function field_connections( object $field ): void {
		echo $this->backlinks( 'field', (int) $field->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function backlinks( string $type, int $id ): string {
		$connected = $this->relationships->connected( $type, $id );
		$cards = array();
		foreach ( $connected as $edge ) {
			$source = $edge->target_type === $type && (int) $edge->target_id === $id;
			$other_type = $source ? $edge->source_type : $edge->target_type;
			$other_id   = $source ? (int) $edge->source_id : (int) $edge->target_id;
			if ( Types::get( $other_type ) ) {
				$item = $this->repository->find( $other_id, $other_type );
				if ( $item && 'published' === $item->status ) {
					$cards[] = View::card( $item );
				}
			}
		}
		return $cards ? '<section class="adam-community-section"><h2>' . esc_html__( 'Community Partners', 'adam-comunidade' ) . '</h2><div class="adam-community-grid">' . implode( '', $cards ) . '</div></section>' : '';
	}

	private function core_card( object $item, string $type, string $url, int $image_id ): string {
		return '<article class="adam-community-card" data-entity-type="' . esc_attr( $type ) . '" data-entity-id="' . esc_attr( (string) $item->id ) . '"><a class="adam-community-card__media" href="' . esc_url( $url ) . '">' . ( $image_id ? wp_get_attachment_image( $image_id, 'medium', false, array( 'loading' => 'lazy' ) ) : '<span class="adam-community-card__placeholder"></span>' ) . '</a><div class="adam-community-card__body"><span class="adam-community-card__meta">' . esc_html( ucfirst( $type ) ) . '</span><h2><a href="' . esc_url( $url ) . '">' . esc_html( $item->name ) . '</a></h2><p>' . esc_html( $item->short_description ?? '' ) . '</p><a class="adam-community-button" href="' . esc_url( $url ) . '">' . esc_html__( 'View details', 'adam-comunidade' ) . '</a></div></article>';
	}

	private function assets( bool $map = false ): void {
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style( 'adam-comunidade-directory', Helpers::url( 'assets/css/directory-public.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		if ( $map ) {
			wp_enqueue_style( 'adam-comunidade-map', Helpers::url( 'assets/css/community-map.css' ), array( 'adam-comunidade-directory' ), ADAM_COMUNIDADE_VERSION );
			wp_enqueue_script( 'adam-comunidade-map', Helpers::url( 'assets/js/community-map.js' ), array(), ADAM_COMUNIDADE_VERSION, true );
		}
	}
}
