<?php
/**
 * Plugin health monitoring and safe repair.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Schema as Directory_Schema;
use ADAM\Comunidade\Fields\Schema as Fields_Schema;
use ADAM\Comunidade\Teams\Schema as Teams_Schema;

/**
 * Reports operational checks without exposing sensitive data.
 */
final class Health {
	public function register(): void {
		add_action( 'adam_comunidade_admin_menu', array( $this, 'menu' ), 45, 2 );
		add_action( 'admin_post_adam_health_repair', array( $this, 'repair' ) );
	}

	public function menu( string $parent, string $capability ): void {
		add_submenu_page( $parent, __( 'System Health', 'adam-comunidade' ), __( 'System Health', 'adam-comunidade' ), $capability, 'adam-comunidade-health', array( $this, 'page' ) );
	}

	public function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot view system health.', 'adam-comunidade' ) );
		}
		$checks = self::checks();
		?>
		<div class="wrap"><h1><?php esc_html_e( 'ADAM Comunidade Health', 'adam-comunidade' ); ?></h1>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Check', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Status', 'adam-comunidade' ); ?></th><th><?php esc_html_e( 'Details', 'adam-comunidade' ); ?></th></tr></thead><tbody>
		<?php foreach ( $checks as $check ) : ?><tr><td><?php echo esc_html( $check['label'] ); ?></td><td><span class="adam-badge adam-badge--<?php echo esc_attr( $check['ok'] ? 'success' : 'warning' ); ?>"><?php echo esc_html( $check['ok'] ? __( 'Healthy', 'adam-comunidade' ) : __( 'Attention', 'adam-comunidade' ) ); ?></span></td><td><?php echo esc_html( $check['detail'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="adam_health_repair"><?php wp_nonce_field( 'adam_health_repair' ); ?><?php submit_button( __( 'Run safe repair', 'adam-comunidade' ), 'secondary' ); ?></form></div>
		<?php
	}

	/**
	 * @return array<int,array{label:string,ok:bool,detail:string}>
	 */
	public static function checks(): array {
		global $wpdb, $wp_version;
		$tables = array( Teams_Schema::teams_table(), Teams_Schema::team_fields_table(), Fields_Schema::fields_table(), Directory_Schema::entries_table(), Schema::submissions_table(), Schema::owners_table(), Schema::notifications_table(), Schema::calendar_table() );
		$missing = array();
		foreach ( $tables as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				$missing[] = $table;
			}
		}
		$missing_images = $missing ? -1 : (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ('
			. 'SELECT logo_id AS attachment_id FROM ' . Teams_Schema::teams_table() . ' WHERE logo_id > 0 UNION ALL '
			. 'SELECT cover_id FROM ' . Teams_Schema::teams_table() . ' WHERE cover_id > 0 UNION ALL '
			. 'SELECT cover_id FROM ' . Fields_Schema::fields_table() . ' WHERE cover_id > 0 UNION ALL '
			. 'SELECT logo_id FROM ' . Directory_Schema::entries_table() . ' WHERE logo_id > 0 UNION ALL '
			. 'SELECT cover_id FROM ' . Directory_Schema::entries_table() . ' WHERE cover_id > 0'
			. ') media LEFT JOIN ' . $wpdb->posts . ' posts ON posts.ID = media.attachment_id WHERE posts.ID IS NULL'
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal schema tables only.
		$broken_team_fields = $missing ? -1 : (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . Teams_Schema::team_fields_table() . ' rel '
			. 'LEFT JOIN ' . Teams_Schema::teams_table() . ' teams ON teams.id = rel.team_id '
			. 'LEFT JOIN ' . Fields_Schema::fields_table() . ' fields ON fields.id = rel.field_id '
			. 'WHERE teams.id IS NULL OR fields.id IS NULL'
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal schema tables only.
		$rules = (array) get_option( 'rewrite_rules', array() );
		$administrator = get_role( 'administrator' );
		$checks = array(
			array( 'label' => __( 'Database tables', 'adam-comunidade' ), 'ok' => ! $missing, 'detail' => $missing ? sprintf( __( '%d table(s) missing.', 'adam-comunidade' ), count( $missing ) ) : __( 'All required tables exist.', 'adam-comunidade' ) ),
			array( 'label' => __( 'PHP version', 'adam-comunidade' ), 'ok' => version_compare( PHP_VERSION, '8.1', '>=' ), 'detail' => PHP_VERSION ),
			array( 'label' => __( 'WordPress version', 'adam-comunidade' ), 'ok' => version_compare( $wp_version, '6.8', '>=' ), 'detail' => $wp_version ),
			array( 'label' => __( 'Friendly URLs', 'adam-comunidade' ), 'ok' => isset( $rules['^comunidade/?$'], $rules['^equipas/?$'], $rules['^campos/?$'] ), 'detail' => __( 'Community, team and field rewrite routes.', 'adam-comunidade' ) ),
			array( 'label' => __( 'Cron', 'adam-comunidade' ), 'ok' => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON, 'detail' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? __( 'WP-Cron is disabled.', 'adam-comunidade' ) : __( 'WP-Cron is available.', 'adam-comunidade' ) ),
			array( 'label' => __( 'REST API', 'adam-comunidade' ), 'ok' => rest_url() && get_option( 'permalink_structure' ), 'detail' => rest_url( 'adam-comunidade/v2/' ) ),
			array( 'label' => __( 'Media references', 'adam-comunidade' ), 'ok' => 0 === $missing_images, 'detail' => $missing_images < 0 ? __( 'Database repair required before checking media.', 'adam-comunidade' ) : ( $missing_images ? sprintf( __( '%d missing image reference(s).', 'adam-comunidade' ), $missing_images ) : __( 'Image references resolve to Media Library records.', 'adam-comunidade' ) ) ),
			array( 'label' => __( 'Relationships', 'adam-comunidade' ), 'ok' => 0 === $broken_team_fields, 'detail' => $broken_team_fields < 0 ? __( 'Database repair required before checking relationships.', 'adam-comunidade' ) : ( $broken_team_fields ? sprintf( __( '%d broken team-field relationship(s).', 'adam-comunidade' ), $broken_team_fields ) : __( 'Team-field relationships are consistent.', 'adam-comunidade' ) ) ),
			array( 'label' => __( 'Permissions', 'adam-comunidade' ), 'ok' => $administrator && $administrator->has_cap( 'manage_options' ), 'detail' => __( 'Administrative capability mapping.', 'adam-comunidade' ) ),
			array( 'label' => __( 'ADAM Members', 'adam-comunidade' ), 'ok' => defined( 'ADAM_MEMBERS_VERSION' ) || class_exists( 'ADAM_Members' ), 'detail' => __( 'Optional integration', 'adam-comunidade' ) ),
			array( 'label' => __( 'ADAM Bot', 'adam-comunidade' ), 'ok' => defined( 'ADAM_BOT_VERSION' ) || class_exists( 'ADAM_Bot' ), 'detail' => __( 'Optional integration', 'adam-comunidade' ) ),
			array( 'label' => __( 'ADAM Events', 'adam-comunidade' ), 'ok' => defined( 'ADAM_EVENTS_VERSION' ) || class_exists( 'ADAM_Events' ), 'detail' => __( 'Optional integration', 'adam-comunidade' ) ),
		);
		return (array) apply_filters( 'adam_comunidade_health_checks', $checks );
	}

	public function repair(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot repair plugin data.', 'adam-comunidade' ) );
		}
		check_admin_referer( 'adam_health_repair' );
		Teams_Schema::install();
		Fields_Schema::install();
		Directory_Schema::install();
		Schema::install();
		global $wpdb;
		foreach ( array( Teams_Schema::teams_table() => array( 'logo_id', 'cover_id' ), Fields_Schema::fields_table() => array( 'cover_id' ), Directory_Schema::entries_table() => array( 'logo_id', 'cover_id' ) ) as $table => $columns ) {
			foreach ( $columns as $column ) {
				$wpdb->query( 'UPDATE ' . $table . ' records LEFT JOIN ' . $wpdb->posts . ' posts ON posts.ID = records.' . $column . ' SET records.' . $column . ' = 0 WHERE records.' . $column . ' > 0 AND posts.ID IS NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed internal table and column allowlist.
			}
		}
		$wpdb->query( 'DELETE rel FROM ' . Teams_Schema::team_fields_table() . ' rel LEFT JOIN ' . Teams_Schema::teams_table() . ' teams ON teams.id = rel.team_id LEFT JOIN ' . Fields_Schema::fields_table() . ' fields ON fields.id = rel.field_id WHERE teams.id IS NULL OR fields.id IS NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal schema tables.
		Portal::add_rewrite_rules();
		Calendar::add_rewrite_rules();
		Router::add_rewrite_rules();
		flush_rewrite_rules( false );
		do_action( 'adam_comunidade_health_repaired' );
		wp_safe_redirect( add_query_arg( 'repaired', 1, admin_url( 'admin.php?page=adam-comunidade-health' ) ) );
		exit;
	}
}
