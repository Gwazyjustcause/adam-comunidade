<?php
/**
 * Module contract.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Contract implemented by feature modules.
 */
interface Module_Interface {
	/**
	 * Returns a unique module identifier.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Registers module hooks.
	 *
	 * @return void
	 */
	public function register(): void;
}
