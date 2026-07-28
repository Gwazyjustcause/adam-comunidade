<?php
/**
 * Contracts for the simplified public-submission moderation workflow.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static fn( string $path ): string => (string) file_get_contents( $root . '/' . $path );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$portal   = $read( 'includes/experience/class-portal.php' );
$component = $read( 'includes/experience/class-moderation-component.php' );
$reasons  = $read( 'includes/experience/class-moderation-reasons.php' );
$settings = $read( 'includes/class-settings.php' );
$email    = $read( 'includes/experience/class-email-service.php' );
$admin_js = $read( 'assets/js/admin.js' );
$manager_admin = $read( 'includes/managers/class-admin.php' );
$manager_service = $read( 'includes/managers/class-service.php' );

$assert( ! str_contains( $portal, "name=\"admin_note\"" ) && ! str_contains( $portal, 'Notas internas' ), 'The legacy free-text note is still visible on submission approvals.' );
$assert( ! str_contains( $manager_admin, "name=\"admin_note\"" ) && ! str_contains( $manager_admin, 'Nota para o Gestor' ), 'The legacy free-text note is still visible on manager revision approvals.' );
foreach ( array( 'Aprovar e publicar', 'Pedir alterações', 'Rejeitar' ) as $action ) {
	$assert( str_contains( $portal, $action ), 'Missing moderation action: ' . $action );
}
$assert(
	str_contains( $portal, 'Moderation_Component::render' )
	&& str_contains( $manager_admin, 'Moderation_Component::render' )
	&& str_contains( $component, 'moderation_reasons[]' )
	&& str_contains( $component, 'data-adam-custom-reason' ),
	'All moderation screens do not use the same focused structured-reason component.'
);
$assert(
	str_contains( $settings, 'render_moderation_reasons' )
	&& str_contains( $settings, 'data-adam-add-reason' )
	&& str_contains( $settings, 'data-adam-move-reason' )
	&& str_contains( $settings, 'data-adam-remove-reason' ),
	'Moderation reasons cannot be fully managed from Settings.'
);
$assert(
	str_contains( $reasons, 'function configured' )
	&& str_contains( $reasons, 'function sanitize' )
	&& str_contains( $reasons, 'function resolve' )
	&& str_contains( $reasons, "'enabled'" )
	&& str_contains( $reasons, "'allows_custom'" ),
	'The reusable reason registry is incomplete.'
);
$assert(
	str_contains( $portal, 'Moderation_Reasons::resolve' )
	&& str_contains( $manager_service, 'Moderation_Reasons::resolve' )
	&& str_contains( $manager_service, "'changes' => 'needs_info'" ),
	'Submission and revision decisions do not share server-side reason validation.'
);
$assert(
	str_contains( $email, "'field_changes_requested'" )
	&& str_contains( $email, "'community_changes_requested'" )
	&& str_contains( $email, "nl2br( esc_html( \$value ), false )" ),
	'Structured feedback is not included legibly in all submission decision emails.'
);
$assert(
	str_contains( $admin_js, 'updateModerationCustomField' )
	&& str_contains( $admin_js, 'moderationReasonRequired' ),
	'The reason picker lacks conditional custom feedback or client-side validation.'
);

echo "Moderation action tests passed.\n";
