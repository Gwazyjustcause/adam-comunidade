<?php
/**
 * Manager revision moderation in the existing Approvals screen.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Experience\Moderation_Component;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Helpers;
use ADAM\Comunidade\Teams\Repository as Team_Repository;

/**
 * Adds manager work to the existing approval workflow.
 */
final class Admin {
	public function __construct( private Service $service ) {}

	public function register(): void {
		add_action( 'adam_comunidade_moderation_after_submissions', array( $this, 'render' ) );
		add_filter( 'adam_comunidade_moderation_pending_count', array( $this, 'pending_count' ) );
		Admin_Router::register_page(
			'managers',
			array(
				'title'      => __( 'Gestores', 'adam-comunidade' ),
				'menu_title' => __( 'Gestores', 'adam-comunidade' ),
				'controller' => $this,
				'method'     => 'index',
			)
		);
		add_action( 'admin_post_adam_moderate_manager_revision', array( $this, 'moderate' ) );
		add_action( 'admin_post_adam_resend_manager_invitation', array( $this, 'resend' ) );
		add_action( 'admin_post_adam_manager_admin_status', array( $this, 'status' ) );
		add_action( 'admin_post_adam_manager_admin_remove_assignment', array( $this, 'remove_assignment' ) );
		add_action( 'admin_post_adam_manager_admin_assign', array( $this, 'assign' ) );
		add_action( 'admin_post_adam_manager_admin_transfer', array( $this, 'transfer' ) );
		add_action( 'admin_post_adam_manager_admin_cancel_invitation', array( $this, 'cancel_invitation' ) );
		add_action( 'admin_post_adam_manager_admin_reset_password', array( $this, 'reset_password' ) );
		add_action( 'admin_post_adam_manager_admin_delete', array( $this, 'delete' ) );
	}

