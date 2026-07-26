<?php
/**
 * Teams admin controller.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Logger;
use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Repository;
use ADAM\Comunidade\Teams\Validator;
use ADAM\Comunidade\Fields\Repository as Field_Repository;

/**
 * Coordinates team admin screens and actions.
 */
final class Controller {
	/**
	 * Teams repository.
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
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		Admin_Router::register_module(
			'teams',
			array(
				'title'         => __( 'Equipas', 'adam-comunidade' ),
				'singular'      => __( 'Team', 'adam-comunidade' ),
				'singular_slug' => 'team',
				'controller'    => $this,
				'methods'       => array(
					'list'   => 'list',
					'create' => 'create',
					'edit'   => 'edit',
				),
				'load'          => array( $this, 'add_screen_options' ),
			)
		);
		add_action( 'admin_post_adam_team_save', array( $this, 'save' ) );
		add_action( 'admin_post_adam_team_action', array( $this, 'single_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
	}

	/**
	 * Adds the teams-per-page option.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Teams per page', 'adam-comunidade' ),
				'default' => 20,
				'option'  => 'adam_teams_per_page',
			)
		);
	}

	/**
	 * Persists the list-table screen option.
	 *
	 * @param mixed  $status Screen option status.
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted value.
	 * @return mixed
	 */
	public function save_screen_option( mixed $status, string $option, mixed $value ): mixed {
		if ( 'adam_teams_per_page' === $option ) {
			return max( 1, min( 100, absint( $value ) ) );
		}

		return $status;
	}

	/**
	 * Loads module assets only on team screens.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, 'adam-comunidade-team' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'adam-comunidade-teams-admin',
			Helpers::url( 'assets/css/teams-admin.css' ),
			array( 'adam-comunidade-admin' ),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_script(
			'adam-comunidade-teams-admin',
			Helpers::url( 'assets/js/teams-admin.js' ),
			array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_localize_script(
			'adam-comunidade-teams-admin',
			'adamTeamsAdmin',
			array(
				'confirmDelete' => __( 'Permanently delete this team? Media files will be retained.', 'adam-comunidade' ),
				'logoTitle'     => __( 'Choose Team Logo', 'adam-comunidade' ),
				'coverTitle'    => __( 'Choose Cover Image', 'adam-comunidade' ),
				'galleryTitle'  => __( 'Choose Gallery Images', 'adam-comunidade' ),
				'useImage'      => __( 'Use image', 'adam-comunidade' ),
				'useImages'     => __( 'Use images', 'adam-comunidade' ),
			)
		);
	}

	/**
	 * Renders the list screen.
	 *
	 * @return void
	 */
	public function list(): void {
		$table = new Team_List_Table( $this->repository );
		$this->process_bulk_action( $table );
		$table->prepare_items();
		$counts         = $this->repository->status_counts();
		$districts      = $this->repository->distinct( 'district' );
		$municipalities = $this->repository->distinct( 'municipality' );
		$view      = Helpers::path( 'admin/views/teams/list.php' );

		require $view;
	}

	/**
	 * Renders the create/edit screen.
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
	public function edit( int $team_id ): void {
		$this->editor( $team_id );
	}

	/**
	 * Loads the shared create/edit editor.
	 *
	 * @param int $team_id Team ID, or zero for create mode.
	 * @return void
	 */
	private function editor( int $team_id ): void {
		$team    = $team_id ? $this->repository->find( $team_id ) : null;

		if ( $team_id && ! $team ) {
			wp_die( esc_html__( 'Team not found.', 'adam-comunidade' ) );
		}

		$form_state = get_transient( 'adam_team_form_' . get_current_user_id() );
		if ( is_array( $form_state ) ) {
			delete_transient( 'adam_team_form_' . get_current_user_id() );
			$team = (object) array_merge( $team ? (array) $team : array(), $form_state['data'] ?? array() );
		}

		$view = Helpers::path( 'admin/views/teams/editor.php' );
		$field_repository = new Field_Repository();
		$available_fields = $field_repository->choices( 'published' );
		$selected_fields = isset( $team->associated_fields )
			? array_map( 'absint', (array) $team->associated_fields )
			: ( $team_id ? $this->repository->field_ids( $team_id ) : array() );
		require $view;
	}

	/**
	 * Saves a team submission.
	 *
	 * @return void
	 */
	public function save(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_team_save' );

		$team_id = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;
		if ( $team_id && ! $this->repository->find( $team_id ) ) {
			wp_die( esc_html__( 'Team not found.', 'adam-comunidade' ) );
		}

		$input   = isset( $_POST['team'] ) && is_array( $_POST['team'] )
			? wp_unslash( $_POST['team'] )
			: array();
		$result  = ( new Validator( $this->repository ) )->validate( $input, $team_id );

		if ( is_wp_error( $result ) ) {
			set_transient(
				'adam_team_form_' . get_current_user_id(),
				array( 'data' => $input ),
				5 * MINUTE_IN_SECONDS
			);
			Helpers::add_admin_notice( implode( ' ', $result->get_error_messages() ), 'error' );
			$this->redirect_editor( $team_id );
		}

		$saved_id = $team_id;
		if ( $team_id ) {
			$success = $this->repository->update( $team_id, $result );
		} else {
			$saved_id = $this->repository->create( $result );
			$success  = false !== $saved_id;
		}

		if ( ! $success ) {
			Helpers::add_admin_notice( __( 'The team could not be saved.', 'adam-comunidade' ), 'error' );
			$this->redirect_editor( $team_id );
		}

		$field_repository = new Field_Repository();
		$allowed_fields   = array_map(
			static fn( object $field ): int => (int) $field->id,
			$field_repository->choices( 'published' )
		);
		$selected_fields  = array_intersect(
			array_map( 'absint', (array) ( $input['associated_fields'] ?? array() ) ),
			$allowed_fields
		);
		$this->repository->sync_fields( (int) $saved_id, $selected_fields );

		Logger::info(
			$team_id ? 'Team updated' : 'Team created',
			array( 'team_id' => $saved_id, 'user_id' => get_current_user_id() )
		);
		do_action( 'adam_comunidade_team_saved', (int) $saved_id, $result );
		Helpers::add_admin_notice( __( 'Team saved successfully.', 'adam-comunidade' ), 'success' );
		$this->redirect_editor( (int) $saved_id );
	}

