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
$single     = (string) file_get_contents( $root . '/templates/teams/single.php' );
$styles     = (string) file_get_contents( $root . '/assets/css/teams-public.css' );
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
$assert( str_contains( $card, 'class="adam-recruitment adam-recruitment--' ) && str_contains( $card, 'normalize_recruitment_status' ), 'Team cards must render normalized recruitment statuses with the shared badge component.' );
$assert( strpos( $card, 'adam-team-card__meta' ) < strpos( $card, 'adam-team-badges' ), 'Playing-style badges must remain on their own row below Team metadata.' );
$assert( str_contains( $single, 'if ( $adam_recruitment_status )' ) && ! str_contains( $single, "'unknown'" ), 'Teams without a recruitment status must omit the badge in the Team header.' );
foreach ( array( 'recruiting' => '#15803d', 'limited' => '#2563eb', 'not_recruiting' => '#334155' ) as $status => $colour ) {
	$assert( str_contains( $styles, '.adam-recruitment--' . $status ) && str_contains( $styles, $colour ), 'Missing recruitment badge colour for ' . $status . '.' );
}
$assert( (bool) preg_match( '/\.adam-recruitment\s*\{[^}]*color:\s*#fff/s', $styles ), 'Recruitment badges must use white text.' );
$assert( (bool) preg_match( '/\.adam-team-card__meta\s*\{[^}]*flex-wrap:\s*wrap/s', $styles ), 'Team card metadata must wrap cleanly on narrow screens.' );
$assert( ! str_contains( $archive, 'No published teams are available yet.' ), 'The public empty state is still in English.' );
$assert( str_contains( $archive, 'data-adam-directory-carousel' ), 'Teams are not using the shared carousel behavior.' );
$assert( str_contains( $controller, "'team-hero'" ) && str_contains( $controller, 'save_hero' ), 'The Teams hero admin route is incomplete.' );
$assert( str_contains( $hero, "'published_teams'" ) && str_contains( $hero, 'minimum_featured' ), 'Published Team covers need a manual fallback.' );
foreach ( array( "'team_logo'", "'team_cover'", "'team_photos'", "'playing_styles'" ) as $field ) {
	$assert( str_contains( $form, $field ), 'The public Team submission is missing ' . $field );
}

echo "Teams directory tests passed.\n";
