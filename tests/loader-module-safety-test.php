<?php
/**
 * Loader module path and failure-isolation checks.
 *
 * @package ADAM_Comunidade
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
define( 'ABSPATH', $root . '/' );
define( 'ADAM_COMUNIDADE_PATH', $root . '/' );

$GLOBALS['adam_loader_test_actions'] = array();
function add_action( string $hook, mixed $callback ): void {
	$GLOBALS['adam_loader_test_actions'][] = $hook;
}
function sanitize_key( string $key ): string {
	return strtolower( preg_replace( '/[^a-z0-9_-]/', '', $key ) ?? '' );
}

require_once $root . '/includes/class-module-interface.php';
require_once $root . '/includes/class-module-manager.php';
require_once $root . '/includes/class-loader.php';

use ADAM\Comunidade\Directory\Module as Directory_Module;
use ADAM\Comunidade\Loader;
use ADAM\Comunidade\Module_Manager;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

Loader::register_autoloader();
$assert( class_exists( Directory_Module::class ), 'The exact Directory Module namespace must autoload.' );

$loader_reflection = new ReflectionClass( Loader::class );
$class_file        = $loader_reflection->getMethod( 'class_file' );
$expected          = $root . '/includes/directory/class-module.php';
$assert( str_replace( '\\', '/', (string) $class_file->invoke( null, Directory_Module::class ) ) === str_replace( '\\', '/', $expected ), 'The Directory Module path does not match Linux filename casing.' );

$loader   = $loader_reflection->newInstanceWithoutConstructor();
$services = $loader_reflection->getProperty( 'services' );
$services->setValue( $loader, array( 'modules' => new Module_Manager() ) );
$register = $loader_reflection->getMethod( 'register_module' );
$register->invoke( $loader, 'ADAM\\Comunidade\\Missing\\Module' );

$failures = $loader_reflection->getProperty( 'module_failures' )->getValue( $loader );
$assert( 1 === count( $failures ), 'A missing module must be recorded instead of throwing a fatal error.' );
$assert( str_contains( $failures[0], 'includes/missing/class-module.php' ), 'The module failure must report the expected autoload path.' );
$assert( in_array( 'admin_notices', $GLOBALS['adam_loader_test_actions'], true ), 'A missing module must register an admin notice.' );

echo "Loader module safety tests passed.\n";
