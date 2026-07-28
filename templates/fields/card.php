<?php
/**
 * Public field card.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Router;
use ADAM\Comunidade\Fields\View;

$adam_styles     = Options::decode_list( $field->playing_styles );
$adam_associated = ! empty( $field->is_associated );
?>
<article class="adam-field-card adam-directory-card<?php echo $adam_associated ? ' adam-field-card--associated' : ''; ?>">
	<a class="adam-field-card__cover" href="<?php echo esc_url( Router::field_url( $field ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $field->cover_id ) : ?>
			<?php echo wp_get_attachment_image( (int) $field->cover_id, 'adam-field-card', false, array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<?php echo Placeholder_Image::cover( 'field', (string) $field->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
		<?php if ( $adam_associated ) : ?><strong class="adam-field-associated-badge"><?php esc_html_e( 'Associado ADAM', 'adam-comunidade' ); ?></strong><?php endif; ?>
	</a>
	<div class="adam-field-card__body">
		<?php if ( $adam_associated ) : ?><span class="screen-reader-text"><?php esc_html_e( 'Campo Associado ADAM', 'adam-comunidade' ); ?></span><?php endif; ?>
		<h3><a href="<?php echo esc_url( Router::field_url( $field ) ); ?>"><?php echo esc_html( $field->name ); ?></a></h3>
		<?php if ( $field->municipality || $field->district ) : ?><p class="adam-field-card__location"><?php echo esc_html( implode( ', ', array_filter( array( $field->municipality, $field->district ) ) ) ); ?></p><?php endif; ?>
		<?php if ( $field->short_description ) : ?><p class="adam-field-card__summary"><?php echo esc_html( wp_trim_words( $field->short_description, 20 ) ); ?></p><?php endif; ?>
		<?php if ( $adam_styles ) : ?><div class="adam-field-badges"><?php foreach ( array_slice( $adam_styles, 0, 2 ) as $style ) : ?><span><?php echo esc_html( View::style_label( $style ) ); ?></span><?php endforeach; ?></div><?php endif; ?>
		<?php if ( $amenities ) : ?><div class="adam-field-card__amenities" aria-label="<?php esc_attr_e( 'Instalações', 'adam-comunidade' ); ?>"><?php foreach ( array_slice( $amenities, 0, 6 ) as $amenity ) : ?><span title="<?php echo esc_attr( $amenity->label ); ?>"><?php echo View::amenity_icon( $amenity->icon, 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text"><?php echo esc_html( $amenity->label ); ?></span></span><?php endforeach; ?></div><?php endif; ?>
		<?php if ( $field->associated_team_name ) : ?><p class="adam-field-card__team"><?php esc_html_e( 'Campo da equipa:', 'adam-comunidade' ); ?> <strong><?php echo esc_html( $field->associated_team_name ); ?></strong></p><?php endif; ?>
		<a class="adam-field-card__link" href="<?php echo esc_url( Router::field_url( $field ) ); ?>"><?php esc_html_e( 'Ver detalhes', 'adam-comunidade' ); ?> <span aria-hidden="true">→</span></a>
	</div>
</article>
