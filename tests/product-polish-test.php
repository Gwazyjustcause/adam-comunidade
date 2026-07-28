<?php
/**
 * Product polish contracts for the release candidate.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static function ( string $path ) use ( $root ): string {
	$content = file_get_contents( $root . '/' . $path );
	if ( false === $content ) {
		throw new RuntimeException( 'Unable to read ' . $path );
	}
	return $content;
};
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$settings        = $read( 'includes/class-settings.php' );
$admin_assets    = $read( 'admin/class-assets.php' );
$public_assets   = $read( 'includes/class-assets.php' );
$admin_view      = $read( 'admin/views/managers.php' );
$manager_portal  = $read( 'includes/managers/class-portal.php' );
$admin_script    = $read( 'assets/js/admin.js' );
$fields_script   = $read( 'assets/js/fields-admin.js' );
$teams_script    = $read( 'assets/js/teams-admin.js' );
$directory_script = $read( 'assets/js/directory-admin.js' );
$public_script   = $read( 'assets/js/public.js' );
$upload          = $read( 'includes/uploads/class-component.php' );
$upload_script   = $read( 'assets/js/upload.js' );
$admin_styles    = $read( 'assets/css/admin.css' );
$public_styles   = $read( 'assets/css/public.css' );

foreach ( array( '#315c25', '#17241a', '#97c44b' ) as $brand_colour ) {
	$assert( str_contains( $settings, $brand_colour ), 'The default ADAM palette is incomplete: ' . $brand_colour );
}
$assert(
	str_contains( $public_assets, '.adam-comunidade,.adam-community,.adam-experience' ),
	'Configured appearance tokens do not override component-level defaults.'
);

$assert(
	str_contains( $admin_view, 'data-adam-entity-picker' )
	&& str_contains( $admin_view, 'type="checkbox" name="entities[]"' )
	&& str_contains( $admin_script, 'updateEntityPicker' ),
	'The manager assignment picker still depends on modifier-key multi-select.'
);
$assert(
	str_contains( $admin_script, 'HTMLDialogElement' )
	&& str_contains( $admin_script, 'adam-confirm-dialog' )
	&& str_contains( $admin_styles, '.adam-confirm-dialog' ),
	'Destructive-action confirmation is not presented as a polished accessible dialog.'
);
$assert(
	str_contains( $admin_script, 'window.adamConfirm = confirmation' )
	&& str_contains( $fields_script, 'window.adamConfirm' )
	&& str_contains( $teams_script, 'window.adamConfirm' )
	&& str_contains( $directory_script, 'window.adamConfirm' ),
	'Entity editors do not reuse the shared destructive-action confirmation.'
);
$assert(
	str_contains( $manager_portal, 'adam-manager-summary' )
	&& str_contains( $manager_portal, 'data-adam-save-state' )
	&& str_contains( $public_script, 'enhancePasswords' )
	&& str_contains( $public_styles, '.adam-manager-submit-bar' ),
	'The occasional-use Community Manager experience lacks progress or edit-state guidance.'
);
$assert(
	str_contains( $upload, 'data-adam-upload-move' )
	&& str_contains( $upload, 'data-adam-upload-live' )
	&& str_contains( $upload_script, 'moveItem' )
	&& str_contains( $upload_script, 'labels.duplicate' ),
	'The uploader lacks touch-friendly ordering or duplicate-file feedback.'
);
$assert(
	str_contains( $admin_assets, 'wp_localize_script' )
	&& str_contains( $public_assets, 'wp_localize_script' )
	&& ! str_contains( $admin_script, "'A processar…" )
	&& ! str_contains( $public_script, "'A processar…" ),
	'Interactive product copy is hardcoded instead of localized.'
);

echo "Product polish tests passed.\n";
