<?php
/**
 * Team editor view.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Teams\Options;

$adam_value = static function ( string $key, mixed $default = '' ) use ( $team ): mixed {
	return $team->{$key} ?? $default;
};
$adam_gallery          = Options::decode_list( $adam_value( 'gallery', array() ) );
$adam_playing_styles   = Options::decode_list( $adam_value( 'playing_styles', array() ) );
$adam_equipment_tags   = Options::decode_list( $adam_value( 'equipment_tags', array() ) );
$adam_status           = (string) $adam_value( 'status', 'draft' );
$adam_slug             = (string) $adam_value( 'slug' );
$adam_preview_url      = '';

if ( $team_id && $adam_slug ) {
	$adam_preview_url = add_query_arg(
		array(
			'adam_preview' => $team_id,
			'_wpnonce'     => wp_create_nonce( 'adam_team_preview_' . $team_id ),
		),
		trailingslashit( \ADAM\Comunidade\Managed_Pages::url( 'teams' ) ) . user_trailingslashit( $adam_slug )
	);
}
?>
<div class="wrap adam-comunidade-admin adam-team-editor">
	<header class="adam-page-header">
		<div>
			<a class="adam-back-link" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'teams' ) ); ?>">
				&larr; <?php esc_html_e( 'Voltar às equipas', 'adam-comunidade' ); ?>
			</a>
			<h1><?php echo $team_id ? esc_html__( 'Editar equipa', 'adam-comunidade' ) : esc_html__( 'Adicionar equipa', 'adam-comunidade' ); ?></h1>
		</div>
		<?php if ( $adam_preview_url ) : ?>
			<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( $adam_preview_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Pré-visualizar', 'adam-comunidade' ); ?>
			</a>
		<?php endif; ?>
	</header>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_team_save">
		<input type="hidden" name="team_id" value="<?php echo esc_attr( (string) $team_id ); ?>">
		<?php wp_nonce_field( 'adam_team_save' ); ?>

		<div class="adam-editor-layout">
			<div>
				<nav class="adam-editor-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Secções da equipa', 'adam-comunidade' ); ?>">
					<?php
					$adam_tabs = array(
						'basic'       => __( 'Informação principal', 'adam-comunidade' ),
						'branding'    => __( 'Imagem', 'adam-comunidade' ),
						'description' => __( 'Descrição', 'adam-comunidade' ),
						'location'    => __( 'Localização', 'adam-comunidade' ),
						'contacts'    => __( 'Contactos', 'adam-comunidade' ),
						'details'     => __( 'Detalhes da equipa', 'adam-comunidade' ),
						'fields'      => __( 'Campos associados', 'adam-comunidade' ),
						'seo'         => __( 'SEO', 'adam-comunidade' ),
					);
					foreach ( $adam_tabs as $adam_tab_id => $adam_tab_label ) :
						?>
						<button
							type="button"
							role="tab"
							aria-selected="<?php echo 'basic' === $adam_tab_id ? 'true' : 'false'; ?>"
							aria-controls="adam-team-panel-<?php echo esc_attr( $adam_tab_id ); ?>"
							id="adam-team-tab-<?php echo esc_attr( $adam_tab_id ); ?>"
							data-adam-tab="<?php echo esc_attr( $adam_tab_id ); ?>"
						>
							<?php echo esc_html( $adam_tab_label ); ?>
						</button>
					<?php endforeach; ?>
				</nav>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-basic" role="tabpanel" aria-labelledby="adam-team-tab-basic">
					<h2><?php esc_html_e( 'Informação principal', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<label class="adam-field adam-field--wide">
							<span><?php esc_html_e( 'Nome da equipa', 'adam-comunidade' ); ?> *</span>
							<input id="adam-team-name" type="text" name="team[name]" value="<?php echo esc_attr( (string) $adam_value( 'name' ) ); ?>" required maxlength="191">
						</label>
						<label class="adam-field">
							<span><?php esc_html_e( 'Nome curto', 'adam-comunidade' ); ?></span>
							<input type="text" name="team[short_name]" value="<?php echo esc_attr( (string) $adam_value( 'short_name' ) ); ?>" maxlength="100">
						</label>
						<label class="adam-field">
							<span><?php esc_html_e( 'Slug', 'adam-comunidade' ); ?> *</span>
							<input id="adam-team-slug" type="text" name="team[slug]" value="<?php echo esc_attr( $adam_slug ); ?>" required maxlength="191">
						</label>
					</div>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-branding" role="tabpanel" aria-labelledby="adam-team-tab-branding" hidden>
					<h2><?php esc_html_e( 'Imagem', 'adam-comunidade' ); ?></h2>
					<div class="adam-media-grid">
						<div class="adam-media-field" data-adam-media="single">
							<h3><?php esc_html_e( 'Logótipo', 'adam-comunidade' ); ?></h3>
							<p><?php esc_html_e( 'Recomendado: 600×600 px, recorte quadrado.', 'adam-comunidade' ); ?></p>
							<input type="hidden" name="team[logo_id]" value="<?php echo esc_attr( (string) $adam_value( 'logo_id', 0 ) ); ?>">
							<div class="adam-media-preview">
								<?php echo wp_get_attachment_image( (int) $adam_value( 'logo_id', 0 ), 'adam-team-logo' ); ?>
							</div>
							<button class="button adam-media-select" type="button" data-media-kind="logo"><?php esc_html_e( 'Escolher logótipo', 'adam-comunidade' ); ?></button>
							<button class="button-link-delete adam-media-remove" type="button"><?php esc_html_e( 'Remover', 'adam-comunidade' ); ?></button>
							<?php if ( $adam_value( 'logo_id', 0 ) ) : ?>
								<a class="adam-media-edit" href="<?php echo esc_url( get_edit_post_link( (int) $adam_value( 'logo_id', 0 ) ) ); ?>" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Editar / recortar logótipo', 'adam-comunidade' ); ?>
								</a>
							<?php endif; ?>
						</div>
						<div class="adam-media-field" data-adam-media="single">
							<h3><?php esc_html_e( 'Imagem de capa', 'adam-comunidade' ); ?></h3>
							<p><?php esc_html_e( 'Recomendado: 1920×600 px.', 'adam-comunidade' ); ?></p>
							<input type="hidden" name="team[cover_id]" value="<?php echo esc_attr( (string) $adam_value( 'cover_id', 0 ) ); ?>">
							<div class="adam-media-preview adam-media-preview--cover">
								<?php echo wp_get_attachment_image( (int) $adam_value( 'cover_id', 0 ), 'adam-team-cover' ); ?>
							</div>
							<button class="button adam-media-select" type="button" data-media-kind="cover"><?php esc_html_e( 'Escolher capa', 'adam-comunidade' ); ?></button>
							<button class="button-link-delete adam-media-remove" type="button"><?php esc_html_e( 'Remover', 'adam-comunidade' ); ?></button>
						</div>
					</div>
					<div class="adam-media-field adam-gallery-field" data-adam-media="gallery">
						<h3><?php esc_html_e( 'Galeria', 'adam-comunidade' ); ?></h3>
						<p><?php esc_html_e( 'Selecione várias imagens e arraste as miniaturas para alterar a ordem.', 'adam-comunidade' ); ?></p>
						<div class="adam-gallery-list">
							<?php foreach ( $adam_gallery as $adam_image_id ) : ?>
								<div class="adam-gallery-item" data-attachment-id="<?php echo esc_attr( (string) $adam_image_id ); ?>">
									<input type="hidden" name="team[gallery][]" value="<?php echo esc_attr( (string) $adam_image_id ); ?>">
									<?php echo wp_get_attachment_image( $adam_image_id, 'thumbnail' ); ?>
									<button type="button" class="adam-gallery-remove" aria-label="<?php esc_attr_e( 'Remover imagem', 'adam-comunidade' ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button class="button adam-media-select" type="button" data-media-kind="gallery"><?php esc_html_e( 'Escolher imagens da galeria', 'adam-comunidade' ); ?></button>
					</div>
					<label class="adam-field adam-colour-field">
						<span><?php esc_html_e( 'Cor da equipa', 'adam-comunidade' ); ?></span>
						<input class="adam-team-colour" type="text" name="team[team_colour]" value="<?php echo esc_attr( (string) $adam_value( 'team_colour' ) ); ?>" data-default-color="">
					</label>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-description" role="tabpanel" aria-labelledby="adam-team-tab-description" hidden>
					<h2><?php esc_html_e( 'Descrição', 'adam-comunidade' ); ?></h2>
					<label class="adam-field">
						<span><?php esc_html_e( 'Descrição breve', 'adam-comunidade' ); ?></span>
						<textarea name="team[short_description]" rows="3" maxlength="320"><?php echo esc_textarea( (string) $adam_value( 'short_description' ) ); ?></textarea>
					</label>
					<div class="adam-field">
						<span><?php esc_html_e( 'Descrição completa', 'adam-comunidade' ); ?></span>
						<?php
						wp_editor(
							wp_kses_post( (string) $adam_value( 'full_description' ) ),
							'adam_team_full_description',
							array(
								'textarea_name' => 'team[full_description]',
								'textarea_rows' => 14,
								'media_buttons' => true,
							)
						);
						?>
					</div>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-location" role="tabpanel" aria-labelledby="adam-team-tab-location" hidden>
					<h2><?php esc_html_e( 'Localização', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<?php foreach ( array( 'district' => __( 'Distrito', 'adam-comunidade' ), 'municipality' => __( 'Concelho', 'adam-comunidade' ), 'address' => __( 'Morada', 'adam-comunidade' ) ) as $adam_key => $adam_label ) : ?>
							<label class="adam-field <?php echo 'address' === $adam_key ? 'adam-field--wide' : ''; ?>">
								<span><?php echo esc_html( $adam_label ); ?></span>
								<input type="text" name="team[<?php echo esc_attr( $adam_key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $adam_key ) ); ?>">
							</label>
						<?php endforeach; ?>
						<label class="adam-field"><span><?php esc_html_e( 'Latitude', 'adam-comunidade' ); ?></span><input type="number" step="0.0000001" min="-90" max="90" name="team[latitude]" value="<?php echo esc_attr( (string) $adam_value( 'latitude' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Longitude', 'adam-comunidade' ); ?></span><input type="number" step="0.0000001" min="-180" max="180" name="team[longitude]" value="<?php echo esc_attr( (string) $adam_value( 'longitude' ) ); ?>"></label>
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Google Maps URL', 'adam-comunidade' ); ?></span><input type="url" name="team[maps_url]" value="<?php echo esc_attr( (string) $adam_value( 'maps_url' ) ); ?>"></label>
					</div>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-contacts" role="tabpanel" aria-labelledby="adam-team-tab-contacts" hidden>
					<h2><?php esc_html_e( 'Informações de contacto', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<?php foreach ( array( 'website' => 'Website', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'discord' => 'Discord', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' ) as $adam_key => $adam_label ) : ?>
							<label class="adam-field"><span><?php echo esc_html( $adam_label ); ?></span><input type="url" name="team[<?php echo esc_attr( $adam_key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $adam_key ) ); ?>"></label>
						<?php endforeach; ?>
						<label class="adam-field"><span><?php esc_html_e( 'E-mail', 'adam-comunidade' ); ?></span><input type="email" name="team[email]" value="<?php echo esc_attr( (string) $adam_value( 'email' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Telefone', 'adam-comunidade' ); ?></span><input type="tel" name="team[phone]" value="<?php echo esc_attr( (string) $adam_value( 'phone' ) ); ?>"></label>
					</div>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-details" role="tabpanel" aria-labelledby="adam-team-tab-details" hidden>
					<h2><?php esc_html_e( 'Detalhes da equipa', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<label class="adam-field"><span><?php esc_html_e( 'Ano de fundação', 'adam-comunidade' ); ?></span><input type="number" min="1800" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" name="team[founded]" value="<?php echo esc_attr( (string) $adam_value( 'founded' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Número de membros', 'adam-comunidade' ); ?></span><input type="number" min="0" name="team[members]" value="<?php echo esc_attr( (string) $adam_value( 'members', 0 ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Estado do recrutamento', 'adam-comunidade' ); ?></span><select name="team[recruitment_status]">
							<?php foreach ( Options::recruitment_statuses() as $adam_key => $adam_label ) : ?><option value="<?php echo esc_attr( $adam_key ); ?>" <?php selected( $adam_value( 'recruitment_status', 'closed' ), $adam_key ); ?>><?php echo esc_html( $adam_label ); ?></option><?php endforeach; ?>
						</select></label>
						<label class="adam-field"><span><?php esc_html_e( 'Idade mínima', 'adam-comunidade' ); ?></span><input type="number" min="0" max="99" name="team[recruitment_min_age]" value="<?php echo esc_attr( (string) $adam_value( 'recruitment_min_age', 0 ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Experiência necessária', 'adam-comunidade' ); ?></span><textarea name="team[recruitment_experience]"><?php echo esc_textarea( (string) $adam_value( 'recruitment_experience' ) ); ?></textarea></label>
						<label class="adam-field"><span><?php esc_html_e( 'Equipamento obrigatório', 'adam-comunidade' ); ?></span><textarea name="team[recruitment_equipment]"><?php echo esc_textarea( (string) $adam_value( 'recruitment_equipment' ) ); ?></textarea></label>
						<label class="adam-field adam-field--checkbox"><input type="checkbox" name="team[recruitment_training]" value="1" <?php checked( (int) $adam_value( 'recruitment_training', 0 ), 1 ); ?>> <span><?php esc_html_e( 'Treino disponível', 'adam-comunidade' ); ?></span></label>
					</div>
					<fieldset class="adam-fieldset"><legend><?php esc_html_e( 'Estilos de jogo', 'adam-comunidade' ); ?></legend><div class="adam-choice-grid">
						<?php foreach ( Options::playing_styles() as $adam_key => $adam_label ) : ?><label><input type="checkbox" name="team[playing_styles][]" value="<?php echo esc_attr( $adam_key ); ?>" <?php checked( in_array( $adam_key, $adam_playing_styles, true ) ); ?>><?php echo esc_html( $adam_label ); ?></label><?php endforeach; ?>
					</div></fieldset>
					<fieldset class="adam-fieldset"><legend><?php esc_html_e( 'Equipamento', 'adam-comunidade' ); ?></legend><div class="adam-choice-grid">
						<?php foreach ( Options::equipment_tags() as $adam_key => $adam_label ) : ?><label><input type="checkbox" name="team[equipment_tags][]" value="<?php echo esc_attr( $adam_key ); ?>" <?php checked( in_array( $adam_key, $adam_equipment_tags, true ) ); ?>><?php echo esc_html( $adam_label ); ?></label><?php endforeach; ?>
					</div></fieldset>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-fields" role="tabpanel" aria-labelledby="adam-team-tab-fields" hidden>
					<h2><?php esc_html_e( 'Campos associados', 'adam-comunidade' ); ?></h2>
					<label class="adam-field">
						<span><?php esc_html_e( 'Campos Associados', 'adam-comunidade' ); ?></span>
						<select name="team[associated_fields][]" multiple size="8">
							<?php foreach ( $available_fields as $adam_available_field ) : ?>
								<option value="<?php echo esc_attr( (string) $adam_available_field->id ); ?>" <?php selected( in_array( (int) $adam_available_field->id, $selected_fields, true ) ); ?>>
									<?php echo esc_html( $adam_available_field->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<small><?php esc_html_e( 'Selecione um ou mais campos publicados.', 'adam-comunidade' ); ?></small>
					</label>
				</section>

				<section class="adam-card adam-editor-panel" id="adam-team-panel-seo" role="tabpanel" aria-labelledby="adam-team-tab-seo" hidden>
					<h2><?php esc_html_e( 'SEO', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Título SEO', 'adam-comunidade' ); ?></span><input type="text" maxlength="255" name="team[meta_title]" value="<?php echo esc_attr( (string) $adam_value( 'meta_title' ) ); ?>"><small><?php esc_html_e( 'Se vazio, utiliza o nome da equipa.', 'adam-comunidade' ); ?></small></label>
					<label class="adam-field"><span><?php esc_html_e( 'Descrição SEO', 'adam-comunidade' ); ?></span><textarea maxlength="320" rows="3" name="team[meta_description]"><?php echo esc_textarea( (string) $adam_value( 'meta_description' ) ); ?></textarea><small><?php esc_html_e( 'Se vazio, utiliza a descrição breve.', 'adam-comunidade' ); ?></small></label>
				</section>
			</div>

			<aside class="adam-card adam-publish-card">
				<h2><?php esc_html_e( 'Publicar', 'adam-comunidade' ); ?></h2>
				<label class="adam-field"><span><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></span><select name="team[status]">
					<?php foreach ( Options::statuses() as $adam_key => $adam_label ) : ?><option value="<?php echo esc_attr( $adam_key ); ?>" <?php selected( $adam_status, $adam_key ); ?>><?php echo esc_html( $adam_label ); ?></option><?php endforeach; ?>
				</select></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="team[featured]" value="1" <?php checked( (int) $adam_value( 'featured', 0 ), 1 ); ?>> <span><?php esc_html_e( 'Equipa em destaque', 'adam-comunidade' ); ?></span></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="team[verification]" value="verified_team" <?php checked( $adam_value( 'verification' ), 'verified_team' ); ?>> <span><?php esc_html_e( 'Equipa verificada', 'adam-comunidade' ); ?></span></label>
				<button class="button button-primary adam-button" type="submit"><?php esc_html_e( 'Guardar equipa', 'adam-comunidade' ); ?></button>
			</aside>
		</div>
	</form>
</div>
