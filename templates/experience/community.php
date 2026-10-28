<?php
/** Map-first community discovery page. @package ADAM_Comunidade */
defined( 'ABSPATH' ) || exit;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Experience\Portal;
get_header();
?>
<main class="adam-community-hub" id="main">
	<div class="adam-community-hub__inner">
		<header class="adam-community-hub__intro">
			<div><p class="adam-community-hub__eyebrow"><?php esc_html_e( 'ADAM / Comunidade', 'adam-comunidade' ); ?></p><h1><?php esc_html_e( 'O airsoft do Centro.', 'adam-comunidade' ); ?><br><span><?php esc_html_e( 'Num só mapa.', 'adam-comunidade' ); ?></span></h1></div>
			<div class="adam-community-hub__lead"><p><?php esc_html_e( 'Encontra equipas, descobre campos e faz parte do próximo jogo.', 'adam-comunidade' ); ?></p><nav aria-label="<?php esc_attr_e( 'Diretórios da comunidade', 'adam-comunidade' ); ?>"><a href="<?php echo esc_url( Managed_Pages::url( 'teams' ) ); ?>"><?php esc_html_e( 'Ver equipas', 'adam-comunidade' ); ?> <span aria-hidden="true">↗</span></a><a href="<?php echo esc_url( Managed_Pages::url( 'fields' ) ); ?>"><?php esc_html_e( 'Ver campos', 'adam-comunidade' ); ?> <span aria-hidden="true">↗</span></a></nav></div>
		</header>
		<section class="adam-community-hub__explore" aria-label="<?php esc_attr_e( 'Explorar equipas e campos', 'adam-comunidade' ); ?>">
			<div class="adam-community-hub__map-heading"><h2><?php esc_html_e( 'Explora a região', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Aveiro · Viseu · Guarda · Coimbra · Castelo Branco · Leiria', 'adam-comunidade' ); ?></p></div>
			<?php echo do_shortcode( '[adam_community_map]' ); ?>
		</section>
		<section class="adam-community-hub__contribute">
			<div><p class="adam-community-hub__eyebrow"><?php esc_html_e( 'O próximo ponto pode ser o teu', 'adam-comunidade' ); ?></p><h2><?php esc_html_e( 'A tua equipa já está no mapa?', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Dá visibilidade ao teu projeto. A ADAM revê cada submissão antes da publicação.', 'adam-comunidade' ); ?></p></div>
			<div class="adam-community-hub__actions"><a class="adam-community-hub__button" href="<?php echo esc_url( Portal::submission_url( 'team' ) ); ?>"><?php esc_html_e( 'Adicionar equipa', 'adam-comunidade' ); ?> <span aria-hidden="true">+</span></a><a class="adam-community-hub__button adam-community-hub__button--outline" href="<?php echo esc_url( Portal::submission_url( 'field' ) ); ?>"><?php esc_html_e( 'Adicionar campo', 'adam-comunidade' ); ?> <span aria-hidden="true">+</span></a></div>
		</section>
		<nav class="adam-community-hub__network" aria-label="<?php esc_attr_e( 'Rede ADAM', 'adam-comunidade' ); ?>"><span><?php esc_html_e( 'Uma comunidade que vai além do campo.', 'adam-comunidade' ); ?></span><a href="<?php echo esc_url( Managed_Pages::url( 'partners' ) ); ?>"><?php esc_html_e( 'Parceiros', 'adam-comunidade' ); ?> <span aria-hidden="true">↗</span></a><a href="<?php echo esc_url( Managed_Pages::url( 'institutions' ) ); ?>"><?php esc_html_e( 'Instituições', 'adam-comunidade' ); ?> <span aria-hidden="true">↗</span></a></nav>
	</div>
</main>
<?php get_footer(); ?>
