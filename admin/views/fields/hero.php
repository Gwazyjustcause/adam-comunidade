<?php
/**
 * Fields directory hero manager.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Uploads\Component as Upload_Component;
?>
<div class="wrap adam-comunidade-admin adam-fields-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Hero dos Campos', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Escolha, ordene e ative as imagens do carrossel apresentado no topo do diretório.', 'adam-comunidade' ); ?></p>
		</div>
		<a class="button adam-button adam-button--secondary" href="<?php echo esc_url( Admin_Router::module_url( 'fields' ) ); ?>"><?php esc_html_e( 'Voltar aos campos', 'adam-comunidade' ); ?></a>
	</header>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="adam_fields_hero_save">
		<?php wp_nonce_field( 'adam_fields_hero_save' ); ?>

		<section class="adam-card adam-fields-hero-settings">
			<h2><?php esc_html_e( 'Origem das imagens', 'adam-comunidade' ); ?></h2>
			<label>
				<select name="hero[source]">
					<option value="manual" <?php selected( $hero_settings['source'], 'manual' ); ?>><?php esc_html_e( 'Imagens selecionadas manualmente', 'adam-comunidade' ); ?></option>
					<option value="approved_fields" <?php selected( $hero_settings['source'], 'approved_fields' ); ?>><?php esc_html_e( 'Capas de campos aprovados (com fallback manual)', 'adam-comunidade' ); ?></option>
				</select>
			</label>
			<p class="description"><?php esc_html_e( 'No modo automático, as capas dos campos destacados aparecem primeiro. Se não existirem capas suficientes, são usadas as imagens manuais abaixo.', 'adam-comunidade' ); ?></p>

			<div class="adam-form-grid adam-form-grid--3">
				<label>
					<span><?php esc_html_e( 'Transição automática', 'adam-comunidade' ); ?></span>
					<input type="checkbox" name="hero[autoplay]" value="1" <?php checked( ! empty( $hero_settings['autoplay'] ) ); ?>>
				</label>
				<label>
					<span><?php esc_html_e( 'Intervalo (milissegundos)', 'adam-comunidade' ); ?></span>
					<input type="number" min="3000" max="15000" step="500" name="hero[interval]" value="<?php echo esc_attr( (string) $hero_settings['interval'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Mínimo de capas aprovadas', 'adam-comunidade' ); ?></span>
					<input type="number" min="2" max="12" name="hero[minimum_featured]" value="<?php echo esc_attr( (string) $hero_settings['minimum_featured'] ); ?>">
				</label>
			</div>
		</section>

		<section class="adam-card adam-fields-hero-settings">
			<h2><?php esc_html_e( 'Imagens manuais', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'Adicione imagens de woodland, CQB, fábricas, aldeias, terreno misto, ambientes militares e campos abertos. Arraste os cartões para definir a ordem.', 'adam-comunidade' ); ?></p>
			<?php
			Upload_Component::render(
				array(
					'id'             => 'adam-fields-hero-images',
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
