<?php
/**
 * Community News single.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<main class="adam-experience" id="main"><article class="adam-news-single"><header><?php the_post_thumbnail( 'adam-directory-cover', array( 'fetchpriority' => 'high' ) ); ?><div><time datetime="<?php echo esc_attr( get_the_date( DATE_ATOM ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><h1><?php the_title(); ?></h1><p><?php echo esc_html( get_the_excerpt() ); ?></p><span><?php echo esc_html( get_the_author() ); ?></span></div></header><div class="adam-news-single__content"><?php the_content(); ?></div></article></main>
	<?php
endwhile;
get_footer();
