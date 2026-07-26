<?php
/**
 * Guided bulk import with preview, mapping and rollback.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository;
use ADAM\Comunidade\Directory\Validator;

/**
 * Imports directory records in bounded, reviewable batches.
 */
final class Import_Wizard {
	private const FIELDS = array( 'entity_type', 'name', 'slug', 'status', 'short_description', 'full_description', 'website', 'email', 'phone', 'address', 'district', 'category', 'country', 'featured' );

	public function register(): void {
		add_action( 'adam_comunidade_admin_menu', array( $this, 'menu' ), 42, 2 );
		add_action( 'admin_post_adam_import_preview', array( $this, 'preview' ) );
		add_action( 'admin_post_adam_import_commit', array( $this, 'commit' ) );
		add_action( 'admin_post_adam_import_rollback', array( $this, 'rollback' ) );
	}

	public function menu( string $parent, string $capability ): void {
		add_submenu_page( $parent, __( 'Import Wizard', 'adam-comunidade' ), __( 'Import Wizard', 'adam-comunidade' ), $capability, 'adam-comunidade-import-wizard', array( $this, 'page' ) );
	}

	public function page(): void {
		$this->authorize();
		$key   = $this->key( 'preview' );
		$batch = get_transient( $key );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Import Wizard', 'adam-comunidade' ); ?></h1>
		<?php if ( ! $batch ) : ?>
			<div class="adam-card"><h2><?php esc_html_e( '1. Upload', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'CSV, Excel (.xlsx), and JSON are supported. Nothing is changed until you confirm the preview.', 'adam-comunidade' ); ?></p><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="adam_import_preview"><?php wp_nonce_field( 'adam_import_preview' ); ?><input type="file" name="import_file" accept=".csv,.xlsx,.json" required> <?php submit_button( __( 'Preview import', 'adam-comunidade' ), 'primary', 'submit', false ); ?></form></div>
		<?php else : ?>
			<div class="adam-card"><h2><?php esc_html_e( '2. Map and review', 'adam-comunidade' ); ?></h2><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="adam_import_commit"><?php wp_nonce_field( 'adam_import_commit' ); ?>
			<table class="widefat striped"><thead><tr><?php foreach ( $batch['headers'] as $header ) : ?><th><?php echo esc_html( $header ); ?><select name="mapping[<?php echo esc_attr( $header ); ?>]"><option value=""><?php esc_html_e( 'Ignore', 'adam-comunidade' ); ?></option><?php foreach ( self::FIELDS as $field ) : ?><option value="<?php echo esc_attr( $field ); ?>" <?php selected( sanitize_key( $header ), $field ); ?>><?php echo esc_html( $field ); ?></option><?php endforeach; ?></select></th><?php endforeach; ?></tr></thead><tbody><?php foreach ( array_slice( $batch['rows'], 0, 10 ) as $row ) : ?><tr><?php foreach ( $batch['headers'] as $header ) : ?><td><?php echo esc_html( $row[ $header ] ?? '' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
			<p><?php echo esc_html( sprintf( _n( '%d row ready for validation.', '%d rows ready for validation.', count( $batch['rows'] ), 'adam-comunidade' ), count( $batch['rows'] ) ) ); ?></p><?php submit_button( __( 'Validate and import', 'adam-comunidade' ) ); ?></form></div>
		<?php endif; ?>
		<?php $rollback = get_transient( $this->key( 'rollback' ) ); if ( $rollback ) : ?><div class="adam-card"><h2><?php esc_html_e( 'Rollback', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'The most recent batch can be reverted while this recovery window remains open.', 'adam-comunidade' ); ?></p><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="adam_import_rollback"><?php wp_nonce_field( 'adam_import_rollback' ); ?><?php submit_button( __( 'Rollback last import', 'adam-comunidade' ), 'secondary' ); ?></form></div><?php endif; ?>
		</div>
		<?php
	}

	public function preview(): void {
		$this->authorize();
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
		wp_safe_redirect( admin_url( 'admin.php?page=adam-comunidade-import-wizard' ) );
		exit;
	}

	public function commit(): void {
		$this->authorize();
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
		wp_safe_redirect( admin_url( 'admin.php?page=adam-comunidade-import-wizard&imported=1' ) );
		exit;
	}

	public function rollback(): void {
		$this->authorize();
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
		wp_safe_redirect( admin_url( 'admin.php?page=adam-comunidade-import-wizard&rolled_back=1' ) );
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

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot import community data.', 'adam-comunidade' ) );
		}
	}
}
