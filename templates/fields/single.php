<?php
/**
 * Public single field.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Public_Hero;
use ADAM\Comunidade\Fields\Map;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Repository;
use ADAM\Comunidade\Fields\Router;
use ADAM\Comunidade\Fields\Validator;
use ADAM\Comunidade\Fields\View;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;
use ADAM\Comunidade\Teams\View as Team_View;

$field       = Router::current_field();
$repository  = new Repository();
$styles      = Options::decode_list( $field->playing_styles );
$amenities   = $repository->amenities( (int) $field->id );
$gallery     = array_values(
	array_filter(
		$repository->gallery( (int) $field->id ),
		static fn( object $item ): bool => wp_attachment_is_image( (int) $item->attachment_id )
			&& (bool) wp_get_attachment_image_url( (int) $item->attachment_id, 'full' )
	)
);
$team_id     = $repository->associated_team_id( (int) $field->id );
$team        = $team_id ? ( new Team_Repository() )->find( $team_id ) : null;
$team        = $team && 'published' === $team->status ? $team : null;
$has_coordinates = is_numeric( $field->latitude ) && is_numeric( $field->longitude )
	&& (float) $field->latitude >= -90 && (float) $field->latitude <= 90
	&& (float) $field->longitude >= -180 && (float) $field->longitude <= 180;
$coordinates = $has_coordinates
	? (string) $field->latitude . ', ' . (string) $field->longitude
	: '';
$google_maps_url = Validator::sanitize_maps_url( (string) $field->maps_url );
$google_maps_url = is_wp_error( $google_maps_url ) ? '' : $google_maps_url;
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
$capacity = array_filter(
	array(
		'max_players'         => array( absint( $field->max_players ), __( 'Máximo', 'adam-comunidade' ) ),
		'min_players'         => array( absint( $field->min_players ), __( 'Mínimo', 'adam-comunidade' ) ),
		'recommended_players' => array( absint( $field->recommended_players ), __( 'Recomendado', 'adam-comunidade' ) ),
	),
	static fn( array $item ): bool => $item[0] > 0
);
$has_mobile_actions = (bool) ( $directions_url || $google_maps_url || $coordinates );
$has_description    = '' !== trim( (string) $field->short_description )
	|| '' !== trim( wp_strip_all_tags( (string) $field->full_description ) );
$has_rules          = '' !== trim( wp_strip_all_tags( (string) $field->rules ) );
$has_opening_hours  = '' !== trim( (string) ( $field->opening_hours ?? '' ) );

get_header();
?>
<main class="adam-comunidade adam-field-single<?php echo $has_mobile_actions ? ' has-mobile-actions' : ''; ?>" id="main">
	<section class="<?php echo esc_attr( Public_Hero::root( 'adam-field-hero' ) ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'media', 'adam-field-hero__cover' ) ); ?>">
			<?php if ( $field->cover_id ) : ?>
				<?php echo wp_get_attachment_image( (int) $field->cover_id, 'adam-field-cover', false, array( 'fetchpriority' => 'high' ) ); ?>
			<?php else : ?>
				<?php echo Placeholder_Image::cover( 'field', (string) $field->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-field-container adam-field-hero__content' ) ); ?>">
			<div>
				<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $field->name ); ?></h1>
				<?php if ( ! empty( $field->is_associated ) ) : ?><span class="adam-badge adam-badge--associated"><?php esc_html_e( 'Associado ADAM', 'adam-comunidade' ); ?></span><?php endif; ?>
				<?php if ( 'verified_field' === ( $field->verification ?? '' ) ) : ?><span class="adam-badge adam-badge--verified"><?php esc_html_e( 'Autorização verificada', 'adam-comunidade' ); ?></span><?php endif; ?>
				<span class="adam-badge adam-availability adam-availability--<?php echo esc_attr( $field->availability ?? 'open' ); ?>"><?php echo esc_html( Options::availability_statuses()[ $field->availability ?? 'open' ] ?? __( 'Aberto', 'adam-comunidade' ) ); ?></span>
				<?php if ( $field->municipality || $field->district ) : ?><p><?php echo esc_html( implode( ', ', array_filter( array( $field->municipality, $field->district ) ) ) ); ?></p><?php endif; ?>
				<?php if ( $styles ) : ?><div class="adam-field-badges adam-field-badges--hero">
					<?php foreach ( $styles as $style ) : ?><span><?php echo esc_html( View::style_label( $style ) ); ?></span><?php endforeach; ?>
				</div><?php endif; ?>
				<?php if ( $team ) : ?><a class="adam-field-team-badge" href="<?php echo esc_url( Team_Router::team_url( $team ) ); ?>"><?php echo esc_html( $team->name ); ?></a><?php endif; ?>
			</div>
		</div>
	</section>

	<?php if ( $has_mobile_actions ) : ?><div class="adam-field-mobile-actions">
		<?php if ( $directions_url ) : ?><a href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Obter direções', 'adam-comunidade' ); ?></a><?php endif; ?>
		<?php if ( $google_maps_url && $google_maps_url !== $directions_url ) : ?><a href="<?php echo esc_url( $google_maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Google Maps', 'adam-comunidade' ); ?></a><?php endif; ?>
		<?php if ( $coordinates ) : ?><button type="button" data-copy-gps="<?php echo esc_attr( $coordinates ); ?>"><?php esc_html_e( 'Copiar coordenadas GPS', 'adam-comunidade' ); ?></button><?php endif; ?>
	</div><?php endif; ?>

	<div class="adam-field-container adam-field-content">
		<?php if ( $has_description ) : ?>
			<details class="adam-field-section adam-field-collapsible" open>
				<summary><h2><?php esc_html_e( 'Sobre', 'adam-comunidade' ); ?></h2></summary>
				<?php if ( $field->short_description ) : ?><p class="adam-field-lead"><?php echo esc_html( $field->short_description ); ?></p><?php endif; ?>
				<?php echo wp_kses_post( wpautop( $field->full_description ) ); ?>
			</details>
		<?php endif; ?>

		<?php do_action( 'adam_comunidade_field_after_about', $field ); ?>

		<?php if ( $amenities ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Comodidades', 'adam-comunidade' ); ?></h2>
			<div class="adam-facilities-grid">
				<?php foreach ( $amenities as $amenity ) : ?><div>
					<span><?php echo View::amenity_icon( $amenity->icon, 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<strong><?php echo esc_html( $amenity->label ); ?></strong>
				</div><?php endforeach; ?>
			</div>
		</section><?php endif; ?>

		<?php if ( $capacity ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Capacidade', 'adam-comunidade' ); ?></h2>
			<div class="adam-field-capacity">
				<?php foreach ( $capacity as $capacity_item ) : ?><div><strong><?php echo esc_html( (string) $capacity_item[0] ); ?></strong><span><?php echo esc_html( $capacity_item[1] ); ?></span></div><?php endforeach; ?>
			</div>
		</section><?php endif; ?>

		<?php if ( $has_rules ) : ?><details class="adam-field-section adam-field-collapsible" open>
			<summary><h2><?php esc_html_e( 'Regras', 'adam-comunidade' ); ?></h2></summary>
			<?php echo wp_kses_post( wpautop( $field->rules ) ); ?>
		</details><?php endif; ?>

		<?php if ( $has_opening_hours ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Horários', 'adam-comunidade' ); ?></h2>
			<p><?php echo nl2br( esc_html( (string) $field->opening_hours ) ); ?></p>
		</section><?php endif; ?>

		<?php if ( $gallery ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Fotografias', 'adam-comunidade' ); ?></h2>
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

		<?php if ( $field->address || $coordinates || $google_maps_url ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Mapa e direções', 'adam-comunidade' ); ?></h2>
			<?php if ( $field->address ) : ?><p class="adam-field-address"><?php echo esc_html( $field->address ); ?></p><?php endif; ?>
			<?php if ( $coordinates ) : ?><div class="adam-field-map">
				<iframe title="<?php esc_attr_e( 'Mapa da localização do campo', 'adam-comunidade' ); ?>" loading="lazy" src="<?php echo esc_url( Map::embed_url( (float) $field->latitude, (float) $field->longitude ) ); ?>"></iframe>
			</div><?php endif; ?>
			<div class="adam-field-actions">
				<?php if ( $directions_url ) : ?><a class="adam-field-button" href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Obter direções no Google Maps', 'adam-comunidade' ); ?></a><?php endif; ?>
				<?php if ( $google_maps_url && $google_maps_url !== $directions_url ) : ?><a class="adam-field-button adam-field-button--secondary" href="<?php echo esc_url( $google_maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Abrir no Google Maps', 'adam-comunidade' ); ?></a><?php endif; ?>
				<?php if ( $coordinates ) : ?><button class="adam-field-button adam-field-button--secondary" type="button" data-copy-gps="<?php echo esc_attr( $coordinates ); ?>"><?php esc_html_e( 'Copiar coordenadas GPS', 'adam-comunidade' ); ?></button><?php endif; ?>
			</div>
		</section><?php endif; ?>

		<?php if ( $team ) : ?><section class="adam-field-section">
			<h2><?php esc_html_e( 'Equipa Associada', 'adam-comunidade' ); ?></h2>
			<div class="adam-associated-team-card">
				<?php echo Team_View::logo( $team, array( 'class' => 'adam-associated-team-card__logo', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div><h3><?php echo esc_html( $team->name ); ?></h3><?php if ( $team->short_description ) : ?><p><?php echo esc_html( $team->short_description ); ?></p><?php endif; ?><a class="adam-field-button" href="<?php echo esc_url( Team_Router::team_url( $team ) ); ?>"><?php esc_html_e( 'Ver Equipa', 'adam-comunidade' ); ?></a></div>
			</div>
		</section><?php endif; ?>

		<?php do_action( 'adam_comunidade_field_before_events', $field ); ?>
		<?php do_action( 'adam_comunidade_field_after_content', $field ); ?>
	</div>

	<?php if ( $gallery ) : ?><div class="adam-field-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Visualizador de imagens', 'adam-comunidade' ); ?>" hidden>
		<button type="button" aria-label="<?php esc_attr_e( 'Fechar visualizador de imagens', 'adam-comunidade' ); ?>">&times;</button>
		<figure><img src="" alt=""><figcaption></figcaption></figure>
	</div><?php endif; ?>
	<div class="adam-field-toast" role="status" aria-live="polite" hidden></div>
</main>
<?php get_footer(); ?>