	/**
	 * Handles a nonce-protected row action.
	 *
	 * @return void
	 */
	public function single_action(): void {
		Admin_Router::authorize();

		$team_id = filter_input( INPUT_GET, 'team_id', FILTER_VALIDATE_INT ) ?: 0;
		$action  = sanitize_key( (string) filter_input( INPUT_GET, 'team_action' ) );

		check_admin_referer( 'adam_team_action_' . $team_id );

		if ( ! $team_id || ! in_array( $action, array( 'duplicate', 'hide', 'delete' ), true ) ) {
			wp_die( esc_html__( 'Invalid team action.', 'adam-comunidade' ) );
		}

		if ( 'duplicate' === $action ) {
			$new_id = $this->duplicate( $team_id );
			Helpers::add_admin_notice(
				$new_id
					? __( 'Team duplicated as a draft.', 'adam-comunidade' )
					: __( 'The team could not be duplicated.', 'adam-comunidade' ),
				$new_id ? 'success' : 'error'
			);
		} elseif ( 'hide' === $action ) {
			$this->repository->set_status( array( $team_id ), 'hidden' );
			Helpers::add_admin_notice( __( 'Team hidden.', 'adam-comunidade' ), 'success' );
		} else {
			$this->repository->delete( $team_id );
			Helpers::add_admin_notice( __( 'Team deleted. Media files were retained.', 'adam-comunidade' ), 'success' );
		}

		Logger::info( 'Team ' . $action, array( 'team_id' => $team_id, 'user_id' => get_current_user_id() ) );
		Admin_Router::redirect_module( 'teams' );
	}

	/**
	 * Processes standard bulk actions.
	 *
	 * @param Team_List_Table $table List table.
	 * @return void
	 */
	private function process_bulk_action( Team_List_Table $table ): void {
		$action = $table->current_action();

		if ( ! in_array( $action, array( 'publish', 'hide', 'delete' ), true ) ) {
			return;
		}

		check_admin_referer( 'bulk-teams' );
		$request_ids = $_POST['team'] ?? $_GET['team'] ?? array();
		$ids         = array_map( 'absint', (array) wp_unslash( $request_ids ) );

		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$this->repository->delete( $id );
			} else {
				$this->repository->set_status( array( $id ), 'publish' === $action ? 'published' : 'hidden' );
			}
		}

		if ( $ids ) {
			Logger::info( 'Team bulk action: ' . $action, array( 'team_ids' => $ids ) );
			Helpers::add_admin_notice( __( 'Bulk action completed.', 'adam-comunidade' ), 'success' );
			Admin_Router::redirect_module( 'teams' );
		}
	}

	/**
	 * Duplicates a team as a uniquely named draft.
	 *
	 * @param int $team_id Source team ID.
	 * @return int|false
	 */
	private function duplicate( int $team_id ): int|false {
		$source = $this->repository->find( $team_id );

		if ( ! $source ) {
			return false;
		}

		$data                    = (array) $source;
		$data['gallery']          = Options::decode_list( $source->gallery );
		$data['playing_styles']   = Options::decode_list( $source->playing_styles );
		$data['equipment_tags']   = Options::decode_list( $source->equipment_tags );
		$data['status']            = 'draft';
		$base_name                 = $source->name . ' ' . __( 'Copy', 'adam-comunidade' );
		$data['name']              = $base_name;
		$data['slug']              = sanitize_title( $base_name );
		$suffix                    = 2;

		while ( $this->repository->exists( 'name', $data['name'] ) || $this->repository->exists( 'slug', $data['slug'] ) ) {
			$data['name'] = $base_name . ' ' . $suffix;
			$data['slug'] = sanitize_title( $data['name'] );
			++$suffix;
		}

		$validated = ( new Validator( $this->repository ) )->validate( $data );

		if ( is_wp_error( $validated ) ) {
			return false;
		}

		return $this->repository->create( $validated );
	}

	/**
	 * Redirects back to the editor and exits.
	 *
	 * @param int $team_id Team ID.
	 * @return never
	 */
	private function redirect_editor( int $team_id ): never {
		Admin_Router::redirect_module(
			'teams',
			$team_id ? 'edit' : 'add',
			$team_id ? array( 'id' => $team_id ) : array()
		);
	}
}
