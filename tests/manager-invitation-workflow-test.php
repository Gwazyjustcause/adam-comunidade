<?php
/**
 * Standalone contracts for the account-aware manager invitation workflow.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema  = (string) file_get_contents( $root . '/includes/managers/class-schema.php' );
$service = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$portal  = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$emails  = (string) file_get_contents( $root . '/includes/experience/class-email-service.php' );
$admin   = (string) file_get_contents( $root . '/includes/managers/class-admin.php' );

$assert( str_contains( $schema, 'UNIQUE KEY email (email)' ), 'Manager email addresses must remain unique at database level.' );
$assert( str_contains( $service, 'function provision_organisation' ), 'Approved organisations do not use the canonical account provisioning workflow.' );
$assert( str_contains( $service, 'function prepare_changes_access' ), 'Requests for changes do not resolve account access automatically.' );
$assert( str_contains( $service, 'function manager_by_email' ) && str_contains( $service, 'strtolower( sanitize_email( $email ) )' ), 'Manager identities are not normalized and resolved by email.' );
$assert( str_contains( $service, "'state' => 'active'" ) && str_contains( $service, "'state' => 'pending_activation'" ), 'Existing manager states are not distinguished.' );
$assert( str_contains( $service, "purpose = 'invitation' AND used_at IS NULL AND id <> %d" ), 'A manager can retain multiple current activation tokens.' );
$assert( str_contains( $portal, "'approve' === \$decision" ) && str_contains( $portal, 'provision_organisation' ), 'Approval does not assign the organisation automatically.' );
$assert( str_contains( $portal, "'changes' === \$decision" ) && str_contains( $portal, 'prepare_changes_access' ), 'Request-changes emails are not account-aware.' );
$assert( ! preg_match( '/\'reject\'\s*===\s*\$decision[^}]+(?:provision_organisation|prepare_changes_access)/s', $portal ), 'Permanent rejection must not create or prepare a manager account.' );
$assert( str_contains( $emails, "'manager_organisation_assigned'" ) && str_contains( $emails, 'Aceder à Área do Gestor' ), 'Existing managers do not receive the portal workflow.' );
$assert( str_contains( $emails, "'manager_organisation_pending_activation'" ), 'Pending accounts do not receive a duplicate-safe notification.' );
$assert( str_contains( $emails, '{{manager_action_url}}' ) && str_contains( $emails, '{{manager_action_label}}' ), 'Request-changes templates do not adapt their call to action.' );
$assert( str_contains( $admin, 'provision_organisation' ), 'Manual organisation assignment can still create duplicate manager accounts or invitations.' );

echo "Manager invitation workflow tests passed.\n";
