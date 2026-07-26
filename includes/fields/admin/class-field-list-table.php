<?php
/**
 * Fields admin list table.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields\Admin;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Repository;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays field records using native WordPress table behavior.
 */
final class Field_List_Table extends \WP_List_Table {
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

		parent::__construct(
			array(
				'singular' => 'field',
				'plural'   => 'fields',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Defines list columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'              => '<input type="checkbox">',
			'cover'           => __( 'Capa', 'adam-comunidade' ),
			'name'            => __( 'Nome', 'adam-comunidade' ),
			'district'        => __( 'Distrito', 'adam-comunidade' ),
			'municipality'    => __( 'Concelho', 'adam-comunidade' ),
			'association'     => __( 'Associação', 'adam-comunidade' ),
			'associated_team' => __( 'Equipa associada', 'adam-comunidade' ),
			'status'          => __( 'Estado', 'adam-comunidade' ),
			'updated_at'      => __( 'Última atualização', 'adam-comunidade' ),
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
	 * Loads rows and pagination.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$result = $this->repository->query(
			array(
				'page'         => filter_input( INPUT_GET, 'paged', FILTER_VALIDATE_INT ) ?: 1,
				'per_page'     => $this->get_items_per_page( 'adam_fields_per_page', 20 ),
				'orderby'      => sanitize_key( (string) filter_input( INPUT_GET, 'orderby' ) ),
				'order'        => sanitize_key( (string) filter_input( INPUT_GET, 'order' ) ),
				'status'       => sanitize_key( (string) filter_input( INPUT_GET, 'field_status' ) ),
				'district'     => sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) ),
				'municipality' => sanitize_text_field( (string) filter_input( INPUT_GET, 'municipality' ) ),
				'search'       => sanitize_text_field( (string) filter_input( INPUT_GET, 's' ) ),
			)
		);
		$this->items = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $this->get_items_per_page( 'adam_fields_per_page', 20 ),
				'total_pages' => $result['pages'],
			)
		);
	}

	/**
	 * Renders a row checkbox.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="field[]" value="%d">', absint( $item->id ) );
	}

	/**
	 * Renders a cover thumbnail.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_cover( $item ): string {
		if ( ! $item->cover_id ) {
			return '<span class="adam-field-cover-placeholder" aria-hidden="true"></span>';
		}

		return wp_get_attachment_image(
			(int) $item->cover_id,
			array( 72, 44 ),
			false,
			array( 'class' => 'adam-field-table-cover' )
		);
	}

	/**
	 * Renders name and row actions.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$edit_url = Admin_Router::module_url( 'fields', 'edit', array( 'id' => absint( $item->id ) ) );
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
	 * Renders status.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$class = 'published' === $item->status ? 'success' : ( 'hidden' === $item->status ? 'warning' : 'muted' );

		return sprintf(
			'<span class="adam-badge adam-badge--%1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( Options::statuses()[ $item->status ] ?? $item->status )
		);
	}

	/**
	 * Renders associated team.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_associated_team( $item ): string {
		return $item->associated_team_name
			? esc_html( $item->associated_team_name )
			: '&mdash;';
	}

	/**
	 * Renders the ADAM association distinction.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_association( $item ): string {
		return ! empty( $item->is_associated )
			? '<span class="adam-badge adam-badge--success">' . esc_html__( 'Associado ADAM', 'adam-comunidade' ) . '</span>'
			: '&mdash;';
	}

	/**
	 * Renders update time.
	 *
	 * @param object $item Field row.
	 * @return string
	 */
	protected function column_updated_at( $item ): string {
		$timestamp = strtotime( $item->updated_at . ' UTC' );

		return $timestamp
			? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) )
			: '&mdash;';
	}

	/**
	 * Renders scalar columns.
	 *
	 * @param object $item        Field row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		if ( in_array( $column_name, array( 'district', 'municipality' ), true ) ) {
			return $item->{$column_name} ? esc_html( $item->{$column_name} ) : '&mdash;';
		}

		return '';
	}

	/**
	 * Builds a secure row-action link.
	 *
	 * @param object $item   Field row.
	 * @param string $action Action.
	 * @param string $label  Label.
	 * @param bool   $delete Add delete class.
	 * @return string
	 */
	private function action_link( object $item, string $action, string $label, bool $delete = false ): string {
		$url = add_query_arg(
			array(
				'action'       => 'adam_field_action',
				'field_action' => $action,
				'field_id'     => absint( $item->id ),
			),
			admin_url( 'admin-post.php' )
		);
		$url = wp_nonce_url( $url, 'adam_field_action_' . absint( $item->id ) );

		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			$delete ? ' class="adam-field-delete"' : '',
			esc_html( $label )
		);
	}
}
