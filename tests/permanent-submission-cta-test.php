<?php
/**
 * Standalone checks for permanent submission invitations on public directories.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$teams    = (string) file_get_contents( $root . '/templates/teams/archive.php' );
$fields   = (string) file_get_contents( $root . '/templates/fields/archive.php' );
$directory = (string) file_get_contents( $root . '/templates/directory/archive.php' );

foreach ( array( 'team' => $teams, 'field' => $fields ) as $type => $template ) {
	$cta = strpos( $template, 'data-adam-submission-cta' );
	$pagination = strrpos( $template, 'pagination' );
	$assert( false !== $cta, "The {$type} directory is missing its permanent submission invitation." );
	$assert( false !== $pagination && $cta > $pagination, "The {$type} submission invitation must appear after the results and pagination." );
	$assert( 1 === substr_count( $template, 'data-adam-submission-cta' ), "The {$type} submission invitation must render exactly once." );
	$assert( ! str_contains( $template, 'is_user_logged_in' ) && ! str_contains( $template, 'current_user_can' ), "The {$type} invitation must not depend on authentication or role." );
}

$team_results     = (int) strpos( $teams, 'id="adam-team-results"' );
$team_empty_start = (int) strpos( $teams, '<?php else : ?>', $team_results );
$team_empty_end   = (int) strpos( $teams, '<?php endif; ?>', $team_empty_start );
$empty_team_state = substr( $teams, $team_empty_start, $team_empty_end - $team_empty_start );
$assert( str_contains( $empty_team_state, 'Ainda não existem equipas publicadas.' ), 'The Teams empty state message must remain conditional.' );
$assert( ! str_contains( $empty_team_state, 'submission_url' ), 'The Teams submission invitation must not be conditional on an empty result set.' );
$assert( str_contains( $teams, 'Queres adicionar a tua equipa?' ), 'The permanent Teams invitation copy is missing.' );

$assert( 1 === substr_count( $directory, 'data-adam-submission-cta' ), 'Partners and Institutions must share one unconditional archive invitation.' );
$assert( ! str_contains( $directory, 'is_user_logged_in' ) && ! str_contains( $directory, 'current_user_can' ), 'The Partner/Institution invitation must not depend on authentication or role.' );
$assert( str_contains( $directory, "Portal::submission_url( \$type )" ), 'The shared directory invitation must target the current Partner or Institution form.' );
foreach ( array( 'Queres adicionar o teu parceiro?', 'Queres adicionar uma instituição?' ) as $copy ) {
	$assert( str_contains( $directory, $copy ), "Missing permanent directory invitation: {$copy}" );
}
$assert( strpos( $directory, 'data-adam-submission-cta' ) > strrpos( $directory, 'pagination' ), 'The Partner/Institution invitation must appear after results and pagination.' );

echo "Permanent submission CTA tests passed.\n";
