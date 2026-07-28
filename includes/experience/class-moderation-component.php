<?php
/**
 * Shared moderation action controls.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the canonical approve, request-changes, and reject workflow.
 */
final class Moderation_Component {
	/**
	 * Renders one complete moderation action group.
	 *
	 * @param array<string,mixed> $args Component configuration.
	 */
	public static function render( array $args ): void {
		$context       = sanitize_key( (string) ( $args['context'] ?? 'moderation' ) );
		$identifier    = absint( $args['identifier'] ?? 0 );
		$dialog_prefix = $context . '-' . $identifier;
		$form_action   = esc_url( (string) ( $args['form_action'] ?? admin_url( 'admin-post.php' ) ) );
		$hidden        = isset( $args['hidden'] ) && is_array( $args['hidden'] ) ? $args['hidden'] : array();
		$nonce_action  = (string) ( $args['nonce_action'] ?? '' );
		$nonce_name    = (string) ( $args['nonce_name'] ?? '_wpnonce' );
		$class         = trim( 'adam-approval-actions ' . sanitize_html_class( (string) ( $args['class'] ?? '' ) ) );
		?>
		<div class="<?php echo esc_attr( $class ); ?>" data-adam-moderation-actions>
			<form action="<?php echo $form_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" method="post">
				<?php self::form_fields( $hidden, $nonce_action, $nonce_name, 'approve' ); ?>
				<?php if ( isset( $args['approve_fields'] ) && is_callable( $args['approve_fields'] ) ) : ?>
					<?php call_user_func( $args['approve_fields'] ); ?>
				<?php endif; ?>
				<button class="button button-primary" type="submit"><?php echo esc_html( (string) ( $args['approve_label'] ?? __( 'Aprovar e publicar', 'adam-comunidade' ) ) ); ?></button>
			</form>
			<button class="button" type="button" data-adam-open-moderation="<?php echo esc_attr( 'changes-' . $dialog_prefix ); ?>"><?php echo esc_html( (string) ( $args['changes_label'] ?? __( 'Pedir alterações', 'adam-comunidade' ) ) ); ?></button>
			<button class="button button-link-delete" type="button" data-adam-open-moderation="<?php echo esc_attr( 'reject-' . $dialog_prefix ); ?>"><?php echo esc_html( (string) ( $args['reject_label'] ?? __( 'Rejeitar', 'adam-comunidade' ) ) ); ?></button>
			<?php self::dialog( 'changes', $dialog_prefix, $form_action, $hidden, $nonce_action, $nonce_name, $args ); ?>
			<?php self::dialog( 'reject', $dialog_prefix, $form_action, $hidden, $nonce_action, $nonce_name, $args ); ?>
		</div>
		<?php
	}

	/**
	 * Renders one shared reason-selection dialog.
	 *
	 * @param array<string,mixed> $hidden Hidden form values.
	 * @param array<string,mixed> $args   Component configuration.
	 */
	private static function dialog( string $decision, string $prefix, string $form_action, array $hidden, string $nonce_action, string $nonce_name, array $args ): void {
		$is_reject = 'reject' === $decision;
		$dialog_id = $decision . '-' . $prefix;
		$groups    = Moderation_Reasons::grouped( $decision );
		$title_key = $is_reject ? 'reject_title' : 'changes_title';
		$intro_key = $is_reject ? 'reject_intro' : 'changes_intro';
		$submit_key = $is_reject ? 'reject_submit_label' : 'changes_submit_label';
		$title = (string) ( $args[ $title_key ] ?? ( $is_reject ? __( 'Rejeitar', 'adam-comunidade' ) : __( 'Pedir alterações', 'adam-comunidade' ) ) );
		$intro = (string) ( $args[ $intro_key ] ?? ( $is_reject
			? __( 'Selecione os motivos que impedem a aprovação.', 'adam-comunidade' )
			: __( 'Selecione tudo o que deve ser corrigido antes de uma nova análise.', 'adam-comunidade' ) ) );
		?>
		<dialog class="adam-moderation-dialog" data-adam-moderation-dialog="<?php echo esc_attr( $dialog_id ); ?>" aria-labelledby="<?php echo esc_attr( $dialog_id . '-title' ); ?>">
			<form action="<?php echo $form_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" method="post">
				<?php self::form_fields( $hidden, $nonce_action, $nonce_name, $decision ); ?>
				<header>
					<div>
						<span class="adam-card__eyebrow"><?php esc_html_e( 'Decisão de moderação', 'adam-comunidade' ); ?></span>
						<h2 id="<?php echo esc_attr( $dialog_id . '-title' ); ?>"><?php echo esc_html( $title ); ?></h2>
						<p><?php echo esc_html( $intro ); ?></p>
					</div>
					<button type="button" class="adam-moderation-dialog__close" data-adam-close-moderation aria-label="<?php esc_attr_e( 'Fechar', 'adam-comunidade' ); ?>">×</button>
				</header>
				<div class="adam-moderation-dialog__body">
					<?php if ( $groups ) : ?>
						<?php foreach ( $groups as $category => $reasons ) : ?>
							<fieldset>
								<legend><?php echo esc_html( $category ); ?></legend>
								<div class="adam-moderation-reason-list">
									<?php foreach ( $reasons as $reason ) : ?>
										<label>
											<input type="checkbox" name="moderation_reasons[]" value="<?php echo esc_attr( (string) $reason['id'] ); ?>" <?php echo ! empty( $reason['allows_custom'] ) ? 'data-adam-custom-reason' : ''; ?>>
											<span><?php echo esc_html( (string) $reason['label'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endforeach; ?>
						<label class="adam-moderation-custom" data-adam-moderation-custom hidden>
							<span><?php esc_html_e( 'Informação adicional (opcional)', 'adam-comunidade' ); ?></span>
							<textarea name="moderation_custom_reason" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'Explique brevemente o outro motivo.', 'adam-comunidade' ); ?>"></textarea>
						</label>
					<?php else : ?>
						<div class="adam-notice adam-notice--warning"><p><?php esc_html_e( 'Não existem motivos ativos para esta decisão. Configure-os em Definições antes de continuar.', 'adam-comunidade' ); ?></p></div>
					<?php endif; ?>
					<p class="adam-form-feedback adam-form-feedback--error" data-adam-moderation-feedback role="alert" tabindex="-1" hidden></p>
				</div>
				<footer>
					<button type="button" class="button" data-adam-close-moderation><?php esc_html_e( 'Cancelar', 'adam-comunidade' ); ?></button>
					<?php if ( $groups ) : ?>
						<button type="submit" class="<?php echo esc_attr( $is_reject ? 'button adam-button--danger' : 'button button-primary' ); ?>"><?php echo esc_html( (string) ( $args[ $submit_key ] ?? ( $is_reject ? __( 'Confirmar rejeição', 'adam-comunidade' ) : __( 'Enviar pedido de alterações', 'adam-comunidade' ) ) ) ); ?></button>
					<?php endif; ?>
				</footer>
			</form>
		</dialog>
		<?php
	}

	/**
	 * Renders common protected fields for one action form.
	 *
	 * @param array<string,mixed> $hidden Hidden values.
	 */
	private static function form_fields( array $hidden, string $nonce_action, string $nonce_name, string $decision ): void {
		if ( '' !== $nonce_action ) {
			wp_nonce_field( $nonce_action, $nonce_name );
		}
		foreach ( $hidden as $name => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( sanitize_key( (string) $name ) ), esc_attr( (string) $value ) );
		}
		printf( '<input type="hidden" name="decision" value="%s">', esc_attr( $decision ) );
	}
}
