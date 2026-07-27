<?php
/**
 * Plugin autoloader and service bootstrap.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Admin;
use ADAM\Comunidade\Admin\Assets as Admin_Assets;
use ADAM\Comunidade\Admin\Router as Admin_Router;

/**
 * Loads and coordinates plugin services.
 */
final class Loader {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service instances.
	 *
	 * @var array<string,object>
	 */
	private array $services = array();

	/**
	 * Whether the plugin has booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared loader.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the plugin namespace autoloader.
	 *
	 * @return void
	 */
	public static function register_autoloader(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Loads an ADAM Comunidade class.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class_name = array_pop( $parts );
		$filename = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		$directory = ADAM_COMUNIDADE_PATH;

		if ( ! empty( $parts ) && 'Admin' === $parts[0] ) {
			array_shift( $parts );
			$directory .= 'admin/';
		} else {
			$directory .= 'includes/';
		}

		if ( ! empty( $parts ) ) {
			$directory .= strtolower( implode( '/', $parts ) ) . '/';
		}

		$file = $directory . $filename;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Registers core services and modules.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// WordPress 6.7+ requires plugin translations to load on init or later.
		load_plugin_textdomain( 'adam-comunidade', false, dirname( ADAM_COMUNIDADE_BASENAME ) . '/languages' );

		$this->services['settings']    = new Settings();
		$this->services['managed_pages'] = new Managed_Pages();
		$this->services['assets']      = new Assets();
		$this->services['post_types']  = new Post_Types();
		$this->services['shortcodes']  = new Shortcodes();
		$this->services['modules']     = new Module_Manager();
		$this->services['modules']->add( new Teams\Module() );
		$this->services['modules']->add( new Fields\Module() );
		$this->services['modules']->add( new Directory\Module() );
		$this->services['modules']->add( new Events\Module() );
		$this->services['modules']->add( new Experience\Module() );

		if ( is_admin() ) {
			$admin = new Admin();
			$this->services['admin']        = $admin;
			$this->services['admin_router'] = new Admin_Router();
			$this->services['admin_assets'] = new Admin_Assets();
		}

		foreach ( $this->services as $service ) {
			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}
		}

		/**
		 * Fires before independently registered modules are initialized.
		 *
		 * @param Module_Manager $manager Module registry.
		 */
		do_action( 'adam_comunidade_register_modules', $this->services['modules'] );

		$this->services['modules']->boot();

		do_action( 'adam_comunidade_loaded', $this );
	}

	/**
	 * Retrieves a registered service.
	 *
	 * @param string $name Service key.
	 * @return object|null
	 */
	public function service( string $name ): ?object {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}
}
