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
use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Public_Hero;

$type       = Router::current_type();
$definition = Types::get( $type );
$repository = new Repository();
$search     = sanitize_text_field( (string) filter_input( INPUT_GET, 'search' ) );
$district   = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
$category   = sanitize_key( (string) filter_input( INPUT_GET, 'category' ) );
$featured   = filter_input( INPUT_GET, 'featured', FILTER_VALIDATE_BOOL ) ? 1 : '';
$sort       = sanitize_key( (string) filter_input( INPUT_GET, 'sort' ) ) ?: 'alphabetical';
$page       = max( 1, absint( filter_input( INPUT_GET, 'pagina', FILTER_VALIDATE_INT ) ?: 1 ) );
$sorts      = array( 'alphabetical' => array( 'name', 'ASC' ), 'newest' => array( 'created_at', 'DESC' ), 'priority' => array( 'priority', 'DESC' ) );
$selected_sort = $sorts[ $sort ] ?? $sorts['alphabetical'];
$result     = $repository->query( $type, array( 'status' => 'published', 'search' => $search, 'district' => $district, 'category' => $category, 'featured' => $featured, 'orderby' => $selected_sort[0], 'order' => $selected_sort[1], 'page' => $page, 'per_page' => 12 ) );
$directory_title = get_the_title( Managed_Pages::id( (string) $definition['module_id'] ) ) ?: (string) $definition['plural'];

get_header();
?>
<main class="adam-community adam-community-archive" id="main" data-directory-type="<?php echo esc_attr( $type ); ?>">
	<header class="<?php echo esc_attr( Public_Hero::root( 'adam-community-header', 'archive' ) ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'media', 'adam-community-header__media' ) ); ?>">
			<?php echo Placeholder_Image::cover( (string) $type, (string) $directory_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-community-container' ) ); ?>">
			<span class="<?php echo esc_attr( Public_Hero::element( 'kicker' ) ); ?>"><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span>
			<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $directory_title ); ?></h1>
			<p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php esc_html_e( 'Descubra as organizações que colaboram com a comunidade ADAM e a apoiam.', 'adam-comunidade' ); ?></p>
		</div>
	</header>
	<div class="adam-community-container">
		<form class="adam-community-filters" data-directory-filters method="get">
			<label><span><?php esc_html_e( 'Pesquisar', 'adam-comunidade' ); ?></span><input type="search" name="search" value="<?php echo esc_attr( $search ); ?>"></label>
			<?php if ( $definition['categories'] ) : ?><label><span><?php echo esc_html( 'institution' === $type ? __( 'Tipo', 'adam-comunidade' ) : __( 'Categoria', 'adam-comunidade' ) ); ?></span><select name="category"><option value=""><?php esc_html_e( 'Todas', 'adam-comunidade' ); ?></option><?php foreach ( $definition['categories'] as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $category, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><?php endif; ?>
			<label><span><?php esc_html_e( 'Distrito', 'adam-comunidade' ); ?></span><select name="district"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( $repository->distinct( $type, 'district' ) as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $district, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Ordenar', 'adam-comunidade' ); ?></span><select name="sort"><option value="alphabetical" <?php selected( $sort, 'alphabetical' ); ?>><?php esc_html_e( 'Ordem alfabética', 'adam-comunidade' ); ?></option><option value="newest" <?php selected( $sort, 'newest' ); ?>><?php esc_html_e( 'Mais recentes', 'adam-comunidade' ); ?></option><option value="priority" <?php selected( $sort, 'priority' ); ?>><?php esc_html_e( 'Prioridade', 'adam-comunidade' ); ?></option></select></label>
			<label class="adam-community-check"><input type="checkbox" name="featured" value="1" <?php checked( $featured ); ?>> <span><?php esc_html_e( 'Apenas em destaque', 'adam-comunidade' ); ?></span></label>
			<button class="adam-community-button" type="submit"><?php esc_html_e( 'Aplicar filtros', 'adam-comunidade' ); ?></button>
		</form>
		<p><strong data-directory-total><?php echo esc_html( (string) $result['total'] ); ?></strong> <?php esc_html_e( 'resultados', 'adam-comunidade' ); ?></p>
		<div class="adam-community-grid" data-directory-results aria-live="polite">
			<?php if ( $result['items'] ) : foreach ( $result['items'] as $entry ) : echo View::card( $entry ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'Ainda não existe conteúdo publicado.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</div>
		<div data-directory-pagination><?php echo View::pagination( $page, $result['pages'], array_filter( array( 'search' => $search, 'district' => $district, 'category' => $category, 'featured' => $featured, 'sort' => $sort ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</main>
<?php get_footer(); ?>
