<?php
/**
 * Administration and portal contracts for Community Manager Phase 2.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$admin         = (string) file_get_contents( $root . '/includes/managers/class-admin.php' );
$view          = (string) file_get_contents( $root . '/admin/views/managers.php' );
$service       = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$portal        = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$managed_pages = (string) file_get_contents( $root . '/includes/class-managed-pages.php' );
$settings      = (string) file_get_contents( $root . '/includes/class-settings.php' );
$admin_css     = (string) file_get_contents( $root . '/assets/css/admin.css' );
$public_css    = (string) file_get_contents( $root . '/assets/css/public.css' );
$admin_js      = (string) file_get_contents( $root . '/assets/js/admin.js' );
$public_js     = (string) file_get_contents( $root . '/assets/js/public.js' );

foreach ( array( 'cancel_invitation', 'reset_password', 'delete' ) as $method ) {
	$assert( str_contains( $admin, 'function ' . $method ), 'Missing manager lifecycle action: ' . $method );
}
foreach ( array( 'adam_manager_admin_cancel_invitation', 'adam_manager_admin_reset_password', 'adam_manager_admin_delete' ) as $action ) {
	$assert( str_contains( $admin, $action ) && str_contains( $view, $action ), 'Lifecycle action is not connected to its interface: ' . $action );
}
$assert( str_contains( $service, "array( 'release', 'transfer' )" ), 'Manager deletion does not support both assignment outcomes.' );
$assert( str_contains( $service, "'status' => 'deleted'" ) && str_contains( $service, "'password_hash' => ''" ), 'Manager deletion is not a credential-revoking soft delete.' );
$assert( ! preg_match( '/(?:Team|Field|Directory)_Repository[^;]+->delete\\s*\\(/', $service ), 'Deleting a manager may delete a Community entity.' );
$assert( str_contains( $service, "'START TRANSACTION'" ) && str_contains( $service, "'ROLLBACK'" ), 'Manager deletion is not atomic.' );
$assert( str_contains( $view, 'data-adam-confirm' ) && str_contains( $admin_js, 'window.confirm' ), 'Destructive actions do not require confirmation.' );

$assert( str_contains( $view, 'name="entities[]" multiple' ), 'Administrators cannot assign multiple organisations.' );
$assert( str_contains( $admin, "'last_login_desc'" ) && str_contains( $admin, 'LIMIT %d OFFSET %d' ), 'Manager sorting or pagination is incomplete.' );
$assert( str_contains( $view, 'assigned_manager_count' ), 'The interface cannot reveal multiple managers assigned to one organisation.' );
$assert( str_contains( $view, 'invitation_expires_at' ) && str_contains( $view, 'last_activity_at' ), 'Invitation expiry or future activity information is missing.' );
$assert( str_contains( $view, 'data-adam-close-details' ) && str_contains( $view, "'Cancelar'" ), 'Manager deletion cannot be cancelled explicitly.' );

$assert( str_contains( $managed_pages, "'manager_login'" ) && str_contains( $settings, "'manager_login_page_id'" ), 'The dedicated manager login is not managed in Endereços.' );
$assert( str_contains( $portal, "Managed_Pages::url( 'manager_login' )" ), 'The portal does not resolve the managed login page.' );
$assert( str_contains( $portal, 'function access_control' ) && str_contains( $portal, "array( 'dashboard', 'edit' )" ), 'Private portal routes do not fail closed.' );
$assert( str_contains( $portal, 'can_manage' ) && str_contains( $portal, 'verify_csrf' ), 'Organisation ownership or mutation CSRF checks are missing.' );
$assert( str_contains( $admin, 'Admin_Router::authorize()' ), 'Administrative manager actions do not enforce the plugin capability.' );

$assert( str_contains( $admin_css, '@media (max-width: 520px)' ) && str_contains( $public_css, '@media (max-width: 700px)' ), 'Manager screens are not responsive.' );
$assert( str_contains( $admin_css, 'prefers-color-scheme: dark' ) && str_contains( $public_css, 'prefers-color-scheme: dark' ), 'Manager screens do not account for dark mode.' );
$assert( str_contains( $admin_css, ':focus-visible' ) && str_contains( $public_css, ':focus-visible' ), 'Keyboard focus indicators are missing.' );
$assert( str_contains( $admin_js, "'A processar…'" ) && str_contains( $public_js, "'A processar…'" ), 'Loading feedback is missing.' );

foreach ( array( 'Delete Manager', 'Reset Password', 'Cancel Invitation', 'Loading...', 'Transfer ownership' ) as $english ) {
	$assert( ! str_contains( $view . $portal, $english ), 'English manager-interface text remains: ' . $english );
}

echo "Community Manager interface tests passed.\n";
