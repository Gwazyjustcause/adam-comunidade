<?php
/**
 * Central community hub.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\Types;
use ADAM\Comunidade\Experience\Builder;
use ADAM\Comunidade\Experience\Discovery;
use ADAM\Comunidade\Experience\Smart_Blocks;
use ADAM\Comunidade\Fields\Amenity_Repository;
use ADAM\Comunidade\Fields\Options as Field_Options;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Public_Hero;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

$discovery = new Discovery( new Team_Repository(), new Field_Repository(), new Directory_Repository() );
$markers   = $discovery->map_records();
$amenities = ( new Amenity_Repository() )->all( 'field', true );
$partner_types = Types::get( 'partner' )['categories'];
$institution_types = Types::get( 'institution' )['categories'];

get_header();
?>
<main class="adam-experience" id="main">
	<header class="<?php echo esc_attr( Public_Hero::root( 'adam-experience-hero', 'archive' ) ); ?>"><div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-experience-container' ) ); ?>"><span class="<?php echo esc_attr( Public_Hero::element( 'kicker' ) ); ?>"><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span><h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( get_the_title( Managed_Pages::id( 'community' ) ) ); ?></h1><p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php esc_html_e( 'Explore, compare, descubra e ligue-se à comunidade portuguesa de airsoft.', 'adam-comunidade' ); ?></p></div></header>
	<div class="adam-experience-container">
		<?php echo Builder::search_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo ( new Smart_Blocks( $discovery ) )->statistics(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<section class="adam-advanced-map" data-adam-advanced-map data-markers="<?php echo esc_attr( wp_json_encode( $markers ) ); ?>">
			<div class="adam-section-title"><div><span><?php esc_html_e( 'Diretório em direto', 'adam-comunidade' ); ?></span><h2><?php esc_html_e( 'Mapa da Comunidade', 'adam-comunidade' ); ?></h2></div><a class="adam-community-button adam-community-button--ghost" href="<?php echo esc_url( trailingslashit( Managed_Pages::url( 'community' ) ) . 'comparar/' ); ?>"><?php esc_html_e( 'Comparar registos', 'adam-comunidade' ); ?></a></div>
			<form class="adam-map-filters" data-adam-map-filters>
				<label><?php esc_html_e( 'Distrito', 'adam-comunidade' ); ?><input name="district" type="text"></label>
				<label><?php esc_html_e( 'Concelho', 'adam-comunidade' ); ?><input name="municipality" type="text"></label>
				<label><?php esc_html_e( 'Estilo de jogo', 'adam-comunidade' ); ?><select name="playing_style"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( Field_Options::playing_styles() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Comodidade', 'adam-comunidade' ); ?><select name="facility"><option value=""><?php esc_html_e( 'Todas', 'adam-comunidade' ); ?></option><?php foreach ( $amenities as $amenity ) : ?><option value="<?php echo esc_attr( $amenity->id ); ?>"><?php echo esc_html( $amenity->label ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Categoria de parceiro', 'adam-comunidade' ); ?><select name="partner_category"><option value=""><?php esc_html_e( 'Todas', 'adam-comunidade' ); ?></option><?php foreach ( $partner_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Tipo de instituição', 'adam-comunidade' ); ?><select name="institution_category"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( $institution_types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			</form>
			<div class="adam-map-layout"><div class="adam-map-canvas" data-adam-map-canvas role="region" aria-label="<?php esc_attr_e( 'Mapa interativo da comunidade', 'adam-comunidade' ); ?>"></div><div class="adam-map-results" data-adam-map-results aria-live="polite"></div></div>
		</section>

		<?php echo do_shortcode( '[adam_community_section type="teams" number="6" order="newest"]' ); ?>
		<?php echo do_shortcode( '[adam_community_section type="fields" number="6" order="newest"]' ); ?>
		<?php echo do_shortcode( '[adam_community_section type="partners" number="6" order="priority"]' ); ?>
		<?php echo do_shortcode( '[adam_community_section type="institutions" number="6" order="newest"]' ); ?>
		<?php echo Builder::news_cards( 6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo Builder::event_cards( 6 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</main>
<?php get_footer(); ?>
