<?php
/**
 * Community Manager database schema and migrations.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Logger;

/**
 * Owns the isolated Community Manager identity and moderation tables.
 *
 * Migrations are deliberately verified against the database instead of trusting
 * the stored version. This makes partially completed upgrades safe to resume.
 *
 * Physical foreign keys are intentionally avoided: assignments reference
 * polymorphic records owned by several WordPress tables, and WordPress upgrades
 * must remain compatible with database engines where dbDelta cannot manage
 * foreign-key constraints reliably. Manager-owned relations are enforced by
 * application checks and the scheduled orphan cleanup.
 */
final class Schema {
	public const VERSION = '1.3.0';

	private const VERSION_OPTION = 'adam_comunidade_managers_db_version';
	private const ERROR_OPTION   = 'adam_comunidade_managers_migration_error';
	private const LOCK_OPTION    = 'adam_comunidade_managers_migration_lock';
	private const LOCK_TTL       = 300;
	private const MIGRATIONS     = array(
		'1.0.0' => 'migration_100_create_tables',
		'1.1.0' => 'migration_110_repair_invitation_purpose',
		'1.2.0' => 'migration_120_normalize_integrity',
		'1.3.0' => 'migration_130_add_last_activity',
	);

	/**
	 * Installs or repairs the current schema.
	 *
	 * @return true|\WP_Error
	 */
	public static function install(): true|\WP_Error {
		return self::maybe_upgrade();
	}

	/**
	 * Runs idempotent migrations and verifies their result.
	 *
	 * This method always inspects the physical schema. It therefore repairs the
	 * historical 1.1.0 state where the version option existed but `purpose` had
	 * accidentally been added to assignments rather than invitations.
	 *
	 * @return true|\WP_Error
	 */
	public static function maybe_upgrade(): true|\WP_Error {
		if ( self::is_current() ) {
			return true;
		}

		$lock = self::acquire_lock();
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		global $wpdb;
		$previous_suppression = $wpdb->suppress_errors( true );
		try {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			foreach ( self::MIGRATIONS as $version => $callback ) {
				$result = self::{$callback}();
				if ( is_wp_error( $result ) ) {
					$result->add_data( array( 'migration' => $version ) );
					return self::migration_failed( $result );
				}
			}

			$result = self::verify();
			if ( is_wp_error( $result ) ) {
				return self::migration_failed( $result );
			}

			update_option( self::VERSION_OPTION, self::VERSION, false );
			delete_option( self::ERROR_OPTION );
			return true;
		} catch ( \Throwable $throwable ) {
			return self::migration_failed(
				new \WP_Error(
					'manager_schema_exception',
					__( 'A base de dados dos Gestores não pôde ser atualizada.', 'adam-comunidade' ),
					array( 'exception' => get_class( $throwable ) )
				)
			);
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
			self::release_lock( $lock );
		}
	}

	/**
	 * Checks the last verified schema version.
	 *
	 * The version is written only after physical verification succeeds. Avoiding
	 * repeated SHOW queries keeps normal public requests free of schema overhead.
	 */
	public static function is_current(): bool {
		return self::VERSION === (string) get_option( self::VERSION_OPTION, '' );
	}

	/**
	 * Returns the last safe migration diagnostic for administrators.
	 *
	 * @return array<string,mixed>
	 */
	public static function last_error(): array {
		$error = get_option( self::ERROR_OPTION, array() );
		return is_array( $error ) ? $error : array();
	}

	private static function create_or_update_tables(): void {
		global $wpdb;

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
			last_activity_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$assignments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			manager_id bigint(20) unsigned NOT NULL,
			entity_type varchar(40) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY manager_entity (manager_id,entity_type,entity_id),
			KEY manager_status (manager_id,status),
			KEY entity_lookup (entity_type,entity_id,status)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$invitations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			manager_id bigint(20) unsigned NOT NULL,
			purpose varchar(30) NOT NULL DEFAULT 'invitation',
			entity_type varchar(40) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			expires_at datetime NOT NULL,
			used_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY manager_open (manager_id,purpose,used_at,expires_at),
			KEY invitation_history (manager_id,purpose,entity_type,entity_id,created_at)
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
			KEY manager_expiry (manager_id,expires_at),
			KEY expires_at (expires_at)
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
	}

