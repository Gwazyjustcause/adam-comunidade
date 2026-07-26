<?php
/**
 * Standalone checks for the focused PT-PT administrator experience.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$admin_source = '';
$iterator     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/admin' ) );
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'php' === $file->getExtension() ) {
		$admin_source .= (string) file_get_contents( $file->getPathname() );
	}
}

foreach (
	array(
		"'Add Team'",
		"'Team Name'",
		"'Published'",
		"'Draft'",
		"'Hidden'",
		"'Status'",
		"'Members'",
		"'Last Updated'",
		"'Label'",
		"'Icon'",
		"'Order'",
		"'Visible'",
		"'Search Teams'",
		"'Search Fields'",
	) as $english_admin_string
) {
	$assert( ! str_contains( $admin_source, $english_admin_string ), 'English administrator string remains: ' . $english_admin_string );
}

$module = (string) file_get_contents( $root . '/includes/experience/class-module.php' );
foreach ( array( 'Admin_Tools', 'Import_Wizard', 'new Analytics()', 'new Health()' ) as $removed_service ) {
	$assert( ! str_contains( $module, $removed_service ), 'Removed developer service is still registered: ' . $removed_service );
}

echo "Admin UX tests passed.\n";
