<?php
/**
 * Shared responsive directory-filter layout contract.
 *
 * Run with: php tests/directory-filter-layout-test.php
 *
 * @package ADAM_Comunidade
 */

$root = dirname( __DIR__ );
$read = static fn( string $file ): string => (string) file_get_contents( $root . '/' . $file );
$failures = array();
$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$shared = $read( 'assets/css/public.css' );
$assert( str_contains( $shared, '.adam-directory-filters {' ), 'The shared directory-filter component is missing.' );
$assert( str_contains( $shared, 'grid-template-columns: repeat(auto-fit, minmax(min(100%, var(--adam-directory-filter-min, 13rem)), 1fr))' ), 'Directory filters must wrap automatically from available width.' );
$assert( str_contains( $shared, 'gap: 14px' ), 'Directory filters must keep a consistent control gap.' );
$assert( str_contains( $shared, 'align-self: end' ) && str_contains( $shared, 'width: 100%' ), 'The filter action must align and size with the controls.' );
$assert( str_contains( $shared, 'input:not([type="checkbox"]):not([type="radio"])' ), 'Full-width controls must not distort checkbox or radio filters.' );

$views = array(
	'templates/teams/archive.php'       => 'adam-team-filters adam-directory-filters',
	'templates/fields/archive.php'      => 'adam-field-filters adam-directory-filters',
	'templates/directory/archive.php'   => 'adam-community-filters adam-directory-filters',
	'templates/experience/community.php' => 'adam-map-filters adam-directory-filters',
	'includes/directory/class-components.php' => 'adam-map-filters adam-directory-filters',
);
foreach ( $views as $file => $class_contract ) {
	$assert( str_contains( $read( $file ), $class_contract ), $file . ' must use the shared filter layout.' );
}

foreach ( array( 'assets/css/teams-public.css' => 'adam-team-filters', 'assets/css/fields-public.css' => 'adam-field-filters', 'assets/css/directory-public.css' => 'adam-community-filters', 'assets/css/experience.css' => 'adam-map-filters' ) as $file => $selector ) {
	$css = $read( $file );
	$assert( ! preg_match( '/\.' . preg_quote( $selector, '/' ) . '[^{]*\{[^}]*grid-template-columns/s', $css ), $file . ' must not hardcode filter rows outside the shared component.' );
}

if ( $failures ) {
	fwrite( STDERR, "FAIL:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: shared directory filter layout contract.\n";
