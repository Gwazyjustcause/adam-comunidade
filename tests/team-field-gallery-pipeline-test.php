<?php
/**
 * End-to-end source contracts for Team and Field public gallery persistence.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$forms            = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$component        = (string) file_get_contents( $root . '/includes/uploads/class-component.php' );
$upload_script    = (string) file_get_contents( $root . '/assets/js/upload.js' );
$handler          = (string) file_get_contents( $root . '/includes/uploads/class-handler.php' );
$portal           = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$team_options     = (string) file_get_contents( $root . '/includes/teams/class-options.php' );
$team_validator   = (string) file_get_contents( $root . '/includes/teams/class-validator.php' );
$team_template    = (string) file_get_contents( $root . '/templates/teams/single.php' );
$field_template   = (string) file_get_contents( $root . '/templates/fields/single.php' );
$field_controller = (string) file_get_contents( $root . '/includes/fields/admin/class-controller.php' );
$team_editor      = (string) file_get_contents( $root . '/admin/views/teams/editor.php' );
$field_editor     = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );
$manager_portal   = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$manager_service  = (string) file_get_contents( $root . '/includes/managers/class-service.php' );

foreach ( array( 'team_photos', 'field_photos' ) as $field ) {
	$assert( str_contains( $forms, "'{$field}'" ) && str_contains( $forms, "'.jpg,.jpeg,.png,.webp', 5" ), "The {$field} public uploader must accept up to five images." );
}
$assert( str_contains( $component, 'data-adam-upload-input' ) && str_contains( $upload_script, 'DataTransfer' ) && str_contains( $upload_script, 'URL.createObjectURL' ), 'Public gallery files must be selectable and previewable before submission.' );
$assert( str_contains( $handler, 'media_handle_upload' ) && str_contains( $handler, 'upload_many' ), 'Gallery files must be stored through the WordPress Media Library.' );

$assert( str_contains( $portal, "'team_photos' === \$key" ) && str_contains( $portal, "\$payload['gallery']" ), 'Team attachment IDs must be stored in the submission payload.' );
$assert( str_contains( $portal, "'field_photos' === \$key" ) && str_contains( $portal, "\$payload['gallery_ids']" ), 'Field attachment IDs must be stored in the submission payload.' );
$assert( str_contains( $portal, "Options::decode_ids( \$input['gallery']" ), 'Team approval must normalize and retain submitted attachment IDs.' );
$assert( str_contains( $portal, "array_key_exists( 'gallery_ids', \$payload )" ) && str_contains( $portal, 'sync_gallery' ), 'Field approval must persist the complete submitted gallery, including an intentional empty gallery.' );

$assert( str_contains( $team_options, 'function decode_ids' ) && str_contains( $team_validator, 'Options::decode_ids' ), 'Team galleries need attachment-ID-specific normalization before database storage.' );
$assert( str_contains( $team_validator, "empty( \$input['gallery_reviewed'] )" ), 'A Team edit that did not review the gallery must preserve existing IDs.' );
$assert( str_contains( $team_editor, 'team[gallery_reviewed]' ), 'The Team editor must distinguish an intentional gallery change from a missing field.' );
$assert( str_contains( $field_editor, 'field[gallery_reviewed]' ) && str_contains( $field_controller, "! empty( \$input['gallery_reviewed'] )" ), 'The Field editor must not erase its gallery when gallery controls were not submitted.' );
$assert( str_contains( $manager_portal, 'keep_gallery_ids[]' ) && str_contains( $manager_service, "array_merge( (array) \$current, \$payload )" ), 'Manager revisions must carry existing gallery IDs through review and approval.' );

foreach ( array( $team_template, $field_template ) as $template ) {
	$assert( str_contains( $template, 'wp_attachment_is_image' ), 'Public galleries must contain valid image attachments only.' );
	$assert( str_contains( $template, "wp_get_attachment_image_url" ), 'Public gallery thumbnails must link to the full-size image.' );
}
$assert( str_contains( $team_template, 'if ( $adam_gallery )' ) && str_contains( $team_template, 'data-adam-lightbox' ), 'The Team gallery must be conditional and open images in its lightbox.' );
$assert( str_contains( $field_template, 'if ( $gallery )' ) && str_contains( $field_template, 'data-field-lightbox' ), 'The Field gallery must be conditional and open images in its lightbox.' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $value ): int {
		return abs( (int) $value );
	}
}
require_once $root . '/includes/teams/class-options.php';
$assert(
	array( 12, 13 ) === \ADAM\Comunidade\Teams\Options::decode_ids( '[12,"13",0,12,"invalid"]' ),
	'Team attachment IDs must be decoded as ordered, unique positive integers.'
);

echo "Team and Field gallery pipeline tests passed.\n";
