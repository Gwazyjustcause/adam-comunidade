<?php
/**
 * Shared privacy-first directory entry page.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Relationship_Repository;
use ADAM\Comunidade\Directory\Repository;
use ADAM\Comunidade\Directory\Router;
use ADAM\Comunidade\Directory\Types;
use ADAM\Comunidade\Directory\View;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Public_Hero;
use ADAM\Comunidade\Public_Privacy;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;

$entry      = Router::current_entry();
$definition = Types::get( $entry->entity_type );
$repository = new Repository();
$gallery    = array_values(
	array_filter(
		$repository->gallery( (int) $entry->id ),
		static fn( object $image ): bool => wp_attachment_is_image( (int) $image->attachment_id )
			&& (bool) wp_get_attachment_image_url( (int) $image->attachment_id, 'full' )
	)
);
$public_links          = Public_Privacy::public_links( $entry );
$has_description       = '' !== trim( (string) $entry->short_description ) || '' !== trim( wp_strip_all_tags( (string) $entry->full_description ) );
$has_benefits          = 'partner' === $entry->entity_type && '' !== trim( wp_strip_all_tags( (string) $entry->benefits ) );
$has_brand_information = 'partner' === $entry->entity_type && 'brand' === $entry->category
	&& ( ! empty( $entry->official_distributor ) || '' !== trim( wp_strip_all_tags( (string) $entry->popular_products ) ) );
$has_notes             = 'institution' === $entry->entity_type && '' !== trim( wp_strip_all_tags( (string) $entry->notes ) );
$has_location          = '' !== trim( (string) $entry->address )
	|| ( null !== $entry->latitude && null !== $entry->longitude );

$relationships = ( new Relationship_Repository() )->connected( $entry->entity_type, (int) $entry->id );
$connected     = array();
foreach ( $relationships as $relationship ) {
	$outgoing    = $relationship->source_type === $entry->entity_type && (int) $relationship->source_id === (int) $entry->id;
	$target_type = $outgoing ? $relationship->target_type : $relationship->source_type;
	$target_id   = $outgoing ? (int) $relationship->target_id : (int) $relationship->source_id;
	if ( Types::get( $target_type ) ) {
		$target = $repository->find( $target_id, $target_type );
		$url    = $target ? Router::entry_url( $target ) : '';
	} elseif ( 'team' === $target_type ) {
		$target = ( new Team_Repository() )->find( $target_id );
		$url    = $target ? Team_Router::team_url( $target ) : '';
	} elseif ( 'field' === $target_type ) {
		$target = ( new Field_Repository() )->find( $target_id );
		$url    = $target ? Field_Router::field_url( $target ) : '';
	} else {
		$target = null;
		$url    = '';
	}
	if ( $target && 'published' === $target->status ) {
		$connected[] = array( 'item' => $target, 'type' => $target_type, 'url' => $url, 'relation' => $relationship->relation );
	}
}

$downloads = array_filter(
	array(
		array( __( 'PDF promocional', 'adam-comunidade' ), $entry->promo_pdf_id ? wp_get_attachment_url( $entry->promo_pdf_id ) : '' ),
		array( __( 'Descarregar catálogo', 'adam-comunidade' ), $entry->catalogue_id ? wp_get_attachment_url( $entry->catalogue_id ) : '' ),
	),
	static fn( array $download ): bool => (bool) $download[1]
);

get_header();
?>
<main class="adam-community adam-community-single" id="main" data-entity-type="<?php echo esc_attr( $entry->entity_type ); ?>" data-entity-id="<?php echo esc_attr( $entry->id ); ?>">
	<header class="<?php echo esc_attr( Public_Hero::root( 'adam-community-hero' ) ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'media', 'adam-community-hero__cover' ) ); ?>"><?php echo $entry->cover_id ? wp_get_attachment_image( (int) $entry->cover_id, 'adam-directory-cover', false, array( 'fetchpriority' => 'high' ) ) : Placeholder_Image::cover( (string) $entry->entity_type, (string) $entry->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-community-container adam-community-hero__content' ) ); ?>">
			<?php if ( $entry->verification ) : ?><span class="adam-community-badge adam-badge--verified"><?php echo esc_html( View::verification_label( (string) $entry->verification ) ); ?></span><?php endif; ?>
			<?php echo $entry->logo_id ? wp_get_attachment_image( (int) $entry->logo_id, 'adam-directory-logo', false, array( 'class' => 'adam-community-hero__logo' ) ) : Placeholder_Image::avatar( (string) $entry->entity_type, (string) $entry->name, 'adam-community-hero__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div>
				<?php if ( $entry->featured ) : ?><span class="adam-community-badge adam-public-hero__badge"><?php esc_html_e( 'Em destaque', 'adam-comunidade' ); ?></span><?php endif; ?>
				<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $entry->name ); ?></h1>
				<?php $hero_meta = implode( ' · ', array_filter( array( $definition['categories'][ $entry->category ] ?? '', $entry->district, $entry->country ) ) ); ?>
				<?php if ( $hero_meta ) : ?><p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php echo esc_html( $hero_meta ); ?></p><?php endif; ?>
			</div>
		</div>
	</header>

	<div class="adam-community-container adam-community-content">
		<?php if ( $has_description ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Sobre', 'adam-comunidade' ); ?></h2><?php if ( $entry->short_description ) : ?><p class="adam-community-lead"><?php echo esc_html( $entry->short_description ); ?></p><?php endif; ?><?php echo wp_kses_post( wpautop( $entry->full_description ) ); ?></section><?php endif; ?>

		<?php if ( $has_benefits ) : ?><section class="adam-community-section adam-community-benefits"><h2><?php esc_html_e( 'Benefícios para membros', 'adam-comunidade' ); ?></h2><?php echo wp_kses_post( wpautop( $entry->benefits ) ); ?></section><?php endif; ?>

		<?php if ( $has_brand_information ) : ?><section class="adam-community-section">
			<h2><?php esc_html_e( 'Informação da marca', 'adam-comunidade' ); ?></h2>
			<?php if ( $entry->official_distributor ) : ?><span class="adam-community-badge"><?php esc_html_e( 'Distribuidor oficial', 'adam-comunidade' ); ?></span><?php endif; ?>
			<?php if ( trim( wp_strip_all_tags( (string) $entry->popular_products ) ) ) : ?><h3><?php esc_html_e( 'Produtos populares', 'adam-comunidade' ); ?></h3><?php echo wp_kses_post( wpautop( $entry->popular_products ) ); ?><?php endif; ?>
		</section><?php endif; ?>

		<?php if ( $has_notes ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Notas', 'adam-comunidade' ); ?></h2><?php echo wp_kses_post( wpautop( $entry->notes ) ); ?></section><?php endif; ?>

		<?php if ( $gallery ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Fotografias', 'adam-comunidade' ); ?></h2><div class="adam-community-gallery"><?php foreach ( $gallery as $image ) : ?><a href="<?php echo esc_url( wp_get_attachment_image_url( $image->attachment_id, 'full' ) ); ?>" data-directory-lightbox data-caption="<?php echo esc_attr( $image->caption ); ?>"><?php echo wp_get_attachment_image( $image->attachment_id, 'adam-directory-gallery', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endforeach; ?></div></section><?php endif; ?>

		<?php if ( $public_links ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Presença online', 'adam-comunidade' ); ?></h2><div class="adam-contact-grid"><?php echo View::public_links( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>

		<?php if ( $has_location ) : ?><section class="adam-community-section">
			<h2><?php esc_html_e( 'Localização', 'adam-comunidade' ); ?></h2>
			<?php if ( $entry->address ) : ?><p><?php echo esc_html( $entry->address ); ?></p><?php endif; ?>
			<?php if ( null !== $entry->latitude && null !== $entry->longitude ) : ?><iframe class="adam-community-map-frame" title="<?php esc_attr_e( 'Mapa da localização', 'adam-comunidade' ); ?>" loading="lazy" src="<?php echo esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . ( (float) $entry->longitude - 0.01 ) . ',' . ( (float) $entry->latitude - 0.01 ) . ',' . ( (float) $entry->longitude + 0.01 ) . ',' . ( (float) $entry->latitude + 0.01 ) . '&layer=mapnik&marker=' . $entry->latitude . ',' . $entry->longitude ); ?>"></iframe><?php endif; ?>
		</section><?php endif; ?>

		<?php if ( $downloads ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Documentos', 'adam-comunidade' ); ?></h2><div class="adam-contact-grid"><?php foreach ( $downloads as $download ) : ?><a class="adam-contact-button" href="<?php echo esc_url( $download[1] ); ?>" download>↓ <?php echo esc_html( $download[0] ); ?></a><?php endforeach; ?></div></section><?php endif; ?>

		<?php if ( $connected ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Ligações à comunidade', 'adam-comunidade' ); ?></h2><div class="adam-community-connections"><?php foreach ( $connected as $connection ) : ?><a href="<?php echo esc_url( $connection['url'] ); ?>"><strong><?php echo esc_html( $connection['item']->name ); ?></strong><span><?php echo esc_html( View::type_label( (string) $connection['type'] ) ); ?></span></a><?php endforeach; ?></div></section><?php endif; ?>

		<?php do_action( 'adam_comunidade_directory_entry_content', $entry ); ?>
	</div>
	<?php if ( $gallery ) : ?><div class="adam-community-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Visualizador de imagens', 'adam-comunidade' ); ?>" hidden><button type="button" aria-label="<?php esc_attr_e( 'Fechar visualizador de imagens', 'adam-comunidade' ); ?>">×</button><figure><img src="" alt=""><figcaption></figcaption></figure></div><?php endif; ?>
</main>
<?php get_footer(); ?>
