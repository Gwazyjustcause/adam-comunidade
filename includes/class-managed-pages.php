<?php
/**
 * Managed WordPress pages for public Community modules.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;

/**
 * Stores, resolves, recovers and updates module pages exclusively by post ID.
 */
final class Managed_Pages {
	private const VERSION = '1.6.0';
	private const META_KEY = '_adam_comunidade_managed_module';
	private static bool $synchronizing = false;
	private bool $managed_page_deleting = false;

	/**
	 * Registers lifecycle, recovery and URL-management hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_install' ), 999 );
		add_action( 'post_updated', array( $this, 'page_updated' ), 10, 3 );
		add_action( 'update_option_' . Settings::OPTION_NAME, array( $this, 'settings_updated' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'page_deleted' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'after_page_deleted' ) );
		add_action( 'trashed_post', array( $this, 'page_trashed' ) );
		add_action( 'untrashed_post', array( $this, 'page_untrashed' ) );
		add_filter( 'redirect_canonical', array( $this, 'preserve_child_routes' ) );
		add_action( 'template_redirect', array( $this, 'redirect_legacy_brands_page' ) );

		if ( function_exists( 'adam_ui_register_system_pages' ) ) {
			adam_ui_register_system_pages( 'adam-comunidade', array( $this, 'protection_definitions' ) );
		}

		if ( is_admin() ) {
			Admin_Router::register_page(
				'urls',
				array(
					'title'      => __( 'Endereços', 'adam-comunidade' ),
					'controller' => $this,
					'method'     => 'admin_page',
				)
			);
			add_action( 'admin_notices', array( $this, 'missing_page_notices' ) );
			add_action( 'admin_notices', array( $this, 'editor_notice' ) );
			add_action( 'admin_post_adam_comunidade_save_urls', array( $this, 'save_urls' ) );
			add_action( 'admin_post_adam_comunidade_recover_page', array( $this, 'recover_page' ) );
		}
	}

	/**
	 * Returns the fixed module definitions.
	 *
	 * @return array<string,array{label:string,default_title:string,default_slug:string,option:string}>
	 */
	public static function definitions(): array {
		return array(
			'community' => array(
				'label'         => __( 'Comunidade', 'adam-comunidade' ),
				'default_title' => __( 'Comunidade', 'adam-comunidade' ),
				'default_slug'  => 'comunidade',
				'option'        => 'community_page_id',
			),
			'teams' => array(
				'label'         => __( 'Equipas', 'adam-comunidade' ),
				'default_title' => __( 'Equipas', 'adam-comunidade' ),
				'default_slug'  => 'equipas',
				'option'        => 'teams_page_id',
			),
			'fields' => array(
				'label'         => __( 'Campos', 'adam-comunidade' ),
				'default_title' => __( 'Campos', 'adam-comunidade' ),
				'default_slug'  => 'campos',
				'option'        => 'fields_page_id',
			),
			'partners' => array(
				'label'         => __( 'Parceiros', 'adam-comunidade' ),
				'default_title' => __( 'Parceiros', 'adam-comunidade' ),
				'default_slug'  => 'parceiros',
				'option'        => 'partners_page_id',
			),
			'institutions' => array(
				'label'         => __( 'Instituições', 'adam-comunidade' ),
				'default_title' => __( 'Instituições', 'adam-comunidade' ),
				'default_slug'  => 'instituicoes',
				'option'        => 'institutions_page_id',
			),
			'manager' => array(
				'label'         => __( 'Área do Gestor', 'adam-comunidade' ),
				'default_title' => __( 'Área do Gestor', 'adam-comunidade' ),
				'default_slug'  => 'gestor',
				'option'        => 'manager_page_id',
			),
			'manager_login' => array(
				'label'         => __( 'Login do Gestor', 'adam-comunidade' ),
				'default_title' => __( 'Login do Gestor', 'adam-comunidade' ),
				'default_slug'  => 'login-gestor',
				'option'        => 'manager_login_page_id',
			),
			'manager_activation' => array(
				'label'         => __( 'Definir Palavra-passe do Gestor', 'adam-comunidade' ),
				'default_title' => __( 'Definir Palavra-passe', 'adam-comunidade' ),
				'default_slug'  => 'definir-palavra-passe',
				'option'        => 'manager_activation_page_id',
			),
			'manager_recovery' => array(
				'label'         => __( 'Recuperar Palavra-passe do Gestor', 'adam-comunidade' ),
				'default_title' => __( 'Recuperar Palavra-passe', 'adam-comunidade' ),
				'default_slug'  => 'recuperar-palavra-passe',
				'option'        => 'manager_recovery_page_id',
			),
		);
	}

