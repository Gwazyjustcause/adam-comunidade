<?php
/**
 * Shared form manager.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin adam-form-manager">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Gestor de Formulários', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Configure todos os formulários públicos num único local. Arraste os campos para alterar a ordem.', 'adam-comunidade' ); ?></p>
		</div>
	</header>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Formulários guardados com sucesso.', 'adam-comunidade' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Formulários disponíveis', 'adam-comunidade' ); ?>">
		<?php $first = true; foreach ( $types as $type => $label ) : ?>
			<a href="#adam-form-<?php echo esc_attr( $type ); ?>" class="nav-tab <?php echo $first ? 'nav-tab-active' : ''; ?>" data-adam-form-tab="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php $first = false; endforeach; ?>
	</nav>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_comunidade_save_forms">
		<?php wp_nonce_field( 'adam_comunidade_save_forms' ); ?>

		<?php $first = true; foreach ( $forms as $type => $form ) : ?>
			<section id="adam-form-<?php echo esc_attr( $type ); ?>" class="adam-form-manager__panel <?php echo $first ? 'is-active' : ''; ?>" data-adam-form-panel="<?php echo esc_attr( $type ); ?>">
				<div class="adam-card">
					<h2><?php echo esc_html( $types[ $type ] ); ?></h2>
					<div class="adam-form-manager__messages">
						<label><?php esc_html_e( 'Título do formulário', 'adam-comunidade' ); ?><input class="regular-text" name="forms[<?php echo esc_attr( $type ); ?>][title]" value="<?php echo esc_attr( $form['title'] ); ?>"></label>
						<label><?php esc_html_e( 'Descrição', 'adam-comunidade' ); ?><textarea name="forms[<?php echo esc_attr( $type ); ?>][description]" rows="2"><?php echo esc_textarea( $form['description'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Título do aviso', 'adam-comunidade' ); ?><input class="regular-text" name="forms[<?php echo esc_attr( $type ); ?>][notice_title]" value="<?php echo esc_attr( $form['notice_title'] ); ?>"></label>
						<label><?php esc_html_e( 'Texto do aviso', 'adam-comunidade' ); ?><textarea name="forms[<?php echo esc_attr( $type ); ?>][notice_text]" rows="3"><?php echo esc_textarea( $form['notice_text'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Texto de confirmação', 'adam-comunidade' ); ?><textarea name="forms[<?php echo esc_attr( $type ); ?>][confirmation_message]" rows="2"><?php echo esc_textarea( $form['confirmation_message'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Mensagem de sucesso', 'adam-comunidade' ); ?><textarea name="forms[<?php echo esc_attr( $type ); ?>][success_message]" rows="2"><?php echo esc_textarea( $form['success_message'] ); ?></textarea></label>
						<label><?php esc_html_e( 'Texto do botão', 'adam-comunidade' ); ?><input class="regular-text" name="forms[<?php echo esc_attr( $type ); ?>][submit_label]" value="<?php echo esc_attr( $form['submit_label'] ); ?>"></label>
					</div>
				</div>

				<div class="adam-card adam-form-builder">
					<h2><?php esc_html_e( 'Campos do formulário', 'adam-comunidade' ); ?></h2>
					<p><?php esc_html_e( 'Use a pega para ordenar. As alterações são aplicadas ao formulário público depois de guardar.', 'adam-comunidade' ); ?></p>
					<div class="adam-form-builder__rows" data-adam-sortable>
						<?php $index = 0; foreach ( $form['fields'] as $key => $field ) : ?>
							<article class="adam-form-builder__row">
								<span class="dashicons dashicons-move adam-form-builder__handle" aria-label="<?php esc_attr_e( 'Arrastar para ordenar', 'adam-comunidade' ); ?>"></span>
								<input type="hidden" name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $key ); ?>">
								<div class="adam-form-builder__main">
									<strong><?php echo esc_html( $field['label'] ); ?></strong>
									<code><?php echo esc_html( $key ); ?></code>
									<div class="adam-form-builder__grid">
										<label><?php esc_html_e( 'Etiqueta', 'adam-comunidade' ); ?><input name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>"></label>
										<label><?php esc_html_e( 'Tipo', 'adam-comunidade' ); ?><select name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][type]"><?php foreach ( $field_types as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
										<label><?php esc_html_e( 'Texto de exemplo', 'adam-comunidade' ); ?><input name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>"></label>
										<label class="adam-form-builder__wide"><?php esc_html_e( 'Descrição do campo', 'adam-comunidade' ); ?><textarea name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][description]" rows="2"><?php echo esc_textarea( $field['description'] ); ?></textarea></label>
										<label class="adam-form-builder__wide"><?php esc_html_e( 'Texto de ajuda', 'adam-comunidade' ); ?><textarea name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][help_text]" rows="2"><?php echo esc_textarea( $field['help_text'] ); ?></textarea></label>
										<label><?php esc_html_e( 'Tipos de ficheiro aceites', 'adam-comunidade' ); ?><input name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][accept]" value="<?php echo esc_attr( $field['accept'] ); ?>" placeholder=".pdf,.jpg,.png"></label>
										<label><?php esc_html_e( 'Limite de ficheiros', 'adam-comunidade' ); ?><input type="number" min="1" max="20" name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][max_files]" value="<?php echo esc_attr( $field['max_files'] ); ?>"></label>
										<label><?php esc_html_e( 'Tamanho máximo por ficheiro (MB)', 'adam-comunidade' ); ?><input type="number" min="1" max="100" name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][max_size_mb]" value="<?php echo esc_attr( $field['max_size_mb'] ); ?>"></label>
									</div>
								</div>
								<div class="adam-form-builder__toggles">
									<label><input type="checkbox" name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][visible]" value="1" <?php checked( $field['visible'] ); ?>> <?php esc_html_e( 'Visível', 'adam-comunidade' ); ?></label>
									<label><input type="checkbox" name="forms[<?php echo esc_attr( $type ); ?>][fields][<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( $field['required'] ); ?>> <?php esc_html_e( 'Obrigatório', 'adam-comunidade' ); ?></label>
								</div>
							</article>
						<?php ++$index; endforeach; ?>
					</div>
				</div>
			</section>
		<?php $first = false; endforeach; ?>
		<?php submit_button( __( 'Guardar formulários', 'adam-comunidade' ), 'primary adam-button' ); ?>
	</form>
</div>
