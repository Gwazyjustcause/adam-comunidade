<?php
/**
 * Standalone checks for runtime-generated entity placeholders.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function __( string $text, string $domain = '' ): string {
	return $text;
}

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) ) ?? '';
}

function wp_strip_all_tags( string $text ): string {
	return strip_tags( $text );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

require_once dirname( __DIR__ ) . '/includes/class-placeholder-image.php';

use ADAM\Comunidade\Placeholder_Image;

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$field_cover = Placeholder_Image::cover( 'field', 'Campo do Pinhal' );
$team_cover  = Placeholder_Image::cover( 'team', 'Raposas do Centro' );
$team_avatar = Placeholder_Image::avatar( 'team', 'Raposas do Centro' );
$safe_cover  = Placeholder_Image::cover( 'field', '<script>alert(1)</script> Campo' );

foreach ( array( $field_cover, $team_cover, $team_avatar ) as $artwork ) {
	$assert( str_contains( $artwork, '<svg' ), 'Placeholder must be self-contained SVG.' );
	$assert( str_contains( $artwork, 'role="img"' ), 'Placeholder must expose an accessible image role.' );
	$assert( str_contains( $artwork, '<title>' ), 'Placeholder must expose an accessible title.' );
	$assert( str_contains( $artwork, 'adam-generated-placeholder' ), 'Shared placeholder CSS contract is missing.' );
	$assert( ! str_contains( $artwork, '<img' ), 'Placeholder must not depend on a committed media file.' );
	$assert( ! str_contains( $artwork, '<text' ), 'Placeholder artwork must not contain visible embedded text.' );
	$assert( ! str_contains( $artwork, 'Sem Fotografia' ), 'Placeholder artwork must not embed an empty-image message.' );
}

$assert( str_contains( $field_cover, '<title>Imagem ilustrativa de Campo do Pinhal</title>' ), 'Field cover needs an accessible title.' );
$assert( str_contains( $team_cover, '<title>Imagem ilustrativa de Raposas do Centro</title>' ), 'Team cover needs an accessible title.' );
$assert( str_contains( $team_avatar, '<title>Imagem ilustrativa de Raposas do Centro</title>' ), 'Team avatar needs an accessible title.' );
$assert( $field_cover !== $team_cover, 'Entity types must retain distinct graphical artwork.' );
$assert( ! str_contains( $safe_cover, '<script>' ), 'Entity names must be escaped inside generated SVG.' );

$integrations = array(
	'templates/fields/card.php'         => "Placeholder_Image::cover( 'field'",
	'templates/fields/single.php'       => "Placeholder_Image::cover( 'field'",
	'templates/fields/archive.php'      => "Placeholder_Image::cover( 'field'",
	'templates/teams/card.php'          => 'View::logo(',
	'templates/teams/single.php'        => 'View::logo(',
	'templates/teams/archive.php'       => "Placeholder_Image::cover( 'team'",
	'templates/directory/archive.php'   => 'Placeholder_Image::cover',
	'templates/directory/single.php'    => 'Placeholder_Image::avatar',
	'includes/directory/class-view.php' => 'Placeholder_Image::cover',
	'includes/teams/class-view.php'     => "Placeholder_Image::avatar( 'team'",
);

foreach ( $integrations as $file => $contract ) {
	$source = (string) file_get_contents( $root . '/' . $file );
	$assert( str_contains( $source, $contract ), 'Missing placeholder integration: ' . $file );
}

$types = (string) file_get_contents( $root . '/includes/class-placeholder-image.php' );
foreach ( array( 'field', 'team', 'partner', 'institution', 'brand' ) as $type ) {
	$assert( str_contains( $types, "'" . $type . "'" ), 'Unsupported placeholder entity type: ' . $type );
}

echo "Placeholder image tests passed.\n";
