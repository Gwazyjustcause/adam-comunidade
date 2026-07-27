<?php
/**
 * Manager revision moderation in the existing Approvals screen.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;

/**
 * Adds manager work to the existing approval workflow.
 */
final class Admin {
	public function __construct( private Service $service ) {}

	public function register(): void {
		add_action( 'adam_comunidade_moderation_after_submissions', array( $this, 'render' ) );
		add_action( 'admin_post_adam_moderate_manager_revision', array( $this, 'moderate' ) );
		add_action( 'admin_post_adam_resend_manager_invitation', array( $this, 'resend' ) );
	}

	public function render(): void {
		global $wpdb;
		$revisions = $wpdb->get_results( 'SELECT r.*,m.email FROM ' . Schema::revisions_table() . ' r INNER JOIN ' . Schema::managers_table() . " m ON m.id=r.manager_id WHERE r.status IN ('pending','needs_info') ORDER BY r.submitted_at ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$assignments = $wpdb->get_results( 'SELECT a.*,m.email,m.status AS manager_status FROM ' . Schema::assignments_table() . ' a INNER JOIN ' . Schema::managers_table() . " m ON m.id=a.manager_id WHERE a.status='active' ORDER BY a.created_at DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<hr>
		<h2><?php esc_html_e( 'Alterações propostas por Gestores', 'adam-comunidade' ); ?></h2>
		<?php if ( ! $revisions ) : ?><p><?php esc_html_e( 'Não existem alterações de Gestores a aguardar revisão.', 'adam-comunidade' ); ?></p><?php endif; ?>
		<div class="adam-card-grid">
			<?php foreach ( $revisions as $revision ) : $record = $this->service->record( (string) $revision->entity_type, (int) $revision->entity_id ); $payload = json_decode( (string) $revision->payload, true ) ?: array(); ?>
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
		<?php foreach ( $assignments as $assignment ) : $record = $this->service->record( (string) $assignment->entity_type, (int) $assignment->entity_id ); ?><tr><td><?php echo esc_html( (string) $assignment->email ); ?></td><td><?php echo esc_html( (string) ( $record->name ?? '#' . $assignment->entity_id ) ); ?></td><td><?php echo esc_html( 'active' === $assignment->manager_status ? __( 'Ativo', 'adam-comunidade' ) : __( 'Convite pendente', 'adam-comunidade' ) ); ?></td><td><a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'adam_resend_manager_invitation', 'assignment_id' => $assignment->id ), admin_url( 'admin-post.php' ) ), 'adam_resend_manager_invitation_' . $assignment->id ) ); ?>"><?php esc_html_e( 'Reenviar convite', 'adam-comunidade' ); ?></a></td></tr><?php endforeach; ?>
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
		$id = absint( $_GET['assignment_id'] ?? 0 );
		check_admin_referer( 'adam_resend_manager_invitation_' . $id );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*,m.email FROM ' . Schema::assignments_table() . ' a INNER JOIN ' . Schema::managers_table() . ' m ON m.id=a.manager_id WHERE a.id=%d', $id ) );
		if ( $row ) {
			$url = $this->service->invite( (string) $row->email, (string) $row->entity_type, (int) $row->entity_id );
			$record = $this->service->record( (string) $row->entity_type, (int) $row->entity_id );
			if ( ! is_wp_error( $url ) ) {
				( new \ADAM\Comunidade\Experience\Email_Service() )->send( 'manager_invitation', (string) $row->email, array( 'entity_name' => (string) ( $record->name ?? '' ), 'manager_invite_url' => $url ) );
			}
		}
		wp_safe_redirect( Admin_Router::page_url( 'moderation' ) );
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
