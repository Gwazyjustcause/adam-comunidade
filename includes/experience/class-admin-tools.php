<?php
/**
 * Analytics and data portability administration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\Validator as Directory_Validator;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Validator as Team_Validator;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Validator as Field_Validator;

/**
 * Provides insight reports and guarded CSV/JSON import/export workflows.
 */
final class Admin_Tools {
	public function __construct( private Directory_Repository $directory ) {}

	public function register(): void {
		add_action( 'adam_comunidade_admin_menu', array( $this, 'menu' ), 40, 2 );
		add_action( 'admin_post_adam_analytics_export', array( $this, 'analytics_export' ) );
		add_action( 'admin_post_adam_community_export', array( $this, 'community_export' ) );
		add_action( 'admin_post_adam_community_import', array( $this, 'community_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets( string $hook ): void {
		if ( str_contains( $hook, 'adam-comunidade-analytics' ) || str_contains( $hook, 'adam-comunidade-tools' ) ) {
			wp_enqueue_style( 'adam-directory-admin', Helpers::url( 'assets/css/directory-admin.css' ), array( 'adam-comunidade-admin' ), ADAM_COMUNIDADE_VERSION );
		}
	}

	public function menu( string $parent, string $capability ): void {
		add_submenu_page( $parent, __( 'Analytics', 'adam-comunidade' ), __( 'Analytics', 'adam-comunidade' ), $capability, 'adam-comunidade-analytics', array( $this, 'analytics_page' ) );
		add_submenu_page( $parent, __( 'Import & Export', 'adam-comunidade' ), __( 'Import & Export', 'adam-comunidade' ), $capability, 'adam-comunidade-tools', array( $this, 'tools_page' ) );
	}

	public function analytics_page(): void {
		$this->authorize();
		$views    = Analytics::top( 'view', 10 );
		$searches = Analytics::top( 'search', 10 );
		$clicks   = Analytics::top( 'click', 10 );
		$municipalities = Analytics::top( 'municipality', 10 );
		$widgets = Analytics::top( 'widget', 10 );
		require Helpers::path( 'admin/views/experience/analytics.php' );
	}

	public function tools_page(): void {
		$this->authorize();
		require Helpers::path( 'admin/views/experience/tools.php' );
	}

	public function analytics_export(): void {
		$this->authorize();
		check_admin_referer( 'adam_analytics_export' );
		$this->csv_headers( 'adam-community-analytics.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'event', 'object_type', 'object_id', 'dimension', 'total', 'updated_at' ) );
		foreach ( array( 'view', 'search', 'municipality', 'click', 'widget' ) as $event ) {
			foreach ( Analytics::top( $event, 1000 ) as $row ) {
				fputcsv( $output, array( $row->event_type, $row->object_type, $row->object_id, $row->dimension, $row->total, $row->updated_at ) );
			}
		}
		fclose( $output );
		exit;
	}

	public function community_export(): void {
		$this->authorize();
		check_admin_referer( 'adam_community_export' );
		$format = sanitize_key( $_GET['format'] ?? 'json' );
		if ( 'csv' === $format ) {
			$type = sanitize_key( $_GET['entity_type'] ?? 'partner' );
			if ( ! in_array( $type, array( 'partner', 'institution', 'brand' ), true ) ) {
				$type = 'partner';
			}
			$this->csv_headers( 'adam-' . $type . '-export.csv' );
			$output = fopen( 'php://output', 'w' );
			$rows = $this->all_pages( fn( int $page ): array => $this->directory->query( $type, array( 'per_page' => 100, 'page' => $page ) ) );
			$columns = array( 'id', 'entity_type', 'name', 'slug', 'status', 'short_description', 'website', 'email', 'phone', 'district', 'category', 'featured', 'country', 'logo_id', 'cover_id', 'logo_url', 'cover_url' );
			fputcsv( $output, $columns );
			foreach ( $rows as $row ) {
				fputcsv(
					$output,
					array_map(
						static function ( string $column ) use ( $row ): mixed {
							if ( 'logo_url' === $column ) {
								return $row->logo_id ? wp_get_attachment_url( $row->logo_id ) : '';
							}
							if ( 'cover_url' === $column ) {
								return $row->cover_id ? wp_get_attachment_url( $row->cover_id ) : '';
							}
							return $row->{$column} ?? '';
						},
						$columns
					)
				);
			}
			fclose( $output );
			exit;
		}
		$backup = array(
			'version' => ADAM_COMUNIDADE_VERSION,
			'created_at' => gmdate( DATE_ATOM ),
			'settings' => get_option( 'adam_comunidade_settings', array() ),
			'directory' => array(),
			'teams' => array(),
			'fields' => array(),
			'news' => array(),
			'relationships' => $this->table_rows( \ADAM\Comunidade\Directory\Schema::relationships_table() ),
			'field_amenities' => $this->table_rows( \ADAM\Comunidade\Fields\Schema::field_amenities_table() ),
			'field_galleries' => $this->table_rows( \ADAM\Comunidade\Fields\Schema::galleries_table() ),
			'directory_galleries' => $this->table_rows( \ADAM\Comunidade\Directory\Schema::galleries_table() ),
			'owners' => $this->table_rows( Schema::owners_table() ),
			'calendar' => $this->table_rows( Schema::calendar_table() ),
			'rich_media' => $this->table_rows( Schema::media_table() ),
		);
		$teams_repository = new Team_Repository();
		$fields_repository = new Field_Repository();
		$backup['teams'] = array_map( static fn( object $row ): array => (array) $row, $this->all_pages( static fn( int $page ): array => $teams_repository->query( array( 'per_page' => 100, 'page' => $page ) ) ) );
		$backup['fields'] = array_map( static fn( object $row ): array => (array) $row, $this->all_pages( static fn( int $page ): array => $fields_repository->query( array( 'per_page' => 100, 'page' => $page ) ) ) );
		foreach ( array( 'partner', 'institution', 'brand' ) as $type ) {
			$backup['directory'][ $type ] = array_map( static fn( object $row ): array => (array) $row, $this->all_pages( fn( int $page ): array => $this->directory->query( $type, array( 'per_page' => 100, 'page' => $page ) ) ) );
		}
		foreach ( get_posts( array( 'post_type' => 'adam_news', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1 ) ) as $post ) {
			$backup['news'][] = array( 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status, 'excerpt' => $post->post_excerpt, 'content' => $post->post_content, 'author' => (int) $post->post_author, 'date' => $post->post_date_gmt, 'featured' => (int) get_post_meta( $post->ID, '_adam_featured', true ), 'members_only' => (int) get_post_meta( $post->ID, '_adam_members_only', true ), 'related_type' => (string) get_post_meta( $post->ID, '_adam_related_type', true ), 'related_id' => (int) get_post_meta( $post->ID, '_adam_related_id', true ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="adam-community-backup.json"' );
		echo wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function community_import(): void {
		$this->authorize();
		check_admin_referer( 'adam_community_import' );
		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Choose a valid import file.', 'adam-comunidade' ) );
		}
		$filename = sanitize_file_name( $_FILES['import_file']['name'] ?? '' );
		$count = str_ends_with( strtolower( $filename ), '.csv' ) ? $this->import_csv( $_FILES['import_file']['tmp_name'] ) : $this->import_json( $_FILES['import_file']['tmp_name'] );
		do_action( 'adam_comunidade_import_completed', $count, $filename );
		( new Cache() )->flush();
		wp_safe_redirect( add_query_arg( array( 'page' => 'adam-comunidade-tools', 'imported' => $count ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function import_csv( string $path ): int {
		$handle = fopen( $path, 'r' );
		$headers = $handle ? fgetcsv( $handle ) : false;
		$count = 0;
		while ( $handle && $headers && false !== ( $values = fgetcsv( $handle ) ) ) {
			$row  = array_combine( $headers, array_pad( $values, count( $headers ), '' ) );
			$type = sanitize_key( $row['entity_type'] ?? 'partner' );
			if ( ! in_array( $type, array( 'partner', 'institution', 'brand' ), true ) ) {
				continue;
			}
			foreach ( array( 'logo', 'cover' ) as $media_field ) {
				$url = esc_url_raw( $row[ $media_field . '_url' ] ?? '' );
				if ( $url && empty( $row[ $media_field . '_id' ] ) ) {
					$row[ $media_field . '_id' ] = $this->sideload_image( $url );
				}
			}
			$data = Directory_Validator::sanitize( $type, $row );
			$existing = ! empty( $row['id'] ) ? $this->directory->find( absint( $row['id'] ), $type ) : $this->directory->find_by_slug( $type, $data['slug'], false );
			$success = $existing ? $this->directory->update( (int) $existing->id, $data ) : $this->directory->create( $data );
			$count += $success ? 1 : 0;
		}
		if ( $handle ) {
			fclose( $handle );
		}
		return $count;
	}

	private function sideload_image( string $url ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$result = media_sideload_image( $url, 0, null, 'id' );
		return is_wp_error( $result ) ? 0 : absint( $result );
	}

	private function import_json( string $path ): int {
		$backup = json_decode( (string) file_get_contents( $path ), true );
		$count  = 0;
		foreach ( (array) ( $backup['directory'] ?? array() ) as $type => $rows ) {
			if ( ! in_array( $type, array( 'partner', 'institution', 'brand' ), true ) ) {
				continue;
			}
			foreach ( (array) $rows as $row ) {
				$data = Directory_Validator::sanitize( $type, $row );
				$existing = $this->directory->find_by_slug( $type, $data['slug'], false );
				$count += ( $existing ? $this->directory->update( (int) $existing->id, $data ) : $this->directory->create( $data ) ) ? 1 : 0;
			}
		}
		$teams_repository = new Team_Repository();
		foreach ( (array) ( $backup['teams'] ?? array() ) as $row ) {
			$existing = $teams_repository->find_by_slug( sanitize_title( $row['slug'] ?? '' ), false );
			$row['playing_styles'] = json_decode( $row['playing_styles'] ?? '[]', true ) ?: array();
			$row['equipment_tags'] = json_decode( $row['equipment_tags'] ?? '[]', true ) ?: array();
			$row['gallery'] = json_decode( $row['gallery'] ?? '[]', true ) ?: array();
			$data = ( new Team_Validator( $teams_repository ) )->validate( $row, (int) ( $existing->id ?? 0 ) );
			if ( ! is_wp_error( $data ) ) {
				$count += ( $existing ? $teams_repository->update( (int) $existing->id, $data ) : $teams_repository->create( $data ) ) ? 1 : 0;
			}
		}
		$fields_repository = new Field_Repository();
		foreach ( (array) ( $backup['fields'] ?? array() ) as $row ) {
			$existing = $fields_repository->find_by_slug( sanitize_title( $row['slug'] ?? '' ), false );
			$row['playing_styles'] = json_decode( $row['playing_styles'] ?? '[]', true ) ?: array();
			$data = ( new Field_Validator( $fields_repository ) )->validate( $row, (int) ( $existing->id ?? 0 ) );
			if ( ! is_wp_error( $data ) ) {
				$count += ( $existing ? $fields_repository->update( (int) $existing->id, $data ) : $fields_repository->create( $data ) ) ? 1 : 0;
			}
		}
		foreach ( (array) ( $backup['news'] ?? array() ) as $news ) {
			$slug = sanitize_title( $news['slug'] ?? '' );
			$existing = get_posts( array( 'post_type' => 'adam_news', 'post_status' => 'any', 'name' => $slug, 'posts_per_page' => 1, 'fields' => 'ids' ) );
			$result = wp_insert_post( array( 'ID' => absint( $existing[0] ?? 0 ), 'post_type' => 'adam_news', 'post_title' => sanitize_text_field( $news['title'] ?? '' ), 'post_name' => $slug, 'post_status' => in_array( $news['status'] ?? '', array( 'publish', 'draft', 'private' ), true ) ? $news['status'] : 'draft', 'post_excerpt' => sanitize_textarea_field( $news['excerpt'] ?? '' ), 'post_content' => wp_kses_post( $news['content'] ?? '' ), 'post_author' => absint( $news['author'] ?? get_current_user_id() ) ), true );
			if ( ! is_wp_error( $result ) ) {
				update_post_meta( $result, '_adam_featured', empty( $news['featured'] ) ? 0 : 1 );
				update_post_meta( $result, '_adam_members_only', empty( $news['members_only'] ) ? 0 : 1 );
				update_post_meta( $result, '_adam_related_type', sanitize_key( $news['related_type'] ?? '' ) );
				update_post_meta( $result, '_adam_related_id', absint( $news['related_id'] ?? 0 ) );
				++$count;
			}
		}
		if ( isset( $backup['settings'] ) && is_array( $backup['settings'] ) ) {
			update_option( 'adam_comunidade_settings', map_deep( $backup['settings'], 'sanitize_text_field' ), false );
		}
		$restore_tables = array(
			'relationships'       => \ADAM\Comunidade\Directory\Schema::relationships_table(),
			'field_amenities'     => \ADAM\Comunidade\Fields\Schema::field_amenities_table(),
			'field_galleries'     => \ADAM\Comunidade\Fields\Schema::galleries_table(),
			'directory_galleries' => \ADAM\Comunidade\Directory\Schema::galleries_table(),
			'owners'              => Schema::owners_table(),
			'calendar'            => Schema::calendar_table(),
			'rich_media'          => Schema::media_table(),
		);
		global $wpdb;
		foreach ( $restore_tables as $key => $table ) {
			$columns = $wpdb->get_col( 'DESCRIBE ' . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table allowlist.
			foreach ( (array) ( $backup[ $key ] ?? array() ) as $row ) {
				$clean = array_intersect_key( (array) $row, array_flip( $columns ) );
				if ( $clean ) {
					$count += false !== $wpdb->replace( $table, $clean ) ? 1 : 0;
				}
			}
		}
		return $count;
	}

	/**
	 * Reads a known internal table for portable backups.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function table_rows( string $table ): array {
		global $wpdb;
		return array_map( static fn( object $row ): array => (array) $row, $wpdb->get_results( 'SELECT * FROM ' . $table ) ?: array() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal schema table.
	}

	private function csv_headers( string $filename ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	}

	/**
	 * Collects every page from a repository query.
	 *
	 * @return object[]
	 */
	private function all_pages( callable $query ): array {
		$items = array();
		$page  = 1;
		do {
			$result = $query( $page );
			$items  = array_merge( $items, $result['items'] );
			++$page;
		} while ( $page <= $result['pages'] );
		return $items;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot access these tools.', 'adam-comunidade' ) );
		}
	}
}
