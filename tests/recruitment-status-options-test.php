<?php
/**
 * Recruitment status option and legacy compatibility checks.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$options = (string) file_get_contents( $root . '/includes/teams/class-options.php' );
$public  = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$manager = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$admin   = (string) file_get_contents( $root . '/admin/views/teams/editor.php' );
$schema  = (string) file_get_contents( $root . '/includes/teams/class-schema.php' );
$script  = (string) file_get_contents( $root . '/assets/js/experience.js' );

foreach ( array( "'recruiting'", "'limited'", "'not_recruiting'" ) as $canonical ) {
	$assert( str_contains( $options, $canonical ), "Missing canonical recruitment status: {$canonical}." );
}
$visible_options = substr( $options, (int) strpos( $options, 'public static function recruitment_statuses' ), (int) strpos( $options, 'public static function normalize_recruitment_status' ) - (int) strpos( $options, 'public static function recruitment_statuses' ) );
foreach ( array( "'open'", "'invite_only'", "'closed'" ) as $legacy ) {
	$assert( ! str_contains( $visible_options, $legacy ), "Legacy recruitment status {$legacy} must not render as a duplicate option." );
}
$assert( str_contains( $options, "'open'        => 'recruiting'" ) && str_contains( $options, "'invite_only' => 'limited'" ) && str_contains( $options, "'closed'      => 'not_recruiting'" ), 'Legacy stored statuses must map to canonical values.' );

foreach ( array( 'public' => $public, 'manager' => $manager, 'admin' => $admin ) as $surface => $source ) {
	$assert( str_contains( $source, 'Não indicado' ) && str_contains( $source, 'recruitment_statuses()' ), "The {$surface} Team form must render the four required choices." );
}
$assert( ! str_contains( $script, 'recruitment_status' ), 'JavaScript must not inject a second set of recruitment options.' );
$assert( str_contains( $schema, "SET recruitment_status = 'recruiting'" ) && str_contains( $schema, "SET recruitment_status = 'limited'" ) && str_contains( $schema, "SET recruitment_status = 'not_recruiting'" ), 'Existing legacy database values must be migrated.' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value ): mixed {
		return $value;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}
}
require_once $root . '/includes/teams/class-options.php';
$assert(
	array( 'recruiting', 'limited', 'not_recruiting' ) === array_keys( \ADAM\Comunidade\Teams\Options::recruitment_statuses() ),
	'The shared PHP source must expose exactly three selectable statuses after the explicit "Não indicado" choice.'
);
$assert( 'recruiting' === \ADAM\Comunidade\Teams\Options::normalize_recruitment_status( 'open' ), 'Legacy open status was not normalized.' );
$assert( 'limited' === \ADAM\Comunidade\Teams\Options::normalize_recruitment_status( 'invite_only' ), 'Legacy invite_only status was not normalized.' );
$assert( 'not_recruiting' === \ADAM\Comunidade\Teams\Options::normalize_recruitment_status( 'closed' ), 'Legacy closed status was not normalized.' );
$assert( '' === \ADAM\Comunidade\Teams\Options::normalize_recruitment_status( '' ), 'An empty recruitment status must remain "Não indicado".' );

echo "Recruitment status option tests passed.\n";
