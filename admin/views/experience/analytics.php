<?php
/**
 * Analytics dashboard.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<div class="adam-admin-heading"><div><h1><?php esc_html_e( 'Community Analytics', 'adam-comunidade' ); ?></h1><p><?php esc_html_e( 'Aggregate, privacy-conscious activity. No visitor identities are stored.', 'adam-comunidade' ); ?></p></div><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adam_analytics_export' ), 'adam_analytics_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'adam-comunidade' ); ?></a></div>
	<div class="adam-dashboard-columns">
		<?php foreach ( array( __( 'Most Viewed Content', 'adam-comunidade' ) => $views, __( 'Popular Search Terms', 'adam-comunidade' ) => $searches, __( 'Most Searched Municipality', 'adam-comunidade' ) => $municipalities, __( 'Most Clicked Content', 'adam-comunidade' ) => $clicks, __( 'Homepage Widget Performance', 'adam-comunidade' ) => $widgets ) as $title => $rows ) : ?>
			<section class="adam-comunidade__card"><h2><?php echo esc_html( $title ); ?></h2><?php if ( $rows ) : ?><ol class="adam-analytics-bars"><?php $maximum = max( array_map( static fn( object $row ): int => (int) $row->total, $rows ) ); foreach ( $rows as $row ) : ?><li><span><?php echo esc_html( $row->dimension ?: $row->object_type . ' #' . $row->object_id ); ?></span><i style="--value:<?php echo esc_attr( (string) round( 100 * $row->total / $maximum ) ); ?>%"></i><strong><?php echo esc_html( (string) $row->total ); ?></strong></li><?php endforeach; ?></ol><?php else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'Analytics will appear as visitors use the directory.', 'adam-comunidade' ); ?></div><?php endif; ?></section>
		<?php endforeach; ?>
	</div>
</div>
