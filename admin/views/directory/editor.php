<?php
/**
 * Shared tabbed directory editor.
 *
 * @var string              $type
 * @var array<string,mixed> $definition
 * @var object|null         $entry
 * @var object[]            $gallery
 * @var array<string,int[]> $selected
 * @var array<string,array> $choices
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
$value = static fn( string $key, mixed $default = '' ): mixed => $entry->{$key} ?? $default;
?>
<div class="wrap adam-comunidade-admin adam-directory-editor">
	<p><a class="adam-back-link" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( (string) $definition['module_id'] ) ); ?>">&larr; <?php echo esc_html( sprintf( __( 'Voltar a %s', 'adam-comunidade' ), $definition['plural'] ) ); ?></a></p>
	<h1><?php echo esc_html( $entry ? sprintf( __( 'Editar %s', 'adam-comunidade' ), $definition['singular'] ) : sprintf( __( 'Adicionar %s', 'adam-comunidade' ), $definition['singular'] ) ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="adam_directory_save">
		<input type="hidden" name="entity_type" value="<?php echo esc_attr( $type ); ?>">
		<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->id ?? 0 ); ?>">
		<?php wp_nonce_field( 'adam_directory_save_' . $type ); ?>

		<nav class="adam-directory-tabs" aria-label="<?php esc_attr_e( 'Secções do editor', 'adam-comunidade' ); ?>">
			<?php foreach ( array( 'basic' => __( 'Informação', 'adam-comunidade' ), 'media' => __( 'Imagem e multimédia', 'adam-comunidade' ), 'contact' => __( 'Contacto', 'adam-comunidade' ), 'details' => __( 'Detalhes', 'adam-comunidade' ), 'relationships' => __( 'Relações', 'adam-comunidade' ), 'seo' => __( 'SEO', 'adam-comunidade' ) ) as $tab => $label ) : ?>
				<button type="button" class="button <?php echo 'basic' === $tab ? 'button-primary' : ''; ?>" data-adam-tab="<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</nav>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="basic">
			<h2><?php esc_html_e( 'Informação principal', 'adam-comunidade' ); ?></h2>
			<div class="adam-directory-grid">
				<label><?php esc_html_e( 'Nome', 'adam-comunidade' ); ?><input required type="text" name="entry[name]" value="<?php echo esc_attr( $value( 'name' ) ); ?>"></label>
				<label><?php esc_html_e( 'Slug', 'adam-comunidade' ); ?><input type="text" name="entry[slug]" value="<?php echo esc_attr( $value( 'slug' ) ); ?>" data-adam-slug></label>
				<label><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?><select name="entry[status]"><?php foreach ( array( 'draft' => __( 'Rascunho', 'adam-comunidade' ), 'published' => __( 'Publicado', 'adam-comunidade' ), 'hidden' => __( 'Oculto', 'adam-comunidade' ) ) as $status => $status_label ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $value( 'status', 'draft' ), $status ); ?>><?php echo esc_html( $status_label ); ?></option><?php endforeach; ?></select></label>
				<?php if ( $definition['categories'] ) : ?><label><?php echo esc_html( 'institution' === $type ? __( 'Tipo', 'adam-comunidade' ) : __( 'Categoria', 'adam-comunidade' ) ); ?><select name="entry[category]"><option value=""><?php esc_html_e( 'Selecionar…', 'adam-comunidade' ); ?></option><?php foreach ( $definition['categories'] as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value( 'category' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><?php endif; ?>
			</div>
			<label><?php esc_html_e( 'Descrição breve', 'adam-comunidade' ); ?><textarea name="entry[short_description]" rows="3"><?php echo esc_textarea( $value( 'short_description' ) ); ?></textarea></label>
			<label><?php esc_html_e( 'Descrição completa', 'adam-comunidade' ); ?></label>
			<?php wp_editor( $value( 'full_description' ), 'adam_directory_description', array( 'textarea_name' => 'entry[full_description]', 'textarea_rows' => 12 ) ); ?>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="media" hidden>
			<h2><?php esc_html_e( 'Imagem e multimédia', 'adam-comunidade' ); ?></h2>
			<div class="adam-directory-media-grid">
				<?php foreach ( array( 'logo_id' => __( 'Logótipo', 'adam-comunidade' ), 'cover_id' => __( 'Capa', 'adam-comunidade' ) ) as $field => $label ) : ?>
					<div class="adam-directory-media" data-adam-media>
						<h3><?php echo esc_html( $label ); ?></h3><div data-adam-preview><?php echo $value( $field ) ? wp_get_attachment_image( $value( $field ), 'medium' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<input type="hidden" name="entry[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value( $field ) ); ?>">
						<button type="button" class="button" data-adam-select-media><?php esc_html_e( 'Escolher', 'adam-comunidade' ); ?></button>
						<button type="button" class="button-link-delete" data-adam-remove-media><?php esc_html_e( 'Remover', 'adam-comunidade' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<h3><?php esc_html_e( 'Galeria', 'adam-comunidade' ); ?></h3>
			<button type="button" class="button" data-adam-gallery-add><?php esc_html_e( 'Adicionar imagens à galeria', 'adam-comunidade' ); ?></button>
			<ul class="adam-directory-gallery" data-adam-gallery>
				<?php foreach ( $gallery as $image ) : ?><li data-id="<?php echo esc_attr( $image->attachment_id ); ?>"><?php echo wp_get_attachment_image( $image->attachment_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="entry[gallery_ids][]" value="<?php echo esc_attr( $image->attachment_id ); ?>"><input type="text" name="entry[gallery_captions][<?php echo esc_attr( $image->attachment_id ); ?>]" value="<?php echo esc_attr( $image->caption ); ?>" placeholder="<?php esc_attr_e( 'Legenda', 'adam-comunidade' ); ?>"><button type="button" class="button-link-delete" data-adam-gallery-remove>×</button></li><?php endforeach; ?>
			</ul>
			<div class="adam-directory-media-grid">
				<?php foreach ( array( 'promo_pdf_id' => __( 'PDF promocional', 'adam-comunidade' ), 'catalogue_id' => __( 'Catálogo para descarregar', 'adam-comunidade' ) ) as $field => $label ) : ?>
					<div class="adam-directory-media" data-adam-media data-media-type="application/pdf"><h3><?php echo esc_html( $label ); ?></h3><div data-adam-preview><?php echo $value( $field ) ? esc_html( get_the_title( $value( $field ) ) ) : ''; ?></div><input type="hidden" name="entry[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value( $field ) ); ?>"><button type="button" class="button" data-adam-select-media><?php esc_html_e( 'Escolher ficheiro', 'adam-comunidade' ); ?></button><button type="button" class="button-link-delete" data-adam-remove-media><?php esc_html_e( 'Remover', 'adam-comunidade' ); ?></button></div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="contact" hidden>
			<h2><?php esc_html_e( 'Contacto e localização', 'adam-comunidade' ); ?></h2>
			<div class="adam-directory-grid">
				<?php foreach ( array( 'website' => array( 'url', __( 'Website', 'adam-comunidade' ) ), 'facebook' => array( 'url', 'Facebook' ), 'instagram' => array( 'url', 'Instagram' ), 'email' => array( 'email', __( 'E-mail', 'adam-comunidade' ) ), 'phone' => array( 'text', __( 'Telefone', 'adam-comunidade' ) ), 'address' => array( 'text', __( 'Morada', 'adam-comunidade' ) ), 'district' => array( 'text', __( 'Distrito', 'adam-comunidade' ) ), 'latitude' => array( 'number', __( 'Latitude', 'adam-comunidade' ) ), 'longitude' => array( 'number', __( 'Longitude', 'adam-comunidade' ) ) ) as $field => $field_config ) : $input_type = $field_config[0]; ?>
					<label><?php echo esc_html( $field_config[1] ); ?><input type="<?php echo esc_attr( $input_type ); ?>" <?php echo 'number' === $input_type ? 'step="any"' : ''; ?> name="entry[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value( $field ) ); ?>"></label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="details" hidden>
			<h2><?php esc_html_e( 'Detalhes do diretório', 'adam-comunidade' ); ?></h2>
			<?php if ( 'partner' === $type ) : ?><label><?php esc_html_e( 'Benefícios', 'adam-comunidade' ); ?><textarea name="entry[benefits]" rows="7" placeholder="<?php esc_attr_e( '10% de desconto, portes grátis, exclusivo para membros…', 'adam-comunidade' ); ?>"><?php echo esc_textarea( $value( 'benefits' ) ); ?></textarea></label><?php endif; ?>
			<?php if ( 'partner' === $type ) : ?><label><?php esc_html_e( 'Benefícios exclusivos para membros', 'adam-comunidade' ); ?><textarea name="entry[member_benefits]" rows="6"><?php echo esc_textarea( $value( 'member_benefits' ) ); ?></textarea><span class="description"><?php esc_html_e( 'Apenas membros ADAM verificados podem ver este conteúdo.', 'adam-comunidade' ); ?></span></label><?php endif; ?>
			<?php if ( 'brand' === $type ) : ?><div class="adam-directory-grid"><label><?php esc_html_e( 'País', 'adam-comunidade' ); ?><input type="text" name="entry[country]" value="<?php echo esc_attr( $value( 'country' ) ); ?>"></label><label><input type="checkbox" name="entry[official_distributor]" value="1" <?php checked( $value( 'official_distributor' ) ); ?>> <?php esc_html_e( 'Distribuidor oficial', 'adam-comunidade' ); ?></label></div><label><?php esc_html_e( 'Produtos populares', 'adam-comunidade' ); ?><textarea name="entry[popular_products]" rows="6"><?php echo esc_textarea( $value( 'popular_products' ) ); ?></textarea></label><?php endif; ?>
			<?php if ( 'institution' === $type ) : ?><label><?php esc_html_e( 'Notas internas / públicas', 'adam-comunidade' ); ?><textarea name="entry[notes]" rows="7"><?php echo esc_textarea( $value( 'notes' ) ); ?></textarea></label><?php endif; ?>
			<div class="adam-directory-grid">
				<label><input type="checkbox" name="entry[featured]" value="1" <?php checked( $value( 'featured' ) ); ?>> <?php esc_html_e( 'Em destaque', 'adam-comunidade' ); ?></label>
				<label><input type="checkbox" name="entry[homepage_featured]" value="1" <?php checked( $value( 'homepage_featured' ) ); ?>> <?php esc_html_e( 'Em destaque na página inicial', 'adam-comunidade' ); ?></label>
				<label><?php esc_html_e( 'Ordem de prioridade', 'adam-comunidade' ); ?><input type="number" name="entry[priority]" value="<?php echo esc_attr( $value( 'priority', 0 ) ); ?>"></label>
				<label><?php esc_html_e( 'Distintivo de confiança', 'adam-comunidade' ); ?><select name="entry[verification]"><option value=""><?php esc_html_e( 'Nenhum', 'adam-comunidade' ); ?></option><option value="official_partner" <?php selected( $value( 'verification' ), 'official_partner' ); ?>><?php esc_html_e( 'Parceiro oficial', 'adam-comunidade' ); ?></option><option value="adam_partner" <?php selected( $value( 'verification' ), 'adam_partner' ); ?>><?php esc_html_e( 'Parceiro ADAM', 'adam-comunidade' ); ?></option><option value="institutional_partner" <?php selected( $value( 'verification' ), 'institutional_partner' ); ?>><?php esc_html_e( 'Parceiro institucional', 'adam-comunidade' ); ?></option></select></label>
			</div>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="relationships" hidden>
			<h2><?php esc_html_e( 'Relações', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'As relações aparecem automaticamente nas duas páginas públicas ligadas e ficam preparadas para futuros módulos de eventos e associações.', 'adam-comunidade' ); ?></p>
			<?php foreach ( array( 'brand' => __( 'Marcas associadas', 'adam-comunidade' ), 'partner' => __( 'Lojas parceiras / distribuidores', 'adam-comunidade' ), 'team' => __( 'Equipas associadas / patrocinadas', 'adam-comunidade' ), 'field' => __( 'Campos associados / apoiados', 'adam-comunidade' ) ) as $target_type => $label ) : ?>
				<?php if ( 'brand' === $type && 'brand' === $target_type ) { continue; } ?>
				<?php if ( 'brand' !== $type && 'partner' === $target_type ) { continue; } ?>
				<label><?php echo esc_html( $label ); ?><select multiple size="6" name="entry[relations][<?php echo esc_attr( $target_type ); ?>][]"><?php foreach ( $choices[ $target_type ] as $choice ) : ?><option value="<?php echo esc_attr( $choice->id ); ?>" <?php selected( in_array( (int) $choice->id, $selected[ $target_type ], true ) ); ?>><?php echo esc_html( $choice->name ); ?></option><?php endforeach; ?></select></label>
			<?php endforeach; ?>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="seo" hidden>
			<h2><?php esc_html_e( 'SEO', 'adam-comunidade' ); ?></h2>
			<label><?php esc_html_e( 'Título SEO', 'adam-comunidade' ); ?><input type="text" name="entry[meta_title]" value="<?php echo esc_attr( $value( 'meta_title' ) ); ?>" placeholder="<?php esc_attr_e( 'Se vazio, utiliza o nome', 'adam-comunidade' ); ?>"></label>
			<label><?php esc_html_e( 'Descrição SEO', 'adam-comunidade' ); ?><textarea name="entry[meta_description]" rows="4" maxlength="320" placeholder="<?php esc_attr_e( 'Se vazio, utiliza a descrição breve', 'adam-comunidade' ); ?>"><?php echo esc_textarea( $value( 'meta_description' ) ); ?></textarea></label>
		</section>

		<p class="submit"><button class="button button-primary button-large"><?php esc_html_e( 'Guardar', 'adam-comunidade' ); ?></button><?php if ( $entry ) : ?>
			<?php
			$preview_url = Router::entry_url( $entry );
			if ( 'published' !== $entry->status ) {
				$preview_url = add_query_arg(
					array(
						'adam_directory_preview' => $entry->id,
						'_wpnonce'               => wp_create_nonce( 'adam_directory_preview_' . $entry->id ),
					),
					$preview_url
				);
			}
			?>
			<a class="button button-large" target="_blank" rel="noopener" href="<?php echo esc_url( $preview_url ); ?>"><?php echo esc_html( 'published' === $entry->status ? __( 'Ver página pública', 'adam-comunidade' ) : __( 'Pré-visualizar página', 'adam-comunidade' ) ); ?></a>
		<?php endif; ?></p>
	</form>
</div>
