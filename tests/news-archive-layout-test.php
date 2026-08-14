<?php
/**
 * News archive layout and query contract checks.
 *
 * Run with: php tests/news-archive-layout-test.php
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$read = static fn ( string $file ): string => (string) file_get_contents( $root . '/' . $file );
$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$news = $read( 'includes/experience/class-news.php' );
$archive = $read( 'templates/experience/news-archive.php' );
$styles = $read( 'assets/css/experience.css' );
$single = $read( 'templates/experience/news-single.php' );

$assert( str_contains( $news, '$query->set( \'posts_per_page\', 9 );' ), 'Archive must show nine news items per page.' );
$assert( str_contains( $news, '$query->set( \'orderby\', \'date\' );' ) && str_contains( $news, '$query->set( \'order\', \'DESC\' );' ), 'Archive order must remain newest first.' );
$assert( str_contains( $archive, 'adam-news-archive' ), 'Archive must have a scoped layout class.' );
$assert( str_contains( $archive, 'has_post_thumbnail()' ), 'Archive must branch on the native featured image.' );
$assert( str_contains( $archive, 'get_media_embedded_in_content' ) && str_contains( $archive, "array( 'img' )" ), 'Archive must use the first image embedded in article content as a fallback.' );
$assert( str_contains( $archive, 'the_posts_pagination' ), 'Archive must use WordPress pagination.' );
$assert( str_contains( $archive, 'adam-news-card__link' ), 'The complete archive card must be clickable.' );
$assert( str_contains( $styles, 'grid-template-columns: repeat(3, minmax(0, 1fr));' ) && str_contains( $styles, 'grid-template-columns: repeat(2, minmax(0, 1fr));' ) && str_contains( $styles, 'grid-template-columns: 1fr;' ), 'Archive must define desktop, tablet and mobile columns.' );
$assert( str_contains( $styles, '.adam-news-archive .adam-news-card img' ) || str_contains( $styles, '.adam-news-card img' ), 'Archive cards must retain consistent cropped image rendering.' );
$assert( ! str_contains( $single, 'adam-news-archive' ), 'News single template must remain outside archive changes.' );

echo "PASS: news archive layout contract.\n";
