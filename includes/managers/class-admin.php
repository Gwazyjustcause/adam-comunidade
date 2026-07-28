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
		$assignments = $wpdb->get_results( 'SELECT a.*,m.email,m.status AS manager_status FROM ' . Schema::assignments_table() . ' a INNER JOIN ' . Schema::managers_table() . " m ON m.id=a.manager_id WHERE a.status='active' ORDER BY a.created_at DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$revisions    = is_array( $revisions ) ? $revisions : array();
		$assignments  = is_array( $assignments ) ? $assignments : array();
		$record_names = $this->service->record_names( array_merge( $revisions, $assignments ) );
		?>
		<hr>
		<h2><?php esc_html_e( 'Alterações propostas por Gestores', 'adam-comunidade' ); ?></h2>
		<?php if ( ! $revisions ) : ?><p><?php esc_html_e( 'Não existem alterações de Gestores a aguardar revisão.', 'adam-comunidade' ); ?></p><?php endif; ?>
		<div class="adam-card-grid">
			<?php foreach ( $revisions as $revision ) : $record = (object) array( 'name' => $record_names[ (string) $revision->entity_type . ':' . (int) $revision->entity_id ] ?? __( 'Registo indisponível', 'adam-comunidade' ) ); $payload = json_decode( (string) $revision->payload, true ) ?: array(); ?>
				<article class="adam-card">
					<span class="adam-card__eyebrow"><?php esc_html_e( 'Revisão de Gestor', 'adam-comunidade' ); ?></span>
					<h3><?php echo esc_html( (string) ( $record->name ?? __( 'Registo indisponível', 'adam-comunidade' ) ) ); ?></h3>
					<p><?php echo esc_html( (string) $revision->email ); ?></p>
					<dl><?php foreach ( $payload as $key => $value ) : ?><div><dt><?php echo esc_html( $this->field_label( (string) $key ) ); ?></dt><dd><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : wp_strip_all_tags( (string) $value ) ); ?></dd></div><?php endforeach; ?></dl>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="adam_moderate_manager_revision">
						<input type="hidden" name="revision_id" value="<?php echo esc_attr( (string) $revision->id ); ?>">
						<?php wp_nonce_field( 'adam_moderate_manager_revision_' . $revision->id ); ?>
						<label><span><?php esc_html_e( 'Nota para o Gestor', 'adam-comunidade' ); ?></span><textarea name="admin_note" rows="3"><?php echo esc_textarea( (string) $revision->admin_note ); ?></textarea></label>
						<p><button class="button button-primary" name="decision" value="approve"><?php esc_html_e( 'Aprovar alterações', 'adam-comunidade' ); ?></button> <button class="button" name="decision" value="info"><?php esc_html_e( 'Pedir informação', 'adam-comunidade' ); ?></button> <button class="button button-link-delete" name="decision" value="reject"><?php esc_html_e( 'Rejeitar', 'adam-comunidade' ); ?></button></p>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
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
		$result = $this->service->moderate_revision( $id, sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) ), sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ?? '' ) ), get_current_user_id() );
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
			'website'                => __( 'Website', 'adam-comunidade' ),
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
			'recruitment_experience' => __( 'Experiência necessária', 'adam-comunidade' ),
			'recruitment_equipment'  => __( 'Equipamento obrigatório', 'adam-comunidade' ),
		);
		return $labels[ $key ] ?? __( 'Informação atualizada', 'adam-comunidade' );
	}
}
