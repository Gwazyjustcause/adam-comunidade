<?php
/**
 * Standalone smoke test for the central admin router.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$GLOBALS['adam_test_submenus'] = array();
$GLOBALS['adam_test_removed']  = array();
$GLOBALS['adam_test_actions']  = array();

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) ) ?? '';
}

function wp_parse_args( array $args, array $defaults = array() ): array {
	return array_merge( $defaults, $args );
}

function apply_filters( string $hook, mixed $value ): mixed {
	unset( $hook );
	return $value;
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function esc_html__( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function add_query_arg( array|string $args, string|false $value = false, string|false $url = false ): string {
	if ( is_string( $args ) ) {
		$args = array( $args => $value );
		$url  = (string) $url;
	} else {
		$url = (string) $value;
	}
	$separator = str_contains( $url, '?' ) ? '&' : '?';
	return $url . $separator . http_build_query( $args );
}

function add_action( string $hook, callable $callback, int $priority = 10 ): void {
	$GLOBALS['adam_test_actions'][ $hook ][ $priority ][] = $callback;
}

function add_menu_page(
	string $page_title,
	string $menu_title,
	string $capability,
	string $menu_slug,
	callable $callback,
	string $icon = '',
	int $position = 0
): string {
	unset( $page_title, $menu_title, $capability, $callback, $icon, $position );
	return 'toplevel_page_' . $menu_slug;
}

function add_submenu_page(
	string $parent_slug,
	string $page_title,
	string $menu_title,
	string $capability,
	string $menu_slug,
	callable $callback
): string {
	unset( $menu_title );
	$GLOBALS['adam_test_submenus'][ $menu_slug ] = compact( 'parent_slug', 'page_title', 'capability', 'callback' );
	return 'adam-comunidade-dashboard' === $parent_slug
		? 'adam-comunidade_page_' . $menu_slug
		: 'admin_page_' . $menu_slug;
}

function remove_submenu_page( string $parent_slug, string $menu_slug ): void {
	$GLOBALS['adam_test_removed'][] = compact( 'parent_slug', 'menu_slug' );
}

function current_user_can( string $capability ): bool {
	return 'manage_options' === $capability;
}

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function wp_die( string $message ): never {
	throw new RuntimeException( $message );
}

function wp_safe_redirect( string $url ): void {
	unset( $url );
}

require dirname( __DIR__ ) . '/admin/class-router.php';

use ADAM\Comunidade\Admin\Router;

$controller = new class() {
	public array $calls = array();

	public function dashboard(): void {
		$this->calls[] = 'dashboard';
	}

	public function settings(): void {
		$this->calls[] = 'settings';
	}

	public function list( string $type = '' ): void {
		$this->calls[] = 'list:' . $type;
	}

	public function create( string $type = '' ): void {
		$this->calls[] = 'create:' . $type;
	}

	public function edit( string $type = '', int $id = 0 ): void {
		$this->calls[] = 'edit:' . $type . ':' . $id;
	}
};

Router::register_page( 'dashboard', array( 'title' => 'Dashboard', 'controller' => $controller, 'method' => 'dashboard' ) );
Router::register_page( 'moderation', array( 'title' => 'Approvals', 'controller' => $controller, 'method' => 'settings' ) );
Router::register_page( 'forms', array( 'title' => 'Forms', 'controller' => $controller, 'method' => 'settings' ) );
Router::register_page( 'urls', array( 'title' => 'Addresses', 'controller' => $controller, 'method' => 'settings' ) );
Router::register_page( 'settings', array( 'title' => 'Settings', 'controller' => $controller, 'method' => 'settings' ) );
Router::register_page(
	'fallback-title',
	array(
		'title'      => null,
		'controller' => $controller,
		'method'     => 'settings',
		'visible'    => false,
	)
);

$modules = array(
	'teams'        => array( 'team', '' ),
	'fields'       => array( 'field', '' ),
	'partners'     => array( 'partner', 'partner' ),
	'institutions' => array( 'institution', 'institution' ),
	'news'         => array( 'news', '' ),
);

foreach ( $modules as $module => $definition ) {
	list( $singular, $argument ) = $definition;
	Router::register_module(
		$module,
		array(
			'title'         => ucfirst( $module ),
			'singular'      => ucfirst( $singular ),
			'singular_slug' => $singular,
			'controller'    => $controller,
			'methods'       => array( 'list' => 'list', 'create' => 'create', 'edit' => 'edit' ),
			'arguments'     => $argument ? array( $argument ) : array(),
		)
	);
}

$router = new Router();
$router->register();
assert(
	isset( $GLOBALS['adam_test_actions']['admin_menu'][100][0] ),
	'The central router must register pages on admin_menu.'
);
$router->register_pages();
assert( 'menu' === Router::registration_log()[0]['type'], 'The top-level add_menu_page() call must be audited.' );
assert(
	count( $GLOBALS['adam_test_submenus'] ) + 1 === count( Router::registration_log() ),
	'Every WordPress menu API call must be included in the registration audit.'
);

$expected = array(
	'adam-comunidade-dashboard',
	'adam-comunidade-moderation',
	'adam-comunidade-teams',
	'adam-comunidade-team-add',
	'adam-comunidade-team-edit',
	'adam-comunidade-fields',
	'adam-comunidade-field-add',
	'adam-comunidade-field-edit',
	'adam-comunidade-partners',
	'adam-comunidade-partner-add',
	'adam-comunidade-partner-edit',
	'adam-comunidade-institutions',
	'adam-comunidade-institution-add',
	'adam-comunidade-institution-edit',
	'adam-comunidade-forms',
	'adam-comunidade-urls',
	'adam-comunidade-settings',
);

foreach ( $expected as $slug ) {
	assert( isset( Router::routes()[ $slug ] ), 'Missing route: ' . $slug );
	assert( isset( $GLOBALS['adam_test_submenus'][ $slug ] ), 'Page not registered with WordPress: ' . $slug );
	assert( 'manage_options' === $GLOBALS['adam_test_submenus'][ $slug ]['capability'], 'Wrong capability: ' . $slug );
	assert( isset( Router::registered_pages()[ $slug ] ), 'Missing registration diagnostic: ' . $slug );
	assert( Router::registered_pages()[ $slug ]['callback_callable'], 'Invalid WordPress callback: ' . $slug );
	assert( Router::registered_pages()[ $slug ]['controller_callable'], 'Invalid controller callback: ' . $slug );
	assert( '' !== Router::registered_pages()[ $slug ]['page_title'], 'Empty page title: ' . $slug );
}
assert( 'Fallback Title' === Router::registered_pages()['adam-comunidade-fallback-title']['page_title'] );

foreach ( $modules as $module => $definition ) {
	$singular = $definition[0];
	assert(
		str_contains( Router::module_url( $module, 'add' ), 'page=adam-comunidade-' . $singular . '-add' ),
		'Invalid add URL for ' . $module
	);
	assert(
		str_contains( Router::module_url( $module, 'edit', array( 'id' => 8 ) ), 'page=adam-comunidade-' . $singular . '-edit&id=8' ),
		'Invalid edit URL for ' . $module
	);
}

assert(
	'https://example.test/wp-admin/admin.php?page=adam-comunidade-team-edit&id=15'
	=== Router::module_url( 'teams', 'edit', array( 'id' => 15 ) )
);

Router::dispatch( 'adam-comunidade-partner-add' );
assert( array( 'create:partner' ) === $controller->calls );
$_GET['id'] = 15;
Router::dispatch( 'adam-comunidade-partner-edit' );
assert( array( 'create:partner', 'edit:partner:15' ) === $controller->calls );
unset( $_GET['id'] );

$registered_slugs = array_keys( $GLOBALS['adam_test_submenus'] );
assert( 'adam-comunidade-settings' === end( $registered_slugs ), 'Settings must be the final submenu.' );
$visible_slugs = array_keys(
	array_filter(
		$GLOBALS['adam_test_submenus'],
		static fn( array $page ): bool => Router::PARENT_SLUG === $page['parent_slug']
	)
);
assert(
	array(
		'adam-comunidade-dashboard',
		'adam-comunidade-moderation',
		'adam-comunidade-teams',
		'adam-comunidade-fields',
		'adam-comunidade-partners',
		'adam-comunidade-institutions',
		'adam-comunidade-news',
		'adam-comunidade-forms',
		'adam-comunidade-urls',
		'adam-comunidade-settings',
	) === $visible_slugs,
	'Visible administration pages are not in the expected task-focused order.'
);
assert( ! isset( Router::routes()['adam-comunidade-brands'] ), 'Brands must not be a standalone administration module.' );
assert( ! isset( Router::routes()['adam-comunidade-calendar'] ), 'Calendar must not be a standalone administration page.' );

foreach ( $modules as $module => $definition ) {
	$add_slug  = 'adam-comunidade-' . $definition[0] . '-add';
	$edit_slug = 'adam-comunidade-' . $definition[0] . '-edit';
	assert( 'options.php' === $GLOBALS['adam_test_submenus'][ $add_slug ]['parent_slug'], 'Add route must be registered as hidden: ' . $add_slug );
	assert( 'options.php' === $GLOBALS['adam_test_submenus'][ $edit_slug ]['parent_slug'], 'Edit route must be registered as hidden: ' . $edit_slug );
	assert( 'admin_page_' . $add_slug === Router::registered_pages()[ $add_slug ]['hook'] );
	assert( 'admin_page_' . $edit_slug === Router::registered_pages()[ $edit_slug ]['hook'] );
	assert( '' !== $GLOBALS['adam_test_submenus'][ $add_slug ]['page_title'] );
	assert( '' !== $GLOBALS['adam_test_submenus'][ $edit_slug ]['page_title'] );
}
assert( array() === $GLOBALS['adam_test_removed'], 'Registered routes must never be removed from WordPress menus.' );

foreach (
	array(
		'adam-comunidade-field-add'       => 'Add Field',
		'adam-comunidade-field-edit'      => 'Edit Field',
		'adam-comunidade-team-add'        => 'Add Team',
		'adam-comunidade-team-edit'       => 'Edit Team',
		'adam-comunidade-partner-add'     => 'Add Partner',
		'adam-comunidade-institution-add' => 'Add Institution',
	) as $required_hidden_route => $expected_page_title
) {
	assert( isset( Router::registered_pages()[ $required_hidden_route ] ), 'Required route was not registered: ' . $required_hidden_route );
	assert( 'manage_options' === Router::registered_pages()[ $required_hidden_route ]['capability'] );
	assert( Router::registered_pages()[ $required_hidden_route ]['controller_callable'] );
	assert( $expected_page_title === Router::registered_pages()[ $required_hidden_route ]['page_title'] );
}

$source_files = array_merge(
	glob( dirname( __DIR__ ) . '/admin/*.php' ) ?: array(),
	glob( dirname( __DIR__ ) . '/includes/**/*.php' ) ?: array()
);
foreach ( $source_files as $source_file ) {
	$normalized_file = str_replace( '\\', '/', $source_file );
	if ( str_ends_with( $normalized_file, 'admin/class-router.php' ) ) {
		continue;
	}
	$source = (string) file_get_contents( $source_file );
	assert( ! str_contains( $source, 'adam_comunidade_admin_menu' ), 'Legacy menu hook in ' . $source_file );
	assert( ! str_contains( $source, 'add_submenu_page(' ), 'Direct submenu registration in ' . $source_file );
	assert( ! str_contains( $source, "admin_url( 'admin.php?page=" ), 'Hardcoded admin route in ' . $source_file );
}

echo "Admin router smoke test passed.\n";
