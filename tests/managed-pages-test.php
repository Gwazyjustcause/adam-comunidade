<?php
/**
 * Standalone managed-page lifecycle and rewrite smoke test.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$GLOBALS['adam_options']               = array();
$GLOBALS['adam_posts']                 = array();
$GLOBALS['adam_meta']                  = array();
$GLOBALS['adam_rules']                 = array();
$GLOBALS['adam_next_id']               = 100;
$GLOBALS['adam_system_page_providers'] = array();

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) ) ?? '';
}

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function wp_parse_args( mixed $args, array $defaults = array() ): array {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function get_option( string $name, mixed $default = false ): mixed {
	return $GLOBALS['adam_options'][ $name ] ?? $default;
}

function update_option( string $name, mixed $value, bool $autoload = false ): bool {
	unset( $autoload );
	$GLOBALS['adam_options'][ $name ] = $value;
	return true;
}

function delete_option( string $name ): bool {
	unset( $GLOBALS['adam_options'][ $name ] );
	return true;
}

function get_post( int $post_id ): ?object {
	return $GLOBALS['adam_posts'][ $post_id ] ?? null;
}

function get_post_field( string $field, int $post_id ): mixed {
	return $GLOBALS['adam_posts'][ $post_id ]->{$field} ?? '';
}

function get_page_uri( int $post_id ): string {
	return (string) get_post_field( 'post_name', $post_id );
}

function get_permalink( int $post_id ): string|false {
	$post = get_post( $post_id );
	return $post ? 'https://example.test/' . $post->post_name . '/' : false;
}

function home_url( string $path = '' ): string {
	return 'https://example.test/' . ltrim( $path, '/' );
}

function esc_url( string $url ): string {
	return $url;
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function wp_insert_post( array $data, bool $wp_error = false ): int {
	unset( $wp_error );
	$id = ++$GLOBALS['adam_next_id'];
	$GLOBALS['adam_posts'][ $id ] = (object) array_merge(
		array( 'ID' => $id, 'post_parent' => 0 ),
		$data
	);
	return $id;
}

function wp_update_post( array $data ): int {
	$id = absint( $data['ID'] ?? 0 );
	if ( $id && isset( $GLOBALS['adam_posts'][ $id ] ) ) {
		foreach ( $data as $key => $value ) {
			if ( 'ID' !== $key ) {
				$GLOBALS['adam_posts'][ $id ]->{$key} = $value;
			}
		}
	}
	return $id;
}

function is_wp_error( mixed $value ): bool {
	return false;
}

function update_post_meta( int $post_id, string $key, mixed $value ): void {
	$GLOBALS['adam_meta'][ $post_id ][ $key ] = $value;
}

function get_posts( array $args ): array {
	$ids = array();
	foreach ( $GLOBALS['adam_posts'] as $post_id => $post ) {
		if ( ( $GLOBALS['adam_meta'][ $post_id ][ $args['meta_key'] ] ?? null ) === $args['meta_value'] ) {
			$ids[] = $post_id;
		}
	}
	return array_slice( $ids, 0, (int) $args['posts_per_page'] );
}

function wp_untrash_post( int $post_id ): void {
	$GLOBALS['adam_posts'][ $post_id ]->post_status = 'publish';
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	unset( $hook, $callback, $priority, $accepted_args );
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	unset( $hook, $callback, $priority, $accepted_args );
}

function is_admin(): bool {
	return false;
}

function add_rewrite_rule( string $regex, string $query, string $position = 'bottom' ): void {
	$GLOBALS['adam_rules'][ $regex ] = compact( 'query', 'position' );
}

function apply_filters( string $hook, mixed $value ): mixed {
	unset( $hook );
	return $value;
}

function adam_ui_register_system_pages( string $provider, callable $resolver ): bool {
	$GLOBALS['adam_system_page_providers'][ $provider ] = $resolver;
	return true;
}

function adam_ui_is_system_page_protected( int $page_id ): bool {
	return '1' === (string) ( $GLOBALS['adam_meta'][ $page_id ]['_adam_system_page_protected'] ?? '' );
}

function adam_ui_set_system_page_protected( int $page_id, bool $protected ): bool {
	if ( $protected ) {
		$GLOBALS['adam_meta'][ $page_id ]['_adam_system_page_protected'] = '1';
	} else {
		unset( $GLOBALS['adam_meta'][ $page_id ]['_adam_system_page_protected'] );
	}
	return true;
}

require dirname( __DIR__ ) . '/includes/class-settings.php';
require dirname( __DIR__ ) . '/includes/class-managed-pages.php';
require dirname( __DIR__ ) . '/includes/teams/class-router.php';

use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Settings;
use ADAM\Comunidade\Teams\Router as Team_Router;

Managed_Pages::activate();

$managed_pages = new Managed_Pages();
$managed_pages->register();
$protection_resolver = $GLOBALS['adam_system_page_providers']['adam-comunidade'] ?? null;
assert( is_callable( $protection_resolver ), 'Community pages were not registered with the shared protection service.' );
$protected_page_ids = array_column( $protection_resolver(), 'id' );
assert( 9 === count( $protected_page_ids ), 'Not every managed Community page was registered for protection.' );

$settings = get_option( Settings::OPTION_NAME, array() );
assert( 9 === count( $GLOBALS['adam_posts'] ), 'Activation must create nine managed pages.' );
$community_id = Managed_Pages::id( 'community' );
assert( str_contains( $GLOBALS['adam_posts'][ $community_id ]->post_content, 'adam-community-landing' ), 'The blank Community page did not receive the editable starter composition.' );
$GLOBALS['adam_posts'][ $community_id ]->post_content = '<!-- wp:paragraph --><p>Conteúdo editorial</p><!-- /wp:paragraph -->';
Managed_Pages::activate();
assert( '<!-- wp:paragraph --><p>Conteúdo editorial</p><!-- /wp:paragraph -->' === $GLOBALS['adam_posts'][ $community_id ]->post_content, 'Activation replaced existing Community page content.' );
foreach ( Managed_Pages::definitions() as $module => $definition ) {
	$page_id = absint( $settings[ $definition['option'] ] ?? 0 );
	assert( $page_id > 0, 'Missing stored Page ID for ' . $module );
	assert( $page_id === Managed_Pages::id( $module ), 'Page ID does not resolve for ' . $module );
	assert( $definition['default_slug'] === Managed_Pages::slug( $module ), 'Wrong default slug for ' . $module );
}

Managed_Pages::activate();
assert( 9 === count( $GLOBALS['adam_posts'] ), 'Repeated activation created duplicate pages.' );

$fields_id = Managed_Pages::id( 'fields' );
assert( 'Campos' === $GLOBALS['adam_posts'][ $fields_id ]->post_title, 'The Fields directory must use the Campos default title.' );
$GLOBALS['adam_posts'][ $fields_id ]->post_title = 'Campos Associados';
$migrate_titles = new ReflectionMethod( Managed_Pages::class, 'migrate_legacy_titles' );
$migrate_titles->setAccessible( true );
$migrate_titles->invoke( null );
assert( 'Campos' === $GLOBALS['adam_posts'][ $fields_id ]->post_title, 'The untouched legacy Fields title was not migrated.' );

$teams_id = Managed_Pages::id( 'teams' );
$GLOBALS['adam_posts'][ $teams_id ]->post_title = 'Equipas Associadas Renomeadas';
assert( 'equipas' === Managed_Pages::slug( 'teams' ), 'Changing a title affected routing.' );

$GLOBALS['adam_posts'][ $teams_id ]->post_status = 'trash';
assert( 0 === Managed_Pages::id( 'teams' ), 'A trashed page remained routable.' );
assert( $teams_id === Managed_Pages::id( 'teams', true ), 'A trashed page lost its stored ID.' );

$ensure = new ReflectionMethod( Managed_Pages::class, 'ensure' );
$ensure->setAccessible( true );
$recovered_id = $ensure->invoke( null, 'teams', true );
assert( $teams_id === $recovered_id, 'Recovery created a different page.' );
assert( 9 === count( $GLOBALS['adam_posts'] ), 'Recovery created a duplicate page.' );

unset( $GLOBALS['adam_posts'][ $teams_id ], $GLOBALS['adam_meta'][ $teams_id ] );
$recreated_id = $ensure->invoke( null, 'teams', true );
assert( $recreated_id !== $teams_id, 'Permanent deletion did not recreate the page.' );
assert( 9 === count( $GLOBALS['adam_posts'] ), 'Permanent-deletion recovery created duplicates.' );
$protected_page_ids = array_column( $protection_resolver(), 'id' );
assert( in_array( $recreated_id, $protected_page_ids, true ), 'A recreated page was not registered for protection.' );
assert( ! in_array( $teams_id, $protected_page_ids, true ), 'The deleted page remained registered for protection.' );
adam_ui_set_system_page_protected( $recreated_id, true );
assert( adam_ui_is_system_page_protected( $recreated_id ), 'Protection could not be stored for a recreated page.' );
$teams_id = $recreated_id;
$GLOBALS['adam_posts'][ $teams_id ]->post_title = 'Equipas Associadas Renomeadas';

Team_Router::add_rewrite_rules();
$team_rule = reset( $GLOBALS['adam_rules'] );
assert( str_contains( $team_rule['query'], 'page_id=' . $teams_id ), 'Single routes are not anchored to the managed Page ID.' );

$GLOBALS['adam_posts'][ $teams_id ]->post_name = 'clubes';
$GLOBALS['adam_rules'] = array();
Team_Router::add_rewrite_rules();
assert( isset( $GLOBALS['adam_rules']['^clubes/([^/]+)/?$'] ), 'Changing the WordPress page slug did not update routing.' );
assert( 'Equipas Associadas Renomeadas' === $GLOBALS['adam_posts'][ $teams_id ]->post_title, 'Routing changed the editable page title.' );

$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-managed-pages.php' );
$view   = (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/urls.php' );
assert( str_contains( $source, "adam_ui_register_system_pages( 'adam-comunidade'" ), 'Managed Community pages are not registered with shared protection.' );
assert( str_contains( $source, 'protection_definitions' ), 'Protected token journeys are not defined.' );
assert( str_contains( $view, 'Página Protegida' ) && str_contains( $view, 'Recriar página' ), 'Endereços is missing protection or recovery controls.' );
assert( ! str_contains( $source, 'get_page_by_title' ) );
assert( ! str_contains( $source, 'get_page_by_path' ) );

echo "Managed pages smoke test passed.\n";
