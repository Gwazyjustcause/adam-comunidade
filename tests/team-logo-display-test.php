<?php
/**
 * Team logo containment contract.
 *
 * Run with: php tests/team-logo-display-test.php
 *
 * @package ADAM_Comunidade
 */

$root = dirname( __DIR__ );
$read = static fn( string $file ): string => (string) file_get_contents( $root . '/' . $file );
$failures = array();
$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$view       = $read( 'includes/teams/class-view.php' );
$module     = $read( 'includes/teams/class-module.php' );
$public_css = $read( 'assets/css/public.css' );
$admin_css  = $read( 'assets/css/admin.css' );
$upload_css = $read( 'assets/css/upload.css' );

$assert( str_contains( $view, 'public static function logo(' ), 'Team View must expose the shared logo renderer.' );
$assert( str_contains( $view, "'adam-team-logo-contain'" ) && str_contains( $view, "'adam-team-logo__image'" ), 'The shared renderer must use the uncropped rendition and component image class.' );
$assert( str_contains( $module, "add_image_size( 'adam-team-logo-contain', 600, 600, false )" ), 'The logo rendition must preserve its original proportions.' );

foreach ( array( $public_css, $admin_css ) as $css ) {
	$assert( str_contains( $css, '.adam-team-logo {' ), 'The fixed square team-logo frame is missing.' );
	$assert( str_contains( $css, 'aspect-ratio: 1' ), 'The team-logo frame must remain square.' );
	$assert( str_contains( $css, 'object-fit: contain' ) && str_contains( $css, 'object-position: center' ), 'Team logos must be contained and centered.' );
}
$assert( str_contains( $upload_css, '.adam-upload--team-logo .adam-upload__preview' ) && str_contains( $upload_css, 'object-fit: contain' ), 'Team logo upload previews must use the same contained square behavior.' );

$call_sites = array(
	'templates/teams/card.php'                       => 'View::logo(',
	'templates/teams/single.php'                     => 'View::logo(',
	'templates/fields/single.php'                    => 'Team_View::logo(',
	'includes/teams/admin/class-team-list-table.php' => 'View::logo(',
	'includes/directory/class-components.php'        => 'Team_View::logo(',
	'includes/managers/class-portal.php'              => 'Team_View::logo(',
	'admin/views/dashboard.php'                       => 'Team_View::logo(',
);
foreach ( $call_sites as $file => $needle ) {
	$assert( str_contains( $read( $file ), $needle ), $file . ' must render primary team logos through the shared component.' );
}

$assert( str_contains( $read( 'admin/views/teams/editor.php' ), "'class' => 'adam-upload--team-logo'" ), 'The admin team editor must opt into the logo preview behavior.' );
$assert( str_contains( $read( 'includes/managers/class-portal.php' ), "'class' => 'adam-upload--team-logo'" ), 'Gestor de Comunidade must opt into the logo preview behavior.' );
$assert( str_contains( $read( 'includes/experience/class-portal.php' ), "? 'adam-upload--team-logo' : ''" ), 'Public team submission previews must opt into the logo behavior.' );

if ( $failures ) {
	fwrite( STDERR, "FAIL:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: team logo display contract.\n";
