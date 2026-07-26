<?php
/**
 * Plugin Name:       ADAM Comunidade
 * Plugin URI:        https://adam.pt/
 * Description:       Community management foundation for ADAM.
 * Version:           6.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            ADAM
 * Text Domain:       adam-comunidade
 * Domain Path:       /languages
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

define( 'ADAM_COMUNIDADE_VERSION', '6.0.0' );
define( 'ADAM_COMUNIDADE_DB_VERSION', '6.0.0' );
define( 'ADAM_COMUNIDADE_FILE', __FILE__ );
define( 'ADAM_COMUNIDADE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADAM_COMUNIDADE_URL', plugin_dir_url( __FILE__ ) );
define( 'ADAM_COMUNIDADE_BASENAME', plugin_basename( __FILE__ ) );

require_once ADAM_COMUNIDADE_PATH . 'includes/class-loader.php';

ADAM\Comunidade\Loader::register_autoloader();

register_activation_hook( __FILE__, array( ADAM\Comunidade\Install::class, 'activate' ) );

/**
 * Boots the plugin after all plugins are loaded.
 *
 * @return void
 */
function adam_comunidade_boot(): void {
	ADAM\Comunidade\Loader::instance()->boot();
}
add_action( 'plugins_loaded', 'adam_comunidade_boot' );
