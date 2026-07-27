<?php
/**
 * Community News archive.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="adam-experience" id="main"><div class="adam-experience-container"><header class="adam-community-header"><span><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span><h1><?php esc_html_e( 'Notícias', 'adam-comunidade' ); ?></h1></header><div class="adam-news-grid"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?><article class="adam-news-card"><?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?><div><time datetime="<?php echo esc_attr( get_the_date( DATE_ATOM ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p><?php echo esc_html( get_the_excerpt() ); ?></p></div></article><?php endwhile; else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'Ainda não foram publicadas notícias da comunidade.', 'adam-comunidade' ); ?></div><?php endif; ?></div><?php the_posts_pagination( array( 'prev_text' => __( 'Anterior', 'adam-comunidade' ), 'next_text' => __( 'Seguinte', 'adam-comunidade' ), 'screen_reader_text' => __( 'Paginação das notícias', 'adam-comunidade' ) ) ); ?></div></main>
<?php get_footer(); ?>
