<?php
/**
 * Standalone architecture contracts for the Community Manager system.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema  = (string) file_get_contents( $root . '/includes/managers/class-schema.php' );
$auth    = (string) file_get_contents( $root . '/includes/managers/class-auth.php' );
$service = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$policy = (string) file_get_contents( $root . '/includes/managers/class-policy.php' );
$portal  = (string) file_get_contents( $root . '/includes/managers/class-portal.php' );
$admin   = (string) file_get_contents( $root . '/includes/managers/class-admin.php' );
$cleanup = (string) file_get_contents( $root . '/includes/managers/class-cleanup.php' );
$loader  = (string) file_get_contents( $root . '/includes/class-loader.php' );
$emails  = (string) file_get_contents( $root . '/includes/experience/class-email-service.php' );
$approval = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$field_schema = (string) file_get_contents( $root . '/includes/fields/class-schema.php' );
$field_page = (string) file_get_contents( $root . '/templates/fields/single.php' );

foreach ( array( 'managers_table', 'assignments_table', 'invitations_table', 'sessions_table', 'revisions_table' ) as $table_method ) {
	$assert( str_contains( $schema, 'function ' . $table_method ), 'Missing isolated manager table: ' . $table_method );
}
$assert( str_contains( $loader, 'new Managers\\Module()' ), 'The manager module is not booted.' );
$assert( str_contains( $cleanup, 'adam_comunidade_manager_cleanup' ), 'The manager data lifecycle cleanup is not registered.' );
$assert( str_contains( $auth, "private const COOKIE = 'adam_community_manager_session'" ), 'The manager session must use its own cookie.' );
$assert( str_contains( $auth, "hash( 'sha256', \$raw )" ), 'Session tokens must be hashed at rest.' );
$assert( str_contains( $auth, "password_verify( \$password" ), 'Manager login does not verify its own password hash.' );
$assert( str_contains( $service, "password_hash( \$password, PASSWORD_DEFAULT )" ), 'Manager passwords are not independently hashed.' );
$assert( str_contains( $service, 'random_bytes( 32 )' ) && str_contains( $service, "'token_hash' => hash( 'sha256', \$raw )" ), 'Invitation tokens are not secure and hashed at rest.' );
$assert( str_contains( $portal, "Managed_Pages::url( 'manager' )" ) && str_contains( $portal, "Managed_Pages::url( 'manager_activation' )" ), 'Managed manager routes are incomplete.' );
$assert( str_contains( $portal, 'verify_csrf' ), 'Authenticated manager mutations need session-bound CSRF checks.' );
$assert( str_contains( $portal, "unset( \$input['cover_id'], \$input['logo_id'], \$input['gallery'] )" ), 'Posted attachment IDs can bypass manager upload validation.' );
$assert( str_contains( $service, "array( 'team', 'field', 'partner', 'institution' )" ), 'Assignments are not ready for future Community entity types.' );
$assert( str_contains( $service, "'status'     => 'pending'" ), 'Manager edits must create pending revisions.' );
$assert( str_contains( $service, 'Policy::decode_lists' ) && str_contains( $policy, 'function decode_lists' ), 'Stored JSON lists are not normalized before revision validation.' );
$assert( str_contains( $service, "'approve' => 'approved'" ) && str_contains( $admin, "value=\"info\"" ) && str_contains( $admin, "value=\"reject\"" ), 'Revision moderation decisions are incomplete.' );
$assert( str_contains( $admin, 'adam_resend_manager_invitation' ), 'Administrators cannot resend invitations.' );
$assert( str_contains( $portal, 'Upload_Component::render' ), 'Manager images must reuse the ADAM Upload component.' );
$assert( str_contains( $approval, "'manager_invite_url' => \$manager_invite_url" ), 'Field approval email does not receive the manager invitation.' );
foreach ( array( 'manager_invitation', 'manager_revision_approved', 'manager_revision_rejected', 'manager_information_requested', 'manager_password_reset' ) as $template ) {
	$assert( str_contains( $emails, "'{$template}'" ), 'Missing manager email template: ' . $template );
}
$assert( str_contains( $admin, "'managers'" ) && str_contains( $admin, 'adam_manager_admin_transfer' ), 'Dedicated manager administration is incomplete.' );
$assert( str_contains( $field_schema, 'opening_hours longtext' ), 'Fields cannot store manager-maintained opening hours.' );
$assert( str_contains( $field_page, '$has_opening_hours' ), 'Empty-aware public opening hours rendering is missing.' );

$manager_code = $schema . $auth . $service . $portal . $admin;
foreach ( array( 'wp_create_user', 'wp_insert_user', 'get_user_by', 'WP_User', 'adam_members', 'ADAM\\Members' ) as $forbidden ) {
	$assert( ! str_contains( $manager_code, $forbidden ), 'Manager identity is coupled to WordPress or ADAM Members: ' . $forbidden );
}

echo "Community Manager system tests passed.\n";
