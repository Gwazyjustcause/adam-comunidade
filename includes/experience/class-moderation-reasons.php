<?php
/**
 * Configurable reasons used by public-submission moderation.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Settings;

/**
 * Provides one validated source of truth for moderation feedback.
 */
final class Moderation_Reasons {
	public const CHANGES_KEY = 'moderation_changes_reasons';
	public const REJECT_KEY  = 'moderation_reject_reasons';

	/**
	 * Returns the initial editable reason collections.
	 *
	 * These values seed the Settings option. Runtime moderation always reads the
	 * saved configuration rather than maintaining a separate list in the UI.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function defaults(): array {
		return array(
			self::CHANGES_KEY => array(
				self::reason( 'changes_authorization_missing', __( 'Documentação', 'adam-comunidade' ), __( 'Falta o comprovativo de autorização.', 'adam-comunidade' ) ),
				self::reason( 'changes_document_invalid', __( 'Documentação', 'adam-comunidade' ), __( 'O documento enviado não é válido.', 'adam-comunidade' ) ),
				self::reason( 'changes_document_unreadable', __( 'Documentação', 'adam-comunidade' ), __( 'O documento não é legível.', 'adam-comunidade' ) ),
				self::reason( 'changes_required_information', __( 'Informação', 'adam-comunidade' ), __( 'Existem informações obrigatórias em falta.', 'adam-comunidade' ) ),
				self::reason( 'changes_description_incomplete', __( 'Informação', 'adam-comunidade' ), __( 'A descrição está incompleta.', 'adam-comunidade' ) ),
				self::reason( 'changes_address_incorrect', __( 'Informação', 'adam-comunidade' ), __( 'A morada está incorreta.', 'adam-comunidade' ) ),
				self::reason( 'changes_contacts_incomplete', __( 'Informação', 'adam-comunidade' ), __( 'Os contactos estão incompletos.', 'adam-comunidade' ) ),
				self::reason( 'changes_featured_image', __( 'Imagens', 'adam-comunidade' ), __( 'É necessária uma imagem de destaque.', 'adam-comunidade' ) ),
				self::reason( 'changes_image_quality', __( 'Imagens', 'adam-comunidade' ), __( 'As fotografias têm qualidade insuficiente.', 'adam-comunidade' ) ),
				self::reason( 'changes_more_images', __( 'Imagens', 'adam-comunidade' ), __( 'São necessárias mais fotografias.', 'adam-comunidade' ) ),
				self::reason( 'changes_other', __( 'Outros', 'adam-comunidade' ), __( 'Outro motivo…', 'adam-comunidade' ), true ),
			),
			self::REJECT_KEY => array(
				self::reason( 'reject_invalid_authorization', __( 'Legal', 'adam-comunidade' ), __( 'Não foi apresentado um comprovativo válido.', 'adam-comunidade' ) ),
				self::reason( 'reject_unconfirmed_authorization', __( 'Legal', 'adam-comunidade' ), __( 'Não foi possível confirmar a autorização.', 'adam-comunidade' ) ),
				self::reason( 'reject_criteria', __( 'Conteúdo', 'adam-comunidade' ), __( 'A submissão não cumpre os critérios da ADAM.', 'adam-comunidade' ) ),
				self::reason( 'reject_incorrect_information', __( 'Conteúdo', 'adam-comunidade' ), __( 'A informação fornecida é incorreta.', 'adam-comunidade' ) ),
				self::reason( 'reject_directory_scope', __( 'Conteúdo', 'adam-comunidade' ), __( 'A organização não se enquadra no diretório.', 'adam-comunidade' ) ),
				self::reason( 'reject_duplicate', __( 'Duplicado', 'adam-comunidade' ), __( 'Esta organização já existe no diretório.', 'adam-comunidade' ) ),
				self::reason( 'reject_other', __( 'Outros', 'adam-comunidade' ), __( 'Outro motivo…', 'adam-comunidade' ), true ),
			),
		);
	}

	/**
	 * Returns configured reasons, optionally excluding disabled entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function configured( string $decision, bool $enabled_only = true ): array {
		$key      = self::settings_key( $decision );
		$defaults = self::defaults();
		$settings = get_option( Settings::OPTION_NAME, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$raw      = array_key_exists( $key, $settings ) ? $settings[ $key ] : ( $defaults[ $key ] ?? array() );
		$reasons  = self::sanitize( $raw );

		return $enabled_only
			? array_values( array_filter( $reasons, static fn( array $reason ): bool => ! empty( $reason['enabled'] ) ) )
			: $reasons;
	}

	/**
	 * Sanitizes a reason collection from Settings.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$clean = array();
		$used  = array();
		foreach ( array_slice( $input, 0, 100 ) as $index => $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$category = sanitize_text_field( (string) ( $raw['category'] ?? '' ) );
			$label    = sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$id = sanitize_key( (string) ( $raw['id'] ?? '' ) );
			if ( '' === $id || isset( $used[ $id ] ) ) {
				$id = 'reason_' . substr( md5( $category . '|' . $label . '|' . (string) $index ), 0, 12 );
			}
			$used[ $id ] = true;
			$clean[] = array(
				'id'            => $id,
				'category'      => '' !== $category ? $category : __( 'Sem categoria', 'adam-comunidade' ),
				'label'         => $label,
				'enabled'       => ! empty( $raw['enabled'] ) ? 1 : 0,
				'allows_custom' => ! empty( $raw['allows_custom'] ) ? 1 : 0,
			);
		}

		return $clean;
	}

	/**
	 * Resolves posted identifiers against the enabled configuration.
	 *
	 * @param string[] $selected Posted identifiers.
	 * @return string[]|\WP_Error
	 */
	public static function resolve( string $decision, array $selected, string $custom = '' ): array|\WP_Error {
		if ( ! in_array( $decision, array( 'changes', 'reject' ), true ) ) {
			return new \WP_Error( 'invalid_moderation_decision', __( 'A decisão selecionada não é válida.', 'adam-comunidade' ) );
		}

		$selected = array_values( array_unique( array_filter( array_map( 'sanitize_key', $selected ) ) ) );
		if ( empty( $selected ) ) {
			return new \WP_Error( 'moderation_reason_required', __( 'Selecione pelo menos um motivo para comunicar esta decisão.', 'adam-comunidade' ) );
		}

		$available = array_column( self::configured( $decision ), null, 'id' );
		$resolved  = array();
		$can_add_custom = false;
		foreach ( $selected as $id ) {
			if ( ! isset( $available[ $id ] ) ) {
				continue;
			}
			$reason = $available[ $id ];
			$resolved[] = sprintf( '%s — %s', (string) $reason['category'], (string) $reason['label'] );
			$can_add_custom = $can_add_custom || ! empty( $reason['allows_custom'] );
		}
		if ( empty( $resolved ) ) {
			return new \WP_Error( 'moderation_reason_unavailable', __( 'Os motivos selecionados já não estão disponíveis. Reveja a decisão.', 'adam-comunidade' ) );
		}

		$custom = sanitize_textarea_field( $custom );
		if ( $can_add_custom && '' !== trim( $custom ) ) {
			$resolved[] = sprintf( __( 'Informação adicional — %s', 'adam-comunidade' ), $custom );
		}

		return $resolved;
	}

	/**
	 * Builds the backwards-compatible summary saved and emailed.
	 *
	 * @param string[] $reasons Resolved labels.
	 */
	public static function summary( array $reasons ): string {
		return implode( "\n", array_map( static fn( string $reason ): string => '• ' . $reason, $reasons ) );
	}

	/**
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function grouped( string $decision ): array {
		$groups = array();
		foreach ( self::configured( $decision ) as $reason ) {
			$groups[ (string) $reason['category'] ][] = $reason;
		}
		return $groups;
	}

	/**
	 * Creates a normalized default row.
	 *
	 * @return array<string,mixed>
	 */
	private static function reason( string $id, string $category, string $label, bool $allows_custom = false ): array {
		return array(
			'id'            => $id,
			'category'      => $category,
			'label'         => $label,
			'enabled'       => 1,
			'allows_custom' => $allows_custom ? 1 : 0,
		);
	}

	private static function settings_key( string $decision ): string {
		return 'changes' === $decision ? self::CHANGES_KEY : self::REJECT_KEY;
	}
}
