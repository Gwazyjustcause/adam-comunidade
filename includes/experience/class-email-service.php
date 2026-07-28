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
	private static string $active_plain_text = '';
	private static int $active_attempt = 0;

	public function __construct() {
		if ( self::$mail_hooks_registered ) {
			return;
		}
		self::$mail_hooks_registered = true;
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_mail_failed', array( self::class, 'mail_failed' ) );
			add_action( 'wp_mail_succeeded', array( self::class, 'mail_succeeded' ) );
			add_action( 'phpmailer_init', array( self::class, 'set_plain_text_fallback' ) );
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
		$defaults = $this->template_definitions();
		$merged   = array_replace_recursive( $defaults, $stored );

		foreach ( $defaults as $key => $default ) {
			$template = isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ? $merged[ $key ] : array();
			$merged[ $key ] = array(
				'label'        => $this->string_value( $template['label'] ?? null, $default['label'] ),
				'description'  => $this->string_value( $default['description'] ?? null ),
				'category'     => $this->template_category( $key ),
				'placeholders' => $this->extract_placeholders( $default ),
				'enabled'      => isset( $template['enabled'] ) ? (bool) $template['enabled'] : (bool) $default['enabled'],
				'subject'      => $this->string_value( $template['subject'] ?? null, $default['subject'] ),
				'heading'      => $this->string_value( $template['heading'] ?? null, $default['heading'] ),
				'body'         => $this->string_value( $template['body'] ?? null, $default['body'] ),
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
		$clean = $this->template_definitions();

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
			Logger::info(
				'Community email was not sent because the recipient or template was unavailable.',
				array( 'email_type' => sanitize_key( $template_key ) )
			);
			return false;
		}
		if ( $this->is_production() && '' === $this->contact_email() ) {
			Logger::info(
				'Community email was blocked because no production-safe sender address was configured.',
				array( 'email_type' => sanitize_key( $template_key ) )
			);
			return false;
		}

		$context = $this->normalize_context( $template_key, $context );
		if ( in_array( $template_key, array( 'field_approved', 'community_approved' ), true ) && '' !== $context['manager_invite_url'] && ! str_contains( $template['body'], 'independente da conta de Sócio ADAM' ) ) {
			$template['body'] .= str_contains( $template['body'], '{{manager_invite_url}}' )
				? $this->manager_onboarding_explanation()
				: $this->manager_onboarding_content();
		}
		if ( in_array( $template_key, array( 'field_approved', 'community_approved', 'manager_invitation' ), true ) ) {
			$template['body'] = (string) preg_replace(
				'#(<a[^>]+href=(["\'])\{\{manager_invite_url\}\}\2[^>]*>).*?(</a>)#is',
				'$1' . __( 'Criar Palavra-passe', 'adam-comunidade' ) . '$3',
				$template['body']
			);
		}
		if (
			in_array( $template_key, array( 'field_changes_requested', 'community_changes_requested' ), true )
			&& '' !== $context['manager_guidance']
			&& ! str_contains( $template['body'], '{{manager_guidance}}' )
		) {
			$template['body'] .= $this->manager_access_content();
		}

		foreach ( $context as $key => $value ) {
			if ( '' === $value && ( str_ends_with( $key, '_url' ) || in_array( $key, array( 'adam_email', 'invitation_expiry' ), true ) ) ) {
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
		$content = preg_match( '/<[a-z][^>]*>/i', $body ) ? wp_kses_post( $body ) : wp_kses_post( wpautop( esc_html( $body ) ) );
		$content = $this->decorate_content( $content );

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
		$plain_text = $this->render_plain_text( $heading, $content );
		if ( $this->is_production() && $this->contains_development_value( $subject . "\n" . $html . "\n" . $plain_text ) ) {
			Logger::info( 'Community email was blocked because rendered output contained a development value.', array( 'email_type' => $template_key ) );
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from = $this->mail_from();
		if ( $from ) {
			add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		}
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
		self::$active_template = $template_key;
		self::$active_plain_text = $plain_text;
		$sent = false;
		$attempt = 1;
		$max_attempts = max( 1, min( 3, (int) apply_filters( 'adam_comunidade_email_max_attempts', 2, $template_key ) ) );
		try {
			for ( ; $attempt <= $max_attempts && ! $sent; ++$attempt ) {
				self::$active_attempt = $attempt;
				try {
					$sent = (bool) wp_mail( $recipient, $subject, $html, $headers );
				} catch ( \Throwable $error ) {
					$sent = false;
					Logger::info(
						'Community mailer raised an exception.',
						array( 'email_type' => $template_key, 'attempt' => $attempt, 'error_code' => strtolower( str_replace( '\\', '_', get_class( $error ) ) ) )
					);
				}
			}
		} finally {
			if ( $from ) {
				remove_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
			}
			remove_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
			self::$active_template = '';
			self::$active_plain_text = '';
			self::$active_attempt = 0;
		}
		$attempts = min( $max_attempts, $attempt - 1 );
		if ( function_exists( 'update_option' ) ) {
			update_option(
				'adam_comunidade_email_last_status',
				array( 'status' => $sent ? 'sent' : 'failed', 'email_type' => $template_key, 'attempts' => $attempts, 'timestamp' => time() ),
				false
			);
		}

		Logger::info(
			$sent ? 'Community lifecycle email sent.' : 'Community lifecycle email failed after retry policy.',
			array(
				'email_type'     => $template_key,
				'recipient_hash' => wp_hash( $recipient ),
				'attempts'       => $attempts,
			)
		);

		return (bool) $sent;
	}

	public function mail_from(): string {
		return $this->contact_email();
	}

	public function mail_from_name(): string {
		$name = sanitize_text_field( (string) Settings::get( 'email_from_name' ) );
		return '' !== $name && ! $this->contains_development_value( $name ) && 'wordpress' !== strtolower( $name ) ? $name : 'ADAM Comunidade';
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
			'attempt'    => self::$active_attempt,
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
			'attempt'    => self::$active_attempt,
			'timestamp'  => time(),
		);
		update_option( 'adam_comunidade_email_last_status', $status, false );
		Logger::info( 'Community email accepted by the WordPress mailer.', $status );
	}

	/**
	 * Adds a genuine text alternative to the HTML message.
	 *
	 * @param object $phpmailer WordPress PHPMailer instance.
	 */
	public static function set_plain_text_fallback( object $phpmailer ): void {
		if ( '' !== self::$active_plain_text ) {
			$phpmailer->AltBody = self::$active_plain_text;
		}
	}

	/**
	 * Ensures calls to action remain recognizable in conservative email clients.
	 */
	private function decorate_content( string $content ): string {
		return (string) preg_replace(
			'/<a\s+(?![^>]*\bstyle=)/i',
			'<a style="display:inline-block;background:#2e7d32;color:#ffffff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:7px;" ',
			$content
		);
	}

	/**
	 * Produces the alternative text body while retaining destination addresses.
	 */
	private function render_plain_text( string $heading, string $content ): string {
		$content = (string) preg_replace_callback(
			'#<a[^>]+href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
			static function ( array $matches ): string {
				$label = trim( wp_strip_all_tags( (string) $matches[3] ) );
				$url   = html_entity_decode( (string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				return '' !== $label ? $label . ': ' . $url : $url;
			},
			$content
		);
		$text = html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br/>', '<br />' ), "\n\n", $content ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = (string) preg_replace( "/\n{3,}/", "\n\n", $text );
		$footer = __( 'ADAM — Associação Desportiva de Airsoft do Mondego', 'adam-comunidade' );
		$contact = $this->contact_email();
		if ( '' !== $contact ) {
			$footer .= "\n" . sprintf( __( 'Contacto: %s', 'adam-comunidade' ), $contact );
		}
		return trim( $heading . "\n\n" . $text . "\n\n" . $footer );
	}

	/**
	 * Appends mandatory onboarding to customized legacy approval templates.
	 */
	private function manager_onboarding_content(): string {
		return __(
			'<h2>Faça a gestão da sua organização</h2><p>Como Gestor da Comunidade, pode atualizar a informação sempre que for necessário. As alterações são revistas pela ADAM antes da publicação, ajudando a manter o Diretório correto e atualizado.</p><p>A conta de Gestor da Comunidade é independente da conta de Sócio ADAM. Se vier a tornar-se Sócio, as duas contas permanecem separadas.</p><p><a href="{{manager_invite_url}}">Criar Palavra-passe</a></p><p>Este endereço é pessoal, de utilização única e expira ao fim de {{invitation_expiry}}.</p>',
			'adam-comunidade'
		);
	}

	/**
	 * Adds mandatory account separation guidance to older customized templates.
	 */
	private function manager_onboarding_explanation(): string {
		return __(
			'<h2>Faça a gestão da sua organização</h2><p>Como Gestor da Comunidade, pode atualizar a informação sempre que for necessário. As alterações são revistas pela ADAM antes da publicação, ajudando a manter o Diretório correto e atualizado.</p><p>A conta de Gestor da Comunidade é independente da conta de Sócio ADAM. Se vier a tornar-se Sócio, as duas contas permanecem separadas.</p>',
			'adam-comunidade'
		);
	}

	/**
	 * Appends account-aware access guidance to customized legacy templates.
	 */
	private function manager_access_content(): string {
		return __(
			'<h2>Atualizar a organização</h2><p>{{manager_guidance}}</p><p><a href="{{manager_action_url}}">{{manager_action_label}}</a></p>',
			'adam-comunidade'
		);
	}

	/**
	 * Keeps the administration interface organized as new templates are added.
	 */
	private function template_category( string $key ): string {
		if ( str_starts_with( $key, 'manager_password' ) ) {
			return 'access';
		}
		if ( in_array( $key, array( 'manager_invitation', 'manager_organisation_assigned', 'manager_organisation_pending_activation' ), true ) ) {
			return 'onboarding';
		}
		if ( str_starts_with( $key, 'manager_revision' ) || 'manager_information_requested' === $key ) {
			return 'moderation';
		}
		return 'submissions';
	}

	/**
	 * Discovers the supported placeholders from the canonical template.
	 *
	 * @param array<string,mixed> $template Template definition.
	 * @return string[]
	 */
	private function extract_placeholders( array $template ): array {
		$text = implode( "\n", array_map( array( $this, 'string_value' ), array( $template['subject'] ?? '', $template['heading'] ?? '', $template['body'] ?? '' ) ) );
		preg_match_all( '/\{\{([a-z0-9_]+)\}\}/i', $text, $matches );
		$placeholders = array_values(
			array_unique(
				array_map(
					static fn( mixed $key ): string => (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $key ) ),
					$matches[1] ?? array()
				)
			)
		);
		sort( $placeholders );
		return $placeholders;
	}

	/**
	 * @param array<string,string> $context Placeholder values.
	 */
	private function replace_placeholders( string $text, array $context ): string {
		$replacements = array();
		foreach ( $context as $key => $value ) {
			$value = $this->string_value( $value );
			if ( in_array( $key, array( 'admin_note', 'manager_guidance' ), true ) ) {
				$replacements[ '{{' . $key . '}}' ] = nl2br( esc_html( $value ), false );
			} else {
				$replacements[ '{{' . $key . '}}' ] = str_ends_with( $key, '_url' ) || 'adam_email' === $key
					? esc_url( 'adam_email' === $key ? 'mailto:' . $value : $value )
					: esc_html( $value );
			}
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
		$manager_invite_url = $this->public_url( $context['manager_invite_url'] ?? null );
		$manager_action_url = $this->public_url( $context['manager_action_url'] ?? null );

		return array(
			'field_name'         => $field_name,
			'field_url'          => $field_url,
			'entity_name'        => $this->string_value( $context['entity_name'] ?? null, __( 'Registo da Comunidade', 'adam-comunidade' ) ),
			'entity_type'        => $this->string_value( $context['entity_type'] ?? null, __( 'organização', 'adam-comunidade' ) ),
			'entity_url'         => $this->public_url( $context['entity_url'] ?? null ),
			'manager_invite_url' => $manager_invite_url,
			'manager_url'        => $this->public_url( $context['manager_url'] ?? null ),
			'manager_reset_url'  => $this->public_url( $context['manager_reset_url'] ?? null ),
			'manager_action_url'   => $manager_action_url,
			'manager_action_label' => $this->string_value( $context['manager_action_label'] ?? null ),
			'manager_guidance'     => $this->string_value( $context['manager_guidance'] ?? null ),
			'invitation_expiry'  => '' !== $manager_invite_url ? $this->string_value( $context['invitation_expiry'] ?? null, __( '14 dias', 'adam-comunidade' ) ) : '',
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
				array( 'email_type' => $template_key, 'error_code' => strtolower( str_replace( '\\', '_', get_class( $error ) ) ) )
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
		$contact = $this->contact_email();
		ob_start();
		?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="color-scheme" content="light dark">
	<meta name="supported-color-schemes" content="light dark">
	<title><?php echo esc_html( $heading ); ?></title>
	<style>
		@media only screen and (max-width: 680px) {
			.adam-email-shell { width: 100% !important; border-radius: 0 !important; }
			.adam-email-header, .adam-email-content, .adam-email-footer { padding-left: 24px !important; padding-right: 24px !important; }
			.adam-email-title { font-size: 26px !important; }
			.adam-email-content a { display: block !important; text-align: center !important; }
		}
		@media (prefers-color-scheme: dark) {
			.adam-email-page { background: #111713 !important; }
			.adam-email-shell, .adam-email-content { background: #1c251f !important; color: #f4f7f5 !important; }
			.adam-email-footer { background: #172019 !important; color: #d4ddd6 !important; border-color: #34453a !important; }
			.adam-email-content h2 { color: #b7dc59 !important; }
		}
	</style>
</head>
<body class="adam-email-page" style="margin:0;padding:0;background:#eef2ef;font-family:Arial,Helvetica,sans-serif;color:#1d2921;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;"><?php esc_html_e( 'Mensagem de ADAM Comunidade', 'adam-comunidade' ); ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#eef2ef;"><tr><td align="center" style="padding:32px 12px;">
<table class="adam-email-shell" role="presentation" width="650" cellpadding="0" cellspacing="0" style="width:100%;max-width:650px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.08);">
<tr><td class="adam-email-header" style="background:#245f2b;padding:34px;text-align:center;"><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_html__( 'Logótipo da ADAM', 'adam-comunidade' ); ?>" width="180" style="width:100%;max-width:180px;height:auto;display:block;margin:0 auto 22px;"><?php endif; ?><h1 class="adam-email-title" style="margin:0;color:#ffffff;font-size:30px;line-height:1.25;font-weight:700;"><?php echo esc_html( $heading ); ?></h1></td></tr>
<tr><td class="adam-email-content" style="padding:38px 40px;background:#ffffff;color:#1d2921;font-size:16px;line-height:1.7;"><?php echo wp_kses_post( $content ); ?></td></tr>
<tr><td class="adam-email-footer" style="padding:26px 40px;background:#f7f9f7;border-top:1px solid #dfe7e1;font-size:13px;line-height:1.65;color:#526057;"><p style="margin:0 0 8px;"><?php esc_html_e( 'Caso necessite de apoio, contacte a Direção da ADAM.', 'adam-comunidade' ); ?></p><?php if ( $contact ) : ?><p style="margin:0 0 8px;"><a href="<?php echo esc_url( 'mailto:' . $contact ); ?>" style="color:#245f2b;"><?php echo esc_html( $contact ); ?></a></p><?php endif; ?><p style="margin:0;"><strong>ADAM — Associação Desportiva de Airsoft do Mondego</strong></p></td></tr>
</table></td></tr></table>
</body></html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Returns the canonical extensible template registry.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function template_definitions(): array {
		$defaults = array_merge( $this->defaults(), $this->manager_defaults() );
		$filtered = apply_filters( 'adam_comunidade_email_templates', $defaults );
		return is_array( $filtered ) ? $filtered : $defaults;
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
				'body'    => __( '<p>Obrigado por contribuir para a comunidade de airsoft.</p><p>Recebemos a submissão de {{entity_type}} <strong>{{entity_name}}</strong>.</p><h2>O que acontece agora?</h2><p>A nossa equipa irá analisar a informação enviada. Receberá outra mensagem quando a revisão estiver concluída ou se precisarmos de algum esclarecimento.</p><p>Para qualquer questão, contacte-nos através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'community_approved' => array(
				'label'   => __( 'Submissão da Comunidade aprovada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'A sua submissão foi aprovada', 'adam-comunidade' ),
				'heading' => __( 'A organização foi aprovada', 'adam-comunidade' ),
				'body'    => __( '<p>Parabéns! A submissão de {{entity_type}} <strong>{{entity_name}}</strong> foi aprovada e já está visível no Diretório ADAM.</p><p><a href="{{entity_url}}">Ver organização no Diretório</a></p><h2>Faça a gestão da sua organização</h2><p>Como Gestor da Comunidade, pode atualizar a informação sempre que for necessário. As alterações são revistas pela ADAM antes da publicação, ajudando a manter o Diretório correto e atualizado.</p><p>A conta de Gestor da Comunidade é independente da conta de Sócio ADAM. Se vier a tornar-se Sócio, as duas contas permanecem separadas.</p><p><a href="{{manager_invite_url}}">Criar Palavra-passe</a></p><p>Este endereço é pessoal, de utilização única e expira ao fim de {{invitation_expiry}}.</p>', 'adam-comunidade' ),
			),
			'community_changes_requested' => array(
				'label'   => __( 'Alterações pedidas numa submissão da Comunidade', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Precisamos de alterações à sua submissão', 'adam-comunidade' ),
				'heading' => __( 'Alterações necessárias', 'adam-comunidade' ),
				'body'    => __( '<p>Revimos a submissão de {{entity_type}} <strong>{{entity_name}}</strong> e precisamos que corrija os pontos seguintes antes de uma nova análise.</p><h2>O que deve corrigir</h2><p>{{admin_note}}</p><h2>Atualizar a organização</h2><p>{{manager_guidance}}</p><p><a href="{{manager_action_url}}">{{manager_action_label}}</a></p><p>Se precisar de esclarecimentos, contacte a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'community_rejected' => array(
				'label'   => __( 'Submissão da Comunidade rejeitada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Atualização sobre a sua submissão', 'adam-comunidade' ),
				'heading' => __( 'Submissão não aprovada', 'adam-comunidade' ),
				'body'    => __( '<p>Concluímos a análise da submissão de {{entity_type}} <strong>{{entity_name}}</strong> e esta não foi aceite para publicação.</p><h2>Motivos da rejeição</h2><p>{{admin_note}}</p><p>Se precisar de esclarecimentos, contacte a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'manager_invitation' => array(
				'label'   => __( 'Convite de Gestor da Comunidade', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Crie a sua conta de Gestor da Comunidade', 'adam-comunidade' ),
				'heading' => __( 'Convite para Gestor da Comunidade', 'adam-comunidade' ),
				'body'    => __( '<p>Recebeu este convite porque a ADAM lhe atribuiu a gestão de <strong>{{entity_name}}</strong> no Diretório da Comunidade.</p><h2>O que pode fazer?</h2><p>Poderá atualizar a informação da organização sempre que necessário. Para garantir a qualidade do Diretório, todas as alterações são revistas pela ADAM antes da publicação.</p><p>A conta de Gestor da Comunidade é independente da conta de Sócio ADAM e não requer nome de utilizador nem um novo formulário de registo.</p><p><a href="{{manager_invite_url}}">Criar Palavra-passe</a></p><p>Este convite é pessoal, de utilização única e expira ao fim de {{invitation_expiry}}. Se não estava à espera desta mensagem, não utilize o endereço e contacte a ADAM.</p>', 'adam-comunidade' ),
			),
			'manager_organisation_assigned' => array(
				'label'   => __( 'Nova organização atribuída a um Gestor', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Uma nova organização foi adicionada à sua conta', 'adam-comunidade' ),
				'heading' => __( 'Nova organização na Área do Gestor', 'adam-comunidade' ),
				'body'    => __( '<p>A submissão de {{entity_type}} <strong>{{entity_name}}</strong> foi aprovada e a organização foi adicionada à sua conta de Gestor da Comunidade.</p><p><a href="{{entity_url}}">Ver organização no Diretório</a></p><p>Não precisa de criar outra conta nem definir uma nova palavra-passe. Utilize as suas credenciais habituais para ver todas as organizações que gere.</p><p><a href="{{manager_url}}">Aceder à Área do Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_organisation_pending_activation' => array(
				'label'   => __( 'Nova organização atribuída a um Gestor por ativar', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Uma nova organização foi adicionada à sua conta', 'adam-comunidade' ),
				'heading' => __( 'Nova organização atribuída', 'adam-comunidade' ),
				'body'    => __( '<p>A submissão de {{entity_type}} <strong>{{entity_name}}</strong> foi aprovada e a organização foi adicionada à sua conta de Gestor da Comunidade.</p><p><a href="{{entity_url}}">Ver organização no Diretório</a></p><p>A sua conta ainda aguarda ativação. Utilize o convite de ativação que já lhe enviámos para criar a palavra-passe. Não é necessário criar outra conta nem efetuar um novo registo.</p>', 'adam-comunidade' ),
			),
			'manager_revision_approved' => array(
				'label'   => __( 'Alteração de Gestor aprovada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'As suas alterações foram aprovadas', 'adam-comunidade' ),
				'heading' => __( 'Alterações publicadas', 'adam-comunidade' ),
				'body'    => __( '<p>As alterações propostas para <strong>{{entity_name}}</strong> foram aprovadas e já estão publicadas.</p><p><a href="{{manager_url}}">Aceder à Área do Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_revision_rejected' => array(
				'label'   => __( 'Alteração de Gestor rejeitada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Atualização sobre as alterações propostas', 'adam-comunidade' ),
				'heading' => __( 'Alterações não aprovadas', 'adam-comunidade' ),
				'body'    => __( '<p>As alterações propostas para <strong>{{entity_name}}</strong> não foram aprovadas. O registo público mantém-se inalterado.</p><h2>Motivos da rejeição</h2><p>{{admin_note}}</p><p><a href="{{manager_url}}">Aceder à Área do Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_information_requested' => array(
				'label'   => __( 'Alterações pedidas ao Gestor', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Precisamos de alterações à sua proposta', 'adam-comunidade' ),
				'heading' => __( 'Alterações necessárias', 'adam-comunidade' ),
				'body'    => __( '<p>Revimos as alterações propostas para <strong>{{entity_name}}</strong> e precisamos que corrija os pontos seguintes antes de uma nova análise.</p><h2>O que deve corrigir</h2><p>{{admin_note}}</p><p><a href="{{manager_url}}">Aceder à Área do Gestor</a></p>', 'adam-comunidade' ),
			),
			'manager_password_reset' => array(
				'label'   => __( 'Recuperação de palavra-passe do Gestor', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Recupere a sua palavra-passe de Gestor', 'adam-comunidade' ),
				'heading' => __( 'Recuperar palavra-passe', 'adam-comunidade' ),
				'body'    => __( '<p>Recebemos um pedido para definir uma nova palavra-passe da sua conta de Gestor da Comunidade.</p><p><a href="{{manager_reset_url}}">Definir nova palavra-passe</a></p><p>Este endereço é de utilização única e expira ao fim de uma hora.</p><h2>Não fez este pedido?</h2><p>Pode ignorar esta mensagem. A palavra-passe atual não será alterada enquanto o endereço acima não for utilizado.</p>', 'adam-comunidade' ),
			),
			'manager_password_created' => array(
				'label'   => __( 'Palavra-passe do Gestor criada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'A sua conta de Gestor está pronta', 'adam-comunidade' ),
				'heading' => __( 'Conta de Gestor ativada', 'adam-comunidade' ),
				'body'    => __( '<p>A palavra-passe foi criada e a sua conta de Gestor da Comunidade está pronta a utilizar.</p><p><a href="{{manager_url}}">Aceder à Área do Gestor</a></p><p>Na Área do Gestor verá apenas as organizações que lhe foram atribuídas. As alterações submetidas serão revistas pela ADAM antes da publicação.</p><p>Se não criou esta palavra-passe, contacte imediatamente a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'manager_password_changed' => array(
				'label'   => __( 'Palavra-passe do Gestor alterada', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'A sua palavra-passe foi alterada', 'adam-comunidade' ),
				'heading' => __( 'Palavra-passe alterada', 'adam-comunidade' ),
				'body'    => __( '<p>A palavra-passe da sua conta de Gestor da Comunidade foi alterada com sucesso. Todas as sessões anteriores foram terminadas por segurança.</p><p><a href="{{manager_url}}">Iniciar sessão na Área do Gestor</a></p><p>Se não efetuou esta alteração, contacte imediatamente a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
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
				'body'    => __( '<p>Obrigado por contribuir para a comunidade de airsoft.</p><p>Recebemos a submissão do campo <strong>{{field_name}}</strong>.</p><h2>O que acontece agora?</h2><p>A ADAM irá analisar a informação, as fotografias e o comprovativo de autorização enviados. Receberá outra mensagem quando a revisão estiver concluída ou se precisarmos de algum esclarecimento.</p><p>Para qualquer questão, contacte-nos através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'field_approved' => array(
				'label'   => __( 'Campo aprovado', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'O seu campo foi aprovado', 'adam-comunidade' ),
				'heading' => __( 'O campo foi aprovado', 'adam-comunidade' ),
				'body'    => __( '<p>Parabéns! O campo <strong>{{field_name}}</strong> foi aprovado e já está visível no Diretório ADAM.</p><p><a href="{{field_url}}">Ver Campo no Diretório</a></p><h2>Faça a gestão do seu campo</h2><p>Como Gestor da Comunidade, pode atualizar a informação sempre que for necessário. As alterações são revistas pela ADAM antes da publicação, ajudando a manter o Diretório correto e atualizado.</p><p>A conta de Gestor da Comunidade é independente da conta de Sócio ADAM. Se vier a tornar-se Sócio, as duas contas permanecem separadas.</p><p><a href="{{manager_invite_url}}">Criar Palavra-passe</a></p><p>Este endereço é pessoal, de utilização única e expira ao fim de {{invitation_expiry}}.</p>', 'adam-comunidade' ),
			),
			'field_changes_requested' => array(
				'label'   => __( 'Alterações pedidas numa submissão de campo', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Precisamos de alterações à submissão do seu campo', 'adam-comunidade' ),
				'heading' => __( 'Alterações necessárias', 'adam-comunidade' ),
				'body'    => __( '<p>Revimos a submissão do campo <strong>{{field_name}}</strong> e precisamos que corrija os pontos seguintes antes de uma nova análise.</p><h2>O que deve corrigir</h2><p>{{admin_note}}</p><h2>Atualizar a organização</h2><p>{{manager_guidance}}</p><p><a href="{{manager_action_url}}">{{manager_action_label}}</a></p><p>Se precisar de esclarecimentos, contacte a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
			'field_rejected' => array(
				'label'   => __( 'Campo rejeitado', 'adam-comunidade' ),
				'enabled' => true,
				'subject' => __( 'Atualização da submissão do seu campo', 'adam-comunidade' ),
				'heading' => __( 'Atualização da submissão', 'adam-comunidade' ),
				'body'    => __( '<p>Concluímos a análise da submissão do campo <strong>{{field_name}}</strong> e esta não foi aceite para publicação.</p><h2>Motivos da rejeição</h2><p>{{admin_note}}</p><p>Se precisar de esclarecimentos, contacte a ADAM através de {{adam_email}}.</p>', 'adam-comunidade' ),
			),
		);
	}
}
