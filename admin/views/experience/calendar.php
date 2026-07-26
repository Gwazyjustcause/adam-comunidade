<?php
/**
 * Community calendar administration.
 *
 * @var object[]             $entries Calendar entries.
 * @var array<string,string> $types   Entry types.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<h1><?php esc_html_e( 'Calendário da Comunidade', 'adam-comunidade' ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adam-card">
		<input type="hidden" name="action" value="adam_calendar_save">
		<?php wp_nonce_field( 'adam_calendar_save' ); ?>
		<table class="form-table">
			<tr><th><label for="adam-calendar-title"><?php esc_html_e( 'Título', 'adam-comunidade' ); ?></label></th><td><input class="regular-text" id="adam-calendar-title" name="title" required></td></tr>
			<tr><th><label for="adam-calendar-type"><?php esc_html_e( 'Tipo', 'adam-comunidade' ); ?></label></th><td><select id="adam-calendar-type" name="entry_type"><?php foreach ( $types as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><label for="adam-calendar-start"><?php esc_html_e( 'Início', 'adam-comunidade' ); ?></label></th><td><input id="adam-calendar-start" name="start_at" type="datetime-local" required></td></tr>
			<tr><th><label for="adam-calendar-end"><?php esc_html_e( 'Fim', 'adam-comunidade' ); ?></label></th><td><input id="adam-calendar-end" name="end_at" type="datetime-local"></td></tr>
			<tr><th><label for="adam-calendar-summary"><?php esc_html_e( 'Resumo', 'adam-comunidade' ); ?></label></th><td><textarea class="large-text" id="adam-calendar-summary" name="summary"></textarea></td></tr>
		</table>
		<?php submit_button( __( 'Publicar entrada no calendário', 'adam-comunidade' ) ); ?>
	</form>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Título', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Tipo', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Início', 'adam-comunidade' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $entries as $entry ) : ?>
				<tr><td><?php echo esc_html( $entry->title ); ?></td><td><?php echo esc_html( $types[ $entry->entry_type ] ?? $entry->entry_type ); ?></td><td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->start_at . ' UTC' ) ) ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
