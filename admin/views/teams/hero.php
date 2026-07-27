<?php
/**
 * Teams directory hero manager.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Uploads\Component as Upload_Component;
?>
<div class="wrap adam-comunidade-admin adam-teams-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Hero das Equipas', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Escolha, ordene e ative as imagens do carrossel apresentado no topo do diretório.', 'adam-comunidade' ); ?></p>
		</div>
		<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( Admin_Router::module_url( 'teams' ) ); ?>"><?php esc_html_e( 'Voltar às equipas', 'adam-comunidade' ); ?></a>
	</header>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="adam_teams_hero_save">
		<?php wp_nonce_field( 'adam_teams_hero_save' ); ?>
		<section class="adam-card adam-fields-hero-settings">
			<h2><?php esc_html_e( 'Origem das imagens', 'adam-comunidade' ); ?></h2>
			<select name="hero[source]">
				<option value="manual" <?php selected( $hero_settings['source'], 'manual' ); ?>><?php esc_html_e( 'Imagens selecionadas manualmente', 'adam-comunidade' ); ?></option>
				<option value="published_teams" <?php selected( $hero_settings['source'], 'published_teams' ); ?>><?php esc_html_e( 'Capas de equipas publicadas (com fallback manual)', 'adam-comunidade' ); ?></option>
			</select>
			<div class="adam-form-grid adam-form-grid--3">
				<label><span><?php esc_html_e( 'Transição automática', 'adam-comunidade' ); ?></span><input type="checkbox" name="hero[autoplay]" value="1" <?php checked( ! empty( $hero_settings['autoplay'] ) ); ?>></label>
				<label><span><?php esc_html_e( 'Intervalo (milissegundos)', 'adam-comunidade' ); ?></span><input type="number" min="3000" max="15000" step="500" name="hero[interval]" value="<?php echo esc_attr( (string) $hero_settings['interval'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Mínimo de capas publicadas', 'adam-comunidade' ); ?></span><input type="number" min="2" max="12" name="hero[minimum_featured]" value="<?php echo esc_attr( (string) $hero_settings['minimum_featured'] ); ?>"></label>
			</div>
		</section>
		<section class="adam-card adam-fields-hero-settings">
			<h2><?php esc_html_e( 'Imagens manuais', 'adam-comunidade' ); ?></h2>
			<?php
			Upload_Component::render(
				array(
					'id'             => 'adam-teams-hero-images',
					'mode'           => 'library',
					'kind'           => 'image',
					'name'           => 'hero[image_ids][]',
					'label'          => __( 'Imagens do hero', 'adam-comunidade' ),
					'multiple'       => true,
					'max'            => 20,
					'items'          => $hero_images,
					'toggle_pattern' => 'hero[enabled][__ID__]',
					'toggle_label'   => __( 'Imagem ativa', 'adam-comunidade' ),
				)
			);
			?>
		</section>
		<?php submit_button( __( 'Guardar hero', 'adam-comunidade' ), 'primary adam-button' ); ?>
	</form>
</div>
