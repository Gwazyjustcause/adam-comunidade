<?php
/**
 * Import/export tools.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin"><h1><?php esc_html_e( 'Import & Export', 'adam-comunidade' ); ?></h1>
	<div class="adam-dashboard-columns">
		<section class="adam-comunidade__card"><h2><?php esc_html_e( 'Export', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Create a JSON backup or export a directory as CSV for spreadsheet bulk updates.', 'adam-comunidade' ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adam_community_export&format=json' ), 'adam_community_export' ) ); ?>"><?php esc_html_e( 'Download JSON Backup', 'adam-comunidade' ); ?></a>
			<?php foreach ( array( 'partner' => __( 'Partners CSV', 'adam-comunidade' ), 'institution' => __( 'Institutions CSV', 'adam-comunidade' ), 'brand' => __( 'Brands CSV', 'adam-comunidade' ) ) as $type => $label ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adam_community_export&format=csv&entity_type=' . $type ), 'adam_community_export' ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
		</section>
		<section class="adam-comunidade__card"><h2><?php esc_html_e( 'Import / Restore', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Import JSON backups or CSV exports. Matching IDs or slugs are updated; other rows are created.', 'adam-comunidade' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="adam_community_import"><?php wp_nonce_field( 'adam_community_import' ); ?><input required type="file" name="import_file" accept=".json,.csv,application/json,text/csv"><button class="button button-primary"><?php esc_html_e( 'Import File', 'adam-comunidade' ); ?></button></form>
		</section>
	</div>
</div>
