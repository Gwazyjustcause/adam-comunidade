<?php
/**
 * Fields presentation helpers.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Fields;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;

/**
 * Shared field card, pagination, and icon rendering.
 */
final class View {
	/**
	 * Renders one field card.
	 *
	 * @param object     $field      Field row.
	 * @param Repository $repository Fields repository.
	 * @return string
	 */
	public static function card( object $field, Repository $repository ): string {
		$amenities = $repository->amenities( (int) $field->id );
		ob_start();
		require Helpers::path( 'templates/fields/card.php' );

		return (string) ob_get_clean();
	}

	/**
	 * Builds compact AJAX pagination.
	 *
	 * @param int $current Current page.
	 * @param int $pages   Total pages.
	 * @param array<string,mixed> $query_args Query arguments for non-JavaScript links.
	 * @return string
	 */
	public static function pagination( int $current, int $pages, array $query_args = array() ): string {
		if ( $pages <= 1 ) {
			return '';
		}

		$html  = '<nav class="adam-fields-pagination" aria-label="'
			. esc_attr__( 'Paginação dos campos', 'adam-comunidade' ) . '">';
		$start = max( 1, $current - 2 );
		$end   = min( $pages, $current + 2 );

		if ( $current > 1 ) {
			$html .= self::page_button( $current - 1, __( 'Anterior', 'adam-comunidade' ), false, $query_args );
		}
		for ( $page = $start; $page <= $end; ++$page ) {
			$html .= self::page_button( $page, (string) $page, $page === $current, $query_args );
		}
		if ( $current < $pages ) {
			$html .= self::page_button( $current + 1, __( 'Seguinte', 'adam-comunidade' ), false, $query_args );
		}

		return $html . '</nav>';
	}

	/**
	 * Renders a selected amenity icon from a fixed SVG allowlist.
	 *
	 * @param string $icon  Icon key.
	 * @param int    $size  Icon size.
	 * @return string
	 */
	public static function amenity_icon( string $icon, int $size = 22 ): string {
		$paths = array(
			'check'     => '<path d="m20 6-11 11-5-5"/>',
			'parking'   => '<path d="M6 21V3h7a5 5 0 0 1 0 10H6"/><path d="M6 13h7"/>',
			'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
			'gauge'     => '<path d="M20 13a8 8 0 1 0-16 0"/><path d="m12 13 4-4"/>',
			'bolt'      => '<path d="m13 2-9 12h8l-1 8 9-12h-8z"/>',
			'water'     => '<path d="M12 2s6 7 6 12a6 6 0 0 1-12 0c0-5 6-12 6-12z"/>',
			'camping'   => '<path d="m3 21 9-18 9 18"/><path d="M7 21l5-10 5 10"/>',
			'fire'      => '<path d="M12 22c4 0 7-3 7-7 0-3-2-6-5-8 0 3-2 4-3 5 0-4-2-7-4-9 0 6-3 8-3 12 0 4 4 7 8 7z"/>',
			'toilets'   => '<circle cx="7" cy="4" r="2"/><circle cx="17" cy="4" r="2"/><path d="M5 22v-8H3l2-7h4l2 7H9v8M15 22v-6h-2l2-9h4l2 9h-2v6"/>',
			'shop'      => '<path d="M3 9l2-6h14l2 6"/><path d="M5 13v8h14v-8"/><path d="M9 21v-6h6v6"/>',
			'equipment' => '<circle cx="12" cy="12" r="3"/><path d="M19 12h3M2 12h3M12 2v3M12 19v3"/>',
			'battery'   => '<rect x="2" y="6" width="18" height="12" rx="2"/><path d="M22 10v4M7 10v4M5 12h4"/>',
			'food'      => '<path d="M4 3v8M8 3v8M4 7h4M6 11v10M16 3v18M16 3c4 3 4 7 0 10"/>',
			'indoor'    => '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10"/>',
			'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/>',
			'changing'  => '<path d="M6 3h12v18H6zM10 3v18M14 12h.01"/>',
			'first-aid' => '<path d="M9 3h6l1 4h4v14H4V7h4z"/><path d="M9 13h6M12 10v6"/>',
		);
		$path = $paths[ $icon ] ?? $paths['check'];
		$size = max( 14, min( 64, $size ) );

		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none"'
				. ' stroke="currentColor" stroke-width="2" stroke-linecap="round"'
				. ' stroke-linejoin="round" aria-hidden="true">%2$s</svg>',
			$size,
			$path // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Returns a playing style label.
	 *
	 * @param string $key Stored key.
	 * @return string
	 */
	public static function style_label( string $key ): string {
		return Options::playing_styles()[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Builds one pagination button.
	 *
	 * @param int    $page    Page.
	 * @param string $label   Label.
	 * @param bool   $current Current state.
	 * @param array<string,mixed> $query_args Query arguments for non-JavaScript links.
	 * @return string
	 */
	private static function page_button( int $page, string $label, bool $current = false, array $query_args = array() ): string {
		if ( $query_args ) {
			$query_args['pagina'] = $page;
			return sprintf(
				'<a href="%1$s" data-page="%2$d"%3$s>%4$s</a>',
				esc_url( add_query_arg( $query_args, get_permalink() ) ),
				$page,
				$current ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		return sprintf(
			'<button type="button" data-page="%1$d"%2$s>%3$s</button>',
			$page,
			$current ? ' aria-current="page"' : '',
			esc_html( $label )
		);
	}
}
