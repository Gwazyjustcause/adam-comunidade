<?php
/**
 * Rich media administration.
 *
 * @var object[]             $items Media rows.
 * @var array<string,string> $types Media types.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<h1><?php esc_html_e( 'Rich Media', 'adam-comunidade' ); ?></h1>
	<p><?php esc_html_e( 'Attach 360 images, YouTube videos, Instagram posts, virtual tours or downloads to any community listing.', 'adam-comunidade' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adam-card">
		<input type="hidden" name="action" value="adam_rich_media_save">
		<?php wp_nonce_field( 'adam_rich_media_save' ); ?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Listing', 'adam-comunidade' ); ?></th><td><select name="object_type"><?php foreach ( array( 'team', 'field', 'partner', 'institution', 'brand' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?></select> <input type="number" min="1" name="object_id" required placeholder="<?php esc_attr_e( 'Listing ID', 'adam-comunidade' ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Media type', 'adam-comunidade' ); ?></th><td><select name="media_type"><?php foreach ( $types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><?php esc_html_e( 'URL', 'adam-comunidade' ); ?></th><td><input class="large-text" type="url" name="media_url" required></td></tr>
			<tr><th><?php esc_html_e( 'Caption', 'adam-comunidade' ); ?></th><td><input class="large-text" name="caption"></td></tr>
			<tr><th><?php esc_html_e( 'Order', 'adam-comunidade' ); ?></th><td><input type="number" min="0" name="sort_order" value="0"></td></tr>
		</table>
		<?php submit_button( __( 'Add media', 'adam-comunidade' ) ); ?>
	</form>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Listing', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Type', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'URL', 'adam-comunidade' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr><td><?php echo esc_html( $item->object_type . ' #' . $item->object_id ); ?></td><td><?php echo esc_html( $types[ $item->media_type ] ?? $item->media_type ); ?></td><td><a href="<?php echo esc_url( $item->media_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item->caption ?: $item->media_url ); ?></a></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
