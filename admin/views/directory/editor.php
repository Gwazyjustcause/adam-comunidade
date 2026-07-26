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
	<p><a class="adam-back-link" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( (string) $definition['module_id'] ) ); ?>">&larr; <?php echo esc_html( sprintf( __( 'Back to %s', 'adam-comunidade' ), $definition['plural'] ) ); ?></a></p>
	<h1><?php echo esc_html( $entry ? sprintf( __( 'Edit %s', 'adam-comunidade' ), $definition['singular'] ) : sprintf( __( 'Add %s', 'adam-comunidade' ), $definition['singular'] ) ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="adam_directory_save">
		<input type="hidden" name="entity_type" value="<?php echo esc_attr( $type ); ?>">
		<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry->id ?? 0 ); ?>">
		<?php wp_nonce_field( 'adam_directory_save_' . $type ); ?>

		<nav class="adam-directory-tabs" aria-label="<?php esc_attr_e( 'Editor sections', 'adam-comunidade' ); ?>">
			<?php foreach ( array( 'basic' => __( 'Basic', 'adam-comunidade' ), 'media' => __( 'Branding & Media', 'adam-comunidade' ), 'contact' => __( 'Contact', 'adam-comunidade' ), 'details' => __( 'Details', 'adam-comunidade' ), 'relationships' => __( 'Relationships', 'adam-comunidade' ), 'seo' => __( 'SEO', 'adam-comunidade' ) ) as $tab => $label ) : ?>
				<button type="button" class="button <?php echo 'basic' === $tab ? 'button-primary' : ''; ?>" data-adam-tab="<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</nav>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="basic">
			<h2><?php esc_html_e( 'Basic Information', 'adam-comunidade' ); ?></h2>
			<div class="adam-directory-grid">
				<label><?php esc_html_e( 'Name', 'adam-comunidade' ); ?><input required type="text" name="entry[name]" value="<?php echo esc_attr( $value( 'name' ) ); ?>"></label>
				<label><?php esc_html_e( 'Slug', 'adam-comunidade' ); ?><input type="text" name="entry[slug]" value="<?php echo esc_attr( $value( 'slug' ) ); ?>" data-adam-slug></label>
				<label><?php esc_html_e( 'Status', 'adam-comunidade' ); ?><select name="entry[status]"><?php foreach ( array( 'draft', 'published', 'hidden' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $value( 'status', 'draft' ), $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option><?php endforeach; ?></select></label>
				<?php if ( $definition['categories'] ) : ?><label><?php echo esc_html( 'institution' === $type ? __( 'Type', 'adam-comunidade' ) : __( 'Category', 'adam-comunidade' ) ); ?><select name="entry[category]"><option value=""><?php esc_html_e( 'Select…', 'adam-comunidade' ); ?></option><?php foreach ( $definition['categories'] as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value( 'category' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><?php endif; ?>
			</div>
			<label><?php esc_html_e( 'Short description', 'adam-comunidade' ); ?><textarea name="entry[short_description]" rows="3"><?php echo esc_textarea( $value( 'short_description' ) ); ?></textarea></label>
			<label><?php esc_html_e( 'Full description', 'adam-comunidade' ); ?></label>
			<?php wp_editor( $value( 'full_description' ), 'adam_directory_description', array( 'textarea_name' => 'entry[full_description]', 'textarea_rows' => 12 ) ); ?>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="media" hidden>
			<h2><?php esc_html_e( 'Branding & Media', 'adam-comunidade' ); ?></h2>
			<div class="adam-directory-media-grid">
				<?php foreach ( array( 'logo_id' => __( 'Logo', 'adam-comunidade' ), 'cover_id' => __( 'Cover', 'adam-comunidade' ) ) as $field => $label ) : ?>
					<div class="adam-directory-media" data-adam-media>
						<h3><?php echo esc_html( $label ); ?></h3><div data-adam-preview><?php echo $value( $field ) ? wp_get_attachment_image( $value( $field ), 'medium' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<input type="hidden" name="entry[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value( $field ) ); ?>">
						<button type="button" class="button" data-adam-select-media><?php esc_html_e( 'Choose', 'adam-comunidade' ); ?></button>
						<button type="button" class="button-link-delete" data-adam-remove-media><?php esc_html_e( 'Remove', 'adam-comunidade' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<h3><?php esc_html_e( 'Gallery', 'adam-comunidade' ); ?></h3>
			<button type="button" class="button" data-adam-gallery-add><?php esc_html_e( 'Add gallery images', 'adam-comunidade' ); ?></button>
			<ul class="adam-directory-gallery" data-adam-gallery>
				<?php foreach ( $gallery as $image ) : ?><li data-id="<?php echo esc_attr( $image->attachment_id ); ?>"><?php echo wp_get_attachment_image( $image->attachment_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="hidden" name="entry[gallery_ids][]" value="<?php echo esc_attr( $image->attachment_id ); ?>"><input type="text" name="entry[gallery_captions][<?php echo esc_attr( $image->attachment_id ); ?>]" value="<?php echo esc_attr( $image->caption ); ?>" placeholder="<?php esc_attr_e( 'Caption', 'adam-comunidade' ); ?>"><button type="button" class="button-link-delete" data-adam-gallery-remove>×</button></li><?php endforeach; ?>
			</ul>
			<div class="adam-directory-media-grid">
				<?php foreach ( array( 'promo_pdf_id' => __( 'Promotional PDF', 'adam-comunidade' ), 'catalogue_id' => __( 'Downloadable catalogue', 'adam-comunidade' ) ) as $field => $label ) : ?>
					<div class="adam-directory-media" data-adam-media data-media-type="application/pdf"><h3><?php echo esc_html( $label ); ?></h3><div data-adam-preview><?php echo $value( $field ) ? esc_html( get_the_title( $value( $field ) ) ) : ''; ?></div><input type="hidden" name="entry[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value( $field ) ); ?>"><button type="button" class="button" data-adam-select-media><?php esc_html_e( 'Choose file', 'adam-comunidade' ); ?></button><button type="button" class="button-link-delete" data-adam-remove-media><?php esc_html_e( 'Remove', 'adam-comunidade' ); ?></button></div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="contact" hidden>
			<h2><?php esc_html_e( 'Contact & Location', 'adam-comunidade' ); ?></h2>
			<div class="adam-directory-grid">
				<?php foreach ( array( 'website' => 'url', 'facebook' => 'url', 'instagram' => 'url', 'email' => 'email', 'phone' => 'text', 'address' => 'text', 'district' => 'text', 'latitude' => 'number', 'longitude' => 'number' ) as $field => $input_type ) : ?>
					<label><?php echo esc_html( ucwords( str_replace( '_', ' ', $field ) ) ); ?><input type="<?php echo esc_attr( $input_type ); ?>" <?php echo 'number' === $input_type ? 'step="any"' : ''; ?> name="entry[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value( $field ) ); ?>"></label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="details" hidden>
			<h2><?php esc_html_e( 'Directory Details', 'adam-comunidade' ); ?></h2>
			<?php if ( 'partner' === $type ) : ?><label><?php esc_html_e( 'Benefits', 'adam-comunidade' ); ?><textarea name="entry[benefits]" rows="7" placeholder="<?php esc_attr_e( '10% discount, free shipping, members only…', 'adam-comunidade' ); ?>"><?php echo esc_textarea( $value( 'benefits' ) ); ?></textarea></label><?php endif; ?>
			<?php if ( 'partner' === $type ) : ?><label><?php esc_html_e( 'Member-only benefits', 'adam-comunidade' ); ?><textarea name="entry[member_benefits]" rows="6"><?php echo esc_textarea( $value( 'member_benefits' ) ); ?></textarea><span class="description"><?php esc_html_e( 'Only verified ADAM Members can see this content.', 'adam-comunidade' ); ?></span></label><?php endif; ?>
			<?php if ( 'brand' === $type ) : ?><div class="adam-directory-grid"><label><?php esc_html_e( 'Country', 'adam-comunidade' ); ?><input type="text" name="entry[country]" value="<?php echo esc_attr( $value( 'country' ) ); ?>"></label><label><input type="checkbox" name="entry[official_distributor]" value="1" <?php checked( $value( 'official_distributor' ) ); ?>> <?php esc_html_e( 'Official distributor', 'adam-comunidade' ); ?></label></div><label><?php esc_html_e( 'Popular products', 'adam-comunidade' ); ?><textarea name="entry[popular_products]" rows="6"><?php echo esc_textarea( $value( 'popular_products' ) ); ?></textarea></label><?php endif; ?>
			<?php if ( 'institution' === $type ) : ?><label><?php esc_html_e( 'Internal / public notes', 'adam-comunidade' ); ?><textarea name="entry[notes]" rows="7"><?php echo esc_textarea( $value( 'notes' ) ); ?></textarea></label><?php endif; ?>
			<div class="adam-directory-grid">
				<label><input type="checkbox" name="entry[featured]" value="1" <?php checked( $value( 'featured' ) ); ?>> <?php esc_html_e( 'Featured', 'adam-comunidade' ); ?></label>
				<label><input type="checkbox" name="entry[homepage_featured]" value="1" <?php checked( $value( 'homepage_featured' ) ); ?>> <?php esc_html_e( 'Homepage featured', 'adam-comunidade' ); ?></label>
				<label><?php esc_html_e( 'Priority order', 'adam-comunidade' ); ?><input type="number" name="entry[priority]" value="<?php echo esc_attr( $value( 'priority', 0 ) ); ?>"></label>
				<label><?php esc_html_e( 'Trust badge', 'adam-comunidade' ); ?><select name="entry[verification]"><option value=""><?php esc_html_e( 'None', 'adam-comunidade' ); ?></option><option value="official_partner" <?php selected( $value( 'verification' ), 'official_partner' ); ?>><?php esc_html_e( 'Official Partner', 'adam-comunidade' ); ?></option><option value="adam_partner" <?php selected( $value( 'verification' ), 'adam_partner' ); ?>><?php esc_html_e( 'ADAM Partner', 'adam-comunidade' ); ?></option><option value="institutional_partner" <?php selected( $value( 'verification' ), 'institutional_partner' ); ?>><?php esc_html_e( 'Institutional Partner', 'adam-comunidade' ); ?></option></select></label>
			</div>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="relationships" hidden>
			<h2><?php esc_html_e( 'Relationships', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'Relationships appear automatically on both connected public pages and remain ready for future Events and Associations modules.', 'adam-comunidade' ); ?></p>
			<?php foreach ( array( 'brand' => __( 'Associated brands', 'adam-comunidade' ), 'partner' => __( 'Partner shops / distributors', 'adam-comunidade' ), 'team' => __( 'Associated / sponsored teams', 'adam-comunidade' ), 'field' => __( 'Associated / supported fields', 'adam-comunidade' ) ) as $target_type => $label ) : ?>
				<?php if ( 'brand' === $type && 'brand' === $target_type ) { continue; } ?>
				<?php if ( 'brand' !== $type && 'partner' === $target_type ) { continue; } ?>
				<label><?php echo esc_html( $label ); ?><select multiple size="6" name="entry[relations][<?php echo esc_attr( $target_type ); ?>][]"><?php foreach ( $choices[ $target_type ] as $choice ) : ?><option value="<?php echo esc_attr( $choice->id ); ?>" <?php selected( in_array( (int) $choice->id, $selected[ $target_type ], true ) ); ?>><?php echo esc_html( $choice->name ); ?></option><?php endforeach; ?></select></label>
			<?php endforeach; ?>
		</section>

		<section class="adam-comunidade__card adam-directory-panel" data-adam-panel="seo" hidden>
			<h2><?php esc_html_e( 'SEO', 'adam-comunidade' ); ?></h2>
			<label><?php esc_html_e( 'Meta title', 'adam-comunidade' ); ?><input type="text" name="entry[meta_title]" value="<?php echo esc_attr( $value( 'meta_title' ) ); ?>" placeholder="<?php esc_attr_e( 'Falls back to the name', 'adam-comunidade' ); ?>"></label>
			<label><?php esc_html_e( 'Meta description', 'adam-comunidade' ); ?><textarea name="entry[meta_description]" rows="4" maxlength="320" placeholder="<?php esc_attr_e( 'Falls back to the short description', 'adam-comunidade' ); ?>"><?php echo esc_textarea( $value( 'meta_description' ) ); ?></textarea></label>
		</section>

		<p class="submit"><button class="button button-primary button-large"><?php esc_html_e( 'Save', 'adam-comunidade' ); ?></button><?php if ( $entry ) : ?>
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
			<a class="button button-large" target="_blank" rel="noopener" href="<?php echo esc_url( $preview_url ); ?>"><?php echo esc_html( 'published' === $entry->status ? __( 'View public page', 'adam-comunidade' ) : __( 'Preview page', 'adam-comunidade' ) ); ?></a>
		<?php endif; ?></p>
	</form>
</div>
