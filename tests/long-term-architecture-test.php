<?php
/**
 * Static contracts for maintainability and extension boundaries.
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

$config          = $read( 'includes/class-config.php' );
$upload_handler  = $read( 'includes/uploads/class-handler.php' );
$public_portal   = $read( 'includes/experience/class-portal.php' );
$manager_portal  = $read( 'includes/managers/class-portal.php' );
$manager_service = $read( 'includes/managers/class-service.php' );
$manager_policy  = $read( 'includes/managers/class-policy.php' );
$manager_auth    = $read( 'includes/managers/class-auth.php' );
$manager_cleanup = $read( 'includes/managers/class-cleanup.php' );
$event_store     = $read( 'includes/events/class-store-interface.php' );
$event_options   = $read( 'includes/events/class-option-store.php' );
$event_repo      = $read( 'includes/events/class-repository.php' );
$architecture    = $read( 'docs/ARCHITECTURE.md' );

foreach ( array( 'cache_ttl', 'upload_policy', 'manager_security' ) as $method ) {
	$assert( str_contains( $config, 'function ' . $method ), 'Missing central configuration method: ' . $method );
}
foreach ( array( 'adam_comunidade_cache_ttl', 'adam_comunidade_upload_policy', 'adam_comunidade_manager_security_policy' ) as $hook ) {
	$assert( str_contains( $config, $hook ), 'Missing bounded configuration extension point: ' . $hook );
}

foreach ( array( 'function validate', 'function upload_one', 'function upload_many', 'function delete' ) as $method ) {
	$assert( str_contains( $upload_handler, $method ), 'The shared upload lifecycle is incomplete: ' . $method );
}
$assert( str_contains( $upload_handler, 'media_handle_upload' ), 'The shared handler must use the WordPress Media API.' );
$assert( str_contains( $upload_handler, '$this->delete( $ids )' ), 'Partial upload batches are not rolled back.' );
$assert(
	str_contains( $public_portal, 'Upload_Handler' ) && str_contains( $manager_portal, 'Upload_Handler' ),
	'Public and manager forms do not share the same upload implementation.'
);
$assert(
	! str_contains( $public_portal, 'media_handle_upload' ) && ! str_contains( $manager_portal, 'media_handle_upload' ),
	'Upload persistence leaked back into a portal controller.'
);

$assert(
	str_contains( $manager_auth, 'Config::manager_security()' )
	&& str_contains( $manager_cleanup, 'Config::manager_security()' )
	&& str_contains( $manager_service, 'Config::manager_security()' ),
	'Manager security and retention values are still duplicated.'
);
$assert(
	str_contains( $manager_service, 'Policy::editable_fields' )
	&& str_contains( $manager_service, 'Policy::decode_lists' )
	&& str_contains( $manager_policy, 'adam_comunidade_manager_revision_fields' ),
	'Manager entity policy is not separated from account and moderation orchestration.'
);

foreach ( array( 'events', 'find_event', 'query_events', 'save_event', 'delete_event', 'next_id', 'taxonomy' ) as $method ) {
	$assert( str_contains( $event_store, 'function ' . $method ), 'Events store contract is incomplete: ' . $method );
}
$assert( str_contains( $event_repo, 'Store_Interface' ) && str_contains( $event_repo, 'adam_comunidade_events_store' ), 'Events persistence cannot be replaced without changing its API.' );
$assert( str_contains( $event_options, 'OPTION_EVENTS' ), 'The backwards-compatible Events option store is missing.' );

foreach (
	array(
		'adam_comunidade_manager_assigned',
		'adam_comunidade_manager_invited',
		'adam_comunidade_manager_deleted',
		'adam_comunidade_manager_revision_submitted',
		'adam_comunidade_manager_revision_approved',
		'adam_comunidade_manager_revision_rejected',
	) as $hook
) {
	$assert( str_contains( $manager_service, $hook ), 'Missing manager domain hook: ' . $hook );
}
$assert( str_contains( $event_repo, 'adam_comunidade_event_published' ), 'Event publication is missing a domain hook.' );
$assert( str_contains( $architecture, 'ADAM Sócios' ) && str_contains( $architecture, 'Store_Interface' ) && str_contains( $architecture, 'Actions de domínio' ), 'Architecture documentation does not cover ecosystem ownership, storage and hooks.' );

echo "Long-term architecture tests passed.\n";
