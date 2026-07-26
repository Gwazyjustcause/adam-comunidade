<?php
/**
 * Amenity vocabulary manager.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin adam-amenities-admin">
	<header class="adam-page-header">
		<div>
			<a class="adam-back-link" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields' ) ); ?>">
				&larr; <?php esc_html_e( 'Voltar aos campos', 'adam-comunidade' ); ?>
			</a>
			<h1><?php esc_html_e( 'Comodidades dos campos', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Altere nomes, oculte, ordene e escolha ícones sem modificar o editor de campos.', 'adam-comunidade' ); ?></p>
		</div>
	</header>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_amenities_save">
		<?php wp_nonce_field( 'adam_amenities_save' ); ?>
		<div class="adam-card">
			<table class="adam-table adam-amenities-table">
				<thead><tr>
					<th><?php esc_html_e( 'Etiqueta', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Ícone', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Ordem', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Visível', 'adam-comunidade' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $amenity_options as $amenity ) : ?>
						<tr>
							<td><input type="text" name="amenities[<?php echo esc_attr( (string) $amenity->id ); ?>][label]" value="<?php echo esc_attr( $amenity->label ); ?>" required></td>
							<td><select name="amenities[<?php echo esc_attr( (string) $amenity->id ); ?>][icon]">
								<?php foreach ( $icon_options as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $amenity->icon, $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></td>
							<td><input type="number" min="0" name="amenities[<?php echo esc_attr( (string) $amenity->id ); ?>][sort_order]" value="<?php echo esc_attr( (string) $amenity->sort_order ); ?>"></td>
							<td><label class="adam-switch"><input type="checkbox" name="amenities[<?php echo esc_attr( (string) $amenity->id ); ?>][status]" value="active" <?php checked( $amenity->status, 'active' ); ?>><span></span></label></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="adam-card adam-new-amenity">
			<h2><?php esc_html_e( 'Adicionar comodidade', 'adam-comunidade' ); ?></h2>
			<input type="text" name="new_amenity[label]" placeholder="<?php esc_attr_e( 'Nome da comodidade', 'adam-comunidade' ); ?>">
			<select name="new_amenity[icon]">
				<?php foreach ( $icon_options as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="number" min="0" name="new_amenity[sort_order]" value="<?php echo esc_attr( (string) count( $amenity_options ) ); ?>">
		</div>
		<?php submit_button( __( 'Guardar comodidades', 'adam-comunidade' ), 'primary adam-button' ); ?>
	</form>
</div>
