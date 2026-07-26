<?php
/**
 * Public community calendar.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Calendar;

$entries = Calendar::upcoming();
get_header();
?>
<main id="main" class="adam-experience"><div class="adam-experience-container">
	<header class="adam-section-title"><div><span><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span><h1><?php esc_html_e( 'Community Calendar', 'adam-comunidade' ); ?></h1></div></header>
	<div class="adam-community-grid">
	<?php if ( ! $entries ) : ?><div class="adam-empty-state"><?php esc_html_e( 'No upcoming community dates.', 'adam-comunidade' ); ?></div><?php endif; ?>
	<?php foreach ( $entries as $entry ) : ?><article class="adam-card"><span class="adam-badge"><?php echo esc_html( Calendar::types()[ $entry->entry_type ] ?? $entry->entry_type ); ?></span><h2><?php echo esc_html( $entry->title ); ?></h2><time datetime="<?php echo esc_attr( gmdate( DATE_ATOM, strtotime( $entry->start_at ) ) ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->start_at . ' UTC' ) ) ); ?></time><p><?php echo esc_html( $entry->summary ); ?></p></article><?php endforeach; ?>
	</div>
</div></main>
<?php get_footer(); ?>
