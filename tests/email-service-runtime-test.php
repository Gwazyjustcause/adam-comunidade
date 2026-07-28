<?php
/**
 * Runtime regression for null context and failing shared email renderers.
 */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['adam_test_mail'] = array();
	$GLOBALS['adam_test_mail_calls'] = 0;
	$GLOBALS['adam_test_mail_failures'] = 0;
	$GLOBALS['adam_test_actions'] = array();
	$GLOBALS['adam_test_alt_body'] = '';
	$GLOBALS['adam_test_options'] = array(
		'adam_comunidade_submission_email_templates' => array(
			'field_rejected' => array(
				'subject' => null,
				'heading' => null,
				'body'    => null,
			),
		),
		'admin_email' => 'admin@dominio.pt',
	);

	function get_option( string $key, mixed $default = false ): mixed {
		return $GLOBALS['adam_test_options'][ $key ] ?? $default;
	}
	function __( string $text, string $domain = '' ): string {
		unset( $domain );
		return $text;
	}
	function esc_html__( string $text, string $domain = '' ): string {
		return esc_html( __( $text, $domain ) );
	}
	function esc_html_e( string $text, string $domain = '' ): void {
		echo esc_html__( $text, $domain );
	}
	function sanitize_email( mixed $value ): string {
		return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
	}
	function is_email( string $value ): bool {
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
	function sanitize_text_field( mixed $value ): string {
		return trim( strip_tags( (string) $value ) );
	}
	function wp_unslash( mixed $value ): mixed {
		return $value;
	}
	function wp_strip_all_tags( string $value ): string {
		return strip_tags( $value );
	}
	function wp_kses_post( string $value ): string {
		return $value;
	}
	function wpautop( string $value ): string {
		return '<p>' . $value . '</p>';
	}
	function esc_html( mixed $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
	function esc_url( mixed $value ): string {
		return (string) $value;
	}
	function esc_url_raw( mixed $value, array $protocols = array() ): string {
		unset( $protocols );
		$value = (string) $value;
		return preg_match( '#^https?://#i', $value ) ? $value : '';
	}
	function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed {
		unset( $args );
		if ( 'adam_render_branded_email' === $tag ) {
			// Reproduces the reported PHP 8.1+ diagnostic in an integration.
			htmlspecialchars( null );
		}
		return $value;
	}
	function add_filter( string $tag, callable $callback ): void {
		unset( $tag, $callback );
	}
	function add_action( string $tag, callable $callback ): void {
		$GLOBALS['adam_test_actions'][ $tag ][] = $callback;
	}
	function remove_filter( string $tag, callable $callback ): void {
		unset( $tag, $callback );
	}
	function wp_mail( string $recipient, string $subject, string $html, array $headers ): bool {
		++$GLOBALS['adam_test_mail_calls'];
		$mailer = new \stdClass();
		$mailer->AltBody = '';
		foreach ( $GLOBALS['adam_test_actions']['phpmailer_init'] ?? array() as $callback ) {
			$callback( $mailer );
		}
		$GLOBALS['adam_test_alt_body'] = (string) $mailer->AltBody;
		$GLOBALS['adam_test_mail'] = compact( 'recipient', 'subject', 'html', 'headers' );
		if ( $GLOBALS['adam_test_mail_failures'] > 0 ) {
			--$GLOBALS['adam_test_mail_failures'];
			return false;
		}
		return true;
	}
	function wp_hash( string $value ): string {
		return hash( 'sha256', $value );
	}
}

namespace ADAM\Comunidade {
	final class Settings {
		public static function get( string $key ): mixed {
			return 'contact_email' === $key ? 'contacto@adam.pt' : 0;
		}
	}
	final class Logger {
		public static function info( string $message, array $context = array() ): void {
			unset( $message, $context );
		}
	}
	final class Managed_Pages {
		public static function url( string $module ): string {
			unset( $module );
			return 'https://airsoftmondego.pt/campos/';
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/includes/experience/class-email-service.php';

	$service = new \ADAM\Comunidade\Experience\Email_Service();
	$sent = $service->send(
		'field_rejected',
		'pessoa@dominio.pt',
		array(
			'field_name' => null,
			'field_url'  => null,
			'admin_note' => null,
		)
	);

	if ( ! $sent ) {
		throw new \RuntimeException( 'The null-safe rejection email was not sent.' );
	}
	$html = (string) ( $GLOBALS['adam_test_mail']['html'] ?? '' );
	foreach ( array( 'Deprecated:', 'Warning:', 'Notice:', 'wpengine.local', 'dev-email', '{{' ) as $forbidden ) {
		if ( str_contains( $html, $forbidden ) ) {
			throw new \RuntimeException( 'Unsafe email output remains: ' . $forbidden );
		}
	}
	if ( ! str_contains( $html, 'Campo submetido' ) || ! str_contains( $html, 'contacto@adam.pt' ) ) {
		throw new \RuntimeException( 'Required null fallbacks were not rendered.' );
	}
	if ( ! str_contains( $GLOBALS['adam_test_alt_body'], 'Campo submetido' ) || str_contains( $GLOBALS['adam_test_alt_body'], '<table' ) ) {
		throw new \RuntimeException( 'The accessible plain-text alternative was not attached.' );
	}

	$GLOBALS['adam_test_mail_failures'] = 1;
	$calls_before_retry = $GLOBALS['adam_test_mail_calls'];
	if ( ! $service->send( 'manager_password_changed', 'pessoa@dominio.pt', array( 'manager_url' => 'https://dominio.pt/gestor/' ) ) ) {
		throw new \RuntimeException( 'The bounded retry did not recover from a transient mail failure.' );
	}
	if ( 2 !== $GLOBALS['adam_test_mail_calls'] - $calls_before_retry ) {
		throw new \RuntimeException( 'The mail retry policy did not make exactly one additional attempt.' );
	}

	echo "Email service runtime tests passed.\n";
}
