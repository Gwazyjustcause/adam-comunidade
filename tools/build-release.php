<?php
/**
 * Builds and validates the distributable ADAM Comunidade ZIP.
 *
 * Usage: php tools/build-release.php [output.zip]
 *
 * @package ADAM_Comunidade
 */

declare(strict_types=1);

$root    = dirname( __DIR__ );
$version = '6.30.3';
$output  = $argv[1] ?? ( $root . '/dist/adam-comunidade-' . $version . '.zip' );
$output  = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $output );
$prefix  = 'adam-comunidade/';

if ( ! class_exists( ZipArchive::class ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" );
	exit( 1 );
}

$runtime_roots = array( 'admin', 'assets', 'includes', 'languages', 'templates' );
$files         = array( 'adam-comunidade.php', 'uninstall.php' );

foreach ( $runtime_roots as $runtime_root ) {
	$directory = $root . DIRECTORY_SEPARATOR . $runtime_root;
	$iterator  = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $item ) {
		if ( $item->isFile() && ! $item->isLink() ) {
			$files[] = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $root ) + 1 ) );
		}
	}
}

$files = array_values( array_unique( $files ) );
sort( $files, SORT_STRING );

$required_files = array(
	'adam-comunidade.php',
	'includes/class-loader.php',
	'includes/teams/class-module.php',
	'includes/fields/class-module.php',
	'includes/directory/class-module.php',
	'includes/events/class-module.php',
	'includes/experience/class-module.php',
	'includes/managers/class-module.php',
);

foreach ( $required_files as $required_file ) {
	if ( ! in_array( $required_file, $files, true ) || ! is_readable( $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $required_file ) ) ) {
		fwrite( STDERR, 'Required release file is missing: ' . $required_file . "\n" );
		exit( 1 );
	}
}

$module_source = (string) file_get_contents( $root . '/includes/directory/class-module.php' );
if ( ! str_contains( $module_source, 'namespace ADAM\\Comunidade\\Directory;' ) || ! preg_match( '/final\s+class\s+Module\s+implements\s+Module_Interface/', $module_source ) ) {
	fwrite( STDERR, "Directory Module namespace or class declaration does not match the autoloader contract.\n" );
	exit( 1 );
}

$output_directory = dirname( $output );
if ( ! is_dir( $output_directory ) && ! mkdir( $output_directory, 0775, true ) && ! is_dir( $output_directory ) ) {
	fwrite( STDERR, 'Could not create output directory: ' . $output_directory . "\n" );
	exit( 1 );
}
if ( is_file( $output ) && ! unlink( $output ) ) {
	fwrite( STDERR, 'Could not replace output archive: ' . $output . "\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $output, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
	fwrite( STDERR, 'Could not create archive: ' . $output . "\n" );
	exit( 1 );
}
$zip->addEmptyDir( rtrim( $prefix, '/' ) );
foreach ( $files as $file ) {
	$source = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $file );
	$entry  = $prefix . $file;
	if ( ! $zip->addFile( $source, $entry ) ) {
		$zip->close();
		fwrite( STDERR, 'Could not add release file: ' . $file . "\n" );
		exit( 1 );
	}
}
$zip->close();

$audit = new ZipArchive();
if ( true !== $audit->open( $output ) ) {
	fwrite( STDERR, "Created archive could not be reopened.\n" );
	exit( 1 );
}
foreach ( $required_files as $required_file ) {
	$entry = $prefix . $required_file;
	if ( false === $audit->locateName( $entry, 0 ) ) {
		$audit->close();
		fwrite( STDERR, 'Created archive omitted required file: ' . $entry . "\n" );
		exit( 1 );
	}
}
$count = $audit->numFiles;
$audit->close();

echo sprintf( "Built %s with %d entries.\n", $output, $count );
