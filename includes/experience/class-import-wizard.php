<?php
/**
 * Guided bulk import with preview, mapping and rollback.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Directory\Repository;
use ADAM\Comunidade\Directory\Validator;
use ADAM\Comunidade\Helpers;

/**
 * Imports directory records in bounded, reviewable batches.
 */
final class Import_Wizard {
	private const FIELDS = array( 'entity_type', 'name', 'slug', 'status', 'short_description', 'full_description', 'website', 'email', 'phone', 'address', 'district', 'category', 'country', 'featured' );

	public function register(): void {
		Admin_Router::register_page( 'import-wizard', array( 'title' => __( 'Import Wizard', 'adam-comunidade' ), 'controller' => $this, 'method' => 'page' ) );
		add_action( 'admin_post_adam_import_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_adam_import_commit', array( $this, 'commit' ) );
		add_action( 'admin_post_adam_import_rollback', array( $this, 'rollback' ) );
	}

	public function page(): void {
		$key   = $this->key( 'preview' );
		$batch = get_transient( $key );
		$rollback = get_transient( $this->key( 'rollback' ) );
		$fields   = self::FIELDS;
		require Helpers::path( 'admin/views/experience/import-wizard.php' );
	}

	public function preview(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_import_preview' );
		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Choose a valid import file.', 'adam-comunidade' ) );
		}
		$name = sanitize_file_name( $_FILES['import_file']['name'] ?? '' );
		$rows = match ( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			'csv'  => $this->csv( $_FILES['import_file']['tmp_name'] ),
			'json' => $this->json( $_FILES['import_file']['tmp_name'] ),
			'xlsx' => $this->xlsx( $_FILES['import_file']['tmp_name'] ),
			default => array(),
		};
		if ( ! $rows ) {
			wp_die( esc_html__( 'The file contains no readable rows.', 'adam-comunidade' ) );
		}
		$headers = array_values( array_unique( array_merge( ...array_map( 'array_keys', $rows ) ) ) );
		set_transient( $this->key( 'preview' ), array( 'headers' => $headers, 'rows' => array_slice( $rows, 0, 5000 ) ), 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( Admin_Router::page_url( 'import-wizard' ) );
		exit;
	}

	public function commit(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_import_commit' );
		$batch   = get_transient( $this->key( 'preview' ) );
		$mapping = array_map( 'sanitize_key', (array) wp_unslash( $_POST['mapping'] ?? array() ) );
		if ( ! $batch || ! in_array( 'name', $mapping, true ) ) {
			wp_die( esc_html__( 'Map at least the name field.', 'adam-comunidade' ) );
		}
		$repo = new Repository();
		$undo = array( 'created' => array(), 'updated' => array() );
		foreach ( $batch['rows'] as $source ) {
			$row = array();
			foreach ( $mapping as $column => $field ) {
				if ( $field && in_array( $field, self::FIELDS, true ) ) {
					$row[ $field ] = $source[ $column ] ?? '';
				}
			}
			$type = sanitize_key( $row['entity_type'] ?? 'partner' );
			if ( ! in_array( $type, array( 'partner', 'institution', 'brand' ), true ) ) {
				$type = 'partner';
			}
			$data = Validator::sanitize( $type, $row );
			if ( ! $data['name'] ) {
				continue;
			}
			$existing = $repo->find_by_slug( $type, $data['slug'], false );
			if ( $existing ) {
				$undo['updated'][] = (array) $existing;
				$repo->update( (int) $existing->id, $data );
			} else {
				$id = $repo->create( $data );
				if ( $id ) {
					$undo['created'][] = array( 'id' => $id, 'type' => $type );
				}
			}
		}
		set_transient( $this->key( 'rollback' ), $undo, HOUR_IN_SECONDS );
		delete_transient( $this->key( 'preview' ) );
		do_action( 'adam_comunidade_import_batch_completed', $undo );
		wp_safe_redirect( Admin_Router::page_url( 'import-wizard', array( 'imported' => 1 ) ) );
		exit;
	}

	public function rollback(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_import_rollback' );
		$undo = get_transient( $this->key( 'rollback' ) );
		if ( ! $undo ) {
			wp_die( esc_html__( 'The rollback window has expired.', 'adam-comunidade' ) );
		}
		$repo = new Repository();
		foreach ( $undo['created'] as $created ) {
			$repo->delete( (int) $created['id'] );
		}
		foreach ( $undo['updated'] as $previous ) {
			$type = sanitize_key( $previous['entity_type'] ?? '' );
			$id   = absint( $previous['id'] ?? 0 );
			if ( $id && in_array( $type, array( 'partner', 'institution', 'brand' ), true ) ) {
				$repo->update( $id, Validator::sanitize( $type, $previous ) );
			}
		}
		delete_transient( $this->key( 'rollback' ) );
		do_action( 'adam_comunidade_import_batch_rolled_back' );
		wp_safe_redirect( Admin_Router::page_url( 'import-wizard', array( 'rolled_back' => 1 ) ) );
		exit;
	}

	private function csv( string $path ): array {
		$handle = fopen( $path, 'r' );
		$headers = $handle ? fgetcsv( $handle ) : false;
		$rows = array();
		while ( $handle && $headers && false !== ( $values = fgetcsv( $handle ) ) ) {
			$rows[] = array_combine( array_map( 'sanitize_key', $headers ), array_pad( $values, count( $headers ), '' ) );
		}
		if ( $handle ) {
			fclose( $handle );
		}
		return $rows;
	}

	private function json( string $path ): array {
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( isset( $data['directory'] ) ) {
			$rows = array();
			foreach ( $data['directory'] as $type => $items ) {
				foreach ( $items as $item ) {
					$rows[] = array_merge( array( 'entity_type' => $type ), (array) $item );
				}
			}
			return $rows;
		}
		return array_is_list( (array) $data ) ? $data : array();
	}

	private function xlsx( string $path ): array {
		if ( ! class_exists( '\ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel import requires the PHP Zip extension.', 'adam-comunidade' ) );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return array();
		}
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		$sheet_xml  = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();
		$shared = array();
		if ( $shared_xml ) {
			$xml = simplexml_load_string( $shared_xml );
			foreach ( $xml->si as $item ) {
				$shared[] = trim( implode( '', $item->xpath( './/t' ) ?: array() ) );
			}
		}
		$sheet = $sheet_xml ? simplexml_load_string( $sheet_xml ) : false;
		if ( ! $sheet ) {
			return array();
		}
		$matrix = array();
		foreach ( $sheet->sheetData->row as $row ) {
			$values = array();
			foreach ( $row->c as $cell ) {
				$value = (string) $cell->v;
				$values[] = 's' === (string) $cell['t'] ? ( $shared[ (int) $value ] ?? '' ) : $value;
			}
			$matrix[] = $values;
		}
		$headers = array_map( 'sanitize_key', array_shift( $matrix ) ?: array() );
		return array_map( static fn( array $values ): array => array_combine( $headers, array_pad( $values, count( $headers ), '' ) ), $matrix );
	}

	private function key( string $kind ): string {
		return 'adam_import_' . $kind . '_' . get_current_user_id();
	}

}
