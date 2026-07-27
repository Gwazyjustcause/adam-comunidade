<?php
/**
 * Teams public routing and AJAX controller.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Experience\Templates;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Experience\Portal;

/**
 * Owns clean team URLs and public data resolution.
 */
final class Router {
	/**
	 * Resolved single team.
	 *
	 * @var object|null
	 */
	private static ?object $current_team = null;

	/**
	 * Teams repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Teams repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers public hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_action( 'wp_head', array( $this, 'meta_description' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_ajax_adam_filter_teams', array( $this, 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_adam_filter_teams', array( $this, 'ajax_filter' ) );
	}

	/**
	 * Adds clean archive and single rewrite rules.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules(): void {
		$path = Managed_Pages::path( 'teams' );
		$page_id = Managed_Pages::id( 'teams' );
		if ( $path && $page_id ) {
			add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/([^/]+)/?$', 'index.php?page_id=' . $page_id . '&adam_team_slug=$matches[1]', 'top' );
		}
	}

	/**
	 * Registers public query variables.
	 *
	 * @param string[] $vars Existing query variables.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'adam_team_slug';

		return $vars;
	}

	/**
	 * Resolves module templates.
	 *
	 * @param string $template Theme template.
	 * @return string
	 */
	public function template_include( string $template ): string {
		$slug = sanitize_title( (string) get_query_var( 'adam_team_slug' ) );

		if ( ! $slug ) {
			return Managed_Pages::is_current( 'teams' )
				? Templates::locate( 'teams/archive.php' )
				: $template;
		}

		$preview_id = filter_input( INPUT_GET, 'adam_preview', FILTER_VALIDATE_INT ) ?: 0;
		$nonce      = sanitize_text_field( (string) filter_input( INPUT_GET, '_wpnonce' ) );
		$can_preview = $preview_id
			&& current_user_can( apply_filters( 'adam_comunidade_teams_capability', 'manage_options' ) )
			&& wp_verify_nonce( $nonce, 'adam_team_preview_' . $preview_id );

		self::$current_team = $can_preview
			? $this->repository->find( $preview_id )
			: $this->repository->find_by_slug( $slug, true );

		if ( $can_preview ) {
			nocache_headers();
		}

		if ( ! self::$current_team || self::$current_team->slug !== $slug ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );

			return get_404_template();
		}

		return Templates::locate( 'teams/single.php' );
	}

	/**
	 * Returns the team resolved for the current single route.
	 *
	 * @return object|null
	 */
	public static function current_team(): ?object {
		return self::$current_team;
	}

	/**
	 * Builds a clean public team URL.
	 *
	 * @param object $team Team record.
	 * @return string
	 */
	public static function team_url( object $team ): string {
		return trailingslashit( Managed_Pages::url( 'teams' ) ) . user_trailingslashit( $team->slug );
	}

	/**
	 * Sets SEO-aware document titles.
	 *
	 * @param string $title Existing document title.
	 * @return string
	 */
	public function document_title( string $title ): string {
		if ( self::$current_team ) {
			return self::$current_team->meta_title ?: self::$current_team->name;
		}

		return $title;
	}

	/**
	 * Prints a sanitized description meta tag for single team pages.
	 *
	 * @return void
	 */
	public function meta_description(): void {
		if ( ! self::$current_team ) {
			return;
		}

		$description = self::$current_team->meta_description ?: self::$current_team->short_description;

		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_strip_all_tags( $description ) ) );
		}
	}

	/**
	 * Enqueues public assets only on Teams routes.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! Managed_Pages::is_current( 'teams' ) && ! get_query_var( 'adam_team_slug' ) ) {
			return;
		}

		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style(
			'adam-comunidade-teams',
			Helpers::url( 'assets/css/teams-public.css' ),
			array( 'adam-comunidade' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_style(
			'adam-comunidade-fields',
			Helpers::url( 'assets/css/fields-public.css' ),
			array( 'adam-comunidade-teams' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_script(
			'adam-comunidade-directory-carousel',
			Helpers::url( 'assets/js/fields-public.js' ),
			array(),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_enqueue_script(
			'adam-comunidade-teams',
			Helpers::url( 'assets/js/teams-public.js' ),
			array( 'adam-comunidade-directory-carousel' ),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_localize_script(
			'adam-comunidade-teams',
			'adamTeams',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'adam_teams_filter' ),
				'error'   => __( 'Não foi possível carregar as equipas. Tente novamente.', 'adam-comunidade' ),
			)
		);
	}

	/**
	 * Returns filtered public cards for progressive enhancement.
	 *
	 * @return void
	 */
	public function ajax_filter(): void {
		check_ajax_referer( 'adam_teams_filter', 'nonce' );

		$page = isset( $_POST['page_number'] ) ? absint( wp_unslash( $_POST['page_number'] ) ) : 1;
		$sort = isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : 'alphabetical';
		$sort_options = array(
			'alphabetical' => array( 'name', 'ASC' ),
			'newest'      => array( 'created_at', 'DESC' ),
			'oldest'      => array( 'created_at', 'ASC' ),
		);
		$selected_sort = $sort_options[ $sort ] ?? $sort_options['alphabetical'];
		$result = $this->repository->query(
			array(
				'status'        => 'published',
				'search'        => $this->post_value( 'search' ),
				'district'      => $this->post_value( 'district' ),
				'municipality'  => $this->post_value( 'municipality' ),
				'playing_style' => sanitize_key( $this->post_value( 'playing_style' ) ),
				'recruitment'   => sanitize_key( $this->post_value( 'recruitment' ) ),
				'associated'    => 'associated' === $this->post_value( 'association' ) ? 1 : '',
				'prioritize_associated' => true,
				'orderby'       => $selected_sort[0],
				'order'         => $selected_sort[1],
				'page'          => $page,
				'per_page'      => 12,
			)
		);

		$cards = '';
		foreach ( $result['items'] as $team ) {
			$cards .= View::card( $team );
		}

		if ( '' === $cards ) {
			$cards = '<div class="adam-comunidade__empty adam-teams-empty">'
				. '<h2>' . esc_html__( 'Nenhuma equipa corresponde aos filtros selecionados.', 'adam-comunidade' ) . '</h2>'
				. '<p>' . esc_html__( 'Experimente alterar os filtros ou ajude-nos a adicionar uma equipa ao diretório.', 'adam-comunidade' ) . '</p>'
				. '<a class="adam-team-button adam-directory-button" href="' . esc_url( Portal::submission_url( 'team' ) ) . '">'
				. esc_html__( 'Submeter uma Equipa', 'adam-comunidade' ) . '</a></div>';
		}

		wp_send_json_success(
			array(
				'cards'      => $cards,
				'pagination' => View::pagination( $page, $result['pages'] ),
				'total'      => $result['total'],
			)
		);
	}

	/**
	 * Reads and sanitizes one POST filter value.
	 *
	 * @param string $key Input key.
	 * @return string
	 */
	private function post_value( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}
}
