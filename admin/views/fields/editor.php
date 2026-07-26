<?php
/**
 * Field editor.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Options;

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
		home_url( '/campos/' . $adam_slug . '/' )
	);
}
?>
<div class="wrap adam-comunidade-admin adam-field-editor">
	<header class="adam-page-header">
		<div>
			<a class="adam-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=adam-comunidade-fields' ) ); ?>">&larr; <?php esc_html_e( 'Back to fields', 'adam-comunidade' ); ?></a>
			<h1><?php echo $field_id ? esc_html__( 'Edit Field', 'adam-comunidade' ) : esc_html__( 'Add Field', 'adam-comunidade' ); ?></h1>
		</div>
		<?php if ( $adam_preview ) : ?>
			<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( $adam_preview ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'adam-comunidade' ); ?></a>
		<?php endif; ?>
	</header>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_field_save">
		<input type="hidden" name="field_id" value="<?php echo esc_attr( (string) $field_id ); ?>">
		<?php wp_nonce_field( 'adam_field_save' ); ?>
		<div class="adam-editor-layout">
			<div>
				<nav class="adam-editor-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Field sections', 'adam-comunidade' ); ?>">
					<?php
					$tabs = array(
						'basic'       => __( 'Basic Information', 'adam-comunidade' ),
						'branding'    => __( 'Branding', 'adam-comunidade' ),
						'description' => __( 'Description', 'adam-comunidade' ),
						'location'    => __( 'Location', 'adam-comunidade' ),
						'ownership'   => __( 'Ownership', 'adam-comunidade' ),
						'styles'      => __( 'Playing Styles', 'adam-comunidade' ),
						'facilities'  => __( 'Facilities', 'adam-comunidade' ),
						'rules'       => __( 'Rules', 'adam-comunidade' ),
						'capacity'    => __( 'Capacity', 'adam-comunidade' ),
						'contacts'    => __( 'Contacts', 'adam-comunidade' ),
						'seo'         => __( 'SEO', 'adam-comunidade' ),
					);
					foreach ( $tabs as $tab_id => $tab_label ) :
						?>
						<button type="button" role="tab" data-adam-field-tab="<?php echo esc_attr( $tab_id ); ?>" aria-selected="<?php echo 'basic' === $tab_id ? 'true' : 'false'; ?>" aria-controls="adam-field-panel-<?php echo esc_attr( $tab_id ); ?>"><?php echo esc_html( $tab_label ); ?></button>
					<?php endforeach; ?>
				</nav>

				<section class="adam-card adam-field-panel" id="adam-field-panel-basic">
					<h2><?php esc_html_e( 'Basic Information', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Field Name', 'adam-comunidade' ); ?> *</span><input id="adam-field-name" type="text" name="field[name]" value="<?php echo esc_attr( (string) $adam_value( 'name' ) ); ?>" maxlength="191" required></label>
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Slug', 'adam-comunidade' ); ?> *</span><input id="adam-field-slug" type="text" name="field[slug]" value="<?php echo esc_attr( $adam_slug ); ?>" maxlength="191" required></label>
					</div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-branding" hidden>
					<h2><?php esc_html_e( 'Branding', 'adam-comunidade' ); ?></h2>
					<div class="adam-media-field" data-adam-field-media="cover">
						<h3><?php esc_html_e( 'Cover Image', 'adam-comunidade' ); ?></h3>
						<p><?php esc_html_e( 'Recommended: 1920×700 px.', 'adam-comunidade' ); ?></p>
						<input type="hidden" name="field[cover_id]" value="<?php echo esc_attr( (string) $adam_value( 'cover_id', 0 ) ); ?>">
						<div class="adam-media-preview adam-media-preview--cover"><?php echo wp_get_attachment_image( (int) $adam_value( 'cover_id', 0 ), 'adam-field-cover' ); ?></div>
						<button class="button adam-field-media-select" type="button" data-kind="cover"><?php esc_html_e( 'Choose Cover', 'adam-comunidade' ); ?></button>
						<button class="button-link-delete adam-field-media-remove" type="button"><?php esc_html_e( 'Remove', 'adam-comunidade' ); ?></button>
					</div>
					<div class="adam-media-field adam-gallery-field" data-adam-field-media="gallery">
						<h3><?php esc_html_e( 'Gallery', 'adam-comunidade' ); ?></h3>
						<p><?php esc_html_e( 'Drag to sort. Captions are displayed in the public lightbox.', 'adam-comunidade' ); ?></p>
						<div class="adam-field-gallery-list">
							<?php foreach ( $gallery as $item ) : ?>
								<div class="adam-field-gallery-item" data-attachment-id="<?php echo esc_attr( (string) $item->attachment_id ); ?>">
									<input type="hidden" name="field[gallery_ids][]" value="<?php echo esc_attr( (string) $item->attachment_id ); ?>">
									<?php echo wp_get_attachment_image( (int) $item->attachment_id, 'thumbnail' ); ?>
									<input type="text" name="field[gallery_captions][<?php echo esc_attr( (string) $item->attachment_id ); ?>]" value="<?php echo esc_attr( $item->caption ); ?>" placeholder="<?php esc_attr_e( 'Caption', 'adam-comunidade' ); ?>">
									<button type="button" class="adam-field-gallery-remove" aria-label="<?php esc_attr_e( 'Remove image', 'adam-comunidade' ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>
						<button class="button adam-field-media-select" type="button" data-kind="gallery"><?php esc_html_e( 'Choose Gallery Images', 'adam-comunidade' ); ?></button>
					</div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-description" hidden>
					<h2><?php esc_html_e( 'Description', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Short Description', 'adam-comunidade' ); ?></span><textarea name="field[short_description]" maxlength="320" rows="3"><?php echo esc_textarea( (string) $adam_value( 'short_description' ) ); ?></textarea></label>
					<div class="adam-field"><span><?php esc_html_e( 'Full Description', 'adam-comunidade' ); ?></span>
						<?php wp_editor( wp_kses_post( (string) $adam_value( 'full_description' ) ), 'adam_field_description', array( 'textarea_name' => 'field[full_description]', 'textarea_rows' => 14, 'media_buttons' => true ) ); ?>
					</div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-location" hidden>
					<h2><?php esc_html_e( 'Location', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<?php foreach ( array( 'district' => __( 'District', 'adam-comunidade' ), 'municipality' => __( 'Municipality', 'adam-comunidade' ), 'address' => __( 'Address', 'adam-comunidade' ) ) as $key => $label ) : ?>
							<label class="adam-field <?php echo 'address' === $key ? 'adam-field--wide' : ''; ?>"><span><?php echo esc_html( $label ); ?></span><input type="text" name="field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $key ) ); ?>"></label>
						<?php endforeach; ?>
						<label class="adam-field"><span><?php esc_html_e( 'Latitude', 'adam-comunidade' ); ?></span><input type="number" step="0.0000001" min="-90" max="90" name="field[latitude]" value="<?php echo esc_attr( (string) $adam_value( 'latitude' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Longitude', 'adam-comunidade' ); ?></span><input type="number" step="0.0000001" min="-180" max="180" name="field[longitude]" value="<?php echo esc_attr( (string) $adam_value( 'longitude' ) ); ?>"></label>
						<label class="adam-field adam-field--wide"><span><?php esc_html_e( 'Google Maps URL', 'adam-comunidade' ); ?></span><input type="url" name="field[maps_url]" value="<?php echo esc_attr( (string) $adam_value( 'maps_url' ) ); ?>"></label>
					</div>
					<p class="adam-notice"><?php esc_html_e( 'Coordinates power the OpenStreetMap view and future directory maps.', 'adam-comunidade' ); ?></p>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-ownership" hidden>
					<h2><?php esc_html_e( 'Ownership', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Associated Team', 'adam-comunidade' ); ?></span><select name="field[associated_team]">
						<option value="0"><?php esc_html_e( 'No Associated Team', 'adam-comunidade' ); ?></option>
						<?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( (string) $team->id ); ?>" <?php selected( $selected_team, $team->id ); ?>><?php echo esc_html( $team->name ); ?></option><?php endforeach; ?>
					</select></label>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-styles" hidden>
					<h2><?php esc_html_e( 'Playing Styles', 'adam-comunidade' ); ?></h2>
					<div class="adam-choice-grid"><?php foreach ( Options::playing_styles() as $key => $label ) : ?><label><input type="checkbox" name="field[playing_styles][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $adam_styles, true ) ); ?>><?php echo esc_html( $label ); ?></label><?php endforeach; ?></div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-facilities" hidden>
					<div class="adam-card__header"><div><h2><?php esc_html_e( 'Facilities', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Reusable amenities can be renamed or extended from the amenities manager.', 'adam-comunidade' ); ?></p></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=adam-comunidade-field-amenities' ) ); ?>"><?php esc_html_e( 'Manage', 'adam-comunidade' ); ?></a></div>
					<div class="adam-facility-switches"><?php foreach ( $amenity_options as $amenity ) : ?><label><span><?php echo esc_html( $amenity->label ); ?></span><span class="adam-switch"><input type="checkbox" name="field[amenities][]" value="<?php echo esc_attr( (string) $amenity->id ); ?>" <?php checked( in_array( (int) $amenity->id, $selected_amenities, true ) ); ?>><span></span></span></label><?php endforeach; ?></div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-rules" hidden>
					<h2><?php esc_html_e( 'Rules', 'adam-comunidade' ); ?></h2>
					<p><?php esc_html_e( 'Document FPS limits, pyro policy, Bio BB requirements, smoking, alcohol, pets, and other local rules.', 'adam-comunidade' ); ?></p>
					<?php wp_editor( wp_kses_post( (string) $adam_value( 'rules' ) ), 'adam_field_rules', array( 'textarea_name' => 'field[rules]', 'textarea_rows' => 12, 'media_buttons' => false ) ); ?>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-capacity" hidden>
					<h2><?php esc_html_e( 'Capacity', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid"><?php foreach ( array( 'max_players' => __( 'Maximum Players', 'adam-comunidade' ), 'min_players' => __( 'Minimum Players', 'adam-comunidade' ), 'recommended_players' => __( 'Recommended Players', 'adam-comunidade' ) ) as $key => $label ) : ?><label class="adam-field"><span><?php echo esc_html( $label ); ?></span><input type="number" min="0" name="field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $key, 0 ) ); ?>"></label><?php endforeach; ?></div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-contacts" hidden>
					<h2><?php esc_html_e( 'Contacts', 'adam-comunidade' ); ?></h2>
					<div class="adam-form-grid">
						<?php foreach ( array( 'website' => 'Website', 'facebook' => 'Facebook', 'instagram' => 'Instagram' ) as $key => $label ) : ?><label class="adam-field"><span><?php echo esc_html( $label ); ?></span><input type="url" name="field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $adam_value( $key ) ); ?>"></label><?php endforeach; ?>
						<label class="adam-field"><span><?php esc_html_e( 'Email', 'adam-comunidade' ); ?></span><input type="email" name="field[email]" value="<?php echo esc_attr( (string) $adam_value( 'email' ) ); ?>"></label>
						<label class="adam-field"><span><?php esc_html_e( 'Phone', 'adam-comunidade' ); ?></span><input type="tel" name="field[phone]" value="<?php echo esc_attr( (string) $adam_value( 'phone' ) ); ?>"></label>
					</div>
				</section>

				<section class="adam-card adam-field-panel" id="adam-field-panel-seo" hidden>
					<h2><?php esc_html_e( 'SEO', 'adam-comunidade' ); ?></h2>
					<label class="adam-field"><span><?php esc_html_e( 'Meta Title', 'adam-comunidade' ); ?></span><input type="text" maxlength="255" name="field[meta_title]" value="<?php echo esc_attr( (string) $adam_value( 'meta_title' ) ); ?>"><small><?php esc_html_e( 'Falls back to the field name.', 'adam-comunidade' ); ?></small></label>
					<label class="adam-field"><span><?php esc_html_e( 'Meta Description', 'adam-comunidade' ); ?></span><textarea maxlength="320" rows="3" name="field[meta_description]"><?php echo esc_textarea( (string) $adam_value( 'meta_description' ) ); ?></textarea><small><?php esc_html_e( 'Falls back to the short description.', 'adam-comunidade' ); ?></small></label>
				</section>
			</div>

			<aside class="adam-card adam-publish-card">
				<h2><?php esc_html_e( 'Publish', 'adam-comunidade' ); ?></h2>
				<label class="adam-field"><span><?php esc_html_e( 'Status', 'adam-comunidade' ); ?></span><select name="field[status]"><?php foreach ( Options::statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $adam_status, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label class="adam-field"><span><?php esc_html_e( 'Availability', 'adam-comunidade' ); ?></span><select name="field[availability]"><?php foreach ( Options::availability_statuses() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $adam_value( 'availability', 'open' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="field[featured]" value="1" <?php checked( (int) $adam_value( 'featured', 0 ), 1 ); ?>> <span><?php esc_html_e( 'Featured Field', 'adam-comunidade' ); ?></span></label>
				<label class="adam-field adam-field--checkbox"><input type="checkbox" name="field[verification]" value="verified_field" <?php checked( $adam_value( 'verification' ), 'verified_field' ); ?>> <span><?php esc_html_e( 'Verified Field', 'adam-comunidade' ); ?></span></label>
				<button class="button button-primary adam-button" type="submit"><?php esc_html_e( 'Save Field', 'adam-comunidade' ); ?></button>
			</aside>
		</div>
	</form>
</div>
