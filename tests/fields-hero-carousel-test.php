<?php
/**
 * Standalone checks for the Fields hero carousel.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$archive    = (string) file_get_contents( $root . '/templates/fields/archive.php' );
$carousel   = (string) file_get_contents( $root . '/includes/fields/class-hero-carousel.php' );
$controller = (string) file_get_contents( $root . '/includes/fields/admin/class-controller.php' );
$admin      = (string) file_get_contents( $root . '/admin/views/fields/hero.php' );
$script     = (string) file_get_contents( $root . '/assets/js/fields-public.js' );
$style      = (string) file_get_contents( $root . '/assets/css/fields-public.css' );
$shared     = (string) file_get_contents( $root . '/assets/css/public.css' );

$assert( ! str_contains( $archive, 'adam-field-submit-button' ), 'The redundant hero CTA remains.' );
foreach ( array( 'data-adam-fields-carousel', 'data-adam-fields-prev', 'data-adam-fields-next', 'data-adam-fields-indicator' ) as $contract ) {
	$assert( str_contains( $archive, $contract ), 'Missing public carousel contract: ' . $contract );
}
$assert( str_contains( $shared, '--adam-hero-title: #c9f6a1' ), 'The hero heading is not using the shared light ADAM green.' );
$assert( str_contains( $style, 'transition: opacity' ), 'The carousel needs a fade transition.' );
foreach ( array( "'mouseenter'", "'mouseleave'", "'touchstart'", "'touchend'", "'visibilitychange'" ) as $interaction ) {
	$assert( str_contains( $script, $interaction ), 'Missing carousel interaction: ' . $interaction );
}
$assert( str_contains( $controller, "'field-hero'" ) && str_contains( $controller, 'save_hero' ), 'The admin hero route is incomplete.' );
$assert( str_contains( $admin, 'Upload_Component::render' ), 'The hero manager must use the shared uploader.' );
$assert( str_contains( $admin, "'toggle_pattern'" ), 'Admin images need individual enable/disable controls.' );
$assert( str_contains( $carousel, "'approved_fields'" ) && str_contains( $carousel, 'minimum_featured' ), 'Approved-field covers need a configurable manual fallback.' );

echo "Fields hero carousel tests passed.\n";
