<?php
/**
 * Teams database schema.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

/**
 * Installs and upgrades the Teams module tables.
 */
final class Schema {
	/**
	 * Creates or upgrades the module tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$teams_table     = self::teams_table();
		$fields_table    = self::team_fields_table();

		$teams_sql = "CREATE TABLE {$teams_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			short_name varchar(100) NOT NULL DEFAULT '',
			slug varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			logo_id bigint(20) unsigned NOT NULL DEFAULT 0,
			cover_id bigint(20) unsigned NOT NULL DEFAULT 0,
			gallery longtext NULL,
			team_colour varchar(7) NOT NULL DEFAULT '',
			short_description text NULL,
			full_description longtext NULL,
			district varchar(100) NOT NULL DEFAULT '',
			municipality varchar(100) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			latitude decimal(10,7) NULL,
			longitude decimal(10,7) NULL,
			maps_url varchar(500) NOT NULL DEFAULT '',
			website varchar(500) NOT NULL DEFAULT '',
			facebook varchar(500) NOT NULL DEFAULT '',
			instagram varchar(500) NOT NULL DEFAULT '',
			discord varchar(500) NOT NULL DEFAULT '',
			youtube varchar(500) NOT NULL DEFAULT '',
			tiktok varchar(500) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			founded smallint(4) unsigned NULL,
			members int(10) unsigned NOT NULL DEFAULT 0,
			recruitment_status varchar(30) NOT NULL DEFAULT 'closed',
			playing_styles text NULL,
			equipment_tags text NULL,
			meta_title varchar(255) NOT NULL DEFAULT '',
			meta_description varchar(320) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			published_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name),
			UNIQUE KEY slug (slug),
			KEY status_updated (status,updated_at),
			KEY district (district),
			KEY municipality (municipality)
		) {$charset_collate};";

		$fields_sql = "CREATE TABLE {$fields_table} (
			team_id bigint(20) unsigned NOT NULL,
			field_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (team_id,field_id),
			KEY field_id (field_id)
		) {$charset_collate};";

		dbDelta( $teams_sql );
		dbDelta( $fields_sql );

		update_option( 'adam_comunidade_db_version', ADAM_COMUNIDADE_DB_VERSION, false );
	}

	/**
	 * Returns the teams table name.
	 *
	 * @return string
	 */
	public static function teams_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_teams';
	}

	/**
	 * Returns the future team-field relationship table name.
	 *
	 * @return string
	 */
	public static function team_fields_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_team_fields';
	}
}
