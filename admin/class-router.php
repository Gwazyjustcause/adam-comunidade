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
				'title'      => sprintf( __( 'Add %s', 'adam-comunidade' ), $config['singular'] ),
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
				'title'      => sprintf( __( 'Edit %s', 'adam-comunidade' ), $config['singular'] ),
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

		add_menu_page(
			__( 'ADAM Comunidade', 'adam-comunidade' ),
			__( 'ADAM Comunidade', 'adam-comunidade' ),
			(string) $dashboard['capability'],
			self::PARENT_SLUG,
			self::callback( self::PARENT_SLUG ),
			'dashicons-groups',
			26
		);

		$routes   = array( self::PARENT_SLUG => $dashboard ) + self::$routes;
		$settings = $routes['adam-comunidade-settings'] ?? null;
		unset( $routes['adam-comunidade-settings'] );
		if ( $settings ) {
			$routes['adam-comunidade-settings'] = $settings;
		}

		foreach ( $routes as $slug => $route ) {
			$hook = add_submenu_page(
				self::PARENT_SLUG,
				(string) $route['title'],
				(string) ( $route['menu_title'] ?: $route['title'] ),
				(string) $route['capability'],
				$slug,
				self::callback( $slug )
			);

			if ( ! empty( $route['load'] ) && is_callable( $route['load'] ) ) {
				add_action( 'load-' . $hook, $route['load'] );
			}
		}

		foreach ( $routes as $slug => $route ) {
			if ( empty( $route['visible'] ) ) {
				remove_submenu_page( self::PARENT_SLUG, $slug );
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
			wp_die( esc_html__( 'Admin route not found.', 'adam-comunidade' ) );
		}

		self::authorize( (string) $route['capability'] );
		$callback = array( $route['controller'], $route['method'] );

		if ( ! is_callable( $callback ) ) {
			wp_die( esc_html__( 'Admin controller is not available.', 'adam-comunidade' ) );
		}

		$arguments = (array) $route['arguments'];
		if ( ! empty( $route['requires_id'] ) ) {
			$id = absint( $_GET['id'] ?? 0 );
			if ( ! $id ) {
				wp_die( esc_html__( 'A valid item ID is required.', 'adam-comunidade' ) );
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
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'adam-comunidade' ) );
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
	 * Adds a normalized route to the registry.
	 *
	 * @param string              $slug  Page slug.
	 * @param array<string,mixed> $route Route configuration.
	 * @return void
	 */
	private static function add_route( string $slug, array $route ): void {
		self::$routes[ sanitize_key( $slug ) ] = wp_parse_args(
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
