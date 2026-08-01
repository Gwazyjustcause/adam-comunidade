<?php
/**
 * Team gallery limits and exclusive media lifecycle contracts.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$forms           = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$public_portal   = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$manager_portal  = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$manager_service = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$admin_view      = (string) file_get_contents( $root . '/admin/views/teams/editor.php' );
$admin           = (string) file_get_contents( $root . '/includes/teams/admin/class-controller.php' );
$validator       = (string) file_get_contents( $root . '/includes/teams/class-validator.php' );
$repository      = (string) file_get_contents( $root . '/includes/teams/class-repository.php' );
$media           = (string) file_get_contents( $root . '/includes/teams/class-media-lifecycle.php' );
$component       = (string) file_get_contents( $root . '/includes/uploads/class-component.php' );
$upload_script   = (string) file_get_contents( $root . '/assets/js/upload.js' );
$public_script   = (string) file_get_contents( $root . '/assets/js/public.js' );

$assert( str_contains( $forms, "'team_photos'" ) && str_contains( $forms, "'.jpg,.jpeg,.png,.webp', 5" ), 'The public Team gallery must expose a five-image limit.' );
$assert( str_contains( $admin_view, "'name' => 'team[gallery][]'" ) && str_contains( $admin_view, "'max' => 5" ), 'The administrative Team gallery must prevent selecting more than five images.' );
$assert( str_contains( $manager_portal, "'team' === \$type ? 5" ) && str_contains( $manager_portal, "count( \$kept_gallery_ids ) + count( \$gallery ) > \$gallery_max" ), 'The Community Manager must count saved and newly selected Team images.' );
$assert( str_contains( $validator, 'count( $gallery ) > 5' ) && str_contains( $validator, 'no máximo 5 imagens' ), 'The server must reject Team galleries over five images with a clear message.' );
$assert( str_contains( $component, 'data-existing-count' ) && str_contains( $upload_script, 'existingCount()' ) && str_contains( $public_script, 'keep_gallery_ids[]' ), 'The client-side limit must include existing saved images and react to removals.' );

foreach ( array( "current_user_can( 'upload_files' )", 'get_post_meta', 'used_elsewhere', 'wp_delete_attachment( $attachment_id, true )' ) as $guard ) {
	$assert( str_contains( $media, $guard ), "Missing permanent-deletion guard: {$guard}." );
}
foreach ( array( 'Schema::teams_table()', 'Field_Schema::fields_table()', 'Field_Schema::galleries_table()', 'Directory_Schema::entries_table()', 'Directory_Schema::galleries_table()', "meta_key='_thumbnail_id'", 'Event_Repository', 'Hero_Carousel::OPTION' ) as $reference_store ) {
	$assert( str_contains( $media, $reference_store ), "Exclusive-use checks do not cover: {$reference_store}." );
}
$assert( str_contains( $media, "array( \$team['logo_id'] ?? 0, \$team['cover_id'] ?? 0 )" ) && str_contains( $media, 'Options::decode_ids' ), 'Logo, cover and additional gallery images must share the safe lifecycle.' );

$admin_failure = (int) strpos( $admin, 'if ( ! $success )' );
$admin_cleanup = (int) strpos( $admin, 'Media_Lifecycle::delete_removed' );
$assert( $admin_cleanup > $admin_failure, 'Administrative replacements must delete old media only after the Team update succeeds.' );
$commit  = (int) strpos( $manager_service, "query( 'COMMIT'" );
$cleanup = (int) strpos( $manager_service, 'Team_Media_Lifecycle::delete_removed' );
$assert( $cleanup > $commit, 'Manager replacements must delete old media only after the approved revision commits.' );
$assert( str_contains( $manager_portal, 'Team_Media_Lifecycle::claim' ) && str_contains( $public_portal, 'Team_Media_Lifecycle::claim' ), 'New public and Manager uploads must be marked as belonging to their Team.' );
$assert( str_contains( $repository, 'Media_Lifecycle::delete_team_media' ), 'Permanently deleting a Team must clean up its exclusive images.' );
$assert( str_contains( $admin, "check_admin_referer( 'adam_team_save' )" ) && str_contains( $admin, "check_admin_referer( 'adam_team_action_'" ), 'Team updates and deletions must remain nonce protected.' );

echo "Team media lifecycle tests passed.\n";
