<?php
/**
 * Runtime validation for configured moderation reasons.
 */

declare(strict_types=1);

namespace ADAM\Comunidade {
	final class Settings {
		public const OPTION_NAME = 'adam_comunidade_settings';
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	final class WP_Error {
		public function __construct( private string $code, private string $message ) {}
		public function get_error_message(): string {
			return $this->message;
		}
	}

	$GLOBALS['adam_test_options'] = array();
	function __( string $text, string $domain = '' ): string {
		unset( $domain );
		return $text;
	}
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
	function sanitize_textarea_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
	function sanitize_key( mixed $value ): string {
		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
	}
	function get_option( string $key, mixed $default = false ): mixed {
		return $GLOBALS['adam_test_options'][ $key ] ?? $default;
	}
	function is_wp_error( mixed $value ): bool {
		return $value instanceof WP_Error;
	}

	require dirname( __DIR__ ) . '/includes/experience/class-moderation-reasons.php';

	use ADAM\Comunidade\Experience\Moderation_Reasons;

	$GLOBALS['adam_test_options'][ \ADAM\Comunidade\Settings::OPTION_NAME ] = array(
		Moderation_Reasons::CHANGES_KEY => array(
			array( 'id' => 'active', 'category' => 'Informação', 'label' => 'Descrição incompleta.', 'enabled' => 1, 'allows_custom' => 0 ),
			array( 'id' => 'other', 'category' => 'Outros', 'label' => 'Outro motivo…', 'enabled' => 1, 'allows_custom' => 1 ),
			array( 'id' => 'disabled', 'category' => 'Antigo', 'label' => 'Não usar.', 'enabled' => 0, 'allows_custom' => 0 ),
		),
	);

	$resolved = Moderation_Reasons::resolve( 'changes', array( 'active', 'other' ), 'Detalhe específico.' );
	if ( is_wp_error( $resolved ) || 3 !== count( $resolved ) ) {
		throw new RuntimeException( 'Enabled configured reasons and optional detail were not resolved.' );
	}
	if ( ! str_contains( Moderation_Reasons::summary( $resolved ), 'Detalhe específico.' ) ) {
		throw new RuntimeException( 'The email/storage summary lost the optional detail.' );
	}
	if ( ! is_wp_error( Moderation_Reasons::resolve( 'changes', array( 'disabled' ) ) ) ) {
		throw new RuntimeException( 'A disabled reason was accepted by server-side validation.' );
	}
	if ( ! is_wp_error( Moderation_Reasons::resolve( 'reject', array() ) ) ) {
		throw new RuntimeException( 'A decision without reasons was accepted.' );
	}

	echo "Moderation reason runtime tests passed.\n";
}
