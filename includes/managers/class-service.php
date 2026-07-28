<?php
/**
 * Community Manager domain service.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Email_Service;
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
	public const INVITE_TTL = 14 * DAY_IN_SECONDS;

	public function __construct( private ?Email_Service $emails = null ) {
		$this->emails = $emails ?? new Email_Service();
	}

	/**
	 * Creates an explicit assignment and a new single-use invitation.
	 *
	 * This deliberately queries only the Community Manager tables.
	 */
	public function invite( string $email, string $entity_type, int $entity_id ): string|\WP_Error {
		global $wpdb;
		$email       = sanitize_email( $email );
		$entity_type = sanitize_key( $entity_type );
		if ( ! is_email( $email ) || ! in_array( $entity_type, array( 'team', 'field', 'partner', 'institution' ), true ) || $entity_id < 1 ) {
			return new \WP_Error( 'invalid_invitation', __( 'Não foi possível criar o convite de Gestor.', 'adam-comunidade' ) );
		}

		if ( ! $this->record( $entity_type, $entity_id ) ) {
			return new \WP_Error( 'invalid_assignment_record', __( 'A organização selecionada já não está disponível.', 'adam-comunidade' ) );
		}

		$manager = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::managers_table() . ' WHERE email = %s', $email ) );
		$now     = current_time( 'mysql', true );
		if ( ! $manager ) {
			$wpdb->insert(
				Schema::managers_table(),
				array( 'email' => $email, 'status' => 'invited', 'created_at' => $now, 'updated_at' => $now )
			);
			$manager_id = (int) $wpdb->insert_id;
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
		if ( $manager && 'active' === $manager->status ) {
			return Portal::url();
		}
		$raw = $this->new_token();
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$inserted = $wpdb->insert(
			Schema::invitations_table(),
			array(
				'manager_id' => $manager_id,
				'purpose'    => 'invitation',
				'entity_type'=> $entity_type,
				'entity_id'  => $entity_id,
				'token_hash' => hash( 'sha256', $raw ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::INVITE_TTL ),
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
				'UPDATE ' . Schema::invitations_table() . " SET used_at = %s WHERE manager_id = %d AND purpose = 'invitation' AND entity_type = %s AND entity_id = %d AND used_at IS NULL AND id <> %d",
				$now,
				$manager_id,
				$entity_type,
				$entity_id,
				$invitation_id
			)
		);
		if ( false === $superseded ) {
			$wpdb->delete( Schema::invitations_table(), array( 'id' => $invitation_id ) );
			Logger::error( 'community_manager_invitation_supersede_failed', array( 'manager_id' => $manager_id ) );
			return new \WP_Error( 'invitation_failed', __( 'Não foi possível concluir o convite de Gestor.', 'adam-comunidade' ) );
		}
		return Portal::activation_url( $raw );
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
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
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
	public function assign_existing( int $manager_id, string $type, int $entity_id ): true|\WP_Error {
		global $wpdb;
		$type = sanitize_key( $type );
		if ( $manager_id < 1 || $entity_id < 1 || ! in_array( $type, array( 'team', 'field', 'partner', 'institution' ), true ) || ! $this->record( $type, $entity_id ) ) {
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
					$result = $this->assign_existing( $target_manager_id, (string) $assignment->entity_type, (int) $assignment->entity_id );
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
			return true;
		} catch ( \Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			Logger::error( 'community_manager_delete_failed', array( 'manager_id' => $manager_id, 'operation' => sanitize_key( $throwable->getMessage() ) ) );
			return new \WP_Error( 'manager_delete_failed', __( 'Não foi possível eliminar o Gestor. Nenhuma alteração foi aplicada.', 'adam-comunidade' ) );
		}
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
		return $names;
	}

	public function record( string $type, int $id ): ?object {
		if ( 'team' === $type ) {
			return ( new Team_Repository() )->find( $id );
		}
		if ( 'field' === $type ) {
			return ( new Field_Repository() )->find( $id );
		}
		if ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			return ( new Directory_Repository() )->find( $id, $type );
		}
		return null;
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
		$merged = $this->decode_lists( $type, array_merge( (array) $current, $input ) );
		if ( 'team' === $type ) {
			$data = ( new Team_Validator( new Team_Repository() ) )->validate( $merged, $id );
		} elseif ( 'field' === $type ) {
			$data = ( new Field_Validator( new Field_Repository() ) )->validate( $merged, $id );
		} elseif ( in_array( $type, array( 'partner', 'institution' ), true ) ) {
			$data = Directory_Validator::sanitize( $type, $merged );
		} else {
			return new \WP_Error( 'unsupported_type', __( 'Este tipo de organização não é suportado.', 'adam-comunidade' ) );
		}
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$allowed = $this->allowed_fields( $type );
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
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			Schema::revisions_table(),
			array(
				'manager_id' => $manager_id,
				'entity_type'=> $type,
				'entity_id'  => $id,
				'payload'    => wp_json_encode( $payload ),
				'status'     => 'pending',
				'submitted_at'=> $now,
				'updated_at' => $now,
			)
		);
		if ( false === $inserted ) {
			Logger::error( 'community_manager_revision_insert_failed', array( 'manager_id' => $manager_id, 'entity_type' => $type, 'entity_id' => $id ) );
			return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
		}
		$revision_id = (int) $wpdb->insert_id;
		$superseded = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::revisions_table() . " SET status = 'superseded', updated_at = %s WHERE manager_id = %d AND entity_type = %s AND entity_id = %d AND status IN ('pending','needs_info') AND id <> %d",
				$now,
				$manager_id,
				$type,
				$id,
				$revision_id
			)
		);
		if ( false === $superseded ) {
			$wpdb->delete( Schema::revisions_table(), array( 'id' => $revision_id ) );
			Logger::error( 'community_manager_revision_supersede_failed', array( 'manager_id' => $manager_id, 'entity_type' => $type, 'entity_id' => $id ) );
			return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
		}
		return $revision_id;
	}

	public function moderate_revision( int $revision_id, string $decision, string $note, int $reviewer_id ): bool|\WP_Error {
		global $wpdb;
		$revision = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::revisions_table() . ' WHERE id = %d', $revision_id ) );
		if ( ! $revision || ! in_array( $revision->status, array( 'pending', 'needs_info' ), true ) ) {
			return new \WP_Error( 'revision_unavailable', __( 'A revisão já não está disponível.', 'adam-comunidade' ) );
		}
		$status = array( 'approve' => 'approved', 'reject' => 'rejected', 'info' => 'needs_info' )[ $decision ] ?? '';
		if ( ! $status ) {
			return new \WP_Error( 'invalid_decision', __( 'A decisão selecionada não é válida.', 'adam-comunidade' ) );
		}
		if ( in_array( $decision, array( 'reject', 'info' ), true ) && '' === trim( $note ) ) {
			return new \WP_Error( 'note_required', __( 'Indique ao Gestor o motivo da decisão ou a informação necessária.', 'adam-comunidade' ) );
		}
		$original_status = (string) $revision->status;
		$now             = current_time( 'mysql', true );
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::revisions_table() . " SET status='processing',updated_at=%s WHERE id=%d AND status IN ('pending','needs_info')",
				$now,
				$revision_id
			)
		);
		if ( 1 !== $claimed ) {
			return new \WP_Error( 'revision_unavailable', __( 'A revisão já não está disponível.', 'adam-comunidade' ) );
		}
		if ( 'approve' === $decision ) {
			$result = $this->apply_revision( $revision );
			if ( is_wp_error( $result ) ) {
				$wpdb->update(
					Schema::revisions_table(),
					array( 'status' => $original_status, 'updated_at' => current_time( 'mysql', true ) ),
					array( 'id' => $revision_id, 'status' => 'processing' )
				);
				return $result;
			}
		}
		$updated = $wpdb->update(
			Schema::revisions_table(),
			array( 'status' => $status, 'admin_note' => sanitize_textarea_field( $note ), 'reviewed_by' => $reviewer_id, 'reviewed_at' => $now, 'updated_at' => $now ),
			array( 'id' => $revision_id, 'status' => 'processing' )
		);
		if ( false === $updated ) {
			Logger::error( 'community_manager_revision_moderation_failed', array( 'revision_id' => $revision_id ) );
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
		return true;
	}

	private function apply_revision( object $revision ): bool|\WP_Error {
		$payload = json_decode( (string) $revision->payload, true );
		$payload = is_array( $payload ) ? $payload : array();
		$type    = (string) $revision->entity_type;
		$id      = (int) $revision->entity_id;
		$current = $this->record( $type, $id );
		if ( ! $current ) {
			return new \WP_Error( 'not_found', __( 'O registo já não está disponível.', 'adam-comunidade' ) );
		}
		$relations = array_intersect_key( $payload, array_flip( array( 'amenity_ids', 'gallery_ids' ) ) );
		unset( $payload['amenity_ids'], $payload['gallery_ids'] );
		$input = $this->decode_lists( $type, array_merge( (array) $current, $payload ) );
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
			return new \WP_Error( 'unsupported_type', __( 'Este tipo de organização não é suportado.', 'adam-comunidade' ) );
		}
		if ( is_wp_error( $data ) || ! $repo->update( $id, $data ) ) {
			return is_wp_error( $data ) ? $data : new \WP_Error( 'save_failed', __( 'Não foi possível aplicar a revisão.', 'adam-comunidade' ) );
		}
		if ( 'field' === $type && isset( $relations['amenity_ids'] ) ) {
			$repo->sync_amenities( $id, $relations['amenity_ids'] );
		}
		if ( 'field' === $type && isset( $relations['gallery_ids'] ) ) {
			$repo->sync_gallery( $id, array_map( static fn( int $attachment_id ): array => array( 'id' => $attachment_id, 'caption' => '' ), $relations['gallery_ids'] ) );
		}
		if ( in_array( $type, array( 'partner', 'institution' ), true ) && isset( $relations['gallery_ids'] ) ) {
			$repo->sync_gallery( $id, array_map( static fn( int $attachment_id ): array => array( 'id' => $attachment_id, 'caption' => '' ), $relations['gallery_ids'] ) );
		}
		return true;
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

	private function allowed_fields( string $type ): array {
		$common = array( 'name', 'cover_id', 'short_description', 'full_description', 'district', 'municipality', 'address', 'latitude', 'longitude', 'maps_url', 'website', 'facebook', 'instagram', 'email', 'phone', 'playing_styles' );
		if ( 'team' === $type ) {
			return array_merge( $common, array( 'short_name', 'logo_id', 'gallery', 'team_colour', 'discord', 'youtube', 'tiktok', 'founded', 'members', 'recruitment_status', 'recruitment_min_age', 'recruitment_experience', 'recruitment_equipment', 'recruitment_training', 'equipment_tags' ) );
		}
		if ( 'field' === $type ) {
			return array_merge( $common, array( 'availability', 'rules', 'opening_hours', 'max_players', 'min_players', 'recommended_players' ) );
		}
		return array( 'name', 'logo_id', 'cover_id', 'short_description', 'full_description', 'website', 'facebook', 'instagram', 'email', 'phone', 'address', 'district', 'latitude', 'longitude', 'category', 'benefits', 'member_benefits', 'country', 'popular_products' );
	}

	private function decode_lists( string $type, array $input ): array {
		$keys = 'team' === $type
			? array( 'gallery', 'playing_styles', 'equipment_tags' )
			: array( 'playing_styles' );
		foreach ( $keys as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) {
				$decoded = json_decode( $input[ $key ], true );
				$input[ $key ] = is_array( $decoded ) ? $decoded : array();
			}
		}
		return $input;
	}
}
