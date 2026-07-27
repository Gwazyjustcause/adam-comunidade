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
);
?>
<div class="wrap adam-comunidade-admin adam-managers-admin">
	<header class="adam-page-header">
		<div><h1><?php esc_html_e( 'Gestores', 'adam-comunidade' ); ?></h1><p><?php esc_html_e( 'Gerir contas, convites e organizações atribuídas aos Gestores da Comunidade.', 'adam-comunidade' ); ?></p></div>
	</header>
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
		<label><span><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></span><select name="status"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( $adam_status_labels as $adam_key => $adam_label ) : ?><option value="<?php echo esc_attr( $adam_key ); ?>" <?php selected( $status, $adam_key ); ?>><?php echo esc_html( $adam_label ); ?></option><?php endforeach; ?></select></label>
		<button class="button button-primary" type="submit"><?php esc_html_e( 'Aplicar filtros', 'adam-comunidade' ); ?></button>
	</form>

	<section class="adam-card">
		<h2><?php esc_html_e( 'Atribuir Gestor', 'adam-comunidade' ); ?></h2>
		<p><?php esc_html_e( 'Selecione uma conta existente ou introduza um novo e-mail para criar e enviar um convite.', 'adam-comunidade' ); ?></p>
		<form class="adam-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_manager_admin_assign">
			<?php wp_nonce_field( 'adam_manager_admin_assign' ); ?>
			<label><span><?php esc_html_e( 'Gestor existente', 'adam-comunidade' ); ?></span><select name="manager_id"><option value="0"><?php esc_html_e( 'Criar novo convite', 'adam-comunidade' ); ?></option><?php foreach ( $managers as $adam_manager ) : ?><option value="<?php echo esc_attr( (string) $adam_manager->id ); ?>"><?php echo esc_html( (string) $adam_manager->email ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Novo e-mail', 'adam-comunidade' ); ?></span><input type="email" name="manager_email" placeholder="<?php esc_attr_e( 'gestor@organizacao.pt', 'adam-comunidade' ); ?>"></label>
			<label><span><?php esc_html_e( 'Organização', 'adam-comunidade' ); ?></span><select name="entity" required><option value=""><?php esc_html_e( 'Selecionar organização', 'adam-comunidade' ); ?></option>
				<optgroup label="<?php esc_attr_e( 'Equipas', 'adam-comunidade' ); ?>"><?php foreach ( $team_choices as $adam_choice ) : ?><option value="team:<?php echo esc_attr( (string) $adam_choice->id ); ?>"><?php echo esc_html( (string) $adam_choice->name ); ?></option><?php endforeach; ?></optgroup>
				<optgroup label="<?php esc_attr_e( 'Campos', 'adam-comunidade' ); ?>"><?php foreach ( $field_choices as $adam_choice ) : ?><option value="field:<?php echo esc_attr( (string) $adam_choice->id ); ?>"><?php echo esc_html( (string) $adam_choice->name ); ?></option><?php endforeach; ?></optgroup>
				<optgroup label="<?php esc_attr_e( 'Parceiros', 'adam-comunidade' ); ?>"><?php foreach ( $partner_choices as $adam_choice ) : ?><option value="partner:<?php echo esc_attr( (string) $adam_choice->id ); ?>"><?php echo esc_html( (string) $adam_choice->name ); ?></option><?php endforeach; ?></optgroup>
				<optgroup label="<?php esc_attr_e( 'Instituições', 'adam-comunidade' ); ?>"><?php foreach ( $institution_choices as $adam_choice ) : ?><option value="institution:<?php echo esc_attr( (string) $adam_choice->id ); ?>"><?php echo esc_html( (string) $adam_choice->name ); ?></option><?php endforeach; ?></optgroup>
			</select></label>
			<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Atribuir', 'adam-comunidade' ); ?></button></p>
		</form>
	</section>

	<?php if ( ! $managers ) : ?><div class="adam-card adam-empty-state"><h2><?php esc_html_e( 'Nenhum Gestor encontrado', 'adam-comunidade' ); ?></h2></div><?php endif; ?>
	<?php foreach ( $managers as $adam_manager ) : $adam_manager_assignments = $assignments[ (int) $adam_manager->id ] ?? array(); ?>
		<article class="adam-card adam-manager-admin-card">
			<header class="adam-card__header">
				<div><span class="adam-status-pill"><?php echo esc_html( $adam_status_labels[ $adam_manager->status ] ?? $adam_manager->status ); ?></span><h2><?php echo esc_html( (string) $adam_manager->email ); ?></h2></div>
				<dl>
					<div><dt><?php esc_html_e( 'Criado em', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ), (string) $adam_manager->created_at ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Último acesso', 'adam-comunidade' ); ?></dt><dd><?php echo $adam_manager->last_login_at ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $adam_manager->last_login_at ) ) : esc_html__( 'Ainda não iniciou sessão', 'adam-comunidade' ); ?></dd></div>
				</dl>
			</header>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="adam_manager_admin_status"><input type="hidden" name="manager_id" value="<?php echo esc_attr( (string) $adam_manager->id ); ?>">
				<?php wp_nonce_field( 'adam_manager_admin_status_' . $adam_manager->id ); ?>
				<input type="hidden" name="manager_status" value="<?php echo esc_attr( 'disabled' === $adam_manager->status ? 'active' : 'disabled' ); ?>">
				<button class="button" type="submit"><?php echo esc_html( 'disabled' === $adam_manager->status ? __( 'Reativar acesso', 'adam-comunidade' ) : __( 'Desativar acesso', 'adam-comunidade' ) ); ?></button>
			</form>
			<h3><?php esc_html_e( 'Organizações atribuídas', 'adam-comunidade' ); ?></h3>
			<?php if ( ! $adam_manager_assignments ) : ?><p><?php esc_html_e( 'Sem organizações atribuídas.', 'adam-comunidade' ); ?></p><?php endif; ?>
			<?php foreach ( $adam_manager_assignments as $adam_assignment ) :
				$adam_invitation_status = 'active' === $adam_manager->status
					? __( 'Conta ativa', 'adam-comunidade' )
					: ( $adam_assignment->invitation_used_at
						? __( 'Convite utilizado', 'adam-comunidade' )
						: ( $adam_assignment->invitation_expires_at && strtotime( $adam_assignment->invitation_expires_at . ' UTC' ) < time() ? __( 'Convite expirado', 'adam-comunidade' ) : __( 'Convite pendente', 'adam-comunidade' ) ) );
				?>
				<div class="adam-manager-assignment">
					<div><strong><?php echo esc_html( (string) ( $adam_assignment->record->name ?? '#' . $adam_assignment->entity_id ) ); ?></strong><small><?php echo esc_html( $adam_invitation_status ); ?></small></div>
					<?php if ( 'disabled' !== $adam_manager->status ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="adam_resend_manager_invitation"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_resend_manager_invitation_' . $adam_assignment->id ); ?><button class="button" type="submit"><?php esc_html_e( 'Reenviar convite', 'adam-comunidade' ); ?></button></form><?php else : ?><span><?php esc_html_e( 'Reative a conta para enviar convites.', 'adam-comunidade' ); ?></span><?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="adam_manager_admin_transfer"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_manager_transfer_' . $adam_assignment->id ); ?><select name="target_manager_id" required><option value=""><?php esc_html_e( 'Transferir para…', 'adam-comunidade' ); ?></option><?php foreach ( $managers as $adam_target ) : if ( (int) $adam_target->id === (int) $adam_manager->id ) { continue; } ?><option value="<?php echo esc_attr( (string) $adam_target->id ); ?>"><?php echo esc_html( (string) $adam_target->email ); ?></option><?php endforeach; ?></select><button class="button" type="submit"><?php esc_html_e( 'Transferir', 'adam-comunidade' ); ?></button></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="adam_manager_admin_remove_assignment"><input type="hidden" name="assignment_id" value="<?php echo esc_attr( (string) $adam_assignment->id ); ?>"><?php wp_nonce_field( 'adam_manager_remove_assignment_' . $adam_assignment->id ); ?><button class="button button-link-delete" type="submit"><?php esc_html_e( 'Remover acesso', 'adam-comunidade' ); ?></button></form>
				</div>
			<?php endforeach; ?>
		</article>
	<?php endforeach; ?>
</div>
