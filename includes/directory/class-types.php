<?php
/**
 * Directory entity definitions.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Central configuration for independently registered directory entities.
 */
final class Types {
	/**
	 * Returns all registered directory types.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		$types = array(
			'partner' => array(
				'plural'     => __( 'Parceiros', 'adam-comunidade' ),
				'singular'   => __( 'Parceiro', 'adam-comunidade' ),
				'slug'       => 'parceiros',
				'menu_slug'  => 'adam-comunidade-partners',
				'categories' => array(
					'sponsor'       => __( 'Sponsor', 'adam-comunidade' ),
					'loja'          => __( 'Loja', 'adam-comunidade' ),
					'technical'     => __( 'Parceiro Técnico', 'adam-comunidade' ),
					'institutional' => __( 'Parceiro Institucional', 'adam-comunidade' ),
					'supporter'     => __( 'Apoiante', 'adam-comunidade' ),
					'organisation'  => __( 'Organização', 'adam-comunidade' ),
				),
				'icon'       => 'groups',
				'marker'     => 'shop',
			),
			'institution' => array(
				'plural'     => __( 'Instituições', 'adam-comunidade' ),
				'singular'   => __( 'Instituição', 'adam-comunidade' ),
				'slug'       => 'instituicoes',
				'menu_slug'  => 'adam-comunidade-institutions',
				'categories' => array(
					'municipality'     => __( 'Município', 'adam-comunidade' ),
					'parish'           => __( 'Junta de Freguesia', 'adam-comunidade' ),
					'public_body'      => __( 'Entidade Pública', 'adam-comunidade' ),
					'security'         => __( 'Força de Segurança', 'adam-comunidade' ),
					'emergency'        => __( 'Emergência e Proteção Civil', 'adam-comunidade' ),
					'sports_authority' => __( 'Entidade Desportiva', 'adam-comunidade' ),
				),
				'icon'       => 'bank',
				'marker'     => 'institution',
			),
			'brand' => array(
				'plural'     => __( 'Marcas', 'adam-comunidade' ),
				'singular'   => __( 'Marca', 'adam-comunidade' ),
				'slug'       => 'marcas',
				'menu_slug'  => 'adam-comunidade-brands',
				'categories' => array(),
				'icon'       => 'tag',
				'marker'     => 'brand',
			),
		);

		return apply_filters( 'adam_comunidade_directory_types', $types );
	}

	/**
	 * Returns one type, or null for an invalid key.
	 *
	 * @param string $type Type key.
	 * @return array<string,mixed>|null
	 */
	public static function get( string $type ): ?array {
		return self::all()[ sanitize_key( $type ) ] ?? null;
	}

	/**
	 * Resolves a public slug to its type.
	 *
	 * @param string $slug Public archive slug.
	 * @return string
	 */
	public static function from_slug( string $slug ): string {
		foreach ( self::all() as $type => $definition ) {
			if ( $definition['slug'] === $slug ) {
				return $type;
			}
		}

		return '';
	}
}
