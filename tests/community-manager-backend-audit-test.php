<?php
/**
 * Production-readiness contracts for the Community Manager backend.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema  = (string) file_get_contents( $root . '/includes/managers/class-schema.php' );
$module  = (string) file_get_contents( $root . '/includes/managers/class-module.php' );
$cleanup = (string) file_get_contents( $root . '/includes/managers/class-cleanup.php' );
$auth    = (string) file_get_contents( $root . '/includes/managers/class-auth.php' );
$service = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$portal  = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$admin   = (string) file_get_contents( $root . '/includes/managers/class-admin.php' );
$install = (string) file_get_contents( $root . '/includes/class-install.php' );

$assignments_sql_start = strpos( $schema, 'CREATE TABLE {$assignments}' );
$invitations_sql_start = strpos( $schema, 'CREATE TABLE {$invitations}' );
$sessions_sql_start    = strpos( $schema, 'CREATE TABLE {$sessions}' );
$assert( false !== $assignments_sql_start && false !== $invitations_sql_start && false !== $sessions_sql_start, 'Manager table definitions are incomplete.' );
$assignments_sql = substr( $schema, $assignments_sql_start, $invitations_sql_start - $assignments_sql_start );
$invitations_sql = substr( $schema, $invitations_sql_start, $sessions_sql_start - $invitations_sql_start );
$assert( ! str_contains( $assignments_sql, 'purpose varchar' ), 'The legacy purpose column is still defined on assignments.' );
$assert( str_contains( $invitations_sql, "purpose varchar(30) NOT NULL DEFAULT 'invitation'" ), 'Invitation purpose is not part of the canonical schema.' );

foreach ( array( "'1.0.0'", "'1.1.0'", "'1.2.0'", "'1.3.0'" ) as $migration ) {
	$assert( str_contains( $schema, $migration ), 'Missing versioned manager migration: ' . $migration );
}
$assert( str_contains( $schema, 'self::is_current()' ), 'Schema upgrades do not inspect the physical database.' );
$assert( str_contains( $schema, 'self::verify()' ), 'Schema migrations are not verified.' );
$assert( strpos( $schema, 'self::verify()' ) < strpos( $schema, 'update_option( self::VERSION_OPTION, self::VERSION' ), 'Schema version is stored before verification.' );
$assert( str_contains( $schema, 'finally' ) && str_contains( $schema, 'release_lock' ), 'Migration lock is not reliably released.' );
$assert( str_contains( $schema, 'ADD COLUMN purpose' ) && str_contains( $schema, 'DROP COLUMN purpose' ), 'The broken purpose migration cannot self-heal.' );
$assert( str_contains( $schema, 'SHOW INDEX FROM' ) && str_contains( $schema, 'manager_schema_missing_index' ), 'Required indexes are not verified.' );
$assert( str_contains( $schema, 'suppress_errors( true )' ), 'Database migration errors may be rendered publicly.' );
$assert( str_contains( $module, 'Schema::maybe_upgrade()' ) && str_contains( $module, 'community_manager_module_unavailable' ), 'Runtime does not fail closed when migrations fail.' );
$assert( str_contains( $install, 'Managers\\Schema::install()' ), 'Fresh installations do not install the manager schema.' );

$assert( str_contains( $cleanup, 'wp_schedule_event' ) && str_contains( $cleanup, "purpose='password_reset'" ), 'Expired credential cleanup is incomplete.' );
$assert( str_contains( $cleanup, 'LEFT JOIN' ) && str_contains( $cleanup, 'IS NULL' ), 'Relational orphan cleanup is missing.' );
$assert( str_contains( $cleanup, "status='processing'" ), 'Interrupted moderation cannot recover.' );

$assert( str_contains( $auth, 'headers_sent()' ) && str_contains( $auth, 'community_manager_cookie_headers_sent' ), 'Cookie creation can emit headers-already-sent warnings.' );
$assert( str_contains( $auth, 'HttpOnly' ) || str_contains( $auth, "'httponly' => true" ), 'Manager session cookie is not HttpOnly.' );
$assert( str_contains( $auth, "'samesite' => 'Lax'" ) && str_contains( $auth, "'secure'   => is_ssl()" ), 'Manager session cookie flags are incomplete.' );
$assert( str_contains( $auth, 'array_slice' ) && str_contains( $auth, 'manager_id=%d ORDER BY created_at DESC' ), 'Concurrent sessions are not bounded.' );
$assert( str_contains( $service, "SET status='processing'" ), 'Revision moderation is not atomically claimed.' );
$assert( str_contains( $service, 'AND id <> %d' ), 'New tokens/revisions do not preserve a valid replacement before superseding the previous record.' );
$assert( ! str_contains( $service . $admin, '$wpdb->replace(' ), 'REPLACE may silently delete manager history.' );
$assert( str_contains( $service, 'function record_names' ), 'Manager listings still require one entity query per assignment.' );

foreach ( array( 'adam_manager_login', 'adam_manager_activate', 'adam_manager_request_reset', 'adam_manager_reset' ) as $action ) {
	$assert( str_contains( $portal, "wp_nonce_field( '{$action}' )" ), 'Missing public form CSRF token: ' . $action );
	$assert( str_contains( $portal, "check_admin_referer( '{$action}' )" ), 'Missing public form CSRF verification: ' . $action );
}

echo "Community Manager backend audit tests passed.\n";
