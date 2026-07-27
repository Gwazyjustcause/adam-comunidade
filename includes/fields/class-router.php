<?php
/**
 * Fields public routing.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Experience\Templates;
use ADAM\Comunidade\Managed_Pages;

/**
 * Owns clean URLs, previews, SEO, and AJAX discovery.
 */
final class Router {
	/**
	 * Current single field.
	 *
	 * @var object|null
	 */
	private static ?object $current_field = null;

	/**
	 * Clean location segment when a field slug is not found.
	 *
	 * @var string
	 */
	private static string $archive_location = '';

	/**
	 * Fields repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Fields repository.
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
		add_action( 'wp_ajax_adam_filter_fields', array( $this, 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_adam_filter_fields', array( $this, 'ajax_filter' ) );
	}

	/**
	 * Adds /campos and /campos/{slug}.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules(): void {
		$path = Managed_Pages::path( 'fields' );
		$page_id = Managed_Pages::id( 'fields' );
		if ( $path && $page_id ) {
			add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/([^/]+)/?$', 'index.php?page_id=' . $page_id . '&adam_field_slug=$matches[1]', 'top' );
		}
	}

	/**
	 * Adds route variables.
	 *
	 * @param string[] $vars Existing vars.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'adam_field_slug';

		return $vars;
	}

	/**
	 * Selects archive, location archive, single, preview, or 404 template.
	 *
	 * @param string $template Theme template.
	 * @return string
	 */
	public function template_include( string $template ): string {
		$slug = sanitize_title( (string) get_query_var( 'adam_field_slug' ) );
		if ( ! $slug ) {
			return Managed_Pages::is_current( 'fields' )
				? Templates::locate( 'fields/archive.php' )
				: $template;
		}

		$preview_id = filter_input( INPUT_GET, 'adam_field_preview', FILTER_VALIDATE_INT ) ?: 0;
		$nonce      = sanitize_text_field( (string) filter_input( INPUT_GET, '_wpnonce' ) );
		$can_preview = $preview_id
			&& current_user_can( apply_filters( 'adam_comunidade_fields_capability', 'manage_options' ) )
			&& wp_verify_nonce( $nonce, 'adam_field_preview_' . $preview_id );
		self::$current_field = $can_preview
			? $this->repository->find( $preview_id )
			: $this->repository->find_by_slug( $slug, true );

		if ( $can_preview ) {
			nocache_headers();
		}

		if ( self::$current_field && self::$current_field->slug === $slug ) {
			return Templates::locate( 'fields/single.php' );
		}

		foreach ( $this->repository->distinct( 'district', 'published' ) as $district ) {
			if ( sanitize_title( $district ) === $slug ) {
				self::$archive_location = $district;

				return Templates::locate( 'fields/archive.php' );
			}
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );

		return get_404_template();
	}

	/**
	 * Returns the resolved field.
	 *
	 * @return object|null
	 */
	public static function current_field(): ?object {
		return self::$current_field;
	}

	/**
	 * Returns a clean field URL.
	 *
	 * @param object $field Field row.
	 * @return string
	 */
	public static function field_url( object $field ): string {
		return trailingslashit( Managed_Pages::url( 'fields' ) ) . user_trailingslashit( $field->slug );
	}

	/**
	 * Returns the clean-route district selection.
	 *
	 * @return string
	 */
	public static function archive_location(): string {
		return self::$archive_location;
	}

	/**
	 * Sets route document titles.
	 *
	 * @param string $title Existing title.
	 * @return string
	 */
	public function document_title( string $title ): string {
		if ( self::$current_field ) {
			return self::$current_field->meta_title ?: self::$current_field->name;
		}
		if ( self::$archive_location ) {
			return sprintf(
				/* translators: %s: district. */
				__( 'Campos em %s', 'adam-comunidade' ),
				self::$archive_location
			);
		}

		return $title;
	}

	/**
	 * Prints single-field meta description fallback.
	 *
	 * @return void
	 */
	public function meta_description(): void {
		if ( self::$current_field ) {
			$description = self::$current_field->meta_description
				?: self::$current_field->short_description;
			if ( $description ) {
				printf(
					'<meta name="description" content="%s">' . "\n",
					esc_attr( wp_strip_all_tags( $description ) )
				);
			}
		}
	}

