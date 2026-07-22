<?php
/**
 * Independent module registry.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Registers, enables, and boots feature modules.
 */
final class Module_Manager {
	/**
	 * Registered modules.
	 *
	 * @var array<string,Module_Interface>
	 */
	private array $modules = array();

	/**
	 * Exists for consistency with other services.
	 *
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Adds a module to the registry.
	 *
	 * @param Module_Interface $module Module instance.
	 * @return void
	 */
	public function add( Module_Interface $module ): void {
		$this->modules[ sanitize_key( $module->id() ) ] = $module;
	}

	/**
	 * Boots all enabled modules.
	 *
	 * @return void
	 */
	public function boot(): void {
		foreach ( $this->modules as $id => $module ) {
			/**
			 * Filters whether a feature module is enabled.
			 *
			 * @param bool             $enabled Whether the module is enabled.
			 * @param string           $id      Module identifier.
			 * @param Module_Interface $module  Module instance.
			 */
			$enabled = (bool) apply_filters( 'adam_comunidade_module_enabled', true, $id, $module );

			if ( $enabled ) {
				$module->register();
			}
		}
	}

	/**
	 * Returns registered modules.
	 *
	 * @return array<string,Module_Interface>
	 */
	public function all(): array {
		return $this->modules;
	}
}
