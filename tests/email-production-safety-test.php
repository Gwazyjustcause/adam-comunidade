<?php
/**
 * Standalone contracts for production-safe lifecycle emails.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$service  = (string) file_get_contents( $root . '/includes/experience/class-email-service.php' );
$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
$portal   = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );

foreach ( array( 'normalize_context', 'string_value', 'public_url', 'is_public_email', 'contains_development_value' ) as $guard ) {
	$assert( str_contains( $service, 'function ' . $guard ), 'Missing email production guard: ' . $guard );
}
$assert( str_contains( $service, "Settings::get( 'contact_email' )" ), 'The official ADAM contact must come from Community settings.' );
$assert( str_contains( $service, "get_option( 'admin_email'" ), 'The WordPress administration email fallback is missing.' );
$assert( ! str_contains( $service, "get_option( 'adam_membership_email_from_address'" ), 'The Members development sender can still leak into Community emails.' );
foreach ( array( 'wpengine\\.local', 'dev-email', 'localhost', 'example\\.' ) as $development_marker ) {
	$assert( str_contains( $service, $development_marker ), 'Missing development marker protection: ' . $development_marker );
}
$assert( str_contains( $service, 'set_error_handler' ) && str_contains( $service, 'render_shared_layout' ), 'Third-party renderer warnings are not isolated from email output.' );
$assert( str_contains( $settings, "'contact_email'" ) && str_contains( $settings, 'render_email' ), 'The official contact email setting is incomplete.' );
$assert( str_contains( $portal, "'' !== \$admin_note ? \$admin_note" ), 'The rejection note needs a non-null fallback before rendering.' );

echo "Email production safety tests passed.\n";
