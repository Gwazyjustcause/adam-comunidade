<?php
/**
 * Central administration router.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Owns every ADAM Comunidade admin page, route, permission check and URL.
 */
final class Router {
	public const PARENT_SLUG = 'adam-comunidade-dashboard';
	private const HIDDEN_PARENT_SLUG = 'options.php';

	/**
	 * Registered routes keyed by page slug.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $routes = array();

	/**
	 * Registered modules keyed by module ID.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $modules = array();

	/**
	 * Pages registered with WordPress during admin_menu.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private static array $registered_pages = array();

	/**
	 * Chronological log of every add_menu_page() and add_submenu_page() call.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private static array $registration_log = array();

	/**
	 * Hooks the router into WordPress after modules have registered their routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_pages' ), 100 );
	}

	/**
	 * Registers a conventional list/create/edit module.
	 *
	 * @param string              $id   Plural module ID, for example "teams".
	 * @param array<string,mixed> $args Module configuration.
	 * @return void
	 */
	public static function register_module( string $id, array $args ): void {
		$id = sanitize_key( $id );

		if ( ! $id || isset( self::$modules[ $id ] ) ) {
			return;
		}

		$singular = sanitize_key( (string) ( $args['singular_slug'] ?? rtrim( $id, 's' ) ) );
		$config   = wp_parse_args(
			$args,
			array(
				'title'       => ucfirst( $id ),
				'singular'    => ucfirst( $singular ),
				'capability'  => self::capability(),
				'controller'  => null,
				'methods'     => array(),
				'arguments'   => array(),
				'load'        => null,
				'menu'        => true,
			)
		);
		$methods  = wp_parse_args(
			$config['methods'],
			array(
				'list'   => 'index',
				'create' => 'create',
				'edit'   => 'edit',
			)
		);

		$config['id']            = $id;
		$config['singular_slug'] = $singular;
		self::$modules[ $id ]    = $config;

		self::add_route(
			'adam-comunidade-' . $id,
			array(
				'title'      => $config['title'],
				'menu_title' => $config['title'],
				'capability' => $config['capability'],
				'controller' => $config['controller'],
				'method'     => $methods['list'],
				'visible'    => (bool) $config['menu'],
				'load'       => $config['load'],
				'arguments'  => $config['arguments'],
			)
		);
		self::add_route(
			'adam-comunidade-' . $singular . '-add',
			array(
				'title'      => sprintf( __( 'Adicionar %s', 'adam-comunidade' ), $config['singular'] ),
				'capability' => $config['capability'],
				'controller' => $config['controller'],
				'method'     => $methods['create'],
				'visible'    => false,
				'arguments'  => $config['arguments'],
			)
		);
		self::add_route(
			'adam-comunidade-' . $singular . '-edit',
			array(
				'title'      => sprintf( __( 'Editar %s', 'adam-comunidade' ), $config['singular'] ),
				'capability' => $config['capability'],
				'controller' => $config['controller'],
				'method'     => $methods['edit'],
				'visible'    => false,
				'arguments'  => $config['arguments'],
				'requires_id' => true,
			)
		);
	}

	/**
	 * Registers a non-CRUD admin page through the same router.
	 *
	 * @param string              $id   Route ID.
	 * @param array<string,mixed> $args Route configuration.
	 * @return void
	 */
	public static function register_page( string $id, array $args ): void {
		$id   = sanitize_key( $id );
		$slug = (string) ( $args['slug'] ?? 'adam-comunidade-' . $id );
		self::add_route( $slug, $args );
	}