	public function pending_count( int $count ): int {
		global $wpdb;
		return $count + (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::revisions_table() . " WHERE status IN ('pending','needs_info')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function index(): void {
		global $wpdb;
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		$status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
		$sort   = sanitize_key( wp_unslash( $_GET['orderby'] ?? 'created_desc' ) );
		$paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 20;
		$orders = array(
			'created_desc'   => 'm.created_at DESC',
			'created_asc'    => 'm.created_at ASC',
			'email_asc'      => 'm.email ASC',
			'email_desc'     => 'm.email DESC',
			'last_login_desc'=> 'm.last_login_at DESC, m.created_at DESC',
		);
		$order_sql = $orders[ $sort ] ?? $orders['created_desc'];
		$where  = array( '1=1' );
		$args   = array();
		if ( $search ) {
			$where[] = 'm.email LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( in_array( $status, array( 'invited', 'active', 'disabled', 'deleted' ), true ) ) {
			$where[] = 'm.status = %s';
			$args[]  = $status;
		} else {
			$where[] = 'm.status <> %s';
			$args[]  = 'deleted';
		}
		$count_sql = 'SELECT COUNT(*) FROM ' . Schema::managers_table() . ' m WHERE ' . implode( ' AND ', $where );
		$total_managers = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = 'SELECT m.* FROM ' . Schema::managers_table()
			. ' m WHERE ' . implode( ' AND ', $where ) . " ORDER BY {$order_sql} LIMIT %d OFFSET %d";
		$managers = $wpdb->get_results( $wpdb->prepare( $sql, ...array_merge( $args, array( $per_page, ( $paged - 1 ) * $per_page ) ) ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$assignments = array();
		$manager_ids = array_map( static fn( object $manager ): int => (int) $manager->id, $managers );
		$rows = array();
		if ( $manager_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $manager_ids ), '%d' ) );
			$assignment_sql = 'SELECT a.*,i.used_at AS invitation_used_at,i.expires_at AS invitation_expires_at,'
				. '(SELECT COUNT(*) FROM ' . Schema::assignments_table() . " ax WHERE ax.entity_type=a.entity_type AND ax.entity_id=a.entity_id AND ax.status='active') AS assigned_manager_count FROM " . Schema::assignments_table() . ' a'
				. ' LEFT JOIN ' . Schema::invitations_table() . " i ON i.id=(SELECT i2.id FROM " . Schema::invitations_table() . " i2 WHERE i2.manager_id=a.manager_id AND i2.purpose='invitation' AND i2.entity_type=a.entity_type AND i2.entity_id=a.entity_id ORDER BY i2.created_at DESC LIMIT 1)"
				. " WHERE a.manager_id IN ({$placeholders}) AND a.status=%s ORDER BY a.created_at ASC";
			$rows = $wpdb->get_results( $wpdb->prepare( $assignment_sql, ...array_merge( $manager_ids, array( 'active' ) ) ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$record_names = $this->service->record_names( $rows );
		foreach ( $rows as $row ) {
			$key = (string) $row->entity_type . ':' . (int) $row->entity_id;
			$row->record = isset( $record_names[ $key ] ) ? (object) array( 'name' => $record_names[ $key ] ) : null;
			$assignments[ (int) $row->manager_id ][] = $row;
		}
		$team_choices  = ( new Team_Repository() )->choices( '' );
		$field_choices = ( new Field_Repository() )->choices( '' );
		$directory     = new Directory_Repository();
		$partner_choices = $directory->choices( 'partner' );
		$institution_choices = $directory->choices( 'institution' );
		$manager_choices = $wpdb->get_results(
			"SELECT id,email,status FROM " . Schema::managers_table() . " WHERE status IN ('invited','active') ORDER BY email ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		) ?: array();
		$status_counts = array( 'invited' => 0, 'active' => 0, 'disabled' => 0 );
		$count_rows = $wpdb->get_results(
			"SELECT status,COUNT(*) AS total FROM " . Schema::managers_table() . " WHERE status<>'deleted' GROUP BY status" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		) ?: array();
		foreach ( $count_rows as $count_row ) {
			$status_counts[ (string) $count_row->status ] = (int) $count_row->total;
		}
		$total_pages = max( 1, (int) ceil( $total_managers / $per_page ) );
		$email_status = get_option( 'adam_comunidade_email_last_status', array() );
		$email_status = is_array( $email_status ) ? $email_status : array();
		$email_templates = ( new \ADAM\Comunidade\Experience\Email_Service() )->templates();
		$email_type_label = (string) ( $email_templates[ $email_status['email_type'] ?? '' ]['label'] ?? '' );
		require Helpers::path( 'admin/views/managers.php' );
	}

	public function render(): void {
		global $wpdb;
		$revisions = $wpdb->get_results( 'SELECT r.*,m.email FROM ' . Schema::revisions_table() . ' r INNER JOIN ' . Schema::managers_table() . " m ON m.id=r.manager_id WHERE r.status IN ('pending','needs_info') ORDER BY r.submitted_at ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$history = $this->service->revision_history( 50 );
		$assignments = $wpdb->get_results( 'SELECT a.*,m.email,m.status AS manager_status FROM ' . Schema::assignments_table() . ' a INNER JOIN ' . Schema::managers_table() . " m ON m.id=a.manager_id WHERE a.status='active' ORDER BY a.created_at DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$revisions    = is_array( $revisions ) ? $revisions : array();
		$assignments  = is_array( $assignments ) ? $assignments : array();
		$record_names = $this->service->record_names( array_merge( $revisions, $history, $assignments ) );
		?>
		<hr>
		<h2><?php esc_html_e( 'Alterações propostas por Gestores', 'adam-comunidade' ); ?></h2>
		<?php if ( ! $revisions ) : ?><p><?php esc_html_e( 'Não existem alterações de Gestores a aguardar revisão.', 'adam-comunidade' ); ?></p><?php endif; ?>
		<div class="adam-manager-revision-list">
			<?php foreach ( $revisions as $revision ) :
				$record = (object) array( 'name' => $record_names[ (string) $revision->entity_type . ':' . (int) $revision->entity_id ] ?? __( 'Registo indisponível', 'adam-comunidade' ) );
				$changes = $this->service->revision_changes( $revision );
				$has_conflict = $this->service->revision_has_conflict( $revision );
				$conflicts = $has_conflict ? $this->service->revision_conflicts( $revision ) : array();
				?>
				<article class="adam-card adam-manager-revision-card">
					<header class="adam-manager-revision-header">
						<div>
							<span class="adam-card__eyebrow"><?php echo esc_html( 'needs_info' === $revision->status ? __( 'A aguardar alterações', 'adam-comunidade' ) : __( 'Revisão de Gestor', 'adam-comunidade' ) ); ?></span>
							<h3><?php echo esc_html( (string) $record->name ); ?></h3>
							<p><?php echo esc_html( (string) $revision->email ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $revision->submitted_at ) ); ?></p>
						</div>
						<span class="adam-status-pill"><?php echo esc_html( sprintf( _n( '%d alteração', '%d alterações', count( $changes ), 'adam-comunidade' ), count( $changes ) ) ); ?></span>
					</header>
					<?php if ( $has_conflict ) : ?>
						<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Atenção:', 'adam-comunidade' ); ?></strong> <?php esc_html_e( 'O registo publicado foi alterado depois desta proposta. Compare cuidadosamente antes de forçar a aprovação.', 'adam-comunidade' ); ?></p></div>
						<div class="adam-revision-conflict-comparison" role="table" aria-label="<?php esc_attr_e( 'Conflitos com alterações publicadas após a proposta', 'adam-comunidade' ); ?>">
							<div class="adam-revision-conflict-comparison__head" role="row"><span role="columnheader"><?php esc_html_e( 'Campo', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Ao enviar', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Publicado agora', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Proposta do Gestor', 'adam-comunidade' ); ?></span></div>
							<?php foreach ( $conflicts as $key => $conflict ) : ?>
								<div class="adam-revision-conflict-row" role="row">
									<div role="cell"><strong><?php echo esc_html( $this->field_label( (string) $key ) ); ?></strong></div>
									<div role="cell" data-label="<?php esc_attr_e( 'Ao enviar', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $conflict['before'], (string) $revision->entity_type ); ?></div>
									<div role="cell" data-label="<?php esc_attr_e( 'Publicado agora', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $conflict['current'], (string) $revision->entity_type ); ?></div>
									<div role="cell" data-label="<?php esc_attr_e( 'Proposta do Gestor', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $conflict['proposed'], (string) $revision->entity_type ); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( 'needs_info' === $revision->status && $revision->admin_note ) : ?><p class="adam-revision-previous-note"><strong><?php esc_html_e( 'Alterações anteriormente pedidas:', 'adam-comunidade' ); ?></strong><br><?php echo nl2br( esc_html( (string) $revision->admin_note ) ); ?></p><?php endif; ?>
					<?php if ( ! $changes ) : ?><p><?php esc_html_e( 'Esta proposta não contém diferenças detetáveis.', 'adam-comunidade' ); ?></p><?php endif; ?>
					<div class="adam-revision-comparison" role="table" aria-label="<?php esc_attr_e( 'Comparação das alterações propostas', 'adam-comunidade' ); ?>">
						<div class="adam-revision-comparison__head" role="row"><span role="columnheader"><?php esc_html_e( 'Campo', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Versão publicada', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Versão proposta', 'adam-comunidade' ); ?></span></div>
						<?php foreach ( $changes as $key => $change ) : ?>
							<div class="adam-revision-change adam-revision-change--<?php echo esc_attr( $change['kind'] ); ?>" role="row">
								<div class="adam-revision-change__label" role="cell"><strong><?php echo esc_html( $this->field_label( (string) $key ) ); ?></strong><small><?php echo esc_html( $this->change_label( (string) $change['kind'] ) ); ?></small></div>
								<div role="cell" data-label="<?php esc_attr_e( 'Versão publicada', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $change['before'], (string) $revision->entity_type ); ?></div>
								<div role="cell" data-label="<?php esc_attr_e( 'Versão proposta', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $change['after'], (string) $revision->entity_type ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
					<?php
					Moderation_Component::render(
						array(
							'context'      => 'manager-revision',
							'identifier'   => (int) $revision->id,
							'class'        => 'adam-revision-actions',
							'nonce_action' => 'adam_moderate_manager_revision_' . $revision->id,
							'hidden'       => array(
								'action'      => 'adam_moderate_manager_revision',
								'revision_id' => (int) $revision->id,
							),
							'approve_label' => __( 'Aprovar alterações', 'adam-comunidade' ),
							'changes_label' => __( 'Pedir alterações', 'adam-comunidade' ),
							'reject_label'  => __( 'Rejeitar alterações', 'adam-comunidade' ),
							'changes_title' => __( 'Pedir alterações ao Gestor', 'adam-comunidade' ),
							'changes_intro' => __( 'Selecione os pontos que o Gestor deve corrigir antes de uma nova análise.', 'adam-comunidade' ),
							'reject_title'  => __( 'Rejeitar alterações', 'adam-comunidade' ),
							'reject_intro'  => __( 'Selecione os motivos pelos quais estas alterações não podem ser aceites.', 'adam-comunidade' ),
							'approve_fields' => static function () use ( $has_conflict ): void {
								if ( $has_conflict ) {
									?>
									<label class="adam-revision-conflict-confirm"><input type="checkbox" name="confirm_conflict" value="1"> <span><?php esc_html_e( 'Revisei o conflito e pretendo substituir a versão publicada.', 'adam-comunidade' ); ?></span></label>
									<?php
								}
							},
						)
					);
					?>
				</article>
			<?php endforeach; ?>
		</div>
		<h2><?php esc_html_e( 'Histórico de moderação', 'adam-comunidade' ); ?></h2>
		<?php $this->render_revision_history( $history, $record_names ); ?>
		<h2><?php esc_html_e( 'Atribuições de Gestor', 'adam-comunidade' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'E-mail', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Registo', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Estado', 'adam-comunidade' ); ?></th><th></th></tr></thead><tbody>
		<?php foreach ( $assignments as $assignment ) : $record = (object) array( 'name' => $record_names[ (string) $assignment->entity_type . ':' . (int) $assignment->entity_id ] ?? '#' . $assignment->entity_id ); ?><tr><td><?php echo esc_html( (string) $assignment->email ); ?></td><td><?php echo esc_html( (string) $record->name ); ?></td><td><?php echo esc_html( 'active' === $assignment->manager_status ? __( 'Ativo', 'adam-comunidade' ) : __( 'Convite pendente', 'adam-comunidade' ) ); ?></td><td><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'adam_resend_manager_invitation', 'assignment_id' => $assignment->id ), admin_url( 'admin-post.php' ) ), 'adam_resend_manager_invitation_' . $assignment->id ) ); ?>"><?php esc_html_e( 'Reenviar convite', 'adam-comunidade' ); ?></a></td></tr><?php endforeach; ?>
		</tbody></table>
		<?php
	}

	public function moderate(): never {
		Admin_Router::authorize();
		$id = absint( $_POST['revision_id'] ?? 0 );
		check_admin_referer( 'adam_moderate_manager_revision_' . $id );
		$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
		$reasons  = isset( $_POST['moderation_reasons'] ) && is_array( $_POST['moderation_reasons'] )
			? array_map( 'strval', wp_unslash( $_POST['moderation_reasons'] ) )
			: array();
		$result = $this->service->moderate_revision(
			$id,
			$decision,
			$reasons,
			get_current_user_id(),
			! empty( $_POST['confirm_conflict'] ),
			sanitize_textarea_field( wp_unslash( $_POST['moderation_custom_reason'] ?? '' ) )
		);
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( Admin_Router::page_url( 'moderation' ) );
		exit;
	}

	public function resend(): never {
		Admin_Router::authorize();
		$id = absint( $_REQUEST['assignment_id'] ?? 0 );
		check_admin_referer( 'adam_resend_manager_invitation_' . $id );
		global $wpdb;
		$sent = false;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*,m.email FROM ' . Schema::assignments_table() . ' a INNER JOIN ' . Schema::managers_table() . ' m ON m.id=a.manager_id WHERE a.id=%d', $id ) );
		if ( $row ) {
			$url = $this->service->invite( (string) $row->email, (string) $row->entity_type, (int) $row->entity_id );
			$record = $this->service->record( (string) $row->entity_type, (int) $row->entity_id );
			if ( ! is_wp_error( $url ) ) {
				$sent = ( new \ADAM\Comunidade\Experience\Email_Service() )->send( 'manager_invitation', (string) $row->email, array( 'entity_name' => (string) ( $record->name ?? '' ), 'manager_invite_url' => $url ) );
			}
		}
		$this->redirect_managers( $sent ? 'invitation-sent' : 'invitation-send-failed' );
	}

	public function status(): never {
		Admin_Router::authorize();
		$id = absint( $_POST['manager_id'] ?? 0 );
		check_admin_referer( 'adam_manager_admin_status_' . $id );
		$status = sanitize_key( wp_unslash( $_POST['manager_status'] ?? '' ) );
		if ( in_array( $status, array( 'active', 'disabled' ), true ) ) {
			global $wpdb;
			$password_hash = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT password_hash FROM ' . Schema::managers_table() . ' WHERE id=%d AND status<>%s', $id, 'deleted' ) );
			$next_status   = 'active' === $status && '' === $password_hash ? 'invited' : $status;
			$wpdb->update( Schema::managers_table(), array( 'status' => $next_status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
			if ( 'disabled' === $next_status ) {
				$wpdb->delete( Schema::sessions_table(), array( 'manager_id' => $id ) );
			}
		}
		$this->redirect_managers( 'status-updated' );
	}

	public function remove_assignment(): never {
		Admin_Router::authorize();
		$id = absint( $_POST['assignment_id'] ?? 0 );
		check_admin_referer( 'adam_manager_remove_assignment_' . $id );
		global $wpdb;
		$cancelled = $this->service->cancel_invitation( $id );
		$removed   = is_wp_error( $cancelled )
			? false
			: $wpdb->update( Schema::assignments_table(), array( 'status' => 'removed', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id, 'status' => 'active' ) );
		$this->redirect_managers( 1 !== $removed ? 'assignment-failed' : 'assignment-removed' );
	}

	public function assign(): never {
		Admin_Router::authorize();
		check_admin_referer( 'adam_manager_admin_assign' );
		global $wpdb;
		$manager_id = absint( $_POST['manager_id'] ?? 0 );
		$manager    = $manager_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::managers_table() . ' WHERE id=%d', $manager_id ) ) : null;
		$email      = $manager ? (string) $manager->email : sanitize_email( wp_unslash( $_POST['manager_email'] ?? '' ) );
		$entities = isset( $_POST['entities'] ) && is_array( $_POST['entities'] ) ? wp_unslash( $_POST['entities'] ) : array();
		$references = array();
		foreach ( array_slice( array_unique( array_map( 'strval', $entities ) ), 0, 100 ) as $value ) {
			$parts = explode( ':', sanitize_text_field( $value ), 2 );
			$type  = sanitize_key( $parts[0] ?? '' );
			$id    = absint( $parts[1] ?? 0 );
			if ( $id && in_array( $type, array( 'team', 'field', 'partner', 'institution' ), true ) ) {
				$references[] = array( 'type' => $type, 'id' => $id );
			}
		}
		if ( ! $references || ( ! $manager && ! is_email( $email ) ) ) {
			$this->redirect_managers( 'assignment-invalid' );
		}

		$url = '';
		$partial_failure = false;
		$assigned_manager_id = $manager_id;
		foreach ( $references as $index => $reference ) {
			if ( 0 === $index && ! $manager ) {
				$url = $this->service->invite( $email, $reference['type'], $reference['id'] );
				if ( is_wp_error( $url ) ) {
					$this->redirect_managers( 'assignment-failed' );
				}
				$assigned_manager_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::managers_table() . ' WHERE email=%s', $email ) );
				continue;
			}
			$result = $this->service->assign_existing( $assigned_manager_id, $reference['type'], $reference['id'] );
			if ( is_wp_error( $result ) ) {
				$partial_failure = true;
			}
		}
		if ( ! $manager && $url ) {
			$record = 1 === count( $references )
				? $this->service->record( $references[0]['type'], $references[0]['id'] )
				: null;
			$label = 1 === count( $references )
				? (string) ( $record->name ?? __( 'Organização da Comunidade', 'adam-comunidade' ) )
				: sprintf( _n( '%d organização', '%d organizações', count( $references ), 'adam-comunidade' ), count( $references ) );
			$sent = ( new \ADAM\Comunidade\Experience\Email_Service() )->send( 'manager_invitation', $email, array( 'entity_name' => $label, 'manager_invite_url' => $url ) );
			if ( ! $sent ) {
				$this->redirect_managers( 'invitation-send-failed' );
			}
		}
		$this->redirect_managers( $partial_failure ? 'assignment-partial' : 'assignment-saved' );
	}

