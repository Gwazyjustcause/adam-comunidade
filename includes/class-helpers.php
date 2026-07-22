<?php
/**
 * Shared helper library.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable, side-effect-light helpers for all feature modules.
 */
final class Helpers {
	/**
	 * Returns a trusted SVG icon from the internal icon set.
	 *
	 * @param string $name  Icon name.
	 * @param int    $size  Width and height in pixels.
	 * @param string $class Optional CSS class.
	 * @return string
	 */
	public static function svg_icon( string $name, int $size = 20, string $class = '' ): string {
		$paths = array(
			'community' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>'
				. '<circle cx="9" cy="7" r="4"/>'
				. '<path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
			'check'     => '<path d="m20 6-11 11-5-5"/>',
			'chart'     => '<path d="M3 3v18h18"/><path d="m7 16 4-5 4 3 5-7"/>',
			'settings'  => '<circle cx="12" cy="12" r="3"/>'
				. '<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-2.83 2.83-.06-.06'
				. 'a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-4v-.09'
				. 'a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06-2.83-2.83.06-.06'
				. 'A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3v-4h.09A1.65 1.65 0 0 0 4.6 9'
				. 'a1.65 1.65 0 0 0-.33-1.82l-.06-.06 2.83-2.83.06.06A1.65 1.65 0 0 0 8.92 4'
				. 'a1.65 1.65 0 0 0 1-1.51V2h4v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33'
				. 'l.06-.06 2.83 2.83-.06.06A1.65 1.65 0 0 0 19.4 9c.12.37.19.76.19 1.15V14'
				. 'c0 .35-.07.69-.19 1z"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		$size  = max( 12, min( 128, $size ) );
		$class = sanitize_html_class( $class );

		$svg = '<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none"'
			. ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
			. ' aria-hidden="true">%3$s</svg>';

		return sprintf(
			$svg,
			esc_attr( $class ),
			$size,
			$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Paths are hard-coded above.
		);
	}

	/**
	 * Recursively sanitizes scalar data.
	 *
	 * @param mixed $value Untrusted value.
	 * @return mixed
	 */
	public static function sanitize( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'sanitize' ), $value );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Escapes plain text.
	 *
	 * @param mixed $value Value to escape.
	 * @return string
	 */
	public static function escape( mixed $value ): string {
		return esc_html( (string) $value );
	}

	/**
	 * Returns an attachment image URL or an empty string.
	 *
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Registered size or dimensions.
	 * @return string
	 */
	public static function image_url( int $attachment_id, string|array $size = 'full' ): string {
		$url = wp_get_attachment_image_url( absint( $attachment_id ), $size );

		return $url ? esc_url( $url ) : '';
	}

	/**
	 * Formats a date using the site's locale and timezone.
	 *
	 * @param int|string  $date   Timestamp or parseable date.
	 * @param string|null $format Optional WordPress date format.
	 * @return string
	 */
	public static function format_date( int|string $date, ?string $format = null ): string {
		$timestamp = is_int( $date ) ? $date : strtotime( $date );

		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( $format ?: get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Builds an absolute plugin path.
	 *
	 * @param string $relative Relative path.
	 * @return string
	 */
	public static function path( string $relative = '' ): string {
		return ADAM_COMUNIDADE_PATH . ltrim( $relative, '/\\' );
	}

	/**
	 * Builds an absolute plugin URL.
	 *
	 * @param string $relative Relative URL path.
	 * @return string
	 */
	public static function url( string $relative = '' ): string {
		return ADAM_COMUNIDADE_URL . ltrim( $relative, '/\\' );
	}

	/**
	 * Queues a user-specific admin notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    success, error, warning, or info.
	 * @return void
	 */
	public static function add_admin_notice( string $message, string $type = 'info' ): void {
		$allowed = array( 'success', 'error', 'warning', 'info' );
		$type    = in_array( $type, $allowed, true ) ? $type : 'info';
		$key     = 'adam_comunidade_notice_' . get_current_user_id();

		set_transient( $key, array( 'message' => sanitize_text_field( $message ), 'type' => $type ), MINUTE_IN_SECONDS );
	}

	/**
	 * Displays and clears the current user's queued notice.
	 *
	 * @return void
	 */
	public static function render_admin_notices(): void {
		$key    = 'adam_comunidade_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ?? 'info' ),
			esc_html( $notice['message'] )
		);
	}
}
