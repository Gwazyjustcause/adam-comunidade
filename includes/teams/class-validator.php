<?php
/**
 * Team input validation and normalization.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

/**
 * Converts untrusted editor input into repository-ready data.
 */
final class Validator {
	/**
	 * Repository instance.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Teams repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validates and sanitizes a team submission.
	 *
	 * @param array<string,mixed> $input Untrusted form values.
	 * @param int                 $team_id Existing team ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function validate( array $input, int $team_id = 0 ): array|\WP_Error {
		$errors = new \WP_Error();
		$name   = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$slug   = sanitize_title( (string) ( $input['slug'] ?? $name ) );

		if ( '' === $name ) {
			$errors->add( 'name_required', __( 'Team name is required.', 'adam-comunidade' ) );
		} elseif ( $this->repository->exists( 'name', $name, $team_id ) ) {
			$errors->add( 'name_exists', __( 'A team with this name already exists.', 'adam-comunidade' ) );
		}

		if ( '' === $slug ) {
			$errors->add( 'slug_required', __( 'A valid team slug is required.', 'adam-comunidade' ) );
		} elseif ( $this->repository->exists( 'slug', $slug, $team_id ) ) {
			$errors->add( 'slug_exists', __( 'A team with this slug already exists.', 'adam-comunidade' ) );
		}

		$status = sanitize_key( (string) ( $input['status'] ?? 'draft' ) );
		if ( ! isset( Options::statuses()[ $status ] ) ) {
			$status = 'draft';
		}

		$recruitment = sanitize_key( (string) ( $input['recruitment_status'] ?? 'closed' ) );
		if ( ! isset( Options::recruitment_statuses()[ $recruitment ] ) ) {
			$recruitment = 'closed';
		}

		$playing_styles = $this->sanitize_choices( $input['playing_styles'] ?? array(), Options::playing_styles() );
		$equipment_tags = $this->sanitize_choices( $input['equipment_tags'] ?? array(), Options::equipment_tags() );
		$gallery        = array_values( array_filter( array_map( 'absint', (array) ( $input['gallery'] ?? array() ) ) ) );
		$email          = sanitize_email( (string) ( $input['email'] ?? '' ) );

		if ( ! empty( $input['email'] ) && ! is_email( $email ) ) {
			$errors->add( 'invalid_email', __( 'Enter a valid email address.', 'adam-comunidade' ) );
		}

		$url_fields = array( 'maps_url', 'website', 'facebook', 'instagram', 'discord', 'youtube', 'tiktok' );
		$urls       = array();

		foreach ( $url_fields as $field ) {
			$raw            = trim( (string) ( $input[ $field ] ?? '' ) );
			$urls[ $field ] = esc_url_raw( $raw, array( 'http', 'https' ) );

			if ( '' !== $raw && '' === $urls[ $field ] ) {
				$errors->add(
					'invalid_' . $field,
					sprintf(
						/* translators: %s: field label. */
						__( 'Enter a valid URL for %s.', 'adam-comunidade' ),
						ucwords( str_replace( '_', ' ', $field ) )
					)
				);
			}
		}

		$latitude  = $this->coordinate( $input['latitude'] ?? '', -90, 90, __( 'latitude', 'adam-comunidade' ), $errors );
		$longitude = $this->coordinate( $input['longitude'] ?? '', -180, 180, __( 'longitude', 'adam-comunidade' ), $errors );
		$founded   = absint( $input['founded'] ?? 0 );

		if ( $founded && ( $founded < 1800 || $founded > (int) gmdate( 'Y' ) ) ) {
			$errors->add( 'invalid_founded', __( 'Enter a valid founding year.', 'adam-comunidade' ) );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return array_merge(
			array(
				'name'             => $name,
				'short_name'       => sanitize_text_field( (string) ( $input['short_name'] ?? '' ) ),
				'slug'             => $slug,
				'status'           => $status,
				'logo_id'          => absint( $input['logo_id'] ?? 0 ),
				'cover_id'         => absint( $input['cover_id'] ?? 0 ),
				'gallery'          => wp_json_encode( $gallery ),
				'team_colour'      => sanitize_hex_color( $input['team_colour'] ?? '' ) ?: '',
				'short_description' => sanitize_textarea_field( (string) ( $input['short_description'] ?? '' ) ),
				'full_description' => wp_kses_post( (string) ( $input['full_description'] ?? '' ) ),
				'district'         => sanitize_text_field( (string) ( $input['district'] ?? '' ) ),
				'municipality'     => sanitize_text_field( (string) ( $input['municipality'] ?? '' ) ),
				'address'          => sanitize_text_field( (string) ( $input['address'] ?? '' ) ),
				'latitude'         => $latitude,
				'longitude'        => $longitude,
				'email'            => $email,
				'phone'            => sanitize_text_field( (string) ( $input['phone'] ?? '' ) ),
				'founded'          => $founded ?: null,
				'members'          => absint( $input['members'] ?? 0 ),
				'recruitment_status' => $recruitment,
				'playing_styles'   => wp_json_encode( $playing_styles ),
				'equipment_tags'   => wp_json_encode( $equipment_tags ),
				'meta_title'       => sanitize_text_field( (string) ( $input['meta_title'] ?? '' ) ),
				'meta_description' => sanitize_textarea_field( (string) ( $input['meta_description'] ?? '' ) ),
			),
			$urls
		);
	}

	/**
	 * Sanitizes a multiple-choice value.
	 *
	 * @param mixed                $values  Submitted values.
	 * @param array<string,string> $allowed Allowed options.
	 * @return string[]
	 */
	private function sanitize_choices( mixed $values, array $allowed ): array {
		$values = array_map( 'sanitize_key', (array) $values );

		return array_values( array_intersect( $values, array_keys( $allowed ) ) );
	}

	/**
	 * Validates one GPS coordinate.
	 *
	 * @param mixed     $value  Submitted coordinate.
	 * @param int       $min    Minimum coordinate.
	 * @param int       $max    Maximum coordinate.
	 * @param string    $label  Translated field label.
	 * @param \WP_Error $errors Error collection.
	 * @return float|null
	 */
	private function coordinate( mixed $value, int $min, int $max, string $label, \WP_Error $errors ): ?float {
		if ( '' === trim( (string) $value ) ) {
			return null;
		}

		if ( ! is_numeric( $value ) || (float) $value < $min || (float) $value > $max ) {
			$errors->add(
				'invalid_' . sanitize_key( $label ),
				sprintf(
					/* translators: %s: coordinate label. */
					__( 'Enter a valid %s.', 'adam-comunidade' ),
					$label
				)
			);

			return null;
		}

		return round( (float) $value, 7 );
	}
}
