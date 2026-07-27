<?php
/**
 * Public privacy contracts shared by every directory entity.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$team       = (string) file_get_contents( $root . '/templates/teams/single.php' );
$field      = (string) file_get_contents( $root . '/templates/fields/single.php' );
$directory  = (string) file_get_contents( $root . '/templates/directory/single.php' );
$view       = (string) file_get_contents( $root . '/includes/directory/class-view.php' );
$rest       = (string) file_get_contents( $root . '/includes/directory/class-rest-api.php' );
$api_v2     = (string) file_get_contents( $root . '/includes/experience/class-api-v2.php' );
$privacy    = (string) file_get_contents( $root . '/includes/class-public-privacy.php' );

foreach ( array( $team, $directory, $view, $rest ) as $public_source ) {
	foreach ( array( 'mailto:', 'tel:' ) as $direct_link ) {
		$assert( ! str_contains( $public_source, $direct_link ), 'A direct public contact link remains: ' . $direct_link );
	}
}
foreach ( array( "'email'             =>", "'phone'             =>" ) as $private_api_field ) {
	$assert( ! str_contains( $rest, $private_api_field ), 'The public REST API still exposes ' . $private_api_field );
}
$assert( ! str_contains( $team, '$adam_contacts' ) && ! str_contains( $team, "'Contactos'" ), 'The Team Contactos section remains public.' );
$assert( ! str_contains( $directory, 'View::contacts' ) && ! str_contains( $directory, "'Contact'" ), 'A shared entity Contact section remains public.' );
$assert( str_contains( $team, 'Public_Privacy::public_links' ) && str_contains( $directory, 'Public_Privacy::public_links' ), 'Public profiles are not using the shared privacy policy.' );
$assert( str_contains( $rest, 'Public_Privacy::without_direct_contacts' ), 'The public REST API is not protected by the shared privacy policy.' );
$assert( str_contains( $api_v2, 'Public_Privacy::without_direct_contacts' ), 'API v2 is not protected by the shared privacy policy.' );
foreach ( array( "'email'", "'contact_email'", "'phone'", "'whatsapp'" ) as $private_field ) {
	$assert( str_contains( $privacy, $private_field ), 'The privacy denylist is missing ' . $private_field );
}
foreach ( array( '$has_description', '$has_benefits', '$has_brand_information', '$has_notes', '$has_location', '$public_links', '$gallery', '$connected' ) as $dynamic_condition ) {
	$assert( str_contains( $directory, $dynamic_condition ), 'Missing adaptive directory condition: ' . $dynamic_condition );
}
$assert( ! str_contains( $directory, 'Event integration will become available' ), 'A future-feature placeholder still renders publicly.' );
$assert( str_contains( $field, 'if ( $gallery )' ) && str_contains( $team, 'if ( $adam_gallery )' ), 'Empty image viewers should not be rendered.' );

echo "Public entity privacy tests passed.\n";
