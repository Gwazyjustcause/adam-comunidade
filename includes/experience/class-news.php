<?php
/**
 * Community News content module.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Helpers;

/**
 * Registers a lightweight editorial post type with relationship metadata.
 */
final class News {
	public function register(): void {
		if ( is_admin() ) {
			Admin_Router::register_module(
				'news',
				array(
					'title'         => __( 'Notícias', 'adam-comunidade' ),
					'singular'      => __( 'Notícia', 'adam-comunidade' ),
					'singular_slug' => 'news',
					'controller'    => $this,
					'methods'       => array( 'list' => 'list', 'create' => 'create', 'edit' => 'edit' ),
				)
			);
		}
		add_action( 'init', array( $this, 'register_content' ) );
		add_action( 'add_meta_boxes_adam_news', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_adam_news', array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'template_include', array( $this, 'template' ), 30 );
		add_action( 'pre_get_posts', array( $this, 'protect_member_news' ) );
	}

	public function register_content(): void {
		register_post_type(
			'adam_news',
			array(
				'labels' => array(
					'name'          => __( 'Notícias', 'adam-comunidade' ),
					'singular_name' => __( 'Notícia', 'adam-comunidade' ),
					'add_new_item'  => __( 'Adicionar notícia da Comunidade', 'adam-comunidade' ),
					'edit_item'     => __( 'Editar notícia da Comunidade', 'adam-comunidade' ),
				),
				'public'       => true,
				'show_in_menu' => false,
				'has_archive'  => 'noticias',
				'rewrite'      => array( 'slug' => 'noticias', 'with_front' => false ),
				'menu_icon'    => 'dashicons-megaphone',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
				'show_in_rest' => true,
			)
		);
		register_taxonomy(
			'adam_news_category',
			'adam_news',
			array(
				'labels'       => array( 'name' => __( 'Categorias de notícias', 'adam-comunidade' ), 'singular_name' => __( 'Categoria de notícia', 'adam-comunidade' ) ),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'noticias/categoria' ),
			)
		);
	}

	/**
	 * Opens the native WordPress list through the ADAM route.
	 *
	 * @return never
	 */
	public function list(): never {
		wp_safe_redirect( admin_url( 'edit.php?post_type=adam_news' ) );
		exit;
	}

	/**
	 * Opens the native WordPress editor in create mode.
	 *
	 * @return never
	 */
	public function create(): never {
		wp_safe_redirect( admin_url( 'post-new.php?post_type=adam_news' ) );
		exit;
	}

	/**
	 * Opens the native WordPress editor for one news item.
	 *
	 * @return never
	 */
	public function edit( int $post_id ): never {
		$edit_url = $post_id ? get_edit_post_link( $post_id, 'raw' ) : '';
		wp_safe_redirect( $edit_url ?: Admin_Router::module_url( 'news', 'add' ) );
		exit;
	}

	public function add_meta_box(): void {
		add_meta_box( 'adam-news-community', __( 'Relações na Comunidade', 'adam-comunidade' ), array( $this, 'render_meta_box' ), 'adam_news', 'side' );
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'adam_news_meta', 'adam_news_nonce' );
		$type = (string) get_post_meta( $post->ID, '_adam_related_type', true );
		$id   = (int) get_post_meta( $post->ID, '_adam_related_id', true );
		?>
		<p><label for="adam-related-type"><?php esc_html_e( 'Módulo relacionado', 'adam-comunidade' ); ?></label>
		<select id="adam-related-type" name="adam_related_type"><option value=""><?php esc_html_e( 'Nenhum', 'adam-comunidade' ); ?></option>
		<?php foreach ( array( 'team' => __( 'Equipa', 'adam-comunidade' ), 'field' => __( 'Campo', 'adam-comunidade' ), 'partner' => __( 'Parceiro', 'adam-comunidade' ), 'institution' => __( 'Instituição', 'adam-comunidade' ) ) as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
		<p><label for="adam-related-id"><?php esc_html_e( 'ID do registo relacionado', 'adam-comunidade' ); ?></label><input id="adam-related-id" name="adam_related_id" type="number" min="0" value="<?php echo esc_attr( $id ); ?>"></p>
		<p><label><input type="checkbox" name="adam_news_featured" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_adam_featured', true ) ); ?>> <?php esc_html_e( 'Notícia em destaque', 'adam-comunidade' ); ?></label></p>
		<p><label><input type="checkbox" name="adam_news_members_only" value="1" <?php checked( (bool) get_post_meta( $post->ID, '_adam_members_only', true ) ); ?>> <?php esc_html_e( 'Apenas membros ADAM', 'adam-comunidade' ); ?></label></p>
		<?php
	}

	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['adam_news_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['adam_news_nonce'] ) ), 'adam_news_meta' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$type = sanitize_key( $_POST['adam_related_type'] ?? '' );
		if ( ! in_array( $type, array( '', 'team', 'field', 'partner', 'institution' ), true ) ) {
			$type = '';
		}
		update_post_meta( $post_id, '_adam_related_type', $type );
		update_post_meta( $post_id, '_adam_related_id', absint( $_POST['adam_related_id'] ?? 0 ) );
		update_post_meta( $post_id, '_adam_featured', empty( $_POST['adam_news_featured'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_adam_members_only', empty( $_POST['adam_news_members_only'] ) ? 0 : 1 );
		do_action( 'adam_comunidade_news_saved', $post_id, $post );
	}

	public function template( string $template ): string {
		if ( is_post_type_archive( 'adam_news' ) ) {
			return Templates::locate( 'experience/news-archive.php' );
		}
		if ( is_singular( 'adam_news' ) ) {
			return Templates::locate( 'experience/news-single.php' );
		}
		return $template;
	}

	public function protect_member_news( \WP_Query $query ): void {
		if ( is_admin() || Related_Content::is_member() || ! $query->is_main_query() || ( ! $query->is_post_type_archive( 'adam_news' ) && ! $query->is_singular( 'adam_news' ) ) ) {
			return;
		}
		$query->set(
			'meta_query',
			array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_adam_members_only', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_adam_members_only', 'value' => '0' ),
			)
		);
	}

	/**
	 * Returns published news for public components.
	 *
	 * @return \WP_Post[]
	 */
	public static function latest( int $number = 6, string $search = '', string $district = '', bool $featured = false, bool $include_member_content = true ): array {
		$meta_query = array();
		if ( $featured ) {
			$meta_query[] = array( 'key' => '_adam_featured', 'value' => '1' );
		}
		if ( ! $include_member_content || ! Related_Content::is_member() ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => '_adam_members_only', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_adam_members_only', 'value' => '0' ),
			);
		}
		$query = new \WP_Query(
			array(
				'post_type'           => 'adam_news',
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, min( 50, $number ) ),
				's'                   => $search,
				'meta_query'          => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		unset( $district );
		return $query->posts;
	}
}
