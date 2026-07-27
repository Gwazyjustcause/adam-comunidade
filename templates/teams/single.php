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
use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Public_Hero;
use ADAM\Comunidade\Public_Privacy;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\View as Field_View;

$adam_team        = Router::current_team();
$adam_styles      = Options::decode_list( $adam_team->playing_styles );
$adam_equipment   = Options::decode_list( $adam_team->equipment_tags );
$adam_gallery     = Options::decode_list( $adam_team->gallery );
$adam_recruitment = View::label( $adam_team->recruitment_status, Options::recruitment_statuses() );
$adam_has_description = '' !== trim( (string) $adam_team->short_description )
	|| '' !== trim( wp_strip_all_tags( (string) $adam_team->full_description ) );
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
$adam_public_links = Public_Privacy::public_links( $adam_team );
$adam_link_labels  = array(
	'website'   => __( 'Página Web', 'adam-comunidade' ),
	'facebook'  => 'Facebook',
	'instagram' => 'Instagram',
	'discord'   => 'Discord',
	'youtube'   => 'YouTube',
	'tiktok'    => 'TikTok',
	'linkedin'  => 'LinkedIn',
);

get_header();
?>
<main
	class="adam-comunidade adam-team-single"
	id="main"
	style="--adam-team-colour: <?php echo esc_attr( $adam_team->team_colour ?: '#1d4ed8' ); ?>"
