<?php
/**
 * Community experience database schema.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Installs aggregated analytics storage.
 */
final class Schema {
	public const VERSION = '6.1.0';

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$submissions = self::submissions_table();
		$owners = self::owners_table();
		$notifications = self::notifications_table();
		dbDelta( "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_type varchar(30) NOT NULL,
			object_type varchar(40) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			contact_email varchar(190) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			verification_details longtext NULL,
			status varchar(30) NOT NULL DEFAULT 'pending',
			admin_note text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY queue (status,created_at),
			KEY object_lookup (object_type,object_id),
			KEY user_lookup (user_id,status)
		) {$collate};" );
		dbDelta( "CREATE TABLE {$owners} (
			object_type varchar(40) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'verified',
			created_at datetime NOT NULL,
			PRIMARY KEY  (object_type,object_id,user_id),
			KEY user_status (user_id,status)
		) {$collate};" );
		dbDelta( "CREATE TABLE {$notifications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			message text NOT NULL,
			action_url varchar(500) NOT NULL DEFAULT '',
			is_read tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY inbox (user_id,is_read,created_at)
		) {$collate};" );
		update_option( 'adam_comunidade_experience_db_version', self::VERSION, false );
		update_option( 'adam_comunidade_db_version', ADAM_COMUNIDADE_DB_VERSION, false );
	}

	public static function submissions_table(): string { global $wpdb; return $wpdb->prefix . 'adam_submissions'; }
	public static function owners_table(): string { global $wpdb; return $wpdb->prefix . 'adam_listing_owners'; }
	public static function notifications_table(): string { global $wpdb; return $wpdb->prefix . 'adam_notifications'; }
}
