<?php
/**
 * Public Teams archive.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Portal;
use ADAM\Comunidade\Managed_Pages;
use ADAM\Comunidade\Placeholder_Image;
use ADAM\Comunidade\Public_Hero;
use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Repository;
use ADAM\Comunidade\Teams\View;
use ADAM\Comunidade\Teams\Hero_Carousel;

$adam_repository = new Repository();
$adam_result     = $adam_repository->query(
	array(
		'status'                 => 'published',
		'orderby'                => 'name',
		'order'                  => 'ASC',
		'per_page'               => 12,
		'prioritize_associated' => true,
	)
);
$adam_districts      = $adam_repository->distinct( 'district', 'published' );
$adam_municipalities = $adam_repository->distinct( 'municipality', 'published' );
$adam_hero_slides    = Hero_Carousel::slides( $adam_repository );
$adam_hero_settings  = Hero_Carousel::settings();
$adam_directory_title = get_the_title( Managed_Pages::id( 'teams' ) ) ?: __( 'Equipas', 'adam-comunidade' );
if ( ! $adam_hero_slides ) {
	foreach ( $adam_result['items'] as $adam_hero_team ) {
		if ( ! empty( $adam_hero_team->cover_id ) ) {
			$adam_hero_url = wp_get_attachment_image_url( (int) $adam_hero_team->cover_id, 'adam-team-cover' );
			if ( $adam_hero_url ) {
				$adam_hero_slides[] = array( 'id' => (int) $adam_hero_team->cover_id, 'url' => $adam_hero_url, 'alt' => sprintf( __( 'Equipa de airsoft %s', 'adam-comunidade' ), $adam_hero_team->name ) );
				break;
			}
		}
	}
}

get_header();
?>
<main class="adam-comunidade adam-teams-archive" id="main">
	<header class="<?php echo esc_attr( Public_Hero::root( 'adam-teams-header adam-fields-directory-hero', 'archive' ) ); ?>" data-adam-directory-carousel data-autoplay="<?php echo ! empty( $adam_hero_settings['autoplay'] ) ? 'true' : 'false'; ?>" data-interval="<?php echo esc_attr( (string) absint( $adam_hero_settings['interval'] ) ); ?>" aria-roledescription="<?php esc_attr_e( 'carrossel', 'adam-comunidade' ); ?>">
		<div class="<?php echo esc_attr( Public_Hero::element( 'media', 'adam-fields-hero-slides' ) ); ?>" aria-live="off">
			<?php if ( ! $adam_hero_slides ) : ?>
				<div class="adam-fields-hero-slide is-active" data-adam-directory-slide aria-hidden="false">
					<?php echo Placeholder_Image::cover( 'team', (string) $adam_directory_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<?php foreach ( $adam_hero_slides as $adam_hero_index => $adam_hero_slide ) : ?>
				<div class="adam-fields-hero-slide<?php echo 0 === $adam_hero_index ? ' is-active' : ''; ?>" data-adam-directory-slide aria-hidden="<?php echo 0 === $adam_hero_index ? 'false' : 'true'; ?>">
					<img src="<?php echo esc_url( $adam_hero_slide['url'] ); ?>" alt="<?php echo esc_attr( $adam_hero_slide['alt'] ); ?>" <?php echo 0 === $adam_hero_index ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="<?php echo esc_attr( Public_Hero::element( 'content', 'adam-teams-container' ) ); ?>">
			<span class="<?php echo esc_attr( Public_Hero::element( 'kicker', 'adam-teams-kicker' ) ); ?>"><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span>
			<h1 class="<?php echo esc_attr( Public_Hero::element( 'title' ) ); ?>"><?php echo esc_html( $adam_directory_title ); ?></h1>
			<p class="<?php echo esc_attr( Public_Hero::element( 'subtitle' ) ); ?>"><?php esc_html_e( 'Descubra as equipas de airsoft do Centro de Portugal.', 'adam-comunidade' ); ?></p>
		</div>
		<?php if ( count( $adam_hero_slides ) > 1 ) : ?>
			<div class="adam-fields-hero-controls">
				<button type="button" data-adam-directory-prev aria-label="<?php esc_attr_e( 'Imagem anterior', 'adam-comunidade' ); ?>">&#8592;</button>
				<div class="adam-fields-hero-pagination" aria-label="<?php esc_attr_e( 'Escolher imagem', 'adam-comunidade' ); ?>">
					<?php foreach ( $adam_hero_slides as $adam_hero_index => $adam_hero_slide ) : ?>
						<button type="button" data-adam-directory-indicator="<?php echo esc_attr( (string) $adam_hero_index ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Mostrar imagem %d', 'adam-comunidade' ), $adam_hero_index + 1 ) ); ?>" aria-current="<?php echo 0 === $adam_hero_index ? 'true' : 'false'; ?>"></button>
					<?php endforeach; ?>
				</div>
				<button type="button" data-adam-directory-next aria-label="<?php esc_attr_e( 'Imagem seguinte', 'adam-comunidade' ); ?>">&#8594;</button>
			</div>
		<?php endif; ?>
	</header>

	<div class="adam-teams-container">
		<form class="adam-team-filters adam-directory-filters" id="adam-team-filters">
			<div class="adam-team-filter adam-team-filter--search">
				<label for="adam-team-search"><?php esc_html_e( 'Pesquisar', 'adam-comunidade' ); ?></label>
				<input id="adam-team-search" type="search" name="search" placeholder="<?php esc_attr_e( 'Pesquisar equipas…', 'adam-comunidade' ); ?>">
			</div>
			<?php
			$adam_filter_selects = array(
				'district'      => array( __( 'Distrito', 'adam-comunidade' ), $adam_districts ),
				'municipality'  => array( __( 'Concelho', 'adam-comunidade' ), $adam_municipalities ),
				'playing_style' => array( __( 'Estilo de jogo', 'adam-comunidade' ), Options::playing_styles() ),
				'recruitment'   => array( __( 'Recrutamento', 'adam-comunidade' ), Options::recruitment_statuses() ),
			);
			foreach ( $adam_filter_selects as $adam_filter_key => $adam_filter_data ) :
				?>
				<div class="adam-team-filter">
					<label for="adam-team-<?php echo esc_attr( $adam_filter_key ); ?>"><?php echo esc_html( $adam_filter_data[0] ); ?></label>
					<select id="adam-team-<?php echo esc_attr( $adam_filter_key ); ?>" name="<?php echo esc_attr( $adam_filter_key ); ?>">
						<option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option>
						<?php foreach ( $adam_filter_data[1] as $adam_option_key => $adam_option_label ) : ?>
							<?php $adam_option_value = is_int( $adam_option_key ) ? $adam_option_label : $adam_option_key; ?>
							<option value="<?php echo esc_attr( $adam_option_value ); ?>"><?php echo esc_html( $adam_option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>
			<div class="adam-team-filter">
				<label for="adam-team-association"><?php esc_html_e( 'Associação', 'adam-comunidade' ); ?></label>
				<select id="adam-team-association" name="association">
					<option value="all"><?php esc_html_e( 'Todas as Equipas', 'adam-comunidade' ); ?></option>
					<option value="associated"><?php esc_html_e( 'Apenas Equipas Associadas', 'adam-comunidade' ); ?></option>
				</select>
			</div>
			<div class="adam-team-filter">
				<label for="adam-team-sort"><?php esc_html_e( 'Ordenar', 'adam-comunidade' ); ?></label>
				<select id="adam-team-sort" name="sort">
					<option value="alphabetical"><?php esc_html_e( 'Ordem alfabética', 'adam-comunidade' ); ?></option>
					<option value="newest"><?php esc_html_e( 'Mais recentes', 'adam-comunidade' ); ?></option>
					<option value="oldest"><?php esc_html_e( 'Mais antigas', 'adam-comunidade' ); ?></option>
				</select>
			</div>
			<button class="adam-team-button adam-directory-button" type="submit"><?php esc_html_e( 'Aplicar filtros', 'adam-comunidade' ); ?></button>
		</form>

		<div class="adam-team-results-summary" aria-live="polite">
			<span id="adam-team-total"><?php echo esc_html( (string) $adam_result['total'] ); ?></span>
			<?php esc_html_e( 'equipas encontradas', 'adam-comunidade' ); ?>
		</div>
		<div class="adam-team-grid adam-directory-grid" id="adam-team-results" aria-live="polite">
			<?php if ( $adam_result['items'] ) : ?>
				<?php foreach ( $adam_result['items'] as $team ) : ?>
					<?php echo View::card( $team ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped card template. ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="adam-comunidade__empty adam-teams-empty adam-directory-empty">
					<h2><?php esc_html_e( 'Ainda não existem equipas publicadas.', 'adam-comunidade' ); ?></h2>
					<p><?php esc_html_e( 'Conhece uma equipa do Centro de Portugal? Ajude-nos a construir este diretório.', 'adam-comunidade' ); ?></p>
					<a class="adam-team-button adam-directory-button" href="<?php echo esc_url( Portal::submission_url( 'team' ) ); ?>"><?php esc_html_e( 'Submeter uma Equipa', 'adam-comunidade' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<div class="adam-directory-pagination" id="adam-team-pagination"><?php echo View::pagination( 1, $adam_result['pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</main>
<?php
get_footer();
