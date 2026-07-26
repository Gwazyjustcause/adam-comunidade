<?php
/**
 * Homepage builder view.
 *
 * @var array<int,array<string,mixed>> $sections
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Builder;
?>
<div class="wrap adam-comunidade-admin">
	<h1><?php esc_html_e( 'Community Homepage Builder', 'adam-comunidade' ); ?></h1>
	<p><?php esc_html_e( 'Drag sections into the desired order. Use the [adam_community_home] shortcode on any page.', 'adam-comunidade' ); ?></p>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_home_builder_save">
		<?php wp_nonce_field( 'adam_home_builder_save' ); ?>
		<div class="adam-builder-list" data-adam-builder-sortable>
			<?php foreach ( $sections as $index => $section ) : ?>
				<div class="adam-comunidade__card adam-builder-row">
					<span class="dashicons dashicons-move" aria-hidden="true"></span>
					<input type="hidden" name="sections[<?php echo esc_attr( $index ); ?>][type]" value="<?php echo esc_attr( $section['type'] ); ?>">
					<strong><?php echo esc_html( Builder::definitions()[ $section['type'] ] ?? $section['type'] ); ?></strong>
					<label><input type="checkbox" name="sections[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $section['enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'adam-comunidade' ); ?></label>
					<label><?php esc_html_e( 'Cards', 'adam-comunidade' ); ?><input type="number" min="1" max="24" name="sections[<?php echo esc_attr( $index ); ?>][number]" value="<?php echo esc_attr( $section['number'] ); ?>"></label>
					<label><?php esc_html_e( 'Order', 'adam-comunidade' ); ?><select name="sections[<?php echo esc_attr( $index ); ?>][order]"><option value="newest" <?php selected( $section['order'], 'newest' ); ?>><?php esc_html_e( 'Newest', 'adam-comunidade' ); ?></option><option value="alphabetical" <?php selected( $section['order'], 'alphabetical' ); ?>><?php esc_html_e( 'Alphabetical', 'adam-comunidade' ); ?></option><option value="random" <?php selected( $section['order'], 'random' ); ?>><?php esc_html_e( 'Random', 'adam-comunidade' ); ?></option></select></label>
					<label><?php esc_html_e( 'Category key', 'adam-comunidade' ); ?><input type="text" name="sections[<?php echo esc_attr( $index ); ?>][category]" value="<?php echo esc_attr( $section['category'] ); ?>"></label>
					<label><input type="checkbox" name="sections[<?php echo esc_attr( $index ); ?>][featured]" value="1" <?php checked( $section['featured'] ); ?>> <?php esc_html_e( 'Featured only', 'adam-comunidade' ); ?></label>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="submit"><button class="button button-primary"><?php esc_html_e( 'Save Homepage Layout', 'adam-comunidade' ); ?></button></p>
	</form>
</div>
