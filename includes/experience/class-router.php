<?php
/**
 * Public routes and AJAX discovery.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Managed_Pages;

/**
 * Routes the central hub, automatic region pages, and comparison tool.
 */
final class Router {
	public function __construct( private Discovery $discovery ) {}

	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template' ), 40 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
		add_action( 'wp_ajax_adam_universal_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_adam_universal_search', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_adam_community_map', array( $this, 'ajax_map' ) );
		add_action( 'wp_ajax_nopriv_adam_community_map', array( $this, 'ajax_map' ) );
	}

	public static function add_rewrite_rules(): void {
		$community_path = Managed_Pages::path( 'community' );
		$community_id   = Managed_Pages::id( 'community' );
		if ( $community_path && $community_id ) {
			add_rewrite_rule( '^' . preg_quote( $community_path, '#' ) . '/comparar/?$', 'index.php?page_id=' . $community_id . '&adam_compare=1', 'top' );
		}
		foreach ( array_keys( self::regions() ) as $slug ) {
			add_rewrite_rule( '^' . preg_quote( $slug, '#' ) . '/?$', 'index.php?adam_region=' . $slug, 'top' );
		}
	}

	/**
	 * Known Portugal districts, filterable by integrations.
	 *
	 * @return array<string,string>
	 */
	public static function regions(): array {
		$names = array( 'Aveiro', 'Beja', 'Braga', 'Bragança', 'Castelo Branco', 'Coimbra', 'Évora', 'Faro', 'Guarda', 'Leiria', 'Lisboa', 'Portalegre', 'Porto', 'Santarém', 'Setúbal', 'Viana do Castelo', 'Vila Real', 'Viseu' );
		$regions = array();
		foreach ( $names as $name ) {
			$regions[ sanitize_title( $name ) ] = $name;
		}
		return apply_filters( 'adam_comunidade_regions', $regions );
	}

	public function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'adam_compare', 'adam_region' ) );
	}

	public function template( string $template ): string {
		if ( get_query_var( 'adam_compare' ) ) {
			return Templates::locate( 'experience/compare.php' );
		}
		if ( get_query_var( 'adam_region' ) ) {
			return Templates::locate( 'experience/region.php' );
		}
		return $template;
	}

	public function title( string $title ): string {
		if ( get_query_var( 'adam_compare' ) ) {
			return __( 'Comparar conteúdo da Comunidade', 'adam-comunidade' );
		}
		$slug = sanitize_title( (string) get_query_var( 'adam_region' ) );
		return $slug ? ( self::regions()[ $slug ] ?? $title ) : $title;
	}

	public function assets(): void {
		if ( ! Managed_Pages::is_current( 'community' ) && ! get_query_var( 'adam_compare' ) && ! get_query_var( 'adam_region' ) && ! is_post_type_archive( 'adam_news' ) && ! is_singular( 'adam_news' ) ) {
			return;
		}
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'adam-experience', Helpers::url( 'assets/css/experience.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_style( 'adam-comunidade-directory', Helpers::url( 'assets/css/directory-public.css' ), array( 'adam-experience' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_style( 'adam-comunidade-map', Helpers::url( 'assets/css/community-map.css' ), array( 'adam-comunidade-directory' ), ADAM_COMUNIDADE_VERSION );
		if ( Managed_Pages::is_current( 'community' ) ) {
			wp_enqueue_style( 'adam-community-landing', Helpers::url( 'assets/css/community-landing.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		}
		wp_enqueue_script( 'adam-experience', Helpers::url( 'assets/js/experience.js' ), array(), ADAM_COMUNIDADE_VERSION, true );
		wp_localize_script(
			'adam-experience',
			'adamExperience',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'adam_experience' ),
				'labels'  => array( 'view' => __( 'Ver', 'adam-comunidade' ), 'empty' => __( 'Nenhum conteúdo da comunidade corresponde à pesquisa.', 'adam-comunidade' ), 'error' => __( 'Não foi possível carregar os resultados da comunidade.', 'adam-comunidade' ), 'processing' => __( 'A processar…', 'adam-comunidade' ) ),
				'groupLabels' => array( 'teams' => __( 'Equipas', 'adam-comunidade' ), 'fields' => __( 'Campos', 'adam-comunidade' ), 'partners' => __( 'Parceiros', 'adam-comunidade' ), 'institutions' => __( 'Instituições', 'adam-comunidade' ), 'news' => __( 'Notícias', 'adam-comunidade' ), 'events' => __( 'Eventos', 'adam-comunidade' ) ),
			)
		);
	}

	/**
	 * Mirrors the landing styles inside the block editor for the Community page.
	 */
	public function editor_assets(): void {
		$page_id = absint( $_GET['post'] ?? $_POST['post_ID'] ?? 0 );
		if ( $page_id !== Managed_Pages::id( 'community', true ) ) {
			return;
		}
		wp_enqueue_style( 'adam-community-landing-editor', Helpers::url( 'assets/css/community-landing.css' ), array(), ADAM_COMUNIDADE_VERSION );
	}

	public function ajax_search(): void {
		check_ajax_referer( 'adam_experience', 'nonce' );
		wp_send_json_success( $this->discovery->search( sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ), $this->filters( $_POST ) ) );
	}

	public function ajax_map(): void {
		check_ajax_referer( 'adam_experience', 'nonce' );
		$filters = $this->filters( $_POST );
		wp_send_json_success( $this->discovery->map_records( $filters ) );
	}

	private function filters( array $input ): array {
		return array(
			'district'            => sanitize_text_field( wp_unslash( $input['district'] ?? '' ) ),
			'municipality'        => sanitize_text_field( wp_unslash( $input['municipality'] ?? '' ) ),
			'playing_style'       => sanitize_key( $input['playing_style'] ?? '' ),
			'facility'            => sanitize_text_field( wp_unslash( $input['facility'] ?? '' ) ),
			'partner_category'    => sanitize_key( $input['partner_category'] ?? '' ),
			'institution_category'=> sanitize_key( $input['institution_category'] ?? '' ),
			'recruitment'         => sanitize_key( $input['recruitment'] ?? '' ),
			'featured'            => empty( $input['featured'] ) ? '' : 1,
		);
	}
}
