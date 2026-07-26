<?php
/**
 * Guided import wizard.
 *
 * @var array<string,mixed>|false $batch    Preview batch.
 * @var array<string,mixed>|false $rollback Rollback batch.
 * @var string[]                  $fields   Supported fields.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<h1><?php esc_html_e( 'Import Wizard', 'adam-comunidade' ); ?></h1>
	<?php if ( ! $batch ) : ?>
		<div class="adam-card">
			<h2><?php esc_html_e( '1. Upload', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'CSV, Excel (.xlsx), and JSON are supported. Nothing is changed until you confirm the preview.', 'adam-comunidade' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="adam_import_preview">
				<?php wp_nonce_field( 'adam_import_preview' ); ?>
				<input type="file" name="import_file" accept=".csv,.xlsx,.json" required>
				<?php submit_button( __( 'Preview import', 'adam-comunidade' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
	<?php else : ?>
		<div class="adam-card">
			<h2><?php esc_html_e( '2. Map and review', 'adam-comunidade' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="adam_import_commit">
				<?php wp_nonce_field( 'adam_import_commit' ); ?>
				<table class="widefat striped">
					<thead><tr><?php foreach ( $batch['headers'] as $header ) : ?><th><?php echo esc_html( $header ); ?><select name="mapping[<?php echo esc_attr( $header ); ?>]"><option value=""><?php esc_html_e( 'Ignore', 'adam-comunidade' ); ?></option><?php foreach ( $fields as $field ) : ?><option value="<?php echo esc_attr( $field ); ?>" <?php selected( sanitize_key( $header ), $field ); ?>><?php echo esc_html( $field ); ?></option><?php endforeach; ?></select></th><?php endforeach; ?></tr></thead>
					<tbody><?php foreach ( array_slice( $batch['rows'], 0, 10 ) as $row ) : ?><tr><?php foreach ( $batch['headers'] as $header ) : ?><td><?php echo esc_html( $row[ $header ] ?? '' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
				</table>
				<p><?php echo esc_html( sprintf( _n( '%d row ready for validation.', '%d rows ready for validation.', count( $batch['rows'] ), 'adam-comunidade' ), count( $batch['rows'] ) ) ); ?></p>
				<?php submit_button( __( 'Validate and import', 'adam-comunidade' ) ); ?>
			</form>
		</div>
	<?php endif; ?>
	<?php if ( $rollback ) : ?>
		<div class="adam-card">
			<h2><?php esc_html_e( 'Rollback', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'The most recent batch can be reverted while this recovery window remains open.', 'adam-comunidade' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="adam_import_rollback">
				<?php wp_nonce_field( 'adam_import_rollback' ); ?>
				<?php submit_button( __( 'Rollback last import', 'adam-comunidade' ), 'secondary' ); ?>
			</form>
		</div>
	<?php endif; ?>
</div>
