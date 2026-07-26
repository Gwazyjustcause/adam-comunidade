<?php
/**
 * Community calendar.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

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
		add_action( 'adam_comunidade_admin_menu', array( $this, 'menu' ), 37, 2 );
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

	public function menu( string $parent, string $capability ): void {
		add_submenu_page( $parent, __( 'Calendar', 'adam-comunidade' ), __( 'Calendar', 'adam-comunidade' ), $capability, 'adam-comunidade-calendar', array( $this, 'admin_page' ) );
	}

	public function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage the calendar.', 'adam-comunidade' ) );
		}
		global $wpdb;
		$entries = $wpdb->get_results( 'SELECT * FROM ' . Schema::calendar_table() . ' ORDER BY start_at DESC LIMIT 100' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Community Calendar', 'adam-comunidade' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adam-card">
				<input type="hidden" name="action" value="adam_calendar_save"><?php wp_nonce_field( 'adam_calendar_save' ); ?>
				<table class="form-table"><tr><th><label for="adam-calendar-title"><?php esc_html_e( 'Title', 'adam-comunidade' ); ?></label></th><td><input class="regular-text" id="adam-calendar-title" name="title" required></td></tr>
				<tr><th><label for="adam-calendar-type"><?php esc_html_e( 'Type', 'adam-comunidade' ); ?></label></th><td><select id="adam-calendar-type" name="entry_type"><?php foreach ( self::types() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th><label for="adam-calendar-start"><?php esc_html_e( 'Starts', 'adam-comunidade' ); ?></label></th><td><input id="adam-calendar-start" name="start_at" type="datetime-local" required></td></tr>
				<tr><th><label for="adam-calendar-end"><?php esc_html_e( 'Ends', 'adam-comunidade' ); ?></label></th><td><input id="adam-calendar-end" name="end_at" type="datetime-local"></td></tr>
				<tr><th><label for="adam-calendar-summary"><?php esc_html_e( 'Summary', 'adam-comunidade' ); ?></label></th><td><textarea class="large-text" id="adam-calendar-summary" name="summary"></textarea></td></tr></table>
				<?php submit_button( __( 'Publish calendar entry', 'adam-comunidade' ) ); ?>
			</form>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Title', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Type', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Starts', 'adam-comunidade' ); ?></th></tr></thead><tbody><?php foreach ( $entries as $entry ) : ?><tr><td><?php echo esc_html( $entry->title ); ?></td><td><?php echo esc_html( self::types()[ $entry->entry_type ] ?? $entry->entry_type ); ?></td><td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->start_at . ' UTC' ) ) ); ?></td></tr><?php endforeach; ?></tbody></table>
		</div>
		<?php
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage the calendar.', 'adam-comunidade' ) );
		}
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
		wp_safe_redirect( admin_url( 'admin.php?page=adam-comunidade-calendar' ) );
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
