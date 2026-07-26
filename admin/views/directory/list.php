<?php
/**
 * Shared directory list.
 *
 * @var string              $type
 * @var array<string,mixed> $definition
 * @var array<string,mixed> $args
 * @var array<string,mixed> $result
 * @var array<string,int>   $counts
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$module_id = (string) $definition['module_id'];
$edit_url  = \ADAM\Comunidade\Admin\Router::module_url( $module_id, 'add' );
?>
<div class="wrap adam-comunidade-admin">
	<div class="adam-admin-heading">
		<div>
			<h1><?php echo esc_html( $definition['plural'] ); ?></h1>
			<p><?php esc_html_e( 'Gira a informação pública da Comunidade sem editar páginas WordPress.', 'adam-comunidade' ); ?></p>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">
			<?php echo esc_html( sprintf( __( 'Adicionar %s', 'adam-comunidade' ), $definition['singular'] ) ); ?>
		</a>
	</div>

	<div class="adam-directory-statuses">
		<?php foreach ( array( 'all' => __( 'Todos', 'adam-comunidade' ), 'published' => __( 'Publicados', 'adam-comunidade' ), 'draft' => __( 'Rascunhos', 'adam-comunidade' ), 'hidden' => __( 'Ocultos', 'adam-comunidade' ), 'featured' => __( 'Em destaque', 'adam-comunidade' ) ) as $status => $status_label ) : ?>
			<span class="adam-comunidade__badge"><?php echo esc_html( $status_label . ': ' . ( $counts[ $status ] ?? 0 ) ); ?></span>
		<?php endforeach; ?>
	</div>

	<form method="get" class="adam-directory-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( 'adam-comunidade-' . $module_id ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Pesquisar…', 'adam-comunidade' ); ?>">
		<select name="status">
			<option value=""><?php esc_html_e( 'Todos os estados', 'adam-comunidade' ); ?></option>
			<?php foreach ( array( 'published' => __( 'Publicado', 'adam-comunidade' ), 'draft' => __( 'Rascunho', 'adam-comunidade' ), 'hidden' => __( 'Oculto', 'adam-comunidade' ) ) as $status => $status_label ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $args['status'], $status ); ?>><?php echo esc_html( $status_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $definition['categories'] ) : ?>
			<select name="category">
				<option value=""><?php esc_html_e( 'Todas as categorias', 'adam-comunidade' ); ?></option>
				<?php foreach ( $definition['categories'] as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $args['category'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<select name="orderby">
			<option value="updated_at" <?php selected( $args['orderby'], 'updated_at' ); ?>><?php esc_html_e( 'Atualizados recentemente', 'adam-comunidade' ); ?></option>
			<option value="name" <?php selected( $args['orderby'], 'name' ); ?>><?php esc_html_e( 'Nome', 'adam-comunidade' ); ?></option>
			<option value="priority" <?php selected( $args['orderby'], 'priority' ); ?>><?php esc_html_e( 'Prioridade', 'adam-comunidade' ); ?></option>
		</select>
		<button class="button"><?php esc_html_e( 'Filtrar', 'adam-comunidade' ); ?></button>
	</form>

	<form method="post">
		<?php wp_nonce_field( 'adam_directory_bulk_' . $type ); ?>
		<div class="adam-directory-bulk">
			<select name="bulk_action">
				<option value=""><?php esc_html_e( 'Ações por lote', 'adam-comunidade' ); ?></option>
				<option value="publish"><?php esc_html_e( 'Publicar', 'adam-comunidade' ); ?></option>
				<option value="hide"><?php esc_html_e( 'Ocultar', 'adam-comunidade' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Eliminar', 'adam-comunidade' ); ?></option>
			</select>
			<button class="button"><?php esc_html_e( 'Aplicar', 'adam-comunidade' ); ?></button>
		</div>
		<table class="wp-list-table widefat fixed striped adam-directory-table">
			<thead><tr>
				<td class="check-column"><input type="checkbox" data-adam-check-all></td>
				<th><?php esc_html_e( 'Logótipo', 'adam-comunidade' ); ?></th>
				<th><?php esc_html_e( 'Nome', 'adam-comunidade' ); ?></th>
				<th><?php esc_html_e( 'Categoria', 'adam-comunidade' ); ?></th>
				<th><?php esc_html_e( 'Distrito / País', 'adam-comunidade' ); ?></th>
				<th><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></th>
				<th><?php esc_html_e( 'Em destaque', 'adam-comunidade' ); ?></th>
				<th><?php esc_html_e( 'Última atualização', 'adam-comunidade' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $result['items'] ) : ?>
				<tr><td colspan="8"><div class="adam-comunidade__empty"><?php esc_html_e( 'Não foram encontrados registos.', 'adam-comunidade' ); ?></div></td></tr>
			<?php endif; ?>
			<?php foreach ( $result['items'] as $entry ) : ?>
				<?php
				$action_base = array(
					'action'      => 'adam_directory_action',
					'entity_type' => $type,
					'entry_id'    => $entry->id,
				);
				?>
				<tr>
					<th class="check-column"><input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr( $entry->id ); ?>"></th>
					<td><?php echo $entry->logo_id ? wp_get_attachment_image( $entry->logo_id, array( 48, 48 ) ) : '<span class="dashicons dashicons-format-image"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td>
						<strong><a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( $module_id, 'edit', array( 'id' => $entry->id ) ) ); ?>"><?php echo esc_html( $entry->name ); ?></a></strong>
						<div class="row-actions">
							<a href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::module_url( $module_id, 'edit', array( 'id' => $entry->id ) ) ); ?>"><?php esc_html_e( 'Editar', 'adam-comunidade' ); ?></a> |
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( $action_base + array( 'entry_action' => 'duplicate' ), admin_url( 'admin-post.php' ) ), 'adam_directory_action_' . $entry->id ) ); ?>"><?php esc_html_e( 'Duplicar', 'adam-comunidade' ); ?></a> |
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( $action_base + array( 'entry_action' => 'hide' ), admin_url( 'admin-post.php' ) ), 'adam_directory_action_' . $entry->id ) ); ?>"><?php esc_html_e( 'Ocultar', 'adam-comunidade' ); ?></a> |
							<a class="submitdelete adam-directory-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( $action_base + array( 'entry_action' => 'delete' ), admin_url( 'admin-post.php' ) ), 'adam_directory_action_' . $entry->id ) ); ?>"><?php esc_html_e( 'Eliminar', 'adam-comunidade' ); ?></a>
						</div>
					</td>
					<td><?php echo esc_html( $definition['categories'][ $entry->category ] ?? '—' ); ?></td>
					<td><?php echo esc_html( $entry->district ?: ( $entry->country ?: '—' ) ); ?></td>
					<td><span class="adam-comunidade__badge"><?php echo esc_html( array( 'published' => __( 'Publicado', 'adam-comunidade' ), 'draft' => __( 'Rascunho', 'adam-comunidade' ), 'hidden' => __( 'Oculto', 'adam-comunidade' ) )[ $entry->status ] ?? $entry->status ); ?></span></td>
					<td><?php echo $entry->featured ? esc_html__( 'Sim', 'adam-comunidade' ) : '—'; ?></td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $entry->updated_at . ' UTC' ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</form>
	<?php if ( $result['pages'] > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php echo wp_kses_post( paginate_links( array( 'total' => $result['pages'], 'current' => $page ) ) ); ?>
		</div></div>
	<?php endif; ?>
</div>
