<?php
/**
 * Standalone checks for the shared public hero component.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$component = (string) file_get_contents( $root . '/includes/class-public-hero.php' );
$style     = (string) file_get_contents( $root . '/assets/css/public.css' );

foreach ( array( 'root', 'element', 'adam-public-hero', 'adam-public-hero__' ) as $contract ) {
	$assert( str_contains( $component, $contract ), 'Missing shared hero component contract: ' . $contract );
}

foreach ( array( '--adam-hero-title: #c9f6a1', '--adam-hero-overlay-start', '.adam-public-hero__subtitle', '.adam-public-hero__badge', '@media (max-width: 700px)' ) as $rule ) {
	$assert( str_contains( $style, $rule ), 'Missing shared hero contrast rule: ' . $rule );
}

$templates = array(
	'templates/fields/archive.php',
	'templates/fields/single.php',
	'templates/teams/archive.php',
	'templates/teams/single.php',
	'templates/directory/archive.php',
	'templates/directory/single.php',
	'templates/experience/community.php',
	'templates/experience/region.php',
	'templates/experience/news-single.php',
);

foreach ( $templates as $template ) {
	$source = (string) file_get_contents( $root . '/' . $template );
	$assert( str_contains( $source, 'Public_Hero::root' ), 'Public hero is not using the shared component: ' . $template );
}

foreach ( array( 'assets/css/fields-public.css', 'assets/css/teams-public.css' ) as $feature_style ) {
	$source = (string) file_get_contents( $root . '/' . $feature_style );
	$assert( ! str_contains( $source, '__cover::after' ), 'A feature-specific image overlay remains: ' . $feature_style );
}

echo "Shared public hero tests passed.\n";
