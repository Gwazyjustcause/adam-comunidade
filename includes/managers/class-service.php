<?php
/**
 * Community Manager domain service.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Config;
use ADAM\Comunidade\Experience\Email_Service;
use ADAM\Comunidade\Experience\Moderation_Reasons;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\Validator as Field_Validator;
use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\Validator as Directory_Validator;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\Validator as Team_Validator;
use ADAM\Comunidade\Logger;

/**
 * Coordinates invitations, assignments and moderated revisions.
 */
final class Service {
	public function __construct( private ?Email_Service $emails = null ) {
		$this->emails = $emails ?? new Email_Service();
	}

	/**
	 * Assigns an approved organisation and returns the correct account action.
	 *
	 * @return array{manager_id:int,state:string,action_url:string}|\WP_Error
	 */
	public function provision_organisation( string $email, string $entity_type, int $entity_id ): array|\WP_Error {
		$email   = strtolower( sanitize_email( $email ) );
		$manager = $this->manager_by_email( $email );

		if ( $manager && 'disabled' === (string) $manager->status ) {
			return new \WP_Error( 'manager_disabled', __( 'Esta conta de Gestor está desativada.', 'adam-comunidade' ) );
		}
		if ( $manager && in_array( (string) $manager->status, array( 'active', 'invited' ), true ) ) {
			$assigned = $this->assign_existing( (int) $manager->id, $entity_type, $entity_id );
			if ( is_wp_error( $assigned ) ) {
				return $assigned;
			}
			return array(
				'manager_id' => (int) $manager->id,
				'state'      => 'active' === (string) $manager->status ? 'active' : 'pending_activation',
				'action_url' => 'active' === (string) $manager->status ? Portal::url() : '',
			);
		}

		$url = $this->invite( $email, $entity_type, $entity_id );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$manager = $this->manager_by_email( $email );
		return array(
			'manager_id' => (int) ( $manager->id ?? 0 ),
			'state'      => 'active' === (string) ( $manager->status ?? '' ) ? 'active' : 'activation_required',
			'action_url' => $url,
		);
	}

	/**
	 * Resolves account access for a request-changes notification.
	 *
	 * A new account is invited once. Existing pending accounts retain their
	 * original token instead of receiving duplicate activation links.
	 *
	 * @return array{manager_id:int,state:string,action_url:string}|\WP_Error
	 */
	public function prepare_changes_access( string $email, string $entity_type, int $submission_id ): array|\WP_Error {
		global $wpdb;
		$email   = strtolower( sanitize_email( $email ) );
		$manager = $this->manager_by_email( $email );
		if ( $manager && 'active' === (string) $manager->status ) {
			return array( 'manager_id' => (int) $manager->id, 'state' => 'active', 'action_url' => Portal::url() );
		}
		if ( $manager && 'invited' === (string) $manager->status ) {
			return array( 'manager_id' => (int) $manager->id, 'state' => 'pending_activation', 'action_url' => '' );
		}
		if ( $manager && 'disabled' === (string) $manager->status ) {
			return new \WP_Error( 'manager_disabled', __( 'Esta conta de Gestor está desativada.', 'adam-comunidade' ) );
		}
		if ( ! is_email( $email ) || ! in_array( $entity_type, Policy::entity_types(), true ) || $submission_id < 1 ) {
			return new \WP_Error( 'invalid_invitation', __( 'Não foi possível preparar o acesso de Gestor.', 'adam-comunidade' ) );
		}

		$now = current_time( 'mysql', true );
		if ( $manager && 'deleted' === (string) $manager->status ) {
			$updated = $wpdb->update(
				Schema::managers_table(),
				array( 'status' => 'invited', 'password_hash' => '', 'updated_at' => $now ),
				array( 'id' => (int) $manager->id )
			);
			if ( false === $updated ) {
				return new \WP_Error( 'manager_create_failed', __( 'Não foi possível preparar a conta de Gestor.', 'adam-comunidade' ) );
			}
			$manager_id = (int) $manager->id;
		} else {
			$inserted = $wpdb->insert(
				Schema::managers_table(),
				array( 'email' => $email, 'status' => 'invited', 'created_at' => $now, 'updated_at' => $now )
			);
			if ( false === $inserted ) {
				// The unique email constraint may have won a concurrent request.
				$concurrent = $this->manager_by_email( $email );
				if ( $concurrent ) {
					return 'active' === (string) $concurrent->status
						? array( 'manager_id' => (int) $concurrent->id, 'state' => 'active', 'action_url' => Portal::url() )
						: array( 'manager_id' => (int) $concurrent->id, 'state' => 'pending_activation', 'action_url' => '' );
				}
				return new \WP_Error( 'manager_create_failed', __( 'Não foi possível preparar a conta de Gestor.', 'adam-comunidade' ) );
			}
			$manager_id = (int) $wpdb->insert_id;
		}

		$url = $this->create_invitation_token( $manager_id, 'submission_' . sanitize_key( $entity_type ), $submission_id, $now );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		return array( 'manager_id' => $manager_id, 'state' => 'activation_required', 'action_url' => $url );
	}

