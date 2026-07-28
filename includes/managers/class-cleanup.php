<?php
/**
 * Community Manager data lifecycle cleanup.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Managers;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Config;
use ADAM\Comunidade\Logger;

/**
 * Removes expired credentials and relational orphans.
 */
final class Cleanup {
	public const HOOK = 'adam_comunidade_manager_cleanup';

	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public function run(): void {
		global $wpdb;

		$security     = Config::manager_security();
		$now          = current_time( 'mysql', true );
		$history_cut  = gmdate( 'Y-m-d H:i:s', time() - $security['invitation_history_ttl'] );
		$reset_cut    = gmdate( 'Y-m-d H:i:s', time() - $security['password_reset_history_ttl'] );
		$manager_cut  = gmdate( 'Y-m-d H:i:s', time() - $security['unclaimed_manager_ttl'] );
		$stale_review = gmdate( 'Y-m-d H:i:s', time() - $security['processing_recovery_ttl'] );
		$managers     = Schema::managers_table();
		$assignments  = Schema::assignments_table();
		$invitations  = Schema::invitations_table();
		$sessions     = Schema::sessions_table();
		$revisions    = Schema::revisions_table();
		$teams        = \ADAM\Comunidade\Teams\Schema::teams_table();
		$fields       = \ADAM\Comunidade\Fields\Schema::fields_table();
		$directory    = \ADAM\Comunidade\Directory\Schema::entries_table();

		$queries = array(
			$wpdb->prepare( "DELETE FROM {$sessions} WHERE expires_at <= %s", $now ),
			"DELETE s FROM {$sessions} s LEFT JOIN {$managers} m ON m.id=s.manager_id WHERE m.id IS NULL OR m.status<>'active'",
			"DELETE i FROM {$invitations} i LEFT JOIN {$managers} m ON m.id=i.manager_id WHERE m.id IS NULL",
			"DELETE a FROM {$assignments} a LEFT JOIN {$managers} m ON m.id=a.manager_id WHERE m.id IS NULL",
			"DELETE r FROM {$revisions} r LEFT JOIN {$managers} m ON m.id=r.manager_id WHERE m.id IS NULL",
			$wpdb->prepare( "UPDATE {$assignments} a LEFT JOIN {$teams} e ON e.id=a.entity_id SET a.status='removed',a.updated_at=%s WHERE a.entity_type='team' AND a.status='active' AND e.id IS NULL", $now ),
			$wpdb->prepare( "UPDATE {$assignments} a LEFT JOIN {$fields} e ON e.id=a.entity_id SET a.status='removed',a.updated_at=%s WHERE a.entity_type='field' AND a.status='active' AND e.id IS NULL", $now ),
			$wpdb->prepare( "UPDATE {$assignments} a LEFT JOIN {$directory} e ON e.id=a.entity_id AND e.entity_type=a.entity_type SET a.status='removed',a.updated_at=%s WHERE a.entity_type IN ('partner','institution') AND a.status='active' AND e.id IS NULL", $now ),
			$wpdb->prepare( "DELETE FROM {$invitations} WHERE purpose='password_reset' AND (used_at IS NOT NULL OR expires_at <= %s) AND created_at < %s", $now, $reset_cut ),
			$wpdb->prepare( "DELETE FROM {$invitations} WHERE purpose='invitation' AND (used_at IS NOT NULL OR expires_at <= %s) AND created_at < %s", $now, $history_cut ),
			$wpdb->prepare( "DELETE m FROM {$managers} m LEFT JOIN {$assignments} a ON a.manager_id=m.id AND a.status='active' WHERE m.status='invited' AND m.created_at < %s AND a.id IS NULL", $manager_cut ),
			$wpdb->prepare( "UPDATE {$revisions} SET status='pending',updated_at=%s WHERE status='processing' AND updated_at < %s", $now, $stale_review ),
		);

		foreach ( $queries as $index => $query ) {
			if ( false === $wpdb->query( $query ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				Logger::error( 'community_manager_cleanup_failed', array( 'operation' => (int) $index + 1 ) );
			}
		}
	}
}
