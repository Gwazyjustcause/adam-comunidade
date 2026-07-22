<?php
/**
 * Public Teams archive.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Teams\Options;
use ADAM\Comunidade\Teams\Repository;
use ADAM\Comunidade\Teams\View;

$adam_repository   = new Repository();
$adam_result       = $adam_repository->query(
	array(
		'status'   => 'published',
		'orderby'  => 'name',
		'order'    => 'ASC',
		'per_page' => 12,
	)
);
$adam_districts      = $adam_repository->distinct( 'district', 'published' );
$adam_municipalities = $adam_repository->distinct( 'municipality', 'published' );

get_header();
?>
<main class="adam-comunidade adam-teams-archive" id="main">
	<div class="adam-teams-container">
		<header class="adam-teams-header">
			<span class="adam-teams-kicker"><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span>
			<h1><?php esc_html_e( 'Equipas Associadas', 'adam-comunidade' ); ?></h1>
			<p><?php esc_html_e( 'Discover associated airsoft teams across Portugal.', 'adam-comunidade' ); ?></p>
		</header>

		<form class="adam-team-filters" id="adam-team-filters">
			<div class="adam-team-filter adam-team-filter--search">
				<label for="adam-team-search"><?php esc_html_e( 'Search', 'adam-comunidade' ); ?></label>
				<input id="adam-team-search" type="search" name="search" placeholder="<?php esc_attr_e( 'Search teams…', 'adam-comunidade' ); ?>">
			</div>
			<?php
			$adam_filter_selects = array(
				'district'      => array( __( 'District', 'adam-comunidade' ), $adam_districts ),
				'municipality'  => array( __( 'Municipality', 'adam-comunidade' ), $adam_municipalities ),
				'playing_style' => array( __( 'Playing Style', 'adam-comunidade' ), Options::playing_styles() ),
				'recruitment'   => array( __( 'Recruitment', 'adam-comunidade' ), Options::recruitment_statuses() ),
			);
			foreach ( $adam_filter_selects as $adam_filter_key => $adam_filter_data ) :
				?>
				<div class="adam-team-filter">
					<label for="adam-team-<?php echo esc_attr( $adam_filter_key ); ?>"><?php echo esc_html( $adam_filter_data[0] ); ?></label>
					<select id="adam-team-<?php echo esc_attr( $adam_filter_key ); ?>" name="<?php echo esc_attr( $adam_filter_key ); ?>">
						<option value=""><?php esc_html_e( 'All', 'adam-comunidade' ); ?></option>
						<?php foreach ( $adam_filter_data[1] as $adam_option_key => $adam_option_label ) : ?>
							<?php $adam_option_value = is_int( $adam_option_key ) ? $adam_option_label : $adam_option_key; ?>
							<option value="<?php echo esc_attr( $adam_option_value ); ?>"><?php echo esc_html( $adam_option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>
			<div class="adam-team-filter">
				<label for="adam-team-sort"><?php esc_html_e( 'Sort', 'adam-comunidade' ); ?></label>
				<select id="adam-team-sort" name="sort">
					<option value="alphabetical"><?php esc_html_e( 'Alphabetical', 'adam-comunidade' ); ?></option>
					<option value="newest"><?php esc_html_e( 'Newest', 'adam-comunidade' ); ?></option>
					<option value="oldest"><?php esc_html_e( 'Oldest', 'adam-comunidade' ); ?></option>
				</select>
			</div>
			<button class="adam-team-button" type="submit"><?php esc_html_e( 'Apply Filters', 'adam-comunidade' ); ?></button>
		</form>

		<div class="adam-team-results-summary" aria-live="polite">
			<span id="adam-team-total"><?php echo esc_html( (string) $adam_result['total'] ); ?></span>
			<?php esc_html_e( 'teams found', 'adam-comunidade' ); ?>
		</div>
		<div class="adam-team-grid" id="adam-team-results" aria-live="polite">
			<?php if ( $adam_result['items'] ) : ?>
				<?php foreach ( $adam_result['items'] as $team ) : ?>
					<?php echo View::card( $team ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped card template. ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="adam-comunidade__empty adam-teams-empty"><?php esc_html_e( 'No published teams are available yet.', 'adam-comunidade' ); ?></div>
			<?php endif; ?>
		</div>
		<div id="adam-team-pagination"><?php echo View::pagination( 1, $adam_result['pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>
</main>
<?php
get_footer();
