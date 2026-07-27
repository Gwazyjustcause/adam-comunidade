<?php
/**
 * Regression checks for the task-focused administrator navigation.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$router       = (string) file_get_contents( $root . '/admin/class-router.php' );
$types        = (string) file_get_contents( $root . '/includes/directory/class-types.php' );
$directory    = (string) file_get_contents( $root . '/includes/directory/class-schema.php' );
$experience   = (string) file_get_contents( $root . '/includes/experience/class-module.php' );
$dashboard    = (string) file_get_contents( $root . '/admin/views/dashboard.php' );
$managed      = (string) file_get_contents( $root . '/includes/class-managed-pages.php' );
$notifications = (string) file_get_contents( $root . '/includes/experience/class-notifications.php' );
$experience_schema = (string) file_get_contents( $root . '/includes/experience/class-schema.php' );

$order = array(
	'adam-comunidade-dashboard',
	'adam-comunidade-moderation',
	'adam-comunidade-teams',
	'adam-comunidade-fields',
	'adam-comunidade-partners',
	'adam-comunidade-institutions',
	'adam-comunidade-news',
	'adam-comunidade-forms',
	'adam-comunidade-urls',
	'adam-comunidade-settings',
);
$position = -1;
foreach ( $order as $slug ) {
	$next = strpos( $router, "'{$slug}'" );
	$assert( false !== $next && $next > $position, "Missing or incorrectly ordered admin route: {$slug}" );
	$position = $next;
}

$assert( str_contains( $types, "'brand'         => __( 'Marca'" ), 'Marca must be a Partner category.' );
$assert( ! str_contains( $types, "'module_id'  => 'brands'" ), 'Brands must not remain a standalone directory module.' );
$assert( str_contains( $directory, 'migrate_brands_to_partners' ), 'Existing Brand records require a safe Partner migration.' );
$assert( str_contains( $directory, "'category'    => 'brand'" ), 'Migrated Brands must retain their distinction as a Partner category.' );
$assert( ! str_contains( $experience, '( new Calendar() )->register()' ), 'The standalone Calendar module must not be registered.' );
$assert( ! str_contains( $notifications, 'scan_calendar' ), 'Calendar notifications must not remain active without an Events owner.' );
$assert( str_contains( $notifications, "wp_clear_scheduled_hook( 'adam_comunidade_notification_scan' )" ), 'The retired Calendar schedule must be cleaned up.' );
$assert( ! str_contains( $experience_schema, 'CREATE TABLE {$calendar}' ), 'New installations must not create standalone Calendar storage.' );
$assert( ! str_contains( $dashboard, "'brands'" ), 'The dashboard must not expose Brand actions.' );
$assert( ! str_contains( $dashboard, "__( 'Marcas'" ), 'The dashboard must not expose Brand statistics.' );
$assert( ! str_contains( $managed, "'brands' => array(" ), 'A separate public Brands page must not be managed.' );

echo "Admin navigation tests passed.\n";
