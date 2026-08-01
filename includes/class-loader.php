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
	 * Module loading failures shown to administrators after WordPress initializes.
	 *
	 * @var array<int,string>
	 */
	private array $module_failures = array();

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
		$file = self::class_file( $class );

		if ( $file && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolves one plugin class to its case-sensitive filesystem path.
	 *
	 * @param string $class Fully qualified class name.
	 * @return string
	 */
	private static function class_file( string $class ): string {
		$prefix = __NAMESPACE__ . '\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return '';
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

		return $directory . $filename;
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
		$this->register_module( Teams\Module::class );
		$this->register_module( Fields\Module::class );
		$this->register_module( Directory\Module::class );
		$this->register_module( Events\Module::class );
		$this->register_module( Experience\Module::class );
		$this->register_module( Managers\Module::class );

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
	 * Renders module failures without exposing filesystem paths to public visitors.
	 *
	 * @return void
	 */
	public function render_module_failure_notice(): void {
		if ( ! $this->module_failures || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p><strong><?php esc_html_e( 'ADAM Comunidade could not load one or more required modules.', 'adam-comunidade' ); ?></strong></p>
			<ul>
				<?php foreach ( $this->module_failures as $failure ) : ?>
					<li><?php echo esc_html( $failure ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'Reinstall the plugin using a verified complete release package. The rest of the website has been kept online.', 'adam-comunidade' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Autoloads, validates, and registers a module without allowing one missing file
	 * to take down the complete WordPress request.
	 *
	 * @param class-string $class Module class.
	 * @return void
	 */
	private function register_module( string $class ): void {
		try {
			if ( ! class_exists( $class ) ) {
				$this->report_module_failure( $class, 'class not found or module file is unreadable' );
				return;
			}

			$module = new $class();
			if ( ! $module instanceof Module_Interface ) {
				$this->report_module_failure( $class, 'class does not implement Module_Interface' );
				return;
			}

			$this->services['modules']->add( $module );
		} catch ( \Throwable $error ) {
			$this->report_module_failure( $class, $error->getMessage() );
		}
	}

	/**
	 * Records a module failure for the WordPress debug log and wp-admin.
	 *
	 * @param string $class  Module class.
	 * @param string $reason Failure reason.
	 * @return void
	 */
	private function report_module_failure( string $class, string $reason ): void {
		$file    = self::class_file( $class );
		$message = sprintf(
			'Module "%1$s" was not loaded: %2$s. Expected file: %3$s',
			$class,
			$reason,
			$file ?: '(unmapped class)'
		);

		$this->module_failures[] = $message;
		error_log( '[ADAM Comunidade] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		if ( 1 === count( $this->module_failures ) ) {
			add_action( 'admin_notices', array( $this, 'render_module_failure_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'render_module_failure_notice' ) );
		}
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}
}
