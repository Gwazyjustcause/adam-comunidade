<?php
/**
 * Standalone checks for the public Teams directory redesign.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$schema     = (string) file_get_contents( $root . '/includes/teams/class-schema.php' );
$repository = (string) file_get_contents( $root . '/includes/teams/class-repository.php' );
$archive    = (string) file_get_contents( $root . '/templates/teams/archive.php' );
$card       = (string) file_get_contents( $root . '/templates/teams/card.php' );
$controller = (string) file_get_contents( $root . '/includes/teams/admin/class-controller.php' );
$hero       = (string) file_get_contents( $root . '/includes/teams/class-hero-carousel.php' );
$form       = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );

foreach ( array( 'is_associated', 'associated_status' ) as $contract ) {
	$assert( str_contains( $schema, $contract ), 'Missing Teams association schema contract: ' . $contract );
}
$assert( str_contains( $repository, 'is_associated DESC' ), 'Associated Teams must always be prioritized.' );
$assert( str_contains( $archive, 'Apenas Equipas Associadas' ), 'The association filter is missing.' );
$assert( str_contains( $card, 'Equipa Associada ADAM' ), 'The associated Team badge is missing.' );
$assert( str_contains( $card, 'adam-team-card__association-badge' ), 'The associated Team badge must be anchored to the cover corner.' );
$assert( strpos( $card, 'adam-team-card__association-badge' ) < strpos( $card, 'adam-team-card__body' ), 'The associated Team badge must not overlap the logo/content layout.' );
$assert( ! str_contains( $archive, 'No published teams are available yet.' ), 'The public empty state is still in English.' );
$assert( str_contains( $archive, 'data-adam-directory-carousel' ), 'Teams are not using the shared carousel behavior.' );
$assert( str_contains( $controller, "'team-hero'" ) && str_contains( $controller, 'save_hero' ), 'The Teams hero admin route is incomplete.' );
$assert( str_contains( $hero, "'published_teams'" ) && str_contains( $hero, 'minimum_featured' ), 'Published Team covers need a manual fallback.' );
foreach ( array( "'team_logo'", "'team_cover'", "'team_photos'", "'playing_styles'" ) as $field ) {
	$assert( str_contains( $form, $field ), 'The public Team submission is missing ' . $field );
}

echo "Teams directory tests passed.\n";
