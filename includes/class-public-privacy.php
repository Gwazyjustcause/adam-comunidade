<?php
/**
 * Privacy policy for public directory output.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps direct contact data private while allowing explicit public profiles.
 */
final class Public_Privacy {
	/**
	 * Direct contact fields that must never be exposed by public renderers.
	 */
	private const DIRECT_CONTACT_FIELDS = array(
		'email',
		'contact_email',
		'phone',
		'telephone',
		'mobile',
		'whatsapp',
	);

	/**
	 * Explicit allowlist for public organisational links.
	 */
	private const PUBLIC_LINK_FIELDS = array(
		'website',
		'facebook',
		'instagram',
		'discord',
		'youtube',
		'tiktok',
		'linkedin',
	);

	/**
	 * Returns validated public web profiles only.
	 *
	 * @return array<string,string>
	 */
	public static function public_links( object $entity ): array {
		$links = array();
		foreach ( self::PUBLIC_LINK_FIELDS as $field ) {
			$value = isset( $entity->{$field} ) ? esc_url_raw( (string) $entity->{$field}, array( 'http', 'https' ) ) : '';
			if ( $value ) {
				$links[ $field ] = $value;
			}
		}
		return $links;
	}

	/**
	 * Removes personal contacts recursively from a public API record.
	 *
	 * @param array<string,mixed> $record Public record candidate.
	 * @return array<string,mixed>
	 */
	public static function without_direct_contacts( array $record ): array {
		foreach ( $record as $key => $value ) {
			$normalized_key = strtolower( (string) $key );
			if (
				in_array( $normalized_key, self::DIRECT_CONTACT_FIELDS, true )
				|| preg_match( '/(?:e_?mail|phone|telephone|mobile|whatsapp|direct_contact|contact_email)/', $normalized_key )
			) {
				unset( $record[ $key ] );
				continue;
			}
			if ( is_array( $value ) ) {
				$record[ $key ] = self::without_direct_contacts( $value );
			}
		}
		return $record;
	}
}
