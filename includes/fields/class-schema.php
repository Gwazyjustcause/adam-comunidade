<?php
/**
 * Fields database schema.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Installs field, amenity, gallery, and relationship storage.
 */
final class Schema {
	/**
	 * Module schema version.
	 */
	public const VERSION = '6.0.0';

	/**
	 * Creates or upgrades all Fields module tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$fields          = self::fields_table();
		$amenities       = self::amenities_table();
		$field_amenities = self::field_amenities_table();
		$galleries       = self::galleries_table();
		$relationships   = self::field_teams_table();

		$fields_sql = "CREATE TABLE {$fields} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			featured tinyint(1) unsigned NOT NULL DEFAULT 0,
			verification varchar(40) NOT NULL DEFAULT '',
			availability varchar(40) NOT NULL DEFAULT 'open',
			cover_id bigint(20) unsigned NOT NULL DEFAULT 0,
			short_description text NULL,
			full_description longtext NULL,
			district varchar(100) NOT NULL DEFAULT '',
			municipality varchar(100) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			latitude decimal(10,7) NULL,
			longitude decimal(10,7) NULL,
			maps_url varchar(500) NOT NULL DEFAULT '',
			playing_styles text NULL,
			rules longtext NULL,
			max_players int(10) unsigned NOT NULL DEFAULT 0,
			min_players int(10) unsigned NOT NULL DEFAULT 0,
			recommended_players int(10) unsigned NOT NULL DEFAULT 0,
			website varchar(500) NOT NULL DEFAULT '',
			facebook varchar(500) NOT NULL DEFAULT '',
			instagram varchar(500) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
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
			KEY featured_status (featured,status),
			KEY district (district),
			KEY municipality (municipality),
			KEY capacity (max_players)
		) {$charset_collate};";

		$amenities_sql = "CREATE TABLE {$amenities} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			context varchar(50) NOT NULL DEFAULT 'field',
			amenity_key varchar(100) NOT NULL,
			label varchar(191) NOT NULL,
			icon varchar(50) NOT NULL DEFAULT 'check',
			status varchar(20) NOT NULL DEFAULT 'active',
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY context_key (context,amenity_key),
			KEY context_status (context,status,sort_order)
		) {$charset_collate};";

		$field_amenities_sql = "CREATE TABLE {$field_amenities} (
			field_id bigint(20) unsigned NOT NULL,
			amenity_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (field_id,amenity_id),
			KEY amenity_id (amenity_id)
		) {$charset_collate};";

		$galleries_sql = "CREATE TABLE {$galleries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			field_id bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL,
			caption varchar(500) NOT NULL DEFAULT '',
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY field_attachment (field_id,attachment_id),
			KEY field_order (field_id,sort_order)
		) {$charset_collate};";

		$relationships_sql = "CREATE TABLE {$relationships} (
			team_id bigint(20) unsigned NOT NULL,
			field_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (team_id,field_id),
			KEY field_id (field_id)
		) {$charset_collate};";

		dbDelta( $fields_sql );
		dbDelta( $amenities_sql );
		dbDelta( $field_amenities_sql );
		dbDelta( $galleries_sql );
		dbDelta( $relationships_sql );

		self::seed_amenities();
		update_option( 'adam_comunidade_fields_db_version', self::VERSION, false );
		update_option( 'adam_comunidade_db_version', ADAM_COMUNIDADE_DB_VERSION, false );
	}

	/**
	 * Returns the fields table.
	 *
	 * @return string
	 */
	public static function fields_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_fields';
	}

	/**
	 * Returns the reusable amenities definition table.
	 *
	 * @return string
	 */
	public static function amenities_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_amenities';
	}

	/**
	 * Returns the field-amenity relationship table.
	 *
	 * @return string
	 */
	public static function field_amenities_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_field_amenities';
	}

	/**
	 * Returns the field gallery table.
	 *
	 * @return string
	 */
	public static function galleries_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_field_gallery';
	}

	/**
	 * Returns the shared field-team relationship table.
	 *
	 * @return string
	 */
	public static function field_teams_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'adam_team_fields';
	}

	/**
	 * Creates the initial editable amenity vocabulary.
	 *
	 * @return void
	 */
	private static function seed_amenities(): void {
		global $wpdb;

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . self::amenities_table() . " WHERE context = 'field'"
		);

		if ( $count ) {
			return;
		}

		$defaults = array(
			'parking'          => array( __( 'Parking', 'adam-comunidade' ), 'parking' ),
			'safe_zone'        => array( __( 'Safe Zone', 'adam-comunidade' ), 'shield' ),
			'chronograph'      => array( __( 'Chronograph', 'adam-comunidade' ), 'gauge' ),
			'electricity'      => array( __( 'Electricity', 'adam-comunidade' ), 'bolt' ),
			'water'            => array( __( 'Water', 'adam-comunidade' ), 'water' ),
			'camping'          => array( __( 'Camping', 'adam-comunidade' ), 'camping' ),
			'bbq'              => array( __( 'BBQ', 'adam-comunidade' ), 'fire' ),
			'toilets'          => array( __( 'Toilets', 'adam-comunidade' ), 'toilets' ),
			'shop'             => array( __( 'Shop', 'adam-comunidade' ), 'shop' ),
			'rental_equipment' => array( __( 'Rental Equipment', 'adam-comunidade' ), 'equipment' ),
			'battery_charging' => array( __( 'Battery Charging', 'adam-comunidade' ), 'battery' ),
			'food_available'   => array( __( 'Food Available', 'adam-comunidade' ), 'food' ),
			'indoor_area'      => array( __( 'Indoor Area', 'adam-comunidade' ), 'indoor' ),
			'night_games'      => array( __( 'Night Games', 'adam-comunidade' ), 'moon' ),
			'changing_rooms'   => array( __( 'Changing Rooms', 'adam-comunidade' ), 'changing' ),
			'first_aid'        => array( __( 'First Aid', 'adam-comunidade' ), 'first-aid' ),
		);
		$now = current_time( 'mysql', true );
		$order = 0;

		foreach ( $defaults as $key => $data ) {
			$wpdb->insert(
				self::amenities_table(),
				array(
					'context'     => 'field',
					'amenity_key' => $key,
					'label'       => $data[0],
					'icon'        => $data[1],
					'status'      => 'active',
					'sort_order'  => $order,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
			++$order;
		}
	}
}
