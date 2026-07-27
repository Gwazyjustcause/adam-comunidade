<?php
/**
 * Shared directory administration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Directory\Relationship_Repository;
use ADAM\Comunidade\Directory\Repository;
use ADAM\Comunidade\Directory\Router;
use ADAM\Comunidade\Directory\Types;
use ADAM\Comunidade\Directory\Validator;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Logger;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

/**
 * Coordinates all Phase 4 directory screens.
 */
final class Controller {
	public function __construct(
		private Repository $repository,
		private Relationship_Repository $relationships
	) {}

	public function register(): void {
		foreach ( Types::all() as $type => $definition ) {
			Admin_Router::register_module(
				(string) $definition['module_id'],
				array(
					'title'         => $definition['plural'],
					'singular'      => $definition['singular'],
					'singular_slug' => $type,
					'controller'    => $this,
					'methods'       => array( 'list' => 'list', 'create' => 'create', 'edit' => 'edit' ),
					'arguments'     => array( $type ),
				)
			);
		}
		add_action( 'admin_post_adam_directory_save', array( $this, 'save' ) );
		add_action( 'admin_post_adam_directory_action', array( $this, 'action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'adam-comunidade-directory' )
			&& ! str_contains( $hook_suffix, 'adam-comunidade-partner' )
			&& ! str_contains( $hook_suffix, 'adam-comunidade-institution' )
		) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'adam-comunidade-directory-admin', Helpers::url( 'assets/css/directory-admin.css' ), array( 'adam-comunidade-admin' ), ADAM_COMUNIDADE_VERSION );
		wp_enqueue_script( 'adam-comunidade-directory-admin', Helpers::url( 'assets/js/directory-admin.js' ), array( 'jquery', 'jquery-ui-sortable' ), ADAM_COMUNIDADE_VERSION, true );
		wp_localize_script(
			'adam-comunidade-directory-admin',
			'adamDirectoryAdmin',
			array(
				'mediaTitle'    => __( 'Escolher ficheiro multimédia', 'adam-comunidade' ),
				'useMedia'      => __( 'Usar ficheiro selecionado', 'adam-comunidade' ),
				'galleryTitle'  => __( 'Escolher imagens da galeria', 'adam-comunidade' ),
				'confirmDelete' => __( 'Eliminar permanentemente este registo? Os ficheiros multimédia serão mantidos.', 'adam-comunidade' ),
			)
		);
	}

	public function list( string $type ): void {
		$definition = $this->definition( $type );
		$this->process_bulk( $type );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$args = array(
			'search'   => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'status'   => sanitize_key( $_GET['status'] ?? '' ),
			'category' => sanitize_key( $_GET['category'] ?? '' ),
			'district' => sanitize_text_field( wp_unslash( $_GET['district'] ?? '' ) ),
			'orderby'  => sanitize_key( $_GET['orderby'] ?? 'updated_at' ),
			'order'    => sanitize_key( $_GET['order'] ?? 'desc' ),
			'page'     => $page,
			'per_page' => 20,
		);
		$result = $this->repository->query( $type, $args );
		$counts = $this->repository->statistics( $type );
		require Helpers::path( 'admin/views/directory/list.php' );
	}

	public function create( string $type ): void {
		$this->editor( $type, 0 );
	}

	public function edit( string $type, int $entry_id ): void {
		$this->editor( $type, $entry_id );
	}

	private function editor( string $type, int $entry_id ): void {
		$definition = $this->definition( $type );
		$entry      = $entry_id ? $this->repository->find( $entry_id, $type ) : null;
		if ( $entry_id && ! $entry ) {
			wp_die( esc_html__( 'O registo do diretório não foi encontrado.', 'adam-comunidade' ) );
		}
		$gallery = $entry ? $this->repository->gallery( $entry_id ) : array();
		$selected = array(
			'partner' => $entry ? $this->relationships->target_ids( $type, $entry_id, 'associated', 'partner' ) : array(),
			'team'  => $entry ? $this->relationships->target_ids( $type, $entry_id, 'supports', 'team' ) : array(),
			'field' => $entry ? $this->relationships->target_ids( $type, $entry_id, 'supports', 'field' ) : array(),
		);
		$choices = array(
			'partner' => $this->repository->choices( 'partner' ),
			'team'  => ( new Team_Repository() )->choices( 'published' ),
			'field' => ( new Field_Repository() )->choices( 'published' ),
		);
		require Helpers::path( 'admin/views/directory/editor.php' );
	}