	/**
	 * Redirects the retired Brands archive to the unified Partners directory.
	 *
	 * The legacy Page is retained to avoid destructive content changes, but it
	 * can no longer surface as a separate public directory.
	 *
	 * @return void
	 */
	public function redirect_legacy_brands_page(): void {
		$settings       = wp_parse_args( get_option( Settings::OPTION_NAME, array() ), Settings::defaults() );
		$brands_page_id = absint( $settings['brands_page_id'] ?? 0 );

		if ( $brands_page_id && is_page( $brands_page_id ) ) {
			wp_safe_redirect( self::url( 'partners' ), 301 );
			exit;
		}
	}

	/**
	 * Creates and stores every required page during activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::$synchronizing = true;
		foreach ( array_keys( self::definitions() ) as $module ) {
			self::ensure( $module );
		}
		self::install_community_landing();
		update_option( 'adam_comunidade_managed_pages_version', self::VERSION, false );
		self::$synchronizing = false;
	}

	/**
	 * Creates managed pages once when upgrading an existing installation.
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		$requires_install = self::VERSION !== get_option( 'adam_comunidade_managed_pages_version' );
		$requires_flush   = (bool) get_option( Install::REWRITE_FLUSH_OPTION );

		if ( $requires_install ) {
			self::activate();
			self::migrate_legacy_titles();
		}

		if ( $requires_install || $requires_flush ) {
			self::regenerate_rewrite_rules();
			delete_option( Install::REWRITE_FLUSH_OPTION );
		}
	}

	/**
	 * Renames only untouched legacy defaults while preserving custom page titles.
	 *
	 * @return void
	 */
	private static function migrate_legacy_titles(): void {
		$page_id = self::id( 'fields', true );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( $page && 'Campos Associados' === $page->post_title ) {
			wp_update_post(
				array(
					'ID'         => $page_id,
					'post_title' => 'Campos',
				)
			);
		}

		$page_id = self::id( 'teams', true );
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( $page && 'Equipas Associadas' === $page->post_title ) {
			wp_update_post(
				array(
					'ID'         => $page_id,
					'post_title' => 'Equipas',
				)
			);
		}
	}

	/**
	 * Returns a stored page ID only when it still identifies the expected page.
	 *
	 * @param string $module Module key.
	 * @param bool   $include_trash Whether trashed pages are valid.
	 * @return int
	 */
	public static function id( string $module, bool $include_trash = false ): int {
		$definition = self::definition( $module );
		if ( ! $definition ) {
			return 0;
		}
		$page_id = absint( Settings::get( $definition['option'] ) );
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( ! $page || 'page' !== $page->post_type || ( ! $include_trash && 'trash' === $page->post_status ) ) {
			return 0;
		}
		return $page_id;
	}

	/**
	 * Returns the managed page's current WordPress slug.
	 *
	 * @param string $module Module key.
	 * @return string
	 */
	public static function slug( string $module ): string {
		$page_id = self::id( $module );
		return $page_id ? (string) get_post_field( 'post_name', $page_id ) : '';
	}

	/**
	 * Returns the complete page URI used as the base for child rewrite rules.
	 *
	 * @param string $module Module key.
	 * @return string
	 */
	public static function path( string $module ): string {
		$page_id = self::id( $module );
		return $page_id ? trim( (string) get_page_uri( $page_id ), '/' ) : '';
	}

	/**
	 * Returns the managed page permalink.
	 *
	 * @param string $module Module key.
	 * @return string
	 */
	public static function url( string $module ): string {
		$page_id = self::id( $module );
		$url     = $page_id ? get_permalink( $page_id ) : false;
		return $url ? (string) $url : home_url( '/' );
	}

