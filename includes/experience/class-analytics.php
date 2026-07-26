<?php
/**
 * Privacy-conscious aggregate analytics.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Managed_Pages;

/**
 * Records counters without storing visitor identities.
 */
final class Analytics {
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'track_view' ), 20 );
		add_action( 'wp_ajax_adam_track_interaction', array( $this, 'track_interaction' ) );
		add_action( 'wp_ajax_nopriv_adam_track_interaction', array( $this, 'track_interaction' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 40 );
	}

	public function assets(): void {
		global $post;
		$content = $post instanceof \WP_Post ? $post->post_content : '';
		if ( ! get_query_var( 'adam_team_slug' ) && ! get_query_var( 'adam_field_slug' ) && ! get_query_var( 'adam_directory_type' ) && ! Managed_Pages::is_current( 'community' ) && ! get_query_var( 'adam_region' ) && ! str_contains( $content, '[adam_' ) ) {
			return;
		}
		wp_enqueue_script( 'adam-community-analytics', \ADAM\Comunidade\Helpers::url( 'assets/js/analytics.js' ), array(), ADAM_COMUNIDADE_VERSION, true );
		wp_localize_script( 'adam-community-analytics', 'adamAnalytics', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'adam_experience' ) ) );
	}

	public static function record( string $event, string $object_type = '', int $object_id = 0, string $dimension = '' ): void {
		global $wpdb;
		$event       = sanitize_key( $event );
		$object_type = sanitize_key( $object_type );
		$dimension   = substr( sanitize_text_field( $dimension ), 0, 191 );
		if ( ! $event ) {
			return;
		}
		$table = Schema::metrics_table();
		$hash  = md5( strtolower( $dimension ) );
		$sql   = $wpdb->prepare(
			"INSERT INTO {$table} (event_type,object_type,object_id,dimension_hash,dimension,total,updated_at)
			VALUES (%s,%s,%d,%s,%s,1,%s)
			ON DUPLICATE KEY UPDATE total=total+1,updated_at=VALUES(updated_at)",
			$event,
			$object_type,
			$object_id,
			$hash,
			$dimension,
			current_time( 'mysql', true )
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function track_view(): void {
		$directory_type = sanitize_key( (string) get_query_var( 'adam_directory_type' ) );
		$mapping = array(
			'team'    => array( 'adam_team_slug', 'adam_teams' ),
			'field'   => array( 'adam_field_slug', 'adam_fields' ),
			$directory_type ?: 'directory' => array( 'adam_directory_slug', 'adam_directory_entries' ),
		);
		foreach ( $mapping as $type => $data ) {
			$slug = sanitize_title( (string) get_query_var( $data[0] ) );
			if ( $slug ) {
				global $wpdb;
				if ( 'adam_directory_entries' === $data[1] && $directory_type ) {
					$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}{$data[1]} WHERE slug=%s AND entity_type=%s LIMIT 1", $slug, $directory_type ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				} else {
					$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}{$data[1]} WHERE slug=%s LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				self::record( 'view', $type, $id );
				break;
			}
		}
		if ( is_singular( 'adam_news' ) ) {
			self::record( 'view', 'news', (int) get_queried_object_id() );
		}
	}

	public function track_interaction(): void {
		check_ajax_referer( 'adam_experience', 'nonce' );
		self::record(
			sanitize_key( $_POST['event_type'] ?? 'click' ),
			sanitize_key( $_POST['object_type'] ?? '' ),
			absint( $_POST['object_id'] ?? 0 ),
			sanitize_text_field( wp_unslash( $_POST['dimension'] ?? '' ) )
		);
		wp_send_json_success();
	}

	/**
	 * Returns top aggregate rows.
	 *
	 * @return object[]
	 */
	public static function top( string $event, int $limit = 10 ): array {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . Schema::metrics_table() . ' WHERE event_type=%s ORDER BY total DESC LIMIT %d', $event, $limit );
		return $wpdb->get_results( $sql ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
