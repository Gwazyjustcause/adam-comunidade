<?php
/**
 * Runs plugin initialization from an extracted release on a case-sensitive path.
 *
 * Usage: php tests/release-activation-smoke.php path/to/adam-comunidade
 *
 * @package ADAM_Comunidade
 */

declare(strict_types=1);

$plugin_root = isset( $argv[1] ) ? rtrim( str_replace( '\\', '/', $argv[1] ), '/' ) : '';
if ( ! is_file( $plugin_root . '/adam-comunidade.php' ) ) {
	fwrite( STDERR, "Extracted plugin path is required.\n" );
	exit( 1 );
}
if ( is_file( $plugin_root . '/includes/Directory/class-module.php' ) ) {
	fwrite( STDERR, "Activation path is not enforcing case-sensitive directory lookup.\n" );
	exit( 1 );
}

define( 'ABSPATH', $plugin_root . '/wordpress/' );
$GLOBALS['adam_activation_actions'] = array();

function plugin_dir_path( string $file ): string { return rtrim( str_replace( '\\', '/', dirname( $file ) ), '/' ) . '/'; }
function plugin_dir_url( string $file ): string { unset( $file ); return 'https://example.test/wp-content/plugins/adam-comunidade/'; }
function plugin_basename( string $file ): string { return 'adam-comunidade/' . basename( $file ); }
function register_activation_hook( string $file, mixed $callback ): void { unset( $file, $callback ); }
function add_action( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void { unset( $callback, $priority, $accepted_args ); $GLOBALS['adam_activation_actions'][] = $hook; }
function add_filter( string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1 ): void { unset( $hook, $callback, $priority, $accepted_args ); }
function load_plugin_textdomain( string $domain, bool $deprecated = false, string $path = '' ): bool { unset( $domain, $deprecated, $path ); return true; }
function is_admin(): bool { return false; }
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed { unset( $args ); return 'adam_comunidade_module_enabled' === $hook ? false : $value; }
function do_action( string $hook, mixed ...$args ): void { unset( $hook, $args ); }
function sanitize_key( string $key ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', $key ) ?? '' ); }

require $plugin_root . '/adam-comunidade.php';
adam_comunidade_boot();

$manager = ADAM\Comunidade\Loader::instance()->service( 'modules' );
if ( ! $manager || ! isset( $manager->all()['directory'] ) || ! class_exists( ADAM\Comunidade\Directory\Module::class ) ) {
	fwrite( STDERR, "Directory Module did not initialize from the extracted release.\n" );
	exit( 1 );
}

echo "Release activation smoke test passed on a case-sensitive path.\n";