	/**
	 * Registers all collected routes with WordPress.
	 *
	 * @return void
	 */
	public function register_pages(): void {
		$dashboard = self::$routes[ self::PARENT_SLUG ] ?? null;

		if ( ! $dashboard ) {
			return;
		}

		$menu_title    = trim( (string) __( 'ADAM Comunidade', 'adam-comunidade' ) ) ?: 'ADAM Comunidade';
		$menu_callback = self::callback( self::PARENT_SLUG );
		$menu_hook     = add_menu_page(
			$menu_title,
			$menu_title,
			(string) $dashboard['capability'],
			self::PARENT_SLUG,
			$menu_callback,
			'dashicons-groups',
			26
		);
		self::record_registration(
			self::PARENT_SLUG,
			'',
			$menu_hook,
			array_merge( $dashboard, array( 'title' => $menu_title ) ),
			$menu_callback,
			'menu'
		);

		$routes = array( self::PARENT_SLUG => $dashboard ) + self::$routes;
		uksort(
			$routes,
			static fn( string $first, string $second ): int => self::menu_order( $first ) <=> self::menu_order( $second )
		);

		foreach ( $routes as $slug => $route ) {
			$parent_slug = ! empty( $route['visible'] ) ? self::PARENT_SLUG : self::HIDDEN_PARENT_SLUG;
			$callback    = self::callback( $slug );
			$hook = add_submenu_page(
				$parent_slug,
				(string) $route['title'],
				(string) ( $route['menu_title'] ?: $route['title'] ),
				(string) $route['capability'],
				$slug,
				$callback
			);

			self::record_registration( $slug, $parent_slug, $hook, $route, $callback, 'submenu' );

			if ( $hook && ! empty( $route['load'] ) && is_callable( $route['load'] ) ) {
				add_action( 'load-' . $hook, $route['load'] );
			}
		}
	}

	/**
	 * Dispatches a registered route to its controller.
	 *
	 * @param string $slug Page slug.
	 * @return void
	 */
	public static function dispatch( string $slug ): void {
		$route = self::$routes[ $slug ] ?? null;

		if ( ! $route ) {
			wp_die( esc_html__( 'A página de administração não foi encontrada.', 'adam-comunidade' ) );
		}

		self::authorize( (string) $route['capability'] );
		$callback = array( $route['controller'], $route['method'] );

		if ( ! is_callable( $callback ) ) {
			wp_die( esc_html__( 'O controlador da página não está disponível.', 'adam-comunidade' ) );
		}

		$arguments = (array) $route['arguments'];
		if ( ! empty( $route['requires_id'] ) ) {
			$id = absint( $_GET['id'] ?? 0 );
			if ( ! $id ) {
				wp_die( esc_html__( 'É necessário indicar um identificador válido.', 'adam-comunidade' ) );
			}
			$arguments[] = $id;
		}

		call_user_func_array( $callback, $arguments );
	}

	/**
	 * Builds a conventional module URL.
	 *
	 * @param string              $module Module ID.
	 * @param string              $action list, add, or edit.
	 * @param array<string,mixed> $args   Additional query arguments.
	 * @return string
	 */
	public static function module_url( string $module, string $action = 'list', array $args = array() ): string {
		$module = sanitize_key( $module );
		$config = self::$modules[ $module ] ?? null;

		if ( ! $config ) {
			return self::page_url( $module, $args );
		}

		$slug = 'adam-comunidade-' . $module;
		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$slug = 'adam-comunidade-' . $config['singular_slug'] . '-' . $action;
		}

