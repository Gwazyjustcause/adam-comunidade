<?php
/**
 * Automatic district landing page.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\View as Directory_View;
use ADAM\Comunidade\Experience\Builder;
use ADAM\Comunidade\Experience\Discovery;
use ADAM\Comunidade\Experience\Smart_Blocks;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\View as Field_View;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\View as Team_View;

$region_slug = sanitize_title( (string) get_query_var( 'adam_region' ) );
$district = \ADAM\Comunidade\Experience\Router::regions()[ $region_slug ] ?? ucwords( str_replace( '-', ' ', $region_slug ) );
$teams_repo = new Team_Repository();
$fields_repo = new Field_Repository();
$directory_repo = new Directory_Repository();
$discovery = new Discovery( $teams_repo, $fields_repo, $directory_repo );
$teams = $teams_repo->query( array( 'status' => 'published', 'district' => $district, 'per_page' => 6 ) )['items'];
$recruiting_teams = $teams_repo->query( array( 'status' => 'published', 'district' => $district, 'recruitment' => 'recruiting', 'per_page' => 6 ) )['items'];
$fields = $fields_repo->query( array( 'status' => 'published', 'district' => $district, 'per_page' => 6 ) )['items'];
$featured_fields = $fields_repo->query( array( 'status' => 'published', 'district' => $district, 'featured' => 1, 'per_page' => 3 ) )['items'];
$partners = $directory_repo->query( 'partner', array( 'status' => 'published', 'district' => $district, 'per_page' => 6 ) )['items'];
$institutions = $directory_repo->query( 'institution', array( 'status' => 'published', 'district' => $district, 'per_page' => 6 ) )['items'];
$markers = $discovery->map_records( array( 'district' => $district ) );
$section = static fn( string $title, array $cards ): string => $cards ? '<section class="adam-community-widget"><h2>' . esc_html( $title ) . '</h2><div class="adam-community-grid">' . implode( '', $cards ) . '</div></section>' : '';

get_header();
?>
<main class="adam-experience" id="main">
	<header class="adam-experience-hero"><div class="adam-experience-container"><span><?php esc_html_e( 'Automatic Region', 'adam-comunidade' ); ?></span><h1><?php echo esc_html( $district ); ?></h1><p><?php echo esc_html( sprintf( __( 'The live ADAM community in %s.', 'adam-comunidade' ), $district ) ); ?></p></div></header>
	<div class="adam-experience-container">
		<?php echo ( new Smart_Blocks( $discovery ) )->statistics( $district ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<section class="adam-advanced-map" data-adam-advanced-map data-markers="<?php echo esc_attr( wp_json_encode( $markers ) ); ?>"><div class="adam-section-title"><h2><?php esc_html_e( 'Region Map', 'adam-comunidade' ); ?></h2></div><div class="adam-map-layout"><div class="adam-map-canvas" data-adam-map-canvas role="region" aria-label="<?php esc_attr_e( 'Region map', 'adam-comunidade' ); ?>"></div><div class="adam-map-results" data-adam-map-results></div></div></section>
		<?php echo $section( __( 'Teams', 'adam-comunidade' ), array_map( array( Team_View::class, 'card' ), $teams ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $section( __( 'Teams recruiting', 'adam-comunidade' ), array_map( array( Team_View::class, 'card' ), $recruiting_teams ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $section( __( 'Fields', 'adam-comunidade' ), array_map( static fn( object $item ): string => Field_View::card( $item, $fields_repo ), $fields ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $section( __( 'Featured fields', 'adam-comunidade' ), array_map( static fn( object $item ): string => Field_View::card( $item, $fields_repo ), $featured_fields ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $section( __( 'Partners', 'adam-comunidade' ), array_map( array( Directory_View::class, 'card' ), $partners ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $section( __( 'Institutions', 'adam-comunidade' ), array_map( array( Directory_View::class, 'card' ), $institutions ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo Builder::news_cards( 4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<section class="adam-community-widget"><h2><?php esc_html_e( 'Upcoming Events', 'adam-comunidade' ); ?></h2><div class="adam-comunidade__empty"><?php esc_html_e( 'Event integration will appear automatically when its module is enabled.', 'adam-comunidade' ); ?></div></section>
	</div>
</main>
<?php get_footer(); ?>
