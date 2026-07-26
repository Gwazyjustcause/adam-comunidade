<?php
/**
 * Standalone regression test for the translation-safe startup sequence.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$main    = file_get_contents( $root . '/adam-comunidade.php' );
$loader  = file_get_contents( $root . '/includes/class-loader.php' );
$install = file_get_contents( $root . '/includes/class-install.php' );

$assert( false !== $main, 'Unable to read the plugin bootstrap.' );
$assert( false !== $loader, 'Unable to read the loader.' );
$assert( false !== $install, 'Unable to read the installer.' );

$assert(
	1 === preg_match( "/add_action\\(\\s*'init'\\s*,\\s*'adam_comunidade_boot'\\s*,\\s*0\\s*\\)/", $main ),
	'The plugin must boot on init at priority 0.'
);
$assert(
	0 === preg_match( "/add_action\\(\\s*'plugins_loaded'\\s*,\\s*'adam_comunidade_boot'/", $main ),
	'The plugin must not boot on plugins_loaded.'
);

$textdomain_position = strpos( $loader, 'load_plugin_textdomain(' );
$services_position   = strpos( $loader, "new Settings()" );
$assert( false !== $textdomain_position, 'The loader must explicitly load the text domain.' );
$assert( false !== $services_position, 'The loader service bootstrap was not found.' );
$assert(
	$textdomain_position < $services_position,
	'The text domain must load before any service can register translated labels.'
);

$assert(
	0 === preg_match( '/\\b(?:__|_e|_x|_n|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\\s*\\(/', $install ),
	'Activation must not call translation functions before init.'
);
$assert(
	! str_contains( $install, 'flush_rewrite_rules(' ),
	'Activation must defer rewrite flushing until a complete init request.'
);
$assert(
	! str_contains( $install, 'register_content()' ) && ! str_contains( $install, 'Managed_Pages::activate()' ),
	'Activation must not register translated content or create translated pages.'
);

foreach ( array( 'teams', 'fields', 'directory', 'experience' ) as $module ) {
	$source = file_get_contents( $root . '/includes/' . $module . '/class-module.php' );
	$assert( false !== $source, sprintf( 'Unable to read the %s module.', $module ) );
	$assert(
		! str_contains( $source, 'flush_rewrite_rules(' ),
		sprintf( 'The %s module must use the shared deferred rewrite flush.', $module )
	);
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	$handle = fopen( $file->getPathname(), 'rb' );
	$assert( false !== $handle, 'Unable to inspect ' . $file->getPathname() );
	$prefix = fread( $handle, 3 );
	fclose( $handle );

	$assert( "\xEF\xBB\xBF" !== $prefix, 'UTF-8 BOM would send output from ' . $file->getPathname() );
}

echo "i18n startup tests passed\n";
