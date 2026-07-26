<?php
/**
 * Community calendar.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Admin\Router as Admin_Router;
use ADAM\Comunidade\Helpers;

/**
 * Publishes lightweight community announcements without coupling to Events.
 */
final class Calendar {
	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'template_include', array( $this, 'template' ), 50 );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 40 );
		Admin_Router::register_page( 'calendar', array( 'title' => __( 'Calendar', 'adam-comunidade' ), 'controller' => $this, 'method' => 'admin_page' ) );
		add_action( 'admin_post_adam_calendar_save', array( $this, 'save' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^calendario/?$', 'index.php?adam_calendar=1', 'top' );
	}

	public function query_vars( array $vars ): array {
		$vars[] = 'adam_calendar';
		return $vars;
	}

	public function template( string $template ): string {
		return get_query_var( 'adam_calendar' ) ? Templates::locate( 'experience/calendar.php' ) : $template;
	}

	public function title( string $title ): string {
		return get_query_var( 'adam_calendar' ) ? __( 'Community Calendar', 'adam-comunidade' ) : $title;
	}

	public function assets(): void {
		if ( get_query_var( 'adam_calendar' ) ) {
			wp_enqueue_style( 'adam-comunidade' );
			wp_enqueue_style( 'adam-experience', Helpers::url( 'assets/css/experience.css' ), array( 'adam-comunidade' ), ADAM_COMUNIDADE_VERSION );
			wp_enqueue_style( 'adam-comunidade-directory', Helpers::url( 'assets/css/directory-public.css' ), array( 'adam-experience' ), ADAM_COMUNIDADE_VERSION );
		}
	}

	public function admin_page(): void {
		global $wpdb;
		$entries = $wpdb->get_results( 'SELECT * FROM ' . Schema::calendar_table() . ' ORDER BY start_at DESC LIMIT 100' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$types   = self::types();
		require Helpers::path( 'admin/views/experience/calendar.php' );
	}

	public function save(): void {
		Admin_Router::authorize();
		check_admin_referer( 'adam_calendar_save' );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$type  = sanitize_key( wp_unslash( $_POST['entry_type'] ?? '' ) );
		$start = self::datetime( wp_unslash( $_POST['start_at'] ?? '' ) );
		$end   = self::datetime( wp_unslash( $_POST['end_at'] ?? '' ) );
		if ( ! $title || ! isset( self::types()[ $type ] ) || ! $start ) {
			wp_die( esc_html__( 'Complete the required calendar fields.', 'adam-comunidade' ) );
		}
		global $wpdb;
		$now = current_time( 'mysql', true );
		$base_slug = sanitize_title( $title ) ?: 'calendar-entry';
		$slug      = $base_slug;
		$suffix    = 2;
		while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::calendar_table() . ' WHERE slug = %s', $slug ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$slug = $base_slug . '-' . $suffix++;
		}
		$wpdb->insert( Schema::calendar_table(), array( 'title' => $title, 'slug' => $slug, 'entry_type' => $type, 'summary' => sanitize_textarea_field( wp_unslash( $_POST['summary'] ?? '' ) ), 'start_at' => $start, 'end_at' => $end, 'status' => 'published', 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) );
		do_action( 'adam_comunidade_calendar_entry_published', (int) $wpdb->insert_id );
		wp_safe_redirect( Admin_Router::page_url( 'calendar' ) );
		exit;
	}

	/**
	 * Returns upcoming public entries.
	 *
	 * @return object[]
	 */
	public static function upcoming( int $limit = 50 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::calendar_table() . ' WHERE status = %s AND start_at >= %s ORDER BY start_at ASC LIMIT %d', 'published', current_time( 'mysql', true ), max( 1, min( 100, $limit ) ) ) ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Calendar entry types are extensible by future modules.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return apply_filters( 'adam_comunidade_calendar_types', array( 'announcement' => __( 'Announcement', 'adam-comunidade' ), 'open_day' => __( 'Open day', 'adam-comunidade' ), 'recruitment' => __( 'Recruitment', 'adam-comunidade' ), 'training' => __( 'Training', 'adam-comunidade' ) ) );
	}

	private static function datetime( string $value ): ?string {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ) : null;
	}
}
