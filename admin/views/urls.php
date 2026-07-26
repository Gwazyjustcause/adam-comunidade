<?php
/**
 * Managed public URL settings.
 *
 * @var array<string,array<string,mixed>> $pages           Managed page rows.
 * @var \WP_Post[]                       $available_pages Available WordPress pages.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap adam-comunidade-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Endereços da Comunidade', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'O encaminhamento é controlado pelas páginas WordPress abaixo. Os títulos e o conteúdo são editados no editor normal.', 'adam-comunidade' ); ?></p>
		</div>
	</header>
	<form class="adam-card" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="adam_comunidade_save_urls">
		<?php wp_nonce_field( 'adam_comunidade_save_urls' ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Módulo', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Página atual', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Endereço atual', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Pré-visualizar', 'adam-comunidade' ); ?></th>
					<th><?php esc_html_e( 'Página', 'adam-comunidade' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $module => $row ) : ?>
					<?php $page = $row['page']; ?>
					<tr>
						<td><strong><?php echo esc_html( $row['definition']['label'] ); ?></strong></td>
						<td>
							<select name="page_ids[<?php echo esc_attr( $module ); ?>]">
								<option value="0"><?php esc_html_e( 'Selecionar uma página', 'adam-comunidade' ); ?></option>
								<?php foreach ( $available_pages as $available_page ) : ?>
									<option value="<?php echo esc_attr( $available_page->ID ); ?>" <?php selected( $row['id'], $available_page->ID ); ?>><?php echo esc_html( $available_page->post_title ?: sprintf( __( 'Página #%d', 'adam-comunidade' ), $available_page->ID ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><?php if ( $row['url'] ) : ?><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['url'] ); ?></a><?php else : ?>—<?php endif; ?></td>
						<td><input type="text" name="slugs[<?php echo esc_attr( $module ); ?>]" value="<?php echo esc_attr( $page ? $page->post_name : '' ); ?>" <?php disabled( ! $row['url'] ); ?>></td>
						<td><?php if ( $row['url'] ) : ?><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '/' . $page->post_name ); ?></a><?php else : ?>—<?php endif; ?></td>
						<td>
							<?php if ( $page ) : ?>
								<a class="button" href="<?php echo esc_url( get_edit_post_link( $row['id'], 'raw' ) ); ?>"><?php esc_html_e( 'Editar página', 'adam-comunidade' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( __( 'Guardar endereços', 'adam-comunidade' ) ); ?>
	</form>
</div>
