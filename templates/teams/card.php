<?php
/**
 * Public team card.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Router;
use ADAM\Comunidade\Teams\View;

$adam_card_styles = Options::decode_list( $team->playing_styles );
?>
<article class="adam-team-card adam-directory-card<?php echo ! empty( $team->is_associated ) ? ' adam-team-card--associated' : ''; ?>">
	<a class="adam-team-card__media" href="<?php echo esc_url( Router::team_url( $team ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $team->cover_id ) : ?>
			<?php echo wp_get_attachment_image( (int) $team->cover_id, 'adam-team-card', false, array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<?php echo Placeholder_Image::cover( 'team', (string) $team->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</a>
	<?php if ( ! empty( $team->is_associated ) ) : ?>
		<span class="adam-badge adam-badge--associated adam-directory-badge adam-team-card__association-badge"><?php esc_html_e( 'Equipa Associada ADAM', 'adam-comunidade' ); ?></span>
	<?php endif; ?>
	<div class="adam-team-card__body">
		<div class="adam-team-card__status">
			<?php if ( ! empty( $team->featured ) ) : ?><span class="adam-comunidade__badge adam-directory-badge"><?php esc_html_e( 'Em destaque', 'adam-comunidade' ); ?></span><?php endif; ?>
			<?php if ( 'verified_team' === ( $team->verification ?? '' ) ) : ?><span class="adam-badge adam-badge--verified adam-directory-badge"><?php esc_html_e( 'Equipa verificada', 'adam-comunidade' ); ?></span><?php endif; ?>
		</div>
		<div class="adam-team-card__identity">
			<div class="adam-team-card__logo">
				<?php if ( $team->logo_id ) : ?>
					<?php echo wp_get_attachment_image( (int) $team->logo_id, 'adam-team-logo', false, array( 'loading' => 'lazy' ) ); ?>
				<?php else : ?>
					<?php echo Placeholder_Image::avatar( 'team', (string) $team->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
			<?php if ( $team->members ) : ?><span><?php echo esc_html( sprintf( _n( '%d membro', '%d membros', (int) $team->members, 'adam-comunidade' ), (int) $team->members ) ); ?></span><?php endif; ?>
			<?php if ( $team->recruitment_status ) : ?><span><?php echo esc_html( View::label( $team->recruitment_status, Options::recruitment_statuses() ) ); ?></span><?php endif; ?>
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
