<?php
/**
 * Configurable submission lifecycle emails.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Logger;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Settings;

/**
 * Sends Community emails through the shared ADAM visual contract.
 */
final class Email_Service {
	public const OPTION_NAME = 'adam_comunidade_submission_email_templates';
	private static bool $mail_hooks_registered = false;
	private static string $active_template = '';

	public function __construct() {
		if ( self::$mail_hooks_registered ) {
			return;
		}
		self::$mail_hooks_registered = true;
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_mail_failed', array( self::class, 'mail_failed' ) );
			add_action( 'wp_mail_succeeded', array( self::class, 'mail_succeeded' ) );
		}
	}

	/**
	 * Returns editable field-submission templates merged with defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function templates(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$defaults = array_merge( $this->defaults(), $this->manager_defaults() );
		$merged   = array_replace_recursive( $defaults, $stored );

		foreach ( $defaults as $key => $default ) {
			$template = isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ? $merged[ $key ] : array();
			$merged[ $key ] = array(
				'label'   => $this->string_value( $template['label'] ?? null, $default['label'] ),
				'enabled' => isset( $template['enabled'] ) ? (bool) $template['enabled'] : (bool) $default['enabled'],
				'subject' => $this->string_value( $template['subject'] ?? null, $default['subject'] ),
				'heading' => $this->string_value( $template['heading'] ?? null, $default['heading'] ),
				'body'    => $this->string_value( $template['body'] ?? null, $default['body'] ),
			);
		}

		return $merged;
	}

	/**
	 * Saves the editable parts of all templates.
	 *
	 * @param array<string,mixed> $input Posted template settings.
	 */
	public function save( array $input ): void {
		$clean = array_merge( $this->defaults(), $this->manager_defaults() );

		foreach ( $clean as $key => $default ) {
			$template                 = isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
			$clean[ $key ]['enabled'] = ! empty( $template['enabled'] );
			$clean[ $key ]['subject'] = sanitize_text_field( wp_unslash( $template['subject'] ?? $default['subject'] ) );
			$clean[ $key ]['heading'] = sanitize_text_field( wp_unslash( $template['heading'] ?? $default['heading'] ) );
			$clean[ $key ]['body']    = wp_kses_post( wp_unslash( $template['body'] ?? $default['body'] ) );
		}

		update_option( self::OPTION_NAME, $clean, false );
	}

	/**
	 * Sends one configured field-submission email.
	 *
	 * @param string               $template_key Template identifier.
	 * @param string               $recipient Recipient address.
	 * @param array<string,string> $context Placeholder values.
	 */
	public function send( string $template_key, string $recipient, array $context ): bool {
		$recipient = sanitize_email( $recipient );
		$template  = $this->templates()[ $template_key ] ?? null;

		if ( ! $this->is_deliverable_email( $recipient ) || ! is_array( $template ) || empty( $template['enabled'] ) ) {
			return false;
		}

		$context = $this->normalize_context( $template_key, $context );
		if ( 'field_approved' === $template_key && '' !== $context['manager_invite_url'] ) {
			$template['body'] .= __( '<p>Pode manter os dados deste campo atualizados através de uma conta independente de Gestor da Comunidade.</p><p><a href="{{manager_invite_url}}">Criar Conta de Gestor</a></p>', 'adam-comunidade' );
		}

		foreach ( $context as $key => $value ) {
			if ( '' === $value && ( str_ends_with( $key, '_url' ) || 'adam_email' === $key ) ) {
				$template['body'] = (string) preg_replace( '#<p>.*?\{\{' . preg_quote( $key, '#' ) . '\}\}.*?</p>#is', '', $template['body'] );
			}
		}
		$subject = wp_strip_all_tags( $this->replace_placeholders( $template['subject'], $context ) );
		$heading = wp_strip_all_tags( $this->replace_placeholders( $template['heading'], $context ) );
		$body    = $this->replace_placeholders( $template['body'], $context );
		$subject = '' !== trim( $subject ) ? $subject : $this->string_value( $template['label'] ?? null, 'ADAM' );
		$heading = '' !== trim( $heading ) ? $heading : $subject;
		if ( '' === $context['field_url'] ) {
			$body = (string) preg_replace( '#<p>\s*<a[^>]*href=(["\'])\s*\1[^>]*>.*?</a>\s*</p>#is', '', $body );
		}
		$content               = preg_match( '/<[a-z][^>]*>/i', $body ) ? wp_kses_post( $body ) : wp_kses_post( wpautop( esc_html( $body ) ) );

		/**
		 * Lets ADAM Members (or another shared platform service) render the
		 * canonical branded layout without coupling either plugin to internals.
		 *
		 * @param string $html Empty string requests the shared renderer.
		 * @param string $heading Email heading.
		 * @param string $content Sanitized email body.
		 */
		$html = $this->render_shared_layout( $heading, $content, $template_key );
		if ( '' === $html ) {
			$html = $this->render_adam_layout( $heading, $content );
		}
		if ( $this->is_production() && $this->contains_development_value( $subject . "\n" . $html ) ) {
			Logger::info( 'Submission email blocked because rendered output contained a development value.', array( 'email_type' => $template_key ) );
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from = $this->mail_from();
		if ( $from ) {
			add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		}
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
		self::$active_template = $template_key;
		try {
			$sent = wp_mail( $recipient, $subject, $html, $headers );
		} catch ( \Throwable $error ) {
			$sent = false;
			$status = array(
				'status'     => 'failed',
				'email_type' => $template_key,
				'timestamp'  => time(),
				'error_code' => 'mailer_exception',
			);
			update_option( 'adam_comunidade_email_last_status', $status, false );
			Logger::info( 'Community mailer raised an exception.', array( 'email_type' => $template_key, 'error' => $error->getMessage() ) );
		} finally {
			if ( $from ) {
				remove_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
			}
			remove_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
			self::$active_template = '';
		}
		$last_status = get_option( 'adam_comunidade_email_last_status', array() );
		if ( function_exists( 'update_option' ) && ( ! is_array( $last_status ) || ( $last_status['email_type'] ?? '' ) !== $template_key || ( $last_status['status'] ?? '' ) !== ( $sent ? 'sent' : 'failed' ) ) ) {
			update_option(
				'adam_comunidade_email_last_status',
				array( 'status' => $sent ? 'sent' : 'failed', 'email_type' => $template_key, 'timestamp' => time() ),
				false
			);
		}

		Logger::info(
			$sent ? 'Submission lifecycle email sent.' : 'Submission lifecycle email failed.',
			array(
				'email_type'     => $template_key,
				'recipient_hash' => wp_hash( $recipient ),
			)
		);

		return (bool) $sent;
	}

	public function mail_from(): string {
		return $this->contact_email();
	}

	public function mail_from_name(): string {
		$name = sanitize_text_field( (string) get_option( 'adam_membership_email_from_name', '' ) );
		return '' !== $name && ! $this->contains_development_value( $name ) && 'wordpress' !== strtolower( $name ) ? $name : 'ADAM';
	}

	public function contact_email(): string {
		$email = sanitize_email( (string) Settings::get( 'contact_email' ) );
		if ( ! $this->is_deliverable_email( $email ) ) {
			$email = sanitize_email( (string) get_option( 'admin_email', '' ) );
		}
		return $this->is_deliverable_email( $email ) ? $email : '';
	}

	public static function mail_failed( \WP_Error $error ): void {
		$status = array(
			'status'     => 'failed',
			'email_type' => self::$active_template,
			'timestamp'  => time(),
			'error_code' => sanitize_key( $error->get_error_code() ),
		);
		update_option( 'adam_comunidade_email_last_status', $status, false );
		Logger::info( 'Community email delivery failed.', $status );
	}

	public static function mail_succeeded( array $mail_data ): void {
		unset( $mail_data );
		$status = array(
			'status'     => 'sent',
			'email_type' => self::$active_template,
			'timestamp'  => time(),
		);
		update_option( 'adam_comunidade_email_last_status', $status, false );
		Logger::info( 'Community email accepted by the WordPress mailer.', $status );
	}

	/**
	 * @param array<string,string> $context Placeholder values.
	 */
	private function replace_placeholders( string $text, array $context ): string {
		$replacements = array();
		foreach ( $context as $key => $value ) {
			$value = $this->string_value( $value );
			$replacements[ '{{' . $key . '}}' ] = str_ends_with( $key, '_url' ) || 'adam_email' === $key
				? esc_url( 'adam_email' === $key ? 'mailto:' . $value : $value )
				: esc_html( $value );
		}
		// The email placeholder is used both as visible text and as a mailto URL.
		$replacements['{{adam_email}}'] = esc_html( $context['adam_email'] ?? '' );
		$rendered = strtr( $text, $replacements );
		return (string) preg_replace( '/\{\{[a-z0-9_]+\}\}/i', '', $rendered );
	}

	/**
	 * Guarantees scalar, non-null values and type-specific fallbacks.
	 *
	 * @param array<string,mixed> $context Raw placeholder values.
	 * @return array<string,string>
	 */
	private function normalize_context( string $template_key, array $context ): array {
		$field_name = $this->string_value( $context['field_name'] ?? null, __( 'Campo submetido', 'adam-comunidade' ) );
		$admin_note = $this->string_value(
			$context['admin_note'] ?? null,
			__( 'A submissão não reuniu, nesta fase, as condições necessárias para publicação.', 'adam-comunidade' )
		);
		$field_url = $this->public_url( $context['field_url'] ?? null );
		if ( 'field_approved' === $template_key && '' === $field_url ) {
			$field_url = $this->public_url( Managed_Pages::url( 'fields' ) );
		}

		return array(
			'field_name'         => $field_name,
			'field_url'          => $field_url,
			'entity_name'        => $this->string_value( $context['entity_name'] ?? null, __( 'Registo da Comunidade', 'adam-comunidade' ) ),
			'entity_type'        => $this->string_value( $context['entity_type'] ?? null, __( 'organização', 'adam-comunidade' ) ),
			'entity_url'         => $this->public_url( $context['entity_url'] ?? null ),
			'manager_invite_url' => $this->public_url( $context['manager_invite_url'] ?? null ),
			'manager_url'        => $this->public_url( $context['manager_url'] ?? null ),
			'manager_reset_url'  => $this->public_url( $context['manager_reset_url'] ?? null ),
			'admin_note'         => $admin_note,
			'adam_email'         => $this->contact_email(),
		);
	}

	/**
	 * Runs a third-party branded renderer without allowing its diagnostics into
	 * the email body. Warnings are converted to logged failures and the local
	 * validated layout is used.
	 */
	private function render_shared_layout( string $heading, string $content, string $template_key ): string {
		$previous = set_error_handler(
			static function ( int $severity, string $message, string $file, int $line ): never {
				throw new \ErrorException( $message, 0, $severity, $file, $line );
			}
		);
		try {
			$html = apply_filters( 'adam_render_branded_email', '', $heading, $content );
			return is_string( $html ) ? $html : '';
		} catch ( \Throwable $error ) {
			Logger::info(
				'Shared email renderer failed; the validated Community fallback was used.',
				array( 'email_type' => $template_key, 'error' => $error->getMessage() )
			);
			return '';
		} finally {
			restore_error_handler();
			unset( $previous );
		}
	}

	/**
	 * Returns a trimmed scalar string without ever passing null to WordPress
	 * escaping functions.
	 */
	private function string_value( mixed $value, string $fallback = '' ): string {
		if ( ! is_scalar( $value ) || null === $value ) {
			return $fallback;
		}
		$value = trim( (string) $value );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Allows only public HTTP(S) URLs.
	 */
	private function public_url( mixed $value ): string {
		$url = esc_url_raw( $this->string_value( $value ), array( 'http', 'https' ) );
		return $url && ( ! $this->is_production() || ! $this->contains_development_value( $url ) ) ? $url : '';
	}

	/**
	 * Rejects addresses that are valid syntactically but belong to local or
	 * documentation-only domains.
	 */
	private function is_public_email( string $email ): bool {
		if ( ! is_email( $email ) ) {
			return false;
		}
		$domain = strtolower( (string) substr( strrchr( $email, '@' ) ?: '', 1 ) );
		return ! $this->contains_development_value( $domain );
	}

	private function is_deliverable_email( string $email ): bool {
		return is_email( $email ) && ( ! $this->is_production() || $this->is_public_email( $email ) );
	}

	private function is_production(): bool {
		return ! function_exists( 'wp_get_environment_type' ) || 'production' === wp_get_environment_type();
	}

	/**
	 * Detects local, development and documentation placeholders.
	 */
	private function contains_development_value( string $value ): bool {
		return 1 === preg_match(
			'/(?:localhost|127\.0\.0\.1|0\.0\.0\.0|\.local(?:host)?\b|\.test\b|\.invalid\b|example\.(?:com|org|net)\b|wpengine\.local|dev-email)/i',
			$value
		);
	}

	/**
	 * Fallback matching the ADAM Members email design system.
	 */
	private function render_adam_layout( string $heading, string $content ): string {
		$logo = $this->public_url( apply_filters(
			'adam_email_logo_url',
			'https://airsoftmondego.pt/wp-content/uploads/2026/06/ADAM.png'
		) );
		ob_start();
		?>
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo esc_html( $heading ); ?></title></head>
<body style="margin:0;padding:40px 0;background:#f3f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2327;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="650" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.08);">
<tr><td style="background:#2e7d32;padding:35px;text-align:center;"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="ADAM" style="max-width:180px;height:auto;display:block;margin:0 auto 25px;"><?php endif; ?><h1 style="margin:0;color:#ffffff;font-size:30px;font-weight:700;"><?php echo esc_html( $heading ); ?></h1></td></tr>
<tr><td style="padding:40px;font-size:16px;line-height:1.8;"><?php echo wp_kses_post( $content ); ?></td></tr>
<tr><td style="padding:30px;background:#fafafa;border-top:1px solid #e4e4e4;font-size:13px;line-height:1.8;color:#666;"><p style="margin-top:0;"><?php esc_html_e( 'Caso necessite de apoio, contacte a Direção da ADAM.', 'adam-comunidade' ); ?></p><p style="margin-bottom:0;"><strong>ADAM - Associação Desportiva de Airsoft do Mondego</strong></p></td></tr>
</table></td></tr></table>
</body></html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Manager notification defaults are kept in the same editable email system.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function manager_defaults(): array {
		return array(
			'community_received' => array(
				'label'   => __( 'Submissão da Comunidade recebida', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Recebemos a sua submissão', 'adam-comunidade' ),
				'heading' => __( 'Submissão recebida', 'adam-comunidade' ),
				'body'    => __( '<p>Recebemos a submissão de {{entity_type}} <strong>{{entity_name}}</strong>.</p><p>O pedido está agora a aguardar revisão administrativa. Entraremos em contacto quando a análise estiver concluída.</p><p>Para qualquer questão, contacte-nos através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'community_approved' => array(
				'label'   => __( 'Submissão da Comunidade aprovada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'A sua submissão foi aprovada', 'adam-comunidade' ),
				'heading' => __( 'Registo aprovado', 'adam-comunidade' ),
				'body'    => __( '<p>A submissão de {{entity_type}} <strong>{{entity_name}}</strong> foi aprovada e já se encontra publicada.</p><p><a href="{{entity_url}}">Ver registo publicado</a></p><p>Pode manter a informação atualizada através de uma conta independente de Gestor da Comunidade. Todas as alterações continuam sujeitas a aprovação da ADAM.</p><p><a href="{{manager_invite_url}}">Criar Conta de Gestor</a></p>', 'adam-comunidade' ),
			),
			'community_rejected' => array(
				'label'   => __( 'Submissão da Comunidade rejeitada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Atualização sobre a sua submissão', 'adam-comunidade' ),
				'heading' => __( 'Submissão não aprovada', 'adam-comunidade' ),
				'body'    => __( '<p>A submissão de {{entity_type}} <strong>{{entity_name}}</strong> não foi aprovada.</p><p>{{admin_note}}</p><p>Se tiver dúvidas, contacte a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'manager_invitation' => array(
				'label'   => __( 'Convite de Gestor da Comunidade', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Crie a sua conta de Gestor da Comunidade', 'adam-comunidade' ),
				'heading' => __( 'O seu registo foi aprovado', 'adam-comunidade' ),
				'body'    => __( '<p>O registo <strong>{{entity_name}}</strong> foi aprovado.</p><p>Pode criar uma conta de Gestor da Comunidade para manter esta informação atualizada. Esta conta é independente de qualquer conta WordPress ou de Sócio ADAM.</p><p><a href="{{manager_invite_url}}">Criar Conta de Gestor</a></p><p>O convite é pessoal, de utilização única e expira ao fim de 14 dias. A publicação do registo não depende da ativação da conta.</p>', 'adam-comunidade' ),
			),
			'manager_revision_approved' => array(
				'label'   => __( 'Alteração de Gestor aprovada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'As suas alterações foram aprovadas', 'adam-comunidade' ),
				'heading' => __( 'Alterações publicadas', 'adam-comunidade' ),
				'body'    => __( '<p>As alterações propostas para <strong>{{entity_name}}</strong> foram aprovadas e já estão publicadas.</p><p><a href="{{manager_url}}">Abrir o portal de Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_revision_rejected' => array(
				'label'   => __( 'Alteração de Gestor rejeitada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Atualização sobre as alterações propostas', 'adam-comunidade' ),
				'heading' => __( 'Alterações não aprovadas', 'adam-comunidade' ),
				'body'    => __( '<p>As alterações propostas para <strong>{{entity_name}}</strong> não foram aprovadas. O registo público mantém-se inalterado.</p><p>{{admin_note}}</p><p><a href="{{manager_url}}">Abrir o portal de Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_information_requested' => array(
				'label'   => __( 'Pedido de informação ao Gestor', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Precisamos de informação adicional', 'adam-comunidade' ),
				'heading' => __( 'Informação adicional necessária', 'adam-comunidade' ),
				'body'    => __( '<p>Antes de concluir a revisão das alterações de <strong>{{entity_name}}</strong>, precisamos de informação adicional:</p><p>{{admin_note}}</p><p><a href="{{manager_url}}">Abrir o portal de Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_password_reset' => array(
				'label'   => __( 'Recuperação de palavra-passe do Gestor', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Recupere a sua palavra-passe de Gestor', 'adam-comunidade' ),
				'heading' => __( 'Recuperar palavra-passe', 'adam-comunidade' ),
				'body'    => __( '<p>Recebemos um pedido para definir uma nova palavra-passe da sua conta de Gestor da Comunidade.</p><p><a href="{{manager_reset_url}}">Definir nova palavra-passe</a></p><p>Este endereço é de utilização única e expira ao fim de uma hora. Se não fez este pedido, pode ignorar esta mensagem.</p>', 'adam-comunidade' ),
			),
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function defaults(): array {
		return array(
			'field_received' => array(
				'label'   => __( 'Submissão recebida', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Recebemos a submissão do seu campo', 'adam-comunidade' ),
				'heading' => __( 'Submissão recebida', 'adam-comunidade' ),
				'body'    => __( '<p>Olá,</p><p>Obrigado por contribuir para a comunidade de airsoft. Recebemos a submissão do campo <strong>{{field_name}}</strong>.</p><p>O pedido está agora a aguardar revisão administrativa. A ADAM irá verificar a informação e os documentos enviados antes da publicação.</p><p>Se precisarmos de esclarecimentos, entraremos em contacto através deste endereço.</p><p>Para qualquer questão, contacte-nos através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'field_approved' => array(
				'label'   => __( 'Campo aprovado', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'O seu campo foi aprovado', 'adam-comunidade' ),
				'heading' => __( 'Campo aprovado', 'adam-comunidade' ),
				'body'    => __( '<p>Parabéns!</p><p>O campo <strong>{{field_name}}</strong> foi aprovado e já se encontra publicado na plataforma ADAM.</p><p><a href="{{field_url}}">Ver página do campo</a></p><p>Obrigado por contribuir para a comunidade de airsoft. Se precisar de alterar alguma informação no futuro, contacte-nos através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'field_rejected' => array(
				'label'   => __( 'Campo rejeitado', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Atualização da submissão do seu campo', 'adam-comunidade' ),
				'heading' => __( 'Atualização da submissão', 'adam-comunidade' ),
				'body'    => __( '<p>Olá,</p><p>Após análise, a submissão do campo <strong>{{field_name}}</strong> não foi aprovada.</p><p>{{admin_note}}</p><p>Se tiver dúvidas, contacte a ADAM através de {{adam_email}}. Pode enviar uma nova submissão depois de corrigir a informação indicada.</p>', 'adam-comunidade' ),
			),
		);
	}
}
