<?php
/**
 * Public Fields archive.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Amenity_Repository;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Repository;
use ADAM\Comunidade\Fields\Router;
use ADAM\Comunidade\Fields\View;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

$repository = new Repository();
$query_search = sanitize_text_field( (string) filter_input( INPUT_GET, 'q' ) );
$query_district = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
$route_district = Router::archive_location();
$selected_district = $route_district ?: $query_district;
$result = $repository->query(
	array(
		'status'   => 'published',
		'search'   => $query_search,
		'district' => $selected_district,
		'orderby'  => 'name',
		'order'    => 'ASC',
		'per_page' => 12,
	)
);
$districts      = $repository->distinct( 'district', 'published' );
$municipalities = $repository->distinct( 'municipality', 'published' );
$amenities      = ( new Amenity_Repository() )->all( 'field', true );
$teams          = ( new Team_Repository() )->choices( 'published' );

get_header();
?>
<main class="adam-comunidade adam-fields-archive" id="main">
	<div class="adam-fields-container">
		<header class="adam-fields-header">
			<span><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span>
			<h1><?php echo $route_district ? esc_html( sprintf( __( 'Campos em %s', 'adam-comunidade' ), $route_district ) ) : esc_html__( 'Campos Associados', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Explore airsoft fields, facilities, rules, and directions.', 'adam-comunidade' ); ?></p>
		</header>

		<form class="adam-field-filters" id="adam-field-filters">
			<label><span><?php esc_html_e( 'Search', 'adam-comunidade' ); ?></span><input type="search" name="search" value="<?php echo esc_attr( $query_search ); ?>" placeholder="<?php esc_attr_e( 'Name, location, CQB…', 'adam-comunidade' ); ?>"></label>
			<label><span><?php esc_html_e( 'District', 'adam-comunidade' ); ?></span><select name="district"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( $districts as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_district, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Municipality', 'adam-comunidade' ); ?></span><select name="municipality"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( $municipalities as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Playing Style', 'adam-comunidade' ); ?></span><select name="playing_style"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( Options::playing_styles() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Facility', 'adam-comunidade' ); ?></span><select name="amenity_id"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( $amenities as $amenity ) : ?><option value="<?php echo esc_attr( (string) $amenity->id ); ?>"><?php echo esc_html( $amenity->label ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Associated Team', 'adam-comunidade' ); ?></span><select name="team_id"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( $teams as $team ) : ?><option value="<?php echo esc_attr( (string) $team->id ); ?>"><?php echo esc_html( $team->name ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Sort', 'adam-comunidade' ); ?></span><select name="sort"><option value="alphabetical"><?php esc_html_e( 'Alphabetical', 'adam-comunidade' ); ?></option><option value="newest"><?php esc_html_e( 'Newest', 'adam-comunidade' ); ?></option><option value="capacity"><?php esc_html_e( 'Largest Capacity', 'adam-comunidade' ); ?></option></select></label>
			<button class="adam-field-button" type="submit"><?php esc_html_e( 'Apply Filters', 'adam-comunidade' ); ?></button>
		</form>

		<p class="adam-field-results-count"><span id="adam-field-total"><?php echo esc_html( (string) $result['total'] ); ?></span> <?php esc_html_e( 'fields found', 'adam-comunidade' ); ?></p>
		<div class="adam-field-grid" id="adam-field-results" aria-live="polite">
			<?php if ( $result['items'] ) : foreach ( $result['items'] as $field ) : ?>
				<?php echo View::card( $field, $repository ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; else : ?>
				<div class="adam-comunidade__empty adam-fields-empty"><?php esc_html_e( 'No published fields are available.', 'adam-comunidade' ); ?></div>
			<?php endif; ?>
		</div>
		<div id="adam-field-pagination"><?php echo View::pagination( 1, $result['pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</main>
<?php get_footer(); ?>
