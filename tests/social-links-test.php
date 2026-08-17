<?php
/**
 * Runtime and contract checks for Campo/Equipa social links.
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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { return $text; }
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;

		public function __construct( string $code, string $message ) {
			$this->code = $code;
			$this->message = $message;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

require_once $root . '/includes/class-social-links.php';

$valid = array(
	'website'   => 'https://www.adam-airsoft.pt/contacto?ref=public',
	'whatsapp'  => 'https://wa.me/351912345678',
	'instagram' => 'https://www.instagram.com/adam_airsoft/',
	'facebook'  => 'https://www.facebook.com/groups/adam-airsoft/',
);
foreach ( $valid as $platform => $url ) {
	$assert( $url === ADAM\Comunidade\Social_Links::sanitize( $platform, $url ), 'Valid ' . $platform . ' URL was rejected.' );
}
$assert( is_string( ADAM\Comunidade\Social_Links::sanitize( 'website', 'http://example.org' ) ), 'HTTP Website URL was rejected.' );
$assert( ADAM\Comunidade\Social_Links::sanitize( 'website', 'javascript:alert(1)' ) instanceof WP_Error, 'Javascript Website URL was accepted.' );
$assert( ADAM\Comunidade\Social_Links::sanitize( 'website', 'data:text/html,unsafe' ) instanceof WP_Error, 'Data Website URL was accepted.' );
$assert( ADAM\Comunidade\Social_Links::sanitize( 'website', 'ftp://example.org' ) instanceof WP_Error, 'Arbitrary Website protocol was accepted.' );
$assert( str_contains( ADAM\Comunidade\Social_Links::icon( 'website' ), '<circle' ), 'Website icon is missing the globe shape.' );
$empty_entity = (object) array( 'website' => '', 'whatsapp' => '', 'instagram' => '', 'facebook' => '' );
$assert( array() === ADAM\Comunidade\Social_Links::public_links( $empty_entity ), 'Empty public contact links should produce no links.' );
$website_entity = (object) array( 'website' => 'https://example.org', 'whatsapp' => '', 'instagram' => '', 'facebook' => '' );
$assert( array( 'website' => 'https://example.org' ) === ADAM\Comunidade\Social_Links::public_links( $website_entity ), 'Website-only public links are not preserved.' );
$combined_entity = (object) array( 'website' => 'https://example.org', 'whatsapp' => 'https://wa.me/351912345678', 'instagram' => 'https://instagram.com/adam', 'facebook' => 'https://facebook.com/adam' );
$combined_links = ADAM\Comunidade\Social_Links::public_links( $combined_entity );
$assert( array( 'website', 'whatsapp', 'instagram', 'facebook' ) === array_keys( $combined_links ), 'Website and social links are not ordered consistently.' );
$removed_entity = (object) array( 'website' => '', 'whatsapp' => '', 'instagram' => '', 'facebook' => '' );
$assert( array() === ADAM\Comunidade\Social_Links::public_links( $removed_entity ), 'Removing Website and socials should hide the public section.' );
$assert( is_string( ADAM\Comunidade\Social_Links::sanitize( 'whatsapp', 'https://chat.whatsapp.com/invite' ) ), 'WhatsApp group URL was rejected.' );
$assert( is_string( ADAM\Comunidade\Social_Links::sanitize( 'whatsapp', 'https://www.whatsapp.com/channel/123' ) ), 'WhatsApp community URL was rejected.' );
$assert( ADAM\Comunidade\Social_Links::sanitize( 'instagram', 'https://instagram.com.example.com/profile' ) instanceof WP_Error, 'Instagram lookalike hostname was accepted.' );
$assert( ADAM\Comunidade\Social_Links::sanitize( 'facebook', 'https://facebook.com.example.com/page' ) instanceof WP_Error, 'Facebook lookalike hostname was accepted.' );
$assert( ADAM\Comunidade\Social_Links::sanitize( 'whatsapp', 'https://example.com/contact' ) instanceof WP_Error, 'Non-WhatsApp hostname was accepted.' );

$fields_schema = (string) file_get_contents( $root . '/includes/fields/class-schema.php' );
$teams_schema  = (string) file_get_contents( $root . '/includes/teams/class-schema.php' );
$forms         = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$manager      = (string) file_get_contents( $root . '/includes/managers/class-policy.php' );
$fields_view  = (string) file_get_contents( $root . '/templates/fields/single.php' );
$teams_view   = (string) file_get_contents( $root . '/templates/teams/single.php' );
$fields_admin = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );
$teams_admin  = (string) file_get_contents( $root . '/admin/views/teams/editor.php' );
$public_css   = (string) file_get_contents( $root . '/assets/css/public.css' );
$fields_css   = (string) file_get_contents( $root . '/assets/css/fields-public.css' );
$teams_css    = (string) file_get_contents( $root . '/assets/css/teams-public.css' );

foreach ( array( 'whatsapp varchar', "VERSION = '6.3.0'" ) as $contract ) {
	$assert( str_contains( $fields_schema, $contract ), 'Missing Field schema contract: ' . $contract );
}
foreach ( array( 'whatsapp varchar', "VERSION = '6.2.0'" ) as $contract ) {
	$assert( str_contains( $teams_schema, $contract ), 'Missing Team schema contract: ' . $contract );
}
$assert( substr_count( $forms, "'whatsapp'" ) >= 2, 'Public forms are missing optional WhatsApp fields.' );
$assert( substr_count( $forms, "'website' => \$this->field( 'website'" ) >= 1, 'Public forms are missing the existing optional Website field.' );
$assert( str_contains( $forms, "'instagram' => \$this->field( 'instagram', 'Instagram', 'url', false" ), 'Field public form is missing optional Instagram.' );
$assert( str_contains( $forms, "'facebook'  => \$this->field( 'facebook', 'Facebook', 'url', false" ), 'Field public form is missing optional Facebook.' );
$assert( str_contains( $manager, "'whatsapp'" ), 'Manager revision policy is missing WhatsApp.' );
foreach ( array( $fields_view, $teams_view ) as $template ) {
	$assert( str_contains( $template, 'social_links' ) && str_contains( $template, 'Social_Links::icon' ), 'Public template is missing conditional social icons.' );
	$assert( str_contains( $template, 'noopener noreferrer' ) && str_contains( $template, 'aria-label' ), 'Social links are missing safe and accessible attributes.' );
}
$assert( str_contains( $fields_view, "'website' => 'Website'" ) && str_contains( $teams_view, "'website'   => 'Website'" ), 'Public templates are missing the Website label.' );
$assert( str_contains( $fields_view, 'target="_blank" rel="noopener noreferrer"' ) && str_contains( $teams_view, 'target="_blank" rel="noopener noreferrer"' ), 'Website/social links are missing safe new-tab attributes.' );
$assert( str_contains( $fields_view, 'adam-field-hero__details' ) && str_contains( $fields_view, 'adam-hero-social-links' ), 'Field public contacts are not rendered in the hero.' );
$assert( str_contains( $teams_view, 'adam-team-hero__details' ) && str_contains( $teams_view, 'adam-hero-social-links' ), 'Team public contacts are not rendered in the hero.' );
$assert( ! str_contains( $fields_view, 'adam-social-section' ) && ! str_contains( $teams_view, 'adam-social-section' ), 'The standalone public contacts section was not removed.' );
$assert( ! str_contains( $fields_view, 'Redes e contactos' ) && ! str_contains( $teams_view, 'Redes e contactos' ), 'The standalone public contacts heading remains in a single template.' );
$assert( str_contains( $public_css, 'color: #b7dc59' ) && str_contains( $public_css, 'height: 52px' ) && str_contains( $public_css, 'height: 28px' ), 'Hero social icons do not have the required contrast and size.' );
$assert( str_contains( $public_css, 'gap: 16px' ) && str_contains( $public_css, 'drop-shadow' ), 'Hero social icon spacing/hover treatment is missing.' );
$assert( str_contains( $public_css, 'align-self: center' ) && str_contains( $fields_css, 'align-self: flex-start' ) && str_contains( $teams_css, 'align-self: flex-start' ), 'Hero social icons are missing desktop centering/mobile start alignment.' );
$assert( str_contains( $fields_admin, "'whatsapp' => 'WhatsApp'" ), 'Field admin editor is missing WhatsApp.' );
$assert( str_contains( $teams_admin, "'whatsapp' => 'WhatsApp'" ), 'Team admin editor is missing WhatsApp.' );

echo "Social links tests passed.\n";
