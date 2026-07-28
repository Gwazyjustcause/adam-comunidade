<?php
/**
 * Opt-in logging infrastructure.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Writes structured entries only when logging is enabled.
 */
final class Logger {
	/**
	 * Writes an error log entry.
	 *
	 * Operational failures are always logged because they require administrator
	 * attention. Context must contain identifiers/codes only, never tokens,
	 * passwords, SQL text or stack traces.
	 *
	 * @param string              $message Event message.
	 * @param array<string,mixed> $context Safe event context.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::write( 'ERROR', $message, $context );
	}

	/**
	 * Writes an informational log entry.
	 *
	 * The third argument permits settings-update hooks to use the newly saved
	 * value rather than the cached option value.
	 *
	 * @param string               $message  Event message.
	 * @param array<string,mixed>  $context  Safe event context.
	 * @param array<string,mixed>  $settings Optional current settings.
	 * @return void
	 */
	public static function info( string $message, array $context = array(), array $settings = array() ): void {
		$enabled = $settings['enable_logs'] ?? Settings::get( 'enable_logs' );

		if ( ! $enabled ) {
			return;
		}

		self::write( 'INFO', $message, $context );
	}

	/**
	 * Dispatches a sanitized log line to WordPress/PHP's configured error log.
	 *
	 * @param string              $level   Log level.
	 * @param string              $message Event message.
	 * @param array<string,mixed> $context Event context.
	 * @return void
	 */
	private static function write( string $level, string $message, array $context ): void {
		$entry = sprintf(
			'[ADAM Comunidade] [%s] %s',
			sanitize_key( $level ),
			sanitize_text_field( $message )
		);

		if ( ! empty( $context ) ) {
			$entry .= ' ' . wp_json_encode( Helpers::sanitize( $context ) );
		}

		/**
		 * Fires for integrations that store plugin logs elsewhere.
		 *
		 * @param string              $entry   Formatted log line.
		 * @param string              $level   Log level.
		 * @param array<string,mixed> $context Sanitized context.
		 */
		do_action( 'adam_comunidade_log_entry', $entry, $level, $context );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional opt-in plugin logger.
		error_log( $entry );
	}
}
