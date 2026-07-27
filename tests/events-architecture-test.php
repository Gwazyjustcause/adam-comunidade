<?php
/**
 * Static contracts for the cross-plugin Events architecture.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$members = dirname( $root ) . '/adam-members';
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$main = (string) file_get_contents( $root . '/adam-comunidade.php' );
$loader = (string) file_get_contents( $root . '/includes/class-loader.php' );
$migration = (string) file_get_contents( $root . '/includes/events/class-migration.php' );
$api = (string) file_get_contents( $root . '/includes/events/class-api.php' );
$router = (string) file_get_contents( $root . '/includes/events/class-router.php' );
$rest = (string) file_get_contents( $root . '/includes/events/class-rest-api.php' );
$module = (string) file_get_contents( $root . '/includes/events/class-module.php' );
$admin_router = (string) file_get_contents( $root . '/admin/class-router.php' );
$builder = (string) file_get_contents( $root . '/includes/experience/class-builder.php' );
$api_v2 = (string) file_get_contents( $root . '/includes/experience/class-api-v2.php' );

$assert( str_contains( $main, 'function adam_comunidade_events()' ), 'Stable PHP API facade is missing.' );
$assert( str_contains( $loader, 'new Events\\Module()' ), 'Events is not owned by ADAM Comunidade.' );
$assert( str_contains( $migration, "'adam_membership_events'" ), 'Legacy events are not imported.' );
$assert( str_contains( $migration, 'legacy_events_preserved' ), 'Rollback data preservation is not documented in the migration.' );
$assert( str_contains( $api, 'get_events' ) && str_contains( $api, 'get_event' ) && str_contains( $api, 'register_attendee' ) && str_contains( $api, 'attendance_status' ), 'Shared API contract is incomplete.' );
$assert( str_contains( $router, "'^eventos/?$'" ) && str_contains( $router, "'^eventos/check-in/([^/]+)/?$'" ), 'Legacy public URLs are not preserved.' );
$assert( str_contains( $rest, "'/events'" ) && str_contains( $rest, "'/events/(?P<id>\\d+)'" ), 'REST event endpoints are missing.' );
$assert( str_contains( $module, "'adam_bot_dynamic_events'" ) && str_contains( $module, "'adam_bot_knowledge_event_items'" ), 'ADAM Bot integration is missing.' );
$assert( str_contains( $module, "'adam_comunidade_search_results'" ), 'Universal search integration is missing.' );
$assert( str_contains( $admin_router, "'adam-comunidade-events'" ), 'Events is missing from Community administration.' );
$assert( str_contains( $builder, 'event_cards' ), 'Community homepage Events widget is missing.' );
$assert( str_contains( $api_v2, "'events'" ), 'Events is missing from the platform API v2.' );

if ( is_dir( $members ) ) {
	$repository = (string) file_get_contents( $members . '/src/Event/EventRepository.php' );
	$frontend = (string) file_get_contents( $members . '/src/Event/EventFrontend.php' );
	$plugin = (string) file_get_contents( $members . '/src/Core/Plugin.php' );
	$assert( str_contains( $repository, "\\adam_comunidade_events()->get_events" ), 'ADAM Sócios does not consume canonical event listings.' );
	$assert( str_contains( $repository, "\\adam_comunidade_events()->save_event" ), 'ADAM Sócios event writes do not use the shared API.' );
	$assert( str_contains( $frontend, 'adam_comunidade_events_render_checkin' ), 'QR check-in is not bridged to ADAM Sócios.' );
	$assert( str_contains( $frontend, 'adam_comunidade_events_register_attendee' ), 'Member registrations are not bridged.' );
	$assert( str_contains( $frontend, 'adam_comunidade_events_attendance_status' ), 'Attendance lookup is not bridged.' );
	$assert( str_contains( $plugin, "! function_exists( '\\adam_comunidade_events' )" ), 'Legacy Events admin is not disabled when Community owns Events.' );
}

echo "Events architecture tests passed.\n";