	/**
	 * Repairs defects that dbDelta cannot reliably infer on an existing site.
	 *
	 * @return true|\WP_Error
	 */
	private static function migration_100_create_tables(): true {
		self::create_or_update_tables();
		return true;
	}

	private static function migration_110_repair_invitation_purpose(): true|\WP_Error {
		global $wpdb;

		$invitations = self::invitations_table();
		if ( ! self::table_exists( $invitations ) ) {
			return new \WP_Error( 'manager_schema_missing_invitations', __( 'Não foi possível criar a estrutura de convites dos Gestores.', 'adam-comunidade' ) );
		}
		if ( ! self::column_exists( $invitations, 'purpose' ) ) {
			$result = $wpdb->query( "ALTER TABLE {$invitations} ADD COLUMN purpose varchar(30) NOT NULL DEFAULT 'invitation' AFTER manager_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				return new \WP_Error( 'manager_schema_add_purpose', __( 'Não foi possível concluir a atualização da base de dados dos Gestores.', 'adam-comunidade' ) );
			}
		}

		$assignments = self::assignments_table();
		if ( ! self::table_exists( $assignments ) ) {
			return new \WP_Error( 'manager_schema_missing_assignments', __( 'Não foi possível criar a estrutura de atribuições dos Gestores.', 'adam-comunidade' ) );
		}
		if ( self::column_exists( $assignments, 'purpose' ) ) {
			$result = $wpdb->query( "ALTER TABLE {$assignments} DROP COLUMN purpose" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				return new \WP_Error( 'manager_schema_remove_legacy_purpose', __( 'Não foi possível concluir a atualização da base de dados dos Gestores.', 'adam-comunidade' ) );
			}
		}

		return true;
	}

	private static function migration_120_normalize_integrity(): true|\WP_Error {
		global $wpdb;

		$assignments = self::assignments_table();
		if ( ! self::column_exists( $assignments, 'updated_at' ) ) {
			$added = $wpdb->query( "ALTER TABLE {$assignments} ADD COLUMN updated_at datetime NULL AFTER created_at" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $added ) {
				return new \WP_Error( 'manager_schema_add_assignment_updated', __( 'Não foi possível concluir a atualização da base de dados dos Gestores.', 'adam-comunidade' ) );
			}
		}
		$normalized = $wpdb->query( "UPDATE {$assignments} SET updated_at=created_at WHERE updated_at IS NULL OR updated_at='0000-00-00 00:00:00'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $normalized ) {
			return new \WP_Error( 'manager_schema_normalize_assignment_updated', __( 'Não foi possível concluir a atualização da base de dados dos Gestores.', 'adam-comunidade' ) );
		}
		$not_null = $wpdb->query( "ALTER TABLE {$assignments} MODIFY updated_at datetime NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $not_null ) {
			return new \WP_Error( 'manager_schema_assignment_updated_not_null', __( 'Não foi possível concluir a atualização da base de dados dos Gestores.', 'adam-comunidade' ) );
		}
		return true;
	}

	private static function migration_130_add_last_activity(): true|\WP_Error {
		global $wpdb;
		$managers = self::managers_table();
		if ( ! self::column_exists( $managers, 'last_activity_at' ) ) {
			$result = $wpdb->query( "ALTER TABLE {$managers} ADD COLUMN last_activity_at datetime NULL AFTER last_login_at" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				return new \WP_Error( 'manager_schema_add_last_activity', __( 'Não foi possível concluir a atualização da atividade dos Gestores.', 'adam-comunidade' ) );
			}
		}
		return true;
	}

