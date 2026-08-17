<?php
/**
 * Field submission validation.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes and validates field editor data.
 */
final class Validator {
	/**
	 * Fields repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Fields repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validates a field.
	 *
	 * @param array<string,mixed> $input    Untrusted input.
	 * @param int                 $field_id Existing field ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function validate( array $input, int $field_id = 0 ): array|\WP_Error {
		$errors = new \WP_Error();
		$name   = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$slug   = sanitize_title( (string) ( $input['slug'] ?? $name ) );

		if ( ! $name ) {
			$errors->add( 'name_required', __( 'O nome do campo é obrigatório.', 'adam-comunidade' ) );
		} elseif ( $this->repository->exists( 'name', $name, $field_id ) ) {
			$errors->add( 'name_exists', __( 'Já existe um campo com este nome.', 'adam-comunidade' ) );
		}

		if ( ! $slug ) {
			$errors->add( 'slug_required', __( 'É obrigatório indicar um slug válido para o campo.', 'adam-comunidade' ) );
		} elseif ( $this->repository->exists( 'slug', $slug, $field_id ) ) {
			$errors->add( 'slug_exists', __( 'Já existe um campo com este slug.', 'adam-comunidade' ) );
		}

		$status = sanitize_key( (string) ( $input['status'] ?? 'draft' ) );
		if ( ! isset( Options::statuses()[ $status ] ) ) {
			$status = 'draft';
		}

		$email = sanitize_email( (string) ( $input['email'] ?? '' ) );
		if ( ! empty( $input['email'] ) && ! is_email( $email ) ) {
			$errors->add( 'invalid_email', __( 'Introduza um endereço de e-mail válido.', 'adam-comunidade' ) );
		}

		$urls = array();
		$url_labels = array(
			'maps_url' => __( 'Google Maps', 'adam-comunidade' ),
			'website'  => __( 'página Web', 'adam-comunidade' ),
			'facebook' => 'Facebook',
			'instagram'=> 'Instagram',
		);
		foreach ( array( 'maps_url', 'website', 'facebook', 'instagram' ) as $key ) {
			$raw          = trim( (string) ( $input[ $key ] ?? '' ) );
			$urls[ $key ] = esc_url_raw( $raw, array( 'http', 'https' ) );
			if ( 'maps_url' === $key ) {
				$maps_result = self::sanitize_maps_url( $raw );
				if ( is_wp_error( $maps_result ) ) {
					$current = $field_id ? $this->repository->find( $field_id ) : null;
					// Do not break an untouched legacy value. It is filtered from
					// public output and can be replaced or removed by an editor.
					if ( ! $current || (string) $current->maps_url !== $raw ) {
						$errors->add( 'invalid_maps_url', $maps_result->get_error_message() );
					}
				} else {
					$urls[ $key ] = $maps_result;
				}
				continue;
			}

			if ( $raw && ! $urls[ $key ] ) {
				$errors->add(
					'invalid_' . $key,
					sprintf(
						/* translators: %s: URL field label. */
						__( 'Introduza um URL válido para %s.', 'adam-comunidade' ),
						$url_labels[ $key ]
					)
				);
			}
		}

		$latitude  = $this->coordinate( $input['latitude'] ?? '', -90, 90, 'latitude', $errors );
		$longitude = $this->coordinate( $input['longitude'] ?? '', -180, 180, 'longitude', $errors );
		if ( $urls['maps_url'] && '' === trim( (string) ( $input['latitude'] ?? '' ) ) && '' === trim( (string) ( $input['longitude'] ?? '' ) ) ) {
			$coordinates = self::extract_coordinates( $urls['maps_url'] );
			if ( is_wp_error( $coordinates ) ) {
				$errors->add( 'invalid_maps_coordinates', $coordinates->get_error_message() );
			} elseif ( is_array( $coordinates ) ) {
				$latitude  = $coordinates['latitude'];
				$longitude = $coordinates['longitude'];
			}
		}
		$minimum   = absint( $input['min_players'] ?? 0 );
		$maximum   = absint( $input['max_players'] ?? 0 );
		$recommended = absint( $input['recommended_players'] ?? 0 );

		if ( $maximum && $minimum > $maximum ) {
			$errors->add(
				'invalid_capacity',
				__( 'O número mínimo de jogadores não pode exceder o máximo.', 'adam-comunidade' )
			);
		}

		if ( $maximum && $recommended > $maximum ) {
			$errors->add(
				'invalid_recommended',
				__( 'O número recomendado de jogadores não pode exceder o máximo.', 'adam-comunidade' )
			);
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$styles = array_intersect(
			array_map( 'sanitize_key', (array) ( $input['playing_styles'] ?? array() ) ),
			array_keys( Options::playing_styles() )
		);

		$cover_id = absint( $input['cover_id'] ?? 0 );
		if ( $cover_id && ! wp_attachment_is_image( $cover_id ) ) {
			$cover_id = 0;
		}
		$verification = in_array( sanitize_key( $input['verification'] ?? '' ), array( '', 'verified_field' ), true )
			? sanitize_key( $input['verification'] ?? '' )
			: '';
		$authorization_document_id = absint( $input['authorization_document_id'] ?? 0 );
		if ( $authorization_document_id ) {
			$document_mime = (string) get_post_mime_type( $authorization_document_id );
			if (
				'attachment' !== get_post_type( $authorization_document_id )
				|| ! in_array( $document_mime, array( 'application/pdf', 'image/jpeg', 'image/png' ), true )
			) {
				$authorization_document_id = 0;
			}
		}

		if ( 'published' === $status && 'verified_field' !== $verification ) {
			$errors->add(
				'legal_authorization_required',
				__( 'O campo só pode ser publicado depois de a autorização legal ser verificada.', 'adam-comunidade' )
			);
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return array_merge(
			array(
				'name'                => $name,
				'slug'                => $slug,
				'status'              => $status,
				'featured'            => empty( $input['featured'] ) ? 0 : 1,
				'is_associated'       => empty( $input['is_associated'] ) ? 0 : 1,
				'verification'        => $verification,
				'authorization_document_id' => $authorization_document_id,
				'availability'        => in_array( sanitize_key( $input['availability'] ?? 'open' ), array( 'open', 'seasonal', 'temporary_closure', 'private_events', 'maintenance' ), true ) ? sanitize_key( $input['availability'] ?? 'open' ) : 'open',
				'cover_id'            => $cover_id,
				'short_description'   => sanitize_textarea_field( (string) ( $input['short_description'] ?? '' ) ),
				'full_description'    => wp_kses_post( (string) ( $input['full_description'] ?? '' ) ),
				'district'            => sanitize_text_field( (string) ( $input['district'] ?? '' ) ),
				'municipality'        => sanitize_text_field( (string) ( $input['municipality'] ?? '' ) ),
				'address'             => sanitize_text_field( (string) ( $input['address'] ?? '' ) ),
				'latitude'            => $latitude,
				'longitude'           => $longitude,
				'playing_styles'      => wp_json_encode( array_values( $styles ) ),
				'rules'               => wp_kses_post( (string) ( $input['rules'] ?? '' ) ),
				'opening_hours'       => sanitize_textarea_field( (string) ( $input['opening_hours'] ?? '' ) ),
				'max_players'         => $maximum,
				'min_players'         => $minimum,
				'recommended_players' => $recommended,
				'email'               => $email,
				'phone'               => sanitize_text_field( (string) ( $input['phone'] ?? '' ) ),
				'meta_title'          => sanitize_text_field( (string) ( $input['meta_title'] ?? '' ) ),
				'meta_description'    => sanitize_textarea_field( (string) ( $input['meta_description'] ?? '' ) ),
			),
			$urls
		);
	}

	/**
	 * Sanitizes and strictly validates a Google Maps URL.
	 *
	 * @param mixed $value Untrusted URL.
	 * @return string|\WP_Error
	 */
	public static function sanitize_maps_url( mixed $value ): string|\WP_Error {
		$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = strtolower( (string) ( $parts['path'] ?? '' ) );
		$port  = absint( $parts['port'] ?? 0 );
		$valid = in_array( (string) ( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
			&& $host
			&& empty( $parts['user'] )
			&& empty( $parts['pass'] )
			&& ( 0 === $port || ( 'https' === $parts['scheme'] && 443 === $port ) || ( 'http' === $parts['scheme'] && 80 === $port ) );

		$google_host = (bool) preg_match( '/(?:^|\.)google\.[a-z]{2,}(?:\.[a-z]{2})?$/', $host );
		$valid = $valid && (
			( $google_host && str_starts_with( $path, '/maps' ) )
			|| 'maps.google.com' === $host
			|| 'maps.app.goo.gl' === $host
			|| ( 'goo.gl' === $host && str_starts_with( $path, '/maps' ) )
		);

		return $valid
			? $url
			: new \WP_Error( 'invalid_maps_url', __( 'Introduza um link válido e reconhecido do Google Maps.', 'adam-comunidade' ) );
	}

	/**
	 * Extracts explicit coordinates from common Google Maps URL formats.
	 *
	 * @param string $url Validated Google Maps URL.
	 * @return array{latitude:float,longitude:float}|null|\WP_Error
	 */
	public static function extract_coordinates( string $url ): array|null|\WP_Error {
		$decoded = rawurldecode( $url );
		$patterns = array(
			'/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
			'/[?&](?:q|query|destination|ll)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/',
			'/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $decoded, $matches ) ) {
				$latitude  = (float) $matches[1];
				$longitude = (float) $matches[2];
				if ( $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
					return new \WP_Error( 'invalid_maps_coordinates', __( 'O link do Google Maps contém coordenadas fora dos limites válidos.', 'adam-comunidade' ) );
				}

				return array( 'latitude' => round( $latitude, 7 ), 'longitude' => round( $longitude, 7 ) );
			}
		}

		return null;
	}

	/**
	 * Validates one coordinate.
	 *
	 * @param mixed     $value  Submitted value.
	 * @param int       $min    Minimum.
	 * @param int       $max    Maximum.
	 * @param string    $label  Field label.
	 * @param \WP_Error $errors Error collection.
	 * @return float|null
	 */
	private function coordinate( mixed $value, int $min, int $max, string $label, \WP_Error $errors ): ?float {
		if ( '' === trim( (string) $value ) ) {
			return null;
		}

		if ( ! is_numeric( $value ) || (float) $value < $min || (float) $value > $max ) {
			$errors->add(
				'invalid_' . $label,
				sprintf(
					/* translators: %s: coordinate name. */
					__( 'Introduza uma %s válida.', 'adam-comunidade' ),
					$label
				)
			);

			return null;
		}

		return round( (float) $value, 7 );
	}
}
