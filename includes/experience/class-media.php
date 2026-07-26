<?php
/**
 * Image performance and accessibility defaults.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Uses WordPress responsive media APIs and modern output formats.
 */
final class Media {
	public function register(): void {
		add_action( 'init', array( $this, 'sizes' ) );
		add_filter( 'image_editor_output_format', array( $this, 'modern_formats' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'attributes' ), 10, 3 );
		add_filter( 'wp_preload_resources', array( $this, 'preloads' ) );
		add_shortcode( 'adam_rich_media', array( $this, 'shortcode' ) );
		add_action( 'adam_comunidade_admin_menu', array( $this, 'menu' ), 38, 2 );
		add_action( 'admin_post_adam_rich_media_save', array( $this, 'save' ) );
		add_action( 'adam_comunidade_team_after_content', array( $this, 'for_team' ), 15 );
		add_action( 'adam_comunidade_field_after_content', array( $this, 'for_field' ), 15 );
		add_action( 'adam_comunidade_directory_entry_content', array( $this, 'for_directory' ), 15 );
	}

	public function menu( string $parent, string $capability ): void {
		add_submenu_page( $parent, __( 'Rich Media', 'adam-comunidade' ), __( 'Rich Media', 'adam-comunidade' ), $capability, 'adam-comunidade-media', array( $this, 'admin_page' ) );
	}

