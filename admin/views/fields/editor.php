<?php
/**
 * Field editor.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Uploads\Component as Upload_Component;

$adam_value = static fn( string $key, mixed $default = '' ): mixed => $field->{$key} ?? $default;
$adam_styles = Options::decode_list( $adam_value( 'playing_styles', array() ) );
$adam_status = (string) $adam_value( 'status', 'draft' );
$adam_slug   = (string) $adam_value( 'slug' );
$adam_preview = '';

if ( $field_id && $adam_slug ) {
	$adam_preview = add_query_arg(
		array(
			'adam_field_preview' => $field_id,
			'_wpnonce'           => wp_create_nonce( 'adam_field_preview_' . $field_id ),
		),
		trailingslashit( \ADAM\Comunidade\Managed_Pages::url( 'fields' ) ) . user_trailingslashit( $adam_slug )
	);
}
?>
<div class="wrap adam-comunidade-admin adam-field-editor">
	<header class="adam-page-header">
		<div>
			<a class="adam-back-link" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( 'fields' ) ); ?>">&larr; <?php esc_html_e( 'Voltar aos campos', 'adam-comunidade' ); ?></a>
			<h1><?php echo $field_id ? esc_html__( 'Editar campo', 'adam-comunidade' ) : esc_html__( 'Adicionar campo', 'adam-comunidade' ); ?></h1>
		</div>
		<?php if ( $adam_preview ) : ?>
			<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( $adam_preview ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Pré-visualizar', 'adam-comunidade' ); ?></a>
		<?php endif; ?>
	</header>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_field_save">
		<input type="hidden" name="field_id" value="<?php echo esc_attr( (string) $field_id ); ?>">
		<?php wp_nonce_field( 'adam_field_save' ); ?>
		<div class="adam-editor-layout">
			<div>
				<nav class="adam-editor-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Secções do campo', 'adam-comunidade' ); ?>">
					<?php
					$tabs = array(
						'basic'       => __( 'Informação principal', 'adam-comunidade' ),
						'branding'    => __( 'Imagem', 'adam-comunidade' ),
						'description' => __( 'Descrição', 'adam-comunidade' ),
						'location'    => __( 'Localização', 'adam-comunidade' ),
						'ownership'   => __( 'Responsabilidade', 'adam-comunidade' ),
						'styles'      => __( 'Estilos de jogo', 'adam-comunidade' ),
						'facilities'  => __( 'Comodidades', 'adam-comunidade' ),
						'rules'       => __( 'Regras', 'adam-comunidade' ),
						'capacity'    => __( 'Capacidade', 'adam-comunidade' ),
						'contacts'    => __( 'Contactos', 'adam-comunidade' ),
						'seo'         => __( 'SEO', 'adam-comunidade' ),
					);
					foreach ( $tabs as $tab_id => $tab_label ) :
						?>
						<button type="button" role="tab" data-adam-field-tab="<?php echo esc_attr( $tab_id ); ?>" aria-selected="<?php echo 'basic' === $tab_id ? 'true' : 'false'; ?>" aria-controls="adam-field-panel-<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $tab_label ); ?></button>
					<?php endforeach; ?>
				</nav>

				<section class="adam-card adam-field-panel" id="adam-field-panel-basic">
					<h2><?php esc_html_e( 'Informação principal', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Nome do campo', 'adam-comunidade' ); ?> *</span><input id="adam-field-name" type="text" name="field[name]" value="<?php echo esc_attr( (string) $adam_value( 'name' ) ); ?>" maxlength="191" required></label>
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Slug', 'adam-comunidade' ); ?> *</span><input id="adam-field-slug" type="text" name="field[slug]" value="<?php echo esc_attr( $adam_slug ); ?>" maxlength="191" required></label>
					</div>
				</section>

					<section class="adam-card adam-field-panel" id="adam-field-panel-branding" hidden>
						<h2><?php esc_html_e( 'Imagem', 'adam-comunidade' ); ?></h2>
						<h3><?php esc_html_e( 'Imagem de capa', 'adam-comunidade' ); ?></h3>
						<p><?php esc_html_e( 'Recomendado: 1920×700 px.', 'adam-comunidade' ); ?></p>
						<?php Upload_Component::render( array( 'mode' => 'library', 'kind' => 'image', 'name' => 'field[cover_id]', 'items' => $adam_value( 'cover_id', 0 ) ? array( Upload_Component::attachment( (int) $adam_value( 'cover_id', 0 ) ) ) : array() ) ); ?>
						<h3><?php esc_html_e( 'Galeria', 'adam-comunidade' ); ?></h3>
						<p><?php esc_html_e( 'Arraste para ordenar. As legendas aparecem na galeria pública.', 'adam-comunidade' ); ?></p>
						<?php
						$adam_gallery_items = array();
						foreach ( $gallery as $item ) {
							$adam_gallery_items[] = Upload_Component::attachment( (int) $item->attachment_id, (string) $item->caption );
						}
						Upload_Component::render( array( 'mode' => 'library', 'kind' => 'image', 'name' => 'field[gallery_ids][]', 'multiple' => true, 'max' => 20, 'items' => $adam_gallery_items, 'caption_pattern' => 'field[gallery_captions][__ID__]' ) );
						?>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-description" hidden>
					<h2><?php esc_html_e( 'Descrição', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Descrição breve', 'adam-comunidade' ); ?></span><textarea name="field[short_description]" maxlength="320" rows="3"><?php echo esc_textarea( (string) $adam_value( 'short_description' ) ); ?></textarea></label>
					<div class="adam-field"><span><?php esc_html_e( 'Descrição completa', 'adam-comunidade' ); ?></span>
						<?php wp_editor( wp_kses_post( (string) $adam_value( 'full_description' ) ), 'adam_field_description', array( 'textarea_name' => 'field[full_description]', 'textarea_rows' => 14, 'media_buttons' => true ) ); ?>
					</div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-location" hidden>
					<h2><?php esc_html_e( 'Localização', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<?php foreach ( array( 'district' => __( 'Distrito', 'adam-comunidade' ), 'municipality' => __( 'Concelho', 'adam-comunidade' ), 'address' => __( 'Morada', 'adam-comunidade' ) ) as $key => $label ) : ?>
							<label class="adam-field <?php echo 'address' === $key ? 'adam-field--wide' : ''; ?>"><span><?php echo esc_html( $label ); ?></span><input type="text" name="field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $key ) ); ?>"></label>
						<?php endforeach; ?>
						<label class="adam-field"><span><?php esc_html_e( 'Latitude', 'adam-comunidade' ); ?></span><input type="number" step="0.0000001" min="-90" max="90" name="field[latitude]" value="<?php echo esc_attr( (string) $adam_value( 'latitude' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Longitude', 'adam-comunidade' ); ?></span><input type="number" step="0.0000001" min="-180" max="180" name="field[longitude]" value="<?php echo esc_attr( (string) $adam_value( 'longitude' ) ); ?>"></label>
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Google Maps URL', 'adam-comunidade' ); ?></span><input type="url" name="field[maps_url]" value="<?php echo esc_attr( (string) $adam_value( 'maps_url' ) ); ?>"></label>
					</div>
					<p class="adam-notice"><?php esc_html_e( 'As coordenadas alimentam o mapa OpenStreetMap e os futuros mapas do diretório.', 'adam-comunidade' ); ?></p>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-ownership" hidden>
					<h2><?php esc_html_e( 'Responsabilidade', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Equipa associada', 'adam-comunidade' ); ?></span><select name="field[associated_team]">
						<option value="0"><?php esc_html_e( 'Sem equipa associada', 'adam-comunidade' ); ?></option>
						<?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( (string) $team->id ); ?>" <?php selected( $selected_team, $team->id ); ?>><?php echo esc_html( $team->name ); ?></option><?php endforeach; ?>
					</select></label>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-styles" hidden>
					<h2><?php esc_html_e( 'Estilos de jogo', 'adam-comunidade' ); ?></h2>
					<div class="adam-choice-grid"><?php foreach ( Options::playing_styles() as $key => $label ) : ?><label><input type="checkbox" name="field[playing_styles][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $adam_styles, true ) ); ?>><?php echo esc_html( $label ); ?></label><?php endforeach; ?></div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-facilities" hidden>
					<div class="adam-card__header"><div><h2><?php esc_html_e( 'Comodidades', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'As comodidades reutilizáveis podem ser alteradas ou acrescentadas neste gestor.', 'adam-comunidade' ); ?></p></div><a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::page_url( 'field-amenities' ) ); ?>"><?php esc_html_e( 'Gerir', 'adam-comunidade' ); ?></a></div>
					<div class="adam-facility-switches"><?php foreach ( $amenity_options as $amenity ) : ?><label><span><?php echo esc_html( $amenity->label ); ?></span><span class="adam-switch"><input type="checkbox" name="field[amenities][]" value="<?php echo esc_attr( (string) $amenity->id ); ?>" <?php checked( in_array( (int) $amenity->id, $selected_amenities, true ) ); ?>><span></span></span></label><?php endforeach; ?></div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-rules" hidden>
					<h2><?php esc_html_e( 'Regras', 'adam-comunidade' ); ?></h2>
					<p><?php esc_html_e( 'Registe limites de FPS, regras de pirotecnia, requisitos de BB Bio, tabaco, álcool, animais e outras regras locais.', 'adam-comunidade' ); ?></p>
					<?php wp_editor( wp_kses_post( (string) $adam_value( 'rules' ) ), 'adam_field_rules', array( 'textarea_name' => 'field[rules]', 'textarea_rows' => 12, 'media_buttons' => false ) ); ?>
					<label class="adam-field"><span><?php esc_html_e( 'Horários', 'adam-comunidade' ); ?></span><textarea name="field[opening_hours]" rows="6" placeholder="<?php esc_attr_e( 'Ex.: Sábado e domingo, 09:00–18:00', 'adam-comunidade' ); ?>"><?php echo esc_textarea( (string) $adam_value( 'opening_hours' ) ); ?></textarea></label>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-capacity" hidden>
					<h2><?php esc_html_e( 'Capacidade', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid"><?php foreach ( array( 'max_players' => __( 'Máximo de jogadores', 'adam-comunidade' ), 'min_players' => __( 'Mínimo de jogadores', 'adam-comunidade' ), 'recommended_players' => __( 'Número recomendado de jogadores', 'adam-comunidade' ) ) as $key => $label ) : ?><label class="adam-field"><span><?php echo esc_html( $label ); ?></span><input type="number" min="0" name="field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $key, 0 ) ); ?>"></label><?php endforeach; ?></div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-contacts" hidden>
					<h2><?php esc_html_e( 'Contactos', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<?php foreach ( array( 'website' => 'Website', 'facebook' => 'Facebook', 'instagram' => 'Instagram' ) as $key => $label ) : ?><label class="adam-field"><span><?php echo esc_html( $label ); ?></span><input type="url" name="field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $key ) ); ?>"></label><?php endforeach; ?>
						<label class="adam-field"><span><?php esc_html_e( 'E-mail', 'adam-comunidade' ); ?></span><input type="email" name="field[email]" value="<?php echo esc_attr( (string) $adam_value( 'email' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Telefone', 'adam-comunidade' ); ?></span><input type="tel" name="field[phone]" value="<?php echo esc_attr( (string) $adam_value( 'phone' ) ); ?>"></label>
					</div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-seo" hidden>
					<h2><?php esc_html_e( 'SEO', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Título SEO', 'adam-comunidade' ); ?></span><input type="text" maxlength="255" name="field[meta_title]" value="<?php echo esc_attr( (string) $adam_value( 'meta_title' ) ); ?>"><small><?php esc_html_e( 'Se vazio, utiliza o nome do campo.', 'adam-comunidade' ); ?></small></label>
					<label class="adam-field"><span><?php esc_html_e( 'Descrição SEO', 'adam-comunidade' ); ?></span><textarea maxlength="320" rows="3" name="field[meta_description]"><?php echo esc_textarea( (string) $adam_value( 'meta_description' ) ); ?></textarea><small><?php esc_html_e( 'Se vazio, utiliza a descrição breve.', 'adam-comunidade' ); ?></small></label>
				</section>
			</div>

			<aside class="adam-card adam-publish-card">
				<h2><?php esc_html_e( 'Publicar', 'adam-comunidade' ); ?></h2>
				<label class="adam-field"><span><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></span><select name="field[status]"><?php foreach ( Options::statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $adam_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label class="adam-field"><span><?php esc_html_e( 'Disponibilidade', 'adam-comunidade' ); ?></span><select name="field[availability]"><?php foreach ( Options::availability_statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $adam_value( 'availability', 'open' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="field[featured]" value="1" <?php checked( (int) $adam_value( 'featured', 0 ), 1 ); ?>> <span><?php esc_html_e( 'Campo em destaque', 'adam-comunidade' ); ?></span></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="field[verification]" value="verified_field" <?php checked( $adam_value( 'verification' ), 'verified_field' ); ?>> <span><?php esc_html_e( 'Autorização legal verificada', 'adam-comunidade' ); ?></span></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="field[is_associated]" value="1" <?php checked( (int) $adam_value( 'is_associated', 0 ), 1 ); ?>> <span><?php esc_html_e( 'Campo associado', 'adam-comunidade' ); ?></span></label>
					<h3><?php esc_html_e( 'Documento de autorização', 'adam-comunidade' ); ?></h3>
					<?php Upload_Component::render( array( 'mode' => 'library', 'kind' => 'document', 'name' => 'field[authorization_document_id]', 'accept' => '.pdf,.jpg,.jpeg,.png', 'items' => $adam_value( 'authorization_document_id', 0 ) ? array( Upload_Component::attachment( (int) $adam_value( 'authorization_document_id', 0 ) ) ) : array() ) ); ?>
				<button class="button button-primary adam-button" type="submit"><?php esc_html_e( 'Guardar campo', 'adam-comunidade' ); ?></button>
			</aside>
		</div>
	</form>
</div>