>
	<section class="<?php echo esc_attr( Public_Hero::root( 'adam-team-hero' ) ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'media', 'adam-team-hero__cover' ) ); ?>">
			<?php if ( $adam_team->cover_id ) : ?>
				<?php echo wp_get_attachment_image( (int) $adam_team->cover_id, 'adam-team-cover', false, array( 'fetchpriority' => 'high' ) ); ?>
			<?php else : ?>
				<?php echo Placeholder_Image::cover( 'team', (string) $adam_team->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-team-container adam-team-hero__content' ) ); ?>">
			<div class="adam-team-hero__logo">
				<?php if ( $adam_team->logo_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $adam_team->logo_id, 'adam-team-logo' ); ?>
				<?php else : ?>
					<?php echo Placeholder_Image::avatar( 'team', (string) $adam_team->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
			<div>
				<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $adam_team->name ); ?></h1>
				<?php if ( ! empty( $adam_team->is_associated ) ) : ?><span class="adam-badge adam-badge--associated"><?php esc_html_e( 'Equipa Associada ADAM', 'adam-comunidade' ); ?></span><?php endif; ?>
				<?php if ( 'verified_team' === ( $adam_team->verification ?? '' ) ) : ?><span class="adam-badge adam-badge--verified"><?php esc_html_e( 'Equipa verificada', 'adam-comunidade' ); ?></span><?php endif; ?>
				<p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php echo esc_html( implode( ', ', array_filter( array( $adam_team->municipality, $adam_team->district ) ) ) ); ?></p>
				<div class="<?php echo esc_attr( Public_Hero::element( 'meta', 'adam-team-hero__meta' ) ); ?>">
					<?php if ( $adam_team->members ) : ?><span><?php echo esc_html( sprintf( _n( '%d membro', '%d membros', (int) $adam_team->members, 'adam-comunidade' ), (int) $adam_team->members ) ); ?></span><?php endif; ?>
					<span class="adam-recruitment adam-recruitment--<?php echo esc_attr( $adam_team->recruitment_status ); ?>"><?php echo esc_html( $adam_recruitment ); ?></span>
				</div>
				<?php if ( $adam_team->recruitment_min_age || $adam_team->recruitment_experience || $adam_team->recruitment_equipment || $adam_team->recruitment_training ) : ?><div class="adam-recruitment-details"><strong><?php esc_html_e( 'Detalhes do recrutamento', 'adam-comunidade' ); ?></strong><?php if ( $adam_team->recruitment_min_age ) : ?><span><?php echo esc_html( sprintf( __( 'Idade mínima: %d', 'adam-comunidade' ), $adam_team->recruitment_min_age ) ); ?></span><?php endif; ?><?php if ( $adam_team->recruitment_experience ) : ?><span><?php echo esc_html( $adam_team->recruitment_experience ); ?></span><?php endif; ?><?php if ( $adam_team->recruitment_equipment ) : ?><span><?php echo esc_html( $adam_team->recruitment_equipment ); ?></span><?php endif; ?><?php if ( $adam_team->recruitment_training ) : ?><span><?php esc_html_e( 'Treino disponível', 'adam-comunidade' ); ?></span><?php endif; ?></div><?php endif; ?>
			</div>
		</div>
	</section>

	<div class="adam-team-container adam-team-content">
		<?php if ( $adam_has_description ) : ?>
			<section class="adam-team-section"><h2><?php esc_html_e( 'Sobre', 'adam-comunidade' ); ?></h2>
				<?php if ( $adam_team->short_description ) : ?><p class="adam-team-lead"><?php echo esc_html( $adam_team->short_description ); ?></p><?php endif; ?>
				<?php echo wp_kses_post( wpautop( $adam_team->full_description ) ); ?>
			</section>
		<?php endif; ?>

		<?php if ( $adam_team->founded || $adam_team->members || $adam_styles || $adam_equipment ) : ?><section class="adam-team-section"><h2><?php esc_html_e( 'Dados da equipa', 'adam-comunidade' ); ?></h2><div class="adam-team-stats">
			<?php if ( $adam_team->founded ) : ?><div><strong><?php echo esc_html( (string) $adam_team->founded ); ?></strong><span><?php esc_html_e( 'Fundação', 'adam-comunidade' ); ?></span></div><?php endif; ?>
			<?php if ( $adam_team->members ) : ?><div><strong><?php echo esc_html( (string) $adam_team->members ); ?></strong><span><?php esc_html_e( 'Membros', 'adam-comunidade' ); ?></span></div><?php endif; ?>
			<?php if ( $adam_styles ) : ?><div><strong><?php echo esc_html( (string) count( $adam_styles ) ); ?></strong><span><?php esc_html_e( 'Estilos de jogo', 'adam-comunidade' ); ?></span></div><?php endif; ?>
		</div>
		<?php if ( $adam_styles || $adam_equipment ) : ?><div class="adam-team-badges adam-team-badges--large">
			<?php foreach ( array_merge( $adam_styles, $adam_equipment ) as $adam_tag ) : ?><span><?php echo esc_html( View::label( $adam_tag, array_merge( Options::playing_styles(), Options::equipment_tags() ) ) ); ?></span><?php endforeach; ?>
		</div><?php endif; ?></section><?php endif; ?>

		<?php if ( $adam_gallery ) : ?><section class="adam-team-section"><h2><?php esc_html_e( 'Fotografias', 'adam-comunidade' ); ?></h2><div class="adam-team-gallery">
			<?php foreach ( $adam_gallery as $adam_image_id ) : ?>
				<?php $adam_full_image = wp_get_attachment_image_url( $adam_image_id, 'full' ); ?>
				<a href="<?php echo esc_url( $adam_full_image ); ?>" data-adam-lightbox><?php echo wp_get_attachment_image( $adam_image_id, 'large', false, array( 'loading' => 'lazy' ) ); ?></a>
			<?php endforeach; ?>
		</div></section><?php endif; ?>

		<?php if ( $adam_public_links ) : ?><section class="adam-team-section"><h2><?php esc_html_e( 'Presença online', 'adam-comunidade' ); ?></h2><div class="adam-team-contact-buttons">
			<?php foreach ( $adam_public_links as $adam_link_key => $adam_link_url ) : ?><a href="<?php echo esc_url( $adam_link_url ); ?>" class="adam-team-button" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $adam_link_labels[ $adam_link_key ] ?? ucfirst( $adam_link_key ) ); ?></a><?php endforeach; ?>
		</div></section><?php endif; ?>

		<?php if ( $adam_team->address || $adam_team->maps_url || ( $adam_team->latitude && $adam_team->longitude ) ) : ?>
			<section class="adam-team-section">
				<h2><?php esc_html_e( 'Localização', 'adam-comunidade' ); ?></h2>
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
							title="<?php esc_attr_e( 'Mapa da localização da equipa', 'adam-comunidade' ); ?>"
							loading="lazy"
							src="<?php echo esc_url( $adam_map_url ); ?>"
						></iframe>
					</div>
				<?php endif; ?>
				<?php if ( $adam_team->maps_url ) : ?>
					<a class="adam-team-text-link" href="<?php echo esc_url( $adam_team->maps_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Abrir no Google Maps', 'adam-comunidade' ); ?>
					</a>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $adam_associated_fields ) : ?><section class="adam-team-section adam-associated-fields">
			<h2><?php esc_html_e( 'Campos Associados', 'adam-comunidade' ); ?></h2>
			<div class="adam-field-grid">
				<?php foreach ( $adam_associated_fields as $adam_associated_field ) : ?>
					<?php echo Field_View::card( $adam_associated_field, $adam_field_repository ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</section><?php endif; ?>
		<?php do_action( 'adam_comunidade_team_after_content', $adam_team ); ?>
	</div>
	<?php if ( $adam_gallery ) : ?><div class="adam-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Visualizador de imagens', 'adam-comunidade' ); ?>" hidden><button type="button" aria-label="<?php esc_attr_e( 'Fechar visualizador de imagens', 'adam-comunidade' ); ?>">&times;</button><img src="" alt=""></div><?php endif; ?>
</main>
<?php
get_footer();
