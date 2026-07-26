<?php
/**
 * Directory input validation.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes editor payloads and reports uniqueness errors.
 */
final class Validator {
	/**
	 * Sanitizes a submitted entry.
	 *
	 * @return array<string,mixed>
	 */
	public static function sanitize( string $type, array $input ): array {
		$definition = Types::get( $type );
		$status     = sanitize_key( $input['status'] ?? 'draft' );
		$category   = sanitize_key( $input['category'] ?? '' );
		if ( ! in_array( $status, array( 'draft', 'published', 'hidden' ), true ) ) {
			$status = 'draft';
		}
		if ( $definition && $definition['categories'] && ! isset( $definition['categories'][ $category ] ) ) {
			$category = '';
		}
		$data = array(
			'entity_type'         => $type,
			'name'                => sanitize_text_field( $input['name'] ?? '' ),
			'slug'                => sanitize_title( $input['slug'] ?? $input['name'] ?? '' ),
			'status'              => $status,
			'logo_id'             => self::image_id( $input['logo_id'] ?? 0 ),
			'cover_id'            => self::image_id( $input['cover_id'] ?? 0 ),
			'short_description'   => sanitize_textarea_field( $input['short_description'] ?? '' ),
			'full_description'    => wp_kses_post( $input['full_description'] ?? '' ),
			'website'             => self::url( $input['website'] ?? '' ),
			'facebook'            => self::url( $input['facebook'] ?? '' ),
			'instagram'           => self::url( $input['instagram'] ?? '' ),
			'email'               => sanitize_email( $input['email'] ?? '' ),
			'phone'               => preg_replace( '/[^0-9+().\s-]/', '', (string) ( $input['phone'] ?? '' ) ),
			'address'             => sanitize_text_field( $input['address'] ?? '' ),
			'district'            => sanitize_text_field( $input['district'] ?? '' ),
			'latitude'            => self::coordinate( $input['latitude'] ?? '', -90, 90 ),
			'longitude'           => self::coordinate( $input['longitude'] ?? '', -180, 180 ),
			'category'            => $category,
			'benefits'            => wp_kses_post( $input['benefits'] ?? '' ),
			'member_benefits'     => wp_kses_post( $input['member_benefits'] ?? '' ),
			'featured'            => empty( $input['featured'] ) ? 0 : 1,
			'homepage_featured'   => empty( $input['homepage_featured'] ) ? 0 : 1,
			'verification'        => in_array( sanitize_key( $input['verification'] ?? '' ), array( '', 'official_partner', 'adam_partner', 'institutional_partner' ), true ) ? sanitize_key( $input['verification'] ?? '' ) : '',
			'priority'            => intval( $input['priority'] ?? 0 ),
			'country'             => sanitize_text_field( $input['country'] ?? '' ),
			'popular_products'    => sanitize_textarea_field( $input['popular_products'] ?? '' ),
			'official_distributor'=> empty( $input['official_distributor'] ) ? 0 : 1,
			'notes'               => wp_kses_post( $input['notes'] ?? '' ),
			'promo_pdf_id'        => self::pdf_id( $input['promo_pdf_id'] ?? 0 ),
			'catalogue_id'        => self::pdf_id( $input['catalogue_id'] ?? 0 ),
			'meta_title'          => sanitize_text_field( $input['meta_title'] ?? '' ),
			'meta_description'    => sanitize_textarea_field( $input['meta_description'] ?? '' ),
		);
		return $data;
	}

	private static function coordinate( mixed $value, float $minimum, float $maximum ): ?float {
		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;
		return $value >= $minimum && $value <= $maximum ? $value : null;
	}

	private static function url( mixed $value ): string {
		$url = esc_url_raw( (string) $value, array( 'http', 'https' ) );
		return $url && wp_http_validate_url( $url ) ? $url : '';
	}

	private static function image_id( mixed $value ): int {
		$attachment_id = absint( $value );
		return $attachment_id && wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
	}

	private static function pdf_id( mixed $value ): int {
		$attachment_id = absint( $value );
		return $attachment_id && 'application/pdf' === get_post_mime_type( $attachment_id ) ? $attachment_id : 0;
	}
}
