<?php
/**
 * Automatic related content engine.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Experience;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Directory\Repository as Directory_Repository;
use ADAM\Comunidade\Directory\View as Directory_View;
use ADAM\Comunidade\Fields\Repository as Field_Repository;
use ADAM\Comunidade\Fields\View as Field_View;
use ADAM\Comunidade\Teams\Repository as Team_Repository;
use ADAM\Comunidade\Teams\View as Team_View;

/**
 * Derives nearby and relevant content from location and existing relationships.
 */
final class Related_Content {
	public function __construct(
		private Team_Repository $teams,
		private Field_Repository $fields,
		private Directory_Repository $directory
	) {}

	public function register(): void {
		add_action( 'adam_comunidade_team_after_content', array( $this, 'for_team' ), 20 );
		add_action( 'adam_comunidade_field_after_content', array( $this, 'for_field' ), 20 );
		add_action( 'adam_comunidade_directory_entry_content', array( $this, 'for_directory' ), 20 );
	}

	public function for_team( object $team ): void {
		$nearby_teams = $this->without( $this->teams->query( array( 'status' => 'published', 'district' => $team->district, 'per_page' => 4, 'orderby' => 'updated_at' ) )['items'], (int) $team->id );
		$fields = $this->fields->query( array( 'status' => 'published', 'district' => $team->district, 'playing_style' => $this->first_style( $team ), 'per_page' => 4 ) )['items'];
		$partners = $this->directory->query( 'partner', array( 'status' => 'published', 'district' => $team->district, 'per_page' => 4 ) )['items'];
		echo $this->section( __( 'Campos recomendados', 'adam-comunidade' ), array_map( fn( object $item ): string => Field_View::card( $item, $this->fields ), $fields ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->section( __( 'Descontos de parceiros', 'adam-comunidade' ), array_map( array( Directory_View::class, 'card' ), array_filter( $partners, static fn( object $item ): bool => (bool) $item->benefits ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->section( __( 'Equipas próximas', 'adam-comunidade' ), array_map( array( Team_View::class, 'card' ), $nearby_teams ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function for_field( object $field ): void {
		$nearby_fields = $this->without( $this->fields->query( array( 'status' => 'published', 'district' => $field->district, 'per_page' => 5 ) )['items'], (int) $field->id );
		$partners = $this->directory->query( 'partner', array( 'status' => 'published', 'district' => $field->district, 'per_page' => 6 ) )['items'];
		$shops = array_filter( $partners, static fn( object $item ): bool => 'loja' === $item->category );
		echo $this->section( __( 'Campos próximos', 'adam-comunidade' ), array_map( fn( object $item ): string => Field_View::card( $item, $this->fields ), $nearby_fields ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->section( __( 'Parceiros próximos', 'adam-comunidade' ), array_map( array( Directory_View::class, 'card' ), $partners ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->section( __( 'Lojas próximas', 'adam-comunidade' ), array_map( array( Directory_View::class, 'card' ), $shops ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->related_news( 'field', (int) $field->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function for_directory( object $entry ): void {
		echo $this->related_news( $entry->entity_type, (int) $entry->id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'partner' === $entry->entity_type && $entry->member_benefits && self::is_member() ) {
			echo '<section class="adam-community-section adam-member-only"><span class="adam-community-badge">' . esc_html__( 'ADAM Members', 'adam-comunidade' ) . '</span><h2>' . esc_html__( 'Benefícios exclusivos para membros', 'adam-comunidade' ) . '</h2>' . wp_kses_post( wpautop( $entry->member_benefits ) ) . '</section>';
		}
	}

	public static function is_member(): bool {
		$available = defined( 'ADAM_MEMBERS_VERSION' ) || class_exists( 'ADAM_Members' );
		return (bool) apply_filters( 'adam_comunidade_current_user_is_member', $available && is_user_logged_in(), get_current_user_id() );
	}

	private function related_news( string $type, int $id ): string {
		$query = new \WP_Query(
			array(
				'post_type' => 'adam_news', 'post_status' => 'publish', 'posts_per_page' => 3, 'no_found_rows' => true,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array( 'key' => '_adam_related_type', 'value' => $type ),
					array( 'key' => '_adam_related_id', 'value' => $id, 'type' => 'NUMERIC' ),
				),
			)
		);
		if ( ! $query->posts ) {
			return '';
		}
		$cards = array_map( static fn( \WP_Post $post ): string => '<article class="adam-news-card"><h3><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h3><p>' . esc_html( get_the_excerpt( $post ) ) . '</p></article>', $query->posts );
		return $this->section( __( 'Notícias relacionadas', 'adam-comunidade' ), $cards );
	}

	private function section( string $title, array $cards ): string {
		$cards = array_filter( $cards );
		return $cards ? '<section class="adam-community-section adam-related-content"><h2>' . esc_html( $title ) . '</h2><div class="adam-community-grid">' . implode( '', $cards ) . '</div></section>' : '';
	}

	private function without( array $items, int $id ): array {
		return array_values( array_filter( $items, static fn( object $item ): bool => (int) $item->id !== $id ) );
	}

	private function first_style( object $item ): string {
		$styles = json_decode( $item->playing_styles ?? '[]', true );
		return sanitize_key( $styles[0] ?? '' );
	}
}
