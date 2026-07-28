<?php
/**
 * Secure upload processing shared by public and manager forms.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Uploads;

defined( 'ABSPATH' ) || exit;

/**
 * Validates uploads, stores attachments and rolls back partial batches.
 */
final class Handler {
	/**
	 * Converts an accept string into safe extension keys.
	 *
	 * @return string[]
	 */
	public static function extensions( string $accept, array $fallback = array( 'pdf', 'jpg', 'jpeg', 'png' ) ): array {
		$extensions = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( string $extension ): string => ltrim( strtolower( trim( $extension ) ), '.' ),
						explode( ',', $accept )
					),
					static fn( string $extension ): bool => (bool) preg_match( '/^[a-z0-9]+$/', $extension )
				)
			)
		);

		return $extensions ?: $fallback;
	}

	/**
	 * Validates a single or multiple file input before storing anything.
	 */
	public function validate( string $name, array $extensions, int $max_files, int $max_size_mb, bool $required = false, string $label = '' ): true|\WP_Error {
		$files = $this->files( $name );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		if ( ! $files ) {
			return $required
				? new \WP_Error( 'upload_required', sprintf( __( 'É obrigatório anexar: %s.', 'adam-comunidade' ), $label ) )
				: true;
		}
		if ( count( $files ) > max( 1, $max_files ) ) {
			return new \WP_Error( 'too_many_uploads', sprintf( __( 'Pode selecionar no máximo %d ficheiros.', 'adam-comunidade' ), max( 1, $max_files ) ) );
		}

		$extensions = array_map( 'strtolower', $extensions );
		$max_bytes  = max( 1, $max_size_mb ) * MB_IN_BYTES;
		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				return new \WP_Error( 'upload_failed', __( 'Não foi possível receber um dos ficheiros. Selecione-o novamente.', 'adam-comunidade' ) );
			}
			$extension = strtolower( (string) pathinfo( sanitize_file_name( wp_unslash( $file['name'] ) ), PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $extensions, true ) ) {
				return new \WP_Error( 'invalid_upload_type', __( 'O tipo de ficheiro enviado não é permitido.', 'adam-comunidade' ) );
			}
			if ( $file['size'] > $max_bytes ) {
				return new \WP_Error( 'upload_too_large', sprintf( __( 'Cada ficheiro pode ter no máximo %d MB.', 'adam-comunidade' ), max( 1, $max_size_mb ) ) );
			}
		}

		return true;
	}

	/**
	 * Stores one upload in the WordPress Media Library.
	 */
	public function upload_one( string $name, array $extensions, int $max_size_mb, bool $required = false, string $label = '' ): int|\WP_Error {
		$validation = $this->validate( $name, $extensions, 1, $max_size_mb, $required, $label );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		if ( ! $this->files( $name ) ) {
			return 0;
		}

		$this->load_wordpress_media();
		$attachment_id = media_handle_upload( $name, 0 );
		return is_wp_error( $attachment_id ) ? $attachment_id : absint( $attachment_id );
	}

	/**
	 * Stores a bounded upload batch and removes partial results on failure.
	 *
	 * @return int[]|\WP_Error
	 */
	public function upload_many( string $name, array $extensions, int $max_files, int $max_size_mb, bool $required = false, string $label = '' ): array|\WP_Error {
		$validation = $this->validate( $name, $extensions, $max_files, $max_size_mb, $required, $label );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$original = $_FILES[ $name ] ?? null;
		$files    = $this->files( $name );
		if ( ! is_array( $original ) || is_wp_error( $files ) || ! $files ) {
			return array();
		}

		$ids = array();
		foreach ( $files as $file ) {
			$_FILES[ $name ] = $file;
			$id = $this->upload_one( $name, $extensions, $max_size_mb );
			if ( is_wp_error( $id ) ) {
				$_FILES[ $name ] = $original;
				$this->delete( $ids );
				return $id;
			}
			if ( $id ) {
				$ids[] = $id;
			}
		}
		$_FILES[ $name ] = $original;
		return $ids;
	}

	/**
	 * Removes attachments created by an unsuccessful operation.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 */
	public function delete( array $attachment_ids ): void {
		foreach ( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	/**
	 * Normalizes PHP's single and multiple upload array shapes.
	 *
	 * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>|\WP_Error
	 */
	private function files( string $name ): array|\WP_Error {
		$input = $_FILES[ $name ] ?? null;
		if ( ! is_array( $input ) || empty( $input['name'] ) ) {
			return array();
		}
		$names = is_array( $input['name'] ) ? $input['name'] : array( $input['name'] );
		foreach ( array( 'type', 'tmp_name', 'error', 'size' ) as $property ) {
			if ( ! array_key_exists( $property, $input ) ) {
				return new \WP_Error( 'invalid_upload', __( 'Os dados do envio de ficheiros não são válidos.', 'adam-comunidade' ) );
			}
			$input[ $property ] = is_array( $input[ $property ] ) ? $input[ $property ] : array( $input[ $property ] );
		}

		$files = array();
		foreach ( $names as $index => $filename ) {
			$error = (int) ( $input['error'][ $index ] ?? UPLOAD_ERR_NO_FILE );
			if ( '' === (string) $filename || UPLOAD_ERR_NO_FILE === $error ) {
				continue;
			}
			$files[] = array(
				'name'     => (string) $filename,
				'type'     => (string) ( $input['type'][ $index ] ?? '' ),
				'tmp_name' => (string) ( $input['tmp_name'][ $index ] ?? '' ),
				'error'    => $error,
				'size'     => absint( $input['size'][ $index ] ?? 0 ),
			);
		}
		return $files;
	}

	private function load_wordpress_media(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}
