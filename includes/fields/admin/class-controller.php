<?php
/**
 * Fields admin controller.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Fields\Amenity_Repository;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Repository;
use ADAM\Comunidade\Fields\Validator;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Logger;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

/**
 * Coordinates Campos administration.
 */
final class Controller {
	/**
	 * Fields repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Amenities repository.
	 *
	 * @var Amenity_Repository
	 */
	private Amenity_Repository $amenities;

	/**
	 * Constructor.
	 *
	 * @param Repository         $repository Fields repository.
	 * @param Amenity_Repository $amenities  Amenity repository.
	 */
	public function __construct( Repository $repository, Amenity_Repository $amenities ) {
		$this->repository = $repository;
		$this->amenities  = $amenities;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		Admin_Router::register_module(
			'fields',
			array(
				'title'         => __( 'Campos', 'adam-comunidade' ),
				'singular'      => __( 'Campo', 'adam-comunidade' ),
				'singular_slug' => 'field',
				'controller'    => $this,
				'methods'       => array( 'list' => 'list', 'create' => 'create', 'edit' => 'edit' ),
				'load'          => array( $this, 'add_screen_options' ),
			)
		);
		Admin_Router::register_page(
			'field-amenities',
			array(
				'title'      => __( 'Comodidades dos campos', 'adam-comunidade' ),
				'controller' => $this,
				'method'     => 'render_amenities',
				'visible'    => false,
			)
		);
		add_action( 'admin_post_adam_field_save', array( $this, 'save' ) );
		add_action( 'admin_post_adam_field_action', array( $this, 'single_action' ) );
		add_action( 'admin_post_adam_amenities_save', array( $this, 'save_amenities' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
	}

	/**
	 * Adds fields-per-page option.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Campos por página', 'adam-comunidade' ),
				'default' => 20,
				'option'  => 'adam_fields_per_page',
			)
		);
	}

	/**
	 * Saves screen option.
	 *
	 * @param mixed  $status Screen option status.
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return mixed
	 */
	public function save_screen_option( mixed $status, string $option, mixed $value ): mixed {
		return 'adam_fields_per_page' === $option
			? max( 1, min( 100, absint( $value ) ) )
			: $status;
	}

	/**
	 * Loads field assets on module screens.
	 *
	 * @param string $hook_suffix Current hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'adam-comunidade-field' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'adam-comunidade-teams-admin',
			Helpers::url( 'assets/css/teams-admin.css' ),
			array( 'adam-comunidade-admin' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_style(
			'adam-comunidade-fields-admin',
			Helpers::url( 'assets/css/fields-admin.css' ),
			array( 'adam-comunidade-teams-admin' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_script(
			'adam-comunidade-fields-admin',
			Helpers::url( 'assets/js/fields-admin.js' ),
			array( 'jquery', 'jquery-ui-sortable' ),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_localize_script(
			'adam-comunidade-fields-admin',
			'adamFieldsAdmin',
			array(
				'confirmDelete' => __( 'Eliminar permanentemente este campo? Os ficheiros multimédia serão mantidos.', 'adam-comunidade' ),
				'coverTitle'    => __( 'Escolher capa do campo', 'adam-comunidade' ),
				'galleryTitle'  => __( 'Escolher imagens da galeria', 'adam-comunidade' ),
				'documentTitle' => __( 'Escolher documento de autorização legal', 'adam-comunidade' ),
				'useImage'      => __( 'Usar imagem', 'adam-comunidade' ),
				'useImages'     => __( 'Usar imagens', 'adam-comunidade' ),
				'useDocument'   => __( 'Usar documento', 'adam-comunidade' ),
				'removeImage'   => __( 'Remover imagem', 'adam-comunidade' ),
				'caption'       => __( 'Legenda', 'adam-comunidade' ),
			)
		);
	}

	/**
	 * Renders the fields list.
	 *
	 * @return void
	 */
	public function list(): void {
		$table = new Field_List_Table( $this->repository );
		$this->process_bulk_action( $table );
		$table->prepare_items();
		$counts         = $this->repository->statistics();
		$districts      = $this->repository->distinct( 'district' );
		$municipalities = $this->repository->distinct( 'municipality' );

		require Helpers::path( 'admin/views/fields/list.php' );
	}

	/**
	 * Renders create/edit screen.
	 *
	 * @return void
	 */
	public function create(): void {
		$this->editor( 0 );
	}

	/**
	 * Renders the editor in update mode.
	 *
	 * @return void
	 */
	public function edit( int $field_id ): void {
		$this->editor( $field_id );
	}

	/**
	 * Loads the shared create/edit editor.
	 *
	 * @param int $field_id Field ID, or zero for create mode.
	 * @return void
	 */
	private function editor( int $field_id ): void {
		$field    = $field_id ? $this->repository->find( $field_id ) : null;

		if ( $field_id && ! $field ) {
			wp_die( esc_html__( 'O campo não foi encontrado.', 'adam-comunidade' ) );
		}

		$form_state = get_transient( 'adam_field_form_' . get_current_user_id() );
		if ( is_array( $form_state ) ) {
			delete_transient( 'adam_field_form_' . get_current_user_id() );
			$field = (object) array_merge( $field ? (array) $field : array(), $form_state['data'] ?? array() );
		}

		$gallery            = $field_id ? $this->repository->gallery( $field_id ) : array();
		$selected_amenities = isset( $field->amenities )
			? array_map( 'absint', (array) $field->amenities )
			: ( $field_id ? $this->repository->amenity_ids( $field_id ) : array() );
		$selected_team = isset( $field->associated_team )
			? absint( $field->associated_team )
			: ( $field_id ? $this->repository->associated_team_id( $field_id ) : 0 );

		if ( isset( $field->gallery_ids ) ) {
			$captions = (array) ( $field->gallery_captions ?? array() );
			$gallery  = array_map(
				static fn( int $id ): object => (object) array(
					'attachment_id' => $id,
					'caption'       => sanitize_text_field( $captions[ $id ] ?? '' ),
				),
				array_filter( array_map( 'absint', (array) $field->gallery_ids ) )
			);
		}
		$amenity_options    = $this->amenities->all( 'field', true );
		$teams              = ( new Team_Repository() )->choices( 'published' );

		require Helpers::path( 'admin/views/fields/editor.php' );
	}

	/**
	 * Renders amenity vocabulary manager.
	 *
	 * @return void
	 */
	public function render_amenities(): void {
		$amenity_options = $this->amenities->all();
		$icon_options    = Options::amenity_icons();

		require Helpers::path( 'admin/views/fields/amenities.php' );
	}

	/**
	 * Saves a field and its related collections.
	 *
	 * @return void
	 */
	public function save(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_field_save' );

		$field_id = isset( $_POST['field_id'] ) ? absint( wp_unslash( $_POST['field_id'] ) ) : 0;
		if ( $field_id && ! $this->repository->find( $field_id ) ) {
			wp_die( esc_html__( 'O campo não foi encontrado.', 'adam-comunidade' ) );
		}

		$input = isset( $_POST['field'] ) && is_array( $_POST['field'] )
			? wp_unslash( $_POST['field'] )
			: array();
		$result = ( new Validator( $this->repository ) )->validate( $input, $field_id );

		if ( is_wp_error( $result ) ) {
			set_transient(
				'adam_field_form_' . get_current_user_id(),
				array( 'data' => $input ),
				5 * MINUTE_IN_SECONDS
			);
			Helpers::add_admin_notice( implode( ' ', $result->get_error_messages() ), 'error' );
			$this->redirect_editor( $field_id );
		}

		$saved_id = $field_id;
		if ( $field_id ) {
			$success = $this->repository->update( $field_id, $result );
		} else {
			$saved_id = $this->repository->create( $result );
			$success  = false !== $saved_id;
		}

		if ( ! $success ) {
			Helpers::add_admin_notice( __( 'Não foi possível guardar o campo.', 'adam-comunidade' ), 'error' );
			$this->redirect_editor( $field_id );
		}

		$this->repository->sync_amenities(
			(int) $saved_id,
			array_map( 'absint', (array) ( $input['amenities'] ?? array() ) )
		);
		$team_id = absint( $input['associated_team'] ?? 0 );
		$team    = $team_id ? ( new Team_Repository() )->find( $team_id ) : null;
		$this->repository->sync_team(
			(int) $saved_id,
			$team && 'published' === $team->status ? $team_id : 0
		);
		$this->repository->sync_gallery( (int) $saved_id, $this->gallery_items( $input ) );

		/**
		 * Fires after a field and its relationships have been saved.
		 *
		 * Future event, booking, weather, rating, review, and attendance modules
		 * can attach their own records to the stable field ID.
		 *
		 * @param int                 $field_id Saved field ID.
		 * @param array<string,mixed> $data     Validated field data.
		 */
		do_action( 'adam_comunidade_field_saved', (int) $saved_id, $result );

		Logger::info(
			$field_id ? 'Field updated' : 'Field created',
			array( 'field_id' => $saved_id, 'user_id' => get_current_user_id() )
		);
		Helpers::add_admin_notice( __( 'O campo foi guardado com sucesso.', 'adam-comunidade' ), 'success' );
		$this->redirect_editor( (int) $saved_id );
	}

	/**
	 * Saves amenity labels, icons, visibility, order, and a new definition.
	 *
	 * @return void
	 */
	public function save_amenities(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_amenities_save' );

		$rows = isset( $_POST['amenities'] ) && is_array( $_POST['amenities'] )
			? wp_unslash( $_POST['amenities'] )
			: array();

		foreach ( $rows as $id => $row ) {
			if ( is_array( $row ) && ! empty( $row['label'] ) ) {
				$this->amenities->update( absint( $id ), $row );
			}
		}

		$new = isset( $_POST['new_amenity'] ) && is_array( $_POST['new_amenity'] )
			? wp_unslash( $_POST['new_amenity'] )
			: array();

		if ( ! empty( $new['label'] ) ) {
			$new['context']     = 'field';
			$new['amenity_key'] = sanitize_title( $new['label'], '', 'save' );
			$new['status']      = 'active';
			$this->amenities->create( $new );
		}

		Logger::info( 'Field amenities changed', array( 'user_id' => get_current_user_id() ) );
		Helpers::add_admin_notice( __( 'As comodidades foram guardadas.', 'adam-comunidade' ), 'success' );
		wp_safe_redirect( Admin_Router::page_url( 'field-amenities' ) );
		exit;
	}

	/**
	 * Handles duplicate, hide, and delete actions.
	 *
	 * @return void
	 */
	public function single_action(): void {
		Admin_Router::authorize();
		$field_id = filter_input( INPUT_GET, 'field_id', FILTER_VALIDATE_INT ) ?: 0;
		$action   = sanitize_key( (string) filter_input( INPUT_GET, 'field_action' ) );
		check_admin_referer( 'adam_field_action_' . $field_id );

		if ( ! $field_id || ! in_array( $action, array( 'duplicate', 'hide', 'delete' ), true ) ) {
			wp_die( esc_html__( 'A ação do campo não é válida.', 'adam-comunidade' ) );
		}

		if ( 'duplicate' === $action ) {
			$new_id = $this->duplicate( $field_id );
			Helpers::add_admin_notice(
				$new_id
					? __( 'O campo foi duplicado como rascunho.', 'adam-comunidade' )
					: __( 'Não foi possível duplicar o campo.', 'adam-comunidade' ),
				$new_id ? 'success' : 'error'
			);
		} elseif ( 'hide' === $action ) {
			$this->repository->set_status( array( $field_id ), 'hidden' );
			Helpers::add_admin_notice( __( 'O campo foi ocultado.', 'adam-comunidade' ), 'success' );
		} else {
			$this->repository->delete( $field_id );
			Helpers::add_admin_notice( __( 'O campo foi eliminado. Os ficheiros multimédia foram mantidos.', 'adam-comunidade' ), 'success' );
		}

		Logger::info( 'Field ' . $action, array( 'field_id' => $field_id ) );
		Admin_Router::redirect_module( 'fields' );
	}

	/**
	 * Processes list-table bulk actions.
	 *
	 * @param Field_List_Table $table Table instance.
	 * @return void
	 */
	private function process_bulk_action( Field_List_Table $table ): void {
		$action = $table->current_action();

		if ( ! in_array( $action, array( 'publish', 'hide', 'delete' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-fields' );
		$request_ids = $_POST['field'] ?? $_GET['field'] ?? array();
		$ids         = array_map( 'absint', (array) wp_unslash( $request_ids ) );

		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$this->repository->delete( $id );
			} else {
				$this->repository->set_status(
					array( $id ),
					'publish' === $action ? 'published' : 'hidden'
				);
			}
		}

		if ( $ids ) {
			Helpers::add_admin_notice( __( 'A ação por lote foi concluída.', 'adam-comunidade' ), 'success' );
			Admin_Router::redirect_module( 'fields' );
		}
	}

	/**
	 * Duplicates a field and its gallery/amenity selections.
	 *
	 * @param int $field_id Source field ID.
	 * @return int|false
	 */
	private function duplicate( int $field_id ): int|false {
		$source = $this->repository->find( $field_id );
		if ( ! $source ) {
			return false;
		}

		$data                  = (array) $source;
		$data['status']        = 'draft';
		$data['playing_styles']= Options::decode_list( $source->playing_styles );
		$base_name             = $source->name . ' ' . __( 'Cópia', 'adam-comunidade' );
		$data['name']          = $base_name;
		$data['slug']          = sanitize_title( $base_name );
		$suffix                = 2;

		while ( $this->repository->exists( 'name', $data['name'] ) || $this->repository->exists( 'slug', $data['slug'] ) ) {
			$data['name'] = $base_name . ' ' . $suffix;
			$data['slug'] = sanitize_title( $data['name'] );
			++$suffix;
		}

		$validated = ( new Validator( $this->repository ) )->validate( $data );
		if ( is_wp_error( $validated ) ) {
			return false;
		}

		$new_id = $this->repository->create( $validated );
		if ( ! $new_id ) {
			return false;
		}

		$this->repository->sync_amenities( $new_id, $this->repository->amenity_ids( $field_id ) );
		$this->repository->sync_team( $new_id, $this->repository->associated_team_id( $field_id ) );
		$gallery = array_map(
			static fn( object $item ): array => array(
				'id'      => (int) $item->attachment_id,
				'caption' => $item->caption,
			),
			$this->repository->gallery( $field_id )
		);
		$this->repository->sync_gallery( $new_id, $gallery );

		return $new_id;
	}

	/**
	 * Builds ordered gallery rows from form data.
	 *
	 * @param array<string,mixed> $input Field input.
	 * @return array<int,array{id:int,caption:string}>
	 */
	private function gallery_items( array $input ): array {
		$ids      = array_map( 'absint', (array) ( $input['gallery_ids'] ?? array() ) );
		$captions = (array) ( $input['gallery_captions'] ?? array() );
		$items    = array();

		foreach ( $ids as $id ) {
			if ( $id && wp_attachment_is_image( $id ) ) {
				$items[] = array(
					'id'      => $id,
					'caption' => sanitize_text_field( $captions[ $id ] ?? '' ),
				);
			}
		}

		return $items;
	}

	/**
	 * Enforces capability.
	 *
	 * @return void
	 */
	private function redirect_editor( int $field_id ): never {
		Admin_Router::redirect_module(
			'fields',
			$field_id ? 'edit' : 'add',
			$field_id ? array( 'id' => $field_id ) : array()
		);
	}
}
