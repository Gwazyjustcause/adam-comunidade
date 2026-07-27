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
	public const VERSION = '6.1.0';

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
			is_associated tinyint(1) unsigned NOT NULL DEFAULT 0,
			verification varchar(40) NOT NULL DEFAULT '',
			authorization_document_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
			KEY associated_status (is_associated,status),
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
		self::localize_default_amenities();
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
			'parking'          => array( __( 'Estacionamento', 'adam-comunidade' ), 'parking' ),
			'safe_zone'        => array( __( 'Zona segura', 'adam-comunidade' ), 'shield' ),
			'chronograph'      => array( __( 'Cronógrafo', 'adam-comunidade' ), 'gauge' ),
			'electricity'      => array( __( 'Eletricidade', 'adam-comunidade' ), 'bolt' ),
			'water'            => array( __( 'Água', 'adam-comunidade' ), 'water' ),
			'camping'          => array( __( 'Campismo', 'adam-comunidade' ), 'camping' ),
			'bbq'              => array( __( 'BBQ', 'adam-comunidade' ), 'fire' ),
			'toilets'          => array( __( 'Casas de banho', 'adam-comunidade' ), 'toilets' ),
			'shop'             => array( __( 'Loja', 'adam-comunidade' ), 'shop' ),
			'rental_equipment' => array( __( 'Aluguer de equipamento', 'adam-comunidade' ), 'equipment' ),
			'battery_charging' => array( __( 'Carregamento de baterias', 'adam-comunidade' ), 'battery' ),
			'food_available'   => array( __( 'Alimentação', 'adam-comunidade' ), 'food' ),
			'indoor_area'      => array( __( 'Área interior', 'adam-comunidade' ), 'indoor' ),
			'night_games'      => array( __( 'Jogos noturnos', 'adam-comunidade' ), 'moon' ),
			'changing_rooms'   => array( __( 'Balneário', 'adam-comunidade' ), 'changing' ),
			'first_aid'        => array( __( 'Primeiros socorros', 'adam-comunidade' ), 'first-aid' ),
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

	/**
	 * Translates only untouched legacy defaults, preserving administrator edits.
	 */
	private static function localize_default_amenities(): void {
		global $wpdb;

		$labels = array(
			'parking'          => array( 'Parking', __( 'Estacionamento', 'adam-comunidade' ) ),
			'safe_zone'        => array( 'Safe Zone', __( 'Zona segura', 'adam-comunidade' ) ),
			'chronograph'      => array( 'Chronograph', __( 'Cronógrafo', 'adam-comunidade' ) ),
			'electricity'      => array( 'Electricity', __( 'Eletricidade', 'adam-comunidade' ) ),
			'water'            => array( 'Water', __( 'Água', 'adam-comunidade' ) ),
			'camping'          => array( 'Camping', __( 'Campismo', 'adam-comunidade' ) ),
			'toilets'          => array( 'Toilets', __( 'Casas de banho', 'adam-comunidade' ) ),
			'shop'             => array( 'Shop', __( 'Loja', 'adam-comunidade' ) ),
			'rental_equipment' => array( 'Rental Equipment', __( 'Aluguer de equipamento', 'adam-comunidade' ) ),
			'battery_charging' => array( 'Battery Charging', __( 'Carregamento de baterias', 'adam-comunidade' ) ),
			'food_available'   => array( 'Food Available', __( 'Alimentação', 'adam-comunidade' ) ),
			'indoor_area'      => array( 'Indoor Area', __( 'Área interior', 'adam-comunidade' ) ),
			'night_games'      => array( 'Night Games', __( 'Jogos noturnos', 'adam-comunidade' ) ),
			'changing_rooms'   => array( 'Changing Rooms', __( 'Balneário', 'adam-comunidade' ) ),
			'first_aid'        => array( 'First Aid', __( 'Primeiros socorros', 'adam-comunidade' ) ),
		);

		foreach ( $labels as $key => $label ) {
			$wpdb->update(
				self::amenities_table(),
				array( 'label' => $label[1] ),
				array( 'context' => 'field', 'amenity_key' => $key, 'label' => $label[0] )
			);
		}
	}
}
