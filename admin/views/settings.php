<?php
/**
 * Settings page view.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Definições do ADAM Comunidade', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Configure as opções comuns a todos os módulos.', 'adam-comunidade' ); ?></p>
		</div>
	</header>

	<div class="adam-settings-layout">
		<form class="adam-card adam-settings-form" action="options.php" method="post">
			<?php
			settings_fields( 'adam_comunidade_settings_group' );
			do_settings_sections( 'adam-comunidade-settings' );
			submit_button( __( 'Guardar definições', 'adam-comunidade' ), 'primary adam-button' );
			?>
		</form>
	</div>
</div>
