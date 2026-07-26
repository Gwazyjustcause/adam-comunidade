<?php
/**
 * ADAM Comunidade uninstall routine.
 *
 * Content is deliberately preserved. A future explicit full-cleanup setting may
 * opt into content deletion, but uninstalling the foundation never does so.
 *
 * @package ADAM_Comunidade
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$adam_comunidade_options = array(
	'adam_comunidade_version',
	'adam_comunidade_db_version',
	'adam_comunidade_settings',
	'adam_comunidade_teams_db_version',
	'adam_comunidade_fields_db_version',
	'adam_comunidade_directory_db_version',
	'adam_comunidade_experience_db_version',
	'adam_comunidade_home_sections',
	'adam_comunidade_cache_version',
	'adam_comunidade_managed_pages_version',
	'adam_comunidade_public_forms',
	'adam_comunidade_flush_rewrite_rules',
);

if ( is_multisite() ) {
	$adam_comunidade_site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $adam_comunidade_site_ids as $adam_comunidade_site_id ) {
		switch_to_blog( (int) $adam_comunidade_site_id );

		foreach ( $adam_comunidade_options as $adam_comunidade_option ) {
			delete_option( $adam_comunidade_option );
		}
		wp_clear_scheduled_hook( 'adam_comunidade_notification_scan' );

		restore_current_blog();
	}
} else {
	foreach ( $adam_comunidade_options as $adam_comunidade_option ) {
		delete_option( $adam_comunidade_option );
	}
	wp_clear_scheduled_hook( 'adam_comunidade_notification_scan' );
}
