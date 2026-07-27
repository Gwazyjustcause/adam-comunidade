<?php
/**
 * Standalone architecture checks for public-submission UX and notifications.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$portal = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$email  = (string) file_get_contents( $root . '/includes/experience/class-email-service.php' );
$script = (string) file_get_contents( $root . '/assets/js/upload.js' );
$validation_script = (string) file_get_contents( $root . '/assets/js/experience.js' );
$style  = (string) file_get_contents( $root . '/assets/css/experience.css' );
$view   = (string) file_get_contents( $root . '/admin/views/forms/manager.php' );
$module = (string) file_get_contents( $root . '/includes/experience/class-module.php' );

$assert( ! str_contains( substr( $portal, strpos( $portal, 'public function submit()' ), 6500 ), 'wp_die(' ), 'Public validation must return to the form instead of rendering a blank error page.' );
$assert( str_contains( $portal, 'redirect_form_errors' ), 'Server validation must preserve scalar form state.' );
$assert( str_contains( $portal, 'adam-field-error' ), 'Validation errors must render beside their field.' );
$assert( str_contains( $portal, 'field_duplicate_error' ), 'Field submissions need duplicate protection.' );
$assert( str_contains( $portal, "'changes_requested','awaiting_information','under_review'" ), 'All active review states must block duplicates.' );
$assert( str_contains( $portal, "status = %s LIMIT 1" ), 'Published fields must be checked before accepting a duplicate.' );

$assert( str_contains( $script, 'data-adam-upload' ), 'Reusable upload enhancement is missing.' );
$assert( str_contains( $script, 'DataTransfer' ), 'Repeated file selections must be accumulated.' );
$assert( str_contains( $script, 'dragover' ) && str_contains( $script, "'drop'" ), 'Drag and drop support is missing.' );
$assert( str_contains( $validation_script, 'scrollIntoView' ), 'The form must scroll to its first error.' );
$assert( str_contains( $style, '.adam-portal-consent' ), 'Confirmation checkbox component styles are missing.' );
$assert( str_contains( $style, '.adam-portal-form > .adam-community-button' ), 'Accessible submit button override is missing.' );

foreach ( array( 'field_received', 'field_approved', 'field_rejected' ) as $template ) {
	$assert( str_contains( $email, "'{$template}'" ), 'Missing email template: ' . $template );
}
$assert( str_contains( $email, "Settings::get( 'contact_email' )" ), 'Community emails must use the configured official ADAM contact.' );
$assert( str_contains( $email, "get_option( 'admin_email'" ), 'Community emails must fall back to the WordPress administration email.' );
$assert( str_contains( $email, "apply_filters( 'adam_render_branded_email'" ), 'Community emails must offer the shared ADAM renderer integration.' );
$assert( str_contains( $view, 'email_templates[' ), 'Email subject, heading and body must be editable in wp-admin.' );
$assert( str_contains( $module, 'new Email_Service()' ), 'The shared submission email service must be injected.' );

echo "Public submission UX tests passed.\n";
