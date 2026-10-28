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
			<div><p class="adam-community-hub__eyebrow"><?php esc_html_e( 'O próximo ponto pode ser o teu', 'adam-comunidade' ); ?></p><h2><?php esc_html_e( 'A tua equipa já está no mapa?', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Registar a tua equipa é gratuito. A ADAM revê cada submissão antes da publicação.', 'adam-comunidade' ); ?></p></div>
			<div class="adam-community-hub__actions"><a class="adam-community-hub__button" href="<?php echo esc_url( Portal::submission_url( 'team' ) ); ?>"><?php esc_html_e( 'Adicionar equipa', 'adam-comunidade' ); ?> <span aria-hidden="true">+</span></a><a class="adam-community-hub__button adam-community-hub__button--outline" href="<?php echo esc_url( Portal::submission_url( 'field' ) ); ?>"><?php esc_html_e( 'Adicionar campo', 'adam-comunidade' ); ?> <span aria-hidden="true">+</span></a></div>
		</section>
		<section class="adam-community-hub__mission" aria-labelledby="adam-community-mission-title">
			<div><p class="adam-community-hub__eyebrow"><?php esc_html_e( 'Uma iniciativa gratuita', 'adam-comunidade' ); ?></p><h2 id="adam-community-mission-title"><?php esc_html_e( 'Onde jogar. Com quem jogar.', 'adam-comunidade' ); ?></h2></div>
			<div><p><?php esc_html_e( 'Queremos dar visibilidade às equipas do Centro de Portugal e aproximar quem partilha a mesma vontade de jogar.', 'adam-comunidade' ); ?></p><p><?php esc_html_e( 'Submete a tua equipa no nosso site, sem custos. Cada equipa que se junta ajuda a construir um ponto de encontro onde, cada vez mais, será possível descobrir onde jogar e com quem jogar na região.', 'adam-comunidade' ); ?></p></div>
		</section>
	</div>
</main>
<?php get_footer(); ?>