	public function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage rich media.', 'adam-comunidade' ) );
		}
		global $wpdb;
		$items = $wpdb->get_results( 'SELECT * FROM ' . Schema::media_table() . ' ORDER BY object_type, object_id, sort_order LIMIT 200' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Rich Media', 'adam-comunidade' ); ?></h1><p><?php esc_html_e( 'Attach 360 images, YouTube videos, Instagram posts, virtual tours or downloads to any community listing.', 'adam-comunidade' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adam-card"><input type="hidden" name="action" value="adam_rich_media_save"><?php wp_nonce_field( 'adam_rich_media_save' ); ?>
		<table class="form-table"><tr><th><?php esc_html_e( 'Listing', 'adam-comunidade' ); ?></th><td><select name="object_type"><?php foreach ( array( 'team', 'field', 'partner', 'institution', 'brand' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?></select> <input type="number" min="1" name="object_id" required placeholder="<?php esc_attr_e( 'Listing ID', 'adam-comunidade' ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Media type', 'adam-comunidade' ); ?></th><td><select name="media_type"><?php foreach ( self::types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><?php esc_html_e( 'URL', 'adam-comunidade' ); ?></th><td><input class="large-text" type="url" name="media_url" required></td></tr>
		<tr><th><?php esc_html_e( 'Caption', 'adam-comunidade' ); ?></th><td><input class="large-text" name="caption"></td></tr>
		<tr><th><?php esc_html_e( 'Order', 'adam-comunidade' ); ?></th><td><input type="number" min="0" name="sort_order" value="0"></td></tr></table><?php submit_button( __( 'Add media', 'adam-comunidade' ) ); ?></form>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Listing', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Type', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'URL', 'adam-comunidade' ); ?></th></tr></thead><tbody><?php foreach ( $items as $item ) : ?><tr><td><?php echo esc_html( $item->object_type . ' #' . $item->object_id ); ?></td><td><?php echo esc_html( self::types()[ $item->media_type ] ?? $item->media_type ); ?></td><td><a href="<?php echo esc_url( $item->media_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item->caption ?: $item->media_url ); ?></a></td></tr><?php endforeach; ?></tbody></table></div>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage rich media.', 'adam-comunidade' ) );
		}
		check_admin_referer( 'adam_rich_media_save' );
		$type = sanitize_key( wp_unslash( $_POST['media_type'] ?? '' ) );
		$url  = esc_url_raw( wp_unslash( $_POST['media_url'] ?? '' ), array( 'http', 'https' ) );
		if ( ! isset( self::types()[ $type ] ) || ! wp_http_validate_url( $url ) ) {
			wp_die( esc_html__( 'Choose a supported media type and valid URL.', 'adam-comunidade' ) );
		}
		global $wpdb;
		$wpdb->insert( Schema::media_table(), array( 'object_type' => sanitize_key( wp_unslash( $_POST['object_type'] ?? '' ) ), 'object_id' => absint( $_POST['object_id'] ?? 0 ), 'media_type' => $type, 'media_url' => $url, 'caption' => sanitize_text_field( wp_unslash( $_POST['caption'] ?? '' ) ), 'sort_order' => absint( $_POST['sort_order'] ?? 0 ) ) );
		wp_safe_redirect( admin_url( 'admin.php?page=adam-comunidade-media' ) );
		exit;
	}

	public function shortcode( array $attributes ): string {
		$attributes = shortcode_atts( array( 'type' => '', 'id' => 0 ), $attributes, 'adam_rich_media' );
		$object_type = sanitize_key( $attributes['type'] );
		$object_id   = absint( $attributes['id'] );
		if ( ! $object_type || ! $object_id ) {
			return '';
		}
		global $wpdb;
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::media_table() . ' WHERE object_type = %s AND object_id = %d ORDER BY sort_order ASC', $object_type, $object_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $items ) {
			return '';
		}
		$html = '<section class="adam-rich-media"><h2>' . esc_html__( 'Media', 'adam-comunidade' ) . '</h2><div class="adam-community-grid">';
		foreach ( $items as $item ) {
			$label = $item->caption ?: ( self::types()[ $item->media_type ] ?? __( 'Open media', 'adam-comunidade' ) );
			if ( 'youtube' === $item->media_type ) {
				$embed = wp_oembed_get( $item->media_url, array( 'width' => 720 ) );
				$html .= '<figure>' . ( $embed ?: '<a href="' . esc_url( $item->media_url ) . '">' . esc_html( $label ) . '</a>' ) . '<figcaption>' . esc_html( $item->caption ) . '</figcaption></figure>';
			} elseif ( 'image_360' === $item->media_type ) {
				$html .= '<figure class="adam-media-360"><img src="' . esc_url( $item->media_url ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" decoding="async"><figcaption>' . esc_html( $label ) . '</figcaption></figure>';
			} else {
				$html .= '<a class="adam-community-button adam-community-button--ghost" href="' . esc_url( $item->media_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
			}
		}
		return $html . '</div></section>';
	}

	public function for_team( object $item ): void {
		echo do_shortcode( '[adam_rich_media type="team" id="' . absint( $item->id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function for_field( object $item ): void {
		echo do_shortcode( '[adam_rich_media type="field" id="' . absint( $item->id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function for_directory( object $item ): void {
		echo do_shortcode( '[adam_rich_media type="' . esc_attr( $item->entity_type ) . '" id="' . absint( $item->id ) . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @return array<string,string>
	 */
	public static function types(): array {
		return (array) apply_filters( 'adam_comunidade_rich_media_types', array( 'image_360' => __( '360 image', 'adam-comunidade' ), 'youtube' => __( 'YouTube video', 'adam-comunidade' ), 'instagram' => __( 'Instagram post', 'adam-comunidade' ), 'virtual_tour' => __( 'Virtual tour', 'adam-comunidade' ), 'download' => __( 'Download', 'adam-comunidade' ) ) );
	}

	public function sizes(): void {
		add_image_size( 'adam-community-thumb', 480, 300, true );
		add_image_size( 'adam-community-wide', 1280, 720, true );
	}

	public function modern_formats( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return apply_filters( 'adam_comunidade_image_output_formats', $formats );
	}

	public function attributes( array $attributes, \WP_Post $attachment, string|array $size ): array {
		unset( $attachment, $size );
		$attributes['decoding'] = 'async';
		if ( empty( $attributes['fetchpriority'] ) ) {
			$attributes['loading'] = $attributes['loading'] ?? 'lazy';
		}
		return $attributes;
	}

	public function preloads( array $preloads ): array {
		if ( is_singular( 'adam_news' ) && has_post_thumbnail() ) {
			$url = get_the_post_thumbnail_url( get_queried_object_id(), 'adam-community-wide' );
			if ( $url ) {
				$preloads[] = array( 'href' => $url, 'as' => 'image', 'fetchpriority' => 'high' );
			}
		}
		return $preloads;
	}
}
