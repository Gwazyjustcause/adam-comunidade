<?php
/**
 * Community Manager administration.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

$adam_status_labels = array(
	'invited'  => __( 'Convite pendente', 'adam-comunidade' ),
	'active'   => __( 'Ativo', 'adam-comunidade' ),
	'disabled' => __( 'Desativado', 'adam-comunidade' ),
	'deleted'  => __( 'Eliminado', 'adam-comunidade' ),
);
$adam_notices = array(
	'assignment-saved'          => array( 'success', __( 'As organizações foram atribuídas com sucesso.', 'adam-comunidade' ) ),
	'assignment-partial'        => array( 'warning', __( 'Algumas organizações foram atribuídas, mas pelo menos uma atribuição falhou.', 'adam-comunidade' ) ),
	'assignment-invalid'        => array( 'error', __( 'Selecione um Gestor ou indique um e-mail válido e escolha pelo menos uma organização.', 'adam-comunidade' ) ),
	'assignment-failed'         => array( 'error', __( 'Não foi possível concluir a atribuição.', 'adam-comunidade' ) ),
	'assignment-transferred'    => array( 'success', __( 'A organização foi transferida.', 'adam-comunidade' ) ),
	'assignment-removed'        => array( 'success', __( 'O acesso à organização foi removido.', 'adam-comunidade' ) ),
	'status-updated'            => array( 'success', __( 'O estado da conta foi atualizado.', 'adam-comunidade' ) ),
	'invitation-sent'           => array( 'success', __( 'O convite foi enviado novamente.', 'adam-comunidade' ) ),
	'invitation-send-failed'    => array( 'error', __( 'Não foi possível enviar novamente o convite.', 'adam-comunidade' ) ),
	'invitation-cancelled'      => array( 'success', __( 'O convite foi cancelado. Pode criar um novo convite a qualquer momento.', 'adam-comunidade' ) ),
	'invitation-cancel-failed'  => array( 'error', __( 'Não foi possível cancelar o convite.', 'adam-comunidade' ) ),
	'password-reset-sent'       => array( 'success', __( 'As instruções para definir uma nova palavra-passe foram enviadas.', 'adam-comunidade' ) ),
	'password-reset-failed'     => array( 'error', __( 'Não foi possível enviar a recuperação da palavra-passe.', 'adam-comunidade' ) ),
	'manager-deleted'           => array( 'success', __( 'O Gestor foi eliminado. As organizações foram preservadas.', 'adam-comunidade' ) ),
	'manager-delete-failed'     => array( 'error', __( 'Não foi possível eliminar o Gestor. Nenhuma alteração foi aplicada.', 'adam-comunidade' ) ),
);
$adam_notice_key = sanitize_key( wp_unslash( $_GET['gestor_estado'] ?? '' ) );
$adam_notice     = $adam_notices[ $adam_notice_key ] ?? null;
$adam_all_entities = array(
	'team'        => array( 'label' => __( 'Equipas', 'adam-comunidade' ), 'items' => $team_choices ),
	'field'       => array( 'label' => __( 'Campos', 'adam-comunidade' ), 'items' => $field_choices ),
	'partner'     => array( 'label' => __( 'Parceiros', 'adam-comunidade' ), 'items' => $partner_choices ),
	'institution' => array( 'label' => __( 'Instituições', 'adam-comunidade' ), 'items' => $institution_choices ),
);
?>
<div class="wrap adam-comunidade-admin adam-managers-admin">
	<header class="adam-page-header">
		<div>
			<h1><?php esc_html_e( 'Gestores', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Gerir contas, convites e organizações atribuídas aos Gestores da Comunidade.', 'adam-comunidade' ); ?></p>
		</div>
	</header>

	<?php if ( $adam_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $adam_notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $adam_notice[1] ); ?></p></div>
	<?php endif; ?>

	<section class="adam-manager-stats" aria-label="<?php esc_attr_e( 'Resumo dos Gestores', 'adam-comunidade' ); ?>">
		<div class="adam-card"><strong><?php echo esc_html( (string) array_sum( $status_counts ) ); ?></strong><span><?php esc_html_e( 'Gestores', 'adam-comunidade' ); ?></span></div>
		<div class="adam-card"><strong><?php echo esc_html( (string) ( $status_counts['active'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Ativos', 'adam-comunidade' ); ?></span></div>
		<div class="adam-card"><strong><?php echo esc_html( (string) ( $status_counts['invited'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Convites pendentes', 'adam-comunidade' ); ?></span></div>
		<div class="adam-card"><strong><?php echo esc_html( (string) ( $status_counts['disabled'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Desativados', 'adam-comunidade' ); ?></span></div>
	</section>

	<?php if ( $email_status ) : ?>
		<div class="notice inline <?php echo 'failed' === ( $email_status['status'] ?? '' ) ? 'notice-error' : 'notice-success'; ?>">
			<p><strong><?php esc_html_e( 'Último estado do correio:', 'adam-comunidade' ); ?></strong>
				<?php echo esc_html( 'failed' === ( $email_status['status'] ?? '' ) ? __( 'Falhou', 'adam-comunidade' ) : __( 'Aceite pelo serviço de correio', 'adam-comunidade' ) ); ?>
				<?php if ( $email_type_label ) : ?> · <?php echo esc_html( $email_type_label ); ?><?php endif; ?>
				<?php if ( ! empty( $email_status['timestamp'] ) ) : ?> · <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $email_status['timestamp'] ) ); ?><?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<form class="adam-card adam-admin-filters" method="get">
		<input type="hidden" name="page" value="adam-comunidade-managers">
		<label><span><?php esc_html_e( 'Pesquisar', 'adam-comunidade' ); ?></span><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Pesquisar por e-mail', 'adam-comunidade' ); ?>"></label>
		<label><span><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></span><select name="status">
			<option value=""><?php esc_html_e( 'Todos os Gestores', 'adam-comunidade' ); ?></option>
			<?php foreach ( $adam_status_labels as $adam_key => $adam_label ) : ?><option value="<?php echo esc_attr( $adam_key ); ?>" <?php selected( $status, $adam_key ); ?>><?php echo esc_html( $adam_label ); ?></option><?php endforeach; ?>
		</select></label>
		<label><span><?php esc_html_e( 'Ordenar por', 'adam-comunidade' ); ?></span><select name="orderby">
			<option value="created_desc" <?php selected( $sort, 'created_desc' ); ?>><?php esc_html_e( 'Criação — mais recentes', 'adam-comunidade' ); ?></option>
			<option value="created_asc" <?php selected( $sort, 'created_asc' ); ?>><?php esc_html_e( 'Criação — mais antigos', 'adam-comunidade' ); ?></option>
			<option value="email_asc" <?php selected( $sort, 'email_asc' ); ?>><?php esc_html_e( 'E-mail — A a Z', 'adam-comunidade' ); ?></option>
			<option value="email_desc" <?php selected( $sort, 'email_desc' ); ?>><?php esc_html_e( 'E-mail — Z a A', 'adam-comunidade' ); ?></option>
			<option value="last_login_desc" <?php selected( $sort, 'last_login_desc' ); ?>><?php esc_html_e( 'Último acesso', 'adam-comunidade' ); ?></option>
		</select></label>
		<button class="button button-primary" type="submit"><?php esc_html_e( 'Aplicar filtros', 'adam-comunidade' ); ?></button>
		<?php if ( $search || $status || 'created_desc' !== $sort ) : ?>
			<a class="button" href="<?php echo esc_url( \ADAM\Comunidade\Admin\Router::page_url( 'managers' ) ); ?>"><?php esc_html_e( 'Limpar filtros', 'adam-comunidade' ); ?></a>
		<?php endif; ?>
	</form>

	<section class="adam-card adam-manager-create">
		<div>
			<h2><?php esc_html_e( 'Criar convite ou atribuir organizações', 'adam-comunidade' ); ?></h2>
			<p><?php esc_html_e( 'Pode atribuir várias organizações de uma só vez. Um novo Gestor recebe um único convite para criar a conta.', 'adam-comunidade' ); ?></p>
		</div>
		<form class="adam-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_manager_admin_assign">
			<?php wp_nonce_field( 'adam_manager_admin_assign' ); ?>
			<label><span><?php esc_html_e( 'Gestor existente', 'adam-comunidade' ); ?></span><select name="manager_id">
				<option value="0"><?php esc_html_e( 'Criar novo convite', 'adam-comunidade' ); ?></option>
				<?php foreach ( $manager_choices as $adam_manager ) : ?><option value="<?php echo esc_attr( (string) $adam_manager->id ); ?>"><?php echo esc_html( (string) $adam_manager->email ); ?> — <?php echo esc_html( $adam_status_labels[ $adam_manager->status ] ?? $adam_manager->status ); ?></option><?php endforeach; ?>
			</select></label>
			<label><span><?php esc_html_e( 'E-mail do novo Gestor', 'adam-comunidade' ); ?></span><input type="email" name="manager_email" placeholder="<?php esc_attr_e( 'gestor@organizacao.pt', 'adam-comunidade' ); ?>"><small><?php esc_html_e( 'Preencha apenas quando criar um novo convite.', 'adam-comunidade' ); ?></small></label>
			<div class="adam-manager-entity-picker" data-adam-entity-picker>
				<div class="adam-manager-entity-picker__heading">
					<label><span><?php esc_html_e( 'Organizações', 'adam-comunidade' ); ?></span><input type="search" data-adam-entity-search placeholder="<?php esc_attr_e( 'Filtrar organizações…', 'adam-comunidade' ); ?>" aria-label="<?php esc_attr_e( 'Filtrar a lista de organizações', 'adam-comunidade' ); ?>"></label>
					<strong data-adam-entity-count aria-live="polite"><?php esc_html_e( 'Nenhuma organização selecionada', 'adam-comunidade' ); ?></strong>
				</div>
				<div class="adam-manager-entity-options">
					<?php foreach ( $adam_all_entities as $adam_type => $adam_group ) : ?>
						<?php if ( ! empty( $adam_group['items'] ) ) : ?>
							<fieldset data-adam-entity-group>
								<legend><?php echo esc_html( $adam_group['label'] ); ?></legend>
								<?php foreach ( $adam_group['items'] as $adam_choice ) : ?>
									<label data-adam-entity-option><input type="checkbox" name="entities[]" value="<?php echo esc_attr( $adam_type . ':' . $adam_choice->id ); ?>"> <span><?php echo esc_html( (string) $adam_choice->name ); ?></span></label>
								<?php endforeach; ?>
							</fieldset>
						<?php endif; ?>
					<?php endforeach; ?>
					<p class="adam-manager-entity-empty" data-adam-entity-empty hidden><?php esc_html_e( 'Nenhuma organização corresponde à pesquisa.', 'adam-comunidade' ); ?></p>
				</div>
				<small><?php esc_html_e( 'Selecione todas as organizações que este Gestor poderá atualizar.', 'adam-comunidade' ); ?></small>
			</div>
			<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Guardar atribuições', 'adam-comunidade' ); ?></button></p>
		</form>
	</section>

	<?php if ( ! $managers ) : ?>
		<div class="adam-card adam-empty-state"><h2><?php esc_html_e( 'Nenhum Gestor encontrado', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Altere os filtros ou crie o primeiro convite.', 'adam-comunidade' ); ?></p></div>
	<?php else : ?>
		<p class="adam-manager-results" role="status"><?php echo esc_html( sprintf( _n( '%d Gestor encontrado', '%d Gestores encontrados', $total_managers, 'adam-comunidade' ), $total_managers ) ); ?></p>
	<?php endif; ?>

	<?php foreach ( $managers as $adam_manager ) :
		$adam_manager_assignments = $assignments[ (int) $adam_manager->id ] ?? array();
		$adam_assignment_count    = count( $adam_manager_assignments );
		?>
		<article class="adam-card adam-manager-admin-card">
			<header class="adam-card__header">
				<div><span class="adam-status-pill adam-status-pill--<?php echo esc_attr( (string) $adam_manager->status ); ?>"><?php echo esc_html( $adam_status_labels[ $adam_manager->status ] ?? $adam_manager->status ); ?></span><h2><?php echo esc_html( (string) $adam_manager->email ); ?></h2><p><?php echo esc_html( sprintf( _n( '%d organização atribuída', '%d organizações atribuídas', $adam_assignment_count, 'adam-comunidade' ), $adam_assignment_count ) ); ?></p></div>
				<dl>
					<div><dt><?php esc_html_e( 'Criado em', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), (string) $adam_manager->created_at ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Último acesso', 'adam-comunidade' ); ?></dt><dd><?php echo $adam_manager->last_login_at ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $adam_manager->last_login_at ) ) : esc_html__( 'Ainda não iniciou sessão', 'adam-comunidade' ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Última atividade', 'adam-comunidade' ); ?></dt><dd><?php echo $adam_manager->last_activity_at ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $adam_manager->last_activity_at ) ) : esc_html__( 'Sem atividade registada', 'adam-comunidade' ); ?></dd></div>
				</dl>
			</header>

			<?php if ( 'deleted' !== $adam_manager->status ) : ?>
				<div class="adam-manager-account-actions" aria-label="<?php esc_attr_e( 'Ações da conta', 'adam-comunidade' ); ?>">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="adam_manager_admin_status"><input type="hidden" name="manager_id" value="<?php echo esc_attr( (string) $adam_manager->id ); ?>">
						<?php wp_nonce_field( 'adam_manager_admin_status_' . $adam_manager->id ); ?>
						<input type="hidden" name="manager_status" value="<?php echo esc_attr( 'disabled' === $adam_manager->status ? 'active' : 'disabled' ); ?>">
						<button class="button" type="submit"><?php echo esc_html( 'disabled' === $adam_manager->status ? __( 'Reativar acesso', 'adam-comunidade' ) : __( 'Desativar acesso', 'adam-comunidade' ) ); ?></button>
					</form>
					<?php if ( 'active' === $adam_manager->status ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-adam-confirm="<?php esc_attr_e( 'Enviar instruções para definir uma nova palavra-passe?', 'adam-comunidade' ); ?>"><input type="hidden" name="action" value="adam_manager_admin_reset_password"><input type="hidden" name="manager_id" value="<?php echo esc_attr( (string) $adam_manager->id ); ?>"><?php wp_nonce_field( 'adam_manager_reset_password_' . $adam_manager->id ); ?><button class="button" type="submit"><?php esc_html_e( 'Repor palavra-passe', 'adam-comunidade' ); ?></button></form><?php endif; ?>
				</div>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Organizações atribuídas', 'adam-comunidade' ); ?></h3>
			<?php if ( ! $adam_manager_assignments ) : ?><p class="adam-manager-empty"><?php esc_html_e( 'Este Gestor não tem organizações atribuídas.', 'adam-comunidade' ); ?></p><?php endif; ?>
			<div class="adam-manager-assignment-list">
			<?php foreach ( $adam_manager_assignments as $adam_assignment ) :
				$adam_has_open_invitation = ! $adam_assignment->invitation_used_at && $adam_assignment->invitation_expires_at && strtotime( $adam_assignment->invitation_expires_at . ' UTC' ) >= time();
				$adam_invitation_status = 'active' === $adam_manager->status
					? __( 'Conta ativa', 'adam-comunidade' )
					: ( $adam_has_open_invitation
						? __( 'Convite pendente', 'adam-comunidade' )
						: ( $adam_assignment->invitation_expires_at ? __( 'Convite expirado ou cancelado', 'adam-comunidade' ) : __( 'Sem convite ativo', 'adam-comunidade' ) ) );
				?>
				<div class="adam-manager-assignment">
					<div><strong><?php echo esc_html( (string) ( $adam_assignment->record->name ?? '#' . $adam_assignment->entity_id ) ); ?></strong><small><?php echo esc_html( $adam_invitation_status ); ?> · <?php echo esc_html( sprintf( _n( '%d Gestor atribuído', '%d Gestores atribuídos', (int) $adam_assignment->assigned_manager_count, 'adam-comunidade' ), (int) $adam_assignment->assigned_manager_count ) ); ?></small><?php if ( $adam_has_open_invitation ) : ?><small><?php echo esc_html( sprintf( __( 'Expira em %s', 'adam-comunidade' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $adam_assignment->invitation_expires_at ) ) ); ?></small><?php endif; ?></div>
					<?php if ( 'invited' === $adam_manager->status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="adam_resend_manager_invitation"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_resend_manager_invitation_' . $adam_assignment->id ); ?><button class="button" type="submit"><?php esc_html_e( 'Reenviar convite', 'adam-comunidade' ); ?></button></form>
						<?php if ( $adam_has_open_invitation ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-adam-confirm="<?php esc_attr_e( 'Cancelar este convite? O endereço recebido deixará de funcionar.', 'adam-comunidade' ); ?>"><input type="hidden" name="action" value="adam_manager_admin_cancel_invitation"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_manager_cancel_invitation_' . $adam_assignment->id ); ?><button class="button" type="submit"><?php esc_html_e( 'Cancelar convite', 'adam-comunidade' ); ?></button></form><?php endif; ?>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="adam_manager_admin_transfer"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_manager_transfer_' . $adam_assignment->id ); ?><label class="screen-reader-text" for="adam-transfer-<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php esc_html_e( 'Gestor de destino', 'adam-comunidade' ); ?></label><select id="adam-transfer-<?php echo esc_attr( (string) $adam_assignment->id ); ?>" name="target_manager_id" required><option value=""><?php esc_html_e( 'Transferir para…', 'adam-comunidade' ); ?></option><?php foreach ( $manager_choices as $adam_target ) : if ( (int) $adam_target->id === (int) $adam_manager->id ) { continue; } ?><option value="<?php echo esc_attr( (string) $adam_target->id ); ?>"><?php echo esc_html( (string) $adam_target->email ); ?></option><?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e( 'Transferir', 'adam-comunidade' ); ?></button></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-adam-confirm="<?php esc_attr_e( 'Remover o acesso deste Gestor à organização?', 'adam-comunidade' ); ?>"><input type="hidden" name="action" value="adam_manager_admin_remove_assignment"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_manager_remove_assignment_' . $adam_assignment->id ); ?><button class="button button-link-delete" type="submit"><?php esc_html_e( 'Remover acesso', 'adam-comunidade' ); ?></button></form>
				</div>
			<?php endforeach; ?>
			</div>

			<?php if ( 'deleted' !== $adam_manager->status ) : ?>
				<details class="adam-manager-delete">
					<summary><?php esc_html_e( 'Eliminar Gestor', 'adam-comunidade' ); ?></summary>
					<p><?php esc_html_e( 'A conta e o acesso serão removidos. Os Campos, Equipas, Parceiros e Instituições nunca serão eliminados.', 'adam-comunidade' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-adam-confirm="<?php esc_attr_e( 'Eliminar definitivamente o acesso deste Gestor? Esta ação termina todas as sessões e invalida os convites.', 'adam-comunidade' ); ?>">
						<input type="hidden" name="action" value="adam_manager_admin_delete"><input type="hidden" name="manager_id" value="<?php echo esc_attr( (string) $adam_manager->id ); ?>">
						<?php wp_nonce_field( 'adam_manager_delete_' . $adam_manager->id ); ?>
						<label><span><?php esc_html_e( 'Organizações atribuídas', 'adam-comunidade' ); ?></span><select name="assignment_action" required><option value="release"><?php esc_html_e( 'Deixar sem este Gestor', 'adam-comunidade' ); ?></option><option value="transfer"><?php esc_html_e( 'Transferir para outro Gestor', 'adam-comunidade' ); ?></option></select></label>
						<label><span><?php esc_html_e( 'Gestor de destino, se transferir', 'adam-comunidade' ); ?></span><select name="target_manager_id"><option value="0"><?php esc_html_e( 'Selecionar Gestor', 'adam-comunidade' ); ?></option><?php foreach ( $manager_choices as $adam_target ) : if ( (int) $adam_target->id === (int) $adam_manager->id ) { continue; } ?><option value="<?php echo esc_attr( (string) $adam_target->id ); ?>"><?php echo esc_html( (string) $adam_target->email ); ?></option><?php endforeach; ?></select></label>
						<div class="adam-manager-delete-actions">
							<button class="button" type="button" data-adam-close-details><?php esc_html_e( 'Cancelar', 'adam-comunidade' ); ?></button>
							<button class="button button-link-delete" type="submit"><?php esc_html_e( 'Eliminar Gestor', 'adam-comunidade' ); ?></button>
						</div>
					</form>
				</details>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>

	<?php if ( $total_pages > 1 ) : ?>
		<nav class="adam-manager-pagination" aria-label="<?php esc_attr_e( 'Paginação dos Gestores', 'adam-comunidade' ); ?>">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $total_pages,
						'prev_text' => __( 'Anterior', 'adam-comunidade' ),
						'next_text' => __( 'Seguinte', 'adam-comunidade' ),
					)
				)
			);
			?>
		</nav>
	<?php endif; ?>
</div>
