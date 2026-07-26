<?php
/**
 * Public field card.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Router;
use ADAM\Comunidade\Fields\View;

$adam_styles = Options::decode_list( $field->playing_styles );
?>
<article class="adam-field-card">
	<a class="adam-field-card__cover" href="<?php echo esc_url( Router::field_url( $field ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $field->cover_id ) : ?>
			<?php echo wp_get_attachment_image( (int) $field->cover_id, 'adam-field-card', false, array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
	</a>
	<div class="adam-field-card__body">
		<?php if ( ! empty( $field->featured ) ) : ?><span class="adam-comunidade__badge"><?php esc_html_e( 'Featured', 'adam-comunidade' ); ?></span><?php endif; ?>
		<?php if ( 'verified_field' === ( $field->verification ?? '' ) ) : ?><span class="adam-badge adam-badge--verified"><?php esc_html_e( 'Verified Field', 'adam-comunidade' ); ?></span><?php endif; ?>
		<span class="adam-badge adam-availability adam-availability--<?php echo esc_attr( $field->availability ?? 'open' ); ?>"><?php echo esc_html( Options::availability_statuses()[ $field->availability ?? 'open' ] ?? __( 'Open', 'adam-comunidade' ) ); ?></span>
		<h2><a href="<?php echo esc_url( Router::field_url( $field ) ); ?>"><?php echo esc_html( $field->name ); ?></a></h2>
		<p class="adam-field-card__location"><?php echo esc_html( implode( ', ', array_filter( array( $field->municipality, $field->district ) ) ) ); ?></p>
		<?php if ( $adam_styles ) : ?><div class="adam-field-badges">
			<?php foreach ( array_slice( $adam_styles, 0, 3 ) as $style ) : ?><span><?php echo esc_html( View::style_label( $style ) ); ?></span><?php endforeach; ?>
		</div><?php endif; ?>
		<?php if ( $amenities ) : ?><div class="adam-field-card__amenities" aria-label="<?php esc_attr_e( 'Facilities', 'adam-comunidade' ); ?>">
			<?php foreach ( array_slice( $amenities, 0, 5 ) as $amenity ) : ?><span title="<?php echo esc_attr( $amenity->label ); ?>"><?php echo View::amenity_icon( $amenity->icon, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php endforeach; ?>
		</div><?php endif; ?>
		<?php if ( $field->associated_team_name ) : ?><p class="adam-field-card__team"><?php esc_html_e( 'Team:', 'adam-comunidade' ); ?> <strong><?php echo esc_html( $field->associated_team_name ); ?></strong></p><?php endif; ?>
		<a class="adam-field-button" href="<?php echo esc_url( Router::field_url( $field ) ); ?>"><?php esc_html_e( 'View Field', 'adam-comunidade' ); ?></a>
	</div>
</article>
