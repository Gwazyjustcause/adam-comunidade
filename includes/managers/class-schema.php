<?php
/**
 * Community Manager database schema.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the isolated Community Manager identity and moderation tables.
 */
final class Schema {
	public const VERSION = '1.1.0';

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate     = $wpdb->get_charset_collate();
		$managers    = self::managers_table();
		$assignments = self::assignments_table();
		$invitations = self::invitations_table();
		$sessions    = self::sessions_table();
		$revisions   = self::revisions_table();

		dbDelta( "CREATE TABLE {$managers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			password_hash varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'invited',
			last_login_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$assignments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			manager_id bigint(20) unsigned NOT NULL,
			purpose varchar(30) NOT NULL DEFAULT 'invitation',
			entity_type varchar(40) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY manager_entity (manager_id,entity_type,entity_id),
			KEY entity_lookup (entity_type,entity_id,status)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$invitations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			manager_id bigint(20) unsigned NOT NULL,
			entity_type varchar(40) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			expires_at datetime NOT NULL,
			used_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY manager_open (manager_id,purpose,used_at,expires_at)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			manager_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			expires_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY manager_expiry (manager_id,expires_at)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			manager_id bigint(20) unsigned NOT NULL,
			entity_type varchar(40) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			payload longtext NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'pending',
			admin_note text NULL,
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			submitted_at datetime NOT NULL,
			reviewed_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY moderation_queue (status,submitted_at),
			KEY entity_history (entity_type,entity_id,submitted_at),
			KEY manager_history (manager_id,status,submitted_at)
		) {$collate};" );

		update_option( 'adam_comunidade_managers_db_version', self::VERSION, false );
	}

	public static function managers_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_managers'; }
	public static function assignments_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_manager_assignments'; }
	public static function invitations_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_manager_invitations'; }
	public static function sessions_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_manager_sessions'; }
	public static function revisions_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_revisions'; }
}
