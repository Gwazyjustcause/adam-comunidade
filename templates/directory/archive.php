<?php
/**
 * Shared Partners, Institutions, and Brands archive.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository;
use ADAM\Comunidade\Directory\Router;
use ADAM\Comunidade\Directory\Types;
use ADAM\Comunidade\Directory\View;
use ADAM\Comunidade\Managed_Pages;

$type       = Router::current_type();
$definition = Types::get( $type );
$repository = new Repository();
$search     = sanitize_text_field( (string) filter_input( INPUT_GET, 'q' ) );
$district   = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
$category   = sanitize_key( (string) filter_input( INPUT_GET, 'category' ) );
$featured   = filter_input( INPUT_GET, 'featured', FILTER_VALIDATE_BOOL ) ? 1 : '';
$result     = $repository->query( $type, array( 'status' => 'published', 'search' => $search, 'district' => $district, 'category' => $category, 'featured' => $featured, 'orderby' => 'name', 'order' => 'ASC', 'per_page' => 12 ) );

get_header();
?>
<main class="adam-community adam-community-archive" id="main" data-directory-type="<?php echo esc_attr( $type ); ?>">
	<div class="adam-community-container">
		<header class="adam-community-header">
			<span><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span>
			<h1><?php echo esc_html( get_the_title( Managed_Pages::id( (string) $definition['module_id'] ) ) ); ?></h1>
			<p><?php esc_html_e( 'Discover the organisations that collaborate with and support the ADAM community.', 'adam-comunidade' ); ?></p>
		</header>
		<form class="adam-community-filters" data-directory-filters>
			<label><span><?php esc_html_e( 'Search', 'adam-comunidade' ); ?></span><input type="search" name="search" value="<?php echo esc_attr( $search ); ?>"></label>
			<?php if ( $definition['categories'] ) : ?><label><span><?php echo esc_html( 'institution' === $type ? __( 'Type', 'adam-comunidade' ) : __( 'Category', 'adam-comunidade' ) ); ?></span><select name="category"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( $definition['categories'] as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><?php endif; ?>
			<?php if ( 'brand' !== $type ) : ?><label><span><?php esc_html_e( 'District', 'adam-comunidade' ); ?></span><select name="district"><option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option><?php foreach ( $repository->distinct( $type, 'district' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $district, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label><?php endif; ?>
			<label><span><?php esc_html_e( 'Sort', 'adam-comunidade' ); ?></span><select name="sort"><option value="alphabetical"><?php esc_html_e( 'Alphabetical', 'adam-comunidade' ); ?></option><option value="newest"><?php esc_html_e( 'Newest', 'adam-comunidade' ); ?></option><option value="priority"><?php esc_html_e( 'Priority', 'adam-comunidade' ); ?></option></select></label>
			<label class="adam-community-check"><input type="checkbox" name="featured" value="1" <?php checked( $featured ); ?>> <span><?php esc_html_e( 'Featured only', 'adam-comunidade' ); ?></span></label>
			<button class="adam-community-button" type="submit"><?php esc_html_e( 'Apply filters', 'adam-comunidade' ); ?></button>
		</form>
		<p><strong data-directory-total><?php echo esc_html( (string) $result['total'] ); ?></strong> <?php esc_html_e( 'results', 'adam-comunidade' ); ?></p>
		<div class="adam-community-grid" data-directory-results aria-live="polite">
			<?php if ( $result['items'] ) : foreach ( $result['items'] as $entry ) : echo View::card( $entry ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'No published content is available yet.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</div>
		<div data-directory-pagination><?php echo View::pagination( 1, $result['pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</main>
<?php get_footer(); ?>
