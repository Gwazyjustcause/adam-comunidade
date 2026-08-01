<?php
/**
 * Owner notification centre.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Options as Field_Options;
use ADAM\Comunidade\Teams\Options as Team_Options;

/**
 * Creates in-product notices for listing and time-sensitive changes.
 */
final class Notifications {
	public function register(): void {
		add_action( 'adam_comunidade_team_saved', array( $this, 'team_changed' ), 10, 2 );
		add_action( 'adam_comunidade_field_saved', array( $this, 'field_changed' ), 10, 2 );
		if ( wp_next_scheduled( 'adam_comunidade_notification_scan' ) ) {
			wp_clear_scheduled_hook( 'adam_comunidade_notification_scan' );
		}
	}

	public function team_changed( int $id, array $data ): void {
		$status = Team_Options::normalize_recruitment_status( $data['recruitment_status'] ?? '' );
		$this->owners( 'team', $id, __( 'Perfil da equipa atualizado', 'adam-comunidade' ), sprintf( __( 'O estado do recrutamento é agora: %s.', 'adam-comunidade' ), Team_Options::recruitment_statuses()[ $status ] ?? __( 'Não indicado', 'adam-comunidade' ) ) );
	}

	public function field_changed( int $id, array $data ): void {
		$status = sanitize_key( $data['availability'] ?? 'open' );
		$this->owners( 'field', $id, __( 'Perfil do campo atualizado', 'adam-comunidade' ), sprintf( __( 'A disponibilidade é agora: %s.', 'adam-comunidade' ), Field_Options::availability_statuses()[ $status ] ?? __( 'Não indicada', 'adam-comunidade' ) ) );
	}

	private function owners( string $type, int $id, string $title, string $message ): void {
		global $wpdb;
		$users = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . Schema::owners_table() . ' WHERE object_type = %s AND object_id = %d AND status = %s', $type, $id, 'verified' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $users as $user_id ) {
			$this->add_once( (int) $user_id, $title, $message, home_url( '/painel-comunidade/' ) );
		}
	}

	private function add_once( int $user_id, string $title, string $message, string $url ): void {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::notifications_table() . ' WHERE user_id = %d AND title = %s AND message = %s AND created_at >= %s LIMIT 1', $user_id, $title, $message, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $exists ) {
			$wpdb->insert( Schema::notifications_table(), array( 'user_id' => $user_id, 'title' => $title, 'message' => $message, 'action_url' => esc_url_raw( $url ), 'is_read' => 0, 'created_at' => current_time( 'mysql', true ) ) );
		}
	}
}
