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
			<h1><?php esc_html_e( 'ADAM Comunidade Settings', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Configure the shared foundation used by every module.', 'adam-comunidade' ); ?></p>
		</div>
	</header>

	<div class="adam-settings-layout">
		<form class="adam-card adam-settings-form" action="options.php" method="post">
			<?php
			settings_fields( 'adam_comunidade_settings_group' );
			do_settings_sections( 'adam-comunidade-settings' );
			submit_button( __( 'Save Settings', 'adam-comunidade' ), 'primary adam-button' );
			?>
		</form>

		<aside class="adam-card adam-cache-card">
			<span class="adam-card__eyebrow"><?php esc_html_e( 'Advanced', 'adam-comunidade' ); ?></span>
			<h2><?php esc_html_e( 'Reset Cache', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'Notify current and future modules to clear their cached data.', 'adam-comunidade' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="adam_comunidade_reset_cache">
				<?php wp_nonce_field( 'adam_comunidade_reset_cache' ); ?>
				<button class="button adam-button adam-button--secondary" type="submit">
					<?php esc_html_e( 'Reset Cache', 'adam-comunidade' ); ?>
				</button>
			</form>
		</aside>
	</div>
</div>