	/**
	 * Loads public assets only on field routes.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! Managed_Pages::is_current( 'fields' ) && ! get_query_var( 'adam_field_slug' ) ) {
			return;
		}

		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style(
			'adam-comunidade-fields',
			Helpers::url( 'assets/css/fields-public.css' ),
			array( 'adam-comunidade' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_script(
			'adam-comunidade-fields',
			Helpers::url( 'assets/js/fields-public.js' ),
			array(),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_localize_script(
			'adam-comunidade-fields',
			'adamFields',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'adam_fields_filter' ),
				'error'    => __( 'Não foi possível carregar os campos. Tente novamente.', 'adam-comunidade' ),
				'copied'   => __( 'Coordenadas GPS copiadas.', 'adam-comunidade' ),
				'copyFail' => __( 'Não foi possível copiar as coordenadas.', 'adam-comunidade' ),
			)
		);
	}

	/**
	 * Returns filtered cards for public visitors.
	 *
	 * @return void
	 */
	public function ajax_filter(): void {
		check_ajax_referer( 'adam_fields_filter', 'nonce' );
		$sort = $this->post_value( 'sort' );
		$sorts = array(
			'alphabetical' => array( 'name', 'ASC' ),
			'newest'       => array( 'created_at', 'DESC' ),
			'capacity'     => array( 'max_players', 'DESC' ),
		);
		$selected = $sorts[ $sort ] ?? $sorts['alphabetical'];
		$page     = isset( $_POST['page_number'] ) ? absint( wp_unslash( $_POST['page_number'] ) ) : 1;
		$result   = $this->repository->query(
			array(
				'status'        => 'published',
				'legally_authorized' => 1,
				'prioritize_associated' => true,
				'associated'    => 'only' === $this->post_value( 'associated' ) ? 1 : '',
				'search'        => $this->post_value( 'search' ),
				'district'      => $this->post_value( 'district' ),
				'municipality'  => $this->post_value( 'municipality' ),
				'playing_style' => sanitize_key( $this->post_value( 'playing_style' ) ),
				'amenity_id'    => absint( $this->post_value( 'amenity_id' ) ),
				'team_id'       => absint( $this->post_value( 'team_id' ) ),
				'orderby'       => $selected[0],
				'order'         => $selected[1],
				'page'          => $page,
				'per_page'      => 12,
			)
		);
		$associated_cards = '';
		$other_cards      = '';

		foreach ( $result['items'] as $field ) {
			if ( ! empty( $field->is_associated ) ) {
				$associated_cards .= View::card( $field, $this->repository );
			} else {
				$other_cards .= View::card( $field, $this->repository );
			}
		}
		$cards = $associated_cards
			? '<section class="adam-field-group adam-field-group--associated"><header><h2>'
				. esc_html__( 'Campos Associados', 'adam-comunidade' )
				. '</h2><p>' . esc_html__( 'Campos com associação ativa à ADAM e prioridade na listagem.', 'adam-comunidade' )
				. '</p></header><div class="adam-field-grid">' . $associated_cards . '</div></section>'
			: '';
		$cards .= $other_cards
			? '<section class="adam-field-group"><header><h2>'
				. esc_html__( 'Outros Campos', 'adam-comunidade' )
				. '</h2><p>' . esc_html__( 'Todos com autorização legal verificada.', 'adam-comunidade' )
				. '</p></header><div class="adam-field-grid">' . $other_cards . '</div></section>'
			: '';
		if ( ! $cards ) {
			$cards = '<div class="adam-comunidade__empty adam-fields-empty">'
				. esc_html__( 'Nenhum campo corresponde aos filtros selecionados.', 'adam-comunidade' )
				. '</div>';
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
	 * Reads a sanitized POST value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function post_value( string $key ): string {
		return isset( $_POST[ $key ] )
			? sanitize_text_field( wp_unslash( $_POST[ $key ] ) )
			: '';
	}
}