	/**
	 * Verifies tables and columns used by runtime queries.
	 *
	 * @return true|\WP_Error
	 */
	private static function verify(): true|\WP_Error {
		$required = array(
			self::managers_table()    => array( 'id', 'email', 'password_hash', 'status', 'last_login_at', 'last_activity_at', 'created_at', 'updated_at' ),
			self::assignments_table() => array( 'id', 'manager_id', 'entity_type', 'entity_id', 'status', 'created_at', 'updated_at' ),
			self::invitations_table() => array( 'id', 'manager_id', 'purpose', 'entity_type', 'entity_id', 'token_hash', 'expires_at', 'used_at', 'created_at' ),
			self::sessions_table()    => array( 'id', 'manager_id', 'token_hash', 'expires_at', 'last_seen_at', 'created_at' ),
			self::revisions_table()   => array( 'id', 'manager_id', 'entity_type', 'entity_id', 'payload', 'status', 'admin_note', 'reviewed_by', 'submitted_at', 'reviewed_at', 'updated_at' ),
		);

		foreach ( $required as $table => $columns ) {
			if ( ! self::table_exists( $table ) ) {
				return new \WP_Error( 'manager_schema_missing_table', __( 'A estrutura de dados dos Gestores está incompleta.', 'adam-comunidade' ), array( 'component' => 'table' ) );
			}
			foreach ( $columns as $column ) {
				if ( ! self::column_exists( $table, $column ) ) {
					return new \WP_Error( 'manager_schema_missing_column', __( 'A estrutura de dados dos Gestores está incompleta.', 'adam-comunidade' ), array( 'component' => sanitize_key( $column ) ) );
				}
			}
		}

		$indexes = array(
			self::managers_table()    => array( 'PRIMARY', 'email', 'status' ),
			self::assignments_table() => array( 'PRIMARY', 'manager_entity', 'manager_status', 'entity_lookup' ),
			self::invitations_table() => array( 'PRIMARY', 'token_hash', 'manager_open', 'invitation_history' ),
			self::sessions_table()    => array( 'PRIMARY', 'token_hash', 'manager_expiry', 'expires_at' ),
			self::revisions_table()   => array( 'PRIMARY', 'moderation_queue', 'entity_history', 'manager_history' ),
		);
		foreach ( $indexes as $table => $names ) {
			foreach ( $names as $name ) {
				if ( ! self::index_exists( $table, $name ) ) {
					return new \WP_Error( 'manager_schema_missing_index', __( 'A estrutura de dados dos Gestores está incompleta.', 'adam-comunidade' ), array( 'component' => sanitize_key( $name ) ) );
				}
			}
		}

		return true;
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		return $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return in_array( $column, array_map( 'strval', is_array( $columns ) ? $columns : array() ), true );
	}

	private static function index_exists( string $table, string $index ): bool {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEX FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( isset( $row->Key_name ) && $index === (string) $row->Key_name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string|\WP_Error Lock token.
	 */
	private static function acquire_lock(): string|\WP_Error {
		try {
			$token = bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $throwable ) {
			Logger::error( 'community_manager_migration_lock_failed', array( 'exception' => get_class( $throwable ) ) );
			return new \WP_Error( 'manager_schema_lock_failed', __( 'Não foi possível iniciar a atualização segura dos Gestores.', 'adam-comunidade' ) );
		}
		$value = array( 'token' => $token, 'created_at' => time() );
		if ( add_option( self::LOCK_OPTION, $value, '', false ) ) {
			return $token;
		}

		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && (int) ( $current['created_at'] ?? 0 ) < time() - self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
			if ( add_option( self::LOCK_OPTION, $value, '', false ) ) {
				return $token;
			}
		}

		return new \WP_Error( 'manager_schema_locked', __( 'A atualização dos Gestores já está em curso. Tente novamente dentro de instantes.', 'adam-comunidade' ) );
	}

	private static function release_lock( string|\WP_Error $lock ): void {
		if ( is_wp_error( $lock ) ) {
			return;
		}
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && hash_equals( $lock, (string) ( $current['token'] ?? '' ) ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private static function migration_failed( \WP_Error $error ): \WP_Error {
		$diagnostic = array(
			'code'       => sanitize_key( (string) $error->get_error_code() ),
			'occurred_at'=> current_time( 'mysql', true ),
			'version'    => self::VERSION,
		);
		update_option( self::ERROR_OPTION, $diagnostic, false );
		Logger::error( 'community_manager_schema_migration_failed', $diagnostic );
		return $error;
	}

	public static function managers_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_managers'; }
	public static function assignments_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_manager_assignments'; }
	public static function invitations_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_manager_invitations'; }
	public static function sessions_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_manager_sessions'; }
	public static function revisions_table(): string { global $wpdb; return $wpdb->prefix . 'adam_community_revisions'; }
}