	public function save(): void {
		Admin_Router::authorize();
		$type = sanitize_key( $_POST['entity_type'] ?? '' );
		$this->definition( $type );
		check_admin_referer( 'adam_directory_save_' . $type );
		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		if ( $entry_id && ! $this->repository->find( $entry_id, $type ) ) {
			wp_die( esc_html__( 'O registo do diretório não foi encontrado.', 'adam-comunidade' ) );
		}
		$input = isset( $_POST['entry'] ) && is_array( $_POST['entry'] ) ? wp_unslash( $_POST['entry'] ) : array();
		$data  = Validator::sanitize( $type, $input );
		if ( ! $data['name'] || ! $data['slug'] || $this->repository->exists( $type, 'name', $data['name'], $entry_id ) || $this->repository->exists( $type, 'slug', $data['slug'], $entry_id ) ) {
			Helpers::add_admin_notice( __( 'O nome e o slug são obrigatórios e têm de ser únicos neste diretório.', 'adam-comunidade' ), 'error' );
			$this->redirect_editor( $type, $entry_id );
		}
		$saved_id = $entry_id;
		$success  = $entry_id ? $this->repository->update( $entry_id, $data ) : false !== ( $saved_id = $this->repository->create( $data ) );
		if ( ! $success ) {
			Helpers::add_admin_notice( __( 'Não foi possível guardar o registo.', 'adam-comunidade' ), 'error' );
			$this->redirect_editor( $type, $entry_id );
		}
		$this->repository->sync_gallery( (int) $saved_id, $this->gallery_items( $input ) );
		$relations = isset( $input['relations'] ) && is_array( $input['relations'] ) ? $input['relations'] : array();
		$this->relationships->sync( $type, (int) $saved_id, 'associated', 'partner', (array) ( $relations['partner'] ?? array() ) );
		$this->relationships->sync( $type, (int) $saved_id, 'supports', 'team', (array) ( $relations['team'] ?? array() ) );
		$this->relationships->sync( $type, (int) $saved_id, 'supports', 'field', (array) ( $relations['field'] ?? array() ) );
		do_action( 'adam_comunidade_directory_entry_saved', $type, (int) $saved_id, $data );
		Logger::info( ucfirst( $type ) . ( $entry_id ? ' updated' : ' created' ), array( 'entry_id' => $saved_id ) );
		Helpers::add_admin_notice( __( 'O registo foi guardado com sucesso.', 'adam-comunidade' ), 'success' );
		$this->redirect_editor( $type, (int) $saved_id );
	}

	public function action(): void {
		Admin_Router::authorize();
		$type     = sanitize_key( $_GET['entity_type'] ?? '' );
		$entry_id = absint( $_GET['entry_id'] ?? 0 );
		$action   = sanitize_key( $_GET['entry_action'] ?? '' );
		$definition = $this->definition( $type );
		check_admin_referer( 'adam_directory_action_' . $entry_id );
		$entry = $this->repository->find( $entry_id, $type );
		if ( ! $entry || ! in_array( $action, array( 'duplicate', 'hide', 'delete' ), true ) ) {
			wp_die( esc_html__( 'A ação do diretório não é válida.', 'adam-comunidade' ) );
		}
		if ( 'delete' === $action ) {
			$this->repository->delete( $entry_id );
		} elseif ( 'hide' === $action ) {
			$this->repository->update( $entry_id, array( 'status' => 'hidden' ) );
		} else {
			$data         = (array) $entry;
			$data['name'] = $entry->name . ' ' . __( 'Cópia', 'adam-comunidade' );
			$data['slug'] = sanitize_title( $data['name'] );
			$data['status'] = 'draft';
			foreach ( array( 'id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'published_at' ) as $key ) {
				unset( $data[ $key ] );
			}
			$suffix = 2;
			while ( $this->repository->exists( $type, 'name', $data['name'] ) || $this->repository->exists( $type, 'slug', $data['slug'] ) ) {
				$data['name'] = $entry->name . ' ' . __( 'Cópia', 'adam-comunidade' ) . ' ' . $suffix++;
				$data['slug'] = sanitize_title( $data['name'] );
			}
			$new_id = $this->repository->create( $data );
			if ( $new_id ) {
				$gallery = array_map(
					static fn( object $image ): array => array( 'id' => (int) $image->attachment_id, 'caption' => $image->caption ),
					$this->repository->gallery( $entry_id )
				);
				$this->repository->sync_gallery( $new_id, $gallery );
				foreach ( array(
					array( 'associated', 'partner' ),
					array( 'supports', 'team' ),
					array( 'supports', 'field' ),
				) as $relation ) {
					$this->relationships->sync(
						$type,
						$new_id,
						$relation[0],
						$relation[1],
						$this->relationships->target_ids( $type, $entry_id, $relation[0], $relation[1] )
					);
				}
			}
		}
		Logger::info( ucfirst( $type ) . ' ' . $action, array( 'entry_id' => $entry_id ) );
		Helpers::add_admin_notice( __( 'A ação foi concluída.', 'adam-comunidade' ), 'success' );
		Admin_Router::redirect_module( (string) $definition['module_id'] );
	}

	private function process_bulk( string $type ): void {
		if ( empty( $_POST['bulk_action'] ) || empty( $_POST['entry_ids'] ) ) {
			return;
		}
		check_admin_referer( 'adam_directory_bulk_' . $type );
		$action = sanitize_key( $_POST['bulk_action'] );
		if ( ! in_array( $action, array( 'publish', 'hide', 'delete' ), true ) ) {
			return;
		}
		foreach ( array_map( 'absint', (array) $_POST['entry_ids'] ) as $id ) {
			if ( $this->repository->find( $id, $type ) ) {
				'delete' === $action ? $this->repository->delete( $id ) : $this->repository->update( $id, array( 'status' => 'publish' === $action ? 'published' : 'hidden' ) );
			}
		}
	}

	private function gallery_items( array $input ): array {
		$captions = (array) ( $input['gallery_captions'] ?? array() );
		$items = array();
		foreach ( array_map( 'absint', (array) ( $input['gallery_ids'] ?? array() ) ) as $id ) {
			if ( $id && wp_attachment_is_image( $id ) ) {
				$items[] = array( 'id' => $id, 'caption' => sanitize_text_field( $captions[ $id ] ?? '' ) );
			}
		}
		return $items;
	}

	private function definition( string $type ): array {
		$definition = Types::get( $type );
		if ( ! $definition ) {
			wp_die( esc_html__( 'O tipo de diretório não é válido.', 'adam-comunidade' ) );
		}
		return $definition;
	}

	private function redirect_editor( string $type, int $entry_id ): never {
		$definition = $this->definition( $type );
		Admin_Router::redirect_module(
			(string) $definition['module_id'],
			$entry_id ? 'edit' : 'add',
			$entry_id ? array( 'id' => $entry_id ) : array()
		);
	}
}
