<?php
/**
 * Public Events archive and calendar.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Events\Api;
use ADAM\Comunidade\Public_Hero;

$view = 'calendar' === sanitize_key( (string) ( $_GET['view'] ?? '' ) ) ? 'calendar' : 'list';
$month = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_GET['month'] ?? '' ) ) ? (string) $_GET['month'] : wp_date( 'Y-m' );
$events = array_values( array_filter( Api::instance()->get_events(), static fn( object $event ): bool => $event->is_visible() ) );
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
		<a class="<?php echo 'list' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/eventos/' ) ); ?>"><?php esc_html_e( 'Lista', 'adam-comunidade' ); ?></a>
		<a class="<?php echo 'calendar' === $view ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'view' => 'calendar', 'month' => $month ), home_url( '/eventos/' ) ) ); ?>"><?php esc_html_e( 'Calendário', 'adam-comunidade' ); ?></a>
	</nav>
	<?php if ( 'calendar' === $view ) : ?>
		<section class="adam-events__calendar">
			<header>
				<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'calendar', 'month' => $previous ), home_url( '/eventos/' ) ) ); ?>">&larr; <?php esc_html_e( 'Mês anterior', 'adam-comunidade' ); ?></a>
				<h2><?php echo esc_html( wp_date( 'F Y', $month_start ) ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'calendar', 'month' => $next ), home_url( '/eventos/' ) ) ); ?>"><?php esc_html_e( 'Mês seguinte', 'adam-comunidade' ); ?> &rarr;</a>
			</header>
			<div class="adam-events__grid">
				<?php foreach ( $events as $event ) : ?>
					<?php if ( str_starts_with( $event->event_date(), $month ) ) : ?>
						<article class="adam-event-card">
							<time datetime="<?php echo esc_attr( $event->event_date() ); ?>"><?php echo esc_html( wp_date( 'j M', $event->starts_at_timestamp() ) ); ?></time>
							<h3><a href="<?php echo esc_url( Api::instance()->event_url( $event ) ); ?>"><?php echo esc_html( $event->title() ); ?></a></h3>
							<?php if ( $event->location() ) : ?><p><?php echo esc_html( $event->location() ); ?></p><?php endif; ?>
						</article>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php elseif ( $events ) : ?>
		<section class="adam-events__grid">
			<?php foreach ( $events as $event ) : ?>
				<article class="adam-event-card">
					<?php if ( $event->cover_image() ) : ?><img src="<?php echo esc_url( $event->cover_image() ); ?>" alt=""><?php endif; ?>
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
