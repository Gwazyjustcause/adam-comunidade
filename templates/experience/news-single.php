<?php
/**
 * Community News single.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Public_Hero;

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<main class="adam-experience" id="main"><article class="adam-news-single"><header class="<?php echo esc_attr( Public_Hero::root( 'adam-news-hero' ) ); ?>"><?php the_post_thumbnail( 'adam-directory-cover', array( 'fetchpriority' => 'high', 'class' => Public_Hero::element( 'media' ) ) ); ?><div class="<?php echo esc_attr( Public_Hero::element( 'content' ) ); ?>"><time class="<?php echo esc_attr( Public_Hero::element( 'meta' ) ); ?>" datetime="<?php echo esc_attr( get_the_date( DATE_ATOM ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php the_title(); ?></h1><p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php echo esc_html( get_the_excerpt() ); ?></p><span class="<?php echo esc_attr( Public_Hero::element( 'meta' ) ); ?>"><?php echo esc_html( get_the_author() ); ?></span></div></header><div class="adam-news-single__content"><?php the_content(); ?></div></article></main>
	<?php
endwhile;
get_footer();