	public function transfer(): never {
		Admin_Router::authorize();
		$assignment_id = absint( $_POST['assignment_id'] ?? 0 );
		check_admin_referer( 'adam_manager_transfer_' . $assignment_id );
		$target_id = absint( $_POST['target_manager_id'] ?? 0 );
		global $wpdb;
		$assignment = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::assignments_table() . ' WHERE id=%d', $assignment_id ) );
		$target_exists = $target_id && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . Schema::managers_table() . " WHERE id=%d AND status IN ('invited','active')", $target_id ) );
		if ( $assignment && $target_exists && $target_id !== (int) $assignment->manager_id ) {
			$now = current_time( 'mysql', true );
			$transaction_started = false !== $wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $transaction_started ) {
				$this->redirect_managers( 'assignment-failed' );
			}
			$transferred = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ' . Schema::assignments_table() . ' (manager_id,entity_type,entity_id,status,created_at,updated_at) VALUES (%d,%s,%d,%s,%s,%s)'
					. ' ON DUPLICATE KEY UPDATE status=VALUES(status),updated_at=VALUES(updated_at)',
					$target_id,
					(string) $assignment->entity_type,
					(int) $assignment->entity_id,
					'active',
					$now,
					$now
				)
			);
			if ( false !== $transferred ) {
				$cancelled = $this->service->cancel_invitation( $assignment_id );
				$source_updated = is_wp_error( $cancelled )
					? false
					: $wpdb->update( Schema::assignments_table(), array( 'status' => 'transferred', 'updated_at' => $now ), array( 'id' => $assignment_id, 'status' => 'active' ) );
				if ( 1 === $source_updated ) {
					$committed = $wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					if ( false === $committed ) {
						$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$transferred = false;
					}
				} else {
					$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$transferred = false;
				}
			} else {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		$this->redirect_managers( isset( $transferred ) && false !== $transferred ? 'assignment-transferred' : 'assignment-failed' );
	}

	public function cancel_invitation(): never {
		Admin_Router::authorize();
		$id = absint( $_POST['assignment_id'] ?? 0 );
		check_admin_referer( 'adam_manager_cancel_invitation_' . $id );
		$result = $this->service->cancel_invitation( $id );
		$this->redirect_managers( is_wp_error( $result ) ? 'invitation-cancel-failed' : 'invitation-cancelled' );
	}

	public function reset_password(): never {
		Admin_Router::authorize();
		$id = absint( $_POST['manager_id'] ?? 0 );
		check_admin_referer( 'adam_manager_reset_password_' . $id );
		global $wpdb;
		$email = (string) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT email FROM ' . Schema::managers_table() . ' WHERE id=%d AND status=%s',
				$id,
				'active'
			)
		);
		$sent = $email && $this->service->request_password_reset( $email );
		$this->redirect_managers( $sent ? 'password-reset-sent' : 'password-reset-failed' );
	}

	public function delete(): never {
		Admin_Router::authorize();
		$id = absint( $_POST['manager_id'] ?? 0 );
		check_admin_referer( 'adam_manager_delete_' . $id );
		$strategy = sanitize_key( wp_unslash( $_POST['assignment_action'] ?? '' ) );
		$target   = absint( $_POST['target_manager_id'] ?? 0 );
		$result   = $this->service->delete_manager( $id, $strategy, $target );
		$this->redirect_managers( is_wp_error( $result ) ? 'manager-delete-failed' : 'manager-deleted' );
	}

	private function redirect_managers( string $status = '' ): never {
		$url = Admin_Router::page_url( 'managers' );
		if ( $status ) {
			$url = add_query_arg( 'gestor_estado', sanitize_key( $status ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders recent immutable moderation decisions.
	 *
	 * @param object[]             $history Revision rows.
	 * @param array<string,string> $record_names Entity labels.
	 */
	private function render_revision_history( array $history, array $record_names ): void {
		$status_labels = array(
			'approved'   => __( 'Aprovada', 'adam-comunidade' ),
			'rejected'   => __( 'Rejeitada', 'adam-comunidade' ),
			'needs_info' => __( 'Alterações pedidas', 'adam-comunidade' ),
			'superseded' => __( 'Substituída', 'adam-comunidade' ),
		);
		if ( ! $history ) {
			echo '<p>' . esc_html__( 'Ainda não existe histórico de moderação.', 'adam-comunidade' ) . '</p>';
			return;
		}
		?>
		<div class="adam-revision-history">
			<?php foreach ( $history as $revision ) :
				$key = (string) $revision->entity_type . ':' . (int) $revision->entity_id;
				$changes = $this->service->revision_changes( $revision );
				?>
				<details>
					<summary><strong><?php echo esc_html( $record_names[ $key ] ?? __( 'Registo indisponível', 'adam-comunidade' ) ); ?></strong><span><?php echo esc_html( $status_labels[ $revision->status ] ?? (string) $revision->status ); ?></span><time><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) ( $revision->reviewed_at ?: $revision->updated_at ) ) ); ?></time></summary>
					<dl>
						<div><dt><?php esc_html_e( 'Criada em', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $revision->submitted_at ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Gestor', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( (string) ( $revision->email ?: __( 'Conta indisponível', 'adam-comunidade' ) ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Administrador', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( (string) ( $revision->reviewer_name ?: __( 'Não aplicável', 'adam-comunidade' ) ) ); ?></dd></div>
						<?php if ( $revision->reviewed_at ) : ?><div><dt><?php esc_html_e( 'Decidida em', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $revision->reviewed_at ) ); ?></dd></div><?php endif; ?>
						<?php if ( $revision->published_at ) : ?><div><dt><?php esc_html_e( 'Publicada em', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $revision->published_at ) ); ?></dd></div><?php endif; ?>
					</dl>
					<?php if ( $revision->admin_note ) : ?><p><strong><?php esc_html_e( 'Motivos da moderação:', 'adam-comunidade' ); ?></strong><br><?php echo nl2br( esc_html( (string) $revision->admin_note ) ); ?></p><?php endif; ?>
					<p><?php echo esc_html( sprintf( _n( '%d alteração registada.', '%d alterações registadas.', count( $changes ), 'adam-comunidade' ), count( $changes ) ) ); ?></p>
					<?php if ( $changes ) : ?>
						<div class="adam-revision-comparison adam-revision-history-changes" role="table" aria-label="<?php esc_attr_e( 'Alterações desta revisão', 'adam-comunidade' ); ?>">
							<div class="adam-revision-comparison__head" role="row"><span role="columnheader"><?php esc_html_e( 'Campo', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Versão anterior', 'adam-comunidade' ); ?></span><span role="columnheader"><?php esc_html_e( 'Versão proposta', 'adam-comunidade' ); ?></span></div>
							<?php foreach ( $changes as $key => $change ) : ?>
								<div class="adam-revision-change adam-revision-change--<?php echo esc_attr( $change['kind'] ); ?>" role="row">
									<div class="adam-revision-change__label" role="cell"><strong><?php echo esc_html( $this->field_label( (string) $key ) ); ?></strong><small><?php echo esc_html( $this->change_label( (string) $change['kind'] ) ); ?></small></div>
									<div role="cell" data-label="<?php esc_attr_e( 'Versão anterior', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $change['before'], (string) $revision->entity_type ); ?></div>
									<div role="cell" data-label="<?php esc_attr_e( 'Versão proposta', 'adam-comunidade' ); ?>"><?php $this->render_revision_value( (string) $key, $change['after'], (string) $revision->entity_type ); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function change_label( string $kind ): string {
		return array(
			'added'   => __( 'Adicionado', 'adam-comunidade' ),
			'removed' => __( 'Removido', 'adam-comunidade' ),
			'changed' => __( 'Alterado', 'adam-comunidade' ),
		)[ $kind ] ?? __( 'Alterado', 'adam-comunidade' );
	}

	/**
	 * Renders text, lists and image changes without exposing raw markup.
	 */
	private function render_revision_value( string $key, mixed $value, string $entity_type ): void {
		if ( in_array( $key, array( 'cover_id', 'logo_id' ), true ) ) {
			$id = absint( $value );
			if ( $id && wp_attachment_is_image( $id ) ) {
				echo '<a class="adam-revision-image" href="' . esc_url( wp_get_attachment_url( $id ) ) . '" target="_blank" rel="noopener">' . wp_get_attachment_image( $id, 'medium' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			}
			echo '<em>' . esc_html__( 'Sem imagem', 'adam-comunidade' ) . '</em>';
			return;
		}
		if ( in_array( $key, array( 'gallery', 'gallery_ids' ), true ) ) {
			$ids = array_values( array_filter( array_map( 'absint', (array) $value ), 'wp_attachment_is_image' ) );
			if ( ! $ids ) {
				echo '<em>' . esc_html__( 'Sem fotografias', 'adam-comunidade' ) . '</em>';
				return;
			}
			echo '<div class="adam-revision-gallery">';
			foreach ( $ids as $position => $id ) {
				echo '<a href="' . esc_url( wp_get_attachment_url( $id ) ) . '" target="_blank" rel="noopener"><span>' . esc_html( (string) ( $position + 1 ) ) . '</span>' . wp_get_attachment_image( $id, 'thumbnail' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
			return;
		}
		if ( 'playing_styles' === $key ) {
			$labels = 'field' === $entity_type ? \ADAM\Comunidade\Fields\Options::playing_styles() : ( 'team' === $entity_type ? \ADAM\Comunidade\Teams\Options::playing_styles() : array() );
			$value = array_map( static fn( string $item ): string => (string) ( $labels[ $item ] ?? $item ), (array) $value );
		} elseif ( 'amenity_ids' === $key ) {
			global $wpdb;
			$ids = array_filter( array_map( 'absint', (array) $value ) );
			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,label FROM ' . \ADAM\Comunidade\Fields\Schema::amenities_table() . " WHERE id IN ({$placeholders})", ...$ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$labels = array();
				foreach ( is_array( $rows ) ? $rows : array() as $row ) {
					$labels[ (int) $row->id ] = (string) $row->label;
				}
				$value = array_map( static fn( int $id ): string => $labels[ $id ] ?? '#' . $id, $ids );
			}
		}
		if ( is_array( $value ) ) {
			echo $value ? esc_html( implode( ', ', array_map( 'strval', $value ) ) ) : '<em>' . esc_html__( 'Vazio', 'adam-comunidade' ) . '</em>';
			return;
		}
		if ( null === $value || '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			echo '<em>' . esc_html__( 'Vazio', 'adam-comunidade' ) . '</em>';
			return;
		}
		if ( in_array( $key, array( 'full_description', 'rules', 'benefits', 'member_benefits' ), true ) ) {
			echo '<div class="adam-revision-long-text">' . wp_kses_post( (string) $value ) . '</div>';
			return;
		}
		if ( in_array( $key, array( 'website', 'facebook', 'instagram', 'discord', 'youtube', 'tiktok', 'maps_url' ), true ) ) {
			echo '<a href="' . esc_url( (string) $value ) . '" target="_blank" rel="noopener">' . esc_html( (string) $value ) . '</a>';
			return;
		}
		echo nl2br( esc_html( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function field_label( string $key ): string {
		$labels = array(
			'name'                   => __( 'Nome', 'adam-comunidade' ),
			'short_name'             => __( 'Nome abreviado', 'adam-comunidade' ),
			'cover_id'               => __( 'Imagem de capa', 'adam-comunidade' ),
			'logo_id'                => __( 'Logótipo', 'adam-comunidade' ),
			'gallery'                => __( 'Galeria', 'adam-comunidade' ),
			'gallery_ids'            => __( 'Galeria', 'adam-comunidade' ),
			'short_description'      => __( 'Descrição breve', 'adam-comunidade' ),
			'full_description'       => __( 'Descrição completa', 'adam-comunidade' ),
			'district'               => __( 'Distrito', 'adam-comunidade' ),
			'municipality'           => __( 'Concelho', 'adam-comunidade' ),
			'address'                => __( 'Morada', 'adam-comunidade' ),
			'latitude'               => __( 'Latitude', 'adam-comunidade' ),
			'longitude'              => __( 'Longitude', 'adam-comunidade' ),
			'maps_url'               => __( 'Google Maps', 'adam-comunidade' ),
			'website'                => __( 'Website', 'adam-comunidade' ),
			'facebook'               => 'Facebook',
			'instagram'              => 'Instagram',
			'discord'                => 'Discord',
			'youtube'                => 'YouTube',
			'tiktok'                 => 'TikTok',
			'email'                  => __( 'E-mail', 'adam-comunidade' ),
			'phone'                  => __( 'Telefone', 'adam-comunidade' ),
			'playing_styles'         => __( 'Estilos de jogo', 'adam-comunidade' ),
			'amenity_ids'            => __( 'Comodidades', 'adam-comunidade' ),
			'rules'                  => __( 'Regras', 'adam-comunidade' ),
			'opening_hours'          => __( 'Horários', 'adam-comunidade' ),
			'max_players'            => __( 'Máximo de jogadores', 'adam-comunidade' ),
			'min_players'            => __( 'Mínimo de jogadores', 'adam-comunidade' ),
			'recommended_players'    => __( 'Jogadores recomendados', 'adam-comunidade' ),
			'recruitment_status'     => __( 'Estado do recrutamento', 'adam-comunidade' ),
			'recruitment_min_age'    => __( 'Idade mínima', 'adam-comunidade' ),
			'recruitment_experience' => __( 'Experiência necessária', 'adam-comunidade' ),
			'recruitment_equipment'  => __( 'Equipamento obrigatório', 'adam-comunidade' ),
			'recruitment_training'   => __( 'Treino necessário', 'adam-comunidade' ),
			'equipment_tags'         => __( 'Equipamento', 'adam-comunidade' ),
			'founded'                => __( 'Ano de fundação', 'adam-comunidade' ),
			'members'                => __( 'Número de elementos', 'adam-comunidade' ),
			'team_colour'            => __( 'Cor da Equipa', 'adam-comunidade' ),
			'availability'           => __( 'Disponibilidade', 'adam-comunidade' ),
			'category'               => __( 'Categoria', 'adam-comunidade' ),
			'country'                => __( 'País', 'adam-comunidade' ),
			'benefits'               => __( 'Benefícios', 'adam-comunidade' ),
			'member_benefits'        => __( 'Benefícios para Sócios ADAM', 'adam-comunidade' ),
			'popular_products'       => __( 'Produtos populares', 'adam-comunidade' ),
		);
		return $labels[ $key ] ?? __( 'Informação atualizada', 'adam-comunidade' );
	}
}
