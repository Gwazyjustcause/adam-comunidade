<?php
/**
 * Public single team page.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Router;
use ADAM\Comunidade\Teams\View;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\View as Field_View;

$adam_team        = Router::current_team();
$adam_styles      = Options::decode_list( $adam_team->playing_styles );
$adam_equipment   = Options::decode_list( $adam_team->equipment_tags );
$adam_gallery     = Options::decode_list( $adam_team->gallery );
$adam_recruitment = View::label( $adam_team->recruitment_status, Options::recruitment_statuses() );
$adam_field_repository = new Field_Repository();
$adam_associated_fields = $adam_field_repository->query(
	array(
		'status'   => 'published',
		'team_id'  => (int) $adam_team->id,
		'orderby'  => 'name',
		'order'    => 'ASC',
		'per_page' => 12,
	)
)['items'];
$adam_contacts    = array_filter(
	array(
		'website'   => array( __( 'Website', 'adam-comunidade' ), $adam_team->website ),
		'facebook'  => array( 'Facebook', $adam_team->facebook ),
		'instagram' => array( 'Instagram', $adam_team->instagram ),
		'discord'   => array( 'Discord', $adam_team->discord ),
		'youtube'   => array( 'YouTube', $adam_team->youtube ),
		'tiktok'    => array( 'TikTok', $adam_team->tiktok ),
		'email'     => array( __( 'Email', 'adam-comunidade' ), $adam_team->email ? 'mailto:' . $adam_team->email : '' ),
		'phone'     => array( __( 'Phone', 'adam-comunidade' ), $adam_team->phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $adam_team->phone ) : '' ),
	),
	static fn( array $contact ): bool => ! empty( $contact[1] )
);

get_header();
?>
<main
	class="adam-comunidade adam-team-single"
	id="main"
	style="--adam-team-colour: <?php echo esc_attr( $adam_team->team_colour ?: '#1d4ed8' ); ?>"
>
	<section class="adam-team-hero">
		<div class="adam-team-hero__cover">
			<?php echo wp_get_attachment_image( (int) $adam_team->cover_id, 'adam-team-cover', false, array( 'fetchpriority' => 'high' ) ); ?>
		</div>
		<div class="adam-team-container adam-team-hero__content">
			<div class="adam-team-hero__logo">
				<?php if ( $adam_team->logo_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $adam_team->logo_id, 'adam-team-logo' ); ?>
				<?php else : ?>
					<?php echo Helpers::svg_icon( 'community', 54 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
			<div>
				<h1><?php echo esc_html( $adam_team->name ); ?></h1>
				<p><?php echo esc_html( implode( ', ', array_filter( array( $adam_team->municipality, $adam_team->district ) ) ) ); ?></p>
				<div class="adam-team-hero__meta">
					<span><?php echo esc_html( sprintf( _n( '%d member', '%d members', (int) $adam_team->members, 'adam-comunidade' ), (int) $adam_team->members ) ); ?></span>
					<span class="adam-recruitment adam-recruitment--<?php echo esc_attr( $adam_team->recruitment_status ); ?>"><?php echo esc_html( $adam_recruitment ); ?></span>
				</div>
			</div>
		</div>
	</section>

	<div class="adam-team-container adam-team-content">
		<?php if ( $adam_team->short_description || $adam_team->full_description ) : ?>
			<section class="adam-team-section"><h2><?php esc_html_e( 'About', 'adam-comunidade' ); ?></h2>
				<?php if ( $adam_team->short_description ) : ?><p class="adam-team-lead"><?php echo esc_html( $adam_team->short_description ); ?></p><?php endif; ?>
				<?php echo wp_kses_post( wpautop( $adam_team->full_description ) ); ?>
			</section>
		<?php endif; ?>

		<section class="adam-team-section"><h2><?php esc_html_e( 'Statistics', 'adam-comunidade' ); ?></h2><div class="adam-team-stats">
			<div><strong><?php echo esc_html( $adam_team->founded ?: '—' ); ?></strong><span><?php esc_html_e( 'Founded', 'adam-comunidade' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) $adam_team->members ); ?></strong><span><?php esc_html_e( 'Members', 'adam-comunidade' ); ?></span></div>
			<div><strong><?php echo esc_html( (string) count( $adam_styles ) ); ?></strong><span><?php esc_html_e( 'Playing Styles', 'adam-comunidade' ); ?></span></div>
		</div>
		<?php if ( $adam_styles || $adam_equipment ) : ?><div class="adam-team-badges adam-team-badges--large">
			<?php foreach ( array_merge( $adam_styles, $adam_equipment ) as $adam_tag ) : ?><span><?php echo esc_html( View::label( $adam_tag, array_merge( Options::playing_styles(), Options::equipment_tags() ) ) ); ?></span><?php endforeach; ?>
		</div><?php endif; ?></section>

		<?php if ( $adam_gallery ) : ?><section class="adam-team-section"><h2><?php esc_html_e( 'Gallery', 'adam-comunidade' ); ?></h2><div class="adam-team-gallery">
			<?php foreach ( $adam_gallery as $adam_image_id ) : ?>
				<?php $adam_full_image = wp_get_attachment_image_url( $adam_image_id, 'full' ); ?>
				<a href="<?php echo esc_url( $adam_full_image ); ?>" data-adam-lightbox><?php echo wp_get_attachment_image( $adam_image_id, 'large', false, array( 'loading' => 'lazy' ) ); ?></a>
			<?php endforeach; ?>
		</div></section><?php endif; ?>

		<?php if ( $adam_contacts ) : ?><section class="adam-team-section"><h2><?php esc_html_e( 'Contacts', 'adam-comunidade' ); ?></h2><div class="adam-team-contact-buttons">
			<?php foreach ( $adam_contacts as $adam_contact ) : ?><a href="<?php echo esc_url( $adam_contact[1] ); ?>" class="adam-team-button" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $adam_contact[0] ); ?></a><?php endforeach; ?>
		</div></section><?php endif; ?>

		<?php if ( $adam_team->address || $adam_team->maps_url || ( $adam_team->latitude && $adam_team->longitude ) ) : ?>
			<section class="adam-team-section">
				<h2><?php esc_html_e( 'Map', 'adam-comunidade' ); ?></h2>
				<?php if ( $adam_team->address ) : ?>
					<p class="adam-team-address"><?php echo esc_html( $adam_team->address ); ?></p>
				<?php endif; ?>
				<?php if ( $adam_team->latitude && $adam_team->longitude ) : ?>
					<?php
					$adam_map_url = 'https://www.openstreetmap.org/export/embed.html?bbox='
						. ( (float) $adam_team->longitude - 0.02 ) . '%2C'
						. ( (float) $adam_team->latitude - 0.02 ) . '%2C'
						. ( (float) $adam_team->longitude + 0.02 ) . '%2C'
						. ( (float) $adam_team->latitude + 0.02 )
						. '&layer=mapnik&marker=' . $adam_team->latitude . '%2C' . $adam_team->longitude;
					?>
					<div class="adam-team-map">
						<iframe
							title="<?php esc_attr_e( 'Team location map', 'adam-comunidade' ); ?>"
							loading="lazy"
							src="<?php echo esc_url( $adam_map_url ); ?>"
						></iframe>
					</div>
				<?php endif; ?>
				<?php if ( $adam_team->maps_url ) : ?>
					<a class="adam-team-text-link" href="<?php echo esc_url( $adam_team->maps_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Open in Google Maps', 'adam-comunidade' ); ?>
					</a>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="adam-team-section adam-associated-fields">
			<h2><?php esc_html_e( 'Campos Associados', 'adam-comunidade' ); ?></h2>
			<?php if ( $adam_associated_fields ) : ?>
				<div class="adam-field-grid">
					<?php foreach ( $adam_associated_fields as $adam_associated_field ) : ?>
						<?php echo Field_View::card( $adam_associated_field, $adam_field_repository ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="adam-comunidade__empty"><p><?php esc_html_e( 'No associated fields are currently published.', 'adam-comunidade' ); ?></p></div>
			<?php endif; ?>
		</section>
	</div>
	<div class="adam-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Image viewer', 'adam-comunidade' ); ?>" hidden><button type="button" aria-label="<?php esc_attr_e( 'Close image viewer', 'adam-comunidade' ); ?>">&times;</button><img src="" alt=""></div>
</main>
<?php
get_footer();
