<?php
/**
 * Public single event.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Events\Api;
use ADAM\Comunidade\Events\Router;
use ADAM\Comunidade\Public_Hero;

$event = Router::current_event();
get_header();
?>
<main class="adam-events adam-event">
	<p class="adam-events__back"><a href="<?php echo esc_url( home_url( '/eventos/' ) ); ?>">&larr; <?php esc_html_e( 'Voltar aos eventos', 'adam-comunidade' ); ?></a></p>
	<article>
		<header class="<?php echo esc_attr( Public_Hero::root( 'adam-event__hero', 'single' ) ); ?>"<?php echo $event->cover_image() ? ' style="background-image:linear-gradient(rgba(4,15,10,.72),rgba(4,15,10,.78)),url(' . esc_url( $event->cover_image() ) . ')"' : ''; ?>>
			<time datetime="<?php echo esc_attr( $event->event_date() ); ?>"><?php echo esc_html( wp_date( 'j \d\e F \d\e Y', $event->starts_at_timestamp() ) ); ?></time>
			<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $event->title() ); ?></h1>
			<?php if ( $event->short_description() ) : ?><p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php echo esc_html( $event->short_description() ); ?></p><?php endif; ?>
		</header>
		<div class="adam-event__content">
			<dl>
				<?php if ( $event->start_time() ) : ?><div><dt><?php esc_html_e( 'Horário', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( $event->start_time() . ( $event->end_time() ? '–' . $event->end_time() : '' ) ); ?></dd></div><?php endif; ?>
				<?php if ( $event->location() ) : ?><div><dt><?php esc_html_e( 'Local', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( $event->location() ); ?></dd></div><?php endif; ?>
				<?php if ( $event->player_limit() ) : ?><div><dt><?php esc_html_e( 'Limite', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( sprintf( __( '%d participantes', 'adam-comunidade' ), $event->player_limit() ) ); ?></dd></div><?php endif; ?>
				<?php if ( $event->is_paid() && $event->price() ) : ?><div><dt><?php esc_html_e( 'Preço', 'adam-comunidade' ); ?></dt><dd><?php echo esc_html( $event->price() ); ?></dd></div><?php endif; ?>
			</dl>
			<?php if ( $event->full_description() ) : ?><div class="adam-event__description"><?php echo wp_kses_post( wpautop( $event->full_description() ) ); ?></div><?php endif; ?>
			<div class="adam-event__actions">
				<?php if ( $event->map_link() ) : ?><a class="button" href="<?php echo esc_url( $event->map_link() ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'Ver no mapa', 'adam-comunidade' ); ?></a><?php endif; ?>
				<?php if ( $event->external_registration_url() && $event->is_registration_open() ) : ?><a class="button button-primary" href="<?php echo esc_url( $event->external_registration_url() ); ?>" rel="noopener noreferrer" target="_blank"><?php echo $event->external_provider_name() ? esc_html( sprintf( __( 'Inscrever em %s', 'adam-comunidade' ), $event->external_provider_name() ) ) : esc_html__( 'Inscrever no evento', 'adam-comunidade' ); ?></a><?php endif; ?>
			</div>
		</div>
	</article>
</main>
<?php get_footer(); ?>
