<?php
/**
 * System health administration.
 *
 * @var array<int,array{label:string,ok:bool,detail:string}> $checks Health checks.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<h1><?php esc_html_e( 'ADAM Comunidade Health', 'adam-comunidade' ); ?></h1>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Check', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Status', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Details', 'adam-comunidade' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $checks as $check ) : ?>
				<tr><td><?php echo esc_html( $check['label'] ); ?></td><td><span class="adam-badge adam-badge--<?php echo esc_attr( $check['ok'] ? 'success' : 'warning' ); ?>"><?php echo esc_html( $check['ok'] ? __( 'Healthy', 'adam-comunidade' ) : __( 'Attention', 'adam-comunidade' ) ); ?></span></td><td><?php echo esc_html( $check['detail'] ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="adam_health_repair">
		<?php wp_nonce_field( 'adam_health_repair' ); ?>
		<?php submit_button( __( 'Run safe repair', 'adam-comunidade' ), 'secondary' ); ?>
	</form>
</div>
