<?php
/**
 * Public directory routes.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Experience\Templates;
use ADAM\Comunidade\Managed_Pages;

/**
 * Handles clean archives, singles, previews, SEO, and AJAX filtering.
 */
final class Router {
	private static string $current_type = '';
	private static ?object $current_entry = null;

	public function __construct( private Repository $repository ) {}

	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_action( 'wp_head', array( $this, 'meta_description' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_ajax_adam_filter_directory', array( $this, 'ajax_filter' ) );
		add_action( 'wp_ajax_nopriv_adam_filter_directory', array( $this, 'ajax_filter' ) );
	}

	public static function add_rewrite_rules(): void {
		foreach ( Types::all() as $type => $definition ) {
			$path = Managed_Pages::path( (string) $definition['module_id'] );
			$page_id = Managed_Pages::id( (string) $definition['module_id'] );
			if ( $path && $page_id ) {
				add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/([^/]+)/?$', 'index.php?page_id=' . $page_id . '&adam_directory_type=' . $type . '&adam_directory_slug=$matches[1]', 'top' );
			}
		}
	}

	public function query_vars( array $vars ): array {
		$vars[] = 'adam_directory_type';
		$vars[] = 'adam_directory_slug';
		return $vars;
	}

	public function template_include( string $template ): string {
		$type = sanitize_key( (string) get_query_var( 'adam_directory_type' ) );
		$slug = sanitize_title( (string) get_query_var( 'adam_directory_slug' ) );
		if ( ! $type || ! $slug ) {
			foreach ( Types::all() as $page_type => $page_definition ) {
				if ( Managed_Pages::is_current( (string) $page_definition['module_id'] ) ) {
					self::$current_type = $page_type;
					return Templates::locate( 'directory/archive.php' );
				}
			}
			return $template;
		}

		if ( ! Types::get( $type ) ) {
			return $template;
		}
		self::$current_type = $type;
		$preview_id = absint( $_GET['adam_directory_preview'] ?? 0 );
		$capability = (string) apply_filters( 'adam_comunidade_directory_capability', 'manage_options' );
		$can_preview = $preview_id
			&& current_user_can( $capability )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'adam_directory_preview_' . $preview_id );
		self::$current_entry = $can_preview ? $this->repository->find( $preview_id, $type ) : $this->repository->find_by_slug( $type, $slug, true );
		if ( $can_preview ) {
			nocache_headers();
		}
		if ( self::$current_entry && self::$current_entry->slug === $slug ) {
			return Templates::locate( 'directory/single.php' );
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		return get_404_template();
	}

	public static function current_type(): string {
		return self::$current_type;
	}

	public static function current_entry(): ?object {
		return self::$current_entry;
	}

	public static function entry_url( object $entry ): string {
		$definition = Types::get( $entry->entity_type );
		return $definition
			? trailingslashit( Managed_Pages::url( (string) $definition['module_id'] ) ) . user_trailingslashit( $entry->slug )
			: home_url( '/' );
	}

	public function document_title( string $title ): string {
		if ( self::$current_entry ) {
			return self::$current_entry->meta_title ?: self::$current_entry->name;
		}
		if ( self::$current_type ) {
			$definition = Types::get( self::$current_type );
			if ( $definition && Managed_Pages::is_current( (string) $definition['module_id'] ) ) {
				return $title;
			}
		}
		$definition = Types::get( self::$current_type );
		return $definition ? $definition['plural'] : $title;
	}

	public function meta_description(): void {
		if ( self::$current_entry ) {
			$description = self::$current_entry->meta_description ?: self::$current_entry->short_description;
			if ( $description ) {
				printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_strip_all_tags( $description ) ) );
			}
		}
	}

	public function enqueue_assets(): void {
		$is_archive = false;
		foreach ( Types::all() as $definition ) {
			$is_archive = $is_archive || Managed_Pages::is_current( (string) $definition['module_id'] );
		}
		if ( ! $is_archive && ! get_query_var( 'adam_directory_type' ) ) {
			return;
		}
		wp_enqueue_style( 'adam-comunidade' );
		wp_enqueue_style( 'adam-comunidade-directory', Helpers::url( 'assets/css/directory-public.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_script( 'adam-comunidade-directory', Helpers::url( 'assets/js/directory-public.js' ), array(), ADAM_COMUNIDADE_VERSION, true );
		wp_localize_script( 'adam-comunidade-directory', 'adamDirectory', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'adam_directory_filter' ), 'error' => __( 'Content could not be loaded.', 'adam-comunidade' ) ) );
	}

	public function ajax_filter(): void {
		check_ajax_referer( 'adam_directory_filter', 'nonce' );
		$type = sanitize_key( $_POST['entity_type'] ?? '' );
		if ( ! Types::get( $type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid directory.', 'adam-comunidade' ) ), 400 );
		}
		$sorts = array(
			'alphabetical' => array( 'name', 'ASC' ),
			'newest'       => array( 'created_at', 'DESC' ),
			'priority'     => array( 'priority', 'DESC' ),
		);
		$sort = $sorts[ sanitize_key( $_POST['sort'] ?? '' ) ] ?? $sorts['alphabetical'];
		$page = max( 1, absint( $_POST['page_number'] ?? 1 ) );
		$result = $this->repository->query(
			$type,
			array(
				'status'   => 'published',
				'search'   => sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) ),
				'category' => sanitize_key( $_POST['category'] ?? '' ),
				'district' => sanitize_text_field( wp_unslash( $_POST['district'] ?? '' ) ),
				'featured' => empty( $_POST['featured'] ) ? '' : 1,
				'orderby'  => $sort[0],
				'order'    => $sort[1],
				'page'     => $page,
				'per_page' => 12,
			)
		);
		$cards = implode( '', array_map( array( View::class, 'card' ), $result['items'] ) );
		if ( ! $cards ) {
			$cards = '<div class="adam-comunidade__empty">' . esc_html__( 'No results match these filters.', 'adam-comunidade' ) . '</div>';
		}
		wp_send_json_success( array( 'cards' => $cards, 'pagination' => View::pagination( $page, $result['pages'] ), 'total' => $result['total'] ) );
	}
}
