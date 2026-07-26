<?php
/**
 * Standalone checks for the shared submission success workflow.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$portal  = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$manager = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$view    = (string) file_get_contents( $root . '/admin/views/forms/manager.php' );
$style   = (string) file_get_contents( $root . '/assets/css/experience.css' );

$assert( str_contains( $portal, "'^submeter-' . \$slug . '/sucesso/?$'" ), 'Each form type needs a clean success route.' );
$assert( str_contains( $portal, 'adam_submission_success' ), 'Success routes need their own query variable.' );
$assert( str_contains( $portal, 'render_submission_success' ), 'The shared success renderer is missing.' );
$assert( str_contains( $portal, 'wp_safe_redirect( self::success_url( $type ) )' ), 'Successful POST requests must redirect using PRG.' );
$assert( ! str_contains( $portal, "add_query_arg( 'adam_status', 'submitted'" ), 'Successful submissions must not return to the form.' );

foreach ( array( 'field', 'team', 'partner', 'institution' ) as $type ) {
	$assert( str_contains( $portal, "'{$type}' => array(" ), 'Missing success-page copy for: ' . $type );
}

foreach ( array( 'Submissão Recebida', 'Em análise', 'Aguarda decisão', 'Publicação' ) as $step ) {
	$assert( str_contains( $portal, $step ), 'Missing success timeline step: ' . $step );
}

$assert( str_contains( $manager, "'review_time'" ), 'Review time must be stored with every form configuration.' );
$assert( str_contains( $view, '[review_time]' ), 'Administrators need to edit the review time.' );
$assert( str_contains( $style, '.adam-submission-success' ), 'Success-page styles are missing.' );
$assert( str_contains( $portal, 'maybe_flush_rewrite_rules' ), 'New success routes must be activated after plugin updates.' );

echo "Submission success tests passed.\n";
