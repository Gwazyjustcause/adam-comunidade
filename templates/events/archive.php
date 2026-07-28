<?php
/**
 * Public Events archive and calendar.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Events\Api;
use ADAM\Comunidade\Public_Hero;

$requested_view  = sanitize_key( (string) wp_unslash( $_GET['view'] ?? '' ) );
$requested_month = sanitize_text_field( (string) wp_unslash( $_GET['month'] ?? '' ) );
$view = 'calendar' === $requested_view ? 'calendar' : 'list';
$month_parts = preg_match( '/^(\d{4})-(\d{2})$/', $requested_month, $matches ) ? array( (int) $matches[1], (int) $matches[2] ) : array();
$month = $month_parts && checkdate( $month_parts[1], 1, $month_parts[0] ) ? $requested_month : wp_date( 'Y-m' );
$events = Api::instance()->get_events( array( 'status' => \ADAM\Comunidade\Events\Event::STATUS_PUBLISHED ) );
$month_start = strtotime( $month . '-01' );
$previous = wp_date( 'Y-m', strtotime( '-1 month', $month_start ) );
$next = wp_date( 'Y-m', strtotime( '+1 month', $month_start ) );

get_header();
?>
<main class="adam-events">
	<header class="<?php echo esc_attr( Public_Hero::root( 'adam-events__hero', 'archive' ) ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'content' ) ); ?>">
			<p class="<?php echo esc_attr( Public_Hero::element( 'kicker', 'adam-events__eyebrow' ) ); ?>"><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></p>
			<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php esc_html_e( 'Eventos', 'adam-comunidade' ); ?></h1>
			<p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php esc_html_e( 'Descubra os próximos encontros, jogos e iniciativas da comunidade de airsoft.', 'adam-comunidade' ); ?></p>
		</div>
	</header>
	<nav class="adam-events__views" aria-label="<?php esc_attr_e( 'Vista dos eventos', 'adam-comunidade' ); ?>">
		<a class="<?php echo 'list' === $view ? 'is-active' : ''; ?>" <?php echo 'list' === $view ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( home_url( '/eventos/' ) ); ?>"><?php esc_html_e( 'Lista', 'adam-comunidade' ); ?></a>
		<a class="<?php echo 'calendar' === $view ? 'is-active' : ''; ?>" <?php echo 'calendar' === $view ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( add_query_arg( array( 'view' => 'calendar', 'month' => $month ), home_url( '/eventos/' ) ) ); ?>"><?php esc_html_e( 'Calendário', 'adam-comunidade' ); ?></a>
	</nav>
	<?php if ( 'calendar' === $view ) : ?>
		<section class="adam-events__calendar">
			<header>
				<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'calendar', 'month' => $previous ), home_url( '/eventos/' ) ) ); ?>">&larr; <?php esc_html_e( 'Mês anterior', 'adam-comunidade' ); ?></a>
				<h2><?php echo esc_html( wp_date( 'F Y', $month_start ) ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'calendar', 'month' => $next ), home_url( '/eventos/' ) ) ); ?>"><?php esc_html_e( 'Mês seguinte', 'adam-comunidade' ); ?> &rarr;</a>
			</header>
			<?php $month_events = array_values( array_filter( $events, static fn( object $event ): bool => str_starts_with( $event->event_date(), $month ) ) ); ?>
			<?php if ( $month_events ) : ?>
				<div class="adam-events__grid">
					<?php foreach ( $month_events as $event ) : ?>
						<article class="adam-event-card">
							<time datetime="<?php echo esc_attr( $event->event_date() ); ?>"><?php echo esc_html( wp_date( 'j M', $event->starts_at_timestamp() ) ); ?></time>
							<h3><a href="<?php echo esc_url( Api::instance()->event_url( $event ) ); ?>"><?php echo esc_html( $event->title() ); ?></a></h3>
							<?php if ( $event->location() ) : ?><p><?php echo esc_html( $event->location() ); ?></p><?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="adam-events__empty"><p><?php esc_html_e( 'Não existem eventos publicados neste mês.', 'adam-comunidade' ); ?></p><a href="<?php echo esc_url( home_url( '/eventos/' ) ); ?>"><?php esc_html_e( 'Ver todos os eventos', 'adam-comunidade' ); ?></a></div>
			<?php endif; ?>
		</section>
	<?php elseif ( $events ) : ?>
		<section class="adam-events__grid">
			<?php foreach ( $events as $event ) : ?>
				<article class="adam-event-card">
					<?php if ( $event->cover_image() ) : ?><img src="<?php echo esc_url( $event->cover_image() ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Imagem do evento %s', 'adam-comunidade' ), $event->title() ) ); ?>" loading="lazy" decoding="async"><?php endif; ?>
					<div>
						<time datetime="<?php echo esc_attr( $event->event_date() ); ?>"><?php echo esc_html( wp_date( 'j \d\e F \d\e Y', $event->starts_at_timestamp() ) ); ?></time>
						<h2><a href="<?php echo esc_url( Api::instance()->event_url( $event ) ); ?>"><?php echo esc_html( $event->title() ); ?></a></h2>
						<?php if ( $event->location() ) : ?><p><?php echo esc_html( $event->location() ); ?></p><?php endif; ?>
						<?php if ( $event->short_description() ) : ?><p><?php echo esc_html( $event->short_description() ); ?></p><?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
	<?php else : ?>
		<div class="adam-events__empty"><?php esc_html_e( 'Ainda não existem eventos publicados.', 'adam-comunidade' ); ?></div>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
