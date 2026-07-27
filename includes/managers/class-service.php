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
		if ( ! $manager_id ) {
			return new \WP_Error( 'manager_create_failed', __( 'Não foi possível criar a conta de Gestor.', 'adam-comunidade' ) );
		}

		$assigned = $wpdb->replace(
			Schema::assignments_table(),
			array(
				'manager_id' => $manager_id,
				'entity_type'=> $entity_type,
				'entity_id'  => $entity_id,
				'status'     => 'active',
				'created_at' => $now,
			)
		);
		if ( false === $assigned ) {
			return new \WP_Error( 'assignment_failed', __( 'Não foi possível atribuir o registo ao Gestor.', 'adam-comunidade' ) );
		}
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::invitations_table() . " SET used_at = %s WHERE manager_id = %d AND purpose = 'invitation' AND entity_type = %s AND entity_id = %d AND used_at IS NULL",
				$now,
				$manager_id,
				$entity_type,
				$entity_id
			)
		);
		$raw = bin2hex( random_bytes( 32 ) );
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
			return new \WP_Error( 'invitation_failed', __( 'Não foi possível criar o convite de Gestor.', 'adam-comunidade' ) );
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
		if ( 'disabled' === $manager_status ) {
			return new \WP_Error( 'manager_disabled', __( 'Esta conta de Gestor está desativada.', 'adam-comunidade' ) );
		}
		if ( strlen( $password ) < 10 ) {
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
		if ( false === $activated ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . Schema::invitations_table() . ' SET used_at = NULL WHERE id = %d AND used_at = %s', (int) $invitation->id, $now ) );
			return new \WP_Error( 'activation_failed', __( 'Não foi possível ativar a conta de Gestor.', 'adam-comunidade' ) );
		}
		$wpdb->delete( Schema::sessions_table(), array( 'manager_id' => (int) $invitation->manager_id ) );
		return (int) $invitation->manager_id;
	}

	/**
	 * Creates a password-reset message without revealing whether an account exists.
	 */
	public function request_password_reset( string $email ): void {
		global $wpdb;
		$email   = sanitize_email( $email );
		$manager = is_email( $email )
			? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::managers_table() . ' WHERE email = %s AND status = %s', $email, 'active' ) )
			: null;
		if ( ! $manager ) {
			return;
		}
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::invitations_table() . " SET used_at = %s WHERE manager_id = %d AND purpose = 'password_reset' AND used_at IS NULL",
				$now,
				(int) $manager->id
			)
		);
		$raw = bin2hex( random_bytes( 32 ) );
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
			$this->emails->send(
				'manager_password_reset',
				$email,
				array( 'manager_reset_url' => Portal::recovery_url( $raw ) )
			);
		}
	}

	public function reset_password( string $token, string $password ): bool|\WP_Error {
		global $wpdb;
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Este pedido de recuperação não é válido.', 'adam-comunidade' ) );
		}
		if ( strlen( $password ) < 10 ) {
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
		if ( false === $updated ) {
			return new \WP_Error( 'reset_failed', __( 'Não foi possível atualizar a palavra-passe.', 'adam-comunidade' ) );
		}
		$wpdb->delete( Schema::sessions_table(), array( 'manager_id' => (int) $row->manager_id ) );
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
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::revisions_table() . " SET status = 'superseded', updated_at = %s WHERE manager_id = %d AND entity_type = %s AND entity_id = %d AND status IN ('pending','needs_info')",
				$now,
				$manager_id,
				$type,
				$id
			)
		);
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
			return new \WP_Error( 'revision_failed', __( 'Não foi possível guardar as alterações para revisão.', 'adam-comunidade' ) );
		}
		return (int) $wpdb->insert_id;
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
		if ( 'approve' === $decision ) {
			$result = $this->apply_revision( $revision );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$now = current_time( 'mysql', true );
		$wpdb->update(
			Schema::revisions_table(),
			array( 'status' => $status, 'admin_note' => sanitize_textarea_field( $note ), 'reviewed_by' => $reviewer_id, 'reviewed_at' => $now, 'updated_at' => $now ),
			array( 'id' => $revision_id )
		);
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
