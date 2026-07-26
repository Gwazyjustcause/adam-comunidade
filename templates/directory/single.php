<?php
/**
 * Shared directory entry page.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Public_Hero;
use ADAM\Comunidade\Directory\Relationship_Repository;
use ADAM\Comunidade\Directory\Repository;
use ADAM\Comunidade\Directory\Router;
use ADAM\Comunidade\Directory\Types;
use ADAM\Comunidade\Directory\View;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Router as Field_Router;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Router as Team_Router;

$entry         = Router::current_entry();
$definition    = Types::get( $entry->entity_type );
$repository    = new Repository();
$gallery       = $repository->gallery( (int) $entry->id );
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
		array( __( 'Promotional PDF', 'adam-comunidade' ), $entry->promo_pdf_id ? wp_get_attachment_url( $entry->promo_pdf_id ) : '' ),
		array( __( 'Download catalogue', 'adam-comunidade' ), $entry->catalogue_id ? wp_get_attachment_url( $entry->catalogue_id ) : '' ),
	),
	static fn( array $download ): bool => (bool) $download[1]
);

get_header();
?>
<main class="adam-community adam-community-single" id="main" data-entity-type="<?php echo esc_attr( $entry->entity_type ); ?>" data-entity-id="<?php echo esc_attr( $entry->id ); ?>">
	<header class="<?php echo esc_attr( Public_Hero::root( 'adam-community-hero' ) ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'media', 'adam-community-hero__cover' ) ); ?>"><?php echo $entry->cover_id ? wp_get_attachment_image( (int) $entry->cover_id, 'adam-directory-cover', false, array( 'fetchpriority' => 'high' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-community-container adam-community-hero__content' ) ); ?>">
			<?php if ( $entry->verification ) : ?><span class="adam-community-badge adam-badge--verified"><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry->verification ) ) ); ?></span><?php endif; ?>
			<?php echo $entry->logo_id ? wp_get_attachment_image( (int) $entry->logo_id, 'adam-directory-logo', false, array( 'class' => 'adam-community-hero__logo' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div><?php if ( $entry->featured ) : ?><span class="adam-community-badge adam-public-hero__badge"><?php esc_html_e( 'Featured', 'adam-comunidade' ); ?></span><?php endif; ?><h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $entry->name ); ?></h1><p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php echo esc_html( implode( ' · ', array_filter( array( $definition['categories'][ $entry->category ] ?? '', $entry->district, $entry->country ) ) ) ); ?></p></div>
		</div>
	</header>
	<div class="adam-community-container adam-community-content">
		<?php if ( $entry->short_description || $entry->full_description ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'About', 'adam-comunidade' ); ?></h2><?php if ( $entry->short_description ) : ?><p class="adam-community-lead"><?php echo esc_html( $entry->short_description ); ?></p><?php endif; ?><?php echo wp_kses_post( wpautop( $entry->full_description ) ); ?></section><?php endif; ?>

		<?php if ( 'partner' === $entry->entity_type && $entry->benefits ) : ?><section class="adam-community-section adam-community-benefits"><h2><?php esc_html_e( 'Member Benefits', 'adam-comunidade' ); ?></h2><?php echo wp_kses_post( wpautop( $entry->benefits ) ); ?></section><?php endif; ?>
		<?php if ( 'brand' === $entry->entity_type && ( $entry->popular_products || $entry->official_distributor ) ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Brand Information', 'adam-comunidade' ); ?></h2><?php if ( $entry->official_distributor ) : ?><span class="adam-community-badge"><?php esc_html_e( 'Official distributor', 'adam-comunidade' ); ?></span><?php endif; ?><?php if ( $entry->popular_products ) : ?><h3><?php esc_html_e( 'Popular products', 'adam-comunidade' ); ?></h3><?php echo wp_kses_post( wpautop( $entry->popular_products ) ); ?><?php endif; ?></section><?php endif; ?>
		<?php if ( 'institution' === $entry->entity_type && $entry->notes ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Notes', 'adam-comunidade' ); ?></h2><?php echo wp_kses_post( wpautop( $entry->notes ) ); ?></section><?php endif; ?>

		<?php if ( $gallery ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Gallery', 'adam-comunidade' ); ?></h2><div class="adam-community-gallery"><?php foreach ( $gallery as $image ) : ?><a href="<?php echo esc_url( wp_get_attachment_image_url( $image->attachment_id, 'full' ) ); ?>" data-directory-lightbox data-caption="<?php echo esc_attr( $image->caption ); ?>"><?php echo wp_get_attachment_image( $image->attachment_id, 'adam-directory-gallery', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a><?php endforeach; ?></div></section><?php endif; ?>

		<?php if ( $entry->website || $entry->facebook || $entry->instagram || $entry->email || $entry->phone || $entry->address ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Contact', 'adam-comunidade' ); ?></h2><div class="adam-contact-grid"><?php echo View::contacts( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>

		<?php if ( null !== $entry->latitude && null !== $entry->longitude ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Location', 'adam-comunidade' ); ?></h2><iframe class="adam-community-map-frame" title="<?php esc_attr_e( 'Location map', 'adam-comunidade' ); ?>" loading="lazy" src="<?php echo esc_url( 'https://www.openstreetmap.org/export/embed.html?bbox=' . ( (float) $entry->longitude - 0.01 ) . ',' . ( (float) $entry->latitude - 0.01 ) . ',' . ( (float) $entry->longitude + 0.01 ) . ',' . ( (float) $entry->latitude + 0.01 ) . '&layer=mapnik&marker=' . $entry->latitude . ',' . $entry->longitude ); ?>"></iframe></section><?php endif; ?>

		<?php if ( $downloads ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Downloads', 'adam-comunidade' ); ?></h2><div class="adam-contact-grid"><?php foreach ( $downloads as $download ) : ?><a class="adam-contact-button" href="<?php echo esc_url( $download[1] ); ?>" download>↓ <?php echo esc_html( $download[0] ); ?></a><?php endforeach; ?></div></section><?php endif; ?>

		<?php if ( $connected ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Community Connections', 'adam-comunidade' ); ?></h2><div class="adam-community-connections"><?php foreach ( $connected as $connection ) : ?><a href="<?php echo esc_url( $connection['url'] ); ?>"><strong><?php echo esc_html( $connection['item']->name ); ?></strong><span><?php echo esc_html( ucfirst( $connection['type'] ) ); ?></span></a><?php endforeach; ?></div></section><?php endif; ?>

		<?php do_action( 'adam_comunidade_directory_entry_content', $entry ); ?>
		<?php if ( 'partner' === $entry->entity_type ) : ?><section class="adam-community-section"><h2><?php esc_html_e( 'Sponsored Events', 'adam-comunidade' ); ?></h2><div class="adam-comunidade__empty"><?php esc_html_e( 'Event integration will become available in a future phase.', 'adam-comunidade' ); ?></div></section><?php endif; ?>
	</div>
	<div class="adam-community-lightbox" role="dialog" aria-modal="true" hidden><button type="button" aria-label="<?php esc_attr_e( 'Close', 'adam-comunidade' ); ?>">×</button><figure><img src="" alt=""><figcaption></figcaption></figure></div>
</main>
<?php get_footer(); ?>
