<?php
/**
 * Community News archive.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main class="adam-experience adam-news-archive" id="main"><div class="adam-experience-container"><header class="adam-community-header"><span><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span><h1><?php esc_html_e( 'Notícias', 'adam-comunidade' ); ?></h1></header><div class="adam-news-grid"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?><?php $adam_news_archive_image = \ADAM\Comunidade\Experience\News::archive_image( get_the_ID() ); ?><article class="adam-news-card"><a class="adam-news-card__link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>"><?php if ( $adam_news_archive_image['has_image'] ) : ?><?php echo $adam_news_archive_image['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?><div><time datetime="<?php echo esc_attr( get_the_date( DATE_ATOM ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time><h2><?php the_title(); ?></h2><?php if ( ! $adam_news_archive_image['has_image'] ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?></div></a></article><?php endwhile; else : ?><div class="adam-comunidade__empty"><?php esc_html_e( 'Ainda não foram publicadas notícias da comunidade.', 'adam-comunidade' ); ?></div><?php endif; ?></div><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => __( '← Anterior', 'adam-comunidade' ), 'next_text' => __( 'Seguinte →', 'adam-comunidade' ), 'screen_reader_text' => __( 'Paginação das notícias', 'adam-comunidade' ) ) ); ?></div></main>
<?php get_footer(); ?>
