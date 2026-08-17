<?php
/**
 * Static contract checks for Field Google Maps location handling.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = array() ) { return (string) $url; }
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url ) { return parse_url( $url ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;

		public function __construct( string $code, string $message ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

$validator = (string) file_get_contents( $root . '/includes/fields/class-validator.php' );
$forms     = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$portal    = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$manager   = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$service   = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$template  = (string) file_get_contents( $root . '/templates/fields/single.php' );
$admin     = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );

require_once $root . '/includes/fields/class-validator.php';

$valid_with_coordinates = ADAM\Comunidade\Fields\Validator::sanitize_maps_url( 'https://www.google.com/maps/@40.1234567,-8.7654321,15z' );
$assert( is_string( $valid_with_coordinates ), 'A valid Google Maps URL was rejected.' );
$coordinates = ADAM\Comunidade\Fields\Validator::extract_coordinates( $valid_with_coordinates );
$assert( is_array( $coordinates ) && 40.1234567 === $coordinates['latitude'] && -8.7654321 === $coordinates['longitude'], 'Explicit coordinates were not extracted correctly.' );
$short_url = ADAM\Comunidade\Fields\Validator::sanitize_maps_url( 'https://maps.app.goo.gl/example' );
$assert( is_string( $short_url ) && null === ADAM\Comunidade\Fields\Validator::extract_coordinates( $short_url ), 'A valid shortened URL without coordinates must remain usable.' );
$invalid_coordinates_url = ADAM\Comunidade\Fields\Validator::sanitize_maps_url( 'https://google.com/maps/@91,2' );
$assert( is_string( $invalid_coordinates_url ) && ADAM\Comunidade\Fields\Validator::extract_coordinates( $invalid_coordinates_url ) instanceof WP_Error, 'Out-of-range coordinates must be rejected.' );
$assert( ADAM\Comunidade\Fields\Validator::sanitize_maps_url( 'https://google.com.evil.test/maps/@1,2' ) instanceof WP_Error, 'Lookalike Google hostname must be rejected.' );

foreach ( array( 'sanitize_maps_url', 'extract_coordinates', 'invalid_maps_url', 'invalid_maps_coordinates' ) as $contract ) {
	$assert( str_contains( $validator, $contract ), 'Missing location validator contract: ' . $contract );
}

foreach ( array( 'google\\.[a-z]', 'maps.app.goo.gl', "'goo.gl'", "'/maps'" ) as $host_contract ) {
	$assert( str_contains( $validator, $host_contract ), 'Missing strict Google Maps host contract: ' . $host_contract );
}

foreach ( array( '/@(-?', 'query|destination|ll', '!3d', 'latitude', 'longitude' ) as $coordinate_contract ) {
	$assert( str_contains( $validator, $coordinate_contract ), 'Missing coordinate extraction contract: ' . $coordinate_contract );
}

$assert( str_contains( $forms, "'maps_url'" ) && str_contains( $forms, 'Localização do Campo no Google Maps' ), 'Public Field form is missing the Google Maps location field.' );
$assert( str_contains( $portal, 'Field_Validator::sanitize_maps_url' ), 'Public submission does not strictly validate the Google Maps URL.' );
$assert( str_contains( $manager, 'manager[maps_url]' ), 'Manager portal is missing the Google Maps location field.' );
$assert( str_contains( $service, 'Field_Validator::extract_coordinates' ), 'Manager revisions do not reconcile URL coordinates.' );
$assert( str_contains( $service, 'data[\'latitude\']  = null' ), 'Manager URL changes must clear stale coordinates when needed.' );
$assert( str_contains( $template, 'Validator::sanitize_maps_url' ), 'Frontend does not filter legacy/arbitrary maps URLs.' );
$assert( str_contains( $template, 'Obter direções no Google Maps' ) && str_contains( $template, 'noopener noreferrer' ), 'Directions action is missing secure Google Maps output.' );
$assert( str_contains( $template, 'if ( $field->address || $coordinates || $google_maps_url )' ), 'Location section must not render from an invalid URL alone.' );
$assert( str_contains( $admin, 'field[maps_url]' ) && str_contains( $admin, 'latitude' ) && str_contains( $admin, 'longitude' ), 'Admin must retain URL and manual coordinate controls.' );

echo "Field location tests passed.\n";
