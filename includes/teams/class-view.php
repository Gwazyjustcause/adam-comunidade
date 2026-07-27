<?php
/**
 * Teams presentation helpers.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Teams;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;

/**
 * Shared rendering used by initial pages and AJAX responses.
 */
final class View {
	/**
	 * Renders one team card.
	 *
	 * @param object $team Team record.
	 * @return string
	 */
	public static function card( object $team ): string {
		ob_start();
		require Helpers::path( 'templates/teams/card.php' );

		return (string) ob_get_clean();
	}

	/**
	 * Builds accessible pagination controls.
	 *
	 * @param int $current Current page.
	 * @param int $pages   Total pages.
	 * @return string
	 */
	public static function pagination( int $current, int $pages ): string {
		if ( $pages <= 1 ) {
			return '';
		}

		$html = '<nav class="adam-teams-pagination" aria-label="'
			. esc_attr__( 'Paginação das equipas', 'adam-comunidade' ) . '">';
		$start = max( 1, $current - 2 );
		$end   = min( $pages, $current + 2 );

		if ( $current > 1 ) {
			$html .= self::page_button( $current - 1, __( 'Anterior', 'adam-comunidade' ) );
		}

		for ( $page = $start; $page <= $end; ++$page ) {
			$html .= self::page_button( $page, (string) $page, $page === $current );
		}

		if ( $current < $pages ) {
			$html .= self::page_button( $current + 1, __( 'Seguinte', 'adam-comunidade' ) );
		}

		return $html . '</nav>';
	}

	/**
	 * Builds one AJAX pagination button.
	 *
	 * @param int    $page    Target page.
	 * @param string $label   Button label.
	 * @param bool   $current Whether this is the current page.
	 * @return string
	 */
	private static function page_button( int $page, string $label, bool $current = false ): string {
		return sprintf(
			'<button type="button" data-page="%1$d"%2$s>%3$s</button>',
			$page,
			$current ? ' aria-current="page"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Gets a label from a keyed option collection.
	 *
	 * @param string               $key     Stored key.
	 * @param array<string,string> $options Options collection.
	 * @return string
	 */
	public static function label( string $key, array $options ): string {
		return $options[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
	}
}
