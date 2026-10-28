<?php
/** @package ADAM_Comunidade */
defined( 'ABSPATH' ) || exit;
use ADAM\Comunidade\Directory\Community_Map;
?>
<section class="adam-map-widget" id="<?php echo esc_attr( $id ); ?>" data-adam-map="<?php echo esc_attr( wp_json_encode( $markers ) ); ?>">
	<header><div><span><?php esc_html_e( 'Centro de Portugal', 'adam-comunidade' ); ?></span><h2><?php esc_html_e( 'Encontra o teu próximo jogo', 'adam-comunidade' ); ?></h2><p><?php echo esc_html( implode( ' · ', Community_Map::DISTRICTS ) ); ?></p></div></header>
	<form class="adam-map-filters" data-map-filters>
		<label><?php esc_html_e( 'Mostrar', 'adam-comunidade' ); ?><select name="type"><option value=""><?php esc_html_e( 'Equipas e campos', 'adam-comunidade' ); ?></option><option value="team"><?php esc_html_e( 'Equipas', 'adam-comunidade' ); ?></option><option value="field"><?php esc_html_e( 'Campos', 'adam-comunidade' ); ?></option></select></label>
		<label><?php esc_html_e( 'Distrito', 'adam-comunidade' ); ?><select name="district"><option value=""><?php esc_html_e( 'Todos os distritos', 'adam-comunidade' ); ?></option><?php foreach ( Community_Map::DISTRICTS as $district ) : ?><option value="<?php echo esc_attr( $district ); ?>"><?php echo esc_html( $district ); ?></option><?php endforeach; ?></select></label>
		<label><?php esc_html_e( 'Pesquisar', 'adam-comunidade' ); ?><input name="search" type="search" placeholder="<?php esc_attr_e( 'Nome ou concelho', 'adam-comunidade' ); ?>"></label>
		<button type="reset"><?php esc_html_e( 'Limpar filtros', 'adam-comunidade' ); ?></button>
		<button type="button" data-map-reset><?php esc_html_e( 'Ver toda a região', 'adam-comunidade' ); ?></button>
	</form>
	<div class="adam-map-legend"><span data-type="team"><?php esc_html_e( 'Equipas', 'adam-comunidade' ); ?></span><span data-type="field"><?php esc_html_e( 'Campos', 'adam-comunidade' ); ?></span></div>
	<p class="adam-map-status" data-map-status role="status"><?php echo esc_html( sprintf( __( '%d localizações disponíveis', 'adam-comunidade' ), count( $markers ) ) ); ?></p>
	<p class="adam-map-status" data-map-error role="status" hidden></p>
	<div class="adam-map-layout">
		<div class="adam-map-canvas" data-map-canvas role="region" aria-label="<?php esc_attr_e( 'Mapa interativo de equipas e campos do Centro de Portugal', 'adam-comunidade' ); ?>"></div>
		<div class="adam-map-results" data-map-results>
			<?php foreach ( $markers as $marker ) : ?><article><a href="<?php echo esc_url( $marker['url'] ); ?>"><?php echo esc_html( $marker['name'] ); ?></a><p><?php echo esc_html( $marker['municipality'] . ' · ' . $marker['district'] ); ?></p><?php if ( $marker['approximate'] ) : ?><small><?php echo esc_html( $marker['location_note'] ); ?></small><?php endif; ?></article><?php endforeach; ?>
		</div>
	</div>
	<p class="adam-map-note"><?php esc_html_e( 'Sem coordenadas, o ponto indica o centro do concelho ou, se não estiver identificado, do distrito. Contacta os responsáveis para confirmar a morada.', 'adam-comunidade' ); ?> <span><?php esc_html_e( 'Centros de concelho:', 'adam-comunidade' ); ?> <a href="https://www.geonames.org/" rel="external">GeoNames</a>.</span></p>
	<noscript><p class="adam-map-note"><?php esc_html_e( 'Ativa o JavaScript para explorar o mapa. Podes consultar as localizações na lista.', 'adam-comunidade' ); ?></p></noscript>
</section>
