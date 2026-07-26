<?php
/**
 * Owner notification centre.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Creates in-product notices for listing and time-sensitive changes.
 */
final class Notifications {
	public function register(): void {
		add_action( 'adam_comunidade_team_saved', array( $this, 'team_changed' ), 10, 2 );
		add_action( 'adam_comunidade_field_saved', array( $this, 'field_changed' ), 10, 2 );
		add_action( 'adam_comunidade_notification_scan', array( $this, 'scan_calendar' ) );
		if ( ! wp_next_scheduled( 'adam_comunidade_notification_scan' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'adam_comunidade_notification_scan' );
		}
	}

	public function team_changed( int $id, array $data ): void {
		$this->owners( 'team', $id, __( 'Team profile updated', 'adam-comunidade' ), sprintf( __( 'Recruitment is now: %s.', 'adam-comunidade' ), sanitize_text_field( $data['recruitment_status'] ?? '' ) ) );
	}

	public function field_changed( int $id, array $data ): void {
		$this->owners( 'field', $id, __( 'Field profile updated', 'adam-comunidade' ), sprintf( __( 'Availability is now: %s.', 'adam-comunidade' ), sanitize_text_field( $data['availability'] ?? 'open' ) ) );
	}

	public function scan_calendar(): void {
		global $wpdb;
		$until   = gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS );
		$entries = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::calendar_table() . ' WHERE status = %s AND start_at BETWEEN %s AND %s', 'published', current_time( 'mysql', true ), $until ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$users   = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT user_id FROM " . Schema::owners_table() . " WHERE status = 'verified'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $entries as $entry ) {
			foreach ( $users as $user_id ) {
				$message = sprintf( __( '%1$s begins on %2$s.', 'adam-comunidade' ), $entry->title, wp_date( get_option( 'date_format' ), strtotime( $entry->start_at . ' UTC' ) ) );
				$this->add_once( $user_id, __( 'Upcoming community date', 'adam-comunidade' ), $message, home_url( '/calendario/' ) );
			}
		}
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
