<?php
/**
 * Regression checks for the PT-PT public interface.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$public_files = array_merge(
	glob( $root . '/templates/**/*.php' ) ?: array(),
	array(
		$root . '/includes/directory/class-components.php',
		$root . '/includes/directory/class-router.php',
		$root . '/includes/directory/class-view.php',
		$root . '/includes/experience/class-builder.php',
		$root . '/includes/experience/class-notifications.php',
		$root . '/includes/experience/class-related-content.php',
		$root . '/includes/experience/class-router.php',
		$root . '/includes/experience/class-smart-blocks.php',
		$root . '/includes/fields/class-options.php',
		$root . '/includes/fields/class-router.php',
		$root . '/includes/fields/class-validator.php',
		$root . '/includes/fields/class-view.php',
		$root . '/includes/teams/class-options.php',
		$root . '/includes/teams/class-view.php',
	)
);

$forbidden = array(
	'About',
	'All',
	'Alphabetical',
	'Apply filters',
	'Capacity',
	'Choose up to three',
	'Close image viewer',
	'Community Calendar',
	'Community Map',
	'Community Partners',
	'Compare items',
	'Content could not be loaded.',
	'Copy GPS',
	'Could not load community results.',
	'Directory pages',
	'Directory statistics',
	'Discover the organisations that collaborate with and support the ADAM community.',
	'District',
	'Facilities',
	'Featured only',
	'Field location map',
	'Fields could not be loaded. Please try again.',
	'Fields pagination',
	'Gallery',
	'Latest News',
	'Live Directory',
	'Map & Directions',
	'Municipality',
	'Nearby Fields',
	'Nearby Partners',
	'Nearby Shops',
	'Nearby Teams',
	'Newest',
	'Next',
	'No matching community content.',
	'No published content is available yet.',
	'No results match these filters.',
	'No upcoming community dates.',
	'Open Google Maps',
	'Playing Style',
	'Previous',
	'Priority',
	'Recommended Fields',
	'Relevant News',
	'Rules',
	'Search',
	'Search the community',
	'Select two or three items to compare side-by-side.',
	'Sort',
	'Teams pagination',
	'Upcoming Events',
	'View details',
	'Visit Website',
	'Website',
	'Woodland',
);

foreach ( $public_files as $file ) {
	$source = (string) file_get_contents( $file );
	foreach ( $forbidden as $english ) {
		$quoted = preg_match( '/[\'"]' . preg_quote( $english, '/' ) . '[\'"]/', $source );
		$assert( 0 === $quoted, 'English client string remains in ' . basename( $file ) . ': ' . $english );
	}
}

$map_script = (string) file_get_contents( $root . '/assets/js/community-map.js' );
$upload_script = (string) file_get_contents( $root . '/assets/js/upload.js' );
$experience_script = (string) file_get_contents( $root . '/assets/js/experience.js' );

$assert( str_contains( $map_script, 'adamCommunityMap' ), 'Map labels must come from the WordPress localization system.' );
$assert( str_contains( $upload_script, 'labels.caption' ) && str_contains( $upload_script, 'labels.file' ), 'Uploader labels must not be hardcoded in JavaScript.' );
$assert( str_contains( $experience_script, 'config.groupLabels' ), 'Search result group labels must be localized.' );

$schema = (string) file_get_contents( $root . '/includes/fields/class-schema.php' );
$assert( str_contains( $schema, 'localize_default_amenities' ), 'Legacy English amenity defaults need a safe PT-PT migration.' );
$assert( str_contains( $schema, "'label' => \$label[0]" ), 'Amenity migration must preserve administrator customizations.' );

echo "Client PT-PT localization tests passed.\n";