	/**
	 * Tests the main queried object against the stored page ID.
	 *
	 * @param string $module Module key.
	 * @return bool
	 */
	public static function is_current( string $module ): bool {
		$page_id = self::id( $module );
		return $page_id > 0 && is_page( $page_id );
	}

	/**
	 * Resolves the managed module for a page ID.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public static function module_for_id( int $page_id ): string {
		foreach ( array_keys( self::definitions() ) as $module ) {
			if ( self::id( $module, true ) === $page_id ) {
				return $module;
			}
		}
		return '';
	}

	/**
	 * Renders URL-only settings.
	 *
	 * @return void
	 */
	public function admin_page(): void {
		$pages = array();
		$available_pages = get_pages(
			array(
				'post_status' => array( 'publish', 'draft', 'private' ),
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			)
		);
		foreach ( self::definitions() as $module => $definition ) {
			$page_id          = self::id( $module, true );
			$page             = $page_id ? get_post( $page_id ) : null;
			$pages[ $module ] = array(
				'definition' => $definition,
				'id'         => $page_id,
				'page'       => $page,
				'url'        => $page && 'trash' !== $page->post_status ? get_permalink( $page_id ) : '',
				'protected'  => $page_id && function_exists( 'adam_ui_is_system_page_protected' ) ? adam_ui_is_system_page_protected( $page_id ) : false,
			);
		}
		require Helpers::path( 'admin/views/urls.php' );
	}

	/**
	 * Updates only WordPress page slugs.
	 *
	 * @return never
	 */
	public function save_urls(): never {
		Admin_Router::authorize();
		check_admin_referer( 'adam_comunidade_save_urls' );
		$slugs    = isset( $_POST['slugs'] ) && is_array( $_POST['slugs'] ) ? wp_unslash( $_POST['slugs'] ) : array();
		$page_ids = isset( $_POST['page_ids'] ) && is_array( $_POST['page_ids'] ) ? wp_unslash( $_POST['page_ids'] ) : array();
		$protected = isset( $_POST['protected'] ) && is_array( $_POST['protected'] ) ? wp_unslash( $_POST['protected'] ) : array();
		$settings = wp_parse_args( get_option( Settings::OPTION_NAME, array() ), Settings::defaults() );
		$changed  = false;
		$requested_ids = array_filter( array_map( 'absint', $page_ids ) );
		if ( count( $requested_ids ) !== count( array_unique( $requested_ids ) ) ) {
			Helpers::add_admin_notice( __( 'Cada módulo da Comunidade deve utilizar uma página WordPress diferente.', 'adam-comunidade' ), 'error' );
			wp_safe_redirect( Admin_Router::page_url( 'urls' ) );
			exit;
		}

		self::$synchronizing = true;
		foreach ( self::definitions() as $module => $definition ) {
			$current_id = self::id( $module, true );
			$page_id    = absint( $page_ids[ $module ] ?? $current_id );
			$page       = $page_id ? get_post( $page_id ) : null;
			if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
				continue;
			}
			if ( $current_id !== $page_id ) {
				if ( $current_id ) {
					delete_post_meta( $current_id, self::META_KEY, $module );
				}
				update_post_meta( $page_id, self::META_KEY, $module );
				$settings[ $definition['option'] ] = $page_id;
				$changed = true;
			}
			$slug = sanitize_title( (string) ( $slugs[ $module ] ?? '' ) );
			if ( ! $page_id || ! $slug || $slug === get_post_field( 'post_name', $page_id ) ) {
				continue;
			}
			$result = wp_update_post( array( 'ID' => $page_id, 'post_name' => $slug ), true );
			if ( ! is_wp_error( $result ) ) {
				$changed = true;
			}
		}
		update_option( Settings::OPTION_NAME, $settings, false );
		if ( function_exists( 'adam_ui_set_system_page_protected' ) ) {
			foreach ( array_keys( self::definitions() ) as $module ) {
				$page_id = self::id( $module );
				if ( $page_id ) {
					adam_ui_set_system_page_protected( $page_id, ! empty( $protected[ $module ] ) );
				}
			}
		}
		self::$synchronizing = false;

