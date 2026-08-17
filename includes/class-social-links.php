<?php
/**
 * Validated social/contact links shared by public organisation pages.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the platform allowlist, sanitization, and controlled SVG icons.
 */
final class Social_Links {
	/**
	 * Returns the supported public contact link fields.
	 *
	 * @return string[]
	 */
	public static function fields(): array {
		return array( 'website', 'whatsapp', 'instagram', 'facebook' );
	}

	/**
	 * Sanitizes and validates one platform URL.
	 *
	 * @param string $platform Platform key.
	 * @param mixed  $value    Untrusted URL.
	 * @return string|\WP_Error
	 */
	public static function sanitize( string $platform, mixed $value ): string|\WP_Error {
		$platform = sanitize_key( $platform );
		$url      = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$port  = absint( $parts['port'] ?? 0 );
		$valid = in_array( (string) ( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
			&& $host
			&& empty( $parts['user'] )
			&& empty( $parts['pass'] )
			&& ( 0 === $port || ( 'https' === $parts['scheme'] && 443 === $port ) || ( 'http' === $parts['scheme'] && 80 === $port ) );

		if ( 'website' === $platform && $valid ) {
			return $url;
		}

		$allowed_hosts = array(
			'whatsapp' => array( 'wa.me', 'whatsapp.com', 'www.whatsapp.com', 'chat.whatsapp.com', 'api.whatsapp.com', 'web.whatsapp.com' ),
			'instagram' => array( 'instagram.com', 'www.instagram.com', 'm.instagram.com' ),
			'facebook' => array( 'facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com' ),
		);
		$valid = $valid && isset( $allowed_hosts[ $platform ] ) && in_array( $host, $allowed_hosts[ $platform ], true );

		if ( 'whatsapp' === $platform && $valid ) {
			$path  = strtolower( (string) ( $parts['path'] ?? '' ) );
			$valid = ( 'wa.me' === $host && '/' !== $path )
				|| ( 'chat.whatsapp.com' === $host && '/' !== $path )
				|| ( in_array( $host, array( 'whatsapp.com', 'www.whatsapp.com' ), true ) && ( str_starts_with( $path, '/channel' ) || str_starts_with( $path, '/community' ) || str_starts_with( $path, '/send' ) || str_starts_with( $path, '/contact' ) ) )
				|| ( in_array( $host, array( 'api.whatsapp.com', 'web.whatsapp.com' ), true ) && $path );
		}

		if ( ! $valid ) {
			$labels = array( 'website' => 'Website', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook' );
			return new \WP_Error(
				'invalid_' . $platform,
				sprintf( __( 'Introduza um link válido de %s.', 'adam-comunidade' ), $labels[ $platform ] ?? __( 'rede social', 'adam-comunidade' ) )
			);
		}

		return $url;
	}

	/**
	 * Returns only valid public social/contact links for an entity.
	 *
	 * @return array<string,string>
	 */
	public static function public_links( object $entity ): array {
		$links = array();
		foreach ( self::fields() as $platform ) {
			$value = self::sanitize( $platform, $entity->{$platform} ?? '' );
			if ( is_string( $value ) && '' !== $value ) {
				$links[ $platform ] = $value;
			}
		}
		return $links;
	}

	/**
	 * Renders one controlled inline social icon.
	 */
	public static function icon( string $platform, int $size = 24 ): string {
		$paths = array(
			'website'  => '<circle cx="12" cy="12" r="9.5"/><path d="M2.8 12h18.4M12 2.5c2.2 2.5 3.4 5.7 3.4 9.5S14.2 19 12 21.5C9.8 19 8.6 15.8 8.6 12S9.8 5 12 2.5z"/>',
			'whatsapp' => '<path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.5 0 .1 5.4.1 12c0 2.1.6 4.1 1.6 5.9L0 24l6.3-1.7a12 12 0 0 0 5.8 1.5h.1c6.6 0 11.9-5.4 11.9-12 0-3.2-1.2-6.1-3.6-8.3z"/><path d="M8.3 6.8c.2-.4.4-.4.8-.4h.6c.2 0 .5.1.6.5l.9 2.2c.1.3.1.5-.1.8l-.6.8c-.2.2-.2.4 0 .7.4.7 1 1.6 1.8 2.3.9.8 1.7 1.2 2.4 1.5.3.1.5.1.7-.2l.9-1.1c.2-.3.4-.3.7-.2l2.2 1c.3.1.4.3.3.7-.1.5-.4 1.5-.9 1.8-.5.4-1.2.6-2 .5-1.4-.2-3.1-1-4.8-2.4-1.8-1.5-3.1-3.3-3.7-4.6-.6-1.3-.8-2.3-.7-3.1.1-.6.5-1.2.8-1.8z" fill="currentColor" stroke="none"/>',
			'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>',
			'facebook' => '<path d="M14 8h3V4h-3c-3.3 0-5 2-5 5v3H6v4h3v8h4v-8h3.5l.5-4H13V9c0-.7.3-1 1-1z" fill="currentColor" stroke="none"/>',
		);
		$size = max( 16, min( 48, $size ) );
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
			$size,
			$paths[ $platform ] ?? '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}
