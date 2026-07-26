<?php
/**
 * Configurable submission lifecycle emails.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Logger;

/**
 * Sends Community emails through the shared ADAM visual contract.
 */
final class Email_Service {
	public const OPTION_NAME = 'adam_comunidade_submission_email_templates';

	/**
	 * Returns editable field-submission templates merged with defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function templates(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array_replace_recursive( $this->defaults(), $stored );
	}

	/**
	 * Saves the editable parts of all templates.
	 *
	 * @param array<string,mixed> $input Posted template settings.
	 */
	public function save( array $input ): void {
		$clean = $this->defaults();

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

		if ( ! is_email( $recipient ) || ! is_array( $template ) || empty( $template['enabled'] ) ) {
			return false;
		}

		$context['adam_email'] = $this->contact_email();
		$subject               = wp_strip_all_tags( $this->replace_placeholders( (string) $template['subject'], $context ) );
		$heading               = wp_strip_all_tags( $this->replace_placeholders( (string) $template['heading'], $context ) );
		$body                  = $this->replace_placeholders( (string) $template['body'], $context );
		$content               = preg_match( '/<[a-z][^>]*>/i', $body ) ? wp_kses_post( $body ) : wp_kses_post( wpautop( esc_html( $body ) ) );

		/**
		 * Lets ADAM Members (or another shared platform service) render the
		 * canonical branded layout without coupling either plugin to internals.
		 *
		 * @param string $html Empty string requests the shared renderer.
		 * @param string $heading Email heading.
		 * @param string $content Sanitized email body.
		 */
		$html = (string) apply_filters( 'adam_render_branded_email', '', $heading, $content );
		if ( '' === $html ) {
			$html = $this->render_adam_layout( $heading, $content );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
		try {
			$sent = wp_mail( $recipient, $subject, $html, $headers );
		} finally {
			remove_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
			remove_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
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
		return '' !== $name ? $name : 'ADAM';
	}

	public function contact_email(): string {
		$email = sanitize_email( (string) get_option( 'adam_membership_email_from_address', '' ) );
		if ( ! is_email( $email ) ) {
			$email = sanitize_email( (string) get_option( 'admin_email', '' ) );
		}
		return $email;
	}

	/**
	 * @param array<string,string> $context Placeholder values.
	 */
	private function replace_placeholders( string $text, array $context ): string {
		$replacements = array();
		foreach ( $context as $key => $value ) {
			$value = (string) $value;
			$replacements[ '{{' . $key . '}}' ] = str_ends_with( $key, '_url' ) || 'adam_email' === $key
				? esc_url( 'adam_email' === $key ? 'mailto:' . $value : $value )
				: esc_html( $value );
		}
		// The email placeholder is used both as visible text and as a mailto URL.
		$replacements['{{adam_email}}'] = esc_html( $context['adam_email'] ?? '' );
		return strtr( $text, $replacements );
	}

	/**
	 * Fallback matching the ADAM Members email design system.
	 */
	private function render_adam_layout( string $heading, string $content ): string {
		$logo = (string) apply_filters(
			'adam_email_logo_url',
			'https://airsoftmondego.pt/wp-content/uploads/2026/06/ADAM.png'
		);
		ob_start();
		?>
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo esc_html( $heading ); ?></title></head>
<body style="margin:0;padding:40px 0;background:#f3f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2327;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="650" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.08);">
<tr><td style="background:#2e7d32;padding:35px;text-align:center;"><img src="<?php echo esc_url( $logo ); ?>" alt="ADAM" style="max-width:180px;height:auto;display:block;margin:0 auto 25px;"><h1 style="margin:0;color:#ffffff;font-size:30px;font-weight:700;"><?php echo esc_html( $heading ); ?></h1></td></tr>
<tr><td style="padding:40px;font-size:16px;line-height:1.8;"><?php echo wp_kses_post( $content ); ?></td></tr>
<tr><td style="padding:30px;background:#fafafa;border-top:1px solid #e4e4e4;font-size:13px;line-height:1.8;color:#666;"><p style="margin-top:0;"><?php esc_html_e( 'Caso necessite de apoio, contacte a Direção da ADAM.', 'adam-comunidade' ); ?></p><p style="margin-bottom:0;"><strong>ADAM - Associação Desportiva de Airsoft do Mondego</strong></p></td></tr>
</table></td></tr></table>
</body></html>
		<?php
		return (string) ob_get_clean();
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
