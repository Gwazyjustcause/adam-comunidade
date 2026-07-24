<?php
/**
 * Public single field.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Map;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Repository;
use ADAM\Comunidade\Fields\Router;
use ADAM\Comunidade\Fields\View;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;

$field       = Router::current_field();
$repository  = new Repository();
$styles      = Options::decode_list( $field->playing_styles );
$amenities   = $repository->amenities( (int) $field->id );
$gallery     = $repository->gallery( (int) $field->id );
$team_id     = $repository->associated_team_id( (int) $field->id );
$team        = $team_id ? ( new Team_Repository() )->find( $team_id ) : null;
$team        = $team && 'published' === $team->status ? $team : null;
$coordinates = null !== $field->latitude && null !== $field->longitude
	? $field->latitude . ', ' . $field->longitude
	: '';
$google_maps_url = $field->maps_url;
$directions_url  = '';
if ( $coordinates ) {
	$directions_url = 'https://www.google.com/maps/dir/?api=1&destination='
		. rawurlencode( $field->latitude . ',' . $field->longitude );
	$google_maps_url = $google_maps_url
		?: 'https://www.google.com/maps/search/?api=1&query='
			. rawurlencode( $field->latitude . ',' . $field->longitude );
} elseif ( $google_maps_url ) {
	$directions_url = $google_maps_url;
}
$contacts    = array_filter(
	array(
		array( __( 'Website', 'adam-comunidade' ), $field->website ),
		array( 'Facebook', $field->facebook ),
		array( 'Instagram', $field->instagram ),
		array( __( 'Email', 'adam-comunidade' ), $field->email ? 'mailto:' . $field->email : '' ),
		array(
			__( 'Phone', 'adam-comunidade' ),
			$field->phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $field->phone ) : '',
		),
	),
	static fn( array $contact ): bool => ! empty( $contact[1] )
);

get_header();
?>
<main class="adam-comunidade adam-field-single" id="main">
	<section class="adam-field-hero">
		<div class="adam-field-hero__cover">
			<?php echo wp_get_attachment_image( (int) $field->cover_id, 'adam-field-cover', false, array( 'fetchpriority' => 'high' ) ); ?>
		</div>
		<div class="adam-field-container adam-field-hero__content">
			<div>
				<h1><?php echo esc_html( $field->name ); ?></h1>
				<p><?php echo esc_html( implode( ', ', array_filter( array( $field->municipality, $field->district ) ) ) ); ?></p>
				<?php if ( $styles ) : ?><div class="adam-field-badges adam-field-badges--hero">
					<?php foreach ( $styles as $style ) : ?><span><?php echo esc_html( View::style_label( $style ) ); ?></span><?php endforeach; ?>
				</div><?php endif; ?>
				<?php if ( $team ) : ?><a class="adam-field-team-badge" href="<?php echo esc_url( Team_Router::team_url( $team ) ); ?>"><?php echo esc_html( $team->name ); ?></a><?php endif; ?>
			</div>
		</div>
	</section>

	<div class="adam-field-mobile-actions">
		<?php if ( $directions_url ) : ?><a href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Directions', 'adam-comunidade' ); ?></a><?php endif; ?>
		<?php if ( $field->phone ) : ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $field->phone ) ); ?>"><?php esc_html_e( 'Call', 'adam-comunidade' ); ?></a><?php endif; ?>
		<?php if ( $google_maps_url ) : ?><a href="<?php echo esc_url( $google_maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Google Maps', 'adam-comunidade' ); ?></a><?php endif; ?>
		<?php if ( $coordinates ) : ?><button type="button" data-copy-gps="<?php echo esc_attr( $coordinates ); ?>"><?php esc_html_e( 'Copy GPS', 'adam-comunidade' ); ?></button><?php endif; ?>
	</div>

	<div class="adam-field-container adam-field-content">
		<?php if ( $field->short_description || $field->full_description ) : ?>
			<details class="adam-field-section adam-field-collapsible" open>
				<summary><h2><?php esc_html_e( 'About', 'adam-comunidade' ); ?></h2></summary>
				<?php if ( $field->short_description ) : ?><p class="adam-field-lead"><?php echo esc_html( $field->short_description ); ?></p><?php endif; ?>
				<?php echo wp_kses_post( wpautop( $field->full_description ) ); ?>
			</details>
		<?php endif; ?>

		<?php do_action( 'adam_comunidade_field_after_about', $field ); ?>

		<?php if ( $amenities ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Facilities', 'adam-comunidade' ); ?></h2>
			<div class="adam-facilities-grid">
				<?php foreach ( $amenities as $amenity ) : ?><div>
					<span><?php echo View::amenity_icon( $amenity->icon, 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<strong><?php echo esc_html( $amenity->label ); ?></strong>
				</div><?php endforeach; ?>
			</div>
		</section><?php endif; ?>

		<section class="adam-field-section">
			<h2><?php esc_html_e( 'Capacity', 'adam-comunidade' ); ?></h2>
			<div class="adam-field-capacity">
				<div><strong><?php echo esc_html( (string) $field->max_players ); ?></strong><span><?php esc_html_e( 'Maximum', 'adam-comunidade' ); ?></span></div>
				<div><strong><?php echo esc_html( (string) $field->min_players ); ?></strong><span><?php esc_html_e( 'Minimum', 'adam-comunidade' ); ?></span></div>
				<div><strong><?php echo esc_html( (string) $field->recommended_players ); ?></strong><span><?php esc_html_e( 'Recommended', 'adam-comunidade' ); ?></span></div>
			</div>
		</section>

		<?php if ( $field->rules ) : ?><details class="adam-field-section adam-field-collapsible" open>
			<summary><h2><?php esc_html_e( 'Rules', 'adam-comunidade' ); ?></h2></summary>
			<?php echo wp_kses_post( wpautop( $field->rules ) ); ?>
		</details><?php endif; ?>

		<?php if ( $gallery ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Gallery', 'adam-comunidade' ); ?></h2>
			<div class="adam-field-gallery">
				<?php foreach ( $gallery as $item ) : ?>
					<?php $full = wp_get_attachment_image_url( (int) $item->attachment_id, 'full' ); ?>
					<a href="<?php echo esc_url( $full ); ?>" data-field-lightbox data-caption="<?php echo esc_attr( $item->caption ); ?>">
						<?php echo wp_get_attachment_image( (int) $item->attachment_id, 'adam-field-gallery', false, array( 'loading' => 'lazy' ) ); ?>
						<?php if ( $item->caption ) : ?><span><?php echo esc_html( $item->caption ); ?></span><?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section><?php endif; ?>

		<?php if ( $field->address || $coordinates || $field->maps_url ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Map & Directions', 'adam-comunidade' ); ?></h2>
			<?php if ( $field->address ) : ?><p class="adam-field-address"><?php echo esc_html( $field->address ); ?></p><?php endif; ?>
			<?php if ( $coordinates ) : ?><div class="adam-field-map">
				<iframe title="<?php esc_attr_e( 'Field location map', 'adam-comunidade' ); ?>" loading="lazy" src="<?php echo esc_url( Map::embed_url( (float) $field->latitude, (float) $field->longitude ) ); ?>"></iframe>
			</div><?php endif; ?>
			<div class="adam-field-actions">
				<?php if ( $directions_url ) : ?><a class="adam-field-button" href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Directions / Google Maps', 'adam-comunidade' ); ?></a><?php endif; ?>
				<?php if ( $google_maps_url && $google_maps_url !== $directions_url ) : ?><a class="adam-field-button adam-field-button--secondary" href="<?php echo esc_url( $google_maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Google Maps', 'adam-comunidade' ); ?></a><?php endif; ?>
				<?php if ( $coordinates ) : ?><button class="adam-field-button adam-field-button--secondary" type="button" data-copy-gps="<?php echo esc_attr( $coordinates ); ?>"><?php esc_html_e( 'Copy GPS', 'adam-comunidade' ); ?></button><?php endif; ?>
			</div>
		</section><?php endif; ?>

		<?php if ( $contacts ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Contact', 'adam-comunidade' ); ?></h2>
			<div class="adam-field-actions"><?php foreach ( $contacts as $contact ) : ?><a class="adam-field-button" href="<?php echo esc_url( $contact[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $contact[0] ); ?></a><?php endforeach; ?></div>
		</section><?php endif; ?>

		<?php if ( $team ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Equipa Associada', 'adam-comunidade' ); ?></h2>
			<div class="adam-associated-team-card">
				<div><?php echo wp_get_attachment_image( (int) $team->logo_id, 'adam-team-logo' ); ?></div>
				<div><h3><?php echo esc_html( $team->name ); ?></h3><p><?php echo esc_html( $team->short_description ); ?></p><a class="adam-field-button" href="<?php echo esc_url( Team_Router::team_url( $team ) ); ?>"><?php esc_html_e( 'Ver Equipa', 'adam-comunidade' ); ?></a></div>
			</div>
		</section><?php endif; ?>

		<?php do_action( 'adam_comunidade_field_before_events', $field ); ?>
		<section class="adam-field-section adam-upcoming-events">
			<h2><?php esc_html_e( 'Upcoming Events', 'adam-comunidade' ); ?></h2>
			<div class="adam-comunidade__empty"><?php esc_html_e( 'Integração disponível numa fase futura.', 'adam-comunidade' ); ?></div>
		</section>
		<?php do_action( 'adam_comunidade_field_after_content', $field ); ?>
	</div>

	<div class="adam-field-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'adam-comunidade' ); ?>" hidden>
		<button type="button" aria-label="<?php esc_attr_e( 'Close image viewer', 'adam-comunidade' ); ?>">&times;</button>
		<figure><img src="" alt=""><figcaption></figcaption></figure>
	</div>
	<div class="adam-field-toast" role="status" aria-live="polite" hidden></div>
</main>
<?php get_footer(); ?>
