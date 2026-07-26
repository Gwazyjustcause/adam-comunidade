<?php
/**
 * Community comparison tool.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

$type = sanitize_key( (string) filter_input( INPUT_GET, 'type' ) );
$type = in_array( $type, array( 'team', 'field', 'brand' ), true ) ? $type : 'field';
$raw_ids = isset( $_GET['ids'] ) ? wp_unslash( $_GET['ids'] ) : array();
$raw_ids = is_array( $raw_ids ) ? $raw_ids : explode( ',', sanitize_text_field( (string) $raw_ids ) );
$ids = array_slice( array_filter( array_map( 'absint', $raw_ids ) ), 0, 3 );
$repository = 'team' === $type ? new Team_Repository() : ( 'field' === $type ? new Field_Repository() : new Directory_Repository() );
$choices = 'brand' === $type ? $repository->choices( 'brand' ) : $repository->choices( 'published' );
$items = array();
foreach ( $ids as $id ) {
	$item = 'brand' === $type ? $repository->find( $id, 'brand' ) : $repository->find( $id );
	if ( $item && 'published' === $item->status ) {
		$items[] = $item;
	}
}
$rows = array(
	__( 'Location', 'adam-comunidade' ) => static fn( object $item ): string => implode( ', ', array_filter( array( $item->municipality ?? '', $item->district ?? '', $item->country ?? '' ) ) ),
	__( 'Description', 'adam-comunidade' ) => static fn( object $item ): string => $item->short_description ?? '',
	__( 'Members', 'adam-comunidade' ) => static fn( object $item ): string => isset( $item->members ) ? (string) $item->members : '—',
	__( 'Capacity', 'adam-comunidade' ) => static fn( object $item ): string => isset( $item->max_players ) ? (string) $item->max_players : '—',
	__( 'Playing Styles', 'adam-comunidade' ) => static fn( object $item ): string => implode( ', ', json_decode( $item->playing_styles ?? '[]', true ) ?: array() ),
	__( 'Website', 'adam-comunidade' ) => static fn( object $item ): string => $item->website ?? '',
);
get_header();
?>
<main class="adam-experience" id="main"><div class="adam-experience-container"><header class="adam-community-header"><span><?php esc_html_e( 'Community Tool', 'adam-comunidade' ); ?></span><h1><?php esc_html_e( 'Compare', 'adam-comunidade' ); ?></h1></header>
	<form class="adam-compare-picker" method="get"><label><?php esc_html_e( 'Type', 'adam-comunidade' ); ?><select name="type" onchange="this.form.submit()"><option value="field" <?php selected( $type, 'field' ); ?>><?php esc_html_e( 'Fields', 'adam-comunidade' ); ?></option><option value="team" <?php selected( $type, 'team' ); ?>><?php esc_html_e( 'Teams', 'adam-comunidade' ); ?></option><option value="brand" <?php selected( $type, 'brand' ); ?>><?php esc_html_e( 'Brands', 'adam-comunidade' ); ?></option></select></label><label><?php esc_html_e( 'Choose up to three', 'adam-comunidade' ); ?><select name="ids[]" multiple size="8"><?php foreach ( $choices as $choice ) : ?><option value="<?php echo esc_attr( $choice->id ); ?>" <?php selected( in_array( (int) $choice->id, $ids, true ) ); ?>><?php echo esc_html( $choice->name ); ?></option><?php endforeach; ?></select></label><button class="adam-community-button"><?php esc_html_e( 'Compare', 'adam-comunidade' ); ?></button></form>
	<?php if ( $items ) : ?><div class="adam-comparison" style="--columns:<?php echo esc_attr( count( $items ) ); ?>"><div class="adam-comparison__label"></div><?php foreach ( $items as $item ) : ?><h2><?php echo esc_html( $item->name ); ?></h2><?php endforeach; ?><?php foreach ( $rows as $label => $callback ) : ?><strong class="adam-comparison__label"><?php echo esc_html( $label ); ?></strong><?php foreach ( $items as $item ) : ?><div><?php echo esc_html( $callback( $item ) ?: '—' ); ?></div><?php endforeach; ?><?php endforeach; ?></div><?php else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'Select two or three items to compare side-by-side.', 'adam-comunidade' ); ?></div><?php endif; ?>
</div></main>
<?php get_footer(); ?>
