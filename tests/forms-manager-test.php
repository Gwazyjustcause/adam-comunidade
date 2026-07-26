<?php
/**
 * Standalone architecture checks for the shared public form manager.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$manager    = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$controller = (string) file_get_contents( $root . '/includes/forms/class-admin-controller.php' );
$view       = (string) file_get_contents( $root . '/admin/views/forms/manager.php' );
$portal     = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$module     = (string) file_get_contents( $root . '/includes/experience/class-module.php' );

foreach ( array( "'field'", "'team'", "'partner'", "'institution'" ) as $type ) {
	$assert( str_contains( $manager, $type ), 'Missing shared form type: ' . $type );
}

foreach ( array( 'label', 'description', 'help_text', 'placeholder', 'type', 'visible', 'required', 'accept', 'max_files', 'max_size_mb' ) as $setting ) {
	$assert( str_contains( $manager, "'{$setting}'" ), 'Missing configurable field property: ' . $setting );
}

$assert( str_contains( $controller, "register_page(\n\t\t\t'forms'" ), 'Form manager must register through the central admin router.' );
$assert( str_contains( $view, 'data-adam-sortable' ), 'Form fields must be reorderable.' );
$assert( str_contains( $portal, "foreach ( \$form['fields']" ), 'The public renderer must use the shared form schema.' );
$assert( str_contains( $module, 'new Portal( $forms, $emails )' ), 'The form manager and email service must be injected into the public portal.' );

foreach ( array( 'Admin_Tools', 'Import_Wizard', 'new Analytics()', 'new Health()' ) as $removed_service ) {
	$assert( ! str_contains( $module, $removed_service ), 'Removed developer service is still registered: ' . $removed_service );
}

echo "Forms manager tests passed.\n";