		if ( $changed ) {
			self::regenerate_rewrite_rules();
			Helpers::add_admin_notice( __( 'Os endereços da Comunidade foram atualizados.', 'adam-comunidade' ), 'success' );
		}
		wp_safe_redirect( Admin_Router::page_url( 'urls' ) );
		exit;
	}

	/**
	 * Registers managed page IDs and explicit token-entry journeys with ADAM UI.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function protection_definitions(): array {
		$token_pages = array(
			'manager_activation' => static fn (): bool => ! empty( $_GET['convite'] ) || (string) get_query_var( 'adam_manager_token' ) !== '',
			'manager_recovery'   => static fn (): bool => ! empty( $_GET['codigo'] ),
		);
		$pages = array();
		foreach ( array_keys( self::definitions() ) as $module ) {
			$page_id = self::id( $module );
			if ( $page_id ) {
				$pages[] = array(
					'id'           => $page_id,
					'allow_access' => $token_pages[ $module ] ?? null,
				);
			}
		}
		return $pages;
	}

	/**
	 * Restores or recreates one missing managed page.
	 *
	 * @return never
	 */
	public function recover_page(): never {
		Admin_Router::authorize();
		$module = sanitize_key( $_GET['module'] ?? '' );
		check_admin_referer( 'adam_comunidade_recover_page_' . $module );
		if ( self::definition( $module ) ) {
			self::$synchronizing = true;
			self::ensure( $module, true );
			self::$synchronizing = false;
			self::regenerate_rewrite_rules();
			Helpers::add_admin_notice( __( 'A página gerida foi recuperada com sucesso.', 'adam-comunidade' ), 'success' );
		}
		wp_safe_redirect( wp_get_referer() ?: Admin_Router::page_url( 'urls' ) );
		exit;
	}

	/**
	 * Warns administrators about missing or trashed pages.
	 *
	 * @return void
	 */
	public function missing_page_notices(): void {
		if ( ! current_user_can( Admin_Router::capability() ) ) {
			return;
		}
		foreach ( self::definitions() as $module => $definition ) {
			$page_id = self::id( $module, true );
			$page    = $page_id ? get_post( $page_id ) : null;
			if ( $page && 'trash' !== $page->post_status ) {
				continue;
			}
			$url = wp_nonce_url(
				add_query_arg(
					array( 'action' => 'adam_comunidade_recover_page', 'module' => $module ),
					admin_url( 'admin-post.php' )
				),
				'adam_comunidade_recover_page_' . $module
			);
			printf(
				'<div class="notice notice-warning"><p>%1$s <a class="button button-secondary" href="%2$s">%3$s</a></p></div>',
				esc_html( sprintf( __( 'A página gerida de %s está em falta ou no Lixo.', 'adam-comunidade' ), $definition['label'] ) ),
				esc_url( $url ),
				esc_html__( 'Recriar página', 'adam-comunidade' )
			);
		}
	}

	/**
	 * Shows context in the normal WordPress editor.
	 *
	 * @return void
	 */
	public function editor_notice(): void {
		$page_id = absint( $_GET['post'] ?? 0 );
		$module  = $page_id ? self::module_for_id( $page_id ) : '';
		if ( ! $module ) {
			return;
		}
		if ( 'community' === $module ) {
			echo '<div class="notice notice-success"><p>'
				. esc_html__( 'Esta landing page é totalmente editável. Pode alterar, mover ou remover os blocos e inserir os componentes dinâmicos da Comunidade onde quiser.', 'adam-comunidade' )
				. '</p></div>';
			return;
		}
		echo '<div class="notice notice-info"><p>'
			. esc_html__( 'Esta página é gerida pelo ADAM Comunidade. O conteúdo é gerado automaticamente.', 'adam-comunidade' )
			. '</p></div>';
	}

	/**
	 * Adds the editable starter composition only to a blank Community page.
	 *
	 * Existing editorial content is never replaced during upgrades.
	 */
	private static function install_community_landing(): void {
		$page_id = self::id( 'community', true );
		$page    = $page_id ? get_post( $page_id ) : null;
		if ( ! $page || '' !== trim( (string) $page->post_content ) ) {
			return;
		}

		$urls = array(
			'hero'             => defined( 'ADAM_COMUNIDADE_URL' ) ? ADAM_COMUNIDADE_URL . 'assets/images/community-hero.webp' : '',
			'teams'            => self::url( 'teams' ),
			'fields'           => self::url( 'fields' ),
			'partners'         => self::url( 'partners' ),
			'institutions'     => self::url( 'institutions' ),
			'add_team'         => home_url( '/submeter-equipa/' ),
			'add_field'        => home_url( '/submeter-campo/' ),
			'add_partner'      => home_url( '/submeter-parceiro/' ),
			'add_institution'  => home_url( '/submeter-instituicao/' ),
		);

		$content = self::community_landing_content( $urls );
		wp_update_post( array( 'ID' => $page_id, 'post_content' => $content ) );
	}

	/**
	 * Returns a native Gutenberg composition whose copy, media and order remain editable.
	 *
	 * @param array<string,string> $urls Landing destinations.
	 */
	private static function community_landing_content( array $urls ): string {
		$button = static function ( string $label, string $url, bool $outline = false ): string {
			$class   = $outline ? ' is-style-outline' : '';
			$opening = $outline ? '<!-- wp:button {"className":"is-style-outline"} -->' : '<!-- wp:button -->';
			return $opening . '<div class="wp-block-button' . $class . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div><!-- /wp:button -->';
		};
		$buttons = static fn( string $view_label, string $view_url, string $add_label, string $add_url ): string => '<!-- wp:buttons --><div class="wp-block-buttons">' . $button( $view_label, $view_url ) . $button( $add_label, $add_url, true ) . '</div><!-- /wp:buttons -->';

		$hero = '<!-- wp:cover {"url":"' . esc_url( $urls['hero'] ) . '","dimRatio":70,"minHeight":680,"minHeightUnit":"px","align":"full","className":"adam-community-landing__hero"} --><div class="wp-block-cover alignfull adam-community-landing__hero" style="min-height:680px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . esc_url( $urls['hero'] ) . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:paragraph {"className":"adam-community-landing__eyebrow"} --><p class="adam-community-landing__eyebrow">ADAM Comunidade</p><!-- /wp:paragraph --><!-- wp:heading {"level":1} --><h1 class="wp-block-heading">A comunidade constrói-se com todos.</h1><!-- /wp:heading --><!-- wp:paragraph {"fontSize":"large"} --><p class="has-large-font-size">Um ponto de encontro público para descobrir quem faz parte do airsoft, dar visibilidade a projetos locais e aproximar pessoas, espaços e organizações.</p><!-- /wp:paragraph -->' . $buttons( 'Explorar a comunidade', $urls['teams'], 'Adicionar o meu projeto', $urls['add_team'] ) . '</div><!-- /wp:group --></div></div><!-- /wp:cover -->';

		$teams = '<!-- wp:group {"align":"full","className":"adam-community-landing__section adam-community-landing__section--teams","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull adam-community-landing__section adam-community-landing__section--teams"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"adam-community-landing__eyebrow"} --><p class="adam-community-landing__eyebrow">01 · Equipas</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">Todas as equipas têm lugar</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Grandes ou pequenas, recentes ou experientes, competitivas ou descontraídas: queremos mostrar a diversidade de formas de viver o airsoft e facilitar novas ligações.</p><!-- /wp:paragraph -->' . $buttons( 'Ver equipas', $urls['teams'], 'Adicionar equipa', $urls['add_team'] ) . '</div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center","className":"adam-community-landing__visual"} --><div class="wp-block-column is-vertically-aligned-center adam-community-landing__visual"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Diferentes dimensões.<br>Diferentes estilos.<br>A mesma comunidade.</h3><!-- /wp:heading --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';

		$fields = '<!-- wp:group {"align":"full","className":"adam-community-landing__section adam-community-landing__section--fields","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull adam-community-landing__section adam-community-landing__section--fields"><!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center","className":"adam-community-landing__visual"} --><div class="wp-block-column is-vertically-aligned-center adam-community-landing__visual"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Espaços que dão vida à modalidade.</h3><!-- /wp:heading --></div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:paragraph {"className":"adam-community-landing__eyebrow"} --><p class="adam-community-landing__eyebrow">02 · Campos</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">Dar visibilidade a cada campo</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Mapeamos os espaços onde se pratica airsoft para ajudar jogadores e equipas a descobrir locais, conhecer condições e preparar novas experiências.</p><!-- /wp:paragraph -->' . $buttons( 'Ver campos', $urls['fields'], 'Adicionar campo', $urls['add_field'] ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';

		$network = '<!-- wp:group {"align":"full","className":"adam-community-landing__section adam-community-landing__section--network","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull adam-community-landing__section adam-community-landing__section--network"><!-- wp:paragraph {"align":"center","className":"adam-community-landing__eyebrow"} --><p class="has-text-align-center adam-community-landing__eyebrow">Uma rede que cresce em conjunto</p><!-- /wp:paragraph --><!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">Parceiros e instituições</h2><!-- /wp:heading --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column {"className":"adam-community-landing__card"} --><div class="wp-block-column adam-community-landing__card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Parceiros</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Projetos, lojas, marcas e serviços que apoiam a comunidade e contribuem para uma prática mais informada e sustentável.</p><!-- /wp:paragraph -->' . $buttons( 'Ver parceiros', $urls['partners'], 'Adicionar parceiro', $urls['add_partner'] ) . '</div><!-- /wp:column --><!-- wp:column {"className":"adam-community-landing__card"} --><div class="wp-block-column adam-community-landing__card"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Instituições</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Associações, entidades públicas e organizações que ajudam a criar pontes, contexto e reconhecimento para a modalidade.</p><!-- /wp:paragraph -->' . $buttons( 'Ver instituições', $urls['institutions'], 'Adicionar instituição', $urls['add_institution'] ) . '</div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';

		$cta = '<!-- wp:group {"align":"full","className":"adam-community-landing__cta","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull adam-community-landing__cta"><!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">A sua participação torna este mapa mais completo.</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Partilhe uma equipa, um campo ou uma organização. Todas as submissões são revistas pela ADAM antes da publicação.</p><!-- /wp:paragraph --><!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">' . $button( 'Adicionar equipa', $urls['add_team'] ) . $button( 'Adicionar campo', $urls['add_field'], true ) . '</div><!-- /wp:buttons --></div><!-- /wp:group -->';

		return '<!-- wp:group {"align":"full","className":"adam-community-landing","layout":{"type":"default"}} --><div class="wp-block-group alignfull adam-community-landing">' . $hero . $teams . $fields . $network . $cta . '</div><!-- /wp:group -->';
	}

	/**
	 * Flushes routes when a managed page slug changes.
	 *
	 * @param int      $post_id     Page ID.
	 * @param \WP_Post $post_after  Updated page.
	 * @param \WP_Post $post_before Previous page.
	 * @return void
	 */
	public function page_updated( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if (
			! self::$synchronizing
			&& self::module_for_id( $post_id )
			&& ( $post_after->post_name !== $post_before->post_name || $post_after->post_parent !== $post_before->post_parent )
		) {
			self::regenerate_rewrite_rules();
		}
	}

	/**
	 * Flushes routes when a stored managed Page ID changes.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value New settings.
	 * @return void
	 */
	public function settings_updated( mixed $old_value, mixed $new_value ): void {
		if ( self::$synchronizing ) {
			return;
		}
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();
		foreach ( self::definitions() as $definition ) {
			$key = $definition['option'];
			if ( absint( $old_value[ $key ] ?? 0 ) !== absint( $new_value[ $key ] ?? 0 ) ) {
				self::regenerate_rewrite_rules();
				return;
			}
		}
	}

	/**
	 * Flushes routes after permanent deletion.
	 *
	 * @param int      $post_id Page ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function page_deleted( int $post_id, \WP_Post $post ): void {
		if ( 'page' === $post->post_type && self::module_for_id( $post_id ) ) {
			$this->managed_page_deleting = true;
		}
	}

	/**
	 * Regenerates routes after WordPress has completed a managed page deletion.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public function after_page_deleted( int $post_id ): void {
		unset( $post_id );
		if ( $this->managed_page_deleting ) {
			$this->managed_page_deleting = false;
			self::regenerate_rewrite_rules();
		}
	}

	/**
	 * Handles Trash transitions for managed pages.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function page_trashed( int $post_id ): void {
		if ( self::module_for_id( $post_id ) ) {
			self::regenerate_rewrite_rules();
		}
	}

	/**
	 * Handles restoration from Trash.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public function page_untrashed( int $post_id ): void {
		if ( self::module_for_id( $post_id ) ) {
			self::regenerate_rewrite_rules();
		}
	}

	/**
	 * Prevents WordPress from collapsing managed child routes to their parent page.
	 *
	 * @param string|false $redirect Canonical redirect.
	 * @return string|false
	 */
	public function preserve_child_routes( string|false $redirect ): string|false {
		if (
			get_query_var( 'adam_team_slug' )
			|| get_query_var( 'adam_field_slug' )
			|| get_query_var( 'adam_directory_slug' )
			|| get_query_var( 'adam_compare' )
			|| get_query_var( 'adam_manager_route' )
		) {
			return false;
		}
		return $redirect;
	}

	/**
	 * Restores an existing managed page or creates exactly one new page.
	 *
	 * @param string $module Module key.
	 * @param bool   $restore_trash Whether to restore a trashed page.
	 * @return int
	 */
	private static function ensure( string $module, bool $restore_trash = false ): int {
		$definition = self::definition( $module );
		if ( ! $definition ) {
			return 0;
		}

		$page_id = self::id( $module, true );
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page ) {
			$matches = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::META_KEY,
					'meta_value'     => $module,
				)
			);
			$page_id = absint( $matches[0] ?? 0 );
			$page    = $page_id ? get_post( $page_id ) : null;
		}

		if ( $page ) {
			if ( 'trash' === $page->post_status && $restore_trash ) {
				wp_untrash_post( $page_id );
			}
			self::store_id( $module, $page_id );
			return $page_id;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $definition['default_title'],
				'post_name'    => $definition['default_slug'],
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return 0;
		}
		update_post_meta( $page_id, self::META_KEY, $module );
		self::store_id( $module, $page_id );
		return $page_id;
	}

	/**
	 * Updates one ID within the shared plugin settings.
	 *
	 * @param string $module  Module key.
	 * @param int    $page_id Page ID.
	 * @return void
	 */
	private static function store_id( string $module, int $page_id ): void {
		$definition = self::definition( $module );
		if ( ! $definition ) {
			return;
		}
		$settings = wp_parse_args( get_option( Settings::OPTION_NAME, array() ), Settings::defaults() );
		if ( absint( $settings[ $definition['option'] ] ?? 0 ) === $page_id ) {
			return;
		}
		$settings[ $definition['option'] ] = $page_id;
		update_option( Settings::OPTION_NAME, $settings, false );
	}

	/**
	 * Returns one definition.
	 *
	 * @param string $module Module key.
	 * @return array{label:string,default_title:string,default_slug:string,option:string}|null
	 */
	private static function definition( string $module ): ?array {
		return self::definitions()[ sanitize_key( $module ) ] ?? null;
	}

	/**
	 * Re-registers dynamic rules and flushes WordPress rewrite rules.
	 *
	 * @return void
	 */
	private static function regenerate_rewrite_rules(): void {
		Teams\Router::add_rewrite_rules();
		Fields\Router::add_rewrite_rules();
		Directory\Router::add_rewrite_rules();
		Directory\Rest_API::add_rewrite_rules();
		Experience\Router::add_rewrite_rules();
		Experience\Api_V2::add_rewrite_rules();
		Experience\Portal::add_rewrite_rules();
		Events\Router::add_rewrite_rules();
		Managers\Portal::add_rewrite_rules();
		flush_rewrite_rules( false );
	}
}
