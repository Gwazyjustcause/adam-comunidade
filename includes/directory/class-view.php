<?php
/**
 * Directory presentation helpers.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Renders reusable public fragments.
 */
final class View {
	public static function card( object $entry ): string {
		$definition = Types::get( $entry->entity_type );
		if ( ! $definition ) {
			return '';
		}
		ob_start();
		?>
		<article class="adam-community-card" data-entity-type="<?php echo esc_attr( $entry->entity_type ); ?>" data-entity-id="<?php echo esc_attr( $entry->id ); ?>">
			<a class="adam-community-card__media" href="<?php echo esc_url( Router::entry_url( $entry ) ); ?>">
				<?php if ( $entry->logo_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $entry->logo_id, 'adam-directory-logo', false, array( 'loading' => 'lazy' ) ); ?>
				<?php elseif ( $entry->cover_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $entry->cover_id, 'adam-directory-card', false, array( 'loading' => 'lazy' ) ); ?>
				<?php else : ?><span class="adam-community-card__placeholder" aria-hidden="true"></span><?php endif; ?>
			</a>
			<div class="adam-community-card__body">
				<?php if ( $entry->featured ) : ?><span class="adam-community-badge"><?php esc_html_e( 'Featured', 'adam-comunidade' ); ?></span><?php endif; ?>
				<?php if ( $entry->verification ) : ?><span class="adam-community-badge adam-badge--verified"><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry->verification ) ) ); ?></span><?php endif; ?>
				<h2><a href="<?php echo esc_url( Router::entry_url( $entry ) ); ?>"><?php echo esc_html( $entry->name ); ?></a></h2>
				<?php if ( $entry->category && isset( $definition['categories'][ $entry->category ] ) ) : ?><p class="adam-community-card__meta"><?php echo esc_html( $definition['categories'][ $entry->category ] ); ?></p><?php endif; ?>
				<?php if ( $entry->short_description ) : ?><p><?php echo esc_html( $entry->short_description ); ?></p><?php endif; ?>
				<div class="adam-community-card__actions">
					<a class="adam-community-button" href="<?php echo esc_url( Router::entry_url( $entry ) ); ?>"><?php esc_html_e( 'View details', 'adam-comunidade' ); ?></a>
					<?php if ( $entry->website ) : ?><a class="adam-community-button adam-community-button--ghost" href="<?php echo esc_url( $entry->website ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit Website', 'adam-comunidade' ); ?></a><?php endif; ?>
				</div>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	public static function pagination( int $page, int $pages ): string {
		if ( $pages <= 1 ) {
			return '';
		}
		$output = '<nav class="adam-community-pagination" aria-label="' . esc_attr__( 'Directory pages', 'adam-comunidade' ) . '">';
		for ( $number = 1; $number <= $pages; $number++ ) {
			$output .= '<button type="button" data-page="' . esc_attr( (string) $number ) . '"'
				. ( $number === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( (string) $number ) . '</button>';
		}
		return $output . '</nav>';
	}

	public static function contacts( object $entry ): string {
		$contacts = array(
			array( '🌍', __( 'Website', 'adam-comunidade' ), $entry->website ),
			array( 'f', 'Facebook', $entry->facebook ),
			array( '◎', 'Instagram', $entry->instagram ),
			array( '📧', __( 'Email', 'adam-comunidade' ), $entry->email ? 'mailto:' . $entry->email : '' ),
			array( '📞', __( 'Phone', 'adam-comunidade' ), $entry->phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $entry->phone ) : '' ),
			array( '📍', __( 'Address', 'adam-comunidade' ), $entry->address ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $entry->address ) : '' ),
		);
		ob_start();
		foreach ( $contacts as $contact ) {
			if ( $contact[2] ) {
				printf( '<a class="adam-contact-button" href="%1$s" target="_blank" rel="noopener"><span aria-hidden="true">%2$s</span>%3$s</a>', esc_url( $contact[2] ), esc_html( $contact[0] ), esc_html( $contact[1] ) );
			}
		}
		return (string) ob_get_clean();
	}
}
