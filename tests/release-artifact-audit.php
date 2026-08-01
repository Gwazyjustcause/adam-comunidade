<?php
/**
 * Audits the exact contents and casing of a distributable plugin ZIP.
 *
 * Usage: php tests/release-artifact-audit.php path/to/adam-comunidade.zip
 *
 * @package ADAM_Comunidade
 */

declare(strict_types=1);

$zip_path = $argv[1] ?? '';
if ( ! is_file( $zip_path ) ) {
	fwrite( STDERR, "Release ZIP path is required.\n" );
	exit( 1 );
}

$required = array(
	'adam-comunidade/adam-comunidade.php',
	'adam-comunidade/includes/class-loader.php',
	'adam-comunidade/includes/teams/class-module.php',
	'adam-comunidade/includes/fields/class-module.php',
	'adam-comunidade/includes/directory/class-module.php',
	'adam-comunidade/includes/events/class-module.php',
	'adam-comunidade/includes/experience/class-module.php',
	'adam-comunidade/includes/managers/class-module.php',
);

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	fwrite( STDERR, "Could not open release ZIP.\n" );
	exit( 1 );
}

$entries = array();
for ( $index = 0; $index < $zip->numFiles; ++$index ) {
	$name = $zip->getNameIndex( $index );
	if ( false === $name || str_contains( $name, '\\' ) || ! str_starts_with( $name, 'adam-comunidade/' ) ) {
		fwrite( STDERR, "Invalid ZIP entry or root directory.\n" );
		exit( 1 );
	}
	$entries[] = $name;
}

foreach ( $required as $entry ) {
	if ( ! in_array( $entry, $entries, true ) ) {
		fwrite( STDERR, "Missing exact-case ZIP entry: {$entry}\n" );
		exit( 1 );
	}
}

$directory_module = $zip->getFromName( 'adam-comunidade/includes/directory/class-module.php' );
$zip->close();

if (
	false === $directory_module
	|| ! str_contains( $directory_module, 'namespace ADAM\\Comunidade\\Directory;' )
	|| ! preg_match( '/final\s+class\s+Module\s+implements\s+Module_Interface/', $directory_module )
) {
	fwrite( STDERR, "Directory module declaration in ZIP is invalid.\n" );
	exit( 1 );
}

echo "Release artifact audit passed; Directory Module is present with exact casing.\n";
