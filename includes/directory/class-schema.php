<?php
/**
 * Community directory database schema.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Installs shared entity, gallery, and relationship storage.
 */
final class Schema {
	public const VERSION = '6.0.0';

	/**
	 * Creates or upgrades Phase 4 tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate       = $wpdb->get_charset_collate();
		$entries       = self::entries_table();
		$galleries     = self::galleries_table();
		$relationships = self::relationships_table();

		dbDelta(
			"CREATE TABLE {$entries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				entity_type varchar(40) NOT NULL,
				name varchar(191) NOT NULL,
				slug varchar(191) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				logo_id bigint(20) unsigned NOT NULL DEFAULT 0,
				cover_id bigint(20) unsigned NOT NULL DEFAULT 0,
				short_description text NULL,
				full_description longtext NULL,
				website varchar(500) NOT NULL DEFAULT '',
				facebook varchar(500) NOT NULL DEFAULT '',
				instagram varchar(500) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				phone varchar(50) NOT NULL DEFAULT '',
				address varchar(255) NOT NULL DEFAULT '',
				district varchar(100) NOT NULL DEFAULT '',
				latitude decimal(10,7) NULL,
				longitude decimal(10,7) NULL,
				category varchar(100) NOT NULL DEFAULT '',
				benefits longtext NULL,
				member_benefits longtext NULL,
				featured tinyint(1) unsigned NOT NULL DEFAULT 0,
				homepage_featured tinyint(1) unsigned NOT NULL DEFAULT 0,
				verification varchar(40) NOT NULL DEFAULT '',
				priority int(11) NOT NULL DEFAULT 0,
				country varchar(100) NOT NULL DEFAULT '',
				popular_products longtext NULL,
				official_distributor tinyint(1) unsigned NOT NULL DEFAULT 0,
				notes longtext NULL,
				promo_pdf_id bigint(20) unsigned NOT NULL DEFAULT 0,
				catalogue_id bigint(20) unsigned NOT NULL DEFAULT 0,
				meta_title varchar(255) NOT NULL DEFAULT '',
				meta_description varchar(320) NOT NULL DEFAULT '',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				published_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY type_name (entity_type,name),
				UNIQUE KEY type_slug (entity_type,slug),
				KEY type_status_updated (entity_type,status,updated_at),
				KEY type_featured (entity_type,status,featured,priority),
				KEY district (district),
				KEY category (category)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$galleries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				entry_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL,
				caption varchar(500) NOT NULL DEFAULT '',
				sort_order int(10) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY entry_attachment (entry_id,attachment_id),
				KEY entry_order (entry_id,sort_order)
			) {$collate};"
		);

		dbDelta(
			"CREATE TABLE {$relationships} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_type varchar(40) NOT NULL,
				source_id bigint(20) unsigned NOT NULL,
				relation varchar(80) NOT NULL,
				target_type varchar(40) NOT NULL,
				target_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY relationship (source_type,source_id,relation,target_type,target_id),
				KEY source_lookup (source_type,source_id,relation),
				KEY target_lookup (target_type,target_id,relation)
			) {$collate};"
		);

		update_option( 'adam_comunidade_directory_db_version', self::VERSION, false );
		update_option( 'adam_comunidade_db_version', ADAM_COMUNIDADE_DB_VERSION, false );
	}

	public static function entries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'adam_directory_entries';
	}

	public static function galleries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'adam_directory_gallery';
	}

	public static function relationships_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'adam_relationships';
	}
}
