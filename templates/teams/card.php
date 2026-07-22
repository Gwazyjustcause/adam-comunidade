<?php
/**
 * Public team card.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Router;
use ADAM\Comunidade\Teams\View;
use ADAM\Comunidade\Helpers;

$adam_card_styles = Options::decode_list( $team->playing_styles );
?>
<article class="adam-team-card">
	<a class="adam-team-card__media" href="<?php echo esc_url( Router::team_url( $team ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $team->cover_id ) : ?>
			<?php echo wp_get_attachment_image( (int) $team->cover_id, 'adam-team-card', false, array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="adam-team-card__cover-placeholder"></span>
		<?php endif; ?>
	</a>
	<div class="adam-team-card__body">
		<div class="adam-team-card__identity">
			<div class="adam-team-card__logo">
				<?php if ( $team->logo_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $team->logo_id, 'adam-team-logo', false, array( 'loading' => 'lazy' ) ); ?>
				<?php else : ?>
					<?php echo Helpers::svg_icon( 'community', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
			<div>
				<h2><a href="<?php echo esc_url( Router::team_url( $team ) ); ?>"><?php echo esc_html( $team->name ); ?></a></h2>
				<?php if ( $team->municipality || $team->district ) : ?>
					<p><?php echo esc_html( implode( ', ', array_filter( array( $team->municipality, $team->district ) ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="adam-team-card__meta">
			<span><?php echo esc_html( sprintf( _n( '%d member', '%d members', (int) $team->members, 'adam-comunidade' ), (int) $team->members ) ); ?></span>
		</div>
		<?php if ( $adam_card_styles ) : ?>
			<div class="adam-team-badges">
				<?php foreach ( array_slice( $adam_card_styles, 0, 3 ) as $adam_style ) : ?>
					<span><?php echo esc_html( View::label( $adam_style, Options::playing_styles() ) ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<a class="adam-team-button" href="<?php echo esc_url( Router::team_url( $team ) ); ?>"><?php esc_html_e( 'Ver Equipa', 'adam-comunidade' ); ?></a>
	</div>
</article>
