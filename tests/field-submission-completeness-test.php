<?php
/**
 * Standalone checks for complete public field submissions.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$manager   = (string) file_get_contents( $root . '/includes/forms/class-manager.php' );
$portal    = (string) file_get_contents( $root . '/includes/experience/class-portal.php' );
$validator = (string) file_get_contents( $root . '/includes/fields/class-validator.php' );
$editor    = (string) file_get_contents( $root . '/admin/views/fields/editor.php' );
$style     = (string) file_get_contents( $root . '/assets/css/experience.css' );

foreach ( array( "'playing_styles'", "'amenities'", "'rules'", "'recommended_players'", "'max_players'" ) as $field ) {
	$assert( str_contains( $manager, $field ), 'Missing optional public field definition: ' . $field );
}

$assert( str_contains( $manager, 'semantic_types' ), 'Shared vocabulary field types must not drift through form configuration.' );
$assert( str_contains( $manager, "\$form['fields'][ \$optional_key ]['required'] = false" ), 'The new field sections must remain optional.' );
$assert( str_contains( $portal, 'Field_Options::playing_styles()' ), 'Public playing styles must use the field Options source.' );
$assert( str_contains( $portal, "all( 'field', true )" ), 'Public amenities must use active administrator-managed amenities.' );
$assert( str_contains( $portal, 'wp_editor(' ) && str_contains( $portal, "'textarea_name' => \$key" ), 'Rules must use the WordPress rich-text editor.' );
$assert( str_contains( $portal, "\$payload['amenity_ids']" ), 'Selected amenities must be stored with the submission.' );
$assert( str_contains( $portal, "absint( \$payload['recommended_players'] ?? 0 ) > absint( \$payload['max_players'] )" ), 'Public capacity values need cross-field validation before moderation.' );
$assert( str_contains( $portal, 'sync_amenities' ), 'Approval must carry amenities into the approved field.' );
$assert( str_contains( $portal, '$playing_style_labels' ) && str_contains( $portal, '$amenity_labels' ), 'Moderation must show submitted vocabulary labels.' );
$assert( str_contains( $portal, "'rules' === \$key ? wp_kses_post" ), 'Moderation must preserve submitted rule formatting.' );

foreach ( array( "'playing_styles'", "'rules'", "'max_players'", "'recommended_players'" ) as $mapping ) {
	$assert( str_contains( $validator, $mapping ), 'Field approval validator is missing mapping: ' . $mapping );
}
$assert( str_contains( $editor, 'Options::playing_styles()' ) && str_contains( $editor, '$amenity_options' ), 'Admin and public forms must share their option sources.' );
$assert( str_contains( $style, '.adam-portal-options__grid' ) && str_contains( $style, '.adam-portal-richtext' ), 'New public sections need responsive component styling.' );

echo "Field submission completeness tests passed.\n";
