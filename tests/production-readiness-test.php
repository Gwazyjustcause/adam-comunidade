<?php
/**
 * Production-readiness contracts for the final UX and quality audit.
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

$events_api       = $read( 'includes/events/class-rest-api.php' );
$approval         = $read( 'includes/experience/class-portal.php' );
$fields_repo      = $read( 'includes/fields/class-repository.php' );
$directory_repo   = $read( 'includes/directory/class-repository.php' );
$directory_api    = $read( 'includes/directory/class-rest-api.php' );
$directory_view   = $read( 'includes/directory/class-view.php' );
$fields_view      = $read( 'includes/fields/class-view.php' );
$teams_view       = $read( 'includes/teams/class-view.php' );
$directory_page   = $read( 'templates/directory/archive.php' );
$fields_page      = $read( 'templates/fields/archive.php' );
$teams_page       = $read( 'templates/teams/archive.php' );
$experience_js    = $read( 'assets/js/experience.js' );
$directory_js     = $read( 'assets/js/directory-public.js' );
$fields_js        = $read( 'assets/js/fields-public.js' );
$teams_js         = $read( 'assets/js/teams-public.js' );
$upload_php       = $read( 'includes/uploads/class-component.php' );
$upload_js        = $read( 'assets/js/upload.js' );
$directory_single = $read( 'templates/directory/single.php' );
$event_archive    = $read( 'templates/events/archive.php' );

$assert(
	str_contains( $events_api, "'permission_callback' => array( \$this, 'can_read_attendance' )" )
	&& str_contains( $events_api, 'adam_comunidade_events_attendance_permission' ),
	'Attendance data is not protected by an ownership-aware permission contract.'
);
$assert(
	str_contains( $events_api, "'permission_callback' => array( \$this, 'can_register_attendee' )" )
	&& str_contains( $events_api, 'adam_comunidade_events_registration_permission' )
	&& str_contains( $events_api, '! $event->is_visible()' ),
	'Authenticated member registrations are not separated from attendance lookups.'
);

foreach ( array( "'START TRANSACTION'", 'FOR UPDATE', "'ROLLBACK'", "'COMMIT'" ) as $transaction_contract ) {
	$assert( str_contains( $approval, $transaction_contract ), 'Submission moderation is not atomic: ' . $transaction_contract );
}
$assert(
	str_contains( $approval, "in_array( \$decision, array( 'changes', 'reject' ), true )" )
	&& str_contains( $approval, "'' === trim( \$admin_note )" ),
	'Moderation decisions that need an explanation do not require one.'
);
$assert(
	str_contains( $approval, 'sync_gallery' )
	&& str_contains( $approval, 'sync_amenities' )
	&& str_contains( $approval, "'gallery_failed'" )
	&& str_contains( $approval, "'amenities_failed'" ),
	'Relationship updates cannot abort a failed approval safely.'
);

$assert(
	str_contains( $fields_repo, 'function prime_amenities' ) && str_contains( $fields_repo, '$amenities_cache' ),
	'Field cards still risk an amenities N+1 query.'
);
$assert(
	str_contains( $directory_repo, 'function prime_galleries' ) && str_contains( $directory_repo, '$gallery_cache' )
	&& str_contains( $directory_api, 'prime_galleries' ),
	'Directory API cards still risk a gallery N+1 query.'
);

foreach (
	array(
		$directory_page => array( 'method="get"', "'pagina'" ),
		$fields_page    => array( 'method="get"', "'pagina'" ),
		$teams_page     => array( 'method="get"', "'pagina'" ),
	) as $page => $contracts
) {
	foreach ( $contracts as $contract ) {
		$assert( str_contains( $page, $contract ), 'A directory cannot preserve filters without JavaScript: ' . $contract );
	}
}
foreach ( array( $directory_view, $fields_view, $teams_view ) as $view ) {
	$assert(
		str_contains( $view, '<a href="' ) && str_contains( $view, 'add_query_arg' ) && str_contains( $view, 'data-page=' ),
		'Server-rendered pagination is not progressively enhanced.'
	);
}
foreach ( array( $directory_js, $fields_js, $teams_js ) as $script ) {
	$assert(
		str_contains( $script, 'aria-busy' ) && str_contains( $script, 'history.replaceState' ),
		'Async directory state is not exposed or reflected in the URL.'
	);
}

$assert(
	str_contains( $experience_js, 'function safeUrl' )
	&& str_contains( $experience_js, "document.createElement('a')" )
	&& str_contains( $experience_js, '.textContent =' ),
	'Search and map results are not constructed with safe DOM APIs.'
);
$assert(
	str_contains( $experience_js, 'aria-invalid' )
	&& str_contains( $experience_js, 'aria-describedby' )
	&& str_contains( $experience_js, 'invalid[0].focus' ),
	'Public form validation is not announced and focused accessibly.'
);

$assert(
	str_contains( $upload_php, 'aria-live="polite"' )
	&& str_contains( $upload_php, 'role="alert"' )
	&& str_contains( $upload_js, 'ArrowLeft' )
	&& str_contains( $upload_js, 'ArrowRight' ),
	'The reusable uploader is missing accessible feedback or keyboard ordering.'
);
$assert(
	str_contains( $directory_single, 'role="dialog"' ) && str_contains( $directory_single, 'aria-modal="true"' ),
	'The media lightbox is not exposed as a modal dialog.'
);

$assert(
	str_contains( $event_archive, 'checkdate' )
	&& str_contains( $event_archive, 'Não existem eventos publicados neste mês.' )
	&& str_contains( $event_archive, 'aria-current' ),
	'The event archive does not handle invalid dates, empty months and active views safely.'
);

echo "Production readiness tests passed.\n";
