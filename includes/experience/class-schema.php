<?php
/**
 * Phase 5 database schema.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Installs aggregated analytics storage.
 */
final class Schema {
	public const VERSION = '6.0.0';

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::metrics_table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_type varchar(40) NOT NULL,
				object_type varchar(40) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				dimension_hash char(32) NOT NULL,
				dimension varchar(191) NOT NULL DEFAULT '',
				total bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY metric (event_type,object_type,object_id,dimension_hash),
				KEY event_total (event_type,total),
				KEY object_total (object_type,object_id,total)
			) {$collate};"
		);
		$submissions = self::submissions_table();
		$owners = self::owners_table();
		$notifications = self::notifications_table();
		$calendar = self::calendar_table();
		$media = self::media_table();
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
		dbDelta( "CREATE TABLE {$calendar} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			entry_type varchar(40) NOT NULL,
			summary text NULL,
			start_at datetime NOT NULL,
			end_at datetime NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'published',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY schedule (status,start_at),
			KEY related (object_type,object_id)
		) {$collate};" );
		dbDelta( "CREATE TABLE {$media} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(40) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			media_type varchar(30) NOT NULL,
			media_url varchar(500) NOT NULL,
			caption varchar(500) NOT NULL DEFAULT '',
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY object_order (object_type,object_id,sort_order)
		) {$collate};" );
		update_option( 'adam_comunidade_experience_db_version', self::VERSION, false );
		update_option( 'adam_comunidade_db_version', ADAM_COMUNIDADE_DB_VERSION, false );
	}

	public static function metrics_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'adam_community_metrics';
	}

	public static function submissions_table(): string { global $wpdb; return $wpdb->prefix . 'adam_submissions'; }
	public static function owners_table(): string { global $wpdb; return $wpdb->prefix . 'adam_listing_owners'; }
	public static function notifications_table(): string { global $wpdb; return $wpdb->prefix . 'adam_notifications'; }
	public static function calendar_table(): string { global $wpdb; return $wpdb->prefix . 'adam_calendar'; }
	public static function media_table(): string { global $wpdb; return $wpdb->prefix . 'adam_rich_media'; }
}
