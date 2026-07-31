<?php
/**
 * Static contracts for the editable Community landing page.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static function ( string $path ) use ( $root ): string {
	$content = file_get_contents( $root . '/' . $path );
	if ( false === $content ) {
		throw new RuntimeException( 'Unable to read ' . $path );
	}
	return $content;
};
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$router  = $read( 'includes/experience/class-router.php' );
$pages   = $read( 'includes/class-managed-pages.php' );
$builder = $read( 'includes/experience/class-builder.php' );
$blocks  = $read( 'includes/experience/class-smart-blocks.php' );
$editor  = $read( 'assets/js/community-blocks.js' );
$style   = $read( 'assets/css/community-landing.css' );

$assert( ! str_contains( $router, "Templates::locate( 'experience/community.php' )" ), 'The Community page is still replaced by a fixed plugin template.' );
$assert( str_contains( $pages, "'' !== trim( (string) \$page->post_content )" ), 'The starter composition does not protect existing editorial content.' );
foreach ( array( 'wp:cover', 'wp:heading', 'wp:paragraph', 'wp:columns', 'wp:buttons' ) as $native_block ) {
	$assert( str_contains( $pages, $native_block ), 'The starter composition is missing editable native block: ' . $native_block );
}
foreach ( array( 'Ver equipas', 'Adicionar equipa', 'Ver campos', 'Adicionar campo', 'Parceiros', 'Instituições' ) as $copy ) {
	$assert( str_contains( $pages, $copy ), 'The starter composition is missing requested content: ' . $copy );
}
foreach ( array( 'adam_community_search', 'adam_recent_records' ) as $shortcode ) {
	$assert( str_contains( $builder, $shortcode ), 'Missing independent shortcode: ' . $shortcode );
}
foreach ( array( 'community-search', 'recent-records', 'newest-teams', 'featured-fields', 'live-statistics' ) as $block ) {
	$assert( str_contains( $blocks, "adam-comunidade/$block" ) && str_contains( $editor, "adam-comunidade/$block" ), 'Missing dynamic Gutenberg block: ' . $block );
}
$assert( str_contains( $style, '--adam-primary: #315c25' ) && ! str_contains( strtolower( $style ), 'camouflage' ), 'The landing style does not preserve the clean ADAM visual identity.' );
$assert( is_file( $root . '/assets/images/community-hero.webp' ), 'The editable starter hero image is missing.' );

echo "Community landing tests passed.\n";