	/**
	 * Creates an explicit assignment and a new single-use invitation.
	 *
	 * This deliberately queries only the Community Manager tables.
	 */
	public function invite( string $email, string $entity_type, int $entity_id ): string|\WP_Error {
		global $wpdb;
		$email       = strtolower( sanitize_email( $email ) );
		$entity_type = sanitize_key( $entity_type );
		if ( ! is_email( $email ) || ! in_array( $entity_type, Policy::entity_types(), true ) || $entity_id < 1 ) {
			return new \WP_Error( 'invalid_invitation', __( 'Não foi possível criar o convite de Gestor.', 'adam-comunidade' ) );
		}

		if ( ! $this->record( $entity_type, $entity_id ) ) {
			return new \WP_Error( 'invalid_assignment_record', __( 'A organização selecionada já não está disponível.', 'adam-comunidade' ) );
		}

		$manager = $this->manager_by_email( $email );
		$now     = current_time( 'mysql', true );
		if ( ! $manager ) {
			$inserted = $wpdb->insert(
				Schema::managers_table(),
				array( 'email' => $email, 'status' => 'invited', 'created_at' => $now, 'updated_at' => $now )
			);
			if ( false === $inserted ) {
				// A concurrent request may have created the canonical account.
				$manager = $this->manager_by_email( $email );
				if ( ! $manager ) {
					return new \WP_Error( 'manager_create_failed', __( 'Não foi possível criar a conta de Gestor.', 'adam-comunidade' ) );
				}
				$manager_id = (int) $manager->id;
			} else {
				$manager_id = (int) $wpdb->insert_id;
			}
		} else {
			$manager_id = (int) $manager->id;
		}
		if ( $manager && 'disabled' === $manager->status ) {
			return new \WP_Error( 'manager_disabled', __( 'Esta conta de Gestor está desativada.', 'adam-comunidade' ) );
		}
		if ( $manager && 'deleted' === $manager->status ) {
			$wpdb->update(
				Schema::managers_table(),
				array( 'status' => 'invited', 'updated_at' => $now ),
				array( 'id' => (int) $manager->id )
			);
			$manager->status = 'invited';
		}
		if ( ! $manager_id ) {
			Logger::error( 'community_manager_create_failed' );
			return new \WP_Error( 'manager_create_failed', __( 'Não foi possível criar a conta de Gestor.', 'adam-comunidade' ) );
		}

		$assigned = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . Schema::assignments_table() . ' (manager_id,entity_type,entity_id,status,created_at,updated_at) VALUES (%d,%s,%d,%s,%s,%s)'
				. ' ON DUPLICATE KEY UPDATE status=VALUES(status),updated_at=VALUES(updated_at)',
				$manager_id,
				$entity_type,
				$entity_id,
				'active',
				$now,
				$now
			)
		);
		if ( false === $assigned ) {
			Logger::error( 'community_manager_assignment_failed', array( 'manager_id' => $manager_id, 'entity_type' => $entity_type, 'entity_id' => $entity_id ) );
			return new \WP_Error( 'assignment_failed', __( 'Não foi possível atribuir o registo ao Gestor.', 'adam-comunidade' ) );
		}
		do_action( 'adam_comunidade_manager_assigned', $manager_id, $entity_type, $entity_id );
		if ( $manager && 'active' === $manager->status ) {
			return Portal::url();
		}
		return $this->create_invitation_token( $manager_id, $entity_type, $entity_id, $now );
	}

	public function activate( string $token, string $password ): int|\WP_Error {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Este convite não é válido.', 'adam-comunidade' ) );
		}
		$invitation = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::invitations_table() . " WHERE token_hash = %s AND purpose = 'invitation' AND used_at IS NULL AND expires_at > %s",
				hash( 'sha256', $token ),
				current_time( 'mysql', true )
			)
		);
		if ( ! $invitation ) {
			return new \WP_Error( 'expired_token', __( 'Este convite já foi utilizado ou expirou. Solicite um novo convite à ADAM.', 'adam-comunidade' ) );
		}
		$manager_status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Schema::managers_table() . ' WHERE id = %d', (int) $invitation->manager_id ) );
		if ( '' === $manager_status ) {
			return new \WP_Error( 'manager_not_found', __( 'Este convite já não está associado a uma conta válida.', 'adam-comunidade' ) );
		}
		if ( 'disabled' === $manager_status ) {
			return new \WP_Error( 'manager_disabled', __( 'Esta conta de Gestor está desativada.', 'adam-comunidade' ) );
		}
		if ( strlen( $password ) < 10 || strlen( $password ) > 4096 ) {
			return new \WP_Error( 'weak_password', __( 'A palavra-passe deve ter pelo menos 10 caracteres.', 'adam-comunidade' ) );
		}
		$now = current_time( 'mysql', true );
		$claimed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::invitations_table() . ' SET used_at = %s WHERE id = %d AND used_at IS NULL AND expires_at > %s',
				$now,
				(int) $invitation->id,
				$now
			)
		);
		if ( 1 !== $claimed ) {
			return new \WP_Error( 'used_token', __( 'Este convite já foi utilizado ou expirou.', 'adam-comunidade' ) );
		}
		$activated = $wpdb->update(
			Schema::managers_table(),
			array( 'password_hash' => password_hash( $password, PASSWORD_DEFAULT ), 'status' => 'active', 'updated_at' => $now ),
			array( 'id' => (int) $invitation->manager_id )
		);
		if ( 1 !== $activated ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::invitations_table() . ' SET used_at = NULL WHERE id = %d AND used_at = %s', (int) $invitation->id, $now ) );
			Logger::error( 'community_manager_activation_failed', array( 'manager_id' => (int) $invitation->manager_id ) );
			return new \WP_Error( 'activation_failed', __( 'Não foi possível ativar a conta de Gestor.', 'adam-comunidade' ) );
		}
		$invalidated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::invitations_table() . " SET used_at=%s WHERE manager_id=%d AND purpose='invitation' AND used_at IS NULL",
				$now,
				(int) $invitation->manager_id
			)
		);
		if ( false === $invalidated ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM " . Schema::invitations_table() . " WHERE manager_id=%d AND purpose='invitation' AND used_at IS NULL",
					(int) $invitation->manager_id
				)
			);
			Logger::error( 'community_manager_invitation_replay_cleanup_failed', array( 'manager_id' => (int) $invitation->manager_id ) );
		}
		$wpdb->delete( Schema::sessions_table(), array( 'manager_id' => (int) $invitation->manager_id ) );
		$manager_email = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . Schema::managers_table() . ' WHERE id=%d', (int) $invitation->manager_id ) );
		if ( is_email( $manager_email ) ) {
			$this->emails->send(
				'manager_password_created',
				$manager_email,
				array( 'manager_url' => Portal::url() )
			);
		}
		return (int) $invitation->manager_id;
	}

	/**
	 * Creates a password-reset message without revealing whether an account exists.
	 */
	public function request_password_reset( string $email ): bool {
		global $wpdb;
		$email   = sanitize_email( $email );
		$manager = is_email( $email )
			? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::managers_table() . ' WHERE email = %s AND status = %s', $email, 'active' ) )
			: null;
		if ( ! $manager ) {
			return false;
		}
		$now = current_time( 'mysql', true );
		$raw = $this->new_token();
		if ( is_wp_error( $raw ) ) {
			return false;
		}
		$inserted = $wpdb->insert(
			Schema::invitations_table(),
			array(
				'manager_id' => (int) $manager->id,
				'purpose'    => 'password_reset',
				'entity_type'=> 'manager',
				'entity_id'  => (int) $manager->id,
				'token_hash' => hash( 'sha256', $raw ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + Config::manager_security()['password_reset_ttl'] ),
				'created_at' => $now,
			)
		);
		if ( false !== $inserted ) {
			$reset_id = (int) $wpdb->insert_id;
			$superseded = $wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . Schema::invitations_table() . " SET used_at = %s WHERE manager_id = %d AND purpose = 'password_reset' AND used_at IS NULL AND id <> %d",
					$now,
					(int) $manager->id,
					$reset_id
				)
			);
			if ( false === $superseded ) {
				$wpdb->delete( Schema::invitations_table(), array( 'id' => $reset_id ) );
				Logger::error( 'community_manager_password_reset_supersede_failed', array( 'manager_id' => (int) $manager->id ) );
				return false;
			}
			return $this->emails->send(
				'manager_password_reset',
				$email,
				array( 'manager_reset_url' => Portal::recovery_url( $raw ) )
			);
		} else {
			Logger::error( 'community_manager_password_reset_insert_failed', array( 'manager_id' => (int) $manager->id ) );
		}
		return false;
	}

	public function reset_password( string $token, string $password ): bool|\WP_Error {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Este pedido de recuperação não é válido.', 'adam-comunidade' ) );
		}
		if ( strlen( $password ) < 10 || strlen( $password ) > 4096 ) {
			return new \WP_Error( 'weak_password', __( 'A palavra-passe deve ter pelo menos 10 caracteres.', 'adam-comunidade' ) );
		}
		$now = current_time( 'mysql', true );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::invitations_table() . " WHERE token_hash = %s AND purpose = 'password_reset' AND used_at IS NULL AND expires_at > %s",
				hash( 'sha256', $token ),
				$now
			)
		);
		if ( ! $row ) {
			return new \WP_Error( 'expired_reset', __( 'Este pedido já foi utilizado ou expirou.', 'adam-comunidade' ) );
		}
		$claimed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::invitations_table() . ' SET used_at = %s WHERE id = %d AND used_at IS NULL AND expires_at > %s',
				$now,
				(int) $row->id,
				$now
			)
		);
		if ( 1 !== $claimed ) {
			return new \WP_Error( 'used_reset', __( 'Este pedido já foi utilizado ou expirou.', 'adam-comunidade' ) );
		}
		$updated = $wpdb->update(
			Schema::managers_table(),
			array( 'password_hash' => password_hash( $password, PASSWORD_DEFAULT ), 'updated_at' => $now ),
			array( 'id' => (int) $row->manager_id, 'status' => 'active' )
		);
		if ( 1 !== $updated ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::invitations_table() . ' SET used_at = NULL WHERE id = %d AND used_at = %s', (int) $row->id, $now ) );
			Logger::error( 'community_manager_password_reset_failed', array( 'manager_id' => (int) $row->manager_id ) );
			return new \WP_Error( 'reset_failed', __( 'Não foi possível atualizar a palavra-passe.', 'adam-comunidade' ) );
		}
		$wpdb->delete( Schema::sessions_table(), array( 'manager_id' => (int) $row->manager_id ) );
		$invalidated_resets = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::invitations_table() . " SET used_at=%s WHERE manager_id=%d AND purpose='password_reset' AND used_at IS NULL",
				$now,
				(int) $row->manager_id
			)
		);
		if ( false === $invalidated_resets ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM " . Schema::invitations_table() . " WHERE manager_id=%d AND purpose='password_reset' AND used_at IS NULL",
					(int) $row->manager_id
				)
			);
			Logger::error( 'community_manager_password_reset_replay_cleanup_failed', array( 'manager_id' => (int) $row->manager_id ) );
		}
		$manager_email = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM ' . Schema::managers_table() . ' WHERE id=%d', (int) $row->manager_id ) );
		if ( is_email( $manager_email ) ) {
			$this->emails->send(
				'manager_password_changed',
				$manager_email,
				array( 'manager_url' => Portal::login_url() )
			);
		}
		return true;
	}

	public function assignments( int $manager_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::assignments_table() . ' WHERE manager_id = %d AND status = %s ORDER BY entity_type, entity_id',
				$manager_id,
				'active'
			)
		) ?: array();
	}

	/**
	 * Assigns an existing manager without issuing another activation token.
	 *
	 * @return true|\WP_Error
	 */
	public function assign_existing( int $manager_id, string $type, int $entity_id, bool $notify = true ): true|\WP_Error {
		global $wpdb;
		$type = sanitize_key( $type );
		if ( $manager_id < 1 || $entity_id < 1 || ! in_array( $type, Policy::entity_types(), true ) || ! $this->record( $type, $entity_id ) ) {
			return new \WP_Error( 'invalid_assignment', __( 'A atribuição selecionada não é válida.', 'adam-comunidade' ) );
		}
		$status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Schema::managers_table() . ' WHERE id=%d', $manager_id ) );
		if ( ! in_array( $status, array( 'invited', 'active' ), true ) ) {
			return new \WP_Error( 'manager_unavailable', __( 'O Gestor selecionado não pode receber atribuições.', 'adam-comunidade' ) );
		}
		$now = current_time( 'mysql', true );
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . Schema::assignments_table() . ' (manager_id,entity_type,entity_id,status,created_at,updated_at) VALUES (%d,%s,%d,%s,%s,%s)'
				. ' ON DUPLICATE KEY UPDATE status=VALUES(status),updated_at=VALUES(updated_at)',
				$manager_id,
				$type,
				$entity_id,
				'active',
				$now,
				$now
			)
		);
		if ( false === $result ) {
			Logger::error( 'community_manager_assignment_failed', array( 'manager_id' => $manager_id, 'entity_type' => $type, 'entity_id' => $entity_id ) );
			return new \WP_Error( 'assignment_failed', __( 'Não foi possível guardar a atribuição.', 'adam-comunidade' ) );
		}
		if ( $notify ) {
			do_action( 'adam_comunidade_manager_assigned', $manager_id, $type, $entity_id );
		}
		return true;
	}

	/**
	 * Cancels every open activation token for one assignment.
	 *
	 * @return true|\WP_Error
	 */
	public function cancel_invitation( int $assignment_id ): true|\WP_Error {
		global $wpdb;
		$assignment = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::assignments_table() . ' WHERE id=%d AND status=%s', $assignment_id, 'active' ) );
		if ( ! $assignment ) {
			return new \WP_Error( 'assignment_not_found', __( 'A atribuição já não está disponível.', 'adam-comunidade' ) );
		}
		$now = current_time( 'mysql', true );
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::invitations_table() . " SET used_at=%s WHERE manager_id=%d AND purpose='invitation' AND entity_type=%s AND entity_id=%d AND used_at IS NULL",
				$now,
				(int) $assignment->manager_id,
				(string) $assignment->entity_type,
				(int) $assignment->entity_id
			)
		);
		if ( false === $result ) {
			Logger::error( 'community_manager_invitation_cancel_failed', array( 'assignment_id' => $assignment_id ) );
			return new \WP_Error( 'invitation_cancel_failed', __( 'Não foi possível cancelar o convite.', 'adam-comunidade' ) );
		}
		return true;
	}

	/**
	 * Soft-deletes a manager and either releases or transfers active assignments.
	 *
	 * @return true|\WP_Error
	 */
	public function delete_manager( int $manager_id, string $assignment_action, int $target_manager_id = 0 ): true|\WP_Error {
		global $wpdb;
		$manager = $wpdb->get_row( $wpdb->prepare( 'SELECT id,status FROM ' . Schema::managers_table() . ' WHERE id=%d AND status<>%s', $manager_id, 'deleted' ) );
		if ( ! $manager ) {
			return new \WP_Error( 'manager_not_found', __( 'O Gestor já não está disponível.', 'adam-comunidade' ) );
		}
		if ( ! in_array( $assignment_action, array( 'release', 'transfer' ), true ) ) {
			return new \WP_Error( 'invalid_delete_action', __( 'Selecione o que deve acontecer às organizações atribuídas.', 'adam-comunidade' ) );
		}
		if ( 'transfer' === $assignment_action ) {
			$target_status = $target_manager_id && $target_manager_id !== $manager_id
				? (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . Schema::managers_table() . ' WHERE id=%d', $target_manager_id ) )
				: '';
			if ( ! in_array( $target_status, array( 'invited', 'active' ), true ) ) {
				return new \WP_Error( 'invalid_transfer_target', __( 'Selecione um Gestor de destino válido.', 'adam-comunidade' ) );
			}
		}

		$now = current_time( 'mysql', true );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_delete_failed', array( 'manager_id' => $manager_id, 'operation' => 'transaction_start_failed' ) );
			return new \WP_Error( 'manager_delete_failed', __( 'Não foi possível eliminar o Gestor. Nenhuma alteração foi aplicada.', 'adam-comunidade' ) );
		}
		try {
			$active = $this->assignments( $manager_id );
			if ( 'transfer' === $assignment_action ) {
				foreach ( $active as $assignment ) {
					$result = $this->assign_existing( $target_manager_id, (string) $assignment->entity_type, (int) $assignment->entity_id, false );
					if ( is_wp_error( $result ) ) {
						throw new \RuntimeException( 'assignment_transfer_failed' );
					}
				}
			}
			$assignment_status = 'transfer' === $assignment_action ? 'transferred' : 'removed';
			if ( false === $wpdb->update( Schema::assignments_table(), array( 'status' => $assignment_status, 'updated_at' => $now ), array( 'manager_id' => $manager_id, 'status' => 'active' ) ) ) {
				throw new \RuntimeException( 'assignment_release_failed' );
			}
			if ( false === $wpdb->update( Schema::managers_table(), array( 'status' => 'deleted', 'password_hash' => '', 'updated_at' => $now ), array( 'id' => $manager_id ) ) ) {
				throw new \RuntimeException( 'manager_soft_delete_failed' );
			}
			if ( false === $wpdb->delete( Schema::sessions_table(), array( 'manager_id' => $manager_id ) ) ) {
				throw new \RuntimeException( 'session_revoke_failed' );
			}
			if ( false === $wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::invitations_table() . ' SET used_at=%s WHERE manager_id=%d AND used_at IS NULL', $now, $manager_id ) ) ) {
				throw new \RuntimeException( 'token_revoke_failed' );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				throw new \RuntimeException( 'manager_delete_commit_failed' );
			}
		} catch ( \Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_delete_failed', array( 'manager_id' => $manager_id, 'operation' => sanitize_key( $throwable->getMessage() ) ) );
			return new \WP_Error( 'manager_delete_failed', __( 'Não foi possível eliminar o Gestor. Nenhuma alteração foi aplicada.', 'adam-comunidade' ) );
		}
		if ( 'transfer' === $assignment_action ) {
			foreach ( $active as $assignment ) {
				do_action( 'adam_comunidade_manager_assigned', $target_manager_id, (string) $assignment->entity_type, (int) $assignment->entity_id );
			}
		}
		do_action( 'adam_comunidade_manager_deleted', $manager_id, $assignment_action, $target_manager_id );
		return true;
	}

	public function can_manage( int $manager_id, string $type, int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::assignments_table() . ' WHERE manager_id = %d AND entity_type = %s AND entity_id = %d AND status = %s',
				$manager_id,
				$type,
				$id,
				'active'
			)
		);
	}

	/**
	 * Loads entity names in at most one query per entity type.
	 *
	 * @param object[] $references Objects containing entity_type and entity_id.
	 * @return array<string,string> Map keyed by "type:id".
	 */
	public function record_names( array $references ): array {
		global $wpdb;
		$grouped = array();
		foreach ( $references as $reference ) {
			$type = sanitize_key( (string) ( $reference->entity_type ?? '' ) );
			$id   = absint( $reference->entity_id ?? 0 );
			if ( $id && in_array( $type, array( 'team', 'field', 'partner', 'institution' ), true ) ) {
				$grouped[ $type ][ $id ] = $id;
			}
		}

		$tables = array(
			'team'        => \ADAM\Comunidade\Teams\Schema::teams_table(),
			'field'       => \ADAM\Comunidade\Fields\Schema::fields_table(),
			'partner'     => \ADAM\Comunidade\Directory\Schema::entries_table(),
			'institution' => \ADAM\Comunidade\Directory\Schema::entries_table(),
		);
		$names = array();
		foreach ( $grouped as $type => $ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$args         = array_values( $ids );
			$sql          = 'SELECT id,name FROM ' . $tables[ $type ] . " WHERE id IN ({$placeholders})";
			if ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
				$sql   .= ' AND entity_type=%s';
				$args[] = $type;
			}
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				$names[ $type . ':' . (int) $row->id ] = (string) $row->name;
			}
		}
		$names = apply_filters( 'adam_comunidade_manager_entity_names', $names, $references );
		return is_array( $names ) ? $names : array();
	}

	public function record( string $type, int $id ): ?object {
		$record = null;
		if ( 'team' === $type ) {
			$record = ( new Team_Repository() )->find( $id );
		} elseif ( 'field' === $type ) {
			$record = ( new Field_Repository() )->find( $id );
		} elseif ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			$record = ( new Directory_Repository() )->find( $id, $type );
		}
		$record = apply_filters( 'adam_comunidade_manager_entity_record', $record, $type, $id );
		return is_object( $record ) ? $record : null;
	}

	/**
	 * Returns the single active revision for an entity, if one exists.
	 */
	public function active_revision( string $type, int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::revisions_table() . " WHERE entity_type=%s AND entity_id=%d AND status IN ('pending','needs_info','processing') ORDER BY id DESC LIMIT 1",
				sanitize_key( $type ),
				$id
			)
		);
		return is_object( $row ) ? $row : null;
	}

	/**
	 * Returns active revisions visible to one manager, keyed by entity.
	 *
	 * @return array<string,object>
	 */
	public function active_revisions_for_manager( int $manager_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.* FROM ' . Schema::revisions_table() . ' r INNER JOIN ' . Schema::assignments_table()
				. " a ON a.entity_type=r.entity_type AND a.entity_id=r.entity_id AND a.manager_id=%d AND a.status='active'"
				. " WHERE r.status IN ('pending','needs_info','processing') ORDER BY r.submitted_at DESC",
				$manager_id
			)
		);
		$indexed = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$indexed[ (string) $row->entity_type . ':' . (int) $row->entity_id ] = $row;
		}
		return $indexed;
	}

	/**
	 * Decodes a stored proposal into comparable values.
	 *
	 * @return array<string,mixed>
	 */
	public function revision_payload( object $revision ): array {
		$payload = json_decode( (string) ( $revision->payload ?? '' ), true );
		return $this->normalize_revision_payload( (string) ( $revision->entity_type ?? '' ), is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Returns the immutable published baseline captured at submission time.
	 *
	 * Legacy revisions fall back to the current record because no historical
	 * snapshot existed before schema 1.4.
	 *
	 * @return array<string,mixed>
	 */
	public function revision_baseline( object $revision ): array {
		$baseline = json_decode( (string) ( $revision->base_payload ?? '' ), true );
		if ( is_array( $baseline ) ) {
			return $this->normalize_revision_payload( (string) $revision->entity_type, $baseline );
		}
		$current = $this->record( (string) $revision->entity_type, (int) $revision->entity_id );
		return $current ? $this->snapshot( (string) $revision->entity_type, $current ) : array();
	}

	/**
	 * Returns only fields whose proposed value differs from the captured base.
	 *
	 * @return array<string,array{before:mixed,after:mixed,kind:string}>
	 */
	public function revision_changes( object $revision ): array {
		$before = $this->revision_baseline( $revision );
		$after  = $this->revision_payload( $revision );
		$changes = array();
		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $key ) {
			$old = $before[ $key ] ?? null;
			$new = $after[ $key ] ?? null;
			if ( $this->canonicalize( $old ) === $this->canonicalize( $new ) ) {
				continue;
			}
			$old_empty = $this->is_empty_revision_value( $old );
			$new_empty = $this->is_empty_revision_value( $new );
			$changes[ $key ] = array(
				'before' => $old,
				'after'  => $new,
				'kind'   => $old_empty && ! $new_empty ? 'added' : ( ! $old_empty && $new_empty ? 'removed' : 'changed' ),
			);
		}
		return $changes;
	}

	/**
	 * Detects administrator-side changes made after a proposal was submitted.
	 */
	public function revision_has_conflict( object $revision ): bool {
		$stored = json_decode( (string) ( $revision->base_payload ?? '' ), true );
		if ( ! is_array( $stored ) ) {
			return false;
		}
		$current = $this->record( (string) $revision->entity_type, (int) $revision->entity_id );
		return ! $current || $this->payload_hash( $this->normalize_revision_payload( (string) $revision->entity_type, $stored ) ) !== $this->payload_hash( $this->snapshot( (string) $revision->entity_type, $current ) );
	}

	/**
	 * Describes changes made directly to the published record after submission.
	 *
	 * @return array<string,array{before:mixed,current:mixed,proposed:mixed}>
	 */
	public function revision_conflicts( object $revision ): array {
		$stored = json_decode( (string) ( $revision->base_payload ?? '' ), true );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$type    = (string) $revision->entity_type;
		$record  = $this->record( $type, (int) $revision->entity_id );
		$before  = $this->normalize_revision_payload( $type, $stored );
		$current = $record ? $this->snapshot( $type, $record ) : array();
		$proposal = $this->revision_payload( $revision );
		$conflicts = array();
		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $current ), array_keys( $proposal ) ) ) as $key ) {
			$old = $before[ $key ] ?? null;
			$now = $current[ $key ] ?? null;
			if ( $this->canonicalize( $old ) === $this->canonicalize( $now ) ) {
				continue;
			}
			$conflicts[ $key ] = array(
				'before'   => $old,
				'current'  => $now,
				'proposed' => $proposal[ $key ] ?? null,
			);
		}
		return $conflicts;
	}

	/**
	 * Returns recent moderation history for administrators.
	 *
	 * @return object[]
	 */
	public function revision_history( int $limit = 50 ): array {
		global $wpdb;
		$limit = max( 1, min( 200, $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.*,m.email,u.display_name AS reviewer_name FROM ' . Schema::revisions_table() . ' r'
				. ' LEFT JOIN ' . Schema::managers_table() . ' m ON m.id=r.manager_id'
				. " LEFT JOIN {$wpdb->users} u ON u.ID=r.reviewed_by"
				. " WHERE r.status IN ('approved','rejected','needs_info','superseded')"
				. ' ORDER BY COALESCE(r.reviewed_at,r.updated_at,r.submitted_at) DESC LIMIT %d',
				$limit
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	public function submit_revision( int $manager_id, string $type, int $id, array $input, array $relations = array() ): int|\WP_Error {
		global $wpdb;
		if ( ! $this->can_manage( $manager_id, $type, $id ) ) {
			return new \WP_Error( 'forbidden', __( 'Não tem permissão para gerir este registo.', 'adam-comunidade' ) );
		}
		$current = $this->record( $type, $id );
		if ( ! $current ) {
			return new \WP_Error( 'not_found', __( 'O registo já não está disponível.', 'adam-comunidade' ) );
		}
		$active_revision = $this->active_revision( $type, $id );
		if ( $active_revision && (int) $active_revision->manager_id !== $manager_id ) {
			return new \WP_Error( 'revision_conflict', __( 'Já existe uma revisão desta organização a aguardar análise. Tente novamente depois de a ADAM concluir essa revisão.', 'adam-comunidade' ) );
		}
		if ( $active_revision && 'processing' === (string) $active_revision->status ) {
			return new \WP_Error( 'revision_processing', __( 'Esta revisão está a ser analisada neste momento. Aguarde até a decisão estar concluída.', 'adam-comunidade' ) );
		}
		$baseline = $active_revision ? $this->revision_baseline( $active_revision ) : $this->snapshot( $type, $current );
		$merged = Policy::decode_lists( $type, array_merge( (array) $current, $input ) );
		if ( 'team' === $type ) {
			$data = ( new Team_Validator( new Team_Repository() ) )->validate( $merged, $id );
		} elseif ( 'field' === $type ) {
			$data = ( new Field_Validator( new Field_Repository() ) )->validate( $merged, $id );
		} elseif ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			$data = Directory_Validator::sanitize( $type, $merged );
		} else {
			$data = apply_filters( 'adam_comunidade_manager_revision_validate', null, $type, $id, $merged, $current );
			if ( ! is_array( $data ) && ! is_wp_error( $data ) ) {
				return new \WP_Error( 'unsupported_type', __( 'Este tipo de organização não é suportado.', 'adam-comunidade' ) );
			}
		}
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$allowed = Policy::editable_fields( $type );
		$payload = array_intersect_key( $data, array_flip( $allowed ) );
		foreach ( $relations as $key => $value ) {
			if ( in_array( $key, array( 'amenity_ids', 'gallery_ids' ), true ) ) {
				$payload[ $key ] = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
			}
		}
		if ( isset( $payload['amenity_ids'] ) ) {
			global $wpdb;
			$active_ids = array_map(
				'intval',
				$wpdb->get_col( 'SELECT id FROM ' . \ADAM\Comunidade\Fields\Schema::amenities_table() . " WHERE status = 'active'" ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
			$payload['amenity_ids'] = array_values( array_intersect( $payload['amenity_ids'], $active_ids ) );
		}
		$payload = $this->normalize_revision_payload( $type, $payload );
		if ( $this->payload_hash( $baseline ) === $this->payload_hash( $payload ) ) {
			return new \WP_Error( 'revision_unchanged', __( 'Não existem alterações para enviar. O registo publicado já contém estes dados.', 'adam-comunidade' ) );
		}

		$now = current_time( 'mysql', true );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_revision_transaction_failed', array( 'manager_id' => $manager_id, 'entity_type' => $type, 'entity_id' => $id ) );
			return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
		}
		$locked_revision = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::revisions_table() . " WHERE active_key=%s AND status IN ('pending','needs_info','processing') FOR UPDATE",
				$type . ':' . $id
			)
		);
		if ( $locked_revision && ( (int) $locked_revision->manager_id !== $manager_id || 'processing' === (string) $locked_revision->status ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new \WP_Error( 'revision_conflict', __( 'Já existe uma revisão desta organização a aguardar análise.', 'adam-comunidade' ) );
		}
		if ( $locked_revision ) {
			$superseded = $wpdb->update(
				Schema::revisions_table(),
				array( 'status' => 'superseded', 'active_key' => null, 'updated_at' => $now ),
				array( 'id' => (int) $locked_revision->id, 'active_key' => $type . ':' . $id )
			);
			if ( 1 !== $superseded ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				Logger::error( 'community_manager_revision_supersede_failed', array( 'manager_id' => $manager_id, 'entity_type' => $type, 'entity_id' => $id ) );
				return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
			}
		}
		$inserted = $wpdb->insert(
			Schema::revisions_table(),
			array(
				'manager_id' => $manager_id,
				'entity_type'=> $type,
				'entity_id'  => $id,
				'payload'    => wp_json_encode( $payload ),
				'base_payload'=> wp_json_encode( $baseline ),
				'status'     => 'pending',
				'active_key' => $type . ':' . $id,
				'submitted_at'=> $now,
				'updated_at' => $now,
			)
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_revision_insert_failed', array( 'manager_id' => $manager_id, 'entity_type' => $type, 'entity_id' => $id ) );
			return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
		}
		$revision_id = (int) $wpdb->insert_id;
		if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_revision_commit_failed', array( 'revision_id' => $revision_id ) );
			return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
		}
		do_action( 'adam_comunidade_manager_revision_submitted', $revision_id, $manager_id, $type, $id );
		return $revision_id;
	}

	/**
	 * Applies a structured moderation decision to a manager revision.
	 *
	 * @param string[] $reason_ids Configured reason identifiers.
	 */
	public function moderate_revision( int $revision_id, string $decision, array $reason_ids, int $reviewer_id, bool $force_conflict = false, string $custom_reason = '' ): bool|\WP_Error {
		global $wpdb;
		// Preserve compatibility with the legacy internal decision name while
		// exposing the same "changes" action used by every moderation screen.
		$decision = 'info' === $decision ? 'changes' : $decision;
		$revision = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::revisions_table() . ' WHERE id = %d', $revision_id ) );
		if ( ! $revision || ! in_array( $revision->status, array( 'pending', 'needs_info' ), true ) ) {
			return new \WP_Error( 'revision_unavailable', __( 'A revisão já não está disponível.', 'adam-comunidade' ) );
		}
		$status = array( 'approve' => 'approved', 'reject' => 'rejected', 'changes' => 'needs_info' )[ $decision ] ?? '';
		if ( ! $status ) {
			return new \WP_Error( 'invalid_decision', __( 'A decisão selecionada não é válida.', 'adam-comunidade' ) );
		}
		$note = '';
		if ( in_array( $decision, array( 'reject', 'changes' ), true ) ) {
			$reasons = Moderation_Reasons::resolve( $decision, $reason_ids, $custom_reason );
			if ( is_wp_error( $reasons ) ) {
				return $reasons;
			}
			$note = Moderation_Reasons::summary( $reasons );
		}
		$now = current_time( 'mysql', true );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new \WP_Error( 'moderation_failed', __( 'Não foi possível iniciar a decisão de moderação.', 'adam-comunidade' ) );
		}
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::revisions_table() . " SET status='processing',updated_at=%s WHERE id=%d AND status IN ('pending','needs_info')",
				$now,
				$revision_id
			)
		);
		if ( 1 !== $claimed ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new \WP_Error( 'revision_unavailable', __( 'A revisão já não está disponível.', 'adam-comunidade' ) );
		}
		if ( 'approve' === $decision ) {
			$result = $this->apply_revision( $revision, $force_conflict );
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				return $result;
			}
		}
		$terminal = in_array( $status, array( 'approved', 'rejected' ), true );
		$updated = $wpdb->update(
			Schema::revisions_table(),
			array(
				'status'       => $status,
				'active_key'   => $terminal ? null : (string) $revision->entity_type . ':' . (int) $revision->entity_id,
				'admin_note'   => sanitize_textarea_field( $note ),
				'reviewed_by'  => $reviewer_id,
				'reviewed_at'  => $now,
				'published_at' => 'approved' === $status ? $now : null,
				'updated_at'   => $now,
			),
			array( 'id' => $revision_id, 'status' => 'processing' )
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_revision_moderation_failed', array( 'revision_id' => $revision_id ) );
			return new \WP_Error( 'moderation_failed', __( 'Não foi possível concluir a revisão.', 'adam-comunidade' ) );
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_revision_commit_failed', array( 'revision_id' => $revision_id ) );
			return new \WP_Error( 'moderation_failed', __( 'Não foi possível concluir a revisão.', 'adam-comunidade' ) );
		}
		$manager = $wpdb->get_row( $wpdb->prepare( 'SELECT email FROM ' . Schema::managers_table() . ' WHERE id = %d', $revision->manager_id ) );
		if ( $manager ) {
			$record = $this->record( (string) $revision->entity_type, (int) $revision->entity_id );
			$this->emails->send(
				'approve' === $decision ? 'manager_revision_approved' : ( 'reject' === $decision ? 'manager_revision_rejected' : 'manager_information_requested' ),
				(string) $manager->email,
				array(
					'entity_name' => (string) ( $record->name ?? __( 'Registo da Comunidade', 'adam-comunidade' ) ),
					'admin_note'  => $note,
					'manager_url' => Portal::url(),
				)
			);
		}
		do_action( 'adam_comunidade_manager_revision_moderated', $revision_id, $status, $reviewer_id, $revision );
		if ( 'approved' === $status ) {
			do_action( 'adam_comunidade_manager_revision_approved', $revision_id, $revision, $reviewer_id );
			do_action( 'adam_comunidade_organisation_saved', (string) $revision->entity_type, (int) $revision->entity_id, $this->revision_payload( $revision ), 'manager_revision' );
		} elseif ( 'rejected' === $status ) {
			do_action( 'adam_comunidade_manager_revision_rejected', $revision_id, $revision, $reviewer_id, $note );
		}
		return true;
	}

	private function apply_revision( object $revision, bool $force_conflict = false ): bool|\WP_Error {
		$payload = json_decode( (string) $revision->payload, true );
		$payload = is_array( $payload ) ? $payload : array();
		$type    = (string) $revision->entity_type;
		$id      = (int) $revision->entity_id;
		$locked  = $this->lock_revision_entity( $type, $id );
		if ( is_wp_error( $locked ) ) {
			return $locked;
		}
		$current = $this->record( $type, $id );
		if ( ! $current ) {
			return new \WP_Error( 'not_found', __( 'O registo já não está disponível.', 'adam-comunidade' ) );
		}
		if ( ! $force_conflict && $this->revision_has_conflict( $revision ) ) {
			return new \WP_Error( 'published_version_changed', __( 'O registo publicado foi alterado depois desta proposta. Reveja o conflito antes de forçar a aprovação.', 'adam-comunidade' ) );
		}
		$relations = array_intersect_key( $payload, array_flip( array( 'amenity_ids', 'gallery_ids' ) ) );
		unset( $payload['amenity_ids'], $payload['gallery_ids'] );
		$input = Policy::decode_lists( $type, array_merge( (array) $current, $payload ) );
		if ( 'team' === $type ) {
			$repo = new Team_Repository();
			$data = ( new Team_Validator( $repo ) )->validate( $input, $id );
		} elseif ( 'field' === $type ) {
			$repo = new Field_Repository();
			$data = ( new Field_Validator( $repo ) )->validate( $input, $id );
		} elseif ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			$repo = new Directory_Repository();
			$data = Directory_Validator::sanitize( $type, $input );
		} else {
			$result = apply_filters( 'adam_comunidade_apply_manager_revision', null, $type, $id, $input, $relations, $revision );
			return is_wp_error( $result ) || true === $result
				? $result
				: new \WP_Error( 'unsupported_type', __( 'Este tipo de organização não é suportado.', 'adam-comunidade' ) );
		}
		if ( is_wp_error( $data ) || ! $repo->update( $id, $data ) ) {
			return is_wp_error( $data ) ? $data : new \WP_Error( 'save_failed', __( 'Não foi possível aplicar a revisão.', 'adam-comunidade' ) );
		}
		if ( 'field' === $type && isset( $relations['amenity_ids'] ) ) {
			if ( ! $repo->sync_amenities( $id, $relations['amenity_ids'] ) ) {
				return new \WP_Error( 'amenities_failed', __( 'Não foi possível aplicar as alterações às comodidades.', 'adam-comunidade' ) );
			}
		}
		if ( 'field' === $type && isset( $relations['gallery_ids'] ) ) {
			if ( ! $repo->sync_gallery( $id, array_map( static fn( int $attachment_id ): array => array( 'id' => $attachment_id, 'caption' => '' ), $relations['gallery_ids'] ) ) ) {
				return new \WP_Error( 'gallery_failed', __( 'Não foi possível aplicar as alterações à galeria.', 'adam-comunidade' ) );
			}
		}
		if ( in_array( $type, array( 'partner', 'institution' ), true ) && isset( $relations['gallery_ids'] ) ) {
			if ( ! $repo->sync_gallery( $id, array_map( static fn( int $attachment_id ): array => array( 'id' => $attachment_id, 'caption' => '' ), $relations['gallery_ids'] ) ) ) {
				return new \WP_Error( 'gallery_failed', __( 'Não foi possível aplicar as alterações à galeria.', 'adam-comunidade' ) );
			}
		}
		return true;
	}

	/**
	 * Locks the published row until the surrounding moderation transaction ends.
	 *
	 * Future entity adapters can return true from the filter after acquiring
	 * their own equivalent lock.
	 */
	private function lock_revision_entity( string $type, int $id ): true|\WP_Error {
		global $wpdb;
		$table = match ( $type ) {
			'team'  => \ADAM\Comunidade\Teams\Schema::teams_table(),
			'field' => \ADAM\Comunidade\Fields\Schema::fields_table(),
			'partner', 'institution' => \ADAM\Comunidade\Directory\Schema::entries_table(),
			default => '',
		};
		if ( $table ) {
			$sql = 'SELECT id FROM ' . $table . ' WHERE id=%d';
			$args = array( $id );
			if ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
				$sql   .= ' AND entity_type=%s';
				$args[] = $type;
			}
			$locked_id = $wpdb->get_var( $wpdb->prepare( $sql . ' FOR UPDATE', ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $locked_id ? true : new \WP_Error( 'not_found', __( 'O registo já não está disponível.', 'adam-comunidade' ) );
		}
		$result = apply_filters( 'adam_comunidade_lock_manager_revision_entity', null, $type, $id );
		return true === $result
			? true
			: new \WP_Error( 'unsupported_type', __( 'Este tipo de organização não é suportado.', 'adam-comunidade' ) );
	}

	/**
	 * Captures only manager-editable fields and moderated relationships.
	 *
	 * @return array<string,mixed>
	 */
	private function snapshot( string $type, object $record ): array {
		$data = Policy::decode_lists( $type, (array) $record );
		$data = array_intersect_key( $data, array_flip( Policy::editable_fields( $type ) ) );
		if ( 'field' === $type ) {
			$repo = new Field_Repository();
			$data['amenity_ids'] = $repo->amenity_ids( (int) $record->id );
			$data['gallery_ids'] = array_map(
				static fn( object $item ): int => absint( $item->attachment_id ?? 0 ),
				$repo->gallery( (int) $record->id )
			);
		} elseif ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			$data['gallery_ids'] = array_map(
				static fn( object $item ): int => absint( $item->attachment_id ?? 0 ),
				( new Directory_Repository() )->gallery( (int) $record->id )
			);
		}
		return $this->normalize_revision_payload( $type, $data );
	}

	/**
	 * Normalizes JSON lists and media relations without losing gallery order.
	 *
	 * @param array<string,mixed> $payload Revision values.
	 * @return array<string,mixed>
	 */
	private function normalize_revision_payload( string $type, array $payload ): array {
		$payload = Policy::decode_lists( $type, $payload );
		foreach ( array( 'gallery', 'gallery_ids', 'amenity_ids' ) as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$payload[ $key ] = array_values( array_unique( array_filter( array_map( 'absint', (array) $payload[ $key ] ) ) ) );
			}
		}
		if ( isset( $payload['amenity_ids'] ) ) {
			sort( $payload['amenity_ids'], SORT_NUMERIC );
		}
		foreach ( array( 'playing_styles', 'equipment_tags' ) as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				$payload[ $key ] = array_values( array_unique( array_map( 'sanitize_key', (array) $payload[ $key ] ) ) );
			}
		}
		return $payload;
	}

	/**
	 * Creates a stable hash while preserving intentional gallery ordering.
	 *
	 * @param array<string,mixed> $payload Revision values.
	 */
	private function payload_hash( array $payload ): string {
		return hash( 'sha256', (string) wp_json_encode( $this->canonicalize( $payload ) ) );
	}

	private function canonicalize( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			if ( ! array_is_list( $value ) ) {
				ksort( $value );
			}
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->canonicalize( $item );
			}
			return $value;
		}
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	private function is_empty_revision_value( mixed $value ): bool {
		if ( is_array( $value ) ) {
			return array() === $value;
		}
		return null === $value || '' === trim( (string) $value );
	}

	/**
	 * Finds the canonical manager identity for a normalized email address.
	 */
	private function manager_by_email( string $email ): ?object {
		global $wpdb;
		if ( ! is_email( $email ) ) {
			return null;
		}
		$manager = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::managers_table() . ' WHERE email = %s LIMIT 1',
				strtolower( $email )
			)
		);
		return is_object( $manager ) ? $manager : null;
	}

	/**
	 * Creates the single current activation token for one manager account.
	 */
	private function create_invitation_token( int $manager_id, string $entity_type, int $entity_id, string $now ): string|\WP_Error {
		global $wpdb;
		$raw = $this->new_token();
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$inserted = $wpdb->insert(
			Schema::invitations_table(),
			array(
				'manager_id' => $manager_id,
				'purpose'    => 'invitation',
				'entity_type'=> sanitize_key( $entity_type ),
				'entity_id'  => $entity_id,
				'token_hash' => hash( 'sha256', $raw ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + Config::manager_security()['invitation_ttl'] ),
				'created_at' => $now,
			)
		);
		if ( false === $inserted ) {
			Logger::error( 'community_manager_invitation_insert_failed', array( 'manager_id' => $manager_id, 'entity_type' => $entity_type, 'entity_id' => $entity_id ) );
			return new \WP_Error( 'invitation_failed', __( 'Não foi possível criar o convite de Gestor.', 'adam-comunidade' ) );
		}
		$invitation_id = (int) $wpdb->insert_id;
		$superseded = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::invitations_table() . " SET used_at = %s WHERE manager_id = %d AND purpose = 'invitation' AND used_at IS NULL AND id <> %d",
				$now,
				$manager_id,
				$invitation_id
			)
		);
		if ( false === $superseded ) {
			$wpdb->delete( Schema::invitations_table(), array( 'id' => $invitation_id ) );
			Logger::error( 'community_manager_invitation_supersede_failed', array( 'manager_id' => $manager_id ) );
			return new \WP_Error( 'invitation_failed', __( 'Não foi possível concluir o convite de Gestor.', 'adam-comunidade' ) );
		}
		do_action( 'adam_comunidade_manager_invited', $manager_id, $entity_type, $entity_id, $invitation_id );
		return Portal::activation_url( $raw );
	}

	/**
	 * Creates a cryptographically secure opaque token.
	 *
	 * @return string|\WP_Error
	 */
	private function new_token(): string|\WP_Error {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $throwable ) {
			Logger::error( 'community_manager_token_generation_failed', array( 'exception' => get_class( $throwable ) ) );
			return new \WP_Error( 'token_generation_failed', __( 'Não foi possível criar um pedido seguro. Tente novamente.', 'adam-comunidade' ) );
		}
	}

}
