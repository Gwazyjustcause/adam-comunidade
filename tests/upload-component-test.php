<?php
/**
 * Standalone architecture checks for the reusable ADAM Upload component.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$component = (string) file_get_contents( $root . '/includes/uploads/class-component.php' );
$script    = (string) file_get_contents( $root . '/assets/js/upload.js' );
$style     = (string) file_get_contents( $root . '/assets/css/upload.css' );
$portal    = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$fields    = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );
$teams     = (string) file_get_contents( $root . '/admin/views/teams/editor.php' );
$directory = (string) file_get_contents( $root . '/admin/views/directory/editor.php' );

foreach ( array( "'mode'", "'kind'", "'multiple'", "'max'", "'items'" ) as $configuration ) {
	$assert( str_contains( $component, $configuration ), 'Missing uploader configuration: ' . $configuration );
}

foreach ( array( 'data-adam-upload-add', 'data-adam-upload-remove', 'data-adam-upload-replace', 'data-adam-upload-progress' ) as $contract ) {
	$assert( str_contains( $component, $contract ), 'Missing uploader markup contract: ' . $contract );
}

$assert( str_contains( $script, 'URL.createObjectURL' ), 'Images need immediate local previews.' );
$assert( str_contains( $script, 'DataTransfer' ), 'Local selections must remain synchronized after removal and ordering.' );
$assert( str_contains( $script, "'dragover'" ) && str_contains( $script, "'drop'" ), 'Drag and drop support is missing.' );
$assert( str_contains( $script, 'data-adam-upload-replace' ), 'Individual replacement support is missing.' );
$assert( str_contains( $style, '.adam-upload__actions' ), 'Hover actions are missing.' );
$assert( str_contains( $style, '.adam-upload__document-icon' ), 'Document cards are missing.' );

foreach ( array( $portal, $fields, $teams, $directory ) as $integration ) {
	$assert( str_contains( $integration, 'Upload_Component::render' ), 'An upload surface is not using the shared component.' );
}

foreach ( array( $fields, $teams, $directory ) as $admin_integration ) {
	$assert( ! str_contains( $admin_integration, 'data-adam-select-media' ), 'A legacy admin uploader remains in use.' );
}

echo "Upload component tests passed.\n";