		return self::url( $slug, $args );
	}

	/**
	 * Builds a URL for a named auxiliary page.
	 *
	 * @param string              $id   Route ID or complete slug.
	 * @param array<string,mixed> $args Query arguments.
	 * @return string
	 */
	public static function page_url( string $id, array $args = array() ): string {
		$slug = str_starts_with( $id, 'adam-comunidade-' ) ? $id : 'adam-comunidade-' . sanitize_key( $id );
		return self::url( $slug, $args );
	}

	/**
	 * Redirects to a module route and terminates the request.
	 *
	 * @param string              $module Module ID.
	 * @param string              $action Route action.
	 * @param array<string,mixed> $args   Query arguments.
	 * @return never
	 */
	public static function redirect_module( string $module, string $action = 'list', array $args = array() ): never {
		wp_safe_redirect( self::module_url( $module, $action, $args ) );
		exit;
	}

	/**
	 * Enforces the plugin-wide admin capability.
	 *
	 * @param string|null $capability Optional registered route capability.
	 * @return void
	 */
	public static function authorize( ?string $capability = null ): void {
		$required = $capability ?: self::capability();

		if ( ! current_user_can( $required ) ) {
			wp_die( esc_html__( 'Não tem permissão para aceder a esta página.', 'adam-comunidade' ) );
		}
	}

	/**
	 * Returns the single capability used throughout the admin.
	 *
	 * @return string
	 */
	public static function capability(): string {
		return (string) apply_filters( 'adam_comunidade_admin_capability', 'manage_options' );
	}

	/**
	 * Exposes routes for diagnostics and automated tests.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function routes(): array {
		return self::$routes;
	}

	/**
	 * Exposes the exact pages handed to WordPress during admin_menu.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function registered_pages(): array {
		return self::$registered_pages;
	}

	/**
	 * Returns the chronological WordPress menu-registration audit.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function registration_log(): array {
		return self::$registration_log;
	}

	/**
	 * Adds a normalized route to the registry.
	 *
	 * @param string              $slug  Page slug.
	 * @param array<string,mixed> $route Route configuration.
	 * @return void
	 */
	private static function add_route( string $slug, array $route ): void {
		$slug       = sanitize_key( $slug );
		$normalized = wp_parse_args(
			$route,
			array(
				'title'      => '',
				'menu_title' => '',
				'capability' => self::capability(),
				'controller' => null,
				'method'     => 'index',
				'arguments'  => array(),
				'visible'    => true,
				'load'       => null,
				'requires_id' => false,
			)
		);
		$title      = trim( (string) $normalized['title'] );

		if ( '' === $title ) {
			$title = ucwords(
				str_replace(
					'-',
					' ',
					str_replace( 'adam-comunidade-', '', $slug )
				)
			);
		}

		$normalized['title'] = $title ?: __( 'ADAM Comunidade', 'adam-comunidade' );
		if ( '' === trim( (string) $normalized['menu_title'] ) ) {
			$normalized['menu_title'] = $normalized['title'];
		}

		self::$routes[ $slug ] = $normalized;
	}

	/**
	 * Returns the administrator-focused order for visible menu pages.
	 *
	 * Hidden routes remain registered for direct links but are placed after the
	 * visible navigation because their order is irrelevant to the menu.
	 *
	 * @param string $slug Route slug.
	 * @return int
	 */
	private static function menu_order( string $slug ): int {
		$preferred = array(
			self::PARENT_SLUG              => 10,
			'adam-comunidade-moderation'   => 20,
			'adam-comunidade-teams'        => 30,
			'adam-comunidade-fields'       => 40,
			'adam-comunidade-partners'     => 50,
			'adam-comunidade-institutions' => 60,
			'adam-comunidade-news'         => 70,
			'adam-comunidade-events'       => 80,
			'adam-comunidade-forms'        => 90,
			'adam-comunidade-urls'         => 100,
			'adam-comunidade-settings'     => 110,
		);

		if ( isset( $preferred[ $slug ] ) ) {
			return $preferred[ $slug ];
		}

		return ! empty( self::$routes[ $slug ]['visible'] ) ? 85 : 95;
	}

	/**
	 * Creates a menu callback that only dispatches through this router.
	 *
	 * @param string $slug Route slug.
	 * @return callable
	 */
	private static function callback( string $slug ): callable {
		return static function () use ( $slug ): void {
			self::dispatch( $slug );
		};
	}

	/**
	 * Records and safely logs one WordPress page registration.
	 *
	 * @param string              $slug        Registered menu slug.
	 * @param string              $parent_slug WordPress parent slug, empty for hidden routes.
	 * @param string|false        $hook         Hook suffix returned by WordPress.
	 * @param array<string,mixed> $route        Normalized route.
	 * @param callable            $callback     WordPress page callback.
	 * @param string              $registration_type WordPress menu API used.
	 * @return void
	 */
	private static function record_registration(
		string $slug,
		string $parent_slug,
		string|false $hook,
		array $route,
		callable $callback,
		string $registration_type
	): void {
		$controller_callback = array( $route['controller'], $route['method'] );
		$registration        = array(
			'type'                => $registration_type,
			'slug'                => $slug,
			'parent_slug'         => $parent_slug,
			'page_title'          => (string) $route['title'],
			'hook'                => $hook,
			'capability'          => (string) $route['capability'],
			'visible'             => (bool) $route['visible'],
			'callback_callable'   => is_callable( $callback ),
			'controller_callable' => is_callable( $controller_callback ),
		);

		self::$registered_pages[ $slug ] = $registration;
		self::$registration_log[]        = $registration;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				'[ADAM Comunidade] Admin page registered: ' . wp_json_encode( $registration )
			);
		}
	}

	/**
	 * Builds the final wp-admin URL.
	 *
	 * @param string              $slug Page slug.
	 * @param array<string,mixed> $args Query arguments.
	 * @return string
	 */
	private static function url( string $slug, array $args ): string {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}
}
