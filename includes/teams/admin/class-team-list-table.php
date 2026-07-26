<?php
/**
 * Teams admin list table.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Repository;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays scalable team records with standard WordPress table controls.
 */
final class Team_List_Table extends \WP_List_Table {
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

		parent::__construct(
			array(
				'singular' => 'team',
				'plural'   => 'teams',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Defines visible columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'           => '<input type="checkbox">',
			'logo'         => __( 'Logótipo', 'adam-comunidade' ),
			'name'         => __( 'Nome da equipa', 'adam-comunidade' ),
			'district'     => __( 'Distrito', 'adam-comunidade' ),
			'municipality' => __( 'Concelho', 'adam-comunidade' ),
			'status'       => __( 'Estado', 'adam-comunidade' ),
			'members'      => __( 'Membros', 'adam-comunidade' ),
			'fields'       => __( 'Campos', 'adam-comunidade' ),
			'updated_at'   => __( 'Última atualização', 'adam-comunidade' ),
		);
	}

	/**
	 * Defines sortable columns.
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'name'         => array( 'name', false ),
			'district'     => array( 'district', false ),
			'municipality' => array( 'municipality', false ),
			'status'       => array( 'status', false ),
			'members'      => array( 'members', false ),
			'updated_at'   => array( 'updated_at', true ),
		);
	}

	/**
	 * Defines bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'publish' => __( 'Publicar', 'adam-comunidade' ),
			'hide'    => __( 'Ocultar', 'adam-comunidade' ),
			'delete'  => __( 'Eliminar', 'adam-comunidade' ),
		);
	}

	/**
	 * Loads table rows and pagination details.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$page     = filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT ) ?: 1;
		$orderby  = sanitize_key( (string) filter_input( INPUT_GET, 'orderby' ) );
		$order    = sanitize_key( (string) filter_input( INPUT_GET, 'order' ) );
		$status   = sanitize_key( (string) filter_input( INPUT_GET, 'team_status' ) );
		$district    = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
		$municipality = sanitize_text_field( (string) filter_input( INPUT_GET, 'municipality' ) );
		$search   = sanitize_text_field( (string) filter_input( INPUT_GET, 's' ) );
		$result   = $this->repository->query(
			array(
				'page'     => $page,
				'per_page' => $this->get_items_per_page( 'adam_teams_per_page', 20 ),
				'orderby'  => $orderby ?: 'updated_at',
				'order'    => $order ?: 'DESC',
				'status'   => $status,
				'district'     => $district,
				'municipality' => $municipality,
				'search'   => $search,
			)
		);

		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $this->get_items_per_page( 'adam_teams_per_page', 20 ),
				'total_pages' => $result['pages'],
			)
		);
	}

	/**
	 * Renders the selection checkbox.
	 *
	 * @param object $item Team row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="team[]" value="%d">', absint( $item->id ) );
	}

	/**
	 * Renders the logo thumbnail.
	 *
	 * @param object $item Team row.
	 * @return string
	 */
	protected function column_logo( $item ): string {
		if ( $item->logo_id ) {
			return wp_get_attachment_image(
				(int) $item->logo_id,
				array( 48, 48 ),
				false,
				array( 'class' => 'adam-team-table-logo' )
			);
		}

		return '<span class="adam-team-table-logo adam-team-table-logo--empty" aria-hidden="true">'
			. Helpers::svg_icon( 'community', 22 )
			. '</span>';
	}

	/**
	 * Renders the name and row actions.
	 *
	 * @param object $item Team row.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$edit_url = Admin_Router::module_url( 'teams', 'edit', array( 'id' => absint( $item->id ) ) );
		$actions  = array(
			'edit'      => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editar', 'adam-comunidade' ) . '</a>',
			'duplicate' => $this->action_link( $item, 'duplicate', __( 'Duplicar', 'adam-comunidade' ) ),
			'hide'      => $this->action_link( $item, 'hide', __( 'Ocultar', 'adam-comunidade' ) ),
			'delete'    => $this->action_link( $item, 'delete', __( 'Eliminar', 'adam-comunidade' ), true ),
		);

		return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->name ) . '</a></strong>'
			. $this->row_actions( $actions );
	}

	/**
	 * Renders the team status.
	 *
	 * @param object $item Team row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$statuses = Options::statuses();
		$class    = 'published' === $item->status ? 'success' : ( 'hidden' === $item->status ? 'warning' : 'muted' );

		return sprintf(
			'<span class="adam-badge adam-badge--%1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( $statuses[ $item->status ] ?? $item->status )
		);
	}

	/**
	 * Renders the future fields relationship column.
	 *
	 * @param object $item Team row.
	 * @return string
	 */
	protected function column_fields( $item ): string {
		$count = count( $this->repository->field_ids( (int) $item->id ) );

		return $count ? (string) $count : '&mdash;';
	}

	/**
	 * Renders the update timestamp.
	 *
	 * @param object $item Team row.
	 * @return string
	 */
	protected function column_updated_at( $item ): string {
		$timestamp = strtotime( $item->updated_at . ' UTC' );

		return $timestamp
			? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) )
			: '&mdash;';
	}

	/**
	 * Renders standard scalar columns.
	 *
	 * @param object $item       Team row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		if ( in_array( $column_name, array( 'district', 'municipality', 'members' ), true ) ) {
			$value = $item->{$column_name};

			return '' !== (string) $value ? esc_html( (string) $value ) : '&mdash;';
		}

		return '';
	}

	/**
	 * Builds a nonce-protected row action URL.
	 *
	 * @param object $item    Team row.
	 * @param string $action  Action name.
	 * @param string $label   Link label.
	 * @param bool   $delete  Whether to add destructive styling.
	 * @return string
	 */
	private function action_link( object $item, string $action, string $label, bool $delete = false ): string {
		$url = add_query_arg(
			array(
				'action'      => 'adam_team_action',
				'team_action' => $action,
				'team_id'     => absint( $item->id ),
			),
			admin_url( 'admin-post.php' )
		);
		$url = wp_nonce_url( $url, 'adam_team_action_' . absint( $item->id ) );

		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			$delete ? ' class="adam-team-delete"' : '',
			esc_html( $label )
		);
	}
}
