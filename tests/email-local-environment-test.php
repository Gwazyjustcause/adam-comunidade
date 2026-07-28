<?php
/**
 * Ensures Local development addresses do not disable the complete mail pipeline.
 */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['adam_local_mail'] = array();

	function get_option( string $key, mixed $default = false ): mixed {
		return 'admin_email' === $key ? 'dev-email@wpengine.local' : $default;
	}
	function update_option( string $key, mixed $value, bool $autoload = false ): bool { unset( $key, $value, $autoload ); return true; }
	function wp_get_environment_type(): string { return 'local'; }
	function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function esc_html__( string $text, string $domain = '' ): string { return esc_html( $text ); }
	function esc_html_e( string $text, string $domain = '' ): void { unset( $domain ); echo esc_html( $text ); }
	function sanitize_email( mixed $value ): string { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
	function is_email( string $value ): bool { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
	function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
	function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ) ?: ''; }
	function wp_unslash( mixed $value ): mixed { return $value; }
	function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
	function wp_kses_post( string $value ): string { return $value; }
	function wpautop( string $value ): string { return '<p>' . $value . '</p>'; }
	function esc_html( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	function esc_url( mixed $value ): string { return (string) $value; }
	function esc_url_raw( mixed $value, array $protocols = array() ): string { unset( $protocols ); return preg_match( '#^https?://#i', (string) $value ) ? (string) $value : ''; }
	function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed { unset( $tag, $args ); return $value; }
	function add_filter( string $tag, callable $callback ): void { unset( $tag, $callback ); }
	function remove_filter( string $tag, callable $callback ): void { unset( $tag, $callback ); }
	function add_action( string $tag, callable $callback ): void { unset( $tag, $callback ); }
	function wp_mail( string $recipient, string $subject, string $html, array $headers ): bool {
		$GLOBALS['adam_local_mail'][] = compact( 'recipient', 'subject', 'html', 'headers' );
		return true;
	}
	function wp_hash( string $value ): string { return hash( 'sha256', $value ); }
}

namespace ADAM\Comunidade {
	final class Settings {
		public static function get( string $key ): mixed { unset( $key ); return ''; }
	}
	final class Logger {
		public static function info( string $message, array $context = array() ): void { unset( $message, $context ); }
	}
	final class Managed_Pages {
		public static function url( string $module ): string { return 'http://adam.local/' . $module . '/'; }
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/includes/experience/class-email-service.php';
	$service = new \ADAM\Comunidade\Experience\Email_Service();
	$templates = array(
		'field_received',
		'field_approved',
		'field_rejected',
		'community_received',
		'community_approved',
		'community_rejected',
		'manager_invitation',
		'manager_organisation_assigned',
		'manager_organisation_pending_activation',
		'manager_password_reset',
		'manager_password_created',
		'manager_password_changed',
		'manager_revision_approved',
		'manager_revision_rejected',
		'manager_information_requested',
	);
	foreach ( $templates as $template ) {
		$sent = $service->send(
			$template,
			'gestor@adam.local',
			array(
				'field_name'         => 'Campo Local',
				'field_url'          => 'http://adam.local/campos/campo-local/',
				'entity_name'        => 'Organização Local',
				'entity_type'        => 'organização',
				'entity_url'         => 'http://adam.local/comunidade/organizacao-local/',
				'manager_invite_url' => 'http://adam.local/definir-palavra-passe/?convite=abc',
				'manager_reset_url'  => 'http://adam.local/recuperar-palavra-passe/?codigo=abc',
				'manager_url'        => 'http://adam.local/gestor/',
				'manager_action_url'   => 'http://adam.local/gestor/',
				'manager_action_label' => 'Aceder Ã  Ãrea do Gestor',
				'manager_guidance'     => 'Inicie sessÃ£o para atualizar a organizaÃ§Ã£o.',
				'admin_note'         => 'Nota de teste.',
			)
		);
		if ( ! $sent ) {
			throw new \RuntimeException( 'Local delivery failed for template: ' . $template );
		}
	}
	if ( count( $GLOBALS['adam_local_mail'] ) !== count( $templates ) ) {
		throw new \RuntimeException( 'Local environment values incorrectly disabled part of the Community email pipeline.' );
	}
	echo "Local email delivery tests passed.\n";
}
