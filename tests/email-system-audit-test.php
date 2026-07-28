<?php
/**
 * Standalone architecture checks for the unified Community email system.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$service  = (string) file_get_contents( $root . '/includes/experience/class-email-service.php' );
$managers = (string) file_get_contents( $root . '/includes/managers/class-service.php' );
$settings = (string) file_get_contents( $root . '/includes/class-settings.php' );
$view     = (string) file_get_contents( $root . '/admin/views/forms/manager.php' );

foreach ( array(
	'field_received',
	'field_approved',
	'field_rejected',
	'community_received',
	'community_approved',
	'community_rejected',
	'manager_invitation',
	'manager_password_reset',
	'manager_password_created',
	'manager_password_changed',
	'manager_revision_approved',
	'manager_revision_rejected',
	'manager_information_requested',
) as $template ) {
	$assert( str_contains( $service, "'{$template}'" ), "Missing audited email template: {$template}" );
}

$assert( str_contains( $service, 'adam_comunidade_email_max_attempts' ) && str_contains( $service, '$max_attempts' ), 'The bounded retry policy is missing.' );
$assert( str_contains( $service, 'phpmailer_init' ) && str_contains( $service, 'AltBody' ) && str_contains( $service, 'render_plain_text' ), 'The plain-text email alternative is incomplete.' );
$assert( str_contains( $service, 'lang="pt-PT"' ) && str_contains( $service, 'prefers-color-scheme: dark' ), 'Responsive PT-PT or dark-mode email markup is incomplete.' );
$assert( str_contains( $service, 'Logótipo da ADAM' ) && str_contains( $service, 'role="presentation"' ), 'Accessible branding markup is incomplete.' );
$assert( str_contains( $service, 'Criar Palavra-passe' ) && str_contains( $service, 'independente da conta de Sócio ADAM' ), 'Approval onboarding does not explain the Community Manager journey.' );
$assert( str_contains( $managers, "'manager_password_created'" ) && str_contains( $managers, "'manager_password_changed'" ), 'Password lifecycle confirmations are not dispatched.' );
$assert( str_contains( $settings, "'email_from_name'" ) && str_contains( $settings, 'render_email_from_name' ), 'The Community sender identity is not configurable.' );
$assert( ! str_contains( $service, 'adam_membership_email_from_name' ), 'The email sender still depends on ADAM Members settings.' );
$assert( str_contains( $view, 'E-mails automáticos da Comunidade' ) && str_contains( $view, 'Variáveis disponíveis:' ), 'The email administration interface was not generalized.' );

echo "Complete email system audit tests passed.\n";
