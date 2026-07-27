<?php
/**
 * Community comparison tool.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Fields\Options as Field_Options;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Teams\Options as Team_Options;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

$type = sanitize_key( (string) filter_input( INPUT_GET, 'type' ) );
$type = in_array( $type, array( 'team', 'field', 'brand' ), true ) ? $type : 'field';
$raw_ids = isset( $_GET['ids'] ) ? wp_unslash( $_GET['ids'] ) : array();
$raw_ids = is_array( $raw_ids ) ? $raw_ids : explode( ',', sanitize_text_field( (string) $raw_ids ) );
$ids = array_slice( array_filter( array_map( 'absint', $raw_ids ) ), 0, 3 );
$repository = 'team' === $type ? new Team_Repository() : ( 'field' === $type ? new Field_Repository() : new Directory_Repository() );
$choices = 'brand' === $type ? $repository->choices( 'brand' ) : $repository->choices( 'published' );
$items = array();
$playing_style_labels = array_merge( Field_Options::playing_styles(), Team_Options::playing_styles() );
foreach ( $ids as $id ) {
	$item = 'brand' === $type ? $repository->find( $id, 'brand' ) : $repository->find( $id );
	if ( $item && 'published' === $item->status ) {
		$items[] = $item;
	}
}
$rows = array(
	__( 'Localização', 'adam-comunidade' ) => static fn( object $item ): string => implode( ', ', array_filter( array( $item->municipality ?? '', $item->district ?? '', $item->country ?? '' ) ) ),
	__( 'Descrição', 'adam-comunidade' ) => static fn( object $item ): string => $item->short_description ?? '',
	__( 'Membros', 'adam-comunidade' ) => static fn( object $item ): string => isset( $item->members ) ? (string) $item->members : '—',
	__( 'Capacidade', 'adam-comunidade' ) => static fn( object $item ): string => isset( $item->max_players ) ? (string) $item->max_players : '—',
	__( 'Estilos de jogo', 'adam-comunidade' ) => static function ( object $item ) use ( $playing_style_labels ): string {
		$styles = json_decode( $item->playing_styles ?? '[]', true ) ?: array();
		return implode( ', ', array_map( static fn( string $style ): string => $playing_style_labels[ $style ] ?? $style, $styles ) );
	},
	__( 'Página Web', 'adam-comunidade' ) => static fn( object $item ): string => $item->website ?? '',
);
get_header();
?>
<main class="adam-experience" id="main"><div class="adam-experience-container"><header class="adam-community-header"><span><?php esc_html_e( 'Ferramenta da Comunidade', 'adam-comunidade' ); ?></span><h1><?php esc_html_e( 'Comparar', 'adam-comunidade' ); ?></h1></header>
	<form class="adam-compare-picker" method="get"><label><?php esc_html_e( 'Tipo', 'adam-comunidade' ); ?><select name="type" onchange="this.form.submit()"><option value="field" <?php selected( $type, 'field' ); ?>><?php esc_html_e( 'Campos', 'adam-comunidade' ); ?></option><option value="team" <?php selected( $type, 'team' ); ?>><?php esc_html_e( 'Equipas', 'adam-comunidade' ); ?></option><option value="brand" <?php selected( $type, 'brand' ); ?>><?php esc_html_e( 'Marcas', 'adam-comunidade' ); ?></option></select></label><label><?php esc_html_e( 'Escolha até três', 'adam-comunidade' ); ?><select name="ids[]" multiple size="8"><?php foreach ( $choices as $choice ) : ?><option value="<?php echo esc_attr( $choice->id ); ?>" <?php selected( in_array( (int) $choice->id, $ids, true ) ); ?>><?php echo esc_html( $choice->name ); ?></option><?php endforeach; ?></select></label><button class="adam-community-button"><?php esc_html_e( 'Comparar', 'adam-comunidade' ); ?></button></form>
	<?php if ( $items ) : ?><div class="adam-comparison" style="--columns:<?php echo esc_attr( count( $items ) ); ?>"><div class="adam-comparison__label"></div><?php foreach ( $items as $item ) : ?><h2><?php echo esc_html( $item->name ); ?></h2><?php endforeach; ?><?php foreach ( $rows as $label => $callback ) : ?><strong class="adam-comparison__label"><?php echo esc_html( $label ); ?></strong><?php foreach ( $items as $item ) : ?><div><?php echo esc_html( $callback( $item ) ?: '—' ); ?></div><?php endforeach; ?><?php endforeach; ?></div><?php else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'Selecione dois ou três registos para os comparar lado a lado.', 'adam-comunidade' ); ?></div><?php endif; ?>
</div></main>
<?php get_footer(); ?>
