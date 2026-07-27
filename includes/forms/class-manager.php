<?php
/**
 * Shared public form configuration.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the schema and persisted configuration for every public form.
 */
final class Manager {
	public const OPTION_NAME = 'adam_comunidade_public_forms';

	/**
	 * Returns the supported form types and their administrator labels.
	 *
	 * @return array<string,string>
	 */
	public function types(): array {
		return array(
			'field'       => __( 'Formulário de Campos', 'adam-comunidade' ),
			'team'        => __( 'Formulário de Equipas', 'adam-comunidade' ),
			'partner'     => __( 'Formulário de Parceiros', 'adam-comunidade' ),
			'institution' => __( 'Formulário de Instituições', 'adam-comunidade' ),
		);
	}

	/**
	 * Returns one merged form configuration.
	 *
	 * @param string $type Form type.
	 * @return array<string,mixed>
	 */
	public function get( string $type ): array {
		$defaults = $this->defaults();
		if ( ! isset( $defaults[ $type ] ) ) {
			return array();
		}

		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) || empty( $stored[ $type ] ) || ! is_array( $stored[ $type ] ) ) {
			return $defaults[ $type ];
		}

		$form           = wp_parse_args( $stored[ $type ], $defaults[ $type ] );
		$form['fields'] = $this->merge_fields( (array) ( $stored[ $type ]['fields'] ?? array() ), $defaults[ $type ]['fields'] );
		foreach ( $this->semantic_types() as $key => $field_type ) {
			if ( isset( $form['fields'][ $key ] ) ) {
				$form['fields'][ $key ]['type'] = $field_type;
			}
		}
		foreach ( array( 'playing_styles', 'amenities', 'rules', 'recommended_players', 'max_players' ) as $optional_key ) {
			if ( isset( $form['fields'][ $optional_key ] ) ) {
				$form['fields'][ $optional_key ]['required'] = false;
			}
		}

		return $form;
	}

	/**
	 * Saves all supported form configurations.
	 *
	 * @param array<string,mixed> $input Posted configuration.
	 * @return void
	 */
	public function save( array $input ): void {
		$clean = array();

		foreach ( array_keys( $this->types() ) as $type ) {
			$current = $this->get( $type );
			$posted  = isset( $input[ $type ] ) && is_array( $input[ $type ] ) ? $input[ $type ] : array();
			$fields  = array();

			foreach ( (array) ( $posted['fields'] ?? array() ) as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$key = sanitize_key( (string) ( $field['key'] ?? '' ) );
				if ( ! $key || ! isset( $current['fields'][ $key ] ) ) {
					continue;
				}
				$base     = $current['fields'][ $key ];
				$file_max = max( 1, min( 20, absint( $field['max_files'] ?? $base['max_files'] ) ) );
				$fields[ $key ] = array(
					'key'         => $key,
					'label'       => sanitize_text_field( wp_unslash( $field['label'] ?? $base['label'] ) ),
					'description' => sanitize_textarea_field( wp_unslash( $field['description'] ?? $base['description'] ) ),
					'help_text'   => sanitize_textarea_field( wp_unslash( $field['help_text'] ?? $base['help_text'] ) ),
					'placeholder' => sanitize_text_field( wp_unslash( $field['placeholder'] ?? $base['placeholder'] ) ),
					'type'        => $this->sanitize_type( (string) ( $field['type'] ?? $base['type'] ) ),
					'visible'     => ! empty( $field['visible'] ),
					'required'    => ! empty( $field['required'] ),
					'accept'      => sanitize_text_field( wp_unslash( $field['accept'] ?? $base['accept'] ) ),
					'max_files'   => $file_max,
					'max_size_mb' => max( 1, min( 100, absint( $field['max_size_mb'] ?? $base['max_size_mb'] ) ) ),
				);
			}

			// Keep newly introduced fields available after plugin updates.
			foreach ( $current['fields'] as $key => $field ) {
				if ( ! isset( $fields[ $key ] ) ) {
					$fields[ $key ] = $field;
				}
			}

			$clean[ $type ] = array(
				'title'                => sanitize_text_field( wp_unslash( $posted['title'] ?? $current['title'] ) ),
				'description'          => sanitize_textarea_field( wp_unslash( $posted['description'] ?? $current['description'] ) ),
				'notice_title'         => sanitize_text_field( wp_unslash( $posted['notice_title'] ?? $current['notice_title'] ) ),
				'notice_text'          => sanitize_textarea_field( wp_unslash( $posted['notice_text'] ?? $current['notice_text'] ) ),
				'confirmation_message' => sanitize_textarea_field( wp_unslash( $posted['confirmation_message'] ?? $current['confirmation_message'] ) ),
				'success_message'      => sanitize_textarea_field( wp_unslash( $posted['success_message'] ?? $current['success_message'] ) ),
				'review_time'          => sanitize_text_field( wp_unslash( $posted['review_time'] ?? $current['review_time'] ) ),
				'submit_label'         => sanitize_text_field( wp_unslash( $posted['submit_label'] ?? $current['submit_label'] ) ),
				'fields'               => $fields,
			);
		}

		update_option( self::OPTION_NAME, $clean, false );
	}

	/**
	 * Returns public labels for the available field controls.
	 *
	 * @return array<string,string>
	 */
	public function field_types(): array {
		return array(
			'text'     => __( 'Texto', 'adam-comunidade' ),
			'email'    => __( 'E-mail', 'adam-comunidade' ),
			'url'      => __( 'Endereço Web', 'adam-comunidade' ),
			'tel'      => __( 'Telefone', 'adam-comunidade' ),
			'number'   => __( 'Número', 'adam-comunidade' ),
			'textarea' => __( 'Texto longo', 'adam-comunidade' ),
			'richtext' => __( 'Editor de texto formatado', 'adam-comunidade' ),
			'playing_styles' => __( 'Estilos de jogo configurados', 'adam-comunidade' ),
			'amenities' => __( 'Comodidades configuradas', 'adam-comunidade' ),
			'team_recruitment' => __( 'Estados de recrutamento configurados', 'adam-comunidade' ),
			'file'     => __( 'Ficheiro', 'adam-comunidade' ),
		);
	}

	/**
	 * Returns default form definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function defaults(): array {
		$common = array(
			'name' => $this->field( 'name', __( 'Nome', 'adam-comunidade' ), 'text', true, __( 'Nome público da entidade', 'adam-comunidade' ) ),
			'contact_email' => $this->field( 'contact_email', __( 'E-mail de contacto', 'adam-comunidade' ), 'email', true, __( 'nome@exemplo.pt', 'adam-comunidade' ) ),
			'district' => $this->field( 'district', __( 'Distrito', 'adam-comunidade' ), 'text', false, __( 'Ex.: Coimbra', 'adam-comunidade' ) ),
			'municipality' => $this->field( 'municipality', __( 'Concelho', 'adam-comunidade' ), 'text', false, __( 'Ex.: Montemor-o-Velho', 'adam-comunidade' ) ),
			'website' => $this->field( 'website', __( 'Website', 'adam-comunidade' ), 'url', false, 'https://' ),
			'phone' => $this->field( 'phone', __( 'Telefone', 'adam-comunidade' ), 'tel', false, __( 'Contacto telefónico', 'adam-comunidade' ) ),
			'short_description' => $this->field( 'short_description', __( 'Descrição', 'adam-comunidade' ), 'textarea', true, __( 'Apresente a entidade de forma breve.', 'adam-comunidade' ) ),
			'verification_details' => $this->field( 'verification_details', __( 'Informação para verificação', 'adam-comunidade' ), 'textarea', true, __( 'Indique os dados que ajudam a ADAM a validar esta submissão.', 'adam-comunidade' ) ),
		);
		$base = array(
			'description'          => __( 'Todas as submissões são revistas manualmente pela ADAM antes da publicação.', 'adam-comunidade' ),
			'notice_title'         => '',
			'notice_text'          => '',
			'confirmation_message' => __( 'Confirmo que a informação fornecida é verdadeira e pode ser verificada pela ADAM.', 'adam-comunidade' ),
			'success_message'      => __( 'Obrigado. A submissão foi recebida e está a aguardar revisão.', 'adam-comunidade' ),
			'review_time'          => __( '2–5 dias úteis', 'adam-comunidade' ),
			'submit_label'         => __( 'Enviar para revisão', 'adam-comunidade' ),
		);

		return array(
			'field' => array_merge(
				$base,
				array(
					'title'        => __( 'Submeter Campo', 'adam-comunidade' ),
					'notice_title' => __( 'Autorização legal obrigatória', 'adam-comunidade' ),
					'notice_text'  => __( 'A ADAM apenas publica campos com autorização ou permissão legal para funcionar. É obrigatório anexar uma cópia legível do documento. Submissões sem comprovativo não serão aprovadas.', 'adam-comunidade' ),
					'fields'       => array_merge(
						array_slice( $common, 0, 4, true ),
						array( 'address' => $this->field( 'address', __( 'Morada', 'adam-comunidade' ), 'text', true, __( 'Morada completa do campo', 'adam-comunidade' ) ) ),
						array_slice( $common, 4, null, true ),
						array(
							'playing_styles' => $this->field( 'playing_styles', __( 'Estilos de jogo', 'adam-comunidade' ), 'playing_styles', false, '', __( 'Selecione todos os estilos aplicáveis ao campo.', 'adam-comunidade' ) ),
							'amenities' => $this->field( 'amenities', __( 'Comodidades', 'adam-comunidade' ), 'amenities', false, '', __( 'Indique as instalações e serviços disponíveis.', 'adam-comunidade' ) ),
							'rules' => $this->field( 'rules', __( 'Regras do campo', 'adam-comunidade' ), 'richtext', false, '', __( 'Inclua limites de FPS/Joule, regras de medic, BB Bio, pirotecnia, distâncias mínimas, idade e outras regras locais.', 'adam-comunidade' ) ),
							'recommended_players' => $this->field( 'recommended_players', __( 'Número recomendado de jogadores', 'adam-comunidade' ), 'number', false, '0' ),
							'max_players' => $this->field( 'max_players', __( 'Máximo de jogadores', 'adam-comunidade' ), 'number', false, '0' ),
							'authorization_document' => $this->field( 'authorization_document', __( 'Comprovativo de autorização legal', 'adam-comunidade' ), 'file', true, '', __( 'PDF, JPG ou PNG. Documento obrigatório para análise administrativa.', 'adam-comunidade' ), '.pdf,.jpg,.jpeg,.png', 1 ),
							'field_photos' => $this->field( 'field_photos', __( 'Fotografias do campo', 'adam-comunidade' ), 'file', false, '', __( 'Até cinco fotografias em JPG, PNG ou WebP.', 'adam-comunidade' ), '.jpg,.jpeg,.png,.webp', 5 ),
						)
					),
				)
			),
			'team' => array_merge(
				$base,
				array(
					'title'  => __( 'Submeter Equipa', 'adam-comunidade' ),
					'fields' => array_merge(
						array_slice( $common, 0, 1, true ),
						array(
							'short_name' => $this->field( 'short_name', __( 'Nome abreviado', 'adam-comunidade' ), 'text', false ),
						),
						array_slice( $common, 1, null, true ),
						array(
							'full_description' => $this->field( 'full_description', __( 'Apresentação completa', 'adam-comunidade' ), 'richtext', false ),
							'founded' => $this->field( 'founded', __( 'Ano de fundação', 'adam-comunidade' ), 'number', false ),
							'members' => $this->field( 'members', __( 'Número de membros', 'adam-comunidade' ), 'number', false ),
							'playing_styles' => $this->field( 'playing_styles', __( 'Estilos de jogo', 'adam-comunidade' ), 'playing_styles', false, '', __( 'Selecione todos os estilos praticados pela equipa.', 'adam-comunidade' ) ),
							'recruitment_status' => $this->field( 'recruitment_status', __( 'Estado do recrutamento', 'adam-comunidade' ), 'team_recruitment', false ),
							'facebook' => $this->field( 'facebook', 'Facebook', 'url', false, 'https://' ),
							'instagram' => $this->field( 'instagram', 'Instagram', 'url', false, 'https://' ),
							'team_logo' => $this->field( 'team_logo', __( 'Logótipo da equipa', 'adam-comunidade' ), 'file', false, '', __( 'JPG, PNG ou WebP.', 'adam-comunidade' ), '.jpg,.jpeg,.png,.webp', 1 ),
							'team_cover' => $this->field( 'team_cover', __( 'Imagem de capa', 'adam-comunidade' ), 'file', false, '', __( 'Imagem panorâmica em JPG, PNG ou WebP.', 'adam-comunidade' ), '.jpg,.jpeg,.png,.webp', 1 ),
							'team_photos' => $this->field( 'team_photos', __( 'Fotografias da equipa', 'adam-comunidade' ), 'file', false, '', __( 'Pode enviar até cinco fotografias.', 'adam-comunidade' ), '.jpg,.jpeg,.png,.webp', 5 ),
						)
					),
				)
			),
			'partner' => array_merge( $base, array( 'title' => __( 'Submeter Parceiro', 'adam-comunidade' ), 'fields' => $common ) ),
			'institution' => array_merge( $base, array( 'title' => __( 'Submeter Instituição', 'adam-comunidade' ), 'fields' => $common ) ),
		);
	}

	/**
	 * Builds a field definition.
	 *
	 * @return array<string,mixed>
	 */
	private function field( string $key, string $label, string $type, bool $required, string $placeholder = '', string $description = '', string $accept = '', int $max_files = 1, int $max_size_mb = 10 ): array {
		return compact( 'key', 'label', 'type', 'required', 'placeholder', 'description', 'accept', 'max_files', 'max_size_mb' ) + array(
			'visible'   => true,
			'help_text' => '',
		);
	}

	/**
	 * Preserves saved order while applying defaults for every field property.
	 *
	 * @param array<string,mixed> $stored Stored fields.
	 * @param array<string,mixed> $defaults Default fields.
	 * @return array<string,array<string,mixed>>
	 */
	private function merge_fields( array $stored, array $defaults ): array {
		$merged = array();
		foreach ( $stored as $key => $field ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $defaults[ $key ] ) && is_array( $field ) ) {
				$merged[ $key ] = wp_parse_args( $field, $defaults[ $key ] );
			}
		}
		foreach ( $defaults as $key => $field ) {
			if ( ! isset( $merged[ $key ] ) ) {
				$merged[ $key ] = $field;
			}
		}
		return $merged;
	}

	private function sanitize_type( string $type ): string {
		return isset( $this->field_types()[ $type ] ) ? $type : 'text';
	}

	/**
	 * Field keys whose control type is tied to shared plugin configuration.
	 *
	 * @return array<string,string>
	 */
	private function semantic_types(): array {
		return array(
			'playing_styles' => 'playing_styles',
			'amenities'      => 'amenities',
			'rules'          => 'richtext',
			'recruitment_status' => 'team_recruitment',
		);
	}
}
